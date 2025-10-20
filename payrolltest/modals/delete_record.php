<?php
require_once '../dbconfig.php';
header('Content-Type: application/json');

try {
    if (empty($_POST['ID'])) throw new Exception("Invalid record ID.");

    $stmt = $pdo->prepare("DELETE FROM payrolldata WHERE ID = :ID");
    $stmt->execute([':ID' => $_POST['ID']]);

    echo json_encode(['success' => true, 'message' => 'Record deleted successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
