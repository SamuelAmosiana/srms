<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

// Check if user has HR Manager role
$stmt = $pdo->prepare("
    SELECT r.name 
    FROM users u 
    JOIN user_roles ur ON u.id = ur.user_id 
    JOIN roles r ON ur.role_id = r.id 
    WHERE u.id = ? AND r.name = 'HR Manager'
");
$stmt->execute([currentUserId()]);
$hrRole = $stmt->fetch();

if (!$hrRole) {
    header('Location: ../unauthorized.php');
    exit;
}

// Get export type
$export_type = $_GET['type'] ?? 'csv';

// Fetch employees
$stmt = $pdo->prepare("
    SELECT e.*, d.name as department_name 
    FROM employees e 
    LEFT JOIN department d ON e.department_id = d.id 
    ORDER BY e.hire_date DESC
");
$stmt->execute();
$employees = $stmt->fetchAll();

if ($export_type === 'csv') {
    // CSV export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="employee_directory_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 to handle special characters in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write header row
    fputcsv($output, [
        'Employee ID',
        'First Name',
        'Last Name', 
        'Email',
        'Department',
        'Position',
        'Hire Date',
        'Salary',
        'Status'
    ]);
    
    // Write data rows
    foreach ($employees as $employee) {
        fputcsv($output, [
            $employee['employee_id'],
            $employee['first_name'],
            $employee['last_name'],
            $employee['email'],
            $employee['department_name'] ?? 'N/A',
            $employee['position'] ?? 'N/A',
            $employee['hire_date'] ? date('Y-m-d', strtotime($employee['hire_date'])) : 'N/A',
            $employee['salary'] ?? 'N/A',
            $employee['status']
        ]);
    }
    
    fclose($output);
    exit;
} elseif ($export_type === 'pdf') {
    // For PDF, we'll generate using FPDF
    require_once '../lib/fpdf/fpdf.php';
    
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    
    // Add title
    $pdf->Cell(0, 10, 'LUSAKA SOUTH COLLEGE', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 5, 'Student Records Management System', 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Employee Directory', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Table headers
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(25, 10, 'ID', 1, 0, 'C');
    $pdf->Cell(30, 10, 'First Name', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Last Name', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Email', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Department', 1, 0, 'C');
    $pdf->Cell(25, 10, 'Position', 1, 0, 'C');
    $pdf->Cell(25, 10, 'Hire Date', 1, 0, 'C');
    $pdf->Cell(20, 10, 'Salary', 1, 0, 'C');
    $pdf->Cell(20, 10, 'Status', 1, 1, 'C');
    
    // Table data
    $pdf->SetFont('Arial', '', 9);
    foreach ($employees as $employee) {
        $pdf->Cell(25, 8, $employee['employee_id'], 1, 0, 'C');
        $pdf->Cell(30, 8, $employee['first_name'], 1, 0, 'L');
        $pdf->Cell(30, 8, $employee['last_name'], 1, 0, 'L');
        $pdf->Cell(40, 8, $employee['email'], 1, 0, 'L');
        $pdf->Cell(30, 8, $employee['department_name'] ?? 'N/A', 1, 0, 'L');
        $pdf->Cell(25, 8, $employee['position'] ?? 'N/A', 1, 0, 'L');
        $pdf->Cell(25, 8, $employee['hire_date'] ? date('Y-m-d', strtotime($employee['hire_date'])) : 'N/A', 1, 0, 'L');
        $pdf->Cell(20, 8, $employee['salary'] ? '$' . number_format($employee['salary'], 2) : 'N/A', 1, 0, 'R');
        $pdf->Cell(20, 8, ucfirst($employee['status']), 1, 1, 'C');
    }
    
    $pdf->Output('D', 'employee_directory_' . date('Y-m-d') . '.pdf');
    exit;
} else {
    // Default to CSV if invalid type
    header('Location: ?type=csv');
    exit;
}