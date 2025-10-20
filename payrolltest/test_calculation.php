<?php
// test_calculation.php
// Quick test to verify calculations match professor's summary

require_once 'dbconfig.php';
require_once 'calculate_rates.php';

echo "<h1>Payroll Calculation Test</h1>";

// Test with January 2025 data
$start = '2025-01-01';
$end = '2025-01-31';

$result = calculateRates($pdo, $start, $end);

echo "<p><strong>Data Source:</strong> " . $result['source'] . "</p>";
echo "<p><strong>Period:</strong> {$start} to {$end}</p>";
echo "<p><strong>Employees Found:</strong> " . $result['count'] . "</p>";

echo "<h2>Sample Calculations:</h2>";

// Find Allen, Barry
$allen = null;
foreach ($result['employees'] as $emp) {
    if ($emp['name'] === 'Allen, Barry') {
        $allen = $emp;
        break;
    }
}

if ($allen) {
    echo "<h3>Allen, Barry</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Component</th><th>Value</th><th>Expected</th><th>Match?</th></tr>";
    
    $tests = [
        ['Days Worked', $allen['totals']['days_worked'] ?? 0, 22],
        ['Regular Pay', $allen['totals']['regular'], 11440.00],
        ['Overtime Pay', $allen['totals']['overtime'], 975.00],
        ['Allowance', $allen['totals']['allowance'], 200.00],
        ['Night Diff', $allen['totals']['night'], 260.00],
        ['Holiday Pay', $allen['totals']['holiday'], 676.00],
        ['GROSS PAY', $allen['totals']['gross'], 13551.00],
        ['Net Pay', $allen['totals']['net'], 13539.89]
    ];
    
    foreach ($tests as $test) {
        $match = abs($test[1] - $test[2]) < 0.01 ? '✅ YES' : '❌ NO';
        echo "<tr>";
        echo "<td>{$test[0]}</td>";
        echo "<td>₱" . number_format($test[1], 2) . "</td>";
        echo "<td>₱" . number_format($test[2], 2) . "</td>";
        echo "<td>{$match}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Allen, Barry not found in results!</p>";
}

// Find Barnes, James
$barnes = null;
foreach ($result['employees'] as $emp) {
    if ($emp['name'] === 'Barnes, James') {
        $barnes = $emp;
        break;
    }
}

if ($barnes) {
    echo "<h3>Barnes, James</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Component</th><th>Value</th><th>Expected</th><th>Match?</th></tr>";
    
    $tests = [
        ['Days Worked', $barnes['totals']['days_worked'] ?? 0, 26],
        ['Regular Pay', $barnes['totals']['regular'], 13520.00],
        ['Overtime Pay', $barnes['totals']['overtime'], 3120.00],
        ['Night Diff', $barnes['totals']['night'], 0.00],
        ['Holiday Pay', $barnes['totals']['holiday'], 676.00],
        ['GROSS PAY', $barnes['totals']['gross'], 17316.00],
        ['Net Pay', $barnes['totals']['net'], 11106.00]
    ];
    
    foreach ($tests as $test) {
        $match = abs($test[1] - $test[2]) < 0.01 ? '✅ YES' : '❌ NO';
        echo "<tr>";
        echo "<td>{$test[0]}</td>";
        echo "<td>₱" . number_format($test[1], 2) . "</td>";
        echo "<td>₱" . number_format($test[2], 2) . "</td>";
        echo "<td>{$match}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Barnes, James not found in results!</p>";
}

echo "<hr>";
echo "<h2>All Employees:</h2>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
echo "<tr><th>Name</th><th>Days</th><th>Regular</th><th>OT</th><th>Gross</th><th>Net</th></tr>";

foreach ($result['employees'] as $emp) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($emp['name']) . "</td>";
    echo "<td>" . ($emp['totals']['days_worked'] ?? 0) . "</td>";
    echo "<td>₱" . number_format($emp['totals']['regular'], 2) . "</td>";
    echo "<td>₱" . number_format($emp['totals']['overtime'], 2) . "</td>";
    echo "<td><strong>₱" . number_format($emp['totals']['gross'], 2) . "</strong></td>";
    echo "<td><strong>₱" . number_format($emp['totals']['net'], 2) . "</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<p><a href='salary_summary.php'>Go to Salary Summary</a></p>";