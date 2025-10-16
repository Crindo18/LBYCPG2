<?php
require_once '../dbconfig.php';
header('Content-Type: application/json');

try {
    $required = ['Name','Role','BusinessUnit','Remarks'];
    foreach ($required as $r) {
        if (empty($_POST[$r])) throw new Exception("Please fill out all required fields.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO payrolldata (Name, Role, BusinessUnit, Remarks, Deductions, Extra)
        VALUES (:Name, :Role, :BusinessUnit, :Remarks, :Deductions, :Extra)
    ");
    $stmt->execute([
        ':Name' => trim($_POST['Name']),
        ':Role' => trim($_POST['Role']),
        ':BusinessUnit' => trim($_POST['BusinessUnit']),
        ':Remarks' => trim($_POST['Remarks']),
        ':Deductions' => $_POST['Deductions'] ?? 0,
        ':Extra' => $_POST['Extra'] ?? ''
    ]);

    echo json_encode(['success' => true, 'message' => 'Employee added successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
