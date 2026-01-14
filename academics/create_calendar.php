<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit;
}

// Check if user has Academics Coordinator role
requireRole('Academics Coordinator', $pdo);

// Get admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $academic_year = trim($_POST['academic_year']);
    $semester = trim($_POST['semester']);
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);
    $registration_deadline = trim($_POST['registration_deadline']);
    $exam_start_date = trim($_POST['exam_start_date']);
    $exam_end_date = trim($_POST['exam_end_date']);
    $holidays = trim($_POST['holidays']);
    $remarks = trim($_POST['remarks']);
    
    // Validation
    if (empty($academic_year) || empty($semester) || empty($start_date) || empty($end_date)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } elseif (strtotime($start_date) >= strtotime($end_date)) {
        $message = 'Start date must be before end date.';
        $message_type = 'error';
    } else {
        try {
            // Insert academic calendar
            $stmt = $pdo->prepare("
                INSERT INTO academic_calendars 
                (academic_year, semester, start_date, end_date, registration_deadline, exam_start_date, exam_end_date, holidays, remarks, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $academic_year, $semester, $start_date, $end_date, $registration_deadline,
                $exam_start_date, $exam_end_date, $holidays, $remarks, currentUserId()
            ]);
            
            $message = 'Academic calendar created successfully!';
            $message_type = 'success';
            
            // Clear form values
            $academic_year = $semester = $start_date = $end_date = $registration_deadline = '';
            $exam_start_date = $exam_end_date = $holidays = $remarks = '';
        } catch (Exception $e) {
            $message = 'Error creating academic calendar: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Fetch existing academic calendars
$stmt = $pdo->prepare("
    SELECT ac.*, CONCAT(ap.full_name, ' (', ap.staff_id, ')') as creator_name 
    FROM academic_calendars ac 
    LEFT JOIN admin_profile ap ON ac.created_by = ap.user_id 
    ORDER BY ac.academic_year DESC, ac.semester ASC
");
$stmt->execute();
$calendars = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Academic Calendar - Academics Dashboard</title>
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
        
        .calendar-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .calendar-table th,
        .calendar-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .calendar-table th {
            background-color: var(--primary-green);
            color: white;
            font-weight: 600;
        }
        
        .calendar-table tbody tr:hover {
            background-color: rgba(34, 139, 34, 0.05);
        }
        
        .calendar-table .actions {
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
        
        @media (max-width: 768px) {
            .form-group {
                flex: 1 0 100%;
            }
            
            .calendar-table {
                font-size: 14px;
            }
            
            .calendar-table th,
            .calendar-table td {
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
            <h3><i class="fas fa-graduation-cap"></i> Academics Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Academic Planning</h4>
                <a href="create_calendar.php" class="nav-item active">
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
            <h1><i class="fas fa-calendar-alt"></i> Create Academic Calendar</h1>
            <p>Define academic years, semesters, and important dates</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-card">
            <div class="card-header">
                <h2><i class="fas fa-plus-circle"></i> New Academic Calendar</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group required">
                            <label for="academic_year">Academic Year</label>
                            <input type="text" id="academic_year" name="academic_year" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['academic_year'] ?? ''); ?>" 
                                   placeholder="e.g., 2024/2025" required>
                        </div>
                        
                        <div class="form-group required">
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
                        <div class="form-group required">
                            <label for="start_date">Academic Year Start Date</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group required">
                            <label for="end_date">Academic Year End Date</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="registration_deadline">Registration Deadline</label>
                            <input type="date" id="registration_deadline" name="registration_deadline" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['registration_deadline'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="exam_start_date">Examination Start Date</label>
                            <input type="date" id="exam_start_date" name="exam_start_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['exam_start_date'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="exam_end_date">Examination End Date</label>
                            <input type="date" id="exam_end_date" name="exam_end_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['exam_end_date'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="holidays">Holidays (comma separated)</label>
                        <input type="text" id="holidays" name="holidays" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['holidays'] ?? ''); ?>" 
                               placeholder="e.g., 2024-12-25, 2024-01-01">
                    </div>
                    
                    <div class="form-group">
                        <label for="remarks">Remarks/Notes</label>
                        <textarea id="remarks" name="remarks" class="form-control" rows="3" 
                                  placeholder="Additional notes or information about this academic calendar"><?php echo htmlspecialchars($_POST['remarks'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Calendar
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <h2 class="section-title">Existing Academic Calendars</h2>
        
        <?php if (count($calendars) > 0): ?>
            <div class="table-responsive">
                <table class="calendar-table">
                    <thead>
                        <tr>
                            <th>Academic Year</th>
                            <th>Semester</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Exam Period</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calendars as $calendar): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($calendar['academic_year']); ?></td>
                                <td><?php echo htmlspecialchars($calendar['semester']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($calendar['start_date'])); ?></td>
                                <td><?php echo date('M j, Y', strtotime($calendar['end_date'])); ?></td>
                                <td>
                                    <?php if ($calendar['exam_start_date'] && $calendar['exam_end_date']): ?>
                                        <?php echo date('M j', strtotime($calendar['exam_start_date'])); ?> - <?php echo date('M j, Y', strtotime($calendar['exam_end_date'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($calendar['creator_name'] ?? 'Unknown'); ?></td>
                                <td class="actions">
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
                <i class="fas fa-calendar-times"></i>
                <h3>No Academic Calendars Found</h3>
                <p>Create your first academic calendar using the form above.</p>
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