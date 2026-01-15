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

// Handle form actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_session':
                $session_name = trim($_POST['session_name']);
                $academic_year = trim($_POST['academic_year']);
                $term = trim($_POST['term']);
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $status = $_POST['status'];
                
                // Validate inputs
                if (empty($session_name) || empty($academic_year) || empty($term) || empty($start_date) || empty($end_date)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } elseif (strtotime($start_date) > strtotime($end_date)) {
                    $message = "Start date cannot be after end date!";
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO academic_sessions (session_name, academic_year, term, start_date, end_date, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        if ($stmt->execute([$session_name, $academic_year, $term, $start_date, $end_date, $status])) {
                            $message = "Session added successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to add session!";
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;

            case 'update_session':
                $session_id = $_POST['session_id'];
                $session_name = trim($_POST['session_name']);
                $academic_year = trim($_POST['academic_year']);
                $term = trim($_POST['term']);
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $status = $_POST['status'];
                
                // Validate inputs
                if (empty($session_name) || empty($academic_year) || empty($term) || empty($start_date) || empty($end_date)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } elseif (strtotime($start_date) > strtotime($end_date)) {
                    $message = "Start date cannot be after end date!";
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE academic_sessions SET session_name = ?, academic_year = ?, term = ?, start_date = ?, end_date = ?, status = ?, updated_at = NOW() WHERE id = ?");
                        if ($stmt->execute([$session_name, $academic_year, $term, $start_date, $end_date, $status, $session_id])) {
                            $message = "Session updated successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to update session!";
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;

            case 'delete_session':
                $session_id = $_POST['session_id'];
                
                try {
                    // Check if session is being used in programme schedules
                    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM programme_schedule WHERE session_id = ?");
                    $check_stmt->execute([$session_id]);
                    $usage_count = $check_stmt->fetchColumn();
                    
                    if ($usage_count > 0) {
                        $message = "Cannot delete session. It is currently being used in {$usage_count} programme schedule(s)!";
                        $messageType = 'error';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM academic_sessions WHERE id = ?");
                        if ($stmt->execute([$session_id])) {
                            $message = "Session deleted successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to delete session!";
                            $messageType = 'error';
                        }
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;

            case 'add_programme_schedule':
                $programme_id = $_POST['programme_id'];
                $session_id = $_POST['session_id'];
                $intake_id = $_POST['intake_id'];
                $year_of_study = $_POST['year_of_study'];
                
                // Validate inputs
                if (empty($programme_id) || empty($session_id) || empty($intake_id) || empty($year_of_study)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO programme_schedule (programme_id, session_id, intake_id, year_of_study, created_at) VALUES (?, ?, ?, ?, NOW())");
                        if ($stmt->execute([$programme_id, $session_id, $intake_id, $year_of_study])) {
                            $message = "Programme schedule added successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to add programme schedule!";
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;

            case 'update_programme_schedule':
                $schedule_id = $_POST['schedule_id'];
                $programme_id = $_POST['programme_id'];
                $session_id = $_POST['session_id'];
                $intake_id = $_POST['intake_id'];
                $year_of_study = $_POST['year_of_study'];
                
                // Validate inputs
                if (empty($programme_id) || empty($session_id) || empty($intake_id) || empty($year_of_study)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE programme_schedule SET programme_id = ?, session_id = ?, intake_id = ?, year_of_study = ?, updated_at = NOW() WHERE id = ?");
                        if ($stmt->execute([$programme_id, $session_id, $intake_id, $year_of_study, $schedule_id])) {
                            $message = "Programme schedule updated successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to update programme schedule!";
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;

            case 'delete_programme_schedule':
                $schedule_id = $_POST['schedule_id'];
                
                try {
                    $stmt = $pdo->prepare("DELETE FROM programme_schedule WHERE id = ?");
                    if ($stmt->execute([$schedule_id])) {
                        $message = "Programme schedule deleted successfully!";
                        $messageType = 'success';
                    } else {
                        $message = "Failed to delete programme schedule!";
                        $messageType = 'error';
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get sessions
$sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY academic_year DESC, term ASC")->fetchAll();

// Get programmes
$programmes = $pdo->query("SELECT * FROM programme ORDER BY name ASC")->fetchAll();

// Get intakes
$intakes = $pdo->query("SELECT * FROM intake ORDER BY name ASC")->fetchAll();

// Get programme schedules with related data
$programme_schedules = $pdo->query("
    SELECT ps.*, p.name as programme_name, s.session_name, i.name as intake_name
    FROM programme_schedule ps
    LEFT JOIN programme p ON ps.programme_id = p.id
    LEFT JOIN academic_sessions s ON ps.session_id = s.id
    LEFT JOIN intake i ON ps.intake_id = i.id
    ORDER BY ps.year_of_study, p.name, s.academic_year, s.term
")->fetchAll();

// Get session for editing if specified
$editSession = null;
if (isset($_GET['edit_session'])) {
    $stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE id = ?");
    $stmt->execute([$_GET['edit_session']]);
    $editSession = $stmt->fetch();
}

// Get schedule for editing if specified
$editSchedule = null;
if (isset($_GET['edit_schedule'])) {
    $stmt = $pdo->prepare("
        SELECT ps.*, p.name as programme_name, s.session_name, i.name as intake_name
        FROM programme_schedule ps
        LEFT JOIN programme p ON ps.programme_id = p.id
        LEFT JOIN academic_sessions s ON ps.session_id = s.id
        LEFT JOIN intake i ON ps.intake_id = i.id
        WHERE ps.id = ?
    ");
    $stmt->execute([$_GET['edit_schedule']]);
    $editSchedule = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Management - LSC Management System</title>
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sessions-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .sessions-container {
                grid-template-columns: 1fr;
            }
        }

        .session-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #2E8B57;
        }

        .session-card.active {
            border-left-color: #4CAF50;
        }

        .session-card.inactive {
            border-left-color: #FF6B6B;
        }

        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .session-name {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
        }

        .session-details {
            margin-bottom: 10px;
        }

        .session-detail {
            margin: 5px 0;
            color: #666;
        }

        .schedule-table {
            margin-top: 20px;
        }

        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .form-section h3 {
            margin-top: 0;
            color: #2E8B57;
            border-bottom: 2px solid #2E8B57;
            padding-bottom: 10px;
        }

        .stats-grid.sessions-stats {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
    </style>
</head>
<body>
    <!-- Navigation and Sidebar -->
    <nav class="navbar">
        <div class="nav-brand">
            <a href="dashboard.php">
                <h2><i class="fas fa-university"></i> LSC Management System</h2>
            </a>
        </div>
        
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
    </nav>

    <div class="container">
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
                    <a href="manage_intakes.php" class="nav-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Intakes</span>
                    </a>
                    <a href="manage_sessions.php" class="nav-item active">
                        <i class="fas fa-clock"></i>
                        <span>Sessions</span>
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
                    <a href="course_registrations.php" class="nav-item">
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

        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-clock"></i> Session Management</h1>
                <p>Define academic sessions, terms, and programme schedules</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid sessions-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count($sessions); ?></h3>
                        <p>Total Sessions</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count($programme_schedules); ?></h3>
                        <p>Programme Schedules</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count($programmes); ?></h3>
                        <p>Programmes</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count($intakes); ?></h3>
                        <p>Intakes</p>
                    </div>
                </div>
            </div>

            <!-- Navigation Actions -->
            <div class="action-bar">
                <div class="action-left">
                    <button onclick="showSection('add-session')" class="btn btn-green">
                        <i class="fas fa-plus"></i> Add New Session
                    </button>
                    <button onclick="showSection('add-schedule')" class="btn btn-blue">
                        <i class="fas fa-calendar-plus"></i> Add Programme Schedule
                    </button>
                </div>
            </div>

            <!-- Add/Edit Session Form -->
            <div class="form-section" id="add-session-form" style="display: <?php echo $editSession ? 'block' : 'none'; ?>;">
                <h3>
                    <i class="fas fa-<?php echo $editSession ? 'edit' : 'plus'; ?>"></i>
                    <?php echo $editSession ? 'Edit Session' : 'Add New Session'; ?>
                </h3>
                
                <form method="POST" class="school-form">
                    <input type="hidden" name="action" value="<?php echo $editSession ? 'update_session' : 'add_session'; ?>">
                    <?php if ($editSession): ?>
                        <input type="hidden" name="session_id" value="<?php echo $editSession['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="session_name">Session Name *</label>
                            <input type="text" id="session_name" name="session_name" required maxlength="100" 
                                   value="<?php echo htmlspecialchars($editSession['session_name'] ?? ''); ?>" 
                                   placeholder="e.g. First Semester, Term 1">
                        </div>
                        
                        <div class="form-group">
                            <label for="academic_year">Academic Year *</label>
                            <select id="academic_year" name="academic_year" required>
                                <option value="">-- Select Academic Year --</option>
                                <?php 
                                $current_year = date('Y');
                                for ($i = $current_year - 5; $i <= $current_year + 5; $i++) {
                                    $year_range = $i . '/' . ($i + 1);
                                    $selected = (isset($editSession['academic_year']) && $editSession['academic_year'] == $year_range) ? 'selected' : '';
                                    echo "<option value='{$year_range}' {$selected}>{$year_range}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="term">Term/Session *</label>
                            <select id="term" name="term" required>
                                <option value="">-- Select Term --</option>
                                <option value="Term 1" <?php echo (isset($editSession['term']) && $editSession['term'] == 'Term 1') ? 'selected' : ''; ?>>Term 1</option>
                                <option value="Term 2" <?php echo (isset($editSession['term']) && $editSession['term'] == 'Term 2') ? 'selected' : ''; ?>>Term 2</option>
                                <option value="Term 3" <?php echo (isset($editSession['term']) && $editSession['term'] == 'Term 3') ? 'selected' : ''; ?>>Term 3 (Summer)</option>
                                <option value="Semester 1" <?php echo (isset($editSession['term']) && $editSession['term'] == 'Semester 1') ? 'selected' : ''; ?>>Semester 1</option>
                                <option value="Semester 2" <?php echo (isset($editSession['term']) && $editSession['term'] == 'Semester 2') ? 'selected' : ''; ?>>Semester 2</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select id="status" name="status" required>
                                <option value="active" <?php echo (isset($editSession['status']) && $editSession['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (isset($editSession['status']) && $editSession['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date *</label>
                            <input type="date" id="start_date" name="start_date" required 
                                   value="<?php echo htmlspecialchars($editSession['start_date'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="end_date">End Date *</label>
                            <input type="date" id="end_date" name="end_date" required 
                                   value="<?php echo htmlspecialchars($editSession['end_date'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-green">
                            <i class="fas fa-save"></i> <?php echo $editSession ? 'Update Session' : 'Create Session'; ?>
                        </button>
                        <button type="button" onclick="hideSection('add-session')" class="btn btn-orange">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Add/Edit Programme Schedule Form -->
            <div class="form-section" id="add-schedule-form" style="display: <?php echo $editSchedule ? 'block' : 'none'; ?>;">
                <h3>
                    <i class="fas fa-<?php echo $editSchedule ? 'edit' : 'calendar-plus'; ?>"></i>
                    <?php echo $editSchedule ? 'Edit Programme Schedule' : 'Add Programme Schedule'; ?>
                </h3>
                
                <form method="POST" class="school-form">
                    <input type="hidden" name="action" value="<?php echo $editSchedule ? 'update_programme_schedule' : 'add_programme_schedule'; ?>">
                    <?php if ($editSchedule): ?>
                        <input type="hidden" name="schedule_id" value="<?php echo $editSchedule['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="programme_id">Programme *</label>
                            <select id="programme_id" name="programme_id" required>
                                <option value="">-- Select Programme --</option>
                                <?php foreach ($programmes as $programme): ?>
                                    <option value="<?php echo $programme['id']; ?>" 
                                            <?php echo (isset($editSchedule['programme_id']) && $editSchedule['programme_id'] == $programme['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($programme['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="session_id">Session *</label>
                            <select id="session_id" name="session_id" required>
                                <option value="">-- Select Session --</option>
                                <?php foreach ($sessions as $session): ?>
                                    <option value="<?php echo $session['id']; ?>" 
                                            <?php echo (isset($editSchedule['session_id']) && $editSchedule['session_id'] == $session['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($session['session_name'] . ' (' . $session['academic_year'] . ' - ' . $session['term'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="intake_id">Intake *</label>
                            <select id="intake_id" name="intake_id" required>
                                <option value="">-- Select Intake --</option>
                                <?php foreach ($intakes as $intake): ?>
                                    <option value="<?php echo $intake['id']; ?>" 
                                            <?php echo (isset($editSchedule['intake_id']) && $editSchedule['intake_id'] == $intake['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($intake['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="year_of_study">Year of Study *</label>
                            <select id="year_of_study" name="year_of_study" required>
                                <option value="">-- Select Year --</option>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?php echo $i; ?>" 
                                            <?php echo (isset($editSchedule['year_of_study']) && $editSchedule['year_of_study'] == $i) ? 'selected' : ''; ?>>
                                        Year <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-green">
                            <i class="fas fa-save"></i> <?php echo $editSchedule ? 'Update Schedule' : 'Add Schedule'; ?>
                        </button>
                        <button type="button" onclick="hideSection('add-schedule')" class="btn btn-orange">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Sessions List -->
            <div class="section-card">
                <div class="section-header">
                    <h3><i class="fas fa-calendar-alt"></i> Academic Sessions</h3>
                </div>
                
                <?php if (empty($sessions)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-alt"></i>
                        <h4>No Sessions Defined</h4>
                        <p>No academic sessions have been created yet.</p>
                        <button onclick="showSection('add-session')" class="btn btn-green">
                            <i class="fas fa-plus"></i> Add First Session
                        </button>
                    </div>
                <?php else: ?>
                    <div class="sessions-container">
                        <?php foreach ($sessions as $session): ?>
                            <div class="session-card <?php echo $session['status']; ?>">
                                <div class="session-header">
                                    <div class="session-name">
                                        <?php echo htmlspecialchars($session['session_name']); ?>
                                    </div>
                                    <div class="session-actions">
                                        <a href="?edit_session=<?php echo $session['id']; ?>" class="btn-icon btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this session?');">
                                            <input type="hidden" name="action" value="delete_session">
                                            <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                            <button type="submit" class="btn-icon btn-delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="session-details">
                                    <div class="session-detail">
                                        <strong>Academic Year:</strong> <?php echo htmlspecialchars($session['academic_year']); ?>
                                    </div>
                                    <div class="session-detail">
                                        <strong>Term:</strong> <?php echo htmlspecialchars($session['term']); ?>
                                    </div>
                                    <div class="session-detail">
                                        <strong>Dates:</strong> <?php echo date('M d, Y', strtotime($session['start_date'])); ?> - <?php echo date('M d, Y', strtotime($session['end_date'])); ?>
                                    </div>
                                    <div class="session-detail">
                                        <strong>Status:</strong> 
                                        <span class="status-badge <?php echo $session['status']; ?>">
                                            <?php echo ucfirst($session['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Programme Schedules -->
            <div class="section-card">
                <div class="section-header">
                    <h3><i class="fas fa-list-alt"></i> Programme Schedules</h3>
                </div>
                
                <?php if (empty($programme_schedules)): ?>
                    <div class="empty-state">
                        <i class="fas fa-list-alt"></i>
                        <h4>No Programme Schedules</h4>
                        <p>No programme schedules have been defined yet.</p>
                        <button onclick="showSection('add-schedule')" class="btn btn-blue">
                            <i class="fas fa-calendar-plus"></i> Add First Schedule
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="users-table schedule-table">
                            <thead>
                                <tr>
                                    <th>Programme</th>
                                    <th>Session</th>
                                    <th>Intake</th>
                                    <th>Year of Study</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programme_schedules as $schedule): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($schedule['programme_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['session_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['intake_name'] ?? 'N/A'); ?></td>
                                        <td>Year <?php echo htmlspecialchars($schedule['year_of_study']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($schedule['created_at'])); ?></td>
                                        <td>
                                            <a href="?edit_schedule=<?php echo $schedule['id']; ?>" class="btn-icon btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this schedule?');">
                                                <input type="hidden" name="action" value="delete_programme_schedule">
                                                <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
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
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function toggleTheme() {
            document.body.classList.toggle('dark-mode');
            const icon = document.getElementById('theme-icon');
            icon.className = document.body.classList.contains('dark-mode') ? 'fas fa-sun' : 'fas fa-moon';
        }

        function toggleDropdown() {
            document.getElementById('profileDropdown').classList.toggle('show');
        }

        function showSection(section) {
            // Hide all sections
            document.getElementById('add-session-form').style.display = 'none';
            document.getElementById('add-schedule-form').style.display = 'none';
            
            // Show selected section
            if (section === 'add-session') {
                document.getElementById('add-session-form').style.display = 'block';
            } else if (section === 'add-schedule') {
                document.getElementById('add-schedule-form').style.display = 'block';
            }
        }

        function hideSection(section) {
            if (section === 'add-session') {
                document.getElementById('add-session-form').style.display = 'none';
            } else if (section === 'add-schedule') {
                document.getElementById('add-schedule-form').style.display = 'none';
            }
        }

        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.profile-btn') && !event.target.matches('.profile-btn *')) {
                var dropdowns = document.getElementsByClassName("dropdown-menu");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            for (var i = 0; i < alerts.length; i++) {
                alerts[i].style.display = 'none';
            }
        }, 5000);
    </script>
</body>
</html>