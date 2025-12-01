<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has Sub Admin role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Sub Admin (Finance)', $pdo);

// Get sub admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Try to add the results_access column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE student_profile ADD COLUMN IF NOT EXISTS results_access TINYINT(1) DEFAULT 1");
} catch (Exception $e) {
    // Column might already exist or there's an issue, ignore error
}

// Handle single student results access update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_access'])) {
    $student_id = $_POST['student_id'];
    $results_access = $_POST['results_access'] === '1' ? 1 : 0;
    
    // Update results access
    $stmt = $pdo->prepare("UPDATE student_profile SET results_access = ? WHERE student_number = ?");
    $stmt->execute([$results_access, $student_id]);
    
    $success_message = "Results access updated successfully!";
}

// Handle bulk results access update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_access'])) {
    $student_ids = $_POST['selected_students'] ?? [];
    $bulk_action = $_POST['bulk_action'] === 'grant' ? 1 : 0;
    
    if (!empty($student_ids)) {
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        $stmt = $pdo->prepare("UPDATE student_profile SET results_access = ? WHERE student_number IN ($placeholders)");
        $stmt->execute(array_merge([$bulk_action], $student_ids));
        
        $success_message = "Bulk results access updated successfully!";
    } else {
        $error_message = "No students selected for bulk update.";
    }
}

// Fetch student data with fee balances and results access status
// Optional search filter
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
try {
    if ($q !== '') {
        $like = "%$q%";
        $stmt = $pdo->prepare("SELECT sp.student_number as student_id, sp.full_name, sp.balance, sp.results_access 
                               FROM student_profile sp
                               WHERE sp.student_number LIKE ? OR sp.full_name LIKE ?");
        $stmt->execute([$like, $like]);
    } else {
        $stmt = $pdo->query("SELECT sp.student_number as student_id, sp.full_name, sp.balance, sp.results_access 
                            FROM student_profile sp");
    }
} catch (Exception $e) {
    // If results_access column doesn't exist, select without it
    if ($q !== '') {
        $like = "%$q%";
        $stmt = $pdo->prepare("SELECT sp.student_number as student_id, sp.full_name, sp.balance, 1 as results_access 
                               FROM student_profile sp
                               WHERE sp.student_number LIKE ? OR sp.full_name LIKE ?");
        $stmt->execute([$like, $like]);
    } else {
        $stmt = $pdo->query("SELECT sp.student_number as student_id, sp.full_name, sp.balance, 1 as results_access 
                            FROM student_profile sp");
    }
}
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Results Access - LSC SRMS</title>
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($admin['full_name'] ?? 'Finance Administrator'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($admin['staff_id'] ?? 'N/A'); ?>)</span>
            </div>
            
            <div class="nav-actions">
                <a href="manage_programme_fees.php" class="nav-link" title="Programme Fees">
                    <i class="fas fa-file-invoice-dollar"></i>
                </a>
                
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
            <h3><i class="fas fa-tachometer-alt"></i> Finance Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Student Management</h4>
                <a href="view_students.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>View Students</span>
                </a>
                <a href="manage_fees.php" class="nav-item">
                    <i class="fas fa-money-bill"></i>
                    <span>Manage Fees & Finances</span>
                </a>
                <a href="results_access.php" class="nav-item active">
                    <i class="fas fa-lock"></i>
                    <span>Manage Results Access</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Financial Operations</h4>
                <a href="manage_programme_fees.php" class="nav-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Programme Fees</span>
                </a>
                <a href="income_expenses.php" class="nav-item">
                    <i class="fas fa-chart-pie"></i>
                    <span>Income & Expenses</span>
                </a>
                <a href="finance_reports.php" class="nav-item">
                    <i class="fas fa-file-invoice"></i>
                    <span>Finance Reports</span>
                </a>
                <a href="registration_clearance.php" class="nav-item">
                    <i class="fas fa-user-check"></i>
                    <span>Registration Clearance</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-lock"></i> Manage Results Access</h1>
            <p>Control student access to results based on fee balances</p>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Bulk Access Form -->
        <div class="form-container">
            <h2>Bulk Results Access</h2>
            <form id="bulkForm" method="POST" action="results_access.php">
                <div class="form-group">
                    <label for="bulk_action">Action</label>
                    <select name="bulk_action" id="bulk_action" required>
                        <option value="grant">Grant Access</option>
                        <option value="restrict">Restrict Access</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" name="bulk_update_access" class="btn green">Apply to Selected</button>
                </div>
            </form>
        </div>
        
        <!-- Search & Student Results Access Table -->
        <div class="table-container">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <h2 style="margin:0;">Student Results Access</h2>
                <form method="GET" action="results_access.php" style="display:flex;gap:8px;align-items:center;">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search by Student # or Name" style="min-width:260px;">
                    <button type="submit" class="btn">Search</button>
                    <?php if ($q !== ''): ?>
                        <a class="btn" href="results_access.php">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll" onclick="toggleSelectAll()"></th>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Fee Balance (K)</th>
                        <th>Results Access</th>
                        <th>Individual Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_students[]" form="bulkForm" value="<?php echo $student['student_id']; ?>"></td>
                            <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td><?php echo number_format($student['balance'] ?? 0, 2); ?></td>
                            <td>
                                <span class="status <?php echo $student['results_access'] ? 'green' : 'orange'; ?>">
                                    <?php echo $student['results_access'] ? 'Granted' : 'Restricted'; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="results_access.php">
                                    <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                    <select name="results_access" onchange="this.form.submit()">
                                        <option value="1" <?php echo $student['results_access'] ? 'selected' : ''; ?>>Grant Access</option>
                                        <option value="0" <?php echo !$student['results_access'] ? 'selected' : ''; ?>>Restrict Access</option>
                                    </select>
                                    <input type="hidden" name="update_access" value="1">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('input[name="selected_students[]"]');
            const selectAll = document.getElementById('selectAll');
            checkboxes.forEach(checkbox => checkbox.checked = selectAll.checked);
        }
    </script>
    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>