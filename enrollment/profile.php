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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $staff_id = trim($_POST['staff_id']);
    $email = trim($_POST['email']);
    
    try {
        // Update staff profile
        $stmt = $pdo->prepare("UPDATE staff_profile SET full_name = ?, staff_id = ? WHERE user_id = ?");
        $stmt->execute([$full_name, $staff_id, currentUserId()]);
        
        // Update user email
        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$email, currentUserId()]);
        
        $message = "Profile updated successfully!";
        $messageType = "success";
        
        // Refresh profile data
        $stmt = $pdo->prepare("SELECT sp.* FROM staff_profile sp WHERE sp.user_id = ?");
        $stmt->execute([currentUserId()]);
        $profile = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT u.* FROM users u WHERE u.id = ?");
        $stmt->execute([currentUserId()]);
        $user = $stmt->fetch();
    } catch (Exception $e) {
        $message = "Error updating profile: " . $e->getMessage();
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - LSC SRMS</title>
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
            <p>Manage your profile information</p>
        </div>

        <?php if (isset($message)): ?>
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
                <form method="POST">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="staff_id">Staff ID</label>
                        <input type="text" id="staff_id" name="staff_id" value="<?php echo htmlspecialchars($profile['staff_id'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="nrc">NRC Number</label>
                        <input type="text" id="nrc" name="nrc" value="<?php echo htmlspecialchars($profile['NRC'] ?? ''); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <input type="text" id="gender" name="gender" value="<?php echo htmlspecialchars($profile['gender'] ?? ''); ?>" readonly>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>