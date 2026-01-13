<?php
// Test script to generate password hashes for the database
echo "<h2>Password Hashes for LSC SRMS</h2>";

$passwords = [
    'Admin@123' => 'Super Admin',
    'Lecturer@123' => 'Lecturer', 
    'Finance@123' => 'Sub Admin (Finance)',
    'LSC000001' => 'Student'
];

foreach ($passwords as $password => $role) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "<p><strong>$role:</strong><br>";
    echo "Password: $password<br>";
    echo "Hash: $hash</p>";
}

echo "<h3>Database Setup Commands:</h3>";
echo "<pre>";
echo "UPDATE users SET password_hash = '" . password_hash('Admin@123', PASSWORD_DEFAULT) . "' WHERE username = 'admin@lsc.ac.zm';\n";
echo "UPDATE users SET password_hash = '" . password_hash('Lecturer@123', PASSWORD_DEFAULT) . "' WHERE username = 'lecturer1@lsc.ac.zm';\n";
echo "UPDATE users SET password_hash = '" . password_hash('Finance@123', PASSWORD_DEFAULT) . "' WHERE username = 'finance@lsc.ac.zm';\n";
echo "UPDATE users SET password_hash = '" . password_hash('LSC000001', PASSWORD_DEFAULT) . "' WHERE username = 'LSC000001';\n";
echo "</pre>";
?>