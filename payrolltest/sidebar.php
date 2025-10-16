<?php
// sidebar.php
?>

<!-- Bootstrap 5 & Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">

<div class="d-flex">
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header text-center py-3">
            <img src="assets/logo.png" alt="Logo" class="img-fluid sidebar-logo">
        </div>

        <ul class="nav flex-column mt-3">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a href="payroll.php" class="nav-link"><i class="bi bi-cash-coin"></i> Payroll</a>
            </li>

            <li class="nav-item">
                <a href="employees.php" class="nav-link"><i class="bi bi-people-fill"></i> Employees</a>
            </li>

            <li class="nav-item">
                <a href="timetracking.php" class="nav-link"><i class="bi bi-clock-history"></i> Time Tracking</a>
            </li>

            <li class="nav-item">
                <a href="reports.php" class="nav-link"><i class="bi bi-graph-up"></i> Reports</a>
            </li>
        </ul>
    </nav>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
