<?php
session_start();
require_once '../config.php';
require_once '../auth/auth.php';

// Check if user is logged in and has student role
if (!currentUserId() || !currentUserHasRole('Student', $pdo)) {
    header('Location: ../auth/student_login.php');
    exit();
}

// Handle photo upload
$message = '';
$messageType = '';

// Add profile_photo column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE student_profile ADD COLUMN profile_photo VARCHAR(255) NULL");
} catch (Exception $e) {
    // Column might already exist
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_photo'])) {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (in_array($_FILES['profile_photo']['type'], $allowed_types) && $_FILES['profile_photo']['size'] <= $max_size) {
            $upload_dir = '../uploads/profile_photos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $new_filename = 'student_' . currentUserId() . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                try {
                    // Update student profile with photo path
                    $stmt = $pdo->prepare("UPDATE student_profile SET profile_photo = ? WHERE user_id = ?");
                    $stmt->execute([$target_file, currentUserId()]);
                    
                    $message = "Profile photo uploaded successfully!";
                    $messageType = 'success';
                    
                    // Refresh student data
                    $stmt = $pdo->prepare("SELECT sp.*, u.email, u.contact, u.created_at, u.is_active, p.name as programme_name FROM student_profile sp JOIN users u ON sp.user_id = u.id LEFT JOIN programme p ON sp.programme_id = p.id WHERE sp.user_id = ?");
                    $stmt->execute([currentUserId()]);
                    $student = $stmt->fetch();
                } catch (Exception $e) {
                    $message = "Error updating profile: " . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = "Error uploading file.";
                $messageType = 'error';
            }
        } else {
            $message = "Invalid file type or size. Please upload a JPEG, PNG, or GIF image (max 5MB).";
            $messageType = 'error';
        }
    } else {
        $message = "Please select a file to upload.";
        $messageType = 'error';
    }
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
    header('Location: ../auth/student_login.php');
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
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-control {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
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
        
        .alert {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
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
                <a href="elearning.php" class="nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span>E-Learning (Moodle)</span>
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
                            <span><?php echo htmlspecialchars($student['school_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Campus</label>
                            <span><?php echo htmlspecialchars($student['campus'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Programme</label>
                            <span><?php echo htmlspecialchars($student['programme_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Major</label>
                            <span><?php echo htmlspecialchars($student['major'] ?? 'N/A'); ?></span>
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
                            <span><?php echo htmlspecialchars($student['student_number'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>First Name</label>
                            <span><?php 
                                $names = explode(' ', $student['full_name'] ?? '');
                                echo htmlspecialchars($names[0] ?? 'N/A'); 
                            ?></span>
                        </div>
                        <div class="info-item">
                            <label>Middle Name</label>
                            <span><?php 
                                if (isset($names[1]) && isset($names[count($names)-1])) {
                                    // Get all names except first and last
                                    $middleNames = array_slice($names, 1, -1);
                                    echo htmlspecialchars(implode(' ', $middleNames));
                                } else {
                                    echo 'N/A';
                                }
                            ?></span>
                        </div>
                        <div class="info-item">
                            <label>Surname</label>
                            <span><?php 
                                echo isset($names[count($names)-1]) && count($names) > 1 ? htmlspecialchars($names[count($names)-1]) : (isset($names[0]) ? htmlspecialchars($names[0]) : 'N/A'); 
                            ?></span>
                        </div>
                        <div class="info-item">
                            <label>Gender</label>
                            <span><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Date Of Birth</label>
                            <span><?php echo htmlspecialchars($student['date_of_birth'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>NRC</label>
                            <span><?php echo htmlspecialchars($student['NRC'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Passport</label>
                            <span><?php echo htmlspecialchars($student['passport_number'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Phone Number</label>
                            <span>
                                <?php echo htmlspecialchars($student['contact'] ?? 'N/A'); ?> 
                                <a href="settings.php" class="change-link">Change</a>
                            </span>
                        </div>
                        <div class="info-item">
                            <label>Email Address</label>
                            <span><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Residential Address</label>
                            <span><?php echo htmlspecialchars($student['residential_address'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Postal Address</label>
                            <span>
                                <?php echo htmlspecialchars($student['postal_address'] ?? 'N/A'); ?> 
                                <a href="settings.php" class="change-link">Change</a>
                            </span>
                        </div>
                        <div class="info-item">
                            <label>Nationality</label>
                            <span><?php echo htmlspecialchars($student['nationality'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Marital Status</label>
                            <span><?php echo htmlspecialchars($student['marital_status'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Disability</label>
                            <span><?php echo htmlspecialchars($student['disability'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Next of Kin Information -->
                <div class="profile-section">
                    <h3><i class="fas fa-user-friends"></i> Next of Kin Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Next Of Kin</label>
                            <span><?php echo htmlspecialchars($student['next_of_kin'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Title</label>
                            <span><?php echo htmlspecialchars($student['kin_title'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Province</label>
                            <span><?php echo htmlspecialchars($student['kin_province'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Secondary School</label>
                            <span><?php echo htmlspecialchars($student['secondary_school'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Refugee Status</label>
                            <span><?php echo htmlspecialchars($student['refugee_status'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="profile-sidebar">
                <!-- Photo Section -->
                <div class="profile-section photo-section">
                    <h3><i class="fas fa-camera"></i> Photo</h3>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="profile-photo" id="photoContainer" style="cursor: pointer; position: relative;" title="Click to upload photo">
                        <?php if (!empty($student['profile_photo']) && file_exists($student['profile_photo'])): ?>
                            <img src="<?php echo htmlspecialchars($student['profile_photo']); ?>" alt="Profile Photo" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-user" style="font-size: 60px;"></i>
                        <?php endif; ?>
                        <div style="position: absolute; bottom: 0; right: 0; background: rgba(0,0,0,0.5); color: white; padding: 5px; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" id="photoUploadForm" style="display: none;">
                        <input type="hidden" name="upload_photo" value="1">
                        <div class="form-group">
                            <input type="file" name="profile_photo" id="photoInput" accept="image/*" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Upload Photo</button>
                    </form>
                    
                    <p style="margin-top: 15px;"><small>Click on the photo to upload or change your profile picture</small></p>
                </div>
                
                <!-- Image Cropping Modal -->
                <div id="cropModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8);">
                    <div class="modal-content" style="background-color: white; margin: 5% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 600px; text-align: center;">
                        <span class="close" id="closeCropModal" style="float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                        <h3>Crop Your Photo</h3>
                        <div id="cropContainer" style="position: relative; width: 300px; height: 300px; margin: 20px auto; overflow: hidden; border: 2px dashed #ccc;">
                            <img id="cropImage" src="" style="max-width: 100%; max-height: 100%;">
                        </div>
                        <button id="confirmCrop" class="btn btn-primary">Use This Photo</button>
                        <button id="cancelCrop" class="btn btn-secondary" style="margin-left: 10px;">Cancel</button>
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
            
            // Photo click handler
            const photoContainer = document.getElementById('photoContainer');
            const photoInput = document.getElementById('photoInput');
            
            if (photoContainer && photoInput) {
                photoContainer.addEventListener('click', function() {
                    photoInput.click();
                });
                
                photoInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const file = this.files[0];
                        const fileType = file.type;
                        
                        // Check if it's an image
                        if (fileType.startsWith('image/')) {
                            // For simplicity, we'll submit the form directly
                            // In a more advanced implementation, we could show a cropping modal
                            document.getElementById('photoUploadForm').submit();
                        } else {
                            alert('Please select an image file (JPEG, PNG, GIF)');
                        }
                    }
                });
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
            
            // Close crop modal if clicked outside
            const cropModal = document.getElementById('cropModal');
            if (event.target == cropModal) {
                cropModal.style.display = 'none';
            }
        }
        
        // Close crop modal handlers
        const closeCropModal = document.getElementById('closeCropModal');
        const cancelCrop = document.getElementById('cancelCrop');
        
        if (closeCropModal) {
            closeCropModal.onclick = function() {
                document.getElementById('cropModal').style.display = 'none';
            }
        }
        
        if (cancelCrop) {
            cancelCrop.onclick = function() {
                document.getElementById('cropModal').style.display = 'none';
            }
        }
    </script>
</body>
</html>