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

// Handle form submission for creating timetable
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_timetable'])) {
    $academic_year = trim($_POST['academic_year']);
    $semester = trim($_POST['semester']);
    $programme_id = intval($_POST['programme_id']);
    $start_time = trim($_POST['start_time']);
    $end_time = trim($_POST['end_time']);
    
    // Validation
    if (empty($academic_year) || empty($semester) || empty($programme_id) || empty($start_time) || empty($end_time)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $message = 'Start time must be before end time.';
        $message_type = 'error';
    } else {
        try {
            // Insert timetable record
            $stmt = $pdo->prepare("
                INSERT INTO timetables 
                (academic_year, semester, programme_id, start_time, end_time, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $academic_year, $semester, $programme_id, $start_time, $end_time, currentUserId()
            ]);
            
            $message = 'Timetable generation initiated successfully! The system will generate the timetable shortly.';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error initiating timetable generation: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Fetch existing timetables
$timetables = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*, p.name as programme_name, CONCAT(ap.full_name, ' (', ap.staff_id, ')') as creator_name 
        FROM timetables t 
        LEFT JOIN programme p ON t.programme_id = p.id 
        LEFT JOIN admin_profile ap ON t.created_by = ap.user_id 
        ORDER BY t.created_at DESC
    ");
    $stmt->execute();
    $timetables = $stmt->fetchAll();
} catch (Exception $e) {
    $timetables = [];
}

// Fetch programmes for dropdown
$programmes = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM programme ORDER BY name");
    $programmes = $stmt->fetchAll();
} catch (Exception $e) {
    $programmes = [];
}

// Fetch academic years for dropdown
$academic_years = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT academic_year FROM academic_calendars ORDER BY academic_year DESC");
    $academic_years = $stmt->fetchAll();
} catch (Exception $e) {
    $academic_years = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Timetables - Academics Dashboard</title>
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
        
        .timetable-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .timetable-table th,
        .timetable-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .timetable-table th {
            background-color: var(--primary-green);
            color: white;
            font-weight: 600;
        }
        
        .timetable-table tbody tr:hover {
            background-color: rgba(34, 139, 34, 0.05);
        }
        
        .timetable-table .actions {
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
        
        .preview-container {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--shadow);
            padding: 20px;
            margin-top: 20px;
        }
        
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .preview-title {
            color: var(--primary-green);
            margin: 0;
        }
        
        .preview-actions {
            display: flex;
            gap: 10px;
        }
        
        .preview-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .preview-table th,
        .preview-table td {
            border: 1px solid var(--border-color);
            padding: 10px;
            text-align: center;
        }
        
        .preview-table th {
            background-color: var(--primary-green);
            color: white;
        }
        
        .preview-table td {
            background-color: #f8f9fa;
        }
        
        @media (max-width: 768px) {
            .form-group {
                flex: 1 0 100%;
            }
            
            .timetable-table {
                font-size: 14px;
            }
            
            .timetable-table th,
            .timetable-table td {
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
                <a href="/academics/timetable.php" class="nav-item active">
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
            <h1><i class="fas fa-clock"></i> Generate Timetables</h1>
            <p>Create and manage academic timetables</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-card">
            <div class="card-header">
                <h2><i class="fas fa-plus-circle"></i> Generate New Timetable</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="generate_timetable" value="1">
                    <div class="form-row">
                        <div class="form-group required">
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
                        
                        <div class="form-group required">
                            <label for="semester">Semester/Term</label>
                            <select id="semester" name="semester" class="form-control" required>
                                <option value="">Select Semester</option>
                                <option value="Semester 1" <?php echo (($_POST['semester'] ?? '') === 'Semester 1') ? 'selected' : ''; ?>>Semester 1</option>
                                <option value="Semester 2" <?php echo (($_POST['semester'] ?? '') === 'Semester 2') ? 'selected' : ''; ?>>Semester 2</option>
                                <option value="Summer" <?php echo (($_POST['semester'] ?? '') === 'Summer') ? 'selected' : ''; ?>>Summer</option>
                            </select>
                        </div>
                        
                        <div class="form-group required">
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
                        <div class="form-group required">
                            <label for="start_time">Timetable Start Time</label>
                            <input type="time" id="start_time" name="start_time" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['start_time'] ?? '08:00'); ?>" required>
                        </div>
                        
                        <div class="form-group required">
                            <label for="end_time">Timetable End Time</label>
                            <input type="time" id="end_time" name="end_time" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['end_time'] ?? '17:00'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-cogs"></i> Generate Timetable
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <h2 class="section-title">Existing Timetables</h2>
        
        <?php if (count($timetables) > 0): ?>
            <div class="table-responsive">
                <table class="timetable-table">
                    <thead>
                        <tr>
                            <th>Academic Year</th>
                            <th>Semester</th>
                            <th>Programme</th>
                            <th>Time Range</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timetables as $timetable): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($timetable['academic_year']); ?></td>
                                <td><?php echo htmlspecialchars($timetable['semester']); ?></td>
                                <td><?php echo htmlspecialchars($timetable['programme_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('H:i', strtotime($timetable['start_time'])); ?> - <?php echo date('H:i', strtotime($timetable['end_time'])); ?></td>
                                <td><?php echo htmlspecialchars($timetable['creator_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo date('M j, Y H:i', strtotime($timetable['created_at'])); ?></td>
                                <td><span class="badge badge-success">Generated</span></td>
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
                <i class="fas fa-clock"></i>
                <h3>No Timetables Found</h3>
                <p>Generate your first timetable using the form above.</p>
            </div>
        <?php endif; ?>
        
        <div class="preview-container">
            <div class="preview-header">
                <h3 class="preview-title">Timetable Preview</h3>
                <div class="preview-actions">
                    <button class="btn btn-secondary">
                        <i class="fas fa-download"></i> Export PDF
                    </button>
                    <button class="btn btn-primary">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
            
            <table class="preview-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Monday</th>
                        <th>Tuesday</th>
                        <th>Wednesday</th>
                        <th>Thursday</th>
                        <th>Friday</th>
                        <th>Saturday</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>08:00 - 09:00</td>
                        <td>Math 101<br>Room 101<br>Dr. Smith</td>
                        <td>Physics 101<br>Room 202<br>Prof. Johnson</td>
                        <td>Chemistry 101<br>Lab 301<br>Dr. Brown</td>
                        <td>English 101<br>Room 105<br>Ms. Davis</td>
                        <td>Biology 101<br>Lab 205<br>Dr. Wilson</td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td>09:00 - 10:00</td>
                        <td>Physics 101<br>Room 202<br>Prof. Johnson</td>
                        <td>Math 101<br>Room 101<br>Dr. Smith</td>
                        <td>English 101<br>Room 105<br>Ms. Davis</td>
                        <td>Chemistry 101<br>Lab 301<br>Dr. Brown</td>
                        <td>-</td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td>10:00 - 11:00</td>
                        <td>Chemistry 101<br>Lab 301<br>Dr. Brown</td>
                        <td>English 101<br>Room 105<br>Ms. Davis</td>
                        <td>Math 101<br>Room 101<br>Dr. Smith</td>
                        <td>Physics 101<br>Room 202<br>Prof. Johnson</td>
                        <td>Biology 101<br>Lab 205<br>Dr. Wilson</td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td>11:00 - 12:00</td>
                        <td>English 101<br>Room 105<br>Ms. Davis</td>
                        <td>Biology 101<br>Lab 205<br>Dr. Wilson</td>
                        <td>Physics 101<br>Room 202<br>Prof. Johnson</td>
                        <td>Math 101<br>Room 101<br>Dr. Smith</td>
                        <td>Chemistry 101<br>Lab 301<br>Dr. Brown</td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td>12:00 - 13:00</td>
                        <td colspan="6" style="text-align: center; background-color: #e9ecef;">Lunch Break</td>
                    </tr>
                    <tr>
                        <td>13:00 - 14:00</td>
                        <td>Biology 101<br>Lab 205<br>Dr. Wilson</td>
                        <td>Chemistry 101<br>Lab 301<br>Dr. Brown</td>
                        <td>English 101<br>Room 105<br>Ms. Davis</td>
                        <td>Biology 101<br>Lab 205<br>Dr. Wilson</td>
                        <td>Math 101<br>Room 101<br>Dr. Smith</td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td>14:00 - 15:00</td>
                        <td>Physics 101<br>Room 202<br>Prof. Johnson</td>
                        <td>Math 101<br>Room 101<br>Dr. Smith</td>
                        <td>Biology 101<br>Lab 205<br>Dr. Wilson</td>
                        <td>Chemistry 101<br>Lab 301<br>Dr. Brown</td>
                        <td>English 101<br>Room 105<br>Ms. Davis</td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td>15:00 - 16:00</td>
                        <td>English 101<br>Room 105<br>Ms. Davis</td>
                        <td>Physics 101<br>Room 202<br>Prof. Johnson</td>
                        <td>Math 101<br>Room 101<br>Dr. Smith</td>
                        <td>Biology 101<br>Lab 205<br>Dr. Wilson</td>
                        <td>Physics 101<br>Room 202<br>Prof. Johnson</td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td>16:00 - 17:00</td>
                        <td>Chemistry 101<br>Lab 301<br>Dr. Brown</td>
                        <td>English 101<br>Room 105<br>Ms. Davis</td>
                        <td>Physics 101<br>Room 202<br>Prof. Johnson</td>
                        <td>Math 101<br>Room 101<br>Dr. Smith</td>
                        <td>Biology 101<br>Lab 205<br>Dr. Wilson</td>
                        <td>-</td>
                    </tr>
                </tbody>
            </table>
        </div>
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