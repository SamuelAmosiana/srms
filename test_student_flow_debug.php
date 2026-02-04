<?php
// Test the student session and course loading flow
require_once 'config.php';

echo "Testing student session and course loading flow...\n\n";

// First, let's check if there are active sessions
echo "1. Checking active sessions:\n";
$stmt = $pdo->query("SELECT id, session_name, status FROM academic_sessions WHERE status = 'active'");
$sessions = $stmt->fetchAll();
foreach($sessions as $s) {
    echo "Session ID: {$s['id']}, Name: {$s['session_name']}, Status: {$s['status']}\n";
}

if (count($sessions) == 0) {
    echo "ERROR: No active sessions found!\n";
    exit;
}

// Test get_programme_sessions.php
echo "\n2. Testing get_programme_sessions.php with student user_id=39:\n";
// Mock the session for the include
$_SESSION['user_id'] = 39;

// Include and capture output
ob_start();
include 'student/get_programme_sessions.php';
$output = ob_get_clean();

echo "Raw output:\n$output\n";

$response = json_decode($output, true);
if ($response && $response['success']) {
    echo "SUCCESS: Found " . count($response['sessions']) . " programme sessions\n";
    foreach ($response['sessions'] as $session) {
        echo "  - {$session['display_name']} (ID: {$session['id']})\n";
    }
} else {
    echo "ERROR in get_programme_sessions.php: " . ($response['message'] ?? 'Unknown error') . "\n";
}

// Test get_session_courses.php with a valid session ID
echo "\n3. Testing get_session_courses.php with session_id=1:\n";
$_GET['session_id'] = 1;

ob_start();
include 'student/get_session_courses.php';
$output = ob_get_clean();

echo "Raw output:\n$output\n";

$response = json_decode($output, true);
if ($response && $response['success']) {
    echo "SUCCESS: Found courses for session\n";
    echo "Courses by term:\n";
    foreach ($response['courses_by_term'] as $term => $courses) {
        echo "  Term: $term (" . count($courses) . " courses)\n";
        foreach ($courses as $course) {
            $type = $course['is_session_course'] ? 'SESSION' : 'PROGRAMME';
            echo "    - {$course['course_code']}: {$course['course_name']} [$type]\n";
        }
    }
} else {
    echo "ERROR in get_session_courses.php: " . ($response['message'] ?? 'Unknown error') . "\n";
}

echo "\nTest completed!\n";
?>