<?php
require 'db_conn.php';

echo "<h2>Testing Lecturer Dashboard Queries</h2>";

// Test lecturer user ID (lecturer1@lsc.ac.zm)
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ?");
$stmt->execute(['lecturer1@lsc.ac.zm']);
$lecturer = $stmt->fetch();

if (!$lecturer) {
    echo "<p style='color: red;'>Lecturer not found!</p>";
    exit;
}

echo "<p><strong>Lecturer:</strong> {$lecturer['username']} (ID: {$lecturer['id']})</p>";

// Test query 1: Count courses taught by lecturer
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM course_assignment WHERE lecturer_id = ? AND is_active = 1");
$stmt->execute([$lecturer['id']]);
$coursesCount = $stmt->fetch()['count'];
echo "<p><strong>Total Courses:</strong> $coursesCount</p>";

// Test query 2: Count total students enrolled in lecturer's courses
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ce.student_user_id) as count 
    FROM course_enrollment ce 
    JOIN course_assignment ca ON ce.course_id = ca.course_id 
    WHERE ca.lecturer_id = ? AND ca.is_active = 1 AND ce.status = 'enrolled'
");
$stmt->execute([$lecturer['id']]);
$studentsCount = $stmt->fetch()['count'];
echo "<p><strong>Total Students:</strong> $studentsCount</p>";

// Test query 3: Count pending results
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM results r
    JOIN course_enrollment ce ON r.enrollment_id = ce.id
    JOIN course_assignment ca ON ce.course_id = ca.course_id
    WHERE ca.lecturer_id = ? AND ca.is_active = 1 
    AND (r.total_score = 0 OR r.total_score IS NULL)
");
$stmt->execute([$lecturer['id']]);
$pendingCount = $stmt->fetch()['count'];
echo "<p><strong>Pending Results:</strong> $pendingCount</p>";

// Show course details
echo "<h3>Course Assignments:</h3>";
$stmt = $pdo->prepare("
    SELECT c.code, c.name, ca.academic_year, ca.semester 
    FROM course_assignment ca 
    JOIN course c ON ca.course_id = c.id 
    WHERE ca.lecturer_id = ? AND ca.is_active = 1
");
$stmt->execute([$lecturer['id']]);
$courses = $stmt->fetchAll();

if ($courses) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Course Code</th><th>Course Name</th><th>Academic Year</th><th>Semester</th></tr>";
    foreach ($courses as $course) {
        echo "<tr>";
        echo "<td>{$course['code']}</td>";
        echo "<td>{$course['name']}</td>";
        echo "<td>{$course['academic_year']}</td>";
        echo "<td>{$course['semester']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No courses assigned</p>";
}

echo "<h3 style='color: green;'>✓ All queries executed successfully!</h3>";
echo "<p><a href='lecturer/dashboard.php'>Go to Lecturer Dashboard</a></p>";
?>
