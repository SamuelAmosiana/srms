<?php
// Database configuration
$DB_HOST = '127.0.0.1';
$DB_NAME = 'lscrms';
$DB_USER = 'root'; // replace with your DB user
$DB_PASS = '';     // replace with your DB password

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, $options);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

session_start();
