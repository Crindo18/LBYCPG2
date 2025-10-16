<?php
require_once '../dbconfig.php';
header('Content-Type: application/json');
try {
    if(empty($_POST['ID'])) throw new Exception("Invalid ID.");
    $stmt = $pdo->prepare("
        UPDATE payrolldata 
        SET Date=:Date, ShiftNumber=:ShiftNumber, Name=:Name, BusinessUnit=:BusinessUnit, Role=:Role,
            TimeIn=:TimeIn, TimeOut=:TimeOut, Hours=:Hours, Remarks=:Remarks
        WHERE ID=:ID
    ");
    $stmt->execute([
        ':Date'=>$_POST['Date'],
        ':ShiftNumber'=>$_POST['ShiftNumber'],
        ':Name'=>$_POST['Name'],
        ':BusinessUnit'=>$_POST['BusinessUnit'],
        ':Role'=>$_POST['Role'],
        ':TimeIn'=>$_POST['TimeIn'],
        ':TimeOut'=>$_POST['TimeOut'],
        ':Hours'=>$_POST['Hours'],
        ':Remarks'=>$_POST['Remarks'],
        ':ID'=>$_POST['ID']
    ]);
    echo json_encode(['success'=>true,'message'=>'Time record updated successfully.']);
} catch(Exception $e){
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
