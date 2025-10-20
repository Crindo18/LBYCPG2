<?php
// payslip_functions.php
// Reusable payroll calculation helpers. Requires $pdo from dbconfig.php.

require_once 'dbconfig.php';

/**
 * calculate_payslip($pdo, $employeeName, $start_date, $end_date, $options)
 * - $employeeName: Name string as stored in payrolldata.Name (we use Name as identifier)
 * - $start_date, $end_date: YYYY-MM-DD
 * - $options: associative overrides for rates and flags (optional)
 *
 * returns array with totals and per-day breakdown.
 */
function calculate_payslip($pdo, $employeeName, $start_date, $end_date, $options = []) {
    $opts = array_merge([
        'night_diff_per_hour' => 52.0,
        'late_deduction' => 150.0,
        'cashier_bonus_per_8' => 40.0,
        'allowance_threshold_daily' => 520.0,
        'allowance_amount' => 20.0,
    ], $options);

    // Fetch timesheet rows for the employee in the range
    $stmt = $pdo->prepare("SELECT * FROM payrolldata WHERE Name = :name AND Date BETWEEN :s AND :e ORDER BY Date ASC, ID ASC");
    $stmt->execute([':name'=>$employeeName, ':s'=>$start_date, ':e'=>$end_date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If the employees table exists separately and stores rates, you can fetch it here.
    // For now we'll derive hourly rate per-row using the sheet's Rate (if present) or assume Rate=520.
    $total_regular = $total_overtime = $total_night = $total_bonus = $total_allowance = $total_late = $total_extra = 0.0;
    $per_day = [];

    foreach ($rows as $r) {
        // Column names observed in your DB: Date, BusinessUnit, Name, TimeIn, TimeOut, Hours, Role, Remarks, Deductions, Extra
        $date = $r['Date'];
        $hours = floatval($r['Hours']);
        $role = trim($r['Role'] ?? $r['BusinessUnit'] ?? '');
        $remarks = trim($r['Remarks'] ?? '');
        $deductions = floatval($r['Deductions'] ?? 0.0);
        $extra = floatval($r['Extra'] ?? 0.0);

        // Determine regular hours for role
        $regular_hours = 8.0;
        $max_regular = 8.0;
        if (stripos($role, 'canteen') !== false || stripos($r['BusinessUnit'] ?? '', 'Canteen') !== false) {
            // Canteen crew: base 10 to max 13
            $regular_hours = 10.0;
            $max_regular = 13.0;
        }

        // Derive hourly rate:
        // - If there's a column 'Rate' in table or you store daily rate in 'Extra' or another field, adapt here.
        // Try to pick up Rate from the `payrolldata` row if present.
        $hourly_rate = null;
        if (isset($r['Rate']) && $r['Rate'] !== '') {
            $daily_rate = floatval($r['Rate']);
            // default divide by typical 8 (or for canteen use regular_hours)
            $denom = ($regular_hours > 0) ? $regular_hours : 8;
            $hourly_rate = $daily_rate / $denom;
        } else {
            // fallback daily_rate: attempt to query Employees sheet (if exists) or default 520
            $daily_rate = 520.0;
            // if DB has column DailyRate, use it (customize if you store it elsewhere)
            if (isset($r['DailyRate']) && $r['DailyRate'] !== '') $daily_rate = floatval($r['DailyRate']);
            $hourly_rate = $daily_rate / max(1.0, $regular_hours);
        }

        // Regular pay and overtime: special Canteen rule (overtime after max_regular)
        if ($hours > $max_regular) {
            $reg_pay_hours = $max_regular;
            $ot_hours = $hours - $max_regular;
        } else {
            $reg_pay_hours = min($hours, $regular_hours);
            $ot_hours = max(0.0, $hours - $regular_hours);
        }

        $reg_pay = $reg_pay_hours * $hourly_rate;
        $ot_pay = $ot_hours * $hourly_rate * 2.0; // 100% premium -> 2x

        // Cashier bonus: 40 per each full 8-hour block
        $cashier_bonus = 0.0;
        if (stripos($role, 'cashier') !== false) {
            $cashier_bonus = floor($hours / 8.0) * $opts['cashier_bonus_per_8'];
        }

        // Night differential: compute overlap between TimeIn/TimeOut and 22:00-06:00
        $night_hours = 0.0;
        if (!empty($r['TimeIn']) && !empty($r['TimeOut'])) {
            try {
                $in = new DateTime($date . ' ' . $r['TimeIn']);
                $out = new DateTime($date . ' ' . $r['TimeOut']);
                if ($out <= $in) $out->modify('+1 day'); // overnight
                // night window start and end
                $nightStart = new DateTime($date . ' 22:00:00');
                $nightEnd = new DateTime($date . ' 06:00:00');
                $nightEnd->modify('+1 day');
                // overlap
                $s = max($in, $nightStart);
                $e = min($out, $nightEnd);
                if ($s < $e) $night_hours = ($e->getTimestamp() - $s->getTimestamp()) / 3600.0;
            } catch (Exception $ex) {
                $night_hours = 0.0;
            }
        } else {
            // fallback: no times available — we won't guess night hours
            $night_hours = 0.0;
        }
        $night_pay = $night_hours * $opts['night_diff_per_hour'];

        // Allowance: daily base pay (regular_pay + cashier_bonus) > threshold
        $daily_base_pay = $reg_pay + $cashier_bonus;
        $allowance = ($daily_base_pay > $opts['allowance_threshold_daily']) ? $opts['allowance_amount'] : 0.0;

        // Late deduction if remarks contain 'late'
        $late = (stripos($remarks, 'late') !== false) ? $opts['late_deduction'] : 0.0;

        // Sum up
        $total_regular += $reg_pay;
        $total_overtime += $ot_pay;
        $total_night += $night_pay;
        $total_bonus += $cashier_bonus;
        $total_allowance += $allowance;
        $total_late += $late;
        $total_extra += $extra;

        $per_day[] = [
            'date' => $date,
            'hours' => $hours,
            'role' => $role,
            'regular_hours' => $reg_pay_hours ?? $reg_pay_hours,
            'regular_pay' => round($reg_pay,2),
            'overtime_hours' => round($ot_hours,2),
            'overtime_pay' => round($ot_pay,2),
            'cashier_bonus' => round($cashier_bonus,2),
            'night_hours' => round($night_hours,2),
            'night_pay' => round($night_pay,2),
            'allowance' => round($allowance,2),
            'late' => round($late,2),
            'deductions' => round($deductions,2),
            'extra' => round($extra,2),
        ];
    }

    $gross = $total_regular + $total_overtime + $total_night + $total_bonus + $total_allowance + $total_extra;
    $deductions_total = $total_late + array_sum(array_column($per_day, 'deductions') ?: []) ;

    $net = $gross - $deductions_total;

    return [
        'employee' => $employeeName,
        'period' => ['start'=>$start_date, 'end'=>$end_date],
        'totals' => [
            'regular' => round($total_regular,2),
            'overtime' => round($total_overtime,2),
            'night' => round($total_night,2),
            'bonus' => round($total_bonus,2),
            'allowance' => round($total_allowance,2),
            'extra' => round($total_extra,2),
            'late' => round($total_late,2),
            'deductions' => round($deductions_total,2),
            'gross' => round($gross,2),
            'net' => round($net,2),
        ],
        'per_day' => $per_day
    ];
}
