<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has student role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Student', $pdo);

// Get student profile
$stmt = $pdo->prepare("SELECT sp.full_name, sp.student_number as student_id, sp.programme_id FROM student_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$student = $stmt->fetch();

// Get current academic year (assume from config or database, here placeholder)
$current_year = date('Y'); // Or fetch from intakes or config

// Check if already registered for current year
$stmt = $pdo->prepare("SELECT * FROM course_enrollment WHERE student_user_id = ? AND academic_year = ? LIMIT 1");
$stmt->execute([currentUserId(), $current_year]);
$existing_registration = $stmt->fetch();

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing_registration) {
    $selected_courses = $_POST['courses'] ?? [];
    if (empty($selected_courses)) {
        $message = 'Please select at least one course.';
    } else {
        // Insert enrollments for each selected course
        foreach ($selected_courses as $course_id) {
            $stmt = $pdo->prepare("INSERT INTO course_enrollment (student_user_id, course_id, academic_year, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([currentUserId(), $course_id, $current_year]);
        }
        $message = 'Registration submitted successfully. Awaiting approval.';
        $existing_registration = true; // Refresh status
    }
}

// Fetch available courses for student's programme
$stmt = $pdo->prepare("SELECT c.id as course_id, c.code as course_code, c.name as course_name FROM course c WHERE c.programme_id = ?");
$stmt->execute([$student['programme_id']]);
$available_courses = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Courses - LSC SRMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            <p>Register for courses in the current academic year: <?php echo $current_year; ?></p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($existing_registration): ?>
            <div class="registration-status">
                <h2>Registration Status</h2>
                <p>Status: <strong>Pending Approval</strong></p>
                <p>Your course registration is awaiting approval from the administration.</p>
            </div>
        <?php else: ?>
            <form method="POST" class="registration-form">
                <h2>Select Courses</h2>
                <div class="courses-list">
                    <?php foreach ($available_courses as $course): ?>
                        <div class="course-item">
                            <input type="checkbox" name="courses[]" value="<?php echo $course['course_id']; ?>" id="course_<?php echo $course['course_id']; ?>">
                            <label for="course_<?php echo $course['course_id']; ?>">
                                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" class="btn-submit">Submit Registration</button>
            </form>
        <?php endif; ?>
    </main>

    <script src="../assets/js/student-dashboard.js"></script>
</body>
</html>