<?php
// Suppress errors to prevent HTML warnings from breaking JS
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
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
try {
    $sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY academic_year DESC, term ASC")->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching sessions: " . $e->getMessage());
    $sessions = [];
}

// Get programmes
try {
    $programmes = $pdo->query("SELECT * FROM programme ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching programmes: " . $e->getMessage());
    $programmes = [];
}

// Get intakes
try {
    $intakes = $pdo->query("SELECT * FROM intake ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching intakes: " . $e->getMessage());
    $intakes = [];
}

// Get programme schedules with related data
try {
    $programme_schedules = $pdo->query("
        SELECT ps.*, 
               p.name as programme_name, 
               s.session_name, s.academic_year, s.term, s.status as session_status,
               i.name as intake_name
        FROM programme_schedule ps
        LEFT JOIN programme p ON ps.programme_id = p.id
        LEFT JOIN academic_sessions s ON ps.session_id = s.id
        LEFT JOIN intake i ON ps.intake_id = i.id
        ORDER BY ps.year_of_study, p.name, s.academic_year, s.term
    ")->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching programme schedules: " . $e->getMessage());
    $programme_schedules = []; // Provide empty array as fallback
}

// Get courses associated with each session through programme_schedule and intake_courses
$courses_by_session = [];
$sessions_result = $pdo->query("
    SELECT s.id as session_id, s.session_name, s.academic_year, s.term, 
           p.name as programme_name, ic.course_id, c.name as course_name, c.code as course_code
    FROM academic_sessions s
    LEFT JOIN programme_schedule ps ON s.id = ps.session_id
    LEFT JOIN programme p ON ps.programme_id = p.id
    LEFT JOIN intake_courses ic ON p.id = ic.programme_id
    LEFT JOIN course c ON ic.course_id = c.id
    WHERE c.id IS NOT NULL
    ORDER BY s.id, p.name, c.name
")->fetchAll();

foreach ($sessions_result as $row) {
    $session_id = $row['session_id'];
    if (!isset($courses_by_session[$session_id])) {
        $courses_by_session[$session_id] = [
            'session_info' => [
                'session_name' => $row['session_name'],
                'academic_year' => $row['academic_year'],
                'term' => $row['term']
            ],
            'courses' => []
        ];
    }
    
    if ($row['course_id']) {
        $courses_by_session[$session_id]['courses'][] = [
            'course_id' => $row['course_id'],
            'course_name' => $row['course_name'],
            'course_code' => $row['course_code'],
            'programme_name' => $row['programme_name']
        ];
    }
}

// Get sessions with programme details
$sessions_with_details = $pdo->query("
    SELECT s.*, 
           COUNT(ps.id) as schedule_count,
           GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') as programmes
    FROM academic_sessions s
    LEFT JOIN programme_schedule ps ON s.id = ps.session_id
    LEFT JOIN programme p ON ps.programme_id = p.id
    GROUP BY s.id
    ORDER BY s.academic_year DESC, s.term ASC
")->fetchAll();

// Get programmes with session details
$programmes_with_sessions = $pdo->query("
    SELECT p.*, 
           COUNT(ps.id) as session_count,
           GROUP_CONCAT(DISTINCT s.session_name SEPARATOR ', ') as active_sessions
    FROM programme p
    LEFT JOIN programme_schedule ps ON p.id = ps.programme_id
    LEFT JOIN academic_sessions s ON ps.session_id = s.id
    GROUP BY p.id
    ORDER BY p.name
")->fetchAll();

// Get intakes with programme details
$intakes_with_programmes = $pdo->query("
    SELECT i.*,
           COUNT(ps.id) as programme_count,
           GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') as programmes
    FROM intake i
    LEFT JOIN programme_schedule ps ON i.id = ps.intake_id
    LEFT JOIN programme p ON ps.programme_id = p.id
    GROUP BY i.id
    ORDER BY i.name
")->fetchAll();

// Ensure all intakes have a status field (some might not have it in the database)
foreach ($intakes_with_programmes as &$intake) {
    if (!isset($intake['status']) || is_null($intake['status'])) {
        $intake['status'] = 'active'; // Default to 'active' if not set or null
    }
}

// Handle AJAX requests for course assignment (per session + programme)
if (isset($_POST['action']) && $_POST['action'] === 'assign_courses_to_session_programme') {
    header('Content-Type: application/json');

    $session_id = (int)($_POST['session_id'] ?? 0);
    $programme_id = (int)($_POST['programme_id'] ?? 0);
    $course_ids = $_POST['course_ids'] ?? [];

    if ($session_id <= 0 || $programme_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Session ID and Programme ID are required']);
        exit;
    }

    // normalize course ids
    if (!is_array($course_ids)) {
        $course_ids = [$course_ids];
    }
    $course_ids = array_values(array_filter(array_map('intval', $course_ids), fn($id) => $id > 0));

    if (empty($course_ids)) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one course']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Ensure the programme is scheduled in the given session
        $check = $pdo->prepare("SELECT COUNT(*) FROM programme_schedule WHERE session_id = ? AND programme_id = ?");
        $check->execute([$session_id, $programme_id]);
        if ((int)$check->fetchColumn() === 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Selected programme is not scheduled under this session']);
            exit;
        }

        // Replace previous selections for this session+programme (idempotent UX)
        $del = $pdo->prepare("DELETE FROM session_programme_courses WHERE session_id = ? AND programme_id = ?");
        $del->execute([$session_id, $programme_id]);

        $ins = $pdo->prepare("INSERT INTO session_programme_courses (session_id, programme_id, course_id) VALUES (?, ?, ?)");
        foreach ($course_ids as $course_id) {
            $ins->execute([$session_id, $programme_id, $course_id]);
        }

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Courses saved for the selected programme in this session',
            'session_id' => $session_id,
            'programme_id' => $programme_id,
            'assigned_count' => count($course_ids)
        ]);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error saving courses: ' . $e->getMessage()]);
        exit;
    }
}

// AJAX: programmes scheduled in a session
if (isset($_GET['action']) && $_GET['action'] === 'get_session_programmes') {
    header('Content-Type: application/json');
    $session_id = (int)($_GET['session_id'] ?? 0);

    if ($session_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid session id']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id, p.name
        FROM programme_schedule ps
        JOIN programme p ON ps.programme_id = p.id
        WHERE ps.session_id = ?
        ORDER BY p.name
    ");
    $stmt->execute([$session_id]);

    echo json_encode([
        'success' => true,
        'session_id' => $session_id,
        'programmes' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
    exit;
}

// AJAX: courses for session + programme (with assignment state)
if (isset($_GET['action']) && $_GET['action'] === 'get_session_programme_courses') {
    header('Content-Type: application/json');
    $session_id = (int)($_GET['session_id'] ?? 0);
    $programme_id = (int)($_GET['programme_id'] ?? 0);

    if ($session_id <= 0 || $programme_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Session ID and Programme ID are required']);
        exit;
    }

    // Validate programme is scheduled for the session
    $check = $pdo->prepare("SELECT COUNT(*) FROM programme_schedule WHERE session_id = ? AND programme_id = ?");
    $check->execute([$session_id, $programme_id]);
    if ((int)$check->fetchColumn() === 0) {
        echo json_encode(['success' => false, 'message' => 'Programme not scheduled for this session']);
        exit;
    }

    // Fetch all courses mapped to this programme
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.code, c.name
        FROM intake_courses ic
        JOIN course c ON ic.course_id = c.id
        WHERE ic.programme_id = ?
        ORDER BY c.name
    ");
    $stmt->execute([$programme_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Assigned course IDs for this session+programme
    $a = $pdo->prepare("SELECT course_id FROM session_programme_courses WHERE session_id = ? AND programme_id = ?");
    $a->execute([$session_id, $programme_id]);
    $assigned = array_map('intval', $a->fetchAll(PDO::FETCH_COLUMN));
    $assignedSet = array_flip($assigned);

    $courses = array_map(function($c) use ($assignedSet) {
        $c['assigned'] = isset($assignedSet[(int)$c['id']]);
        return $c;
    }, $courses);

    echo json_encode([
        'success' => true,
        'session_id' => $session_id,
        'programme_id' => $programme_id,
        'courses' => $courses
    ]);
    exit;
}

// Handle AJAX requests for getting card data
if (isset($_GET['action']) && $_GET['action'] === 'get_card_data') {
    $type = $_GET['type'] ?? '';
    
    $response = ['success' => false, 'message' => 'Invalid type'];
    
    switch($type) {
        case 'sessions':
            $response = [
                'success' => true,
                'sessions' => array_map(function($session) {
                    return [
                        'id' => $session['id'],
                        'session_name' => $session['session_name'],
                        'academic_year' => $session['academic_year'],
                        'term' => $session['term'],
                        'start_date' => $session['start_date'],
                        'end_date' => $session['end_date'],
                        'status' => $session['status'],
                        'programmes' => $session['programmes'] ?: 'None'
                    ];
                }, $sessions_with_details)
            ];
            break;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Check and create session_programme_courses table if it doesn't exist (used for selecting courses per programme per session)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS session_programme_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        programme_id INT NOT NULL,
        course_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (programme_id) REFERENCES programme(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE,
        UNIQUE KEY unique_session_programme_course (session_id, programme_id, course_id)
    )");
} catch (Exception $e) {
    // Table creation failed, log the error if needed
    error_log("Could not create session_programme_courses table: " . $e->getMessage());
}

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
        
        .course-selection-modal {
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .course-selection-modal h4 {
            margin-top: 0;
            color: #2E8B57;
            border-bottom: 2px solid #2E8B57;
            padding-bottom: 10px;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            cursor: pointer;
        }
        
        .stat-card {
            transition: all 0.3s ease;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #666;
        }
    </style>
</head>
<body class="admin-layout" data-theme="light">
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
                <div class="stat-card" onclick="showCardDetails('sessions')" style="cursor: pointer;">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count($sessions_with_details); ?></h3>
                        <p>Total Sessions</p>
                    </div>
                </div>
                
                <div class="stat-card" onclick="showCardDetails('schedules')" style="cursor: pointer;">
                    <div class="stat-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count($programme_schedules); ?></h3>
                        <p>Programme Schedules</p>
                    </div>
                </div>
                
                <div class="stat-card" onclick="showCardDetails('programmes')" style="cursor: pointer;">
                    <div class="stat-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count($programmes_with_sessions); ?></h3>
                        <p>Programmes</p>
                    </div>
                </div>
                
                <div class="stat-card" onclick="showCardDetails('intakes')" style="cursor: pointer;">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count($intakes_with_programmes); ?></h3>
                        <p>Intakes</p>
                    </div>
                </div>
            </div>
            
            <!-- Card Details Section -->
            <div id="card-details-section" class="section-card" style="display: none; margin-top: 20px;">
                <div class="section-header">
                    <h3 id="details-title"><i class="fas fa-info-circle"></i> <span id="details-type">Details</span></h3>
                    <button onclick="hideCardDetails()" class="btn btn-orange"><i class="fas fa-times"></i> Close</button>
                </div>
                
                <div id="details-content">
                    <!-- Dynamic content will be loaded here -->
                </div>
            </div>

            <!-- Navigation Actions -->
            <div class="action-bar">
                <div class="action-left">
                    <button onclick="showSection('add-session')" class="btn btn-green">
                        <i class="fas fa-plus"></i> Add New Session
                    </button>
                    <button onclick="showSection('add-schedule')" class="btn btn-green">
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
        // Safely embed server-side data into JS (prevents JS syntax errors from unescaped DB text)
        const CARD_DATA = {
            sessions: <?php echo json_encode(array_map(function($s) {
                return [
                    'id' => (int)$s['id'],
                    'session_name' => $s['session_name'],
                    'academic_year' => $s['academic_year'],
                    'term' => $s['term'],
                    'start_date' => $s['start_date'],
                    'end_date' => $s['end_date'],
                    'status' => $s['status'],
                    'programmes' => $s['programmes'] ?: 'None',
                ];
            }, $sessions_with_details), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            schedules: <?php echo json_encode(array_map(function($s) {
                return [
                    'programme_name' => $s['programme_name'] ?? 'N/A',
                    'session_name' => $s['session_name'] ?? 'N/A',
                    'academic_year' => $s['academic_year'] ?? 'N/A',
                    'term' => $s['term'] ?? 'N/A',
                    'year_of_study' => $s['year_of_study'],
                    'intake_name' => $s['intake_name'] ?? 'N/A',
                    'session_status' => $s['session_status'] ?? 'inactive',
                ];
            }, $programme_schedules), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            programmes: <?php echo json_encode(array_map(function($p) {
                return [
                    'name' => $p['name'],
                    'department' => $p['department'] ?? 'N/A',
                    'school' => $p['school'] ?? 'N/A',
                    'duration' => $p['duration'] ?? 'N/A',
                    'session_count' => (int)$p['session_count'],
                    'active_sessions' => $p['active_sessions'] ?? 'None',
                ];
            }, $programmes_with_sessions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            intakes: <?php echo json_encode(array_map(function($i) {
                return [
                    'name' => $i['name'],
                    'start_date' => $i['start_date'],
                    'end_date' => $i['end_date'],
                    'status' => $i['status'],
                    'programme_count' => (int)$i['programme_count'],
                    'programmes' => $i['programmes'] ?? 'None',
                ];
            }, $intakes_with_programmes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        };

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

        function showCardDetails(type) {
            document.getElementById('details-type').textContent = 
                type.charAt(0).toUpperCase() + type.slice(1) + ' Details';
                    
            let content = '';
                    
            switch(type) {
                case 'sessions':
                    content = '<div class="table-responsive">' +
                        '<table class="users-table">' +
                        '<thead>' +
                            '<tr>' +
                                '<th>Session Name</th>' +
                                '<th>Academic Year</th>' +
                                '<th>Term</th>' +
                                '<th>Dates</th>' +
                                '<th>Status</th>' +
                                '<th>Programmes</th>' +
                                '<th>Courses (per Programme)</th>' +
                            '</tr>' +
                        '</thead>' +
                        '<tbody>';

                    (CARD_DATA.sessions || []).forEach(s => {
                        const dates = `${formatDate(s.start_date)} - ${formatDate(s.end_date)}`;
                        content += '<tr>' +
                            `<td>${escapeHtml(s.session_name)}</td>` +
                            `<td>${escapeHtml(s.academic_year)}</td>` +
                            `<td>${escapeHtml(s.term)}</td>` +
                            `<td>${escapeHtml(dates)}</td>` +
                            `<td><span class="status-badge ${escapeHtml(s.status)}">${escapeHtml(capitalize(s.status))}</span></td>` +
                            `<td>${escapeHtml(s.programmes)}</td>` +
                            '<td>' +
                                `<button onclick="showSessionCoursePicker(${Number(s.id)})" class="btn btn-sm btn-blue">` +
                                    '<i class="fas fa-book"></i> Manage Courses' +
                                '</button>' +
                            '</td>' +
                        '</tr>';
                    });
                            
                    content += '</tbody>' +
                        '</table>' +
                    '</div>';
                    break;
                            
                case 'schedules':
                    content = '<div class="table-responsive">' +
                        '<table class="users-table">' +
                        '<thead>' +
                            '<tr>' +
                                '<th>Programme</th>' +
                                '<th>Session</th>' +
                                '<th>Academic Year</th>' +
                                '<th>Term</th>' +
                                '<th>Year of Study</th>' +
                                '<th>Intake</th>' +
                                '<th>Status</th>' +
                            '</tr>' +
                        '</thead>' +
                        '<tbody>';

                    (CARD_DATA.schedules || []).forEach(s => {
                        const status = s.session_status || 'inactive';
                        content += '<tr>' +
                            `<td>${escapeHtml(s.programme_name)}</td>` +
                            `<td>${escapeHtml(s.session_name)}</td>` +
                            `<td>${escapeHtml(s.academic_year)}</td>` +
                            `<td>${escapeHtml(s.term)}</td>` +
                            `<td>Year ${escapeHtml(s.year_of_study)}</td>` +
                            `<td>${escapeHtml(s.intake_name)}</td>` +
                            `<td><span class="status-badge ${escapeHtml(status)}">${escapeHtml(capitalize(status))}</span></td>` +
                        '</tr>';
                    });
                            
                    content += '</tbody>' +
                        '</table>' +
                    '</div>';
                    break;
                            
                case 'programmes':
                    content = '<div class="table-responsive">' +
                        '<table class="users-table">' +
                        '<thead>' +
                            '<tr>' +
                                '<th>Programme Name</th>' +
                                '<th>Department</th>' +
                                '<th>School</th>' +
                                '<th>Duration</th>' +
                                '<th>Schedule Count</th>' +
                                '<th>Active Sessions</th>' +
                            '</tr>' +
                        '</thead>' +
                        '<tbody>';

                    (CARD_DATA.programmes || []).forEach(p => {
                        content += '<tr>' +
                            `<td>${escapeHtml(p.name)}</td>` +
                            `<td>${escapeHtml(p.department)}</td>` +
                            `<td>${escapeHtml(p.school)}</td>` +
                            `<td>${escapeHtml(p.duration)} years</td>` +
                            `<td>${escapeHtml(p.session_count)}</td>` +
                            `<td>${escapeHtml(p.active_sessions)}</td>` +
                        '</tr>';
                    });
                            
                    content += '</tbody>' +
                        '</table>' +
                    '</div>';
                    break;
                            
                case 'intakes':
                    content = '<div class="table-responsive">' +
                        '<table class="users-table">' +
                        '<thead>' +
                            '<tr>' +
                                '<th>Intake Name</th>' +
                                '<th>Start Date</th>' +
                                '<th>End Date</th>' +
                                '<th>Status</th>' +
                                '<th>Programme Count</th>' +
                                '<th>Programmes</th>' +
                            '</tr>' +
                        '</thead>' +
                        '<tbody>';

                    (CARD_DATA.intakes || []).forEach(i => {
                        const status = i.status || 'inactive';
                        content += '<tr>' +
                            `<td>${escapeHtml(i.name)}</td>` +
                            `<td>${escapeHtml(formatDate(i.start_date))}</td>` +
                            `<td>${escapeHtml(formatDate(i.end_date))}</td>` +
                            `<td><span class="status-badge ${escapeHtml(status)}">${escapeHtml(capitalize(status))}</span></td>` +
                            `<td>${escapeHtml(i.programme_count)}</td>` +
                            `<td>${escapeHtml(i.programmes)}</td>` +
                        '</tr>';
                    });
                            
                    content += '</tbody>' +
                        '</table>' +
                    '</div>';
                    break;
            }
                    
            document.getElementById('details-content').innerHTML = content;
            document.getElementById('card-details-section').style.display = 'block';
            document.getElementById('card-details-section').scrollIntoView({behavior: 'smooth'});
        }

        function hideCardDetails() {
            document.getElementById('card-details-section').style.display = 'none';
        }

        function showSessionCoursePicker(sessionId) {
            document.getElementById('details-content').innerHTML = '<div class="loading">Loading programmes...</div>';

            fetch(`?action=get_session_programmes&session_id=${sessionId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        document.getElementById('details-content').innerHTML = `<p>Error: ${data.message || 'Unable to load programmes.'}</p>`;
                        return;
                    }

                    const programmes = data.programmes || [];
                    let html = '<div class="course-selection-modal">' +
                        '<h4>Courses to Run (choose Programme)</h4>';

                    if (programmes.length === 0) {
                        html += '<p>No programmes are scheduled under this session yet. Add a programme schedule first.</p>';
                        html += '<button class="btn btn-orange" onclick="closeCourseSelection()">Close</button></div>';
                        document.getElementById('details-content').innerHTML = html;
                        return;
                    }

                    html += `<div class="form-row" style="margin-bottom: 12px;">
                                <div class="form-group" style="max-width: 520px;">
                                    <label for="coursePickerProgramme">Programme</label>
                                    <select id="coursePickerProgramme" onchange="loadProgrammeCoursesForSession(${sessionId})">
                                        <option value="">-- Select Programme --</option>
                                        ${programmes.map(p => `<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('')}
                                    </select>
                                </div>
                             </div>
                             <div id="coursePickerCourses" class="loading">Select a programme to load its courses...</div>
                             <div style="margin-top: 12px; display:flex; gap:10px; flex-wrap:wrap;">
                                <button class="btn btn-orange" onclick="closeCourseSelection()">Close</button>
                             </div>
                         </div>`;

                    document.getElementById('details-content').innerHTML = html;
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('details-content').innerHTML = '<p>Error loading programmes.</p>';
                });
        }

        function loadProgrammeCoursesForSession(sessionId) {
            const programmeId = document.getElementById('coursePickerProgramme').value;
            const container = document.getElementById('coursePickerCourses');

            if (!programmeId) {
                container.innerHTML = '<div class="loading">Select a programme to load its courses...</div>';
                return;
            }

            container.innerHTML = '<div class="loading">Loading courses...</div>';
            fetch(`?action=get_session_programme_courses&session_id=${sessionId}&programme_id=${programmeId}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        container.innerHTML = `<p>Error: ${data.message || 'Unable to load courses.'}</p>`;
                        return;
                    }

                    const courses = data.courses || [];
                    if (courses.length === 0) {
                        container.innerHTML = '<p>No courses found for this programme.</p>';
                        return;
                    }

                    let html = '<table class="users-table">' +
                        '<thead><tr><th>Select</th><th>Code</th><th>Course Name</th></tr></thead><tbody>';

                    courses.forEach(c => {
                        html += `<tr>
                            <td><input type="checkbox" name="selected_courses[]" value="${c.id}" ${c.assigned ? 'checked' : ''}></td>
                            <td>${escapeHtml(c.code || '')}</td>
                            <td>${escapeHtml(c.name || '')}</td>
                        </tr>`;
                    });

                    html += '</tbody></table>';
                    html += `<div style="margin-top: 12px; display:flex; gap:10px; flex-wrap:wrap;">
                                <button class="btn btn-green" onclick="saveProgrammeCoursesForSession(${sessionId}, ${programmeId})">
                                    <i class="fas fa-save"></i> Save Selected Courses
                                </button>
                             </div>`;

                    container.innerHTML = html;
                })
                .catch(err => {
                    console.error(err);
                    container.innerHTML = '<p>Error loading courses.</p>';
                });
        }

        function showProgrammeSessions(programmeId) {
            // This function would typically make an AJAX call to fetch sessions for the programme
            alert('Showing sessions for programme ID: ' + programmeId);
        }

        function showIntakeProgrammes(intakeId) {
            // This function would typically make an AJAX call to fetch programmes for the intake
            alert('Showing programmes for intake ID: ' + intakeId);
        }
        
        function saveProgrammeCoursesForSession(sessionId, programmeId) {
            const checkboxes = document.querySelectorAll('input[name="selected_courses[]"]:checked');
            const selectedCourses = Array.from(checkboxes).map(cb => cb.value);
            
            if (selectedCourses.length === 0) {
                alert('Please select at least one course.');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'assign_courses_to_session_programme');
            formData.append('session_id', sessionId);
            formData.append('programme_id', programmeId);
            selectedCourses.forEach((courseId) => formData.append('course_ids[]', courseId));
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    // Reload the picker content to reflect saved state
                    loadProgrammeCoursesForSession(sessionId);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving courses.');
            });
        }
        
        function closeCourseSelection() {
            // Refresh the current view to show updated information
            showCardDetails('sessions');
        }

        function capitalize(str) {
            const s = String(str || '');
            return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
        }

        function formatDate(dateStr) {
            if (!dateStr) return '';
            // If it's already a friendly date, keep it; otherwise try to format YYYY-MM-DD.
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return String(dateStr);
            return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: '2-digit' });
        }

        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
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