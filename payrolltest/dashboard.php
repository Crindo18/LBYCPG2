<?php
require_once 'dbconfig.php';
include 'sidebar.php';

// --- Date range for current week (Monday–Sunday) ---
$startOfWeek = date('Y-m-d', strtotime('monday this week'));
$endOfWeek   = date('Y-m-d', strtotime('sunday this week'));

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
?>

<div class="main-content">
    <h2 class="mb-4">Dashboard</h2>

    <div class="row g-4">
        <!-- On Duty -->
        <div class="col-md-3">
            <div class="card-panel text-center">
                <h5 class="text-muted">On Duty (This Week)</h5>
                <h2 class="fw-bold text-primary"><?= $onDuty ?></h2>
                <i class="bi bi-person-check display-5 text-primary"></i>
            </div>
        </div>

        <!-- Overtime -->
        <div class="col-md-3">
            <div class="card-panel text-center">
                <h5 class="text-muted">Overtime (This Week)</h5>
                <h2 class="fw-bold text-success"><?= $overtime ?></h2>
                <i class="bi bi-clock-history display-5 text-success"></i>
            </div>
        </div>

        <!-- Late -->
        <div class="col-md-3">
            <div class="card-panel text-center">
                <h5 class="text-muted">Late (This Week)</h5>
                <h2 class="fw-bold text-danger"><?= $late ?></h2>
                <i class="bi bi-alarm display-5 text-danger"></i>
            </div>
        </div>

        <!-- Avg times -->
        <div class="col-md-3">
            <div class="card-panel text-center">
                <h5 class="text-muted">Average Time</h5>
                <p class="mb-0"><strong>In:</strong> <?= htmlspecialchars($avgIn) ?></p>
                <p class="mb-0"><strong>Out:</strong> <?= htmlspecialchars($avgOut) ?></p>
                <i class="bi bi-calendar-week display-5 text-secondary"></i>
            </div>
        </div>
    </div>
</div>
