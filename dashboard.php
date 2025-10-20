<?php
require_once 'config.php';
$conn = getDBConnection();

// Get current week's date range
$current_date = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

// Get weekly statistics
$stats = [
    'on_duty' => 0,
    'overtime' => 0,
    'late' => 0,
    'avg_time_in' => '--:--',
    'avg_time_out' => '--:--'
];

// Count employees by status for current week
$query = "SELECT remarks, COUNT(DISTINCT employee_id) as count 
          FROM time_records 
          WHERE date BETWEEN ? AND ? 
          GROUP BY remarks";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $week_start, $week_end);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if ($row['remarks'] == 'OnDuty') $stats['on_duty'] = $row['count'];
    if ($row['remarks'] == 'Overtime') $stats['overtime'] = $row['count'];
    if ($row['remarks'] == 'Late') $stats['late'] = $row['count'];
}

// Calculate average time in and time out
$query = "SELECT AVG(TIME_TO_SEC(time_in)) as avg_in, AVG(TIME_TO_SEC(time_out)) as avg_out 
          FROM time_records 
          WHERE date BETWEEN ? AND ? AND time_in IS NOT NULL AND time_out IS NOT NULL";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $week_start, $week_end);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if ($row['avg_in']) {
        $stats['avg_time_in'] = gmdate('H:i', $row['avg_in']);
    }
    if ($row['avg_out']) {
        $stats['avg_time_out'] = gmdate('H:i', $row['avg_out']);
    }
}

$notification = getNotification();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Patriot Payroll</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Dashboard</h1>
                <p class="subtitle">Week of <?php echo date('M d', strtotime($week_start)); ?> - <?php echo date('M d, Y', strtotime($week_end)); ?></p>
            </div>

            <?php if ($notification): ?>
            <div class="notification <?php echo $notification['type']; ?>">
                <?php echo htmlspecialchars($notification['message']); ?>
            </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon on-duty">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['on_duty']; ?></h3>
                        <p>On Duty</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon overtime">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['overtime']; ?></h3>
                        <p>Overtime</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon late">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 8v4l3 3"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['late']; ?></h3>
                        <p>Late</p>
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card time-card">
                    <div class="stat-icon time-in">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                            <polyline points="10 17 15 12 10 7"/>
                            <line x1="15" y1="12" x2="3" y2="12"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['avg_time_in']; ?></h3>
                        <p>Average Time In</p>
                    </div>
                </div>

                <div class="stat-card time-card">
                    <div class="stat-icon time-out">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['avg_time_out']; ?></h3>
                        <p>Average Time Out</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>