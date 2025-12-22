<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has lecturer role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Lecturer', $pdo);

// Try to add bio column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE staff_profile ADD COLUMN bio TEXT");
} catch (Exception $e) {
    // Column might already exist, ignore error
}

// Get lecturer profile
try {
    $stmt = $pdo->prepare("
        SELECT sp.full_name, sp.staff_id, sp.NRC, sp.gender, sp.qualification, sp.bio,
               u.email, u.contact as phone
        FROM staff_profile sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.user_id = ?
    ");
    $stmt->execute([currentUserId()]);
    $lecturer = $stmt->fetch();
} catch (Exception $e) {
    // If bio column doesn't exist, select without it
    $stmt = $pdo->prepare("
        SELECT sp.full_name, sp.staff_id, sp.NRC, sp.gender, sp.qualification,
               u.email, u.contact as phone
        FROM staff_profile sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.user_id = ?
    ");
    $stmt->execute([currentUserId()]);
    $lecturer = $stmt->fetch();
    $lecturer['bio'] = null; // Add bio as null to avoid undefined index
}

// Handle bio update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_bio') {
    $bio = trim($_POST['bio']);
    
    try {
        // Check if staff profile exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM staff_profile WHERE user_id = ?");
        $stmt->execute([currentUserId()]);
        $profile_exists = $stmt->fetchColumn();
        
        if ($profile_exists) {
            // Update existing profile
            $stmt = $pdo->prepare("UPDATE staff_profile SET bio = ? WHERE user_id = ?");
            $stmt->execute([$bio, currentUserId()]);
        } else {
            // Create new profile entry with bio
            $stmt = $pdo->prepare("INSERT INTO staff_profile (user_id, bio) VALUES (?, ?)");
            $stmt->execute([currentUserId(), $bio]);
        }
        
        $message = "Bio updated successfully!";
        $messageType = 'success';
        
        // Refresh profile data
        try {
            $stmt = $pdo->prepare("
                SELECT sp.full_name, sp.staff_id, sp.NRC, sp.gender, sp.qualification, sp.bio,
                       u.email, u.contact as phone
                FROM staff_profile sp
                JOIN users u ON sp.user_id = u.id
                WHERE sp.user_id = ?
            ");
            $stmt->execute([currentUserId()]);
            $lecturer = $stmt->fetch();
        } catch (Exception $e) {
            $stmt = $pdo->prepare("
                SELECT sp.full_name, sp.staff_id, sp.NRC, sp.gender, sp.qualification,
                       u.email, u.contact as phone
                FROM staff_profile sp
                JOIN users u ON sp.user_id = u.id
                WHERE sp.user_id = ?
            ");
            $stmt->execute([currentUserId()]);
            $lecturer = $stmt->fetch();
            $lecturer['bio'] = null;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Create staff_profile table if not exists (with bio column)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS staff_profile (
            user_id INT PRIMARY KEY,
            full_name VARCHAR(255),
            staff_id VARCHAR(50),
            NRC VARCHAR(50),
            gender ENUM('Male','Female','Other'),
            qualification VARCHAR(255),
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
    <title>Lecturer Profile - LSC SRMS</title>
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($lecturer['full_name'] ?? 'Lecturer'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($lecturer['staff_id'] ?? 'N/A'); ?>)</span>
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
                        <a href="profile" class="active"><i class="fas fa-user"></i> View Profile</a>
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
            <h3><i class="fas fa-tachometer-alt"></i> Lecturer Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Results Management</h4>
                <a href="upload_results.php" class="nav-item">
                    <i class="fas fa-upload"></i>
                    <span>Upload Results</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Student Management</h4>
                <a href="view_students.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>View Students</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports</h4>
                <a href="manage_reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Manage Reports</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Profile</h4>
                <a href="profile.php" class="nav-item active">
                    <i class="fas fa-user"></i>
                    <span>View Profile</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-user"></i> Lecturer Profile</h1>
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
                        <span><?php echo htmlspecialchars($lecturer['full_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-item">
                        <label>Staff ID:</label>
                        <span><?php echo htmlspecialchars($lecturer['staff_id'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-item">
                        <label>Email:</label>
                        <span><?php echo htmlspecialchars($lecturer['email'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-item">
                        <label>Phone:</label>
                        <span><?php echo htmlspecialchars($lecturer['phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-item">
                        <label>Department:</label>
                        <span><?php echo htmlspecialchars($lecturer['department_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-item">
                        <label>Address:</label>
                        <span><?php echo htmlspecialchars($lecturer['address'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-item">
                        <label>Date of Birth:</label>
                        <span><?php echo htmlspecialchars($lecturer['date_of_birth'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-item full-width">
                        <label>Bio:</label>
                        <p><?php echo htmlspecialchars($lecturer['bio'] ?? 'No bio provided.'); ?></p>
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
                        <textarea id="bio" name="bio" rows="5" placeholder="Enter your bio..."><?php echo htmlspecialchars($lecturer['bio'] ?? ''); ?></textarea>
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