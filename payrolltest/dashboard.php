<?php
require_once 'dbconfig.php';
require_once 'calculate_rates.php';
include 'sidebar.php';

// --- Date range for current week (Monday–Sunday) ---
$startOfWeek = date('Y-m-d', strtotime('monday this week'));
$endOfWeek   = date('Y-m-d', strtotime('sunday this week'));

// --- Current month for payroll stats ---
$startOfMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');

// --- Fetch summary counts ---
function fetchCount($pdo, $remark, $start, $end) {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM payrolldata WHERE Remarks = ? AND Date BETWEEN ? AND ?");
    $stmt->execute([$remark, $start, $end]);
    $row = $stmt->fetch();
    return $row ? $row['cnt'] : 0;
}

$onDuty   = fetchCount($pdo, 'OnDuty', $startOfWeek, $endOfWeek);
$overtime = fetchCount($pdo, 'Overtime', $startOfWeek, $endOfWeek);
$late     = fetchCount($pdo, 'Late', $startOfWeek, $endOfWeek);

// --- Average time in/out for the week ---
$avgQuery = $pdo->prepare("
    SELECT 
        SEC_TO_TIME(AVG(TIME_TO_SEC(TimeIn))) AS avg_in,
        SEC_TO_TIME(AVG(TIME_TO_SEC(TimeOut))) AS avg_out
    FROM payrolldata 
    WHERE Date BETWEEN ? AND ? AND TimeIn IS NOT NULL AND TimeOut IS NOT NULL
");
$avgQuery->execute([$startOfWeek, $endOfWeek]);
$avgTimes = $avgQuery->fetch();

$avgIn  = $avgTimes['avg_in'] ?? '--:--';
$avgOut = $avgTimes['avg_out'] ?? '--:--';

// --- Database Statistics ---
$stats = $pdo->query("
    SELECT 
        COUNT(DISTINCT Name) as total_employees,
        COUNT(*) as total_records,
        MIN(Date) as earliest_date,
        MAX(Date) as latest_date
    FROM payrolldata
    WHERE Date IS NOT NULL
")->fetch();

// --- Current Month Payroll Summary ---
$payrollData = calculateRates($pdo, $startOfMonth, $endOfMonth);
$employees = $payrollData['employees'] ?? [];
$monthlyGrossPay = array_sum(array_column(array_column($employees, 'totals'), 'gross'));
$monthlyNetPay = array_sum(array_column(array_column($employees, 'totals'), 'net'));
$monthlyDeductions = array_sum(array_column(array_column($employees, 'totals'), 'total_deductions'));

// --- Recent Activities (Last 10 records) ---
$recentStmt = $pdo->query("
    SELECT Name, Date, TimeIn, TimeOut, Remarks, Hours 
    FROM payrolldata 
    WHERE Date IS NOT NULL 
    ORDER BY ID DESC 
    LIMIT 10
");
$recentActivities = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Business Unit Summary ---
$businessUnitStmt = $pdo->query("
    SELECT 
        BusinessUnit,
        COUNT(DISTINCT Name) as employee_count,
        COUNT(*) as total_shifts
    FROM payrolldata
    WHERE Date BETWEEN '{$startOfWeek}' AND '{$endOfWeek}'
    AND BusinessUnit IS NOT NULL
    GROUP BY BusinessUnit
    ORDER BY employee_count DESC
");
$businessUnits = $businessUnitStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Today's Schedule ---
$today = date('Y-m-d');
$todayStmt = $pdo->prepare("
    SELECT Name, BusinessUnit, Role, TimeIn, TimeOut, Remarks
    FROM payrolldata
    WHERE Date = ?
    ORDER BY TimeIn
");
$todayStmt->execute([$today]);
$todaySchedule = $todayStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Dashboard</h2>
            <p class="text-muted mb-0">Welcome back! Here's what's happening today.</p>
        </div>
        <div class="text-end">
            <small class="text-muted d-block">Current Time</small>
            <strong id="currentTime"></strong>
        </div>
    </div>

    <!-- Week Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card-panel text-center p-3 shadow-sm" style="border-left: 4px solid #0d6efd;">
                <i class="bi bi-person-check display-6 text-primary mb-2"></i>
                <h2 class="fw-bold text-primary mb-1"><?= $onDuty ?></h2>
                <h6 class="text-muted mb-0">On Duty (This Week)</h6>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-panel text-center p-3 shadow-sm" style="border-left: 4px solid #198754;">
                <i class="bi bi-clock-history display-6 text-success mb-2"></i>
                <h2 class="fw-bold text-success mb-1"><?= $overtime ?></h2>
                <h6 class="text-muted mb-0">Overtime (This Week)</h6>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-panel text-center p-3 shadow-sm" style="border-left: 4px solid #dc3545;">
                <i class="bi bi-alarm display-6 text-danger mb-2"></i>
                <h2 class="fw-bold text-danger mb-1"><?= $late ?></h2>
                <h6 class="text-muted mb-0">Late (This Week)</h6>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-panel text-center p-3 shadow-sm" style="border-left: 4px solid #6c757d;">
                <i class="bi bi-calendar-week display-6 text-secondary mb-2"></i>
                <p class="mb-1"><strong>In:</strong> <?= htmlspecialchars(substr($avgIn, 0, 5)) ?></p>
                <p class="mb-1"><strong>Out:</strong> <?= htmlspecialchars(substr($avgOut, 0, 5)) ?></p>
                <h6 class="text-muted mb-0">Average Time</h6>
            </div>
        </div>
    </div>

    <!-- Database Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card-panel p-4">
                <h5 class="mb-3"><i class="bi bi-graph-up"></i> Database Statistics</h5>
                <div class="row g-3">
                    <div class="col-md-3 text-center">
                        <i class="bi bi-people-fill text-primary" style="font-size: 2.5rem;"></i>
                        <h3 class="fw-bold text-primary mt-2 mb-1"><?= number_format($stats['total_employees'] ?? 0) ?></h3>
                        <small class="text-muted">Total Employees</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <i class="bi bi-file-text-fill text-success" style="font-size: 2.5rem;"></i>
                        <h3 class="fw-bold text-success mt-2 mb-1"><?= number_format($stats['total_records'] ?? 0) ?></h3>
                        <small class="text-muted">Time Records</small>
                    </div>
                    <div class="col-md-6">
                        <div class="border-start ps-3">
                            <p class="mb-2"><strong>Data Range:</strong></p>
                            <?php if ($stats['earliest_date']): ?>
                                <p class="mb-1 text-muted">
                                    <i class="bi bi-calendar-event"></i>
                                    <?= date('M d, Y', strtotime($stats['earliest_date'])) ?>
                                </p>
                                <p class="mb-0 text-muted">
                                    <i class="bi bi-arrow-down"></i>
                                </p>
                                <p class="mb-0 text-muted">
                                    <i class="bi bi-calendar-check"></i>
                                    <?= date('M d, Y', strtotime($stats['latest_date'])) ?>
                                </p>
                            <?php else: ?>
                                <p class="text-muted mb-0">No records yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Payroll Summary -->
        <div class="col-md-4">
            <div class="card-panel p-4 bg-primary text-white">
                <h6 class="mb-3 opacity-75"><i class="bi bi-cash-stack"></i> This Month's Payroll</h6>
                <div class="mb-3">
                    <small class="opacity-75">Gross Pay</small>
                    <h4 class="fw-bold mb-0">₱<?= number_format($monthlyGrossPay, 2) ?></h4>
                </div>
                <div class="mb-3">
                    <small class="opacity-75">Deductions</small>
                    <h5 class="mb-0">₱<?= number_format($monthlyDeductions, 2) ?></h5>
                </div>
                <div class="border-top pt-3 mt-3">
                    <small class="opacity-75">Net Pay</small>
                    <h4 class="fw-bold mb-0">₱<?= number_format($monthlyNetPay, 2) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Business Units & Today's Schedule -->
    <div class="row g-3 mb-4">
        <!-- Business Unit Summary -->
        <div class="col-md-6">
            <div class="card-panel p-4">
                <h5 class="mb-3"><i class="bi bi-building"></i> Business Units (This Week)</h5>
                <?php if (empty($businessUnits)): ?>
                    <p class="text-muted text-center py-3">No data for this week</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($businessUnits as $unit): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <strong><?= htmlspecialchars($unit['BusinessUnit']) ?></strong>
                                <br>
                                <small class="text-muted"><?= $unit['total_shifts'] ?> shifts</small>
                            </div>
                            <span class="badge bg-primary rounded-pill"><?= $unit['employee_count'] ?> employees</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Today's Schedule -->
        <div class="col-md-6">
            <div class="card-panel p-4">
                <h5 class="mb-3"><i class="bi bi-calendar-day"></i> Today's Schedule (<?= date('M d, Y') ?>)</h5>
                <?php if (empty($todaySchedule)): ?>
                    <p class="text-muted text-center py-3">No schedules for today</p>
                <?php else: ?>
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Name</th>
                                    <th>Unit</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todaySchedule as $sched): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($sched['Name']) ?></strong></td>
                                    <td><?= htmlspecialchars($sched['BusinessUnit']) ?></td>
                                    <td>
                                        <small>
                                            <?= htmlspecialchars(substr($sched['TimeIn'] ?? '--:--', 0, 5)) ?> - 
                                            <?= htmlspecialchars(substr($sched['TimeOut'] ?? '--:--', 0, 5)) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php 
                                        $remarks = $sched['Remarks'] ?? 'OnDuty';
                                        $badgeClass = $remarks === 'Late' ? 'bg-danger' : ($remarks === 'Overtime' ? 'bg-success' : 'bg-primary');
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($remarks) ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="card-panel p-4">
        <h5 class="mb-3"><i class="bi bi-activity"></i> Recent Activities</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentActivities as $activity): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($activity['Date'])) ?></td>
                        <td><strong><?= htmlspecialchars($activity['Name']) ?></strong></td>
                        <td><?= htmlspecialchars(substr($activity['TimeIn'] ?? '--:--', 0, 5)) ?></td>
                        <td><?= htmlspecialchars(substr($activity['TimeOut'] ?? '--:--', 0, 5)) ?></td>
                        <td><?= htmlspecialchars($activity['Hours'] ?? '0') ?>h</td>
                        <td>
                            <?php 
                            $remarks = $activity['Remarks'] ?? 'OnDuty';
                            $badgeClass = $remarks === 'Late' ? 'bg-danger' : ($remarks === 'Overtime' ? 'bg-success' : 'bg-primary');
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($remarks) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3 mt-4">
        <div class="col-md-3">
            <a href="timetracking.php" class="card-panel text-decoration-none text-center p-3 d-block hover-shadow">
                <i class="bi bi-clock-history display-5 text-primary"></i>
                <h6 class="mt-2 mb-0">View Time Records</h6>
            </a>
        </div>
        <div class="col-md-3">
            <a href="employees.php" class="card-panel text-decoration-none text-center p-3 d-block hover-shadow">
                <i class="bi bi-people-fill display-5 text-success"></i>
                <h6 class="mt-2 mb-0">View Employees</h6>
            </a>
        </div>
        <div class="col-md-3">
            <a href="salary_summary.php" class="card-panel text-decoration-none text-center p-3 d-block hover-shadow">
                <i class="bi bi-cash-stack display-5 text-info"></i>
                <h6 class="mt-2 mb-0">View Payroll</h6>
            </a>
        </div>
        <div class="col-md-3">
            <a href="batch_upload.php" class="card-panel text-decoration-none text-center p-3 d-block hover-shadow">
                <i class="bi bi-file-earmark-excel display-5 text-warning"></i>
                <h6 class="mt-2 mb-0">Batch Upload</h6>
            </a>
        </div>
    </div>
</div>

<style>
.hover-shadow {
    transition: all 0.3s ease;
}
.hover-shadow:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
}
.sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
}
</style>

<script>
// Real-time clock
function updateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit'
    });
    document.getElementById('currentTime').textContent = timeString;
}
updateTime();
setInterval(updateTime, 1000);
</script>