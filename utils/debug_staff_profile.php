<?php
require '../config.php';

echo "<h2>Debugging Staff Profile Issue</h2>\n";

try {
    // Check if there's a staff_profile entry for user_id 22
    echo "<h3>Checking if staff_profile entry exists for user_id 22:</h3>\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM staff_profile WHERE user_id = 22");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    echo "Number of staff_profile entries for user_id 22: " . $count . "<br>\n";
    
    if ($count == 0) {
        echo "No staff_profile entry exists for user_id 22. This is the problem!<br>\n";
        echo "The UPDATE query won't affect any rows if there's no existing entry.<br>\n";
        
        // Let's check what happens when we try to insert instead
        echo "<h4>Testing INSERT query:</h4>\n";
        $stmt = $pdo->prepare("INSERT INTO staff_profile (user_id, full_name, staff_id, NRC, gender, qualification) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([
            22,                         // user_id
            'Test Enrollment Officer',  // full_name
            'ENR002',                   // staff_id
            '123456/78/91',             // NRC
            'Female',                   // gender
            'Master of Education'       // qualification
        ]);
        
        if ($result) {
            echo "INSERT query executed successfully.<br>\n";
            
            // Check if the insert actually worked
            $stmt = $pdo->prepare("SELECT full_name FROM staff_profile WHERE user_id = 22");
            $stmt->execute();
            $insertedName = $stmt->fetchColumn();
            
            echo "Inserted full_name: " . htmlspecialchars($insertedName ?? 'NULL') . "<br>\n";
            
            // Clean up - delete the test entry
            $stmt = $pdo->prepare("DELETE FROM staff_profile WHERE user_id = 22");
            $stmt->execute();
            echo "Cleaned up test entry.<br>\n";
        } else {
            echo "INSERT query failed.<br>\n";
        }
    } else {
        echo "Staff profile entry exists. Let's check its contents:<br>\n";
        
        $stmt = $pdo->prepare("SELECT * FROM staff_profile WHERE user_id = 22");
        $stmt->execute();
        $profile = $stmt->fetch();
        
        if ($profile) {
            echo "Full Name: " . htmlspecialchars($profile['full_name'] ?? 'NULL') . "<br>\n";
            echo "Staff ID: " . htmlspecialchars($profile['staff_id'] ?? 'NULL') . "<br>\n";
            echo "NRC: " . htmlspecialchars($profile['NRC'] ?? 'NULL') . "<br>\n";
            echo "Gender: " . htmlspecialchars($profile['gender'] ?? 'NULL') . "<br>\n";
            echo "Qualification: " . htmlspecialchars($profile['qualification'] ?? 'NULL') . "<br>\n";
        }
    }
    
    // Let's also check the update logic in manage_users.php
    echo "<h3>Checking the update logic in manage_users.php:</h3>\n";
    
    // First, let's see what role user_id 22 has
    $stmt = $pdo->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = 22");
    $stmt->execute();
    $roleName = $stmt->fetchColumn();
    
    echo "User 22 Role: " . htmlspecialchars($roleName) . "<br>\n";
    
    // Check if the role is handled in the update logic
    if ($roleName == 'Lecturer' || $roleName == 'Sub Admin (Finance)' || $roleName == 'Enrollment Officer') {
        echo "This role IS handled in the update logic.<br>\n";
        echo "The issue is likely that there's no staff_profile entry to update.<br>\n";
        
        echo "<h4>Checking all users and their profile entries:</h4>\n";
        $stmt = $pdo->prepare("SELECT u.id, u.username, r.name as role_name,
                  CASE 
                    WHEN ap.user_id IS NOT NULL THEN 'admin_profile'
                    WHEN sp.user_id IS NOT NULL THEN 'student_profile'
                    WHEN stp.user_id IS NOT NULL THEN 'staff_profile'
                    ELSE 'no_profile'
                  END as profile_type
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
        echo "<tr><th>User ID</th><th>Username</th><th>Role</th><th>Profile Type</th></tr>\n";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role_name']) . "</td>";
            echo "<td>" . $user['profile_type'] . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "This role is NOT handled in the update logic.<br>\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>\n";
}
?>