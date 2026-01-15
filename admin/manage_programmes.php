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

// Get user info
$stmt = $pdo->prepare("SELECT u.*, ap.full_name, ap.staff_id FROM users u LEFT JOIN admin_profile ap ON u.id = ap.user_id WHERE u.id = ?");
$stmt->execute([currentUserId()]);
$user = $stmt->fetch();

// Handle AJAX requests for export
if (isset($_GET['action']) && $_GET['action'] === 'export_programmes') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="programmes_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Code', 'Name', 'School', 'Duration', 'Duration Unit', 'Category', 'Students', 'Courses', 'Created']);
    
    $export_query = "
        SELECT p.code, p.name, s.name as school_name, p.duration, p.duration_unit,
               (SELECT COUNT(*) FROM student_profile sp WHERE sp.programme_id = p.id) as student_count,
               (SELECT COUNT(*) FROM course c WHERE c.programme_id = p.id) as course_count,
               p.category,
               p.created_at
        FROM programme p 
        LEFT JOIN school s ON p.school_id = s.id 
        ORDER BY s.name, p.name
    ";
    $export_data = $pdo->query($export_query)->fetchAll();
    
    foreach ($export_data as $row) {
        fputcsv($output, [
            $row['code'],
            $row['name'],
            $row['school_name'] ?: 'Not assigned',
            $row['duration'],
            $row['duration_unit'] ?: 'years',
            $row['category'] ?: 'undergraduate',
            $row['student_count'],
            $row['course_count'],
            date('Y-m-d', strtotime($row['created_at']))
        ]);
    }
    fclose($output);
    exit();
}

// Export enrolled students for a specific programme
if (isset($_GET['action']) && $_GET['action'] === 'export_programme_students' && isset($_GET['programme_id'])) {
    $programme_id = (int)$_GET['programme_id'];
    // Get programme info
    $pstmt = $pdo->prepare("SELECT p.name, p.code, s.name AS school_name FROM programme p LEFT JOIN school s ON p.school_id = s.id WHERE p.id = ?");
    $pstmt->execute([$programme_id]);
    $pinfo = $pstmt->fetch();

    header('Content-Type: text/csv');
    $fname = 'programme_' . ($pinfo['code'] ?? $programme_id) . '_students_' . date('Y-m-d') . '.csv';
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    $out = fopen('php://output', 'w');
    // Header rows with programme context
    fputcsv($out, ['Programme', $pinfo['name'] ?? 'N/A']);
    fputcsv($out, ['Code', $pinfo['code'] ?? 'N/A']);
    fputcsv($out, ['School', $pinfo['school_name'] ?? 'N/A']);
    fputcsv($out, []);
    // Table header
    fputcsv($out, ['Student Number','Full Name','Email']);
    $students_stmt = $pdo->prepare("SELECT sp.student_number, sp.full_name, u.email FROM student_profile sp JOIN users u ON sp.user_id = u.id WHERE sp.programme_id = ? ORDER BY sp.full_name");
    $students_stmt->execute([$programme_id]);
    foreach ($students_stmt->fetchAll() as $s) {
        fputcsv($out, [$s['student_number'], $s['full_name'], $s['email']]);
    }
    fclose($out);
    exit;
}

// Export courses under a specific programme
if (isset($_GET['action']) && $_GET['action'] === 'export_programme_courses' && isset($_GET['programme_id'])) {
    $programme_id = (int)$_GET['programme_id'];
    $pstmt = $pdo->prepare("SELECT p.name, p.code, s.name AS school_name FROM programme p LEFT JOIN school s ON p.school_id = s.id WHERE p.id = ?");
    $pstmt->execute([$programme_id]);
    $pinfo = $pstmt->fetch();

    header('Content-Type: text/csv');
    $fname = 'programme_' . ($pinfo['code'] ?? $programme_id) . '_courses_' . date('Y-m-d') . '.csv';
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Programme', $pinfo['name'] ?? 'N/A']);
    fputcsv($out, ['Code', $pinfo['code'] ?? 'N/A']);
    fputcsv($out, ['School', $pinfo['school_name'] ?? 'N/A']);
    fputcsv($out, []);
    // Detect if 'term' column exists
    $hasTerm = $pdo->query("SHOW COLUMNS FROM course LIKE 'term'")->rowCount() > 0;
    fputcsv($out, $hasTerm ? ['Course Code','Course Name','Credits','Enrollments','Term'] : ['Course Code','Course Name','Credits','Enrollments']);
    if ($hasTerm) {
        $courses_stmt = $pdo->prepare("SELECT c.code, c.name, c.credits, c.term, (SELECT COUNT(*) FROM course_enrollment ce WHERE ce.course_id = c.id) as enrollment_count FROM course c WHERE c.programme_id = ? ORDER BY COALESCE(c.term, ''), c.name");
    } else {
        $courses_stmt = $pdo->prepare("SELECT c.code, c.name, c.credits, (SELECT COUNT(*) FROM course_enrollment ce WHERE ce.course_id = c.id) as enrollment_count FROM course c WHERE c.programme_id = ? ORDER BY c.name");
    }
    $courses_stmt->execute([$programme_id]);
    foreach ($courses_stmt->fetchAll() as $c) {
        if ($hasTerm) {
            fputcsv($out, [$c['code'], $c['name'], $c['credits'], $c['enrollment_count'], $c['term'] ?? '']);
        } else {
            fputcsv($out, [$c['code'], $c['name'], $c['credits'], $c['enrollment_count']]);
        }
    }
    fclose($out);
    exit;
}

// Export lecturers for a programme's school (if table exists)
if (isset($_GET['action']) && $_GET['action'] === 'export_programme_lecturers' && isset($_GET['programme_id'])) {
    $programme_id = (int)$_GET['programme_id'];
    $pstmt = $pdo->prepare("SELECT p.name, p.code, p.school_id, s.name AS school_name FROM programme p LEFT JOIN school s ON p.school_id = s.id WHERE p.id = ?");
    $pstmt->execute([$programme_id]);
    $pinfo = $pstmt->fetch();

    header('Content-Type: text/csv');
    $fname = 'programme_' . ($pinfo['code'] ?? $programme_id) . '_lecturers_' . date('Y-m-d') . '.csv';
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Programme', $pinfo['name'] ?? 'N/A']);
    fputcsv($out, ['Code', $pinfo['code'] ?? 'N/A']);
    fputcsv($out, ['School', $pinfo['school_name'] ?? 'N/A']);
    fputcsv($out, []);
    fputcsv($out, ['Staff ID','Full Name','Email']);
    $hasLecturerProfile = $pdo->query("SHOW TABLES LIKE 'lecturer_profile'")->rowCount() > 0;
    if ($hasLecturerProfile && $pinfo && $pinfo['school_id']) {
        $lecturers_stmt = $pdo->prepare("SELECT staff_id, full_name, (SELECT email FROM users u WHERE u.id = lp.user_id) AS email FROM lecturer_profile lp WHERE lp.school_id = ? ORDER BY full_name");
        $lecturers_stmt->execute([$pinfo['school_id']]);
        foreach ($lecturers_stmt->fetchAll() as $l) {
            fputcsv($out, [$l['staff_id'], $l['full_name'], $l['email']]);
        }
    }
    fclose($out);
    exit;
}

// Handle form submissions
$message = '';
$messageType = '';
$importReport = []; // For storing import results

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_student_to_programme':
                $programme_id = $_POST['programme_id'];
                $student_id = $_POST['student_id'];
                
                try {
                    // Check if student exists and get user_id
                    $stmt = $pdo->prepare("SELECT user_id FROM student_profile WHERE user_id = ?");
                    $stmt->execute([$student_id]);
                    $existing = $stmt->fetch();
                    
                    if (!$existing) {
                        throw new Exception("Student not found!");
                    }
                    
                    // Update student's programme_id
                    $stmt = $pdo->prepare("UPDATE student_profile SET programme_id = ? WHERE user_id = ?");
                    if ($stmt->execute([$programme_id, $student_id])) {
                        $message = "Student added to programme successfully!";
                        $messageType = 'success';
                    } else {
                        throw new Exception("Failed to add student to programme!");
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'add_lecturer_to_programme':
                $programme_id = $_POST['programme_id'];
                $lecturer_id = $_POST['lecturer_id'];
                $course_id = $_POST['course_id'];
                $academic_year = $_POST['academic_year'];
                $semester = $_POST['semester'];
                
                try {
                    // Check if lecturer and course exist
                    $stmt = $pdo->prepare("SELECT user_id FROM staff_profile WHERE user_id = ?");
                    $stmt->execute([$lecturer_id]);
                    $lecturer_exists = $stmt->fetch();
                    
                    if (!$lecturer_exists) {
                        throw new Exception("Lecturer not found!");
                    }
                    
                    $stmt = $pdo->prepare("SELECT id FROM course WHERE id = ?");
                    $stmt->execute([$course_id]);
                    $course_exists = $stmt->fetch();
                    
                    if (!$course_exists) {
                        throw new Exception("Course not found!");
                    }
                    
                    // Check if this lecturer is already assigned to this course for the same intake/semester
                    $check_stmt = $pdo->prepare("SELECT id FROM course_assignment WHERE course_id = ? AND lecturer_id = ? AND academic_year = ? AND semester = ? AND is_active = 1");
                    $check_stmt->execute([$course_id, $lecturer_id, $academic_year, $semester]);
                    
                    if ($check_stmt->fetch()) {
                        throw new Exception("This lecturer is already assigned to this course for the selected intake and semester!");
                    }
                    
                    // Assign lecturer to course
                    $assign_stmt = $pdo->prepare("INSERT INTO course_assignment (course_id, lecturer_id, academic_year, semester, is_active) VALUES (?, ?, ?, ?, 1)");
                    $assign_stmt->execute([$course_id, $lecturer_id, $academic_year, $semester]);
                    
                    $message = "Lecturer assigned to course successfully!";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'add_course_to_programme':
                $programme_id = $_POST['programme_id'];
                $course_code = strtoupper(trim($_POST['course_code']));
                $course_name = trim($_POST['course_name']);
                $course_credits = $_POST['course_credits'];
                $course_description = trim($_POST['course_description']);
                $course_term = trim($_POST['course_term']);
                
                // Validate inputs
                if (empty($course_code) || empty($course_name) || empty($course_credits) || empty($course_term)) {
                    $message = "Please fill in all required fields! (All fields marked with * are required)";
                    $messageType = 'error';
                } elseif (strlen($course_code) > 20) {
                    $message = "Course code is too long (max 20 characters)!";
                    $messageType = 'error';
                } elseif (!is_numeric($course_credits) || $course_credits <= 0) {
                    $message = "Credits must be a positive number!";
                    $messageType = 'error';
                } else {
                    try {
                        // Check if course code already exists
                        $check_stmt = $pdo->prepare("SELECT id FROM course WHERE code = ?");
                        $check_stmt->execute([$course_code]);
                        
                        if ($check_stmt->rowCount() > 0) {
                            $message = "Course code already exists!";
                            $messageType = 'error';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO course (name, code, programme_id, credits, description, term) VALUES (?, ?, ?, ?, ?, ?)");
                            if ($stmt->execute([$course_name, $course_code, $programme_id, $course_credits, $course_description, $course_term])) {
                                $message = "Course added to programme successfully!";
                                $messageType = 'success';
                            } else {
                                $message = "Failed to add course to programme!";
                                $messageType = 'error';
                            }
                        }
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'update_course':
                $course_id = $_POST['course_id'];
                $course_code = strtoupper(trim($_POST['course_code']));
                $course_name = trim($_POST['course_name']);
                $course_credits = $_POST['course_credits'];
                $course_description = trim($_POST['course_description']);
                $course_term = trim($_POST['course_term']);
                
                // Validate inputs
                if (empty($course_code) || empty($course_name) || empty($course_credits) || empty($course_term)) {
                    $message = "Please fill in all required fields! (All fields marked with * are required)";
                    $messageType = 'error';
                } elseif (strlen($course_code) > 20) {
                    $message = "Course code is too long (max 20 characters)!";
                    $messageType = 'error';
                } elseif (!is_numeric($course_credits) || $course_credits <= 0) {
                    $message = "Credits must be a positive number!";
                    $messageType = 'error';
                } else {
                    try {
                        // Check if course code already exists for a different course
                        $check_stmt = $pdo->prepare("SELECT id FROM course WHERE code = ? AND id != ?");
                        $check_stmt->execute([$course_code, $course_id]);
                        
                        if ($check_stmt->rowCount() > 0) {
                            $message = "Course code already exists for another course!";
                            $messageType = 'error';
                        } else {
                            $stmt = $pdo->prepare("UPDATE course SET name = ?, code = ?, credits = ?, description = ?, term = ? WHERE id = ?");
                            if ($stmt->execute([$course_name, $course_code, $course_credits, $course_description, $course_term, $course_id])) {
                                $message = "Course updated successfully!";
                                $messageType = 'success';
                            } else {
                                $message = "Failed to update course!";
                                $messageType = 'error';
                            }
                        }
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'delete_course':
                $course_id = $_POST['course_id'];
                $programme_id = $_POST['programme_id'];
                
                try {
                    // Check if course exists and belongs to this programme
                    $check_stmt = $pdo->prepare("SELECT id, code, name FROM course WHERE id = ? AND programme_id = ?");
                    $check_stmt->execute([$course_id, $programme_id]);
                    $course = $check_stmt->fetch();
                    
                    if (!$course) {
                        throw new Exception("Course not found or does not belong to this programme!");
                    }
                    
                    // Check if there are any enrollments for this course
                    $enrollment_check = $pdo->prepare("SELECT COUNT(*) FROM course_enrollment WHERE course_id = ?");
                    $enrollment_check->execute([$course_id]);
                    $enrollment_count = $enrollment_check->fetchColumn();
                    
                    if ($enrollment_count > 0) {
                        throw new Exception("Cannot delete course with {$enrollment_count} enrollment(s). Please unenroll students first.");
                    }
                    
                    // Delete the course
                    $stmt = $pdo->prepare("DELETE FROM course WHERE id = ?");
                    if ($stmt->execute([$course_id])) {
                        $message = "Course '{$course['code']} - {$course['name']}' deleted successfully!";
                        $messageType = 'success';
                    } else {
                        throw new Exception("Failed to delete course!");
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'assign_existing_course_to_programme':
                $programme_id = $_POST['programme_id'];
                $course_id = $_POST['existing_course_id'];
                
                try {
                    // Check if course exists and doesn't already belong to this programme
                    $check_stmt = $pdo->prepare("SELECT id, name, code FROM course WHERE id = ? AND (programme_id IS NULL OR programme_id != ?)");
                    $check_stmt->execute([$course_id, $programme_id]);
                    $course = $check_stmt->fetch();
                    
                    if (!$course) {
                        $message = "Selected course does not exist or is already assigned to this programme!";
                        $messageType = 'error';
                    } else {
                        // Assign the course to the programme
                        $stmt = $pdo->prepare("UPDATE course SET programme_id = ? WHERE id = ?");
                        if ($stmt->execute([$programme_id, $course_id])) {
                            $message = "Course '{$course['code']} - {$course['name']}' assigned to programme successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to assign course to programme!";
                            $messageType = 'error';
                        }
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'add_existing_course_to_programme':
                $programme_id = $_POST['programme_id'];
                $course_id = $_POST['existing_course_id'];
                
                try {
                    // Check if course exists and doesn't already belong to this programme
                    $check_stmt = $pdo->prepare("SELECT id, name FROM course WHERE id = ? AND (programme_id IS NULL OR programme_id != ?)");
                    $check_stmt->execute([$course_id, $programme_id]);
                    $course = $check_stmt->fetch();
                    
                    if (!$course) {
                        $message = "Selected course does not exist or is already assigned to this programme!";
                        $messageType = 'error';
                    } else {
                        // Assign the course to the programme
                        $stmt = $pdo->prepare("UPDATE course SET programme_id = ? WHERE id = ?");
                        if ($stmt->execute([$programme_id, $course_id])) {
                            $message = "Course '{$course['name']}' added to programme successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to add course to programme!";
                            $messageType = 'error';
                        }
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'import_programmes':
                if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
                    $file = $_FILES['csv_file'];
                    $fileType = mime_content_type($file['tmp_name']);
                    
                    // Check if file is CSV
                    if ($fileType == 'text/csv' || $fileType == 'text/plain' || strpos($fileType, 'csv') !== false) {
                        $handle = fopen($file['tmp_name'], 'r');
                        if ($handle) {
                            $importedCount = 0;
                            $updatedCount = 0;
                            $errorCount = 0;
                            $lineNumber = 0;
                            
                            // Get all schools for mapping
                            $schools = $pdo->query("SELECT id, name FROM school")->fetchAll(PDO::FETCH_KEY_PAIR);
                            $schoolNames = array_flip($schools); // Flip to get name => id mapping
                            
                            // Read the header row
                            $header = fgetcsv($handle);
                            $lineNumber++;
                            
                            // Validate header - check both old and new format
                            $validHeader = ($header[0] === 'Code' && $header[1] === 'Name' && $header[2] === 'School');
                            $validNewHeader = ($header[0] === 'Code' && $header[1] === 'Name' && $header[2] === 'School' && $header[3] === 'Duration' && $header[4] === 'Duration Unit' && $header[5] === 'Category');
                            
                            if (!$validHeader && !$validNewHeader) {
                                $message = "Invalid CSV format. Please use the correct template.";
                                $messageType = 'error';
                            } else {
                                // Process each row
                                while (($row = fgetcsv($handle)) !== false) {
                                    $lineNumber++;
                                    try {
                                        $code = trim($row[0]);
                                        $name = trim($row[1]);
                                        $schoolName = trim($row[2]);
                                        
                                        // Handle both old and new CSV formats
                                        if (count($header) >= 7) { // New format with category and duration_unit
                                            $duration = isset($row[3]) ? trim($row[3]) : '1';
                                            $category = isset($row[4]) ? trim($row[4]) : 'undergraduate';
                                            $duration_unit = isset($row[5]) ? trim($row[5]) : 'years';
                                        } elseif (count($header) >= 6) { // Format with category but no duration_unit
                                            $duration = isset($row[3]) ? trim($row[3]) : '1';
                                            $category = isset($row[4]) ? trim($row[4]) : 'undergraduate';
                                            $duration_unit = 'years'; // Default to years
                                        } else { // Old format without category or duration_unit
                                            $duration = isset($row[3]) ? trim($row[3]) : '1';
                                            $category = 'undergraduate'; // Default to undergraduate
                                            $duration_unit = 'years'; // Default to years
                                        }
                                        
                                        // Validate category
                                        if (!in_array($category, ['undergraduate', 'short_course'])) {
                                            $category = 'undergraduate'; // Default fallback
                                        }
                                        
                                        // Validate duration_unit
                                        if (!in_array($duration_unit, ['months', 'years'])) {
                                            $duration_unit = 'years'; // Default to years
                                        }
                                        
                                        // Validate duration
                                        if (!is_numeric($duration) || $duration <= 0) {
                                            $duration = '1'; // Default to 1
                                        }
                                        
                                        // Validate required fields
                                        if (empty($code) || empty($name) || empty($schoolName)) {
                                            $importReport[] = "Line $lineNumber: Missing required fields";
                                            $errorCount++;
                                            continue;
                                        }
                                        
                                        // Find school ID
                                        $school_id = null;
                                        if (isset($schoolNames[$schoolName])) {
                                            $school_id = $schoolNames[$schoolName];
                                        } else {
                                            // Try to find school with case-insensitive match
                                            foreach ($schools as $id => $name) {
                                                if (strtolower($name) === strtolower($schoolName)) {
                                                    $school_id = $id;
                                                    break;
                                                }
                                            }
                                        }
                                        
                                        if (!$school_id) {
                                            $importReport[] = "Line $lineNumber: School '$schoolName' not found";
                                            $errorCount++;
                                            continue;
                                        }
                                        
                                        // Check if programme already exists
                                        $check_stmt = $pdo->prepare("SELECT id FROM programme WHERE code = ?");
                                        $check_stmt->execute([$code]);
                                        $existing = $check_stmt->fetch();
                                        
                                        if ($existing) {
                                            // Update existing programme
                                            $stmt = $pdo->prepare("UPDATE programme SET name = ?, school_id = ?, duration = ?, duration_unit = ?, category = ?, updated_at = NOW() WHERE id = ?");
                                            if ($stmt->execute([$name, $school_id, $duration, $duration_unit, $category, $existing['id']])) {
                                                $importReport[] = "Line $lineNumber: Updated programme '$code'";
                                                $updatedCount++;
                                            } else {
                                                $importReport[] = "Line $lineNumber: Failed to update programme '$code'";
                                                $errorCount++;
                                            }
                                        } else {
                                            // Insert new programme
                                            $stmt = $pdo->prepare("INSERT INTO programme (name, code, school_id, duration, duration_unit, category, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                                            if ($stmt->execute([$name, $code, $school_id, $duration, $duration_unit, $category])) {
                                                $importReport[] = "Line $lineNumber: Imported programme '$code'";
                                                $importedCount++;
                                            } else {
                                                $importReport[] = "Line $lineNumber: Failed to import programme '$code'";
                                                $errorCount++;
                                            }
                                        }
                                    } catch (Exception $e) {
                                        $importReport[] = "Line $lineNumber: Error - " . $e->getMessage();
                                        $errorCount++;
                                    }
                                }
                                
                                $message = "Import completed: $importedCount imported, $updatedCount updated, $errorCount errors";
                                $messageType = $errorCount > 0 ? 'warning' : 'success';
                            }
                            
                            fclose($handle);
                        } else {
                            $message = "Failed to open uploaded file!";
                            $messageType = 'error';
                        }
                    } else {
                        $message = "Please upload a valid CSV file!";
                        $messageType = 'error';
                    }
                } else {
                    $message = "Please select a CSV file to import!";
                    $messageType = 'error';
                }
                break;
                
            case 'add_programme':
                $name = trim($_POST['name']);
                $code = strtoupper(trim($_POST['code']));
                $school_id = $_POST['school_id'];
                $duration = trim($_POST['duration']);
                $duration_unit = $_POST['duration_unit'] ?? 'years'; // New field for duration unit
                $description = trim($_POST['description']);
                $category = $_POST['category']; // New field for programme category
                
                // Validate inputs
                if (empty($name) || empty($code) || empty($school_id) || empty($duration) || empty($duration_unit) || empty($category)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } elseif (strlen($code) > 20) {
                    $message = "Programme code is too long (max 20 characters)!";
                    $messageType = 'error';
                } elseif (!is_numeric($duration) || $duration <= 0) {
                    $message = "Duration must be a positive number!";
                    $messageType = 'error';
                } else {
                    // Check if programme code already exists
                    $check_stmt = $pdo->prepare("SELECT id FROM programme WHERE code = ?");
                    $check_stmt->execute([$code]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        $message = "Programme code already exists!";
                        $messageType = 'error';
                    } else {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO programme (name, code, school_id, duration, duration_unit, description, category, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                            if ($stmt->execute([$name, $code, $school_id, $duration, $duration_unit, $description, $category])) {
                                $message = "Programme added successfully!";
                                $messageType = 'success';
                            } else {
                                $message = "Failed to add programme!";
                                $messageType = 'error';
                            }
                        } catch (Exception $e) {
                            $message = "Error: " . $e->getMessage();
                            $messageType = 'error';
                        }
                    }
                }
                break;
                
            case 'edit_programme':
                $id = $_POST['programme_id'];
                $name = trim($_POST['name']);
                $code = strtoupper(trim($_POST['code']));
                $school_id = $_POST['school_id'];
                $duration = trim($_POST['duration']);
                $duration_unit = $_POST['duration_unit'] ?? 'years'; // New field for duration unit
                $description = trim($_POST['description']);
                $category = $_POST['category']; // New field for programme category
                
                // Validate inputs
                if (empty($name) || empty($code) || empty($school_id) || empty($duration) || empty($duration_unit) || empty($category)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } elseif (strlen($code) > 20) {
                    $message = "Programme code is too long (max 20 characters)!";
                    $messageType = 'error';
                } elseif (!is_numeric($duration) || $duration <= 0) {
                    $message = "Duration must be a positive number!";
                    $messageType = 'error';
                } else {
                    // Check if programme code already exists (excluding current programme)
                    $check_stmt = $pdo->prepare("SELECT id FROM programme WHERE code = ? AND id != ?");
                    $check_stmt->execute([$code, $id]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        $message = "Programme code already exists!";
                        $messageType = 'error';
                    } else {
                        try {
                            $stmt = $pdo->prepare("UPDATE programme SET name = ?, code = ?, school_id = ?, duration = ?, duration_unit = ?, description = ?, category = ?, updated_at = NOW() WHERE id = ?");
                            if ($stmt->execute([$name, $code, $school_id, $duration, $duration_unit, $description, $category, $id])) {
                                $message = "Programme updated successfully!";
                                $messageType = 'success';
                            } else {
                                $message = "Failed to update programme!";
                                $messageType = 'error';
                            }
                        } catch (Exception $e) {
                            $message = "Error: " . $e->getMessage();
                            $messageType = 'error';
                        }
                    }
                }
                break;
                
            case 'delete_programme':
                $id = $_POST['programme_id'];
                
                // Check if programme has students
                $student_check = $pdo->prepare("SELECT COUNT(*) FROM student_profile WHERE programme_id = ?");
                $student_check->execute([$id]);
                $student_count = $student_check->fetchColumn();
                
                if ($student_count > 0) {
                    $message = "Cannot delete programme with {$student_count} enrolled student(s)! Please reassign students first.";
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM programme WHERE id = ?");
                        if ($stmt->execute([$id])) {
                            $message = "Programme deleted successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to delete programme!";
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
        }
    }
}

// Get filters
$searchQuery = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10; // Default to 10 per page

// Ensure perPage is at least 5
$perPage = max(5, $perPage);

// Calculate offset for pagination
$offset = ($page - 1) * $perPage;

// Build query with filters
$query = "
    SELECT p.*, s.name as school_name,
           (SELECT COUNT(*) FROM student_profile sp WHERE sp.programme_id = p.id) as student_count,
           (SELECT COUNT(*) FROM course c WHERE c.programme_id = p.id) as course_count
    FROM programme p 
    LEFT JOIN school s ON p.school_id = s.id 
    WHERE 1=1";

$params = [];

if ($searchQuery) {
    $query .= " AND (p.name LIKE ? OR p.code LIKE ? OR p.description LIKE ? OR s.name LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY s.name, p.name";

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) 
    FROM programme p 
    LEFT JOIN school s ON p.school_id = s.id 
    WHERE 1=1";

$countParams = [];

if ($searchQuery) {
    $countQuery .= " AND (p.name LIKE ? OR p.code LIKE ? OR p.description LIKE ? OR s.name LIKE ?)";
    $searchParam = "%$searchQuery%";
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
}

$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($countParams);
$totalProgrammes = $countStmt->fetchColumn();
$totalPages = ceil($totalProgrammes / $perPage);

// Add LIMIT and OFFSET to the main query
$query .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$programmes = $stmt->fetchAll();

// Get schools for dropdowns
$schools = $pdo->query("SELECT * FROM school ORDER BY name")->fetchAll();

// Get programme for editing if specified
$editProgramme = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT p.*, s.name as school_name FROM programme p LEFT JOIN school s ON p.school_id = s.id WHERE p.id = ?");
    $stmt->execute([$_GET['edit']]);
    $editProgramme = $stmt->fetch();
}

// Ensure required schema exists BEFORE any dependent queries
try {
    $pdo->exec("ALTER TABLE programme DROP FOREIGN KEY IF EXISTS FK_programme_department");
    $pdo->exec("ALTER TABLE programme DROP COLUMN IF EXISTS department_id");
    $pdo->exec("ALTER TABLE programme ADD COLUMN IF NOT EXISTS school_id INT");
    $pdo->exec("ALTER TABLE programme ADD CONSTRAINT FK_programme_school FOREIGN KEY (school_id) REFERENCES school(id) ON DELETE SET NULL");
    $pdo->exec("ALTER TABLE programme ADD COLUMN IF NOT EXISTS code VARCHAR(20) UNIQUE");
    $pdo->exec("ALTER TABLE programme ADD COLUMN IF NOT EXISTS description TEXT");
    $pdo->exec("ALTER TABLE programme ADD COLUMN IF NOT EXISTS duration DECIMAL(5,2)"); // Changed to decimal to support months
    $pdo->exec("ALTER TABLE programme ADD COLUMN IF NOT EXISTS duration_unit ENUM('months', 'years') DEFAULT 'years'"); // Added duration unit column
    $pdo->exec("ALTER TABLE programme ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $pdo->exec("ALTER TABLE programme ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    $pdo->exec("ALTER TABLE programme ADD COLUMN IF NOT EXISTS category ENUM('undergraduate', 'short_course') DEFAULT 'undergraduate'");
    $pdo->exec("ALTER TABLE course ADD COLUMN IF NOT EXISTS programme_id INT");
    // Ensure 'term' column exists across MySQL versions
    $hasCourseTerm = $pdo->query("SHOW COLUMNS FROM course LIKE 'term'")->rowCount() > 0;
    if (!$hasCourseTerm) {
        try { $pdo->exec("ALTER TABLE course ADD COLUMN term VARCHAR(50) NULL"); } catch (Exception $e) { /* ignore */ }
    }
    $hasCourseTerm = $pdo->query("SHOW COLUMNS FROM course LIKE 'term'")->rowCount() > 0;
    $pdo->exec("ALTER TABLE course ADD CONSTRAINT FK_course_programme FOREIGN KEY (programme_id) REFERENCES programme(id) ON DELETE SET NULL");

    $pdo->exec("CREATE TABLE IF NOT EXISTS lecturer_profile (
        user_id INT PRIMARY KEY,
        full_name VARCHAR(255),
        staff_id VARCHAR(50),
        school_id INT,
        department_id INT,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (school_id) REFERENCES school(id),
        FOREIGN KEY (department_id) REFERENCES department(id)
    )");
} catch (Exception $e) {
    // Ignore if not supported; page will still function without lecturers
}

// Get programme details for viewing if specified
$viewProgramme = null;
$programmeStudents = [];
$programmeCourses = [];
$programmeLecturers = [];
if (isset($_GET['view'])) {
    $programme_id = $_GET['view'];
    $programme_stmt = $pdo->prepare("
        SELECT p.*, s.name as school_name,
               (SELECT COUNT(*) FROM student_profile sp WHERE sp.programme_id = p.id) as student_count,
               (SELECT COUNT(*) FROM course c WHERE c.programme_id = p.id) as course_count
        FROM programme p 
        LEFT JOIN school s ON p.school_id = s.id 
        WHERE p.id = ?
    ");
    $programme_stmt->execute([$programme_id]);
    $viewProgramme = $programme_stmt->fetch();
    
    if ($viewProgramme) {
        // Get students in this programme
        $students_stmt = $pdo->prepare("
            SELECT sp.*, u.username, u.email as user_email
            FROM student_profile sp 
            JOIN users u ON sp.user_id = u.id
            WHERE sp.programme_id = ?
            ORDER BY sp.full_name
        ");
        $students_stmt->execute([$programme_id]);
        $programmeStudents = $students_stmt->fetchAll();
        
        // Get courses under this programme
        if (isset($hasCourseTerm) && $hasCourseTerm) {
            $courses_stmt = $pdo->prepare("
                SELECT c.*, 
                       (SELECT COUNT(*) FROM course_enrollment ce WHERE ce.course_id = c.id) as enrollment_count
                FROM course c 
                WHERE c.programme_id = ?
                ORDER BY COALESCE(c.term, ''), c.name
            ");
        } else {
            $courses_stmt = $pdo->prepare("
                SELECT c.*, 
                       (SELECT COUNT(*) FROM course_enrollment ce WHERE ce.course_id = c.id) as enrollment_count
                FROM course c 
                WHERE c.programme_id = ?
                ORDER BY c.name
            ");
        }
        $courses_stmt->execute([$programme_id]);
        $programmeCourses = $courses_stmt->fetchAll();

        // Get lecturers in this school if lecturer_profile table exists
        $hasLecturerProfile = $pdo->query("SHOW TABLES LIKE 'lecturer_profile'")->rowCount() > 0;
        if ($hasLecturerProfile) {
            $lecturers_stmt = $pdo->prepare("
                SELECT lp.*, u.username, u.email as user_email
                FROM lecturer_profile lp 
                JOIN users u ON lp.user_id = u.id
                WHERE lp.school_id = ?
                ORDER BY lp.full_name
            ");
            $lecturers_stmt->execute([$viewProgramme['school_id']]);
            $programmeLecturers = $lecturers_stmt->fetchAll();
        } else {
            $programmeLecturers = [];
        }
        
        // Get all students who are not in this programme (for adding students form)
        $existing_student_ids = array_column($programmeStudents, 'user_id');
        $exclude_ids = $existing_student_ids ? implode(',', array_map('intval', $existing_student_ids)) : '0';
        $available_students = $pdo->query("SELECT sp.user_id, sp.full_name, sp.student_number FROM student_profile sp WHERE sp.user_id NOT IN ($exclude_ids) ORDER BY sp.full_name")->fetchAll();
        
        // Get all courses that are not in this programme (for adding courses form)
        $existing_course_ids = array_column($programmeCourses, 'id');
        $exclude_course_ids = $existing_course_ids ? implode(',', array_map('intval', $existing_course_ids)) : '0';
        $available_courses = $pdo->query("SELECT id, name, code FROM course WHERE programme_id IS NULL OR programme_id != $programme_id AND id NOT IN ($exclude_course_ids) ORDER BY name")->fetchAll();
        
        // Get all lecturers from the system (for adding lecturers form)
        $all_lecturers = $pdo->query("SELECT sp.user_id, sp.full_name, sp.staff_id FROM staff_profile sp WHERE sp.user_id IN (SELECT user_id FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE r.name = 'Lecturer') ORDER BY sp.full_name")->fetchAll();
        
        // Get all intakes for assignment dropdown
        $intakes = $pdo->query("SELECT * FROM intake ORDER BY start_date DESC")->fetchAll();
        
        // Get all courses in this programme (for lecturer assignment)
        $programme_courses = $programmeCourses;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Programmes - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        html, body { max-width: 100%; overflow-x: auto; }
        .table-responsive { overflow-x: auto; }
        .users-table { width: 100%; min-width: 900px; }
        /* Ensure complex blocks allow horizontal scroll if needed */
        .section-card, .school-header-card, .stats-grid { overflow-x: auto; }
        /* Use global admin-dashboard.css layout (no custom grid overrides here) */

        .print-header { display: none; text-align: center; margin-bottom: 10px; }
        .print-header img { max-width: 140px; }
        .print-programme-summary { display: none; font-size: 14px; margin: 10px 0 16px; text-align: center; }
        .print-section-title { display: none; font-weight: 600; margin: 8px 0; }
        .term-title { font-weight: 600; margin: 10px 0 6px; }

        .print-programme-summary span { margin-right: 12px; }
        @media print {
            .no-print { display: none !important; }
            .print-header { display: block !important; }
            /* Show only the chosen section during print */
            .section-card { display: none !important; }
            body.print-students #studentsSection,
            body.print-courses #coursesSection,
            body.print-lecturers #lecturersSection { display: block !important; }
            /* Hide all non-essential UI in print */
            nav.top-nav, aside.sidebar, .content-header, .action-bar, .filters-section, .school-header-card, .stats-grid, .section-header { display: none !important; }
            .print-programme-summary span { display: block; margin: 2px 0; }
            .print-section-title { display: block !important; text-align: left; }
        }
        
        .pagination-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin: 15px 0;
        }
        
        .pagination-info, .pagination-settings, .pagination-nav {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pagination-settings label {
            margin: 0;
        }
        
        .pagination-settings select {
            padding: 5px 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            background-color: white;
        }
        
        .pagination-nav .page-info {
            margin: 0 15px;
            font-weight: bold;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.875rem;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #007bff;
            color: #007bff;
        }
        
        .btn-outline:hover {
            background-color: #007bff;
            color: white;
        }
        
        /* Tabs for course forms */
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .tab-button {
            padding: 10px 20px;
            background-color: #f1f1f1;
            border: 1px solid #ddd;
            border-bottom: none;
            cursor: pointer;
            outline: none;
            transition: background-color 0.3s;
        }
        
        .tab-button:first-child {
            border-radius: 5px 0 0 0;
        }
        
        .tab-button:last-child {
            border-radius: 0 5px 0 0;
        }
        
        .tab-button.active {
            background-color: #007bff;
            color: white;
        }
        
        .tab-button:not(:last-child) {
            border-right: none;
        }
        
    </style>
</head>
<body class="admin-layout" data-theme="light">

    <!-- Top Navigation Bar -->
    <nav class="top-nav">
        <div class="nav-left">
            <div class="logo-container">
                <img src="../assets/images/lsc-logo.png" alt="LSC Logo" class="logo" onerror="this.style.display='none'">
                <span class="logo-text">LSC SRMS</span>
            </div>
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="nav-right">
            <div class="user-info">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($user['full_name'] ?? 'Administrator'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($user['staff_id'] ?? 'N/A'); ?>)</span>
            </div>
            <div class="nav-actions">
                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="fas fa-moon" id="theme-icon"></i>
                </button>
                <div class="dropdown">
                    <button class="profile-btn" onclick="toggleDropdown()">
                        <i class="fas fa-user-circle"></i>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="profile.php"><i class="fas fa-user"></i> View Profile</a>
                        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-tachometer-alt"></i> Admin Panel</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <div class="nav-section">
                <h4>Users Management!</h4>
                <a href="manage_users.php" class="nav-item"><i class="fas fa-users"></i><span>Manage Users</span></a>
                <a href="manage_roles.php" class="nav-item"><i class="fas fa-shield-alt"></i><span>Roles & Permissions</span></a>
                <a href="upload_users.php" class="nav-item"><i class="fas fa-upload"></i><span>Bulk Upload</span></a>
            </div>
            <div class="nav-section">
                <h4>Academic Structure</h4>
                <a href="manage_schools.php" class="nav-item"><i class="fas fa-university"></i><span>Schools</span></a>
                <a href="manage_departments.php" class="nav-item"><i class="fas fa-building"></i><span>Departments</span></a>
                <a href="manage_programmes.php" class="nav-item active"><i class="fas fa-graduation-cap"></i><span>Programmes</span></a>
                <a href="manage_courses.php" class="nav-item"><i class="fas fa-book"></i><span>Courses</span></a>
                <a href="manage_intakes.php" class="nav-item"><i class="fas fa-calendar-alt"></i><span>Intakes</span></a>
                                <a href="manage_sessions.php" class="nav-item"><i class="fas fa-clock"></i><span>Sessions</span></a>
            </div>
            <div class="nav-section">
                <h4>Academic Operations</h4>
                <a href="manage_results.php" class="nav-item"><i class="fas fa-chart-line"></i><span>Results Management</span></a>
                <a href="enrollment_approvals.php" class="nav-item"><i class="fas fa-user-check"></i><span>Enrollment Approvals</span></a>
                <a href="course_registrations.php" class="nav-item"><i class="fas fa-clipboard-check"></i><span>Course Registrations</span></a>
            </div>
            <div class="nav-section">
                <h4>Reports & Analytics</h4>
                <a href="reports.php" class="nav-item"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
                <a href="analytics.php" class="nav-item"><i class="fas fa-analytics"></i><span>Analytics</span></a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
                <?php if (!empty($importReport)): ?>
                    <details style="margin-top: 10px;">
                        <summary>Import Details</summary>
                        <ul style="max-height: 200px; overflow-y: auto; margin-top: 10px;">
                            <?php foreach ($importReport as $report): ?>
                                <li><?php echo htmlspecialchars($report); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <!-- Navigation Actions -->
    <div class="action-bar">
        <div class="action-left">
            <?php if (isset($_GET['view'])): ?>
                <a href="manage_programmes.php" class="btn btn-orange">
                    <i class="fas fa-arrow-left"></i> Back to Programmes
                </a>
            <?php endif; ?>
        </div>
        <div class="action-right">
            <?php if (!isset($_GET['view'])): ?>
                <button onclick="showAddForm()" class="btn btn-green">
                    <i class="fas fa-plus"></i> Add New Programme
                </button>
                <button onclick="showImportForm()" class="btn btn-blue">
                    <i class="fas fa-file-import"></i> Import CSV
                </button>
                <a href="?action=export_programmes" class="btn btn-info">
                    <i class="fas fa-download"></i> Export CSV
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Import CSV Form -->
    <div class="form-section" id="importForm" style="display: none;">
        <div class="form-card">
            <h2><i class="fas fa-file-import"></i> Import Programmes from CSV</h2>
            <p>Upload a CSV file with programme data. The file should have columns: Code, Name, School, Duration, Duration Unit (months or years), Category (undergraduate or short_course)</p>
            <form method="POST" enctype="multipart/form-data" class="import-form">
                <input type="hidden" name="action" value="import_programmes">
                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="csv_file">CSV File *</label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
                        <small class="form-text">Download the template <a href="#" onclick="downloadTemplate()">here</a></small>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-blue">
                        <i class="fas fa-upload"></i> Import Programmes
                    </button>
                    <button type="button" onclick="hideImportForm()" class="btn btn-orange">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="filters-section">
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="search">Search Programmes</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search by name, code, description or school">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-green">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="manage_programmes.php" class="btn btn-orange">
                            <i class="fas fa-refresh"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Add/Edit Programme Form -->
    <div class="form-section" id="programmeForm" style="<?php echo $editProgramme ? 'display: block;' : 'display: none;'; ?>">
        <div class="form-card">
            <h2>
                <i class="fas fa-<?php echo $editProgramme ? 'edit' : 'plus'; ?>"></i>
                <?php echo $editProgramme ? 'Edit Programme' : 'Add New Programme'; ?>
            </h2>
            
            <form method="POST" class="school-form">
                <input type="hidden" name="action" value="<?php echo $editProgramme ? 'edit_programme' : 'add_programme'; ?>">
                <?php if ($editProgramme): ?>
                    <input type="hidden" name="programme_id" value="<?php echo $editProgramme['id']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Programme Name *</label>
                        <input type="text" id="name" name="name" required maxlength="150" 
                               value="<?php echo htmlspecialchars($editProgramme['name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="code">Programme Code *</label>
                        <input type="text" id="code" name="code" required maxlength="20" style="text-transform: uppercase;"
                               value="<?php echo htmlspecialchars($editProgramme['code'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="school_id">School *</label>
                        <select id="school_id" name="school_id" required>
                            <option value="">-- Select School --</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?php echo $school['id']; ?>" <?php echo isset($editProgramme['school_id']) && $editProgramme['school_id'] == $school['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($school['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="duration">Duration *</label>
                        <input type="number" id="duration" name="duration" required min="0.1" step="0.1" 
                               value="<?php echo htmlspecialchars($editProgramme['duration'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="duration_unit">Duration Unit *</label>
                        <select id="duration_unit" name="duration_unit" required>
                            <option value="">-- Select Unit --</option>
                            <option value="months" <?php echo isset($editProgramme['duration_unit']) && $editProgramme['duration_unit'] == 'months' ? 'selected' : ''; ?>>Months</option>
                            <option value="years" <?php echo isset($editProgramme['duration_unit']) && $editProgramme['duration_unit'] == 'years' ? 'selected' : ''; ?>>Years</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Programme Category *</label>
                        <select id="category" name="category" required>
                            <option value="">-- Select Category --</option>
                            <option value="undergraduate" <?php echo isset($editProgramme['category']) && $editProgramme['category'] == 'undergraduate' ? 'selected' : ''; ?>>Undergraduate</option>
                            <option value="short_course" <?php echo isset($editProgramme['category']) && $editProgramme['category'] == 'short_course' ? 'selected' : ''; ?>>Short Course</option>
                        </select>
                    </div>
                </div>
                    
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" 
                                      placeholder="Brief description of the programme"><?php echo htmlspecialchars($editProgramme['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-green">
                            <i class="fas fa-save"></i> <?php echo $editProgramme ? 'Update Programme' : 'Create Programme'; ?>
                        </button>
                        <button type="button" onclick="hideForm()" class="btn btn-orange">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($viewProgramme): ?>
            <div class="print-header">
                <img src="../assets/images/school_logo.jpg" alt="School Logo">
            </div>
            <div class="print-programme-summary">
                <span><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($viewProgramme['name']); ?></span>
                <span>Code: <?php echo htmlspecialchars($viewProgramme['code']); ?></span>
                <span>School: <?php echo htmlspecialchars($viewProgramme['school_name'] ?? 'N/A'); ?></span>
                <span>Duration: <?php echo htmlspecialchars($viewProgramme['duration']); ?> <?php echo htmlspecialchars($viewProgramme['duration_unit'] ?? 'Years'); ?></span>
                <span>Category: <?php echo $viewProgramme['category'] == 'undergraduate' ? 'Undergraduate' : 'Short Course'; ?></span>
                <span><?php echo htmlspecialchars($viewProgramme['description'] ?? 'No description available'); ?></span>
            </div>
            <!-- Programme Details View -->
            <div class="school-header-card">
                <div class="school-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="school-info">
                    <h2><?php echo htmlspecialchars($viewProgramme['name']); ?></h2>
                    <p class="department-code">Code: <?php echo htmlspecialchars($viewProgramme['code']); ?></p>
                    <p class="school-info">School: <?php echo htmlspecialchars($viewProgramme['school_name'] ?? 'N/A'); ?></p>
                    <p class="school-info">Duration: <?php echo htmlspecialchars($viewProgramme['duration']); ?> <?php echo htmlspecialchars($viewProgramme['duration_unit'] ?? 'Years'); ?></p>
                    <p class="school-info">Category: 
                        <?php if ($viewProgramme['category'] == 'undergraduate'): ?>
                            <span class="count-badge green">Undergraduate</span>
                        <?php else: ?>
                            <span class="count-badge orange">Short Course</span>
                        <?php endif; ?>
                    </p>
                    <p class="school-description"><?php echo htmlspecialchars($viewProgramme['description'] ?? 'No description available'); ?></p>
                </div>
                <div class="school-actions">
                    <a href="?edit=<?php echo $viewProgramme['id']; ?>" class="btn btn-orange no-print">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $viewProgramme['student_count']; ?></h3>
                        <p>Total Students</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $viewProgramme['course_count']; ?></h3>
                        <p>Courses</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo date('M Y', strtotime($viewProgramme['created_at'])); ?></h3>
                        <p>Created</p>
                    </div>
                </div>
            </div>

            <!-- Students Section -->
            <div class="section-card" id="studentsSection">
                <div class="section-header">
                    <h3><i class="fas fa-users"></i> Students</h3>
                    <div class="section-actions">
                        <a href="?action=export_programme_students&programme_id=<?php echo $viewProgramme['id']; ?>" class="btn btn-blue no-print">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </a>
                        <button class="btn btn-green no-print" onclick="printSection('students')">
                            <i class="fas fa-print"></i> Print Students
                        </button>
                        <input type="text" class="search-input" placeholder="Search students..." onkeyup="filterTable(this, 'studentsTable')">
                    </div>
                </div>
                
                <!-- Add Student to Programme Form -->
                <div class="form-card" style="margin-bottom: 20px;">
                    <h3><i class="fas fa-user-plus"></i> Add Student to Programme</h3>
                    <form method="POST" class="school-form">
                        <input type="hidden" name="action" value="add_student_to_programme">
                        <input type="hidden" name="programme_id" value="<?php echo $viewProgramme['id']; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="student_search">Search Student *</label>
                                <input type="text" id="student_search" placeholder="Type to search student..." autocomplete="off">
                                <select id="student_id" name="student_id" required style="margin-top: 5px;">
                                    <option value="">-- Select Student --</option>
                                    <?php foreach ($available_students as $student): ?>
                                        <option value="<?php echo $student['user_id']; ?>" data-search="<?php echo htmlspecialchars($student['full_name'] . ' ' . $student['student_number']); ?>">
                                            <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['student_number']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn btn-green">
                                    <i class="fas fa-plus"></i> Add Student
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php if (empty($programmeStudents)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h4>No Students</h4>
                        <p>No students enrolled in this programme</p>
                    </div>
                <?php else: ?>
                    <div class="print-section-title">Students List</div>
                    <div class="table-responsive">
                        <table class="users-table" id="studentsTable">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programmeStudents as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_number'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($student['user_email'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Courses Section -->
            <div class="section-card" id="coursesSection">
                <div class="section-header">
                    <h3><i class="fas fa-book"></i> Courses</h3>
                    <div class="section-actions">
                        <a href="?action=export_programme_courses&programme_id=<?php echo $viewProgramme['id']; ?>" class="btn btn-blue no-print">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </a>
                        <button class="btn btn-green no-print" onclick="printSection('courses')">
                            <i class="fas fa-print"></i> Print Courses
                        </button>
                        <input type="text" class="search-input" placeholder="Search courses..." onkeyup="filterTable(this, 'coursesTable')">
                    </div>
                </div>
                
                <!-- Add Course to Programme Form -->
                <div class="form-card" style="margin-bottom: 20px;">
                    <h3><i class="fas fa-book-plus"></i> Add Course to Programme</h3>
                    
                    <!-- Tabs for switching between forms -->
                    <div class="tabs">
                        <button class="tab-button active" onclick="showCourseTab('new')">Add New Course</button>
                        <button class="tab-button" onclick="showCourseTab('existing')">Assign Existing Course</button>
                    </div>
                    
                    <!-- Form for adding new course -->
                    <form method="POST" class="school-form" id="new-course-form">
                        <input type="hidden" name="action" value="add_course_to_programme">
                        <input type="hidden" name="programme_id" value="<?php echo $viewProgramme['id']; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_course_code">Course Code *</label>
                                <input type="text" id="new_course_code" name="course_code" required maxlength="20" style="text-transform: uppercase;" placeholder="e.g. CS101">
                            </div>
                            
                            <div class="form-group">
                                <label for="new_course_name">Course Name *</label>
                                <input type="text" id="new_course_name" name="course_name" required maxlength="150" placeholder="e.g. Introduction to Computer Science">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_course_credits">Credits *</label>
                                <input type="number" id="new_course_credits" name="course_credits" required min="1" max="20" value="3" placeholder="e.g. 3">
                            </div>
                            
                            <div class="form-group">
                                <label for="new_course_term">Term *</label>
                                <input type="text" id="new_course_term" name="course_term" required maxlength="50" placeholder="e.g. First Semester">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="new_course_description">Description</label>
                                <textarea id="new_course_description" name="course_description" rows="3" placeholder="Brief description of the course"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-green">
                                <i class="fas fa-plus"></i> Add Course
                            </button>
                        </div>
                    </form>
                    
                    <!-- Form for assigning existing course -->
                    <form method="POST" class="school-form" id="existing-course-form" style="display: none;">
                        <input type="hidden" name="action" value="assign_existing_course_to_programme">
                        <input type="hidden" name="programme_id" value="<?php echo $viewProgramme['id']; ?>">
                        
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="existing_course_id">Select Existing Course *</label>
                                <select id="existing_course_id" name="existing_course_id" required>
                                    <option value="">-- Select a Course --</option>
                                    <?php 
                                        // Get all courses that are not already in this programme
                                        $existing_course_ids = array_column($programmeCourses, 'id');
                                        $exclude_course_ids = $existing_course_ids ? implode(',', array_map('intval', $existing_course_ids)) : '0';
                                        $available_courses_query = "SELECT id, name, code FROM course WHERE programme_id IS NULL OR programme_id != {$viewProgramme['id']} AND id NOT IN ($exclude_course_ids) ORDER BY name";
                                        $available_courses = $pdo->query($available_courses_query)->fetchAll();
                                        foreach ($available_courses as $course): 
                                    ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['code'] . ' - ' . $course['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text">Select a course that has already been defined in the system</small>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-green">
                                <i class="fas fa-plus"></i> Assign Course to Programme
                            </button>
                        </div>
                    </form>
                </div>
                <?php if (empty($programmeCourses)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <h4>No Courses</h4>
                        <p>No courses registered under this programme</p>
                    </div>
                <?php else: ?>
                    <div class="print-section-title">Courses List</div>
                    <?php
                        $coursesByTerm = [];
                        foreach ($programmeCourses as $c) {
                            $t = $c['term'] ?? '';
                            if ($t === '' || $t === null) { $t = 'Unassigned Term'; }
                            $coursesByTerm[$t][] = $c;
                        }
                        ksort($coursesByTerm);
                    ?>
                    <?php foreach ($coursesByTerm as $termLabel => $list): ?>
                        <div class="term-title">Term: <?php echo htmlspecialchars($termLabel); ?></div>
                        <div class="table-responsive">
                            <table class="users-table coursesTable">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Credits</th>
                                        <th>Enrollments</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($list as $course): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($course['code']); ?></td>
                                            <td><?php echo htmlspecialchars($course['name']); ?></td>
                                            <td><?php echo $course['credits']; ?></td>
                                            <td><?php echo $course['enrollment_count']; ?></td>
                                            <td class="actions">
                                                <a href="#" onclick="editCourse(<?php echo $course['id']; ?>, '<?php echo addslashes(htmlspecialchars($course['code'])); ?>', '<?php echo addslashes(htmlspecialchars($course['name'])); ?>', <?php echo $course['credits']; ?>, '<?php echo addslashes(htmlspecialchars($course['term'] ?? '')); ?>', '<?php echo addslashes(htmlspecialchars($course['description'] ?? '')); ?>')" class="btn-icon btn-edit" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this course?');">
                                                    <input type="hidden" name="action" value="delete_course">
                                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                    <input type="hidden" name="programme_id" value="<?php echo $viewProgramme['id']; ?>">
                                                    <button type="submit" class="btn-icon btn-delete" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Lecturers Section -->
            <div class="section-card" id="lecturersSection">
                <div class="section-header">
                    <h3><i class="fas fa-chalkboard-teacher"></i> Lecturers</h3>
                    <div class="section-actions">
                        <a href="?action=export_programme_lecturers&programme_id=<?php echo $viewProgramme['id']; ?>" class="btn btn-blue no-print">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </a>
                        <button class="btn btn-green no-print" onclick="printSection('lecturers')">
                            <i class="fas fa-print"></i> Print Lecturers
                        </button>
                        <input type="text" class="search-input" placeholder="Search lecturers..." onkeyup="filterTable(this, 'lecturersTable')">
                    </div>
                </div>
                
                <!-- Assign Lecturer to Course Form -->
                <div class="form-card" style="margin-bottom: 20px;">
                    <h3><i class="fas fa-chalkboard-teacher"></i> Assign Lecturer to Course</h3>
                    <form method="POST" class="school-form">
                        <input type="hidden" name="action" value="add_lecturer_to_programme">
                        <input type="hidden" name="programme_id" value="<?php echo $viewProgramme['id']; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="lecturer_id">Select Lecturer *</label>
                                <select id="lecturer_id" name="lecturer_id" required>
                                    <option value="">-- Select Lecturer --</option>
                                    <?php foreach ($all_lecturers as $lecturer): ?>
                                        <option value="<?php echo $lecturer['user_id']; ?>">
                                            <?php echo htmlspecialchars($lecturer['full_name']); ?> (<?php echo htmlspecialchars($lecturer['staff_id']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="course_id">Select Course *</label>
                                <select id="course_id" name="course_id" required>
                                    <option value="">-- Select Course --</option>
                                    <?php foreach ($programme_courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['name']); ?> (<?php echo htmlspecialchars($course['code']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="academic_year">Academic Year *</label>
                                <input type="text" id="academic_year" name="academic_year" required placeholder="e.g. 2024/2025">
                            </div>
                            
                            <div class="form-group">
                                <label for="semester">Semester *</label>
                                <select id="semester" name="semester" required>
                                    <option value="">-- Select Semester --</option>
                                    <option value="First">First</option>
                                    <option value="Second">Second</option>
                                    <option value="Third">Third (Summer)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-green">
                                <i class="fas fa-user-plus"></i> Assign Lecturer
                            </button>
                        </div>
                    </form>
                </div>
                <?php if (empty($programmeLecturers)): ?>
                    <div class="empty-state">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <h4>No Lecturers</h4>
                        <p>No lecturers found in this school</p>
                    </div>
                <?php else: ?>
                    <div class="print-section-title">Lecturers List</div>
                    <div class="table-responsive">
                        <table class="users-table" id="lecturersTable">
                            <thead>
                                <tr>
                                    <th>Staff ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programmeLecturers as $lecturer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($lecturer['staff_id']); ?></td>
                                        <td><?php echo htmlspecialchars($lecturer['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($lecturer['user_email']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Programmes Table -->
            <div class="table-section">
                <div class="table-card">
                    <div class="table-header">
                        <h2><i class="fas fa-list"></i> Programmes List (<?php echo $totalProgrammes; ?> programmes)</h2>
                    </div>
                    
                    <!-- Pagination Controls -->
                    <div class="pagination-controls">
                        <div class="pagination-info">
                            Showing <?php echo min($perPage, $totalProgrammes - ($page - 1) * $perPage); ?> of <?php echo $totalProgrammes; ?> programmes
                        </div>
                        <div class="pagination-settings">
                            <label for="per_page">Per Page:</label>
                            <select id="per_page" onchange="changePerPage()">
                                <option value="5" <?php echo $perPage == 5 ? 'selected' : ''; ?>>5</option>
                                <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo $perPage == 20 ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                        <div class="pagination-nav">
                            <?php if ($totalPages > 1): ?>
                                <?php if ($page > 1): ?>
                                    <a href="?page=1&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($searchQuery); ?>" class="btn btn-sm btn-outline">First</a>
                                    <a href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($searchQuery); ?>" class="btn btn-sm btn-outline">Previous</a>
                                <?php endif; ?>
                                
                                <span class="page-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($searchQuery); ?>" class="btn btn-sm btn-outline">Next</a>
                                    <a href="?page=<?php echo $totalPages; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($searchQuery); ?>" class="btn btn-sm btn-outline">Last</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>School</th>
                                    <th>Duration</th>
                                    <th>Category</th>
                                    <th>Students</th>
                                    <th>Courses</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programmes as $programme): ?>
                                    <tr>
                                        <td><?php echo $programme['id']; ?></td>
                                        <td><?php echo htmlspecialchars($programme['code']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($programme['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($programme['school_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo $programme['duration']; ?> <?php echo $programme['duration_unit'] ?? 'Years'; ?></td>
                                        <td>
                                            <?php if ($programme['category'] == 'undergraduate'): ?>
                                                <span class="count-badge green">Undergraduate</span>
                                            <?php else: ?>
                                                <span class="count-badge orange">Short Course</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="count-badge green"><?php echo $programme['student_count']; ?></span></td>
                                        <td><span class="count-badge orange"><?php echo $programme['course_count']; ?></span></td>
                                        <td class="actions">
                                            <a href="?view=<?php echo $programme['id']; ?>" class="btn-icon btn-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?edit=<?php echo $programme['id']; ?>" class="btn-icon btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($programme['student_count'] == 0): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this programme?');">
                                                    <input type="hidden" name="action" value="delete_programme">
                                                    <input type="hidden" name="programme_id" value="<?php echo $programme['id']; ?>">
                                                    <button type="submit" class="btn-icon btn-delete" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        function printSection(which) {
            const map = {
                students: 'print-students',
                courses: 'print-courses',
                lecturers: 'print-lecturers'
            };
            const cls = map[which];
            if (!cls) { window.print(); return; }
            document.body.classList.remove('print-students','print-courses','print-lecturers');
            document.body.classList.add(cls);
            setTimeout(() => {
                window.print();
                // Remove class shortly after print to restore view
                setTimeout(() => document.body.classList.remove(cls), 100);
            }, 50);
        }
        
        function showAddForm() {
            document.getElementById('programmeForm').style.display = 'block';
            document.getElementById('importForm').style.display = 'none';
            document.getElementById('name').focus();
        }
        
        function hideForm() {
            document.getElementById('programmeForm').style.display = 'none';
        }
        
        function showImportForm() {
            document.getElementById('importForm').style.display = 'block';
            document.getElementById('programmeForm').style.display = 'none';
        }
        
        function hideImportForm() {
            document.getElementById('importForm').style.display = 'none';
        }
        
        function downloadTemplate() {
            // Create CSV content
            let csvContent = "Code,Name,School,Duration,Duration Unit,Category\n";
            csvContent += "BSC-CS,Bachelor of Science in Computer Science,School of Computing,4,years,undergraduate\n";
            csvContent += "SHORT-CS,Short Course in Computer Science,School of Computing,6,months,short_course\n";
            
            // Create blob and download
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", "programmes_template.csv");
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function filterTable(input, tableId) {
            const filter = input.value.toLowerCase();
            const table = document.getElementById(tableId);
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let show = false;
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j] && cells[j].textContent.toLowerCase().includes(filter)) {
                        show = true;
                        break;
                    }
                }
                rows[i].style.display = show ? '' : 'none';
            }
        }
        
        // Auto-show form if editing
        <?php if ($editProgramme): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showAddForm();
            });
        <?php endif; ?>

        // Auto-hide alerts
        <?php if ($message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
        <?php endif; ?>
        
        // Change number of items per page
        function changePerPage() {
            const perPage = document.getElementById('per_page').value;
            const searchQuery = document.getElementById('search').value;
            
            // Construct the URL with current filters and new per_page value
            let url = `?page=1&per_page=${perPage}`;
            if (searchQuery) url += `&search=${encodeURIComponent(searchQuery)}`;
            
            window.location.href = url;
        }
        
        // Searchable dropdown functionality for student selection
        document.addEventListener('DOMContentLoaded', function() {
            // Student search functionality
            const studentSearch = document.getElementById('student_search');
            const studentSelect = document.getElementById('student_id');
            
            studentSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const options = studentSelect.options;
                
                for (let i = 1; i < options.length; i++) { // Start from 1 to skip the "-- Select Student --" option
                    const option = options[i];
                    const searchValue = option.getAttribute('data-search').toLowerCase();
                    
                    if (searchValue.includes(searchTerm)) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                    }
                }
            });
        });
        
        // Function to edit a course
        function editCourse(courseId, code, name, credits, term, description) {
            // Set the form fields with the course data
            document.getElementById('new_course_code').value = code;
            document.getElementById('new_course_name').value = name;
            document.getElementById('new_course_credits').value = credits;
            document.getElementById('new_course_term').value = term;
            document.getElementById('new_course_description').value = description;
            
            // Change the form action to update course
            const form = document.getElementById('new-course-form');
            const actionInput = document.querySelector('#new-course-form input[name="action"]');
            actionInput.value = 'update_course';
            
            // Change submit button text
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.innerHTML = '<i class="fas fa-sync-alt"></i> Update Course';
            
            // Add hidden field for course ID
            let courseIdInput = document.getElementById('course_id_input');
            if (!courseIdInput) {
                courseIdInput = document.createElement('input');
                courseIdInput.type = 'hidden';
                courseIdInput.name = 'course_id';
                courseIdInput.id = 'course_id_input';
                form.appendChild(courseIdInput);
            }
            courseIdInput.value = courseId;
            
            // Show the 'new course' form tab
            showCourseTab('new');
        }
        
        // Function to show selected course tab
        function showCourseTab(tabName) {
            const newForm = document.getElementById('new-course-form');
            const existingForm = document.getElementById('existing-course-form');
            const newTabBtn = document.querySelector('[onclick="showCourseTab(\'new\')"]');
            const existingTabBtn = document.querySelector('[onclick="showCourseTab(\'existing\')"]');
            
            if (tabName === 'new') {
                newForm.style.display = 'block';
                existingForm.style.display = 'none';
                newTabBtn.classList.add('active');
                existingTabBtn.classList.remove('active');
            } else if (tabName === 'existing') {
                newForm.style.display = 'none';
                existingForm.style.display = 'block';
                newTabBtn.classList.remove('active');
                existingTabBtn.classList.add('active');
            }
            
            // Reset form if switching to new course tab
            if (tabName === 'new') {
                const form = document.getElementById('new-course-form');
                const actionInput = document.querySelector('#new-course-form input[name="action"]');
                if (actionInput.value === 'update_course') {
                    actionInput.value = 'add_course_to_programme';
                    const submitButton = form.querySelector('button[type="submit"]');
                    submitButton.innerHTML = '<i class="fas fa-plus"></i> Add Course';
                    
                    // Clear the course ID field
                    const courseIdInput = document.getElementById('course_id_input');
                    if (courseIdInput) {
                        courseIdInput.remove();
                    }
                    
                    // Reset form fields
                    form.reset();
                    // Set the action back to add course
                    actionInput.value = 'add_course_to_programme';
                }
            }
        }
    </script>
</main>
</body>
</html>