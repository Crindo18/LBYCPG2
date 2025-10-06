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


$message = ''; // message to show after operation

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Detect which form submitted based on a hidden input
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'insert') {
            $lname  = $_POST['lname'];
            $fname  = $_POST['fname'];
            $sdate  = $_POST['sdate'];
            $snumber = $_POST['snumber'];
            $hours  = $_POST['hours'];
            $dtype  = $_POST['dtype'];

            $sql = "INSERT INTO empdetails1 (LastName, FirstName, ShiftDate, ShiftNo, Hours, DutyType) 
                    VALUES ('$lname', '$fname', '$sdate', '$snumber', '$hours', '$dtype')";

            if ($conn->query($sql) === TRUE) {
                header("Location: modify.php?msg=success");
                exit();
            } else {
                $message = "<p style='color:red; text-align:center;'>Error: " . $conn->error . "</p>";
            }
        }

        elseif ($action == 'edit') {
            $id      = $_POST['id'];
            $lname   = $_POST['lname'];
            $fname   = $_POST['fname'];
            $sdate   = $_POST['sdate'];
            $snumber = $_POST['snumber'];
            $hours   = $_POST['hours'];
            $dtype   = $_POST['dtype'];

            $sql = "UPDATE empdetails1 
                    SET LastName='$lname', FirstName='$fname', ShiftDate='$sdate', 
                        ShiftNo='$snumber', Hours='$hours', DutyType='$dtype' 
                    WHERE DataEntryID=$id";

            if ($conn->query($sql) === TRUE) {
                header("Location: modify.php?msg=edit_success");
                exit();
            } else {
                $message = "<p style='color:red; text-align:center;'>Error updating record: " . $conn->error . "</p>";
            }
        }

        elseif ($action == 'delete') {
            $id = $_POST['id'];
            $sql = "DELETE FROM empdetails1 WHERE DataEntryID=$id";
            if ($conn->query($sql) === TRUE) {
                header("Location: modify.php?msg=delete_success");
                exit();
            } else {
                $message = "<p style='color:red; text-align:center;'>Error deleting record: " . $conn->error . "</p>";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
<title>Modify Employee Data</title>
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

    /* --- TOP BAR --- */
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

    /* --- SIDEBAR --- */
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


    /* --- MAIN CONTENT --- */
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
        margin-left: 5%;
    }

    /* --- CONTENT CONTAINER (Search + Table) --- */
    .data-container {
        width: 90%;
        margin: 20px auto;
        background-color: #ffffff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }

    /* --- SEARCH & FILTER --- */
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

    /* --- TABLE --- */
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

/* CRUD Container */
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

    /* Tabs */
.tabs {
    display: flex;
    border-bottom: 3px solid #273c75;
    margin-bottom: 15px;
    justify-content: space-between;
    width: 100%;
}

/* Make tabs expand equally across the container */
.tab {
    flex: 1; /* equal width */
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

    /* Hide all tab contents by default */
    .tab-content {
        display: none;
        min-height: 400px;
        align-items: center;
        justify-content: center;
    }
    .tab-content.active {
        display: flex;
    }

/* Centered form */
.modify-form {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    max-width: 350px;
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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

/* Delete button style */
#delete input[type="submit"] {
    background-color: #e84118;
}
#delete input[type="submit"]:hover {
    background-color: #c23616;
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
    <h1>Modify Employee Data</h1>

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

        <!-- Table -->
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

                $stmt->close();
                $conn->close();
                ?>
            </table>
        </div>
    </div>

    <!-- Separate CRUD Section -->
        <div class="crud-container">
            <div class="tabs">
                <div class="tab active" onclick="showTab('insert')">Insert</div>
                <div class="tab" onclick="showTab('edit')">Edit</div>
                <div class="tab" onclick="showTab('delete')">Delete</div>
            </div>

            <!-- Insert -->
            <div class="tab-content active" id="insert">
                <form class="modify-form" method="POST">
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
            <div class="tab-content" id="edit">
                <form class="modify-form"method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="number" name="id" placeholder="DataEntryID to Edit" required>
                    <input type="text" name="lname" placeholder="Last Name">
                    <input type="text" name="fname" placeholder="First Name">
                    <input type="date" name="sdate">
                    <input type="text" name="snumber" placeholder="Shift No">
                    <input type="number" name="hours" placeholder="Hours">
                    <select name="dtype" required>
                        <option value="OnDuty">On Duty</option>
                        <option value="Overtime">Overtime</option>
                        <option value="Late">Late</option>
                    </select>
                    <input type="submit" value="Update">
                </form>
            </div>

            <!-- Delete -->
            <div class="tab-content" id="delete">
                <form class="modify-form delete-btn" method="POST" class="delete-btn">
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

            document.querySelector(`.tab[onclick="showTab('${tabName}')"]`).classList.add('active');
            document.getElementById(tabName).classList.add('active');
        }

        // Show the default tab (Insert)
        document.addEventListener('DOMContentLoaded', () => {
            showTab('insert');
        });
    </script>

</div>

</body>
</html>

