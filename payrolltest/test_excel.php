<?php
// test_excel.php - Debug script to check what's being read from Excel
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$excelFile = 'Payroll Testing Data 1.xlsx';

if (!file_exists($excelFile)) {
    die("Excel file not found: $excelFile");
}

echo "<h2>Excel File Debug Report</h2>";
echo "<p>Reading: <strong>$excelFile</strong></p>";

try {
    $spreadsheet = IOFactory::load($excelFile);
    $sheet = $spreadsheet->getSheetByName('Summary');
    
    if (!$sheet) {
        die("Summary sheet not found!");
    }
    
    $rows = $sheet->toArray(null, true, true, true);
    
    // Find header
    $headerRow = null;
    foreach ($rows as $idx => $row) {
        foreach ($row as $cell) {
            if (stripos($cell, 'Days of Work') !== false || stripos($cell, 'Name') !== false) {
                $headerRow = $idx;
                break 2;
            }
        }
    }
    
    echo "<h3>Header Row Found at Index: $headerRow</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f0f0f0;'><th>Column</th><th>Header Name</th></tr>";
    
    $header = $rows[$headerRow];
    foreach ($header as $col => $val) {
        echo "<tr><td>$col</td><td><strong>" . htmlspecialchars($val) . "</strong></td></tr>";
    }
    echo "</table>";
    
    // Show first 5 data rows
    echo "<h3>First 5 Employee Records</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; font-size: 12px;'>";
    
    // Header row
    echo "<tr style='background: #f0f0f0;'>";
    foreach ($header as $h) {
        echo "<th>" . htmlspecialchars($h) . "</th>";
    }
    echo "</tr>";
    
    // Data rows
    for ($i = $headerRow + 1; $i <= $headerRow + 5 && $i < count($rows); $i++) {
        $row = $rows[$i];
        echo "<tr>";
        foreach ($header as $col => $h) {
            $val = $row[$col] ?? '';
            echo "<td>" . htmlspecialchars($val) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    // Test specific employees
    echo "<h3>Looking for Allen, Barry and Barnes, James</h3>";
    
    // Find name column
    $nameCol = null;
    foreach ($header as $col => $val) {
        if (stripos($val, 'name') !== false) {
            $nameCol = $col;
            break;
        }
    }
    
    echo "<p>Name column: <strong>$nameCol</strong></p>";
    
    $allenRow = null;
    $barnesRow = null;
    
    for ($i = $headerRow + 1; $i < count($rows); $i++) {
        $name = $rows[$i][$nameCol] ?? '';
        if ($name === 'Allen, Barry') {
            $allenRow = $i;
        }
        if ($name === 'Barnes, James') {
            $barnesRow = $i;
        }
    }
    
    if ($allenRow) {
        echo "<h4>Allen, Barry (Row $allenRow)</h4>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        foreach ($header as $col => $h) {
            $val = $rows[$allenRow][$col] ?? '';
            echo "<tr><th>" . htmlspecialchars($h) . "</th><td>" . htmlspecialchars($val) . "</td></tr>";
        }
        echo "</table>";
    }
    
    if ($barnesRow) {
        echo "<h4>Barnes, James (Row $barnesRow)</h4>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        foreach ($header as $col => $h) {
            $val = $rows[$barnesRow][$col] ?? '';
            echo "<tr><th>" . htmlspecialchars($h) . "</th><td>" . htmlspecialchars($val) . "</td></tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
table { margin: 10px 0; }
th { background: #4CAF50; color: white; }
</style>