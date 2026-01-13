<?php
session_start();
require_once '../config.php';
require_once '../auth/auth.php';

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
if (isset($_GET['action']) && $_GET['action'] === 'export_departments') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="departments_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Code', 'Name', 'School', 'Head of Department', 'Students', 'Courses', 'Created']);
    
    $export_query = "
        SELECT d.code, d.name, s.name as school_name, d.head_of_department,
               (SELECT COUNT(*) FROM student_profile sp WHERE sp.department_id = d.id) as student_count,
               (SELECT COUNT(*) FROM course WHERE department_id = d.id) as programme_count,
               d.created_at
        FROM department d 
        LEFT JOIN school s ON d.school_id = s.id 
        ORDER BY s.name, d.name
    ";
    $export_data = $pdo->query($export_query)->fetchAll();
    
    foreach ($export_data as $row) {
        fputcsv($output, [
            $row['code'],
            $row['name'],
            $row['school_name'],
            $row['head_of_department'] ?: 'Not assigned',
            $row['student_count'],
            $row['programme_count'],
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
            case 'add_department':
                $name = trim($_POST['name']);
                $code = strtoupper(trim($_POST['code']));
                $school_id = $_POST['school_id'];
                $description = trim($_POST['description']);
                $head_of_department = trim($_POST['head_of_department']) ?: null;
                
                // Validate inputs
                if (empty($name) || empty($code) || empty($school_id)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } elseif (strlen($code) > 20) {
                    $message = "Department code is too long (max 20 characters)!";
                    $messageType = 'error';
                } else {
                    // Check if department code already exists
                    $check_stmt = $pdo->prepare("SELECT id FROM department WHERE code = ?");
                    $check_stmt->execute([$code]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        $message = "Department code already exists!";
                        $messageType = 'error';
                    } else {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO department (name, code, school_id, description, head_of_department, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                            if ($stmt->execute([$name, $code, $school_id, $description, $head_of_department])) {
                                $message = "Department added successfully!";
                                $messageType = 'success';
                            } else {
                                $message = "Failed to add department!";
                                $messageType = 'error';
                            }
                        } catch (Exception $e) {
                            $message = "Error: " . $e->getMessage();
                            $messageType = 'error';
                        }
                    }
                }
                break;
                
            case 'edit_department':
                $id = $_POST['department_id'];
                $name = trim($_POST['name']);
                $code = strtoupper(trim($_POST['code']));
                $school_id = $_POST['school_id'];
                $description = trim($_POST['description']);
                $head_of_department = trim($_POST['head_of_department']) ?: null;
                
                // Validate inputs
                if (empty($name) || empty($code) || empty($school_id)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } elseif (strlen($code) > 20) {
                    $message = "Department code is too long (max 20 characters)!";
                    $messageType = 'error';
                } else {
                    // Check if department code already exists (excluding current department)
                    $check_stmt = $pdo->prepare("SELECT id FROM department WHERE code = ? AND id != ?");
                    $check_stmt->execute([$code, $id]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        $message = "Department code already exists!";
                        $messageType = 'error';
                    } else {
                        try {
                            $stmt = $pdo->prepare("UPDATE department SET name = ?, code = ?, school_id = ?, description = ?, head_of_department = ?, updated_at = NOW() WHERE id = ?");
                            if ($stmt->execute([$name, $code, $school_id, $description, $head_of_department, $id])) {
                                $message = "Department updated successfully!";
                                $messageType = 'success';
                            } else {
                                $message = "Failed to update department!";
                                $messageType = 'error';
                            }
                        } catch (Exception $e) {
                            $message = "Error: " . $e->getMessage();
                            $messageType = 'error';
                        }
                    }
                }
                break;
                
            case 'delete_department':
                $id = $_POST['department_id'];
                
                // Check if department has students
                $student_check = $pdo->prepare("SELECT COUNT(*) FROM student_profile WHERE department_id = ?");
                $student_check->execute([$id]);
                $student_count = $student_check->fetchColumn();
                
                // Check if department has courses
                $course_check = $pdo->prepare("SELECT COUNT(*) FROM course WHERE department_id = ?");
                $course_check->execute([$id]);
                $course_count = $course_check->fetchColumn();
                
                if ($student_count > 0) {
                    $message = "Cannot delete department with {$student_count} enrolled student(s)! Please reassign students first.";
                    $messageType = 'error';
                } elseif ($course_count > 0) {
                    $message = "Cannot delete department with {$course_count} course(s)! Please reassign or delete courses first.";
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM department WHERE id = ?");
                        if ($stmt->execute([$id])) {
                            $message = "Department deleted successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to delete department!";
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

// Get departments with school info and counts
$departments_query = "
    SELECT d.*, s.name as school_name,
           (SELECT COUNT(*) FROM student_profile sp WHERE sp.department_id = d.id) as student_count,
           (SELECT COUNT(*) FROM course WHERE department_id = d.id) as programme_count
    FROM department d 
    LEFT JOIN school s ON d.school_id = s.id 
    ORDER BY s.name, d.name
";
$departments = $pdo->query($departments_query)->fetchAll();

// Get filters
$searchQuery = $_GET['search'] ?? '';

// Build query with filters
$query = "SELECT d.*, s.name as school_name,
          (SELECT COUNT(*) FROM student_profile sp WHERE sp.department_id = d.id) as student_count,
          (SELECT COUNT(*) FROM course WHERE department_id = d.id) as programme_count
          FROM department d 
          LEFT JOIN school s ON d.school_id = s.id 
          WHERE 1=1";

$params = [];

if ($searchQuery) {
    $query .= " AND (d.name LIKE ? OR d.description LIKE ? OR d.code LIKE ? OR s.name LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY s.name, d.name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$departments = $stmt->fetchAll();

// Get schools for dropdowns
$schools = $pdo->query("SELECT * FROM school ORDER BY name")->fetchAll();

// Get department for editing if specified
$editDepartment = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT d.*, s.name as school_name FROM department d LEFT JOIN school s ON d.school_id = s.id WHERE d.id = ?");
    $stmt->execute([$_GET['edit']]);
    $editDepartment = $stmt->fetch();
}

// Get department details if viewing
$viewingDepartment = null;
$departmentStudents = [];
$departmentProgrammes = [];
if (isset($_GET['view'])) {
    $dept_id = $_GET['view'];
    $dept_stmt = $pdo->prepare("
        SELECT d.*, s.name as school_name,
               (SELECT COUNT(*) FROM student_profile sp WHERE sp.department_id = d.id) as student_count,
               (SELECT COUNT(*) FROM course WHERE department_id = d.id) as programme_count
        FROM department d 
        LEFT JOIN school s ON d.school_id = s.id 
        WHERE d.id = ?
    ");
    $dept_stmt->execute([$dept_id]);
    $viewingDepartment = $dept_stmt->fetch();
    
    if ($viewingDepartment) {
        // Get students in this department
        $students_stmt = $pdo->prepare("
            SELECT sp.*, u.username, u.email as user_email, p.name as programme_name
            FROM student_profile sp 
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN programme p ON sp.programme_id = p.id 
            WHERE sp.department_id = ? 
            ORDER BY sp.full_name
        ");
        $students_stmt->execute([$dept_id]);
        $departmentStudents = $students_stmt->fetchAll();
        
        // Get courses in this department
        $programmes_stmt = $pdo->prepare("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM course_enrollment ce WHERE ce.course_id = c.id) as enrollment_count
            FROM course c 
            WHERE c.department_id = ? 
            ORDER BY c.name
        ");
        $programmes_stmt->execute([$dept_id]);
        $departmentProgrammes = $programmes_stmt->fetchAll();
    }
}

// Add missing columns to department table if they don't exist
try {
    $pdo->exec("ALTER TABLE department ADD COLUMN IF NOT EXISTS code VARCHAR(20) UNIQUE");
    $pdo->exec("ALTER TABLE department ADD COLUMN IF NOT EXISTS description TEXT");
    $pdo->exec("ALTER TABLE department ADD COLUMN IF NOT EXISTS head_of_department VARCHAR(255)");
    $pdo->exec("ALTER TABLE department ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $pdo->exec("ALTER TABLE department ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    
    // Add department_id to programme table for future functionality
    $pdo->exec("ALTER TABLE programme ADD COLUMN IF NOT EXISTS department_id INT");
    $pdo->exec("ALTER TABLE programme ADD CONSTRAINT FK_programme_department FOREIGN KEY (department_id) REFERENCES department(id) ON DELETE SET NULL");
} catch (Exception $e) {
    // Columns might already exist
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - LSC SRMS</title>
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
                <a href="manage_departments.php" class="nav-item active">
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
            <h1><i class="fas fa-building"></i> Department Management</h1>
            <p>Create, edit, and manage academic departments within the institution</p>
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
                    <a href="manage_departments.php" class="btn btn-orange">
                        <i class="fas fa-arrow-left"></i> Back to Departments
                    </a>
                <?php endif; ?>
            </div>
            <div class="action-right">
                <?php if (!isset($_GET['view'])): ?>
                    <button onclick="showAddForm()" class="btn btn-green">
                        <i class="fas fa-plus"></i> Add New Department
                    </button>
                    <a href="?action=export_departments" class="btn btn-info">
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
                            <label for="search">Search Departments</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search by name, code, description or school">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-green">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <a href="manage_departments.php" class="btn btn-orange">
                                <i class="fas fa-refresh"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add/Edit Department Form -->
        <div class="form-section" id="departmentForm" style="<?php echo $editDepartment ? 'display: block;' : 'display: none;'; ?>">
            <div class="form-card">
                <h2>
                    <i class="fas fa-<?php echo $editDepartment ? 'edit' : 'plus'; ?>"></i>
                    <?php echo $editDepartment ? 'Edit Department' : 'Add New Department'; ?>
                </h2>
                
                <form method="POST" class="school-form">
                    <input type="hidden" name="action" value="<?php echo $editDepartment ? 'edit_department' : 'add_department'; ?>">
                    <?php if ($editDepartment): ?>
                        <input type="hidden" name="department_id" value="<?php echo $editDepartment['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Department Name *</label>
                            <input type="text" id="name" name="name" required maxlength="150" 
                                   value="<?php echo htmlspecialchars($editDepartment['name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="code">Department Code *</label>
                            <input type="text" id="code" name="code" required maxlength="20" style="text-transform: uppercase;"
                                   value="<?php echo htmlspecialchars($editDepartment['code'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="school_id">School *</label>
                            <select id="school_id" name="school_id" required>
                                <option value="">-- Select School --</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>" <?php echo isset($editDepartment['school_id']) && $editDepartment['school_id'] == $school['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($school['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="head_of_department">Head of Department</label>
                            <input type="text" id="head_of_department" name="head_of_department" maxlength="255" 
                                   value="<?php echo htmlspecialchars($editDepartment['head_of_department'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" 
                                      placeholder="Brief description of the department"><?php echo htmlspecialchars($editDepartment['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-green">
                            <i class="fas fa-save"></i> <?php echo $editDepartment ? 'Update Department' : 'Create Department'; ?>
                        </button>
                        <button type="button" onclick="hideForm()" class="btn btn-orange">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($viewingDepartment): ?>
            <!-- Department Details View -->
            <div class="school-header-card">
                <div class="school-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="school-info">
                    <h2><?php echo htmlspecialchars($viewingDepartment['name']); ?></h2>
                    <p class="department-code">Code: <?php echo htmlspecialchars($viewingDepartment['code']); ?></p>
                    <p class="school-info">School: <?php echo htmlspecialchars($viewingDepartment['school_name']); ?></p>
                    <?php if ($viewingDepartment['head_of_department']): ?>
                        <p class="hod-info">Head of Department: <?php echo htmlspecialchars($viewingDepartment['head_of_department']); ?></p>
                    <?php endif; ?>
                    <p class="school-description"><?php echo htmlspecialchars($viewingDepartment['description'] ?? 'No description available'); ?></p>
                </div>
                <div class="school-actions">
                    <a href="?edit=<?php echo $viewingDepartment['id']; ?>" class="btn btn-orange">
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
                        <h3><?php echo $viewingDepartment['student_count']; ?></h3>
                        <p>Total Students</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $viewingDepartment['programme_count']; ?></h3>
                        <p>Courses</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo date('M Y', strtotime($viewingDepartment['created_at'])); ?></h3>
                        <p>Established</p>
                    </div>
                </div>
            </div>

            <!-- Courses Section -->
            <div class="section-card">
                <div class="section-header">
                    <h3><i class="fas fa-book"></i> Courses</h3>
                    <div class="section-actions">
                        <input type="text" class="search-input" placeholder="Search courses..." onkeyup="filterTable(this, 'coursesTable')">
                    </div>
                </div>
                <?php if (empty($departmentProgrammes)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <h4>No Courses</h4>
                        <p>No courses found in this department</p>
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
                                <?php foreach ($departmentProgrammes as $programme): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($programme['code']); ?></td>
                                        <td><?php echo htmlspecialchars($programme['name']); ?></td>
                                        <td><?php echo $programme['credits']; ?></td>
                                        <td><?php echo $programme['enrollment_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Students Section -->
            <div class="section-card">
                <div class="section-header">
                    <h3><i class="fas fa-users"></i> Students</h3>
                    <div class="section-actions">
                        <input type="text" class="search-input" placeholder="Search students..." onkeyup="filterTable(this, 'studentsTable')">
                    </div>
                </div>
                <?php if (empty($departmentStudents)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h4>No Students</h4>
                        <p>No students found in this department</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="users-table" id="studentsTable">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Programme</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departmentStudents as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['user_email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['programme_name'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Departments Table -->
            <div class="table-section">
                <div class="table-card">
                    <div class="table-header">
                        <h2><i class="fas fa-list"></i> Departments List (<?php echo count($departments); ?> departments)</h2>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>School</th>
                                    <th>Head of Dept</th>
                                    <th>Students</th>
                                    <th>Courses</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $department): ?>
                                    <tr>
                                        <td><?php echo $department['id']; ?></td>
                                        <td><?php echo htmlspecialchars($department['code']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($department['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($department['school_name']); ?></td>
                                        <td><?php echo htmlspecialchars($department['head_of_department'] ?? 'N/A'); ?></td>
                                        <td><span class="count-badge green"><?php echo $department['student_count']; ?></span></td>
                                        <td><span class="count-badge orange"><?php echo $department['programme_count']; ?></span></td>
                                        <td class="actions">
                                            <a href="?view=<?php echo $department['id']; ?>" class="btn-icon btn-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?edit=<?php echo $department['id']; ?>" class="btn-icon btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($department['student_count'] == 0 && $department['programme_count'] == 0): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this department?');">
                                                    <input type="hidden" name="action" value="delete_department">
                                                    <input type="hidden" name="department_id" value="<?php echo $department['id']; ?>">
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
            document.getElementById('departmentForm').style.display = 'block';
            document.getElementById('name').focus();
        }
        
        function hideForm() {
            document.getElementById('departmentForm').style.display = 'none';
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
        <?php if ($editDepartment): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showAddForm();
            });
        <?php endif; ?>
    </script>
</body>
</html>