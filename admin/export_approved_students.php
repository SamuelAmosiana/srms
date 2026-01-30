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
$type = $_GET['type'] ?? 'all';
$programme_id = (int)($_GET['programme_id'] ?? 0);
$format = strtolower(trim($_GET['format'] ?? 'pdf'));

try {
    // Build query based on filters
    $sql = "SELECT DISTINCT 
                sp.student_number,
                sp.full_name,
                u.email,
                p.name as programme_name,
                CASE 
                    WHEN ps.payment_method IS NOT NULL AND ps.payment_method != '' THEN 'First Time Registration'
                    ELSE 'Regular/Returning'
                END as registration_type,
                COALESCE(cr.submitted_at, ps.updated_at) as registration_date
            FROM student_profile sp
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN programme p ON sp.programme_id = p.id
            LEFT JOIN pending_students ps ON sp.student_number = ps.student_number
            LEFT JOIN course_registration cr ON sp.user_id = cr.student_id
            WHERE (cr.status = 'approved' OR ps.registration_status = 'approved')";
    
    $params = [];
    
    if ($type !== 'all') {
        if ($type === 'first_time') {
            $sql .= " AND ps.payment_method IS NOT NULL AND ps.payment_method != ''";
        } elseif ($type === 'regular') {
            $sql .= " AND (ps.payment_method IS NULL OR ps.payment_method = '')";
        }
    }
    
    if ($programme_id > 0) {
        $sql .= " AND (sp.programme_id = ? OR cr.course_id IN (SELECT id FROM course WHERE programme_id = ?))";
        $params[] = $programme_id;
        $params[] = $programme_id;
    }
    
    $sql .= " ORDER BY registration_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Export based on format
    if ($format === 'excel' || $format === 'csv') {
        // Export to CSV
        header('Content-Type: text/csv; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="approved_students_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM to handle UTF-8 in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write header
        fputcsv($output, ['Student Number', 'Full Name', 'Email', 'Programme', 'Registration Type', 'Registration Date']);
        
        // Write data
        foreach ($students as $student) {
            fputcsv($output, [
                $student['student_number'],
                $student['full_name'],
                $student['email'],
                $student['programme_name'],
                $student['registration_type'],
                $student['registration_date']
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
        
        $pdf->Cell(0, 10, 'Approved Students Report', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $pdf->Ln(10);
        
        // Table header
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(25, 10, 'Student No.', 1, 0, 'C');
        $pdf->Cell(40, 10, 'Full Name', 1, 0, 'C');
        $pdf->Cell(40, 10, 'Email', 1, 0, 'C');
        $pdf->Cell(40, 10, 'Programme', 1, 0, 'C');
        $pdf->Cell(25, 10, 'Type', 1, 0, 'C');
        $pdf->Cell(20, 10, 'Date', 1, 1, 'C');
        
        // Table data
        $pdf->SetFont('Arial', '', 9);
        foreach ($students as $student) {
            $pdf->Cell(25, 8, $student['student_number'], 1, 0, 'C');
            $pdf->Cell(40, 8, $student['full_name'], 1, 0, 'L');
            $pdf->Cell(40, 8, $student['email'], 1, 0, 'L');
            $pdf->Cell(40, 8, $student['programme_name'], 1, 0, 'L');
            $pdf->Cell(25, 8, $student['registration_type'], 1, 0, 'C');
            $pdf->Cell(20, 8, date('Y-m-d', strtotime($student['registration_date'])), 1, 1, 'C');
        }
        
        $pdf->Output('D', 'approved_students_' . date('Y-m-d') . '.pdf');
        exit;
    } else {
        // Unknown format
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Unsupported export format'
        ]);
        exit;
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error exporting approved students: ' . $e->getMessage()
    ]);
}
?>