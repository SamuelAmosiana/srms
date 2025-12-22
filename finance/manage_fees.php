<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has Sub Admin role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Sub Admin (Finance)', $pdo);

// Get sub admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $student_id = $_POST['student_id'];
    $amount = $_POST['amount'];
    $payment_date = $_POST['payment_date'];
    
    try {
        $pdo->beginTransaction();
        
        // Insert payment record into finance_transactions
        $stmt = $pdo->prepare("INSERT INTO finance_transactions (student_user_id, type, amount, description, created_at) VALUES ((SELECT user_id FROM student_profile WHERE student_number = ?), 'income', ?, 'Fee payment', ?)");
        $stmt->execute([$student_id, $amount, $payment_date]);
        
        // Update student balance in student_profile
        $stmt = $pdo->prepare("UPDATE student_profile SET balance = balance - ? WHERE student_number = ?");
        $stmt->execute([$amount, $student_id]);
        
        $pdo->commit();
        $success_message = "Payment recorded successfully!";
    } catch (Exception $e) {
        $pdo->rollback();
        $error_message = "Error recording payment: " . $e->getMessage();
    }
}

// Fetch student fee data
$stmt = $pdo->query("SELECT sp.student_number as student_id, sp.full_name, sp.balance as total_fees, sp.balance 
                    FROM student_profile sp");
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Fees - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="admin-layout" data-theme="light">
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($admin['full_name'] ?? 'Finance Administrator'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($admin['staff_id'] ?? 'N/A'); ?>)</span>
            </div>
            
            <div class="nav-actions">
                <a href="manage_programme_fees.php" class="nav-link" title="Programme Fees">
                    <i class="fas fa-file-invoice-dollar"></i>
                </a>
                
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
            <h3><i class="fas fa-tachometer-alt"></i> Finance Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Student Management</h4>
                <a href="view_students.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>View Students</span>
                </a>
                <a href="manage_fees.php" class="nav-item active">
                    <i class="fas fa-money-bill"></i>
                    <span>Manage Fees & Finances</span>
                </a>
                <a href="results_access.php" class="nav-item">
                    <i class="fas fa-lock"></i>
                    <span>Manage Results Access</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Financial Operations</h4>
                <a href="manage_programme_fees.php" class="nav-item pinned">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Programme Fees</span>
                </a>
                <a href="income_expenses.php" class="nav-item">
                    <i class="fas fa-chart-pie"></i>
                    <span>Income & Expenses</span>
                </a>
                <a href="finance_reports.php" class="nav-item">
                    <i class="fas fa-file-invoice"></i>
                    <span>Finance Reports</span>
                </a>
                <a href="registration_clearance.php" class="nav-item">
                    <i class="fas fa-user-check"></i>
                    <span>Registration Clearance</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-money-bill"></i> Manage Student Fees & Finances</h1>
            <p>View and update student fee balances, record payments, and manage financial reports</p>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Student Fees Table -->
        <div class="table-container">
            <h2>Student Fee Balances</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Total Fees (K)</th>
                        <th>Balance (K)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td><?php echo number_format($student['total_fees'], 2); ?></td>
                            <td><?php echo number_format($student['balance'], 2); ?></td>
                            <td>
                                <button class="btn small orange" onclick="openPaymentForm('<?php echo $student['student_id']; ?>', '<?php echo htmlspecialchars($student['full_name']); ?>')">
                                    <i class="fas fa-money-check"></i> Record Payment
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Payment Form -->
        <div class="form-container" id="paymentForm" style="display: none;">
            <h2>Record Payment</h2>
            <form method="POST" action="manage_fees.php">
                <input type="hidden" name="student_id" id="student_id">
                <div class="form-group">
                    <label for="student_name">Student Name</label>
                    <input type="text" id="student_name" disabled>
                </div>
                <div class="form-group">
                    <label for="amount">Payment Amount (K)</label>
                    <input type="number" name="amount" id="amount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="payment_date">Payment Date</label>
                    <input type="date" name="payment_date" id="payment_date" required>
                </div>
                <div class="form-actions">
                    <button type="submit" name="record_payment" class="btn green">Record Payment</button>
                    <button type="button" class="btn orange" onclick="closePaymentForm()">Cancel</button>
                </div>
            </form>
        </div>
        
        <!-- Quick Actions for Reports -->
        <div class="quick-actions">
            <h2><i class="fas fa-bolt"></i> Financial Reports</h2>
            <div class="actions-grid">
                <a href="income_expenses.php" class="action-card orange">
                    <i class="fas fa-chart-pie"></i>
                    <h3>Manage Income/Expenses</h3>
                    <p>Record and track financial transactions</p>
                </a>
                
                <a href="manage_programme_fees.php" class="action-card blue">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <h3>Programme Fees</h3>
                    <p>Manage fees for each programme</p>
                </a>
                
                <a href="finance_reports.php?type=income" class="action-card green">
                    <i class="fas fa-file-invoice"></i>
                    <h3>Income Reports</h3>
                    <p>Generate and print income reports</p>
                </a>
                
                <a href="finance_reports.php?type=expense" class="action-card orange">
                    <i class="fas fa-file-invoice"></i>
                    <h3>Expense Reports</h3>
                    <p>Generate and print expense reports</p>
                </a>
            </div>
        </div>
    </main>

    <script>
        function openPaymentForm(studentId, studentName) {
            document.getElementById('student_id').value = studentId;
            document.getElementById('student_name').value = studentName;
            document.getElementById('paymentForm').style.display = 'block';
        }

        function closePaymentForm() {
            document.getElementById('paymentForm').style.display = 'none';
            document.getElementById('student_id').value = '';
            document.getElementById('student_name').value = '';
            document.getElementById('amount').value = '';
            document.getElementById('payment_date').value = '';
        }
    </script>
    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>