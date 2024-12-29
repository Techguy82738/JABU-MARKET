<?php
require_once 'config/db.php'; // Include the database connection

session_start(); // Start session to track login status
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $is_logged_in ? $_SESSION['user_name'] : ''; // Assuming user's name is stored in the session
$profile_picture = ''; // Default to no profile picture

// If logged in, fetch user profile picture
if ($is_logged_in) {
    $sql = "SELECT profile_picture FROM users WHERE id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $profile_picture = $user_data['profile_picture'] ?? ''; // Retrieve profile picture or leave empty
}

// Fetch filter and search criteria from query string
$category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Base SQL query with join to fetch seller name
$sql = "SELECT products.*, users.name AS seller_name 
        FROM products 
        JOIN users ON products.seller_id = users.id 
        WHERE 1";

// Apply filters if provided
$params = [];
if (!empty($category)) {
    $sql .= " AND category = :category";
    $params['category'] = $category;
}
if (!empty($search)) {
    $sql .= " AND (products.title LIKE :search OR products.description LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

// Execute query
$sql .= " ORDER BY products.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch unread messages count for the logged-in user
$unread_count = 0;
if ($is_logged_in) {
    $msg_sql = "SELECT COUNT(*) FROM messages WHERE receiver_id = :user_id AND is_read = 0";
    $msg_stmt = $pdo->prepare($msg_sql);
    $msg_stmt->execute(['user_id' => $_SESSION['user_id']]);
    $unread_count = $msg_stmt->fetchColumn();
}

$approved_notifications = [];
if ($is_logged_in) {
    $notification_sql = "
        SELECT products.title, transactions.updated_at
        FROM transactions
        JOIN products ON transactions.product_id = products.id
        WHERE transactions.user_id = :user_id AND transactions.status = 'Approved'
        ORDER BY transactions.updated_at DESC
    ";
    $notification_stmt = $pdo->prepare($notification_sql);
    $notification_stmt->execute(['user_id' => $_SESSION['user_id']]);
    $approved_notifications = $notification_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch buyer notifications (approved transactions for the buyer)
$buyer_notifications = [];
if ($is_logged_in) {
    $buyer_notification_sql = "
        SELECT products.title, transactions.updated_at
        FROM transactions
        JOIN products ON transactions.product_id = products.id
        WHERE transactions.user_id = :user_id AND transactions.status = 'Approved'
        ORDER BY transactions.updated_at DESC
    ";
    $buyer_notification_stmt = $pdo->prepare($buyer_notification_sql);
    $buyer_notification_stmt->execute(['user_id' => $_SESSION['user_id']]);
    $buyer_notifications = $buyer_notification_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch seller notifications (products purchased from the seller)
$seller_notifications = [];
if ($is_logged_in) {
    $seller_notification_sql = "
        SELECT products.title AS product_title, transactions.created_at
        FROM transactions
        JOIN products ON transactions.product_id = products.id
        WHERE products.seller_id = :seller_id AND transactions.status = 'Approved'
        ORDER BY transactions.created_at DESC
    ";
    $seller_notification_stmt = $pdo->prepare($seller_notification_sql);
    $seller_notification_stmt->execute(['seller_id' => $_SESSION['user_id']]);
    $seller_notifications = $seller_notification_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Marketplace - Jabu Market</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    .product-image {
      height: 200px; 
      object-fit: cover; 
      width: 100%; 
      border-radius: 5px;
    }

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

    /* .nav-link:hover {
            color: #70b1ff;
    } */

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

    /* Marketplace Section */
    .marketplace {
        background-color: #f4f9ff;
        padding: 2rem 0;
    }

    .marketplace h1 {
        color: #003f7f;
        font-size: 2rem;
        font-weight: bold;
    }

    .marketplace .btn-primary {
        background-color: #0056b3;
        border-color: #0056b3;
    }

    .marketplace .btn-primary:hover {
        background-color: #003f7f;
        border-color: #003f7f;
    }

    .marketplace .btn-outline-secondary {
        color: #003f7f;
        border-color: #003f7f;
    }

    .marketplace .btn-outline-secondary:hover {
        background-color: #003f7f;
        color: white;
    }

    /* Product Cards */
    .product-image {
        height: 200px;
        object-fit: cover;
        width: 100%;
        border-radius: 5px;
    }

    .card {
        border: 1px solid #ddd;
        border-radius: 5px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .card-title {
        color: #003f7f;
        font-size: 1.25rem;
        font-weight: bold;
    }

    .btn-secondary {
        background-color: #003f7f;
        border-color: #003f7f;
        color: white;
    }

    .btn-secondary:hover {
        background-color: #0056b3;
        border-color: #0056b3;
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

    /* Category Buttons */
    .category-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: center;
    }

    .category-buttons .btn {
        text-transform: capitalize;
    }

    /* Mobile Responsiveness */
    @media (max-width: 767.98px) {
        /* Stack navigation links vertically */
        .header .nav {
            flex-direction: column;
            align-items: flex-start;
        }

        /* Adjust padding for mobile */
        .header {
            padding: 0.5rem 0;
        }

        /* Adjust marketplace padding */
        .marketplace {
            padding: 1rem 0;
        }

        /* Make category buttons full width on mobile */
        .category-buttons .btn {
            flex: 1 1 100%;
            max-width: 100%;
        }

        /* Adjust product image height */
        .product-image {
            height: 150px;
        }

        /* Stack search form elements vertically */
        .marketplace form.d-flex {
            flex-direction: column;
        }

        .marketplace form.d-flex .form-control {
            margin-bottom: 0.5rem;
        }

        .marketplace form.d-flex .btn {
            width: 100%;
        }

        /* Adjust card body for better spacing */
        .card-body {
            display: flex;
            flex-direction: column;
        }

        /* Ensure buttons are at the bottom */
        .card-body .d-flex {
            margin-top: auto;
        }

        /* Reduce font sizes for better fit */
        .marketplace h1 {
            font-size: 1.5rem;
        }

        .card-title {
            font-size: 1rem;
        }

        .btn {
            font-size: 0.9rem;
            padding: 0.4rem 0.8rem;
        }
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


  <!-- Marketplace Section -->
  <section class="marketplace py-5">
    <div class="container">
      <h1 class="text-center mb-4">Marketplace</h1>
      
      <!-- Search Form -->
      <form class="d-flex mb-4" method="GET" action="marketplace.php">
        <input type="text" class="form-control me-2" name="search" placeholder="Search for products..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="btn btn-primary">Search</button>
      </form>
      
      <!-- Category Buttons -->
      <div class="d-flex justify-content-center mb-4 category-buttons">
        <a href="marketplace.php" class="btn btn-outline-secondary me-2">All Categories</a>
        <a href="marketplace.php?category=Books" class="btn btn-outline-secondary me-2">Books</a>
        <a href="marketplace.php?category=Electronics" class="btn btn-outline-secondary me-2">Electronics</a>
        <a href="marketplace.php?category=Clothing" class="btn btn-outline-secondary me-2">Clothing</a>
        <a href="marketplace.php?category=Furniture" class="btn btn-outline-secondary me-2">Furniture</a>
        <a href="marketplace.php?category=Stationery" class="btn btn-outline-secondary me-2">Stationery</a>
        <a href="marketplace.php?category=Sports%20Equipment" class="btn btn-outline-secondary me-2">Sports Equipment</a>
        <a href="marketplace.php?category=Food%20%26%20Beverages" class="btn btn-outline-secondary me-2">Food & Beverages</a>
        <a href="marketplace.php?category=Bicycles%20%26%20Scooters" class="btn btn-outline-secondary me-2">Bicycles & Scooters</a>
        <a href="marketplace.php?category=Accessories" class="btn btn-outline-secondary me-2">Accessories</a>
        <a href="marketplace.php?category=Others" class="btn btn-outline-secondary">Others</a>
      </div>
      
      <!-- Products Grid -->
      <div class="row">
        <?php if (empty($products)): ?>
          <div class="col-12 text-center">
            <p class="text-muted">No products found.</p>
          </div>
        <?php else: ?>
          <?php foreach ($products as $product): ?>
            <div class="col-md-4 mb-4">
              <div class="card h-100">
                <img src="<?php echo htmlspecialchars($product['image']); ?>" class="card-img-top product-image" alt="<?php echo htmlspecialchars($product['title']); ?>">
                <div class="card-body">
                  <h5 class="card-title"><?php echo htmlspecialchars($product['title']); ?></h5>
                  <p class="card-text text-truncate"><?php echo htmlspecialchars($product['description']); ?></p>
                  <p class="card-text"><strong>₦<?php echo number_format($product['price'], 2); ?></strong></p>
                  <p class="card-text"><small>Seller: <?php echo htmlspecialchars($product['seller_name']); ?></small></p>
                  <div class="d-flex justify-content-between">
                    <a href="<?php echo isset($product['seller_id']) ? 'message_seller.php?seller_id=' . htmlspecialchars($product['seller_id']) . '&product_id=' . htmlspecialchars($product['id']) : '#'; ?>" 
                       class="btn btn-secondary">Message Seller</a>
                    <a href="<?php echo isset($product['id']) ? 'payment.php?product_id=' . htmlspecialchars($product['id']) : '#'; ?>" 
                       class="btn btn-primary">Buy Now</a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>
  
  <?php include 'footer.php'; ?>


  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
