<?php
session_start();
require_once '../config.php';
require_once '../auth.php';

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Location: ../login.php');
    exit();
}

// Check if user has admin role or course_registrations permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('course_registrations', $pdo)) {
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
                $registration_id = $_POST['registration_id'];
                
                try {
                    $stmt = $pdo->prepare("UPDATE course_registration SET status = 'approved' WHERE id = ?");
                    $stmt->execute([$registration_id]);
                    
                    $message = "Course registration approved successfully!";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'reject_registration':
                $registration_id = $_POST['registration_id'];
                $rejection_reason = trim($_POST['rejection_reason']);
                
                if (empty($rejection_reason)) {
                    $message = "Please provide a rejection reason!";
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE course_registration SET status = 'rejected', rejection_reason = ? WHERE id = ?");
                        $stmt->execute([$rejection_reason, $registration_id]);
                        
                        $message = "Course registration rejected!";
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'define_intake_courses':
                $intake_id = $_POST['intake_id'];
                $term = trim($_POST['term']);
                $programme_id = $_POST['programme_id'] ?? null; // New field (optional for backward compatibility)
                $course_ids = $_POST['course_ids'] ?? [];
                
                if (empty($intake_id) || empty($term)) {
                    $message = "Please select intake and term!";
                    $messageType = 'error';
                } else {
                    try {
                        // Check if programme_id column exists
                        $column_exists = false;
                        try {
                            $check_column = $pdo->query("SHOW COLUMNS FROM intake_courses LIKE 'programme_id'");
                            $column_exists = $check_column->rowCount() > 0;
                        } catch (Exception $e) {
                            $column_exists = false;
                        }
                        
                        if ($column_exists) {
                            // Delete existing courses for this intake/term (and programme if provided)
                            if (!empty($programme_id)) {
                                $delete_stmt = $pdo->prepare("DELETE FROM intake_courses WHERE intake_id = ? AND term = ? AND programme_id = ?");
                                $delete_stmt->execute([$intake_id, $term, $programme_id]);
                            } else {
                                $delete_stmt = $pdo->prepare("DELETE FROM intake_courses WHERE intake_id = ? AND term = ? AND (programme_id IS NULL OR programme_id = ?)");
                                $delete_stmt->execute([$intake_id, $term, 0]);
                            }
                            
                            // Add new courses
                            foreach ($course_ids as $course_id) {
                                $insert_stmt = $pdo->prepare("INSERT INTO intake_courses (intake_id, term, programme_id, course_id) VALUES (?, ?, ?, ?)");
                                $insert_stmt->execute([$intake_id, $term, $programme_id ?: null, $course_id]);
                            }
                        } else {
                            // Use old method without programme_id
                            $delete_stmt = $pdo->prepare("DELETE FROM intake_courses WHERE intake_id = ? AND term = ?");
                            $delete_stmt->execute([$intake_id, $term]);
                            
                            // Add new courses
                            foreach ($course_ids as $course_id) {
                                $insert_stmt = $pdo->prepare("INSERT INTO intake_courses (intake_id, term, course_id) VALUES (?, ?, ?)");
                                $insert_stmt->execute([$intake_id, $term, $course_id]);
                            }
                        }
                        
                        $message = "Courses defined for intake and term successfully!";
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
        }
    }
}

// Get pending registrations
$pending_query = "
    SELECT cr.*, sp.full_name, sp.student_number as student_id, c.name as course_name, i.name as intake_name
    FROM course_registration cr
    JOIN student_profile sp ON cr.student_id = sp.user_id
    JOIN course c ON cr.course_id = c.id
    JOIN intake i ON sp.intake_id = i.id
    WHERE cr.status = 'pending'
    ORDER BY cr.submitted_at DESC
";
$pending_registrations = $pdo->query($pending_query)->fetchAll();

// Get intakes for defining courses
$intakes = $pdo->query("SELECT * FROM intake ORDER BY start_date DESC")->fetchAll();

// Get all programmes for the dropdown
$programmes = $pdo->query("SELECT * FROM programme ORDER BY name")->fetchAll();

// Get all courses for selection
$courses = $pdo->query("SELECT * FROM course ORDER BY name")->fetchAll();

// Get defined intake courses (for display or edit) - handle both old and new schema
try {
    // Try to get data with programme information first
    $defined_courses_query = "
        SELECT ic.*, i.name as intake_name, c.name as course_name, p.name as programme_name
        FROM intake_courses ic
        JOIN intake i ON ic.intake_id = i.id
        JOIN course c ON ic.course_id = c.id
        LEFT JOIN programme p ON ic.programme_id = p.id
        ORDER BY i.name, ic.term, p.name, c.name
    ";
    $defined_courses = $pdo->query($defined_courses_query)->fetchAll();
} catch (Exception $e) {
    // Fall back to old query without programme information
    $defined_courses_query = "
        SELECT ic.*, i.name as intake_name, c.name as course_name, NULL as programme_name
        FROM intake_courses ic
        JOIN intake i ON ic.intake_id = i.id
        JOIN course c ON ic.course_id = c.id
        ORDER BY i.name, ic.term, c.name
    ";
    $defined_courses = $pdo->query($defined_courses_query)->fetchAll();
}

// Create necessary tables if not exist and update schema
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS course_registration (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        term VARCHAR(50) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        rejection_reason TEXT,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (course_id) REFERENCES course(id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS intake_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        intake_id INT NOT NULL,
        term VARCHAR(50) NOT NULL,
        course_id INT NOT NULL,
        FOREIGN KEY (intake_id) REFERENCES intake(id),
        FOREIGN KEY (course_id) REFERENCES course(id)
    )");
    
    // Try to add programme_id column if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE intake_courses ADD COLUMN programme_id INT");
        $pdo->exec("ALTER TABLE intake_courses ADD CONSTRAINT fk_intake_courses_programme FOREIGN KEY (programme_id) REFERENCES programme(id) ON DELETE SET NULL");
    } catch (Exception $e) {
        // Column might already exist, which is fine
    }
} catch (Exception $e) {
    // Tables might already exist
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Registrations - LSC SRMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                <h4>User Management</h4>
                <a href="manage_users.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Manage Users</span>
                </a>
                <a href="manage_roles.php" class="nav-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Roles & Permissions</span>
                </a>
                <a href="upload_users.php" class="nav-item">
                    <i class="fas fa-upload"></i>
                    <span>Bulk Upload</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Academic Structure</h4>
                <a href="manage_schools.php" class="nav-item">
                    <i class="fas fa-university"></i>
                    <span>Schools</span>
                </a>
                <a href="manage_departments.php" class="nav-item">
                    <i class="fas fa-building"></i>
                    <span>Departments</span>
                </a>
                <a href="manage_programmes.php" class="nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Programmes</span>
                </a>
                <a href="manage_courses.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <span>Courses</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Academic Operations</h4>
                <a href="manage_results.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Results Management</span>
                </a>
                <a href="enrollment_approvals.php" class="nav-item">
                    <i class="fas fa-user-check"></i>
                    <span>Enrollment Approvals</span>
                </a>
                <a href="course_registrations.php" class="nav-item active">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Course Registrations</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports & Analytics</h4>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <a href="analytics.php" class="nav-item">
                    <i class="fas fa-analytics"></i>
                    <span>Analytics</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-clipboard-check"></i> Course Registrations</h1>
            <p>Approve student course registrations and define required courses per intake and term</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Pending Registrations -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-clock"></i> Pending Registrations (<?php echo count($pending_registrations); ?>)</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($pending_registrations)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clock"></i>
                        <p>No pending registrations</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Course</th>
                                    <th>Intake</th>
                                    <th>Term</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_registrations as $reg): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reg['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['intake_name']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['term']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($reg['submitted_at'])); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Approve this registration?');">
                                                <input type="hidden" name="action" value="approve_registration">
                                                <input type="hidden" name="registration_id" value="<?php echo $reg['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Approve</button>
                                            </form>
                                            <button class="btn btn-sm btn-danger" onclick="openRejectModal(<?php echo $reg['id']; ?>)"><i class="fas fa-times"></i> Reject</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Define Intake Courses -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-list"></i> Define Required Courses per Intake and Term</h3>
            </div>
            <div class="panel-content">
                <form method="POST">
                    <input type="hidden" name="action" value="define_intake_courses">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="intake_id">Intake *</label>
                            <select id="intake_id" name="intake_id" required>
                                <option value="">-- Select Intake --</option>
                                <?php foreach ($intakes as $intake): ?>
                                    <option value="<?php echo $intake['id']; ?>"><?php echo htmlspecialchars($intake['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="term">Term *</label>
                            <input type="text" id="term" name="term" required placeholder="e.g., Semester 1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="programme_id">Programme</label>
                        <select id="programme_id" name="programme_id">
                            <option value="">-- Select Programme --</option>
                            <?php foreach ($programmes as $programme): ?>
                                <option value="<?php echo $programme['id']; ?>"><?php echo htmlspecialchars($programme['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Courses *</label>
                        <div class="checkbox-list">
                            <?php foreach ($courses as $course): ?>
                                <label>
                                    <input type="checkbox" name="course_ids[]" value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['name'] . ' (' . $course['code'] . ')'); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Courses</button>
                </form>
            </div>
        </div>

        <!-- Defined Courses -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-check-circle"></i> Defined Courses</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($defined_courses)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No courses defined</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Intake</th>
                                    <th>Term</th>
                                    <th>Programme</th>
                                    <th>Course</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($defined_courses as $dc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dc['intake_name']); ?></td>
                                        <td><?php echo htmlspecialchars($dc['term']); ?></td>
                                        <td><?php echo htmlspecialchars($dc['programme_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($dc['course_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('rejectModal')">&times;</span>
            <h2>Reject Registration</h2>
            <form method="POST">
                <input type="hidden" name="action" value="reject_registration">
                <input type="hidden" id="reject_registration_id" name="registration_id">
                <div class="form-group">
                    <label for="rejection_reason">Rejection Reason</label>
                    <textarea id="rejection_reason" name="rejection_reason" rows="3" required></textarea>
                </div>
                <button type="submit" class="btn btn-danger">Reject</button>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        function openRejectModal(id) {
            document.getElementById('reject_registration_id').value = id;
            document.getElementById('rejectModal').style.display = 'block';
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