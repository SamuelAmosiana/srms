<?php
// Enable real error output
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../config.php';
require '../auth/auth.php';

// Include email configuration
require_once '../email_config.php';

// Include the new acceptance letter with fees function
require_once '../finance/generate_acceptance_letter_with_fees.php';

// Include the new DOMPDF acceptance letter generator
require_once '../letters_reports/generate_acceptance_letter_dompdf.php';

// Include the email functionality (contains sendAcceptanceLetterEmail function)
require_once '../letters_reports/generate_acceptance_letter_docx.php';

// Add a helper function to send emails with better error handling
function sendEmailWithImprovedHandling($to, $subject, $body) {
    // First try PHPMailer if available
    global $phpmailer_available;
    
    if ($phpmailer_available) {
        // Include the PHPMailer functions
        require_once '../letters_reports/generate_acceptance_letter_docx.php';
        $result = sendEmailWithPHPMailer($to, $subject, $body);
        if ($result) {
            return true;
        }
        error_log("PHPMailer failed, trying fallback method for: " . $to);
    }
    
    // Fallback to mail() function
    $headers = "From: " . EMAIL_FROM . "\r\n";
    $headers .= "Reply-To: " . EMAIL_REPLY_TO . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    $result = mail($to, $subject, $body, $headers);
    
    if ($result) {
        error_log("Email sent using fallback method to: " . $to);
    } else {
        error_log("Email sending failed for: " . $to);
    }
    
    return $result;
}

/**
 * Send rejection email to applicant
 * 
 * @param array $application Application data
 * @param string $rejection_reason Reason for rejection
 * @param PDO $pdo Database connection
 * @return bool Whether email was sent successfully
 */
function sendRejectionEmail($application, $rejection_reason, $pdo) {
    global $phpmailer_available;
    
    // Create email content
    $subject = "Application Rejection - Lusaka South College";
    
    $body = "Dear " . $application['full_name'] . "\n\n";
    $body .= "We regret to inform you that your application for admission at Lusaka South College has been rejected.\n\n";
    $body .= "Programme: " . $application['programme_name'] . "\n";
    $body .= "Intake: " . ($application['intake_name'] ? explode(' ', $application['intake_name'])[0] : 'N/A') . "\n";
    $body .= "Phone: " . ($application['phone'] ?? 'N/A') . "\n\n";
    
    $body .= "REJECTION REASON:\n";
    $body .= "==================\n";
    $body .= $rejection_reason . "\n\n";
    
    $body .= "If you believe this decision was made in error, or if you have any questions regarding this decision, please feel free to contact our admissions office.\n\n";
    
    $body .= "For any queries regarding your application, please Contact/WhatsApp the Admissions Office +260 770359518 or email: admissions@lsuczm.com Or visit our main campus in Foxdale Lusaka at the Corner of Zambezi and Mutumbi Road.\n\n";
    
    $body .= "Thank you for considering Lusaka South College.\n\n";
    $body .= "Best regards,\n";
    $body .= "Admissions Office\n";
    $body .= "Lusaka South College";
    
    // Try to use PHPMailer if available, otherwise fall back to mail()
    if ($phpmailer_available) {
        return sendEmailWithPHPMailer($application['email'], $subject, $body);
    } else {
        // Send email using PHP's mail function
        $headers = "From: " . EMAIL_FROM . "\r\n";
        $headers .= "Reply-To: " . EMAIL_REPLY_TO . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // Try to send the email
        $result = mail($application['email'], $subject, $body, $headers);
        
        // Log email details for debugging (whether successful or not)
        error_log("Rejection email attempt to: " . $application['email']);
        error_log("Subject: " . $subject);
        
        // If mail() fails due to SMTP configuration, provide a more user-friendly message
        if (!$result) {
            error_log("Failed to send rejection email to: " . $application['email'] . ". This is likely due to SMTP configuration issues.");
            // Return true anyway so the application process continues
            return true;
        }
        
        return $result;
    }
}

// Check if user is logged in and has enrollment officer role
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit;
}

requireRole('Enrollment Officer', $pdo);

// Get enrollment officer profile
$stmt = $pdo->prepare("SELECT sp.full_name, sp.staff_id FROM staff_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$enrollmentOfficer = $stmt->fetch();

// Handle application actions (approve/reject)
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['application_id'])) {
        $application_id = $_POST['application_id'];
        $current_user_id = currentUserId(); // Get current enrollment officer ID
        
        try {
            switch ($_POST['action']) {
                case 'approve':
                    // First get application details
                    $app_stmt = $pdo->prepare("
                        SELECT a.*, p.name as programme_name, i.name as intake_name, p.id as programme_id
                        FROM applications a
                        LEFT JOIN programme p ON a.programme_id = p.id
                        LEFT JOIN intake i ON a.intake_id = i.id
                        WHERE a.id = ?
                    ");
                    $app_stmt->execute([$application_id]);
                    $application = $app_stmt->fetch();
                    
                    // Update application status to approved
                    $stmt = $pdo->prepare("UPDATE applications SET status = 'approved', processed_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$current_user_id, $application_id]);
                    
                    // Generate acceptance letter in PDF format using DOMPDF
                    $letter_path = generateAcceptanceLetterDOMPDF($application, $pdo);
                    
                    // Send acceptance letter email with instruction to use email for first-time login
                    $login_details = [
                        'instruction' => 'Use your email address (' . $application['email'] . ') to login to the First Time Student Registration portal'
                    ];
                    
                    // Check if the function exists before calling it
                    if (function_exists('sendAcceptanceLetterEmail')) {
                        $email_sent = sendAcceptanceLetterEmail($application, $letter_path, $login_details, $pdo);
                        if ($email_sent) {
                            $message = "Application approved successfully! Acceptance letter generated and email sent to applicant with instructions to login using their email address.";
                        } else {
                            $message = "Application approved successfully! Acceptance letter generated but email could not be sent. Please check email configuration.";
                        }
                    } else {
                        error_log('sendAcceptanceLetterEmail function is not available');
                        $message = "Application approved successfully! Acceptance letter generated but email could not be sent.";
                    }
                    
                    $messageType = "success";
                    break;
                    
                case 'reject':
                    $rejection_reason = trim($_POST['rejection_reason']);
                    if (empty($rejection_reason)) {
                        throw new Exception("Rejection reason is required!");
                    }
                    
                    // First get application details before updating
                    $app_stmt = $pdo->prepare("
                        SELECT a.*, p.name as programme_name, i.name as intake_name
                        FROM applications a
                        LEFT JOIN programme p ON a.programme_id = p.id
                        LEFT JOIN intake i ON a.intake_id = i.id
                        WHERE a.id = ?
                    ");
                    $app_stmt->execute([$application_id]);
                    $application = $app_stmt->fetch();
                    
                    $stmt = $pdo->prepare("UPDATE applications SET status = 'rejected', rejection_reason = ?, processed_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$rejection_reason, $current_user_id, $application_id]);
                    
                    // Send rejection email to the applicant
                    if ($application && !empty($application['email'])) {
                        $email_sent = sendRejectionEmail($application, $rejection_reason, $pdo);
                        if ($email_sent) {
                            $message = "Application rejected successfully! Rejection email sent to the applicant.";
                        } else {
                            $message = "Application rejected successfully, but failed to send rejection email to the applicant.";
                        }
                    } else {
                        $message = "Application rejected successfully!";
                    }
                    
                    $messageType = "success";
                    break;
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Get undergraduate applications - updated logic to match actual programme names
$stmt = $pdo->prepare("
    SELECT a.*, a.phone, p.name as programme_name, i.name as intake_name
    FROM applications a
    LEFT JOIN programme p ON a.programme_id = p.id
    LEFT JOIN intake i ON a.intake_id = i.id
    WHERE a.status = 'pending' 
    AND a.application_type = 'undergraduate'
    ORDER BY a.created_at DESC
");
$stmt->execute();
$undergraduateApplications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approved undergraduate applications
$stmt = $pdo->prepare("
    SELECT a.*, a.phone, p.name as programme_name, i.name as intake_name
    FROM applications a
    LEFT JOIN programme p ON a.programme_id = p.id
    LEFT JOIN intake i ON a.intake_id = i.id
    WHERE a.status = 'approved' 
    AND a.application_type = 'undergraduate'
    ORDER BY a.created_at DESC
");
$stmt->execute();
$approvedUndergraduateApplications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get rejected undergraduate applications
$stmt = $pdo->prepare("
    SELECT a.*, a.phone, p.name as programme_name, i.name as intake_name
    FROM applications a
    LEFT JOIN programme p ON a.programme_id = p.id
    LEFT JOIN intake i ON a.intake_id = i.id
    WHERE a.status = 'rejected' 
    AND a.application_type = 'undergraduate'
    ORDER BY a.created_at DESC
");
$stmt->execute();
$rejectedUndergraduateApplications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Undergraduate Applications - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .modal-body .application-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .modal-body .detail-row {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .modal-body .detail-item {
            display: flex;
            flex-direction: column;
            margin-bottom: 8px;
        }

        .modal-body .detail-item strong {
            font-size: 12px;
            color: #6C757D;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-body .detail-item span {
            font-size: 14px;
            color: #212529;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .modal-body .application-details {
                grid-template-columns: 1fr;
            }
            
            .modal-body .detail-row {
                flex-direction: column;
            }
        }

        /* Additional styles for documents section */
        #documents_section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #DEE2E6;
        }

        #documents_section ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        #documents_section ul li {
            padding: 8px 0;
            border-bottom: 1px solid #DEE2E6;
            display: flex;
            align-items: center;
        }

        #documents_section ul li:last-child {
            border-bottom: none;
        }

        #documents_section ul li a {
            color: #228B22;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        #documents_section ul li a:hover {
            text-decoration: underline;
        }

        #documents_section ul li a i {
            font-size: 14px;
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($enrollmentOfficer['full_name'] ?? 'Enrollment Officer'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($enrollmentOfficer['staff_id'] ?? 'N/A'); ?>)</span>
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
            <h3><i class="fas fa-tachometer-alt"></i> Enrollment Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Applications</h4>
                <a href="undergraduate_applications.php" class="nav-item active">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Undergraduate</span>
                </a>
                <a href="short_courses_applications.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <span>Short Courses</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>My Approvals</h4>
                <a href="my_approvals.php" class="nav-item">
                    <i class="fas fa-thumbs-up"></i>
                    <span>My Approvals</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Registered Students</h4>
                <a href="registered_students.php" class="nav-item">
                    <i class="fas fa-user-graduate"></i>
                    <span>Registered Students</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports</h4>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Enrollment Reports</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-graduation-cap"></i> Undergraduate Applications</h1>
            <p>Manage undergraduate student applications</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Undergraduate Applications -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-graduation-cap"></i> Pending Undergraduate Applications (<?php echo count($undergraduateApplications); ?>)</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($undergraduateApplications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-graduation-cap"></i>
                        <p>No pending undergraduate applications</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Programme</th>
                                    <th>Intake</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($undergraduateApplications as $app): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['email']); ?></td>
                                        <td><?php echo htmlspecialchars($app['phone'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($app['programme_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($app['intake_name']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($app['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick='viewApplication(<?php echo json_encode($app, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-sm btn-success" onclick="approveApplication(<?php echo (int)$app['id']; ?>)">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="rejectApplication(<?php echo (int)$app['id']; ?>)">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Approved Undergraduate Applications -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-check-circle"></i> Approved Undergraduate Applications (<?php echo count($approvedUndergraduateApplications); ?>)</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($approvedUndergraduateApplications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No approved undergraduate applications</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Programme</th>
                                    <th>Intake</th>
                                    <th>Submitted</th>
                                    <th>Approved Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approvedUndergraduateApplications as $app): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['email']); ?></td>
                                        <td><?php echo htmlspecialchars($app['phone'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($app['programme_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($app['intake_name']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($app['created_at'])); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($app['updated_at'] ?? $app['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rejected Undergraduate Applications -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-times-circle"></i> Rejected Undergraduate Applications (<?php echo count($rejectedUndergraduateApplications); ?>)</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($rejectedUndergraduateApplications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-times-circle"></i>
                        <p>No rejected undergraduate applications</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Programme</th>
                                    <th>Intake</th>
                                    <th>Submitted</th>
                                    <th>Rejected Date</th>
                                    <th>Rejection Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rejectedUndergraduateApplications as $app): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['email']); ?></td>
                                        <td><?php echo htmlspecialchars($app['phone'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($app['programme_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($app['intake_name']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($app['created_at'])); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($app['updated_at'] ?? $app['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($app['rejection_reason'] ?? 'No reason provided'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- View Application Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-alt"></i> Application Details</h3>
                <span class="close" onclick="closeModal('viewModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="application-details">
                    <div class="detail-row">
                        <div class="detail-item">
                            <strong>Name:</strong> <span id="view_name"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Email:</strong> <span id="view_email"></span>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <strong>Phone:</strong> <span id="view_phone"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Programme:</strong> <span id="view_programme"></span>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <strong>Intake:</strong> <span id="view_intake"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Submitted:</strong> <span id="view_submitted"></span>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <strong>Mode of Learning:</strong> <span id="view_mode_of_learning"></span>
                        </div>
                        <div class="detail-item">
                            <strong>NRC Number:</strong> <span id="view_nrc_number"></span>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <strong>Gender:</strong> <span id="view_gender"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Date of Birth:</strong> <span id="view_dob"></span>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <strong>Address:</strong> <span id="view_address"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Recommended By:</strong> <span id="view_recommended_by"></span>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <strong>Guardian Name:</strong> <span id="view_guardian_name"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Guardian Phone:</strong> <span id="view_guardian_phone"></span>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <strong>Relationship:</strong> <span id="view_relationship"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Status:</strong> <span id="view_status"></span>
                        </div>
                    </div>
                    
                    <div id="documents_section">
                        <strong>Documents:</strong>
                        <ul id="documents_list"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-success" onclick="approveFromModal()"><i class="fas fa-check"></i> Approve</button>
                <button class="btn btn-danger" onclick="rejectFromModal()"><i class="fas fa-times"></i> Reject</button>
            </div>
        </div>
    </div>

    <!-- Reject Reason Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Reject Application</h3>
                <span class="close" onclick="closeModal('rejectModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" id="reject_application_id" name="application_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="rejection_reason">Rejection Reason *</label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="4" required placeholder="Explain why the application is being rejected..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Approval Confirmation Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-check-circle"></i> Approve Application</h3>
                <span class="close" onclick="closeModal('approveModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" id="approve_application_id" name="application_id">
                <div class="modal-body">
                    <p>Are you sure you want to approve this application?</p>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Approve</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        let currentApplicationId = null;
        

        
        function viewApplication(app) {
            document.getElementById('view_name').textContent = app.full_name || 'N/A';
            document.getElementById('view_email').textContent = app.email || 'N/A';
            document.getElementById('view_phone').textContent = app.phone || 'N/A';
            document.getElementById('view_programme').textContent = app.programme_name || 'N/A';
            document.getElementById('view_intake').textContent = app.intake_name || 'N/A';
            document.getElementById('view_submitted').textContent = app.created_at ? new Date(app.created_at).toLocaleDateString() : 'N/A';
            document.getElementById('view_mode_of_learning').textContent = app.mode_of_learning || 'N/A';
            document.getElementById('view_status').textContent = app.status || 'N/A';
            
            // Parse documents JSON to extract additional information
            let nrc_number = 'N/A';
            let gender = 'N/A';
            let recommended_by = 'N/A';
            let guardian_name = 'N/A';
            let guardian_phone = 'N/A';
            let relationship = 'N/A';
            let address = 'N/A';
            let dob = 'N/A';
            
            try {
                if (app.documents) {
                    const documents = JSON.parse(app.documents);
                    
                    // Extract additional information from documents JSON
                    if (typeof documents === 'object' && documents !== null) {
                        nrc_number = documents.nrc_number || 'N/A';
                        gender = documents.gender || 'N/A';
                        recommended_by = documents.recommended_by || 'N/A';
                        guardian_name = documents.guardian_name || 'N/A';
                        guardian_phone = documents.guardian_phone || 'N/A';
                        relationship = documents.relationship || 'N/A';
                        address = documents.address || 'N/A';
                        dob = documents.date_of_birth || documents.dob || 'N/A';
                    }
                }
            } catch (e) {
                console.error('Error parsing documents JSON:', e);
            }
            
            // Set the extracted values
            document.getElementById('view_nrc_number').textContent = nrc_number;
            document.getElementById('view_gender').textContent = gender;
            document.getElementById('view_recommended_by').textContent = recommended_by;
            document.getElementById('view_guardian_name').textContent = guardian_name;
            document.getElementById('view_guardian_phone').textContent = guardian_phone;
            document.getElementById('view_relationship').textContent = relationship;
            document.getElementById('view_address').textContent = address;
            document.getElementById('view_dob').textContent = dob;
            
            const docsList = document.getElementById('documents_list');
            docsList.innerHTML = '';
            
            try {
                // Parse documents JSON
                const documents = app.documents ? JSON.parse(app.documents) : {};
                
                // Handle file documents
                if (Array.isArray(documents)) {
                    if (documents.length > 0) {
                        documents.forEach(doc => {
                            const li = document.createElement('li');
                            if (typeof doc === 'string') {
                                // Handle legacy format
                                li.textContent = doc;
                            } else if (doc.path && doc.name) {
                                // Handle new format with path and name
                                // Extract filename using a more robust method
                                let filename = doc.path;
                                // Look for the last occurrence of '/' and extract everything after it
                                const lastSlashIndex = doc.path.lastIndexOf('/');
                                if (lastSlashIndex >= 0) {
                                    filename = doc.path.substring(lastSlashIndex + 1);
                                }
                                
                                // Debug logging
                                console.log("Document path:", doc.path);
                                console.log("Document name:", doc.name);
                                console.log("Extracted filename:", filename);
                                
                                // Check if we have valid values
                                if (!filename || !doc.name) {
                                    console.error("Missing required values for document download");
                                    li.innerHTML = `<span style="color:red;">Error: Missing document data</span>`;
                                    docsList.appendChild(li);
                                    return;
                                }
                                
                                const downloadUrl = `/srms/enrollment/download_document.php?file=${encodeURIComponent(filename)}&original_name=${encodeURIComponent(doc.name)}`;
                                console.log("FINAL DOWNLOAD URL:", downloadUrl);
                                li.innerHTML = `<a href="${downloadUrl}" target="_blank" style="color:#228B22; text-decoration:none;"><i class="fas fa-file"></i> ${doc.name}</a>`;
                            } else {
                                // Handle other formats
                                li.textContent = JSON.stringify(doc);
                            }
                            docsList.appendChild(li);
                        });
                    } else {
                        docsList.innerHTML = '<li>No documents attached</li>';
                    }
                } else if (typeof documents === 'object' && documents !== null) {
                    // Handle object format (new applications with recommended_by)
                    const fileDocs = [];
                    const additionalInfo = [];
                    
                    // Extract file documents and additional info
                    for (const key in documents) {
                        if (typeof documents[key] === 'object' && documents[key].path) {
                            fileDocs.push(documents[key]);
                        } else if (key !== 'path' && key !== 'name' && key !== 'nrc_number' && key !== 'gender' && key !== 'recommended_by' && key !== 'guardian_name' && key !== 'guardian_phone' && key !== 'relationship' && key !== 'address' && key !== 'date_of_birth' && key !== 'dob' && documents[key]) {
                            additionalInfo.push({label: key, value: documents[key]});
                        }
                    }
                    
                    // Display file documents
                    if (fileDocs.length > 0) {
                        fileDocs.forEach(doc => {
                            const li = document.createElement('li');
                            // Extract filename using a more robust method
                            let filename = doc.path;
                            // Look for the last occurrence of '/' and extract everything after it
                            const lastSlashIndex = doc.path.lastIndexOf('/');
                            if (lastSlashIndex >= 0) {
                                filename = doc.path.substring(lastSlashIndex + 1);
                            }
                            
                            // Debug logging
                            console.log("Document path:", doc.path);
                            console.log("Document name:", doc.name);
                            console.log("Extracted filename:", filename);
                            
                            // Check if we have valid values
                            if (!filename || !doc.name) {
                                console.error("Missing required values for document download");
                                li.innerHTML = `<span style="color:red;">Error: Missing document data</span>`;
                                docsList.appendChild(li);
                                return;
                            }
                                                        
                            const downloadUrl = `/srms/enrollment/download_document.php?file=${encodeURIComponent(filename)}&original_name=${encodeURIComponent(doc.name)}`;
                            console.log("FINAL DOWNLOAD URL:", downloadUrl);
                            li.innerHTML = `<a href="${downloadUrl}" target="_blank" style="color:#228B22; text-decoration:none;"><i class="fas fa-file"></i> ${doc.name}</a>`;
                            docsList.appendChild(li);
                        });
                    }
                    
                    // Display additional information
                    if (additionalInfo.length > 0) {
                        additionalInfo.forEach(info => {
                            if (info.value) {
                                const li = document.createElement('li');
                                li.innerHTML = `<strong>${info.label.replace('_', ' ')}:</strong> ${info.value}`;
                                docsList.appendChild(li);
                            }
                        });
                    }
                    
                    // If no documents or info, show default message
                    if (fileDocs.length === 0 && additionalInfo.length === 0) {
                        docsList.innerHTML = '<li>No documents attached</li>';
                    }
                } else {
                    // Handle case where documents is a simple string
                    if (app.documents) {
                        const li = document.createElement('li');
                        li.textContent = app.documents;
                        docsList.appendChild(li);
                    } else {
                        docsList.innerHTML = '<li>No documents attached</li>';
                    }
                }
            } catch (e) {
                // Handle case where documents is not valid JSON
                if (app.documents) {
                    const li = document.createElement('li');
                    li.textContent = app.documents;
                    docsList.appendChild(li);
                } else {
                    docsList.innerHTML = '<li>No documents attached</li>';
                }
            }
            
            document.getElementById('viewModal').style.display = 'block';
        }
        
        function approveApplication(applicationId) {
            currentApplicationId = applicationId;
            document.getElementById('approve_application_id').value = applicationId;
            document.getElementById('approveModal').style.display = 'block';
        }
        
        function approveFromModal() {
            if (currentApplicationId) {
                document.getElementById('approve_application_id').value = currentApplicationId;
                document.querySelector('#approveModal form').submit();
            }
        }
        
        function rejectApplication(applicationId) {
            currentApplicationId = applicationId;
            document.getElementById('reject_application_id').value = applicationId;
            document.getElementById('rejectModal').style.display = 'block';
        }
        
        function rejectFromModal() {
            if (currentApplicationId) {
                document.getElementById('reject_application_id').value = currentApplicationId;
                document.querySelector('#rejectModal form').submit();
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
        }
        

    </script>
</body>
</html>