<?php
require 'db_conn.php';

echo "<h2>Testing Student Dashboard Queries</h2>";

// Test student user ID (LSC000001)
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ?");
$stmt->execute(['LSC000001']);
$user = $stmt->fetch();

if (!$user) {
    echo "<p style='color: red;'>Student user not found!</p>";
    exit;
}

echo "<p><strong>Student User:</strong> {$user['username']} (ID: {$user['id']})</p>";

// Test query 1: Get student profile
$stmt = $pdo->prepare("SELECT sp.full_name, sp.student_number, sp.balance FROM student_profile sp WHERE sp.user_id = ?");
$stmt->execute([$user['id']]);
$student = $stmt->fetch();

if ($student) {
    echo "<p><strong>Full Name:</strong> {$student['full_name']}</p>";
    echo "<p><strong>Student Number:</strong> {$student['student_number']}</p>";
    echo "<p><strong>Balance:</strong> K" . number_format($student['balance'], 2) . "</p>";
} else {
    echo "<p style='color: red;'>Student profile not found!</p>";
    exit;
}

// Test query 2: Count enrolled courses
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM course_enrollment WHERE student_user_id = ? AND status = 'enrolled'");
$stmt->execute([$user['id']]);
$enrolledCount = $stmt->fetch()['count'];
echo "<p><strong>Enrolled Courses:</strong> $enrolledCount</p>";

// Test query 3: Calculate GPA
$stmt = $pdo->prepare("
    SELECT AVG(
        CASE 
            WHEN r.grade = 'A' THEN 4.0
            WHEN r.grade = 'B+' THEN 3.5
            WHEN r.grade = 'B' THEN 3.0
            WHEN r.grade = 'C+' THEN 2.5
            WHEN r.grade = 'C' THEN 2.0
            WHEN r.grade = 'D' THEN 1.0
            ELSE 0
        END
    ) as gpa 
    FROM results r
    JOIN course_enrollment ce ON r.enrollment_id = ce.id
    WHERE ce.student_user_id = ? AND r.grade IS NOT NULL
");
$stmt->execute([$user['id']]);
$gpaResult = $stmt->fetch();
$gpa = $gpaResult['gpa'] ? number_format($gpaResult['gpa'], 2) : '0.00';
echo "<p><strong>Current GPA:</strong> $gpa</p>";

// Show enrollment details
echo "<h3>Course Enrollments:</h3>";
$stmt = $pdo->prepare("
    SELECT c.code, c.name, ce.academic_year, ce.semester, ce.status 
    FROM course_enrollment ce 
    JOIN course c ON ce.course_id = c.id 
    WHERE ce.student_user_id = ?
");
$stmt->execute([$user['id']]);
$enrollments = $stmt->fetchAll();

if ($enrollments) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Course Code</th><th>Course Name</th><th>Academic Year</th><th>Semester</th><th>Status</th></tr>";
    foreach ($enrollments as $enrollment) {
        echo "<tr>";
        echo "<td>{$enrollment['code']}</td>";
        echo "<td>{$enrollment['name']}</td>";
        echo "<td>{$enrollment['academic_year']}</td>";
        echo "<td>{$enrollment['semester']}</td>";
        echo "<td>{$enrollment['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No course enrollments found</p>";
}

echo "<h3 style='color: green;'>✓ All queries executed successfully!</h3>";
echo "<p><a href='student/dashboard.php'>Go to Student Dashboard</a></p>";
?>
