<?php
require_once __DIR__ . '/../config.php';

echo "Starting synchronization of student numbers...\n";

try {
    // Get all records from registered_students that have a student_id and no student_number or 'Not Generated'
    $stmt = $pdo->prepare("
        SELECT rs.id, rs.student_id, rs.student_name, rs.student_number as current_student_number
        FROM registered_students rs
        WHERE rs.student_id > 0 
        AND (rs.student_number IS NULL OR rs.student_number = '' OR rs.student_number = 'Not Generated')
    ");
    $stmt->execute();
    $records = $stmt->fetchAll();
    
    $updatedCount = 0;
    
    foreach ($records as $record) {
        // Get the student number from student_profile table
        $profileStmt = $pdo->prepare("SELECT student_number FROM student_profile WHERE user_id = ?");
        $profileStmt->execute([$record['student_id']]);
        $profile = $profileStmt->fetch();
        
        if ($profile && !empty($profile['student_number'])) {
            // Update the registered_students table with the correct student number
            $updateStmt = $pdo->prepare("UPDATE registered_students SET student_number = ? WHERE id = ?");
            $result = $updateStmt->execute([$profile['student_number'], $record['id']]);
            
            if ($result) {
                echo "Updated record ID {$record['id']} for student {$record['student_name']} with number: {$profile['student_number']}\n";
                $updatedCount++;
            } else {
                echo "Failed to update record ID {$record['id']}\n";
            }
        } else {
            echo "No student number found in profile for student ID {$record['student_id']} ({$record['student_name']})\n";
        }
    }
    
    echo "\nSynchronization completed!\n";
    echo "Total records updated: $updatedCount\n";
    
} catch (Exception $e) {
    echo "Error during synchronization: " . $e->getMessage() . "\n";
}
?>