<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in and has lecturer role
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit;
}

requireRole('Lecturer', $pdo);

// Get lecturer profile
$stmt = $pdo->prepare("SELECT sp.full_name, sp.staff_id FROM staff_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$lecturer = $stmt->fetch();

// Handle form submissions for updating results
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_result'])) {
    $student_id = $_POST['student_id'] ?? '';
    $course_id = $_POST['course_id'] ?? '';
    $type = $_POST['type'] ?? '';
    $score = $_POST['score'] ?? '';

    if (empty($student_id) || empty($course_id) || empty($type) || !is_numeric($score)) {
        $errors[] = "Invalid input.";
    } else {
        // Check if lecturer is assigned to course
        $stmt = $pdo->prepare("SELECT * FROM course_assignment WHERE course_id = ? AND lecturer_id = ?");
        $stmt->execute([$course_id, currentUserId()]);
        if (!$stmt->fetch()) {
            $errors[] = "You are not assigned to this course.";
        } else {
            // Check if student is enrolled (using correct table and column names)
            $stmt = $pdo->prepare("
                SELECT ce.id 
                FROM course_enrollment ce 
                JOIN student_profile sp ON ce.student_user_id = sp.user_id 
                WHERE sp.student_number = ? AND ce.course_id = ?
            ");
            $stmt->execute([$student_id, $course_id]);
            $enrollment = $stmt->fetch();
            
            if (!$enrollment) {
                $errors[] = "Student not enrolled in this course.";
            } else {
                $enrollment_id = $enrollment['id'];
                
                // Update or insert result (using correct table structure)
                if ($type == 'CA') {
                    $stmt = $pdo->prepare("
                        INSERT INTO results (enrollment_id, ca_score, uploaded_by_user_id, uploaded_at) 
                        VALUES (?, ?, ?, NOW()) 
                        ON DUPLICATE KEY UPDATE ca_score = ?, uploaded_at = NOW()
                    ");
                    $stmt->execute([$enrollment_id, $score, currentUserId(), $score]);
                } else if ($type == 'Exam') {
                    $stmt = $pdo->prepare("
                        INSERT INTO results (enrollment_id, exam_score, uploaded_by_user_id, uploaded_at) 
                        VALUES (?, ?, ?, NOW()) 
                        ON DUPLICATE KEY UPDATE exam_score = ?, uploaded_at = NOW()
                    ");
                    $stmt->execute([$enrollment_id, $score, currentUserId(), $score]);
                }
                $success = true;
            }
        }
    }
}

// Get lecturer's assigned courses
$stmt = $pdo->prepare("
    SELECT c.id, c.code, c.name 
    FROM course c 
    JOIN course_assignment ca ON c.id = ca.course_id 
    WHERE ca.lecturer_id = ?
");
$stmt->execute([currentUserId()]);
$courses = $stmt->fetchAll();

// Get selected course_id from GET or POST
$selected_course_id = $_GET['course_id'] ?? ($_POST['course_id'] ?? ($courses[0]['id'] ?? ''));

// Get filter student_id
$filter_student_id = $_GET['student_id'] ?? '';

// Fetch enrolled students for selected course
$students = [];
$results = [];
if ($selected_course_id) {
    $query = "
        SELECT sp.student_number as student_id, sp.full_name 
        FROM student_profile sp 
        JOIN course_enrollment e ON sp.user_id = e.student_user_id 
        WHERE e.course_id = ?
    ";
    if ($filter_student_id) {
        $query .= " AND sp.student_number = ?";
    }
    $query .= " ORDER BY sp.full_name";
    
    $stmt = $pdo->prepare($query);
    $params = [$selected_course_id];
    if ($filter_student_id) {
        $params[] = $filter_student_id;
    }
    $stmt->execute($params);
    $students = $stmt->fetchAll();

    // Fetch results for these students
    if (!empty($students)) {
        $student_ids = array_column($students, 'student_id');
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        
        // We need to join with course_enrollment to get the enrollment_id for each student
        $stmt = $pdo->prepare("
            SELECT r.ca_score, r.exam_score, sp.student_number as student_id
            FROM results r
            JOIN course_enrollment ce ON r.enrollment_id = ce.id
            JOIN student_profile sp ON ce.student_user_id = sp.user_id
            WHERE ce.course_id = ? AND sp.student_number IN ($placeholders)
        ");
        $stmt->execute(array_merge([$selected_course_id], $student_ids));
        $raw_results = $stmt->fetchAll();
        
        foreach ($raw_results as $res) {
            $results[$res['student_id']]['CA'] = $res['ca_score'];
            $results[$res['student_id']]['Exam'] = $res['exam_score'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Students - LSC SRMS</title>
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($lecturer['full_name'] ?? 'Lecturer'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($lecturer['staff_id'] ?? 'N/A'); ?>)</span>
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
            <h3><i class="fas fa-tachometer-alt"></i> Lecturer Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Results Management</h4>
                <a href="upload_results.php" class="nav-item">
                    <i class="fas fa-upload"></i>
                    <span>Upload Results</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Student Management</h4>
                <a href="view_students.php" class="nav-item active">
                    <i class="fas fa-users"></i>
                    <span>View Students</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports</h4>
                <a href="manage_reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Manage Reports</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Profile</h4>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>View Profile</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-users"></i> View Students</h1>
            <p>View and manage students enrolled in your courses</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                <p>Result updated successfully!</p>
            </div>
        <?php endif; ?>
        
        <div class="filter-form">
            <form method="GET">
                <div class="form-group">
                    <label for="course_id">Select Course:</label>
                    <select name="course_id" id="course_id" onchange="this.form.submit()">
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo $course['id'] == $selected_course_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['code'] . ' - ' . $course['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="student_id">Filter by Student ID:</label>
                    <input type="text" name="student_id" id="student_id" value="<?php echo htmlspecialchars($filter_student_id); ?>">
                    <button type="submit" class="btn primary">Filter</button>
                </div>
            </form>
        </div>
        
        <?php if ($selected_course_id): ?>
            <div class="students-table">
                <h2>Enrolled Students</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>CA Score</th>
                            <th>Exam Score</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="5">No students found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="update_result" value="1">
                                            <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                            <input type="hidden" name="course_id" value="<?php echo $selected_course_id; ?>">
                                            <input type="hidden" name="type" value="CA">
                                            <input type="number" name="score" value="<?php echo htmlspecialchars($results[$student['student_id']]['CA'] ?? ''); ?>" min="0" max="100" style="width: 60px;">
                                            <button type="submit" class="btn small">Update</button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="update_result" value="1">
                                            <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                            <input type="hidden" name="course_id" value="<?php echo $selected_course_id; ?>">
                                            <input type="hidden" name="type" value="Exam">
                                            <input type="number" name="score" value="<?php echo htmlspecialchars($results[$student['student_id']]['Exam'] ?? ''); ?>" min="0" max="100" style="width: 60px;">
                                            <button type="submit" class="btn small">Update</button>
                                        </form>
                                    </td>
                                    <td>
                                        <!-- Additional actions if needed -->
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>