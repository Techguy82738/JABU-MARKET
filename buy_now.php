<?php
require_once 'config/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize product_id
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

    if ($product_id <= 0) {
        // Invalid product_id
        $_SESSION['error_message'] = 'Invalid product selected.';
        header('Location: marketplace.php');
        exit();
    }

    // Fetch product details
    $product_sql = "SELECT * FROM products WHERE id = :product_id";
    $stmt = $pdo->prepare($product_sql);
    $stmt->execute(['product_id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        // Product not found
        $_SESSION['error_message'] = 'Product not found.';
        header('Location: marketplace.php');
        exit();
    }

    $seller_id = $product['user_id'];
    $buyer_id = $user_id;
    $amount = $product['price'];

    // Generate a unique reference number
    $reference_number = 'REF-' . strtoupper(uniqid());

    // Insert transaction into database
    $transaction_sql = "INSERT INTO transactions (product_id, buyer_id, seller_id, amount, reference_number) 
                        VALUES (:product_id, :buyer_id, :seller_id, :amount, :reference_number)";
    $transaction_stmt = $pdo->prepare($transaction_sql);
    $transaction_stmt->execute([
        'product_id' => $product_id,
        'buyer_id' => $buyer_id,
        'seller_id' => $seller_id,
        'amount' => $amount,
        'reference_number' => $reference_number
    ]);

    // Redirect to payment instructions page with the transaction ID
    $transaction_id = $pdo->lastInsertId();
    header("Location: payment_instructions.php?transaction_id=" . $transaction_id);
    exit();
} else {
    // Invalid request method
    header('Location: marketplace.php');
    exit();
}
?>
