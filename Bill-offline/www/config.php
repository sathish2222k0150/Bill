<?php
// config.php - FINAL PORTABLE VERSION

// The '__DIR__' constant gives the path to the current folder ('www/').
// We use '/../' to go up one level to the main app folder.
// This path is now completely dynamic and will work on any computer.
$db_path = __DIR__ . '/../sds.db';

try {
    // Set the DSN for SQLite
    $dsn = "sqlite:" . $db_path;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    // Create the PDO instance
    $pdo = new PDO($dsn, null, null, $options);

} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}