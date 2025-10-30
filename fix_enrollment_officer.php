<?php
require 'config.php';

echo "<h2>Fixing Enrollment Officer Profile Issue</h2>\n";

try {
    // Check if there's a staff_profile entry for user_id 22
    echo "<h3>Checking if staff_profile entry exists for user_id 22:</h3>\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM staff_profile WHERE user_id = 22");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    echo "Number of staff_profile entries for user_id 22: " . $count . "<br>\n";
    
    if ($count == 0) {
        echo "No staff_profile entry exists for user_id 22. Creating one...<br>\n";
        
        // Insert a staff_profile entry for user_id 22
        $stmt = $pdo->prepare("INSERT INTO staff_profile (user_id, full_name, staff_id, NRC, gender, qualification) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([
            22,                         // user_id
            'Jessy Enrollment Officer', // full_name
            'ENR003',                   // staff_id
            '123456/78/92',             // NRC
            'Female',                   // gender
            'Bachelor of Education'     // qualification
        ]);
        
        if ($result) {
            echo "Successfully created staff_profile entry for user_id 22.<br>\n";
            
            // Verify the entry was created
            $stmt = $pdo->prepare("SELECT full_name, staff_id FROM staff_profile WHERE user_id = 22");
            $stmt->execute();
            $profile = $stmt->fetch();
            
            if ($profile) {
                echo "Verified entry:<br>\n";
                echo "Full Name: " . htmlspecialchars($profile['full_name']) . "<br>\n";
                echo "Staff ID: " . htmlspecialchars($profile['staff_id']) . "<br>\n";
            }
        } else {
            echo "Failed to create staff_profile entry.<br>\n";
        }
    } else {
        echo "Staff profile entry already exists for user_id 22.<br>\n";
        
        // Update the existing entry to ensure it has a full_name
        $stmt = $pdo->prepare("UPDATE staff_profile SET full_name = ? WHERE user_id = 22");
        $result = $stmt->execute(['Jessy Enrollment Officer']);
        
        if ($result) {
            echo "Updated staff_profile entry for user_id 22.<br>\n";
        } else {
            echo "Failed to update staff_profile entry.<br>\n";
        }
    }
    
    // Test the user list query again to see if the full name now appears
    echo "<h3>Testing user list query after fix:</h3>\n";
    $stmt = $pdo->prepare("SELECT u.id as user_id, u.username, r.name as role_name,
              COALESCE(ap.full_name, sp.full_name, stp.full_name) as full_name
              FROM users u 
              JOIN user_roles ur ON u.id = ur.user_id
              JOIN roles r ON ur.role_id = r.id 
              LEFT JOIN admin_profile ap ON u.id = ap.user_id
              LEFT JOIN student_profile sp ON u.id = sp.user_id
              LEFT JOIN staff_profile stp ON u.id = stp.user_id
              WHERE u.id = 22");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result) {
        echo "Query Result for user_id 22:<br>\n";
        echo "User ID: " . $result['user_id'] . "<br>\n";
        echo "Username: " . htmlspecialchars($result['username']) . "<br>\n";
        echo "Role: " . htmlspecialchars($result['role_name']) . "<br>\n";
        echo "Full Name: " . htmlspecialchars($result['full_name'] ?? 'NULL') . "<br>\n";
    } else {
        echo "Query returned no results for user_id 22<br>\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>\n";
}

echo "<p>Refresh the manage_users.php page to see if the full name now appears correctly.</p>\n";
?>