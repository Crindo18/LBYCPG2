<?php
require_once 'dbconfig.php';
require_once 'calculate_rates.php';
include 'sidebar.php';

// Get date range from query params or default to current month
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$filterBusinessUnit = $_GET['business_unit'] ?? '';

// Get payroll data
$payrollData = calculateRates($pdo, $startDate, $endDate);
$employees = $payrollData['employees'] ?? [];

// Apply business unit filter
if ($filterBusinessUnit) {
    $employees = array_filter($employees, function($emp) use ($filterBusinessUnit) {
        return $emp['business_unit'] === $filterBusinessUnit;
    });
}

// Calculate analytics
$totalEmployees = count($employees);
$totalGrossPay = array_sum(array_column(array_column($employees, 'totals'), 'gross'));
$totalNetPay = array_sum(array_column(array_column($employees, 'totals'), 'net'));
$totalDeductions = array_sum(array_column(array_column($employees, 'totals'), 'total_deductions'));
$totalOvertimePay = array_sum(array_column(array_column($employees, 'totals'), 'overtime'));
$totalLateDeductions = array_sum(array_column(array_column($employees, 'totals'), 'late'));

// Get time tracking stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN Remarks = 'Late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN Remarks = 'Overtime' THEN 1 ELSE 0 END) as overtime_count,
        SUM(CASE WHEN Role = 'Cashier' THEN 1 ELSE 0 END) as cashier_shifts,
        SUM(Hours) as total_hours
    FROM payrolldata
    WHERE Date BETWEEN ? AND ?
    " . ($filterBusinessUnit ? "AND BusinessUnit = ?" : "")
);

if ($filterBusinessUnit) {
    $stmt->execute([$startDate, $endDate, $filterBusinessUnit]);
} else {
    $stmt->execute([$startDate, $endDate]);
}
$timeStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get business unit breakdown
$stmt = $pdo->prepare("
    SELECT 
        BusinessUnit,
        COUNT(DISTINCT Name) as employee_count,
        COUNT(*) as record_count,
        SUM(Hours) as total_hours
    FROM payrolldata
    WHERE Date BETWEEN ? AND ?
    AND BusinessUnit IS NOT NULL
    GROUP BY BusinessUnit
    ORDER BY employee_count DESC
");
$stmt->execute([$startDate, $endDate]);
$businessUnitStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top employees by gross pay
$topEarners = $employees;
usort($topEarners, function($a, $b) {
    return $b['totals']['gross'] - $a['totals']['gross'];
});
$topEarners = array_slice($topEarners, 0, 5);

// Get employees with most late incidents
$lateEmployees = array_filter($employees, function($emp) {
    return $emp['totals']['late'] > 0;
});
usort($lateEmployees, function($a, $b) {
    return $b['totals']['late'] - $a['totals']['late'];
});
$lateEmployees = array_slice($lateEmployees, 0, 5);

// Get daily attendance data for chart
$stmt = $pdo->prepare("
    SELECT 
        Date,
        COUNT(DISTINCT Name) as employee_count
    FROM payrolldata
    WHERE Date BETWEEN ? AND ?
    " . ($filterBusinessUnit ? "AND BusinessUnit = ?" : "") . "
    GROUP BY Date
    ORDER BY Date
");

if ($filterBusinessUnit) {
    $stmt->execute([$startDate, $endDate, $filterBusinessUnit]);
} else {
    $stmt->execute([$startDate, $endDate]);
}
$dailyAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique business units for dropdown
$businessUnits = $pdo->query("SELECT DISTINCT BusinessUnit FROM payrolldata WHERE BusinessUnit IS NOT NULL ORDER BY BusinessUnit")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-graph-up"></i> Analytics & Reports</h2>
        <button class="btn btn-success" onclick="window.print()">
            <i class="bi bi-printer"></i> Print Report
        </button>
    </div>

    <!-- Date Range Filter -->
    <div class="card-panel mb-4 p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold"><i class="bi bi-calendar3"></i> Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold"><i class="bi bi-calendar3"></i> End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Business Unit</label>
                <select name="business_unit" class="form-select">
                    <option value="">All Units</option>
                    <?php foreach ($businessUnits as $unit): ?>
                        <option value="<?= htmlspecialchars($unit) ?>" <?= $filterBusinessUnit === $unit ? 'selected' : '' ?>>
                            <?= htmlspecialchars($unit) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Generate Report
                </button>
            </div>
        </form>
        <p class="text-muted mt-3 mb-0">
            <strong>Report Period:</strong> <?= date('F d, Y', strtotime($startDate)) ?> to <?= date('F d, Y', strtotime($endDate)) ?>
        </p>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card-panel text-center p-3 shadow-sm bg-primary text-white">
                <h6 class="mb-2"><i class="bi bi-people"></i> Total Employees</h6>
                <h2 class="fw-bold mb-0"><?= $totalEmployees ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-panel text-center p-3 shadow-sm bg-success text-white">
                <h6 class="mb-2"><i class="bi bi-currency-dollar"></i> Gross Payroll</h6>
                <h3 class="fw-bold mb-0">₱<?= number_format($totalGrossPay, 2) ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-panel text-center p-3 shadow-sm bg-info text-white">
                <h6 class="mb-2"><i class="bi bi-wallet2"></i> Net Payroll</h6>
                <h3 class="fw-bold mb-0">₱<?= number_format($totalNetPay, 2) ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-panel text-center p-3 shadow-sm bg-danger text-white">
                <h6 class="mb-2"><i class="bi bi-dash-circle"></i> Total Deductions</h6>
                <h3 class="fw-bold mb-0">₱<?= number_format($totalDeductions, 2) ?></h3>
            </div>
        </div>
    </div>

    <!-- Time Tracking Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card-panel p-3 text-center">
                <i class="bi bi-clock-history text-primary" style="font-size: 2rem;"></i>
                <h3 class="fw-bold mt-2 mb-1"><?= number_format($timeStats['total_hours'] ?? 0, 1) ?></h3>
                <p class="text-muted mb-0">Total Hours Worked</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-panel p-3 text-center">
                <i class="bi bi-alarm text-warning" style="font-size: 2rem;"></i>
                <h3 class="fw-bold mt-2 mb-1"><?= $timeStats['late_count'] ?? 0 ?></h3>
                <p class="text-muted mb-0">Late Incidents</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-panel p-3 text-center">
                <i class="bi bi-clock text-success" style="font-size: 2rem;"></i>
                <h3 class="fw-bold mt-2 mb-1"><?= $timeStats['overtime_count'] ?? 0 ?></h3>
                <p class="text-muted mb-0">Overtime Shifts</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-panel p-3 text-center">
                <i class="bi bi-cash-coin text-info" style="font-size: 2rem;"></i>
                <h3 class="fw-bold mt-2 mb-1">₱<?= number_format($totalOvertimePay, 2) ?></h3>
                <p class="text-muted mb-0">Overtime Pay</p>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <!-- Daily Attendance Chart -->
        <div class="col-md-6">
            <div class="card-panel p-4">
                <h5 class="mb-3"><i class="bi bi-bar-chart"></i> Daily Attendance</h5>
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>

        <!-- Business Unit Distribution -->
        <div class="col-md-6">
            <div class="card-panel p-4">
                <h5 class="mb-3"><i class="bi bi-pie-chart"></i> Business Unit Distribution</h5>
                <canvas id="businessUnitChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="row g-3 mb-4">
        <!-- Top Earners -->
        <div class="col-md-6">
            <div class="card-panel p-4">
                <h5 class="mb-3"><i class="bi bi-trophy"></i> Top Earners</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Rank</th>
                                <th>Employee</th>
                                <th>Business Unit</th>
                                <th class="text-end">Gross Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($topEarners as $emp): ?>
                            <tr>
                                <td>
                                    <?php if ($rank === 1): ?>
                                        <i class="bi bi-trophy-fill text-warning"></i>
                                    <?php elseif ($rank === 2): ?>
                                        <i class="bi bi-trophy-fill text-secondary"></i>
                                    <?php elseif ($rank === 3): ?>
                                        <i class="bi bi-trophy-fill" style="color: #CD7F32;"></i>
                                    <?php else: ?>
                                        <?= $rank ?>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($emp['name']) ?></strong></td>
                                <td><?= htmlspecialchars($emp['business_unit']) ?></td>
                                <td class="text-end">₱<?= number_format($emp['totals']['gross'], 2) ?></td>
                            </tr>
                            <?php $rank++; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Late Incidents -->
        <div class="col-md-6">
            <div class="card-panel p-4">
                <h5 class="mb-3"><i class="bi bi-exclamation-triangle text-warning"></i> Late Incidents</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Business Unit</th>
                                <th class="text-end">Late Count</th>
                                <th class="text-end">Deduction</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lateEmployees)): ?>
                                <tr><td colspan="4" class="text-center text-muted">No late incidents recorded</td></tr>
                            <?php else: ?>
                                <?php foreach ($lateEmployees as $emp): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($emp['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($emp['business_unit']) ?></td>
                                    <td class="text-end"><?= round($emp['totals']['late'] / 150) ?></td>
                                    <td class="text-end text-danger">₱<?= number_format($emp['totals']['late'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Business Unit Breakdown -->
    <div class="card-panel p-4">
        <h5 class="mb-3"><i class="bi bi-building"></i> Business Unit Breakdown</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Business Unit</th>
                        <th class="text-center">Employees</th>
                        <th class="text-center">Total Records</th>
                        <th class="text-end">Total Hours</th>
                        <th class="text-end">Avg Hours/Employee</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($businessUnitStats as $stat): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($stat['BusinessUnit']) ?></strong></td>
                        <td class="text-center"><?= $stat['employee_count'] ?></td>
                        <td class="text-center"><?= $stat['record_count'] ?></td>
                        <td class="text-end"><?= number_format($stat['total_hours'], 1) ?></td>
                        <td class="text-end"><?= number_format($stat['total_hours'] / $stat['employee_count'], 1) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .btn, form, .no-print { display: none !important; }
    .main-content { margin-left: 0 !important; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daily Attendance Chart
const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
const attendanceData = <?= json_encode(array_column($dailyAttendance, 'employee_count')) ?>;
const attendanceDates = <?= json_encode(array_map(function($d) { return date('M d', strtotime($d['Date'])); }, $dailyAttendance)) ?>;

new Chart(attendanceCtx, {
    type: 'line',
    data: {
        labels: attendanceDates,
        datasets: [{
            label: 'Employees Present',
            data: attendanceData,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// Business Unit Pie Chart
const businessUnitCtx = document.getElementById('businessUnitChart').getContext('2d');
const businessUnitData = <?= json_encode(array_column($businessUnitStats, 'employee_count')) ?>;
const businessUnitLabels = <?= json_encode(array_column($businessUnitStats, 'BusinessUnit')) ?>;

new Chart(businessUnitCtx, {
    type: 'doughnut',
    data: {
        labels: businessUnitLabels,
        datasets: [{
            data: businessUnitData,
            backgroundColor: [
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 99, 132, 0.8)',
                'rgba(255, 206, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'right' }
        }
    }
});
</script>