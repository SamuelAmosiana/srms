<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has student role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Student', $pdo);

// Get student profile with intake information
$stmt = $pdo->prepare("SELECT sp.full_name, sp.student_number as student_id, sp.programme_id, sp.intake_id FROM student_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$student = $stmt->fetch();

// Get current academic year and intake
$current_year = date('Y');
$academic_year = $current_year . '/' . ($current_year + 1);

// Check if already registered for current academic year
$stmt = $pdo->prepare("SELECT * FROM course_enrollment WHERE student_user_id = ? AND academic_year = ? LIMIT 1");
$stmt->execute([currentUserId(), $academic_year]);
$existing_registration = $stmt->fetch();

// Check if payment has been made for current academic year
$stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? AND description LIKE ? AND status = 'paid' LIMIT 1");
$stmt->execute([currentUserId(), "%$academic_year%"]);
$payment_made = $stmt->fetch();

// Handle form submission
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing_registration) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'submit_registration':
                $selected_courses = $_POST['courses'] ?? [];
                if (empty($selected_courses)) {
                    $message = 'Please select at least one course.';
                    $messageType = 'error';
                } else {
                    try {
                        // Insert enrollments for each selected course
                        foreach ($selected_courses as $course_id) {
                            $stmt = $pdo->prepare("INSERT INTO course_enrollment (student_user_id, course_id, academic_year, status) VALUES (?, ?, ?, 'pending')");
                            $stmt->execute([currentUserId(), $course_id, $academic_year]);
                        }
                        $message = 'Registration submitted successfully. Awaiting approval.';
                        $messageType = 'success';
                        $existing_registration = true; // Refresh status
                    } catch (Exception $e) {
                        $message = 'Error submitting registration: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'submit_payment':
                $amount = $_POST['amount'] ?? 0;
                $payment_method = $_POST['payment_method'] ?? '';
                $reference_number = $_POST['reference_number'] ?? '';
                
                // Handle file upload
                $payment_proof = null;
                if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] == 0) {
                    $upload_dir = '../uploads/payment_proofs/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
                    
                    if (in_array($file_extension, $allowed_extensions)) {
                        $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
                        $target_file = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $target_file)) {
                            $payment_proof = $target_file;
                        }
                    }
                }
                
                if (empty($amount) || empty($payment_method) || empty($reference_number)) {
                    $message = 'Please fill in all payment details.';
                    $messageType = 'error';
                } else {
                    try {
                        // Insert payment record
                        $stmt = $pdo->prepare("INSERT INTO payments (student_id, amount, payment_date, payment_method, reference_number, status, description, created_at) VALUES (?, ?, NOW(), ?, ?, 'pending', ?, NOW())");
                        $stmt->execute([
                            currentUserId(),
                            $amount,
                            $payment_method,
                            $reference_number,
                            'Payment for academic year ' . $academic_year
                        ]);
                        
                        $payment_id = $pdo->lastInsertId();
                        
                        // Update payment with proof if uploaded
                        if ($payment_proof) {
                            $stmt = $pdo->prepare("UPDATE payments SET description = ? WHERE id = ?");
                            $stmt->execute([$payment_proof, $payment_id]);
                        }
                        
                        $message = 'Payment submitted successfully. Awaiting verification.';
                        $messageType = 'success';
                        $payment_made = true; // Refresh status
                    } catch (Exception $e) {
                        $message = 'Error submitting payment: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
        }
    }
}

// Fetch available courses for student's programme and intake
// First, get all terms defined for this intake
$terms = [];
$courses_by_term = [];

if ($student['intake_id']) {
    // Get distinct terms for this intake
    $stmt = $pdo->prepare("SELECT DISTINCT term FROM intake_courses WHERE intake_id = ? ORDER BY term");
    $stmt->execute([$student['intake_id']]);
    $terms = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // For each term, get the courses defined for this intake and programme
    foreach ($terms as $term) {
        $stmt = $pdo->prepare("
            SELECT c.id as course_id, c.code as course_code, c.name as course_name, c.credits
            FROM intake_courses ic
            JOIN course c ON ic.course_id = c.id
            WHERE ic.intake_id = ? AND ic.term = ?
            AND (ic.programme_id = ? OR ic.programme_id IS NULL)
            ORDER BY c.name
        ");
        $stmt->execute([$student['intake_id'], $term, $student['programme_id']]);
        $courses_by_term[$term] = $stmt->fetchAll();
    }
} else {
    // Fallback to old method if no intake is set
    $stmt = $pdo->prepare("SELECT c.id as course_id, c.code as course_code, c.name as course_name, c.credits FROM course c WHERE c.programme_id = ?");
    $stmt->execute([$student['programme_id']]);
    $courses_by_term['General'] = $stmt->fetchAll();
    $terms = ['General'];
}

// Fetch programme fees
$stmt = $pdo->prepare("SELECT * FROM programme_fees WHERE programme_id = ? AND is_active = 1");
$stmt->execute([$student['programme_id']]);
$programme_fees = $stmt->fetchAll();

// Calculate total fees
$total_fees = 0;
foreach ($programme_fees as $fee) {
    $total_fees += $fee['fee_amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Courses - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .payment-methods {
            display: flex;
            gap: 15px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .payment-method {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            flex: 1;
            min-width: 120px;
            transition: all 0.3s;
        }
        
        .payment-method:hover {
            background-color: #f0f0f0;
            border-color: #007bff;
        }
        
        .payment-method.selected {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .payment-method i {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .fee-summary {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .fee-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        
        .fee-total {
            font-weight: bold;
            font-size: 18px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #007bff;
        }
        
        .btn-submit {
            background-color: #007bff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        
        .btn-submit:hover {
            background-color: #0056b3;
        }
        
        .registration-step {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .step-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .step-number {
            background-color: #007bff;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: bold;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Term tabs styling */
        .term-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .term-tab {
            padding: 10px 20px;
            cursor: pointer;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
        }
        
        .term-tab.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .term-content {
            display: none;
        }
        
        .term-content.active {
            display: block;
        }
        
        .course-item {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
        }
        
        .course-item label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .course-item input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .course-details {
            flex-grow: 1;
        }
        
        .course-code {
            font-weight: bold;
            color: #007bff;
        }
        
        .course-credits {
            font-size: 0.9em;
            color: #666;
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
                <span class="staff-id">(<?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?>)</span>
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
                <a href="register_courses.php" class="nav-item active">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Register Courses</span>
                </a>
                <a href="view_docket.php" class="nav-item">
                    <i class="fas fa-file-alt"></i>
                    <span>View Docket</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Finance & Accommodation</h4>
                <a href="view_fee_balance.php" class="nav-item">
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
            <h1><i class="fas fa-clipboard-check"></i> Register Courses</h1>
            <p>Register for courses in the academic year: <?php echo $academic_year; ?></p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($existing_registration): ?>
            <div class="registration-status">
                <h2>Registration Status</h2>
                <p>Status: <strong>Pending Approval</strong></p>
                <p>Your course registration is awaiting approval from the administration.</p>
            </div>
        <?php else: ?>
            <!-- Step 1: Make Payment -->
            <div class="registration-step">
                <div class="step-header">
                    <div class="step-number">1</div>
                    <h2>Make Payment</h2>
                </div>
                
                <?php if ($payment_made): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Payment has been made for this academic year. You can now proceed with course registration.
                    </div>
                <?php else: ?>
                    <form method="POST" enctype="multipart/form-data" class="payment-form">
                        <input type="hidden" name="action" value="submit_payment">
                        
                        <div class="fee-summary">
                            <h3>Fee Summary</h3>
                            <?php foreach ($programme_fees as $fee): ?>
                                <div class="fee-item">
                                    <span><?php echo htmlspecialchars($fee['fee_name']); ?></span>
                                    <span>K<?php echo number_format($fee['fee_amount'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                            <div class="fee-item fee-total">
                                <span>Total</span>
                                <span>K<?php echo number_format($total_fees, 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="payment-methods">
                            <div class="payment-method" onclick="selectPaymentMethod('bank_transfer')">
                                <i class="fas fa-university"></i>
                                <div>Bank Transfer</div>
                            </div>
                            <div class="payment-method" onclick="selectPaymentMethod('mobile_money')">
                                <i class="fas fa-mobile-alt"></i>
                                <div>Mobile Money</div>
                            </div>
                            <div class="payment-method" onclick="selectPaymentMethod('credit_card')">
                                <i class="fas fa-credit-card"></i>
                                <div>Credit Card</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_method">Payment Method *</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="">-- Select Payment Method --</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="credit_card">Credit Card</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">Amount (K) *</label>
                            <input type="number" id="amount" name="amount" step="0.01" min="0" value="<?php echo $total_fees; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="reference_number">Reference Number *</label>
                            <input type="text" id="reference_number" name="reference_number" required placeholder="Enter transaction reference number">
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_proof">Payment Proof (Receipt/Slip)</label>
                            <input type="file" id="payment_proof" name="payment_proof" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                        </div>
                        
                        <button type="submit" class="btn-submit">Submit Payment</button>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- Step 2: Register Courses (only if payment is made) -->
            <?php if ($payment_made): ?>
                <div class="registration-step">
                    <div class="step-header">
                        <div class="step-number">2</div>
                        <h2>Select Courses</h2>
                    </div>
                    
                    <form method="POST" class="registration-form">
                        <input type="hidden" name="action" value="submit_registration">
                        
                        <?php if (!empty($terms)): ?>
                            <div class="term-tabs">
                                <?php foreach ($terms as $index => $term): ?>
                                    <div class="term-tab <?php echo $index === 0 ? 'active' : ''; ?>" onclick="showTermTab('<?php echo $term; ?>')">
                                        <?php echo htmlspecialchars($term); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php foreach ($terms as $index => $term): ?>
                                <div id="term-<?php echo $term; ?>" class="term-content <?php echo $index === 0 ? 'active' : ''; ?>">
                                    <h3><?php echo htmlspecialchars($term); ?> Courses</h3>
                                    
                                    <?php if (empty($courses_by_term[$term])): ?>
                                        <p>No courses defined for this term.</p>
                                    <?php else: ?>
                                        <div class="courses-list">
                                            <?php foreach ($courses_by_term[$term] as $course): ?>
                                                <div class="course-item">
                                                    <label>
                                                        <input type="checkbox" name="courses[]" value="<?php echo $course['course_id']; ?>">
                                                        <div class="course-details">
                                                            <span class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></span> - 
                                                            <span class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></span>
                                                            <br>
                                                            <span class="course-credits"><?php echo htmlspecialchars($course['credits']); ?> Credits</span>
                                                        </div>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No terms defined for your intake. Please contact the administrator.</p>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn-submit">Submit Registration</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script src="../assets/js/student-dashboard.js"></script>
    <script>
        function selectPaymentMethod(method) {
            // Remove selected class from all payment methods
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Add selected class to clicked payment method
            event.currentTarget.classList.add('selected');
            
            // Set the payment method select value
            document.getElementById('payment_method').value = method;
        }
        
        function showTermTab(term) {
            // Hide all term content
            document.querySelectorAll('.term-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.term-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected term content
            document.getElementById('term-' + term).classList.add('active');
            
            // Add active class to clicked tab
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>