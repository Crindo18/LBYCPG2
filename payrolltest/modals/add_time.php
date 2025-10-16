<?php
require_once '../dbconfig.php';
header('Content-Type: application/json');
try {
    $stmt = $pdo->prepare("
        INSERT INTO payrolldata (Date, ShiftNumber, Name, BusinessUnit, Role, TimeIn, TimeOut, Hours, Remarks)
        VALUES (:Date, :ShiftNumber, :Name, :BusinessUnit, :Role, :TimeIn, :TimeOut, :Hours, :Remarks)
    ");
    $stmt->execute([
        ':Date' => $_POST['Date'],
        ':ShiftNumber' => $_POST['ShiftNumber'],
        ':Name' => $_POST['Name'],
        ':BusinessUnit' => $_POST['BusinessUnit'],
        ':Role' => $_POST['Role'],
        ':TimeIn' => $_POST['TimeIn'],
        ':TimeOut' => $_POST['TimeOut'],
        ':Hours' => $_POST['Hours'],
        ':Remarks' => $_POST['Remarks']
    ]);
    echo json_encode(['success'=>true,'message'=>'Time record added successfully.']);
} catch(Exception $e){
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
