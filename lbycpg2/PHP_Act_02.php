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

// --- Helper: prepare the search statement (returns mysqli_stmt) ---
function prepare_search_stmt($conn) {
    $search_raw = isset($_GET['search']) ? trim($_GET['search']) : '';
    $column_raw = isset($_GET['column']) ? $_GET['column'] : 'All';

    // whitelist of allowed columns + "All"
    $allowed_columns = ['All','DataEntryID','LastName','FirstName','ShiftDate','ShiftNo','Hours','DutyType'];

    if ($search_raw !== '' && in_array($column_raw, $allowed_columns)) {
        if ($column_raw === 'All') {
            // search across all columns
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
            // search only one column
            $sql = "SELECT DataEntryID, LastName, FirstName, ShiftDate, ShiftNo, Hours, DutyType
                    FROM empdetails1
                    WHERE $column_raw LIKE ?
                    ORDER BY DataEntryID ASC";
            $stmt = $conn->prepare($sql);
            $like = '%' . $search_raw . '%';
            $stmt->bind_param('s', $like);
        }
    } else {
        // no search: show all
        $sql = "SELECT DataEntryID, LastName, FirstName, ShiftDate, ShiftNo, Hours, DutyType
                FROM empdetails1
                ORDER BY DataEntryID ASC";
        $stmt = $conn->prepare($sql);
    }
    return $stmt;
}


// --- If export requested, perform CSV export BEFORE ANY HTML is sent ---
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
            fputcsv($out, [
                $row['DataEntryID'],
                $row['LastName'],
                $row['FirstName'],
                $row['ShiftDate'],
                $row['ShiftNo'],
                $row['Hours'],
                $row['DutyType']
            ]);
        }
    }

    fclose($out);
    $stmt->close();
    $conn->close();
    exit();
}

// --- Otherwise, prepare data for page display ---
$stmt = prepare_search_stmt($conn);
$stmt->execute();
$data = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<title>My 1st PHP</title>
<style>
    body { font-family: Arial, sans-serif; text-align: center; margin-top: 20px; }
    h1 { color: #333; }
    .menu {
        margin: 20px auto;
        display: flex;
        flex-direction: column;
        gap: 15px;
        width: 200px;
    }
    a, button, input[type="submit"] {
        display: block;
        padding: 12px;
        background-color: #007BFF;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        border: none;
        font-size: 16px;
        cursor: pointer;
    }
    a:hover, button:hover, input[type="submit"]:hover { background-color: #0056b3; }
    .searchBox {
    display: flex;
    gap: 10px;
    align-items: center;
    width: 100%; /* match parent (50% of page) */
}

.searchBox select, 
.searchBox input[type="text"] {
    flex: 1; /* stretch dropdown & search bar */
    padding: 10px;
    font-size: 14px;
}

.searchBox input[type="submit"],
.searchBox a,
.searchBox button {
    flex: 0; /* keep natural size */
    white-space: nowrap;
}

    table { margin-top: 20px; }
</style>
</head>
<body>

<h1>Employee Management System</h1>
<div class="menu">
    <a href="insert.php">Insert</a>
    <a href="edit.php">Edit</a>
    <a href="delete.php">Delete</a>
</div>

<!-- Search and filter form -->
<div style="max-width:50%; margin:auto;">
    <form method="GET" class="searchBox" action="PHP_Act_02.php">
        <!-- Column selector -->
        <select name="column" style="padding:10px; font-size:14px;">
            <option value="All" <?php if(isset($_GET['column']) && $_GET['column']=='All') echo 'selected'; ?>>All</option>
            <option value="DataEntryID" <?php if(isset($_GET['column']) && $_GET['column']=='DataEntryID') echo 'selected'; ?>>Data Entry ID</option>
            <option value="LastName" <?php if(isset($_GET['column']) && $_GET['column']=='LastName') echo 'selected'; ?>>Last Name</option>
            <option value="FirstName" <?php if(isset($_GET['column']) && $_GET['column']=='FirstName') echo 'selected'; ?>>First Name</option>
            <option value="ShiftDate" <?php if(isset($_GET['column']) && $_GET['column']=='ShiftDate') echo 'selected'; ?>>Shift Date</option>
            <option value="ShiftNo" <?php if(isset($_GET['column']) && $_GET['column']=='ShiftNo') echo 'selected'; ?>>Shift Number</option>
            <option value="Hours" <?php if(isset($_GET['column']) && $_GET['column']=='Hours') echo 'selected'; ?>>Hours</option>
            <option value="DutyType" <?php if(isset($_GET['column']) && $_GET['column']=='DutyType') echo 'selected'; ?>>Duty Type</option>
        </select>

        <!-- Search box -->
        <input type="text" name="search" placeholder="Search here"
               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">

        <!-- Buttons -->
        <input type="submit" value="Search">
        <a href="PHP_Act_02.php" style="padding:12px; text-align:center;">Reset</a>
        <button type="submit" name="export" value="csv" style="padding:12px;">Export to CSV</button>
    </form>
</div>


<div style="max-height:400px; overflow-y:auto; width:60%; margin:auto;">
<h2 style="text-align:center;">Employee Data</h2>
<table border="2" cellpadding="5" style="border-collapse:collapse; width:100%;">
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
    }
} else {
    echo "<tr><td colspan='7'>No employee data found</td></tr>";
}
$stmt->close();
$conn->close();
?>
</table>
</div>

</body>
</html>
