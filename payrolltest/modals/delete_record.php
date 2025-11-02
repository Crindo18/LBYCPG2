<?php
require_once '../dbconfig.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$name = $_POST['Name'] ?? '';

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Employee name is required']);
    exit;
}

try {
    // Delete all records for this employee
    $stmt = $pdo->prepare("DELETE FROM payrolldata WHERE Name = ?");
    $stmt->execute([$name]);
    
    $deletedCount = $stmt->rowCount();
    
    echo json_encode([
        'success' => true, 
        'message' => "Deleted $deletedCount records for $name",
        'count' => $deletedCount
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>