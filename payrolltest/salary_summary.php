<?php
require_once 'dbconfig.php';
require_once 'calculate_rates.php';
include 'sidebar.php';

// Get date range from query params or default to current month
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Calculate payroll using your calculate_rates.php function
$payrollData = calculateRates($pdo, $startDate, $endDate);
$employees = $payrollData['employees'] ?? [];

// Calculate grand totals
$grandTotals = [
    'regular' => 0,
    'overtime' => 0,
    'night' => 0,
    'bonus' => 0,
    'allowance' => 0,
    'holiday' => 0,
    'extra' => 0,
    'gross' => 0,
    'late' => 0,
    'govt' => 0,
    'loan' => 0,
    'total_deductions' => 0,
    'net' => 0
];

foreach ($employees as $emp) {
    foreach ($grandTotals as $key => $val) {
        $grandTotals[$key] += $emp['totals'][$key] ?? 0;
    }
}
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-cash-stack"></i> Salary Summary</h2>
        <div>
            <button class="btn btn-success" onclick="window.print()">
                <i class="bi bi-printer"></i> Print Report
            </button>
            <button class="btn btn-primary" onclick="exportToExcel()">
                <i class="bi bi-file-excel"></i> Export Excel
            </button>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card-panel mb-3 p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold"><i class="bi bi-calendar3"></i> Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold"><i class="bi bi-calendar3"></i> End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>" required>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Generate Report
                </button>
            </div>
        </form>
        <p class="text-muted mt-3 mb-0">
            <strong>Report Period:</strong> <?= date('F d, Y', strtotime($startDate)) ?> to <?= date('F d, Y', strtotime($endDate)) ?>
            <span class="ms-3"><strong>Employees:</strong> <?= count($employees) ?></span>
        </p>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card-panel text-center p-3 shadow-sm">
                <h6 class="text-muted mb-2"><i class="bi bi-currency-dollar"></i> Total Gross Pay</h6>
                <h3 class="text-success fw-bold mb-0">₱<?= number_format($grandTotals['gross'], 2) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-panel text-center p-3 shadow-sm">
                <h6 class="text-muted mb-2"><i class="bi bi-dash-circle"></i> Total Deductions</h6>
                <h3 class="text-danger fw-bold mb-0">₱<?= number_format($grandTotals['total_deductions'], 2) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-panel text-center p-3 shadow-sm">
                <h6 class="text-muted mb-2"><i class="bi bi-wallet2"></i> Total Net Pay</h6>
                <h3 class="text-primary fw-bold mb-0">₱<?= number_format($grandTotals['net'], 2) ?></h3>
            </div>
        </div>
    </div>

    <!-- Salary Table -->
    <div class="card-panel p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="salaryTable">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th class="text-end">Regular Pay</th>
                        <th class="text-end">Overtime</th>
                        <th class="text-end">Night Diff</th>
                        <th class="text-end">Bonuses</th>
                        <th class="text-end">Allowance</th>
                        <th class="text-end">Holiday Pay</th>
                        <th class="text-end">Gross Pay</th>
                        <th class="text-end">Deductions</th>
                        <th class="text-end">Net Pay</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($emp['name']) ?></strong>
                        </td>
                        <td class="text-end">₱<?= number_format($emp['totals']['regular'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($emp['totals']['overtime'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($emp['totals']['night'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($emp['totals']['bonus'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($emp['totals']['allowance'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($emp['totals']['holiday'], 2) ?></td>
                        <td class="text-end fw-bold text-success">₱<?= number_format($emp['totals']['gross'], 2) ?></td>
                        <td class="text-end text-danger">₱<?= number_format($emp['totals']['total_deductions'], 2) ?></td>
                        <td class="text-end fw-bold text-primary">₱<?= number_format($emp['totals']['net'], 2) ?></td>
                        <td class="text-center">
                            <a href="payslip_view.php?name=<?= urlencode($emp['name']) ?>&start=<?= $startDate ?>&end=<?= $endDate ?>" 
                               class="btn btn-sm btn-outline-primary" title="View Detailed Payslip">
                                <i class="bi bi-file-text"></i>
                            </a>
                            <a href="payslip_pdf.php?name=<?= urlencode($emp['name']) ?>&start=<?= $startDate ?>&end=<?= $endDate ?>" 
                               class="btn btn-sm btn-outline-danger" title="Download PDF" target="_blank">
                                <i class="bi bi-file-pdf"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>TOTAL</td>
                        <td class="text-end">₱<?= number_format($grandTotals['regular'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($grandTotals['overtime'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($grandTotals['night'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($grandTotals['bonus'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($grandTotals['allowance'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($grandTotals['holiday'], 2) ?></td>
                        <td class="text-end text-success">₱<?= number_format($grandTotals['gross'], 2) ?></td>
                        <td class="text-end text-danger">₱<?= number_format($grandTotals['total_deductions'], 2) ?></td>
                        <td class="text-end text-primary">₱<?= number_format($grandTotals['net'], 2) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .btn, form, .no-print { display: none !important; }
    .main-content { margin-left: 0 !important; width: 100% !important; }
}
</style>

<script>
function exportToExcel() {
    // Simple CSV export
    const table = document.getElementById('salaryTable');
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let row of rows) {
        const cols = row.querySelectorAll('td, th');
        let csvRow = [];
        for (let col of cols) {
            if (col.cellIndex !== cols.length - 1) { // Skip action column
                csvRow.push('"' + col.innerText.replace(/"/g, '""') + '"');
            }
        }
        csv.push(csvRow.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'salary_summary_<?= $startDate ?>_<?= $endDate ?>.csv';
    a.click();
}
</script>