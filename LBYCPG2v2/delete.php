<!-- DELETE PAGE -->
<?php
$conn = new mysqli("localhost", "root", "", "act01");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $sql = "DELETE FROM empdetails1 WHERE DataEntryID=$id";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green; text-align:center;'>Record deleted successfully</p>";
    } else {
        echo "<p style='color:red; text-align:center;'>Error deleting record: " . $conn->error . "</p>";
    }
}
?>
<body>
    <h1 style="text-align:center; margin-top:20px;">Delete Employee Data</h1>

    <div class="container">
        <form method="POST" action="">
            <label>Enter DataEntryID to delete:</label>
            <input type="number" name="id" required><br>
            <input type="submit" value="Delete">
        </form>

        <div class="menu">
            <a href="PHP_Act_02.php">Home</a>
            <a href="edit.php">Edit</a>
            <a href="insert.php">Insert</a>
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
        }
        form label { 
            font-weight: bold; 
            margin-bottom: 5px; 
        }
        form input {
            padding: 5px;
            font-size: 12px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        form input[type="submit"] {
            background-color: #dc3545;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }
        form input[type="submit"]:hover { background-color: #a71d2a; }
        .menu {
            margin-top: 25px;
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
        .menu a:hover { background-color: #007BFF; }
    </style>
</body>
