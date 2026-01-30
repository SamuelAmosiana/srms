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

// Get parameters
$session_id = (int)($_GET['session_id'] ?? 0);

if ($session_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Session ID is required'
    ]);
    exit();
}

try {
    // Get student's programme_id
    $stmt = $pdo->prepare("SELECT programme_id FROM student_profile WHERE user_id = ?");
    $stmt->execute([currentUserId()]);
    $student = $stmt->fetch();
    
    if (!$student || !$student['programme_id']) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Student profile not found or programme not assigned'
        ]);
        exit();
    }
    
    $programme_id = $student['programme_id'];
    
    // Check if session exists and is active
    $stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE id = ? AND status = 'active'");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();
    
    if (!$session) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Session not found or not active'
        ]);
        exit();
    }
    
    // Get courses defined for this session and programme
    // This gets the session-specific courses that have been assigned by admin
    $session_courses_sql = "
        SELECT 
            c.id as course_id,
            c.code as course_code,
            c.name as course_name,
            c.description,
            c.credits,
            spc.term,
            1 as is_session_course  -- Flag to indicate this is a session-defined course
        FROM session_programme_courses spc
        JOIN course c ON spc.course_id = c.id
        WHERE spc.session_id = ? AND spc.programme_id = ?
        ORDER BY spc.term, c.name
    ";
    
    $stmt = $pdo->prepare($session_courses_sql);
    $stmt->execute([$session_id, $programme_id]);
    $session_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all programme courses (for hybrid approach - showing additional courses)
    // This allows students to select from all programme courses, not just session-defined ones
    $programme_courses_sql = "
        SELECT 
            c.id as course_id,
            c.code as course_code,
            c.name as course_name,
            c.description,
            c.credits,
            'General' as term,  -- Default term for programme courses
            0 as is_session_course  -- Flag to indicate this is a programme course
        FROM course c
        WHERE c.programme_id = ?
        AND c.id NOT IN (
            SELECT course_id FROM session_programme_courses 
            WHERE session_id = ? AND programme_id = ?
        )
        ORDER BY c.name
    ";
    
    $stmt = $pdo->prepare($programme_courses_sql);
    $stmt->execute([$programme_id, $session_id, $programme_id]);
    $programme_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no session-specific courses are defined, show all programme courses under a general term
    if (count($session_courses) === 0 && count($programme_courses) > 0) {
        // Treat all programme courses as available for the session
        foreach ($programme_courses as &$course) {
            $course['is_session_course'] = 0;  // These are programme courses
        }
        $all_courses = $programme_courses;
    } else {
        // Combine session courses and programme courses
        $all_courses = array_merge($session_courses, $programme_courses);
    }
    
    // Group courses by term for better organization
    $courses_by_term = [];
    foreach ($all_courses as $course) {
        $term = $course['term'];
        if (!isset($courses_by_term[$term])) {
            $courses_by_term[$term] = [];
        }
        $courses_by_term[$term][] = $course;
    }
    
    // Get currently registered courses for this session
    $current_year = date('Y');
    $registered_courses_stmt = $pdo->prepare("
        SELECT ce.course_id
        FROM course_enrollment ce
        WHERE ce.student_user_id = ? AND ce.academic_year = ?
    ");
    $registered_courses_stmt->execute([currentUserId(), $current_year]);
    $registered_courses = $registered_courses_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'session_info' => [
            'id' => $session['id'],
            'session_name' => $session['session_name'],
            'academic_year' => $session['academic_year'],
            'term' => $session['term']
        ],
        'programme_id' => $programme_id,
        'courses_by_term' => $courses_by_term,
        'registered_courses' => $registered_courses,
        'total_session_courses' => count($session_courses),
        'total_programme_courses' => count($programme_courses)
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching session courses: ' . $e->getMessage()
    ]);
}
?>