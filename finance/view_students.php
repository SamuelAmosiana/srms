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

// Handle search query
$search = $_GET['search'] ?? '';
$where_clause = '';
$params = [];

if (!empty($search)) {
    $where_clause = "WHERE sp.full_name LIKE ? OR sp.student_number LIKE ?";
    $params = ["%$search%", "%$search%"];
}

$query = "SELECT sp.student_number as student_id, sp.full_name, u.email, p.name as programme 
          FROM student_profile sp 
          JOIN users u ON sp.user_id = u.id 
          LEFT JOIN programme p ON sp.programme_id = p.id 
          $where_clause 
          ORDER BY sp.full_name";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Students - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
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
                <a href="view_students.php" class="nav-item active">
                    <i class="fas fa-users"></i>
                    <span>View Students</span>
                </a>
                <a href="manage_fees.php" class="nav-item">
                    <i class="fas fa-money-bill"></i>
                    <span>Manage Fees & Finances</span>
                </a>
                <a href="results_access.php" class="nav-item">
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
            <h1><i class="fas fa-users"></i> View Students</h1>
            <p>Search and view student information</p>
        </div>
        
        <!-- Search Form -->
        <div class="form-container">
            <h2>Search Students</h2>
            <form method="GET" action="view_students.php">
                <div class="form-group">
                    <label for="search">Search by Name or Student ID</label>
                    <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Enter name or student ID">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn green">Search</button>
                    <button type="button" class="btn orange" onclick="window.location.href='view_students.php'">Clear</button>
                </div>
            </form>
        </div>
        
        <!-- Students Table -->
        <div class="table-container">
            <h2>Student List</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Programme</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="5">No students found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['programme'] ?? 'N/A'); ?></td>
                                <td>
                                    <a class="btn small green" href="payment_statement.php?student_number=<?php echo urlencode($student['student_id']); ?>">
                                        <i class="fas fa-receipt"></i> Payment Statement
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>