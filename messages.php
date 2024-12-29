<?php
require_once 'config/db.php'; // Include the database connection

session_start(); // Start session to track login status

// Initialize variables for header consistency
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $is_logged_in ? $_SESSION['user_name'] : '';
$profile_picture = ''; // Default to no profile picture
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

// If logged in, fetch user profile picture
if ($is_logged_in) {
    $sql = "SELECT profile_picture FROM users WHERE id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $profile_picture = $user_data['profile_picture'] ?? ''; // Retrieve profile picture or leave empty
}

// Fetch unread messages count
$unread_count = 0;
if ($is_logged_in) {
    $msg_sql = "SELECT COUNT(*) FROM messages WHERE receiver_id = :user_id AND is_read = 0";
    $msg_stmt = $pdo->prepare($msg_sql);
    $msg_stmt->execute(['user_id' => $user_id]);
    $unread_count = $msg_stmt->fetchColumn();
}

// Fetch notifications (buyer and seller)
$buyer_notifications = [];
$seller_notifications = [];
if ($is_logged_in) {
    // Buyer Notifications
    $buyer_notification_sql = "
        SELECT products.title, transactions.updated_at
        FROM transactions
        JOIN products ON transactions.product_id = products.id
        WHERE transactions.user_id = :user_id AND transactions.status = 'Approved'
        ORDER BY transactions.updated_at DESC
    ";
    $buyer_notification_stmt = $pdo->prepare($buyer_notification_sql);
    $buyer_notification_stmt->execute(['user_id' => $user_id]);
    $buyer_notifications = $buyer_notification_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Seller Notifications
    $seller_notification_sql = "
        SELECT products.title AS product_title, transactions.created_at
        FROM transactions
        JOIN products ON transactions.product_id = products.id
        WHERE products.seller_id = :seller_id AND transactions.status = 'Approved'
        ORDER BY transactions.created_at DESC
    ";
    $seller_notification_stmt = $pdo->prepare($seller_notification_sql);
    $seller_notification_stmt->execute(['seller_id' => $user_id]);
    $seller_notifications = $seller_notification_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check if user is logged in
if (!$is_logged_in) {
    header('Location: login.php');
    exit();
}

// Fetch all unique conversations for the user
$conversations = [];
$conversation_sql = "
    SELECT 
        CASE 
            WHEN sender_id = :user_id THEN receiver_id 
            ELSE sender_id 
        END AS other_user_id,
        product_id,
        MAX(timestamp) AS last_message_time,
        COUNT(CASE WHEN receiver_id = :user_id AND is_read = 0 THEN 1 END) AS unread_count
    FROM messages
    WHERE sender_id = :user_id OR receiver_id = :user_id
    GROUP BY other_user_id, product_id
    ORDER BY last_message_time DESC
";
$conversation_stmt = $pdo->prepare($conversation_sql);
$conversation_stmt->execute(['user_id' => $user_id]);
$conversations = $conversation_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch details for each conversation
$conversation_details = [];
foreach ($conversations as $conv) {
    $other_user_id = $conv['other_user_id'];
    $product_id = $conv['product_id'];
    $last_message_time = $conv['last_message_time'];
    $unread = $conv['unread_count'];

    // Fetch other user information
    $user_sql = "SELECT name, profile_picture FROM users WHERE id = :other_user_id";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->execute(['other_user_id' => $other_user_id]);
    $other_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch product information
    $product_sql = "SELECT title FROM products WHERE id = :product_id";
    $product_stmt = $pdo->prepare($product_sql);
    $product_stmt->execute(['product_id' => $product_id]);
    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch the last message snippet
    $message_sql = "
        SELECT message, sender_id
        FROM messages
        WHERE 
            (sender_id = :user_id AND receiver_id = :other_user_id AND product_id = :product_id) OR
            (sender_id = :other_user_id AND receiver_id = :user_id AND product_id = :product_id)
        ORDER BY timestamp DESC
        LIMIT 1
    ";
    $message_stmt = $pdo->prepare($message_sql);
    $message_stmt->execute([
        'user_id' => $user_id,
        'other_user_id' => $other_user_id,
        'product_id' => $product_id
    ]);
    $last_message = $message_stmt->fetch(PDO::FETCH_ASSOC);

    $conversation_details[] = [
        'other_user_id' => $other_user_id,
        'other_user_name' => $other_user['name'],
        'other_user_profile' => $other_user['profile_picture'] ?? '',
        'product_id' => $product_id,
        'product_title' => $product['title'],
        'last_message' => $last_message['message'],
        'last_message_sender' => $last_message['sender_id'],
        'last_message_time' => $last_message_time,
        'unread_count' => $unread
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Jabu Market</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* General Styles */
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            line-height: 1.6;
            color: #333;
            background-color: white;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            background-color: #003f7f;
            color: white;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease-in-out;
        }

        .header.scrolled {
            padding: 0.5rem 0;
            background-color: #002a57;
        }

        .header .nav-link {
            color: white;
            transition: color 0.3s ease;
        }

        .header .nav-link:hover {
            color: #70b1ff;
        }

        .header .btn {
            border-color: white;
            color: white;
        }

        .header .btn:hover {
            background-color: white;
            color: #003f7f;
        }

        /* Notifications */
        .dropdown-item {
            color: #333;
        }

        .dropdown-item:hover {
            background-color: #f4f9ff;
            color: #003f7f;
        }

        .badge.bg-danger {
            background-color: #d9534f;
        }

        /* Messages Page Styles */
        .messages-container {
            margin: 2rem auto;
            max-width: 1000px;
        }

        .messages-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .messages-header h2 {
            color: #003f7f;
        }

        .conversation-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .conversation-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: background-color 0.3s ease;
        }

        .conversation-item:hover {
            background-color: #f1f3f5;
        }

        .conversation-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .conversation-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .conversation-details {
            flex-grow: 1;
        }

        .conversation-details h5 {
            margin: 0;
            font-size: 1.1rem;
            color: #003f7f;
        }

        .conversation-details p {
            margin: 0.25rem 0 0;
            color: #6c757d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-time {
            font-size: 0.85rem;
            color: #6c757d;
            margin-left: auto;
            white-space: nowrap;
            text-align: right;
        }

        .badge-unread {
            background-color: #d9534f;
            color: white;
            margin-left: 0.5rem;
        }

        /* Footer */
        footer {
            background-color: #003f7f;
            color: white;
            padding: 1rem 0;
            position: relative;
            bottom: 0;
            width: 100%;
        }

        footer p {
            margin: 0;
        }

        /* Button Customization */
        .btn-primary {
            background-color: #003f7f;
            border-color: #003f7f;
        }

        .btn-primary:hover {
            background-color: #002a57;
            border-color: #002a57;
        }

        .btn-outline-light {
            color: white;
            border-color: white;
        }

        .btn-outline-light:hover {
            background-color: white;
            color: #003f7f;
        }

        .btn-outline-secondary {
            color: #003f7f;
            border-color: #003f7f;
        }

        .btn-outline-secondary:hover {
            background-color: #003f7f;
            color: white;
        }
    </style>
</head>
<body>
<header class="header py-3 text-white">
  <div class="container d-flex justify-content-between align-items-center">
    <!-- Logo -->
    <a href="index.php" class="text-decoration-none text-white fs-4 fw-bold">
      Jabu Market
    </a>

    <!-- Navigation -->
    <nav class="d-flex align-items-center">
      <ul class="nav me-4">
        <li class="nav-item">
          <a href="index.php" class="nav-link text-white">Home</a>
        </li>
        <li class="nav-item">
          <a href="marketplace.php" class="nav-link text-white">Marketplace</a>
        </li>
        <li class="nav-item">
          <a href="upload_product.php" class="nav-link text-white">Upload Product</a>
        </li>
      </ul>

      <?php if (!$is_logged_in): ?>
        <a href="login.php" class="btn btn-outline-light me-2">Login</a>
        <a href="signup.php" class="btn btn-outline-light text-white fw-bold">Sign Up</a>
      <?php else: ?>
        <div class="d-flex align-items-center">
          <!-- Combined Buyer and Seller Notifications Dropdown -->
          <div class="dropdown me-3">
              <a href="#" class="btn btn-outline-light position-relative dropdown-toggle" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="bi bi-bell"></i>
                  <?php 
                  $total_notifications = count($buyer_notifications) + count($seller_notifications);
                  if ($total_notifications > 0): ?>
                      <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                          <?php echo $total_notifications; ?>
                      </span>
                  <?php endif; ?>
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                  <!-- Buyer Notifications -->
                  <li class="dropdown-header">Buyer Notifications</li>
                  <?php if (!empty($buyer_notifications)): ?>
                      <?php foreach ($buyer_notifications as $notification): ?>
                          <li>
                              <a class="dropdown-item" href="#">
                                  Your payment for <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                  was approved on 
                                  <em><?php echo date('F j, Y, g:i a', strtotime($notification['updated_at'])); ?></em>.
                              </a>
                          </li>
                      <?php endforeach; ?>
                      <li><hr class="dropdown-divider"></li>
                  <?php else: ?>
                      <li class="dropdown-item text-muted">No buyer notifications.</li>
                  <?php endif; ?>

                  <!-- Seller Notifications -->
                  <li class="dropdown-header">Seller Notifications</li>
                  <?php if (!empty($seller_notifications)): ?>
                      <?php foreach ($seller_notifications as $notification): ?>
                          <li>
                              <a class="dropdown-item" href="#">
                                  Your product <strong><?php echo htmlspecialchars($notification['product_title']); ?></strong>
                                  was purchased on 
                                  <em><?php echo date('F j, Y, g:i a', strtotime($notification['created_at'])); ?></em>.
                              </a>
                          </li>
                      <?php endforeach; ?>
                  <?php else: ?>
                      <li class="dropdown-item text-muted">No seller notifications.</li>
                  <?php endif; ?>
              </ul>
          </div>

          <!-- Messages Button -->
          <a href="messages.php" class="btn btn-outline-light position-relative me-3">
              Messages
              <?php if ($unread_count > 0): ?>
                  <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                      <?php echo $unread_count; ?>
                  </span>
              <?php endif; ?>
          </a>

          <!-- User Dropdown -->
          <div class="dropdown">
              <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                  <?php if (!empty($profile_picture)): ?>
                      <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="User Avatar" class="rounded-circle me-2" style="width: 35px; height: 35px;">
                  <?php else: ?>
                      <img src="uploads/profile_pictures/default.png" alt="User Avatar" class="rounded-circle me-2" style="width: 35px; height: 35px;">
                  <?php endif; ?>
                  <span><?php echo htmlspecialchars($user_name); ?></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
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

<div class="container messages-container">
    <div class="messages-header d-flex justify-content-between align-items-center">
        <h2>Your Messages</h2>
        <a href="marketplace.php" class="btn btn-outline-secondary">Back to Marketplace</a>
    </div>

    <?php if (empty($conversation_details)): ?>
        <div class="alert alert-info">
            You have no messages yet. Start a conversation by messaging a seller!
        </div>
    <?php else: ?>
        <ul class="conversation-list">
            <?php foreach ($conversation_details as $conv): ?>
                <li class="conversation-item">
                    <div class="conversation-avatar">
                        <?php if (!empty($conv['other_user_profile'])): ?>
                            <img src="<?php echo htmlspecialchars($conv['other_user_profile']); ?>" alt="User Avatar">
                        <?php else: ?>
                            <img src="uploads/profile_pictures/default.png" alt="User Avatar">
                        <?php endif; ?>
                    </div>
                    <div class="conversation-details">
                        <h5><?php echo htmlspecialchars($conv['other_user_name']); ?></h5>
                        <p>
                            <?php 
                                // Display snippet of the last message
                                $snippet = substr($conv['last_message'], 0, 50);
                                echo htmlspecialchars($snippet) . (strlen($conv['last_message']) > 50 ? '...' : '');
                            ?>
                        </p>
                        <small class="text-muted">
                            <?php echo date('F j, Y, g:i a', strtotime($conv['last_message_time'])); ?>
                        </small>
                    </div>
                    <div class="conversation-time">
                        <?php 
                            // Display unread badge if there are unread messages
                            if ($conv['unread_count'] > 0): 
                        ?>
                            <span class="badge bg-danger badge-unread"><?php echo $conv['unread_count']; ?></span>
                        <?php endif; ?>
                        <a href="message_seller.php?seller_id=<?php echo $conv['other_user_id']; ?>&product_id=<?php echo $conv['product_id']; ?>" class="btn btn-primary btn-sm">
                            View
                        </a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
