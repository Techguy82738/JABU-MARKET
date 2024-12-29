<?php
// header.php

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define constants
define('DEFAULT_PROFILE_PICTURE', 'uploads/profile_pictures/default.png');

// Include necessary files
require_once 'config/db.php'; // Database connection
require_once 'includes/functions.php'; // Assume you have helper functions here

// Initialize variables
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $is_logged_in ? $_SESSION['user_name'] : '';
$profile_picture = DEFAULT_PROFILE_PICTURE;
$total_notifications = 0;
$unread_notifications = 0;
$unread_messages = 0;

// Function to fetch user data
function getUserData(PDO $pdo, int $user_id): array {
    $sql = "SELECT profile_picture FROM users WHERE id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

// Function to fetch notifications
function getUserNotifications(PDO $pdo, int $user_id): array {
    $sql = "SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// Function to count unread messages
function getUnreadMessagesCount(PDO $pdo, int $user_id): int {
    $sql = "SELECT COUNT(*) FROM messages WHERE receiver_id = :user_id AND is_read = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    return (int)$stmt->fetchColumn();
}

// Fetch user-specific data if logged in
if ($is_logged_in) {
    $user_data = getUserData($pdo, $_SESSION['user_id']);
    if (!empty($user_data['profile_picture'])) {
        $profile_picture = htmlspecialchars($user_data['profile_picture']);
    }

    $notifications = getUserNotifications($pdo, $_SESSION['user_id']);
    $total_notifications = count($notifications);
    $unread_notifications = array_reduce($notifications, function($count, $notif) {
        return $count + (!$notif['is_read'] ? 1 : 0);
    }, 0);

    $unread_messages = getUnreadMessagesCount($pdo, $_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Jabu Market</title>
    <!-- Responsive Meta Tag -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/styles.css" rel="stylesheet">
    <style>
        /* assets/css/styles.css */

/* General Styling */
body {
    font-family: 'Roboto', sans-serif;
    background-color: #f8f9fa;
}

/* Header Styling */
.header {
    background: linear-gradient(90deg, #4b6cb7 0%, #182848 100%);
    transition: background 0.3s ease-in-out;
}

.header:hover {
    background: linear-gradient(90deg, #182848 0%, #4b6cb7 100%);
}

.header .nav-link {
    position: relative;
    padding: 0.5rem 1rem;
    transition: color 0.3s ease, background-color 0.3s ease;
}

.header .nav-link.active,
.header .nav-link:hover {
    color: #ffd700;
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 20px;
}

.header .btn-outline-light {
    transition: background-color 0.3s ease, color 0.3s ease;
}

.header .btn-outline-light:hover {
    background-color: #ffd700;
    color: #182848;
}

.header .btn-light.text-primary {
    background-color: #ffd700;
    color: #182848;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.header .btn-light.text-primary:hover {
    background-color: #ffc700;
    color: #141d30;
}

/* Dropdown Styling */
.header .dropdown-menu {
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.header .dropdown-item {
    transition: background-color 0.2s ease;
}

.header .dropdown-item:hover {
    background-color: #f1f1f1;
}

/* Badge Styling */
.badge {
    font-size: 0.75rem;
}

/* Logo Styling */
.header a.text-decoration-none img {
    transition: transform 0.3s ease;
}

.header a.text-decoration-none img:hover {
    transform: scale(1.1);
}

/* User Avatar Styling */
.header .dropdown-toggle img {
    border: 2px solid #ffd700;
    transition: border-color 0.3s ease;
}

.header .dropdown-toggle img:hover {
    border-color: #ffffff;
}

/* Responsive Adjustments */
@media (max-width: 992px) {
    .header .nav-link {
        padding: 0.5rem 0.5rem;
    }

    .header .btn {
        padding: 0.3rem 0.6rem;
        font-size: 0.9rem;
    }

    .header .dropdown-menu {
        width: 200px;
    }
}

    </style>
</head>
<body>
    <header class="header py-3 bg-primary text-white shadow-sm">
        <div class="container d-flex justify-content-between align-items-center">
            <!-- Logo -->
            <a href="index.php" class="text-decoration-none text-white d-flex align-items-center">
                <img src="assets/images/logo.png" alt="Jabu Market Logo" class="me-2" style="width: 40px; height: 40px;">
                <span class="fs-4 fw-bold">Jabu Market</span>
            </a>

            <!-- Navigation -->
            <nav class="d-flex align-items-center">
                <ul class="nav me-4">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link text-white<?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? ' active' : ''; ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="marketplace.php" class="nav-link text-white<?php echo (basename($_SERVER['PHP_SELF']) == 'marketplace.php') ? ' active' : ''; ?>">Marketplace</a>
                    </li>
                    <li class="nav-item">
                        <a href="upload_product.php" class="nav-link text-white<?php echo (basename($_SERVER['PHP_SELF']) == 'upload_product.php') ? ' active' : ''; ?>">Upload Product</a>
                    </li>
                </ul>

                <?php if (!$is_logged_in): ?>
                    <a href="login.php" class="btn btn-outline-light me-2 rounded-pill">Login</a>
                    <a href="signup.php" class="btn btn-light text-primary fw-bold rounded-pill">Sign Up</a>
                <?php else: ?>
                    <div class="d-flex align-items-center">
                        <!-- Notifications Dropdown -->
                        <div class="dropdown me-3">
                            <button class="btn btn-outline-light position-relative dropdown-toggle" type="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-bell"></i>
                                <?php if ($unread_notifications > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo $unread_notifications; ?>
                                        <span class="visually-hidden">unread notifications</span>
                                    </span>
                                <?php endif; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                                <li class="dropdown-header">Notifications</li>
                                <?php if (!empty($notifications)): ?>
                                    <?php foreach ($notifications as $notif): ?>
                                        <li>
                                            <a class="dropdown-item <?php echo !$notif['is_read'] ? 'fw-bold' : ''; ?>" href="<?php echo htmlspecialchars($notif['link'] ?? '#'); ?>">
                                                <?php echo htmlspecialchars($notif['message']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo date('F j, Y, g:i a', strtotime($notif['created_at'])); ?></small>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item text-center" href="view_notifications.php">View All</a>
                                    </li>
                                <?php else: ?>
                                    <li class="dropdown-item text-muted">No notifications.</li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <!-- Messages Button -->
                        <a href="messages.php" class="btn btn-outline-light position-relative me-3">
                            <i class="bi bi-chat-left-text"></i> Messages
                            <?php if ($unread_messages > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $unread_messages; ?>
                                    <span class="visually-hidden">unread messages</span>
                                </span>
                            <?php endif; ?>
                        </a>

                        <!-- User Dropdown -->
                        <div class="dropdown">
                            <button class="btn btn-outline-light d-flex align-items-center dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="<?php echo $profile_picture; ?>" alt="User Avatar" class="rounded-circle me-2" style="width: 35px; height: 35px;">
                                <span><?php echo htmlspecialchars($user_name); ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                                <li><a class="dropdown-item" href="my_listings.php">My Listings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Include Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Include custom JS -->
    <script src="assets/js/scripts.js"></script>
</body>
</html>
