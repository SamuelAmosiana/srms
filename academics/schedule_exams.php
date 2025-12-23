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

// Handle form submission for creating exam schedule
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam_schedule'])) {
    $exam_title = trim($_POST['exam_title']);
    $exam_type = trim($_POST['exam_type']);
    $programme_id = intval($_POST['programme_id']);
    $course_id = intval($_POST['course_id']);
    $exam_date = trim($_POST['exam_date']);
    $start_time = trim($_POST['start_time']);
    $end_time = trim($_POST['end_time']);
    $room = trim($_POST['room']);
    $invigilator_id = intval($_POST['invigilator_id']);
    $description = trim($_POST['description']);
    
    // Validation
    if (empty($exam_title) || empty($exam_type) || empty($programme_id) || empty($course_id) || empty($exam_date) || empty($start_time) || empty($end_time)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $message = 'Start time must be before end time.';
        $message_type = 'error';
    } else {
        try {
            // Insert exam schedule record
            $stmt = $pdo->prepare("
                INSERT INTO exam_schedules 
                (exam_title, exam_type, programme_id, course_id, exam_date, start_time, end_time, room, invigilator_id, description, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $exam_title, $exam_type, $programme_id, $course_id, $exam_date, $start_time, $end_time, $room, $invigilator_id, $description, currentUserId()
            ]);
            
            $message = 'Exam schedule created successfully!';
            $message_type = 'success';
            
            // Clear form values
            $exam_title = $exam_type = $programme_id = $course_id = $exam_date = $start_time = $end_time = $room = $invigilator_id = $description = '';
        } catch (Exception $e) {
            $message = 'Error creating exam schedule: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Fetch existing exam schedules
$stmt = $pdo->prepare("
    SELECT es.*, p.name as programme_name, c.name as course_name, u.username as invigilator_name, CONCAT(ap.full_name, ' (', ap.staff_id, ')') as creator_name 
    FROM exam_schedules es 
    LEFT JOIN programme p ON es.programme_id = p.id 
    LEFT JOIN course c ON es.course_id = c.id 
    LEFT JOIN users u ON es.invigilator_id = u.id 
    LEFT JOIN admin_profile ap ON es.created_by = ap.user_id 
    ORDER BY es.exam_date DESC, es.start_time ASC
");
$stmt->execute();
$exam_schedules = $stmt->fetchAll();

// Fetch programmes for dropdown
$stmt = $pdo->query("SELECT id, name FROM programme ORDER BY name");
$programmes = $stmt->fetchAll();

// Fetch courses for dropdown
$stmt = $pdo->query("SELECT id, name FROM course ORDER BY name");
$courses = $stmt->fetchAll();

// Fetch staff for invigilator dropdown
$stmt = $pdo->query("SELECT u.id, CONCAT(ap.full_name, ' (', u.username, ')') as full_name FROM users u LEFT JOIN admin_profile ap ON u.id = ap.user_id WHERE ap.full_name IS NOT NULL ORDER BY ap.full_name");
$invigilators = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Programs & Exams - Academics Dashboard</title>
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
        
        .exam-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .exam-table th,
        .exam-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .exam-table th {
            background-color: var(--primary-green);
            color: white;
            font-weight: 600;
        }
        
        .exam-table tbody tr:hover {
            background-color: rgba(34, 139, 34, 0.05);
        }
        
        .exam-table .actions {
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
        
        @media (max-width: 768px) {
            .form-group {
                flex: 1 0 100%;
            }
            
            .form-group.half-width {
                flex: 1 0 100%;
            }
            
            .exam-table {
                font-size: 14px;
            }
            
            .exam-table th,
            .exam-table td {
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
                <a href="schedule_exams.php" class="nav-item active">
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
            <h1><i class="fas fa-calendar-check"></i> Schedule Programs & Exams</h1>
            <p>Plan examinations, practicals, and events with venue and invigilator assignment</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-card">
            <div class="card-header">
                <h2><i class="fas fa-plus-circle"></i> Create New Exam Schedule</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="create_exam_schedule" value="1">
                    <div class="form-row">
                        <div class="form-group required half-width">
                            <label for="exam_title">Exam Title</label>
                            <input type="text" id="exam_title" name="exam_title" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['exam_title'] ?? ''); ?>" 
                                   placeholder="e.g., Mid-Semester Exam" required>
                        </div>
                        
                        <div class="form-group required half-width">
                            <label for="exam_type">Exam Type</label>
                            <select id="exam_type" name="exam_type" class="form-control" required>
                                <option value="">Select Exam Type</option>
                                <option value="Mid-Semester" <?php echo (($_POST['exam_type'] ?? '') === 'Mid-Semester') ? 'selected' : ''; ?>>Mid-Semester</option>
                                <option value="Final Exam" <?php echo (($_POST['exam_type'] ?? '') === 'Final Exam') ? 'selected' : ''; ?>>Final Exam</option>
                                <option value="Practical" <?php echo (($_POST['exam_type'] ?? '') === 'Practical') ? 'selected' : ''; ?>>Practical</option>
                                <option value="Quiz" <?php echo (($_POST['exam_type'] ?? '') === 'Quiz') ? 'selected' : ''; ?>>Quiz</option>
                                <option value="Assignment" <?php echo (($_POST['exam_type'] ?? '') === 'Assignment') ? 'selected' : ''; ?>>Assignment</option>
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
                            <label for="exam_date">Exam Date</label>
                            <input type="date" id="exam_date" name="exam_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['exam_date'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group required half-width">
                            <label for="room">Exam Room</label>
                            <input type="text" id="room" name="room" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['room'] ?? ''); ?>" 
                                   placeholder="e.g., Room 101" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group required half-width">
                            <label for="start_time">Start Time</label>
                            <input type="time" id="start_time" name="start_time" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['start_time'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group required half-width">
                            <label for="end_time">End Time</label>
                            <input type="time" id="end_time" name="end_time" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['end_time'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group half-width">
                            <label for="invigilator_id">Invigilator</label>
                            <select id="invigilator_id" name="invigilator_id" class="form-control">
                                <option value="">Select Invigilator (Optional)</option>
                                <?php foreach ($invigilators as $invigilator): ?>
                                    <option value="<?php echo $invigilator['id']; ?>" 
                                        <?php echo (($_POST['invigilator_id'] ?? '') == $invigilator['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($invigilator['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group half-width">
                            <label for="description">Description (Optional)</label>
                            <textarea id="description" name="description" class="form-control" rows="2" 
                                      placeholder="Additional information about this exam"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Create Exam Schedule
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <h2 class="section-title">Scheduled Exams</h2>
        
        <?php if (count($exam_schedules) > 0): ?>
            <div class="table-responsive">
                <table class="exam-table">
                    <thead>
                        <tr>
                            <th>Exam Title</th>
                            <th>Type</th>
                            <th>Programme</th>
                            <th>Course</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Room</th>
                            <th>Invigilator</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exam_schedules as $schedule): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($schedule['exam_title']); ?></td>
                                <td><span class="badge badge-success"><?php echo htmlspecialchars($schedule['exam_type']); ?></span></td>
                                <td><?php echo htmlspecialchars($schedule['programme_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($schedule['course_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($schedule['exam_date'])); ?></td>
                                <td><?php echo date('H:i', strtotime($schedule['start_time'])); ?> - <?php echo date('H:i', strtotime($schedule['end_time'])); ?></td>
                                <td><?php echo htmlspecialchars($schedule['room'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($schedule['invigilator_name'] ?? 'Not assigned'); ?></td>
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
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-check"></i>
                <h3>No Exam Schedules Found</h3>
                <p>Schedule your first exam using the form above.</p>
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