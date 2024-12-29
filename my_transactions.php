<?php
// my_transactions.php

require_once 'config/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch user's transactions
$sql = "SELECT transactions.*, products.title AS product_title, products.description, transactions.status 
        FROM transactions 
        JOIN products ON transactions.product_id = products.id 
        WHERE transactions.user_id = :user_id 
        ORDER BY transactions.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Transactions - Jabu Market</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container py-5">
    <h2 class="mb-4">My Transactions</h2>
    <?php if (empty($transactions)): ?>
        <div class="alert alert-info">You have no transactions.</div>
    <?php else: ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Product</th>
                    <th>Amount</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                    <th>Transaction Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $txn): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($txn['id']); ?></td>
                        <td><?php echo htmlspecialchars($txn['product_title']); ?></td>
                        <td>₦<?php echo number_format($txn['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($txn['payment_method']); ?></td>
                        <td>
                            <?php
                                switch ($txn['status']) {
                                    case 'Pending':
                                        echo '<span class="badge bg-warning text-dark">Pending</span>';
                                        break;
                                    case 'Approved':
                                        echo '<span class="badge bg-success">Approved</span>';
                                        break;
                                    case 'Rejected':
                                        echo '<span class="badge bg-danger">Rejected</span>';
                                        break;
                                    default:
                                        echo htmlspecialchars($txn['status']);
                                }
                            ?>
                        </td>
                        <td><?php echo date('F j, Y, g:i a', strtotime($txn['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
