<?php
// payslip_pdf.php
require_once 'dbconfig.php';
require_once 'payslip_functions.php';
require 'vendor/autoload.php'; // dompdf via composer

use Dompdf\Dompdf;

$name = $_GET['name'] ?? null;
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;
if (!$name || !$start || !$end) { echo "Missing parameters."; exit; }

$data = calculate_payslip($pdo, $name, $start, $end);

// Build simple HTML for PDF
$html = "<h2>Payslip for {$name}</h2>";
$html .= "<p>Period: {$start} — {$end}</p>";
$html .= "<table width='100%' border='1' cellpadding='6' cellspacing='0'><tr><th>Date</th><th>Hours</th><th>Regular</th><th>OT</th><th>Night</th><th>Bonus</th><th>Allowance</th><th>Late</th><th>Deductions</th><th>Extra</th></tr>";
foreach ($data['per_day'] as $d) {
    $html .= "<tr>
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
$html .= "</table>";
$html .= "<p><strong>Gross:</strong> {$t['gross']} &nbsp; <strong>Deductions:</strong> {$t['deductions']} &nbsp; <strong>Net:</strong> {$t['net']}</p>";

// Generate PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$filename = "Payslip_".preg_replace('/\s+/', '_', $name)."_{$start}_{$end}.pdf";
$dompdf->stream($filename, ['Attachment' => 1]); // Attachment=1 forces download
exit;
