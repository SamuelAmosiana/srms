<?php
session_start();
require_once '../config.php';
require_once '../auth/auth.php';

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit();
}

// Check if user has admin role or manage_academic_structure permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('manage_academic_structure', $pdo)) {
    header('Location: ../auth/login.php');
    exit();
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests allowed'
    ]);
    exit();
}

try {
    // Get and validate input parameters
    $session_id = (int)($_POST['session_id'] ?? 0);
    $action_type = $_POST['action_type'] ?? 'new_existing'; // new_existing, existing_only, new_only
    $student_number = trim($_POST['student_number'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $programme_id = (int)($_POST['programme_id'] ?? 0);
    $intake_id = (int)($_POST['intake_id'] ?? 0);

    // Validate required parameters
    if ($session_id <= 0) {
        throw new Exception('Session ID is required');
    }

    // Check if session exists
    $session_stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE id = ?");
    $session_stmt->execute([$session_id]);
    $session = $session_stmt->fetch();
    if (!$session) {
        throw new Exception('Session not found');
    }

    // Validate action type
    $valid_action_types = ['new_existing', 'existing_only', 'new_only'];
    if (!in_array($action_type, $valid_action_types)) {
        throw new Exception('Invalid action type');
    }

    $result = [];

    if ($action_type === 'existing_only' || ($action_type === 'new_existing' && !empty($student_number))) {
        // Try to assign existing student
        if (empty($student_number)) {
            throw new Exception('Student number is required for existing student assignment');
        }

        // Check if student exists
        $student_stmt = $pdo->prepare("SELECT * FROM student_profile WHERE student_number = ?");
        $student_stmt->execute([$student_number]);
        $student = $student_stmt->fetch();

        if (!$student) {
            throw new Exception("Student with number '$student_number' not found");
        }

        // Assign student to session via intake association
        if ($intake_id > 0) {
            // Update student's intake_id to match the session
            $update_stmt = $pdo->prepare("UPDATE student_profile SET intake_id = ? WHERE student_number = ?");
            $update_stmt->execute([$intake_id, $student_number]);

            // Check if a programme_schedule entry exists for this combination
            $check_schedule_stmt = $pdo->prepare("
                SELECT id FROM programme_schedule 
                WHERE programme_id = ? AND session_id = ? AND intake_id = ?
            ");
            $check_schedule_stmt->execute([$student['programme_id'], $session_id, $intake_id]);
            $schedule_exists = $check_schedule_stmt->fetch();

            if (!$schedule_exists) {
                // Create programme_schedule entry
                $insert_schedule_stmt = $pdo->prepare("
                    INSERT INTO programme_schedule (programme_id, session_id, intake_id, year_of_study)
                    VALUES (?, ?, ?, 1)
                ");
                $insert_schedule_stmt->execute([$student['programme_id'], $session_id, $intake_id]);
            }

            $result = [
                'success' => true,
                'message' => "Student '$student_number' successfully assigned to session '{$session['session_name']}'",
                'student_id' => $student['user_id'],
                'student_number' => $student_number,
                'action' => 'assigned_existing'
            ];
        } else {
            throw new Exception('Intake ID is required to assign student to session');
        }
    } else if ($action_type === 'new_only' || $action_type === 'new_existing') {
        // Create new student and assign to session
        if (empty($full_name) || empty($email) || $programme_id <= 0) {
            throw new Exception('Full name, email, and programme are required for new student creation');
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Check if email already exists
        $email_check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $email_check_stmt->execute([$email]);
        if ($email_check_stmt->fetch()) {
            throw new Exception("Email '$email' already exists in the system");
        }

        // Generate student number if not provided
        if (empty($student_number)) {
            $last_student = $pdo->query("SELECT MAX(CAST(SUBSTRING(student_number, 4) AS UNSIGNED)) as max_num FROM student_profile WHERE student_number LIKE 'LSC%'")->fetch();
            $next_num = $last_student['max_num'] ? $last_student['max_num'] + 1 : 1;
            $student_number = sprintf('LSC%06d', $next_num);
        } else {
            // Check if student number already exists
            $num_check_stmt = $pdo->prepare("SELECT id FROM student_profile WHERE student_number = ?");
            $num_check_stmt->execute([$student_number]);
            if ($num_check_stmt->fetch()) {
                throw new Exception("Student number '$student_number' already exists in the system");
            }
        }

        // Check if intake exists
        if ($intake_id > 0) {
            $intake_check_stmt = $pdo->prepare("SELECT id FROM intake WHERE id = ?");
            $intake_check_stmt->execute([$intake_id]);
            if (!$intake_check_stmt->fetch()) {
                throw new Exception("Intake with ID '$intake_id' not found");
            }
        } else {
            throw new Exception('Intake ID is required to assign student to session');
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Create user account
        $password_hash = password_hash('Password123!', PASSWORD_DEFAULT); // Default temporary password
        $user_insert_stmt = $pdo->prepare("
            INSERT INTO users (username, password_hash, email, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $user_insert_stmt->execute([$student_number, $password_hash, $email]);
        $user_id = $pdo->lastInsertId();

        // Create student profile
        $profile_insert_stmt = $pdo->prepare("
            INSERT INTO student_profile (user_id, full_name, student_number, programme_id, intake_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $profile_insert_stmt->execute([$user_id, $full_name, $student_number, $programme_id, $intake_id]);

        // Check if a programme_schedule entry exists for this combination
        $check_schedule_stmt = $pdo->prepare("
            SELECT id FROM programme_schedule 
            WHERE programme_id = ? AND session_id = ? AND intake_id = ?
        ");
        $check_schedule_stmt->execute([$programme_id, $session_id, $intake_id]);
        $schedule_exists = $check_schedule_stmt->fetch();

        if (!$schedule_exists) {
            // Create programme_schedule entry
            $insert_schedule_stmt = $pdo->prepare("
                INSERT INTO programme_schedule (programme_id, session_id, intake_id, year_of_study)
                VALUES (?, ?, ?, 1)
            ");
            $insert_schedule_stmt->execute([$programme_id, $session_id, $intake_id]);
        }

        // Commit transaction
        $pdo->commit();

        $result = [
            'success' => true,
            'message' => "New student '$full_name' ({$student_number}) successfully created and assigned to session '{$session['session_name']}'",
            'student_id' => $user_id,
            'student_number' => $student_number,
            'action' => 'created_new'
        ];
    } else {
        throw new Exception('No valid action specified');
    }

    header('Content-Type: application/json');
    echo json_encode($result);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>