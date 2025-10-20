<?php
session_start();

// Initialize payroll data in session if not exists
if (!isset($_SESSION['payroll_data'])) {
    $_SESSION['payroll_data'] = [];
}

// Get current step
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_payroll'])) {
        // Save payroll entry
        $employee_id = uniqid();
        $_SESSION['payroll_data'][$employee_id] = [
            'name' => $_POST['employee_name'],
            'business_unit' => $_POST['business_unit'],
            'pay_period' => $_POST['pay_period'],
            'worked_days' => $_POST['worked_days'],
            'earnings' => [
                'Basic Rate' => (float)$_POST['basic_rate'],
                'Overtime Pay' => (float)$_POST['overtime_pay'],
                'Rate2' => (float)$_POST['rate2'],
                'Allowance' => (float)$_POST['allowance'],
                'Night Differential' => (float)$_POST['night_differential'],
                'Holiday' => (float)$_POST['holiday'],
                'SIL' => (float)$_POST['sil']
            ],
            'deductions' => [
                'SSS' => (float)$_POST['sss'],
                'PAGIBIG' => (float)$_POST['pagibig'],
                'PHILHEALTH' => (float)$_POST['philhealth'],
                'Government Loan' => (float)$_POST['gov_loan'],
                'Late/Absent' => (float)$_POST['late_absent'],
                'Misload/Shortage' => (float)$_POST['misload'],
                'Uniform/CA' => (float)$_POST['uniform']
            ],
            'status' => 'pending',
            'email' => $_POST['email']
        ];
        $success_message = "Payroll entry saved successfully!";
    }
    
    if (isset($_POST['approve_payroll'])) {
        $employee_id = $_POST['employee_id'];
        if (isset($_SESSION['payroll_data'][$employee_id])) {
            $_SESSION['payroll_data'][$employee_id]['status'] = 'approved';
            $success_message = "Payroll approved successfully!";
        }
    }
    
    if (isset($_POST['send_email'])) {
        $employee_id = $_POST['employee_id'];
        if (isset($_SESSION['payroll_data'][$employee_id])) {
            $employee = $_SESSION['payroll_data'][$employee_id];
            // In real application, send actual email here
            $email_message = "Payslip email sent to: " . $employee['email'];
            $_SESSION['payroll_data'][$employee_id]['status'] = 'sent';
        }
    }
    
    // Handle signature upload
    if (isset($_FILES['signature'])) {
        $allowed = ['png', 'jpg', 'jpeg'];
        $filename = $_FILES['signature']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $upload_dir = 'uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_filename = 'signature_' . time() . '.' . $ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['signature']['tmp_name'], $upload_path)) {
                $_SESSION['signature'] = $upload_path;
                $signature_message = "Signature uploaded successfully!";
            }
        }
    }
}

// Handle signature removal
if (isset($_GET['remove_signature'])) {
    if (isset($_SESSION['signature']) && file_exists($_SESSION['signature'])) {
        unlink($_SESSION['signature']);
    }
    unset($_SESSION['signature']);
    header('Location: paysliptest.php?step=' . $step);
    exit;
}

// Calculate totals for an employee
function calculateTotals($earnings, $deductions) {
    return [
        'gross' => array_sum($earnings),
        'deductions' => array_sum($deductions),
        'net' => array_sum($earnings) - array_sum($deductions)
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LU Ambata Services - Payroll System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body {
            background-color: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .step-indicator {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin: 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .step-item {
            display: flex;
            align-items: center;
            position: relative;
            flex: 1;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #718096;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
            z-index: 1;
        }
        
        .step-item.active .step-number {
            background: #667eea;
            color: white;
        }
        
        .step-item.completed .step-number {
            background: #48bb78;
            color: white;
        }
        
        .step-label {
            font-weight: 600;
            color: #4a5568;
        }
        
        .step-item.active .step-label {
            color: #667eea;
        }
        
        .content-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        
        .table-hover tbody tr:hover {
            background-color: #f7fafc;
        }
        
        .badge-pending {
            background-color: #ed8936;
        }
        
        .badge-approved {
            background-color: #48bb78;
        }
        
        .badge-sent {
            background-color: #4299e1;
        }
        
        /* Payslip Styles */
        .payslip-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 2px solid #4a5568;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .company-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .company-header h2 {
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .company-header p {
            margin: 0;
            color: #4a5568;
            font-size: 14px;
        }
        
        .employee-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .info-row {
            display: flex;
            font-size: 14px;
        }
        
        .info-label {
            font-weight: 600;
            min-width: 130px;
            color: #2d3748;
        }
        
        .info-value {
            color: #4a5568;
        }
        
        .section-title {
            font-weight: bold;
            font-size: 16px;
            margin-top: 20px;
            margin-bottom: 10px;
            color: #2d3748;
            border-bottom: 2px solid #2d3748;
            padding-bottom: 5px;
        }
        
        .amount-table {
            width: 100%;
            font-size: 14px;
        }
        
        .amount-table tr {
            border-bottom: 1px solid #e2e8f0;
        }
        
        .amount-table td {
            padding: 8px 0;
        }
        
        .amount-table td:last-child {
            text-align: right;
            font-weight: 500;
        }
        
        .total-row {
            font-weight: bold;
            font-size: 15px;
            border-top: 2px solid #2d3748 !important;
        }
        
        .total-row td {
            padding-top: 12px !important;
        }
        
        .signature-section {
            margin-top: 40px;
            padding-top: 20px;
        }
        
        .signature-box {
            text-align: center;
        }
        
        .signature-line {
            border-top: 2px solid #2d3748;
            width: 250px;
            margin: 60px auto 10px;
        }
        
        .signature-image {
            max-width: 200px;
            max-height: 60px;
            margin-bottom: 5px;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #718096;
            font-style: italic;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-building"></i> LU Ambata Services - Payroll System
            </span>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="d-flex justify-content-between">
                <div class="step-item <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                    <div class="step-number">1</div>
                    <div class="step-label">Payroll Overview</div>
                </div>
                <div class="step-item <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                    <div class="step-number">2</div>
                    <div class="step-label">Enter Payroll</div>
                </div>
                <div class="step-item <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                    <div class="step-number">3</div>
                    <div class="step-label">Approve Payroll</div>
                </div>
                <div class="step-item <?php echo $step >= 4 ? 'active' : ''; ?>">
                    <div class="step-number">4</div>
                    <div class="step-label">Print / Send Payslips</div>
                </div>
            </div>
        </div>

        <!-- Success Messages -->
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($email_message)): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-envelope-check"></i> <?php echo $email_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($signature_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo $signature_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Step 1: Payroll Overview -->
        <?php if ($step == 1): ?>
        <div class="content-card">
            <h3 class="mb-4"><i class="bi bi-list-ul"></i> Payroll Overview</h3>
            
            <?php if (empty($_SESSION['payroll_data'])): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No payroll entries yet. Click "Enter Payroll" to add employees.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Employee Name</th>
                                <th>Business Unit</th>
                                <th>Pay Period</th>
                                <th>Gross Income</th>
                                <th>Deductions</th>
                                <th>Net Income</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_SESSION['payroll_data'] as $id => $emp): ?>
                                <?php $totals = calculateTotals($emp['earnings'], $emp['deductions']); ?>
                                <tr>
                                    <td><?php echo $emp['name']; ?></td>
                                    <td><?php echo $emp['business_unit']; ?></td>
                                    <td><?php echo $emp['pay_period']; ?></td>
                                    <td>₱<?php echo number_format($totals['gross'], 2); ?></td>
                                    <td>₱<?php echo number_format($totals['deductions'], 2); ?></td>
                                    <td><strong>₱<?php echo number_format($totals['net'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?php echo $emp['status']; ?>">
                                            <?php echo ucfirst($emp['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <a href="?step=2" class="btn btn-primary btn-lg">
                    <i class="bi bi-plus-circle"></i> Enter Payroll
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Step 2: Enter Payroll -->
        <?php if ($step == 2): ?>
        <div class="content-card">
            <h3 class="mb-4"><i class="bi bi-pencil-square"></i> Enter Payroll</h3>
            
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Employee Name *</label>
                    <input type="text" name="employee_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Business Unit *</label>
                    <input type="text" name="business_unit" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Pay Period *</label>
                    <input type="text" name="pay_period" class="form-control" placeholder="e.g. August 2021" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Worked Days *</label>
                    <input type="number" name="worked_days" class="form-control" required>
                </div>
                
                <div class="col-12 mt-4">
                    <h5 class="border-bottom pb-2">Earnings</h5>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Basic Rate</label>
                    <input type="number" step="0.01" name="basic_rate" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Overtime Pay</label>
                    <input type="number" step="0.01" name="overtime_pay" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Rate2</label>
                    <input type="number" step="0.01" name="rate2" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Allowance</label>
                    <input type="number" step="0.01" name="allowance" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Night Differential</label>
                    <input type="number" step="0.01" name="night_differential" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Holiday</label>
                    <input type="number" step="0.01" name="holiday" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">SIL</label>
                    <input type="number" step="0.01" name="sil" class="form-control" value="0">
                </div>
                
                <div class="col-12 mt-4">
                    <h5 class="border-bottom pb-2">Deductions</h5>
                </div>
                <div class="col-md-4">
                    <label class="form-label">SSS</label>
                    <input type="number" step="0.01" name="sss" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">PAGIBIG</label>
                    <input type="number" step="0.01" name="pagibig" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">PHILHEALTH</label>
                    <input type="number" step="0.01" name="philhealth" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Government Loan</label>
                    <input type="number" step="0.01" name="gov_loan" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Late/Absent</label>
                    <input type="number" step="0.01" name="late_absent" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Misload/Shortage</label>
                    <input type="number" step="0.01" name="misload" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Uniform/CA</label>
                    <input type="number" step="0.01" name="uniform" class="form-control" value="0">
                </div>
                
                <div class="col-12 mt-4">
                    <button type="submit" name="save_payroll" class="btn btn-success btn-lg">
                        <i class="bi bi-save"></i> Save Payroll Entry
                    </button>
                    <a href="?step=1" class="btn btn-secondary btn-lg">
                        <i class="bi bi-arrow-left"></i> Back to Overview
                    </a>
                    <?php if (!empty($_SESSION['payroll_data'])): ?>
                    <a href="?step=3" class="btn btn-primary btn-lg">
                        <i class="bi bi-arrow-right"></i> Proceed to Approve
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Step 3: Approve Payroll -->
        <?php if ($step == 3): ?>
        <div class="content-card">
            <h3 class="mb-4"><i class="bi bi-check-circle"></i> Approve Payroll</h3>
            
            <?php if (empty($_SESSION['payroll_data'])): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> No payroll entries to approve.
                </div>
                <a href="?step=2" class="btn btn-primary">Enter Payroll</a>
            <?php else: ?>
                <?php foreach ($_SESSION['payroll_data'] as $id => $emp): ?>
                    <?php $totals = calculateTotals($emp['earnings'], $emp['deductions']); ?>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo $emp['name']; ?></h5>
                                <span class="badge badge-<?php echo $emp['status']; ?>">
                                    <?php echo ucfirst($emp['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Payslip Preview -->
                            <div class="payslip-container" id="payslip-<?php echo $id; ?>">
                                <div class="company-header">
                                    <h2>Payslip</h2>
                                    <p><strong>LU Ambata Services</strong></p>
                                    <p>2401 Taft Avenue, Malate, Manila, Metro Manila</p>
                                </div>

                                <div class="employee-info">
                                    <div>
                                        <div class="info-row">
                                            <span class="info-label">Pay Period</span>
                                            <span class="info-value">: <?php echo $emp['pay_period']; ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Worked Days</span>
                                            <span class="info-value">: <?php echo $emp['worked_days']; ?></span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="info-row">
                                            <span class="info-label">Employee Name</span>
                                            <span class="info-value">: <?php echo $emp['name']; ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Business Unit</span>
                                            <span class="info-value">: <?php echo $emp['business_unit']; ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="section-title">Earnings</div>
                                        <table class="amount-table">
                                            <?php foreach ($emp['earnings'] as $item => $amount): ?>
                                            <tr>
                                                <td><?php echo $item; ?></td>
                                                <td><?php echo number_format($amount, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <tr class="total-row">
                                                <td>Gross Income</td>
                                                <td><?php echo number_format($totals['gross'], 2); ?></td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="section-title">Deductions</div>
                                        <table class="amount-table">
                                            <?php foreach ($emp['deductions'] as $item => $amount): ?>
                                            <tr>
                                                <td><?php echo $item; ?></td>
                                                <td><?php echo number_format($amount, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <tr class="total-row">
                                                <td>Total Deductions</td>
                                                <td><?php echo number_format($totals['deductions'], 2); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <div class="row mt-4">
                                    <div class="col-12">
                                        <table class="amount-table">
                                            <tr class="total-row" style="font-size: 16px;">
                                                <td>Net Income</td>
                                                <td><?php echo number_format($totals['net'], 2); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <div class="signature-section">
                                    <div class="row">
                                        <div class="col-md-6 offset-md-6">
                                            <div class="signature-box">
                                                <p class="mb-1"><strong>Employer Signature</strong></p>
                                                <?php if (isset($_SESSION['signature'])): ?>
                                                    <img src="<?php echo $_SESSION['signature']; ?>" class="signature-image" alt="Signature">
                                                <?php endif; ?>
                                                <div class="signature-line"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="footer-text">
                                    This is system generated payslip
                                </div>
                            </div>
                            
                            <?php if ($emp['status'] === 'pending'): ?>
                            <div class="mt-3 no-print">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="employee_id" value="<?php echo $id; ?>">
                                    <button type="submit" name="approve_payroll" class="btn btn-success">
                                        <i class="bi bi-check-circle"></i> Approve Payroll
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="mt-4 no-print">
                    <a href="?step=2" class="btn btn-secondary btn-lg">
                        <i class="bi bi-arrow-left"></i> Back to Enter Payroll
                    </a>
                    <a href="?step=4" class="btn btn-primary btn-lg">
                        <i class="bi bi-arrow-right"></i> Proceed to Print/Send
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Step 4: Print / Send Payslips -->
        <?php if ($step == 4): ?>
        <div class="content-card">
            <h3 class="mb-4"><i class="bi bi-printer"></i> Print / Send Payslips</h3>
            
            <!-- Signature Management -->
            <div class="card mb-4 bg-light">
                <div class="card-body">
                    <h5 class="mb-3">Manage Employer Signature</h5>
                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <div class="col-md-6">
                            <input type="file" name="signature" class="form-control" accept=".png,.jpg,.jpeg">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-upload"></i> Upload Signature
                            </button>
                        </div>
                        <?php if (isset($_SESSION['signature'])): ?>
                        <div class="col-md-3">
                            <a href="?step=4&remove_signature=1" class="btn btn-danger w-100" onclick="return confirm('Remove signature?')">
                                <i class="bi bi-trash"></i> Remove Signature
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>
                    <?php if (isset($_SESSION['signature'])): ?>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-check-circle"></i> Signature is set and will appear on all payslips.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle"></i> No signature uploaded. Payslips will be generated without signature.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (empty($_SESSION['payroll_data'])): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> No approved payroll entries.
                </div>
            <?php else: ?>
                <?php foreach ($_SESSION['payroll_data'] as $id => $emp): ?>
                    <?php if ($emp['status'] !== 'approved' && $emp['status'] !== 'sent') continue; ?>
                    <?php $totals = calculateTotals($emp['earnings'], $emp['deductions']); ?>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo $emp['name']; ?> - <?php echo $emp['email']; ?></h5>
                                <span class="badge badge-<?php echo $emp['status']; ?>">
                                    <?php echo ucfirst($emp['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Payslip for Download -->
                            <div class="payslip-container" id="payslip-final-<?php echo $id; ?>">
                                <div class="company-header">
                                    <h2>Payslip</h2>
                                    <p><strong>LU Ambata Services</strong></p>
                                    <p>2401 Taft Avenue, Malate, Manila, Metro Manila</p>
                                </div>

                                <div class="employee-info">
                                    <div>
                                        <div class="info-row">
                                            <span class="info-label">Pay Period</span>
                                            <span class="info-value">: <?php echo $emp['pay_period']; ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Worked Days</span>
                                            <span class="info-value">: <?php echo $emp['worked_days']; ?></span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="info-row">
                                            <span class="info-label">Employee Name</span>
                                            <span class="info-value">: <?php echo $emp['name']; ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Business Unit</span>
                                            <span class="info-value">: <?php echo $emp['business_unit']; ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="section-title">Earnings</div>
                                        <table class="amount-table">
                                            <?php foreach ($emp['earnings'] as $item => $amount): ?>
                                            <tr>
                                                <td><?php echo $item; ?></td>
                                                <td><?php echo number_format($amount, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <tr class="total-row">
                                                <td>Gross Income</td>
                                                <td><?php echo number_format($totals['gross'], 2); ?></td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="section-title">Deductions</div>
                                        <table class="amount-table">
                                            <?php foreach ($emp['deductions'] as $item => $amount): ?>
                                            <tr>
                                                <td><?php echo $item; ?></td>
                                                <td><?php echo number_format($amount, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <tr class="total-row">
                                                <td>Total Deductions</td>
                                                <td><?php echo number_format($totals['deductions'], 2); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <div class="row mt-4">
                                    <div class="col-12">
                                        <table class="amount-table">
                                            <tr class="total-row" style="font-size: 16px;">
                                                <td>Net Income</td>
                                                <td><?php echo number_format($totals['net'], 2); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <div class="signature-section">
                                    <div class="row">
                                        <div class="col-md-6 offset-md-6">
                                            <div class="signature-box">
                                                <p class="mb-1"><strong>Employer Signature</strong></p>
                                                <?php if (isset($_SESSION['signature'])): ?>
                                                    <img src="<?php echo $_SESSION['signature']; ?>" class="signature-image" alt="Signature">
                                                <?php endif; ?>
                                                <div class="signature-line"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="footer-text">
                                    This is system generated payslip
                                </div>
                            </div>
                            
                            <div class="mt-3 d-flex gap-2 no-print">
                                <button onclick="downloadPayslip('<?php echo $id; ?>', '<?php echo str_replace(' ', '_', $emp['name']); ?>', '<?php echo str_replace(' ', '_', $emp['pay_period']); ?>')" class="btn btn-success">
                                    <i class="bi bi-download"></i> Download PDF
                                </button>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="employee_id" value="<?php echo $id; ?>">
                                    <button type="submit" name="send_email" class="btn btn-primary">
                                        <i class="bi bi-envelope"></i> Send via Email
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="mt-4 no-print">
                    <a href="?step=3" class="btn btn-secondary btn-lg">
                        <i class="bi bi-arrow-left"></i> Back to Approve
                    </a>
                    <a href="?step=1" class="btn btn-primary btn-lg">
                        <i class="bi bi-house"></i> Back to Overview
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function downloadPayslip(id, name, period) {
            <?php if (!isset($_SESSION['signature'])): ?>
                if (!confirm('No signature has been uploaded yet. Do you want to proceed with downloading the payslip without a signature?')) {
                    return;
                }
            <?php endif; ?>
            
            const element = document.getElementById('payslip-final-' + id);
            const opt = {
                margin: 10,
                filename: 'payslip_' + name + '_' + period + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>