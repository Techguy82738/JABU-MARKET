<?php
// admin_dashboard.php

require_once 'config/db.php';
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle approval or rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['transaction_id'], $_POST['action'])) {
        $transaction_id = intval($_POST['transaction_id']);
        $action = $_POST['action'];

        // Fetch transaction details
        $transaction_sql = "SELECT transactions.*, users.email AS buyer_email, users.name AS buyer_name, 
                               products.title AS product_title, products.seller_id 
                            FROM transactions 
                            JOIN users ON transactions.user_id = users.id 
                            JOIN products ON transactions.product_id = products.id 
                            WHERE transactions.id = :transaction_id";
        $stmt = $pdo->prepare($transaction_sql);
        $stmt->execute(['transaction_id' => $transaction_id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($transaction) {
            if ($action === 'approve') {
                // Update transaction status to 'Approved'
                $update_sql = "UPDATE transactions SET status = 'Approved', updated_at = NOW() WHERE id = :transaction_id";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute(['transaction_id' => $transaction_id]);

                // Mark product as sold
                $product_update_sql = "UPDATE products SET is_sold = 1, is_pending = 0 WHERE id = :product_id";
                $product_update_stmt = $pdo->prepare($product_update_sql);
                $product_update_stmt->execute(['product_id' => $transaction['product_id']]);

                // Send notifications to buyer and seller
                // Buyer Notification
                $buyer_notification = "Your payment for '{$transaction['product_title']}' has been approved.";
                $notification_sql = "INSERT INTO notifications (user_id, message, is_read, created_at) 
                                     VALUES (:user_id, :message, 0, NOW())";
                $notif_stmt = $pdo->prepare($notification_sql);
                $notif_stmt->execute([
                    'user_id' => $transaction['user_id'],
                    'message' => $buyer_notification
                ]);

                // Seller Notification
                $seller_notification = "Your product '{$transaction['product_title']}' has been sold.";
                $notif_stmt->execute([
                    'user_id' => $transaction['seller_id'],
                    'message' => $seller_notification
                ]);

                // Optionally, send emails to buyer and seller
                // mail($transaction['buyer_email'], "Payment Approved", $buyer_notification);
                // Fetch seller's email
                $seller_sql = "SELECT email, name FROM users WHERE id = :seller_id";
                $seller_stmt = $pdo->prepare($seller_sql);
                $seller_stmt->execute(['seller_id' => $transaction['seller_id']]);
                $seller = $seller_stmt->fetch(PDO::FETCH_ASSOC);
                // mail($seller['email'], "Product Sold", $seller_notification);

                header('Location: admin_dashboard.php?success=Transaction approved successfully.');
                exit();

            } elseif ($action === 'reject') {
                // Validate rejection reason
                if (isset($_POST['reason']) && !empty(trim($_POST['reason']))) {
                    $reason = trim($_POST['reason']);

                    // Update transaction status to 'Rejected' and store reason
                    $update_sql = "UPDATE transactions SET status = 'Rejected', rejection_reason = :reason, updated_at = NOW() 
                                   WHERE id = :transaction_id";
                    $update_stmt = $pdo->prepare($update_sql);
                    $update_stmt->execute([
                        'reason' => $reason,
                        'transaction_id' => $transaction_id
                    ]);

                    // Mark product as not pending
                    $product_update_sql = "UPDATE products SET is_pending = 0 WHERE id = :product_id";
                    $product_update_stmt = $pdo->prepare($product_update_sql);
                    $product_update_stmt->execute(['product_id' => $transaction['product_id']]);

                    // Send notifications to buyer and seller
                    // Buyer Notification
                    $buyer_notification = "Your payment for '{$transaction['product_title']}' has been rejected. Reason: {$reason}";
                    $notification_sql = "INSERT INTO notifications (user_id, message, is_read, created_at) 
                                         VALUES (:user_id, :message, 0, NOW())";
                    $notif_stmt = $pdo->prepare($notification_sql);
                    $notif_stmt->execute([
                        'user_id' => $transaction['user_id'],
                        'message' => $buyer_notification
                    ]);

                    // Optionally, send emails to buyer and seller
                    // mail($transaction['buyer_email'], "Payment Rejected", $buyer_notification);

                    header('Location: admin_dashboard.php?success=Transaction rejected successfully.');
                    exit();
                } else {
                    header('Location: admin_dashboard.php?error=Rejection reason is required.');
                    exit();
                }
            }
        }
    }
}

// Fetch all pending transactions
$pending_sql = "SELECT transactions.*, users.name AS buyer_name, users.email AS buyer_email, 
                       products.title AS product_title, products.image AS product_image 
                FROM transactions 
                JOIN users ON transactions.user_id = users.id 
                JOIN products ON transactions.product_id = products.id 
                WHERE transactions.status = 'Pending' 
                ORDER BY transactions.created_at ASC";
$pending_stmt = $pdo->prepare($pending_sql);
$pending_stmt->execute();
$pending_transactions = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Jabu Market</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f9ff;
            padding-top: 60px;
        }
        .container {
            max-width: 1200px;
        }
        .transaction-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h2 class="mb-4">Admin Dashboard</h2>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <h4 class="mb-3">Pending Transactions</h4>

        <?php if (empty($pending_transactions)): ?>
            <p>No pending transactions at the moment.</p>
        <?php else: ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Buyer</th>
                        <th>Product</th>
                        <th>Amount (₦)</th>
                        <th>Proof of Payment</th>
                        <th>Submitted At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_transactions as $transaction): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($transaction['id']); ?></td>
                            <td><?php echo htmlspecialchars($transaction['buyer_name']); ?></td>
                            <td>
                                <img src="<?php echo htmlspecialchars($transaction['product_image']); ?>" alt="<?php echo htmlspecialchars($transaction['product_title']); ?>" class="transaction-image me-2">
                                <?php echo htmlspecialchars($transaction['product_title']); ?>
                            </td>
                            <td><?php echo number_format($transaction['amount'], 2); ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($transaction['proof_of_payment']); ?>" target="_blank">View Proof</a>
                            </td>
                            <td><?php echo date('F j, Y, g:i a', strtotime($transaction['created_at'])); ?></td>
                            <td>
                                <!-- Approve Button -->
                                <button class="btn btn-success btn-sm approve-btn" data-id="<?php echo htmlspecialchars($transaction['id']); ?>">Approve</button>
                                
                                <!-- Reject Button -->
                                <button class="btn btn-danger btn-sm reject-btn" data-id="<?php echo htmlspecialchars($transaction['id']); ?>">Reject</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="POST" action="admin_dashboard.php">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="approveModalLabel">Approve Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="transaction_id" id="approve_transaction_id" value="">
                <p>Are you sure you want to approve this transaction?</p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
              </div>
            </div>
        </form>
      </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="POST" action="admin_dashboard.php">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="rejectModalLabel">Reject Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="transaction_id" id="reject_transaction_id" value="">
                <div class="mb-3">
                    <label for="reason" class="form-label">Reason for Rejection:</label>
                    <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                </div>
                <p>Are you sure you want to reject this transaction?</p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
              </div>
            </div>
        </form>
      </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle Approve Button Click
        const approveButtons = document.querySelectorAll('.approve-btn');
        approveButtons.forEach(button => {
            button.addEventListener('click', () => {
                const transactionId = button.getAttribute('data-id');
                document.getElementById('approve_transaction_id').value = transactionId;
                const approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
                approveModal.show();
            });
        });

        // Handle Reject Button Click
        const rejectButtons = document.querySelectorAll('.reject-btn');
        rejectButtons.forEach(button => {
            button.addEventListener('click', () => {
                const transactionId = button.getAttribute('data-id');
                document.getElementById('reject_transaction_id').value = transactionId;
                const rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
                rejectModal.show();
            });
        });
    </script>
</body>
</html>
