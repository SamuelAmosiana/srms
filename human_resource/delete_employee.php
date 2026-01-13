<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in
if (!currentUserId()) {
    header('Location: ../auth/login.php');
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

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: employees.php');
    exit;
}

// Get employee ID from POST
$employee_id = $_POST['employee_id'] ?? null;

if (!$employee_id) {
    header('Location: employees.php');
    exit;
}

// Fetch employee to verify it exists and get name for message
$stmt = $pdo->prepare("SELECT first_name, last_name FROM employees WHERE employee_id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();

if (!$employee) {
    header('Location: employees.php');
    exit;
}

// Delete the employee
try {
    $stmt = $pdo->prepare("DELETE FROM employees WHERE employee_id = ?");
    $result = $stmt->execute([$employee_id]);
    
    if ($result) {
        // Redirect back to employees page with success message
        header('Location: employees.php?message=deleted&name=' . urlencode($employee['first_name'] . ' ' . $employee['last_name']));
        exit;
    } else {
        header('Location: employees.php?message=error');
        exit;
    }
} catch (Exception $e) {
    header('Location: employees.php?message=error');
    exit;
}