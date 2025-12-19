<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has lecturer role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Lecturer', $pdo);

// Get lecturer profile
$stmt = $pdo->prepare("SELECT sp.full_name, sp.staff_id FROM staff_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$lecturer = $stmt->fetch();

// Get lecturer's assigned courses
$stmt = $pdo->prepare("
    SELECT c.id, c.code, c.name, p.name as programme_name
    FROM course c 
    JOIN course_assignment ca ON c.id = ca.course_id 
    JOIN programme p ON c.programme_id = p.id
    WHERE ca.lecturer_id = ? AND ca.is_active = 1
    ORDER BY c.name
");
$stmt->execute([currentUserId()]);
$courses = $stmt->fetchAll();

// Handle report generation
$message = '';
$messageType = '';

if (isset($_GET['action']) && $_GET['action'] === 'generate_report') {
    $report_type = $_GET['report_type'] ?? '';
    $course_id = $_GET['course_id'] ?? '';
    $format = $_GET['format'] ?? 'pdf';
    
    if (empty($report_type) || empty($course_id)) {
        $message = 'Please select both report type and course.';
        $messageType = 'error';
    } else {
        // Verify lecturer has access to this course
        $stmt = $pdo->prepare("SELECT id FROM course_assignment WHERE course_id = ? AND lecturer_id = ? AND is_active = 1");
        $stmt->execute([$course_id, currentUserId()]);
        if (!$stmt->fetch()) {
            $message = 'You do not have access to this course.';
            $messageType = 'error';
        } else {
            // Generate the report
            switch ($report_type) {
                case 'class_list':
                    generateClassListReport($pdo, $course_id, $format);
                    break;
                case 'results_summary':
                    generateResultsSummaryReport($pdo, $course_id, $format);
                    break;
                case 'detailed_results':
                    generateDetailedResultsReport($pdo, $course_id, $format);
                    break;
                default:
                    $message = 'Invalid report type.';
                    $messageType = 'error';
            }
            exit;
        }
    }
}

// Function to generate class list report
function generateClassListReport($pdo, $course_id, $format) {
    // Get course details
    $stmt = $pdo->prepare("
        SELECT c.code, c.name, p.name as programme_name
        FROM course c
        JOIN programme p ON c.programme_id = p.id
        WHERE c.id = ?
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    // Get enrolled students
    $stmt = $pdo->prepare("
        SELECT sp.student_number, sp.full_name, sp.NRC, i.name as intake_name
        FROM student_profile sp
        JOIN course_enrollment ce ON sp.user_id = ce.student_user_id
        LEFT JOIN intake i ON sp.intake_id = i.id
        WHERE ce.course_id = ? AND ce.status = 'enrolled'
        ORDER BY sp.full_name
    ");
    $stmt->execute([$course_id]);
    $students = $stmt->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="class_list_' . $course['code'] . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Student Number', 'Full Name', 'NRC', 'Intake']);
        foreach ($students as $student) {
            fputcsv($output, [
                $student['student_number'],
                $student['full_name'],
                $student['NRC'] ?? 'N/A',
                $student['intake_name'] ?? 'N/A'
            ]);
        }
        fclose($output);
    } else {
        // PDF generation
        require_once '../lib/fpdf/fpdf.php';
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Class List Report', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Course: ' . $course['code'] . ' - ' . $course['name'], 0, 1);
        $pdf->Cell(0, 10, 'Programme: ' . $course['programme_name'], 0, 1);
        $pdf->Ln(10);
        
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(40, 10, 'Student Number', 1);
        $pdf->Cell(60, 10, 'Full Name', 1);
        $pdf->Cell(40, 10, 'NRC', 1);
        $pdf->Cell(40, 10, 'Intake', 1);
        $pdf->Ln();
        
        $pdf->SetFont('Arial', '', 10);
        foreach ($students as $student) {
            $pdf->Cell(40, 10, $student['student_number'], 1);
            $pdf->Cell(60, 10, $student['full_name'], 1);
            $pdf->Cell(40, 10, $student['NRC'] ?? 'N/A', 1);
            $pdf->Cell(40, 10, $student['intake_name'] ?? 'N/A', 1);
            $pdf->Ln();
        }
        
        $pdf->Output('D', 'class_list_' . $course['code'] . '.pdf');
    }
    exit;
}

// Function to generate results summary report
function generateResultsSummaryReport($pdo, $course_id, $format) {
    // Get course details
    $stmt = $pdo->prepare("
        SELECT c.code, c.name, p.name as programme_name
        FROM course c
        JOIN programme p ON c.programme_id = p.id
        WHERE c.id = ?
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    // Get results summary
    $stmt = $pdo->prepare("
        SELECT 
            sp.student_number,
            sp.full_name,
            r.ca_score,
            r.exam_score,
            r.total_score,
            r.grade
        FROM student_profile sp
        JOIN course_enrollment ce ON sp.user_id = ce.student_user_id
        LEFT JOIN results r ON ce.id = r.enrollment_id
        WHERE ce.course_id = ? AND ce.status = 'enrolled'
        ORDER BY sp.full_name
    ");
    $stmt->execute([$course_id]);
    $results = $stmt->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="results_summary_' . $course['code'] . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Student Number', 'Full Name', 'CA Score', 'Exam Score', 'Total Score', 'Grade']);
        foreach ($results as $result) {
            fputcsv($output, [
                $result['student_number'],
                $result['full_name'],
                $result['ca_score'] ?? 'N/A',
                $result['exam_score'] ?? 'N/A',
                $result['total_score'] ?? 'N/A',
                $result['grade'] ?? 'N/A'
            ]);
        }
        fclose($output);
    } else {
        // PDF generation
        require_once '../lib/fpdf/fpdf.php';
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Results Summary Report', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Course: ' . $course['code'] . ' - ' . $course['name'], 0, 1);
        $pdf->Cell(0, 10, 'Programme: ' . $course['programme_name'], 0, 1);
        $pdf->Ln(10);
        
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(30, 10, 'Student No', 1);
        $pdf->Cell(50, 10, 'Full Name', 1);
        $pdf->Cell(25, 10, 'CA Score', 1);
        $pdf->Cell(25, 10, 'Exam Score', 1);
        $pdf->Cell(25, 10, 'Total', 1);
        $pdf->Cell(25, 10, 'Grade', 1);
        $pdf->Ln();
        
        $pdf->SetFont('Arial', '', 10);
        foreach ($results as $result) {
            $pdf->Cell(30, 10, $result['student_number'], 1);
            $pdf->Cell(50, 10, $result['full_name'], 1);
            $pdf->Cell(25, 10, $result['ca_score'] ?? 'N/A', 1);
            $pdf->Cell(25, 10, $result['exam_score'] ?? 'N/A', 1);
            $pdf->Cell(25, 10, $result['total_score'] ?? 'N/A', 1);
            $pdf->Cell(25, 10, $result['grade'] ?? 'N/A', 1);
            $pdf->Ln();
        }
        
        $pdf->Output('D', 'results_summary_' . $course['code'] . '.pdf');
    }
    exit;
}

// Function to generate detailed results report
function generateDetailedResultsReport($pdo, $course_id, $format) {
    // Get course details
    $stmt = $pdo->prepare("
        SELECT c.code, c.name, p.name as programme_name
        FROM course c
        JOIN programme p ON c.programme_id = p.id
        WHERE c.id = ?
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    // Get detailed results with statistics
    $stmt = $pdo->prepare("
        SELECT 
            sp.student_number,
            sp.full_name,
            r.ca_score,
            r.exam_score,
            r.total_score,
            r.grade
        FROM student_profile sp
        JOIN course_enrollment ce ON sp.user_id = ce.student_user_id
        LEFT JOIN results r ON ce.id = r.enrollment_id
        WHERE ce.course_id = ? AND ce.status = 'enrolled'
        ORDER BY r.total_score DESC, sp.full_name
    ");
    $stmt->execute([$course_id]);
    $results = $stmt->fetchAll();
    
    // Calculate statistics
    $scores = array_filter(array_column($results, 'total_score'));
    $stats = [
        'count' => count($scores),
        'average' => count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0,
        'highest' => count($scores) > 0 ? max($scores) : 0,
        'lowest' => count($scores) > 0 ? min($scores) : 0
    ];
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="detailed_results_' . $course['code'] . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Student Number', 'Full Name', 'CA Score', 'Exam Score', 'Total Score', 'Grade']);
        foreach ($results as $result) {
            fputcsv($output, [
                $result['student_number'],
                $result['full_name'],
                $result['ca_score'] ?? 'N/A',
                $result['exam_score'] ?? 'N/A',
                $result['total_score'] ?? 'N/A',
                $result['grade'] ?? 'N/A'
            ]);
        }
        fputcsv($output, []);
        fputcsv($output, ['Statistics']);
        fputcsv($output, ['Total Students', $stats['count']]);
        fputcsv($output, ['Average Score', $stats['average']]);
        fputcsv($output, ['Highest Score', $stats['highest']]);
        fputcsv($output, ['Lowest Score', $stats['lowest']]);
        fclose($output);
    } else {
        // PDF generation
        require_once '../lib/fpdf/fpdf.php';
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Detailed Results Report', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Course: ' . $course['code'] . ' - ' . $course['name'], 0, 1);
        $pdf->Cell(0, 10, 'Programme: ' . $course['programme_name'], 0, 1);
        $pdf->Ln(5);
        
        // Statistics
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Statistics:', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 8, 'Total Students: ' . $stats['count'], 0, 1);
        $pdf->Cell(0, 8, 'Average Score: ' . $stats['average'], 0, 1);
        $pdf->Cell(0, 8, 'Highest Score: ' . $stats['highest'], 0, 1);
        $pdf->Cell(0, 8, 'Lowest Score: ' . $stats['lowest'], 0, 1);
        $pdf->Ln(5);
        
        // Results table
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(30, 10, 'Student No', 1);
        $pdf->Cell(50, 10, 'Full Name', 1);
        $pdf->Cell(25, 10, 'CA Score', 1);
        $pdf->Cell(25, 10, 'Exam Score', 1);
        $pdf->Cell(25, 10, 'Total', 1);
        $pdf->Cell(25, 10, 'Grade', 1);
        $pdf->Ln();
        
        $pdf->SetFont('Arial', '', 10);
        foreach ($results as $result) {
            $pdf->Cell(30, 10, $result['student_number'], 1);
            $pdf->Cell(50, 10, $result['full_name'], 1);
            $pdf->Cell(25, 10, $result['ca_score'] ?? 'N/A', 1);
            $pdf->Cell(25, 10, $result['exam_score'] ?? 'N/A', 1);
            $pdf->Cell(25, 10, $result['total_score'] ?? 'N/A', 1);
            $pdf->Cell(25, 10, $result['grade'] ?? 'N/A', 1);
            $pdf->Ln();
        }
        
        $pdf->Output('D', 'detailed_results_' . $course['code'] . '.pdf');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reports - LSC SRMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .report-filters {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group select, .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .report-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .report-type-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border: 1px solid #eee;
        }
        
        .report-type-card h3 {
            margin-top: 0;
            color: #007bff;
        }
        
        .report-type-card p {
            color: #666;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
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
                <a href="view_students.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>View Students</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports</h4>
                <a href="manage_reports.php" class="nav-item active">
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
            <h1><i class="fas fa-chart-bar"></i> Manage Reports</h1>
            <p>Generate and download reports for your courses</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="report-filters">
            <h2><i class="fas fa-filter"></i> Report Filters</h2>
            <form method="GET" id="reportForm">
                <input type="hidden" name="action" value="generate_report">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="course_id">Select Course *</label>
                        <select id="course_id" name="course_id" required>
                            <option value="">-- Select Course --</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['code'] . ' - ' . $course['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="report_type">Report Type *</label>
                        <select id="report_type" name="report_type" required>
                            <option value="">-- Select Report Type --</option>
                            <option value="class_list">Class List</option>
                            <option value="results_summary">Results Summary</option>
                            <option value="detailed_results">Detailed Results</option>
                        </select>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" name="format" value="pdf" class="btn btn-primary">
                        <i class="fas fa-file-pdf"></i> Generate PDF
                    </button>
                    <button type="submit" name="format" value="csv" class="btn btn-info">
                        <i class="fas fa-file-csv"></i> Generate CSV
                    </button>
                </div>
            </form>
        </div>
        
        <div class="report-types">
            <div class="report-type-card">
                <h3><i class="fas fa-users"></i> Class List Report</h3>
                <p>Generates a complete list of students enrolled in the selected course with their details including student number, full name, NRC, and intake information.</p>
            </div>
            
            <div class="report-type-card">
                <h3><i class="fas fa-chart-pie"></i> Results Summary Report</h3>
                <p>Provides a summary of results for all students in the selected course, showing CA scores, exam scores, total scores, and grades.</p>
            </div>
            
            <div class="report-type-card">
                <h3><i class="fas fa-chart-line"></i> Detailed Results Report</h3>
                <p>Generates a detailed report with results sorted by performance, including statistical analysis such as average, highest, and lowest scores.</p>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        // Form validation
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            const courseId = document.getElementById('course_id').value;
            const reportType = document.getElementById('report_type').value;
            
            if (!courseId || !reportType) {
                e.preventDefault();
                alert('Please select both course and report type.');
                return false;
            }
        });
    </script>
</body>
</html>