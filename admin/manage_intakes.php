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

// Create intake table if it doesn't exist
try {
    // Check if table exists first
    $table_check = $pdo->query("SHOW TABLES LIKE 'intake'");
    if ($table_check->rowCount() == 0) {
        // Create intake table
        $pdo->exec("CREATE TABLE intake (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    }
    
    // Check if intake_id column exists in student_profile
    $column_check = $pdo->query("SHOW COLUMNS FROM student_profile LIKE 'intake_id'");
    if ($column_check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE student_profile ADD COLUMN intake_id INT DEFAULT NULL");
        
        // Try to add foreign key constraint
        try {
            $pdo->exec("ALTER TABLE student_profile ADD CONSTRAINT FK_student_intake 
                        FOREIGN KEY (intake_id) REFERENCES intake(id) ON DELETE SET NULL");
        } catch (Exception $fk_error) {
            // Foreign key might already exist or other constraint issue
        }
    }
} catch (Exception $e) {
    // Log error but continue - table might already exist
    error_log("Intake table creation error: " . $e->getMessage());
}

// Handle AJAX requests for export
if (isset($_GET['action']) && $_GET['action'] === 'export_intakes') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="intakes_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Start Date', 'End Date', 'Status', 'Students', 'Created']);
    
    $export_query = "
        SELECT i.name, i.start_date, i.end_date,
               CASE 
                   WHEN CURDATE() < i.start_date THEN 'Upcoming'
                   WHEN CURDATE() > i.end_date THEN 'Past'
                   ELSE 'Active'
               END as status,
               (SELECT COUNT(*) FROM student_profile sp WHERE sp.intake_id = i.id) as student_count,
               i.created_at
        FROM intake i 
        ORDER BY i.start_date DESC
    ";
    $export_data = $pdo->query($export_query)->fetchAll();
    
    foreach ($export_data as $row) {
        fputcsv($output, [
            $row['name'],
            $row['start_date'],
            $row['end_date'],
            $row['status'],
            $row['student_count'],
            date('Y-m-d', strtotime($row['created_at']))
        ]);
    }
    fclose($output);
    exit();
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_intake':
                $name = trim($_POST['name']);
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $description = trim($_POST['description']);
                
                // Validate inputs
                if (empty($name) || empty($start_date) || empty($end_date)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } elseif (strtotime($start_date) >= strtotime($end_date)) {
                    $message = "Start date must be before end date!";
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO intake (name, start_date, end_date, description, created_at) VALUES (?, ?, ?, ?, NOW())");
                        if ($stmt->execute([$name, $start_date, $end_date, $description])) {
                            $message = "Intake added successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to add intake!";
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'edit_intake':
                $id = $_POST['intake_id'];
                $name = trim($_POST['name']);
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $description = trim($_POST['description']);
                
                // Validate inputs
                if (empty($name) || empty($start_date) || empty($end_date)) {
                    $message = "Please fill in all required fields!";
                    $messageType = 'error';
                } elseif (strtotime($start_date) >= strtotime($end_date)) {
                    $message = "Start date must be before end date!";
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE intake SET name = ?, start_date = ?, end_date = ?, description = ?, updated_at = NOW() WHERE id = ?");
                        if ($stmt->execute([$name, $start_date, $end_date, $description, $id])) {
                            $message = "Intake updated successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to update intake!";
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'delete_intake':
                $id = $_POST['intake_id'];
                
                // Check if intake has students
                $student_check = $pdo->prepare("SELECT COUNT(*) FROM student_profile WHERE intake_id = ?");
                $student_check->execute([$id]);
                $student_count = $student_check->fetchColumn();
                
                if ($student_count > 0) {
                    $message = "Cannot delete intake with {$student_count} enrolled student(s)! Please reassign students first.";
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM intake WHERE id = ?");
                        if ($stmt->execute([$id])) {
                            $message = "Intake deleted successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to delete intake!";
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
        }
    }
}

// Get filters
$searchQuery = $_GET['search'] ?? '';

// Build query with filters
$query = "
    SELECT i.*,
           CASE 
               WHEN CURDATE() < i.start_date THEN 'Upcoming'
               WHEN CURDATE() > i.end_date THEN 'Past'
               ELSE 'Active'
           END as status,
           (SELECT COUNT(*) FROM student_profile sp WHERE sp.intake_id = i.id) as student_count
    FROM intake i 
    WHERE 1=1";

$params = [];

if ($searchQuery) {
    $query .= " AND (i.name LIKE ? OR i.description LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY i.start_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$intakes = $stmt->fetchAll();

// Get intake for editing if specified
$editIntake = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM intake WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editIntake = $stmt->fetch();
}

// Get intake details for viewing if specified
$viewIntake = null;
$intakeStudents = [];
if (isset($_GET['view'])) {
    $intake_id = $_GET['view'];
    $intake_stmt = $pdo->prepare("
        SELECT i.*,
               CASE 
                   WHEN CURDATE() < i.start_date THEN 'Upcoming'
                   WHEN CURDATE() > i.end_date THEN 'Past'
                   ELSE 'Active'
               END as status,
               (SELECT COUNT(*) FROM student_profile sp WHERE sp.intake_id = i.id) as student_count
        FROM intake i 
        WHERE i.id = ?
    ");
    $intake_stmt->execute([$intake_id]);
    $viewIntake = $intake_stmt->fetch();
    
    if ($viewIntake) {
        // Get students in this intake
        $students_stmt = $pdo->prepare("
            SELECT sp.*, u.username, u.email as user_email, p.name as programme_name
            FROM student_profile sp 
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN programme p ON sp.programme_id = p.id
            WHERE sp.intake_id = ?
            ORDER BY sp.full_name
        ");
        $students_stmt->execute([$intake_id]);
        $intakeStudents = $students_stmt->fetchAll();
    }
}

// Add intake table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS intake (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Add intake_id to student_profile
    $pdo->exec("ALTER TABLE student_profile ADD COLUMN IF NOT EXISTS intake_id INT");
    $pdo->exec("ALTER TABLE student_profile ADD CONSTRAINT FK_student_intake FOREIGN KEY (intake_id) REFERENCES intake(id) ON DELETE SET NULL");
} catch (Exception $e) {
    // Table might already exist
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Intakes - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Unique Styles for Intakes Page */
        .intake-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .intake-card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 2px 8px var(--shadow);
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .intake-card:hover {
            transform: translateY(-5px);
        }
        
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active {
            background: var(--success);
            color: white;
        }
        
        .status-upcoming {
            background: var(--info);
            color: white;
        }
        
        .status-past {
            background: var(--gray);
            color: var(--text-dark);
        }
        
        .intake-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .intake-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-green);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .intake-info h3 {
            margin: 0 0 5px 0;
            color: var(--primary-green);
        }
        
        .intake-dates {
            color: var(--text-light);
            font-size: 14px;
        }
        
        .intake-stats {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-item h4 {
            margin: 0;
            font-size: 24px;
            color: var(--primary-orange);
        }
        
        .stat-item p {
            margin: 5px 0 0;
            font-size: 12px;
            color: var(--text-light);
        }
        
        .intake-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
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
                <a href="manage_intakes.php" class="nav-item active">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Intakes</span>
                </a>
                <a href="manage_sessions.php" class="nav-item">
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

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-calendar-alt"></i> Intake Management</h1>
            <p>Create, edit, and manage student intake periods</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Navigation Actions -->
        <div class="action-bar">
            <div class="action-left">
                <?php if (isset($_GET['view'])): ?>
                    <a href="manage_intakes.php" class="btn btn-orange">
                        <i class="fas fa-arrow-left"></i> Back to Intakes
                    </a>
                <?php endif; ?>
            </div>
            <div class="action-right">
                <?php if (!isset($_GET['view'])): ?>
                    <button onclick="showAddForm()" class="btn btn-green">
                        <i class="fas fa-plus"></i> Add New Intake
                    </button>
                    <a href="?action=export_intakes" class="btn btn-info">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="filters-section">
            <div class="filters-card">
                <form method="GET" class="filters-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="search">Search Intakes</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search by name or description">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-green">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <a href="manage_intakes.php" class="btn btn-orange">
                                <i class="fas fa-refresh"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add/Edit Intake Form -->
        <div class="form-section" id="intakeForm" style="<?php echo $editIntake ? 'display: block;' : 'display: none;'; ?>">
            <div class="form-card">
                <h2>
                    <i class="fas fa-<?php echo $editIntake ? 'edit' : 'plus'; ?>"></i>
                    <?php echo $editIntake ? 'Edit Intake' : 'Add New Intake'; ?>
                </h2>
                
                <form method="POST" class="school-form">
                    <input type="hidden" name="action" value="<?php echo $editIntake ? 'edit_intake' : 'add_intake'; ?>">
                    <?php if ($editIntake): ?>
                        <input type="hidden" name="intake_id" value="<?php echo $editIntake['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Intake Name *</label>
                            <input type="text" id="name" name="name" required maxlength="100" 
                                   placeholder="e.g., Fall 2024" value="<?php echo htmlspecialchars($editIntake['name'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date *</label>
                            <input type="date" id="start_date" name="start_date" required 
                                   value="<?php echo htmlspecialchars($editIntake['start_date'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="end_date">End Date *</label>
                            <input type="date" id="end_date" name="end_date" required 
                                   value="<?php echo htmlspecialchars($editIntake['end_date'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" 
                                      placeholder="Brief description of the intake"><?php echo htmlspecialchars($editIntake['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-green">
                            <i class="fas fa-save"></i> <?php echo $editIntake ? 'Update Intake' : 'Create Intake'; ?>
                        </button>
                        <button type="button" onclick="hideForm()" class="btn btn-orange">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($viewIntake): ?>
            <!-- Intake Details View -->
            <div class="school-header-card">
                <div class="school-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="school-info">
                    <h2><?php echo htmlspecialchars($viewIntake['name']); ?></h2>
                    <p class="school-info">Dates: <?php echo date('M d, Y', strtotime($viewIntake['start_date'])) . ' - ' . date('M d, Y', strtotime($viewIntake['end_date'])); ?></p>
                    <p class="school-info">Status: <span class="status-badge status-<?php echo strtolower($viewIntake['status']); ?>"><?php echo $viewIntake['status']; ?></span></p>
                    <p class="school-description"><?php echo htmlspecialchars($viewIntake['description'] ?? 'No description available'); ?></p>
                </div>
                <div class="school-actions">
                    <a href="?edit=<?php echo $viewIntake['id']; ?>" class="btn btn-orange">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $viewIntake['student_count']; ?></h3>
                        <p>Total Students</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo date('M Y', strtotime($viewIntake['created_at'])); ?></h3>
                        <p>Created</p>
                    </div>
                </div>
            </div>

            <!-- Students Section -->
            <div class="section-card">
                <div class="section-header">
                    <h3><i class="fas fa-users"></i> Students</h3>
                    <div class="section-actions">
                        <input type="text" class="search-input" placeholder="Search students..." onkeyup="filterTable(this, 'studentsTable')">
                    </div>
                </div>
                <?php if (empty($intakeStudents)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h4>No Students</h4>
                        <p>No students in this intake</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="users-table" id="studentsTable">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Programme</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($intakeStudents as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['user_email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['programme_name'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Intakes Grid (Unique View) -->
            <div class="intake-grid">
                <?php foreach ($intakes as $intake): ?>
                    <div class="intake-card">
                        <span class="status-badge status-<?php echo strtolower($intake['status']); ?>"><?php echo $intake['status']; ?></span>
                        <div class="intake-header">
                            <div class="intake-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="intake-info">
                                <h3><?php echo htmlspecialchars($intake['name']); ?></h3>
                                <p class="intake-dates">
                                    <?php echo date('M d, Y', strtotime($intake['start_date'])) . ' - ' . date('M d, Y', strtotime($intake['end_date'])); ?>
                                </p>
                            </div>
                        </div>
                        <p class="school-description"><?php echo htmlspecialchars(substr($intake['description'] ?? '', 0, 100)) . (strlen($intake['description'] ?? '') > 100 ? '...' : ''); ?></p>
                        <div class="intake-stats">
                            <div class="stat-item">
                                <h4><?php echo $intake['student_count']; ?></h4>
                                <p>Students</p>
                            </div>
                        </div>
                        <div class="intake-actions">
                            <a href="?view=<?php echo $intake['id']; ?>" class="btn btn-info btn-sm">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="?edit=<?php echo $intake['id']; ?>" class="btn btn-orange btn-sm">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <?php if ($intake['student_count'] == 0): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this intake?');">
                                    <input type="hidden" name="action" value="delete_intake">
                                    <input type="hidden" name="intake_id" value="<?php echo $intake['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        function showAddForm() {
            document.getElementById('intakeForm').style.display = 'block';
            document.getElementById('name').focus();
        }
        
        function hideForm() {
            document.getElementById('intakeForm').style.display = 'none';
        }

        function filterTable(input, tableId) {
            const filter = input.value.toLowerCase();
            const table = document.getElementById(tableId);
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let show = false;
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j] && cells[j].textContent.toLowerCase().includes(filter)) {
                        show = true;
                        break;
                    }
                }
                rows[i].style.display = show ? '' : 'none';
            }
        }
        
        // Auto-show form if editing
        <?php if ($editIntake): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showAddForm();
            });
        <?php endif; ?>

        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>