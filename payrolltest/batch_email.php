<?php
// Import required dependencies for database, rate calculation, email, and PDF generation
require_once 'dbconfig.php';
require_once 'calculate_rates.php';
require_once '../vendor/autoload.php';
include 'sidebar.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;
use Dompdf\Options;

// Initialize variables for user feedback messages
$message = '';
$messageType = '';

// Set default date range to current month if not provided
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Handle email update requests - allows updating employee email addresses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email'])) {
    $name = $_POST['employee_name'];
    $email = trim($_POST['email']);
    
    try {
        // Update email in database for the specified employee
        $stmt = $pdo->prepare("UPDATE payrolldata SET Email = ? WHERE Name = ?");
        $stmt->execute([$email, $name]);
        
        $message = "Email updated successfully for $name";
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = "Error updating email: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle batch email sending - sends payslips to selected employees
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_batch'])) {
    $selectedEmployees = $_POST['employees'] ?? [];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    
    // Validate that at least one employee is selected
    if (empty($selectedEmployees)) {
        $message = "Please select at least one employee.";
        $messageType = 'warning';
    } else {
        // Validate email addresses for all selected employees before sending
        $invalidEmails = [];
        foreach ($selectedEmployees as $empName) {
            $emailStmt = $pdo->prepare("SELECT Email FROM payrolldata WHERE Name = ? LIMIT 1");
            $emailStmt->execute([$empName]);
            $emailRow = $emailStmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if email exists and is valid format
            if (!$emailRow || empty($emailRow['Email']) || !filter_var($emailRow['Email'], FILTER_VALIDATE_EMAIL)) {
                $invalidEmails[] = $empName;
            }
        }
        
        // Stop if any employees have invalid emails
        if (!empty($invalidEmails)) {
            $message = "Cannot send emails. The following employees have invalid or missing email addresses:<br><strong>" . implode(', ', $invalidEmails) . "</strong><br>Please update their email addresses before sending.";
            $messageType = 'danger';
        } else {
            // Calculate payroll data for the selected period
            $payrollData = calculateRates($pdo, $startDate, $endDate);
            $successCount = 0;
            $failCount = 0;
            $errors = [];
            
            // Process each selected employee
            foreach ($selectedEmployees as $empName) {
                // Find employee data in calculated payroll
                $empData = null;
                foreach ($payrollData['employees'] as $emp) {
                    if ($emp['name'] === $empName) {
                        $empData = $emp;
                        break;
                    }
                }
                
                if (!$empData) continue;
                
                // Retrieve employee's email address
                $emailStmt = $pdo->prepare("SELECT Email FROM payrolldata WHERE Name = ? LIMIT 1");
                $emailStmt->execute([$empName]);
                $emailRow = $emailStmt->fetch(PDO::FETCH_ASSOC);
                $email = $emailRow['Email'] ?? '';
                
                // Generate PDF payslip and send via email
                try {
                    $pdf = generatePayslipPDF($empData, $startDate, $endDate);
                    
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
            
            // Prepare summary message
            $message = "Batch sending complete! Success: $successCount, Failed: $failCount";
            $messageType = $failCount > 0 ? 'warning' : 'success';
            
            if (!empty($errors)) {
                $message .= "<br><small>" . implode("<br>", $errors) . "</small>";
            }
        }
    }
}

/**
 * Format currency with Philippine Peso symbol
 * @param float $number Amount to format
 * @param string $mode 'web' or 'pdf' for different rendering contexts
 * @return string Formatted currency string
 */
function formatCurrency($number, $mode = 'web') {
    $symbol = ($mode === 'pdf') ? '&#8369; ' : '₱';
    return $symbol . number_format($number, 2);
}

/**
 * Generate PDF payslip for an employee
 * @param array $empData Employee payroll data
 * @param string $startDate Pay period start date
 * @param string $endDate Pay period end date
 * @return string PDF content as binary string
 */
function generatePayslipPDF($empData, $startDate, $endDate) {
    $totals = $empData['totals'];
    $name = $empData['name'];
    
    // Build HTML template for PDF with company header and payroll details
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
    
    // Configure and generate PDF using Dompdf library
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
 * Send payslip email with PDF attachment
 * @param string $toEmail Recipient email address
 * @param string $empName Employee name
 * @param string $pdfContent PDF binary content
 * @param string $startDate Pay period start date
 * @param string $endDate Pay period end date
 * @return bool True if email sent successfully
 */
function sendPayslipEmail($toEmail, $empName, $pdfContent, $startDate, $endDate) {
    $mail = new PHPMailer(true);
    
    try {
        // Configure SMTP settings for Gmail
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'christian_alado@dlsu.edu.ph';
        $mail->Password = 'npmcrtycmjrdirmn';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Set sender and recipient
        $mail->setFrom('noreply@luambata.com', 'LU Ambata Services');
        $mail->addAddress($toEmail, $empName);
        
        // Compose email with HTML body
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
        
        // Attach PDF payslip
        $filename = 'Payslip_' . str_replace(' ', '_', $empName) . '_' . date('Y-m', strtotime($startDate)) . '.pdf';
        $mail->addStringAttachment($pdfContent, $filename);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Calculate payroll for preview display
$payrollData = calculateRates($pdo, $startDate, $endDate);

// Fetch all unique employees with their email addresses
$employeeEmailsQuery = $pdo->query("
    SELECT DISTINCT Name, BusinessUnit, Email
    FROM payrolldata
    WHERE Name IS NOT NULL
    ORDER BY Name ASC
");
$allEmployees = $employeeEmailsQuery->fetchAll(PDO::FETCH_ASSOC);
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

    <!-- Date Range Selection Panel -->
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

    <!-- Employee Selection Panel -->
    <div class="card-panel p-4">
        <h5 class="mb-3">Select Employees to Send Payslips</h5>
        
        <!-- Search and Filter Controls -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label fw-semibold text-secondary">Search by Name</label>
                <input type="text" id="searchEmployeeName" class="form-control" placeholder="Enter employee name...">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold text-secondary">Filter by Business Unit</label>
                <select id="filterBusinessUnit" class="form-select">
                    <option value="">All Units</option>
                    <option value="Canteen">Canteen</option>
                    <option value="Service Crew">Service Crew</option>
                    <option value="Main Office">Main Office</option>
                    <option value="Satellite Office">Satellite Office</option>
                </select>
            </div>
        </div>
        
        <?php if (empty($allEmployees)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> No employees found for the selected period.
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                <input type="hidden" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                
                <!-- Select All Checkbox -->
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                        <label class="form-check-label fw-bold" for="selectAll">
                            Select All Employees
                        </label>
                    </div>
                </div>

                <!-- Employee List Table -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="employeeTable">
                        <thead class="table-light">
                            <tr>
                                <th width="50">
                                    <input type="checkbox" class="form-check-input" id="selectAllTable">
                                </th>
                                <th>Employee Name</th>
                                <th>Business Unit</th>
                                <th>Email Address</th>
                                <th class="text-end">Gross Pay</th>
                                <th class="text-end">Deductions</th>
                                <th class="text-end">Net Pay</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="employeeTableBody">
                            <?php foreach ($allEmployees as $emp): ?>
                                <?php 
                                // Match employee with their payroll data
                                $empPayroll = null;
                                foreach ($payrollData['employees'] as $p) {
                                    if ($p['name'] === $emp['Name']) {
                                        $empPayroll = $p;
                                        break;
                                    }
                                }
                                
                                if (!$empPayroll) continue;
                                $totals = $empPayroll['totals'];
                                
                                // Validate email and set status badge
                                $email = $emp['Email'] ?? '';
                                $hasValidEmail = !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
                                $statusClass = $hasValidEmail ? 'bg-success' : 'bg-danger';
                                $statusText = $hasValidEmail ? 'Ready' : 'Invalid';
                                $statusIcon = $hasValidEmail ? 'bi-check-circle' : 'bi-x-circle';
                                ?>
                                <tr data-name="<?= htmlspecialchars($emp['Name']) ?>" data-unit="<?= htmlspecialchars($emp['BusinessUnit']) ?>">
                                    <td>
                                        <input type="checkbox" name="employees[]" 
                                               value="<?= htmlspecialchars($emp['Name']) ?>" 
                                               class="form-check-input employee-checkbox">
                                    </td>
                                    <td><?= htmlspecialchars($emp['Name']) ?></td>
                                    <td><?= htmlspecialchars($emp['BusinessUnit']) ?></td>
                                    <td>
                                        <span class="email-display">
                                            <small class="text-muted"><?= $hasValidEmail ? htmlspecialchars($email) : 'No email' ?></small>
                                        </span>
                                    </td>
                                    <td class="text-end"><?= formatCurrency($totals['gross']) ?></td>
                                    <td class="text-end text-danger"><?= formatCurrency($totals['total_deductions']) ?></td>
                                    <td class="text-end fw-bold"><?= formatCurrency($totals['net']) ?></td>
                                    <td>
                                        <span class="badge <?= $statusClass ?>">
                                            <i class="bi <?= $statusIcon ?>"></i> <?= $statusText ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-email-btn" 
                                                data-name="<?= htmlspecialchars($emp['Name']) ?>"
                                                data-email="<?= htmlspecialchars($email) ?>">
                                            <i class="bi bi-pencil"></i> Edit Email
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Send Button and Selection Counter -->
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

    <!-- Instructions Panel -->
    <div class="card-panel p-4 mt-4">
        <h5 class="mb-3"><i class="bi bi-question-circle"></i> How to Use</h5>
        <ol>
            <li>Select the pay period using the date range above</li>
            <li>Use the search and filter options to find specific employees</li>
            <li>Update email addresses for employees with "Invalid" status</li>
            <li>Check the employees you want to send payslips to</li>
            <li>Click "Send Payslips via Email" to batch send</li>
            <li>Each employee will receive a PDF payslip attached to their email</li>
        </ol>
    </div>
</div>

<!-- Email Edit Modal -->
<div class="modal fade" id="emailEditModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Email Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="employee_name" id="edit_employee_name">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Employee Name</label>
                        <input type="text" class="form-control" id="display_employee_name" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" name="email" id="edit_email" class="form-control" 
                               placeholder="employee@example.com" required>
                        <small class="text-muted">Enter a valid email address</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_email" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize DOM elements for filtering and selection
const searchInput = document.getElementById('searchEmployeeName');
const filterSelect = document.getElementById('filterBusinessUnit');
const tableBody = document.getElementById('employeeTableBody');
const emailEditModal = new bootstrap.Modal(document.getElementById('emailEditModal'));

/**
 * Filter employee table based on search and business unit criteria
 */
function filterEmployees() {
    const searchVal = searchInput.value.toLowerCase();
    const unitVal = filterSelect.value.toLowerCase();
    
    const rows = tableBody.querySelectorAll('tr');
    rows.forEach(row => {
        const name = (row.dataset.name || '').toLowerCase();
        const unit = (row.dataset.unit || '').toLowerCase();
        
        // Check if row matches both search and filter criteria
        const nameMatch = !searchVal || name.includes(searchVal);
        const unitMatch = !unitVal || unit === unitVal;
        
        row.style.display = (nameMatch && unitMatch) ? '' : 'none';
    });
    
    updateSelectedCount();
}

// Attach event listeners for real-time filtering
searchInput.addEventListener('input', filterEmployees);
filterSelect.addEventListener('change', filterEmployees);

// Handle "Select All" checkbox functionality
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    checkboxes.forEach(cb => {
        // Only select visible (non-filtered) rows
        if (cb.closest('tr').style.display !== 'none') {
            cb.checked = this.checked;
        }
    });
    updateSelectedCount();
});

// Handle table header "Select All" checkbox
document.getElementById('selectAllTable')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    checkboxes.forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') {
            cb.checked = this.checked;
        }
    });
    updateSelectedCount();
});

/**
 * Update the selected employee count display
 */
function updateSelectedCount() {
    const checked = Array.from(document.querySelectorAll('.employee-checkbox:checked'))
        .filter(cb => cb.closest('tr').style.display !== 'none').length;
    const countEl = document.getElementById('selectedCount');
    if (countEl) {
        countEl.textContent = `${checked} employee${checked !== 1 ? 's' : ''} selected`;
    }
}

// Add change listeners to all employee checkboxes
document.querySelectorAll('.employee-checkbox').forEach(cb => {
    cb.addEventListener('change', updateSelectedCount);
});

// Handle email edit button clicks - populate modal with employee data
document.querySelectorAll('.edit-email-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const name = this.dataset.name;
        const email = this.dataset.email;
        
        document.getElementById('edit_employee_name').value = name;
        document.getElementById('display_employee_name').value = name;
        document.getElementById('edit_email').value = email;
        
        emailEditModal.show();
    });
});

// Initialize selected count on page load
updateSelectedCount();
</script>