<?php
// Database configuration
$host = 'localhost'; // Database server (use '127.0.0.1' if localhost isn't resolving)
$dbname = 'student_marketplace'; // Correct database name
$username = 'root'; // Database username
$password = ''; // Database password (leave empty if no password is set)

// Enable PDO exception handling
try {
    // Create a new PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Set PDO attributes for error handling and fetch mode
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle connection error
    die("Database connection failed: " . $e->getMessage());
}
?>
