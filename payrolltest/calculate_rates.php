<?php
// calculate_rates.php - FULLY DATABASE-DRIVEN VERSION
// Calculates from database first, uses Excel ONLY for government deductions

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Load ONLY government deductions from Excel Summary
 */
function loadGovtDeductions($path) {
    $map = [];
    if (!file_exists($path)) return $map;
    
    try {
        $ss = IOFactory::load($path);
        $sheet = $ss->getSheetByName('Summary');
        if (!$sheet) return $map;
        
        $rows = $sheet->toArray(null, true, true, true);
        
        // Find header row
        $headerRow = null;
        foreach ($rows as $idx => $row) {
            foreach ($row as $cell) {
                if (stripos($cell, 'Name') !== false) {
                    $headerRow = $idx;
                    break 2;
                }
            }
        }
        
        if ($headerRow === null) return $map;
        
        $header = $rows[$headerRow];
        
        // Map ONLY government deduction columns
        $colmap = [];
        foreach ($header as $col => $val) {
            $h = strtolower(trim($val ?? ''));
            if (strpos($h, 'name') !== false && !isset($colmap['name'])) $colmap['name'] = $col;
            if ($h === 'sss') $colmap['sss'] = $col;
            if ($h === 'phic' || $h === 'philhealth') $colmap['phic'] = $col;
            if ($h === 'hdmf' || $h === 'pagibig' || strpos($h, 'pag-ibig') !== false) $colmap['hdmf'] = $col;
            if (strpos($h, 'loan') !== false) $colmap['loan'] = $col;
        }
        
        // Parse data rows - ONLY govt deductions
        for ($i = $headerRow + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (!isset($colmap['name'])) continue;
            
            $name = isset($colmap['name']) ? trim($row[$colmap['name']] ?? '') : '';
            if ($name === '' || is_numeric($name)) continue;
            
            $map[$name] = [
                'sss' => isset($colmap['sss']) ? floatval($row[$colmap['sss']] ?? 0) : 0,
                'phic' => isset($colmap['phic']) ? floatval($row[$colmap['phic']] ?? 0) : 0,
                'hdmf' => isset($colmap['hdmf']) ? floatval($row[$colmap['hdmf']] ?? 0) : 0,
                'loan' => isset($colmap['loan']) ? floatval($row[$colmap['loan']] ?? 0) : 0
            ];
        }
        
    } catch (Exception $e) {
        error_log("Failed to load govt deductions: " . $e->getMessage());
    }
    
    return $map;
}

// Constants
define('LATE_DEDUCTION', 150.0);
define('DEFAULT_DAILY_RATE', 520);
define('DEFAULT_HOURLY_RATE', 65);
define('DEFAULT_ALLOWANCE_PER_DAY', 20);
define('NIGHT_DIFF_PER_SHIFT', 52);
define('CASHIER_BONUS_PER_SHIFT', 40);

function calculateRates($pdo, $start, $end) {
    // Try to load government deductions from Excel (ONLY govt deductions)
    $defaultPaths = [
        __DIR__ . '/Payroll Testing Data (1).xlsx',
        __DIR__ . '/Payroll Testing Data 1.xlsx',
        __DIR__ . '/PayrollData.xlsx',
        __DIR__ . '/Summary.xlsx'
    ];
    
    $govtDeductions = [];
    foreach ($defaultPaths as $p) {
        if (file_exists($p)) {
            $govtDeductions = loadGovtDeductions($p);
            if (!empty($govtDeductions)) break;
        }
    }

    // Query payroll records from database
    $stmt = $pdo->prepare("
        SELECT * FROM payrolldata 
        WHERE Date BETWEEN :s AND :e 
        AND Date IS NOT NULL 
        ORDER BY Name, Date, ID
    ");
    $stmt->execute([':s' => $start, ':e' => $end]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by employee
    $grouped = [];
    foreach ($rows as $r) {
        $name = $r['Name'];
        if (!isset($grouped[$name])) {
            $grouped[$name] = [
                'name' => $name,
                'business_unit' => $r['BusinessUnit'] ?? '',
                'rows' => []
            ];
        }
        $grouped[$name]['rows'][] = $r;
    }

    $resultEmployees = [];

    foreach ($grouped as $name => $data) {
        $rows = $data['rows'];
        $businessUnit = $data['business_unit'];
        
        // CALCULATE EVERYTHING FROM DATABASE
        $uniqueDates = [];
        $total_hours = 0;
        $ot_hours = 0;
        $night_shifts = 0;
        $cashier_shifts = 0;
        $late_count = 0;
        $db_deductions_total = 0;
        $db_extra_total = 0;
        $holiday_pay_total = 0;
        
        foreach ($rows as $r) {
            $uniqueDates[$r['Date']] = true;
            
            $hours = floatval($r['Hours'] ?? 0);
            $total_hours += $hours;
            
            // Count overtime hours
            if (stripos($r['Remarks'] ?? '', 'overtime') !== false) {
                $ot_hours += $hours;
            }
            
            // Count night shifts
            $timeIn = $r['TimeIn'] ?? null;
            if ($timeIn) {
                try {
                    $dt = new DateTime($r['Date'] . ' ' . $timeIn);
                    $hour = intval($dt->format('H'));
                    if ($hour >= 22 || $hour < 6) {
                        $night_shifts++;
                    }
                } catch (Exception $e) {}
            }
            
            // Count cashier shifts
            if (stripos($r['Role'] ?? '', 'cashier') !== false) {
                $cashier_shifts++;
            }
            
            // Count late incidents
            if (stripos($r['Remarks'] ?? '', 'late') !== false) {
                $late_count++;
            }
            
            // IMPORTANT: Database deductions are stored as NEGATIVE values
            // So if Deductions = -22, we want to SUBTRACT 22 from net
            // Since it's already negative, we ADD it (adding -22 = subtracting 22)
            $deductionValue = floatval($r['Deductions'] ?? 0);
            $db_deductions_total += abs($deductionValue); // Take absolute value to make it positive for subtraction
            
            // Extra/bonuses from database
            $db_extra_total += floatval($r['Extra'] ?? 0);
        }
        
        $days_worked = count($uniqueDates);
        
        // Calculate pay components
        $regular_pay = $days_worked * DEFAULT_DAILY_RATE;
        $overtime_pay = $ot_hours * DEFAULT_HOURLY_RATE;
        $night_diff = $night_shifts * NIGHT_DIFF_PER_SHIFT;
        $cashier_bonus = $cashier_shifts * CASHIER_BONUS_PER_SHIFT;
        $allowance = $days_worked * DEFAULT_ALLOWANCE_PER_DAY;
        
        // Calculate gross pay
        $gross = $regular_pay + $overtime_pay + $night_diff + $cashier_bonus + $allowance + $db_extra_total + $holiday_pay_total;
        
        // Calculate deductions
        $late_deduction = $late_count * LATE_DEDUCTION;
        
        // Get government deductions from Excel (if available)
        $govtData = $govtDeductions[$name] ?? null;
        $sss = $govtData['sss'] ?? 0;
        $phic = $govtData['phic'] ?? 0;
        $hdmf = $govtData['hdmf'] ?? 0;
        $loan = $govtData['loan'] ?? 0;
        $govt_total = $sss + $phic + $hdmf;
        
        // FIXED: Total deductions now properly includes database deductions
        $total_deductions = $late_deduction + $govt_total + $loan + $db_deductions_total;
        $net_pay = $gross - $total_deductions;
        
        // Build per-day breakdown
        $per_day = [];
        foreach ($rows as $r) {
            $per_day[] = [
                'date' => $r['Date'],
                'role' => $r['Role'] ?? '',
                'hours' => floatval($r['Hours'] ?? 0),
                'remarks' => $r['Remarks'] ?? '',
                'holiday' => null,
                'holiday_bonus' => 0,
                'regular_pay' => 0,
                'ot_pay' => 0,
                'cashier_bonus' => 0,
                'night_pay' => 0,
                'allowance' => 0,
                'late' => stripos($r['Remarks'] ?? '', 'late') !== false ? LATE_DEDUCTION : 0,
                'extra' => floatval($r['Extra'] ?? 0),
                'deductions' => abs(floatval($r['Deductions'] ?? 0)) // Show as positive for display
            ];
        }
        
        $resultEmployees[] = [
            'empkey' => $name,
            'name' => $name,
            'business_unit' => $businessUnit,
            'per_day' => $per_day,
            'totals' => [
                'regular' => round($regular_pay, 2),
                'overtime' => round($overtime_pay, 2),
                'night' => round($night_diff, 2),
                'bonus' => round($cashier_bonus, 2),
                'allowance' => round($allowance, 2),
                'holiday' => round($holiday_pay_total, 2),
                'extra' => round($db_extra_total, 2),
                'gross' => round($gross, 2),
                'late' => round($late_deduction, 2),
                'sss' => round($sss, 2),
                'phic' => round($phic, 2),
                'hdmf' => round($hdmf, 2),
                'govt' => round($govt_total, 2),
                'loan' => round($loan, 2),
                'db_deductions' => round($db_deductions_total, 2),
                'total_deductions' => round($total_deductions, 2),
                'net' => round($net_pay, 2)
            ]
        ];
    }

    return [
        'start' => $start,
        'end' => $end,
        'count' => count($resultEmployees),
        'employees' => $resultEmployees
    ];
}

// Standalone execution (returns JSON)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json; charset=utf-8');
    $start = $_GET['start'] ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-t');
    $data = calculateRates($pdo, $start, $end);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}