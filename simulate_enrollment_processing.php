<?php
require 'config.php';

try {
    // Get enrollment officers
    $stmt = $pdo->prepare("SELECT u.id FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN roles r ON ur.role_id = r.id WHERE r.name = 'Enrollment Officer' LIMIT 2");
    $stmt->execute();
    $officers = $stmt->fetchAll();
    
    if (count($officers) < 2) {
        echo "Need at least 2 enrollment officers for simulation\n";
        exit;
    }
    
    // Get some pending applications
    $stmt = $pdo->prepare("SELECT id FROM applications WHERE status = 'pending' LIMIT 10");
    $stmt->execute();
    $applications = $stmt->fetchAll();
    
    if (empty($applications)) {
        // Create some test applications if none exist
        echo "Creating test applications...\n";
        for ($i = 1; $i <= 5; $i++) {
            $stmt = $pdo->prepare("INSERT INTO applications (full_name, email, programme_id, intake_id, status, created_at) VALUES (?, ?, 1, 1, 'pending', NOW())");
            $stmt->execute(["Applicant $i", "applicant$i@example.com"]);
        }
        
        $stmt = $pdo->prepare("SELECT id FROM applications WHERE status = 'pending' LIMIT 10");
        $stmt->execute();
        $applications = $stmt->fetchAll();
    }
    
    // Simulate processing by enrollment officers
    echo "Simulating enrollment processing...\n";
    
    $officer1 = $officers[0]['id'];
    $officer2 = $officers[1]['id'];
    
    // Officer 1 processes some applications (mostly approvals)
    $applications_for_officer1 = array_slice($applications, 0, 3);
    foreach ($applications_for_officer1 as $app) {
        $status = (rand(1, 10) <= 8) ? 'approved' : 'rejected'; // 80% approval rate
        $rejection_reason = ($status == 'rejected') ? 'Documents incomplete' : null;
        
        $stmt = $pdo->prepare("UPDATE applications SET status = ?, rejection_reason = ?, processed_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $rejection_reason, $officer1, $app['id']]);
    }
    
    // Officer 2 processes some applications (mixed approvals/rejections)
    $applications_for_officer2 = array_slice($applications, 3, 4);
    foreach ($applications_for_officer2 as $app) {
        $status = (rand(1, 10) <= 5) ? 'approved' : 'rejected'; // 50% approval rate
        $rejection_reason = ($status == 'rejected') ? 'Does not meet requirements' : null;
        
        $stmt = $pdo->prepare("UPDATE applications SET status = ?, rejection_reason = ?, processed_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $rejection_reason, $officer2, $app['id']]);
    }
    
    echo "Simulation completed successfully!\n";
    echo "Officer $officer1 processed " . count($applications_for_officer1) . " applications\n";
    echo "Officer $officer2 processed " . count($applications_for_officer2) . " applications\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>