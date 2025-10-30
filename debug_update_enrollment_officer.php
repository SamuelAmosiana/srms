<?php
require 'config.php';

echo "<h2>Debugging Enrollment Officer Update Issue</h2>\n";

// Check if there's a user with NULL full_name and Enrollment Officer role
echo "<h3>Checking for Enrollment Officers with NULL full_name:</h3>\n";

try {
    $stmt = $pdo->prepare("SELECT u.id as user_id, u.username, r.name as role_name, stp.full_name
              FROM users u 
              JOIN user_roles ur ON u.id = ur.user_id
              JOIN roles r ON ur.role_id = r.id 
              LEFT JOIN staff_profile stp ON u.id = stp.user_id
              WHERE r.name = 'Enrollment Officer' AND (stp.full_name IS NULL OR stp.full_name = '')");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "No Enrollment Officers with NULL or empty full_name found.<br>\n";
    } else {
        echo "Found " . count($users) . " Enrollment Officers with NULL or empty full_name:<br>\n";
        foreach ($users as $user) {
            echo "- User ID: " . $user['user_id'] . ", Username: " . htmlspecialchars($user['username']) . ", Full Name: " . htmlspecialchars($user['full_name'] ?? 'NULL') . "<br>\n";
        }
        
        // Let's check user_id 22 specifically since that was in our previous debug
        echo "<h4>Checking user_id 22 in detail:</h4>\n";
        $stmt = $pdo->prepare("SELECT u.*, r.name as role_name, stp.* 
                  FROM users u 
                  JOIN user_roles ur ON u.id = ur.user_id
                  JOIN roles r ON ur.role_id = r.id 
                  LEFT JOIN staff_profile stp ON u.id = stp.user_id
                  WHERE u.id = 22");
        $stmt->execute();
        $userDetail = $stmt->fetch();
        
        if ($userDetail) {
            echo "User details:<br>\n";
            echo "ID: " . $userDetail['id'] . "<br>\n";
            echo "Username: " . htmlspecialchars($userDetail['username']) . "<br>\n";
            echo "Role: " . htmlspecialchars($userDetail['role_name']) . "<br>\n";
            echo "Staff Profile Full Name: " . htmlspecialchars($userDetail['full_name'] ?? 'NULL') . "<br>\n";
            echo "Staff Profile Staff ID: " . htmlspecialchars($userDetail['staff_id'] ?? 'NULL') . "<br>\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>\n";
}

// Let's simulate what happens during an update
echo "<h3>Simulating an update for an Enrollment Officer:</h3>\n";

try {
    // Get the role name for user_id 22
    $stmt = $pdo->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = 22");
    $stmt->execute();
    $roleName = $stmt->fetchColumn();
    
    echo "User 22 Role: " . htmlspecialchars($roleName) . "<br>\n";
    
    if ($roleName == 'Enrollment Officer') {
        echo "This user has the Enrollment Officer role. Testing update query:<br>\n";
        
        // Simulate the update query that should be run
        $stmt = $pdo->prepare("UPDATE staff_profile SET full_name = ?, staff_id = ?, NRC = ?, gender = ?, qualification = ? WHERE user_id = ?");
        $result = $stmt->execute([
            'Test Enrollment Officer',  // full_name
            'ENR002',                   // staff_id
            '123456/78/91',             // NRC
            'Female',                   // gender
            'Master of Education',      // qualification
            22                          // user_id
        ]);
        
        if ($result) {
            echo "Update query executed successfully.<br>\n";
            
            // Check if the update actually worked
            $stmt = $pdo->prepare("SELECT full_name FROM staff_profile WHERE user_id = 22");
            $stmt->execute();
            $updatedName = $stmt->fetchColumn();
            
            echo "Updated full_name: " . htmlspecialchars($updatedName ?? 'NULL') . "<br>\n";
        } else {
            echo "Update query failed.<br>\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error during simulation: " . $e->getMessage() . "<br>\n";
}
?>