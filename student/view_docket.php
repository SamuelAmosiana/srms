<?php
require '../config.php';
require '../auth/auth.php';
require '../lib/fpdf/fpdf.php';

// Check if user is logged in and has student role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Student', $pdo);

// Get student profile with balance information and profile photo
$stmt = $pdo->prepare("
    SELECT sp.*, u.email, u.contact, p.name as programme_name 
    FROM student_profile sp 
    JOIN users u ON sp.user_id = u.id 
    LEFT JOIN programme p ON sp.programme_id = p.id
    WHERE sp.user_id = ?
");
$stmt->execute([currentUserId()]);
$student = $stmt->fetch();

if (!$student) {
    // Student profile not found
    header('Location: ../student_login.php');
    exit();
}

// Check if student has outstanding balance
$hasBalance = ($student['balance'] ?? 0) > 0;

// Get current academic year
$current_year = date('Y');

// Get enrollment details
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
if (isset($_GET['download']) && !$hasBalance) {
    // Create new PDF document
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Add logo
    $logo_path = '../assets/images/lsc-logo.png';
    if (file_exists($logo_path)) {
        $pdf->Image($logo_path, 85, 10, 40);
    }
    
    $pdf->Ln(30);
    
    // Header
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'LUSAKA SOUTH COLLEGE', 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'OFFICE OF THE REGISTRAR', 0, 1, 'C');
    $pdf->Cell(0, 10, 'STUDENT EXAMINATION DOCKET', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Add student photo if available
    $photo_path = $student['profile_photo'] ?? '';
    if (!empty($photo_path) && file_exists($photo_path)) {
        // Position the photo on the right side
        $pdf->Image($photo_path, 150, 50, 40, 50); // x, y, width, height
    }
    
    // Student Information
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(45, 10, 'Student Number:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, htmlspecialchars_decode($student['student_number'] ?? 'N/A'), 1, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(45, 10, 'Full Name:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, htmlspecialchars_decode($student['full_name'] ?? 'N/A'), 1, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(45, 10, 'NRC Number:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, htmlspecialchars_decode($student['NRC'] ?? 'N/A'), 1, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(45, 10, 'Gender:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, htmlspecialchars_decode($student['gender'] ?? 'N/A'), 1, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(45, 10, 'Email:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, htmlspecialchars_decode($student['email'] ?? 'N/A'), 1, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(45, 10, 'Contact:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, htmlspecialchars_decode($student['contact'] ?? 'N/A'), 1, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(45, 10, 'Programme:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, htmlspecialchars_decode($student['programme_name'] ?? 'N/A'), 1, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(45, 10, 'Registration Status:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, htmlspecialchars_decode($registration_status), 1, 1);
    
    // Registered Courses Section
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Registered Courses', 0, 1);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 10, 'Course Code', 1);
    $pdf->Cell(0, 10, 'Course Name', 1);
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 12);
    
    if (!empty($courses)) {
        foreach ($courses as $course) {
            $pdf->Cell(40, 10, htmlspecialchars_decode($course['course_code']), 1);
            $pdf->Cell(0, 10, htmlspecialchars_decode($course['course_name']), 1);
            $pdf->Ln();
        }
    } else {
        $pdf->Cell(0, 10, 'No courses registered yet.', 1, 1);
    }
    
    // Footer
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'R');
    
    // Output PDF
    $pdf->Output('D', "student_docket_" . ($student['student_number'] ?? 'unknown') . ".pdf");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Examination Docket - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Add this new style for the logo */
        .college-logo {
            text-align: center;
            margin-bottom: 10px;
        }
        .college-logo img {
            height: 80px; /* Adjust as needed */
            width: auto;
        }
        
        body { 
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.5;
        }
        .header { 
            text-align: center; 
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h2 { 
            margin: 0; 
            font-size: 22px;
            font-weight: bold;
        }
        .header h3 { 
            margin: 5px 0 0 0; 
            font-size: 18px;
        }
        .info-section {
            margin-bottom: 30px;
        }
        .info-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px;
            font-size: 14px;
        }
        .info-table td { 
            padding: 8px; 
            border: 1px solid #000; 
            vertical-align: top;
        }
        .info-table td:first-child {
            width: 25%;
            font-weight: bold;
            background-color: #f2f2f2;
        }
        .courses-table { 
            width: 100%; 
            border-collapse: collapse;
            margin-top: 20px;
        }
        .courses-table th, .courses-table td { 
            border: 1px solid #000; 
            padding: 8px; 
            text-align: left;
        }
        .courses-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .footer { 
            margin-top: 40px;
            text-align: right;
        }
        .student-photo {
            float: right;
            width: 120px;
            height: 150px;
            border: 1px solid #000;
            margin-left: 20px;
            margin-bottom: 20px;
        }
        .clearfix {
            clear: both;
        }
        /* Download button styling */
        .download-btn {
            text-align: center;
            margin: 20px 0;
        }
        .download-btn a, .print-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            margin: 0 5px;
            border: none;
            cursor: pointer;
        }
        .download-btn a:hover, .print-btn:hover {
            background-color: #45a049;
        }
        .balance-error {
            color: #d9534f;
            text-align: center;
            font-weight: bold;
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #d9534f;
            border-radius: 4px;
            background-color: #f2dede;
        }
        .no-balance {
            color: #5cb85c;
            text-align: center;
            font-weight: bold;
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #5cb85c;
            border-radius: 4px;
            background-color: #dff0d8;
        }

        /* Print-specific styles */
        @media print {
            .sidebar, .top-nav, .download-btn, .footer {
                display: none;
            }
            
            body {
                margin: 0;
                padding: 20px;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .student-photo {
                float: right;
                width: 120px;
                height: 150px;
                border: 1px solid #000;
                margin-left: 20px;
                margin-bottom: 20px;
            }
            
            .student-photo img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
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
                <span class="staff-id">(<?php echo htmlspecialchars($student['student_number'] ?? 'N/A'); ?>)</span>
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
        
        
        <!-- School Logo -->
        <div class="college-logo">
            <img src="../assets/images/lsc-logo.png" alt="Lusaka South College Logo" onerror="this.style.display='none'">
        </div>

        <div class="header">
            <h2>LUSAKA SOUTH COLLEGE</h2>
            <h3>OFFICE OF THE REGISTRAR</h3>
            <h3>STUDENT EXAMINATION DOCKET</h3>
        </div>
        <div class="content-header">
            
            <p>Your official examination docket</p>
        </div>
        
        <!-- Balance Check -->
        <?php if($hasBalance): ?>
            <div class="balance-error">
                You have an outstanding balance of K<?php echo number_format($student['balance'], 2); ?>. Please clear your fees to download the docket.
            </div>
        <?php else: ?>
            <div class="no-balance">
                Your account is cleared.
            </div>
            
            <div class="download-btn">
                <a href="?download=1"><i class="fas fa-download"></i> Download Docket as PDF</a>
                <button onclick="window.print()" class="print-btn"><i class="fas fa-print"></i> Print Docket</button>
            </div>
        <?php endif; ?>
        
        <!-- Student Photo -->
        <div class="student-photo" style="text-align: center; line-height: 150px;">
            <?php if (!empty($student['profile_photo']) && file_exists($student['profile_photo'])): ?>
                <img src="<?php echo htmlspecialchars($student['profile_photo']); ?>" alt="Student Photo" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
                Photo<br>Not<br>Available
            <?php endif; ?>
        </div>
        
        <!-- Student Information -->
        <div class="info-section">
            <table class="info-table">
                <tr>
                    <td>Student Number:</td>
                    <td><?php echo htmlspecialchars($student['student_number'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Full Name:</td>
                    <td><?php echo htmlspecialchars($student['full_name'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>NRC Number:</td>
                    <td><?php echo htmlspecialchars($student['NRC'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Gender:</td>
                    <td><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Email:</td>
                    <td><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Contact:</td>
                    <td><?php echo htmlspecialchars($student['contact'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Programme:</td>
                    <td><?php echo htmlspecialchars($student['programme_name'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Registration Status:</td>
                    <td><?php echo htmlspecialchars($registration_status); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Registered Courses -->
        <h3>Registered Courses</h3>
        <table class="courses-table">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Name</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($courses)): ?>
                    <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2">No courses registered yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="footer">
            Generated on: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
        
        <div class="clearfix"></div>
    </main>

    <script src="../assets/js/student-dashboard.js"></script>
</body>
</html>