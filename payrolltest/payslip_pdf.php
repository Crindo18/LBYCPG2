<?php
require_once 'dbconfig.php';
require_once 'calculate_rates.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$employeeName = $_GET['name'] ?? '';
$startDate = $_GET['start'] ?? date('Y-m-01');
$endDate = $_GET['end'] ?? date('Y-m-t');

if (empty($employeeName)) {
    die("Employee name is required.");
}

// Get payroll data
$payrollData = calculateRates($pdo, $startDate, $endDate);
$employeeData = null;

foreach ($payrollData['employees'] as $emp) {
    if ($emp['name'] === $employeeName) {
        $employeeData = $emp;
        break;
    }
}

if (!$employeeData) {
    die("No payroll data found for this employee.");
}

$totals = $employeeData['totals'];
$perDay = $employeeData['per_day'];

// Build HTML for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 24px; }
        .header p { margin: 5px 0; color: #666; }
        .section { margin: 20px 0; }
        .section h3 { background: #f0f0f0; padding: 8px; margin: 10px 0 5px 0; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table th, table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        table th { background: #f8f9fa; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .summary-table td { padding: 5px 10px; }
        .total-row { background: #e8f5e9; font-weight: bold; font-size: 14px; }
        .net-pay { background: #2196F3; color: white; font-size: 16px; font-weight: bold; }
        .deduction { color: #d32f2f; }
        .earning { color: #388e3c; }
        .info-box { background: #f5f5f5; padding: 10px; margin: 10px 0; border-left: 4px solid #2196F3; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PAYSLIP</h1>
        <p>Pay Period: ' . date('F d, Y', strtotime($startDate)) . ' - ' . date('F d, Y', strtotime($endDate)) . '</p>
    </div>

    <div class="info-box">
        <table class="summary-table" style="border: none;">
            <tr>
                <td style="border: none;"><strong>Employee Name:</strong></td>
                <td style="border: none;">' . htmlspecialchars($employeeName) . '</td>
                <td style="border: none;"><strong>Days Worked:</strong></td>
                <td style="border: none;">' . count($perDay) . ' days</td>
            </tr>
            <tr>
                <td style="border: none;"><strong>Generated:</strong></td>
                <td style="border: none;">' . date('F d, Y h:i A') . '</td>
                <td style="border: none;"><strong>Total Hours:</strong></td>
                <td style="border: none;">' . number_format(array_sum(array_column($perDay, 'hours')), 2) . ' hrs</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>EARNINGS SUMMARY</h3>
        <table>
            <tr>
                <td>Regular Pay</td>
                <td class="text-right earning">₱ ' . number_format($totals['regular'], 2) . '</td>
            </tr>
            <tr>
                <td>Overtime Pay</td>
                <td class="text-right earning">₱ ' . number_format($totals['overtime'], 2) . '</td>
            </tr>
            <tr>
                <td>Night Differential</td>
                <td class="text-right earning">₱ ' . number_format($totals['night'], 2) . '</td>
            </tr>
            <tr>
                <td>Cashier Bonus</td>
                <td class="text-right earning">₱ ' . number_format($totals['bonus'], 2) . '</td>
            </tr>
            <tr>
                <td>Allowance</td>
                <td class="text-right earning">₱ ' . number_format($totals['allowance'], 2) . '</td>
            </tr>
            <tr>
                <td>Holiday Pay</td>
                <td class="text-right earning">₱ ' . number_format($totals['holiday'], 2) . '</td>
            </tr>
            <tr>
                <td>Extra/Bonus</td>
                <td class="text-right earning">₱ ' . number_format($totals['extra'], 2) . '</td>
            </tr>
            <tr class="total-row">
                <td><strong>GROSS PAY</strong></td>
                <td class="text-right"><strong>₱ ' . number_format($totals['gross'], 2) . '</strong></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>DEDUCTIONS</h3>
        <table>
            <tr>
                <td>Late Deduction</td>
                <td class="text-right deduction">₱ ' . number_format($totals['late'], 2) . '</td>
            </tr>
            <tr>
                <td>Government Contributions (SSS, PhilHealth, HDMF)</td>
                <td class="text-right deduction">₱ ' . number_format($totals['govt'], 2) . '</td>
            </tr>
            <tr>
                <td>Loans</td>
                <td class="text-right deduction">₱ ' . number_format($totals['loan'], 2) . '</td>
            </tr>
            <tr class="total-row">
                <td><strong>TOTAL DEDUCTIONS</strong></td>
                <td class="text-right"><strong>₱ ' . number_format($totals['total_deductions'], 2) . '</strong></td>
            </tr>
        </table>
    </div>

    <table style="margin-top: 20px;">
        <tr class="net-pay">
            <td><strong>NET PAY</strong></td>
            <td class="text-right"><strong>₱ ' . number_format($totals['net'], 2) . '</strong></td>
        </tr>
    </table>

    <div class="section">
        <h3>DAILY BREAKDOWN</h3>
        <table style="font-size: 10px;">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Role</th>
                    <th class="text-right">Hours</th>
                    <th>Holiday</th>
                    <th class="text-right">Regular</th>
                    <th class="text-right">OT</th>
                    <th class="text-right">Night</th>
                    <th class="text-right">Bonus</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>';

foreach ($perDay as $day) {
    $dayTotal = $day['regular_pay'] + $day['ot_pay'] + $day['night_pay'] 
              + $day['cashier_bonus'] + $day['allowance'] + $day['holiday_bonus']
              + ($day['extra'] ?? 0) - $day['late'] - ($day['deductions'] ?? 0);
    
    $html .= '<tr>
                <td>' . date('M d', strtotime($day['date'])) . '</td>
                <td>' . htmlspecialchars($day['role']) . '</td>
                <td class="text-right">' . number_format($day['hours'], 2) . '</td>
                <td class="text-center">' . ($day['holiday'] ? ucfirst($day['holiday']) : '-') . '</td>
                <td class="text-right">' . number_format($day['regular_pay'], 2) . '</td>
                <td class="text-right">' . number_format($day['ot_pay'], 2) . '</td>
                <td class="text-right">' . number_format($day['night_pay'], 2) . '</td>
                <td class="text-right">' . number_format($day['cashier_bonus'] + $day['allowance'] + $day['holiday_bonus'], 2) . '</td>
                <td class="text-right">' . number_format($dayTotal, 2) . '</td>
              </tr>';
}

$html .= '
            </tbody>
        </table>
    </div>

    <div style="margin-top: 30px; border-top: 2px solid #333; padding-top: 10px; text-align: center; color: #666;">
        <p style="margin: 5px 0; font-size: 10px;">This is a system-generated payslip and does not require a signature.</p>
        <p style="margin: 5px 0; font-size: 10px;">For inquiries, please contact the HR department.</p>
    </div>
</body>
</html>';

// Configure Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('defaultFont', 'Arial');

// Generate PDF
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output
$filename = "Payslip_" . preg_replace('/[^A-Za-z0-9_]/', '_', $employeeName) . "_{$startDate}_to_{$endDate}.pdf";
$dompdf->stream($filename, ['Attachment' => 1]);
exit;