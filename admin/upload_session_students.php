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

    // Check if intake exists
    if ($intake_id > 0) {
        $intake_check_stmt = $pdo->prepare("SELECT id FROM intake WHERE id = ?");
        $intake_check_stmt->execute([$intake_id]);
        if (!$intake_check_stmt->fetch()) {
            throw new Exception("Intake with ID '$intake_id' not found");
        }
    } else {
        throw new Exception('Intake ID is required to assign students to session');
    }

    // Check if file was uploaded
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('CSV file not uploaded properly');
    }

    $file_tmp_path = $_FILES['csv_file']['tmp_name'];
    $file_name = $_FILES['csv_file']['name'];
    $file_size = $_FILES['csv_file']['size'];
    $file_type = $_FILES['csv_file']['type'];

    // Validate file type
    $allowed_extensions = ['csv'];
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if (!in_array($file_extension, $allowed_extensions)) {
        throw new Exception('Only CSV files are allowed');
    }

    // Read and parse CSV file
    if (($handle = fopen($file_tmp_path, "r")) !== FALSE) {
        // Read the header row to check required columns
        $header = fgetcsv($handle);
        if (!$header) {
            throw new Exception('Invalid CSV file format');
        }

        // Normalize headers (trim and lowercase)
        $normalized_header = array_map('strtolower', array_map('trim', $header));

        // Check for required columns
        $required_columns = ['full_name', 'email'];
        $optional_columns = ['student_number', 'programme_id'];
        
        $missing_required = [];
        foreach ($required_columns as $req_col) {
            if (!in_array($req_col, $normalized_header)) {
                $missing_required[] = $req_col;
            }
        }

        if (!empty($missing_required)) {
            throw new Exception('Missing required columns: ' . implode(', ', $missing_required));
        }

        // Process each row
        $row_index = 1; // Start from 1 as header is row 0
        $added_new = 0;
        $assigned_existing = 0;
        $errors = 0;
        $error_details = [];
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row_index++;
            
            // Map data to normalized headers
            $row_data = [];
            for ($i = 0; $i < count($header); $i++) {
                if (isset($data[$i])) {
                    $row_data[strtolower(trim($header[$i]))] = trim($data[$i]);
                }
            }
            
            try {
                $full_name = $row_data['full_name'] ?? '';
                $email = $row_data['email'] ?? '';
                $student_number = $row_data['student_number'] ?? '';
                $programme_id = (int)($row_data['programme_id'] ?? 0);

                // Validate required fields
                if (empty($full_name) || empty($email)) {
                    throw new Exception("Row $row_index: Missing required fields (full_name or email)");
                }

                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Row $row_index: Invalid email format for '$email'");
                }

                // Check if student exists by email or student number
                $existing_student = null;
                
                if (!empty($student_number)) {
                    // Check by student number first
                    $stmt = $pdo->prepare("SELECT * FROM student_profile WHERE student_number = ?");
                    $stmt->execute([$student_number]);
                    $existing_student = $stmt->fetch();
                }
                
                if (!$existing_student) {
                    // Check by email if student number not provided or not found
                    $stmt = $pdo->prepare("SELECT sp.*, u.email FROM student_profile sp JOIN users u ON sp.user_id = u.id WHERE u.email = ?");
                    $stmt->execute([$email]);
                    $existing_student = $stmt->fetch();
                }

                if ($existing_student) {
                    // Assign existing student to session
                    $update_stmt = $pdo->prepare("UPDATE student_profile SET intake_id = ? WHERE user_id = ?");
                    $update_stmt->execute([$intake_id, $existing_student['user_id']]);
                    
                    // Check if a programme_schedule entry exists for this combination
                    $check_schedule_stmt = $pdo->prepare("
                        SELECT id FROM programme_schedule 
                        WHERE programme_id = ? AND session_id = ? AND intake_id = ?
                    ");
                    $check_schedule_stmt->execute([$existing_student['programme_id'], $session_id, $intake_id]);
                    $schedule_exists = $check_schedule_stmt->fetch();

                    if (!$schedule_exists) {
                        // Create programme_schedule entry
                        $insert_schedule_stmt = $pdo->prepare("
                            INSERT INTO programme_schedule (programme_id, session_id, intake_id, year_of_study)
                            VALUES (?, ?, ?, 1)
                        ");
                        $insert_schedule_stmt->execute([$existing_student['programme_id'], $session_id, $intake_id]);
                    }
                    
                    $assigned_existing++;
                } else {
                    // Check if email already exists in users table
                    $email_check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $email_check_stmt->execute([$email]);
                    if ($email_check_stmt->fetch()) {
                        throw new Exception("Row $row_index: Email '$email' already exists in the system");
                    }

                    // Validate programme_id for new student
                    if ($programme_id <= 0) {
                        throw new Exception("Row $row_index: programme_id is required for new student creation");
                    }

                    // Validate programme exists
                    $programme_check_stmt = $pdo->prepare("SELECT id FROM programme WHERE id = ?");
                    $programme_check_stmt->execute([$programme_id]);
                    if (!$programme_check_stmt->fetch()) {
                        throw new Exception("Row $row_index: Programme with ID '$programme_id' not found");
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
                            throw new Exception("Row $row_index: Student number '$student_number' already exists in the system");
                        }
                    }

                    // Begin transaction for new student creation
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
                    
                    $added_new++;
                }
            } catch (Exception $row_error) {
                $errors++;
                $error_details[] = $row_error->getMessage();
            }
        }
        
        fclose($handle);
        
        // Prepare response
        $response = [
            'success' => true,
            'message' => 'CSV processing completed',
            'summary' => [
                'total_processed' => $row_index - 1, // Subtract 1 for header
                'added_new' => $added_new,
                'assigned_existing' => $assigned_existing,
                'errors' => $errors
            ],
            'details' => $error_details
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        throw new Exception('Could not open CSV file for reading');
    }

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>