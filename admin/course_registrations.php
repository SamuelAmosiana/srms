<?php
session_start();
require_once '../config.php';
require_once '../auth/auth.php';

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit();
}

// Check if user has admin role or course_registrations permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('course_registrations', $pdo)) {
    header('Location: ../auth/login.php');
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT u.*, ap.full_name, ap.staff_id FROM users u LEFT JOIN admin_profile ap ON u.id = ap.user_id WHERE u.id = ?");
$stmt->execute([currentUserId()]);
$user = $stmt->fetch();

// Handle form actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve_registration':
                $registration_id = $_POST['registration_id'];
                
                try {
                    // Get registration details
                    $stmt = $pdo->prepare("SELECT cr.*, sp.full_name, sp.student_number FROM course_registration cr JOIN student_profile sp ON cr.student_id = sp.user_id WHERE cr.id = ?");
                    $stmt->execute([$registration_id]);
                    $registration = $stmt->fetch();
                    
                    if ($registration) {
                        // Update registration status to approved
                        $stmt = $pdo->prepare("UPDATE course_registration SET status = 'approved' WHERE id = ?");
                        $stmt->execute([$registration_id]);
                        
                        $message = "Registration approved successfully!";
                        $messageType = 'success';
                    } else {
                        $message = "Registration not found!";
                        $messageType = 'error';
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'reject_registration':
                $registration_id = $_POST['registration_id'];
                $rejection_reason = trim($_POST['rejection_reason']);
                
                if (empty($rejection_reason)) {
                    $message = "Please provide a rejection reason!";
                    $messageType = 'error';
                } else {
                    try {
                        // Update registration status to rejected
                        $stmt = $pdo->prepare("UPDATE course_registration SET status = 'rejected', rejection_reason = ? WHERE id = ?");
                        $stmt->execute([$rejection_reason, $registration_id]);
                        
                        $message = "Registration rejected!";
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
                
            // New action to approve pending students (first-time registrations)
            case 'approve_pending_student':
                $pending_student_id = $_POST['pending_student_id'];
                
                try {
                    // Get pending student details
                    $stmt = $pdo->prepare("SELECT * FROM pending_students WHERE id = ?");
                    $stmt->execute([$pending_student_id]);
                    $pending_student = $stmt->fetch();
                    
                    if ($pending_student) {
                        // Generate student number
                        $student_number = 'LSC' . date('Y') . str_pad($pending_student_id, 6, '0', STR_PAD_LEFT);
                        
                        // Create user account with student number as both username and password
                        $pdo->beginTransaction();
                        
                        try {
                            // Create user account - hash the student number as the password
                            $user_stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, contact, is_active) VALUES (?, ?, ?, ?, 1)");
                            $default_password = $student_number; // Use student number as default password
                            $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
                            $user_stmt->execute([
                                $student_number,
                                $hashed_password,
                                $pending_student['email'],
                                '' // Contact info not available in pending_students
                            ]);
                            
                            $user_id = $pdo->lastInsertId();
                            
                            // Assign student role
                            $role_stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Student'");
                            $role_stmt->execute();
                            $student_role = $role_stmt->fetch();
                            
                            if ($student_role) {
                                $user_role_stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                                $user_role_stmt->execute([$user_id, $student_role['id']]);
                            }
                            
                            // Create student profile
                            $profile_stmt = $pdo->prepare("INSERT INTO student_profile (user_id, full_name, student_number, programme_id, intake_id) VALUES (?, ?, ?, ?, ?)");
                            $profile_stmt->execute([
                                $user_id, 
                                $pending_student['full_name'], 
                                $student_number,
                                $pending_student['programme_id'],
                                $pending_student['intake_id']
                            ]);
                            
                            // Update pending student with student number and approved status
                            $update_stmt = $pdo->prepare("UPDATE pending_students SET student_number = ?, registration_status = 'approved' WHERE id = ?");
                            $update_stmt->execute([$student_number, $pending_student_id]);
                            
                            $pdo->commit();
                            
                            $message = "Pending student registration approved successfully! Student account created with username and password: " . $student_number;
                            $messageType = 'success';
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            throw $e;
                        }
                    } else {
                        $message = "Pending student not found!";
                        $messageType = 'error';
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            // New action to reject pending students (first-time registrations)
            case 'reject_pending_student':
                $pending_student_id = $_POST['pending_student_id'];
                $rejection_reason = trim($_POST['rejection_reason']);
                
                if (empty($rejection_reason)) {
                    $message = "Please provide a rejection reason!";
                    $messageType = 'error';
                } else {
                    try {
                        // Update registration status to rejected
                        $stmt = $pdo->prepare("UPDATE pending_students SET registration_status = 'rejected', rejection_reason = ? WHERE id = ?");
                        $stmt->execute([$rejection_reason, $pending_student_id]);
                        
                        $message = "Pending student registration rejected!";
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
            
            case 'define_intake_courses':
                $intake_id = $_POST['intake_id'];
                $term = trim($_POST['term']);
                $programme_id = !empty($_POST['programme_id']) ? $_POST['programme_id'] : null;
                $course_ids = $_POST['course_ids'] ?? [];
                
                if (empty($intake_id) || empty($term) || empty($course_ids)) {
                    $message = "Please fill in all required fields and select at least one course!";
                    $messageType = 'error';
                } else {
                    try {
                        $pdo->beginTransaction();
                        
                        // Insert each selected course for the intake and term
                        foreach ($course_ids as $course_id) {
                            $stmt = $pdo->prepare("INSERT INTO intake_courses (intake_id, term, course_id, programme_id) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$intake_id, $term, $course_id, $programme_id]);
                        }
                        
                        $pdo->commit();
                        
                        $message = "Courses defined successfully!";
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
        }
    }
}

// Get pending registrations (existing course registrations)
$pending_registrations_limit = isset($_GET['pending_reg_limit']) ? (int)$_GET['pending_reg_limit'] : 5;
$pending_registrations_offset = isset($_GET['pending_reg_page']) ? ((int)$_GET['pending_reg_page'] - 1) * $pending_registrations_limit : 0;

try {
    $pending_count_query = "
        SELECT COUNT(*) as count
        FROM course_registration cr
        JOIN student_profile sp ON cr.student_id = sp.user_id
        JOIN course c ON cr.course_id = c.id
        JOIN intake i ON sp.intake_id = i.id
        WHERE cr.status = 'pending'
    ";
    $pending_count_result = $pdo->query($pending_count_query);
    $pending_total = $pending_count_result->fetch()['count'];
    $pending_pages = ceil($pending_total / $pending_registrations_limit);
    
    $pending_query = "
        SELECT cr.*, sp.full_name, sp.student_number as student_id, c.name as course_name, i.name as intake_name
        FROM course_registration cr
        JOIN student_profile sp ON cr.student_id = sp.user_id
        JOIN course c ON cr.course_id = c.id
        JOIN intake i ON sp.intake_id = i.id
        WHERE cr.status = 'pending'
        ORDER BY cr.submitted_at DESC
        LIMIT $pending_registrations_limit OFFSET $pending_registrations_offset
    ";
    $pending_registrations = $pdo->query($pending_query)->fetchAll();
} catch (Exception $e) {
    $pending_registrations = [];
    $pending_total = 0;
    $pending_pages = 1;
}

// Get pending students (first-time registrations from approved applications)
$pending_students_limit = isset($_GET['pending_students_limit']) ? (int)$_GET['pending_students_limit'] : 5;
$pending_students_offset = isset($_GET['pending_students_page']) ? ((int)$_GET['pending_students_page'] - 1) * $pending_students_limit : 0;

try {
    $pending_students_count_query = "
        SELECT COUNT(*) as count
        FROM pending_students ps
        LEFT JOIN programme p ON ps.programme_id = p.id
        LEFT JOIN intake i ON ps.intake_id = i.id
        WHERE ps.registration_status = 'pending_approval'
    ";
    $pending_students_count_result = $pdo->query($pending_students_count_query);
    $pending_students_total = $pending_students_count_result->fetch()['count'];
    $pending_students_pages = ceil($pending_students_total / $pending_students_limit);
    
    $pending_students_query = "
        SELECT ps.*, p.name as programme_name, i.name as intake_name,
               CASE 
                   WHEN ps.documents IS NOT NULL AND ps.payment_method IS NULL THEN 'Approved Application'
                   WHEN ps.documents IS NULL AND ps.payment_method IS NOT NULL THEN 'First-Time Registration'
                   ELSE 'Other'
               END as source_type
        FROM pending_students ps
        LEFT JOIN programme p ON ps.programme_id = p.id
        LEFT JOIN intake i ON ps.intake_id = i.id
        WHERE ps.registration_status = 'pending_approval'
        ORDER BY ps.created_at DESC
        LIMIT $pending_students_limit OFFSET $pending_students_offset
    ";
    $pending_students = $pdo->query($pending_students_query)->fetchAll();
} catch (Exception $e) {
    $pending_students = [];
    $pending_students_total = 0;
    $pending_students_pages = 1;
}

// Get intakes for defining courses
try {
    $intakes = $pdo->query("SELECT * FROM intake ORDER BY start_date DESC")->fetchAll();
} catch (Exception $e) {
    $intakes = [];
}

// Get all programmes for the dropdown
try {
    $programmes = $pdo->query("SELECT * FROM programme ORDER BY name")->fetchAll();
} catch (Exception $e) {
    $programmes = [];
}

// Get all courses for selection (for debugging purposes)
try {
    $courses = $pdo->query("SELECT * FROM course ORDER BY name")->fetchAll();
    // Debug: Check if courses have programme_id
    $debug_course = $pdo->query("SELECT id, name, programme_id FROM course LIMIT 1")->fetch();
    if ($debug_course) {
        error_log("Course debug - ID: {$debug_course['id']}, Name: {$debug_course['name']}, Programme ID: {$debug_course['programme_id']}");
    }
} catch (Exception $e) {
    $courses = [];
    error_log("Error fetching courses: " . $e->getMessage());
}

// Get defined intake courses (for display or edit) - handle both old and new schema
$defined_courses_limit = isset($_GET['defined_courses_limit']) ? (int)$_GET['defined_courses_limit'] : 5;
$defined_courses_offset = isset($_GET['defined_courses_page']) ? ((int)$_GET['defined_courses_page'] - 1) * $defined_courses_limit : 0;

try {
    // Count total records for pagination
    $defined_courses_count_query = "
        SELECT COUNT(*) as count
        FROM intake_courses ic
        JOIN intake i ON ic.intake_id = i.id
        JOIN course c ON ic.course_id = c.id
        LEFT JOIN programme p ON ic.programme_id = p.id
    ";
    $defined_courses_count_result = $pdo->query($defined_courses_count_query);
    $defined_courses_total = $defined_courses_count_result->fetch()['count'];
    $defined_courses_pages = ceil($defined_courses_total / $defined_courses_limit);
    
    // Try to get data with programme information first
    $defined_courses_query = "
        SELECT ic.*, i.name as intake_name, c.name as course_name, c.code as course_code, p.name as programme_name
        FROM intake_courses ic
        JOIN intake i ON ic.intake_id = i.id
        JOIN course c ON ic.course_id = c.id
        LEFT JOIN programme p ON ic.programme_id = p.id
        ORDER BY i.name, ic.term, p.name, c.name
        LIMIT $defined_courses_limit OFFSET $defined_courses_offset
    ";
    $defined_courses = $pdo->query($defined_courses_query)->fetchAll();
} catch (Exception $e) {
    // Fall back to old query without programme information
    try {
        // Count total records for pagination
        $defined_courses_count_query = "
            SELECT COUNT(*) as count
            FROM intake_courses ic
            JOIN intake i ON ic.intake_id = i.id
            JOIN course c ON ic.course_id = c.id
        ";
        $defined_courses_count_result = $pdo->query($defined_courses_count_query);
        $defined_courses_total = $defined_courses_count_result->fetch()['count'];
        $defined_courses_pages = ceil($defined_courses_total / $defined_courses_limit);
        
        $defined_courses_query = "
            SELECT ic.*, i.name as intake_name, c.name as course_name, c.code as course_code, NULL as programme_name
            FROM intake_courses ic
            JOIN intake i ON ic.intake_id = i.id
            JOIN course c ON ic.course_id = c.id
            ORDER BY i.name, ic.term, c.name
            LIMIT $defined_courses_limit OFFSET $defined_courses_offset
        ";
        $defined_courses = $pdo->query($defined_courses_query)->fetchAll();
    } catch (Exception $e) {
        $defined_courses = [];
        $defined_courses_total = 0;
        $defined_courses_pages = 1;
    }
}

// Create necessary tables if not exist and update schema
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS course_registration (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        term VARCHAR(50) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        rejection_reason TEXT,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        finance_cleared TINYINT(1) DEFAULT 0,
        finance_cleared_at TIMESTAMP NULL,
        finance_cleared_by INT NULL,
        payment_method VARCHAR(50),
        payment_amount DECIMAL(10,2),
        transaction_id VARCHAR(100),
        payment_proof VARCHAR(255),
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (course_id) REFERENCES course(id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS intake_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        intake_id INT NOT NULL,
        term VARCHAR(50) NOT NULL,
        course_id INT NOT NULL,
        FOREIGN KEY (intake_id) REFERENCES intake(id),
        FOREIGN KEY (course_id) REFERENCES course(id)
    )");
    
    // Try to add programme_id column if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE intake_courses ADD COLUMN programme_id INT");
        $pdo->exec("ALTER TABLE intake_courses ADD CONSTRAINT fk_intake_courses_programme FOREIGN KEY (programme_id) REFERENCES programme(id) ON DELETE SET NULL");
    } catch (Exception $e) {
        // Column might already exist, which is fine
    }
} catch (Exception $e) {
    // Tables might already exist
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Registrations - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
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
            width: 100%;
            max-width: 500px;
            border-radius: 5px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
            flex: 1;
        }
        
        .form-group.full-width {
            flex: 1 100%;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-orange {
            background-color: #fd7e14;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .action-bar {
            margin: 20px 0;
        }
        
        .action-bar button {
            margin-right: 10px;
        }
        
        /* Student Details Modal Styles */
        .student-details .detail-row {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .student-details .detail-row strong {
            display: inline-block;
            width: 150px;
            color: #333;
        }
        
        .student-details h3 {
            color: #007bff;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
            margin-top: 25px;
        }
        
        .course-list {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        .course-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .course-item:last-child {
            border-bottom: none;
        }
        
        .course-item strong {
            color: #333;
            display: block;
        }
        
        /* Pagination Styles */
        .pagination-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding: 15px 0;
            border-top: 1px solid #eee;
        }
        
        .pagination-info {
            color: #666;
            font-size: 14px;
        }
        
        .pagination {
            display: flex;
            gap: 5px;
        }
        
        .page-link {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background-color: #fff;
            color: #007bff;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .page-link:hover {
            background-color: #e9ecef;
            border-color: #adb5bd;
        }
        
        .page-link.active {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
        }
        
        .page-link:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($user['full_name'] ?? 'Administrator'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($user['staff_id'] ?? 'N/A'); ?>)</span>
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
            <h3><i class="fas fa-tachometer-alt"></i> Admin Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>User Management</h4>
                <a href="manage_users.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Manage Users</span>
                </a>
                <a href="manage_roles.php" class="nav-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Roles & Permissions</span>
                </a>
                <a href="upload_users.php" class="nav-item">
                    <i class="fas fa-upload"></i>
                    <span>Bulk Upload</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Academic Structure</h4>
                <a href="manage_schools.php" class="nav-item">
                    <i class="fas fa-university"></i>
                    <span>Schools</span>
                </a>
                <a href="manage_departments.php" class="nav-item">
                    <i class="fas fa-building"></i>
                    <span>Departments</span>
                </a>
                <a href="manage_programmes.php" class="nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Programmes</span>
                </a>
                <a href="manage_courses.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <span>Courses</span>
                </a>
                <a href="manage_intakes.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Intakes</span>
                </a>
                <a href="manage_sessions.php" class="nav-item">
                    <i class="fas fa-clock"></i>
                    <span>Sessions</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Academic Operations</h4>
                <a href="manage_results.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Results Management</span>
                </a>
                <a href="enrollment_approvals.php" class="nav-item">
                    <i class="fas fa-user-check"></i>
                    <span>Enrollment Approvals</span>
                </a>
                <a href="course_registrations.php" class="nav-item active">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Course Registrations</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports & Analytics</h4>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <a href="analytics.php" class="nav-item">
                    <i class="fas fa-analytics"></i>
                    <span>Analytics</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-clipboard-check"></i> Course Registrations</h1>
            <p>Approve student course registrations and define required courses per intake and term</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Pending Registrations -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-clock"></i> Pending Registrations (<?php echo $pending_total; ?>)</h3>
                <div class="panel-actions">
                    <label for="pending_reg_limit">Show:</label>
                    <select id="pending_reg_limit" name="pending_reg_limit" onchange="updatePendingRegPage()" style="margin-right: 10px;">
                        <option value="5" <?php echo $pending_registrations_limit == 5 ? 'selected' : ''; ?>>5</option>
                        <option value="10" <?php echo $pending_registrations_limit == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $pending_registrations_limit == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $pending_registrations_limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $pending_registrations_limit == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
            </div>
            <div class="panel-content">
                <?php if (empty($pending_registrations)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clock"></i>
                        <p>No pending registrations</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Course</th>
                                    <th>Intake</th>
                                    <th>Term</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_registrations as $reg): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reg['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['intake_name']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['term']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($reg['submitted_at'])); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Approve this registration?');">
                                                <input type="hidden" name="action" value="approve_registration">
                                                <input type="hidden" name="registration_id" value="<?php echo $reg['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Approve</button>
                                            </form>
                                            <button class="btn btn-sm btn-danger" onclick="openRejectModal(<?php echo $reg['id']; ?>)"><i class="fas fa-times"></i> Reject</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Controls -->
                    <div class="pagination-controls">
                        <div class="pagination-info">
                            Showing <?php echo min($pending_registrations_offset + 1, $pending_total); ?> to <?php echo min($pending_registrations_offset + $pending_registrations_limit, $pending_total); ?> of <?php echo $pending_total; ?> entries
                        </div>
                        <div class="pagination">
                            <?php if ($pending_pages > 1): ?>
                                <?php if (($_GET['pending_reg_page'] ?? 1) > 1): ?>
                                    <a href="?pending_reg_page=<?php echo (int)($_GET['pending_reg_page'] ?? 1) - 1; ?>&pending_reg_limit=<?php echo (int)$pending_registrations_limit; ?>" class="page-link">Previous</a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $pending_pages; $i++): ?>
                                    <a href="?pending_reg_page=<?php echo (int)$i; ?>&pending_reg_limit=<?php echo (int)$pending_registrations_limit; ?>" class="page-link <?php echo ($i == ((int)($_GET['pending_reg_page'] ?? 1))) ? 'active' : ''; ?>"><?php echo (int)$i; ?></a>
                                <?php endfor; ?>
                                
                                <?php if (((int)($_GET['pending_reg_page'] ?? 1)) < $pending_pages): ?>
                                    <a href="?pending_reg_page=<?php echo (int)($_GET['pending_reg_page'] ?? 1) + 1; ?>&pending_reg_limit=<?php echo (int)$pending_registrations_limit; ?>" class="page-link">Next</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Students (First-Time Registrations) -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-user-graduate"></i> Pending Students (<?php echo $pending_students_total; ?>)</h3>
                <div class="panel-actions">
                    <label for="pending_students_limit">Show:</label>
                    <select id="pending_students_limit" name="pending_students_limit" onchange="updatePendingStudentsPage()" style="margin-right: 10px;">
                        <option value="5" <?php echo $pending_students_limit == 5 ? 'selected' : ''; ?>>5</option>
                        <option value="10" <?php echo $pending_students_limit == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $pending_students_limit == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $pending_students_limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $pending_students_limit == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
            </div>
            <div class="panel-content">
                <?php if (empty($pending_students)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-graduate"></i>
                        <p>No pending students</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Programme</th>
                                    <th>Intake</th>
                                    <th>Payment Method</th>
                                    <th>Amount</th>
                                    <th>Source</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['programme_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['intake_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['payment_method'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['payment_amount'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['source_type'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($student['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewStudentDetails(<?php echo $student['id']; ?>)">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Approve this student registration?');">
                                                <input type="hidden" name="action" value="approve_pending_student">
                                                <input type="hidden" name="pending_student_id" value="<?php echo $student['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Approve</button>
                                            </form>
                                            <button class="btn btn-sm btn-danger" onclick="openStudentRejectModal(<?php echo $student['id']; ?>)"><i class="fas fa-times"></i> Reject</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Controls -->
                    <div class="pagination-controls">
                        <div class="pagination-info">
                            Showing <?php echo min($pending_students_offset + 1, $pending_students_total); ?> to <?php echo min($pending_students_offset + $pending_students_limit, $pending_students_total); ?> of <?php echo $pending_students_total; ?> entries
                        </div>
                        <div class="pagination">
                            <?php if ($pending_students_pages > 1): ?>
                                <?php if (($_GET['pending_students_page'] ?? 1) > 1): ?>
                                    <a href="?pending_students_page=<?php echo (int)($_GET['pending_students_page'] ?? 1) - 1; ?>&pending_students_limit=<?php echo (int)$pending_students_limit; ?>" class="page-link">Previous</a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $pending_students_pages; $i++): ?>
                                    <a href="?pending_students_page=<?php echo (int)$i; ?>&pending_students_limit=<?php echo (int)$pending_students_limit; ?>" class="page-link <?php echo ($i == ((int)($_GET['pending_students_page'] ?? 1))) ? 'active' : ''; ?>"><?php echo (int)$i; ?></a>
                                <?php endfor; ?>
                                
                                <?php if (((int)($_GET['pending_students_page'] ?? 1)) < $pending_students_pages): ?>
                                    <a href="?pending_students_page=<?php echo (int)($_GET['pending_students_page'] ?? 1) + 1; ?>&pending_students_limit=<?php echo (int)$pending_students_limit; ?>" class="page-link">Next</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Define Intake Courses -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-list"></i> Define Required Courses per Intake and Term</h3>
            </div>
            <div class="panel-content">
                <form method="POST">
                    <input type="hidden" name="action" value="define_intake_courses">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="intake_id">Intake *</label>
                            <select id="intake_id" name="intake_id" required>
                                <option value="">-- Select Intake --</option>
                                <?php if (!empty($intakes)): ?>
                                    <?php foreach ($intakes as $intake): ?>
                                        <option value="<?php echo $intake['id']; ?>"><?php echo htmlspecialchars($intake['name']); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="term">Term *</label>
                            <input type="text" id="term" name="term" required placeholder="e.g., Semester 1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="programme_id">Programme</label>
                        <select id="programme_id" name="programme_id">
                            <option value="">-- Select Programme --</option>
                            <?php if (!empty($programmes)): ?>
                                <?php foreach ($programmes as $programme): ?>
                                    <option value="<?php echo $programme['id']; ?>"><?php echo htmlspecialchars($programme['name']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="course_ids">Courses *</label>
                        <select id="course_ids" name="course_ids[]" multiple required size="6">
                            <option value="">-- Select Courses --</option>
                            <!-- Courses will be loaded based on selected programme -->
                        </select>
                        <small class="form-text">Hold Ctrl/Cmd to select multiple courses</small>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Courses</button>
                </form>
            </div>
        </div>

        <!-- Defined Courses -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-check-circle"></i> Defined Courses (<?php echo $defined_courses_total; ?>)</h3>
                <div class="panel-actions">
                    <label for="defined_courses_limit">Show:</label>
                    <select id="defined_courses_limit" name="defined_courses_limit" onchange="updateDefinedCoursesPage()" style="margin-right: 10px;">
                        <option value="5" <?php echo $defined_courses_limit == 5 ? 'selected' : ''; ?>>5</option>
                        <option value="10" <?php echo $defined_courses_limit == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $defined_courses_limit == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $defined_courses_limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $defined_courses_limit == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                    <button class="btn btn-primary" onclick="document.getElementById('addDefinedCourseModal').style.display='block'">
                        <i class="fas fa-plus"></i> Add Defined Course
                    </button>
                </div>
            </div>
            <div class="panel-content">
                <?php if (empty($defined_courses)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No courses defined</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Intake</th>
                                    <th>Term</th>
                                    <th>Programme</th>
                                    <th>Course</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($defined_courses as $dc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dc['intake_name']); ?></td>
                                        <td><?php echo htmlspecialchars($dc['term']); ?></td>
                                        <td><?php echo htmlspecialchars($dc['programme_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($dc['course_name'] . ' (' . $dc['course_code'] . ')'); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-orange" onclick="editDefinedCourse(<?php echo $dc['id']; ?>, <?php echo $dc['intake_id']; ?>, '<?php echo htmlspecialchars($dc['term']); ?>', <?php echo $dc['programme_id'] ?? 'null'; ?>, <?php echo $dc['course_id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteDefinedCourse(<?php echo $dc['id']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Controls -->
                    <div class="pagination-controls">
                        <div class="pagination-info">
                            Showing <?php echo min($defined_courses_offset + 1, $defined_courses_total); ?> to <?php echo min($defined_courses_offset + $defined_courses_limit, $defined_courses_total); ?> of <?php echo $defined_courses_total; ?> entries
                        </div>
                        <div class="pagination">
                            <?php if ($defined_courses_pages > 1): ?>
                                <?php if (($_GET['defined_courses_page'] ?? 1) > 1): ?>
                                    <a href="?defined_courses_page=<?php echo (int)($_GET['defined_courses_page'] ?? 1) - 1; ?>&defined_courses_limit=<?php echo (int)$defined_courses_limit; ?>" class="page-link">Previous</a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $defined_courses_pages; $i++): ?>
                                    <a href="?defined_courses_page=<?php echo (int)$i; ?>&defined_courses_limit=<?php echo (int)$defined_courses_limit; ?>" class="page-link <?php echo ($i == ((int)($_GET['defined_courses_page'] ?? 1))) ? 'active' : ''; ?>"><?php echo (int)$i; ?></a>
                                <?php endfor; ?>
                                
                                <?php if (((int)($_GET['defined_courses_page'] ?? 1)) < $defined_courses_pages): ?>
                                    <a href="?defined_courses_page=<?php echo (int)($_GET['defined_courses_page'] ?? 1) + 1; ?>&defined_courses_limit=<?php echo (int)$defined_courses_limit; ?>" class="page-link">Next</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('rejectModal')">&times;</span>
            <h2>Reject Registration</h2>
            <form method="POST">
                <input type="hidden" name="action" value="reject_registration">
                <input type="hidden" id="reject_registration_id" name="registration_id">
                <div class="form-group">
                    <label for="rejection_reason">Rejection Reason</label>
                    <textarea id="rejection_reason" name="rejection_reason" rows="3" required></textarea>
                </div>
                <button type="submit" class="btn btn-danger">Reject</button>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            <h2>Confirm Delete</h2>
            <p>Are you sure you want to delete this defined course?</p>
            <form method="POST">
                <input type="hidden" name="action" value="delete_defined_course">
                <input type="hidden" id="delete_defined_course_id" name="defined_course_id">
                <button type="submit" class="btn btn-danger">Yes, Delete</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h2>Edit Defined Course</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_defined_course">
                <input type="hidden" id="edit_defined_course_id" name="defined_course_id">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_intake_id">Intake *</label>
                        <select id="edit_intake_id" name="edit_intake_id" required>
                            <option value="">-- Select Intake --</option>
                            <?php if (!empty($intakes)): ?>
                                <?php foreach ($intakes as $intake): ?>
                                    <option value="<?php echo $intake['id']; ?>"><?php echo htmlspecialchars($intake['name']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_term">Term *</label>
                        <input type="text" id="edit_term" name="edit_term" required placeholder="e.g., Semester 1">
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_programme_id">Programme</label>
                    <select id="edit_programme_id" name="edit_programme_id">
                        <option value="">-- Select Programme --</option>
                        <?php if (!empty($programmes)): ?>
                            <?php foreach ($programmes as $programme): ?>
                                <option value="<?php echo $programme['id']; ?>"><?php echo htmlspecialchars($programme['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_course_id">Course *</label>
                    <select id="edit_course_id" name="edit_course_id" required>
                        <option value="">-- Select Course --</option>
                        <?php if (!empty($courses)): ?>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name'] . ' (' . $course['code'] . ')'); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Update Course</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Add Defined Course Modal -->
    <div id="addDefinedCourseModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addDefinedCourseModal')">&times;</span>
            <h2>Add Defined Course</h2>
            <form method="POST">
                <input type="hidden" name="action" value="define_intake_courses">
                <div class="form-row">
                    <div class="form-group">
                        <label for="add_intake_id">Intake *</label>
                        <select id="add_intake_id" name="intake_id" required>
                            <option value="">-- Select Intake --</option>
                            <?php if (!empty($intakes)): ?>
                                <?php foreach ($intakes as $intake): ?>
                                    <option value="<?php echo $intake['id']; ?>"><?php echo htmlspecialchars($intake['name']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_term">Term *</label>
                        <input type="text" id="add_term" name="term" required placeholder="e.g., Semester 1">
                    </div>
                </div>
                <div class="form-group">
                    <label for="add_programme_id">Programme</label>
                    <select id="add_programme_id" name="programme_id">
                        <option value="">-- Select Programme --</option>
                        <?php if (!empty($programmes)): ?>
                            <?php foreach ($programmes as $programme): ?>
                                <option value="<?php echo $programme['id']; ?>"><?php echo htmlspecialchars($programme['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="add_course_ids">Courses *</label>
                    <select id="add_course_ids" name="course_ids[]" multiple required size="6">
                        <option value="">-- Select Courses --</option>
                        <!-- Courses will be loaded based on selected programme -->
                    </select>
                    <small class="form-text">Hold Ctrl/Cmd to select multiple courses</small>
                </div>
                <button type="submit" class="btn btn-primary">Add Course</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('addDefinedCourseModal')">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Student Reject Modal -->
    <div id="studentRejectModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('studentRejectModal')">&times;</span>
            <h2>Reject Student Registration</h2>
            <form method="POST">
                <input type="hidden" name="action" value="reject_pending_student">
                <input type="hidden" id="reject_student_id" name="pending_student_id">
                <div class="form-group">
                    <label for="student_rejection_reason">Rejection Reason</label>
                    <textarea id="student_rejection_reason" name="rejection_reason" rows="3" required></textarea>
                </div>
                <button type="submit" class="btn btn-danger">Reject</button>
            </form>
        </div>
    </div>
    
    <!-- Student Details Modal -->
    <div id="studentDetailsModal" class="modal">
        <div class="modal-content" style="max-width: 800px; max-height: 80vh; overflow-y: auto;">
            <span class="close" onclick="closeModal('studentDetailsModal')">&times;</span>
            <h2>Student Registration Details</h2>
            <div id="studentDetailsContent">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        function openRejectModal(id) {
            document.getElementById('reject_registration_id').value = id;
            document.getElementById('rejectModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
        }
        
        function deleteDefinedCourse(id) {
            document.getElementById('delete_defined_course_id').value = id;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function editDefinedCourse(id, intakeId, term, programmeId, courseId) {
            document.getElementById('edit_defined_course_id').value = id;
            document.getElementById('edit_intake_id').value = intakeId;
            document.getElementById('edit_term').value = term;
            document.getElementById('edit_programme_id').value = programmeId || '';
            document.getElementById('edit_course_id').value = courseId;
            document.getElementById('editModal').style.display = 'block';
        }
        
        // New functions for student rejection modal
        function openStudentRejectModal(id) {
            document.getElementById('reject_student_id').value = id;
            document.getElementById('studentRejectModal').style.display = 'block';
        }
        
        // Function to view payment proof
        function viewPaymentProof(studentId, proofPath) {
            if (proofPath) {
                // Open the payment proof in a new window/tab
                window.open('../' + proofPath, '_blank');
            } else {
                alert('No payment proof available for this student.');
            }
        }
        
        // Function to view student details
        function viewStudentDetails(studentId) {
            // Show loading message
            const contentDiv = document.getElementById('studentDetailsContent');
            contentDiv.innerHTML = '<p>Loading student details...</p>';
            document.getElementById('studentDetailsModal').style.display = 'block';
            
            // Fetch student details via AJAX
            fetch(`get_student_details.php?id=${studentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        contentDiv.innerHTML = `<p>Error: ${data.error}</p>`;
                    } else {
                        // Build the HTML for student details
                        let html = `
                            <div class="student-details">
                                <h3>Personal Information</h3>
                                <div class="detail-row">
                                    <strong>Name:</strong> ${data.full_name}
                                </div>
                                <div class="detail-row">
                                    <strong>Email:</strong> ${data.email}
                                </div>
                                <div class="detail-row">
                                    <strong>Programme:</strong> ${data.programme_name || 'N/A'}
                                </div>
                                <div class="detail-row">
                                    <strong>Intake:</strong> ${data.intake_name || 'N/A'}
                                </div>
                                
                                <h3>Payment Information</h3>
                                <div class="detail-row">
                                    <strong>Method:</strong> ${data.payment_method || 'N/A'}
                                </div>
                                <div class="detail-row">
                                    <strong>Amount:</strong> ${data.payment_amount || 'N/A'}
                                </div>
                                <div class="detail-row">
                                    <strong>Transaction ID:</strong> ${data.transaction_id || 'N/A'}
                                </div>
                                <div class="detail-row">
                                    <strong>Submitted:</strong> ${data.created_at ? new Date(data.created_at).toLocaleDateString() : 'N/A'}
                                </div>
                        `;
                        
                        if (data.payment_proof) {
                            html += `
                                <div class="detail-row">
                                    <strong>Payment Proof:</strong> 
                                    <a href="../${data.payment_proof}" target="_blank" class="btn btn-sm btn-info">
                                        <i class="fas fa-file-download"></i> View Document
                                    </a>
                                </div>
                            `;
                        }
                        
                        // Add courses information
                        html += `
                                <h3>Courses for Selected Programme (Term 1)</h3>
                        `;
                        
                        if (data.courses && data.courses.length > 0) {
                            html += '<div class="course-list">';
                            data.courses.forEach(course => {
                                html += `
                                    <div class="course-item">
                                        <strong>${course.course_name}</strong>
                                        <div>${course.course_code} - ${course.credits} Credits</div>
                                    </div>
                                `;
                            });
                            html += '</div>';
                        } else {
                            html += '<p>No courses defined for this programme yet.</p>';
                        }
                        
                        html += '</div>';
                        contentDiv.innerHTML = html;
                    }
                })
                .catch(error => {
                    contentDiv.innerHTML = `<p>Error loading student details: ${error.message}</p>`;
                });
        }
        
        // Pagination functions
        function updatePendingRegPage() {
            const limit = document.getElementById('pending_reg_limit').value;
            // Remove any existing pagination parameters and add new ones
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.delete('pending_reg_limit');
            urlParams.delete('pending_reg_page');
            urlParams.set('pending_reg_limit', limit);
            urlParams.set('pending_reg_page', 1);
            window.location.href = '?' + urlParams.toString();
        }
        
        function updatePendingStudentsPage() {
            const limit = document.getElementById('pending_students_limit').value;
            // Remove any existing pagination parameters and add new ones
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.delete('pending_students_limit');
            urlParams.delete('pending_students_page');
            urlParams.set('pending_students_limit', limit);
            urlParams.set('pending_students_page', 1);
            window.location.href = '?' + urlParams.toString();
        }
        
        function updateDefinedCoursesPage() {
            const limit = document.getElementById('defined_courses_limit').value;
            // Remove any existing pagination parameters and add new ones
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.delete('defined_courses_limit');
            urlParams.delete('defined_courses_page');
            urlParams.set('defined_courses_limit', limit);
            urlParams.set('defined_courses_page', 1);
            window.location.href = '?' + urlParams.toString();
        }
        
        // Dynamic course loading based on selected programme
        document.addEventListener('DOMContentLoaded', function() {
            const programmeSelect = document.getElementById('programme_id');
            const courseSelect = document.getElementById('course_ids');
            
            // Function to load courses based on selected programme
            function loadCoursesForProgramme(programmeId) {
                console.log('Loading courses for programme ID:', programmeId, typeof programmeId);
                
                // Convert to integer to ensure it's a proper ID, and handle potential issues
                let programmeIdInt = parseInt(programmeId);
                
                console.log('Converted programme ID:', programmeIdInt, typeof programmeIdInt);
                
                if (isNaN(programmeIdInt) || programmeIdInt <= 0) {
                    courseSelect.innerHTML = '<option value="">-- Select Courses --</option>';
                    console.log('Programme ID is invalid:', programmeId, programmeIdInt);
                    return;
                }
                
                // Show loading state
                courseSelect.innerHTML = '<option value="">Loading courses...</option>';
                
                // Make AJAX request to get courses for the selected programme
                const params = new URLSearchParams({
                    programme_id: programmeIdInt
                });
                fetch('get_courses_by_programme.php?' + params)
                    .then(response => {
                        console.log('Response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Received data:', data);
                        if (data.error) {
                            courseSelect.innerHTML = '<option value="">Error loading courses</option>';
                            console.error('Error:', data.error);
                        } else {
                            courseSelect.innerHTML = '<option value="">-- Select Courses --</option>';
                            
                            if (data.courses && data.courses.length > 0) {
                                data.courses.forEach(course => {
                                    const option = document.createElement('option');
                                    option.value = course.id;
                                    option.textContent = `${course.name} (${course.code})`;
                                    courseSelect.appendChild(option);
                                });
                            } else {
                                courseSelect.innerHTML = '<option value="">No courses available for this programme</option>';
                                console.log('No courses found for this programme');
                            }
                        }
                    })
                    .catch(error => {
                        courseSelect.innerHTML = '<option value="">Error loading courses</option>';
                        console.error('Fetch error:', error);
                    });
            }
            
            // Add event listener to programme selection
            if (programmeSelect) {
                programmeSelect.addEventListener('change', function() {
                    loadCoursesForProgramme(this.value);
                });
            }
            
            // Also add event listener to the add modal's programme selection
            const addProgrammeSelect = document.getElementById('add_programme_id');
            const addCourseSelect = document.getElementById('add_course_ids');
            
            if (addProgrammeSelect && addCourseSelect) {
                addProgrammeSelect.addEventListener('change', function() {
                    console.log('Loading courses for add modal programme ID:', this.value, typeof this.value);
                    
                    // Convert to integer to ensure it's a proper ID, and handle potential issues
                    let programmeIdInt = parseInt(this.value);
                    
                    console.log('Add modal - Converted programme ID:', programmeIdInt, typeof programmeIdInt);
                    
                    if (isNaN(programmeIdInt) || programmeIdInt <= 0) {
                        addCourseSelect.innerHTML = '<option value="">-- Select Courses --</option>';
                        console.log('Add modal - Programme ID is invalid:', this.value, programmeIdInt);
                        return;
                    }
                    
                    // Show loading state
                    addCourseSelect.innerHTML = '<option value="">Loading courses...</option>';
                    
                    // Make AJAX request to get courses for the selected programme
                    const params = new URLSearchParams({
                        programme_id: programmeIdInt
                    });
                    fetch('get_courses_by_programme.php?' + params)
                        .then(response => {
                            console.log('Add modal - Response status:', response.status);
                            return response.json();
                        })
                        .then(data => {
                            console.log('Add modal - Received data:', data);
                            if (data.error) {
                                addCourseSelect.innerHTML = '<option value="">Error loading courses</option>';
                                console.error('Error:', data.error);
                            } else {
                                addCourseSelect.innerHTML = '<option value="">-- Select Courses --</option>';
                                
                                if (data.courses && data.courses.length > 0) {
                                    data.courses.forEach(course => {
                                        const option = document.createElement('option');
                                        option.value = course.id;
                                        option.textContent = `${course.name} (${course.code})`;
                                        addCourseSelect.appendChild(option);
                                    });
                                } else {
                                    addCourseSelect.innerHTML = '<option value="">No courses available for this programme</option>';
                                    console.log('Add modal - No courses found for this programme');
                                }
                            }
                        })
                        .catch(error => {
                            addCourseSelect.innerHTML = '<option value="">Error loading courses</option>';
                            console.error('Add modal - Fetch error:', error);
                        });
                });
            }
        });

    </script>
</body>
</html>
