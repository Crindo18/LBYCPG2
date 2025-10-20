<?php
require_once 'dbconfig.php';
require_once 'calculate_rates.php';
include 'sidebar.php';

// Get all employees for dropdown
$empStmt = $pdo->query("
    SELECT DISTINCT Name 
    FROM payrolldata 
    WHERE Name IS NOT NULL AND Name != ''
    ORDER BY Name ASC
");
$employees = $empStmt->fetchAll(PDO::FETCH_COLUMN);

// Handle form submission
$selectedEmployee = $_GET['employee'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$payslipData = null;

if ($selectedEmployee && $startDate && $endDate) {
    $payrollData = calculateRates($pdo, $startDate, $endDate);
    
    foreach ($payrollData['employees'] as $emp) {
        if ($emp['name'] === $selectedEmployee) {
            $payslipData = $emp;
            break;
        }
    }
}
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-person-badge"></i> Employee Payslip Portal</h2>
    </div>

    <!-- Selection Form -->
    <div class="card-panel p-4 mb-4">
        <h5 class="mb-3">Select Employee and Date Range</h5>
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Employee</label>
                <select name="employee" class="form-select" required>
                    <option value="">Select Employee...</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= htmlspecialchars($emp) ?>" <?= $emp === $selectedEmployee ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> View Payslip
                </button>
            </div>
        </form>
    </div>

    <?php if ($payslipData): ?>
        <?php $totals = $payslipData['totals']; ?>
        
        <!-- Payslip Display -->
        <div class="card-panel p-4" id="payslip-content">
            <div class="text-center mb-4">
                <h3>PAYSLIP</h3>
                <p class="mb-0"><strong>LU Ambata Services</strong></p>
                <p class="text-muted">2401 Taft Avenue, Malate, Manila, Metro Manila</p>
            </div>

            <!-- Employee Info -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <p><strong>Employee Name:</strong> <?= htmlspecialchars($payslipData['name']) ?></p>
                    <p><strong>Business Unit:</strong> <?= htmlspecialchars($payslipData['business_unit']) ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Pay Period:</strong> <?= date('F d, Y', strtotime($startDate)) ?> - <?= date('F d, Y', strtotime($endDate)) ?></p>
                    <p><strong>Days Worked:</strong> <?= count($payslipData['per_day']) ?> days</p>
                </div>
            </div>

            <!-- Earnings and Deductions -->
            <div class="row">
                <div class="col-md-6">
                    <h5 class="border-bottom pb-2 mb-3">EARNINGS</h5>
                    <table class="table table-sm">
                        <tr>
                            <td>Regular Pay:</td>
                            <td class="text-end">₱<?= number_format($totals['regular'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Overtime Pay:</td>
                            <td class="text-end">₱<?= number_format($totals['overtime'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Night Differential:</td>
                            <td class="text-end">₱<?= number_format($totals['night'], 2) ?></td>
                        </tr>
                        <?php if ($totals['bonus'] > 0): ?>
                        <tr>
                            <td>Cashier Bonus:</td>
                            <td class="text-end">₱<?= number_format($totals['bonus'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td>Allowance:</td>
                            <td class="text-end">₱<?= number_format($totals['allowance'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Holiday Pay:</td>
                            <td class="text-end">₱<?= number_format($totals['holiday'], 2) ?></td>
                        </tr>
                        <?php if ($totals['extra'] > 0): ?>
                        <tr>
                            <td>SIL/Bonus:</td>
                            <td class="text-end">₱<?= number_format($totals['extra'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="fw-bold table-success">
                            <td>GROSS PAY:</td>
                            <td class="text-end">₱<?= number_format($totals['gross'], 2) ?></td>
                        </tr>
                    </table>
                </div>

                <div class="col-md-6">
                    <h5 class="border-bottom pb-2 mb-3">DEDUCTIONS</h5>
                    <table class="table table-sm">
                        <?php if ($totals['late'] > 0): ?>
                        <tr>
                            <td>Late Deduction:</td>
                            <td class="text-end text-danger">₱<?= number_format($totals['late'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($totals['sss'] > 0): ?>
                        <tr>
                            <td>SSS:</td>
                            <td class="text-end text-danger">₱<?= number_format($totals['sss'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($totals['phic'] > 0): ?>
                        <tr>
                            <td>PhilHealth:</td>
                            <td class="text-end text-danger">₱<?= number_format($totals['phic'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($totals['hdmf'] > 0): ?>
                        <tr>
                            <td>HDMF (Pag-IBIG):</td>
                            <td class="text-end text-danger">₱<?= number_format($totals['hdmf'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($totals['govt'] > 0 && ($totals['sss'] == 0 && $totals['phic'] == 0 && $totals['hdmf'] == 0)): ?>
                        <tr>
                            <td>Government Contributions:</td>
                            <td class="text-end text-danger">₱<?= number_format($totals['govt'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($totals['loan'] > 0): ?>
                        <tr>
                            <td>Loans:</td>
                            <td class="text-end text-danger">₱<?= number_format($totals['loan'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="fw-bold table-danger">
                            <td>TOTAL DEDUCTIONS:</td>
                            <td class="text-end">₱<?= number_format($totals['total_deductions'], 2) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Net Pay -->
            <div class="row mt-3">
                <div class="col-12">
                    <div class="alert alert-primary text-center">
                        <h4 class="mb-0">NET PAY: ₱<?= number_format($totals['net'], 2) ?></h4>
                    </div>
                </div>
            </div>

            <!-- Daily Breakdown -->
            <div class="row mt-4">
                <div class="col-12">
                    <h5 class="border-bottom pb-2 mb-3">DAILY BREAKDOWN</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Role</th>
                                    <th class="text-end">Hours</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payslipData['per_day'] as $day): ?>
                                <tr>
                                    <td><?= date('M d, Y', strtotime($day['date'])) ?></td>
                                    <td><?= htmlspecialchars($day['role']) ?></td>
                                    <td class="text-end"><?= number_format($day['hours'], 2) ?></td>
                                    <td>
                                        <?php if ($day['remarks']): ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($day['remarks']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($day['holiday']): ?>
                                            <span class="badge bg-warning"><?= ucfirst($day['holiday']) ?> Holiday</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-4 text-muted">
                <small>This is a system-generated payslip. Generated on <?= date('F d, Y h:i A') ?></small>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="text-center mt-3 no-print">
            <button class="btn btn-success btn-lg" onclick="window.print()">
                <i class="bi bi-printer"></i> Print Payslip
            </button>
            <a href="payslip_pdf.php?name=<?= urlencode($selectedEmployee) ?>&start=<?= $startDate ?>&end=<?= $endDate ?>" 
               class="btn btn-danger btn-lg" target="_blank">
                <i class="bi bi-file-pdf"></i> Download PDF
            </a>
        </div>

    <?php elseif ($selectedEmployee): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> No payroll data found for <strong><?= htmlspecialchars($selectedEmployee) ?></strong> 
            in the selected date range.
        </div>
    <?php endif; ?>
</div>

<style>
@media print {
    .sidebar, .btn, .no-print, nav, .card-panel:first-child { display: none !important; }
    .main-content { margin-left: 0 !important; width: 100% !important; }
    #payslip-content { border: 2px solid #000; }
}
</style>