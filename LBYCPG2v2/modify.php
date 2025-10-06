<?php
// initialization of server or mysql details
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "act01";

// this line connects the php to the mysql database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";
$fetchedRecord = null;

// this is the function for the search bar, already connected with the filter
function searchBar($conn) {
    $search_raw = isset($_GET['search']) ? trim($_GET['search']) : '';
    $column_raw = isset($_GET['column']) ? $_GET['column'] : 'All';
    $allowed_columns = ['All','DataEntryID','LastName','FirstName','ShiftDate','ShiftNo','Hours','DutyType'];

    if ($search_raw !== '' && in_array($column_raw, $allowed_columns)) {
        // if-then statement for filtering
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

// if-then statements for insert, edit, or delete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    // insert function
    if ($action == 'insert') {
        $lname   = isset($_POST['lname']) ? $_POST['lname'] : '';
        $fname   = isset($_POST['fname']) ? $_POST['fname'] : '';
        $sdate   = isset($_POST['sdate']) ? $_POST['sdate'] : '';
        $snumber = isset($_POST['snumber']) ? $_POST['snumber'] : '';
        $hours   = isset($_POST['hours']) ? intval($_POST['hours']) : 0;
        $dtype   = isset($_POST['dtype']) ? $_POST['dtype'] : '';

        $sql = "INSERT INTO empdetails1 (LastName, FirstName, ShiftDate, ShiftNo, Hours, DutyType)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssis', $lname, $fname, $sdate, $snumber, $hours, $dtype);
        if ($stmt->execute()) {
            $message = "<p style='color:green; text-align:center;'>Record inserted successfully.</p>";
        } else {
            $message = "<p style='color:red; text-align:center;'>Insert error: " . htmlspecialchars($stmt->error) . "</p>";
        }
        $stmt->close();
    }

    // line to fetch data according to dataentryid inputted
    elseif ($action == 'fetch') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $sql = "SELECT * FROM empdetails1 WHERE DataEntryID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $fetchedRecord = $res->fetch_assoc();
        } else {
            $message = "<p style='color:red; text-align:center;'>No record found with ID " . htmlspecialchars($id) . ".</p>";
        }
        $stmt->close();
    }

    // update/edit function for editing fetched data
    elseif ($action == 'edit') {
        $id      = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $lname   = isset($_POST['lname']) ? $_POST['lname'] : '';
        $fname   = isset($_POST['fname']) ? $_POST['fname'] : '';
        $sdate   = isset($_POST['sdate']) ? $_POST['sdate'] : '';
        $snumber = isset($_POST['snumber']) ? $_POST['snumber'] : '';
        $hours   = isset($_POST['hours']) ? intval($_POST['hours']) : 0;
        $dtype   = isset($_POST['dtype']) ? $_POST['dtype'] : '';

        $sql = "UPDATE empdetails1
                SET LastName = ?, FirstName = ?, ShiftDate = ?, ShiftNo = ?, Hours = ?, DutyType = ?
                WHERE DataEntryID = ?";
        $stmt = $conn->prepare($sql);
        
        $stmt->bind_param('ssssisi', $lname, $fname, $sdate, $snumber, $hours, $dtype, $id);
        if ($stmt->execute()) {
            // reload updated record into $fetchedRecord so the edit form shows the updated values
            $msg_success = $stmt->affected_rows >= 0 ? "Record updated successfully." : "No changes made.";
            $message = "<p style='color:green; text-align:center;'>" . htmlspecialchars($msg_success) . "</p>";

            $stmt->close();

            $stmt2 = $conn->prepare("SELECT * FROM empdetails1 WHERE DataEntryID = ?");
            $stmt2->bind_param('i', $id);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            if ($res2 && $res2->num_rows > 0) {
                $fetchedRecord = $res2->fetch_assoc();
            }
            $stmt2->close();
        } else {
            $message = "<p style='color:red; text-align:center;'>Error updating record: " . htmlspecialchars($stmt->error) . "</p>";
            $stmt->close();
        }
    }

    // line for the delete menu
    elseif ($action == 'delete') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $sql = "DELETE FROM empdetails1 WHERE DataEntryID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $message = "<p style='color:green; text-align:center;'>Record deleted successfully.</p>";
        } else {
            $message = "<p style='color:red; text-align:center;'>Error deleting record: " . htmlspecialchars($stmt->error) . "</p>";
        }
        $stmt->close();
        // ensure fetchedRecord is cleared if it matched the deleted id
        if ($fetchedRecord && isset($fetchedRecord['DataEntryID']) && intval($fetchedRecord['DataEntryID']) === $id) {
            $fetchedRecord = null;
        }
    }
}

// if-then to export to csv
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmtExport = searchBar($conn);
    $stmtExport->execute();
    $result = $stmtExport->get_result();

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
    $stmtExport->close();
    $conn->close();
    exit();
}

// display data according to searchbar
$stmt = searchBar($conn);
$stmt->execute();
$data = $stmt->get_result();

?>

<!-- CSS and HTML portion of the page -->
<!DOCTYPE html>
<html>
<head>
<title>Modify Employee Data</title>
<meta charset="utf-8">
<style>
    * { box-sizing: border-box; }
    /* --- Body CSS --- */
    body {
        margin: 0;
        font-family: Arial, sans-serif;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        background-color: #f5f6fa;
        color: #333;
    }

    /* --- Sidebar CSS --- */
    .topbar {
        background-color: #f0f0f0; /* light gray close to white */
        height: 10px;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding: 0 20px;
        border-bottom: 1px solid #ddd;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 10;
    }
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
        padding: 50px 20px 20px 20px; 
        flex: 1; 
        overflow-y: auto; 
    }
    .content h1 { 
        text-align: left; 
        color: #333; 
        margin-top: 0 ; 
        margin-left: 5%; 
    }
    /* --- Content Container (Filter + Search + Table) CSS --- */
    .data-container { 
        width: 90%; 
        margin: 20px auto; 
        background-color: #ffffff; 
        border-radius: 8px; padding: 20px; 
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
    .searchBox a, .searchBox button {
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

    /* --- CRUD CSS --- */
    .crud-container { 
        width: 90%; 
        margin: 20px auto; 
        background: #ffffff; 
        border-radius: 8px; 
        padding: 20px; 
        box-shadow: 0 2px 6px rgba(0,0,0,0.1); 
        display: flex; 
        flex-direction: column; 
    }

    .tabs { 
        display: flex; 
        border-bottom: 3px solid #273c75; 
        margin-bottom: 15px; 
        justify-content: space-between; 
        width: 100%; 
    }

    .tab { 
        flex: 1; 
        text-align: center; 
        padding: 14px 0; 
        cursor: pointer; 
        background: #dcdde1; 
        border-top-left-radius: 10px; 
        border-top-right-radius: 10px; 
        font-weight: bold; 
        transition: 0.3s; 
        color: #333; 
    }

    .tab:not(:last-child) { 
        margin-right: 5px; 
    }

    .tab.active { 
        background: #273c75; 
        color: white; 
        box-shadow: inset 0 -3px 0 #192a56; 
    }

    .tab-content { 
        display: none; 
        min-height: 200px; 
        align-items: center; 
        justify-content: center; 
        padding: 15px; 
    }

    .tab-content.active { 
        display: block; 
    }

    .modify-form { 
        display: flex; 
        flex-direction: column; 
        gap: 10px; width: 100%; 
        max-width: 420px; 
        background: #f9f9f9; 
        padding: 20px; 
        border-radius: 8px; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
        margin: 0 auto; 
    }

    .modify-form input, 
    .modify-form select { 
        padding: 10px; 
        border-radius: 6px; 
        border: 1px solid #ccc; 
        font-size: 14px; 
    }

    .modify-form input[type="submit"] { 
        background-color: #273c75; 
        color: white; 
        border: none; 
        cursor: pointer; 
        transition: 0.3s; 
    }

    .modify-form input[type="submit"]:hover { 
        background-color: #192a56; 
    }

    #delete input[type="submit"] { 
        background-color: #e84118; 
    }

    #delete input[type="submit"]:hover { 
        background-color: #c23616; 
    }

    @media (max-width: 800px) {
        .sidebar { display: none; }
        .content { margin-left: 0; padding: 60px 10px; }
    }
</style>
</head>
<body>

<!-- Side Bar Menu Options -->
<div class="sidebar">
    <div class="menu-title">Menu</div>
    <a href="PHP_Act_02.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'PHP_Act_02.php' ? 'active' : ''; ?>">Overview</a>
    <a href="modify.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'modify.php' ? 'active' : ''; ?>">Modify</a>
</div>

<div class="content">
    <h1>Modify Employee Data</h1>

    <div class="data-container">
        <!-- Search and Filter -->
        <div class="search-container">
            <form method="GET" class="searchBox" action="modify.php">
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
                <a href="modify.php">Reset</a>
                <button type="submit" name="export" value="csv">Export CSV</button>
            </form>
        </div>

        <!-- Table -->
        <div class="table-container" role="region" aria-label="Employee table">
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
                for ($i = $rowCount; $i < 10; $i++) {
                    echo "<tr><td colspan='7'>&nbsp;</td></tr>";
                }

                $stmt->close();
                ?>
            </table>
        </div>
    </div>

    <!-- CRUD Section -->
    <div class="crud-container">
        <?php if ($message) echo $message; ?>

        <div class="tabs" role="tablist">
            <div class="tab <?php echo (!isset($fetchedRecord) ? 'active' : ''); ?>" onclick="showTab('insert')">Insert</div>
            <div class="tab <?php echo (isset($fetchedRecord) ? 'active' : ''); ?>" onclick="showTab('edit')">Edit</div>
            <div class="tab" onclick="showTab('delete')">Delete</div>
        </div>

        <!-- Insert -->
        <div class="tab-content <?php echo (!isset($fetchedRecord) ? 'active' : ''); ?>" id="insert">
            <form class="modify-form" method="POST" action="modify.php">
                <input type="hidden" name="action" value="insert">
                <input type="text" name="lname" placeholder="Last Name" required>
                <input type="text" name="fname" placeholder="First Name" required>
                <input type="date" name="sdate" required>
                <input type="text" name="snumber" placeholder="Shift No" required>
                <input type="number" name="hours" placeholder="Hours" required>
                <select name="dtype" required>
                    <option value="OnDuty">On Duty</option>
                    <option value="Overtime">Overtime</option>
                    <option value="Late">Late</option>
                </select>
                <input type="submit" value="Insert">
            </form>
        </div>

        <!-- Edit -->
        <div class="tab-content <?php echo (isset($fetchedRecord) ? 'active' : ''); ?>" id="edit">
            <!-- Fetch data form -->
            <form class="modify-form" method="POST" action="modify.php" style="max-width:380px;">
                <input type="hidden" name="action" value="fetch">
                <label style="font-weight:bold;">Enter DataEntryID to fetch record</label>
                <input type="number" name="id" placeholder="DataEntryID" required>
                <input type="submit" value="Fetch Record">
            </form>

            <!-- Fetched Data -->
            <?php if ($fetchedRecord): ?>
                <form class="modify-form" method="POST" action="modify.php" style="margin-top:16px;">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($fetchedRecord['DataEntryID']); ?>">

                    <label style="font-weight:bold;">Last Name</label>
                    <input type="text" name="lname" value="<?php echo htmlspecialchars($fetchedRecord['LastName']); ?>" required>

                    <label style="font-weight:bold;">First Name</label>
                    <input type="text" name="fname" value="<?php echo htmlspecialchars($fetchedRecord['FirstName']); ?>" required>

                    <label style="font-weight:bold;">Shift Date</label>
                    <input type="date" name="sdate" value="<?php echo htmlspecialchars($fetchedRecord['ShiftDate']); ?>" required>

                    <label style="font-weight:bold;">Shift Number</label>
                    <input type="text" name="snumber" value="<?php echo htmlspecialchars($fetchedRecord['ShiftNo']); ?>" required>

                    <label style="font-weight:bold;">Hours</label>
                    <input type="number" name="hours" value="<?php echo htmlspecialchars($fetchedRecord['Hours']); ?>" required>

                    <label style="font-weight:bold;">Duty Type</label>
                    <select name="dtype" required>
                        <option value="OnDuty" <?php if($fetchedRecord['DutyType']=="OnDuty") echo "selected"; ?>>On Duty</option>
                        <option value="Overtime" <?php if($fetchedRecord['DutyType']=="Overtime") echo "selected"; ?>>Overtime</option>
                        <option value="Late" <?php if($fetchedRecord['DutyType']=="Late") echo "selected"; ?>>Late</option>
                    </select>

                    <input type="submit" value="Update Record">
                </form>
            <?php endif; ?>
        </div>

        <!-- Delete -->
        <div class="tab-content" id="delete">
            <form class="modify-form delete-btn" method="POST" action="modify.php">
                <input type="hidden" name="action" value="delete">
                <input type="number" name="id" placeholder="DataEntryID to Delete" required>
                <input type="submit" value="Delete">
            </form>
        </div>
    </div>
</div>

<script>
    function showTab(tabName) {
        const tabs = document.querySelectorAll('.tab');
        const contents = document.querySelectorAll('.tab-content');
        tabs.forEach(tab => tab.classList.remove('active'));
        contents.forEach(c => c.classList.remove('active'));

        // activate matching tab
        const clicked = Array.from(tabs).find(t => t.getAttribute('onclick') === "showTab('" + tabName + "')");
        if (clicked) clicked.classList.add('active');

        const content = document.getElementById(tabName);
        if (content) content.classList.add('active');
    }

    window.addEventListener('DOMContentLoaded', () => {
        <?php if ($fetchedRecord): ?>
            showTab('edit');
        <?php else: ?>
            showTab('insert');
        <?php endif; ?>
    });
</script>

</body>
</html>

<?php
// Close DB connection
$conn->close();
?>
