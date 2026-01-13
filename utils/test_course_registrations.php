<?php
// Test if course_registrations.php queries work now
require_once 'config.php';

try {
    echo "Testing course registration queries...\n";
    
    // Test pending registrations query
    $pending_query = "
        SELECT cr.*, sp.full_name, sp.student_id, c.name as course_name, i.name as intake_name
        FROM course_registration cr
        JOIN student_profile sp ON cr.student_id = sp.user_id
        JOIN course c ON cr.course_id = c.id
        JOIN intake i ON sp.intake_id = i.id
        WHERE cr.status = 'pending'
        ORDER BY cr.submitted_at DESC
    ";
    $pending_registrations = $pdo->query($pending_query)->fetchAll();
    echo "✅ Pending registrations query successful\n";
    
    // Test intakes query
    $intakes = $pdo->query("SELECT * FROM intake ORDER BY start_date DESC")->fetchAll();
    echo "✅ Intakes query successful\n";
    
    // Test courses query
    $courses = $pdo->query("SELECT * FROM course ORDER BY name")->fetchAll();
    echo "✅ Courses query successful\n";
    
    // Test defined courses query
    $defined_courses_query = "
        SELECT ic.*, i.name as intake_name, c.name as course_name
        FROM intake_courses ic
        JOIN intake i ON ic.intake_id = i.id
        JOIN course c ON ic.course_id = c.id
        ORDER BY i.name, ic.term, c.name
    ";
    $defined_courses = $pdo->query($defined_courses_query)->fetchAll();
    echo "✅ Defined courses query successful\n";
    
    echo "\n🎉 All course registration queries successful!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>