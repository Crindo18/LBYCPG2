<?php
session_start();

// Initialize payroll data in session if not exists
if (!isset($_SESSION['payroll_data'])) {
    $_SESSION['payroll_data'] = [];
}

// Sample employees list (in real app, this comes from database)
if (!isset($_SESSION['employees_list'])) {
    $_SESSION['employees_list'] = ['Sally Harley', 'John Doe', 'Jane Smith', 'Mike Johnson'];
}

// Get current step
$step = isset($_GET['step']) ? (int)$_GET['step'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_payroll'])) {
        // Save payroll entry
        $employee_id = uniqid();
        $_SESSION['payroll_data'][$employee_id] = [
            'name' => $_POST['employee_name'],
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
    
    if (isset($_POST['batch_approve'])) {
        if (!empty($_POST['selected_employees'])) {
            if (!isset($_SESSION['signature'])) {
                if (!isset($_POST['confirm_no_signature'])) {
                    $require_signature_confirmation = true;
                } else {
                    foreach ($_POST['selected_employees'] as $emp_id) {
                        if (isset($_SESSION['payroll_data'][$emp_id])) {
                            $_SESSION['payroll_data'][$emp_id]['status'] = 'approved';
                        }
                    }
                    $success_message = "Selected payrolls approved successfully!";
                }
            } else {
                foreach ($_POST['selected_employees'] as $emp_id) {
                    if (isset($_SESSION['payroll_data'][$emp_id])) {
                        $_SESSION['payroll_data'][$emp_id]['status'] = 'approved';
                    }
                }
                $success_message = "Selected payrolls approved successfully!";
            }
        }
    }
    
    if (isset($_POST['approve_single'])) {
        $employee_id = $_POST['employee_id'];
        if (!isset($_SESSION['signature'])) {
            if (!isset($_POST['confirm_no_signature'])) {
                $require_signature_confirmation_single = $employee_id;
            } else {
                $_SESSION['payroll_data'][$employee_id]['status'] = 'approved';
                $success_message = "Payroll approved successfully!";
            }
        } else {
            $_SESSION['payroll_data'][$employee_id]['status'] = 'approved';
            $success_message = "Payroll approved successfully!";
        }
    }
    
    if (isset($_POST['cancel_approval'])) {
        if (!empty($_POST['selected_employees'])) {
            foreach ($_POST['selected_employees'] as $emp_id) {
                if (isset($_SESSION['payroll_data'][$emp_id])) {
                    $_SESSION['payroll_data'][$emp_id]['status'] = 'pending';
                }
            }
            $success_message = "Approval cancelled for selected payrolls!";
        }
    }
    
    if (isset($_POST['send_email'])) {
        $employee_id = $_POST['employee_id'];
        if (isset($_SESSION['payroll_data'][$employee_id])) {
            $employee = $_SESSION['payroll_data'][$employee_id];
            $email_message = "Payslip email sent to: " . $employee['email'];
            $_SESSION['payroll_data'][$employee_id]['status'] = 'sent';
        }
    }
    
    if (isset($_POST['batch_send_email'])) {
        if (!empty($_POST['selected_employees'])) {
            $count = 0;
            foreach ($_POST['selected_employees'] as $emp_id) {
                if (isset($_SESSION['payroll_data'][$emp_id])) {
                    $_SESSION['payroll_data'][$emp_id]['status'] = 'sent';
                    $count++;
                }
            }
            $email_message = "Payslips sent to $count employees!";
        }
    }
    
    // Handle signature upload
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] === 0) {
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
    header('Location: employee_payslip_portal.php?step=' . $step);
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

// Get filtered payroll data based on search
$searchQuery = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';

// Include sidebar
include 'sidebar.php';
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
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s;
        }
        
        .card-panel {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        .btn-custom {
            padding: 12px 24px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .badge-pending {
            background-color: #ffa500;
            color: white;
        }
        
        .badge-approved {
            background-color: #28a745;
            color: white;
        }
        
        .badge-sent {
            background-color: #007bff;
            color: white;
        }
        
        /* Progress Steps */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 0;
            right: 0;
            height: 2px;
            background: #dee2e6;
            z-index: 1;
        }
        
        .step-item {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 2;
        }
        
        .step-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            border: 2px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .step-item.active .step-circle {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }
        
        .step-item.completed .step-circle {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .step-label {
            font-size: 14px;
            color: #6c757d;
        }
        
        .step-item.active .step-label {
            color: #667eea;
            font-weight: 600;
        }
        
        /* Payslip Styles */
        .payslip-container {
            max-width: 850px;
            margin: 0 auto;
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 40px;
        }
        
        .payslip-container h3 {
            font-weight: 700;
            color: #2c3e50;
        }
        
        .payslip-container .text-muted {
            color: #6c757d !important;
        }
        
        .payslip-container table {
            margin-bottom: 0;
        }
        
        .payslip-container .border-bottom {
            border-bottom: 2px solid #2c3e50 !important;
        }
        
        .signature-box {
            text-align: center;
            margin-top: 40px;
        }
        
        .signature-line {
            border-top: 2px solid #2c3e50;
            width: 250px;
            margin: 60px auto 10px;
        }
        
        .signature-image {
            max-width: 200px;
            max-height: 60px;
            margin-bottom: 5px;
        }
        
        @media print {
            .no-print, .sidebar, .btn, nav, .progress-steps { display: none !important; }
            .main-content { margin: 0 !important; width: 100% !important; }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <!-- Progress Steps -->
        <div class="progress-steps no-print">
            <div class="step-item <?php echo $step == 0 ? 'active' : ($step > 0 ? 'completed' : ''); ?>">
                <div class="step-circle">0</div>
                <div class="step-label">Overview</div>
            </div>
            <div class="step-item <?php echo $step == 1 ? 'active' : ($step > 1 ? 'completed' : ''); ?>">
                <div class="step-circle">1</div>
                <div class="step-label">Enter Payroll</div>
            </div>
            <div class="step-item <?php echo $step == 2 ? 'active' : ($step > 2 ? 'completed' : ''); ?>">
                <div class="step-circle">2</div>
                <div class="step-label">Approve Payroll</div>
            </div>
            <div class="step-item <?php echo $step == 3 ? 'active' : ''; ?>">
                <div class="step-circle">3</div>
                <div class="step-label">Print/Send</div>
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

        <?php if (isset($require_signature_confirmation)): ?>
        <div class="alert alert-warning" role="alert">
            <h5><i class="bi bi-exclamation-triangle"></i> No Signature Uploaded</h5>
            <p>Are you sure you want to approve payrolls without a signature?</p>
            <form method="POST">
                <?php foreach ($_POST['selected_employees'] as $emp_id): ?>
                    <input type="hidden" name="selected_employees[]" value="<?php echo $emp_id; ?>">
                <?php endforeach; ?>
                <input type="hidden" name="confirm_no_signature" value="1">
                <button type="submit" name="batch_approve" class="btn btn-warning">
                    <i class="bi bi-check"></i> Yes, Approve Without Signature
                </button>
                <a href="?step=<?php echo $step; ?>" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
        <?php endif; ?>

        <?php if (isset($require_signature_confirmation_single)): ?>
        <div class="alert alert-warning" role="alert">
            <h5><i class="bi bi-exclamation-triangle"></i> No Signature Uploaded</h5>
            <p>Are you sure you want to approve this payroll without a signature?</p>
            <form method="POST">
                <input type="hidden" name="employee_id" value="<?php echo $require_signature_confirmation_single; ?>">
                <input type="hidden" name="confirm_no_signature" value="1">
                <button type="submit" name="approve_single" class="btn btn-warning">
                    <i class="bi bi-check"></i> Yes, Approve Without Signature
                </button>
                <a href="?step=<?php echo $step; ?>" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
        <?php endif; ?>

        <!-- Step 0: Overview -->
        <?php if ($step == 0): ?>
        <div class="card-panel p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-person-badge"></i> Employee Payslip Portal</h2>
            </div>
            
            <!-- Search and Filter -->
            <form method="GET" class="row g-3 mb-4">
                <input type="hidden" name="step" value="3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Search Employee</label>
                    <input type="text" name="search" class="form-control" placeholder="Search employee name..." 
                           value="<?php echo htmlspecialchars($searchQuery); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Filter by Status</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="sent" <?php echo $statusFilter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                    </select>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-search"></i> Search
                    </button>
                    <?php if ($searchQuery || $statusFilter !== 'all'): ?>
                        <a href="?step=0" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <?php 
            $filteredData = array_filter($_SESSION['payroll_data'], function($emp) use ($searchQuery, $statusFilter) {
                $matchesSearch = empty($searchQuery) || stripos($emp['name'], $searchQuery) !== false;
                $matchesStatus = $statusFilter === 'all' || $emp['status'] === $statusFilter;
                return $matchesSearch && $matchesStatus;
            });
            ?>

            <?php if (empty($filteredData)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No payroll entries found. Click "Enter Payroll" to add employees.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Employee Name</th>
                                <th>Pay Period</th>
                                <th>Gross Income</th>
                                <th>Deductions</th>
                                <th>Net Income</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredData as $id => $emp): ?>
                                <?php $totals = calculateTotals($emp['earnings'], $emp['deductions']); ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($emp['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($emp['pay_period']); ?></td>
                                    <td class="text-success">₱<?php echo number_format($totals['gross'], 2); ?></td>
                                    <td class="text-danger">₱<?php echo number_format($totals['deductions'], 2); ?></td>
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
            
            <div class="mt-4 d-flex gap-2">
                <a href="?step=1" class="btn btn-primary btn-custom">
                    <i class="bi bi-plus-circle"></i> Enter Payroll
                </a>
                <a href="?step=2" class="btn btn-success btn-custom">
                    <i class="bi bi-check-circle"></i> Approve Payroll
                </a>
                <a href="?step=3" class="btn btn-info btn-custom text-white">
                    <i class="bi bi-printer"></i> Print / Send Payslips
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Step 1: Enter Payroll -->
        <?php if ($step == 1): ?>
        <div class="card-panel p-4">
            <h3 class="mb-4"><i class="bi bi-pencil-square"></i> Enter Payroll</h3>
            
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Employee Name *</label>
                    <select name="employee_name" class="form-select" required>
                        <option value="">Select Employee...</option>
                        <?php foreach ($_SESSION['employees_list'] as $empName): ?>
                            <option value="<?php echo htmlspecialchars($empName); ?>"><?php echo htmlspecialchars($empName); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email Address *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Pay Period (Month & Year) *</label>
                    <input type="month" name="pay_period" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Worked Days *</label>
                    <input type="number" name="worked_days" class="form-control" required>
                </div>
                
                <div class="col-12 mt-4">
                    <h5 class="border-bottom pb-2">EARNINGS</h5>
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
                    <h5 class="border-bottom pb-2">DEDUCTIONS</h5>
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
                    <button type="submit" name="save_payroll" class="btn btn-success btn-custom">
                        <i class="bi bi-save"></i> Save Payroll Entry
                    </button>
                    <a href="?step=0" class="btn btn-secondary btn-custom">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Step 2: Approve Payroll -->
        <?php if ($step == 2): ?>
        <div class="card-panel p-4">
            <h3 class="mb-4"><i class="bi bi-check-circle"></i> Approve Payroll</h3>
            
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
                            <a href="?step=2&remove_signature=1" class="btn btn-danger w-100" onclick="return confirm('Remove signature?')">
                                <i class="bi bi-trash"></i> Remove Signature
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>
                    <?php if (isset($_SESSION['signature'])): ?>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-check-circle"></i> Signature is set.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle"></i> No signature uploaded. You'll be asked to confirm approval.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filter -->
            <form method="GET" class="mb-3">
                <input type="hidden" name="step" value="2">
                <div class="row g-2">
                    <div class="col-md-4">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending Only</option>
                            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved Only</option>
                        </select>
                    </div>
                </div>
            </form>

            <?php 
            $filteredByStatus = array_filter($_SESSION['payroll_data'], function($emp) use ($statusFilter) {
                return $statusFilter === 'all' || $emp['status'] === $statusFilter;
            });
            ?>
            
            <?php if (empty($filteredByStatus)): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> No payroll entries to show.
                </div>
                <a href="?step=1" class="btn btn-primary">Enter Payroll</a>
            <?php else: ?>
                <form method="POST" id="approvalForm">
                    <!-- Batch Actions -->
                    <div class="mb-3 d-flex gap-2">
                        <button type="submit" name="batch_approve" class="btn btn-success">
                            <i class="bi bi-check-all"></i> Batch Approve
                        </button>
                        <button type="submit" name="cancel_approval" class="btn btn-warning">
                            <i class="bi bi-x-circle"></i> Cancel Approval
                        </button>
                    </div>

                    <?php foreach ($filteredByStatus as $id => $emp): ?>
                        <?php $totals = calculateTotals($emp['earnings'], $emp['deductions']); ?>
                        
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="selected_employees[]" value="<?php echo $id; ?>" id="emp-<?php echo $id; ?>">
                                        <label class="form-check-label" for="emp-<?php echo $id; ?>">
                                            <strong><?php echo htmlspecialchars($emp['name']); ?></strong>
                                        </label>
                                    </div>
                                    <span class="badge badge-<?php echo $emp['status']; ?>">
                                        <?php echo ucfirst($emp['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Pay Period:</strong> <?php echo htmlspecialchars($emp['pay_period']); ?></p>
                                        <p><strong>Gross Income:</strong> <span class="text-success">₱<?php echo number_format($totals['gross'], 2); ?></span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Total Deductions:</strong> <span class="text-danger">₱<?php echo number_format($totals['deductions'], 2); ?></span></p>
                                        <p><strong>Net Income:</strong> <span class="text-primary fw-bold">₱<?php echo number_format($totals['net'], 2); ?></span></p>
                                    </div>
                                </div>
                                
                                <?php if ($emp['status'] === 'pending'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="employee_id" value="<?php echo $id; ?>">
                                    <button type="submit" name="approve_single" class="btn btn-sm btn-success">
                                        <i class="bi bi-check"></i> Approve
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </form>
                
                <div class="mt-4">
                    <a href="?step=0" class="btn btn-secondary btn-custom">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Step 3: Print / Send Payslips -->
        <?php if ($step == 3): ?>
        <div class="card-panel p-4">
            <h3 class="mb-4"><i class="bi bi-printer"></i> Print / Send Payslips</h3>
            
            <!-- Search Bar -->
            <form method="GET" class="mb-4">
                <input type="hidden" name="step" value="3">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search employee name..." 
                           value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
            </form>

            <?php 
            $approvedPayrolls = array_filter($_SESSION['payroll_data'], function($emp) use ($searchQuery) {
                $matchesSearch = empty($searchQuery) || stripos($emp['name'], $searchQuery) !== false;
                $isApproved = $emp['status'] === 'approved' || $emp['status'] === 'sent';
                return $matchesSearch && $isApproved;
            });
            ?>
            
            <?php if (empty($approvedPayrolls)): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> No approved payroll entries found.
                </div>
                <a href="?step=2" class="btn btn-primary">Go to Approve Payroll</a>
            <?php else: ?>
                <form method="POST" id="sendForm">
                    <!-- Batch Actions -->
                    <div class="mb-3 d-flex gap-2">
                        <button type="button" onclick="batchDownload()" class="btn btn-success">
                            <i class="bi bi-download"></i> Batch Download PDF
                        </button>
                        <button type="submit" name="batch_send_email" class="btn btn-primary">
                            <i class="bi bi-envelope"></i> Batch Send Email
                        </button>
                    </div>

                    <?php foreach ($approvedPayrolls as $id => $emp): ?>
                        <?php $totals = calculateTotals($emp['earnings'], $emp['deductions']); ?>
                        
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input payslip-checkbox" type="checkbox" name="selected_employees[]" value="<?php echo $id; ?>" id="payslip-<?php echo $id; ?>">
                                        <label class="form-check-label" for="payslip-<?php echo $id; ?>">
                                            <strong><?php echo htmlspecialchars($emp['name']); ?></strong> - <?php echo htmlspecialchars($emp['email']); ?>
                                        </label>
                                    </div>
                                    <span class="badge badge-<?php echo $emp['status']; ?>">
                                        <?php echo ucfirst($emp['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <button type="button" class="btn btn-sm btn-info" onclick="togglePayslip('payslip-detail-<?php echo $id; ?>')">
                                    <i class="bi bi-eye"></i> View Payslip
                                </button>
                                <button type="button" onclick="downloadSinglePayslip('<?php echo $id; ?>', '<?php echo str_replace(' ', '_', $emp['name']); ?>', '<?php echo str_replace('-', '_', $emp['pay_period']); ?>')" class="btn btn-sm btn-success">
                                    <i class="bi bi-file-pdf"></i> Download PDF
                                </button>
                                <button type="button" onclick="sendSingleEmail('<?php echo $id; ?>')" class="btn btn-sm btn-primary">
                                    <i class="bi bi-envelope"></i> Send Email
                                </button>
                                
                                <!-- Collapsible Payslip -->
                                <div id="payslip-detail-<?php echo $id; ?>" class="mt-3" style="display: none;">
                                    <div class="payslip-container" id="payslip-<?php echo $id; ?>">
                                        <div class="text-center mb-4">
                                            <h3>PAYSLIP</h3>
                                            <p class="mb-0"><strong>LU Ambata Services</strong></p>
                                            <p class="text-muted">2401 Taft Avenue, Malate, Manila, Metro Manila</p>
                                        </div>

                                        <!-- Employee Info -->
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <p><strong>Employee Name:</strong> <?php echo htmlspecialchars($emp['name']); ?></p>
                                                <p><strong>Pay Period:</strong> <?php echo htmlspecialchars($emp['pay_period']); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Days Worked:</strong> <?php echo htmlspecialchars($emp['worked_days']); ?> days</p>
                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($emp['email']); ?></p>
                                            </div>
                                        </div>

                                        <!-- Earnings and Deductions -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h5 class="border-bottom pb-2 mb-3">EARNINGS</h5>
                                                <table class="table table-sm">
                                                    <?php foreach ($emp['earnings'] as $item => $amount): ?>
                                                    <?php if ($amount > 0): ?>
                                                    <tr>
                                                        <td><?php echo $item; ?>:</td>
                                                        <td class="text-end">₱<?php echo number_format($amount, 2); ?></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php endforeach; ?>
                                                    <tr class="fw-bold table-success">
                                                        <td>GROSS INCOME:</td>
                                                        <td class="text-end">₱<?php echo number_format($totals['gross'], 2); ?></td>
                                                    </tr>
                                                </table>
                                            </div>

                                            <div class="col-md-6">
                                                <h5 class="border-bottom pb-2 mb-3">DEDUCTIONS</h5>
                                                <table class="table table-sm">
                                                    <?php foreach ($emp['deductions'] as $item => $amount): ?>
                                                    <?php if ($amount > 0): ?>
                                                    <tr>
                                                        <td><?php echo $item; ?>:</td>
                                                        <td class="text-end text-danger">₱<?php echo number_format($amount, 2); ?></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php endforeach; ?>
                                                    <tr class="fw-bold table-danger">
                                                        <td>TOTAL DEDUCTIONS:</td>
                                                        <td class="text-end">₱<?php echo number_format($totals['deductions'], 2); ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- Net Pay -->
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <div class="alert alert-primary text-center">
                                                    <h4 class="mb-0">NET INCOME: ₱<?php echo number_format($totals['net'], 2); ?></h4>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Signature -->
                                        <?php if (isset($_SESSION['signature'])): ?>
                                        <div class="signature-box">
                                            <p class="mb-1"><strong>Employer Signature</strong></p>
                                            <img src="<?php echo $_SESSION['signature']; ?>" class="signature-image" alt="Signature">
                                            <div class="signature-line"></div>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Footer -->
                                        <div class="text-center mt-4 text-muted">
                                            <small>This is a system-generated payslip. Generated on <?php echo date('F d, Y h:i A'); ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </form>
                
                <div class="mt-4">
                    <a href="?step=0" class="btn btn-secondary btn-custom">
                        <i class="bi bi-house"></i> Back to Dashboard
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePayslip(id) {
            const element = document.getElementById(id);
            if (element.style.display === 'none') {
                element.style.display = 'block';
            } else {
                element.style.display = 'none';
            }
        }

        function downloadSinglePayslip(id, name, period) {
            <?php if (!isset($_SESSION['signature'])): ?>
                if (!confirm('No signature has been uploaded yet. Do you want to proceed with downloading the payslip without a signature?')) {
                    return;
                }
            <?php endif; ?>
            
            const element = document.getElementById('payslip-' + id);
            const opt = {
                margin: 10,
                filename: 'payslip_' + name + '_' + period + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save();
        }

        function batchDownload() {
            const checkboxes = document.querySelectorAll('.payslip-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one payslip to download.');
                return;
            }

            <?php if (!isset($_SESSION['signature'])): ?>
                if (!confirm('No signature has been uploaded yet. Do you want to proceed with downloading payslips without a signature?')) {
                    return;
                }
            <?php endif; ?>

            checkboxes.forEach((checkbox, index) => {
                setTimeout(() => {
                    const id = checkbox.value;
                    const element = document.getElementById('payslip-' + id);
                    const name = checkbox.closest('.card').querySelector('label').textContent.trim().split(' - ')[0];
                    
                    const opt = {
                        margin: 10,
                        filename: 'payslip_' + name.replace(/\s+/g, '_') + '.pdf',
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { scale: 2 },
                        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                    };
                    
                    html2pdf().set(opt).from(element).save();
                }, index * 1000); // Delay each download by 1 second
            });
        }

        function sendSingleEmail(id) {
            if (confirm('Send payslip via email?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="employee_id" value="' + id + '">' +
                                '<input type="hidden" name="send_email" value="1">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>