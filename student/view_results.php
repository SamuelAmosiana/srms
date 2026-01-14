<?php
require '../config.php';
require '../auth/auth.php';

// Function to calculate grade based on total score and grading scale
function calculateGrade($total_score, $grading_scale) {
    foreach ($grading_scale as $scale) {
        if ($total_score >= $scale['min_score'] && $total_score <= $scale['max_score']) {
            return $scale['grade'];
        }
    }
    return 'N/A'; // Return N/A if no matching grade found
}

// Function to get grade points from grading scale
function getGradePoints($grade, $grading_scale) {
    foreach ($grading_scale as $scale) {
        if (strtoupper($scale['grade']) === strtoupper($grade)) {
            return $scale['points'];
        }
    }
    // Fallback for hardcoded grades if not found in scale
    switch (strtoupper($grade)) {
        case 'A': return 4;
        case 'B+': return 3.5;
        case 'B': return 3;
        case 'C': return 2;
        case 'D': return 1;
        case 'F': return 0;
        default: return 0;
    }
}

// Check if user is logged in and has student role
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit;
}

requireRole('Student', $pdo);

// Get student profile
$stmt = $pdo->prepare("SELECT sp.full_name, sp.student_number as student_id, sp.programme_id, sp.gender FROM student_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$student = $stmt->fetch();

// Get programme name
$stmt = $pdo->prepare("SELECT name FROM programme WHERE id = ?");
$stmt->execute([$student['programme_id']]);
$programme = $stmt->fetch()['name'] ?? 'N/A';

// Get fee balance and results access (using balance and results_access columns from student_profile)
$stmt = $pdo->prepare("SELECT balance, results_access FROM student_profile WHERE user_id = ?");
$stmt->execute([currentUserId()]);
$student_data = $stmt->fetch();
$balance = $student_data['balance'] ?? 0;
$results_access = $student_data['results_access'] ?? 1; // Default to granted if column doesn't exist
$access_granted = ($balance == 0 && $results_access == 1); // Grant access if balance is zero AND results access is granted

// Fetch grading scale for grade calculation
$grading_scale = [];
$stmt = $pdo->query("SELECT min_score, max_score, grade, points FROM grading_scale ORDER BY min_score DESC");
$grading_scale = $stmt->fetchAll();

// Fetch all results grouped by academic year with course credits and admin comments
$results_by_year = [];
$stmt = $pdo->prepare("
    SELECT r.ca_score, r.exam_score, r.grade, r.admin_comment,
           c.code as course_code, c.name as course_name, c.credits,
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
    
    // Calculate total score and grade if not already present
    $ca_score = $result['ca_score'] ?? 0;
    $exam_score = $result['exam_score'] ?? 0;
    $total_score = $ca_score + $exam_score;
    
    // Always calculate grade based on current scores to reflect grading scale
    $calculated_grade = calculateGrade($total_score, $grading_scale);
    $result['grade'] = $calculated_grade;
    
    // Store the total score for potential use in grade calculation
    $result['total_score'] = $total_score;
    
    $results_by_year[$year][] = $result;
}

// Fetch academic year comments added by admins
$academic_year_comments = [];
$stmt = $pdo->prepare("
    SELECT academic_year, comment 
    FROM academic_year_comments 
    WHERE student_user_id = ?
");
$stmt->execute([currentUserId()]);
$year_comments = $stmt->fetchAll();
foreach ($year_comments as $comment) {
    $academic_year_comments[$comment['academic_year']] = $comment['comment'];
}

// For each year, compute GPA, total credits, failed courses, comment
$year_data = [];
$gpa_data = [];
foreach ($results_by_year as $year => $courses) {
    $total_courses = count($courses);
    $failed = 0;
    $total_grade_points = 0;
    $total_credits = 0;
    
    foreach ($courses as $course) {
        // Get grade points from grading scale
        $grade_points = getGradePoints($course['grade'], $grading_scale);
        $credits = $course['credits'] ?? 0;
        $total_grade_points += $grade_points * $credits;
        $total_credits += $credits;
        
        if ($grade_points == 0) { // Assume fail for 0 grade points
            $failed++;
        }
    }
    
    // Calculate GPA using weighted average: (Sum of credits * grade points) / Sum of credits
    $gpa = $total_credits > 0 ? round($total_grade_points / $total_credits, 2) : 0;

    // Get admin comment for this academic year, fallback to system-generated comment
    $admin_comment = $academic_year_comments[$year] ?? '';
    
    if (!empty($admin_comment)) {
        $comment = $admin_comment;
    } else {
        if ($failed == 0) {
            $comment = 'CLEAR PASS';
        } elseif ($failed / $total_courses >= 0.5) {
            $comment = 'REPEAT YEAR';
        } else {
            $comment = 'REPEAT COURSE(S)';
        }
    }
    
    // For students with no credits, set GPA to 0
    if ($total_credits == 0) {
        $gpa = 0;
    }

    $year_data[$year] = [
        'gpa' => $gpa,
        'credits' => $total_credits,
        'comment' => $comment,
        'courses' => $courses
    ];
    
    $gpa_data[] = [
        'year' => $year,
        'credits' => $total_credits,
        'gpa' => $gpa
    ];
}

// Fetch CA results for current academic year
$current_year = date('Y');
$ca_results = [];
$stmt = $pdo->prepare("
    SELECT r.ca_score,
           c.code as course_code, c.name as course_name,
           ce.academic_year
    FROM results r
    JOIN course_enrollment ce ON r.enrollment_id = ce.id
    JOIN course c ON ce.course_id = c.id
    WHERE ce.student_user_id = ? AND ce.academic_year = ?
    ORDER BY c.code ASC
");
$stmt->execute([currentUserId(), $current_year]);
$ca_results = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Results - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .student-info {
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--shadow);
            margin-bottom: 30px;
        }
        
        .student-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            display: flex;
        }
        
        .info-label {
            font-weight: bold;
            width: 200px;
            color: var(--text-dark);
        }
        
        .info-value {
            flex: 1;
            color: var(--text-dark);
        }
        
        .section-title {
            color: var(--primary-green);
            margin: 25px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-green);
        }
        
        .gpa-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: var(--white);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .gpa-table th,
        .gpa-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .gpa-table th {
            background-color: #228B22; /* Green color */
            font-weight: 600;
            color: white;
        }
        
        .gpa-table tbody tr:hover {
            background-color: rgba(34, 139, 34, 0.05);
        }
        
        .results-section {
            margin-bottom: 40px;
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .results-section h3 {
            color: var(--primary-green);
            margin-bottom: 15px;
        }
        
        .year-info {
            display: flex;
            gap: 30px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .year-info-item {
            display: flex;
        }
        
        .year-info-label {
            font-weight: bold;
            margin-right: 10px;
        }
        
        /* Adjust column widths for results table */
        .results-table {
            table-layout: fixed;
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: var(--white);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .results-table th,
        .results-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .results-table th {
            background-color: #228B22; /* Green color */
            font-weight: 600;
            color: white;
        }
        
        .results-table tbody tr:hover {
            background-color: rgba(34, 139, 34, 0.05);
        }
        
        .results-table th:nth-child(1),
        .results-table td:nth-child(1) { /* Academic Year */
            width: 15%;
        }
        
        .results-table th:nth-child(2),
        .results-table td:nth-child(2) { /* Programme */
            width: 25%;
        }
        
        .results-table th:nth-child(3),
        .results-table td:nth-child(3) { /* Year of Study */
            width: 10%;
        }
        
        .results-table th:nth-child(4),
        .results-table td:nth-child(4) { /* Course */
            width: 25%;
        }
        
        .results-table th:nth-child(5),
        .results-table td:nth-child(5) { /* Credits */
            width: 8%;
        }
        
        .results-table th:nth-child(6),
        .results-table td:nth-child(6) { /* Grade */
            width: 8%;
        }
        
        .results-table th:nth-child(7),
        .results-table td:nth-child(7) { /* Comment */
            width: 9%;
        }
        
        .no-results {
            text-align: center;
            padding: 20px;
            color: var(--text-light);
        }
        
        .note {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
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
                <a href="elearning.php" class="nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span>E-Learning (Moodle)</span>
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
        
        <?php if ((int)$results_access === 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Access to exam results has been restricted by the Finance Department. Please contact the Finance Department for assistance.
            </div>
        <?php elseif ($balance > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Your fee balance is K<?php echo number_format($balance, 2); ?>. Access to exam results is restricted until your balance is cleared. You can only view CA results.
            </div>
        <?php endif; ?>
        
        <!-- Student Information -->
        <div class="student-info">
            <div class="student-info-grid">
                <div class="info-item">
                    <div class="info-label">Computer Number:</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Names:</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['full_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Gender:</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Continuous Assessment Results -->
        <h2 class="section-title">Continuous Assessment Results [Current Academic Year]</h2>
        <?php if (!empty($ca_results)): ?>
            <table class="gpa-table">
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>CA Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ca_results as $ca_result): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ca_result['course_code']); ?></td>
                            <td><?php echo htmlspecialchars($ca_result['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($ca_result['ca_score']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-results">
                There are no Continuous Assessment Results available for now
            </div>
        <?php endif; ?>
        
        <!-- Examination Results & GPA Computation -->
        <?php if ($access_granted): ?>
        <h2 class="section-title">Examination Results & GPA Computation</h2>
        
        <div class="note">
            <i class="fas fa-info-circle"></i> Note: GPA per Academic Year is computed by ((Course Credits * grade points)/sum of credits)
        </div>
        
        <table class="gpa-table">
            <thead>
                <tr>
                    <th>Session</th>
                    <th>Credits</th>
                    <th>GPA</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($gpa_data)): ?>
                    <?php foreach ($gpa_data as $gpa_entry): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($gpa_entry['year']); ?></td>
                            <td><?php echo htmlspecialchars($gpa_entry['credits']); ?></td>
                            <td><?php echo htmlspecialchars($gpa_entry['gpa']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="no-results">No GPA data available</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Detailed Results by Academic Year -->
        <?php if (!empty($year_data)): ?>
            <?php foreach ($year_data as $year => $data): ?>
                <div class="results-section">
                    <h3>Academic Year: <?php echo htmlspecialchars($year); ?></h3>
                    
                    <div class="year-info">
                        <div class="year-info-item">
                            <div class="year-info-label">Programme:</div>
                            <div><?php echo htmlspecialchars($programme); ?></div>
                        </div>
                        <div class="year-info-item">
                            <div class="year-info-label">Year of Study:</div>
                            <div><?php 
                                // Extract year from academic year (e.g., 2024/2025 -> 1st Year, 2nd Year, etc.)
                                $year_parts = explode('/', $year);
                                $start_year = intval($year_parts[0]);
                                $current_year = date('Y');
                                $study_year = $current_year - $start_year + 1;
                                echo $study_year . ordinal($study_year) . ' Year';
                            ?></div>
                        </div>
                        <div class="year-info-item">
                            <div class="year-info-label">Credits:</div>
                            <div><?php echo htmlspecialchars($data['credits']); ?></div>
                        </div>
                        <div class="year-info-item">
                            <div class="year-info-label">GPA:</div>
                            <div><?php echo htmlspecialchars($data['gpa']); ?></div>
                        </div>
                        <div class="year-info-item">
                            <div class="year-info-label">Comment:</div>
                            <div><?php echo htmlspecialchars($data['comment']); ?></div>
                        </div>
                    </div>
                    
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Session</th>
                                <th>Programme</th>
                                <th>Year of study</th>
                                <th>Course</th>
                                <th>Credits</th>
                                <th>Grade</th>
                                <th>Comment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['courses'] as $course): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($year); ?></td>
                                    <td><?php echo htmlspecialchars($programme); ?></td>
                                    <td><?php 
                                        $year_parts = explode('/', $year);
                                        $start_year = intval($year_parts[0]);
                                        $current_year = date('Y');
                                        $study_year = $current_year - $start_year + 1;
                                        echo $study_year . ordinal($study_year) . ' Year';
                                    ?></td>
                                    <td><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['credits'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($course['grade'] ?? 'N/A'); ?></td>
                                    <td><?php 
                                        $comment = !empty($course['admin_comment']) ? $course['admin_comment'] : $data['comment'];
                                        echo !empty($comment) ? htmlspecialchars($comment) : 'No Comment';
                                    ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No results available yet.
            </div>
        <?php endif; ?>        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Exam results and GPA are currently unavailable due to finance restrictions. You can view your Continuous Assessment results above.
            </div>
        <?php endif; ?>
    </main>

    <script src="../assets/js/student-dashboard.js"></script>
    
    <?php
    // Helper function to add ordinal suffix to numbers
    function ordinal($number) {
        $ends = array('th','st','nd','rd','th','th','th','th','th','th');
        if ((($number % 100) >= 11) && (($number%100) <= 13))
            return 'th';
        else
            return $ends[$number % 10];
    }
    ?>
</body>
</html>