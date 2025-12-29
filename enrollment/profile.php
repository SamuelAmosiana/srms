<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has enrollment officer role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Enrollment Officer', $pdo);

// Get enrollment officer profile
$stmt = $pdo->prepare("SELECT sp.* FROM staff_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$profile = $stmt->fetch();

// Get user information
$stmt = $pdo->prepare("SELECT u.* FROM users u WHERE u.id = ?");
$stmt->execute([currentUserId()]);
$user = $stmt->fetch();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (password_verify($current_password, $user['password_hash'])) {
            // Check if new passwords match
            if ($new_password === $confirm_password) {
                // Check password strength (at least 8 characters)
                if (strlen($new_password) >= 8) {
                    // Hash new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password in database
                    try {
                        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, currentUserId()]);
                        
                        $message = "Password changed successfully!";
                        $messageType = "success";
                    } catch (Exception $e) {
                        $message = "Error updating password: " . $e->getMessage();
                        $messageType = "error";
                    }
                } else {
                    $message = "New password must be at least 8 characters long.";
                    $messageType = "error";
                }
            } else {
                $message = "New passwords do not match.";
                $messageType = "error";
            }
        } else {
            $message = "Current password is incorrect.";
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - LSC SRMS</title>
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($profile['full_name'] ?? 'Enrollment Officer'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($profile['staff_id'] ?? 'N/A'); ?>)</span>
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
            <h3><i class="fas fa-tachometer-alt"></i> Enrollment Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Applications</h4>
                <a href="undergraduate_applications.php" class="nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Undergraduate</span>
                </a>
                <a href="short_courses_applications.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <span>Short Courses</span>
                </a>
                <a href="corporate_training_applications.php" class="nav-item">
                    <i class="fas fa-building"></i>
                    <span>Corporate Training</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>My Approvals</h4>
                <a href="my_approvals.php" class="nav-item">
                    <i class="fas fa-thumbs-up"></i>
                    <span>My Approvals</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Registered Students</h4>
                <a href="registered_students.php" class="nav-item">
                    <i class="fas fa-user-graduate"></i>
                    <span>Registered Students</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports</h4>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Enrollment Reports</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-user"></i> My Profile</h1>
            <p>View your profile information</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($profile['full_name'] ?? 'Enrollment Officer'); ?></h2>
                    <p class="profile-role">Enrollment Officer</p>
                    <p class="profile-id">Staff ID: <?php echo htmlspecialchars($profile['staff_id'] ?? 'N/A'); ?></p>
                </div>
            </div>
            
            <div class="profile-details">
                <div class="profile-info-grid">
                    <div class="info-item">
                        <label>Full Name</label>
                        <p><?php echo htmlspecialchars($profile['full_name'] ?? 'N/A'); ?></p>
                    </div>
                    
                    <div class="info-item">
                        <label>Staff ID</label>
                        <p><?php echo htmlspecialchars($profile['staff_id'] ?? 'N/A'); ?></p>
                    </div>
                    
                    <div class="info-item">
                        <label>Email</label>
                        <p><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></p>
                    </div>
                    
                    <div class="info-item">
                        <label>NRC Number</label>
                        <p><?php echo htmlspecialchars($profile['NRC'] ?? 'N/A'); ?></p>
                    </div>
                    
                    <div class="info-item">
                        <label>Gender</label>
                        <p><?php echo htmlspecialchars($profile['gender'] ?? 'N/A'); ?></p>
                    </div>
                    
                    <div class="info-item">
                        <label>Qualification</label>
                        <p><?php echo htmlspecialchars($profile['qualification'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Password Change Section -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
            </div>
            <div class="panel-content">
                <form method="POST">
                    <input type="hidden" name="change_password" value="1">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="8">
                        <small class="form-text">Password must be at least 8 characters long</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>