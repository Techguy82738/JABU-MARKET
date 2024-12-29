<?php
// Enable error reporting for development purposes
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include your database connection file
require_once 'config/db.php'; // Ensure this points to your database connection script

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize input data
    $name = htmlspecialchars(trim($_POST['fullName'])); // Change 'fullName' to 'name'
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password']);
    $confirmPassword = trim($_POST['confirmPassword']);

    // Validate inputs
    if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
        die("All fields are required.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Invalid email format.");
    }

    if ($password !== $confirmPassword) {
        die("Passwords do not match.");
    }

    if (strlen($password) < 6) {
        die("Password must be at least 6 characters long.");
    }

    // Check if the email is already registered
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    if ($stmt->fetchColumn() > 0) {
        die("This email is already registered.");
    }

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user into the database
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, created_at) VALUES (:name, :email, :password_hash, NOW())");
        $stmt->execute([
            'name' => $name, // Update to match the 'name' column
            'email' => $email,
            'password_hash' => $hashedPassword, // Update to match the 'password_hash' column
        ]);
        echo "Registration successful. <a href='login.php'>Login here</a>";
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
} else {
    die("Invalid request method.");
}
?>
