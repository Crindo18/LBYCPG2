<?php
function search($conn) {
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : "";

    if ($search != "") {
        return "SELECT * FROM empdetails1 
                WHERE DataEntryID LIKE '%$search%' 
                   OR LastName LIKE '%$search%' 
                   OR FirstName LIKE '%$search%'
                   OR Hours LIKE '%$search%'
                   OR DutyType LIKE '%$search%'
                   OR ShiftDate LIKE '%$search%'
                   OR ShiftNo LIKE '%$search%'
                ORDER BY DataEntryID ASC";
    } else {
        return "SELECT * FROM empdetails1 ORDER BY DataEntryID ASC";
    }
}
