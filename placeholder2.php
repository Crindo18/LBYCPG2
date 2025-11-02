<?php
session_start();
require_once 'dbconfig.php';
require_once 'calculate_rates.php';
include 'sidebar.php';

// Get all employees for dropdown from database
$empStmt = $pdo->query("
    SELECT DISTINCT Name 
    FROM payrolldata 
    WHERE Name IS NOT NULL AND Name != ''
    ORDER BY Name ASC
");
$employees = $empStmt->fetchAll(PDO::FETCH_COLUMN);

// Initialize payroll data in session if not exists
if (!isset($_SESSION['payroll_data'])) {
    $_SESSION['payroll_data'] = [];
}

// Get current step
$step = isset($_GET['step']) ? (int)$_GET['step'] : 0;

// Handle AJAX request for work days calculation
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_work_info') {
    $employeeName = $_GET['employee'] ?? '';
    $payPeriod = $_GET['period'] ?? '';
    
    if ($employeeName && $payPeriod) {
        $date = new DateTime($payPeriod . '-01');
        $startDate = $date->format('Y-m-01');
        $endDate = $date->format('Y-m-t');
        
        // Count distinct dates within the period
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT Date) as work_days, 
                   SUM(Hours) as total_hours
            FROM payrolldata 
            WHERE Name = ? 
            AND Date BETWEEN ? AND ?
        ");
        $stmt->execute([$employeeName, $startDate, $endDate]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get email from employee_info if exists
        $emailStmt = $pdo->prepare("SELECT email FROM employee_info WHERE name = ?");
        $emailStmt->execute([$employeeName]);
        $emailResult = $emailStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get specific deduction data from payrolldata for misload/shortage
        $deductionStmt = $pdo->prepare("
            SELECT SUM(ABS(Deductions)) as total_deductions,
                   GROUP_CONCAT(DISTINCT Extra) as extra_remarks
            FROM payrolldata 
            WHERE Name = ? 
            AND Date BETWEEN ? AND ?
            AND (Deductions < 0 OR Extra LIKE '%Short%' OR Extra LIKE '%Misload%')
        ");
        $deductionStmt->execute([$employeeName, $startDate, $endDate]);
        $deductionResult = $deductionStmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate payroll data with the specific date range
        $payrollData = calculateRates($pdo, $startDate, $endDate);
        $employeeData = null;
        
        foreach ($payrollData['employees'] as $emp) {
            if ($emp['name'] === $employeeName) {
                $employeeData = $emp;
                break;
            }
        }
        
        // Calculate misload/shortage from database deductions and extra remarks
        $misloadAmount = 0;
        if (!empty($deductionResult['extra_remarks']) && 
            (stripos($deductionResult['extra_remarks'], 'short') !== false || 
             stripos($deductionResult['extra_remarks'], 'misload') !== false)) {
            $misloadAmount = $deductionResult['total_deductions'] ?? 0;
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'work_days' => $result['work_days'] ?? 0,
            'total_hours' => round($result['total_hours'] ?? 0, 2),
            'email' => $emailResult['email'] ?? '',
            'earnings' => [
                'basic_rate' => $employeeData ? $employeeData['totals']['regular'] : 0,
                'overtime' => $employeeData ? $employeeData['totals']['overtime'] : 0,
                'rate2' => 0,
                'allowance' => $employeeData ? $employeeData['totals']['allowance'] : 0,
                'night_diff' => $employeeData ? $employeeData['totals']['night'] : 0,
                'holiday' => $employeeData ? $employeeData['totals']['holiday'] : 0,
                'sil' => $employeeData ? $employeeData['totals']['extra'] : 0
            ],
            'deductions' => [
                'sss' => $employeeData ? $employeeData['totals']['sss'] : 0,
                'philhealth' => $employeeData ? $employeeData['totals']['phic'] : 0,
                'pagibig' => $employeeData ? $employeeData['totals']['hdmf'] : 0,
                'gov_loan' => $employeeData ? $employeeData['totals']['loan'] : 0,
                'late' => $employeeData ? $employeeData['totals']['late'] : 0,
                'misload' => $misloadAmount,
                'uniform' => 0
            ]
        ]);
        exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_payroll'])) {
        $employee_id = uniqid();
        $employeeName = $_POST['employee_name'];
        $email = $_POST['email'];
        $payPeriod = $_POST['pay_period'];
        $workedDays = (int)$_POST['worked_days'];
        $totalHours = (float)$_POST['total_hours'];
        
        // Get date range
        $date = new DateTime($payPeriod . '-01');
        $startDate = $date->format('Y-m-01');
        $endDate = $date->format('Y-m-t');
        
        // Save to employee_info table
        $checkStmt = $pdo->prepare("SELECT id FROM employee_info WHERE name = ?");
        $checkStmt->execute([$employeeName]);
        
        if (!$checkStmt->fetch()) {
            $insertStmt = $pdo->prepare("INSERT INTO employee_info (name, email) VALUES (?, ?)");
            $insertStmt->execute([$employeeName, $email]);
        } else {
            $updateStmt = $pdo->prepare("UPDATE employee_info SET email = ? WHERE name = ?");
            $updateStmt->execute([$email, $employeeName]);
        }
        
        // Update payrolldata with new deductions and extras
        $misloadAmount = (float)$_POST['misload'];
        $uniformAmount = (float)$_POST['uniform'];
        $silAmount = (float)$_POST['sil'];
        
        if ($misloadAmount > 0) {
            // Add misload as negative deduction and short remark
            $updateDeductionStmt = $pdo->prepare("
                UPDATE payrolldata 
                SET Deductions = -?,
                    Extra = CASE 
                        WHEN Extra IS NULL OR Extra = '' THEN 'Short' 
                        ELSE CONCAT(Extra, ', Short') 
                    END
                WHERE Name = ? 
                AND Date BETWEEN ? AND ?
            ");
            $updateDeductionStmt->execute([$misloadAmount, $employeeName, $startDate, $endDate]);
        }
        
        if ($uniformAmount > 0) {
            // Add uniform as negative deduction
            $updateUniformStmt = $pdo->prepare("
                UPDATE payrolldata 
                SET Deductions = Deductions - ? 
                WHERE Name = ? 
                AND Date BETWEEN ? AND ?
            ");
            $updateUniformStmt->execute([$uniformAmount, $employeeName, $startDate, $endDate]);
        }
        
        if ($silAmount > 0) {
            // Add SIL as extra
            $updateSilStmt = $pdo->prepare("
                UPDATE payrolldata 
                SET Extra = CASE 
                    WHEN Extra IS NULL OR Extra = '' THEN 'SIL' 
                    ELSE CONCAT(Extra, ', SIL') 
                END
                WHERE Name = ? 
                AND Date BETWEEN ? AND ?
            ");
            $updateSilStmt->execute([$employeeName, $startDate, $endDate]);
        }
        
        // Recalculate payroll data after updates
        $payrollData = calculateRates($pdo, $startDate, $endDate);
        $employeeData = null;
        
        foreach ($payrollData['employees'] as $emp) {
            if ($emp['name'] === $employeeName) {
                $employeeData = $emp;
                break;
            }
        }
        
        // Save payroll with updated calculations
        $_SESSION['payroll_data'][$employee_id] = [
            'name' => $employeeName,
            'pay_period' => $payPeriod,
            'worked_days' => $workedDays,
            'total_hours' => $totalHours,
            'earnings' => [
                'Basic Rate' => (float)$_POST['basic_rate'],
                'Overtime Pay' => (float)$_POST['overtime'],
                'Rate2' => (float)$_POST['rate2'],
                'Allowance' => (float)$_POST['allowance'],
                'Night Differential' => (float)$_POST['night_diff'],
                'Holiday' => (float)$_POST['holiday'],
                'SIL' => (float)$_POST['sil']
            ],
            'deductions' => [
                'SSS' => (float)$_POST['sss'],
                'PAGIBIG' => (float)$_POST['pagibig'],
                'PHILHEALTH' => (float)$_POST['philhealth'],
                'Government Loan' => (float)$_POST['gov_loan'],
                'Late/Absent' => (float)$_POST['late'],
                'Misload/Shortage' => (float)$_POST['misload'],
                'Uniform/CA' => (float)$_POST['uniform']
            ],
            'status' => 'pending',
            'email' => $email,
            'business_unit' => $employeeData ? $employeeData['business_unit'] : 'N/A',
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        $success_message = "Payroll entry saved successfully and database updated!";
    }
    
    if (isset($_POST['batch_approve']) && !isset($_POST['confirm_no_signature'])) {
        if (!empty($_POST['selected_employees']) && !isset($_SESSION['signature'])) {
            $require_signature_confirmation = true;
        } elseif (!empty($_POST['selected_employees'])) {
            foreach ($_POST['selected_employees'] as $emp_id) {
                if (isset($_SESSION['payroll_data'][$emp_id])) {
                    $_SESSION['payroll_data'][$emp_id]['status'] = 'approved';
                }
            }
            $success_message = "Selected payrolls approved!";
        }
    } elseif (isset($_POST['batch_approve']) && isset($_POST['confirm_no_signature'])) {
        foreach ($_POST['selected_employees'] as $emp_id) {
            if (isset($_SESSION['payroll_data'][$emp_id])) {
                $_SESSION['payroll_data'][$emp_id]['status'] = 'approved';
            }
        }
        $success_message = "Selected payrolls approved!";
    }
    
    if (isset($_POST['approve_single']) && !isset($_POST['confirm_no_signature'])) {
        $employee_id = $_POST['employee_id'];
        if (!isset($_SESSION['signature'])) {
            $require_signature_confirmation_single = $employee_id;
        } else {
            $_SESSION['payroll_data'][$employee_id]['status'] = 'approved';
            $success_message = "Payroll approved!";
        }
    } elseif (isset($_POST['approve_single']) && isset($_POST['confirm_no_signature'])) {
        $employee_id = $_POST['employee_id'];
        $_SESSION['payroll_data'][$employee_id]['status'] = 'approved';
        $success_message = "Payroll approved!";
    }
    
    if (isset($_POST['cancel_approval'])) {
        if (!empty($_POST['selected_employees'])) {
            foreach ($_POST['selected_employees'] as $emp_id) {
                if (isset($_SESSION['payroll_data'][$emp_id])) {
                    $_SESSION['payroll_data'][$emp_id]['status'] = 'pending';
                }
            }
            $success_message = "Approval cancelled!";
        }
    }
    
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
                $signature_message = "Signature uploaded!";
            }
        }
    }
}

if (isset($_GET['remove_signature'])) {
    if (isset($_SESSION['signature']) && file_exists($_SESSION['signature'])) {
        unlink($_SESSION['signature']);
    }
    unset($_SESSION['signature']);
    header('Location: employee_payslip_portal.php?step=' . $step);
    exit;
}

function calculateTotals($earnings, $deductions) {
    return [
        'gross' => array_sum($earnings),
        'deductions' => array_sum($deductions),
        'net' => array_sum($earnings) - array_sum($deductions)
    ];
}

function formatPayPeriod($period) {
    $date = DateTime::createFromFormat('Y-m', $period);
    return $date ? $date->format('F Y') : $period;
}

function filterByDateRange($emp, $fromMonth, $toMonth) {
    if (empty($fromMonth) && empty($toMonth)) {
        return true;
    }
    
    $empPeriod = $emp['pay_period'];
    $empDate = DateTime::createFromFormat('Y-m', $empPeriod);
    
    if (!empty($fromMonth)) {
        $fromDate = DateTime::createFromFormat('Y-m', $fromMonth);
        if ($empDate < $fromDate) {
            return false;
        }
    }
    
    if (!empty($toMonth)) {
        $toDate = DateTime::createFromFormat('Y-m', $toMonth);
        if ($empDate > $toDate) {
            return false;
        }
    }
    
    return true;
}

$searchQuery = $_GET['employee'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';
$fromMonth = $_GET['from_month'] ?? '';
$toMonth = $_GET['to_month'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LU Ambata Services - Payroll System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { margin-left: 250px; padding: 20px; transition: margin-left 0.3s; }
        .card-panel { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: 1px solid #e9ecef; }
        .progress-steps { display: flex; justify-content: space-between; margin-bottom: 30px; position: relative; }
        .progress-steps::before { content: ''; position: absolute; top: 25px; left: 0; right: 0; height: 2px; background: #dee2e6; z-index: 1; }
        .step-item { flex: 1; text-align: center; position: relative; z-index: 2; }
        .step-circle { width: 50px; height: 50px; border-radius: 50%; background: white; border: 2px solid #dee2e6; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: bold; }
        .step-item.active .step-circle { background: #667eea; border-color: #667eea; color: white; }
        .step-item.completed .step-circle { background: #28a745; border-color: #28a745; color: white; }
        .step-label { font-size: 14px; color: #6c757d; }
        .step-item.active .step-label { color: #667eea; font-weight: 600; }
        .payslip-container { max-width: 850px; margin: 0 auto; background: white; border: 2px solid #dee2e6; border-radius: 8px; padding: 40px; }
        .signature-box { text-align: center; margin-top: 40px; }
        .signature-line { border-top: 2px solid #2c3e50; width: 250px; margin: 60px auto 10px; }
        .signature-image { max-width: 200px; max-height: 60px; margin-bottom: 5px; }
        @media print { .no-print, .sidebar, .btn, nav, .progress-steps { display: none !important; } .main-content { margin-left: 0 !important; width: 100% !important; } }
    </style>
</head>
<body>
    <div class="main-content">
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
                <div class="step-label">Approve</div>
            </div>
            <div class="step-item <?php echo $step == 3 ? 'active' : ''; ?>">
                <div class="step-circle">3</div>
                <div class="step-label">Print</div>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> <?= $success_message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if (isset($signature_message)): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> <?= $signature_message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if (isset($require_signature_confirmation)): ?>
        <div class="alert alert-warning">
            <h5><i class="bi bi-exclamation-triangle"></i> No Signature</h5>
            <p>Approve without signature?</p>
            <form method="POST">
                <?php foreach ($_POST['selected_employees'] as $emp_id): ?>
                <input type="hidden" name="selected_employees[]" value="<?= $emp_id; ?>">
                <?php endforeach; ?>
                <input type="hidden" name="confirm_no_signature" value="1">
                <button type="submit" name="batch_approve" class="btn btn-warning">Yes, Approve</button>
                <a href="?step=<?= $step; ?>" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
        <?php endif; ?>
        <?php if (isset($require_signature_confirmation_single)): ?>
        <div class="alert alert-warning">
            <h5><i class="bi bi-exclamation-triangle"></i> No Signature</h5>
            <p>Approve without signature?</p>
            <form method="POST">
                <input type="hidden" name="employee_id" value="<?= $require_signature_confirmation_single; ?>">
                <input type="hidden" name="confirm_no_signature" value="1">
                <button type="submit" name="approve_single" class="btn btn-warning">Yes, Approve</button>
                <a href="?step=<?= $step; ?>" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
        <?php endif; ?>

        <!-- STEP 0: Overview -->
        <?php if ($step == 0): ?>
        <div class="card-panel p-4">
            <h2 class="mb-4"><i class="bi bi-speedometer2"></i> Payroll Overview</h2>
            <form method="GET" class="row g-3 mb-4">
                <input type="hidden" name="step" value="0">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Employee</label>
                    <select name="employee" class="form-select">
                        <option value="">All Employees</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?= htmlspecialchars($emp) ?>" <?= $emp === $searchQuery ? 'selected' : '' ?>><?= htmlspecialchars($emp) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">From Month</label>
                    <input type="month" name="from_month" class="form-control" value="<?= $fromMonth ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">To Month</label>
                    <input type="month" name="to_month" class="form-control" value="<?= $toMonth ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Search</button>
                </div>
            </form>
            <?php 
            $filteredData = array_filter($_SESSION['payroll_data'], function($emp) use ($searchQuery, $statusFilter, $fromMonth, $toMonth) {
                $matchesEmployee = empty($searchQuery) || $emp['name'] === $searchQuery;
                $matchesStatus = $statusFilter === 'all' || $emp['status'] === $statusFilter;
                $matchesDate = filterByDateRange($emp, $fromMonth, $toMonth);
                
                return $matchesEmployee && $matchesStatus && $matchesDate;
            });
            ?>
            <?php if (empty($filteredData)): ?>
            <div class="alert alert-info"><i class="bi bi-info-circle"></i> No entries found.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr><th>Employee</th><th>Period</th><th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredData as $id => $emp): $totals = calculateTotals($emp['earnings'], $emp['deductions']); ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($emp['name']); ?></strong></td>
                            <td><?= formatPayPeriod($emp['pay_period']); ?></td>
                            <td class="text-end text-success">₱<?= number_format($totals['gross'], 2); ?></td>
                            <td class="text-end text-danger">₱<?= number_format($totals['deductions'], 2); ?></td>
                            <td class="text-end"><strong>₱<?= number_format($totals['net'], 2); ?></strong></td>
                            <td><span class="badge bg-<?= $emp['status'] === 'pending' ? 'warning' : 'success'; ?>"><?= ucfirst($emp['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <div class="mt-4 d-flex gap-2">
                <a href="?step=1" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Enter Payroll</a>
                <a href="?step=2" class="btn btn-success"><i class="bi bi-check-circle"></i> Approve</a>
                <a href="?step=3" class="btn btn-info text-white"><i class="bi bi-printer"></i> Print</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- STEP 1: Enter Payroll -->
        <?php if ($step == 1): ?>
        <div class="card-panel p-4">
            <h3 class="mb-4"><i class="bi bi-pencil-square"></i> Enter Payroll</h3>
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Employee Name *</label>
                    <select name="employee_name" id="employee_name" class="form-select" required>
                        <option value="">Select...</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?= htmlspecialchars($emp); ?>"><?= htmlspecialchars($emp); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email *</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Pay Period *</label>
                    <input type="month" name="pay_period" id="pay_period" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Worked Days</label>
                    <input type="number" name="worked_days" id="worked_days" class="form-control" readonly style="background-color:#e9ecef;">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Total Hours</label>
                    <input type="number" step="0.01" name="total_hours" id="total_hours" class="form-control" readonly style="background-color:#e9ecef;">
                </div>
                
                <div class="col-12 mt-4">
                    <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-cash-coin"></i> Earnings</h5>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Basic Rate</label>
                    <input type="number" step="0.01" name="basic_rate" id="basic_rate" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Overtime Pay</label>
                    <input type="number" step="0.01" name="overtime" id="overtime" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Rate2</label>
                    <input type="number" step="0.01" name="rate2" id="rate2" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Allowance</label>
                    <input type="number" step="0.01" name="allowance" id="allowance" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Night Differential</label>
                    <input type="number" step="0.01" name="night_diff" id="night_diff" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Holiday</label>
                    <input type="number" step="0.01" name="holiday" id="holiday" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">SIL</label>
                    <input type="number" step="0.01" name="sil" id="sil" class="form-control" value="0">
                </div>
                
                <div class="col-12 mt-4">
                    <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-wallet2"></i> Deductions</h5>
                </div>
                <div class="col-md-4">
                    <label class="form-label">SSS</label>
                    <input type="number" step="0.01" name="sss" id="sss" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">PHILHEALTH</label>
                    <input type="number" step="0.01" name="philhealth" id="philhealth" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">PAGIBIG</label>
                    <input type="number" step="0.01" name="pagibig" id="pagibig" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Government Loan</label>
                    <input type="number" step="0.01" name="gov_loan" id="gov_loan" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Late/Absent</label>
                    <input type="number" step="0.01" name="late" id="late" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Misload/Shortage</label>
                    <input type="number" step="0.01" name="misload" id="misload" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Uniform/CA</label>
                    <input type="number" step="0.01" name="uniform" id="uniform" class="form-control" value="0">
                </div>
                
                <div class="col-12 mt-4">
                    <button type="submit" name="save_payroll" class="btn btn-success"><i class="bi bi-save"></i> Save</button>
                    <a href="?step=0" class="btn btn-secondary"><i class="bi bi-house"></i> Dashboard</a>
                    <?php if (!empty($_SESSION['payroll_data'])): ?>
                    <a href="?step=2" class="btn btn-primary">Continue <i class="bi bi-arrow-right"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <script>
        document.getElementById('employee_name').addEventListener('change', fetchWorkInfo);
        document.getElementById('pay_period').addEventListener('change', fetchWorkInfo);
        function fetchWorkInfo() {
            const employee = document.getElementById('employee_name').value;
            const period = document.getElementById('pay_period').value;
            if (employee && period) {
                fetch(`?ajax=get_work_info&employee=${encodeURIComponent(employee)}&period=${encodeURIComponent(period)}`)
                    .then(r => r.json())
                    .then(data => {
                        document.getElementById('worked_days').value = data.work_days;
                        document.getElementById('total_hours').value = data.total_hours;
                        if(data.email) document.getElementById('email').value = data.email;
                        
                        // Populate earnings
                        document.getElementById('basic_rate').value = data.earnings.basic_rate || 0;
                        document.getElementById('overtime').value = data.earnings.overtime || 0;
                        document.getElementById('rate2').value = data.earnings.rate2 || 0;
                        document.getElementById('allowance').value = data.earnings.allowance || 0;
                        document.getElementById('night_diff').value = data.earnings.night_diff || 0;
                        document.getElementById('holiday').value = data.earnings.holiday || 0;
                        document.getElementById('sil').value = data.earnings.sil || 0;
                        
                        // Populate deductions
                        document.getElementById('sss').value = data.deductions.sss || 0;
                        document.getElementById('philhealth').value = data.deductions.philhealth || 0;
                        document.getElementById('pagibig').value = data.deductions.pagibig || 0;
                        document.getElementById('gov_loan').value = data.deductions.gov_loan || 0;
                        document.getElementById('late').value = data.deductions.late || 0;
                        document.getElementById('misload').value = data.deductions.misload || 0;
                        document.getElementById('uniform').value = data.deductions.uniform || 0;
                    });
            }
        }
        </script>
        <?php endif; ?>

        <!-- STEP 2: Approve -->
        <?php if ($step == 2): ?>
        <div class="card-panel p-4">
            <h3 class="mb-4"><i class="bi bi-check-circle"></i> Approve Payroll</h3>
            <div class="card mb-4 bg-light">
                <div class="card-body">
                    <h5><i class="bi bi-pen"></i> Signature</h5>
                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <div class="col-md-6"><input type="file" name="signature" class="form-control" accept=".png,.jpg,.jpeg"></div>
                        <div class="col-md-3"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload"></i> Upload</button></div>
                        <?php if (isset($_SESSION['signature'])): ?>
                        <div class="col-md-3"><a href="?step=2&remove_signature=1" class="btn btn-danger w-100" onclick="return confirm('Remove?')"><i class="bi bi-trash"></i> Remove</a></div>
                        <?php endif; ?>
                    </form>
                    <?php if (isset($_SESSION['signature'])): ?>
                    <div class="alert alert-info mt-3 mb-0"><i class="bi bi-check-circle"></i> Set</div>
                    <?php else: ?>
                    <div class="alert alert-warning mt-3 mb-0"><i class="bi bi-exclamation-triangle"></i> No signature</div>
                    <?php endif; ?>
                </div>
            </div>
            <form method="GET" class="row g-3 mb-4">
                <input type="hidden" name="step" value="2">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Employee</label>
                    <select name="employee" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?= htmlspecialchars($emp) ?>" <?= $emp === $searchQuery ? 'selected' : '' ?>><?= htmlspecialchars($emp) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">From Month</label>
                    <input type="month" name="from_month" class="form-control" value="<?= $fromMonth ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">To Month</label>
                    <input type="month" name="to_month" class="form-control" value="<?= $toMonth ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Search</button></div>
            </form>
            <?php 
            $filtered = array_filter($_SESSION['payroll_data'], function($emp) use ($searchQuery, $statusFilter, $fromMonth, $toMonth) {
                $matchesEmployee = empty($searchQuery) || $emp['name'] === $searchQuery;
                $matchesStatus = $statusFilter === 'all' || $emp['status'] === $statusFilter;
                $matchesDate = filterByDateRange($emp, $fromMonth, $toMonth);
                
                return $matchesEmployee && $matchesStatus && $matchesDate;
            });
            ?>
            <?php if (empty($filtered)): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> No entries</div>
            <?php else: ?>
            <form method="POST">
                <div class="mb-3 d-flex gap-2">
                    <button type="submit" name="batch_approve" class="btn btn-success"><i class="bi bi-check-all"></i> Batch Approve</button>
                    <button type="submit" name="cancel_approval" class="btn btn-warning"><i class="bi bi-x-circle"></i> Cancel Approval</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr><th width="50"><input type="checkbox" class="form-check-input" id="selectAll"></th><th>Employee</th><th>Period</th><th class="text-end">Gross</th><th class="text-end">Net</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filtered as $id => $emp): $totals = calculateTotals($emp['earnings'], $emp['deductions']); ?>
                            <tr>
                                <td><input class="form-check-input emp-checkbox" type="checkbox" name="selected_employees[]" value="<?= $id; ?>"></td>
                                <td><strong><?= htmlspecialchars($emp['name']); ?></strong></td>
                                <td><?= formatPayPeriod($emp['pay_period']); ?></td>
                                <td class="text-end text-success">₱<?= number_format($totals['gross'], 2); ?></td>
                                <td class="text-end"><strong>₱<?= number_format($totals['net'], 2); ?></strong></td>
                                <td><span class="badge bg-<?= $emp['status'] === 'pending' ? 'warning' : 'success'; ?>"><?= ucfirst($emp['status']); ?></span></td>
                                <td>
                                    <?php if ($emp['status'] === 'pending'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="employee_id" value="<?= $id; ?>">
                                        <button type="submit" name="approve_single" class="btn btn-sm btn-success"><i class="bi bi-check"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <script>
            document.getElementById('selectAll').addEventListener('change', function() {
                document.querySelectorAll('.emp-checkbox').forEach(cb => cb.checked = this.checked);
            });
            </script>
            <?php endif; ?>
            <div class="mt-4">
                <a href="?step=0" class="btn btn-secondary"><i class="bi bi-house"></i> Dashboard</a>
                <a href="?step=1" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
                <a href="?step=3" class="btn btn-primary">Continue <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
        <?php endif; ?>

        <!-- STEP 3: Print -->
        <?php if ($step == 3): ?>
        <div class="card-panel p-4">
            <h3 class="mb-4"><i class="bi bi-printer"></i> Print Payslips</h3>
            <form method="GET" class="row g-3 mb-4">
                <input type="hidden" name="step" value="3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Employee</label>
                    <select name="employee" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?= htmlspecialchars($emp) ?>" <?= $emp === $searchQuery ? 'selected' : '' ?>><?= htmlspecialchars($emp) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">From Month</label>
                    <input type="month" name="from_month" class="form-control" value="<?= $fromMonth ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">To Month</label>
                    <input type="month" name="to_month" class="form-control" value="<?= $toMonth ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
                </div>
            </form>
            <?php 
            $approvedPayrolls = array_filter($_SESSION['payroll_data'], function($emp) use ($searchQuery, $fromMonth, $toMonth) {
                $matchesEmployee = empty($searchQuery) || $emp['name'] === $searchQuery;
                $matchesDate = filterByDateRange($emp, $fromMonth, $toMonth);
                
                return $matchesEmployee && $matchesDate && $emp['status'] === 'approved';
            });
            ?>
            <?php if (empty($approvedPayrolls)): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> No approved entries</div>
            <a href="?step=2" class="btn btn-primary">Go to Approve</a>
            <?php else: ?>
            <form method="POST">
                <div class="mb-3 d-flex gap-2">
                    <button type="button" onclick="batchDownload()" class="btn btn-success"><i class="bi bi-download"></i> Batch Download</button>
                </div>
                <?php foreach ($approvedPayrolls as $id => $emp): $totals = calculateTotals($emp['earnings'], $emp['deductions']); ?>
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input class="form-check-input payslip-checkbox" type="checkbox" name="selected_employees[]" value="<?= $id; ?>" id="ps-<?= $id; ?>">
                                <label class="form-check-label" for="ps-<?= $id; ?>"><strong><?= htmlspecialchars($emp['name']); ?></strong> - <?= htmlspecialchars($emp['email']); ?></label>
                            </div>
                            <span class="badge bg-success"><?= ucfirst($emp['status']); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <button type="button" class="btn btn-sm btn-info" onclick="togglePayslip('detail-<?= $id; ?>')"><i class="bi bi-eye"></i> View Summary</button>
                        <a href="payslip_pdf.php?id=<?= $id; ?>&name=<?= urlencode($emp['name']) ?>&start=<?= $emp['start_date'] ?>&end=<?= $emp['end_date'] ?>" target="_blank" class="btn btn-sm btn-success"><i class="bi bi-file-pdf"></i> Download</a>
                        <div id="detail-<?= $id; ?>" style="display:none;" class="mt-3">
                            <div class="payslip-container" id="payslip-<?= $id; ?>">
                                <div class="text-center mb-4">
                                    <h3>PAYSLIP</h3>
                                    <p class="mb-0"><strong>LU Ambata Services</strong></p>
                                    <p class="text-muted">2401 Taft Avenue, Malate, Manila, Metro Manila</p>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <p><strong>Employee:</strong> <?= htmlspecialchars($emp['name']); ?></p>
                                        <p><strong>Business Unit:</strong> <?= htmlspecialchars($emp['business_unit'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Period:</strong> <?= formatPayPeriod($emp['pay_period']); ?></p>
                                        <p><strong>Days Worked:</strong> <?= htmlspecialchars($emp['worked_days']); ?> (<?= htmlspecialchars($emp['total_hours']); ?> hrs)</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="border-bottom pb-2 mb-3">EARNINGS</h5>
                                        <table class="table table-sm">
                                            <?php foreach ($emp['earnings'] as $item => $amount): if ($amount > 0): ?>
                                            <tr><td><?= $item; ?>:</td><td class="text-end">₱<?= number_format($amount, 2); ?></td></tr>
                                            <?php endif; endforeach; ?>
                                            <tr class="fw-bold table-success"><td>GROSS:</td><td class="text-end">₱<?= number_format($totals['gross'], 2); ?></td></tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="border-bottom pb-2 mb-3">DEDUCTIONS</h5>
                                        <table class="table table-sm">
                                            <?php foreach ($emp['deductions'] as $item => $amount): if ($amount > 0): ?>
                                            <tr><td><?= $item; ?>:</td><td class="text-end text-danger">₱<?= number_format($amount, 2); ?></td></tr>
                                            <?php endif; endforeach; ?>
                                            <tr class="fw-bold table-danger"><td>TOTAL:</td><td class="text-end">₱<?= number_format($totals['deductions'], 2); ?></td></tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="alert alert-primary text-center"><h4 class="mb-0">NET: ₱<?= number_format($totals['net'], 2); ?></h4></div>
                                    </div>
                                </div>
                                <?php if (isset($_SESSION['signature'])): ?>
                                <div class="signature-box">
                                    <p class="mb-1"><strong>Employer Signature</strong></p>
                                    <img src="<?= $_SESSION['signature']; ?>" class="signature-image" alt="Signature">
                                    <div class="signature-line"></div>
                                </div>
                                <?php endif; ?>
                                <div class="text-center mt-4 text-muted"><small>System-generated on <?= date('F d, Y h:i A'); ?></small></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </form>
            <?php endif; ?>
            <div class="mt-4">
                <a href="?step=0" class="btn btn-secondary"><i class="bi bi-house"></i> Dashboard</a>
                <a href="?step=2" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
            </div>
        </div>
        <script>
        function togglePayslip(id) {
            const el = document.getElementById(id);
            el.style.display = el.style.display === 'none' ? 'block' : 'none';
        }
        function batchDownload() {
            const cbs = document.querySelectorAll('.payslip-checkbox:checked');
            if (cbs.length === 0) { alert('Select payslips'); return; }
            <?php if (!isset($_SESSION['signature'])): ?>
            if (!confirm('No signature. Continue?')) return;
            <?php endif; ?>
            cbs.forEach((cb, i) => {
                setTimeout(() => {
                    const id = cb.value;
                    const empData = <?= json_encode($approvedPayrolls); ?>;
                    if (empData[id]) {
                        const emp = empData[id];
                        window.open(`payslip_pdf.php?id=${id}&name=${encodeURIComponent(emp.name)}&start=${emp.start_date}&end=${emp.end_date}`, '_blank');
                    }
                }, i * 500);
            });
        }
        </script>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>