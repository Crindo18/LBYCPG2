<?php
require_once 'dbconfig.php';
require_once 'calculate_rates.php';
include 'sidebar.php';

$employeeName = $_GET['name'] ?? '';
$startDate = $_GET['start'] ?? date('Y-m-01');
$endDate = $_GET['end'] ?? date('Y-m-t');

if (empty($employeeName)) {
    die('<div class="main-content"><div class="alert alert-danger">Employee name is required.</div></div>');
}

// Get payroll data using calculate_rates
$payrollData = calculateRates($pdo, $startDate, $endDate);
$employeeData = null;

foreach ($payrollData['employees'] as $emp) {
    if ($emp['name'] === $employeeName) {
        $employeeData = $emp;
        break;
    }
}

if (!$employeeData) {
    die('<div class="main-content"><div class="alert alert-danger">No payroll data found for this employee in the selected period.</div></div>');
}

$totals = $employeeData['totals'];
$perDay = $employeeData['per_day'];
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-receipt"></i> Payslip Details</h2>
        <div>
            <a href="salary_summary.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Summary
            </a>
            <a href="payslip_pdf.php?name=<?= urlencode($employeeName) ?>&start=<?= $startDate ?>&end=<?= $endDate ?>" 
               class="btn btn-danger" target="_blank">
                <i class="bi bi-file-pdf"></i> Download PDF
            </a>
            <button class="btn btn-success" onclick="window.print()">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>
    </div>

    <!-- Employee Info -->
    <div class="card-panel p-4 mb-4">
        <div class="row">
            <div class="col-md-6">
                <h4 class="mb-3"><?= htmlspecialchars($employeeName) ?></h4>
                <p class="mb-1"><strong>Pay Period:</strong> <?= date('F d, Y', strtotime($startDate)) ?> - <?= date('F d, Y', strtotime($endDate)) ?></p>
                <p class="mb-0"><strong>Total Days Worked:</strong> <?= count($perDay) ?> days</p>
            </div>
            <div class="col-md-6 text-end">
                <div class="bg-light p-3 rounded">
                    <h5 class="text-muted mb-2">Net Pay</h5>
                    <h2 class="text-primary fw-bold mb-0">₱<?= number_format($totals['net'], 2) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Earnings & Deductions Summary -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card-panel p-4">
                <h5 class="mb-3"><i class="bi bi-plus-circle text-success"></i> Earnings</h5>
                <table class="table table-sm">
                    <tr>
                        <td>Regular Pay:</td>
                        <td class="text-end fw-bold">₱<?= number_format($totals['regular'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Overtime Pay:</td>
                        <td class="text-end fw-bold">₱<?= number_format($totals['overtime'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Night Differential:</td>
                        <td class="text-end fw-bold">₱<?= number_format($totals['night'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Cashier Bonus:</td>
                        <td class="text-end fw-bold">₱<?= number_format($totals['bonus'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Allowance:</td>
                        <td class="text-end fw-bold">₱<?= number_format($totals['allowance'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Holiday Pay:</td>
                        <td class="text-end fw-bold">₱<?= number_format($totals['holiday'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Extra/Bonus:</td>
                        <td class="text-end fw-bold">₱<?= number_format($totals['extra'], 2) ?></td>
                    </tr>
                    <tr class="table-success fw-bold">
                        <td>GROSS PAY:</td>
                        <td class="text-end">₱<?= number_format($totals['gross'], 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card-panel p-4">
                <h5 class="mb-3"><i class="bi bi-dash-circle text-danger"></i> Deductions</h5>
                <table class="table table-sm">
                    <tr>
                        <td>Late Deduction:</td>
                        <td class="text-end fw-bold text-danger">₱<?= number_format($totals['late'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Government Contributions:</td>
                        <td class="text-end fw-bold text-danger">₱<?= number_format($totals['govt'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Loans:</td>
                        <td class="text-end fw-bold text-danger">₱<?= number_format($totals['loan'], 2) ?></td>
                    </tr>
                    <tr class="table-danger fw-bold">
                        <td>TOTAL DEDUCTIONS:</td>
                        <td class="text-end">₱<?= number_format($totals['total_deductions'], 2) ?></td>
                    </tr>
                    <tr class="table-primary fw-bold" style="font-size: 1.1em;">
                        <td>NET PAY:</td>
                        <td class="text-end">₱<?= number_format($totals['net'], 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Daily Breakdown -->
    <div class="card-panel p-4">
        <h5 class="mb-3"><i class="bi bi-calendar-week"></i> Daily Breakdown</h5>
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Role</th>
                        <th class="text-end">Hours</th>
                        <th>Holiday</th>
                        <th class="text-end">Regular</th>
                        <th class="text-end">OT</th>
                        <th class="text-end">Night</th>
                        <th class="text-end">Bonus</th>
                        <th class="text-end">Allowance</th>
                        <th class="text-end">Holiday</th>
                        <th class="text-end">Late</th>
                        <th class="text-end">Other</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($perDay as $day): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($day['date'])) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($day['role']) ?></span></td>
                        <td class="text-end"><?= number_format($day['hours'], 2) ?></td>
                        <td>
                            <?php if ($day['holiday']): ?>
                                <span class="badge bg-warning text-dark"><?= ucfirst($day['holiday']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">₱<?= number_format($day['regular_pay'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($day['ot_pay'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($day['night_pay'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($day['cashier_bonus'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($day['allowance'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($day['holiday_bonus'], 2) ?></td>
                        <td class="text-end text-danger">
                            <?php if ($day['late'] > 0): ?>
                                -₱<?= number_format($day['late'], 2) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php 
                            $other = ($day['extra'] ?? 0) - ($day['deductions'] ?? 0);
                            echo $other >= 0 ? '₱'.number_format($other, 2) : '-₱'.number_format(abs($other), 2);
                            ?>
                        </td>
                        <td class="text-end fw-bold">
                            <?php 
                            $dayTotal = $day['regular_pay'] + $day['ot_pay'] + $day['night_pay'] 
                                      + $day['cashier_bonus'] + $day['allowance'] + $day['holiday_bonus']
                                      + ($day['extra'] ?? 0) - $day['late'] - ($day['deductions'] ?? 0);
                            ?>
                            ₱<?= number_format($dayTotal, 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer Note -->
    <div class="text-center mt-4 text-muted">
        <small>This is a system-generated payslip. Generated on <?= date('F d, Y h:i A') ?></small>
    </div>
</div>

<style>
@media print {
    .sidebar, .btn, .no-print { display: none !important; }
    .main-content { margin-left: 0 !important; width: 100% !important; }
    .card-panel { border: 1px solid #ddd !important; }
}
</style>