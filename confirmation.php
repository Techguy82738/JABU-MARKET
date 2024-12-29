<?php
// confirmation.php

require_once 'config/db.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_GET['transaction_id'])) {
    header('Location: marketplace.php');
    exit();
}

$transaction_id = (int)$_GET['transaction_id'];

// Fetch transaction details
$sql = "SELECT transactions.*, products.title, products.description, users.name AS seller_name 
        FROM transactions 
        JOIN products ON transactions.product_id = products.id 
        JOIN users ON products.seller_id = users.id 
        WHERE transactions.id = :transaction_id AND transactions.user_id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    'transaction_id' => $transaction_id,
    'user_id' => $_SESSION['user_id']
]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    header('Location: marketplace.php?error=Transaction not found.');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Confirmation - Jabu Market</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container py-5">
    <h2 class="mb-4">Purchase Initiated Successfully!</h2>
    <div class="alert alert-info">
        Your purchase for <strong><?php echo htmlspecialchars($transaction['title']); ?></strong> is pending verification. 
        Once your bank transfer is confirmed, you will receive a confirmation email, and the seller will be notified.
    </div>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title"><?php echo htmlspecialchars($transaction['title']); ?></h5>
            <p class="card-text"><?php echo htmlspecialchars($transaction['description']); ?></p>
            <p class="card-text"><strong>Amount: ₦<?php echo number_format($transaction['amount'], 2); ?></strong></p>
            <p class="card-text"><small>Seller: <?php echo htmlspecialchars($transaction['seller_name']); ?></small></p>
            <p class="card-text"><small>Transaction Date: <?php echo date('F j, Y, g:i a', strtotime($transaction['created_at'])); ?></small></p>
            <a href="my_transactions.php" class="btn btn-primary">View My Transactions</a>
            <a href="marketplace.php" class="btn btn-secondary">Continue Shopping</a>
        </div>
    </div>
</div>
</body>
</html>
