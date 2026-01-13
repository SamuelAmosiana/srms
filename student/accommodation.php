<?php
session_start();
require_once '../config.php';
require_once '../auth/auth.php';

// Check if user is logged in and has student role
if (!currentUserId() || !currentUserHasRole('Student', $pdo)) {
    header('Location: ../auth/student_login.php');
    exit();
}

// Get student profile
$stmt = $pdo->prepare("SELECT sp.*, u.email, u.contact, p.name as programme_name, i.name as intake_name FROM student_profile sp JOIN users u ON sp.user_id = u.id LEFT JOIN programme p ON sp.programme_id = p.id LEFT JOIN intake i ON sp.intake_id = i.id WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$student = $stmt->fetch();

if (!$student) {
    // Student profile not found
    header('Location: ../auth/student_login.php');
    exit();
}

// Create accommodation tables if they don't exist
try {
    // Accommodation applications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS accommodation_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        room_preference ENUM('single', 'double', 'triple') DEFAULT 'double',
        block_preference VARCHAR(50),
        medical_conditions TEXT,
        special_requests TEXT,
        status ENUM('pending', 'approved', 'rejected', 'allocated') DEFAULT 'pending',
        approved_date TIMESTAMP NULL,
        approved_by INT NULL,
        allocation_details TEXT,
        rejection_reason TEXT,
        FOREIGN KEY (student_id) REFERENCES student_profile(user_id) ON DELETE CASCADE,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    
    // Accommodation rooms table
    $pdo->exec("CREATE TABLE IF NOT EXISTS accommodation_rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        block_name VARCHAR(50) NOT NULL,
        room_number VARCHAR(20) NOT NULL,
        room_type ENUM('single', 'double', 'triple') NOT NULL,
        floor_number INT,
        capacity INT NOT NULL,
        occupied_spots INT DEFAULT 0,
        status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_room (block_name, room_number)
    )");
    
    // Student accommodation allocations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_accommodation (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        room_id INT NOT NULL,
        application_id INT,
        check_in_date DATE,
        check_out_date DATE,
        status ENUM('active', 'checked_out', 'terminated') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES student_profile(user_id) ON DELETE CASCADE,
        FOREIGN KEY (room_id) REFERENCES accommodation_rooms(id) ON DELETE CASCADE,
        FOREIGN KEY (application_id) REFERENCES accommodation_applications(id) ON DELETE SET NULL,
        UNIQUE KEY unique_student_allocation (student_id, status)
    )");
} catch (Exception $e) {
    // Tables might already exist
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'apply':
            try {
                // Check if student already has a pending application
                $checkStmt = $pdo->prepare("SELECT id FROM accommodation_applications WHERE student_id = ? AND status = 'pending'");
                $checkStmt->execute([currentUserId()]);
                
                if ($checkStmt->fetch()) {
                    $message = "You already have a pending accommodation application.";
                    $messageType = 'error';
                } else {
                    // Insert new accommodation application
                    $stmt = $pdo->prepare("INSERT INTO accommodation_applications (student_id, room_preference, block_preference, medical_conditions, special_requests) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        currentUserId(),
                        $_POST['room_preference'] ?? 'double',
                        $_POST['block_preference'] ?? '',
                        $_POST['medical_conditions'] ?? '',
                        $_POST['special_requests'] ?? ''
                    ]);
                    
                    $message = "Your accommodation application has been submitted successfully!";
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                $message = "Error submitting application: " . $e->getMessage();
                $messageType = 'error';
            }
            break;
    }
}

// Get student's current accommodation status
$currentApplication = null;
try {
    $stmt = $pdo->prepare("
        SELECT aa.*, ar.block_name, ar.room_number, sa.check_in_date, sa.check_out_date, sa.status as allocation_status
        FROM accommodation_applications aa
        LEFT JOIN student_accommodation sa ON aa.id = sa.application_id
        LEFT JOIN accommodation_rooms ar ON sa.room_id = ar.id
        WHERE aa.student_id = ?
        ORDER BY aa.application_date DESC
        LIMIT 1
    ");
    $stmt->execute([currentUserId()]);
    $currentApplication = $stmt->fetch();
} catch (Exception $e) {
    // No application found or error
    $currentApplication = null;
}

// Get all accommodation applications for history
$applicationsHistory = [];
try {
    $stmt = $pdo->prepare("
        SELECT aa.*, ar.block_name, ar.room_number
        FROM accommodation_applications aa
        LEFT JOIN student_accommodation sa ON aa.id = sa.application_id
        LEFT JOIN accommodation_rooms ar ON sa.room_id = ar.id
        WHERE aa.student_id = ?
        ORDER BY aa.application_date DESC
    ");
    $stmt->execute([currentUserId()]);
    $applicationsHistory = $stmt->fetchAll();
} catch (Exception $e) {
    // Error fetching history
    $applicationsHistory = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accommodation - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Font Awesome icons are now used throughout the application */
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-allocated {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-checked_out {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .application-form {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .card-header h3 {
            margin: 0;
            color: #333;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: bold;
            width: 150px;
            color: #666;
        }
        
        .detail-value {
            flex: 1;
        }
        
        .no-applications {
            text-align: center;
            padding: 20px;
            color: #666;
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
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <div class="nav-right">
            <div class="user-info">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?></span>
                <span class="student-id">(<?php echo htmlspecialchars($student['student_number'] ?? 'N/A'); ?>)</span>
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
            <h3><i class="fas fa-tachometer-alt"></i> Student Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Academic</h4>
                <a href="view_results.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>View Results</span>
                </a>
                <a href="register_courses.php" class="nav-item">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Register Courses</span>
                </a>
                <a href="view_docket.php" class="nav-item">
                    <i class="fas fa-file-alt"></i>
                    <span>View Docket</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Finance & Accommodation</h4>
                <a href="view_fee_balance.php" class="nav-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>View Fee Balance</span>
                </a>
                <a href="elearning.php" class="nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span>E-Learning (Moodle)</span>
                </a>
                <a href="accommodation.php" class="nav-item active">
                    <i class="fas fa-bed"></i>
                    <span>Accommodation</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-bed"></i> Accommodation Services</h1>
            <p>Apply for student accommodation and view your accommodation status</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Current Accommodation Status -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bed"></i> Current Accommodation Status</h3>
            </div>
            
            <?php if ($currentApplication): ?>
                <div class="detail-row">
                    <div class="detail-label">Status:</div>
                    <div class="detail-value">
                        <span class="status-badge status-<?php echo $currentApplication['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $currentApplication['status'])); ?>
                        </span>
                        <?php if (!empty($currentApplication['allocation_status'])): ?>
                            <span class="status-badge status-<?php echo $currentApplication['allocation_status']; ?>">
                                Allocation: <?php echo ucfirst(str_replace('_', ' ', $currentApplication['allocation_status'])); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($currentApplication['block_name']) && !empty($currentApplication['room_number'])): ?>
                    <div class="detail-row">
                        <div class="detail-label">Room Assignment:</div>
                        <div class="detail-value">
                            Block <?php echo htmlspecialchars($currentApplication['block_name']); ?>, 
                            Room <?php echo htmlspecialchars($currentApplication['room_number']); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($currentApplication['check_in_date'])): ?>
                    <div class="detail-row">
                        <div class="detail-label">Check-in Date:</div>
                        <div class="detail-value"><?php echo date('F j, Y', strtotime($currentApplication['check_in_date'])); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($currentApplication['check_out_date'])): ?>
                    <div class="detail-row">
                        <div class="detail-label">Check-out Date:</div>
                        <div class="detail-value"><?php echo date('F j, Y', strtotime($currentApplication['check_out_date'])); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($currentApplication['application_date'])): ?>
                    <div class="detail-row">
                        <div class="detail-label">Application Date:</div>
                        <div class="detail-value"><?php echo date('F j, Y g:i A', strtotime($currentApplication['application_date'])); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($currentApplication['rejection_reason'])): ?>
                    <div class="detail-row">
                        <div class="detail-label">Rejection Reason:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($currentApplication['rejection_reason']); ?></div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p>You haven't applied for accommodation yet.</p>
            <?php endif; ?>
        </div>

        <!-- Apply for Accommodation Form -->
        <?php if (!$currentApplication || $currentApplication['status'] == 'rejected'): ?>
            <div class="application-form">
                <h3><i class="fas fa-clipboard-check"></i> Apply for Accommodation</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="apply">
                    
                    <div class="form-group">
                        <label for="room_preference">Room Preference:</label>
                        <select id="room_preference" name="room_preference" required>
                            <option value="single">Single Room</option>
                            <option value="double" selected>Double Room</option>
                            <option value="triple">Triple Room</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="block_preference">Block Preference (Optional):</label>
                        <select id="block_preference" name="block_preference">
                            <option value="">No Preference</option>
                            <option value="Block A">Block A</option>
                            <option value="Block B">Block B</option>
                            <option value="Block C">Block C</option>
                            <option value="Block D">Block D</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="medical_conditions">Medical Conditions (Optional):</label>
                        <textarea id="medical_conditions" name="medical_conditions" rows="3" placeholder="Any medical conditions that may affect room placement..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="special_requests">Special Requests (Optional):</label>
                        <textarea id="special_requests" name="special_requests" rows="3" placeholder="Any special accommodation requests..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check-circle"></i> Submit Application
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Applications History -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Applications History</h3>
            </div>
            
            <?php if (!empty($applicationsHistory)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Room Preference</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applicationsHistory as $app): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($app['application_date'])); ?></td>
                                <td><?php echo ucfirst($app['room_preference']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $app['status']; ?>">
                                        <?php echo ucfirst($app['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($app['block_name']) && !empty($app['room_number'])): ?>
                                        Block <?php echo htmlspecialchars($app['block_name']); ?>, 
                                        Room <?php echo htmlspecialchars($app['room_number']); ?>
                                    <?php elseif (!empty($app['rejection_reason'])): ?>
                                        <?php echo htmlspecialchars(substr($app['rejection_reason'], 0, 50)); ?>...
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-applications">
                    <i class="fas fa-clock"></i>
                    <p>No accommodation applications found.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        }
        
        function toggleTheme() {
            const body = document.body;
            const themeIcon = document.getElementById('theme-icon');
            
            if (body.getAttribute('data-theme') === 'light') {
                body.setAttribute('data-theme', 'dark');
                themeIcon.textContent = '☀️';
            } else {
                body.setAttribute('data-theme', 'light');
                themeIcon.textContent = '☾';
            }
        }
        
        function toggleDropdown() {
            document.getElementById('profileDropdown').classList.toggle('show');
        }
        
        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.profile-btn')) {
                const dropdowns = document.getElementsByClassName('dropdown-menu');
                for (let i = 0; i < dropdowns.length; i++) {
                    dropdowns[i].classList.remove('show');
                }
            }
        }
    </script>
</body>
</html>