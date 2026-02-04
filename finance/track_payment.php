<?php
require_once '../config.php';
require_once '../auth/auth.php';

// Check if user is logged in and has finance role
if (!currentUserId()) {
    header('Location: auth/login.php');
    exit();
}

// Check if user has finance/admin role
if (!currentUserHasRole('Finance', $pdo) && !currentUserHasRole('Admin', $pdo) && !currentUserHasRole('Super Admin', $pdo)) {
    header('Location: index.php');
    exit();
}

$message = '';
$messageType = '';

// Handle confirm/reject actions
if (isset($_POST['confirm_payment']) || isset($_POST['reject_payment'])) {
    $payment_id = (int)($_POST['payment_id'] ?? 0);
    $action = isset($_POST['confirm_payment']) ? 'confirm' : 'reject';
    
    if ($payment_id > 0) {
        try {
            if ($action === 'confirm') {
                // Update payment status to confirmed
                $stmt = $pdo->prepare("UPDATE payments SET status = 'confirmed', updated_at = NOW() WHERE id = ?");
                $result = $stmt->execute([$payment_id]);
                
                if ($result) {
                    // Get payment details to record income
                    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
                    $stmt->execute([$payment_id]);
                    $payment = $stmt->fetch();
                    
                    if ($payment) {
                        // Record in finance income
                        $incomeStmt = $pdo->prepare("
                            INSERT INTO finance_income (
                                transaction_reference,
                                amount,
                                category,
                                recorded_by,
                                recorded_at
                            ) VALUES (?, ?, ?, ?, NOW())
                        ");
                        
                        $incomeStmt->execute([
                            $payment['transaction_reference'],
                            $payment['amount'],
                            $payment['category'],
                            currentUserId()
                        ]);
                        
                        // Log the confirmation
                        $logStmt = $pdo->prepare("INSERT INTO payment_logs (transaction_reference, action_taken) VALUES (?, 'confirmed')");
                        $logStmt->execute([$payment['transaction_reference']]);
                        
                        $message = "Payment confirmed successfully.";
                        $messageType = 'success';
                    } else {
                        $message = "Error: Payment details not found.";
                        $messageType = 'error';
                    }
                } else {
                    $message = "Error confirming payment.";
                    $messageType = 'error';
                }
            } else {
                // Reject payment
                $stmt = $pdo->prepare("UPDATE payments SET status = 'rejected', updated_at = NOW() WHERE id = ?");
                $result = $stmt->execute([$payment_id]);
                
                if ($result) {
                    // Log the rejection
                    $stmt = $pdo->prepare("SELECT transaction_reference FROM payments WHERE id = ?");
                    $stmt->execute([$payment_id]);
                    $payment = $stmt->fetch();
                    
                    if ($payment) {
                        $logStmt = $pdo->prepare("INSERT INTO payment_logs (transaction_reference, action_taken) VALUES (?, 'rejected')");
                        $logStmt->execute([$payment['transaction_reference']]);
                    }
                    
                    $message = "Payment rejected successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Error rejecting payment.";
                    $messageType = 'error';
                }
            }
        } catch (Exception $e) {
            $message = "Error processing payment: " . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = "Invalid payment ID.";
        $messageType = 'error';
    }
}

// Get all pending payments
$stmt = $pdo->prepare("
    SELECT * FROM payments 
    WHERE status = 'pending' 
    ORDER BY created_at DESC
");
$stmt->execute();
$pending_payments = $stmt->fetchAll();

// Get all confirmed/rejected payments
$stmt = $pdo->prepare("
    SELECT * FROM payments 
    WHERE status IN ('confirmed', 'rejected') 
    ORDER BY created_at DESC
");
$stmt->execute();
$processed_payments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Payments - Finance Dashboard</title>
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .payment-actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-confirm {
            background-color: #28a745;
            color: white;
        }
        
        .btn-reject {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-default {
            background-color: #6c757d;
            color: white;
        }
        
        .payment-status {
            padding: 4px 8px;
            border-radius: 4px;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-pending {
            background-color: #ffc107;
            color: #212529;
        }
        
        .status-confirmed {
            background-color: #28a745;
        }
        
        .status-rejected {
            background-color: #dc3545;
        }
        
        .payment-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .payment-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .payment-details {
            margin-bottom: 15px;
        }
        
        .payment-detail {
            display: flex;
            margin-bottom: 8px;
        }
        
        .detail-label {
            font-weight: bold;
            width: 150px;
            color: #555;
        }
        
        .detail-value {
            flex: 1;
        }
        
        .amount-large {
            font-size: 24px;
            font-weight: bold;
            color: #2E8B57;
        }
    </style>
</head>
<body>
    <?php include '../templates/admin_header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../templates/admin_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-credit-card"></i> Track Payments</h1>
                <p>Monitor and manage incoming payments</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i> 
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Pending Payments Section -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-clock"></i> Pending Payments (<?php echo count($pending_payments); ?>)</h3>
                </div>
                
                <?php if (empty($pending_payments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h4>No Pending Payments</h4>
                        <p>All payments have been processed</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Transaction Ref</th>
                                    <th>Full Name</th>
                                    <th>Phone</th>
                                    <th>Amount (ZMW)</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['transaction_reference']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['phone_number']); ?></td>
                                        <td><?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($payment['category']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($payment['description'], 0, 50)) . (strlen($payment['description']) > 50 ? '...' : ''); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($payment['created_at'])); ?></td>
                                        <td><span class="status-badge pending">Pending</span></td>
                                        <td>
                                            <div class="payment-actions">
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to confirm this payment?');">
                                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                    <button type="submit" name="confirm_payment" class="btn btn-confirm btn-sm">
                                                        <i class="fas fa-check"></i> Confirm
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to reject this payment?');">
                                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                    <button type="submit" name="reject_payment" class="btn btn-reject btn-sm">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Processed Payments Section -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-history"></i> Processed Payments</h3>
                </div>
                
                <?php if (empty($processed_payments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h4>No Processed Payments</h4>
                        <p>No payments have been confirmed or rejected yet</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Transaction Ref</th>
                                    <th>Full Name</th>
                                    <th>Phone</th>
                                    <th>Amount (ZMW)</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($processed_payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['transaction_reference']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['phone_number']); ?></td>
                                        <td><?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($payment['category']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $payment['status']; ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($payment['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <?php include '../templates/admin_footer.php'; ?>
</body>
</html>