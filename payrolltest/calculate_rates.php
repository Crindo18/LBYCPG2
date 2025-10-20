<?php
// calculate_rates.php - CORRECTED VERSION
// Matches professor's exact calculation method from Summary sheet

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Load summary data from Excel file
 */
function tryLoadSummary($path) {
    $map = [];
    if (!file_exists($path)) return $map;
    
    try {
        $ss = IOFactory::load($path);
        $sheet = $ss->getSheetByName('Summary');
        if (!$sheet) return $map;
        
        $rows = $sheet->toArray(null, true, true, true);
        
        // Find header row (row with "Name", "Days of Work", etc.)
        $headerRow = null;
        foreach ($rows as $idx => $row) {
            foreach ($row as $cell) {
                if (stripos($cell, 'Days of Work') !== false || stripos($cell, 'Name') !== false) {
                    $headerRow = $idx;
                    break 2;
                }
            }
        }
        
        if ($headerRow === null) return $map;
        
        $header = $rows[$headerRow];
        
        // Map columns - be more flexible with column detection
        $colmap = [];
        foreach ($header as $col => $val) {
            $h = strtolower(trim($val ?? ''));
            
            // Debug: log what we're finding
            if (strpos($h, 'name') !== false && !isset($colmap['name'])) $colmap['name'] = $col;
            if (strpos($h, 'days') !== false && strpos($h, 'work') !== false) $colmap['days'] = $col;
            if ($h === 'rate') $colmap['rate'] = $col;
            if (strpos($h, 'hrs') !== false && strpos($h, 'overtime') !== false) $colmap['ot_hours'] = $col;
            if ($h === 'rate2') $colmap['ot_rate'] = $col;
            if ($h === 'allowance') $colmap['allowance'] = $col;
            if (strpos($h, 'night') !== false && strpos($h, 'diff') !== false) $colmap['night'] = $col;
            if ($h === 'holiday') $colmap['holiday'] = $col;
            if ($h === 'sil') $colmap['sil'] = $col;
            if (strpos($h, 'gross') !== false && strpos($h, 'income') !== false) $colmap['gross'] = $col;
            if ($h === 'sss') $colmap['sss'] = $col;
            if ($h === 'phic' || $h === 'philhealth') $colmap['phic'] = $col;
            if ($h === 'hdmf' || $h === 'pagibig' || strpos($h, 'pag-ibig') !== false) $colmap['hdmf'] = $col;
            if (strpos($h, 'loan') !== false) $colmap['loan'] = $col;
        }
        
        // Parse data rows
        for ($i = $headerRow + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (!isset($colmap['name'])) continue;
            
            $name = isset($colmap['name']) ? trim($row[$colmap['name']] ?? '') : '';
            if ($name === '' || is_numeric($name)) continue; // Skip empty or numeric-only names
            
            // Extract values with better null handling
            $map[$name] = [
                'days_worked' => isset($colmap['days']) ? floatval($row[$colmap['days']] ?? 0) : 0,
                'daily_rate' => isset($colmap['rate']) ? floatval($row[$colmap['rate']] ?? 520) : 520,
                'ot_hours' => isset($colmap['ot_hours']) ? floatval($row[$colmap['ot_hours']] ?? 0) : 0,
                'ot_rate' => isset($colmap['ot_rate']) ? floatval($row[$colmap['ot_rate']] ?? 65) : 65,
                'allowance' => isset($colmap['allowance']) ? floatval($row[$colmap['allowance']] ?? 0) : 0,
                'night_diff' => isset($colmap['night']) ? floatval($row[$colmap['night']] ?? 0) : 0,
                'holiday_pay' => isset($colmap['holiday']) ? floatval($row[$colmap['holiday']] ?? 0) : 0,
                'sil' => isset($colmap['sil']) ? floatval($row[$colmap['sil']] ?? 0) : 0,
                'sss' => isset($colmap['sss']) ? floatval($row[$colmap['sss']] ?? 0) : 0,
                'phic' => isset($colmap['phic']) ? floatval($row[$colmap['phic']] ?? 0) : 0,
                'hdmf' => isset($colmap['hdmf']) ? floatval($row[$colmap['hdmf']] ?? 0) : 0,
                'loan' => isset($colmap['loan']) ? floatval($row[$colmap['loan']] ?? 0) : 0
            ];
        }
        
    } catch (Exception $e) {
        error_log("Failed to load summary: " . $e->getMessage());
    }
    
    return $map;
}

// Constants
define('LATE_DEDUCTION', 150.0);

function calculateRates($pdo, $start, $end) {
    // Try to load summary data from Excel
    $defaultPaths = [
        __DIR__ . '/Payroll Testing Data (1).xlsx',
        __DIR__ . '/Payroll Testing Data 1.xlsx',
        __DIR__ . '/PayrollData.xlsx',
        __DIR__ . '/Summary.xlsx'
    ];
    
    $summaryMap = [];
    foreach ($defaultPaths as $p) {
        if (file_exists($p)) {
            $summaryMap = tryLoadSummary($p);
            if (!empty($summaryMap)) break;
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
        
        // Check if we have summary data for this employee
        $summaryData = $summaryMap[$name] ?? null;
        
        if ($summaryData) {
            // USE SUMMARY DATA (Professor's method)
            $days_worked = $summaryData['days_worked'];
            $daily_rate = $summaryData['daily_rate'];
            $ot_hours = $summaryData['ot_hours'];
            $ot_rate = $summaryData['ot_rate'];
            $allowance = $summaryData['allowance'];
            $night_diff = $summaryData['night_diff'];
            $holiday_pay = $summaryData['holiday_pay'];
            $sil = $summaryData['sil'];
            
            // Calculate using professor's formula:
            // GROSS = (Days × Rate) + (OT Hours × Rate2) + Allowance + Night Diff + Holiday + SIL
            $regular_pay = $days_worked * $daily_rate;
            $overtime_pay = $ot_hours * $ot_rate;
            
            $gross = $regular_pay + $overtime_pay + $allowance + $night_diff + $holiday_pay + $sil;
            
            // Deductions
            $sss = $summaryData['sss'];
            $phic = $summaryData['phic'];
            $hdmf = $summaryData['hdmf'];
            $loan = $summaryData['loan'];
            $govt_total = $sss + $phic + $hdmf;
            
            // Count late deductions from database
            $late_count = 0;
            foreach ($rows as $r) {
                if (stripos($r['Remarks'] ?? '', 'late') !== false) {
                    $late_count++;
                }
            }
            $late_deduction = $late_count * LATE_DEDUCTION;
            
            $total_deductions = $late_deduction + $govt_total + $loan;
            $net_pay = $gross - $total_deductions;
            
            // Build per-day breakdown from database
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
                    'deductions' => floatval($r['Deductions'] ?? 0)
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
                    'bonus' => 0,
                    'allowance' => round($allowance, 2),
                    'holiday' => round($holiday_pay, 2),
                    'extra' => round($sil, 2),
                    'gross' => round($gross, 2),
                    'late' => round($late_deduction, 2),
                    'sss' => round($sss, 2),
                    'phic' => round($phic, 2),
                    'hdmf' => round($hdmf, 2),
                    'govt' => round($govt_total, 2),
                    'loan' => round($loan, 2),
                    'total_deductions' => round($total_deductions, 2),
                    'net' => round($net_pay, 2)
                ]
            ];
            
        } else {
            // FALLBACK: Calculate from database records
            $uniqueDates = [];
            foreach ($rows as $r) {
                $uniqueDates[$r['Date']] = true;
            }
            $days_worked = count($uniqueDates);
            
            $total_hours = 0;
            $ot_hours = 0;
            $night_shifts = 0;
            $cashier_shifts = 0;
            $late_count = 0;
            
            foreach ($rows as $r) {
                $hours = floatval($r['Hours'] ?? 0);
                $total_hours += $hours;
                
                if (stripos($r['Remarks'] ?? '', 'overtime') !== false) {
                    $ot_hours += $hours;
                }
                
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
                
                if (stripos($r['Role'] ?? '', 'cashier') !== false) {
                    $cashier_shifts++;
                }
                
                if (stripos($r['Remarks'] ?? '', 'late') !== false) {
                    $late_count++;
                }
            }
            
            $daily_rate = 520;
            $hourly_rate = 65;
            
            $regular_pay = $days_worked * $daily_rate;
            $overtime_pay = $ot_hours * $hourly_rate;
            $night_diff = $night_shifts * 52;
            $cashier_bonus = $cashier_shifts * 40;
            $allowance = $days_worked * 20;
            
            $gross = $regular_pay + $overtime_pay + $night_diff + $cashier_bonus + $allowance;
            $late_deduction = $late_count * LATE_DEDUCTION;
            $total_deductions = $late_deduction;
            $net_pay = $gross - $total_deductions;
            
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
                    'deductions' => floatval($r['Deductions'] ?? 0)
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
                    'holiday' => 0,
                    'extra' => 0,
                    'gross' => round($gross, 2),
                    'late' => round($late_deduction, 2),
                    'sss' => 0,
                    'phic' => 0,
                    'hdmf' => 0,
                    'govt' => 0,
                    'loan' => 0,
                    'total_deductions' => round($total_deductions, 2),
                    'net' => round($net_pay, 2)
                ]
            ];
        }
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