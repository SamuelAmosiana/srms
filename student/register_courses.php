<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in and has student role
if (!currentUserId()) {
    header('Location: ../auth/login.php');
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
$stmt = $pdo->prepare("SELECT * FROM payments WHERE student_or_application_id = ? AND description LIKE ? AND status = 'paid' LIMIT 1");
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
                        $stmt = $pdo->prepare("INSERT INTO payments (student_or_application_id, amount, payment_date, payment_method, reference_number, status, description) VALUES (?, ?, NOW(), ?, ?, 'pending', ?)");
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

// Initialize variables for session-based approach
$selected_session_id = (int)($_GET['session_id'] ?? $_POST['session_id'] ?? 0);
$session_info = null;
$terms = [];
$courses_by_term = [];

// Get currently registered courses for the student in the current academic year
$academic_year = date('Y');
$registered_courses_stmt = $pdo->prepare("
    SELECT ce.course_id
    FROM course_enrollment ce
    WHERE ce.student_user_id = ? AND ce.academic_year = ?
");
$registered_courses_stmt->execute([currentUserId(), $academic_year]);
$registered_courses = $registered_courses_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

// Convert to associative array for quick lookup
$registered_courses_map = [];
foreach ($registered_courses as $course_id) {
    $registered_courses_map[$course_id] = true;
}

// Get currently registered courses for the student in the current academic year
$academic_year = date('Y');
$registered_courses_stmt = $pdo->prepare("
    SELECT ce.course_id
    FROM course_enrollment ce
    WHERE ce.student_user_id = ? AND ce.academic_year = ?
");
$registered_courses_stmt->execute([currentUserId(), $academic_year]);
$registered_courses = $registered_courses_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

// Convert to associative array for quick lookup
$registered_courses_map = [];
foreach ($registered_courses as $course_id) {
    $registered_courses_map[$course_id] = true;
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
        
        /* Session and Programme Course Tags */
        .course-tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .session-tag {
            background-color: #28a745;
            color: white;
        }
        
        .programme-tag {
            background-color: #17a2b8;
            color: white;
        }
        
        .session-course {
            border-left: 4px solid #28a745;
        }
        
        .programme-course {
            border-left: 4px solid #17a2b8;
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
        
        .course-item.registered {
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .course-status {
            color: #28a745;
            font-weight: bold;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
                <a href="elearning.php" class="nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span>E-Learning (Moodle)</span>
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
            <p>Select an academic session and register for courses</p>
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
            <!-- Step 1: Select Academic Session -->
            <div class="registration-step">
                <div class="step-header">
                    <div class="step-number">1</div>
                    <h2>Select Academic Session</h2>
                </div>
                
                <div id="session-selection">
                    <p>Please select an academic session to view available courses:</p>
                    <div class="form-group">
                        <label for="session_id">Academic Session *</label>
                        <select id="session_id" name="session_id" onchange="loadSessionCourses()" required>
                            <option value="">-- Select Session --</option>
                        </select>
                    </div>
                    <div id="session-info" style="margin-top: 15px; display: none;">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <span id="session-details"></span>
                        </div>
                    </div>
                </div>
                
                <div id="course-selection" style="display: none;">
                    <div class="step-header" style="margin-top: 30px;">
                        <div class="step-number">2</div>
                        <h2>Select Courses</h2>
                    </div>
                
                    <form method="POST" class="registration-form">
                        <input type="hidden" name="action" value="submit_registration">
                        <input type="hidden" id="selected_session_id" name="session_id" value="<?php echo $selected_session_id; ?>">
                        
                        <div id="courses-container">
                            <!-- Courses will be loaded here dynamically -->
                            <p>Please select a session to view available courses.</p>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="submit-registration-btn" style="display: none;">Submit Registration</button>
                    </form>
            </div>
            
                <!-- Step 3: Make Payment -->
                <div class="registration-step" id="payment-step" style="display: none;">
                    <div class="step-header">
                        <div class="step-number">3</div>
                        <h2>Make Payment</h2>
                    </div>
                    
                    <?php if ($payment_made): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Payment has been made for this academic year.
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
        <?php endif; ?>
    </main>

    <script src="../assets/js/student-dashboard.js"></script>
    <script>
        // Load programme sessions on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadProgrammeSessions();
        });
        
        function loadProgrammeSessions() {
            console.log('Loading programme sessions...');
            fetch('get_programme_sessions.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Programme sessions response:', data);
                    if (data.success) {
                        const sessionSelect = document.getElementById('session_id');
                        sessionSelect.innerHTML = '<option value="">-- Select Session --</option>';
                        
                        console.log('Number of sessions found:', data.sessions.length);
                        data.sessions.forEach(session => {
                            console.log('Adding session:', session.id, session.display_name);
                            const option = document.createElement('option');
                            option.value = session.id;
                            option.textContent = session.display_name;
                            sessionSelect.appendChild(option);
                        });
                        console.log('Session dropdown populated successfully');
                    } else {
                        console.error('Error loading sessions:', data.message);
                        document.getElementById('courses-container').innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> ' + data.message + '</div>';
                        document.getElementById('session-selection').innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> ' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching sessions:', error);
                    document.getElementById('courses-container').innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Error loading sessions. Please refresh the page or contact support.</div>';
                    document.getElementById('session-selection').innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Error loading sessions. Please refresh the page or contact support.</div>';
                });
        }
        
        function loadSessionCourses() {
            const sessionIdRaw = document.getElementById('session_id').value;
            const sessionId = sessionIdRaw ? parseInt(sessionIdRaw.trim(), 10) : 0;
            const courseSelection = document.getElementById('course-selection');
            const sessionInfo = document.getElementById('session-info');
            const sessionDetails = document.getElementById('session-details');
            const paymentStep = document.getElementById('payment-step');
            
            console.log('Selected session ID:', sessionId, '(Type:', typeof sessionId, ')');
            
            if (!sessionId || sessionId === '') {
                console.log('No session ID selected, hiding course selection');
                courseSelection.style.display = 'none';
                sessionInfo.style.display = 'none';
                paymentStep.style.display = 'none';
                return;
            }
            
            // Show loading state
            document.getElementById('courses-container').innerHTML = '<p>Loading courses...</p>';
            courseSelection.style.display = 'block';
            
            console.log('Fetching courses for session ID:', sessionId);
            // Use clean URL to avoid 301 redirects
            fetch('./get_session_courses', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `session_id=${sessionId}`
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Received response:', data);
                    if (data.success) {
                        // Update session info
                        sessionDetails.textContent = `Selected: ${data.session_info.session_name} (${data.session_info.academic_year} - ${data.session_info.term})`;
                        sessionInfo.style.display = 'block';
                        
                        // Update hidden session ID field
                        document.getElementById('selected_session_id').value = sessionId;
                        
                        // Display courses
                        displayCourses(data.courses_by_term, data.registered_courses);
                        
                        // Show payment step
                        paymentStep.style.display = 'block';
                    } else {
                        console.error('Error from server:', data.message);
                        document.getElementById('courses-container').innerHTML = `<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('courses-container').innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Error loading courses. Please try selecting a session again.</div>';
                });
        }
        
        function displayCourses(coursesByTerm, registeredCourses) {
            const container = document.getElementById('courses-container');
            let html = '';
            
            if (Object.keys(coursesByTerm).length === 0) {
                html = '<p>No courses available for this session.</p>';
            } else {
                // Create term tabs
                const terms = Object.keys(coursesByTerm);
                if (terms.length > 1) {
                    html += '<div class="term-tabs">';
                    terms.forEach((term, index) => {
                        const isActive = index === 0 ? 'active' : '';
                        html += `<div class="term-tab ${isActive}" onclick="showTermTab('${term}')">${term}</div>`;
                    });
                    html += '</div>';
                }
                
                // Create term content
                terms.forEach((term, index) => {
                    const isActive = index === 0 ? 'active' : '';
                    const courses = coursesByTerm[term];
                    
                    html += `<div id="term-${term}" class="term-content ${isActive}">`;
                    html += `<h3>${term} Courses</h3>`;
                    
                    if (courses.length === 0) {
                        html += '<p>No courses defined for this term.</p>';
                    } else {
                        html += '<div class="courses-list">';
                        courses.forEach(course => {
                            const isChecked = registeredCourses.includes(course.course_id.toString()) ? 'checked' : '';
                            const isRegistered = registeredCourses.includes(course.course_id.toString()) ? 'registered' : '';
                            const isSessionCourse = course.is_session_course ? 'session-course' : 'programme-course';
                            
                            html += `
                                <div class="course-item ${isRegistered} ${isSessionCourse}">
                                    <label>
                                        <input type="checkbox" name="courses[]" value="${course.course_id}" ${isChecked}>
                                        <div class="course-details">
                                            <span class="course-code">${course.course_code}</span> - 
                                            <span class="course-name">${course.course_name}</span>
                                            <br>
                                            <span class="course-credits">${course.credits} Credits</span>`;
                            
                            if (course.is_session_course) {
                                html += ' <span class="course-tag session-tag">Session Course</span>';
                            } else {
                                html += ' <span class="course-tag programme-tag">Programme Course</span>';
                            }
                            
                            if (registeredCourses.includes(course.course_id.toString())) {
                                html += ' <span class="course-status"><strong>Registered</strong></span>';
                            }
                            
                            html += `
                                        </div>
                                    </label>
                                </div>`;
                        });
                        html += '</div>';
                    }
                    html += '</div>';
                });
            }
            
            container.innerHTML = html;
            document.getElementById('submit-registration-btn').style.display = 'block';
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
            document.getElementById(`term-${term}`).classList.add('active');
            
            // Add active class to clicked tab
            event.currentTarget.classList.add('active');
        }
        
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