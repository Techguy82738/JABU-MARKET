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

// Get seller_id and product_id from GET parameters
if (!isset($_GET['seller_id']) || !isset($_GET['product_id'])) {
    header('Location: marketplace.php');
    exit();
}

$seller_id = intval($_GET['seller_id']);
$product_id = intval($_GET['product_id']);

// Prevent users from messaging themselves
if ($seller_id === $user_id) {
    $self_message_warning = true;
} else {
    $self_message_warning = false;

    // Fetch seller information
    $sql = "SELECT name, profile_picture FROM users WHERE id = :seller_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['seller_id' => $seller_id]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$seller) {
        $seller_not_found = true;
    } else {
        $seller_not_found = false;

        // Fetch product information
        $sql = "SELECT title, description, image FROM products WHERE id = :product_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['product_id' => $product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $product_not_found = true;
        } else {
            $product_not_found = false;

            // Handle new message submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (isset($_POST['message']) && !empty(trim($_POST['message']))) {
                    $message = trim($_POST['message']);

                    // Insert the new message into the messages table
                    $insert_sql = "INSERT INTO messages (sender_id, receiver_id, product_id, message, timestamp, is_read) 
                                   VALUES (:sender_id, :receiver_id, :product_id, :message, NOW(), 0)";
                    $insert_stmt = $pdo->prepare($insert_sql);
                    $insert_stmt->execute([
                        'sender_id' => $user_id,
                        'receiver_id' => $seller_id,
                        'product_id' => $product_id,
                        'message' => $message
                    ]);

                    // Optionally, implement notifications here

                    // Redirect to avoid form resubmission
                    header("Location: message_seller.php?seller_id=$seller_id&product_id=$product_id");
                    exit();
                }
            }

            // Fetch all messages between the user and the seller for the specific product
            $messages_sql = "
                SELECT messages.*, users.name AS sender_name, users.profile_picture AS sender_profile
                FROM messages
                JOIN users ON messages.sender_id = users.id
                WHERE 
                    ((sender_id = :user_id AND receiver_id = :seller_id) OR 
                     (sender_id = :seller_id AND receiver_id = :user_id))
                    AND product_id = :product_id
                ORDER BY timestamp ASC
            ";
            $messages_stmt = $pdo->prepare($messages_sql);
            $messages_stmt->execute([
                'user_id' => $user_id,
                'seller_id' => $seller_id,
                'product_id' => $product_id
            ]);
            $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mark messages as read where the receiver is the current user
            $mark_read_sql = "
                UPDATE messages 
                SET is_read = 1 
                WHERE receiver_id = :user_id AND sender_id = :seller_id AND product_id = :product_id AND is_read = 0
            ";
            $mark_read_stmt = $pdo->prepare($mark_read_sql);
            $mark_read_stmt->execute([
                'user_id' => $user_id,
                'seller_id' => $seller_id,
                'product_id' => $product_id
            ]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Seller - Jabu Market</title>
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

        /* Messaging Styles */
        .chat-container {
            max-width: 800px;
            margin: 2rem auto;
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .message {
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-end;
        }

        .message.seller {
            justify-content: flex-start;
        }

        .message.user {
            justify-content: flex-end;
        }

        .message .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 0.75rem;
        }

        .message.user .avatar {
            margin-left: 0.75rem;
            margin-right: 0;
        }

        .message .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .message .content {
            max-width: 70%;
            display: flex;
            flex-direction: column;
        }

        .message .content .sender {
            font-weight: bold;
            margin-bottom: 0.25rem;
            color: #003f7f; /* Blue color for sender's name */
        }

        .message .content .text {
            background-color: #e9ecef;
            padding: 0.5rem 1rem;
            border-radius: 15px;
            word-wrap: break-word;
            color: #333;
        }

        .message.user .content .text {
            background-color: #003f7f; /* Blue background for user's messages */
            color: white; /* White text for contrast */
        }

        .message .content .timestamp {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.25rem;
            align-self: flex-end;
        }

        .chat-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .chat-header .avatar {
            width: 60px;
            height: 60px;
            margin-right: 1rem;
        }

        .chat-header .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .chat-header .details {
            flex-grow: 1;
        }

        .chat-header .details h5 {
            margin: 0;
            font-size: 1.25rem;
            color: #003f7f; /* Blue color for seller's name */
        }

        .chat-header .details p {
            margin: 0;
            color: #6c757d;
        }

        .send-message-form textarea {
            resize: none;
        }

        /* Alert Styles */
        .alert {
            border-radius: 0.5rem;
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

<?php if ($self_message_warning): ?>
    <div class="container mt-5">
        <div class="alert alert-warning">
            You cannot message yourself.
        </div>
    </div>
<?php elseif ($seller_not_found): ?>
    <div class="container mt-5">
        <div class="alert alert-danger">
            Seller not found.
        </div>
    </div>
<?php elseif ($product_not_found): ?>
    <div class="container mt-5">
        <div class="alert alert-danger">
            Product not found.
        </div>
    </div>
<?php else: ?>
    <!-- Messaging Section -->
    <section class="chat-container">
        <div class="chat-header">
            <div class="avatar">
                <?php if (!empty($seller['profile_picture'])): ?>
                    <img src="<?php echo htmlspecialchars($seller['profile_picture']); ?>" alt="Seller Avatar">
                <?php else: ?>
                    <img src="uploads/profile_pictures/default.png" alt="Seller Avatar">
                <?php endif; ?>
            </div>
            <div class="details">
                <h5><?php echo htmlspecialchars($seller['name']); ?></h5>
                <p>Product: <strong><?php echo htmlspecialchars($product['title']); ?></strong></p>
            </div>
        </div>

        <div class="messages mb-4" style="max-height: 400px; overflow-y: auto;">
            <?php if (empty($messages)): ?>
                <p class="text-muted text-center">No messages yet. Start the conversation!</p>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <?php 
                        $is_user = ($msg['sender_id'] == $user_id);
                        $message_class = $is_user ? 'user' : 'seller';
                    ?>
                    <div class="message <?php echo $message_class; ?>">
                        <div class="avatar">
                            <?php if ($is_user): ?>
                                <?php if (!empty($profile_picture)): ?>
                                    <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Your Avatar">
                                <?php else: ?>
                                    <img src="uploads/profile_pictures/default.png" alt="Your Avatar">
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (!empty($seller['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($seller['profile_picture']); ?>" alt="Seller Avatar">
                                <?php else: ?>
                                    <img src="uploads/profile_pictures/default.png" alt="Seller Avatar">
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="content">
                            <div class="sender"><?php echo htmlspecialchars($msg['sender_name']); ?></div>
                            <div class="text"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                            <small class="timestamp"><?php echo date('F j, Y, g:i a', strtotime($msg['timestamp'])); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Send Message Form -->
        <form class="send-message-form" method="POST" action="message_seller.php?seller_id=<?php echo $seller_id; ?>&product_id=<?php echo $product_id; ?>">
            <div class="mb-3">
                <label for="message" class="form-label">Type your message:</label>
                <textarea class="form-control" id="message" name="message" rows="3" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Send</button>
        </form>
    </section>
<?php endif; ?>

<!-- Footer -->
<?php include 'footer.php'; ?>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-scroll to the latest message
    const messagesContainer = document.querySelector('.messages');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
</script>
</body>
</html>
