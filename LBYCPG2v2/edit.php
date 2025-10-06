<!-- EDIT PAGE -->
<?php
$conn = new mysqli("localhost", "root", "", "act01");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$record = null;
$message = "";

// --- Step 1: User searches by ID ---
if (isset($_POST['search'])) {
    $id = intval($_POST['id']);
    $result = $conn->query("SELECT * FROM empdetails1 WHERE DataEntryID=$id");

    if ($result->num_rows > 0) {
        $record = $result->fetch_assoc();
    } else {
        $message = "<p style='color:red; text-align:center;'>No record found with ID $id</p>";
    }
}

// --- Step 2: User updates record ---
if (isset($_POST['update'])) {
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
        $message = "<p style='color:green; text-align:center;'>Record updated successfully</p>";
        // Reload updated data
        $result = $conn->query("SELECT * FROM empdetails1 WHERE DataEntryID=$id");
        if ($result->num_rows > 0) $record = $result->fetch_assoc();
    } else {
        $message = "<p style='color:red; text-align:center;'>Error updating record: " . $conn->error . "</p>";
    }
}
?>
<body>
    <h1 style="text-align:center; margin-top:20px;">Edit Employee Data</h1>
    <?php echo $message; ?>

    <div class="container">
        <!-- Always show ID input form -->
        <form method="POST" action="">
            <label>Enter DataEntryID:</label>
            <input type="number" name="id" 
                   value="<?php echo isset($_POST['id']) ? htmlspecialchars($_POST['id']) : ''; ?>" 
                   required><br>
            <input type="submit" name="search" value="Find Record">
        </form>

        <!-- Only show details if a record is found -->
        <?php if ($record): ?>
            <form method="POST" action="">
                <input type="hidden" name="id" value="<?php echo $record['DataEntryID']; ?>">

                <label>Last Name:</label>
                <input type="text" name="lname" value="<?php echo $record['LastName']; ?>"><br>

                <label>First Name:</label>
                <input type="text" name="fname" value="<?php echo $record['FirstName']; ?>"><br>

                <label>Shift Date:</label>
                <input type="date" name="sdate" value="<?php echo $record['ShiftDate']; ?>"><br>

                <label>Shift Number:</label>
                <input type="text" name="snumber" value="<?php echo $record['ShiftNo']; ?>"><br>

                <label>Hours:</label>
                <input type="number" name="hours" value="<?php echo $record['Hours']; ?>"><br>

                <label>Duty Type:</label>
                <select name="dtype" required>
                    <option value="OnDuty"    <?php if($record['DutyType']=="OnDuty") echo "selected"; ?>>On Duty</option>
                    <option value="Overtime"  <?php if($record['DutyType']=="Overtime") echo "selected"; ?>>Overtime</option>
                    <option value="Late"      <?php if($record['DutyType']=="Late") echo "selected"; ?>>Late</option>
                </select><br>

                <input type="submit" name="update" value="Update Record">
            </form>
        <?php endif; ?>

        <div class="menu">
            <a href="PHP_Act_02.php">Home</a>
            <a href="insert.php">Insert</a>
            <a href="delete.php">Delete</a>
        </div>
    </div>

    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 0; }
        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 40px;
        }
        form {
            background: #fff;
            padding: 25px 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            width: 320px;
            display: flex;
            flex-direction: column;
            margin-bottom: 20px;
        }
        form label { font-weight: bold; margin-bottom: 5px; }
        form input, form select {
            padding: 5px;
            font-size: 12px;
            border-radius: 5px;
            border: 1px solid #ccc;
            margin-bottom: 10px;
        }
        form input[type="submit"] {
            background-color: #007BFF;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }
        form input[type="submit"]:hover { background-color: #0056b3; }
        .menu {
            margin-top: 15px;
            display: flex;
            gap: 20px;
        }
        .menu a {
            padding: 10px 15px;
            background-color: #007BFF;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 12px;
        }
        .menu a:hover { background-color: #0056b3; }
    </style>
</body>
