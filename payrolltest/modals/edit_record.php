<?php
require_once '../dbconfig.php';
header('Content-Type: application/json');

try {
    if (empty($_POST['ID'])) throw new Exception("Invalid record ID.");

    // Detect if timetracking or employee
    if (!empty($_POST['Date']) && !empty($_POST['ShiftNumber'])) {
        // TIMETRACKING UPDATE
        $stmt = $pdo->prepare("
            UPDATE payrolldata 
            SET Date=:Date, ShiftNumber=:ShiftNumber, Name=:Name, Role=:Role, BusinessUnit=:BusinessUnit,
                TimeIn=:TimeIn, TimeOut=:TimeOut, Hours=:Hours, Remarks=:Remarks
            WHERE ID=:ID
        ");
        $stmt->execute([
            ':Date'=>$_POST['Date'],
            ':ShiftNumber'=>$_POST['ShiftNumber'],
            ':Name'=>$_POST['Name'],
            ':Role'=>$_POST['Role'],
            ':BusinessUnit'=>$_POST['BusinessUnit'],
            ':TimeIn'=>$_POST['TimeIn'],
            ':TimeOut'=>$_POST['TimeOut'],
            ':Hours'=>$_POST['Hours'],
            ':Remarks'=>$_POST['Remarks'],
            ':ID'=>$_POST['ID']
        ]);
        echo json_encode(['success'=>true,'message'=>'Time record updated successfully.']);
    } else {
        // EMPLOYEE UPDATE
        $stmt = $pdo->prepare("
            UPDATE payrolldata 
            SET Name=:Name, BusinessUnit=:BusinessUnit
            WHERE ID=:ID
        ");
        $stmt->execute([
            ':Name'=>$_POST['Name'],
            ':BusinessUnit'=>$_POST['BusinessUnit'],
            ':ID'=>$_POST['ID']
        ]);
        echo json_encode(['success'=>true,'message'=>'Employee updated successfully.']);
    }
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
