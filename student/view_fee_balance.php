<?php
session_start();
require_once '../config.php';
require_once '../auth.php';

// Check if user is logged in and has student role
if (!currentUserId() || !currentUserHasRole('Student', $pdo)) {
    header('Location: ../student_login.php');
    exit();
}

// Get student profile with balance information
$stmt = $pdo->prepare("
    SELECT sp.*, u.email, u.contact, p.name as programme_name 
    FROM student_profile sp 
    JOIN users u ON sp.user_id = u.id 
    LEFT JOIN programme p ON sp.programme_id = p.id
    WHERE sp.user_id = ?
");
$stmt->execute([currentUserId()]);
$student = $stmt->fetch();

if (!$student) {
    // Student profile not found
    header('Location: ../student_login.php');
    exit();
}

// Get payment history for this student
$payment_history = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM payments 
        WHERE student_id = ? 
        ORDER BY payment_date DESC, created_at DESC
    ");
    $stmt->execute([currentUserId()]);
    $payment_history = $stmt->fetchAll();
} catch (Exception $e) {
    // Payments table might not exist yet
    $payment_history = [];
}

// Get fee details for this student
$fee_details = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM student_fees 
        WHERE student_id = ? 
        ORDER BY due_date DESC, created_at DESC
    ");
    $stmt->execute([currentUserId()]);
    $fee_details = $stmt->fetchAll();
} catch (Exception $e) {
    // student_fees table might not exist yet
    $fee_details = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Balance - <?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?> - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Font Awesome icons are now used throughout the application */
        
        .balance-summary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .balance-amount {
            font-size: 3rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .balance-status {
            font-size: 1.2rem;
            margin-bottom: 20px;
        }
        
        .balance-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .detail-card {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }
        
        .detail-card h4 {
            margin: 0 0 10px 0;
            font-size: 1rem;
        }
        
        .detail-card p {
            margin: 0;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .data-panel {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .panel-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .panel-header h3 {
            margin: 0;
            color: #333;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .data-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-paid {
            background-color: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        .status-overdue {
            background-color: #f8d7da;
            color: #721c24;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        .status-partial {
            background-color: #cce5ff;
            color: #004085;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .no-data-message {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
            background-color: #f8f9fa;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ccc;
        }
        
        @media (max-width: 768px) {
            .balance-summary {
                padding: 20px;
            }
            
            .balance-amount {
                font-size: 2rem;
            }
            
            .balance-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="student-layout" data-theme="light">
    <!-- Top Navigation Bar -->
    <nav class="top-nav">
        <div class="nav-left">
            <div class="logo-container">
                <img src="../assets/images/lsc-logo.png" alt="LSC Logo" class="logo" onerror="this.style.display='none'">
                <span class="logo-text">LSC SRMS</span>
            </div>
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <div class="nav-right">
            <div class="user-info">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?></span>
                <span class="student-id">(<?php echo htmlspecialchars($student['student_number'] ?? 'N/A'); ?>)</span>
            </div>
            
            <div class="nav-actions">
                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="fas fa-moon" id="theme-icon"></i>
                </button>
                
                <div class="dropdown">
                    <button class="profile-btn" onclick="toggleDropdown()">
                        <i class="fas fa-user-circle"></i>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="profile.php"><i class="fas fa-user"></i> View Profile</a>
                        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-tachometer-alt"></i> Student Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Academic</h4>
                <a href="view_results.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>View Results</span>
                </a>
                <a href="register_courses.php" class="nav-item">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Register Courses</span>
                </a>
                <a href="view_docket.php" class="nav-item">
                    <i class="fas fa-file-alt"></i>
                    <span>View Docket</span>
                </a>
                <a href="elearning.php" class="nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span>E-Learning (Moodle)</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Finance & Accommodation</h4>
                <a href="view_fee_balance.php" class="nav-item active">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>View Fee Balance</span>
                </a>
                <a href="accommodation.php" class="nav-item">
                    <i class="fas fa-bed"></i>
                    <span>Accommodation</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-money-bill-wave"></i> Fee Balance</h1>
            <p>Payment History for - <?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?> <?php echo htmlspecialchars($student['student_number'] ?? ''); ?></p>
        </div>

        <!-- Balance Summary -->
        <div class="balance-summary">
            <h2><i class="fas fa-wallet"></i> Current Balance</h2>
            <div class="balance-amount">K<?php echo number_format($student['balance'] ?? 0, 2); ?></div>
            <div class="balance-status">
                <?php if (($student['balance'] ?? 0) > 0): ?>
                    <span class="status-overdue"><i class="fas fa-exclamation-circle"></i> Outstanding Balance</span>
                <?php elseif (($student['balance'] ?? 0) < 0): ?>
                    <span class="status-paid"><i class="fas fa-check-circle"></i> Credit Balance</span>
                <?php else: ?>
                    <span class="status-paid"><i class="fas fa-check-circle"></i> Balance Cleared</span>
                <?php endif; ?>
            </div>
            
            <div class="balance-details">
                <div class="detail-card">
                    <h4>Student ID</h4>
                    <p><?php echo htmlspecialchars($student['student_number'] ?? 'N/A'); ?></p>
                </div>
                <div class="detail-card">
                    <h4>Programme</h4>
                    <p><?php echo htmlspecialchars($student['programme_name'] ?? 'N/A'); ?></p>
                </div>
                <div class="detail-card">
                    <h4>Last Updated</h4>
                    <p><?php echo isset($student['updated_at']) ? date('M j, Y', strtotime($student['updated_at'])) : 'N/A'; ?></p>
                </div>
            </div>
        </div>

        <!-- Fee Details -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-file-invoice-dollar"></i> Fee Details</h3>
            </div>
            
            <?php if (!empty($fee_details)): ?>
                <?php 
                // Group fee details by academic year
                $grouped_fees = [];
                foreach ($fee_details as $fee) {
                    $academic_year = $fee['academic_year'] ?? 'N/A';
                    if (!isset($grouped_fees[$academic_year])) {
                        $grouped_fees[$academic_year] = [];
                    }
                    $grouped_fees[$academic_year][] = $fee;
                }
                ?>
                
                <?php foreach ($grouped_fees as $year => $fees): ?>
                    <div class="fee-year-section" style="margin-bottom: 30px;">
                        <h4 style="color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 10px;">Academic Year: <?php echo htmlspecialchars($year); ?></h4>
                        
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Fee Type</th>
                                        <th>Description</th>
                                        <th>Amount Due</th>
                                        <th>Amount Paid</th>
                                        <th>Balance</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Semester</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fees as $fee): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($fee['fee_type']); ?></td>
                                            <td><?php echo htmlspecialchars($fee['description'] ?? 'N/A'); ?></td>
                                            <td>K<?php echo number_format($fee['amount_due'], 2); ?></td>
                                            <td>K<?php echo number_format($fee['amount_paid'], 2); ?></td>
                                            <td>K<?php echo number_format($fee['balance'], 2); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($fee['due_date'])); ?></td>
                                            <td>
                                                <span class="status-<?php echo $fee['status']; ?>">
                                                    <?php echo ucfirst($fee['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($fee['semester']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-file-invoice"></i>
                    <h3>No Fee Details Found</h3>
                    <p>Your fee details will appear here once they are assigned by the finance office.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment History -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-history"></i> Payment History</h3>
            </div>
            
            <?php if (!empty($payment_history)): ?>
                <?php 
                // Group payments by academic year/term if possible
                $grouped_payments = [];
                foreach ($payment_history as $payment) {
                    // Try to extract academic year from description or use payment date
                    $academic_period = 'General Payments';
                    
                    // Check if payment has academic_year field
                    if (!empty($payment['academic_year'])) {
                        $academic_period = $payment['academic_year'];
                    } elseif (!empty($payment['description'])) {
                        // Look for academic year patterns in description
                        if (preg_match('/(\d{4}[\/\-]\d{4}|\d{4})/', $payment['description'], $matches)) {
                            $academic_period = $matches[1];
                        } elseif (preg_match('/(Semester|Term|Year)\s*[IV1-4]+/', $payment['description'], $matches)) {
                            $academic_period = trim($matches[0]);
                        }
                    }
                    
                    if (!isset($grouped_payments[$academic_period])) {
                        $grouped_payments[$academic_period] = [];
                    }
                    $grouped_payments[$academic_period][] = $payment;
                }
                
                // Also get fee details grouped by academic year
                $fee_details_grouped = [];
                foreach ($fee_details as $fee) {
                    $academic_year = $fee['academic_year'] ?? 'N/A';
                    if (!isset($fee_details_grouped[$academic_year])) {
                        $fee_details_grouped[$academic_year] = [];
                    }
                    $fee_details_grouped[$academic_year][] = $fee;
                }
                
                // Create a mapping of payments to fee details for the same period
                $payment_fee_mapping = [];
                foreach ($grouped_payments as $period => $payments) {
                    // Try to find matching fee details for this period
                    $matching_fee_details = [];
                    
                    // If we have fee details for this academic year, use them
                    if (isset($fee_details_grouped[$period])) {
                        $matching_fee_details = $fee_details_grouped[$period];
                    } elseif (count($fee_details) > 0 && $period == 'General Payments') {
                        // For general payments, show all fee details
                        $matching_fee_details = $fee_details;
                    }
                    
                    $payment_fee_mapping[$period] = [
                        'payments' => $payments,
                        'fee_details' => $matching_fee_details
                    ];
                }
                ?>
                
                <?php foreach ($payment_fee_mapping as $period => $data): ?>
                    <div class="payment-period-section" style="margin-bottom: 30px; border: 1px solid #eee; border-radius: 6px; padding: 15px;">
                        <h4 style="margin-top: 0; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px;"><?php echo htmlspecialchars($period); ?></h4>
                        
                        <!-- Payments Table -->
                        <h5><i class="fas fa-money-bill"></i> Payments</h5>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Transaction ID</th>
                                        <th>Currency</th>
                                        <th>Date & Time</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['payments'] as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['reference_number'] ?? 'N/A'); ?></td>
                                            <td>ZMW</td>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($payment['payment_date'])); ?></td>
                                            <td>K<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                            <td>
                                                <span class="status-<?php echo $payment['status']; ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Fee Breakdown Table -->
                        <h5 style="margin-top: 20px;"><i class="fas fa-file-invoice"></i> Fee Breakdown</h5>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Fee Type</th>
                                        <th>Currency</th>
                                        <th>Due Date</th>
                                        <th>Amount</th>
                                        <th>Paid</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th>Semester</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Initialize totals
                                    $total_amount = 0;
                                    $total_paid = 0;
                                    
                                    // Display only actual fee details from the database for this period
                                    if (!empty($data['fee_details'])):
                                        foreach ($data['fee_details'] as $fee_detail):
                                            $total_amount += $fee_detail['amount_due'];
                                            $total_paid += $fee_detail['amount_paid'];
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($fee_detail['fee_type']); ?></td>
                                            <td>ZMW</td>
                                            <td><?php echo date('M j, Y', strtotime($fee_detail['due_date'])); ?></td>
                                            <td><?php echo number_format($fee_detail['amount_due'], 2); ?></td>
                                            <td><?php echo number_format($fee_detail['amount_paid'], 2); ?></td>
                                            <td><?php echo number_format($fee_detail['balance'], 2); ?></td>
                                            <td>
                                                <span class="status-<?php echo $fee_detail['status']; ?>">
                                                    <?php echo ucfirst($fee_detail['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($fee_detail['semester'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php 
                                        endforeach;
                                    else:
                                        // If no fees are defined by finance officer, show a message
                                    ?>
                                        <tr>
                                            <td colspan="8" class="no-data-message">
                                                No fees have been defined for this period by the finance officer.
                                            </td>
                                        </tr>
                                    <?php 
                                    endif;
                                    ?>
                                    
                                    <?php if (!empty($data['fee_details'])): ?>
                                    <tr style="background-color: #f8f9fa; font-weight: bold;">
                                        <td colspan="3">Totals</td>
                                        <td>K<?php echo number_format($total_amount, 2); ?></td>
                                        <td>K<?php echo number_format($total_paid, 2); ?></td>
                                        <td>K<?php echo number_format($total_amount - $total_paid, 2); ?></td>
                                        <td colspan="2"></td>
                                    </tr>
                                    
                                    <tr style="background-color: #e9ecef; font-weight: bold;">
                                        <td colspan="5">Balance</td>
                                        <td colspan="3">K<?php echo number_format($total_amount - $total_paid, 2); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-receipt"></i>
                    <h3>No Payment History</h3>
                    <p>Your payment history will appear here once you make payments.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Toggle theme function
        function toggleTheme() {
            const currentTheme = document.body.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.body.setAttribute('data-theme', newTheme);
            localStorage.setItem('studentTheme', newTheme);
            
            // Update theme icon
            const themeIcon = document.getElementById('theme-icon');
            if (themeIcon) {
                themeIcon.className = newTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
            }
        }
        
        // Initialize theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('studentTheme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
            
            // Update theme icon
            const themeIcon = document.getElementById('theme-icon');
            if (themeIcon) {
                themeIcon.className = savedTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
            }
        });
        
        // Toggle sidebar function
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        }
        
        // Toggle dropdown function
        function toggleDropdown() {
            document.getElementById('profileDropdown').classList.toggle('show');
        }
        
        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.profile-btn')) {
                const dropdowns = document.getElementsByClassName('dropdown-menu');
                for (let i = 0; i < dropdowns.length; i++) {
                    dropdowns[i].classList.remove('show');
                }
            }
        }
    </script>
</body>
</html> window.onclick = function(event) {
            if (!event.target.matches('.profile-btn')) {
                const dropdowns = document.getElementsByClassName('dropdown-menu');
                for (let i = 0; i < dropdowns.length; i++) {
                    dropdowns[i].classList.remove('show');
                }
            }
        }
    </script>
</body>
</html>
</html>