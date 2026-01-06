<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has admin role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Super Admin', $pdo);

// Get admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Handle AJAX requests for export
if (isset($_GET['action']) && $_GET['action'] === 'export_users') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Username', 'Full Name', 'Email', 'Role', 'Identifier', 'Status', 'Created']);
    
    // Build query with filters for export
    $export_query = "SELECT u.id as user_id, u.username, u.is_active, u.created_at, r.name as role_name,
              COALESCE(ap.full_name, sp.full_name, stp.full_name) as full_name,
              u.email,
              COALESCE(ap.staff_id, sp.student_number, stp.staff_id) as identifier
              FROM users u 
              JOIN user_roles ur ON u.id = ur.user_id
              JOIN roles r ON ur.role_id = r.id 
              LEFT JOIN admin_profile ap ON u.id = ap.user_id
              LEFT JOIN student_profile sp ON u.id = sp.user_id
              LEFT JOIN staff_profile stp ON u.id = stp.user_id
              WHERE 1=1";

    $export_params = [];

    $roleFilter = $_GET['role'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $searchQuery = $_GET['search'] ?? '';

    if ($roleFilter) {
        $export_query .= " AND r.name = ?";
        $export_params[] = $roleFilter;
    }

    if ($statusFilter !== '') {
        $export_query .= " AND u.is_active = ?";
        $export_params[] = $statusFilter;
    }

    if ($searchQuery) {
        $export_query .= " AND (u.username LIKE ? OR COALESCE(ap.full_name, sp.full_name, stp.full_name) LIKE ? OR u.email LIKE ?)";
        $searchParam = "%$searchQuery%";
        $export_params[] = $searchParam;
        $export_params[] = $searchParam;
        $export_params[] = $searchParam;
    }

    $export_query .= " ORDER BY u.created_at DESC";

    $export_stmt = $pdo->prepare($export_query);
    $export_stmt->execute($export_params);
    $export_users = $export_stmt->fetchAll();

    foreach ($export_users as $user) {
        fputcsv($output, [
            $user['user_id'],
            $user['username'],
            $user['full_name'] ?? 'N/A',
            $user['email'] ?? 'N/A',
            $user['role_name'],
            $user['identifier'] ?? 'N/A',
            $user['is_active'] ? 'Active' : 'Inactive',
            date('Y-m-d', strtotime($user['created_at']))
        ]);
    }
    fclose($output);
    exit();
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_user':
                    $pdo->beginTransaction();
                    
                    // Validate required fields
                    if (empty($_POST['username']) || empty($_POST['password']) || empty($_POST['email']) || empty($_POST['full_name']) || empty($_POST['role_id'])) {
                        throw new Exception("Required fields are missing");
                    }
                    
                    // Insert into users table
                    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, contact, is_active) VALUES (?, ?, ?, ?, 1)");
                    $result = $stmt->execute([
                        $_POST['username'],
                        password_hash($_POST['password'], PASSWORD_DEFAULT),
                        $_POST['email'],
                        $_POST['phone'] ?? null
                    ]);
                    
                    if (!$result) {
                        throw new Exception("Failed to create user");
                    }
                    
                    $userId = $pdo->lastInsertId();
                    
                    // Debug: Check if userId is valid
                    if (!$userId || !is_numeric($userId) || $userId <= 0) {
                        throw new Exception("Failed to get user ID after inserting user");
                    }
                    
                    // Insert role assignment
                    $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    $stmt->execute([$userId, $_POST['role_id']]);
                    
                    // Insert into appropriate profile table based on role
                    $roleStmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
                    $roleStmt->execute([$_POST['role_id']]);
                    $roleName = $roleStmt->fetchColumn();
                    
                    if ($roleName == 'Super Admin') {
                        $staff_id = $_POST['staff_id'] ?: 'ADM-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                        $stmt = $pdo->prepare("INSERT INTO admin_profile (user_id, full_name, staff_id) VALUES (?, ?, ?)");
                        $stmt->execute([$userId, $_POST['full_name'], $staff_id]);
                    } elseif ($roleName == 'Student') {
                        $student_number = $_POST['student_number'] ?: 'LSC' . str_pad($userId, 6, '0', STR_PAD_LEFT);
                        $stmt = $pdo->prepare("INSERT INTO student_profile (user_id, full_name, student_number, NRC, gender, programme_id, intake_id, school_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$userId, $_POST['full_name'], $student_number, $_POST['nrc'] ?? null, $_POST['gender'] ?? null, $_POST['programme_id'] ?? null, $_POST['intake_id'] ?? null, $_POST['school_id'] ?? null]);
                        
                        // Assign courses if selected
                        if (isset($_POST['course_ids']) && is_array($_POST['course_ids'])) {
                            // First, verify that the courses exist
                            $courseCheckStmt = $pdo->prepare("SELECT id FROM course WHERE id = ?");
                            foreach ($_POST['course_ids'] as $course_id) {
                                $courseCheckStmt->execute([$course_id]);
                                if ($courseCheckStmt->fetch()) {
                                    // Course exists, so we can enroll the student
                                    $course_stmt = $pdo->prepare("INSERT INTO course_enrollment (student_user_id, course_id, status) VALUES (?, ?, 'approved')");
                                    if (!$course_stmt->execute([$userId, $course_id])) {
                                        throw new Exception("Failed to enroll student in course ID: " . $course_id);
                                    }
                                }
                            }
                        }
                    } elseif ($roleName == 'Lecturer' || $roleName == 'Sub Admin (Finance)' || $roleName == 'Enrollment Officer') {
                        $staff_id = $_POST['staff_id'] ?: 'STF-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                        // Fixed: Updated the query to match the actual staff_profile table structure
                        $stmt = $pdo->prepare("INSERT INTO staff_profile (user_id, full_name, staff_id, NRC, gender, qualification) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$userId, $_POST['full_name'], $staff_id, $_POST['nrc'] ?? null, $_POST['gender'] ?? null, $_POST['qualification'] ?? null]);
                        
                        // Assign courses if selected (for lecturers)
                        if ($roleName == 'Lecturer' && isset($_POST['course_ids']) && is_array($_POST['course_ids'])) {
                            // First, verify that the courses exist
                            $courseCheckStmt = $pdo->prepare("SELECT id FROM course WHERE id = ?");
                            foreach ($_POST['course_ids'] as $course_id) {
                                $courseCheckStmt->execute([$course_id]);
                                if ($courseCheckStmt->fetch()) {
                                    // Course exists, so we can assign it to the lecturer
                                    // Use default values for academic_year and semester to ensure the record is created
                                    $course_stmt = $pdo->prepare("INSERT INTO course_assignment (course_id, lecturer_id, academic_year, semester) VALUES (?, ?, ?, ?)");
                                    $course_stmt->execute([$course_id, $userId, date('Y'), '1']);
                                }
                            }
                        }
                    }
                    
                    $pdo->commit();
                    $message = 'User created successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'update_user':
                    $pdo->beginTransaction();
                    
                    // Fixed: Properly check if user_id is set and valid before using it
                    if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
                        throw new Exception("User ID is missing or invalid");
                    }
                    
                    $userId = $_POST['user_id'];
                    
                    // Validate that userId is a valid integer
                    if (!is_numeric($userId) || $userId <= 0) {
                        throw new Exception("Invalid User ID provided");
                    }
                    
                    // Debug: Check if user exists in database
                    $userCheckStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                    $userCheckStmt->execute([$userId]);
                    if (!$userCheckStmt->fetch()) {
                        throw new Exception("User with ID " . (int)$userId . " does not exist in the database");
                    }
                    
                    // Update users table
                    if (!empty($_POST['password'])) {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, password_hash = ?, email = ?, contact = ?, is_active = ? WHERE id = ?");
                        $result = $stmt->execute([
                            $_POST['username'] ?? '',
                            password_hash($_POST['password'], PASSWORD_DEFAULT),
                            $_POST['email'] ?? '',
                            $_POST['phone'] ?? null,
                            $_POST['is_active'] ?? 1,
                            $userId
                        ]);
                        
                        if (!$result) {
                            throw new Exception("Failed to update user details");
                        }
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, contact = ?, is_active = ? WHERE id = ?");
                        $result = $stmt->execute([
                            $_POST['username'] ?? '',
                            $_POST['email'] ?? '',
                            $_POST['phone'] ?? null,
                            $_POST['is_active'] ?? 1,
                            $userId
                        ]);
                        
                        if (!$result) {
                            throw new Exception("Failed to update user details");
                        }
                    }
                    
                    // Update role assignment
                    if (isset($_POST['role_id']) && !empty($_POST['role_id'])) {
                        $stmt = $pdo->prepare("UPDATE user_roles SET role_id = ? WHERE user_id = ?");
                        $stmt->execute([$_POST['role_id'], $userId]);
                    }
                    
                    // Update profile based on role
                    if (isset($_POST['role_id']) && !empty($_POST['role_id'])) {
                        $roleStmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
                        $roleStmt->execute([$_POST['role_id']]);
                        $roleName = $roleStmt->fetchColumn();
                        
                        if ($roleName == 'Super Admin') {
                            $stmt = $pdo->prepare("UPDATE admin_profile SET full_name = ?, staff_id = ? WHERE user_id = ?");
                            $stmt->execute([$_POST['full_name'] ?? '', $_POST['staff_id'] ?? '', $userId]);
                        } elseif ($roleName == 'Student') {
                            $stmt = $pdo->prepare("UPDATE student_profile SET full_name = ?, student_number = ?, NRC = ?, gender = ?, programme_id = ?, intake_id = ?, school_id = ? WHERE user_id = ?");
                            $stmt->execute([
                                $_POST['full_name'] ?? '', 
                                $_POST['student_number'] ?? '', 
                                $_POST['nrc'] ?? null, 
                                $_POST['gender'] ?? null, 
                                $_POST['programme_id'] ?? null, 
                                $_POST['intake_id'] ?? null, 
                                $_POST['school_id'] ?? null, 
                                $userId
                            ]);
                            
                            // Update courses
                            $delete_stmt = $pdo->prepare("DELETE FROM course_enrollment WHERE student_user_id = ?");
                            $delete_stmt->execute([$userId]);
                            if (isset($_POST['course_ids']) && is_array($_POST['course_ids'])) {
                                // First, verify that the courses exist
                                $courseCheckStmt = $pdo->prepare("SELECT id FROM course WHERE id = ?");
                                foreach ($_POST['course_ids'] as $course_id) {
                                    $courseCheckStmt->execute([$course_id]);
                                    if ($courseCheckStmt->fetch()) {
                                        // Course exists, so we can enroll the student
                                        $course_stmt = $pdo->prepare("INSERT INTO course_enrollment (student_user_id, course_id, status) VALUES (?, ?, 'approved')");
                                        if (!$course_stmt->execute([$userId, $course_id])) {
                                            throw new Exception("Failed to enroll student in course ID: " . $course_id);
                                        }
                                    }
                                }
                            }
                        } elseif ($roleName == 'Lecturer' || $roleName == 'Sub Admin (Finance)' || $roleName == 'Enrollment Officer') {
                            // Fixed: Updated the query to match the actual staff_profile table structure
                            // The staff_profile table has: user_id, full_name, staff_id, NRC, gender, qualification, bio
                            // Handle Lecturer, Sub Admin (Finance), and Enrollment Officer roles the same way
                            
                            // Check if staff profile exists for this user
                            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM staff_profile WHERE user_id = ?");
                            $checkStmt->execute([$userId]);
                            $profileExists = $checkStmt->fetchColumn() > 0;
                            
                            if ($profileExists) {
                                // Update existing staff profile
                                $stmt = $pdo->prepare("UPDATE staff_profile SET full_name = ?, staff_id = ?, NRC = ?, gender = ?, qualification = ? WHERE user_id = ?");
                                $stmt->execute([
                                    $_POST['full_name'] ?? '', 
                                    $_POST['staff_id'] ?? '', 
                                    $_POST['nrc'] ?? null, 
                                    $_POST['gender'] ?? null, 
                                    $_POST['qualification'] ?? null, 
                                    $userId
                                ]);
                            } else {
                                // Insert new staff profile
                                $staff_id = $_POST['staff_id'] ?: 'STF-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                                $stmt = $pdo->prepare("INSERT INTO staff_profile (user_id, full_name, staff_id, NRC, gender, qualification) VALUES (?, ?, ?, ?, ?, ?)");
                                $stmt->execute([
                                    $userId,
                                    $_POST['full_name'] ?? '', 
                                    $staff_id,
                                    $_POST['nrc'] ?? null, 
                                    $_POST['gender'] ?? null, 
                                    $_POST['qualification'] ?? null
                                ]);
                            }
                            
                            // Update courses for lecturers
                            if ($roleName == 'Lecturer') {
                                $delete_stmt = $pdo->prepare("DELETE FROM course_assignment WHERE lecturer_id = ?");
                                $delete_stmt->execute([$userId]);
                                if (isset($_POST['course_ids']) && is_array($_POST['course_ids'])) {
                                    // First, verify that the courses exist
                                    $courseCheckStmt = $pdo->prepare("SELECT id FROM course WHERE id = ?");
                                    foreach ($_POST['course_ids'] as $course_id) {
                                        $courseCheckStmt->execute([$course_id]);
                                        if ($courseCheckStmt->fetch()) {
                                            // Course exists, so we can assign it to the lecturer
                                            $course_stmt = $pdo->prepare("INSERT INTO course_assignment (course_id, lecturer_id) VALUES (?, ?)");
                                            $course_stmt->execute([$course_id, $userId]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    $pdo->commit();
                    $message = 'User updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'delete_user':
                    // Fixed: Check if user_id is set before using it
                    if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
                        throw new Exception("User ID is missing or invalid for delete operation");
                    }
                    
                    $userId = $_POST['user_id'];
                    
                    // Validate that userId is a valid integer
                    if (!is_numeric($userId) || $userId <= 0) {
                        throw new Exception("Invalid User ID provided for delete operation");
                    }
                    
                    $pdo->beginTransaction();
                    
                    // Get user role first to determine which profile table to delete from
                    $stmt = $pdo->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
                    $stmt->execute([$userId]);
                    $roleName = $stmt->fetchColumn();
                    
                    // Delete from profile table first
                    if ($roleName == 'Super Admin') {
                        $stmt = $pdo->prepare("DELETE FROM admin_profile WHERE user_id = ?");
                        $stmt->execute([$userId]);
                    } elseif ($roleName == 'Student') {
                        $stmt = $pdo->prepare("DELETE FROM student_profile WHERE user_id = ?");
                        $stmt->execute([$userId]);
                    } elseif ($roleName == 'Lecturer' || $roleName == 'Sub Admin (Finance)' || $roleName == 'Enrollment Officer') {
                        $stmt = $pdo->prepare("DELETE FROM staff_profile WHERE user_id = ?");
                        $stmt->execute([$userId]);
                    } elseif (!$roleName) {
                        // If no role found, that might indicate the user doesn't exist
                        throw new Exception("User with ID " . (int)$userId . " not found or has no role assigned");
                    }
                    
                    // Delete from user_roles
                    $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Delete from users table
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    
                    $pdo->commit();
                    $message = 'User deleted successfully!';
                    $messageType = 'success';
                    break;
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        // More robust error message handling
        $rawMessage = $e->getMessage();
        // Remove any HTML tags and entities that might be in the message
        $cleanMessage = strip_tags($rawMessage);
        $cleanMessage = htmlspecialchars_decode($cleanMessage);
        $errorMessage = htmlspecialchars($cleanMessage, ENT_QUOTES, 'UTF-8');
        $message = 'Error: ' . $errorMessage;
        $messageType = 'error';
    }
}

// Get filters
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query with filters
$query = "SELECT u.id as user_id, u.username, u.is_active, u.created_at, r.name as role_name,
          COALESCE(ap.full_name, sp.full_name, stp.full_name) as full_name,
          u.email,
          COALESCE(ap.staff_id, sp.student_number, stp.staff_id) as identifier
          FROM users u 
          JOIN user_roles ur ON u.id = ur.user_id
          JOIN roles r ON ur.role_id = r.id 
          LEFT JOIN admin_profile ap ON u.id = ap.user_id
          LEFT JOIN student_profile sp ON u.id = sp.user_id
          LEFT JOIN staff_profile stp ON u.id = stp.user_id
          WHERE 1=1";

$params = [];

if ($roleFilter) {
    $query .= " AND r.name = ?";
    $params[] = $roleFilter;
}

if ($statusFilter !== '') {
    $query .= " AND u.is_active = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $query .= " AND (u.username LIKE ? OR COALESCE(ap.full_name, sp.full_name, stp.full_name) LIKE ? OR u.email LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get roles for dropdown
$stmt = $pdo->query("SELECT id as role_id, name as role_name FROM roles ORDER BY name");
$roles = $stmt->fetchAll();

// Get programmes, intakes, schools, departments, courses for assignments
$programmes = $pdo->query("SELECT id, name FROM programme ORDER BY name")->fetchAll();
$intakes = $pdo->query("SELECT id, name FROM intake ORDER BY name")->fetchAll();
$schools = $pdo->query("SELECT id, name FROM school ORDER BY name")->fetchAll();
$departments = $pdo->query("SELECT id, name FROM department ORDER BY name")->fetchAll();
$courses = $pdo->query("SELECT id, name FROM course ORDER BY name")->fetchAll();

// Get user for editing if specified
$editUser = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT u.*, u.id as user_id, r.id as role_id, r.name as role_name,
                          COALESCE(ap.full_name, sp.full_name, stp.full_name) as full_name,
                          COALESCE(ap.staff_id, sp.student_number, stp.staff_id) as identifier,
                          sp.NRC as student_nrc, sp.gender as student_gender, sp.programme_id, sp.intake_id, sp.school_id,
                          stp.NRC as staff_nrc, stp.gender as staff_gender, stp.qualification
                          FROM users u 
                          JOIN user_roles ur ON u.id = ur.user_id
                          JOIN roles r ON ur.role_id = r.id
                          LEFT JOIN admin_profile ap ON u.id = ap.user_id
                          LEFT JOIN student_profile sp ON u.id = sp.user_id
                          LEFT JOIN staff_profile stp ON u.id = stp.user_id
                          WHERE u.id = ?");
    $stmt->execute([$_GET['edit']]);
    $editUser = $stmt->fetch();
    
    // Get assigned courses if editing student or lecturer
    if ($editUser) {
        if ($editUser['role_name'] == 'Student') {
            $course_stmt = $pdo->prepare("SELECT course_id FROM course_enrollment WHERE student_user_id = ?");
            $course_stmt->execute([$_GET['edit']]);
            $editUser['course_ids'] = array_column($course_stmt->fetchAll(), 'course_id');
        } elseif ($editUser['role_name'] == 'Lecturer') {
            // Fixed: Changed from lecturer_courses to course_assignment table
            $course_stmt = $pdo->prepare("SELECT course_id FROM course_assignment WHERE lecturer_id = ?");
            $course_stmt->execute([$_GET['edit']]);
            $editUser['course_ids'] = array_column($course_stmt->fetchAll(), 'course_id');
        }
    }
}

// Create necessary tables if not exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS lecturer_courses (
        lecturer_id INT,
        course_id INT,
        PRIMARY KEY (lecturer_id, course_id),
        FOREIGN KEY (lecturer_id) REFERENCES users(id),
        FOREIGN KEY (course_id) REFERENCES course(id)
    )");
} catch (Exception $e) {
    // Table might already exist
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - LSC SRMS</title>
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($admin['full_name'] ?? 'Administrator'); ?></span>
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
            <h3><i class="fas fa-tachometer-alt"></i> Admin Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>User Management</h4>
                <a href="manage_users.php" class="nav-item active">
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
                <a href="course_registrations.php" class="nav-item">
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
            <h1><i class="fas fa-users"></i> Manage Users</h1>
            <p>Create, edit, and manage system users</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div class="filters-section">
            <div class="filters-card">
                <form method="GET" class="filters-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role">
                                <option value="">All Roles</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo htmlspecialchars($role['role_name']); ?>" <?php echo $roleFilter === $role['role_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">All Status</option>
                                <option value="1" <?php echo $statusFilter === '1' ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo $statusFilter === '0' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="search">Search</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search by name, username or email">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-green">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="manage_users.php" class="btn btn-orange">
                                <i class="fas fa-refresh"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add/Edit User Form -->
        <div class="form-section">
            <div class="form-card">
                <h2>
                    <i class="fas fa-<?php echo $editUser ? 'edit' : 'plus'; ?>"></i>
                    <?php echo $editUser ? 'Edit User' : 'Add New User'; ?>
                </h2>
                
                <form method="POST" class="user-form">
                    <input type="hidden" name="action" value="<?php echo $editUser ? 'update_user' : 'add_user'; ?>">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="user_id" value="<?php echo $editUser['user_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" required 
                                   value="<?php echo htmlspecialchars($editUser['username'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password <?php echo $editUser ? '(leave blank to keep current)' : '*'; ?></label>
                            <input type="password" id="password" name="password" <?php echo $editUser ? '' : 'required'; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label for="role_id">Role *</label>
                            <select id="role_id" name="role_id" required onchange="toggleFields()">
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['role_id']; ?>" 
                                            <?php echo ($editUser && $editUser['role_id'] == $role['role_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($editUser): ?>
                        <div class="form-group">
                            <label for="is_active">Status *</label>
                            <select id="is_active" name="is_active" required>
                                <option value="1" <?php echo $editUser['is_active'] ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo !$editUser['is_active'] ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" required 
                                   value="<?php echo htmlspecialchars($editUser['full_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($editUser['email'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group" id="staff_id_group">
                            <label for="staff_id">Staff ID (auto-generated if blank)</label>
                            <input type="text" id="staff_id" name="staff_id" 
                                   value="<?php echo htmlspecialchars($editUser['identifier'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group" id="student_id_group" style="display: none;">
                            <label for="student_number">Student Number (auto-generated if blank)</label>
                            <input type="text" id="student_number" name="student_number" 
                                   value="<?php echo htmlspecialchars($editUser['identifier'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group" id="phone_group">
                            <label for="phone">Phone/Contact</label>
                            <input type="text" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($editUser['contact'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row" id="additional_fields">
                        <div class="form-group">
                            <label for="nrc">NRC (Optional)</label>
                            <input type="text" id="nrc" name="nrc" 
                                   value="<?php echo htmlspecialchars($editUser['student_nrc'] ?? $editUser['staff_nrc'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="gender">Gender (Optional)</label>
                            <select id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (($editUser && ($editUser['student_gender'] ?? $editUser['staff_gender']) == 'Male') ? 'selected' : ''); ?>>Male</option>
                                <option value="Female" <?php echo (($editUser && ($editUser['student_gender'] ?? $editUser['staff_gender']) == 'Female') ? 'selected' : ''); ?>>Female</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="qualification_group" style="display: none;">
                            <label for="qualification">Qualification (Optional)</label>
                            <input type="text" id="qualification" name="qualification" 
                                   value="<?php echo htmlspecialchars($editUser['qualification'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row" id="assignment_fields" style="display: none;">
                        <div class="form-group" id="school_id_group">
                            <label for="school_id">School</label>
                            <select id="school_id" name="school_id">
                                <option value="">Select School</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>" <?php echo ($editUser && $editUser['school_id'] == $school['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($school['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" id="department_id_group">
                            <label for="department_id">Department</label>
                            <select id="department_id" name="department_id">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['id']; ?>" <?php echo ($editUser && isset($editUser['school_id']) && $editUser['school_id'] && isset($editUser['department_id']) && $editUser['department_id'] == $department['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($department['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" id="programme_id_group">
                            <label for="programme_id">Programme</label>
                            <select id="programme_id" name="programme_id">
                                <option value="">Select Programme</option>
                                <?php foreach ($programmes as $programme): ?>
                                    <option value="<?php echo $programme['id']; ?>" <?php echo ($editUser && $editUser['programme_id'] == $programme['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($programme['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" id="intake_id_group">
                            <label for="intake_id">Intake</label>
                            <select id="intake_id" name="intake_id">
                                <option value="">Select Intake</option>
                                <?php foreach ($intakes as $intake): ?>
                                    <option value="<?php echo $intake['id']; ?>" <?php echo ($editUser && $editUser['intake_id'] == $intake['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($intake['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row" id="courses_group" style="display: none;">
                        <div class="form-group full-width">
                            <label>Courses</label>
                            <div class="checkbox-list">
                                <?php foreach ($courses as $course): ?>
                                    <label>
                                        <input type="checkbox" name="course_ids[]" value="<?php echo $course['id']; ?>"
                                               <?php echo ($editUser && in_array($course['id'], $editUser['course_ids'] ?? [])) ? 'checked' : ''; ?>>
                                        <?php echo htmlspecialchars($course['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-green">
                            <i class="fas fa-save"></i> <?php echo $editUser ? 'Update User' : 'Create User'; ?>
                        </button>
                        <?php if ($editUser): ?>
                            <a href="manage_users.php" class="btn btn-orange">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <div class="action-left">
                <!-- Export and Print buttons with current filters -->
                <a href="?action=export_users&role=<?php echo urlencode($roleFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" class="btn btn-blue">
                    <i class="fas fa-download"></i> Export CSV
                </a>
                <button onclick="printUsers()" class="btn btn-green">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
            <div class="action-right">
                <button onclick="showAddForm()" class="btn btn-green">
                    <i class="fas fa-plus"></i> Add New User
                </button>
            </div>
        </div>

        <!-- Users Table -->
        <div class="table-section">
            <div class="table-card">
                <div class="table-header">
                    <h2><i class="fas fa-list"></i> Users List (<?php echo count($users); ?> users)</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="users-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Identifier</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['user_id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo strtolower(str_replace([' ', '(', ')'], ['-', '', ''], $user['role_name'])); ?>">
                                            <?php echo htmlspecialchars($user['role_name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['identifier'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                    <td class="actions">
                                        <a href="?edit=<?php echo $user['user_id']; ?>" class="btn-icon btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" class="btn-icon btn-delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        function toggleFields() {
            const roleSelect = document.getElementById('role_id');
            const staffIdGroup = document.getElementById('staff_id_group');
            const studentIdGroup = document.getElementById('student_id_group');
            const phoneGroup = document.getElementById('phone_group');
            const qualificationGroup = document.getElementById('qualification_group');
            const assignmentFields = document.getElementById('assignment_fields');
            const coursesGroup = document.getElementById('courses_group');
            const departmentIdGroup = document.getElementById('department_id_group');
            const programmeIdGroup = document.getElementById('programme_id_group');
            const intakeIdGroup = document.getElementById('intake_id_group');
            
            // Hide all conditional fields
            staffIdGroup.style.display = 'none';
            studentIdGroup.style.display = 'none';
            qualificationGroup.style.display = 'none';
            assignmentFields.style.display = 'none';
            coursesGroup.style.display = 'none';
            departmentIdGroup.style.display = 'none';
            programmeIdGroup.style.display = 'none';
            intakeIdGroup.style.display = 'none';
            
            // Show phone for all
            phoneGroup.style.display = 'block';
            
            const selectedRole = roleSelect.options[roleSelect.selectedIndex].text;
            
            if (selectedRole === 'Super Admin') {
                staffIdGroup.style.display = 'block';
            } else if (selectedRole === 'Student') {
                studentIdGroup.style.display = 'block';
                assignmentFields.style.display = 'block';
                departmentIdGroup.style.display = 'none'; // Hide department for students
                programmeIdGroup.style.display = 'block';
                intakeIdGroup.style.display = 'block';
                coursesGroup.style.display = 'block';
            } else if (selectedRole === 'Lecturer') {
                staffIdGroup.style.display = 'block';
                qualificationGroup.style.display = 'block';
                assignmentFields.style.display = 'block';
                departmentIdGroup.style.display = 'none'; // Hide department for lecturers (no department_id in staff_profile)
                programmeIdGroup.style.display = 'none';
                intakeIdGroup.style.display = 'none';
                coursesGroup.style.display = 'block';
            } else if (selectedRole === 'Sub Admin (Finance)') {
                staffIdGroup.style.display = 'block';
                qualificationGroup.style.display = 'block';
                assignmentFields.style.display = 'block';
                departmentIdGroup.style.display = 'none'; // Hide department for finance staff (no department_id in staff_profile)
                programmeIdGroup.style.display = 'none';
                intakeIdGroup.style.display = 'none';
                coursesGroup.style.display = 'none';
            }
        }
        
        // Initialize fields on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleFields();
        });
        
        // Print functionality
        function printUsers() {
            // Create a new window with the filtered user data
            const printWindow = window.open('', '_blank');
            
            // Get current filter values
            const roleFilter = document.getElementById('role').value;
            const statusFilter = document.getElementById('status').value;
            const searchQuery = document.getElementById('search').value;
            
            // Create the print content with filters
            let printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Users Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { color: #333; text-align: center; }
                        .filters { margin-bottom: 20px; text-align: center; }
                        .filters p { margin: 5px 0; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        tr:nth-child(even) { background-color: #f9f9f9; }
                        .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <h1>Users Report</h1>
                    <div class="filters">
                        <p><strong>Generated on:</strong> ${new Date().toLocaleString()}</p>
                        <p><strong>Filters Applied:</strong></p>
                        <p>Role: ${roleFilter || 'All'} | Status: ${statusFilter === '1' ? 'Active' : statusFilter === '0' ? 'Inactive' : 'All'} | Search: ${searchQuery || 'None'}</p>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Identifier</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>`;
            
            // Get all rows from the current table (excluding the header row)
            const table = document.getElementById('usersTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                printContent += '<tr>';
                for (let j = 0; j < cells.length - 1; j++) { // Exclude the last column (Actions)
                    printContent += '<td>' + cells[j].innerHTML + '</td>';
                }
                printContent += '</tr>';
            }
            
            printContent += `
                        </tbody>
                    </table>
                    <div class="footer">
                        <p>Generated by LSC SRMS System</p>
                    </div>
                </body>
                </html>`;
            
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }
        
        // Show add form function
        function showAddForm() {
            // Scroll to the form section
            document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
            document.getElementById('username').focus();
        }
    </script>
</body>
</html>