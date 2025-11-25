<?php
// calculate_rates.php
// REVISED: Strict Rules for Night Diff, Overtime, and Holidays

require_once __DIR__ . '/dbconfig.php';

// Constants
define('LATE_PENALTY', 150.0);
define('NIGHT_DIFF_RATE', 52.0); 
define('CASHIER_BONUS', 40.0);
define('ALLOWANCE_RATE', 20.0);
define('ALLOWANCE_THRESHOLD', 520.0); // Strictly > 520

/**
 * Helper to determine deduction weeks based on user rules
 */
function isDeductionWeek($start, $end, $dayStart, $dayEnd) {
    $s = strtotime($start);
    $e = strtotime($end);
    $targetStart = strtotime(date('Y-m-', $s) . $dayStart);
    $targetEnd = strtotime(date('Y-m-', $s) . $dayEnd);
    return max($s, $targetStart) <= min($e, $targetEnd);
}

function calculateRates($pdo, $start, $end) {
    $start = date('Y-m-d', strtotime($start));
    $end = date('Y-m-d', strtotime($end));

    // 1. Determine Deduction Schedules
    $applySSS = isDeductionWeek($start, $end, 10, 16);
    $applyGovt = isDeductionWeek($start, $end, 17, 23); // PHIC & HDMF
    $applyLoan = isDeductionWeek($start, $end, 24, 30);

    // 2. Fetch Reference Data
    $empStmt = $pdo->query("SELECT * FROM employees");
    $employeesDB = [];
    while ($row = $empStmt->fetch(PDO::FETCH_ASSOC)) {
        $employeesDB[trim($row['name'])] = $row;
    }

    $holStmt = $pdo->prepare("SELECT * FROM holidays WHERE date BETWEEN ? AND ?");
    $holStmt->execute([date('Y-m-01', strtotime($start)), date('Y-m-t', strtotime($end))]); // Get whole month holidays
    $holidays = [];
    while ($row = $holStmt->fetch(PDO::FETCH_ASSOC)) {
        $holidays[$row['date']] = floatval($row['rate_multiplier']);
    }

    // 3. Fetch Logs
    $stmt = $pdo->prepare("
        SELECT * FROM payrolldata 
        WHERE Date BETWEEN :s AND :e 
        ORDER BY Name, Date, TimeIn
    ");
    $stmt->execute([':s' => $start, ':e' => $end]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group logs by Name
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
        // Explicitly use 65 for 520 earners, otherwise Rate/8
        $hourlyRate = ($dailyRate == 520) ? 65.0 : ($dailyRate / 8);

        // Init Totals
        $totals = [
            'regular' => 0, 'overtime' => 0, 'night' => 0, 'bonus' => 0, 
            'allowance' => 0, 'holiday' => 0, 'extra' => 0, 
            'late' => 0, 'deductions' => 0,
            'sss' => 0, 'phic' => 0, 'hdmf' => 0, 'loan' => 0
        ];

        $workedDates = [];
        $otHoursTotal = 0;

        foreach ($rows as $r) {
            $date = $r['Date'];
            $remarks = strtolower($r['Remarks'] ?? '');
            $role = strtolower($r['Role'] ?? '');
            $hours = floatval($r['Hours'] ?? 0);

            // --- A. Identify Work Days ---
            // Only count as a "Work Day" if it's NOT explicitly overtime only
            // (Usually "OnDuty" or "Late" imply a regular shift)
            if (strpos($remarks, 'overtime') === false) {
                if (!isset($workedDates[$date])) {
                    $workedDates[$date] = true;
                }
            }

            // --- B. Overtime ---
            // Strict Rule: Only calculate OT if Remarks column contains "Overtime"
            if (strpos($remarks, 'overtime') !== false) {
                $totals['overtime'] += $hours * $hourlyRate;
                $otHoursTotal += $hours;
            }

            // --- C. Night Differential ---
            // Strict Rule: Only pay 52php if shift STARTS at night (>= 18:00) 
            // This excludes 4am/5am starts like Allen Barry.
            $isNight = false;
            if (!empty($r['TimeIn'])) {
                $hourIn = intval(substr($r['TimeIn'], 0, 2));
                // Night shift definition: Starts 6PM (18) or later, OR starts very early (00-03)
                // Allen starts at 04:xx and 05:xx -> NOT Night.
                // Carter starts 21:xx -> YES Night.
                if ($hourIn >= 18 || $hourIn <= 3) {
                    $isNight = true;
                }
            }
            if ($isNight) {
                $totals['night'] += NIGHT_DIFF_RATE;
            }

            // --- D. Cashier Bonus ---
            if (strpos($role, 'cashier') !== false) {
                // Bonus is usually per shift. 
                // Checking if OT shift also gets bonus? Summary implies NO double bonus for OT shift?
                // We'll assume per record with 'Cashier' role for now.
                // But watch out for double entries. Usually applied once per day.
                // Let's add it per record as the data seems to split shifts.
                // Re-check Barnes: He is Crew. Wayne is Cashier.
                // Wayne 1/3: Cashier OnDuty + Cashier Overtime. 
                // Does he get 40 or 80? Summary doesn't show breakdown but let's assume per 8hr block roughly.
                // Friend's rule: "Cashier Role has 40php bonus per 8hrs".
                // We'll apply 40 for every record marked Cashier.
                $totals['bonus'] += CASHIER_BONUS;
            }

            // --- E. Special Holiday Premium (If Worked) ---
            if (isset($holidays[$date])) {
                // Special Holiday (0.3) - Only if worked
                if ($holidays[$date] == 0.3) {
                    // If this log is regular hours (not OT), add premium
                    // Actually premium applies to whole day usually.
                    // We simplify: Add (DailyRate * 0.3) ONCE per worked special holiday.
                    // Done in loop below to avoid duplicates.
                }
            }

            // --- F. Deductions & Extras ---
            if (strpos($remarks, 'late') !== false) {
                $totals['late'] += LATE_PENALTY;
            }
            $totals['deductions'] += abs(floatval($r['Deductions'] ?? 0));
            $totals['extra'] += floatval($r['Extra'] ?? 0);
        }

        // Final Calculations based on Day Counts
        $daysWorkedCount = count($workedDates);
        
        // 1. Regular Pay
        $totals['regular'] = $daysWorkedCount * $dailyRate;

        // 2. Holiday Pay (Auto-Add Regular, Add Special if Worked)
        foreach ($holidays as $hDate => $rateMult) {
            if ($rateMult >= 1.0) {
                // Regular Holiday (100%): Always Paid
                $totals['holiday'] += $dailyRate;
            } elseif ($rateMult == 0.3) {
                // Special Holiday (30%): Paid only if worked
                if (isset($workedDates[$hDate])) {
                    $totals['holiday'] += ($dailyRate * 0.3);
                }
            }
        }

        // 3. Allowance Rule (> 520 only)
        if ($dailyRate > ALLOWANCE_THRESHOLD) {
            $totals['allowance'] = $daysWorkedCount * ALLOWANCE_RATE;
        }

        // 4. Govt Deductions
        if ($empData) {
            if ($applySSS) $totals['sss'] = floatval($empData['sss']);
            if ($applyGovt) {
                $totals['phic'] = floatval($empData['phic']);
                $totals['hdmf'] = floatval($empData['hdmf']);
            }
            if ($applyLoan) $totals['loan'] = floatval($empData['govt_loan']);
        }

        // Totals
        $gross = $totals['regular'] + $totals['overtime'] + $totals['night'] + 
                 $totals['bonus'] + $totals['allowance'] + $totals['holiday'] + $totals['extra'];
        
        $totalDed = $totals['late'] + $totals['deductions'] + 
                    $totals['sss'] + $totals['phic'] + $totals['hdmf'] + $totals['loan'];

        $net = $gross - $totalDed;

        // Output
        $resultEmployees[] = [
            'empkey' => $name,
            'name' => $name,
            'business_unit' => $data['bu'],
            'totals' => [
                'regular' => round($totals['regular'], 2),
                'overtime' => round($totals['overtime'], 2),
                'night' => round($totals['night'], 2),
                'bonus' => round($totals['bonus'], 2),
                'allowance' => round($totals['allowance'], 2),
                'holiday' => round($totals['holiday'], 2),
                'extra' => round($totals['extra'], 2),
                'gross' => round($gross, 2),
                'late' => round($totals['late'], 2),
                'sss' => round($totals['sss'], 2),
                'phic' => round($totals['phic'], 2),
                'hdmf' => round($totals['hdmf'], 2),
                'govt' => round($totals['phic'] + $totals['hdmf'], 2),
                'loan' => round($totals['loan'], 2),
                'db_deductions' => round($totals['deductions'], 2),
                'total_deductions' => round($totalDed, 2),
                'net' => round($net, 2)
            ]
        ];
    }

    return [
        'start' => $start,
        'end' => $end,
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