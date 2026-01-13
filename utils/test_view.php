<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in
if (!currentUserId()) {
    echo "User not logged in\n";
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
    echo "User does not have HR Manager role\n";
    exit;
}

// Get employee ID from URL
$employee_id = $_GET['id'] ?? null;

if (!$employee_id) {
    echo "No employee ID provided\n";
    exit;
}

// Fetch employee details
$stmt = $pdo->prepare("
    SELECT e.*, d.name as department_name 
    FROM employees e 
    LEFT JOIN department d ON e.department_id = d.id 
    WHERE e.employee_id = ?
");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();

if (!$employee) {
    echo "Employee not found\n";
    exit;
}

echo "Employee found:\n";
print_r($employee);
?>