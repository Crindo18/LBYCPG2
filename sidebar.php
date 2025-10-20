<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="logo">
        <h2>PATRIOT</h2>
        <p>SOFTWARE</p>
    </div>
    
    <nav class="nav-menu">
        <a href="dashboard.php" class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span>Dashboard</span>
        </a>

        <a href="payroll.php" class="nav-item <?php echo ($current_page == 'payroll.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
            </svg>
            <span>Payroll</span>
        </a>

        <div class="nav-item has-submenu <?php echo (strpos($current_page, 'employees') !== false) ? 'active' : ''; ?>">
            <div class="nav-item-main" onclick="toggleSubmenu(this)">
                <div class="nav-item-content">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span>Employees</span>
                </div>
                <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </div>
            <div class="submenu">
                <a href="employees.php" class="submenu-item <?php echo ($current_page == 'employees.php' && !isset($_GET['unit'])) ? 'active' : ''; ?>">
                    All Business Units
                </a>
                <a href="employees.php?unit=Canteen" class="submenu-item <?php echo (isset($_GET['unit']) && $_GET['unit'] == 'Canteen') ? 'active' : ''; ?>">
                    Canteen
                </a>
                <a href="employees.php?unit=Service Crew" class="submenu-item <?php echo (isset($_GET['unit']) && $_GET['unit'] == 'Service Crew') ? 'active' : ''; ?>">
                    Service Crew
                </a>
                <a href="employees.php?unit=Main Office" class="submenu-item <?php echo (isset($_GET['unit']) && $_GET['unit'] == 'Main Office') ? 'active' : ''; ?>">
                    Main Office
                </a>
                <a href="employees.php?unit=Satellite Office" class="submenu-item <?php echo (isset($_GET['unit']) && $_GET['unit'] == 'Satellite Office') ? 'active' : ''; ?>">
                    Satellite Office
                </a>
            </div>
        </div>

        <a href="time_tracking.php" class="nav-item <?php echo ($current_page == 'time_tracking.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            <span>Time Tracking</span>
        </a>

        <a href="reports.php" class="nav-item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
            <span>Reports</span>
        </a>
    </nav>
</aside>