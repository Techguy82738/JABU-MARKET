<?php
require_once 'config/db.php'; // Include the database connection

session_start(); // Start session to track login status

// Define $is_logged_in
$is_logged_in = isset($_SESSION['user_id']);

// Check if the user is logged in
if (!$is_logged_in) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user'; // Fetch the role from session, default to 'user' if not set

// Fetch user details
$sql = "SELECT * FROM users WHERE id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Initialize variables for profile update
$update_success = false;
$errors = [];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // CSRF Token Verification
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $new_name = trim($_POST['name']);
        $new_email = trim($_POST['email']);
        $new_password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        // Validate inputs
        if (empty($new_name)) {
            $errors[] = "Name cannot be empty.";
        }
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $errors[] = "Passwords do not match.";
            }
            if (strlen($new_password) < 6) {
                $errors[] = "Password must be at least 6 characters.";
            }
        }

        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
            $fileName = $_FILES['profile_picture']['name'];
            $fileSize = $_FILES['profile_picture']['size'];
            $fileType = $_FILES['profile_picture']['type'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));

            $allowedfileExtensions = ['jpg', 'png', 'jpeg', 'gif'];
            if (in_array($fileExtension, $allowedfileExtensions)) {
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $uploadFileDir = 'uploads/profile_pictures/';
                $dest_path = $uploadFileDir . $newFileName;

                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }

                if(move_uploaded_file($fileTmpPath, $dest_path)) {
                    // Delete old profile picture if exists and not default
                    if (!empty($user['profile_picture']) && file_exists($user['profile_picture']) && $user['profile_picture'] !== 'uploads/profile_pictures/default.png') {
                        unlink($user['profile_picture']);
                    }
                    $profile_picture = $dest_path;
                } else {
                    $errors[] = "There was an error uploading the profile picture.";
                }
            } else {
                $errors[] = "Unsupported file format for profile picture.";
            }
        } else {
            $profile_picture = $user['profile_picture'];
        }

        // If no errors, update the user information
        if (empty($errors)) {
            $update_sql = "UPDATE users SET name = :name, email = :email, profile_picture = :profile_picture";
            $params = [
                'name' => $new_name,
                'email' => $new_email,
                'profile_picture' => $profile_picture
            ];

            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql .= ", password = :password";
                $params['password'] = $hashed_password;
            }

            $update_sql .= " WHERE id = :user_id";
            $params['user_id'] = $user_id;

            $update_stmt = $pdo->prepare($update_sql);
            if ($update_stmt->execute($params)) {
                $update_success = true;
                // Update session name if changed
                $_SESSION['user_name'] = $new_name;
                // Refresh user data
                $stmt->execute(['user_id' => $user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $errors[] = "Failed to update profile. Please try again.";
            }
        }
    }
}

// Handle Product Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    // CSRF Token Verification
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $product_id = intval($_POST['product_id']);

        // Verify ownership
        $verify_sql = "SELECT * FROM products WHERE id = :product_id AND seller_id = :user_id";
        $verify_stmt = $pdo->prepare($verify_sql);
        $verify_stmt->execute(['product_id' => $product_id, 'user_id' => $user_id]);
        $product = $verify_stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            // Delete product image if exists
            if (!empty($product['image']) && file_exists($product['image'])) {
                unlink($product['image']);
            }

            // Delete the product
            $delete_sql = "DELETE FROM products WHERE id = :product_id";
            $delete_stmt = $pdo->prepare($delete_sql);
            if ($delete_stmt->execute(['product_id' => $product_id])) {
                $update_success = true;
                // Optionally, add a success message
                $_SESSION['success_message'] = "Product deleted successfully.";
            } else {
                $errors[] = "Failed to delete the product. Please try again.";
            }
        } else {
            $errors[] = "Product not found or you do not have permission to delete this product.";
        }
    }
}

// Handle Product Editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    // CSRF Token Verification
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $product_id = intval($_POST['product_id']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);

        // Validate inputs
        if (empty($title)) {
            $errors[] = "Product title cannot be empty.";
        }
        if (empty($description)) {
            $errors[] = "Product description cannot be empty.";
        }
        if ($price <= 0) {
            $errors[] = "Price must be a positive number.";
        }

        // Verify ownership
        $verify_sql = "SELECT * FROM products WHERE id = :product_id AND seller_id = :user_id";
        $verify_stmt = $pdo->prepare($verify_sql);
        $verify_stmt->execute(['product_id' => $product_id, 'user_id' => $user_id]);
        $product = $verify_stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            // Handle product image upload
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['product_image']['tmp_name'];
                $fileName = $_FILES['product_image']['name'];
                $fileSize = $_FILES['product_image']['size'];
                $fileType = $_FILES['product_image']['type'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));

                $allowedfileExtensions = ['jpg', 'png', 'jpeg', 'gif'];
                if (in_array($fileExtension, $allowedfileExtensions)) {
                    $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                    $uploadFileDir = 'uploads/product_images/';
                    $dest_path = $uploadFileDir . $newFileName;

                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }

                    if(move_uploaded_file($fileTmpPath, $dest_path)) {
                        // Delete old product image if exists
                        if (!empty($product['image']) && file_exists($product['image'])) {
                            unlink($product['image']);
                        }
                        $product_image = $dest_path;
                    } else {
                        $errors[] = "There was an error uploading the product image.";
                    }
                } else {
                    $errors[] = "Unsupported file format for product image.";
                }
            } else {
                $product_image = $product['image'];
            }

            // If no errors, update the product information
            if (empty($errors)) {
                $update_sql = "UPDATE products SET title = :title, description = :description, price = :price, image = :image WHERE id = :product_id";
                $update_stmt = $pdo->prepare($update_sql);
                $params = [
                    'title' => $title,
                    'description' => $description,
                    'price' => $price,
                    'image' => $product_image,
                    'product_id' => $product_id
                ];

                if ($update_stmt->execute($params)) {
                    $update_success = true;
                    $_SESSION['success_message'] = "Product updated successfully.";
                } else {
                    $errors[] = "Failed to update the product. Please try again.";
                }
            }
        } else {
            $errors[] = "Product not found or you do not have permission to edit this product.";
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch user listings
$listings_sql = "SELECT * FROM products WHERE seller_id = :user_id ORDER BY created_at DESC";
$listings_stmt = $pdo->prepare($listings_sql);
$listings_stmt->execute(['user_id' => $user_id]);
$listings = $listings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user purchases
$purchases_sql = "SELECT transactions.*, products.title, products.price AS product_price, products.image, users.name AS seller_name 
                  FROM transactions 
                  JOIN products ON transactions.product_id = products.id 
                  JOIN users ON products.seller_id = users.id
                  WHERE transactions.user_id = :user_id 
                  ORDER BY transactions.updated_at DESC";
$purchases_stmt = $pdo->prepare($purchases_sql);
$purchases_stmt->execute(['user_id' => $user_id]);
$purchases = $purchases_stmt->fetchAll(PDO::FETCH_ASSOC);


// Fetch unread messages count
$msg_sql = "SELECT COUNT(*) FROM messages WHERE receiver_id = :user_id AND is_read = 0";
$msg_stmt = $pdo->prepare($msg_sql);
$msg_stmt->execute(['user_id' => $user_id]);
$unread_count = $msg_stmt->fetchColumn();

// Fetch notifications (as in marketplace.php)
$buyer_notifications = [];
$seller_notifications = [];

$notification_sql = "
    SELECT products.title, transactions.updated_at
    FROM transactions
    JOIN products ON transactions.product_id = products.id
    WHERE transactions.user_id = :user_id AND transactions.status = 'Approved'
    ORDER BY transactions.updated_at DESC
";
$notification_stmt = $pdo->prepare($notification_sql);
$notification_stmt->execute(['user_id' => $user_id]);
$buyer_notifications = $notification_stmt->fetchAll(PDO::FETCH_ASSOC);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Jabu Market</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Reusing the provided CSS styles */
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

        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header .logo a {
            font-size: 2rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
        }

        .nav {
            display: flex;
            align-items: center;
        }

        .nav-list {
            list-style: none;
            display: flex;
            gap: 1.5rem;
            margin: 0;
            padding: 0;
        }

        .nav-list li {
            position: relative;
        }

        .nav-list a {
            color: white;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            position: relative;
            padding: 0.2rem 0;
            transition: color 0.3s ease;
        }

        .nav-list a:hover {
            color: #70b1ff;
        }

        .nav-list a::after {
            content: '';
            display: block;
            width: 0;
            height: 2px;
            background: #70b1ff;
            transition: width 0.3s ease;
            position: absolute;
            bottom: 0;
            left: 0;
        }

        .nav-list a:hover::after {
            width: 100%;
        }

        .nav-list .btn-signup {
            background-color: white;
            color: #003f7f;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .mobile-nav-toggle {
            display: none;
            cursor: pointer;
            flex-direction: column;
            gap: 5px;
        }

        .mobile-nav-toggle span {
            background-color: white;
            height: 3px;
            width: 25px;
            border-radius: 5px;
        }

        @media screen and (max-width: 768px) {
            .nav {
                display: none;
                position: absolute;
                top: 100%;
                right: 0;
                background-color: #003f7f;
                width: 100%;
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            }

            .nav.active {
                display: flex;
            }

            .nav-list {
                flex-direction: column;
                gap: 1rem;
            }

            .mobile-nav-toggle {
                display: flex;
            }
        }

        /* Profile Section */
        .profile-section {
            padding: 2rem 0;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .profile-header img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #003f7f;
            background-color: #f0f0f0; /* Placeholder background */
            cursor: pointer; /* Indicate that the image is clickable */
        }

        .profile-header .profile-info {
            flex: 1;
        }

        .profile-header .profile-info h2 {
            margin-bottom: 0.5rem;
            color: #003f7f;
        }

        .profile-header .profile-info p {
            margin-bottom: 0.2rem;
        }

        .edit-profile-btn {
            margin-top: 1rem;
        }

        /* Listings and Purchases */
        .tab-content {
            margin-top: 2rem;
        }

        .card-img-top {
            height: 200px;
            object-fit: cover;
            border-radius: 5px;
        }

        .tab-pane h3 {
            color: #003f7f;
            margin-bottom: 1rem;
        }

        /* Notifications Badge */
        .badge.bg-danger {
            background-color: #d9534f;
        }

        /* Messages Badge */
        .badge.bg-secondary {
            background-color: #6c757d;
        }

        /* Prompt for Profile Picture Upload */
        .upload-prompt {
            margin-top: 1rem;
            font-style: italic;
            color: #555;
        }

        /* Modal Image Styling */
        .modal-img {
            width: 100%;
            max-width: 400px; /* Reduced maximum width */
            height: auto;
            display: block;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <!-- Header -->
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

                        <!-- Admin Dashboard Link (Visible Only to Admins) -->
                        <?php if ($user_role === 'admin'): ?>
                            <a href="admin_dashboard.php" class="btn btn-outline-warning me-3">
                                <i class="bi bi-speedometer2"></i> Admin Dashboard
                            </a>
                        <?php endif; ?>

                        <!-- User Dropdown -->
                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php if (!empty($user['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" class="rounded-circle me-2" style="width: 35px; height: 35px;" alt="">
                                <?php else: ?>
                                    <img src="uploads/profile_pictures/default.png" class="rounded-circle me-2" style="width: 35px; height: 35px;" alt="">
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($user_name); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                                <li><a class="dropdown-item" href="my_listings.php">My Listings</a></li>
                                <?php if ($user_role === 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin_dashboard.php">Admin Dashboard</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Profile Section -->
    <section class="profile-section py-5">
        <div class="container">
            <!-- Profile Header -->
            <div class="profile-header">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="" data-bs-toggle="modal" data-bs-target="#enlargeProfilePicModal">
                <?php else: ?>
                    <img src="uploads/profile_pictures/default.png" alt="" data-bs-toggle="modal" data-bs-target="#enlargeProfilePicModal">
                <?php endif; ?>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><strong>Joined:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                    <a href="#" class="btn btn-primary edit-profile-btn" data-bs-toggle="modal" data-bs-target="#editProfileModal">Edit Profile</a>
                    <p class="upload-prompt">Upload a profile picture to personalize your account.</p>
                    
                    <!-- Admin Indicator -->
                    <?php if ($user_role === 'admin'): ?>
                        <span class="badge bg-warning text-dark mt-2">Administrator</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Success and Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php 
                        echo htmlspecialchars($_SESSION['success_message']); 
                        unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($update_success): ?>
                <div class="alert alert-success">
                    Profile updated successfully.
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Tabs for Listings and Purchases -->
            <ul class="nav nav-tabs" id="profileTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="listings-tab" data-bs-toggle="tab" data-bs-target="#listings" type="button" role="tab" aria-controls="listings" aria-selected="true">My Listings</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="purchases-tab" data-bs-toggle="tab" data-bs-target="#purchases" type="button" role="tab" aria-controls="purchases" aria-selected="false">My Purchases</button>
                </li>
            </ul>
            <div class="tab-content" id="profileTabContent">
                <!-- My Listings -->
                <div class="tab-pane fade show active" id="listings" role="tabpanel" aria-labelledby="listings-tab">
                    <div class="row mt-4">
                        <?php if (empty($listings)): ?>
                            <div class="col-12 text-center">
                                <p class="text-muted">You have not listed any products yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($listings as $listing): ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100">
                                        <img src="<?php echo htmlspecialchars($listing['image']); ?>" class="card-img-top" alt="">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($listing['title']); ?></h5>
                                            <p class="card-text text-truncate"><?php echo htmlspecialchars($listing['description']); ?></p>
                                            <p class="card-text"><strong>$<?php echo number_format($listing['price'], 2); ?></strong></p>
                                            <div class="d-flex justify-content-between">
                                                <!-- Edit Button -->
                                                <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editProductModal<?php echo $listing['id']; ?>">Edit</button>
                                                
                                                <!-- Delete Button -->
                                                <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteProductModal<?php echo $listing['id']; ?>">Delete</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Product Modal -->
                                <div class="modal fade" id="editProductModal<?php echo $listing['id']; ?>" tabindex="-1" aria-labelledby="editProductModalLabel<?php echo $listing['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="product_id" value="<?php echo $listing['id']; ?>">
                                            <input type="hidden" name="edit_product" value="1">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editProductModalLabel<?php echo $listing['id']; ?>">Edit Product</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label for="title<?php echo $listing['id']; ?>" class="form-label"><strong>Title</strong></label>
                                                        <input type="text" class="form-control" id="title<?php echo $listing['id']; ?>" name="title" value="<?php echo htmlspecialchars($listing['title']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="description<?php echo $listing['id']; ?>" class="form-label"><strong>Description</strong></label>
                                                        <textarea class="form-control" id="description<?php echo $listing['id']; ?>" name="description" rows="3" required><?php echo htmlspecialchars($listing['description']); ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="price<?php echo $listing['id']; ?>" class="form-label"><strong>Price</strong></label>
                                                        <input type="number" step="0.01" class="form-control" id="price<?php echo $listing['id']; ?>" name="price" value="<?php echo htmlspecialchars($listing['price']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="product_image<?php echo $listing['id']; ?>" class="form-label"><strong>Product Image</strong></label>
                                                        <input type="file" class="form-control" id="product_image<?php echo $listing['id']; ?>" name="product_image" accept="image/*">
                                                        <small class="form-text text-muted">Upload a new image to replace the current one.</small>
                                                        <img src="<?php echo htmlspecialchars($listing['image']); ?>" class="mt-2" style="width: 100px; height: 100px; object-fit: cover; border-radius: 5px;" alt="">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Delete Product Modal -->
                                <div class="modal fade" id="deleteProductModal<?php echo $listing['id']; ?>" tabindex="-1" aria-labelledby="deleteProductModalLabel<?php echo $listing['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="product_id" value="<?php echo $listing['id']; ?>">
                                            <input type="hidden" name="delete_product" value="1">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="deleteProductModalLabel<?php echo $listing['id']; ?>">Confirm Deletion</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    Are you sure you want to delete the product <strong><?php echo htmlspecialchars($listing['title']); ?></strong>?
                                                    This action cannot be undone.
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Purchases -->
                <!-- My Purchases -->
<div class="tab-pane fade" id="purchases" role="tabpanel" aria-labelledby="purchases-tab">
    <div class="row mt-4">
        <?php if (empty($purchases)): ?>
            <div class="col-12 text-center">
                <p class="text-muted">You have not purchased any products yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($purchases as $purchase): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <img src="<?php echo htmlspecialchars($purchase['image']); ?>" class="card-img-top" alt="">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($purchase['title']); ?></h5>
                            <p class="card-text"><strong>$<?php echo number_format($purchase['product_price'], 2); ?></strong></p>
                            <p class="card-text"><small>Seller: <?php echo htmlspecialchars($purchase['seller_name'] ?? 'N/A'); ?></small></p>
                            <p class="card-text"><small>Status: <?php echo htmlspecialchars($purchase['status']); ?></small></p>
                            <p class="card-text"><small>Purchased on: <?php echo date('F j, Y, g:i a', strtotime($purchase['updated_at'])); ?></small></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

            </div>
        </div>
    </section>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label"><strong>Name</strong></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label"><strong>Email</strong></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="profile_picture" class="form-label"><strong>Profile Picture</strong></label>
                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                            <small class="form-text text-muted">Upload a picture to personalize your profile.</small>
                            <?php if (!empty($user['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" class="mt-2" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%;" alt="">
                            <?php else: ?>
                                <img src="uploads/profile_pictures/default.png" class="mt-2" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%;" alt="">
                            <?php endif; ?>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label for="password" class="form-label"><strong>New Password</strong></label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Leave blank to keep current password">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label"><strong>Confirm New Password</strong></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Leave blank to keep current password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Enlarge Profile Picture Modal -->
    <div class="modal fade" id="enlargeProfilePicModal" tabindex="-1" aria-labelledby="enlargeProfilePicModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-body p-0">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" class="modal-img" alt="">
                    <?php else: ?>
                        <img src="uploads/profile_pictures/default.png" class="modal-img" alt="">
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
