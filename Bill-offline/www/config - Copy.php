<?php

// Database configuration
$host     = 'localhost';    // Your database host
$db_name  = 'sds_db';       // Your database name
$username = 'root';         // Your database username
$password = '';             // Your database password

try {
    // Set DSN (Data Source Name)
    $dsn = "mysql:host={$host};dbname={$db_name};charset=utf8mb4";

    // PDO options
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch results as associative array
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements
    ];

    // Create PDO instance
    $pdo = new PDO($dsn, $username, $password, $options);

} catch (PDOException $e) {
    // If connection fails, show error and stop execution
    die("Database Connection Failed: " . $e->getMessage());
}