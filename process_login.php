<?php
// Enable error reporting for development purposes
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include your database connection file
require_once 'config/db.php'; // Ensure this points to your database connection script

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize input data
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password']);

    // Validate inputs
    if (empty($email) || empty($password)) {
        die("Both email and password are required.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Invalid email format.");
    }

    // Check if the user exists in the database
    try {
        // Include 'role' in the SELECT statement
        $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Start a session for the logged-in user
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role']; // Store the user's role in the session

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: marketplace.php");
            }
            exit;
        } else {
            // Invalid credentials
            die("Invalid email or password.");
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
} else {
    die("Invalid request method.");
}
?>
