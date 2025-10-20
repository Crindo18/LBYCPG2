<?php
require_once 'dbconfig.php';
require_once 'calculate_rates.php';
require_once '../vendor/autoload.php';
include 'sidebar.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;
use Dompdf\Options;

$message = '';
$messageType = '';

// Get date range
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Get employee emails
$emailQuery = $pdo->query("
    SELECT DISTINCT p.Name, e.Email 
    FROM payrolldata p
    LEFT JOIN employee_info e ON p.Name = e.Name
    WHERE p.Date IS NOT NULL
    AND e.Email IS NOT NULL
    ORDER BY p.Name
");
$employeesWithEmails = $emailQuery->fetchAll(PDO::FETCH_ASSOC);

// Handle batch sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_batch'])) {
    $selectedEmployees = $_POST['employees'] ?? [];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    
    if (empty($selectedEmployees)) {
        $message = "Please select at least one employee.";
        $messageType = 'warning';
    } else {
        $payrollData = calculateRates($pdo, $startDate, $endDate);
        $successCount = 0;
        $failCount = 0;
        $errors = [];
        
        foreach ($selectedEmployees as $empName) {
            // Find employee data
            $empData = null;
            foreach ($payrollData['employees'] as $emp) {
                if ($emp['name'] === $empName) {
                    $empData = $emp;
                    break;
                }
            }
            
            if (!$empData) continue;
            
            // Get email
            $email = null;
            foreach ($employeesWithEmails as $e) {
                if ($e['Name'] === $empName) {
                    $email = $e['Email'];
                    break;
                }
            }
            
            if (!$email) {
                $errors[] = "$empName - No email address";
                $failCount++;
                continue;
            }
            
            // Generate PDF
            try {
                $pdf = generatePayslipPDF($empData, $startDate, $endDate);
                
                // Send email
                if (sendPayslipEmail($email, $empName, $pdf, $startDate, $endDate)) {
                    $successCount++;
                } else {
                    $failCount++;
                    $errors[] = "$empName - Email sending failed";
                }
            } catch (Exception $e) {
                $failCount++;
                $errors[] = "$empName - " . $e->getMessage();
            }
        }
        
        $message = "Batch sending complete! Success: $successCount, Failed: $failCount";
        $messageType = $failCount > 0 ? 'warning' : 'success';
        
        if (!empty($errors)) {
            $message .= "<br><small>" . implode("<br>", $errors) . "</small>";
        }
    }
}

/**
 * Formats a number as currency for a specific output mode.
 * @param float $number The number to format.
 * @param string $mode 'web' for browser display, 'pdf' for Dompdf.
 * @return string The formatted currency string.
 */
function formatCurrency($number, $mode = 'web') {
    // Use the HTML entity for PDF, and the literal symbol for the web.
    $symbol = ($mode === 'pdf') ? '&#8369; ' : '₱';
    return $symbol . number_format($number, 2);
}

/**
 * Generate PDF payslip
 */
function generatePayslipPDF($empData, $startDate, $endDate) {
    $totals = $empData['totals'];
    $name = $empData['name'];
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; font-size: 11px; }
            .header { text-align: center; margin-bottom: 20px; }
            .header h2 { margin: 0; font-size: 20px; }
            .header p { margin: 3px 0; }
            .info-section { margin: 15px 0; }
            .info-row { margin: 5px 0; }
            .info-row strong { display: inline-block; width: 150px; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            table td { padding: 5px; border-bottom: 1px solid #ddd; }
            .total-row { font-weight: bold; border-top: 2px solid #000; }
            .net-pay { background: #e3f2fd; font-size: 14px; font-weight: bold; padding: 10px; text-align: center; margin: 15px 0; }
            .footer { text-align: center; margin-top: 30px; font-size: 9px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>PAYSLIP</h2>
            <p><strong>LU Ambata Services</strong></p>
            <p>2401 Taft Avenue, Malate, Manila, Metro Manila</p>
        </div>
        
        <div class="info-section">
            <div class="info-row"><strong>Employee Name:</strong> ' . htmlspecialchars($name) . '</div>
            <div class="info-row"><strong>Business Unit:</strong> ' . htmlspecialchars($empData['business_unit']) . '</div>
            <div class="info-row"><strong>Pay Period:</strong> ' . date('F d, Y', strtotime($startDate)) . ' - ' . date('F d, Y', strtotime($endDate)) . '</div>
            <div class="info-row"><strong>Days Worked:</strong> ' . count($empData['per_day']) . ' days</div>
        </div>
        
        <h4>EARNINGS</h4>
        <table>
            <tr><td>Regular Pay</td><td style="text-align: right">' . formatCurrency($totals['regular'], 'pdf') . '</td></tr>
            <tr><td>Overtime Pay</td><td style="text-align: right">' . formatCurrency($totals['overtime'], 'pdf') . '</td></tr>
            <tr><td>Night Differential</td><td style="text-align: right">' . formatCurrency($totals['night'], 'pdf') . '</td></tr>
            <tr><td>Allowance</td><td style="text-align: right">' . formatCurrency($totals['allowance'], 'pdf') . '</td></tr>
            <tr><td>Holiday Pay</td><td style="text-align: right">' . formatCurrency($totals['holiday'], 'pdf') . '</td></tr>
            <tr class="total-row"><td>GROSS PAY</td><td style="text-align: right">' . formatCurrency($totals['gross'], 'pdf') . '</td></tr>
        </table>
        
        <h4>DEDUCTIONS</h4>
        <table>
            <tr><td>Late Deduction</td><td style="text-align: right">' . formatCurrency($totals['late'], 'pdf') . '</td></tr>
            <tr><td>SSS</td><td style="text-align: right">' . formatCurrency($totals['sss'], 'pdf') . '</td></tr>
            <tr><td>PhilHealth</td><td style="text-align: right">' . formatCurrency($totals['phic'], 'pdf') . '</td></tr>
            <tr><td>HDMF</td><td style="text-align: right">' . formatCurrency($totals['hdmf'], 'pdf') . '</td></tr>
            <tr><td>Loans</td><td style="text-align: right">' . formatCurrency($totals['loan'], 'pdf') . '</td></tr>
            <tr class="total-row"><td>TOTAL DEDUCTIONS</td><td style="text-align: right">' . formatCurrency($totals['total_deductions'], 'pdf') . '</td></tr>
        </table>
        
        <div class="net-pay">NET PAY: ' . formatCurrency($totals['net'], 'pdf') . '</div>
        
        <div class="footer">
            <p>This is a system-generated payslip from LU Ambata Services</p>
            <p>Generated on ' . date('F d, Y h:i A') . '</p>
        </div>
    </body>
    </html>';
    
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'Arial');
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    return $dompdf->output();
}

/**
 * Send payslip via email
 */
function sendPayslipEmail($toEmail, $empName, $pdfContent, $startDate, $endDate) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'christian_alado@dlsu.edu.ph';  // Change to your email
        $mail->Password = 'npmcrtycmjrdirmn';     // Change to your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('noreply@luambata.com', 'LU Ambata Services');
        $mail->addAddress($toEmail, $empName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Payslip for ' . date('F Y', strtotime($startDate));
        $mail->Body = '
            <html>
            <body style="font-family: Arial, sans-serif;">
                <h2>Dear ' . htmlspecialchars($empName) . ',</h2>
                <p>Please find attached your payslip for the period:</p>
                <p><strong>' . date('F d, Y', strtotime($startDate)) . ' - ' . date('F d, Y', strtotime($endDate)) . '</strong></p>
                <p>If you have any questions regarding your payslip, please contact the HR department.</p>
                <br>
                <p>Best regards,<br>
                <strong>LU Ambata Services HR Department</strong></p>
            </body>
            </html>
        ';
        
        // Attach PDF
        $filename = 'Payslip_' . str_replace(' ', '_', $empName) . '_' . date('Y-m', strtotime($startDate)) . '.pdf';
        $mail->addStringAttachment($pdfContent, $filename);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Calculate payroll for preview
$payrollData = calculateRates($pdo, $startDate, $endDate);
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-envelope"></i> Batch Email Payslips</h2>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card-panel p-4 mb-4">
        <h5 class="mb-3">Select Pay Period</h5>
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>" required>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-arrow-clockwise"></i> Load Employees
                </button>
            </div>
        </form>
    </div>

    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> <strong>Note:</strong> Before sending emails, configure your SMTP settings in the code:
        <ul class="mb-0 mt-2">
            <li>Update <code>$mail->Username</code> with your email</li>
            <li>Update <code>$mail->Password</code> with your app password</li>
            <li>For Gmail, enable "App Passwords" in your Google Account settings</li>
        </ul>
    </div>

    <div class="card-panel p-4">
        <h5 class="mb-3">Select Employees to Send Payslips</h5>
        
        <?php if (empty($employeesWithEmails)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> No employees with email addresses found. 
                Please add employee emails in the database first.
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                <input type="hidden" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                        <label class="form-check-label fw-bold" for="selectAll">
                            Select All Employees
                        </label>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="50">
                                    <input type="checkbox" class="form-check-input" id="selectAllTable">
                                </th>
                                <th>Employee Name</th>
                                <th>Email Address</th>
                                <th class="text-end">Gross Pay</th>
                                <th class="text-end">Deductions</th>
                                <th class="text-end">Net Pay</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employeesWithEmails as $emp): ?>
                                <?php 
                                // Find payroll data
                                $empPayroll = null;
                                foreach ($payrollData['employees'] as $p) {
                                    if ($p['name'] === $emp['Name']) {
                                        $empPayroll = $p;
                                        break;
                                    }
                                }
                                
                                if (!$empPayroll) continue;
                                $totals = $empPayroll['totals'];
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="employees[]" 
                                               value="<?= htmlspecialchars($emp['Name']) ?>" 
                                               class="form-check-input employee-checkbox">
                                    </td>
                                    <td><?= htmlspecialchars($emp['Name']) ?></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($emp['Email']) ?></small></td>
                                    <td class="text-end"><?= formatCurrency($totals['gross']) ?></td>
                                    <td class="text-end text-danger"><?= formatCurrency($totals['total_deductions']) ?></td>
                                    <td class="text-end fw-bold"><?= formatCurrency($totals['net']) ?></td>
                                    <td>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle"></i> Ready
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <button type="submit" name="send_batch" class="btn btn-primary btn-lg" 
                            onclick="return confirm('Are you sure you want to send payslips to selected employees?')">
                        <i class="bi bi-send"></i> Send Payslips via Email
                    </button>
                    <span class="ms-3 text-muted" id="selectedCount">0 employees selected</span>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="card-panel p-4 mt-4">
        <h5 class="mb-3"><i class="bi bi-question-circle"></i> How to Use</h5>
        <ol>
            <li>Select the pay period using the date range above</li>
            <li>Check the employees you want to send payslips to</li>
            <li>Click "Send Payslips via Email" to batch send</li>
            <li>Each employee will receive a PDF payslip attached to their email</li>
        </ol>
        <div class="alert alert-warning mt-3 mb-0">
            <strong>Important:</strong> Make sure to configure SMTP settings in the code before sending emails.
            For Gmail users, you need to create an "App Password" from your Google Account security settings.
        </div>
    </div>
</div>

<script>
// Select all functionality
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    updateSelectedCount();
});

document.getElementById('selectAllTable')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    updateSelectedCount();
});

// Update selected count
function updateSelectedCount() {
    const checked = document.querySelectorAll('.employee-checkbox:checked').length;
    const countEl = document.getElementById('selectedCount');
    if (countEl) {
        countEl.textContent = `${checked} employee${checked !== 1 ? 's' : ''} selected`;
    }
}

// Add change listeners to all checkboxes
document.querySelectorAll('.employee-checkbox').forEach(cb => {
    cb.addEventListener('change', updateSelectedCount);
});

updateSelectedCount();
</script>