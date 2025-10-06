<?php
// --- Configuration ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "act01";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Helper: prepare the search statement ---
function prepare_search_stmt($conn) {
    $search_raw = isset($_GET['search']) ? trim($_GET['search']) : '';
    $column_raw = isset($_GET['column']) ? $_GET['column'] : 'All';
    $allowed_columns = ['All','DataEntryID','LastName','FirstName','ShiftDate','ShiftNo','Hours','DutyType'];

    if ($search_raw !== '' && in_array($column_raw, $allowed_columns)) {
        if ($column_raw === 'All') {
            $sql = "SELECT DataEntryID, LastName, FirstName, ShiftDate, ShiftNo, Hours, DutyType
                    FROM empdetails1
                    WHERE DataEntryID LIKE ?
                       OR LastName LIKE ?
                       OR FirstName LIKE ?
                       OR ShiftDate LIKE ?
                       OR ShiftNo LIKE ?
                       OR Hours LIKE ?
                       OR DutyType LIKE ?
                    ORDER BY DataEntryID ASC";
            $stmt = $conn->prepare($sql);
            $like = '%' . $search_raw . '%';
            $stmt->bind_param('sssssss', $like, $like, $like, $like, $like, $like, $like);
        } else {
            $sql = "SELECT DataEntryID, LastName, FirstName, ShiftDate, ShiftNo, Hours, DutyType
                    FROM empdetails1
                    WHERE $column_raw LIKE ?
                    ORDER BY DataEntryID ASC";
            $stmt = $conn->prepare($sql);
            $like = '%' . $search_raw . '%';
            $stmt->bind_param('s', $like);
        }
    } else {
        $sql = "SELECT DataEntryID, LastName, FirstName, ShiftDate, ShiftNo, Hours, DutyType
                FROM empdetails1
                ORDER BY DataEntryID ASC";
        $stmt = $conn->prepare($sql);
    }
    return $stmt;
}

// --- CSV Export ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = prepare_search_stmt($conn);
    $stmt->execute();
    $result = $stmt->get_result();

    $filename = "employee_records_" . date("Y-m-d_H-i-s") . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['DataEntryID','LastName','FirstName','ShiftDate','ShiftNo','Hours','DutyType']);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($out, $row);
        }
    }
    fclose($out);
    $stmt->close();
    $conn->close();
    exit();
}

// --- Display Data ---
$stmt = prepare_search_stmt($conn);
$stmt->execute();
$data = $stmt->get_result();

// --- SQL: Summary Data (from Lab 1) ---
$summary_sql = "
    SELECT LastName, FirstName,
        SUM(CASE WHEN DutyType IN ('OnDuty', 'Late') THEN Hours ELSE 0 END) AS NumberOfOnDutyHours,
        SUM(CASE WHEN DutyType = 'Overtime' THEN Hours ELSE 0 END) AS NumberOfOvertimeHours,
        SUM(CASE WHEN DutyType = 'Late' THEN 1 ELSE 0 END) AS NumberOfLateDays,
        SUM(
            CASE
                WHEN DutyType = 'OnDuty' AND Hours >= 8 THEN 685
                WHEN DutyType = 'OnDuty' AND Hours < 8 THEN (685 / 8.0) * Hours
                WHEN DutyType = 'Overtime' THEN ((685 / 8.0) * Hours) + 685
                WHEN DutyType = 'Late' THEN ((685 / 8.0) * Hours) - 100
                ELSE 0
            END
        ) AS WeekPay
    FROM empdetails1
    GROUP BY LastName, FirstName
";

$summary_result = $conn->query($summary_sql);
$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Employee Dashboard</title>
<style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: Arial, sans-serif;
        display: flex;
        flex-direction: column;
        height: 100vh;
        background-color: #f5f6fa;
        color: #333;
    }

    /* --- Sidebar CSS --- */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 220px;
        height: 100%;
        background-color: #1e1e2f;
        color: white;
        padding-top: 20px;
        display: flex;
        flex-direction: column;
    }

    .sidebar .menu-title {
        text-align: center;
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 10px;
        color: #bbb;
    }

    .sidebar a {
        display: block;
        padding: 12px 20px;
        text-decoration: none;
        color: #f5f6fa;
        font-weight: 500;
        transition: background 0.2s;
    }

    .sidebar a.active {
        background-color: #273c75;
        color: #ffffff;
    }

    .sidebar a:hover {
        background-color: #40739e;
    }

    /* --- Main Content CSS --- */
    .content {
        margin-left: 220px;
        padding: 50px 20px 20px 20px; /* top space for topbar */
        flex: 1;
        overflow-y: auto;
    }

    .content h1 {
        text-align: left;
        color: #333;
        margin-top: 0;
        margin-left: 30%;
    }

    /* --- Content Container (Filter + Search + Table) CSS --- */
    .data-container {
        width: 90%;
        margin: 20px auto;
        background-color: #ffffff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }

    /* --- Search & Filter CSS --- */
    .search-container {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 20px;
    }

    .label {
        font-weight: bold;
        color: #333;
        text-align: left;
    }

    .searchBox {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .searchBox select,
    .searchBox input[type="text"],
    .searchBox input[type="submit"],
    .searchBox a,
    .searchBox button {
        padding: 10px 15px;
        font-size: 14px;
        border: 1px solid #ccc;
        border-radius: 5px;
        outline: none;
    }

    .searchBox select,
    .searchBox input[type="text"] {
        flex: 1;
        min-width: 150px;
    }

    .searchBox input[type="submit"],
    .searchBox a,
    .searchBox button {
        background-color: #192a56;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        min-width: 100px;
        text-align: center;
    }

    .searchBox a {
        text-decoration: none;
        display: inline-block;
    }

    .searchBox input[type="submit"]:hover,
    .searchBox a:hover,
    .searchBox button:hover {
        background-color: #192a56;
    }

    /* --- Table CSS --- */
    .table-container {
        width: 100%;
        max-height: 400px;
        min-height: 400px;
        overflow-y: scroll;
        scrollbar-width: none;
    }

    .table-container::-webkit-scrollbar {
        display: none;
    }

    table {
        border-collapse: collapse;
        width: 100%;
    }

    th, td {
        border: 1px solid #ccc;
        padding: 8px;
        text-align: center;
    }

    th {
        background-color: #192a56;
        color: white;
        position: sticky;
        top: 0;
        z-index: 2;
    }

    tr:nth-child(even) {
        background-color: #f2f2f2;
    }

</style>
</head>
<body>

<div class="sidebar">
    <div class="menu-title">Menu</div>
    <a href="PHP_Act_02.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'PHP_Act_02.php' ? 'active' : ''; ?>">Overview</a>
    <a href="modify.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'modify.php' ? 'active' : ''; ?>">Modify</a>
</div>

<div class="content">
    <h1>Employee Management System</h1>

    <div class="data-container">
        <!-- Search and Filter -->
        <div class="search-container">
            <form method="GET" class="searchBox" action="PHP_Act_02.php">
                <select name="column">
                    <option value="All" <?php if(isset($_GET['column']) && $_GET['column']=='All') echo 'selected'; ?>>All</option>
                    <option value="DataEntryID" <?php if(isset($_GET['column']) && $_GET['column']=='DataEntryID') echo 'selected'; ?>>Data Entry ID</option>
                    <option value="LastName" <?php if(isset($_GET['column']) && $_GET['column']=='LastName') echo 'selected'; ?>>Last Name</option>
                    <option value="FirstName" <?php if(isset($_GET['column']) && $_GET['column']=='FirstName') echo 'selected'; ?>>First Name</option>
                    <option value="ShiftDate" <?php if(isset($_GET['column']) && $_GET['column']=='ShiftDate') echo 'selected'; ?>>Shift Date</option>
                    <option value="ShiftNo" <?php if(isset($_GET['column']) && $_GET['column']=='ShiftNo') echo 'selected'; ?>>Shift Number</option>
                    <option value="Hours" <?php if(isset($_GET['column']) && $_GET['column']=='Hours') echo 'selected'; ?>>Hours</option>
                    <option value="DutyType" <?php if(isset($_GET['column']) && $_GET['column']=='DutyType') echo 'selected'; ?>>Duty Type</option>
                </select>

                <input type="text" name="search" placeholder="Search here"
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">

                <input type="submit" value="Search">
                <a href="PHP_Act_02.php">Reset</a>
                <button type="submit" name="export" value="csv">Export CSV</button>
            </form>
        </div>

        <!-- Database View Table -->
        <div class="table-container">
            <table>
                <tr>
                    <th>Data Entry ID</th>
                    <th>Last Name</th>
                    <th>First Name</th>
                    <th>Shift Date</th>
                    <th>Shift Number</th>
                    <th>Hours</th>
                    <th>Duty Type</th>
                </tr>
                <?php
                $rowCount = 0;
                if ($data && $data->num_rows > 0) {
                    while ($row = $data->fetch_assoc()) {
                        echo "<tr>
                            <td>" . htmlspecialchars($row['DataEntryID']) . "</td>
                            <td>" . htmlspecialchars($row['LastName']) . "</td>
                            <td>" . htmlspecialchars($row['FirstName']) . "</td>
                            <td>" . htmlspecialchars($row['ShiftDate']) . "</td>
                            <td>" . htmlspecialchars($row['ShiftNo']) . "</td>
                            <td>" . htmlspecialchars($row['Hours']) . "</td>
                            <td>" . htmlspecialchars($row['DutyType']) . "</td>
                        </tr>";
                        $rowCount++;
                    }
                }
                // Fill empty rows to keep table height consistent
                for ($i = $rowCount; $i < 10; $i++) {
                    echo "<tr><td colspan='7'>&nbsp;</td></tr>";
                }
                ?>
            </table>
        </div>
    </div>

    <!-- Summary Table -->
    <h1 style="margin-left:450px; margin-top:60px;">Employee Summary</h1>
    <div class="data-container">
        <div class="table-container">
            <table>
                <tr>
                    <th>Last Name</th>
                    <th>First Name</th>
                    <th>On-Duty Hours</th>
                    <th>Overtime Hours</th>
                    <th>Late Days</th>
                    <th>Week Pay (₱)</th>
                </tr>
                <?php
                $rowCount = 0;
                if ($summary_result && $summary_result->num_rows > 0) {
                    while ($sumRow = $summary_result->fetch_assoc()) {
                        echo "<tr>
                            <td>" . htmlspecialchars($sumRow['LastName']) . "</td>
                            <td>" . htmlspecialchars($sumRow['FirstName']) . "</td>
                            <td>" . htmlspecialchars($sumRow['NumberOfOnDutyHours']) . "</td>
                            <td>" . htmlspecialchars($sumRow['NumberOfOvertimeHours']) . "</td>
                            <td>" . htmlspecialchars($sumRow['NumberOfLateDays']) . "</td>
                            <td>" . htmlspecialchars(number_format($sumRow['WeekPay'], 2)) . "</td>
                        </tr>";
                        $rowCount++;
                    }
                }

                // Fill empty rows to keep table height consistent
                for ($i = $rowCount; $i < 10; $i++) {
                    echo "<tr><td colspan='6'>&nbsp;</td></tr>";
                }

                $stmt->close();
                $summary_stmt->close();
                $conn->close();
                ?>
            </table>
        </div>
    </div>

</div>
</body>
</html>