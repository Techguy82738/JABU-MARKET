<?php
// payment.php

require_once 'config/db.php'; // Include the database connection
session_start();

// ==================== Header.php Content Starts Here ====================

// Ensure that the session is started and necessary variables are available
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Variables used in the header
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $is_logged_in ? $_SESSION['user_name'] : ''; // Assuming user's name is stored in the session
$profile_picture = ''; // Default to no profile picture

// If logged in, fetch user profile picture and notifications
$buyer_notifications = [];
$seller_notifications = [];
$total_notifications = 0;
$unread_notifications = 0;

if ($is_logged_in) {
    // Fetch user profile picture
    $sql = "SELECT profile_picture FROM users WHERE id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $profile_picture = $user_data['profile_picture'] ?? ''; // Retrieve profile picture or leave empty

    // Fetch notifications
    $notifications_sql = "SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC";
    $notifications_stmt = $pdo->prepare($notifications_sql);
    $notifications_stmt->execute(['user_id' => $_SESSION['user_id']]);
    $notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_notifications = count($notifications);
    $unread_notifications = 0;
    foreach ($notifications as $notif) {
        if (!$notif['is_read']) {
            $unread_notifications++;
        }
    }

    // Fetch unread messages count
    $unread_count = 0;
    $msg_sql = "SELECT COUNT(*) FROM messages WHERE receiver_id = :user_id AND is_read = 0";
    $msg_stmt = $pdo->prepare($msg_sql);
    $msg_stmt->execute(['user_id' => $_SESSION['user_id']]);
    $unread_count = $msg_stmt->fetchColumn();
}

// ==================== Header.php Content Ends Here ====================

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get the product_id from the GET parameter
if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
    header('Location: marketplace.php');
    exit();
}

$product_id = intval($_GET['product_id']);

// Fetch product details
$sql = "SELECT products.*, users.name AS seller_name, users.id AS seller_id FROM products 
        JOIN users ON products.seller_id = users.id 
        WHERE products.id = :product_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['product_id' => $product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "Product not found.";
    exit();
}

// Check if the product is already sold
if ($product['is_sold']) {
    echo "Sorry, this product has already been sold.";
    exit();
}

// Handle form submission
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and process the uploaded file
    if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['proof_of_payment']['tmp_name'];
        $fileName = $_FILES['proof_of_payment']['name'];
        $fileSize = $_FILES['proof_of_payment']['size'];
        $fileType = $_FILES['proof_of_payment']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // Allowed file extensions
        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'pdf'];

        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Sanitize file name
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;

            // Directory in which the uploaded file will be moved
            $uploadFileDir = './uploads/payment_proofs/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            $dest_path = $uploadFileDir . $newFileName;

            if(move_uploaded_file($fileTmpPath, $dest_path)) {
                // Insert transaction into database
                $transaction_sql = "INSERT INTO transactions (user_id, product_id, status, proof_of_payment, created_at) 
                                    VALUES (:user_id, :product_id, 'Pending', :proof_of_payment, NOW())";
                $transaction_stmt = $pdo->prepare($transaction_sql);
                $transaction_stmt->execute([
                    'user_id' => $_SESSION['user_id'],
                    'product_id' => $product_id,
                    'proof_of_payment' => $dest_path
                ]);

                // Optionally, mark the product as pending to prevent multiple submissions
                $update_product_sql = "UPDATE products SET is_pending = 1 WHERE id = :product_id";
                $update_product_stmt = $pdo->prepare($update_product_sql);
                $update_product_stmt->execute(['product_id' => $product_id]);

                $success = "Your proof of payment has been submitted and is awaiting approval.";
            } else {
                $errors[] = "There was an error moving the uploaded file.";
            }
        } else {
            $errors[] = "Upload failed. Allowed file types: " . implode(", ", $allowedfileExtensions);
        }
    } else {
        $errors[] = "Please upload a proof of payment.";
    }
}

// Generate a unique reference code
$reference_code = strtoupper(uniqid('PAY-'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment - Jabu Market</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons (for bell icon) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Google Fonts (Poppins) -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* Scoped Styles for Payment Page */
        body {
            font-family: 'Poppins', sans-serif;
        }
        .header {
            background-color: #003f7f;
        }
        .header .nav-link.active {
            font-weight: bold;
            text-decoration: underline;
        }
        .payment-page {
            background-color: #f4f9ff;
            padding-top: 60px;
            padding-bottom: 60px;
        }
        .payment-container {
            max-width: 600px;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin: auto;
        }
        .payment-container h2 {
            color: #003f7f;
            border-bottom: 2px solid #003f7f;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .payment-details, .bank-instructions {
            margin-bottom: 25px;
        }
        .bank-instructions h5 {
            color: #003f7f;
            margin-bottom: 15px;
        }
        .bank-instructions ul li {
            margin-bottom: 8px;
        }
        .payment-container .btn-primary {
            background-color: #003f7f;
            border-color: #003f7f;
        }
        .payment-container .btn-primary:hover {
            background-color: #002a5a;
            border-color: #002a5a;
        }
        .payment-container .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .payment-container .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .payment-container label.form-label {
            color: #003f7f;
        }
        /* Ensure header styles do not interfere with payment page */
        .header a.btn {
            margin-right: 10px;
        }
        .dropdown-menu {
            max-height: 300px;
            overflow-y: auto;
        }
        /* Responsive adjustments */
        @media (max-width: 576px) {
            .header .nav {
                flex-direction: column;
                align-items: flex-start;
            }
            .header .nav-item {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- ==================== Header Content Starts Here ==================== -->
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
              <a href="marketplace.php" class="nav-link text-white active">Marketplace</a>
            </li>
            <li class="nav-item">
              <a href="upload_product.php" class="nav-link text-white">Upload Product</a>
            </li>
          </ul>

          <?php if (!$is_logged_in): ?>
            <a href="login.php" class="btn btn-outline-light me-2">Login</a>
            <a href="signup.php" class="btn btn-light text-primary fw-bold">Sign Up</a>
          <?php else: ?>
            <div class="d-flex align-items-center">
              <!-- Notifications Dropdown -->
              <div class="dropdown me-3">
                  <a href="#" class="btn btn-outline-light position-relative dropdown-toggle" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                      <i class="bi bi-bell"></i>
                      <?php if ($unread_notifications > 0): ?>
                          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                              <?php echo $unread_notifications; ?>
                              <span class="visually-hidden">unread notifications</span>
                          </span>
                      <?php endif; ?>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                      <li class="dropdown-header">Notifications</li>
                      <?php if (!empty($notifications)): ?>
                          <?php foreach ($notifications as $notif): ?>
                              <li>
                                  <a class="dropdown-item <?php echo !$notif['is_read'] ? 'fw-bold' : ''; ?>" href="#">
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
                  <i class="bi bi-chat"></i> Messages
                  <?php if ($unread_count > 0): ?>
                      <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                          <?php echo $unread_count; ?>
                          <span class="visually-hidden">unread messages</span>
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
    <!-- ==================== Header Content Ends Here ==================== -->

    <!-- Payment Page Content -->
    <div class="payment-page">
        <div class="container payment-container">
            <h2>Payment for "<?php echo htmlspecialchars($product['title']); ?>"</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="payment-details">
                <h5>Product Details:</h5>
                <p><strong>Title:</strong> <?php echo htmlspecialchars($product['title']); ?></p>
                <p><strong>Price:</strong> ₦<?php echo number_format($product['price'], 2); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($product['description']); ?></p>
                <p><strong>Seller:</strong> <?php echo htmlspecialchars($product['seller_name']); ?></p>
            </div>

            <div class="bank-instructions">
                <h5>Bank Transfer Instructions:</h5>
                <p>Please transfer the total amount to the following bank account:</p>
                <ul>
                    <li><strong>Bank Name:</strong> Your Bank Name</li>
                    <li><strong>Account Number:</strong> 1234567890</li>
                    <li><strong>Account Holder:</strong> Jabu Market</li>
                    <li><strong>Reference Code:</strong> <?php echo htmlspecialchars($reference_code); ?></li>
                </ul>
                <p>Ensure you include the reference code in your bank transfer to facilitate verification.</p>
            </div>

            <form action="payment.php?product_id=<?php echo $product_id; ?>" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="proof_of_payment" class="form-label">Upload Proof of Payment:</label>
                    <input class="form-control" type="file" id="proof_of_payment" name="proof_of_payment" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>
                <button type="submit" class="btn btn-primary">Submit Payment</button>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <!-- Bootstrap JS Bundle (Includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
