<?php
session_start();
require_once '../config.php';
require_once '../auth.php';

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Location: ../login.php');
    exit();
}

// Check if user has admin role or manage_academic_structure permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('manage_academic_structure', $pdo)) {
    header('Location: ../login.php');
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT u.*, ap.full_name, ap.staff_id FROM users u LEFT JOIN admin_profile ap ON u.id = ap.user_id WHERE u.id = ?");
$stmt->execute([currentUserId()]);
$user = $stmt->fetch();

// Handle AJAX requests for export
if (isset($_GET['action']) && $_GET['action'] === 'export_programmes') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="programmes_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Code', 'Name', 'School', 'Duration', 'Students', 'Courses', 'Created']);
    
    $export_query = "
        SELECT p.code, p.name, s.name as school_name, p.duration,
               (SELECT COUNT(*) FROM student_profile sp WHERE sp.programme_id = p.id) as student_count,
               (SELECT COUNT(*) FROM course c WHERE c.programme_id = p.id) as course_count,
               p.created_at
        FROM programme p 
        LEFT JOIN school s ON p.school_id = s.id 
        ORDER BY s.name, p.name
    ";
    $export_data = $pdo->query($export_query)->fetchAll();
    
    foreach ($export_data as $row) {
        fputcsv($output, [
            $row['code'],
            $row['name'],
            $row['school_name'] ?: 'Not assigned',
            $row['duration'],
            $row['student_count'],
            $row['course_count'],
            date('Y-m-d', strtotime($row['created_at']))
        ]);
    }
    fclose($output);
    exit();
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_programme':
                $name = trim($_POST['name']);
                $code = strtoupper(trim($_POST['code']));
                $school_id = $_POST['school_id'];
                $duration = trim($_POST['duration']);
                $description = trim($_POST['description']);
                
                // Validate inputs
                if (empty($name) || empty($code) || empty($school_id) || empty($duration)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } elseif (strlen($code) > 20) {
                    $message = "Programme code is too long (max 20 characters)!";
                    $messageType = 'error';
                } elseif (!is_numeric($duration) || $duration <= 0) {
                    $message = "Duration must be a positive number!";
                    $messageType = 'error';
                } else {
                    // Check if programme code already exists
                    $check_stmt = $pdo->prepare("SELECT id FROM programme WHERE code = ?");
                    $check_stmt->execute([$code]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        $message = "Programme code already exists!";
                        $messageType = 'error';
                    } else {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO programme (name, code, school_id, duration, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                            if ($stmt->execute([$name, $code, $school_id, $duration, $description])) {
                                $message = "Programme added successfully!";
                                $messageType = 'success';
                            } else {
                                $message = "Failed to add programme!";
                                $messageType = 'error';
                            }
                        } catch (Exception $e) {
                            $message = "Error: " . $e->getMessage();
                            $messageType = 'error';
                        }
                    }
                }
                break;
                
            case 'edit_programme':
                $id = $_POST['programme_id'];
                $name = trim($_POST['name']);
                $code = strtoupper(trim($_POST['code']));
                $school_id = $_POST['school_id'];
                $duration = trim($_POST['duration']);
                $description = trim($_POST['description']);
                
                // Validate inputs
                if (empty($name) || empty($code) || empty($school_id) || empty($duration)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } elseif (strlen($code) > 20) {
                    $message = "Programme code is too long (max 20 characters)!";
                    $messageType = 'error';
                } elseif (!is_numeric($duration) || $duration <= 0) {
                    $message = "Duration must be a positive number!";
                    $messageType = 'error';
                } else {
                    // Check if programme code already exists (excluding current programme)
                    $check_stmt = $pdo->prepare("SELECT id FROM programme WHERE code = ? AND id != ?");
                    $check_stmt->execute([$code, $id]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        $message = "Programme code already exists!";
                        $messageType = 'error';
                    } else {
                        try {
                            $stmt = $pdo->prepare("UPDATE programme SET name = ?, code = ?, school_id = ?, duration = ?, description = ?, updated_at = NOW() WHERE id = ?");
                            if ($stmt->execute([$name, $code, $school_id, $duration, $description, $id])) {
                                $message = "Programme updated successfully!";
                                $messageType = 'success';
                            } else {
                                $message = "Failed to update programme!";
                                $messageType = 'error';
                            }
                        } catch (Exception $e) {
                            $message = "Error: " . $e->getMessage();
                            $messageType = 'error';
                        }
                    }
                }
                break;
                
            case 'delete_programme':
                $id = $_POST['programme_id'];
                
                // Check if programme has students
                $student_check = $pdo->prepare("SELECT COUNT(*) FROM student_profile WHERE programme_id = ?");
                $student_check->execute([$id]);
                $student_count = $student_check->fetchColumn();
                
                if ($student_count > 0) {
                    $message = "Cannot delete programme with {$student_count} enrolled student(s)! Please reassign students first.";
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM programme WHERE id = ?");
                        if ($stmt->execute([$id])) {
                            $message = "Programme deleted successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to delete programme!";
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
        }
    }
}

// Get filters
$searchQuery = $_GET['search'] ?? '';

// Build query with filters
$query = "
    SELECT p.*, s.name as school_name,
           (SELECT COUNT(*) FROM student_profile sp WHERE sp.programme_id = p.id) as student_count,
           (SELECT COUNT(*) FROM course c WHERE c.programme_id = p.id) as course_count
    FROM programme p 
    LEFT JOIN school s ON p.school_id = s.id 
    WHERE 1=1";

$params = [];

if ($searchQuery) {
    $query .= " AND (p.name LIKE ? OR p.code LIKE ? OR p.description LIKE ? OR s.name LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY s.name, p.name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$programmes = $stmt->fetchAll();

// Get schools for dropdowns
$schools = $pdo->query("SELECT * FROM school ORDER BY name")->fetchAll();

// Get programme for editing if specified
$editProgramme = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT p.*, s.name as school_name FROM programme p LEFT JOIN school s ON p.school_id = s.id WHERE p.id = ?");
    $stmt->execute([$_GET['edit']]);
    $editProgramme = $stmt->fetch();
}

// Get programme details for viewing if specified
$viewProgramme = null;
$programmeStudents = [];
$programmeCourses = [];
$programmeLecturers = [];
if (isset($_GET['view'])) {
    $programme_id = $_GET['view'];
    $programme_stmt = $pdo->prepare("
        SELECT p.*, s.name as school_name,
               (SELECT COUNT(*) FROM student_profile sp WHERE sp.programme_id = p.id) as student_count,
               (SELECT COUNT(*) FROM course c WHERE c.programme_id = p.id) as course_count
        FROM programme p 
        LEFT JOIN school s ON p.school_id = s.id 
        WHERE p.id = ?
    ");
    $programme_stmt->execute([$programme_id]);
    $viewProgramme = $programme_stmt->fetch();
    
    if ($viewProgramme) {
        // Get students in this programme
        $students_stmt = $pdo->prepare("
            SELECT sp.*, u.username, u.email as user_email
            FROM student_profile sp 
            JOIN users u ON sp.user_id = u.id
            WHERE sp.programme_id = ?
            ORDER BY sp.full_name
        ");
        $students_stmt->execute([$programme_id]);
        $programmeStudents = $students_stmt->fetchAll();
        
        // Get courses under this programme
        $courses_stmt = $pdo->prepare("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM course_enrollment ce WHERE ce.course_id = c.id) as enrollment_count
            FROM course c 
            WHERE c.programme_id = ?
            ORDER BY c.name
        ");
        $courses_stmt->execute([$programme_id]);
        $programmeCourses = $courses_stmt->fetchAll();

        // Get lecturers in this school (assuming lecturer_profile exists with school_id)
        $lecturers_stmt = $pdo->prepare("
            SELECT lp.*, u.username, u.email as user_email
            FROM lecturer_profile lp 
            JOIN users u ON lp.user_id = u.id
            WHERE lp.school_id = ?
            ORDER BY lp.full_name
        ");
        $lecturers_stmt->execute([$viewProgramme['school_id']]);
        $programmeLecturers = $lecturers_stmt->fetchAll();
    }
}

// Add missing columns to programme table if they don't exist
try {
    $pdo->exec("ALTER TABLE programme DROP FOREIGN KEY IF EXISTS FK_programme_department");
    $pdo->exec("ALTER TABLE programme DROP COLUMN IF EXISTS department_id");
    $pdo->exec("ALTER TABLE programme ADD COLUMN IF NOT EXISTS school_id INT");
    $pdo->exec("ALTER TABLE programme ADD CONSTRAINT FK_programme_school FOREIGN KEY (school_id) REFERENCES school(id) ON DELETE SET NULL");
    $pdo->exec("ALTER TABLE programme ADD COLUMN IF NOT EXISTS code VARCHAR(20) UNIQUE");
    $pdo->exec("ALTER TABLE programme ADD COLUMN IF NOT EXISTS description TEXT");
    $pdo->exec("ALTER TABLE programme ADD COLUMN IF NOT EXISTS duration INT");
    $pdo->exec("ALTER TABLE programme ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $pdo->exec("ALTER TABLE programme ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    
    // Add programme_id to course
    $pdo->exec("ALTER TABLE course ADD COLUMN IF NOT EXISTS programme_id INT");
    $pdo->exec("ALTER TABLE course ADD CONSTRAINT FK_course_programme FOREIGN KEY (programme_id) REFERENCES programme(id) ON DELETE SET NULL");
    
    // Create lecturer_profile if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS lecturer_profile (
        user_id INT PRIMARY KEY,
        full_name VARCHAR(255),
        staff_id VARCHAR(50),
        school_id INT,
        department_id INT,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (school_id) REFERENCES school(id),
        FOREIGN KEY (department_id) REFERENCES department(id)
    )");
} catch (Exception $e) {
    // Columns might already exist or other issues
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Programmes - LSC SRMS</title>
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
                <a href="manage_programmes.php" class="nav-item active">
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
            <h1><i class="fas fa-graduation-cap"></i> Programme Management</h1>
            <p>Create, edit, and manage academic programmes within the institution</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Navigation Actions -->
        <div class="action-bar">
            <div class="action-left">
                <?php if (isset($_GET['view'])): ?>
                    <a href="manage_programmes.php" class="btn btn-orange">
                        <i class="fas fa-arrow-left"></i> Back to Programmes
                    </a>
                <?php endif; ?>
            </div>
            <div class="action-right">
                <?php if (!isset($_GET['view'])): ?>
                    <button onclick="showAddForm()" class="btn btn-green">
                        <i class="fas fa-plus"></i> Add New Programme
                    </button>
                    <a href="?action=export_programmes" class="btn btn-info">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="filters-section">
            <div class="filters-card">
                <form method="GET" class="filters-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="search">Search Programmes</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search by name, code, description or school">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-green">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <a href="manage_programmes.php" class="btn btn-orange">
                                <i class="fas fa-refresh"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add/Edit Programme Form -->
        <div class="form-section" id="programmeForm" style="<?php echo $editProgramme ? 'display: block;' : 'display: none;'; ?>">
            <div class="form-card">
                <h2>
                    <i class="fas fa-<?php echo $editProgramme ? 'edit' : 'plus'; ?>"></i>
                    <?php echo $editProgramme ? 'Edit Programme' : 'Add New Programme'; ?>
                </h2>
                
                <form method="POST" class="school-form">
                    <input type="hidden" name="action" value="<?php echo $editProgramme ? 'edit_programme' : 'add_programme'; ?>">
                    <?php if ($editProgramme): ?>
                        <input type="hidden" name="programme_id" value="<?php echo $editProgramme['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Programme Name *</label>
                            <input type="text" id="name" name="name" required maxlength="150" 
                                   value="<?php echo htmlspecialchars($editProgramme['name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="code">Programme Code *</label>
                            <input type="text" id="code" name="code" required maxlength="20" style="text-transform: uppercase;"
                                   value="<?php echo htmlspecialchars($editProgramme['code'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="school_id">School *</label>
                            <select id="school_id" name="school_id" required>
                                <option value="">-- Select School --</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>" <?php echo isset($editProgramme['school_id']) && $editProgramme['school_id'] == $school['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($school['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="duration">Duration (Years) *</label>
                            <input type="number" id="duration" name="duration" required min="1" max="10" 
                                   value="<?php echo htmlspecialchars($editProgramme['duration'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" 
                                      placeholder="Brief description of the programme"><?php echo htmlspecialchars($editProgramme['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-green">
                            <i class="fas fa-save"></i> <?php echo $editProgramme ? 'Update Programme' : 'Create Programme'; ?>
                        </button>
                        <button type="button" onclick="hideForm()" class="btn btn-orange">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($viewProgramme): ?>
            <!-- Programme Details View -->
            <div class="school-header-card">
                <div class="school-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="school-info">
                    <h2><?php echo htmlspecialchars($viewProgramme['name']); ?></h2>
                    <p class="department-code">Code: <?php echo htmlspecialchars($viewProgramme['code']); ?></p>
                    <p class="school-info">School: <?php echo htmlspecialchars($viewProgramme['school_name'] ?? 'N/A'); ?></p>
                    <p class="school-info">Duration: <?php echo htmlspecialchars($viewProgramme['duration']); ?> Years</p>
                    <p class="school-description"><?php echo htmlspecialchars($viewProgramme['description'] ?? 'No description available'); ?></p>
                </div>
                <div class="school-actions">
                    <a href="?edit=<?php echo $viewProgramme['id']; ?>" class="btn btn-orange">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $viewProgramme['student_count']; ?></h3>
                        <p>Total Students</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $viewProgramme['course_count']; ?></h3>
                        <p>Courses</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo date('M Y', strtotime($viewProgramme['created_at'])); ?></h3>
                        <p>Created</p>
                    </div>
                </div>
            </div>

            <!-- Students Section -->
            <div class="section-card">
                <div class="section-header">
                    <h3><i class="fas fa-users"></i> Students</h3>
                    <div class="section-actions">
                        <input type="text" class="search-input" placeholder="Search students..." onkeyup="filterTable(this, 'studentsTable')">
                    </div>
                </div>
                <?php if (empty($programmeStudents)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h4>No Students</h4>
                        <p>No students enrolled in this programme</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="users-table" id="studentsTable">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programmeStudents as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['user_email']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Courses Section -->
            <div class="section-card">
                <div class="section-header">
                    <h3><i class="fas fa-book"></i> Courses</h3>
                    <div class="section-actions">
                        <input type="text" class="search-input" placeholder="Search courses..." onkeyup="filterTable(this, 'coursesTable')">
                    </div>
                </div>
                <?php if (empty($programmeCourses)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <h4>No Courses</h4>
                        <p>No courses registered under this programme</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="users-table" id="coursesTable">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Credits</th>
                                    <th>Enrollments</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programmeCourses as $course): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['code']); ?></td>
                                        <td><?php echo htmlspecialchars($course['name']); ?></td>
                                        <td><?php echo $course['credits']; ?></td>
                                        <td><?php echo $course['enrollment_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Lecturers Section -->
            <div class="section-card">
                <div class="section-header">
                    <h3><i class="fas fa-chalkboard-teacher"></i> Lecturers</h3>
                    <div class="section-actions">
                        <input type="text" class="search-input" placeholder="Search lecturers..." onkeyup="filterTable(this, 'lecturersTable')">
                    </div>
                </div>
                <?php if (empty($programmeLecturers)): ?>
                    <div class="empty-state">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <h4>No Lecturers</h4>
                        <p>No lecturers found in this school</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="users-table" id="lecturersTable">
                            <thead>
                                <tr>
                                    <th>Staff ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programmeLecturers as $lecturer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($lecturer['staff_id']); ?></td>
                                        <td><?php echo htmlspecialchars($lecturer['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($lecturer['user_email']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Programmes Table -->
            <div class="table-section">
                <div class="table-card">
                    <div class="table-header">
                        <h2><i class="fas fa-list"></i> Programmes List (<?php echo count($programmes); ?> programmes)</h2>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>School</th>
                                    <th>Duration (Years)</th>
                                    <th>Students</th>
                                    <th>Courses</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programmes as $programme): ?>
                                    <tr>
                                        <td><?php echo $programme['id']; ?></td>
                                        <td><?php echo htmlspecialchars($programme['code']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($programme['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($programme['school_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo $programme['duration']; ?></td>
                                        <td><span class="count-badge green"><?php echo $programme['student_count']; ?></span></td>
                                        <td><span class="count-badge orange"><?php echo $programme['course_count']; ?></span></td>
                                        <td class="actions">
                                            <a href="?view=<?php echo $programme['id']; ?>" class="btn-icon btn-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?edit=<?php echo $programme['id']; ?>" class="btn-icon btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($programme['student_count'] == 0): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this programme?');">
                                                    <input type="hidden" name="action" value="delete_programme">
                                                    <input type="hidden" name="programme_id" value="<?php echo $programme['id']; ?>">
                                                    <button type="submit" class="btn-icon btn-delete" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        function showAddForm() {
            document.getElementById('programmeForm').style.display = 'block';
            document.getElementById('name').focus();
        }
        
        function hideForm() {
            document.getElementById('programmeForm').style.display = 'none';
        }

        function filterTable(input, tableId) {
            const filter = input.value.toLowerCase();
            const table = document.getElementById(tableId);
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let show = false;
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j] && cells[j].textContent.toLowerCase().includes(filter)) {
                        show = true;
                        break;
                    }
                }
                rows[i].style.display = show ? '' : 'none';
            }
        }
        
        // Auto-show form if editing
        <?php if ($editProgramme): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showAddForm();
            });
        <?php endif; ?>

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
</body>
</html>