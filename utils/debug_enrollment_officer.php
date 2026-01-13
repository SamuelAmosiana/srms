<?php
require '../config.php';

echo "<h2>Debugging Enrollment Officer Full Name Issue</h2>\n";

// Check the specific user with Enrollment Officer role
echo "<h3>Checking user with Enrollment Officer role (user_id = 20)</h3>\n";

try {
    // Get the user details
    $stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN roles r ON ur.role_id = r.id WHERE u.id = 20");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        echo "User ID: " . $user['id'] . "<br>\n";
        echo "Username: " . $user['username'] . "<br>\n";
        echo "Role: " . $user['role_name'] . "<br>\n";
    } else {
        echo "User not found<br>\n";
    }
    
    // Check if there's a staff profile
    echo "<h4>Staff Profile:</h4>\n";
    $stmt = $pdo->prepare("SELECT * FROM staff_profile WHERE user_id = 20");
    $stmt->execute();
    $staffProfile = $stmt->fetch();
    
    if ($staffProfile) {
        echo "Full Name: " . ($staffProfile['full_name'] ?? 'NULL') . "<br>\n";
        echo "Staff ID: " . ($staffProfile['staff_id'] ?? 'NULL') . "<br>\n";
    } else {
        echo "No staff profile found<br>\n";
    }
    
    // Run the exact query used in manage_users.php for this user
    echo "<h4>Running the exact query from manage_users.php:</h4>\n";
    $stmt = $pdo->prepare("SELECT u.id as user_id, u.username, u.is_active, u.created_at, r.name as role_name,
              COALESCE(ap.full_name, sp.full_name, stp.full_name) as full_name,
              u.email,
              COALESCE(ap.staff_id, sp.student_number, stp.staff_id) as identifier
              FROM users u 
              JOIN user_roles ur ON u.id = ur.user_id
              JOIN roles r ON ur.role_id = r.id 
              LEFT JOIN admin_profile ap ON u.id = ap.user_id
              LEFT JOIN student_profile sp ON u.id = sp.user_id
              LEFT JOIN staff_profile stp ON u.id = stp.user_id
              WHERE u.id = 20");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result) {
        echo "Query Result:<br>\n";
        echo "User ID: " . $result['user_id'] . "<br>\n";
        echo "Username: " . $result['username'] . "<br>\n";
        echo "Role: " . $result['role_name'] . "<br>\n";
        echo "Full Name: " . ($result['full_name'] ?? 'NULL') . "<br>\n";
        echo "Email: " . ($result['email'] ?? 'NULL') . "<br>\n";
        echo "Identifier: " . ($result['identifier'] ?? 'NULL') . "<br>\n";
    } else {
        echo "Query returned no results<br>\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>\n";
}

// Let's also check all users to see if the issue is specific to Enrollment Officers
echo "<h3>Checking all users with their full names:</h3>\n";
try {
    $stmt = $pdo->prepare("SELECT u.id as user_id, u.username, r.name as role_name,
              COALESCE(ap.full_name, sp.full_name, stp.full_name) as full_name
              FROM users u 
              JOIN user_roles ur ON u.id = ur.user_id
              JOIN roles r ON ur.role_id = r.id 
              LEFT JOIN admin_profile ap ON u.id = ap.user_id
              LEFT JOIN student_profile sp ON u.id = sp.user_id
              LEFT JOIN staff_profile stp ON u.id = stp.user_id
              ORDER BY u.id");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    echo "<table border='1'>\n";
    echo "<tr><th>User ID</th><th>Username</th><th>Role</th><th>Full Name</th></tr>\n";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['user_id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['role_name']) . "</td>";
        echo "<td>" . htmlspecialchars($user['full_name'] ?? 'NULL') . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>\n";
}
?>