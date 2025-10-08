<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has admin role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Super Admin', $pdo);

// Get admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Handle form submissions
$message = '';
$messageType = '';
$uploadResults = [];

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'upload_users') {
    if (isset($_FILES['user_file']) && $_FILES['user_file']['error'] === UPLOAD_ERR_OK) {
        $uploadResults = processUserUpload($_FILES['user_file'], $pdo);
        $message = "Upload completed! {$uploadResults['success']} users created, {$uploadResults['errors']} errors.";
        $messageType = $uploadResults['errors'] > 0 ? 'warning' : 'success';
    } else {
        $message = 'Please select a valid CSV file to upload.';
        $messageType = 'error';
    }
}

// Handle sample file download
if (isset($_GET['download_sample'])) {
    downloadSampleFile();
    exit;
}

function downloadSampleFile() {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="user_upload_sample.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // BOM for UTF-8
    
    // CSV Headers
    $headers = ['username', 'password', 'email', 'contact', 'role_name', 'full_name', 'staff_id', 'student_number', 'nrc', 'gender', 'qualification', 'programme_id', 'school_id', 'department_id'];
    fputcsv($output, $headers);
    
    // Sample data
    $samples = [
        ['admin.jane@lsc.ac.zm', 'SecurePass123', 'admin.jane@lsc.ac.zm', '0977123456', 'Super Admin', 'Jane Admin', 'ADM002', '', '123456789', 'Female', 'MBA Administration', '', '', ''],
        ['lecturer.john@lsc.ac.zm', 'LecturerPass123', 'lecturer.john@lsc.ac.zm', '0976234567', 'Lecturer', 'John Lecturer', 'LEC002', '', '234567890', 'Male', 'PhD Computer Science', '', '', ''],
        ['finance.mary@lsc.ac.zm', 'FinancePass123', 'finance.mary@lsc.ac.zm', '0975345678', 'Sub Admin (Finance)', 'Mary Finance', 'FIN002', '', '345678901', 'Female', 'B.Com Accounting', '', '', ''],
        ['student.bob@lsc.ac.zm', 'StudentPass123', 'student.bob@lsc.ac.zm', '0974456789', 'Student', 'Bob Student', '', 'LSC000002', '456789012', 'Male', '', '1', '1', '1']
    ];
    
    foreach ($samples as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
}

function processUserUpload($file, $pdo) {
    $results = ['success' => 0, 'errors' => 0, 'details' => []];
    
    if (!in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['csv'])) {
        throw new Exception('Only CSV files are allowed.');
    }
    
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) throw new Exception('Could not read the uploaded file.');
    
    // Skip BOM if present
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    
    $headers = fgetcsv($handle);
    $expectedHeaders = ['username', 'password', 'email', 'contact', 'role_name', 'full_name', 'staff_id', 'student_number', 'nrc', 'gender', 'qualification', 'programme_id', 'school_id', 'department_id'];
    
    // Validate headers
    foreach ($expectedHeaders as $expected) {
        if (!in_array($expected, $headers)) {
            fclose($handle);
            throw new Exception("Missing required column: $expected");
        }
    }
    
    $rowNumber = 1;
    while (($data = fgetcsv($handle)) !== false) {
        $rowNumber++;
        try {
            $userData = array_combine($headers, $data);
            
            // Validate required fields
            if (empty($userData['username']) || empty($userData['password']) || empty($userData['role_name']) || empty($userData['full_name'])) {
                throw new Exception("Missing required fields in row $rowNumber");
            }
            
            $pdo->beginTransaction();
            
            // Get role ID
            $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
            $stmt->execute([$userData['role_name']]);
            $roleId = $stmt->fetchColumn();
            if (!$roleId) throw new Exception("Invalid role '{$userData['role_name']}' in row $rowNumber");
            
            // Check username uniqueness
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$userData['username']]);
            if ($stmt->fetchColumn()) throw new Exception("Username '{$userData['username']}' already exists in row $rowNumber");
            
            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, contact, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$userData['username'], password_hash($userData['password'], PASSWORD_DEFAULT), $userData['email'] ?: null, $userData['contact'] ?: null]);
            $userId = $pdo->lastInsertId();
            
            // Insert user role
            $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            $stmt->execute([$userId, $roleId]);
            
            // Insert profile based on role
            switch ($userData['role_name']) {
                case 'Super Admin':
                    $stmt = $pdo->prepare("INSERT INTO admin_profile (user_id, full_name, staff_id) VALUES (?, ?, ?)");
                    $stmt->execute([$userId, $userData['full_name'], $userData['staff_id'] ?: null]);
                    break;
                case 'Student':
                    $stmt = $pdo->prepare("INSERT INTO student_profile (user_id, full_name, student_number, NRC, gender, programme_id, school_id, department_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$userId, $userData['full_name'], $userData['student_number'] ?: null, $userData['nrc'] ?: null, $userData['gender'] ?: null, $userData['programme_id'] ?: null, $userData['school_id'] ?: null, $userData['department_id'] ?: null]);
                    break;
                case 'Lecturer':
                case 'Sub Admin (Finance)':
                    $stmt = $pdo->prepare("INSERT INTO staff_profile (user_id, full_name, staff_id, NRC, gender, qualification) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$userId, $userData['full_name'], $userData['staff_id'] ?: null, $userData['nrc'] ?: null, $userData['gender'] ?: null, $userData['qualification'] ?: null]);
                    break;
            }
            
            $pdo->commit();
            $results['success']++;
            $results['details'][] = "Row $rowNumber: Successfully created user '{$userData['username']}'";
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollback();
            $results['errors']++;
            $results['details'][] = "Row $rowNumber: " . $e->getMessage();
        }
    }
    
    fclose($handle);
    return $results;
}

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_users, SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_users, SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_users FROM users");
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Upload Users - LSC SRMS</title>
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($admin['full_name'] ?? 'Administrator'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($admin['staff_id'] ?? 'N/A'); ?>)</span>
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
                <a href="upload_users.php" class="nav-item active">
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
            <h1><i class="fas fa-upload"></i> Bulk Upload Users</h1>
            <p>Upload multiple users at once using CSV files</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_users']); ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['today_users']); ?></h3>
                    <p>Added Today</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['week_users']); ?></h3>
                    <p>Added This Week</p>
                </div>
            </div>
        </div>

        <!-- Upload Instructions -->
        <div class="instructions-section">
            <div class="instructions-card">
                <h2><i class="fas fa-info-circle"></i> Upload Instructions</h2>
                <div class="instructions-content">
                    <div class="instruction-steps">
                        <div class="step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h3>Download Sample Template</h3>
                                <p>Download the CSV template to see the required format.</p>
                                <a href="?download_sample=1" class="btn btn-green">
                                    <i class="fas fa-download"></i> Download Sample CSV
                                </a>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h3>Prepare Your Data</h3>
                                <p>Fill in your user data following the sample format.</p>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h3>Upload CSV File</h3>
                                <p>Select your prepared CSV file and upload it.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Form -->
        <div class="upload-section">
            <div class="upload-card">
                <h2><i class="fas fa-cloud-upload-alt"></i> Upload Users</h2>
                
                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <input type="hidden" name="action" value="upload_users">
                    
                    <div class="file-upload-area">
                        <div class="file-upload-zone" onclick="document.getElementById('user_file').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <h3>Click to select CSV file</h3>
                            <p>or drag and drop your CSV file here</p>
                            <span class="file-types">Supported: .csv files only</span>
                        </div>
                        <input type="file" id="user_file" name="user_file" accept=".csv" required style="display: none;">
                        <div class="selected-file" id="selected-file" style="display: none;">
                            <i class="fas fa-file-csv"></i>
                            <span class="file-name"></span>
                            <button type="button" onclick="clearFileSelection()" class="remove-file">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-green">
                            <i class="fas fa-upload"></i> Upload Users
                        </button>
                        <button type="button" onclick="resetForm()" class="btn btn-orange">
                            <i class="fas fa-refresh"></i> Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Upload Results -->
        <?php if (!empty($uploadResults['details'])): ?>
            <div class="results-section">
                <div class="results-card">
                    <h2><i class="fas fa-chart-line"></i> Upload Results</h2>
                    <div class="results-summary">
                        <div class="result-stat success">
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo $uploadResults['success']; ?> Successful</span>
                        </div>
                        <div class="result-stat error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo $uploadResults['errors']; ?> Errors</span>
                        </div>
                    </div>
                    
                    <div class="results-details">
                        <h3>Detailed Results</h3>
                        <div class="results-log">
                            <?php foreach ($uploadResults['details'] as $detail): ?>
                                <div class="log-item <?php echo strpos($detail, 'Successfully') !== false ? 'success' : 'error'; ?>">
                                    <i class="fas fa-<?php echo strpos($detail, 'Successfully') !== false ? 'check' : 'times'; ?>"></i>
                                    <?php echo htmlspecialchars($detail); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Column Reference -->
        <div class="reference-section">
            <div class="reference-card">
                <h2><i class="fas fa-table"></i> CSV Column Reference</h2>
                <div class="format-requirements">
                    <h3><i class="fas fa-exclamation-triangle"></i> Format Requirements</h3>
                    <ul>
                        <li><strong>File Format:</strong> CSV (Comma Separated Values) only</li>
                        <li><strong>Required Fields:</strong> username, password, role_name, full_name</li>
                        <li><strong>Unique Fields:</strong> username, email, staff_id, student_number</li>
                        <li><strong>Role Names:</strong> Super Admin, Lecturer, Sub Admin (Finance), Student</li>
                        <li><strong>Gender Options:</strong> Male, Female, Other (optional)</li>
                        <li><strong>Password:</strong> Minimum 8 characters recommended</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        // File upload handling
        document.getElementById('user_file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                document.querySelector('.file-upload-zone').style.display = 'none';
                document.getElementById('selected-file').style.display = 'flex';
                document.querySelector('.file-name').textContent = file.name;
            }
        });

        function clearFileSelection() {
            document.getElementById('user_file').value = '';
            document.querySelector('.file-upload-zone').style.display = 'flex';
            document.getElementById('selected-file').style.display = 'none';
        }

        function resetForm() {
            clearFileSelection();
            document.querySelector('form').reset();
        }

        // Drag and drop functionality
        const uploadZone = document.querySelector('.file-upload-zone');
        
        ['dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, function(e) {
                e.preventDefault();
                if (eventName === 'dragover') {
                    this.classList.add('dragover');
                } else if (eventName === 'dragleave') {
                    this.classList.remove('dragover');
                } else if (eventName === 'drop') {
                    this.classList.remove('dragover');
                    const files = e.dataTransfer.files;
                    if (files.length > 0 && files[0].name.endsWith('.csv')) {
                        document.getElementById('user_file').files = files;
                        this.style.display = 'none';
                        document.getElementById('selected-file').style.display = 'flex';
                        document.querySelector('.file-name').textContent = files[0].name;
                    } else {
                        alert('Please select a CSV file.');
                    }
                }
            });
        });
    </script>
</body>
</html>