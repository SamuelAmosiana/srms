<?php
require '../config.php';
require '../auth.php';

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

// Handle form submission for marking lecturer attendance
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $lecturer_id = intval($_POST['lecturer_id']);
    $programme_id = intval($_POST['programme_id']);
    $course_id = intval($_POST['course_id']);
    $date = trim($_POST['date']);
    $status = trim($_POST['status']);
    $session_type = trim($_POST['session_type']);
    $session_time = trim($_POST['session_time']);
    $remarks = trim($_POST['remarks']);
    
    // Validation
    if (empty($lecturer_id) || empty($programme_id) || empty($course_id) || empty($date) || empty($status)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } else {
        try {
            // Insert attendance record
            $stmt = $pdo->prepare("
                INSERT INTO lecturer_attendance 
                (lecturer_id, programme_id, course_id, date, status, session_type, session_time, remarks, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $lecturer_id, $programme_id, $course_id, $date, $status, $session_type, $session_time, $remarks, currentUserId()
            ]);
            
            $message = 'Lecturer attendance marked successfully!';
            $message_type = 'success';
            
            // Clear form values
            $lecturer_id = $programme_id = $course_id = $date = $status = $session_type = $session_time = $remarks = '';
        } catch (Exception $e) {
            $message = 'Error marking lecturer attendance: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Fetch existing attendance records
$stmt = $pdo->prepare("
    SELECT la.*, u.username as lecturer_name, p.name as programme_name, c.name as course_name, CONCAT(ap.full_name, ' (', ap.staff_id, ')') as creator_name 
    FROM lecturer_attendance la 
    LEFT JOIN users u ON la.lecturer_id = u.id 
    LEFT JOIN programme p ON la.programme_id = p.id 
    LEFT JOIN course c ON la.course_id = c.id 
    LEFT JOIN admin_profile ap ON la.created_by = ap.user_id 
    ORDER BY la.date DESC, la.session_time DESC
");
$stmt->execute();
$attendance_records = $stmt->fetchAll();

// Fetch lecturers for dropdown (users with Lecturer role)
$stmt = $pdo->query("
    SELECT u.id, CONCAT(ap.full_name, ' (', u.username, ')') as full_name 
    FROM users u 
    LEFT JOIN admin_profile ap ON u.id = ap.user_id 
    LEFT JOIN user_roles ur ON u.id = ur.user_id 
    LEFT JOIN roles r ON ur.role_id = r.id 
    WHERE r.name = 'Lecturer' AND ap.full_name IS NOT NULL 
    ORDER BY ap.full_name
");
$lecturers = $stmt->fetchAll();

// Fetch programmes for dropdown
$stmt = $pdo->query("SELECT id, name FROM programme ORDER BY name");
$programmes = $stmt->fetchAll();

// Fetch courses for dropdown
$stmt = $pdo->query("SELECT id, name FROM course ORDER BY name");
$courses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Lecturer Attendance - Academics Dashboard</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-card {
            background: var(--white);
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
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .attendance-table th,
        .attendance-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .attendance-table th {
            background-color: var(--primary-green);
            color: white;
            font-weight: 600;
        }
        
        .attendance-table tbody tr:hover {
            background-color: rgba(34, 139, 34, 0.05);
        }
        
        .attendance-table .actions {
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
        
        .attendance-stats {
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
            
            .attendance-table {
                font-size: 14px;
            }
            
            .attendance-table th,
            .attendance-table td {
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
                <a href="publish_results.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Publish Results</span>
                </a>
                <a href="lecturer_attendance.php" class="nav-item active">
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
            <h1><i class="fas fa-user-clock"></i> Track Lecturer Attendance</h1>
            <p>Monitor and manage lecturer attendance across programmes and courses</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="attendance-stats">
            <div class="stat-card">
                <div class="stat-icon bg-blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>24</h3>
                    <p>Total Lecturers</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon bg-green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>89%</h3>
                    <p>Attendance Rate</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon bg-orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>156</h3>
                    <p>Sessions This Week</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon bg-purple">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3>3</h3>
                    <p>Low Attendance</p>
                </div>
            </div>
        </div>
        
        <div class="form-card">
            <div class="card-header">
                <h2><i class="fas fa-calendar-check"></i> Mark Lecturer Attendance</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="mark_attendance" value="1">
                    <div class="form-row">
                        <div class="form-group required half-width">
                            <label for="lecturer_id">Lecturer</label>
                            <select id="lecturer_id" name="lecturer_id" class="form-control" required>
                                <option value="">Select Lecturer</option>
                                <?php foreach ($lecturers as $lecturer): ?>
                                    <option value="<?php echo $lecturer['id']; ?>" 
                                        <?php echo (($_POST['lecturer_id'] ?? '') == $lecturer['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lecturer['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
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
                    </div>
                    
                    <div class="form-row">
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
                        
                        <div class="form-group required half-width">
                            <label for="date">Date</label>
                            <input type="date" id="date" name="date" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['date'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group required half-width">
                            <label for="status">Attendance Status</label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="">Select Status</option>
                                <option value="Present" <?php echo (($_POST['status'] ?? '') === 'Present') ? 'selected' : ''; ?>>Present</option>
                                <option value="Absent" <?php echo (($_POST['status'] ?? '') === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                                <option value="Late" <?php echo (($_POST['status'] ?? '') === 'Late') ? 'selected' : ''; ?>>Late</option>
                                <option value="On Leave" <?php echo (($_POST['status'] ?? '') === 'On Leave') ? 'selected' : ''; ?>>On Leave</option>
                            </select>
                        </div>
                        
                        <div class="form-group half-width">
                            <label for="session_type">Session Type</label>
                            <select id="session_type" name="session_type" class="form-control">
                                <option value="">Select Session Type</option>
                                <option value="Lecture" <?php echo (($_POST['session_type'] ?? '') === 'Lecture') ? 'selected' : ''; ?>>Lecture</option>
                                <option value="Practical" <?php echo (($_POST['session_type'] ?? '') === 'Practical') ? 'selected' : ''; ?>>Practical</option>
                                <option value="Tutorial" <?php echo (($_POST['session_type'] ?? '') === 'Tutorial') ? 'selected' : ''; ?>>Tutorial</option>
                                <option value="Seminar" <?php echo (($_POST['session_type'] ?? '') === 'Seminar') ? 'selected' : ''; ?>>Seminar</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group half-width">
                            <label for="session_time">Session Time</label>
                            <input type="time" id="session_time" name="session_time" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['session_time'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group half-width">
                            <label for="remarks">Remarks (Optional)</label>
                            <input type="text" id="remarks" name="remarks" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['remarks'] ?? ''); ?>" 
                                   placeholder="Additional notes about attendance">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check-circle"></i> Mark Attendance
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <h2 class="section-title">Attendance Records</h2>
        
        <?php if (count($attendance_records) > 0): ?>
            <div class="table-responsive">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Lecturer</th>
                            <th>Programme</th>
                            <th>Course</th>
                            <th>Date</th>
                            <th>Session</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th>Recorded By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                            <?php 
                            $status_class = '';
                            switch ($record['status']) {
                                case 'Present':
                                    $status_class = 'badge-success';
                                    break;
                                case 'Absent':
                                    $status_class = 'badge-danger';
                                    break;
                                case 'Late':
                                    $status_class = 'badge-warning';
                                    break;
                                case 'On Leave':
                                    $status_class = 'badge-info';
                                    break;
                                default:
                                    $status_class = 'badge-warning';
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['lecturer_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($record['programme_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($record['course_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($record['date'])); ?></td>
                                <td><?php echo htmlspecialchars($record['session_type'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($record['session_time'] ?? 'N/A'); ?></td>
                                <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($record['status']); ?></span></td>
                                <td><?php echo htmlspecialchars($record['remarks'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($record['creator_name'] ?? 'Unknown'); ?></td>
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
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-clock"></i>
                <h3>No Attendance Records Found</h3>
                <p>Mark your first lecturer attendance using the form above.</p>
            </div>
        <?php endif; ?>
    </main>

    <script>
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