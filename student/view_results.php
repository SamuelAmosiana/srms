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

// Get programme name
$stmt = $pdo->prepare("SELECT name FROM programme WHERE id = ?");
$stmt->execute([$student['programme_id']]);
$programme = $stmt->fetch()['name'] ?? 'N/A';

// Get fee balance and access (using balance column from student_profile)
$stmt = $pdo->prepare("SELECT balance FROM student_profile WHERE user_id = ?");
$stmt->execute([currentUserId()]);
$fees = $stmt->fetch();
$balance = $fees['balance'] ?? 0;
$access_granted = ($balance == 0); // Grant access if balance is zero

// Fetch all results grouped by academic year
$results_by_year = [];
$stmt = $pdo->prepare("
    SELECT r.ca_score, r.exam_score, r.grade,
           c.code as course_code, c.name as course_name,
           ce.academic_year
    FROM results r
    JOIN course_enrollment ce ON r.enrollment_id = ce.id
    JOIN course c ON ce.course_id = c.id
    WHERE ce.student_user_id = ?
    ORDER BY ce.academic_year DESC, c.code ASC
");
$stmt->execute([currentUserId()]);
$results = $stmt->fetchAll();

foreach ($results as $result) {
    $year = $result['academic_year'];
    if (!isset($results_by_year[$year])) {
        $results_by_year[$year] = [];
    }
    $results_by_year[$year][] = $result;
}

// For each year, compute GPA, failed courses, comment
$year_data = [];
foreach ($results_by_year as $year => $courses) {
    $total_courses = count($courses);
    $failed = 0;
    $total_grade_points = 0;
    
    foreach ($courses as $course) {
        // Convert grade to grade points (assuming A=4, B=3, C=2, D=1, F=0)
        $grade_points = 0;
        $grade = $course['grade'] ?? '';
        switch (strtoupper($grade)) {
            case 'A': $grade_points = 4; break;
            case 'B': $grade_points = 3; break;
            case 'C': $grade_points = 2; break;
            case 'D': $grade_points = 1; break;
            case 'F': $grade_points = 0; break;
        }
        
        $total_grade_points += $grade_points;
        if ($grade_points == 0) { // Assume fail for F grade
            $failed++;
        }
    }
    
    $gpa = $total_courses > 0 ? number_format($total_grade_points / $total_courses, 2) : 0;

    if ($failed == 0) {
        $comment = 'Clear Pass';
    } elseif ($failed / $total_courses >= 0.5) {
        $comment = 'Repeat Year';
    } else {
        $comment = 'Repeat Course(s)';
    }

    $year_data[$year] = [
        'gpa' => $gpa,
        'comment' => $comment,
        'courses' => $courses
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Results - LSC SRMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .results-section {
            margin-bottom: 30px;
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .results-section h2 {
            color: var(--primary-green);
            margin-bottom: 15px;
        }
        
        /* Adjust column widths for results table */
        .results-table {
            table-layout: fixed;
        }
        
        .results-table th:nth-child(1),
        .results-table td:nth-child(1) { /* Course Code */
            width: 15%;
        }
        
        .results-table th:nth-child(2),
        .results-table td:nth-child(2) { /* Course Name */
            width: 45%;
        }
        
        .results-table th:nth-child(3),
        .results-table td:nth-child(3) { /* CA Score */
            width: 15%;
        }
        
        .results-table th:nth-child(4),
        .results-table td:nth-child(4) { /* Exam Score */
            width: 15%;
        }
        
        .results-table th:nth-child(5),
        .results-table td:nth-child(5) { /* Final Grade */
            width: 10%;
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
                <a href="view_results.php" class="nav-item active">
                    <i class="fas fa-chart-line"></i>
                    <span>View Results</span>
                </a>
                <a href="register_courses.php" class="nav-item">
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
            <h1><i class="fas fa-chart-line"></i> View Results</h1>
            <p>Your academic results and transcript</p>
        </div>
        
        <?php if ($balance > 0 && !$access_granted): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Your fee balance is K<?php echo number_format($balance, 2); ?>. Access to exam results is restricted. You can only view CA results. Please contact finance to settle your balance.
            </div>
        <?php endif; ?>

        <?php foreach ($year_data as $year => $data): ?>
            <div class="results-section">
                <h2>Academic Year: <?php echo htmlspecialchars($year); ?></h2>
                <p>Programme: <?php echo htmlspecialchars($programme); ?></p>
                <p>GPA: <?php echo $data['gpa']; ?></p>
                <p>Comment: <?php echo htmlspecialchars($data['comment']); ?></p>
                
                <table class="results-table data-table">
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>CA Score</th>
                            <?php if ($access_granted): ?>
                                <th>Exam Score</th>
                                <th>Final Grade</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['courses'] as $course): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($course['ca_score'] ?? 'N/A'); ?></td>
                                <?php if ($access_granted): ?>
                                    <td><?php echo htmlspecialchars($course['exam_score'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($course['grade'] ?? 'N/A'); ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($year_data)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No results available yet.
            </div>
        <?php endif; ?>
    </main>

    <script src="../assets/js/student-dashboard.js"></script>
</body>
</html>