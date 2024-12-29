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

// Handle purchase confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Example: Record purchase in a purchase history table
    $purchase_sql = "INSERT INTO purchases (user_id, product_id, seller_id, purchased_at) 
                     VALUES (:user_id, :product_id, :seller_id, NOW())";
    $purchase_stmt = $pdo->prepare($purchase_sql);
    $purchase_stmt->execute([
        'user_id' => $user_id,
        'product_id' => $product_id,
        'seller_id' => $product['seller_id']
    ]);

    // Redirect to a success page or confirmation message
    header("Location: purchase_success.php?product_id=$product_id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Buy Product - Jabu Market</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="style.css">
  <style>
    .product-image {
      height: 300px;
      object-fit: cover;
      width: 100%;
    }
  </style>
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

<section class="buy-product py-5">
  <div class="container">
    <h1 class="text-center mb-4">Confirm Purchase</h1>
    <div class="row">
      <div class="col-md-6">
        <img src="<?php echo htmlspecialchars($product['image']); ?>" 
             alt="<?php echo htmlspecialchars($product['title']); ?>" class="product-image">
      </div>
      <div class="col-md-6">
        <h2><?php echo htmlspecialchars($product['title']); ?></h2>
        <p><?php echo htmlspecialchars($product['description']); ?></p>
        <p><strong>Price:</strong> $<?php echo number_format($product['price'], 2); ?></p>
        <p><strong>Seller:</strong> <?php echo htmlspecialchars($product['seller_name']); ?></p>
        <p><strong>Contact:</strong> <?php echo htmlspecialchars($product['seller_email']); ?></p>
        <form method="POST" action="buy_product.php?product_id=<?php echo $product_id; ?>">
          <button type="submit" class="btn btn-success">Confirm Purchase</button>
          <a href="marketplace.php" class="btn btn-secondary">Cancel</a>
        </form>
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
