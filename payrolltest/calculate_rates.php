<?php
// calculate_rates.php
// REVISED: Correct Holiday Logic (Reg=100%, Special=30% on ALL hours including OT)

require_once __DIR__ . '/dbconfig.php';

// --- CONSTANTS ---
define('LATE_PENALTY', 150.0);
define('NIGHT_DIFF_RATE', 52.0);
define('CASHIER_BONUS_PER_8HRS', 40.0);
define('ALLOWANCE_DAILY_AMT', 20.0);
define('ALLOWANCE_THRESHOLD', 520.0);
define('REGULAR_HOURS', 8);

/**
 * Helper: Check deduction weeks
 * Week 1: 1-7, Week 2: 8-14, Week 3: 15-21, Week 4: 22-End
 */
function isDeductionWeek($start, $end, $dayStart, $dayEnd) {
    $s = strtotime($start);
    $e = strtotime($end);
    $year = date('Y', $s);
    $month = date('m', $s);
    
    $targetStart = strtotime("$year-$month-$dayStart");
    $targetEnd = strtotime("$year-$month-$dayEnd");
    
    return max($s, $targetStart) <= min($e, $targetEnd);
}

function calculateRates($pdo, $start, $end) {
    $start = date('Y-m-d', strtotime($start));
    $end = date('Y-m-d', strtotime($end));

    // 1. SETUP DEDUCTION SCHEDULE
    $applySSS = isDeductionWeek($start, $end, 10, 16);
    $applyGovt = isDeductionWeek($start, $end, 17, 23); // PHIC & HDMF
    $applyLoan = isDeductionWeek($start, $end, 24, 30);

    // 2. FETCH DATA
    $empStmt = $pdo->query("SELECT * FROM employees");
    $employeesDB = [];
    while ($row = $empStmt->fetch(PDO::FETCH_ASSOC)) {
        $employeesDB[trim($row['name'])] = $row;
    }

    // Holidays
    $holStmt = $pdo->prepare("SELECT * FROM holidays WHERE date BETWEEN ? AND ?");
    $holStmt->execute([date('Y-m-01', strtotime($start)), date('Y-m-t', strtotime($end))]);
    $holidays = [];
    while ($row = $holStmt->fetch(PDO::FETCH_ASSOC)) {
        $rate = floatval($row['rate_multiplier']);
        $type = ($rate >= 1.0) ? 'Regular' : 'Special';
        $holidays[$row['date']] = ['type' => $type, 'rate' => $rate];
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

        // Init totals
        $total_shifts = 0; 
        $overtime_pay = 0;
        $cashier_hours = 0;
        $night_diff_count = 0;
        $late_count = 0;
        
        $manual_deductions = 0;
        $extra_earnings = 0;

        // Arrays to track what happened on specific dates
        $daily_earnings = []; 

        foreach ($rows as $r) {
            $date = $r['Date'];
            $remarks = strtolower($r['Remarks'] ?? '');
            $role = strtolower($r['Role'] ?? '');
            $hours = floatval($r['Hours'] ?? 0);
            $shiftNo = intval($r['ShiftNumber'] ?? 0);
            $extraCol = $r['Extra'] ?? ''; 

            // Init daily tracking if needed
            if (!isset($daily_earnings[$date])) {
                $daily_earnings[$date] = 0;
            }

            // A. Base Pay (Shift Count)
            if (strpos($remarks, 'onduty') !== false || strpos($remarks, 'late') !== false) {
                $total_shifts++;
                // Track earnings for this specific day (used for Special Holiday percentage)
                $daily_earnings[$date] += ($hours * $hourlyRate); // Assuming 8 hrs for regular shift roughly
                // Correction: Actually Base Pay is fixed Days * Rate. 
                // But for Special Holiday premium, we need "Pay generated on that day".
                // Let's use (Hours * HourlyRate) to be safe for the premium base.
            }

            // B. Overtime Pay
            if (strpos($remarks, 'overtime') !== false) {
                $ot_amount = $hours * $hourlyRate;
                $overtime_pay += $ot_amount;
                $daily_earnings[$date] += $ot_amount;
            }

            // C. Night Differential (Shift 3)
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

            // F. Extras/Manual Deductions
            if (is_numeric($extraCol)) {
                 $extra_earnings += floatval($extraCol);
            } else {
                if (stripos($extraCol, 'uniform') !== false) $manual_deductions += 106.0; 
            }
            $manual_deductions += abs(floatval($r['Deductions'] ?? 0));
        }

        // --- CALCULATIONS ---

        // 1. Base Pay
        $regular_pay = $total_shifts * $dailyRate;

        // 2. Holiday Pay
        $holiday_pay = 0;
        foreach ($holidays as $hDate => $hInfo) {
            // Regular Holiday: +100% Daily Rate (Unworked Benefit)
            if ($hInfo['type'] === 'Regular') {
                $holiday_pay += $dailyRate;
            } 
            // Special Holiday: +30% of TOTAL PAY earned on that day
            elseif ($hInfo['type'] === 'Special') {
                if (isset($daily_earnings[$hDate])) {
                    // Premium = Total Earnings on that day * 30%
                    // Arthur Curry: (8hrs * 65) + (1hr * 65) = 585. 30% of 585 = 175.5.
                    // Total Holiday Pay = 520 (Reg) + 175.5 (Spec) = 695.5.
                    $holiday_pay += ($daily_earnings[$hDate] * 0.30);
                }
            }
        }

        // 3. Allowance
        $allowance = 0;
        if ($dailyRate > ALLOWANCE_THRESHOLD) {
            $allowance += $total_shifts * ALLOWANCE_DAILY_AMT;
        }
        $cashier_bonus_val = ($cashier_hours / 8) * CASHIER_BONUS_PER_8HRS;
        $allowance += $cashier_bonus_val;

        // 4. Night Diff
        $night_diff_pay = $night_diff_count * NIGHT_DIFF_RATE;

        // 5. Gross
        $gross = $regular_pay + $overtime_pay + $holiday_pay + $allowance + $night_diff_pay + $extra_earnings;

        // 6. Deductions
        $sss = $applySSS ? floatval($empData['sss'] ?? 0) : 0;
        $phic = $applyGovt ? floatval($empData['phic'] ?? 0) : 0;
        $hdmf = $applyGovt ? floatval($empData['hdmf'] ?? 0) : 0;
        $govt_loan = $applyLoan ? floatval($empData['govt_loan'] ?? 0) : 0;
        $late_deduction = $late_count * LATE_PENALTY;

        $total_deductions = $sss + $phic + $hdmf + $govt_loan + $late_deduction + $manual_deductions;
        $net = $gross - $total_deductions;

        $resultEmployees[] = [
            'empkey' => $name,
            'name' => $name,
            'business_unit' => $data['bu'],
            'totals' => [
                'regular' => round($regular_pay, 2),
                'overtime' => round($overtime_pay, 2),
                'night' => round($night_diff_pay, 2),
                'bonus' => round($cashier_bonus_val, 2),
                'allowance' => round($allowance, 2),
                'holiday' => round($holiday_pay, 2),
                'extra' => round($extra_earnings, 2),
                'gross' => round($gross, 2),
                'late' => round($late_deduction, 2),
                'sss' => round($sss, 2),
                'phic' => round($phic, 2),
                'hdmf' => round($hdmf, 2),
                'govt' => round($phic + $hdmf, 2),
                'loan' => round($govt_loan, 2),
                'db_deductions' => round($manual_deductions, 2),
                'total_deductions' => round($total_deductions, 2),
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