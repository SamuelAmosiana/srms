<?php
session_start();
require_once '../config.php';
require_once '../auth.php';

// Check if user is logged in and has student role
if (!currentUserId() || !currentUserHasRole('Student', $pdo)) {
    header('Location: ../student_login.php');
    exit();
}

// Get student profile with user account information
$stmt = $pdo->prepare("SELECT sp.*, u.email, u.contact, u.created_at, u.is_active FROM student_profile sp JOIN users u ON sp.user_id = u.id WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$student = $stmt->fetch();

if (!$student) {
    // Student profile not found
    header('Location: ../student_login.php');
    exit();
}

// Handle form submissions
$message = '';
$messageType = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $email = $_POST['email'];
    $contact = $_POST['contact'];
    
    try {
        // Update user information
        $stmt = $pdo->prepare("UPDATE users SET email = ?, contact = ? WHERE id = ?");
        $stmt->execute([$email, $contact, currentUserId()]);
        
        $message = "Profile updated successfully!";
        $messageType = 'success';
        
        // Refresh student data
        $stmt = $pdo->prepare("SELECT sp.*, u.email, u.contact, u.created_at, u.is_active FROM student_profile sp JOIN users u ON sp.user_id = u.id WHERE sp.user_id = ?");
        $stmt->execute([currentUserId()]);
        $student = $stmt->fetch();
    } catch (Exception $e) {
        $message = "Error updating profile: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([currentUserId()]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($current_password, $user['password_hash'])) {
        // Check if new passwords match
        if ($new_password === $confirm_password) {
            // Check password strength
            if (strlen($new_password) >= 6) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$hashed_password, currentUserId()]);
                
                $message = "Password changed successfully!";
                $messageType = 'success';
            } else {
                $message = "Password must be at least 6 characters long.";
                $messageType = 'error';
            }
        } else {
            $message = "New passwords do not match.";
            $messageType = 'error';
        }
    } else {
        $message = "Current password is incorrect.";
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Settings - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student-dashboard.css">
    <style>
        /* Simple icon replacements using CSS */
        .icon-bars:before { content: "≡"; }
        .icon-moon:before { content: "☾"; }
        .icon-user-circle:before { content: "👤"; }
        .icon-chevron-down:before { content: "▼"; }
        .icon-user:before { content: "👨"; }
        .icon-cog:before { content: "⚙"; }
        .icon-sign-out-alt:before { content: "🚪"; }
        .icon-tachometer-alt:before { content: "📊"; }
        .icon-home:before { content: "⌂"; }
        .icon-chart-line:before { content: "📈"; }
        .icon-clipboard-check:before { content: "📋"; }
        .icon-file-alt:before { content: "📄"; }
        .icon-money-bill-wave:before { content: "💰"; }
        .icon-bed:before { content: "🛏"; }
        .icon-book:before { content: "📚"; }
        .icon-graduation-cap:before { content: "🎓"; }
        .icon-lock:before { content: "🔒"; }
        .icon-save:before { content: "💾"; }
        .icon-key:before { content: "🔑"; }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: bold;
            color: var(--primary-green);
            margin-bottom: 5px;
        }
        
        .form-control {
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-text {
            color: var(--text-light);
            font-size: 12px;
            margin-top: 5px;
        }
        
        .password-requirements {
            background-color: var(--light-gray);
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .password-requirements li {
            margin-bottom: 5px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="student-layout" data-theme="light">
    <!-- Top Navigation Bar -->
    <nav class="top-nav">
        <div class="nav-left">
            <div class="logo-container">
                <img src="../assets/images/lsc-logo.png" alt="LSC Logo" class="logo" onerror="this.style.display='none'">
                <span class="logo-text">LSC SRMS</span>
            </div>
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="icon-bars"></i>
            </button>
        </div>
        
        <div class="nav-right">
            <div class="user-info">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?></span>
                <span class="student-id">(<?php echo htmlspecialchars($student['student_number'] ?? 'N/A'); ?>)</span>
            </div>
            
            <div class="nav-actions">
                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="icon-moon" id="theme-icon"></i>
                </button>
                
                <div class="dropdown">
                    <button class="profile-btn" onclick="toggleDropdown()">
                        <i class="icon-user-circle"></i>
                        <i class="icon-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="profile.php"><i class="icon-user"></i> View Profile</a>
                        <a href="settings.php" class="active"><i class="icon-cog"></i> Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="../logout.php"><i class="icon-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3><i class="icon-tachometer-alt"></i> Student Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="icon-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Academic</h4>
                <a href="view_results.php" class="nav-item">
                    <i class="icon-chart-line"></i>
                    <span>View Results</span>
                </a>
                <a href="register_courses.php" class="nav-item">
                    <i class="icon-clipboard-check"></i>
                    <span>Register Courses</span>
                </a>
                <a href="view_docket.php" class="nav-item">
                    <i class="icon-file-alt"></i>
                    <span>View Docket</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Finance & Accommodation</h4>
                <a href="view_fee_balance.php" class="nav-item">
                    <i class="icon-money-bill-wave"></i>
                    <span>View Fee Balance</span>
                </a>
                <a href="accommodation.php" class="nav-item">
                    <i class="icon-bed"></i>
                    <span>Accommodation</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="icon-cog"></i> Account Settings</h1>
            <p>Manage your account preferences and security settings</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Profile Settings -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="icon-user"></i> Profile Information</h3>
            </div>
            <div class="panel-content">
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" class="form-control" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label for="student_number">Student Number</label>
                            <input type="text" id="student_number" name="student_number" value="<?php echo htmlspecialchars($student['student_number']); ?>" class="form-control" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="contact">Phone Number</label>
                            <input type="text" id="contact" name="contact" value="<?php echo htmlspecialchars($student['contact']); ?>" class="form-control">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><i class="icon-save"></i> Save Profile Changes</button>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="icon-lock"></i> Change Password</h3>
            </div>
            <div class="panel-content">
                <div class="password-requirements">
                    <h4>Password Requirements:</h4>
                    <ul>
                        <li>Must be at least 6 characters long</li>
                        <li>Should include a mix of letters and numbers</li>
                        <li>Avoid using easily guessable information</li>
                    </ul>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="change_password" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-warning"><i class="icon-key"></i> Change Password</button>
                </form>
            </div>
        </div>

        <!-- Account Information -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="icon-user"></i> Account Information</h3>
            </div>
            <div class="panel-content">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Account Created:</label>
                        <span><?php echo isset($student['created_at']) ? date('F j, Y', strtotime($student['created_at'])) : 'N/A'; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Last Login:</label>
                        <span><?php echo isset($_SESSION['last_login']) ? date('F j, Y g:i A', strtotime($_SESSION['last_login'])) : 'First login'; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Account Status:</label>
                        <span><?php echo (isset($student['is_active']) && $student['is_active']) ? 'Active' : 'Inactive'; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Toggle theme function
        function toggleTheme() {
            const currentTheme = document.body.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.body.setAttribute('data-theme', newTheme);
            localStorage.setItem('studentTheme', newTheme);
            
            // Update theme icon
            const themeIcon = document.getElementById('theme-icon');
            if (themeIcon) {
                themeIcon.textContent = newTheme === 'light' ? '☾' : '☀';
            }
        }
        
        // Initialize theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('studentTheme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
            
            // Update theme icon
            const themeIcon = document.getElementById('theme-icon');
            if (themeIcon) {
                themeIcon.textContent = savedTheme === 'light' ? '☾' : '☀';
            }
        });
        
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
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const profileBtn = document.querySelector('.profile-btn');
            
            if (dropdown && profileBtn) {
                if (!dropdown.contains(event.target) && !profileBtn.contains(event.target)) {
                    dropdown.classList.remove('show');
                }
            }
        });
    </script>
</body>
</html>