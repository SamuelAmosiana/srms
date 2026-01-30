<?php
session_start();
require_once '../config.php';
require_once '../auth/auth.php';

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit();
}

// Check if user has admin role or manage_academic_structure permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('manage_academic_structure', $pdo)) {
    header('Location: ../auth/login.php');
    exit();
}

// Get session_id from GET parameter
$session_id = (int)($_GET['session_id'] ?? 0);

if ($session_id <= 0) {
    die('Session ID is required');
}

// Check if session exists
$stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE id = ?");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

if (!$session) {
    die('Session not found');
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="session_students_template_' . $session_id . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Define CSV headers
$headers = [
    'student_number',      // Optional: Leave empty for new students, fill for existing students
    'full_name',           // Required: Student's full name
    'email',               // Required: Student's email address
    'programme_id',        // Required for new students: ID of the programme
    'intake_id'            // Required: ID of the intake to assign to
];

// Write headers to CSV
fputcsv($output, $headers);

// Add sample rows
$sample_rows = [
    ['LSC000001', 'John Doe', 'john.doe@example.com', '1', '1'],  // Existing student
    ['', 'Jane Smith', 'jane.smith@example.com', '1', '1'],       // New student
    ['', 'Bob Johnson', 'bob.johnson@example.com', '2', '1'],     // New student
];

foreach ($sample_rows as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?>