<?php
session_start();
require_once '../config.php';
require_once '../auth.php';

// Check if user is logged in and has student role
if (!currentUserId() || !currentUserHasRole('Student', $pdo)) {
    header('Location: ../student_login.php');
    exit();
}

// Get student profile with user account information and programme details
$stmt = $pdo->prepare("
    SELECT sp.*, u.email, u.contact, u.created_at, u.is_active, p.name as programme_name
    FROM student_profile sp 
    JOIN users u ON sp.user_id = u.id 
    LEFT JOIN programme p ON sp.programme_id = p.id
    WHERE sp.user_id = ?
");
$stmt->execute([currentUserId()]);
$student = $stmt->fetch();

if (!$student) {
    // Student profile not found
    header('Location: ../student_login.php');
    exit();
}

// Get additional student information
$campus = "Main Campus"; // Default campus
$major = $student['programme_name'] ?? 'N/A';

// Get intake details
$intake_name = 'N/A';
if (!empty($student['intake_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM intake WHERE id = ?");
        $stmt->execute([$student['intake_id']]);
        $intake_result = $stmt->fetch();
        $intake_name = $intake_result['name'] ?? 'N/A';
    } catch (Exception $e) {
        $intake_name = 'N/A';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Font Awesome icons are now used throughout the application */
        
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .profile-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .profile-section h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-item label {
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
        }
        
        .info-item span {
            padding: 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        
        .photo-section {
            text-align: center;
        }
        
        .profile-photo {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #007bff;
            margin: 0 auto 20px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: #ccc;
        }
        
        .change-link {
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }
        
        .change-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
            
            .info-grid {
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
                <a href="accommodation.php" class="nav-item">
                    <i class="fas fa-bed"></i>
                    <span>Accommodation</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-user"></i> Student Profile</h1>
            <p>View and manage your personal and academic information</p>
        </div>

        <div class="profile-container">
            <div class="profile-main">
                <!-- User Account Details -->
                <div class="profile-section">
                    <h3><i class="fas fa-user-circle"></i> User Account Details</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Username</label>
                            <span><?php echo htmlspecialchars($student['username'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Email Address</label>
                            <span><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Account Status</label>
                            <span><?php echo (isset($student['is_active']) && $student['is_active']) ? 'Active' : 'Inactive'; ?></span>
                        </div>
                        <div class="info-item">
                            <label>Member Since</label>
                            <span><?php echo isset($student['created_at']) ? date('F j, Y', strtotime($student['created_at'])) : 'N/A'; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="profile-section">
                    <h3><i class="fas fa-lock"></i> Security</h3>
                    <div class="info-item">
                        <label>Password</label>
                        <span>•••••••• <a href="settings.php" class="change-link">Change Password</a></span>
                    </div>
                </div>

                <!-- Programme Details -->
                <div class="profile-section">
                    <h3><i class="fas fa-graduation-cap"></i> Programme Details</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>School</label>
                            <span>EDUCATION</span>
                        </div>
                        <div class="info-item">
                            <label>Campus</label>
                            <span><?php echo htmlspecialchars($campus); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Programme</label>
                            <span><?php echo htmlspecialchars($student['programme_name'] ?? 'BACHELOR OF INFORMATION AND COMMUNICATION TECHNOLOGY WITH EDUCATION'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Major</label>
                            <span><?php echo htmlspecialchars($major); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Intake</label>
                            <span><?php echo htmlspecialchars($intake_name); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Personal Information -->
                <div class="profile-section">
                    <h3><i class="fas fa-address-card"></i> Personal Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Student ID</label>
                            <span><?php echo htmlspecialchars($student['student_number'] ?? '2104035934'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>First Name</label>
                            <span><?php 
                                $names = explode(' ', $student['full_name'] ?? 'SAMUEL');
                                echo htmlspecialchars($names[0] ?? 'SAMUEL'); 
                            ?></span>
                        </div>
                        <div class="info-item">
                            <label>Middle Name</label>
                            <span><?php 
                                if (isset($names[1]) && isset($names[2])) {
                                    echo htmlspecialchars(implode(' ', array_slice($names, 1, -1)));
                                } else {
                                    echo '';
                                }
                            ?></span>
                        </div>
                        <div class="info-item">
                            <label>Surname</label>
                            <span><?php 
                                echo isset($names[count($names)-1]) ? htmlspecialchars($names[count($names)-1]) : 'SIANAMATE'; 
                            ?></span>
                        </div>
                        <div class="info-item">
                            <label>Gender</label>
                            <span><?php echo htmlspecialchars($student['gender'] ?? 'M'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Date Of Birth</label>
                            <span><?php echo htmlspecialchars($student['date_of_birth'] ?? '2001-09-28'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>NRC</label>
                            <span><?php echo htmlspecialchars($student['NRC'] ?? '233634/77/1'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Passport</label>
                            <span><?php echo htmlspecialchars($student['passport_number'] ?? ''); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Phone Number</label>
                            <span>
                                <?php echo htmlspecialchars($student['contact'] ?? '0763355990'); ?> 
                                <a href="settings.php" class="change-link">Change</a>
                            </span>
                        </div>
                        <div class="info-item">
                            <label>Email Address</label>
                            <span><?php echo htmlspecialchars($student['email'] ?? ''); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Residential Address</label>
                            <span><?php echo htmlspecialchars($student['residential_address'] ?? 'Site and ServiceMonze'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Postal Address</label>
                            <span>
                                <?php echo htmlspecialchars($student['postal_address'] ?? 'Monze Southern'); ?> 
                                <a href="settings.php" class="change-link">Change</a>
                            </span>
                        </div>
                        <div class="info-item">
                            <label>Nationality</label>
                            <span><?php echo htmlspecialchars($student['nationality'] ?? ''); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Marital Status</label>
                            <span><?php echo htmlspecialchars($student['marital_status'] ?? 's'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Disability</label>
                            <span><?php echo htmlspecialchars($student['disability'] ?? ''); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Next of Kin Information -->
                <div class="profile-section">
                    <h3><i class="fas fa-user-friends"></i> Next of Kin Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Next Of Kin</label>
                            <span><?php echo htmlspecialchars($student['next_of_kin'] ?? 'Weldy Sianamate'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Title</label>
                            <span><?php echo htmlspecialchars($student['kin_title'] ?? 'Mr'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Province</label>
                            <span><?php echo htmlspecialchars($student['kin_province'] ?? ''); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Secondary School</label>
                            <span><?php echo htmlspecialchars($student['secondary_school'] ?? ''); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Refugee Status</label>
                            <span><?php echo htmlspecialchars($student['refugee_status'] ?? 'No'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="profile-sidebar">
                <!-- Photo Section -->
                <div class="profile-section photo-section">
                    <h3><i class="fas fa-camera"></i> Photo</h3>
                    <div class="profile-photo">
                        <i class="fas fa-user"></i>
                    </div>
                    <p><a href="#" class="change-link">Upload New Photo</a></p>
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
                themeIcon.className = newTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
            }
        }
        
        // Initialize theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('studentTheme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
            
            // Update theme icon
            const themeIcon = document.getElementById('theme-icon');
            if (themeIcon) {
                themeIcon.className = savedTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
            }
        });
        
        // Toggle sidebar function
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        }
        
        // Toggle dropdown function
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