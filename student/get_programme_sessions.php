<?php
session_start();
require_once '../config.php';
require_once '../auth/auth.php';

// Check if user is logged in and has student role
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit();
}

requireRole('Student', $pdo);

try {
    // Get student's programme_id and intake_id
    $stmt = $pdo->prepare("SELECT programme_id, intake_id FROM student_profile WHERE user_id = ?");
    $stmt->execute([currentUserId()]);
    $student = $stmt->fetch();
    
    if (!$student) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Student profile not found'
        ]);
        exit();
    }
    
    if (!$student['programme_id']) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No programme assigned to your profile. Please contact the administrator to assign a programme to your account.'
        ]);
        exit();
    }
    
    $programme_id = $student['programme_id'];
    $intake_id = $student['intake_id'];
    
    // Get active sessions for this student
    // Option 1: Get sessions connected to the student's intake (if intake_id is available)
    if ($intake_id) {
        $sql = "
            SELECT DISTINCT 
                s.id,
                s.session_name,
                s.academic_year,
                s.term,
                s.start_date,
                s.end_date,
                s.status
            FROM academic_sessions s
            INNER JOIN programme_schedule ps ON s.id = ps.session_id
            WHERE s.status = 'active' 
            AND ps.intake_id = ?
            ORDER BY s.academic_year DESC, s.term
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$intake_id]);
    } else {
        // Option 2: If no intake is set, get sessions connected to the programme
        $sql = "
            SELECT DISTINCT 
                s.id,
                s.session_name,
                s.academic_year,
                s.term,
                s.start_date,
                s.end_date,
                s.status
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
    
    // Check if any sessions were found
    if (empty($sessions)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No active sessions found for your programme/intake. Please contact the administrator to set up sessions for your programme.',
            'programme_id' => $programme_id,
            'intake_id' => $intake_id
        ]);
        exit();
    }
    
    // Format sessions for dropdown display
    $formatted_sessions = [];
    foreach ($sessions as $session) {
        $formatted_sessions[] = [
            'id' => $session['id'],
            'display_name' => $session['session_name'] . ' (' . $session['academic_year'] . ' - ' . $session['term'] . ')',
            'session_name' => $session['session_name'],
            'academic_year' => $session['academic_year'],
            'term' => $session['term'],
            'start_date' => $session['start_date'],
            'end_date' => $session['end_date']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'sessions' => $formatted_sessions,
        'programme_id' => $programme_id
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching programme sessions: ' . $e->getMessage()
    ]);
}
?>