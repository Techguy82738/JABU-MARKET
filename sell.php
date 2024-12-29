<?php
require_once 'config/db.php'; // Include the database connection

// Initialize variables for form validation
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = $_POST['title'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $description = $_POST['description'];
    $image = $_FILES['image'];

    // Validate inputs
    if (empty($title) || empty($price) || empty($category) || empty($description) || empty($image['name'])) {
        $message = "All fields are required.";
    } else {
        // Handle image upload
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($image["name"]);
        if (move_uploaded_file($image["tmp_name"], $target_file)) {
            // Insert product into database
            $sql = "INSERT INTO products (title, price, category, description, image) VALUES (:title, :price, :category, :description, :image)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'title' => $title,
                'price' => $price,
                'category' => $category,
                'description' => $description,
                'image' => $target_file
            ]);
            $message = "Product successfully listed!";
        } else {
            $message = "Failed to upload image.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sell - Jabu Market</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  
  <!-- Header Section -->
  <header class="header">
    <div class="container">
      <div class="logo">
        <a href="#">Jabu Market</a>
      </div>
      <nav class="nav">
        <ul class="nav-list">
          <li><a href="index.php">Home</a></li>
          <li class="dropdown">
            <a href="marketplace.php">Marketplace</a>
          </li>
          <li><a href="sell.php">Sell</a></li>
          <li><a href="login.php">Login</a></li>
          <li><a href="signup.php" class="btn-signup">Sign Up</a></li>
        </ul>
      </nav>
      <div class="mobile-nav-toggle">
        <span></span>
        <span></span>
        <span></span>
      </div>
    </div>
  </header>

  <!-- Sell Section -->
  <section class="sell-section py-5">
    <div class="container">
      <h2 class="text-center mb-4">Sell Your Product</h2>
      <?php if ($message): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
      <?php endif; ?>
      <form method="POST" enctype="multipart/form-data" class="p-4 shadow rounded bg-white">
        <div class="mb-3">
          <label for="title" class="form-label">Product Title</label>
          <input type="text" class="form-control" id="title" name="title" required>
        </div>
        <div class="mb-3">
          <label for="price" class="form-label">Price (USD)</label>
          <input type="number" step="0.01" class="form-control" id="price" name="price" required>
        </div>
        <div class="mb-3">
          <label for="category" class="form-label">Category</label>
          <select class="form-select" id="category" name="category" required>
            <option value="">Choose a category...</option>
            <option value="Books">Books</option>
            <option value="Electronics">Electronics</option>
            <option value="Clothing">Clothing</option>
            <option value="Furniture">Furniture</option>
          </select>
        </div>
        <div class="mb-3">
          <label for="description" class="form-label">Description</label>
          <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
        </div>
        <div class="mb-3">
          <label for="image" class="form-label">Upload Image</label>
          <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">List Product</button>
      </form>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer mt-5 py-3 bg-dark text-white">
    <div class="container text-center">
      <p>&copy; 2024 Jabu Market. All Rights Reserved.</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
