<?php
session_start();
require_once '../config.php';
require_once '../auth.php';

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Location: ../login.php');
    exit();
}

// Check if user has admin role or manage_results permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('manage_results', $pdo)) {
    header('Location: ../login.php');
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT u.*, ap.full_name, ap.staff_id FROM users u LEFT JOIN admin_profile ap ON u.id = ap.user_id WHERE u.id = ?");
$stmt->execute([currentUserId()]);
$user = $stmt->fetch();

// Create necessary tables if not exist - MUST BE BEFORE ANY QUERIES
try {
    // Check if result_type table exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'result_type'");
    if ($table_check->rowCount() == 0) {
        $pdo->exec("CREATE TABLE result_type (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            weight DECIMAL(5,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    // Check if grading_scale table exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'grading_scale'");
    if ($table_check->rowCount() == 0) {
        $pdo->exec("CREATE TABLE grading_scale (
            id INT AUTO_INCREMENT PRIMARY KEY,
            min_score DECIMAL(5,2) NOT NULL,
            max_score DECIMAL(5,2) NOT NULL,
            grade VARCHAR(5) NOT NULL,
            points DECIMAL(3,1) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    // Check if student_result table exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'student_result'");
    if ($table_check->rowCount() == 0) {
        $pdo->exec("CREATE TABLE student_result (
            id INT AUTO_INCREMENT PRIMARY KEY,
            enrollment_id INT NOT NULL,
            type_id INT NOT NULL,
            score DECIMAL(5,2) NOT NULL,
            remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (enrollment_id) REFERENCES course_enrollment(id) ON DELETE CASCADE,
            FOREIGN KEY (type_id) REFERENCES result_type(id) ON DELETE CASCADE
        )");
    }
    
    // Add result fields to course_enrollment if they don't exist
    $column_check = $pdo->query("SHOW COLUMNS FROM course_enrollment LIKE 'total_score'");
    if ($column_check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE course_enrollment ADD COLUMN total_score DECIMAL(5,2) DEFAULT NULL");
    }
    
    $column_check = $pdo->query("SHOW COLUMNS FROM course_enrollment LIKE 'grade'");
    if ($column_check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE course_enrollment ADD COLUMN grade VARCHAR(5) DEFAULT NULL");
    }
    
    $column_check = $pdo->query("SHOW COLUMNS FROM course_enrollment LIKE 'status'");
    if ($column_check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE course_enrollment ADD COLUMN status VARCHAR(50) DEFAULT NULL");
    }
    
    $column_check = $pdo->query("SHOW COLUMNS FROM course_enrollment LIKE 'published'");
    if ($column_check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE course_enrollment ADD COLUMN published TINYINT(1) DEFAULT 0");
    }
    
    // Add admin_comment column to results table
    $column_check = $pdo->query("SHOW COLUMNS FROM results LIKE 'admin_comment'");
    if ($column_check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE results ADD COLUMN admin_comment TEXT NULL AFTER grade");
    }
    
    // Create academic_year_comments table
    $table_check = $pdo->query("SHOW TABLES LIKE 'academic_year_comments'");
    if ($table_check->rowCount() == 0) {
        $pdo->exec("CREATE TABLE academic_year_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_user_id INT NOT NULL,
            academic_year VARCHAR(20) NOT NULL,
            comment TEXT NOT NULL,
            added_by_user_id INT NOT NULL,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (added_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_student_year_comment (student_user_id, academic_year)
        )");
    }
} catch (Exception $e) {
    // Log error but continue
    error_log("Result tables creation error: " . $e->getMessage());
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_result_type':
                $name = trim($_POST['name']);
                $weight = $_POST['weight'];
                
                if (empty($name) || empty($weight)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } elseif (!is_numeric($weight) || $weight < 0 || $weight > 100) {
                    $message = "Weight must be a number between 0 and 100!";
                    $messageType = 'error';
                } else {
                    // Check total weight doesn't exceed 100
                    $total_weight = $pdo->query("SELECT SUM(weight) FROM result_type")->fetchColumn();
                    if ($total_weight + $weight > 100) {
                        $message = "Total weight cannot exceed 100%!";
                        $messageType = 'error';
                    } else {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO result_type (name, weight) VALUES (?, ?)");
                            if ($stmt->execute([$name, $weight])) {
                                $message = "Result type added successfully!";
                                $messageType = 'success';
                            } else {
                                $message = "Failed to add result type!";
                                $messageType = 'error';
                            }
                        } catch (Exception $e) {
                            $message = "Error: " . $e->getMessage();
                            $messageType = 'error';
                        }
                    }
                }
                break;
                
            case 'edit_result_type':
                $id = $_POST['type_id'];
                $name = trim($_POST['name']);
                $weight = $_POST['weight'];
                
                if (empty($name) || empty($weight)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } elseif (!is_numeric($weight) || $weight < 0 || $weight > 100) {
                    $message = "Weight must be a number between 0 and 100!";
                    $messageType = 'error';
                } else {
                    // Check total weight without current
                    $current_weight = $pdo->prepare("SELECT weight FROM result_type WHERE id = ?");
                    $current_weight->execute([$id]);
                    $old_weight = $current_weight->fetchColumn();
                    
                    $total_weight = $pdo->query("SELECT SUM(weight) FROM result_type")->fetchColumn() - $old_weight;
                    if ($total_weight + $weight > 100) {
                        $message = "Total weight cannot exceed 100%!";
                        $messageType = 'error';
                    } else {
                        try {
                            $stmt = $pdo->prepare("UPDATE result_type SET name = ?, weight = ? WHERE id = ?");
                            if ($stmt->execute([$name, $weight, $id])) {
                                $message = "Result type updated successfully!";
                                $messageType = 'success';
                            } else {
                                $message = "Failed to update result type!";
                                $messageType = 'error';
                            }
                        } catch (Exception $e) {
                            $message = "Error: " . $e->getMessage();
                            $messageType = 'error';
                        }
                    }
                }
                break;
                
            case 'delete_result_type':
                $id = $_POST['type_id'];
                
                // Check if type has results
                $result_check = $pdo->prepare("SELECT COUNT(*) FROM student_result WHERE type_id = ?");
                $result_check->execute([$id]);
                $result_count = $result_check->fetchColumn();
                
                if ($result_count > 0) {
                    $message = "Cannot delete result type with existing results!";
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM result_type WHERE id = ?");
                        if ($stmt->execute([$id])) {
                            $message = "Result type deleted successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to delete result type!";
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'add_grade':
                $min_score = $_POST['min_score'];
                $max_score = $_POST['max_score'];
                $grade = trim($_POST['grade']);
                $points = $_POST['points'];
                
                if (empty($min_score) || empty($max_score) || empty($grade) || empty($points)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } elseif (!is_numeric($min_score) || !is_numeric($max_score) || !is_numeric($points)) {
                    $message = "Scores and points must be numbers!";
                    $messageType = 'error';
                } elseif ($min_score >= $max_score || $min_score < 0 || $max_score > 100) {
                    $message = "Invalid score range!";
                    $messageType = 'error';
                } else {
                    // Check for overlapping ranges
                    $overlap_check = $pdo->prepare("SELECT id FROM grading_scale WHERE (min_score <= ? AND max_score >= ?) OR (min_score <= ? AND max_score >= ?)");
                    $overlap_check->execute([$max_score, $min_score, $min_score, $max_score]);
                    
                    if ($overlap_check->rowCount() > 0) {
                        $message = "Score range overlaps with existing grade!";
                        $messageType = 'error';
                    } else {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO grading_scale (min_score, max_score, grade, points) VALUES (?, ?, ?, ?)");
                            if ($stmt->execute([$min_score, $max_score, $grade, $points])) {
                                $message = "Grade added successfully!";
                                $messageType = 'success';
                            } else {
                                $message = "Failed to add grade!";
                                $messageType = 'error';
                            }
                        } catch (Exception $e) {
                            $message = "Error: " . $e->getMessage();
                            $messageType = 'error';
                        }
                    }
                }
                break;
                
            case 'edit_grade':
                $id = $_POST['grade_id'];
                $min_score = $_POST['min_score'];
                $max_score = $_POST['max_score'];
                $grade = trim($_POST['grade']);
                $points = $_POST['points'];
                
                if (empty($min_score) || empty($max_score) || empty($grade) || empty($points)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } elseif (!is_numeric($min_score) || !is_numeric($max_score) || !is_numeric($points)) {
                    $message = "Scores and points must be numbers!";
                    $messageType = 'error';
                } elseif ($min_score >= $max_score || $min_score < 0 || $max_score > 100) {
                    $message = "Invalid score range!";
                    $messageType = 'error';
                } else {
                    // Check for overlapping ranges excluding current
                    $overlap_check = $pdo->prepare("SELECT id FROM grading_scale WHERE id != ? AND ((min_score <= ? AND max_score >= ?) OR (min_score <= ? AND max_score >= ?))");
                    $overlap_check->execute([$id, $max_score, $min_score, $min_score, $max_score]);
                    
                    if ($overlap_check->rowCount() > 0) {
                        $message = "Score range overlaps with existing grade!";
                        $messageType = 'error';
                    } else {
                        try {
                            $stmt = $pdo->prepare("UPDATE grading_scale SET min_score = ?, max_score = ?, grade = ?, points = ? WHERE id = ?");
                            if ($stmt->execute([$min_score, $max_score, $grade, $points, $id])) {
                                $message = "Grade updated successfully!";
                                $messageType = 'success';
                            } else {
                                $message = "Failed to update grade!";
                                $messageType = 'error';
                            }
                        } catch (Exception $e) {
                            $message = "Error: " . $e->getMessage();
                            $messageType = 'error';
                        }
                    }
                }
                break;
                
            case 'delete_grade':
                $id = $_POST['grade_id'];
                try {
                    $stmt = $pdo->prepare("DELETE FROM grading_scale WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $message = "Grade deleted successfully!";
                        $messageType = 'success';
                    } else {
                        $message = "Failed to delete grade!";
                        $messageType = 'error';
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'publish_results':
                $course_id = $_POST['course_id'];
                
                // Get all enrollments for the course
                $enrollments = $pdo->prepare("SELECT ce.student_id, ce.id as enrollment_id FROM course_enrollment ce WHERE ce.course_id = ?");
                $enrollments->execute([$course_id]);
                $students = $enrollments->fetchAll();
                
                $published = 0;
                foreach ($students as $student) {
                    // Calculate total score
                    $scores_query = $pdo->prepare("SELECT rt.weight, sr.score FROM student_result sr JOIN result_type rt ON sr.type_id = rt.id WHERE sr.enrollment_id = ?");
                    $scores_query->execute([$student['enrollment_id']]);
                    $scores = $scores_query->fetchAll();
                    
                    $total_score = 0;
                    foreach ($scores as $score) {
                        $total_score += ($score['score'] * $score['weight'] / 100);
                    }
                    
                    // Get grade
                    $grade_stmt = $pdo->prepare("SELECT grade FROM grading_scale WHERE min_score <= ? AND max_score >= ?");
                    $grade_stmt->execute([$total_score, $total_score]);
                    $grade = $grade_stmt->fetchColumn() ?: 'F';
                    
                    // Determine status
                    if ($total_score >= 50) {
                        $status = 'clear pass';
                    } elseif ($total_score >= 40) {
                        $status = 'proceed and carry course';
                    } else {
                        $status = 'repeat year';
                    }
                    
                    // Update result
                    $update_stmt = $pdo->prepare("UPDATE course_enrollment SET total_score = ?, grade = ?, status = ?, published = 1 WHERE id = ?");
                    if ($update_stmt->execute([$total_score, $grade, $status, $student['enrollment_id']])) {
                        $published++;
                    }
                }
                
                $message = "{$published} results published successfully!";
                $messageType = 'success';
                break;
                
            case 'add_academic_year_comment':
                $student_user_id = $_POST['student_user_id'];
                $academic_year = $_POST['academic_year'];
                $comment = trim($_POST['comment']);
                
                if (empty($student_user_id) || empty($academic_year) || empty($comment)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } else {
                    try {
                        // Check if comment already exists for this student and year
                        $check_stmt = $pdo->prepare("SELECT id FROM academic_year_comments WHERE student_user_id = ? AND academic_year = ?");
                        $check_stmt->execute([$student_user_id, $academic_year]);
                        
                        if ($check_stmt->rowCount() > 0) {
                            // Update existing comment
                            $stmt = $pdo->prepare("UPDATE academic_year_comments SET comment = ?, added_by_user_id = ?, added_at = NOW() WHERE student_user_id = ? AND academic_year = ?");
                            $result = $stmt->execute([$comment, currentUserId(), $student_user_id, $academic_year]);
                        } else {
                            // Insert new comment
                            $stmt = $pdo->prepare("INSERT INTO academic_year_comments (student_user_id, academic_year, comment, added_by_user_id) VALUES (?, ?, ?, ?)");
                            $result = $stmt->execute([$student_user_id, $academic_year, $comment, currentUserId()]);
                        }
                        
                        if ($result) {
                            $message = "Academic year comment saved successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to save academic year comment!";
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'add_course_comment':
                $enrollment_id = $_POST['enrollment_id'];
                $comment = trim($_POST['comment']);
                
                if (empty($enrollment_id) || empty($comment)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } else {
                    try {
                        // Update the result with the admin comment
                        $stmt = $pdo->prepare("UPDATE results SET admin_comment = ? WHERE enrollment_id = ?");
                        if ($stmt->execute([$comment, $enrollment_id])) {
                            $message = "Course comment saved successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to save course comment!";
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

// Get result types
$result_types = $pdo->query("SELECT * FROM result_type ORDER BY id")->fetchAll();

// Get grading scale
$grades = $pdo->query("SELECT * FROM grading_scale ORDER BY min_score DESC")->fetchAll();

// Get courses for publication
$courses = $pdo->query("SELECT c.id, c.name, c.code, COUNT(ce.id) as enrollment_count FROM course c LEFT JOIN course_enrollment ce ON c.id = ce.course_id GROUP BY c.id ORDER BY c.name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Results - LSC SRMS</title>
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
                <a href="manage_results.php" class="nav-item active">
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
            <h1><i class="fas fa-chart-line"></i> Results Management</h1>
            <p>Define result types, grading scales, and publish student results</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Result Types Section -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-tasks"></i> Result Types (e.g., CA, Final Exam)</h3>
                <button onclick="openModal('addTypeModal')" class="btn btn-primary"><i class="fas fa-plus"></i> Add Type</button>
            </div>
            <div class="panel-content">
                <?php if (empty($result_types)): ?>
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <p>No result types defined</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Weight (%)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($result_types as $type): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($type['name']); ?></td>
                                        <td><?php echo $type['weight']; ?>%</td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick="openEditTypeModal(<?php echo htmlspecialchars(json_encode($type)); ?>)"><i class="fas fa-edit"></i></button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?');">
                                                <input type="hidden" name="action" value="delete_result_type">
                                                <input type="hidden" name="type_id" value="<?php echo $type['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Grading Scale Section -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-graduation-cap"></i> Grading Scale</h3>
                <button onclick="openModal('addGradeModal')" class="btn btn-primary"><i class="fas fa-plus"></i> Add Grade</button>
            </div>
            <div class="panel-content">
                <?php if (empty($grades)): ?>
                    <div class="empty-state">
                        <i class="fas fa-graduation-cap"></i>
                        <p>No grades defined</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Min Score</th>
                                    <th>Max Score</th>
                                    <th>Grade</th>
                                    <th>Points</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grades as $grade): ?>
                                    <tr>
                                        <td><?php echo $grade['min_score']; ?></td>
                                        <td><?php echo $grade['max_score']; ?></td>
                                        <td><?php echo htmlspecialchars($grade['grade']); ?></td>
                                        <td><?php echo $grade['points']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick="openEditGradeModal(<?php echo htmlspecialchars(json_encode($grade)); ?>)"><i class="fas fa-edit"></i></button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?');">
                                                <input type="hidden" name="action" value="delete_grade">
                                                <input type="hidden" name="grade_id" value="<?php echo $grade['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Results Publication Section -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-publish"></i> Publish Results</h3>
            </div>
            <div class="panel-content">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Name</th>
                                <th>Enrollments</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['code']); ?></td>
                                    <td><?php echo htmlspecialchars($course['name']); ?></td>
                                    <td><?php echo $course['enrollment_count']; ?></td>
                                    <td>
                                        <?php if ($course['enrollment_count'] > 0): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Publish results for this course?');">
                                                <input type="hidden" name="action" value="publish_results">
                                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-publish"></i> Publish</button>
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
        
        <!-- Add Comment Section -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-comment"></i> Add Student Comments</h3>
            </div>
            <div class="panel-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add_academic_year_comment">
                    <div class="form-group">
                        <label for="student_user_id">Student:</label>
                        <select id="student_user_id" name="student_user_id" required>
                            <option value="">Select Student</option>
                            <?php
                            $students = $pdo->query("SELECT sp.user_id, sp.full_name, sp.student_number FROM student_profile sp ORDER BY sp.full_name");
                            foreach ($students as $student) {
                                echo '<option value="' . $student['user_id'] . '">' . htmlspecialchars($student['full_name']) . ' (' . htmlspecialchars($student['student_number']) . ')</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="academic_year">Academic Year:</label>
                        <input type="text" id="academic_year" name="academic_year" placeholder="e.g., 2024/2025" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="comment">Comment:</label>
                        <textarea id="comment" name="comment" rows="3" placeholder="Enter academic year comment" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Comment</button>
                </form>
            </div>
        </div>
        
        <!-- Add Course Comment Section -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-book"></i> Add Course Comments</h3>
            </div>
            <div class="panel-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add_course_comment">
                    <div class="form-group">
                        <label for="enrollment_id">Enrollment:</label>
                        <select id="enrollment_id" name="enrollment_id" required>
                            <option value="">Select Enrollment</option>
                            <?php
                            $enrollments = $pdo->query("
                                SELECT ce.id, sp.full_name, sp.student_number, c.code, c.name, ce.academic_year 
                                FROM course_enrollment ce 
                                JOIN student_profile sp ON ce.student_user_id = sp.user_id 
                                JOIN course c ON ce.course_id = c.id 
                                ORDER BY sp.full_name, ce.academic_year, c.code
                            ");
                            foreach ($enrollments as $enrollment) {
                                echo '<option value="' . $enrollment['id'] . '">' . 
                                     htmlspecialchars($enrollment['full_name']) . ' (' . htmlspecialchars($enrollment['student_number']) . ') - ' .
                                     htmlspecialchars($enrollment['code']) . ' - ' . 
                                     htmlspecialchars($enrollment['name']) . ' (' . htmlspecialchars($enrollment['academic_year']) . ')</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="course_comment">Comment:</label>
                        <textarea id="course_comment" name="comment" rows="3" placeholder="Enter course comment" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Comment</button>
                </form>
            </div>
        </div>
    </main>

    <!-- Add Result Type Modal -->
    <div id="addTypeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addTypeModal')">&times;</span>
            <h2>Add Result Type</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_result_type">
                <div class="form-group">
                    <label for="name">Name (e.g., CA, Final Exam)</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="weight">Weight (%)</label>
                    <input type="number" id="weight" name="weight" required min="0" max="100" step="0.1">
                </div>
                <button type="submit" class="btn btn-primary">Add</button>
            </form>
        </div>
    </div>

    <!-- Edit Result Type Modal -->
    <div id="editTypeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editTypeModal')">&times;</span>
            <h2>Edit Result Type</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_result_type">
                <input type="hidden" id="edit_type_id" name="type_id">
                <div class="form-group">
                    <label for="edit_name">Name</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit_weight">Weight (%)</label>
                    <input type="number" id="edit_weight" name="weight" required min="0" max="100" step="0.1">
                </div>
                <button type="submit" class="btn btn-primary">Update</button>
            </form>
        </div>
    </div>

    <!-- Add Grade Modal -->
    <div id="addGradeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addGradeModal')">&times;</span>
            <h2>Add Grade</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_grade">
                <div class="form-group">
                    <label for="min_score">Min Score (%)</label>
                    <input type="number" id="min_score" name="min_score" required min="0" max="100" step="0.1">
                </div>
                <div class="form-group">
                    <label for="max_score">Max Score (%)</label>
                    <input type="number" id="max_score" name="max_score" required min="0" max="100" step="0.1">
                </div>
                <div class="form-group">
                    <label for="grade">Grade (e.g., A+)</label>
                    <input type="text" id="grade" name="grade" required maxlength="5">
                </div>
                <div class="form-group">
                    <label for="points">Points (e.g., 4.0)</label>
                    <input type="number" id="points" name="points" required min="0" max="4" step="0.1">
                </div>
                <button type="submit" class="btn btn-primary">Add</button>
            </form>
        </div>
    </div>

    <!-- Edit Grade Modal -->
    <div id="editGradeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editGradeModal')">&times;</span>
            <h2>Edit Grade</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_grade">
                <input type="hidden" id="edit_grade_id" name="grade_id">
                <div class="form-group">
                    <label for="edit_min_score">Min Score (%)</label>
                    <input type="number" id="edit_min_score" name="min_score" required min="0" max="100" step="0.1">
                </div>
                <div class="form-group">
                    <label for="edit_max_score">Max Score (%)</label>
                    <input type="number" id="edit_max_score" name="max_score" required min="0" max="100" step="0.1">
                </div>
                <div class="form-group">
                    <label for="edit_grade">Grade</label>
                    <input type="text" id="edit_grade" name="grade" required maxlength="5">
                </div>
                <div class="form-group">
                    <label for="edit_points">Points</label>
                    <input type="number" id="edit_points" name="points" required min="0" max="4" step="0.1">
                </div>
                <button type="submit" class="btn btn-primary">Update</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function openEditTypeModal(type) {
            document.getElementById('edit_type_id').value = type.id;
            document.getElementById('edit_name').value = type.name;
            document.getElementById('edit_weight').value = type.weight;
            openModal('editTypeModal');
        }
        
        function openEditGradeModal(grade) {
            document.getElementById('edit_grade_id').value = grade.id;
            document.getElementById('edit_min_score').value = grade.min_score;
            document.getElementById('edit_max_score').value = grade.max_score;
            document.getElementById('edit_grade').value = grade.grade;
            document.getElementById('edit_points').value = grade.points;
            openModal('editGradeModal');
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = "none";
                }
            }
        }
    </script>
</body>
</html>