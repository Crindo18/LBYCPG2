<?php
require_once 'holiday_rates.php';

// Test dates
$testDates = ['2025-01-01', '2025-01-29', '2025-02-15'];

foreach ($testDates as $date) {
    $rate = getHolidayRate($date);
    echo "$date: Rate = $rate (" . ($rate >= 1 ? 'Regular Holiday' : ($rate > 0 ? 'Special Holiday' : 'Regular Day')) . ")<br>";
}
?>