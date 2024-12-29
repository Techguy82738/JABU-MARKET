<?php
require_once 'config/db.php'; // Include the database connection

// Get the category from the query string, if available
$category = isset($_GET['category']) ? $_GET['category'] : '';

// If a category is selected, fetch random products from that category
if ($category) {
    $sql = "SELECT * FROM products WHERE category = :category ORDER BY RAND() LIMIT 3";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['category' => $category]);
} else {
    // Default query to get random products (no category selected)
    $sql = "SELECT * FROM products ORDER BY RAND() LIMIT 3";
    $stmt = $pdo->query($sql);
}

$result = $stmt;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jabu Market - Student Marketplace</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

  <style>
  /* Global Styles */
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

/* Hero Section */
.hero {
    background-color: #f4f9ff;
    padding: 2rem 0;
    text-align: center;
}

.hero h1 {
    font-size: 2rem;
    color: #003f7f;
}

.hero .search-bar {
    margin-top: 1rem;
}

.hero input {
    padding: 0.5rem;
    width: 70%;
    border: 1px solid #ddd;
    border-radius: 5px;
}

.hero button {
    padding: 0.5rem 1rem;
    background-color: #0056b3;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

/* Categories Section */
.categories {
    padding: 2rem 0;
    text-align: center;
}

.categories .category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.categories .category-card {
    padding: 1rem;
    background-color: #003f7f;
    color: white;
    border-radius: 5px;
    font-weight: bold;
    text-align: center;
    cursor: pointer;
}

/* Featured Products Section */
.featured {
    padding: 2rem 0;
}

.featured .product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.featured .product-card {
    text-align: center;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 1rem;
}

.featured .product-card img {
    max-width: 100%;
    height: 200px;
    object-fit: cover;
    border-radius: 5px;
}

.featured .product-card h3 {
    margin: 0.5rem 0;
}

/* CTA Section */
.cta {
    background-color: #003f7f;
    color: white;
    text-align: center;
    padding: 2rem 0;
}

.cta .btn-cta {
    background-color: white;
    color: #003f7f;
    padding: 0.5rem 1rem;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    text-transform: uppercase;
}

/* Footer */
.footer {
    background-color: #003f7f;
    color: white;
    text-align: center;
    padding: 1rem 0;
}

.footer a {
    color: white;
    text-decoration: none;
}

.footer a:hover {
    text-decoration: underline;
}

footer i {
    font-size: 1.2rem;
}

  </style>
</head>
<body>

<header class="header">
  <div class="container">
    <div class="logo">
      <a href="#">Jabu Market</a>
    </div>
    <nav class="nav">
      <ul class="nav-list">
        <li><a href="index.php">Home</a></li>
        <li><a href="marketplace.php">Marketplace</a></li>
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

<!-- Hero Section -->
<section class="hero">
  <div class="container">
    <h1>Welcome to Jabu Market</h1>
    <p>Your one-stop marketplace for everything students need!</p>
    <form class="search-bar" method="GET" action="marketplace.php">
      <input type="text" name="search" placeholder="Search for books, electronics, and more..." aria-label="Search">
      <button type="submit">Search</button>
    </form>

  </div>
</section>

<!-- Categories Section -->
<section class="categories">
  <div class="container">
    <h2>Shop by Categories</h2>
    <div class="category-grid">
      <div class="category-card" data-category="Sports">Sports</div>
      <div class="category-card" data-category="Electronics">Electronics</div>
      <div class="category-card" data-category="Clothing">Clothing</div>
      <div class="category-card" data-category="Furniture">Furniture</div>
    </div>
  </div>
</section>

<!-- Featured Products Section -->
<section class="featured">
  <div class="container">
    <h2>Featured Products</h2>
    <div class="product-grid">
      <?php while($row = $result->fetch()): ?>
        <div class="product-card ">
          <img src="<?php echo $row['image']; ?>" class="product-image" alt="<?php echo $row['title']; ?>">
          <h3><?php echo $row['title']; ?></h3>
          <p>$<?php echo number_format($row['price'], 2); ?></p>
        </div>
      <?php endwhile; ?>
    </div>
  </div>
</section>

<!-- Call to Action Section -->
<section class="cta">
  <div class="container">
    <h2>Join Jabu Market Today!</h2>
    <p>Start selling your products or find amazing deals in just a few clicks.</p>
    <a href="marketplace.php" class="btn-cta">Get Started</a>
  </div>
</section>

<?php include 'footer.php'; ?>

<script>
  // Sticky Header Shrinking Effect
  window.addEventListener('scroll', () => {
      const header = document.querySelector('.header');
      if (window.scrollY > 50) {
          header.classList.add('scrolled');
      } else {
          header.classList.remove('scrolled');
      }
  });

  // Mobile Navigation Toggle
  const navToggle = document.querySelector('.mobile-nav-toggle');
  const nav = document.querySelector('.nav');

  navToggle.addEventListener('click', () => {
      nav.classList.toggle('active');
  });

  // Listen for category click events
  document.querySelectorAll('.category-card').forEach(item => {
    item.addEventListener('click', function() {
      const category = this.getAttribute('data-category');
      
      // Send the selected category to the server using AJAX
      fetch('index.php?category=' + category)
        .then(response => response.text())  // Get the response as text
        .then(data => {
          // Extract the new product grid (HTML)
          const productGrid = document.createElement('div');
          productGrid.innerHTML = data;
          // Find the new grid and replace the existing one
          const newProducts = productGrid.querySelector('.product-grid').innerHTML;
          document.querySelector('.featured .product-grid').innerHTML = newProducts;
        })
        .catch(error => console.error('Error:', error));
    });
  });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
