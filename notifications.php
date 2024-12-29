<?php
require_once 'config/db.php'; // Include the database connection

session_start();

if (!isset($_SESSION['user_id'])) {
    // Redirect to login if not logged in
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Initialize variables
$notifications = [];
$total_notifications = 0;

try {
    // Get total number of notifications for pagination
    $count_sql = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute([':user_id' => $user_id]);
    $total_notifications = (int) $count_stmt->fetchColumn();
    
    // Fetch notifications with pagination
    $notif_sql = "SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $notif_stmt = $pdo->prepare($notif_sql);
    $notif_stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $notif_stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
    $notif_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $notif_stmt->execute();
    $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Optionally, mark all as read
    // $mark_read_sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id";
    // $mark_read_stmt = $pdo->prepare($mark_read_sql);
    // $mark_read_stmt->execute([':user_id' => $user_id]);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An unexpected error occurred. Please try again later.");
}

// Calculate total pages
$total_pages = ceil($total_notifications / $items_per_page);

/**
 * Helper function to build pagination URLs while preserving existing query parameters.
 *
 * @param int $page The page number to link to.
 * @return string The constructed URL.
 */
function build_pagination_url($page) {
    $params = [];
    if ($page > 1) {
        $params['page'] = $page;
    }
    return 'notifications.php' . (empty($params) ? '' : '?' . http_build_query($params));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>All Notifications - Jabu Market</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    .notification-item {
      white-space: normal;
    }
  </style>
</head>
<body>
  
<header class="header py-3 bg-primary text-white">
  <div class="container d-flex justify-content-between align-items-center">
    <!-- Logo -->
    <a href="index.php" class="text-decoration-none text-white fs-4 fw-bold">
      Jabu Market
    </a>
    <nav class="nav">
      <ul class="nav-list">
        <li><a href="index.php" class="text-white text-decoration-none">Home</a></li>
        <li><a href="marketplace.php" class="text-white text-decoration-none">Marketplace</a></li>
        <li><a href="sell.php" class="text-white text-decoration-none">Sell</a></li>
        <?php if (!$is_logged_in): ?>
          <!-- Show Login/Signup if not logged in -->
          <li><a href="login.php" class="text-white text-decoration-none">Login</a></li>
          <li><a href="signup.php" class="btn btn-outline-light btn-signup">Sign Up</a></li>
        <?php else: ?>
          <!-- Show Notifications and Messages -->
          <li class="notification-dropdown">
            <a href="#" class="text-white text-decoration-none position-relative" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-bell" style="font-size: 1.5rem;"></i>
              <?php if ($unread_notif_count > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                  <?php echo $unread_notif_count; ?>
                  <span class="visually-hidden">unread notifications</span>
                </span>
              <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
              <li class="dropdown-header">Notifications</li>
              <?php if (empty($notifications)): ?>
                <li><span class="dropdown-item-text">No notifications.</span></li>
              <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                  <li>
                    <a href="notification.php?id=<?php echo urlencode($notif['id']); ?>" class="dropdown-item notification-item <?php echo !$notif['is_read'] ? 'fw-bold' : ''; ?>">
                      <?php echo htmlspecialchars($notif['content'], ENT_QUOTES, 'UTF-8'); ?>
                      <br>
                      <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($notif['created_at'])); ?></small>
                    </a>
                  </li>
                <?php endforeach; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a href="notifications.php" class="dropdown-item text-center">View All</a></li>
              <?php endif; ?>
            </ul>
          </li>
          <li>
            <a href="messages.php" class="text-white text-decoration-none position-relative">
              Messages
              <?php if ($unread_msg_count > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                  <?php echo $unread_msg_count; ?>
                  <span class="visually-hidden">unread messages</span>
                </span>
              <?php endif; ?>
            </a>
          </li>
          <!-- Show User Profile Dropdown if logged in -->
          <li class="dropdown">
            <a href="#" class="text-white text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
              <?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?> <!-- Display user's name -->
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
              <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
              <li><a class="dropdown-item" href="my_listings.php">My Listings</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="logout.php">Logout</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
    </nav>
  </div>
</header>


  <!-- Notifications Section -->
  <section class="notifications-section py-5">
    <div class="container">
      <h2 class="mb-4">All Notifications</h2>
      <?php if (empty($notifications)): ?>
        <p class="text-muted">You have no notifications.</p>
      <?php else: ?>
        <ul class="list-group">
          <?php foreach ($notifications as $notif): ?>
            <li class="list-group-item <?php echo !$notif['is_read'] ? 'list-group-item-primary' : ''; ?>">
              <a href="notification.php?id=<?php echo urlencode($notif['id']); ?>" class="text-decoration-none <?php echo !$notif['is_read'] ? 'fw-bold' : ''; ?>">
                <?php echo htmlspecialchars($notif['content'], ENT_QUOTES, 'UTF-8'); ?>
              </a>
              <br>
              <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($notif['created_at'])); ?></small>
            </li>
          <?php endforeach; ?>
        </ul>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
              <!-- Previous Page Link -->
              <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo build_pagination_url($page - 1); ?>" aria-label="Previous">
                  <span aria-hidden="true">&laquo;</span>
                </a>
              </li>
              
              <!-- Page Number Links -->
              <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <li class="page-item <?php echo ($p == $page) ? 'active' : ''; ?>">
                  <a class="page-link" href="<?php echo build_pagination_url($p); ?>">
                    <?php echo $p; ?>
                  </a>
                </li>
              <?php endfor; ?>
              
              <!-- Next Page Link -->
              <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo build_pagination_url($page + 1); ?>" aria-label="Next">
                  <span aria-hidden="true">&raquo;</span>
                </a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer py-3 bg-dark text-white">
    <div class="container text-center">
      <p>&copy; <?php echo date('Y'); ?> Jabu Market. All Rights Reserved.</p>
    </div>
  </footer>

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
