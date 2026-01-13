<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

// Check if user has Academics Coordinator role
requireRole('Academics Coordinator', $pdo);

// Get admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Handle form submission for publishing results
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_results'])) {
    $academic_year = trim($_POST['academic_year']);
    $semester = trim($_POST['semester']);
    $programme_id = intval($_POST['programme_id']);
    $course_id = intval($_POST['course_id']);
    $publish_date = trim($_POST['publish_date']);
    $deadline_date = trim($_POST['deadline_date']);
    $description = trim($_POST['description']);
    
    // Validation
    if (empty($academic_year) || empty($semester) || empty($programme_id) || empty($course_id) || empty($publish_date)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } elseif (strtotime($publish_date) < time()) {
        $message = 'Publish date must be in the future.';
        $message_type = 'error';
    } else {
        try {
            // Insert publish schedule record
            $stmt = $pdo->prepare("
                INSERT INTO result_publishing 
                (academic_year, semester, programme_id, course_id, publish_date, deadline_date, description, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $academic_year, $semester, $programme_id, $course_id, $publish_date, $deadline_date, $description, currentUserId()
            ]);
            
            $message = 'Results publishing schedule created successfully!';
            $message_type = 'success';
            
            // Clear form values
            $academic_year = $semester = $programme_id = $course_id = $publish_date = $deadline_date = $description = '';
        } catch (Exception $e) {
            $message = 'Error creating results publishing schedule: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Fetch existing publishing schedules
$stmt = $pdo->prepare("
    SELECT rp.*, p.name as programme_name, c.name as course_name, CONCAT(ap.full_name, ' (', ap.staff_id, ')') as creator_name 
    FROM result_publishing rp 
    LEFT JOIN programme p ON rp.programme_id = p.id 
    LEFT JOIN course c ON rp.course_id = c.id 
    LEFT JOIN admin_profile ap ON rp.created_by = ap.user_id 
    ORDER BY rp.publish_date DESC
");
$stmt->execute();
$publishing_schedules = $stmt->fetchAll();

// Fetch programmes for dropdown
$stmt = $pdo->query("SELECT id, name FROM programme ORDER BY name");
$programmes = $stmt->fetchAll();

// Fetch courses for dropdown
$stmt = $pdo->query("SELECT id, name FROM course ORDER BY name");
$courses = $stmt->fetchAll();

// Fetch academic years for dropdown
$stmt = $pdo->query("SELECT DISTINCT academic_year FROM academic_calendars ORDER BY academic_year DESC");
$academic_years = $stmt->fetchAll();

// Fetch statistics for result cards

// Count of results pending (courses without results uploaded)
try {
    $stmt = $pdo->prepare(
        "SELECT c.id, c.name as course_name, p.name as programme_name, CONCAT(sp.full_name, ' (', u.username, ')') as lecturer_name 
         FROM course c 
         LEFT JOIN programme p ON c.programme_id = p.id 
         LEFT JOIN lecturer_courses lc ON c.id = lc.course_id 
         LEFT JOIN users u ON lc.lecturer_id = u.id 
         LEFT JOIN staff_profile sp ON u.id = sp.user_id
         WHERE c.id NOT IN (SELECT DISTINCT enrollment.course_id FROM results r JOIN course_enrollment enrollment ON r.enrollment_id = enrollment.id) 
         AND p.category = 'undergraduate'
         GROUP BY c.id"
    );
    $stmt->execute();
    $results_pending = $stmt->fetchAll();
    $results_pending_count = count($results_pending);
} catch (Exception $e) {
    $results_pending_count = 0;
    $results_pending = [];
}

// Count of published results (results publishing schedules that have passed)
try {
    $stmt = $pdo->prepare(
        "SELECT rp.*, p.name as programme_name, c.name as course_name, CONCAT(ap.full_name, ' (', ap.staff_id, ')') as creator_name 
         FROM result_publishing rp 
         LEFT JOIN programme p ON rp.programme_id = p.id 
         LEFT JOIN course c ON rp.course_id = c.id 
         LEFT JOIN admin_profile ap ON rp.created_by = ap.user_id 
         WHERE rp.publish_date <= NOW() 
         ORDER BY rp.publish_date DESC"
    );
    $stmt->execute();
    $published_results = $stmt->fetchAll();
    $published_results_count = count($published_results);
} catch (Exception $e) {
    $published_results_count = 0;
    $published_results = [];
}

// Count of results due for publication (scheduled to be published soon)
try {
    $stmt = $pdo->prepare(
        "SELECT rp.*, p.name as programme_name, c.name as course_name, CONCAT(ap.full_name, ' (', ap.staff_id, ')') as creator_name 
         FROM result_publishing rp 
         LEFT JOIN programme p ON rp.programme_id = p.id 
         LEFT JOIN course c ON rp.course_id = c.id 
         LEFT JOIN admin_profile ap ON rp.created_by = ap.user_id 
         WHERE rp.publish_date >= CURDATE() AND rp.publish_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
         AND rp.publish_date > NOW()
         ORDER BY rp.publish_date ASC"
    );
    $stmt->execute();
    $due_for_publication = $stmt->fetchAll();
    $due_for_publication_count = count($due_for_publication);
} catch (Exception $e) {
    $due_for_publication_count = 0;
    $due_for_publication = [];
}

// Count of overdue publications (past due dates)
try {
    $stmt = $pdo->prepare(
        "SELECT rp.*, p.name as programme_name, c.name as course_name, CONCAT(ap.full_name, ' (', ap.staff_id, ')') as creator_name 
         FROM result_publishing rp 
         LEFT JOIN programme p ON rp.programme_id = p.id 
         LEFT JOIN course c ON rp.course_id = c.id 
         LEFT JOIN admin_profile ap ON rp.created_by = ap.user_id 
         WHERE rp.publish_date < CURDATE() AND rp.publish_date <= NOW()
         ORDER BY rp.publish_date ASC"
    );
    $stmt->execute();
    $overdue_publications = $stmt->fetchAll();
    $overdue_publications_count = count($overdue_publications);
} catch (Exception $e) {
    $overdue_publications_count = 0;
    $overdue_publications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publish Results - Academics Dashboard</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-card {
            background: var(--White);
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background-color: var(--primary-green);
            color: white;
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 500;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .form-group {
            flex: 1 0 300px;
            padding: 0 10px;
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            flex: 1 0 100%;
        }
        
        .form-group.half-width {
            flex: 1 0 45%;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .form-group.required label::after {
            content: " *";
            color: #ea4335;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 2px rgba(34, 139, 34, 0.2);
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-primary {
            background-color: var(--primary-green);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--dark-green);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: var(--dark-green);
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .publish-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .publish-table th,
        .publish-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .publish-table th {
            background-color: var(--primary-green);
            color: white;
            font-weight: 600;
        }
        
        .publish-table tbody tr:hover {
            background-color: rgba(34, 139, 34, 0.05);
        }
        
        .publish-table .actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-icon {
            padding: 8px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-edit {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-view {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-publish {
            background-color: #28a745;
            color: white;
        }
        
        .section-title {
            color: var(--primary-green);
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-green);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ccc;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .result-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--white);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px var(--shadow);
            display: flex;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px var(--shadow);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 24px;
            color: white;
        }
        
        .stat-content h3 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-dark);
        }
        
        .stat-content p {
            color: var(--text-light);
            margin: 0;
            font-size: 14px;
        }
        
        .bg-blue { background-color: #4285f4; }
        .bg-green { background-color: #34a853; }
        .bg-orange { background-color: #fbbc05; }
        .bg-purple { background-color: #673ab7; }
        
        @media (max-width: 768px) {
            .form-group {
                flex: 1 0 100%;
            }
            
            .form-group.half-width {
                flex: 1 0 100%;
            }
            
            .publish-table {
                font-size: 14px;
            }
            
            .publish-table th,
            .publish-table td {
                padding: 8px 10px;
            }
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($admin['full_name'] ?? 'Academics Coordinator'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($admin['staff_id'] ?? 'N/A'); ?>)</span>
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
                        <a href="profile"><i class="fas fa-user"></i> View Profile</a>
                        <a href="settings"><i class="fas fa-cog"></i> Settings</a>
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
            <h3><i class="fas fa-graduation-cap"></i> Academics Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Academic Planning</h4>
                <a href="create_calendar.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Create Academic Calendars</span>
                </a>
                <a href="timetable.php" class="nav-item">
                    <i class="fas fa-clock"></i>
                    <span>Generate Timetables</span>
                </a>
                <a href="schedule_exams.php" class="nav-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Schedule Programs & Exams</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Academic Operations</h4>
                <a href="publish_results.php" class="nav-item active">
                    <i class="fas fa-chart-line"></i>
                    <span>Publish Results</span>
                </a>
                <a href="lecturer_attendance.php" class="nav-item">
                    <i class="fas fa-user-clock"></i>
                    <span>Track Lecturer Attendance</span>
                </a>
                <a href="approve_registration.php" class="nav-item">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Approve Course Registration</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports</h4>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Academic Reports</span>
                </a>
                <a href="analytics.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Performance Analytics</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-chart-line"></i> Publish Results</h1>
            <p>Secure upload, verification, and publishing of student results</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="result-stats">
            <div class="stat-card clickable-card" id="results-pending-card" onclick="showCardDetails('results-pending')">
                <div class="stat-icon bg-blue">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $results_pending_count; ?></h3>
                    <p>Results Pending</p>
                </div>
            </div>
            
            <div class="stat-card clickable-card" id="published-results-card" onclick="showCardDetails('published-results')">
                <div class="stat-icon bg-green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $published_results_count; ?></h3>
                    <p>Published Results</p>
                </div>
            </div>
            
            <div class="stat-card clickable-card" id="due-publication-card" onclick="showCardDetails('due-publication')">
                <div class="stat-icon bg-orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $due_for_publication_count; ?></h3>
                    <p>Due for Publication</p>
                </div>
            </div>
            
            <div class="stat-card clickable-card" id="overdue-publication-card" onclick="showCardDetails('overdue-publication')">
                <div class="stat-icon bg-purple">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $overdue_publications_count; ?></h3>
                    <p>Overdue Publications</p>
                </div>
            </div>
        </div>
        
        <!-- Modal to display card details -->
        <div id="card-details-modal" class="modal" style="display:none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modal-title">Card Details</h3>
                    <span class="close" onclick="closeModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="modal-details-content">
                        <!-- Dynamic content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
        
        <div class="form-card">
            <div class="card-header">
                <h2><i class="fas fa-upload"></i> Schedule Results Publication</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="publish_results" value="1">
                    <div class="form-row">
                        <div class="form-group required half-width">
                            <label for="academic_year">Academic Year</label>
                            <select id="academic_year" name="academic_year" class="form-control" required>
                                <option value="">Select Academic Year</option>
                                <?php foreach ($academic_years as $year): ?>
                                    <option value="<?php echo htmlspecialchars($year['academic_year']); ?>" 
                                        <?php echo (($_POST['academic_year'] ?? '') === $year['academic_year']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year['academic_year']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group required half-width">
                            <label for="semester">Semester/Term</label>
                            <select id="semester" name="semester" class="form-control" required>
                                <option value="">Select Semester</option>
                                <option value="Semester 1" <?php echo (($_POST['semester'] ?? '') === 'Semester 1') ? 'selected' : ''; ?>>Semester 1</option>
                                <option value="Semester 2" <?php echo (($_POST['semester'] ?? '') === 'Semester 2') ? 'selected' : ''; ?>>Semester 2</option>
                                <option value="Summer" <?php echo (($_POST['semester'] ?? '') === 'Summer') ? 'selected' : ''; ?>>Summer</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group required half-width">
                            <label for="programme_id">Programme</label>
                            <select id="programme_id" name="programme_id" class="form-control" required>
                                <option value="">Select Programme</option>
                                <?php foreach ($programmes as $programme): ?>
                                    <option value="<?php echo $programme['id']; ?>" 
                                        <?php echo (($_POST['programme_id'] ?? '') == $programme['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($programme['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group required half-width">
                            <label for="course_id">Course</label>
                            <select id="course_id" name="course_id" class="form-control" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                        <?php echo (($_POST['course_id'] ?? '') == $course['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group required half-width">
                            <label for="publish_date">Publish Date</label>
                            <input type="date" id="publish_date" name="publish_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['publish_date'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group half-width">
                            <label for="deadline_date">Submission Deadline</label>
                            <input type="date" id="deadline_date" name="deadline_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['deadline_date'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="description">Description (Optional)</label>
                        <textarea id="description" name="description" class="form-control" rows="3" 
                                  placeholder="Additional information about this results publication"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Schedule Publication
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <h2 class="section-title">Scheduled Publications</h2>
        
        <?php if (count($publishing_schedules) > 0): ?>
            <div class="table-responsive">
                <table class="publish-table">
                    <thead>
                        <tr>
                            <th>Academic Year</th>
                            <th>Semester</th>
                            <th>Programme</th>
                            <th>Course</th>
                            <th>Publish Date</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($publishing_schedules as $schedule): ?>
                            <?php 
                            $publish_date = new DateTime($schedule['publish_date']);
                            $now = new DateTime();
                            $is_past = $publish_date < $now;
                            $status_class = $is_past ? 'badge-success' : 'badge-warning';
                            $status_text = $is_past ? 'Published' : 'Scheduled';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($schedule['academic_year']); ?></td>
                                <td><?php echo htmlspecialchars($schedule['semester']); ?></td>
                                <td><?php echo htmlspecialchars($schedule['programme_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($schedule['course_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($schedule['publish_date'])); ?></td>
                                <td>
                                    <?php if ($schedule['deadline_date']): ?>
                                        <?php echo date('M j, Y', strtotime($schedule['deadline_date'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                <td><?php echo htmlspecialchars($schedule['creator_name'] ?? 'Unknown'); ?></td>
                                <td class="actions">
                                    <a href="#" class="btn-icon btn-view" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="#" class="btn-icon btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" class="btn-icon btn-delete" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php if (!$is_past): ?>
                                        <a href="#" class="btn-icon btn-publish" title="Publish Now">
                                            <i class="fas fa-paper-plane"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <h3>No Publication Schedules Found</h3>
                <p>Schedule your first results publication using the form above.</p>
            </div>
        <?php endif; ?>
    </main>

        <!-- Add styles for interactive cards -->
        <style>
        .clickable-card {
            cursor: pointer;
        }
        
        .clickable-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px var(--shadow);
        }
        
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: var(--white);
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            width: 80%;
            max-width: 900px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            padding: 20px;
            background-color: var(--primary-green);
            color: white;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.5em;
        }
        
        .close {
            font-size: 28px;
            font-weight: bold;
            color: white;
            cursor: pointer;
        }
        
        .close:hover {
            color: #ccc;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .details-table th,
        .details-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .details-table th {
            background-color: var(--primary-green);
            color: white;
            font-weight: 600;
        }
        
        .details-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .details-table tr:hover {
            background-color: rgba(34, 139, 34, 0.05);
        }
        </style>
        
        <script>
        // Function to show card details in modal
        function showCardDetails(cardType) {
            let title = '';
            let content = '';
            
            switch(cardType) {
                case 'results-pending':
                    title = 'Results Pending';
                    content = `
                        <p>Courses in undergraduate programmes whose results have not been uploaded yet by respective lecturers:</p>
                        <table class="details-table">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Programme</th>
                                    <th>Lecturer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results_pending as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['programme_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['lecturer_name'] ?? 'Not assigned'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($results_pending)): ?>
                                <tr>
                                    <td colspan="3">No pending results found</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    `;
                    break;
                    
                case 'published-results':
                    title = 'Published Results';
                    content = `
                        <p>Courses whose results have been published:</p>
                        <table class="details-table">
                            <thead>
                                <tr>
                                    <th>Programme</th>
                                    <th>Course</th>
                                    <th>Publish Date</th>
                                    <th>Published By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($published_results as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['programme_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['course_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($item['publish_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($item['creator_name']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($published_results)): ?>
                                <tr>
                                    <td colspan="4">No published results found</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    `;
                    break;
                    
                case 'due-publication':
                    title = 'Results Due for Publication';
                    content = `
                        <p>Courses whose results have been uploaded by lecturers and are awaiting publication by academic officers:</p>
                        <table class="details-table">
                            <thead>
                                <tr>
                                    <th>Programme</th>
                                    <th>Course</th>
                                    <th>Scheduled Publish Date</th>
                                    <th>Scheduled By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($due_for_publication as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['programme_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['course_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($item['publish_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($item['creator_name']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($due_for_publication)): ?>
                                <tr>
                                    <td colspan="4">No results due for publication</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    `;
                    break;
                    
                case 'overdue-publication':
                    title = 'Overdue Publications';
                    content = `
                        <p>Courses with overdue publication schedules:</p>
                        <table class="details-table">
                            <thead>
                                <tr>
                                    <th>Programme</th>
                                    <th>Course</th>
                                    <th>Overdue Date</th>
                                    <th>Scheduled By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($overdue_publications as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['programme_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['course_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($item['publish_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($item['creator_name']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($overdue_publications)): ?>
                                <tr>
                                    <td colspan="4">No overdue publications</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    `;
                    break;
            }
            
            document.getElementById('modal-title').innerHTML = title;
            document.getElementById('modal-details-content').innerHTML = content;
            document.getElementById('card-details-modal').style.display = 'block';
        }
        
        // Function to close modal
        function closeModal() {
            document.getElementById('card-details-modal').style.display = 'none';
        }
        
        // Toggle theme function
        function toggleTheme() {
            const body = document.body;
            const themeIcon = document.getElementById('theme-icon');
            
            if (body.getAttribute('data-theme') === 'light') {
                body.setAttribute('data-theme', 'dark');
                themeIcon.className = 'fas fa-sun';
                localStorage.setItem('theme', 'dark');
            } else {
                body.setAttribute('data-theme', 'light');
                themeIcon.className = 'fas fa-moon';
                localStorage.setItem('theme', 'light');
            }
        }
        
        // Toggle sidebar function
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
        }
        
        // Toggle dropdown function
        function toggleDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }
        
        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.profile-btn') && !event.target.closest('.dropdown')) {
                const dropdowns = document.getElementsByClassName('dropdown-menu');
                for (let i = 0; i < dropdowns.length; i++) {
                    dropdowns[i].classList.remove('show');
                }
            }
        }
        
        // Load saved theme preference
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            const themeIcon = document.getElementById('theme-icon');
            
            document.body.setAttribute('data-theme', savedTheme);
            themeIcon.className = savedTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        });
    </script>
</body>
</html>