<?php
require 'config.php';

try {
    // Get the Enrollment Officer role ID
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
    $stmt->execute(['Enrollment Officer']);
    $role = $stmt->fetch();
    
    if (!$role) {
        echo "Enrollment Officer role not found!\n";
        exit;
    }
    
    $roleId = $role['id'];
    echo "Enrollment Officer role ID: " . $roleId . "\n\n";
    
    // Get all users with the Enrollment Officer role
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, sp.full_name, sp.staff_id 
        FROM users u
        JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN staff_profile sp ON u.id = sp.user_id
        WHERE ur.role_id = ?
        ORDER BY u.id
    ");
    $stmt->execute([$roleId]);
    $officers = $stmt->fetchAll();
    
    echo "Enrollment Officers in the system:\n";
    echo "==================================\n";
    foreach ($officers as $officer) {
        echo "ID: " . $officer['id'] . ", Name: " . ($officer['full_name'] ?? 'N/A') . 
             ", Staff ID: " . ($officer['staff_id'] ?? 'N/A') . 
             ", Email: " . $officer['email'] . "\n";
    }
    
    // Get statistics for each officer
    echo "\nProcessing Statistics:\n";
    echo "=====================\n";
    foreach ($officers as $officer) {
        // Count approvals
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE processed_by = ? AND status = 'approved'");
        $stmt->execute([$officer['id']]);
        $approvals = $stmt->fetchColumn();
        
        // Count rejections
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE processed_by = ? AND status = 'rejected'");
        $stmt->execute([$officer['id']]);
        $rejections = $stmt->fetchColumn();
        
        // Count total processed
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE processed_by = ?");
        $stmt->execute([$officer['id']]);
        $total = $stmt->fetchColumn();
        
        echo "Officer: " . ($officer['full_name'] ?? $officer['username']) . "\n";
        echo "  Total Processed: " . $total . "\n";
        echo "  Approved: " . $approvals . "\n";
        echo "  Rejected: " . $rejections . "\n";
        echo "  Approval Rate: " . ($total > 0 ? round(($approvals / $total) * 100, 2) : 0) . "%\n\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>