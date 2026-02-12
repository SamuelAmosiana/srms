<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in and has sub admin role
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit;
}

requireRole('Sub Admin (Finance)', $pdo);

// Get sub admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Handle form actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'clear_registration':
                $student_id = $_POST['student_id'];
                $registration_type = $_POST['registration_type']; // 'course' or 'first_time'
                
                try {
                    if ($registration_type === 'course') {
                        // Fetch course registration details to get payment information
                        $stmt = $pdo->prepare("
                            SELECT cr.*, sp.full_name, sp.student_number, sp.user_id as student_user_id, c.name as course_name, p.name as programme_name, i.name as intake_name, u.email as student_email
                            FROM course_registration cr
                            JOIN student_profile sp ON cr.student_id = sp.user_id
                            JOIN course c ON cr.course_id = c.id
                            LEFT JOIN programme p ON sp.programme_id = p.id
                            LEFT JOIN intake i ON sp.intake_id = i.id
                            LEFT JOIN users u ON sp.user_id = u.id
                            WHERE cr.student_id = ? AND cr.status = 'approved_academic' AND cr.finance_cleared = 0
                            LIMIT 1
                        ");
                        $stmt->execute([$student_id]);
                        $registration_details = $stmt->fetch();
                        
                        // Mark course registration as cleared for finance
                        $stmt = $pdo->prepare("UPDATE course_registration SET finance_cleared = 1, finance_cleared_at = NOW(), finance_cleared_by = ?, status = 'fully_approved' WHERE student_id = ? AND status = 'pending_finance_approval' AND finance_cleared = 0");
                        $stmt->execute([currentUserId(), $student_id]);
                        
                        if ($stmt->rowCount() > 0) {
                            // Automatically register payment as income if payment information exists
                            if ($registration_details && isset($registration_details['payment_amount']) && $registration_details['payment_amount'] > 0) {
                                $description = "School fees payment for " . $registration_details['course_name'];
                                $amount = $registration_details['payment_amount'];
                                $student_user_id = $registration_details['student_user_id'];
                                $student_email = $registration_details['student_email'] ?? '';
                                $student_number = $registration_details['student_number'] ?? '';
                                $programme_name = $registration_details['programme_name'] ?? '';
                                $intake_name = $registration_details['intake_name'] ?? '';
                                
                                // Insert into finance_transactions table
                                $insert_stmt = $pdo->prepare("INSERT INTO finance_transactions (student_user_id, type, amount, description, created_at, party_type, party_name) VALUES (?, 'income', ?, ?, NOW(), 'Student', ?)");
                                $insert_stmt->execute([$student_user_id, $amount, $description, $registration_details['full_name']]);
                                
                                // Insert into registered_students table for enrollment officer notification
                                $insert_registered_stmt = $pdo->prepare("INSERT INTO registered_students (student_id, student_name, student_email, student_number, programme_name, intake_name, payment_amount, registration_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'course', 'pending_notification')");
                                $insert_registered_stmt->execute([
                                    $student_user_id,
                                    $registration_details['full_name'],
                                    $student_email,
                                    $student_number,
                                    $programme_name,
                                    $intake_name,
                                    $amount
                                ]);
                            }
                            
                            $message = "Course registration cleared successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "No pending course registrations found for this student!";
                            $messageType = 'error';
                        }
                    } else {
                        // Fetch first-time registration details to get payment information
                        $stmt = $pdo->prepare("
                            SELECT ps.*, u.email as student_email, p.name as programme_name, i.name as intake_name
                            FROM pending_students ps
                            LEFT JOIN users u ON ps.email = u.email
                            LEFT JOIN programme p ON ps.programme_id = p.id
                            LEFT JOIN intake i ON ps.intake_id = i.id
                            WHERE ps.id = ? AND ps.registration_status = 'approved_academic' AND ps.finance_cleared = 0
                            LIMIT 1
                        ");
                        $stmt->execute([$student_id]);
                        $registration_details = $stmt->fetch();
                        
                        // Mark first-time registration as cleared for finance
                        $stmt = $pdo->prepare("UPDATE pending_students SET finance_cleared = 1, finance_cleared_at = NOW(), finance_cleared_by = ?, registration_status = 'fully_approved' WHERE id = ? AND registration_status = 'approved_academic' AND finance_cleared = 0");
                        $stmt->execute([currentUserId(), $student_id]);
                        
                        if ($stmt->rowCount() > 0) {
                            // Automatically register payment as income if payment information exists
                            if ($registration_details && isset($registration_details['payment_amount']) && $registration_details['payment_amount'] > 0) {
                                $description = "School fees payment for first-time registration";
                                $amount = $registration_details['payment_amount'];
                                $student_email = $registration_details['student_email'] ?? $registration_details['email'] ?? '';
                                $programme_name = $registration_details['programme_name'] ?? '';
                                $intake_name = $registration_details['intake_name'] ?? '';
                                $student_full_name = $registration_details['full_name'] ?? '';
                                
                                // Insert into finance_transactions table
                                $insert_stmt = $pdo->prepare("INSERT INTO finance_transactions (type, amount, description, created_at, party_type, party_name) VALUES ('income', ?, ?, NOW(), 'Student', ?)");
                                $insert_stmt->execute([$amount, $description, $student_full_name]);
                                
                                // Insert into registered_students table for enrollment officer notification
                                $insert_registered_stmt = $pdo->prepare("INSERT INTO registered_students (student_id, student_name, student_email, programme_name, intake_name, payment_amount, registration_type, status) VALUES (?, ?, ?, ?, ?, ?, 'first_time', 'pending_notification')");
                                $insert_registered_stmt->execute([
                                    0, // No student_id yet for first-time registrations
                                    $student_full_name,
                                    $student_email,
                                    $programme_name,
                                    $intake_name,
                                    $amount
                                ]);
                            }
                            
                            $message = "First-time registration cleared successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "No pending first-time registrations found for this student!";
                            $messageType = 'error';
                        }
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'suspend_registration':
                $student_id = $_POST['student_id'];
                $registration_type = $_POST['registration_type']; // 'course' or 'first_time'
                $reason = trim($_POST['reason']);
                
                if (empty($reason)) {
                    $message = "Please provide a reason for suspension!";
                    $messageType = 'error';
                } else {
                    try {
                        if ($registration_type === 'course') {
                            // Suspend course registration
                            $stmt = $pdo->prepare("UPDATE course_registration SET status = 'rejected', rejection_reason = ?, finance_cleared = -1 WHERE student_id = ? AND status = 'pending_finance_approval' AND finance_cleared = 0");
                            $stmt->execute([$reason, $student_id]);
                            
                            if ($stmt->rowCount() > 0) {
                                $message = "Course registration suspended successfully!";
                                $messageType = 'success';
                            } else {
                                $message = "No pending course registrations found for this student!";
                                $messageType = 'error';
                            }
                        } else {
                            // Suspend first-time registration
                            $stmt = $pdo->prepare("UPDATE pending_students SET registration_status = 'rejected', rejection_reason = ?, finance_cleared = -1 WHERE id = ? AND registration_status = 'approved_academic' AND finance_cleared = 0");
                            $stmt->execute([$reason, $student_id]);
                            
                            if ($stmt->rowCount() > 0) {
                                $message = "First-time registration suspended successfully!";
                                $messageType = 'success';
                            } else {
                                $message = "No pending first-time registrations found for this student!";
                                $messageType = 'error';
                            }
                        }
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
        }
    }
} else if (isset($_GET['action']) && $_GET['action'] === 'view_details' && isset($_GET['student_id']) && isset($_GET['registration_type'])) {
    // Handle view details request
    $student_id = $_GET['student_id'];
    $registration_type = $_GET['registration_type'];
    
    try {
        if ($registration_type === 'course') {
            // Fetch course registration details
            $stmt = $pdo->prepare("
                SELECT cr.*, sp.full_name, sp.student_number, sp.contact, c.name as course_name, i.name as intake_name, p.name as programme_name
                FROM course_registration cr
                JOIN student_profile sp ON cr.student_id = sp.user_id
                JOIN course c ON cr.course_id = c.id
                JOIN intake i ON sp.intake_id = i.id
                LEFT JOIN programme p ON sp.programme_id = p.id
                WHERE cr.student_id = ? AND cr.status = 'approved_academic'
                LIMIT 1
            ");
            $stmt->execute([$student_id]);
            $student_details = $stmt->fetch();
        } else {
            // Fetch first-time registration details
            $stmt = $pdo->prepare("
                SELECT ps.*, p.name as programme_name, i.name as intake_name
                FROM pending_students ps
                LEFT JOIN programme p ON ps.programme_id = p.id
                LEFT JOIN intake i ON ps.intake_id = i.id
                WHERE ps.id = ? AND ps.registration_status = 'approved'
                LIMIT 1
            ");
            $stmt->execute([$student_id]);
            $student_details = $stmt->fetch();
        }
    } catch (Exception $e) {
        $student_details = null;
        error_log("Error fetching student details: " . $e->getMessage());
    }
}

// Ensure finance_cleared columns exist in both tables
try {
    // Add finance clearance columns to course_registration table if they don't exist
    $pdo->exec("ALTER TABLE course_registration ADD COLUMN IF NOT EXISTS finance_cleared TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE course_registration ADD COLUMN IF NOT EXISTS finance_cleared_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE course_registration ADD COLUMN IF NOT EXISTS finance_cleared_by INT NULL");
    
    // Add payment-related columns to course_registration table if they don't exist
    $pdo->exec("ALTER TABLE course_registration ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50)");
    $pdo->exec("ALTER TABLE course_registration ADD COLUMN IF NOT EXISTS payment_amount DECIMAL(10,2)");
    $pdo->exec("ALTER TABLE course_registration ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100)");
    $pdo->exec("ALTER TABLE course_registration ADD COLUMN IF NOT EXISTS payment_proof VARCHAR(255)");
    
    // Add finance clearance columns to pending_students table if they don't exist
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS finance_cleared TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS finance_cleared_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS finance_cleared_by INT NULL");
    
    // Add registration_status column to pending_students if it doesn't exist
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS registration_status ENUM('pending','pending_approval','approved','rejected') DEFAULT 'pending'");
    
    // Add student_number column to pending_students if it doesn't exist
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS student_number VARCHAR(50)");
    
    // Add payment-related columns to pending_students if they don't exist
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50)");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS payment_amount DECIMAL(10,2)");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100)");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS payment_proof VARCHAR(255)");
} catch (Exception $e) {
    // Columns might already exist or there might be a permissions issue
    error_log("Error adding finance clearance columns: " . $e->getMessage());
}

// Get approved course registrations that are not yet cleared by finance
try {
    $course_registrations_query = "
        SELECT cr.*, sp.full_name, sp.student_number, c.name as course_name, i.name as intake_name, p.name as programme_name
        FROM course_registration cr
        JOIN student_profile sp ON cr.student_id = sp.user_id
        JOIN course c ON cr.course_id = c.id
        JOIN intake i ON sp.intake_id = i.id
        LEFT JOIN programme p ON sp.programme_id = p.id
        WHERE cr.status = 'approved' AND cr.finance_cleared = 0
        ORDER BY cr.submitted_at DESC
    ";
    $course_registrations = $pdo->query($course_registrations_query)->fetchAll();
} catch (Exception $e) {
    $course_registrations = [];
    error_log("Error fetching course registrations: " . $e->getMessage());
}

// Get approved first-time registrations that are not yet cleared by finance
try {
    $first_time_registrations_query = "
        SELECT ps.*, p.name as programme_name, i.name as intake_name
        FROM pending_students ps
        LEFT JOIN programme p ON ps.programme_id = p.id
        LEFT JOIN intake i ON ps.intake_id = i.id
        WHERE ps.registration_status = 'approved' AND ps.finance_cleared = 0
        ORDER BY ps.updated_at DESC
    ";
    $first_time_registrations = $pdo->query($first_time_registrations_query)->fetchAll();
} catch (Exception $e) {
    $first_time_registrations = [];
    error_log("Error fetching first-time registrations: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Clearance - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Custom button styles for blue and red buttons */
        .btn-blue {
            background: #007bff;
            color: white;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-blue:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        
        .btn-red {
            background: #dc3545;
            color: white;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-red:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
        
        /* Modal styles for viewing details */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        .payment-proof {
            max-width: 100%;
            height: auto;
            margin-top: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
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
                <a href="manage_fees.php" class="nav-item">
                    <i class="fas fa-money-bill"></i>
                    <span>Manage Fees & Finances</span>
                </a>
                <a href="manage_programme_fees.php" class="nav-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Programme Fees</span>
                </a>
                <a href="results_access.php" class="nav-item">
                    <i class="fas fa-lock"></i>
                    <span>Manage Results Access</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Financial Operations</h4>
                <a href="manage_programme_fees.php" class="nav-item">
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
                <a href="registration_clearance.php" class="nav-item active">
                    <i class="fas fa-user-check"></i>
                    <span>Registration Clearance</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-user-check"></i> Registration Clearance</h1>
            <p>Clear student accounts for course registrations and first-time registrations</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($student_details)): ?>
            <!-- Student Details Modal -->
            <div class="modal" style="display: block;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Student Registration Details</h2>
                        <span class="close" onclick="window.location.href='?'">&times;</span>
                    </div>
                    <div class="modal-body">
                        <?php if ($student_details): ?>
                            <div class="student-details">
                                <h3>Student Information</h3>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($student_details['full_name']); ?></p>
                                <p><strong><?php echo isset($student_details['student_number']) ? 'Student ID' : 'Email'; ?>:</strong> 
                                   <?php echo htmlspecialchars($student_details['student_number'] ?? $student_details['email']); ?></p>
                                
                                <?php if (isset($student_details['contact'])): ?>
                                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($student_details['contact']); ?></p>
                                <?php endif; ?>
                                
                                <h3>Registration Information</h3>
                                <p><strong>Programme:</strong> <?php echo htmlspecialchars($student_details['programme_name'] ?? 'N/A'); ?></p>
                                <p><strong>Intake:</strong> <?php echo htmlspecialchars($student_details['intake_name'] ?? 'N/A'); ?></p>
                                
                                <?php if (isset($student_details['course_name'])): ?>
                                    <p><strong>Course:</strong> <?php echo htmlspecialchars($student_details['course_name']); ?></p>
                                    <p><strong>Term:</strong> <?php echo htmlspecialchars($student_details['term']); ?></p>
                                <?php endif; ?>
                                
                                <h3>Payment Information</h3>
                                <?php if (isset($student_details['payment_method']) && $student_details['payment_method']): ?>
                                    <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($student_details['payment_method']); ?></p>
                                    <p><strong>Amount:</strong> K<?php echo number_format($student_details['payment_amount'] ?? 0, 2); ?></p>
                                    <?php if (!empty($student_details['transaction_id'])): ?>
                                        <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($student_details['transaction_id']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($student_details['payment_proof'])): ?>
                                        <p><strong>Payment Proof:</strong></p>
                                        <?php if (file_exists('../' . $student_details['payment_proof'])): ?>
                                            <img src="../<?php echo htmlspecialchars($student_details['payment_proof']); ?>" alt="Payment Proof" class="payment-proof">
                                        <?php else: ?>
                                            <p>Payment proof file not found.</p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p>No payment proof uploaded.</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p>Payment information not available.</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p>Unable to fetch student details.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-item active" onclick="showTab('course-registrations')">Course Registrations</button>
            <button class="tab-item" onclick="showTab('first-time-registrations')">First-Time Registrations</button>
        </div>
        
        <!-- Course Registrations Tab -->
        <div id="course-registrations" class="tab-content">
            <div class="table-section">
                <div class="table-card">
                    <div class="table-header">
                        <h2><i class="fas fa-clipboard-check"></i> Approved Course Registrations</h2>
                        <p>Pending finance clearance</p>
                    </div>
                    
                    <?php if (empty($course_registrations)): ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-check"></i>
                            <h3>No Pending Course Registrations</h3>
                            <p>All course registrations have been cleared by finance.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Programme</th>
                                        <th>Course</th>
                                        <th>Intake</th>
                                        <th>Term</th>
                                        <th>Submitted At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($course_registrations as $registration): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($registration['student_number']); ?></td>
                                            <td><?php echo htmlspecialchars($registration['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($registration['programme_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($registration['course_name']); ?></td>
                                            <td><?php echo htmlspecialchars($registration['intake_name']); ?></td>
                                            <td><?php echo htmlspecialchars($registration['term']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($registration['submitted_at'])); ?></td>
                                            <td>
                                                <!-- View Button -->
                                                <button class="btn btn-blue btn-sm" title="View Details" onclick="viewRegistrationDetails(<?php echo $registration['student_id']; ?>, 'course')">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                
                                                <!-- Clear Button -->
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="clear_registration">
                                                    <input type="hidden" name="student_id" value="<?php echo $registration['student_id']; ?>">
                                                    <input type="hidden" name="registration_type" value="course">
                                                    <button type="submit" class="btn btn-green btn-sm" title="Clear Registration">
                                                        <i class="fas fa-check"></i> Clear
                                                    </button>
                                                </form>
                                                
                                                <!-- Suspend Button -->
                                                <button class="btn btn-red btn-sm" title="Suspend Registration" onclick="suspendRegistration(<?php echo $registration['student_id']; ?>, 'course')">
                                                    <i class="fas fa-ban"></i> Suspend
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- First-Time Registrations Tab -->
        <div id="first-time-registrations" class="tab-content" style="display: none;">
            <div class="table-section">
                <div class="table-card">
                    <div class="table-header">
                        <h2><i class="fas fa-user-graduate"></i> Approved First-Time Registrations</h2>
                        <p>Pending finance clearance</p>
                    </div>
                    
                    <?php if (empty($first_time_registrations)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-graduate"></i>
                            <h3>No Pending First-Time Registrations</h3>
                            <p>All first-time registrations have been cleared by finance.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Email</th>
                                        <th>Programme</th>
                                        <th>Intake</th>
                                        <th>Student Number</th>
                                        <th>Approved At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($first_time_registrations as $registration): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($registration['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($registration['email']); ?></td>
                                            <td><?php echo htmlspecialchars($registration['programme_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($registration['intake_name']); ?></td>
                                            <td><?php echo htmlspecialchars($registration['student_number'] ?? 'Not Assigned'); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($registration['updated_at'])); ?></td>
                                            <td>
                                                <!-- View Button -->
                                                <button class="btn btn-blue btn-sm" title="View Details" onclick="viewRegistrationDetails(<?php echo $registration['id']; ?>, 'first_time')">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                
                                                <!-- Clear Button -->
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="clear_registration">
                                                    <input type="hidden" name="student_id" value="<?php echo $registration['id']; ?>">
                                                    <input type="hidden" name="registration_type" value="first_time">
                                                    <button type="submit" class="btn btn-green btn-sm" title="Clear Registration">
                                                        <i class="fas fa-check"></i> Clear
                                                    </button>
                                                </form>
                                                
                                                <!-- Suspend Button -->
                                                <button class="btn btn-red btn-sm" title="Suspend Registration" onclick="suspendRegistration(<?php echo $registration['id']; ?>, 'first_time')">
                                                    <i class="fas fa-ban"></i> Suspend
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        function showTab(tabId) {
            // Hide all tab contents
            document.getElementById('course-registrations').style.display = 'none';
            document.getElementById('first-time-registrations').style.display = 'none';
            
            // Remove active class from all tab items
            const tabItems = document.querySelectorAll('.tab-item');
            tabItems.forEach(item => item.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabId).style.display = 'block';
            
            // Add active class to clicked tab item
            event.target.classList.add('active');
        }
        
        // Function to view registration details
        function viewRegistrationDetails(studentId, registrationType) {
            // Redirect to the same page with parameters to fetch details
            window.location.href = `?action=view_details&student_id=${studentId}&registration_type=${registrationType}`;
        }
        
        // Function to suspend a registration
        function suspendRegistration(studentId, registrationType) {
            const reason = prompt('Please provide a reason for suspending this registration:');
            if (reason !== null && reason.trim() !== '') {
                if (confirm('Are you sure you want to suspend this registration? This action cannot be undone.')) {
                    // Create a form dynamically and submit it
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'suspend_registration';
                    form.appendChild(actionInput);
                    
                    const studentIdInput = document.createElement('input');
                    studentIdInput.type = 'hidden';
                    studentIdInput.name = 'student_id';
                    studentIdInput.value = studentId;
                    form.appendChild(studentIdInput);
                    
                    const registrationTypeInput = document.createElement('input');
                    registrationTypeInput.type = 'hidden';
                    registrationTypeInput.name = 'registration_type';
                    registrationTypeInput.value = registrationType;
                    form.appendChild(registrationTypeInput);
                    
                    const reasonInput = document.createElement('input');
                    reasonInput.type = 'hidden';
                    reasonInput.name = 'reason';
                    reasonInput.value = reason.trim();
                    form.appendChild(reasonInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            } else if (reason !== null) {
                alert('Please provide a reason for suspension.');
            }
        }
        
        // Function to close modal
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                modal.remove();
            }
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    modal.remove();
                }
            });
        }
        
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
    
    <!-- Modal Container -->
    <div id="modalContainer"></div>
</body>
</html>