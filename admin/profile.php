<?php
session_start();
require_once '../config.php';
require_once '../auth.php';

// Check if user is logged in
if (!currentUserId()) {
    header('Location: ../login.php');
    exit();
}

// Check if user has admin role or profile access permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('profile_access', $pdo)) {
    header('Location: ../login.php');
    exit();
}

// Try to add bio column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE admin_profile ADD COLUMN bio TEXT");
} catch (Exception $e) {
    // Column might already exist, ignore error
}

// Get user info - handle case where bio column might not exist yet
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, r.name as role, ap.full_name, ap.staff_id, ap.bio 
        FROM users u 
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        LEFT JOIN admin_profile ap ON u.id = ap.user_id 
        WHERE u.id = ?
    ");
    $stmt->execute([currentUserId()]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    // If bio column doesn't exist, select without it
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, r.name as role, ap.full_name, ap.staff_id
        FROM users u 
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        LEFT JOIN admin_profile ap ON u.id = ap.user_id 
        WHERE u.id = ?
    ");
    $stmt->execute([currentUserId()]);
    $user = $stmt->fetch();
    // Add bio as null to avoid undefined index errors
    $user['bio'] = null;
}

// Handle bio update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_bio') {
    $bio = trim($_POST['bio']);
    
    try {
        // Check if admin profile exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_profile WHERE user_id = ?");
        $stmt->execute([currentUserId()]);
        $profile_exists = $stmt->fetchColumn();
        
        if ($profile_exists) {
            // Update existing profile
            $stmt = $pdo->prepare("UPDATE admin_profile SET bio = ? WHERE user_id = ?");
            $stmt->execute([$bio, currentUserId()]);
        } else {
            // Create new profile entry with bio
            $stmt = $pdo->prepare("INSERT INTO admin_profile (user_id, bio) VALUES (?, ?)");
            $stmt->execute([currentUserId(), $bio]);
        }
        
        $message = "Bio updated successfully!";
        $messageType = 'success';
        
        // Refresh user data
        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.email, r.name as role, ap.full_name, ap.staff_id, ap.bio 
                FROM users u 
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                LEFT JOIN admin_profile ap ON u.id = ap.user_id 
                WHERE u.id = ?
            ");
            $stmt->execute([currentUserId()]);
            $user = $stmt->fetch();
        } catch (Exception $e) {
            // If bio column doesn't exist, select without it
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.email, r.name as role, ap.full_name, ap.staff_id
                FROM users u 
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                LEFT JOIN admin_profile ap ON u.id = ap.user_id 
                WHERE u.id = ?
            ");
            $stmt->execute([currentUserId()]);
            $user = $stmt->fetch();
            // Add bio as null to avoid undefined index errors
            $user['bio'] = null;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Create admin_profile table if not exists (with bio column)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_profile (
            user_id INT PRIMARY KEY,
            full_name VARCHAR(255),
            staff_id VARCHAR(50),
            bio TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
} catch (Exception $e) {
    // Table might already exist
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - LSC SRMS</title>
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
                        <a href="profile.php" class="active"><i class="fas fa-user"></i> View Profile</a>
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
            <h1><i class="fas fa-user"></i> Admin Profile</h1>
            <p>View and update your profile information</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Profile Details -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-user-circle"></i> Profile Details</h3>
            </div>
            <div class="panel-content">
                <div class="profile-grid">
                    <div class="profile-item">
                        <label>Full Name:</label>
                        <span><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-item">
                        <label>Staff ID:</label>
                        <span><?php echo htmlspecialchars($user['staff_id'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-item">
                        <label>Username:</label>
                        <span><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div class="profile-item">
                        <label>Email:</label>
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="profile-item">
                        <label>Role:</label>
                        <span><?php echo htmlspecialchars($user['role']); ?></span>
                    </div>
                    <div class="profile-item full-width">
                        <label>Bio:</label>
                        <p><?php echo htmlspecialchars($user['bio'] ?? 'No bio provided.'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Update Bio -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-edit"></i> Update Bio</h3>
            </div>
            <div class="panel-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_bio">
                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="5" placeholder="Enter your bio..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Bio</button>
                </form>
            </div>
        </div>
    </main>

    <style>
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .profile-item {
            display: flex;
            flex-direction: column;
        }
        
        .profile-item label {
            font-weight: bold;
            color: var(--primary-green);
            margin-bottom: 5px;
        }
        
        .profile-item span, .profile-item p {
            color: var(--text-dark);
        }
        
        .profile-item.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            resize: vertical;
        }
    </style>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>