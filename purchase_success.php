<?php
require_once 'config/db.php'; // Include the database connection

session_start(); // Start session to track login status

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirect to login if not logged in
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if product_id is provided
if (!isset($_GET['product_id'])) {
    echo "Product ID is missing.";
    exit;
}

$product_id = $_GET['product_id'];

// Fetch product details
$product_sql = "SELECT products.*, users.name AS seller_name, users.email AS seller_email 
                FROM products 
                JOIN users ON products.seller_id = users.id 
                WHERE products.id = :product_id";
$product_stmt = $pdo->prepare($product_sql);
$product_stmt->execute(['product_id' => $product_id]);
$product = $product_stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "Product not found.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Purchase Successful - Jabu Market</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="header">
  <div class="container">
    <div class="logo">
      <a href="index.php">Jabu Market</a>
    </div>
    <nav class="nav">
      <ul class="nav-list">
        <li><a href="index.php">Home</a></li>
        <li><a href="marketplace.php">Marketplace</a></li>
        <li><a href="sell.php">Sell</a></li>
        <li class="dropdown">
          <a href="#" class="dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
          </a>
          <ul class="dropdown-menu" aria-labelledby="userDropdown">
            <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
            <li><a class="dropdown-item" href="my_listings.php">My Listings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
          </ul>
        </li>
      </ul>
    </nav>
  </div>
</header>

<section class="purchase-success py-5">
  <div class="container">
    <div class="text-center mb-4">
      <h1 class="text-success">Purchase Successful!</h1>
      <p class="lead">Thank you for your purchase.</p>
    </div>
    <div class="row">
      <div class="col-md-6">
        <img src="<?php echo htmlspecialchars($product['image']); ?>" 
             alt="<?php echo htmlspecialchars($product['title']); ?>" class="img-fluid">
      </div>
      <div class="col-md-6">
        <h2><?php echo htmlspecialchars($product['title']); ?></h2>
        <p><?php echo htmlspecialchars($product['description']); ?></p>
        <p><strong>Price:</strong> $<?php echo number_format($product['price'], 2); ?></p>
        <p><strong>Seller:</strong> <?php echo htmlspecialchars($product['seller_name']); ?></p>
        <p><strong>Contact:</strong> <?php echo htmlspecialchars($product['seller_email']); ?></p>
        <div class="mt-4">
          <a href="marketplace.php" class="btn btn-primary">Back to Marketplace</a>
          <a href="my_purchases.php" class="btn btn-secondary">View My Purchases</a>
        </div>
      </div>
    </div>
  </div>
</section>

<footer class="footer py-3 bg-dark text-white">
  <div class="container text-center">
    <p>&copy; 2024 Jabu Market. All Rights Reserved.</p>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
