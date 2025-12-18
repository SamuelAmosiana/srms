<?php
session_start();
require_once '../config.php';
require_once '../auth.php';
require_once '../lib/fpdf/fpdf.php'; // Include FPDF for PDF generation

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Location: ../login.php');
    exit();
}

// Check if user has admin role or reports permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('reports', $pdo)) {
    header('Location: ../login.php');
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT u.*, ap.full_name, ap.staff_id FROM users u LEFT JOIN admin_profile ap ON u.id = ap.user_id WHERE u.id = ?");
$stmt->execute([currentUserId()]);
$user = $stmt->fetch();

// Handle report generation
if (isset($_GET['action']) && $_GET['action'] === 'generate_report') {
    $module = $_GET['module'] ?? '';
    $format = $_GET['format'] ?? 'pdf';
    
    switch ($module) {
        case 'users':
            generateUsersReport($pdo, $format);
            break;
        case 'roles':
            generateRolesReport($pdo, $format);
            break;
        case 'schools':
            generateSchoolsReport($pdo, $format);
            break;
        case 'departments':
            generateDepartmentsReport($pdo, $format);
            break;
        case 'programmes':
            generateProgrammesReport($pdo, $format);
            break;
        case 'courses':
            generateCoursesReport($pdo, $format);
            break;
        case 'intakes':
            generateIntakesReport($pdo, $format);
            break;
        case 'enrollments':
            generateEnrollmentsReport($pdo, $format);
            break;
        case 'registrations':
            generateRegistrationsReport($pdo, $format);
            break;
        case 'results':
            generateResultsReport($pdo, $format);
            break;
        default:
            header('Location: reports.php?error=Invalid module');
            exit();
    }
}

// Function to generate Users Report
function generateUsersReport($pdo, $format) {
    $data = $pdo->query("SELECT u.id, u.username, u.email, u.role, u.is_active, COUNT(sp.id) as students, COUNT(lp.id) as lecturers FROM users u LEFT JOIN student_profile sp ON u.id = sp.user_id LEFT JOIN lecturer_profile lp ON u.id = lp.user_id GROUP BY u.id")->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="users_report.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Username', 'Email', 'Role', 'Active', 'Students', 'Lecturers']);
        foreach ($data as $row) {
            fputcsv($output, [$row['id'], $row['username'], $row['email'], $row['role'], $row['is_active'], $row['students'], $row['lecturers']]);
        }
        fclose($output);
    } else {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Users Report', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(20, 10, 'ID', 1);
        $pdf->Cell(40, 10, 'Username', 1);
        $pdf->Cell(50, 10, 'Email', 1);
        $pdf->Cell(30, 10, 'Role', 1);
        $pdf->Cell(30, 10, 'Active', 1);
        $pdf->Cell(30, 10, 'Students', 1);
        $pdf->Cell(30, 10, 'Lecturers', 1);
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 12);
        foreach ($data as $row) {
            $pdf->Cell(20, 10, $row['id'], 1);
            $pdf->Cell(40, 10, $row['username'], 1);
            $pdf->Cell(50, 10, $row['email'], 1);
            $pdf->Cell(30, 10, $row['role'], 1);
            $pdf->Cell(30, 10, $row['is_active'] ? 'Yes' : 'No', 1);
            $pdf->Cell(30, 10, $row['students'], 1);
            $pdf->Cell(30, 10, $row['lecturers'], 1);
            $pdf->Ln();
        }
        $pdf->Output('D', 'users_report.pdf');
    }
    exit();
}

// Function to generate Roles Report
function generateRolesReport($pdo, $format) {
    $data = $pdo->query("SELECT r.id, r.name, COUNT(u.id) as users, GROUP_CONCAT(p.name SEPARATOR ', ') as permissions FROM roles r LEFT JOIN users u ON r.id = u.role_id LEFT JOIN role_permissions rp ON r.id = rp.role_id LEFT JOIN permissions p ON rp.permission_id = p.id GROUP BY r.id")->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="roles_report.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Users', 'Permissions']);
        foreach ($data as $row) {
            fputcsv($output, [$row['id'], $row['name'], $row['users'], $row['permissions']]);
        }
        fclose($output);
    } else {
        $pdf = new FPDF();
        $pdf->AddPage('L'); // Landscape for more space
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Roles Report', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(20, 10, 'ID', 1);
        $pdf->Cell(50, 10, 'Name', 1);
        $pdf->Cell(30, 10, 'Users', 1);
        $pdf->Cell(150, 10, 'Permissions', 1);
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 12);
        foreach ($data as $row) {
            $pdf->Cell(20, 10, $row['id'], 1);
            $pdf->Cell(50, 10, $row['name'], 1);
            $pdf->Cell(30, 10, $row['users'], 1);
            $pdf->MultiCell(150, 10, $row['permissions'] ?? 'None', 1);
        }
        $pdf->Output('D', 'roles_report.pdf');
    }
    exit();
}

// Function to generate Schools Report
function generateSchoolsReport($pdo, $format) {
    $data = $pdo->query("SELECT s.id, s.name, COUNT(d.id) as departments, COUNT(sp.id) as students FROM school s LEFT JOIN department d ON s.id = d.school_id LEFT JOIN student_profile sp ON s.id = sp.school_id GROUP BY s.id")->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="schools_report.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Departments', 'Students']);
        foreach ($data as $row) {
            fputcsv($output, [$row['id'], $row['name'], $row['departments'], $row['students']]);
        }
        fclose($output);
    } else {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Schools Report', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(20, 10, 'ID', 1);
        $pdf->Cell(80, 10, 'Name', 1);
        $pdf->Cell(50, 10, 'Departments', 1);
        $pdf->Cell(40, 10, 'Students', 1);
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 12);
        foreach ($data as $row) {
            $pdf->Cell(20, 10, $row['id'], 1);
            $pdf->Cell(80, 10, $row['name'], 1);
            $pdf->Cell(50, 10, $row['departments'], 1);
            $pdf->Cell(40, 10, $row['students'], 1);
            $pdf->Ln();
        }
        $pdf->Output('D', 'schools_report.pdf');
    }
    exit();
}

// Function to generate Departments Report
function generateDepartmentsReport($pdo, $format) {
    $data = $pdo->query("SELECT d.id, d.name, d.code, s.name as school_name, COUNT(sp.id) as students, COUNT(c.id) as courses FROM department d LEFT JOIN school s ON d.school_id = s.id LEFT JOIN student_profile sp ON d.id = sp.department_id LEFT JOIN course c ON d.id = c.department_id GROUP BY d.id")->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="departments_report.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Code', 'School', 'Students', 'Courses']);
        foreach ($data as $row) {
            fputcsv($output, [$row['id'], $row['name'], $row['code'], $row['school_name'], $row['students'], $row['courses']]);
        }
        fclose($output);
    } else {
        $pdf = new FPDF();
        $pdf->AddPage('L');
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Departments Report', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(20, 10, 'ID', 1);
        $pdf->Cell(60, 10, 'Name', 1);
        $pdf->Cell(30, 10, 'Code', 1);
        $pdf->Cell(60, 10, 'School', 1);
        $pdf->Cell(40, 10, 'Students', 1);
        $pdf->Cell(40, 10, 'Courses', 1);
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 12);
        foreach ($data as $row) {
            $pdf->Cell(20, 10, $row['id'], 1);
            $pdf->Cell(60, 10, $row['name'], 1);
            $pdf->Cell(30, 10, $row['code'], 1);
            $pdf->Cell(60, 10, $row['school_name'], 1);
            $pdf->Cell(40, 10, $row['students'], 1);
            $pdf->Cell(40, 10, $row['courses'], 1);
            $pdf->Ln();
        }
        $pdf->Output('D', 'departments_report.pdf');
    }
    exit();
}

// Function to generate Programmes Report
function generateProgrammesReport($pdo, $format) {
    $data = $pdo->query("SELECT p.id, p.name, p.code, s.name as school_name, p.duration, COUNT(sp.id) as students, COUNT(c.id) as courses FROM programme p LEFT JOIN school s ON p.school_id = s.id LEFT JOIN student_profile sp ON p.id = sp.programme_id LEFT JOIN course c ON p.id = c.programme_id GROUP BY p.id")->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="programmes_report.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Code', 'School', 'Duration', 'Students', 'Courses']);
        foreach ($data as $row) {
            fputcsv($output, [$row['id'], $row['name'], $row['code'], $row['school_name'], $row['duration'], $row['students'], $row['courses']]);
        }
        fclose($output);
    } else {
        $pdf = new FPDF();
        $pdf->AddPage('L');
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Programmes Report', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(20, 10, 'ID', 1);
        $pdf->Cell(60, 10, 'Name', 1);
        $pdf->Cell(30, 10, 'Code', 1);
        $pdf->Cell(60, 10, 'School', 1);
        $pdf->Cell(40, 10, 'Duration', 1);
        $pdf->Cell(40, 10, 'Students', 1);
        $pdf->Cell(40, 10, 'Courses', 1);
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 12);
        foreach ($data as $row) {
            $pdf->Cell(20, 10, $row['id'], 1);
            $pdf->Cell(60, 10, $row['name'], 1);
            $pdf->Cell(30, 10, $row['code'], 1);
            $pdf->Cell(60, 10, $row['school_name'], 1);
            $pdf->Cell(40, 10, $row['duration'], 1);
            $pdf->Cell(40, 10, $row['students'], 1);
            $pdf->Cell(40, 10, $row['courses'], 1);
            $pdf->Ln();
        }
        $pdf->Output('D', 'programmes_report.pdf');
    }
    exit();
}

// Function to generate Courses Report
function generateCoursesReport($pdo, $format) {
    $data = $pdo->query("SELECT c.id, c.name, c.code, p.name as programme_name, c.credits, COUNT(ce.id) as enrollments FROM course c LEFT JOIN programme p ON c.programme_id = p.id LEFT JOIN course_enrollment ce ON c.id = ce.course_id GROUP BY c.id")->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="courses_report.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Code', 'Programme', 'Credits', 'Enrollments']);
        foreach ($data as $row) {
            fputcsv($output, [$row['id'], $row['name'], $row['code'], $row['programme_name'], $row['credits'], $row['enrollments']]);
        }
        fclose($output);
    } else {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Courses Report', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(20, 10, 'ID', 1);
        $pdf->Cell(60, 10, 'Name', 1);
        $pdf->Cell(30, 10, 'Code', 1);
        $pdf->Cell(60, 10, 'Programme', 1);
        $pdf->Cell(30, 10, 'Credits', 1);
        $pdf->Cell(40, 10, 'Enrollments', 1);
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 12);
        foreach ($data as $row) {
            $pdf->Cell(20, 10, $row['id'], 1);
            $pdf->Cell(60, 10, $row['name'], 1);
            $pdf->Cell(30, 10, $row['code'], 1);
            $pdf->Cell(60, 10, $row['programme_name'], 1);
            $pdf->Cell(30, 10, $row['credits'], 1);
            $pdf->Cell(40, 10, $row['enrollments'], 1);
            $pdf->Ln();
        }
        $pdf->Output('D', 'courses_report.pdf');
    }
    exit();
}

// Function to generate Intakes Report
function generateIntakesReport($pdo, $format) {
    $data = $pdo->query("SELECT i.id, i.name, i.start_date, i.end_date, COUNT(sp.id) as students FROM intake i LEFT JOIN student_profile sp ON i.id = sp.intake_id GROUP BY i.id ORDER BY i.start_date DESC")->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="intakes_report.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Start Date', 'End Date', 'Students']);
        foreach ($data as $row) {
            fputcsv($output, [$row['id'], $row['name'], $row['start_date'], $row['end_date'], $row['students']]);
        }
        fclose($output);
    } else {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Intakes Report', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(20, 10, 'ID', 1);
        $pdf->Cell(60, 10, 'Name', 1);
        $pdf->Cell(40, 10, 'Start Date', 1);
        $pdf->Cell(40, 10, 'End Date', 1);
        $pdf->Cell(30, 10, 'Students', 1);
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 12);
        foreach ($data as $row) {
            $pdf->Cell(20, 10, $row['id'], 1);
            $pdf->Cell(60, 10, $row['name'], 1);
            $pdf->Cell(40, 10, $row['start_date'], 1);
            $pdf->Cell(40, 10, $row['end_date'], 1);
            $pdf->Cell(30, 10, $row['students'], 1);
            $pdf->Ln();
        }
        $pdf->Output('D', 'intakes_report.pdf');
    }
    exit();
}

// Function to generate Enrollments Report
function generateEnrollmentsReport($pdo, $format) {
    $data = $pdo->query("SELECT a.id, a.full_name, a.email, a.status, p.name as programme_name, i.name as intake_name, a.created_at FROM applications a LEFT JOIN programme p ON a.programme_id = p.id LEFT JOIN intake i ON a.intake_id = i.id ORDER BY a.created_at DESC")->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="enrollments_report.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Email', 'Status', 'Programme', 'Intake', 'Submitted']);
        foreach ($data as $row) {
            fputcsv($output, [$row['id'], $row['full_name'], $row['email'], $row['status'], $row['programme_name'], $row['intake_name'], $row['created_at']]);
        }
        fclose($output);
    } else {
        $pdf = new FPDF();
        $pdf->AddPage('L');
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Enrollments Report', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(20, 10, 'ID', 1);
        $pdf->Cell(50, 10, 'Name', 1);
        $pdf->Cell(60, 10, 'Email', 1);
        $pdf->Cell(30, 10, 'Status', 1);
        $pdf->Cell(50, 10, 'Programme', 1);
        $pdf->Cell(40, 10, 'Intake', 1);
        $pdf->Cell(40, 10, 'Submitted', 1);
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 12);
        foreach ($data as $row) {
            $pdf->Cell(20, 10, $row['id'], 1);
            $pdf->Cell(50, 10, $row['full_name'], 1);
            $pdf->Cell(60, 10, $row['email'], 1);
            $pdf->Cell(30, 10, $row['status'], 1);
            $pdf->Cell(50, 10, $row['programme_name'], 1);
            $pdf->Cell(40, 10, $row['intake_name'], 1);
            $pdf->Cell(40, 10, $row['created_at'], 1);
            $pdf->Ln();
        }
        $pdf->Output('D', 'enrollments_report.pdf');
    }
    exit();
}

// Function to generate Registrations Report
function generateRegistrationsReport($pdo, $format) {
    $data = $pdo->query("SELECT cr.id, sp.student_id, sp.full_name, c.name as course_name, cr.term, cr.status, cr.submitted_at FROM course_registration cr LEFT JOIN student_profile sp ON cr.student_id = sp.user_id LEFT JOIN course c ON cr.course_id = c.id ORDER BY cr.submitted_at DESC")->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="registrations_report.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Student ID', 'Name', 'Course', 'Term', 'Status', 'Submitted']);
        foreach ($data as $row) {
            fputcsv($output, [$row['id'], $row['student_id'], $row['full_name'], $row['course_name'], $row['term'], $row['status'], $row['submitted_at']]);
        }
        fclose($output);
    } else {
        $pdf = new FPDF();
        $pdf->AddPage('L');
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Course Registrations Report', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(20, 10, 'ID', 1);
        $pdf->Cell(40, 10, 'Student ID', 1);
        $pdf->Cell(50, 10, 'Name', 1);
        $pdf->Cell(60, 10, 'Course', 1);
        $pdf->Cell(30, 10, 'Term', 1);
        $pdf->Cell(30, 10, 'Status', 1);
        $pdf->Cell(40, 10, 'Submitted', 1);
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 12);
        foreach ($data as $row) {
            $pdf->Cell(20, 10, $row['id'], 1);
            $pdf->Cell(40, 10, $row['student_id'], 1);
            $pdf->Cell(50, 10, $row['full_name'], 1);
            $pdf->Cell(60, 10, $row['course_name'], 1);
            $pdf->Cell(30, 10, $row['term'], 1);
            $pdf->Cell(30, 10, $row['status'], 1);
            $pdf->Cell(40, 10, $row['submitted_at'], 1);
            $pdf->Ln();
        }
        $pdf->Output('D', 'registrations_report.pdf');
    }
    exit();
}

// Function to generate Results Report
function generateResultsReport($pdo, $format) {
    $data = $pdo->query("SELECT sr.id, sp.student_id, sp.full_name, c.name as course_name, rt.name as type_name, sr.score, ce.total_score, ce.grade, ce.status FROM student_result sr LEFT JOIN course_enrollment ce ON sr.enrollment_id = ce.id LEFT JOIN student_profile sp ON ce.student_id = sp.user_id LEFT JOIN course c ON ce.course_id = c.id LEFT JOIN result_type rt ON sr.type_id = rt.id ORDER BY sp.full_name, c.name")->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="results_report.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Student ID', 'Name', 'Course', 'Type', 'Score', 'Total Score', 'Grade', 'Status']);
        foreach ($data as $row) {
            fputcsv($output, [$row['id'], $row['student_id'], $row['full_name'], $row['course_name'], $row['type_name'], $row['score'], $row['total_score'], $row['grade'], $row['status']]);
        }
        fclose($output);
    } else {
        $pdf = new FPDF();
        $pdf->AddPage('L');
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Results Report', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(20, 10, 'ID', 1);
        $pdf->Cell(40, 10, 'Student ID', 1);
        $pdf->Cell(50, 10, 'Name', 1);
        $pdf->Cell(50, 10, 'Course', 1);
        $pdf->Cell(40, 10, 'Type', 1);
        $pdf->Cell(30, 10, 'Score', 1);
        $pdf->Cell(40, 10, 'Total Score', 1);
        $pdf->Cell(30, 10, 'Grade', 1);
        $pdf->Cell(40, 10, 'Status', 1);
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 12);
        foreach ($data as $row) {
            $pdf->Cell(20, 10, $row['id'], 1);
            $pdf->Cell(40, 10, $row['student_id'], 1);
            $pdf->Cell(50, 10, $row['full_name'], 1);
            $pdf->Cell(50, 10, $row['course_name'], 1);
            $pdf->Cell(40, 10, $row['type_name'], 1);
            $pdf->Cell(30, 10, $row['score'], 1);
            $pdf->Cell(40, 10, $row['total_score'], 1);
            $pdf->Cell(30, 10, $row['grade'], 1);
            $pdf->Cell(40, 10, $row['status'], 1);
            $pdf->Ln();
        }
        $pdf->Output('D', 'results_report.pdf');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - LSC SRMS</title>
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
                <a href="reports.php" class="nav-item active">
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
            <h1><i class="fas fa-chart-bar"></i> Reports</h1>
            <p>Generate and download reports for various modules</p>
        </div>

        <!-- Report Selection -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-file-alt"></i> Generate Report</h3>
            </div>
            <div class="panel-content">
                <div class="report-grid">
                    <div class="report-card">
                        <h4>Users Report</h4>
                        <p>Summary of all users, roles, and counts</p>
                        <a href="?action=generate_report&module=users&format=pdf" class="btn btn-primary"><i class="fas fa-file-pdf"></i> PDF</a>
                        <a href="?action=generate_report&module=users&format=csv" class="btn btn-info"><i class="fas fa-file-csv"></i> CSV</a>
                    </div>
                    <div class="report-card">
                        <h4>Roles Report</h4>
                        <p>Roles, permissions, and user counts</p>
                        <a href="?action=generate_report&module=roles&format=pdf" class="btn btn-primary"><i class="fas fa-file-pdf"></i> PDF</a>
                        <a href="?action=generate_report&module=roles&format=csv" class="btn btn-info"><i class="fas fa-file-csv"></i> CSV</a>
                    </div>
                    <div class="report-card">
                        <h4>Schools Report</h4>
                        <p>Schools with department and student counts</p>
                        <a href="?action=generate_report&module=schools&format=pdf" class="btn btn-primary"><i class="fas fa-file-pdf"></i> PDF</a>
                        <a href="?action=generate_report&module=schools&format=csv" class="btn btn-info"><i class="fas fa-file-csv"></i> CSV</a>
                    </div>
                    <div class="report-card">
                        <h4>Departments Report</h4>
                        <p>Departments with student and course counts</p>
                        <a href="?action=generate_report&module=departments&format=pdf" class="btn btn-primary"><i class="fas fa-file-pdf"></i> PDF</a>
                        <a href="?action=generate_report&module=departments&format=csv" class="btn btn-info"><i class="fas fa-file-csv"></i> CSV</a>
                    </div>
                    <div class="report-card">
                        <h4>Programmes Report</h4>
                        <p>Programmes with student and course counts</p>
                        <a href="?action=generate_report&module=programmes&format=pdf" class="btn btn-primary"><i class="fas fa-file-pdf"></i> PDF</a>
                        <a href="?action=generate_report&module=programmes&format=csv" class="btn btn-info"><i class="fas fa-file-csv"></i> CSV</a>
                    </div>
                    <div class="report-card">
                        <h4>Courses Report</h4>
                        <p>Courses with enrollment counts</p>
                        <a href="?action=generate_report&module=courses&format=pdf" class="btn btn-primary"><i class="fas fa-file-pdf"></i> PDF</a>
                        <a href="?action=generate_report&module=courses&format=csv" class="btn btn-info"><i class="fas fa-file-csv"></i> CSV</a>
                    </div>
                    <div class="report-card">
                        <h4>Intakes Report</h4>
                        <p>Intakes with student counts</p>
                        <a href="?action=generate_report&module=intakes&format=pdf" class="btn btn-primary"><i class="fas fa-file-pdf"></i> PDF</a>
                        <a href="?action=generate_report&module=intakes&format=csv" class="btn btn-info"><i class="fas fa-file-csv"></i> CSV</a>
                    </div>
                    <div class="report-card">
                        <h4>Enrollments Report</h4>
                        <p>Enrollment applications and status</p>
                        <a href="?action=generate_report&module=enrollments&format=pdf" class="btn btn-primary"><i class="fas fa-file-pdf"></i> PDF</a>
                        <a href="?action=generate_report&module=enrollments&format=csv" class="btn btn-info"><i class="fas fa-file-csv"></i> CSV</a>
                    </div>
                    <div class="report-card">
                        <h4>Registrations Report</h4>
                        <p>Course registrations and status</p>
                        <a href="?action=generate_report&module=registrations&format=pdf" class="btn btn-primary"><i class="fas fa-file-pdf"></i> PDF</a>
                        <a href="?action=generate_report&module=registrations&format=csv" class="btn btn-info"><i class="fas fa-file-csv"></i> CSV</a>
                    </div>
                    <div class="report-card">
                        <h4>Results Report</h4>
                        <p>Student results and grades</p>
                        <a href="?action=generate_report&module=results&format=pdf" class="btn btn-primary"><i class="fas fa-file-pdf"></i> PDF</a>
                        <a href="?action=generate_report&module=results&format=csv" class="btn btn-info"><i class="fas fa-file-csv"></i> CSV</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .report-card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 2px 8px var(--shadow);
            padding: 20px;
            text-align: center;
        }
        
        .report-card h4 {
            color: var(--primary-green);
            margin-bottom: 10px;
        }
        
        .report-card p {
            color: var(--text-light);
            margin-bottom: 15px;
        }
        
        .report-card a {
            display: inline-block;
            margin: 5px;
        }
    </style>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>