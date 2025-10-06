<?php
require __DIR__ . '/search.php';
// Include your database connection file if needed
// require_once 'connection.php'; 

// Database connection (example using MySQLi)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "act01";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$now = gmdate("D, d M Y H:i:s");
$filename = "data_export_" . date("Y-m-d g:i:s") . ".csv";
// Set headers for CSV download
header('Content-Type: text/csv');
header("Content-Disposition: attachment; filename={$filename}");

$output = fopen('php://output', 'w');

// Define CSV headers
$headers = array('DataEntryID', 'LastName', 'FirstName', 'ShiftDate', 'ShiftNo', 'Hours', 'DutyType'); 
fputcsv($output, $headers);

// Fetch data from the database (example)
$sql = search($conn);
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Ensure ordering of columns matches headers
        $line = [
            $row['DataEntryID'],
            $row['LastName'],
            $row['FirstName'],
            $row['ShiftDate'],
            $row['ShiftNo'],
            $row['Hours'],
            $row['DutyType']
        ];
        fputcsv($output, $line);
    }
} else {
    // Optionally export empty CSV with only header row (already written)
}

// Close the output stream and database connection
fclose($output);
$conn->close();
exit();
?>