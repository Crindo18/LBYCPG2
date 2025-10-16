<?php
require_once '../dbconfig.php';
header('Content-Type: application/json');

try {
    if (empty($_POST['ID'])) throw new Exception("Invalid record ID.");

    $stmt = $pdo->prepare("
        UPDATE payrolldata 
        SET Name=:Name, Role=:Role, BusinessUnit=:BusinessUnit, Remarks=:Remarks, 
            Deductions=:Deductions, Extra=:Extra
        WHERE ID=:ID
    ");
    $stmt->execute([
        ':Name' => trim($_POST['Name']),
        ':Role' => trim($_POST['Role']),
        ':BusinessUnit' => trim($_POST['BusinessUnit']),
        ':Remarks' => trim($_POST['Remarks']),
        ':Deductions' => $_POST['Deductions'] ?? 0,
        ':Extra' => $_POST['Extra'] ?? '',
        ':ID' => $_POST['ID']
    ]);

    echo json_encode(['success' => true, 'message' => 'Employee updated successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
