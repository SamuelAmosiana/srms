<?php
require '../config.php';
require '../auth.php';
require '../lib/fpdf/fpdf.php'; // Use FPDF instead of Dompdf

// Check if user is logged in and has student role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Student', $pdo);

// Get student profile (using only existing columns)
$stmt = $pdo->prepare("SELECT sp.full_name, sp.student_number as student_id, sp.NRC as nrc_number, sp.gender, sp.intake_id FROM student_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$student = $stmt->fetch();

// Get programme name
$stmt = $pdo->prepare("SELECT name FROM programme WHERE id = ?");
$stmt->execute([$student['programme_id'] ?? null]);
$programme = $stmt->fetch()['name'] ?? 'N/A';

// Get intake details (assuming intake table has 'name' or 'date')
$intake = 'N/A';
if (!empty($student['intake_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM intake WHERE id = ?");
        $stmt->execute([$student['intake_id']]);
        $intake = $stmt->fetch()['name'] ?? 'N/A';
    } catch (Exception $e) {
        // Intake table might not exist yet
        $intake = 'N/A';
    }
}

// Get current academic year
$current_year = date('Y');

// Get enrollment details (using course_enrollment table instead of course_registration)
$stmt = $pdo->prepare("SELECT status FROM course_enrollment WHERE student_user_id = ? AND academic_year = ? LIMIT 1");
$stmt->execute([currentUserId(), $current_year]);
$enrollment = $stmt->fetch();
$registration_status = $enrollment['status'] ?? 'Not Registered';

// Fetch enrolled courses
$courses = [];
$stmt = $pdo->prepare("
    SELECT c.code as course_code, c.name as course_name 
    FROM course_enrollment ce 
    JOIN course c ON ce.course_id = c.id 
    WHERE ce.student_user_id = ? AND ce.academic_year = ?
");
$stmt->execute([currentUserId(), $current_year]);
$courses = $stmt->fetchAll();

// Handle PDF download
if (isset($_GET['download'])) {
    // Create new PDF document
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    
    // Title
    $pdf->Cell(0, 10, 'LSC SRMS Student Docket', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Exam Pass', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Student Information
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 10, 'Student Number:', 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, htmlspecialchars_decode($student['student_id']), 0, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 10, 'NRC Number:', 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, htmlspecialchars_decode($student['nrc_number'] ?? 'N/A'), 0, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 10, 'Gender:', 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, htmlspecialchars_decode($student['gender'] ?? 'N/A'), 0, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 10, 'Current Programme:', 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, htmlspecialchars_decode($programme), 0, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 10, 'Intake:', 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, htmlspecialchars_decode($intake), 0, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 10, 'Registration Status:', 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, htmlspecialchars_decode($registration_status), 0, 1);
    
    // Registered Courses Section
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Registered Courses', 0, 1);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 10, 'Course Code', 1);
    $pdf->Cell(0, 10, 'Course Name', 1);
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 12);
    
    foreach ($courses as $course) {
        $pdf->Cell(40, 10, htmlspecialchars_decode($course['course_code']), 1);
        $pdf->Cell(0, 10, htmlspecialchars_decode($course['course_name']), 1);
        $pdf->Ln();
    }
    
    // Output PDF
    $pdf->Output('D', "student_docket_" . $student['student_id'] . ".pdf");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Docket - LSC SRMS</title>
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
                <a href="register_courses.php" class="nav-item">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Register Courses</span>
                </a>
                <a href="view_docket.php" class="nav-item active">
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
            <h1><i class="fas fa-file-alt"></i> View Docket</h1>
            <p>Your student docket for exam access</p>
        </div>
        
        <?php if ($registration_status !== 'approved'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Your registration is <?php echo htmlspecialchars($registration_status); ?>. Docket may not be complete.
            </div>
        <?php endif; ?>
        
        <div class="docket-preview">
            <div class="info"><strong>Student Number:</strong> <?php echo htmlspecialchars($student['student_id']); ?></div>
            <div class="info"><strong>NRC Number:</strong> <?php echo htmlspecialchars($student['nrc_number'] ?? 'N/A'); ?></div>
            <div class="info"><strong>Gender:</strong> <?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></div>
            <div class="info"><strong>Current Programme:</strong> <?php echo htmlspecialchars($programme); ?></div>
            <div class="info"><strong>Intake:</strong> <?php echo htmlspecialchars($intake); ?></div>
            <div class="info"><strong>Registration Status:</strong> <?php echo htmlspecialchars($registration_status); ?></div>
            
            <h2>Registered Courses</h2>
            <?php if (!empty($courses)): ?>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No courses registered yet.</p>
            <?php endif; ?>
        </div>
        
        <a href="?download=1" class="action-card green" style="display: inline-block; margin-top: 20px; text-decoration: none;">
            <i class="fas fa-download"></i>
            <h3>Download as PDF</h3>
        </a>
    </main>

    <script src="../assets/js/student-dashboard.js"></script>
</body>
</html>