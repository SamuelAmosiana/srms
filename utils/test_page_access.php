<?php
// Simulate accessing view_employee.php with a specific employee ID
$employee_id = 'LSC001';

echo "Testing access to view_employee.php with employee ID: " . $employee_id . "\n";

// This is just to check if the page structure is correct
if (file_exists('human_resource/view_employee.php')) {
    echo "view_employee.php file exists\n";
} else {
    echo "view_employee.php file does NOT exist\n";
}

if (file_exists('human_resource/edit_employee.php')) {
    echo "edit_employee.php file exists\n";
} else {
    echo "edit_employee.php file does NOT exist\n";
}

if (file_exists('human_resource/employees.php')) {
    echo "employees.php file exists\n";
} else {
    echo "employees.php file does NOT exist\n";
}
?>