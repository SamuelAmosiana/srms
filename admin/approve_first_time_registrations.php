<?php
session_start();
require_once '../config.php';
require_once '../auth.php';
require_once '../send_temporary_credentials.php';

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Location: ../login.php');
    exit();
}

// Check if user has admin role
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasRole('Sub Admin (Finance)', $pdo)) {
    header('Location: ../login.php');
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT u.*, ap.full_name, ap.staff_id FROM users u LEFT JOIN admin_profile ap ON u.id = ap.user_id WHERE u.id = ?");
$stmt->execute([currentUserId()]);
$user = $stmt->fetch();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve_registration':
                $pending_student_id = $_POST['pending_student_id'];
                
                try {
                    // Get pending student details
                    $stmt = $pdo->prepare("
                        SELECT ps.*, p.name as programme_name, i.name as intake_name 
                        FROM pending_students ps 
                        LEFT JOIN programme p ON ps.programme_id = p.id 
                        LEFT JOIN intake i ON ps.intake_id = i.id 
                        WHERE ps.id = ?
                    ");
                    $stmt->execute([$pending_student_id]);
                    $pending_student = $stmt->fetch();
                    
                    if (!$pending_student) {
                        throw new Exception("Pending student not found!");
                    }
                    
                    // Generate student number
                    $student_number = generateStudentNumber($pdo);
                    
                    // Generate temporary password
                    $temp_password = 'LSC@' . date('Y') . rand(1000, 9999);
                    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                    
                    // Update pending student with student number and approved status
                    // Handle case where student_number column might not exist yet
                    try {
                        $stmt = $pdo->prepare("UPDATE pending_students SET student_number = ?, temp_password = ?, registration_status = 'approved', updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$student_number, $hashed_password, $pending_student_id]);
                    } catch (Exception $e) {
                        // If student_number column doesn't exist, update without it
                        $stmt = $pdo->prepare("UPDATE pending_students SET temp_password = ?, registration_status = 'approved', updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$hashed_password, $pending_student_id]);
                    }
                    
                    // Create user account
                    $pdo->beginTransaction();
                    
                    try {
                        // Create user account
                        $user_stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, contact, is_active) VALUES (?, ?, ?, ?, 1)");
                        $user_stmt->execute([
                            $student_number,
                            $hashed_password,
                            $pending_student['email'],
                            '' // No contact info available
                        ]);
                        
                        $user_id = $pdo->lastInsertId();
                        
                        // Assign student role
                        $role_stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Student'");
                        $role_stmt->execute();
                        $student_role = $role_stmt->fetch();
                        
                        if ($student_role) {
                            $user_role_stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                            $user_role_stmt->execute([$user_id, $student_role['id']]);
                        }
                        
                        // Create student profile
                        $profile_stmt = $pdo->prepare("INSERT INTO student_profile (user_id, full_name, student_number, programme_id, intake_id) VALUES (?, ?, ?, ?, ?)");
                        $profile_stmt->execute([
                            $user_id,
                            $pending_student['full_name'],
                            $student_number,
                            $pending_student['programme_id'],
                            $pending_student['intake_id']
                        ]);
                        
                        $pdo->commit();
                        
                        // Send registration confirmation email
                        sendRegistrationConfirmationEmail(
                            $pending_student['email'],
                            $pending_student['full_name'],
                            $student_number,
                            $temp_password
                        );
                        
                        $message = "Registration approved successfully! Student account created and email sent.";
                        $messageType = "success";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw new Exception("Failed to create student account: " . $e->getMessage());
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'reject_registration':
                $pending_student_id = $_POST['pending_student_id'];
                $rejection_reason = trim($_POST['rejection_reason']);
                
                if (empty($rejection_reason)) {
                    $message = "Please provide a rejection reason!";
                    $messageType = "error";
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE pending_students SET registration_status = 'rejected', rejection_reason = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$rejection_reason, $pending_student_id]);
                        
                        $message = "Registration rejected successfully!";
                        $messageType = "success";
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = "error";
                    }
                }
                break;
        }
    }
}

// Get pending registrations with 'pending_approval' status
try {
    $stmt = $pdo->prepare("
        SELECT ps.*, p.name as programme_name, i.name as intake_name 
        FROM pending_students ps 
        LEFT JOIN programme p ON ps.programme_id = p.id 
        LEFT JOIN intake i ON ps.intake_id = i.id 
        WHERE ps.registration_status = 'pending_approval' 
        ORDER BY ps.created_at DESC
    ");
    $stmt->execute();
    $pending_registrations = $stmt->fetchAll();
} catch (Exception $e) {
    $pending_registrations = [];
}

// Get approved registrations
try {
    $stmt = $pdo->prepare("
        SELECT ps.*, p.name as programme_name, i.name as intake_name 
        FROM pending_students ps 
        LEFT JOIN programme p ON ps.programme_id = p.id 
        LEFT JOIN intake i ON ps.intake_id = i.id 
        WHERE ps.registration_status = 'approved' 
        ORDER BY ps.updated_at DESC
    ");
    $stmt->execute();
    $approved_registrations = $stmt->fetchAll();
} catch (Exception $e) {
    $approved_registrations = [];
}

// Get rejected registrations
try {
    $stmt = $pdo->prepare("
        SELECT ps.*, p.name as programme_name, i.name as intake_name 
        FROM pending_students ps 
        LEFT JOIN programme p ON ps.programme_id = p.id 
        LEFT JOIN intake i ON ps.intake_id = i.id 
        WHERE ps.registration_status = 'rejected' 
        ORDER BY ps.updated_at DESC
    ");
    $stmt->execute();
    $rejected_registrations = $stmt->fetchAll();
} catch (Exception $e) {
    $rejected_registrations = [];
}

// Function to generate student number
function generateStudentNumber($pdo) {
    // Format: LSC + 6-digit sequential number
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(student_number, 4) AS UNSIGNED)) as max_num FROM student_profile WHERE student_number LIKE 'LSC%'");
    $result = $stmt->fetch();
    $next_num = ($result['max_num'] ?? 0) + 1;
    return sprintf("LSC%06d", $next_num);
}
?>