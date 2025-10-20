<?php
require_once '../dbconfig.php';
header('Content-Type: application/json');

try {
    if (empty($_POST['ID'])) throw new Exception("Invalid record ID.");

    // Handle arrays from the form (Name[], Date[], etc.) by taking the first element
    $getName = isset($_POST['Name']) ? $_POST['Name'] : (isset($_POST['Name'][0]) ? $_POST['Name'][0] : null);
    $getDate = isset($_POST['Date']) ? $_POST['Date'] : (isset($_POST['Date'][0]) ? $_POST['Date'][0] : null);
    
    // If Name is an array, get first element
    if (is_array($getName)) $getName = $getName[0];
    if (is_array($getDate)) $getDate = $getDate[0];

    // Detect if timetracking or employee
    if (!empty($getDate)) {
        // TIMETRACKING UPDATE
        
        // Extract values (handle both array and single values)
        $name = is_array($_POST['Name']) ? $_POST['Name'][0] : $_POST['Name'];
        $date = is_array($_POST['Date']) ? $_POST['Date'][0] : $_POST['Date'];
        $shiftNumber = isset($_POST['ShiftNumber']) ? (is_array($_POST['ShiftNumber']) ? $_POST['ShiftNumber'][0] : $_POST['ShiftNumber']) : null;
        $role = isset($_POST['Role']) ? (is_array($_POST['Role']) ? $_POST['Role'][0] : $_POST['Role']) : null;
        $businessUnit = isset($_POST['BusinessUnit']) ? (is_array($_POST['BusinessUnit']) ? $_POST['BusinessUnit'][0] : $_POST['BusinessUnit']) : null;
        $timeIn = isset($_POST['TimeIn']) ? (is_array($_POST['TimeIn']) ? $_POST['TimeIn'][0] : $_POST['TimeIn']) : null;
        $timeOut = isset($_POST['TimeOut']) ? (is_array($_POST['TimeOut']) ? $_POST['TimeOut'][0] : $_POST['TimeOut']) : null;
        $hours = isset($_POST['Hours']) ? (is_array($_POST['Hours']) ? $_POST['Hours'][0] : $_POST['Hours']) : null;
        $remarks = isset($_POST['Remarks']) ? (is_array($_POST['Remarks']) ? $_POST['Remarks'][0] : $_POST['Remarks']) : null;
        $deductions = isset($_POST['Deductions']) ? (is_array($_POST['Deductions']) ? $_POST['Deductions'][0] : $_POST['Deductions']) : 0;
        $extra = isset($_POST['Extra']) ? (is_array($_POST['Extra']) ? $_POST['Extra'][0] : $_POST['Extra']) : 0;
        
        // Convert deductions to negative if positive (for storage)
        $deductionValue = floatval($deductions);
        if ($deductionValue > 0) {
            $deductionValue = -$deductionValue;
        }
        
        $stmt = $pdo->prepare("
            UPDATE payrolldata 
            SET Date=:Date, ShiftNumber=:ShiftNumber, Name=:Name, Role=:Role, BusinessUnit=:BusinessUnit,
                TimeIn=:TimeIn, TimeOut=:TimeOut, Hours=:Hours, Remarks=:Remarks, Deductions=:Deductions, Extra=:Extra
            WHERE ID=:ID
        ");
        $stmt->execute([
            ':Date' => $date,
            ':ShiftNumber' => $shiftNumber ?: null,
            ':Name' => $name,
            ':Role' => $role ?: null,
            ':BusinessUnit' => $businessUnit ?: null,
            ':TimeIn' => $timeIn ?: null,
            ':TimeOut' => $timeOut ?: null,
            ':Hours' => $hours ?: null,
            ':Remarks' => $remarks ?: null,
            ':Deductions' => $deductionValue,
            ':Extra' => floatval($extra),
            ':ID' => $_POST['ID']
        ]);
        echo json_encode(['success' => true, 'message' => 'Time record updated successfully.']);
    } else {
        // EMPLOYEE UPDATE
        $name = is_array($_POST['Name']) ? $_POST['Name'][0] : $_POST['Name'];
        $businessUnit = is_array($_POST['BusinessUnit']) ? $_POST['BusinessUnit'][0] : $_POST['BusinessUnit'];
        
        $stmt = $pdo->prepare("
            UPDATE payrolldata 
            SET Name=:Name, BusinessUnit=:BusinessUnit
            WHERE ID=:ID
        ");
        $stmt->execute([
            ':Name' => $name,
            ':BusinessUnit' => $businessUnit,
            ':ID' => $_POST['ID']
        ]);
        echo json_encode(['success' => true, 'message' => 'Employee updated successfully.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}