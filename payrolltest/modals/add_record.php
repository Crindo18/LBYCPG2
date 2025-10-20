<?php
require_once '../dbconfig.php';
header('Content-Type: application/json');

try {
    // Detect if this is a timetracking entry or employee entry
    if (!empty($_POST['Date']) && !empty($_POST['ShiftNumber'])) {
        // TIMETRACKING ENTRY
        $required = ['Date','ShiftNumber','Name','Role','BusinessUnit','TimeIn','TimeOut','Hours'];
        foreach ($required as $r) {
            if (empty($_POST[$r])) throw new Exception("Please fill out all required fields ($r).");
        }

        $stmt = $pdo->prepare("
            INSERT INTO payrolldata 
            (Date, ShiftNumber, Name, Role, BusinessUnit, TimeIn, TimeOut, Hours, Remarks)
            VALUES (:Date, :ShiftNumber, :Name, :Role, :BusinessUnit, :TimeIn, :TimeOut, :Hours, :Remarks)
        ");
        $stmt->execute([
            ':Date' => $_POST['Date'],
            ':ShiftNumber' => $_POST['ShiftNumber'],
            ':Name' => $_POST['Name'],
            ':Role' => $_POST['Role'],
            ':BusinessUnit' => $_POST['BusinessUnit'],
            ':TimeIn' => $_POST['TimeIn'],
            ':TimeOut' => $_POST['TimeOut'],
            ':Hours' => $_POST['Hours'],
            ':Remarks' => $_POST['Remarks'] ?? ''
        ]);

        echo json_encode(['success'=>true,'message'=>'Time record added successfully.']);
    } else {
        // EMPLOYEE ENTRY
        $required = ['Name','BusinessUnit'];
        foreach ($required as $r) {
            if (empty($_POST[$r])) throw new Exception("Please fill out all required fields ($r).");
        }

        $stmt = $pdo->prepare("
            INSERT INTO payrolldata (Name, BusinessUnit)
            VALUES (:Name, :BusinessUnit)
        ");
        $stmt->execute([
            ':Name' => $_POST['Name'],
            ':BusinessUnit' => $_POST['BusinessUnit']
        ]);

        echo json_encode(['success'=>true,'message'=>'Employee added successfully.']);
    }
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
