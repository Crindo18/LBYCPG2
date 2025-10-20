<?php
// batch_upload_excel.php
// Uploads an Excel TimeSheet into the payrolldata table without requiring a Rate column.

require __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// adjust DB config or require your dbconfig.php if that provides $pdo
require 'dbconfig.php'; // this file should set $pdo as PDO instance

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo '<form method="post" enctype="multipart/form-data">
            <p><input type="file" name="xls" accept=".xlsx,.xls" required></p>
            <p><button>Upload TimeSheet Excel</button></p>
          </form>';
    exit;
}

if (!isset($_FILES['xls']) || $_FILES['xls']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No file uploaded or upload error.');
}

$tmp = $_FILES['xls']['tmp_name'];

try {
    $spreadsheet = IOFactory::load($tmp);
} catch (Exception $e) {
    die("Failed to read spreadsheet: " . $e->getMessage());
}

// Prefer a sheet named "TimeSheet" (adjust if different)
$sheet = $spreadsheet->getSheetByName('TimeSheet') ?: $spreadsheet->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);

// detect header row (assume row 1)
$header = $rows[1];
$map = []; // column letter -> db column

// Map header text (case-insensitive substrings) to your payrolldata fields.
foreach ($header as $col => $txt) {
    $h = strtolower(trim($txt));
    if (strpos($h, 'employee') !== false || strpos($h, 'emp') !== false || strpos($h, 'employeeid') !== false) $map[$col] = 'EmployeeID';
    elseif (strpos($h, 'date') !== false) $map[$col] = 'Date';
    elseif (strpos($h, 'shift') !== false) $map[$col] = 'ShiftNumber';
    elseif (strpos($h, 'business') !== false || strpos($h, 'unit') !== false) $map[$col] = 'BusinessUnit';
    elseif (strpos($h, 'name') !== false) $map[$col] = 'Name';
    elseif (strpos($h, 'time in') !== false || strpos($h, 'timein') !== false) $map[$col] = 'TimeIn';
    elseif (strpos($h, 'time out') !== false || strpos($h, 'timeout') !== false) $map[$col] = 'TimeOut';
    elseif (strpos($h, 'hours') !== false) $map[$col] = 'Hours';
    elseif (strpos($h, 'role') !== false) $map[$col] = 'Role';
    elseif (strpos($h, 'remark') !== false) $map[$col] = 'Remarks';
    elseif (strpos($h, 'deduct') !== false) $map[$col] = 'Deductions';
    elseif (strpos($h, 'extra') !== false || strpos($h,'bonus') !== false) $map[$col] = 'Extra';
    // ignore Rate here — we will not try to insert Rate into payrolldata
}

// Prepare insert & update statements (only for the columns we will insert)
$dbCols = ['EmployeeID','Date','ShiftNumber','BusinessUnit','Name','TimeIn','TimeOut','Hours','Role','Remarks','Deductions','Extra'];
$insertCols = [];
$insertPlaceholders = [];
foreach ($dbCols as $c) {
    // We'll include a column only if it's present in the map OR always include (we'll use null defaults)
    $insertCols[] = "`$c`";
    $insertPlaceholders[] = ":" . $c;
}
$insertSql = "INSERT INTO payrolldata (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $insertPlaceholders) . ")";
$insertStmt = $pdo->prepare($insertSql);

// Update by EmployeeID + Date if that row exists
$updateSql = "UPDATE payrolldata SET ShiftNumber=:ShiftNumber, BusinessUnit=:BusinessUnit, TimeIn=:TimeIn, TimeOut=:TimeOut, Hours=:Hours, Role=:Role, Remarks=:Remarks, Deductions=:Deductions, Extra=:Extra WHERE EmployeeID=:EmployeeID AND Date=:Date";
$updateStmt = $pdo->prepare($updateSql);

// Helper: convert Excel cell value to date string if necessary
function parseExcelDate($val) {
    if (is_numeric($val)) {
        // Excel serial date
        try {
            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val);
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    } else {
        $ts = strtotime($val);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}

$pdo->beginTransaction();
$inserted = $updated = 0;

foreach ($rows as $rIndex => $row) {
    if ($rIndex == 1) continue; // header

    // Build param array with defaults
    $params = [
        'EmployeeID'=>null, 'Date'=>null, 'ShiftNumber'=>null, 'BusinessUnit'=>null, 'Name'=>null,
        'TimeIn'=>null, 'TimeOut'=>null, 'Hours'=>0, 'Role'=>null, 'Remarks'=>null, 'Deductions'=>0, 'Extra'=>0
    ];

    // Fill from mapped columns
    foreach ($map as $col => $dbcol) {
        $val = trim((string)($row[$col] ?? ''));
        if ($dbcol === 'Date') {
            $params['Date'] = parseExcelDate($val);
        } elseif ($dbcol === 'TimeIn' || $dbcol === 'TimeOut') {
            $time = $val !== '' ? date('H:i:s', strtotime($val)) : null;
            $params[$dbcol] = $time;
        } elseif ($dbcol === 'Hours' || $dbcol === 'Deductions' || $dbcol === 'Extra') {
            $params[$dbcol] = $val === '' ? 0 : floatval(str_replace(',', '', $val));
        } elseif ($dbcol === 'ShiftNumber') {
            $params[$dbcol] = $val === '' ? null : intval($val);
        } else {
            $params[$dbcol] = $val !== '' ? $val : null;
        }
    }

    // If no date or no name/employeeid skip
    if (empty($params['Date']) || (empty($params['EmployeeID']) && empty($params['Name']))) {
        // skip incomplete row
        continue;
    }

    // Check if exists by EmployeeID+Date (if EmployeeID present) or by Name+Date fallback
    if (!empty($params['EmployeeID'])) {
        $check = $pdo->prepare("SELECT ID FROM payrolldata WHERE EmployeeID = :eid AND Date = :dt LIMIT 1");
        $check->execute([':eid'=>$params['EmployeeID'], ':dt'=>$params['Date']]);
    } else {
        $check = $pdo->prepare("SELECT ID FROM payrolldata WHERE Name = :name AND Date = :dt LIMIT 1");
        $check->execute([':name'=>$params['Name'], ':dt'=>$params['Date']]);
    }
    $found = $check->fetchColumn();

    if ($found) {
        // Perform update
        $updateStmt->execute([
            ':ShiftNumber'=>$params['ShiftNumber'],
            ':BusinessUnit'=>$params['BusinessUnit'],
            ':TimeIn'=>$params['TimeIn'],
            ':TimeOut'=>$params['TimeOut'],
            ':Hours'=>$params['Hours'],
            ':Role'=>$params['Role'],
            ':Remarks'=>$params['Remarks'],
            ':Deductions'=>$params['Deductions'],
            ':Extra'=>$params['Extra'],
            ':EmployeeID'=>$params['EmployeeID'],
            ':Date'=>$params['Date']
        ]);
        $updated++;
    } else {
        // Insert. Ensure required params keys exist for placeholders
        $execParams = [];
        foreach ($dbCols as $c) {
            $execParams[":$c"] = $params[$c] ?? null;
        }
        $insertStmt->execute($execParams);
        $inserted++;
    }
}

$pdo->commit();

echo "Import completed. Inserted: {$inserted} Updated: {$updated}";
