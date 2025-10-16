<?php
// dbconfig.php
// PDO connection for MySQL - update credentials if needed

$DB_HOST = 'localhost';
$DB_NAME = 'payrolltest';    // confirmed by you
$DB_USER = 'root';
$DB_PASS = '';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    // Friendly message for local dev. In production, show a generic message.
    die("Database connection failed: " . $e->getMessage());
}
