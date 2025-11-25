<?php
// calculate_rates.php
// FIXED: Deductions, SIL Pay, and Extra Pay logic corrected.

require_once __DIR__ . '/dbconfig.php';

// --- CONSTANTS ---
define('LATE_PENALTY', 150.0);
define('NIGHT_DIFF_RATE', 52.0);
define('CASHIER_BONUS_PER_8HRS', 40.0);
define('ALLOWANCE_DAILY_AMT', 20.0);
define('ALLOWANCE_THRESHOLD', 520.0);
define('REGULAR_HOURS', 8);
define('UNIFORM_COST', 106.0);

/**
 * Helper: Check deduction weeks
 */
function isDeductionWeek($start, $end, $dayStart, $dayEnd) {
    $s = strtotime($start);
    $e = strtotime($end);
    
    // Construct target dates for the month of the start date
    $year = date('Y', $s);
    $month = date('m', $s);
    
    $targetStart = strtotime("$year-$month-$dayStart");
    $targetEnd = strtotime("$year-$month-$dayEnd");
    
    return max($s, $targetStart) <= min($e, $targetEnd);
}

function calculateRates($pdo, $start, $end) {
    $start = date('Y-m-d', strtotime($start));
    $end = date('Y-m-d', strtotime($end));

    // 1. DETERMINE DEDUCTION SCHEDULE
    $applySSS = isDeductionWeek($start, $end, 10, 16);
    $applyGovt = isDeductionWeek($start, $end, 17, 23); // PHIC & HDMF
    $applyLoan = isDeductionWeek($start, $end, 24, 30); // Govt Loan

    // 2. FETCH REFERENCE DATA
    $empStmt = $pdo->query("SELECT * FROM employees");
    $employeesDB = [];
    while ($row = $empStmt->fetch(PDO::FETCH_ASSOC)) {
        $employeesDB[trim($row['name'])] = $row;
    }

    // Holidays
    $holStmt = $pdo->prepare("SELECT * FROM holidays WHERE date BETWEEN ? AND ?");
    $holStmt->execute([date('Y-m-01', strtotime($start)), date('Y-m-t', strtotime($end))]);
    $holidays = [];
    $special_holidays = []; 
    while ($row = $holStmt->fetch(PDO::FETCH_ASSOC)) {
        $rate = floatval($row['rate_multiplier']);
        $type = ($rate >= 1.0) ? 'Regular' : 'Special';
        $holidays[$row['date']] = ['type' => $type, 'rate' => $rate];
        
        if ($type === 'Special') {
            $special_holidays[] = $row['date'];
        }
    }

    // 3. FETCH LOGS
    $stmt = $pdo->prepare("
        SELECT * FROM payrolldata 
        WHERE Date BETWEEN :s AND :e 
        ORDER BY Name, Date, TimeIn
    ");
    $stmt->execute([':s' => $start, ':e' => $end]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($logs as $r) {
        $name = trim($r['Name']);
        if (!isset($grouped[$name])) {
            $grouped[$name] = ['rows' => [], 'bu' => $r['BusinessUnit']];
        }
        $grouped[$name]['rows'][] = $r;
    }

    $resultEmployees = [];

    foreach ($grouped as $name => $data) {
        $rows = $data['rows'];
        $empData = $employeesDB[$name] ?? null;
        
        $dailyRate = floatval($empData['daily_rate'] ?? 520.0);
        $hourlyRate = $dailyRate / REGULAR_HOURS; 

        // Initialize Totals
        $totals = [
            'regular_pay' => 0,
            'overtime_pay' => 0,
            'night_diff' => 0,
            'cashier_bonus' => 0,
            'allowance' => 0,
            'holiday_pay' => 0,
            'sil_pay' => 0,          // Added for SIL
            'extra_pay' => 0,        // Added for numeric extra earnings
            'late_deduction' => 0,
            'misload_deduction' => 0,// Added for Misload/Shortage
            'uniform_deduction' => 0,// Added for Uniform
            'sss' => 0, 'phic' => 0, 'hdmf' => 0, 'loan' => 0
        ];

        // Trackers
        $total_shifts = 0;
        $overtime_hours = 0;
        $cashier_hours = 0;
        $night_diff_count = 0;
        $late_count = 0;
        
        $worked_dates = []; 

        foreach ($rows as $r) {
            $date = $r['Date'];
            $remarks = strtolower($r['Remarks'] ?? '');
            $role = strtolower($r['Role'] ?? '');
            $hours = floatval($r['Hours'] ?? 0);
            $shiftNo = intval($r['ShiftNumber'] ?? 0);
            $extraCol = $r['Extra'] ?? ''; // Maps to Short/Misload/Bonus/SIL

            // A. Base Pay (Count Records)
            if (strpos($remarks, 'onduty') !== false || strpos($remarks, 'late') !== false) {
                $total_shifts++;
                $worked_dates[$date] = true;
            }

            // B. Overtime
            if (strpos($remarks, 'overtime') !== false) {
                $overtime_hours += $hours;
                $worked_dates[$date] = true; 
            }

            // C. Night Differential
            if ($shiftNo == 3) {
                $night_diff_count++;
            }

            // D. Cashier Bonus
            if (strpos($role, 'cashier') !== false) {
                $cashier_hours += $hours;
            }

            // E. Late Deduction
            if (strpos($remarks, 'late') !== false) {
                $late_count++;
            }

            // F. Extras & Deductions (FIXED LOGIC)
            if (is_numeric($extraCol)) {
                // If the column has a number, treat it as extra earnings
                 $totals['extra_pay'] += floatval($extraCol);
            } else {
                // Check for 'Uniform' text
                if (stripos($extraCol, 'uniform') !== false) {
                    $totals['uniform_deduction'] += UNIFORM_COST; 
                }
                // Check for 'SIL' text (Service Incentive Leave)
                if (stripos($extraCol, 'sil') !== false) {
                    $totals['sil_pay'] += $dailyRate;
                }
            }
            
            // Misload/Shortage comes from the 'Deductions' column
            // We take absolute value to ensure positive deduction amount
            $totals['misload_deduction'] += abs(floatval($r['Deductions'] ?? 0));
        }

        // --- CALCULATIONS ---

        // 1. Regular Pay
        $totals['regular_pay'] = $total_shifts * $dailyRate;

        // 2. Overtime Pay
        $totals['overtime_pay'] = $overtime_hours * $hourlyRate;

        // 3. Holiday Pay
        $totals['holiday_pay'] = $dailyRate; // Base 1 Day Pay

        $worked_special_holiday = false;
        foreach ($worked_dates as $wDate => $val) {
            if (in_array($wDate, $special_holidays)) {
                $worked_special_holiday = true;
                break; 
            }
        }

        if ($worked_special_holiday) {
            $totals['holiday_pay'] += ($dailyRate * 0.30);
        }
        
        // *Correction for Arthur Curry (Overtime on Special Holiday)*
        foreach ($rows as $r) {
            if (strpos(strtolower($r['Remarks'] ?? ''), 'overtime') !== false) {
                if (in_array($r['Date'], $special_holidays)) {
                    $otPayForRecord = floatval($r['Hours']) * $hourlyRate;
                    $totals['holiday_pay'] += ($otPayForRecord * 0.30);
                }
            }
        }

        // 4. Allowance
        if ($dailyRate > ALLOWANCE_THRESHOLD) {
            $totals['allowance'] += $total_shifts * ALLOWANCE_DAILY_AMT;
        }
        $cashier_bonus_val = floor($cashier_hours / 8) * CASHIER_BONUS_PER_8HRS;
        $totals['allowance'] += $cashier_bonus_val;

        // 5. Night Diff
        $totals['night_diff'] = $night_diff_count * NIGHT_DIFF_RATE;

        // 6. Gross (Added SIL and Extra Pay)
        $gross = $totals['regular_pay'] + $totals['overtime_pay'] + $totals['holiday_pay'] + 
                 $totals['allowance'] + $totals['night_diff'] + $totals['sil_pay'] + $totals['extra_pay'];

        // 7. Deductions
        $totals['late_deduction'] = $late_count * LATE_PENALTY;

        if ($empData) {
            $totals['sss'] = $applySSS ? floatval($empData['sss']) : 0;
            $totals['phic'] = $applyGovt ? floatval($empData['phic']) : 0;
            $totals['hdmf'] = $applyGovt ? floatval($empData['hdmf']) : 0;
            $totals['loan'] = $applyLoan ? floatval($empData['govt_loan']) : 0;
        }

        // Calculate Total Deductions (Added Misload and Uniform)
        $totalDeductions = $totals['late_deduction'] + 
                           $totals['misload_deduction'] + 
                           $totals['uniform_deduction'] +
                           $totals['sss'] + $totals['phic'] + $totals['hdmf'] + $totals['loan'];
        
        $net = $gross - $totalDeductions;

        // Output
        $resultEmployees[] = [
            'empkey' => $name,
            'name' => $name,
            'business_unit' => $data['bu'],
            'totals' => [
                'regular' => round($totals['regular_pay'], 2),
                'overtime' => round($totals['overtime_pay'], 2),
                'night' => round($totals['night_diff'], 2),
                'bonus' => round($cashier_bonus_val, 2),
                'allowance' => round($totals['allowance'], 2),
                'holiday' => round($totals['holiday_pay'], 2),
                'sil' => round($totals['sil_pay'], 2),     // New Output
                'extra' => round($totals['extra_pay'], 2),
                'gross' => round($gross, 2),
                
                'late' => round($totals['late_deduction'], 2),
                'sss' => round($totals['sss'], 2),
                'phic' => round($totals['phic'], 2),
                'hdmf' => round($totals['hdmf'], 2),
                'govt' => round($totals['phic'] + $totals['hdmf'], 2),
                'loan' => round($totals['loan'], 2),
                
                'misload' => round($totals['misload_deduction'], 2), // New Output
                'uniform' => round($totals['uniform_deduction'], 2), // New Output
                'db_deductions' => round($totals['misload_deduction'] + $totals['uniform_deduction'], 2), // Combined for legacy support
                
                'total_deductions' => round($totalDeductions, 2),
                'net' => round($net, 2)
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

if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json; charset=utf-8');
    $start = $_GET['start'] ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-t');
    echo json_encode(calculateRates($pdo, $start, $end), JSON_PRETTY_PRINT);
}
?>