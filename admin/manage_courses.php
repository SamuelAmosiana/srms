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
if (isset($_GET['action']) && $_GET['action'] === 'export_courses') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="courses_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Code', 'Name', 'Programme', 'Credits', 'Enrollments', 'Created']);
    
    $export_query = "
        SELECT c.code, c.name, p.name as programme_name, c.credits,
               (SELECT COUNT(*) FROM course_enrollment ce WHERE ce.course_id = c.id) as enrollment_count,
               c.created_at
        FROM course c 
        LEFT JOIN programme p ON c.programme_id = p.id 
        ORDER BY p.name, c.name
    ";
    $export_data = $pdo->query($export_query)->fetchAll();
    
    foreach ($export_data as $row) {
        fputcsv($output, [
            $row['code'],
            $row['name'],
            $row['programme_name'] ?: 'Not assigned',
            $row['credits'],
            $row['enrollment_count'],
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
            case 'add_course':
                $name = trim($_POST['name']);
                $code = strtoupper(trim($_POST['code']));
                $programme_id = $_POST['programme_id'];
                $credits = $_POST['credits'];
                $description = trim($_POST['description']);
                
                // Validate inputs
                if (empty($name) || empty($code) || empty($programme_id) || empty($credits)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } elseif (strlen($code) > 20) {
                    $message = "Course code is too long (max 20 characters)!";
                    $messageType = 'error';
                } elseif (!is_numeric($credits) || $credits <= 0) {
                    $message = "Credits must be a positive number!";
                    $messageType = 'error';
                } else {
                    // Check if course code already exists
                    $check_stmt = $pdo->prepare("SELECT id FROM course WHERE code = ?");
                    $check_stmt->execute([$code]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        $message = "Course code already exists!";
                        $messageType = 'error';
                    } else {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO course (name, code, programme_id, credits, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                            if ($stmt->execute([$name, $code, $programme_id, $credits, $description])) {
                                $message = "Course added successfully!";
                                $messageType = 'success';
                            } else {
                                $message = "Failed to add course!";
                                $messageType = 'error';
                            }
                        } catch (Exception $e) {
                            $message = "Error: " . $e->getMessage();
                            $messageType = 'error';
                        }
                    }
                }
                break;
                
            case 'edit_course':
                $id = $_POST['course_id'];
                $name = trim($_POST['name']);
                $code = strtoupper(trim($_POST['code']));
                $programme_id = $_POST['programme_id'];
                $credits = $_POST['credits'];
                $description = trim($_POST['description']);
                
                // Validate inputs
                if (empty($name) || empty($code) || empty($programme_id) || empty($credits)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } elseif (strlen($code) > 20) {
                    $message = "Course code is too long (max 20 characters)!";
                    $messageType = 'error';
                } elseif (!is_numeric($credits) || $credits <= 0) {
                    $message = "Credits must be a positive number!";
                    $messageType = 'error';
                } else {
                    // Check if course code already exists (excluding current course)
                    $check_stmt = $pdo->prepare("SELECT id FROM course WHERE code = ? AND id != ?");
                    $check_stmt->execute([$code, $id]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        $message = "Course code already exists!";
                        $messageType = 'error';
                    } else {
                        try {
                            $stmt = $pdo->prepare("UPDATE course SET name = ?, code = ?, programme_id = ?, credits = ?, description = ?, updated_at = NOW() WHERE id = ?");
                            if ($stmt->execute([$name, $code, $programme_id, $credits, $description, $id])) {
                                $message = "Course updated successfully!";
                                $messageType = 'success';
                            } else {
                                $message = "Failed to update course!";
                                $messageType = 'error';
                            }
                        } catch (Exception $e) {
                            $message = "Error: " . $e->getMessage();
                            $messageType = 'error';
                        }
                    }
                }
                break;
                
            case 'delete_course':
                $id = $_POST['course_id'];
                
                // Check if course has enrollments
                $enrollment_check = $pdo->prepare("SELECT COUNT(*) FROM course_enrollment WHERE course_id = ?");
                $enrollment_check->execute([$id]);
                $enrollment_count = $enrollment_check->fetchColumn();
                
                if ($enrollment_count > 0) {
                    $message = "Cannot delete course with {$enrollment_count} enrollment(s)! Please remove enrollments first.";
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM course WHERE id = ?");
                        if ($stmt->execute([$id])) {
                            $message = "Course deleted successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to delete course!";
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
    SELECT c.*, p.name as programme_name,
           (SELECT COUNT(*) FROM course_enrollment ce WHERE ce.course_id = c.id) as enrollment_count
    FROM course c 
    LEFT JOIN programme p ON c.programme_id = p.id 
    WHERE 1=1";

$params = [];

if ($searchQuery) {
    $query .= " AND (c.name LIKE ? OR c.code LIKE ? OR c.description LIKE ? OR p.name LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY p.name, c.name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// Get programmes for dropdowns
$programmes = $pdo->query("SELECT * FROM programme ORDER BY name")->fetchAll();

// Get course for editing if specified
$editCourse = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT c.*, p.name as programme_name FROM course c LEFT JOIN programme p ON c.programme_id = p.id WHERE c.id = ?");
    $stmt->execute([$_GET['edit']]);
    $editCourse = $stmt->fetch();
}

// Get course details for viewing if specified
$viewCourse = null;
$courseEnrollments = [];
if (isset($_GET['view'])) {
    $course_id = $_GET['view'];
    $course_stmt = $pdo->prepare("
        SELECT c.*, p.name as programme_name,
               (SELECT COUNT(*) FROM course_enrollment ce WHERE ce.course_id = c.id) as enrollment_count
        FROM course c 
        LEFT JOIN programme p ON c.programme_id = p.id 
        WHERE c.id = ?
    ");
    $course_stmt->execute([$course_id]);
    $viewCourse = $course_stmt->fetch();
    
    if ($viewCourse) {
        // Get enrollments for this course
        $enrollments_stmt = $pdo->prepare("
            SELECT ce.*, sp.student_id, sp.full_name, u.email as user_email
            FROM course_enrollment ce
            JOIN student_profile sp ON ce.student_id = sp.user_id
            JOIN users u ON sp.user_id = u.id
            WHERE ce.course_id = ?
            ORDER BY sp.full_name
        ");
        $enrollments_stmt->execute([$course_id]);
        $courseEnrollments = $enrollments_stmt->fetchAll();
    }
}

// Add missing columns to course table if they don't exist
try {
    $pdo->exec("ALTER TABLE course DROP FOREIGN KEY IF EXISTS FK_course_department");
    $pdo->exec("ALTER TABLE course DROP COLUMN IF EXISTS department_id");
    $pdo->exec("ALTER TABLE course ADD COLUMN IF NOT EXISTS programme_id INT");
    $pdo->exec("ALTER TABLE course ADD CONSTRAINT FK_course_programme FOREIGN KEY (programme_id) REFERENCES programme(id) ON DELETE SET NULL");
    $pdo->exec("ALTER TABLE course ADD COLUMN IF NOT EXISTS code VARCHAR(20) UNIQUE");
    $pdo->exec("ALTER TABLE course ADD COLUMN IF NOT EXISTS description TEXT");
    $pdo->exec("ALTER TABLE course ADD COLUMN IF NOT EXISTS credits INT");
    $pdo->exec("ALTER TABLE course ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $pdo->exec("ALTER TABLE course ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
} catch (Exception $e) {
    // Columns might already exist
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - LSC SRMS</title>
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
                <a href="manage_programmes.php" class="nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Programmes</span>
                </a>
                <a href="manage_courses.php" class="nav-item active">
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
            <h1><i class="fas fa-book"></i> Course Management</h1>
            <p>Create, edit, and manage academic courses within the institution</p>
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
                    <a href="manage_courses.php" class="btn btn-orange">
                        <i class="fas fa-arrow-left"></i> Back to Courses
                    </a>
                <?php endif; ?>
            </div>
            <div class="action-right">
                <?php if (!isset($_GET['view'])): ?>
                    <button onclick="showAddForm()" class="btn btn-green">
                        <i class="fas fa-plus"></i> Add New Course
                    </button>
                    <a href="?action=export_courses" class="btn btn-info">
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
                            <label for="search">Search Courses</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search by name, code, description or programme">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-green">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <a href="manage_courses.php" class="btn btn-orange">
                                <i class="fas fa-refresh"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add/Edit Course Form -->
        <div class="form-section" id="courseForm" style="<?php echo $editCourse ? 'display: block;' : 'display: none;'; ?>">
            <div class="form-card">
                <h2>
                    <i class="fas fa-<?php echo $editCourse ? 'edit' : 'plus'; ?>"></i>
                    <?php echo $editCourse ? 'Edit Course' : 'Add New Course'; ?>
                </h2>
                
                <form method="POST" class="school-form">
                    <input type="hidden" name="action" value="<?php echo $editCourse ? 'edit_course' : 'add_course'; ?>">
                    <?php if ($editCourse): ?>
                        <input type="hidden" name="course_id" value="<?php echo $editCourse['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Course Name *</label>
                            <input type="text" id="name" name="name" required maxlength="150" 
                                   value="<?php echo htmlspecialchars($editCourse['name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="code">Course Code *</label>
                            <input type="text" id="code" name="code" required maxlength="20" style="text-transform: uppercase;"
                                   value="<?php echo htmlspecialchars($editCourse['code'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="programme_id">Programme *</label>
                            <select id="programme_id" name="programme_id" required>
                                <option value="">-- Select Programme --</option>
                                <?php foreach ($programmes as $programme): ?>
                                    <option value="<?php echo $programme['id']; ?>" <?php echo isset($editCourse['programme_id']) && $editCourse['programme_id'] == $programme['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($programme['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="credits">Credits *</label>
                            <input type="number" id="credits" name="credits" required min="1" max="100" 
                                   value="<?php echo htmlspecialchars($editCourse['credits'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" 
                                      placeholder="Brief description of the course"><?php echo htmlspecialchars($editCourse['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-green">
                            <i class="fas fa-save"></i> <?php echo $editCourse ? 'Update Course' : 'Create Course'; ?>
                        </button>
                        <button type="button" onclick="hideForm()" class="btn btn-orange">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($viewCourse): ?>
            <!-- Course Details View -->
            <div class="school-header-card">
                <div class="school-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="school-info">
                    <h2><?php echo htmlspecialchars($viewCourse['name']); ?></h2>
                    <p class="department-code">Code: <?php echo htmlspecialchars($viewCourse['code']); ?></p>
                    <p class="school-info">Programme: <?php echo htmlspecialchars($viewCourse['programme_name'] ?? 'N/A'); ?></p>
                    <p class="school-info">Credits: <?php echo htmlspecialchars($viewCourse['credits']); ?></p>
                    <p class="school-description"><?php echo htmlspecialchars($viewCourse['description'] ?? 'No description available'); ?></p>
                </div>
                <div class="school-actions">
                    <a href="?edit=<?php echo $viewCourse['id']; ?>" class="btn btn-orange">
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
                        <h3><?php echo $viewCourse['enrollment_count']; ?></h3>
                        <p>Total Enrollments</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo date('M Y', strtotime($viewCourse['created_at'])); ?></h3>
                        <p>Created</p>
                    </div>
                </div>
            </div>

            <!-- Enrollments Section -->
            <div class="section-card">
                <div class="section-header">
                    <h3><i class="fas fa-users"></i> Enrollments</h3>
                    <div class="section-actions">
                        <input type="text" class="search-input" placeholder="Search enrollments..." onkeyup="filterTable(this, 'enrollmentsTable')">
                    </div>
                </div>
                <?php if (empty($courseEnrollments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h4>No Enrollments</h4>
                        <p>No students enrolled in this course</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="users-table" id="enrollmentsTable">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Enrollment Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courseEnrollments as $enrollment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($enrollment['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($enrollment['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($enrollment['user_email']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($enrollment['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Courses Table -->
            <div class="table-section">
                <div class="table-card">
                    <div class="table-header">
                        <h2><i class="fas fa-list"></i> Courses List (<?php echo count($courses); ?> courses)</h2>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Programme</th>
                                    <th>Credits</th>
                                    <th>Enrollments</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td><?php echo $course['id']; ?></td>
                                        <td><?php echo htmlspecialchars($course['code']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($course['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($course['programme_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo $course['credits']; ?></td>
                                        <td><span class="count-badge green"><?php echo $course['enrollment_count']; ?></span></td>
                                        <td class="actions">
                                            <a href="?view=<?php echo $course['id']; ?>" class="btn-icon btn-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?edit=<?php echo $course['id']; ?>" class="btn-icon btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($course['enrollment_count'] == 0): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this course?');">
                                                    <input type="hidden" name="action" value="delete_course">
                                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
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
            document.getElementById('courseForm').style.display = 'block';
            document.getElementById('name').focus();
        }
        
        function hideForm() {
            document.getElementById('courseForm').style.display = 'none';
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
        <?php if ($editCourse): ?>
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