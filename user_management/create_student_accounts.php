<?php
require '../config.php';

echo "Creating student accounts from pending students...\n";

try {
    // Get all pending students
    $stmt = $pdo->query("SELECT * FROM pending_students ORDER BY id");
    $pendingStudents = $stmt->fetchAll();
    
    if (empty($pendingStudents)) {
        echo "No pending students found.\n";
        exit;
    }
    
    echo "Found " . count($pendingStudents) . " pending students.\n";
    
    foreach ($pendingStudents as $student) {
        echo "Processing: " . $student['full_name'] . " (" . $student['email'] . ")\n";
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Check if user already exists with this email
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->execute([$student['email']]);
            $existingUser = $checkStmt->fetch();
            
            if ($existingUser) {
                echo "  User already exists with ID: " . $existingUser['id'] . "\n";
                $userId = $existingUser['id'];
            } else {
                // Create user account
                $password = 'LSC' . str_pad($student['id'], 6, '0', STR_PAD_LEFT); // Default password
                $hash = password_hash($password, PASSWORD_DEFAULT);
                
                $userStmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, contact, is_active) VALUES (?, ?, ?, ?, 1)");
                $userStmt->execute([$student['email'], $student['email'], $hash, '']);
                $userId = $pdo->lastInsertId();
                echo "  Created user account with ID: " . $userId . "\n";
            }
            
            // Check if student profile already exists
            $checkProfileStmt = $pdo->prepare("SELECT user_id FROM student_profile WHERE user_id = ?");
            $checkProfileStmt->execute([$userId]);
            $existingProfile = $checkProfileStmt->fetch();
            
            if ($existingProfile) {
                echo "  Student profile already exists.\n";
            } else {
                // Create student profile with LSC format student number
                $studentNumber = 'LSC' . str_pad($userId, 6, '0', STR_PAD_LEFT);
                
                $profileStmt = $pdo->prepare("INSERT INTO student_profile (user_id, full_name, student_number, programme_id, intake_id) VALUES (?, ?, ?, ?, ?)");
                $profileStmt->execute([
                    $userId,
                    $student['full_name'],
                    $studentNumber,
                    $student['programme_id'],
                    $student['intake_id']
                ]);
                echo "  Created student profile with number: " . $studentNumber . "\n";
            }
            
            // Check if student role is assigned
            $checkRoleStmt = $pdo->prepare("SELECT user_id FROM user_roles WHERE user_id = ? AND role_id = (SELECT id FROM roles WHERE name = 'Student')");
            $checkRoleStmt->execute([$userId]);
            $existingRole = $checkRoleStmt->fetch();
            
            if ($existingRole) {
                echo "  Student role already assigned.\n";
            } else {
                // Assign student role
                $roleStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name = 'Student'))");
                $roleStmt->execute([$userId]);
                echo "  Assigned student role.\n";
            }
            
            // Remove from pending_students
            $deleteStmt = $pdo->prepare("DELETE FROM pending_students WHERE id = ?");
            $deleteStmt->execute([$student['id']]);
            echo "  Removed from pending students.\n";
            
            // Commit transaction
            $pdo->commit();
            echo "  ✅ Successfully processed " . $student['full_name'] . "\n\n";
            
        } catch (Exception $e) {
            // Rollback transaction
            $pdo->rollback();
            echo "  ❌ Error processing " . $student['full_name'] . ": " . $e->getMessage() . "\n\n";
        }
    }
    
    echo "Finished processing all pending students.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>