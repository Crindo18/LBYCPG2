<?php
require_once 'dbconfig.php';
require_once 'calculate_rates.php';
header('Content-Type: application/json');

// --- Helper Function: Fetch and compute payroll summary ---
function getPayrollSummary($pdo, $startDate = null, $endDate = null)
{
    // Default date range = current month
    if (!$startDate) {
        $startDate = date('Y-m-01');
    }
    if (!$endDate) {
        $endDate = date('Y-m-t');
    }

    // Fetch time tracking data joined with employee info
    $stmt = $pdo->prepare("
        SELECT 
            e.EmployeeID,
            e.Name,
            e.Role,
            t.Date,
            t.TimeIn,
            t.TimeOut,
            t.Status
        FROM timetracking t
        INNER JOIN employees e ON e.EmployeeID = t.EmployeeID
        WHERE t.Date BETWEEN :start AND :end
        ORDER BY e.EmployeeID, t.Date
    ");
    $stmt->execute(['start' => $startDate, 'end' => $endDate]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$records) {
        return ["message" => "No records found between $startDate and $endDate."];
    }

    // Group records by employee
    $payrollData = [];
    foreach ($records as $row) {
        $id = $row['EmployeeID'];
        if (!isset($payrollData[$id])) {
            $payrollData[$id] = [
                'EmployeeID' => $row['EmployeeID'],
                'Name' => $row['Name'],
                'Role' => $row['Role'],
                'DaysWorked' => 0,
                'TotalHours' => 0,
                'GrossPay' => 0,
                'Deductions' => 0,
                'NetPay' => 0
            ];
        }

        // --- Calculate daily pay using calculate_rates.php logic ---
        $dailyComputation = calculateEmployeeDailyRate(
            $row['Role'],
            $row['TimeIn'],
            $row['TimeOut'],
            $row['Status'],
            $row['Date']
        );

        $payrollData[$id]['DaysWorked']++;
        $payrollData[$id]['TotalHours'] += $dailyComputation['hoursWorked'];
        $payrollData[$id]['GrossPay'] += $dailyComputation['totalPay'];
    }

    // Apply deductions (to be manually encoded in payroll system)
    foreach ($payrollData as &$emp) {
        $emp['NetPay'] = $emp['GrossPay'] - $emp['Deductions'];
    }

    return array_values($payrollData);
}

// --- Main Execution ---
try {
    // Optional parameters
    $start = isset($_GET['start']) ? $_GET['start'] : null;
    $end = isset($_GET['end']) ? $_GET['end'] : null;

    $result = getPayrollSummary($pdo, $start, $end);
    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
