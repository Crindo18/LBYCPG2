<?php
// calculate_rates.php
// Computes payroll for a given date range, including holiday pay and all adjustments.
// Can be used standalone (returns JSON) or included from another script (returns array).

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function tryLoadRates($path) {
    $map = [];
    if (!file_exists($path)) return $map;
    try {
        $ss = IOFactory::load($path);
    } catch (Exception $e) {
        return $map;
    }

    $names = ['Rates', 'Rate', 'SalaryRates', 'RatesSheet', 'Sheet2'];
    $sheet = null;
    foreach ($names as $n) {
        if ($ss->sheetNameExists($n)) {
            $sheet = $ss->getSheetByName($n);
            break;
        }
    }
    if (!$sheet) $sheet = $ss->getSheet(0);

    $rows = $sheet->toArray(null, true, true, true);
    if (count($rows) < 2) return $map;
    $header = $rows[1];

    $colmap = [];
    foreach ($header as $col => $val) {
        $h = strtolower(trim($val));
        if (strpos($h, 'emp') !== false) $colmap['id'] = $col;
        if (strpos($h, 'name') !== false) $colmap['name'] = $col;
        if (strpos($h, 'hour') !== false && strpos($h, 'rate') !== false) $colmap['hourly'] = $col;
        if (strpos($h, 'daily') !== false) $colmap['daily'] = $col;
        if (strpos($h, 'gov') !== false) $colmap['govt'] = $col;
        if (strpos($h, 'loan') !== false) $colmap['loan'] = $col;
    }

    foreach ($rows as $i => $r) {
        if ($i === 1) continue;
        $id = isset($colmap['id']) ? trim($r[$colmap['id']]) : '';
        $name = isset($colmap['name']) ? trim($r[$colmap['name']]) : '';
        $daily = isset($colmap['daily']) ? floatval(str_replace(',', '', $r[$colmap['daily']])) : null;
        $hourly = isset($colmap['hourly']) ? floatval(str_replace(',', '', $r[$colmap['hourly']])) : null;
        $govt = isset($colmap['govt']) ? floatval(str_replace(',', '', $r[$colmap['govt']])) : 0.0;
        $loan = isset($colmap['loan']) ? floatval(str_replace(',', '', $r[$colmap['loan']])) : 0.0;

        $key = ($id !== '') ? $id : $name;
        if ($key === '') continue;
        $map[$key] = [
            'daily_rate' => $daily,
            'hourly_rate' => $hourly,
            'govt' => $govt,
            'loan' => $loan
        ];
    }
    return $map;
}

// Payroll constants
define('NIGHT_DIFF_PER_HOUR', 52.0);
define('LATE_DEDUCTION', 150.0);
define('CASHIER_BONUS_PER_8', 40.0);
define('ALLOWANCE_THRESHOLD', 520.0);
define('ALLOWANCE_AMOUNT', 20.0);

// Holiday multipliers
define('HOLIDAY_SPECIAL', 1.30);  // +30%
define('HOLIDAY_REGULAR', 2.00);  // +100%

function calculateRates($pdo, $start, $end) {
    // Load rates file
    $defaultPaths = [
        __DIR__ . '/Payroll Testing Data (1).xlsx',
        __DIR__ . '/rates.xlsx',
        __DIR__ . '/Rates.xlsx'
    ];
    $ratesMap = [];
    foreach ($defaultPaths as $p) {
        if (file_exists($p)) {
            $ratesMap = tryLoadRates($p);
            if (!empty($ratesMap)) break;
        }
    }

    // Load holidays (from a simple 3-column sheet: Date | Type | Description)
    $holidayMap = [];
    $holidayPath = __DIR__ . '/Payroll Testing Data (1).xlsx';
    if (file_exists($holidayPath)) {
        try {
            $ss = IOFactory::load($holidayPath);
            $sheet = $ss->getSheet(0);
            $rows = $sheet->toArray(null, true, true, true);
            foreach ($rows as $i => $r) {
                if ($i === 1) continue;
                $date = trim($r['A']);
                $type = strtolower(trim($r['B']));
                if ($date === '' || $type === '') continue;
                if (strpos($type, 'regular') !== false) $holidayMap[$date] = 'regular';
                elseif (strpos($type, 'special') !== false) $holidayMap[$date] = 'special';
            }
        } catch (Exception $e) {}
    }

    // Query records
    $stmt = $pdo->prepare("SELECT * FROM payrolldata WHERE Date BETWEEN :s AND :e ORDER BY Date, ID");
    $stmt->execute([':s' => $start, ':e' => $end]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by employee
    $grouped = [];
    foreach ($rows as $r) {
        $key = !empty($r['EmployeeID']) ? $r['EmployeeID'] : $r['Name'];
        if (!isset($grouped[$key])) $grouped[$key] = ['name' => $r['Name'], 'rows' => []];
        $grouped[$key]['rows'][] = $r;
    }

    $resultEmployees = [];

    foreach ($grouped as $key => $data) {
        $name = $data['name'];
        $rows = $data['rows'];
        $rateInfo = $ratesMap[$key] ?? ($ratesMap[$name] ?? ['daily_rate' => null, 'hourly_rate' => null, 'govt' => 0, 'loan' => 0]);

        $total_regular = $total_overtime = $total_night = $total_bonus = $total_allowance = 0.0;
        $total_late = $total_extra = $total_row_deductions = $total_holiday = 0.0;
        $per_day = [];

        foreach ($rows as $r) {
            $date = $r['Date'];
            $hours = floatval($r['Hours'] ?? 0);
            $role = $r['Role'] ?? $r['BusinessUnit'] ?? '';
            $remarks = $r['Remarks'] ?? '';
            $rowDeductions = floatval($r['Deductions'] ?? 0);
            $rowExtra = floatval($r['Extra'] ?? 0);
            $timeIn = $r['TimeIn'] ?? null;
            $timeOut = $r['TimeOut'] ?? null;

            if (stripos($role, 'canteen') !== false) {
                $regular_hours = 10.0;
                $max_regular = 13.0;
            } else {
                $regular_hours = 8.0;
                $max_regular = 8.0;
            }

            if (!empty($rateInfo['hourly_rate'])) {
                $hourly = floatval($rateInfo['hourly_rate']);
            } elseif (!empty($rateInfo['daily_rate'])) {
                $hourly = floatval($rateInfo['daily_rate']) / $regular_hours;
            } else {
                $hourly = 520.0 / $regular_hours;
            }

            $reg_hours = min($hours, $regular_hours);
            $ot_hours = max(0.0, $hours - $regular_hours);
            $regular_pay = $reg_hours * $hourly;
            $ot_pay = $ot_hours * $hourly * 2.0;

            $cashier_bonus = (stripos($role, 'cashier') !== false) ? floor($hours / 8.0) * CASHIER_BONUS_PER_8 : 0.0;

            // Night differential calculation
            $night_hours = 0.0;
            if ($timeIn && $timeOut) {
                try {
                    $in = new DateTime($date . ' ' . $timeIn);
                    $out = new DateTime($date . ' ' . $timeOut);
                    if ($out <= $in) $out->modify('+1 day');
                    $nightStart = new DateTime($date . ' 22:00:00');
                    $nightEnd = (new DateTime($date . ' 06:00:00'))->modify('+1 day');
                    $s = $in > $nightStart ? $in : $nightStart;
                    $e = $out < $nightEnd ? $out : $nightEnd;
                    if ($s < $e) $night_hours = ($e->getTimestamp() - $s->getTimestamp()) / 3600.0;
                } catch (Exception $ex) {}
            }
            $night_pay = $night_hours * NIGHT_DIFF_PER_HOUR;

            // Allowance if base > threshold
            $daily_base = $regular_pay + $cashier_bonus;
            $allowance = ($daily_base > ALLOWANCE_THRESHOLD) ? ALLOWANCE_AMOUNT : 0.0;

            // Late deduction
            $late = (stripos($remarks, 'late') !== false) ? LATE_DEDUCTION : 0.0;

            // Holiday pay
            $holidayType = $holidayMap[$date] ?? null;
            $holiday_bonus = 0.0;
            if ($holidayType === 'special') {
                $holiday_bonus = $regular_pay * 0.30;
            } elseif ($holidayType === 'regular') {
                $holiday_bonus = $regular_pay * 1.00;
            }

            $total_regular += $regular_pay;
            $total_overtime += $ot_pay;
            $total_night += $night_pay;
            $total_bonus += $cashier_bonus;
            $total_allowance += $allowance;
            $total_late += $late;
            $total_extra += $rowExtra;
            $total_row_deductions += $rowDeductions;
            $total_holiday += $holiday_bonus;

            $per_day[] = [
                'date' => $date,
                'role' => $role,
                'hours' => $hours,
                'holiday' => $holidayType,
                'holiday_bonus' => round($holiday_bonus, 2),
                'regular_pay' => round($regular_pay, 2),
                'ot_pay' => round($ot_pay, 2),
                'cashier_bonus' => round($cashier_bonus, 2),
                'night_pay' => round($night_pay, 2),
                'allowance' => round($allowance, 2),
                'late' => round($late, 2),
                'extra' => round($rowExtra, 2),
                'deductions' => round($rowDeductions, 2)
            ];
        }

        $gross = $total_regular + $total_overtime + $total_night + $total_bonus + $total_allowance + $total_extra + $total_holiday;
        $govt = floatval($rateInfo['govt'] ?? 0.0);
        $loan = floatval($rateInfo['loan'] ?? 0.0);
        $total_deductions = $total_late + $total_row_deductions + $govt + $loan;
        $net = $gross - $total_deductions;

        $resultEmployees[] = [
            'empkey' => $key,
            'name' => $name,
            'per_day' => $per_day,
            'totals' => [
                'regular' => round($total_regular, 2),
                'overtime' => round($total_overtime, 2),
                'night' => round($total_night, 2),
                'bonus' => round($total_bonus, 2),
                'allowance' => round($total_allowance, 2),
                'holiday' => round($total_holiday, 2),
                'extra' => round($total_extra, 2),
                'gross' => round($gross, 2),
                'late' => round($total_late, 2),
                'govt' => round($govt, 2),
                'loan' => round($loan, 2),
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

// --- main section ---
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json; charset=utf-8');
    $start = $_GET['start'] ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-t');
    $data = calculateRates($pdo, $start, $end);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
