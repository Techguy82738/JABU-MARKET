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
        WHERE products.seller_id = :user_id AND transactions.status = 'Approved'
        ORDER BY transactions.created_at DESC
    ";
    $seller_notification_stmt = $pdo->prepare($seller_notification_sql);
    $seller_notification_stmt->execute(['user_id' => $user_id]);
    $seller_notifications = $seller_notification_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check if user is logged in
if (!$is_logged_in) {
    header('Location: login.php');
    exit();
}

// Initialize variables for form feedback
$success_message = '';
$error_message = '';

// Define categories (synchronized with marketplace.php)
$categories = [
    'Books',
    'Electronics',
    'Clothing',
    'Furniture',
    'Stationery',
    'Sports Equipment',
    'Food & Beverages',
    'Bicycles & Scooters',
    'Accessories',
    'Others'
];

// Initialize $category for form repopulation
$category = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form inputs
    $title = trim($_POST['title']) ?? '';
    $description = trim($_POST['description']) ?? '';
    $price = trim($_POST['price']) ?? '';
    $category = trim($_POST['category']) ?? '';

    // Validate inputs
    if (empty($title) || empty($description) || empty($price) || empty($category)) {
        $error_message = 'All fields are required.';
    } elseif (!in_array($category, $categories)) {
        $error_message = 'Invalid category selected.';
    } elseif (!is_numeric($price) || $price <= 0) {
        $error_message = 'Please enter a valid price in Naira.';
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        $error_message = 'Please upload an image for the product.';
    } else {
        // Handle image upload
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        $image = $_FILES['image'];
        $image_type = mime_content_type($image['tmp_name']);
        $image_size = $image['size'];

        if (!in_array($image_type, $allowed_types)) {
            $error_message = 'Only JPEG, PNG, and GIF images are allowed.';
        } elseif ($image_size > $max_size) {
            $error_message = 'Image size should not exceed 5MB.';
        } else {
            // Generate a unique file name
            $image_extension = pathinfo($image['name'], PATHINFO_EXTENSION);
            $new_image_name = uniqid('product_', true) . '.' . $image_extension;
            $upload_directory = 'uploads/product_images/';
            $upload_path = $upload_directory . $new_image_name;

            // Ensure the upload directory exists
            if (!is_dir($upload_directory)) {
                mkdir($upload_directory, 0755, true);
            }

            // Move the uploaded file to the server
            if (move_uploaded_file($image['tmp_name'], $upload_path)) {
                // Insert product details into the database, including seller_id
                $insert_sql = "INSERT INTO products (user_id, seller_id, title, description, price, category, image) 
                               VALUES (:user_id, :seller_id, :title, :description, :price, :category, :image)";
                $insert_stmt = $pdo->prepare($insert_sql);
                try {
                    $insert_stmt->execute([
                        'user_id' => $user_id,
                        'seller_id' => $user_id, // Ensure seller_id matches user_id
                        'title' => $title,
                        'description' => $description,
                        'price' => $price,
                        'category' => $category,
                        'image' => $upload_path
                    ]);

                    $success_message = 'Product uploaded successfully!';
                    // Optionally, redirect the user to the marketplace or their listings page
                    // header("Location: marketplace.php?upload=success");
                    // exit();
                } catch (PDOException $e) {
                    // Handle insertion errors
                    $error_message = 'Failed to upload product: ' . $e->getMessage();
                }
            } else {
                $error_message = 'Failed to upload image. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Head content remains unchanged -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Product - Jabu Market</title>
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

        /* Upload Product Styles */
        .upload-container {
            margin: 2rem auto;
            max-width: 700px;
            background-color: #f8f9fa;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .upload-container h2 {
            color: #003f7f;
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: bold;
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

        /* Responsive Image Preview */
        #imagePreview {
            width: 100%;
            height: auto;
            max-height: 300px;
            object-fit: contain;
            margin-top: 1rem;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            display: none;
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
          <a href="upload_product.php" class="nav-link text-white active">Upload Product</a>
        </li>
      </ul>

      <?php if (!$is_logged_in): ?>
        <a href="login.php" class="btn btn-outline-light me-2">Login</a>
        <a href="signup.php" class="btn btn-light text-primary fw-bold">Sign Up</a>
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

<div class="container upload-container">
    <h2 class="mb-4">Upload New Product</h2>

    <!-- Display Success or Error Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <form action="upload_product.php" method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="title" class="form-label">Product Name<span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="title" name="title" placeholder="Enter product name" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Product Description<span class="text-danger">*</span></label>
            <textarea class="form-control" id="description" name="description" rows="5" placeholder="Enter product description" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
        </div>

        <div class="mb-3">
            <label for="price" class="form-label">Price (₦)<span class="text-danger">*</span></label>
            <input type="number" step="0.01" min="0.01" class="form-control" id="price" name="price" placeholder="e.g., 2999.99" value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" required>
        </div>

        <div class="mb-3">
            <label for="category" class="form-label">Category<span class="text-danger">*</span></label>
            <select class="form-select" id="category" name="category" required>
                <option value="" disabled <?php echo empty($category) ? 'selected' : ''; ?>>Select a category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="image" class="form-label">Product Image<span class="text-danger">*</span></label>
            <input class="form-control" type="file" id="image" name="image" accept="image/*" required>
            <img id="imagePreview" src="#" alt="Image Preview">
        </div>

        <button type="submit" class="btn btn-primary">Upload Product</button>
    </form>
</div>

<!-- Footer -->
<?php include 'footer.php'; ?>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Image Preview Script
    document.getElementById('image').addEventListener('change', function(event) {
        const [file] = this.files;
        if (file) {
            const preview = document.getElementById('imagePreview');
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
        }
    });
</script>
</body>
</html>
