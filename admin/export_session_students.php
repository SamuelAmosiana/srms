<?php
session_start();
require_once '../config.php';
require_once '../auth/auth.php';

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit();
}

// Check if user has admin role or course_registrations permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('course_registrations', $pdo)) {
    header('Location: ../auth/login.php');
    exit();
}

// Get parameters for filtering
$session_id = (int)($_GET['session_id'] ?? 0);
$format = strtolower(trim($_GET['format'] ?? 'pdf'));

// Validate session_id
if ($session_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid session ID'
    ]);
    exit();
}

try {
    // First, get session information
    $session_stmt = $pdo->prepare("SELECT id, session_name, academic_year, term FROM academic_sessions WHERE id = ?");
    $session_stmt->execute([$session_id]);
    $session_info = $session_stmt->fetch();
    
    if (!$session_info) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Session not found'
        ]);
        exit();
    }
    
    // Build query to get students registered in this session
    $sql = "SELECT DISTINCT 
                sp.student_number,
                sp.full_name,
                u.email,
                p.name as programme_name,
                CASE 
                    WHEN ps.payment_method IS NOT NULL AND ps.payment_method != '' THEN 'First Time Registration'
                    ELSE 'Regular/Returning'
                END as registration_type,
                COALESCE(cr.submitted_at, ps.updated_at, ps.created_at) as registration_date,
                sp.user_id,
                s.session_name,
                s.academic_year,
                s.term
            FROM student_profile sp
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN programme pr ON sp.programme_id = pr.id
            LEFT JOIN pending_students ps ON sp.student_number = ps.student_number
            LEFT JOIN course_registration cr ON sp.user_id = cr.student_id AND cr.status = 'approved'
            LEFT JOIN intake i ON sp.intake_id = i.id
            LEFT JOIN programme_schedule psch ON i.id = psch.intake_id AND psch.session_id = ?
            LEFT JOIN academic_sessions s ON psch.session_id = s.id
            LEFT JOIN programme p ON COALESCE(pr.id, ps.programme_id) = p.id
            WHERE (psch.session_id = ? OR cr.id IS NOT NULL)
              AND (cr.status = 'approved' OR ps.registration_status = 'approved' OR psch.id IS NOT NULL)
            ORDER BY sp.full_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$session_id, $session_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        // Generate CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="session_students_' . $session_info['session_name'] . '_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM to handle UTF-8 in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write header
        fputcsv($output, [
            'Student Number', 
            'Full Name', 
            'Email', 
            'Programme', 
            'Registration Type', 
            'Registration Date',
            'Academic Year',
            'Term'
        ]);
        
        // Write data
        foreach ($students as $student) {
            fputcsv($output, [
                $student['student_number'],
                $student['full_name'],
                $student['email'],
                $student['programme_name'] ?? 'N/A',
                $student['registration_type'],
                date('Y-m-d', strtotime($student['registration_date'])),
                $student['academic_year'],
                $student['term']
            ]);
        }
        
        fclose($output);
        exit;
    } elseif ($format === 'pdf') {
        // Generate PDF using FPDF
        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        require_once '../lib/fpdf/fpdf.php';
        
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        
        // Add logo if exists
        $logo_path = '../assets/images/lsc-logo.png';
        if (file_exists($logo_path)) {
            $pdf->Image($logo_path, 85, 10, 40);
            $pdf->Ln(30);
        }
        
        // Header
        $pdf->Cell(0, 10, 'LUSAKA SOUTH COLLEGE', 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'REGISTERED STUDENTS REPORT', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Session: ' . $session_info['session_name'], 0, 1, 'C');
        $pdf->Cell(0, 10, 'Academic Year: ' . $session_info['academic_year'] . ' | Term: ' . $session_info['term'], 0, 1, 'C');
        $pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $pdf->Ln(10);
        
        // Table header
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(25, 10, 'Student No.', 1);
        $pdf->Cell(50, 10, 'Full Name', 1);
        $pdf->Cell(45, 10, 'Email', 1);
        $pdf->Cell(35, 10, 'Programme', 1);
        $pdf->Cell(35, 10, 'Registration Type', 1);
        $pdf->Ln();
        
        // Table data
        $pdf->SetFont('Arial', '', 9);
        foreach ($students as $student) {
            $pdf->Cell(25, 8, $student['student_number'], 1);
            $pdf->Cell(50, 8, substr($student['full_name'], 0, 25), 1);
            $pdf->Cell(45, 8, substr($student['email'], 0, 25), 1);
            $pdf->Cell(35, 8, substr($student['programme_name'] ?? 'N/A', 0, 20), 1);
            $pdf->Cell(35, 8, substr($student['registration_type'], 0, 20), 1);
            $pdf->Ln();
        }
        
        // Footer
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 5, 'Total Students: ' . count($students), 0, 1, 'C');
        $pdf->Cell(0, 5, 'This is a computer-generated document and is valid without signature.', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Lusaka South College © ' . date('Y') . ' - All Rights Reserved', 0, 1, 'C');
        
        $pdf->Output('D', 'session_students_' . $session_info['session_name'] . '_' . date('Y-m-d') . '.pdf');
        exit;
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error exporting session students: ' . $e->getMessage()
    ]);
    exit;
}
?>