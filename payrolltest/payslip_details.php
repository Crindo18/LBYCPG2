<?php
// payslip_detail.php
require_once 'dbconfig.php';
require_once 'payslip_functions.php';

$name = $_GET['name'] ?? null;
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;
if (!$name || !$start || !$end) {
    echo "Missing parameters. Provide name, start and end in URL.";
    exit;
}

$data = calculate_payslip($pdo, $name, $start, $end);

echo "<h2>Payslip for {$name}</h2>";
echo "<p>Period: {$start} — {$end}</p>";

echo "<table border=1 cellpadding=6><tr><th>Date</th><th>Hours</th><th>Regular Pay</th><th>OT Pay</th><th>Night Pay</th><th>Bonus</th><th>Allowance</th><th>Late</th><th>Deductions</th><th>Extra</th></tr>";
foreach ($data['per_day'] as $d) {
    echo "<tr>
            <td>{$d['date']}</td>
            <td>{$d['hours']}</td>
            <td>{$d['regular_pay']}</td>
            <td>{$d['overtime_pay']}</td>
            <td>{$d['night_pay']}</td>
            <td>{$d['cashier_bonus']}</td>
            <td>{$d['allowance']}</td>
            <td>{$d['late']}</td>
            <td>{$d['deductions']}</td>
            <td>{$d['extra']}</td>
          </tr>";
}
$t = $data['totals'];
echo "</table><h3>Totals</h3>";
echo "<p>Gross: {$t['gross']} &nbsp; Deductions: {$t['deductions']} &nbsp; Net: {$t['net']}</p>";
echo "<p><a href='payslip_pdf.php?name=".urlencode($name)."&start={$start}&end={$end}'>Download PDF</a></p>";
