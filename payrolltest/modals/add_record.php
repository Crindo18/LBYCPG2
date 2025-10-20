<?php
require_once '../dbconfig.php';
header('Content-Type: application/json');

try {
    $pdo->beginTransaction();

    // Check if we are adding employees or time records
    if (isset($_POST['Date'])) {
        // ADDING TIME RECORDS
        $dates = $_POST['Date'];
        $names = $_POST['Name'];
        $units = $_POST['BusinessUnit'];
        $shifts = $_POST['ShiftNumber'];
        $roles = $_POST['Role'];
        $remarks = $_POST['Remarks'];
        $timeIns = $_POST['TimeIn'];
        $timeOuts = $_POST['TimeOut'];
        $hours = $_POST['Hours'];

        // If only one record is submitted, POST values are not arrays. Convert them.
        if (!is_array($dates)) {
            $dates = [$dates];
            $names = [$names];
            $units = [$units];
            $shifts = [$shifts];
            $roles = [$roles];
            $remarks = [$remarks];
            $timeIns = [$timeIns];
            $timeOuts = [$timeOuts];
            $hours = [$hours];
        }

        $stmt = $pdo->prepare(
            "INSERT INTO payrolldata (Date, ShiftNumber, Name, BusinessUnit, Role, Remarks, TimeIn, TimeOut, Hours)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        for ($i = 0; $i < count($dates); $i++) {
            if (empty($names[$i]) || empty($dates[$i])) {
                throw new Exception("Name and Date are required for all records.");
            }
            $stmt->execute([
                $dates[$i],
                $shifts[$i] ?: null,
                $names[$i],
                $units[$i] ?: null,
                $roles[$i] ?: null,
                $remarks[$i] ?: null,
                $timeIns[$i] ?: null,
                $timeOuts[$i] ?: null,
                $hours[$i] ?: null,
            ]);
        }
        $message = count($dates) . " time record(s) added successfully.";

    } else {
        // ADDING EMPLOYEES
        $names = $_POST['Name'];
        $units = $_POST['BusinessUnit'];

        if (!is_array($names)) {
            $names = [$names];
            $units = [$units];
        }
        
        $stmt = $pdo->prepare(
            "INSERT INTO payrolldata (Name, BusinessUnit) VALUES (?, ?)"
        );
        
        for ($i = 0; $i < count($names); $i++) {
            if (empty($names[$i]) || empty($units[$i])) {
                throw new Exception("Name and Business Unit are required for all employees.");
            }
            $stmt->execute([$names[$i], $units[$i]]);
        }
        $message = count($names) . " employee(s) added successfully.";
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}