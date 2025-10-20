<?php
// holiday_rates.php
function getHolidayRate($date) {
    // Convert date to match table format (Y-m-d)
    $date = date('Y-m-d', strtotime($date));

    // Regular Holidays (+100%)
    $regularHolidays = [
        '2025-01-01', '2025-01-05', '2025-04-09', '2025-04-10',
        '2025-05-01', '2025-06-12', '2025-08-26', '2025-11-30',
        '2025-12-25', '2025-12-30', '2026-01-01'
    ];

    // Special Non-Working Holidays (+30%)
    $specialHolidays = [
        '2025-01-29', '2025-08-21', '2025-11-01', '2025-11-02',
        '2025-12-08', '2025-12-24', '2025-12-31'
    ];

    if (in_array($date, $regularHolidays)) {
        return 1.0; // +100% of daily rate
    } elseif (in_array($date, $specialHolidays)) {
        return 0.3; // +30% of daily rate
    } else {
        return 0.0; // No holiday
    }
}
?>
