<?php
// Test script to check what get_programme_sessions.php returns
chdir(__DIR__);
require 'config.php';

echo "Testing get_programme_sessions.php logic...\n\n";

// Get all students
$stmt = $pdo->query("SELECT user_id, full_name, programme_id, intake_id FROM student_profile LIMIT 5");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($students as $student) {
    echo "Student: {$student['full_name']} (user_id: {$student['user_id']})\n";
    echo "  Programme ID: {$student['programme_id']}, Intake ID: {$student['intake_id']}\n";
    
    $programme_id = $student['programme_id'];
    $intake_id = $student['intake_id'];
    
    // Logic from get_programme_sessions.php
    if ($intake_id) {
        $sql = "
            SELECT DISTINCT 
                s.id,
                s.session_name,
                s.academic_year,
                s.term
            FROM academic_sessions s
            INNER JOIN programme_schedule ps ON s.id = ps.session_id
            WHERE s.status = 'active' 
            AND ps.intake_id = ?
            ORDER BY s.academic_year DESC, s.term
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$intake_id]);
    } else {
        $sql = "
            SELECT DISTINCT 
                s.id,
                s.session_name,
                s.academic_year,
                s.term
            FROM academic_sessions s
            INNER JOIN programme_schedule ps ON s.id = ps.session_id
            WHERE s.status = 'active' 
            AND ps.programme_id = ?
            ORDER BY s.academic_year DESC, s.term
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$programme_id]);
    }
    
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  Sessions found: " . count($sessions) . "\n";
    if (count($sessions) > 0) {
        foreach ($sessions as $session) {
            echo "    - Session {$session['id']}: {$session['session_name']} ({$session['academic_year']} - {$session['term']})\n";
        }
    }
    echo "\n";
}
?>
