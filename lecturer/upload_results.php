<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in and has lecturer role
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit;
}

requireRole('Lecturer', $pdo);

// Get lecturer profile
$stmt = $pdo->prepare("SELECT sp.full_name, sp.staff_id FROM staff_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$lecturer = $stmt->fetch();

// Handle form submission
$errors = [];
$success = false;
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['download_sample'])) {
        // Download sample CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sample_results.csv"');
        echo "student_id,score\n";
        echo "STUD001,85\n";
        echo "STUD002,90\n";
        exit;
    } elseif (isset($_FILES['results_file'])) {
        $course_id = $_POST['course_id'] ?? '';
        $type = $_POST['type'] ?? '';
        $processedCount = 0;

        if (empty($course_id) || empty($type)) {
            $errors[] = "Please select course and type.";
        } else {
            // Check if lecturer is assigned to this course
            $stmt = $pdo->prepare("SELECT * FROM course_assignment WHERE course_id = ? AND lecturer_id = ?");
            $stmt->execute([$course_id, currentUserId()]);
            if (!$stmt->fetch()) {
                $errors[] = "You are not assigned to this course.";
            } else {
                $file = $_FILES['results_file'];
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $fileType = pathinfo($file['name'], PATHINFO_EXTENSION);
                    if (in_array($fileType, ['csv'])) {
                        // Process CSV
                        if (($handle = fopen($file['tmp_name'], 'r')) !== false) {
                            // Skip header
                            fgetcsv($handle);
                            $pdo->beginTransaction();
                            try {
                                $stmt = $pdo->prepare("
                                    INSERT INTO results (enrollment_id, ca_score, exam_score, uploaded_by_user_id, uploaded_at) 
                                    VALUES (?, ?, ?, ?, NOW()) 
                                    ON DUPLICATE KEY UPDATE 
                                        ca_score = CASE WHEN ? = 'CA' THEN ? ELSE ca_score END,
                                        exam_score = CASE WHEN ? = 'Exam' THEN ? ELSE exam_score END,
                                        uploaded_at = NOW()
                                ");
                                
                                while (($data = fgetcsv($handle)) !== false) {
                                    if (count($data) === 2) {
                                        $student_id = trim($data[0]);
                                        $score = trim($data[1]);
                                        
                                        // Validate student enrolled
                                        $enrollStmt = $pdo->prepare("
                                            SELECT ce.id 
                                            FROM course_enrollment ce 
                                            JOIN student_profile sp ON ce.student_user_id = sp.user_id 
                                            WHERE sp.student_number = ? AND ce.course_id = ?
                                        ");
                                        $enrollStmt->execute([$student_id, $course_id]);
                                        $enrollment = $enrollStmt->fetch();
                                        
                                        if ($enrollment) {
                                            $enrollment_id = $enrollment['id'];
                                            $ca_score = ($type === 'CA') ? $score : null;
                                            $exam_score = ($type === 'Exam') ? $score : null;
                                            
                                            $stmt->execute([
                                                $enrollment_id,
                                                $ca_score,
                                                $exam_score,
                                                currentUserId(),
                                                $type, $score,
                                                $type, $score
                                            ]);
                                            $processedCount++;
                                        } else {
                                            $errors[] = "Student $student_id is not enrolled in this course.";
                                        }
                                    }
                                }
                                $pdo->commit();
                                $success = true;
                                $successMessage = "Successfully processed $processedCount results!";
                            } catch (Exception $e) {
                                $pdo->rollBack();
                                $errors[] = "Error processing file: " . $e->getMessage();
                            }
                            fclose($handle);
                        }
                    } else {
                        $errors[] = "Only CSV files are allowed.";
                    }
                } else {
                    $errors[] = "File upload error.";
                }
            }
        }
    }
}

// Get lecturer's assigned courses
$stmt = $pdo->prepare("
    SELECT c.id, c.code, c.name 
    FROM course c 
    JOIN course_assignment ca ON c.id = ca.course_id 
    WHERE ca.lecturer_id = ? AND ca.is_active = 1
");
$stmt->execute([currentUserId()]);
$courses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Results - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .notification {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            position: relative;
            padding-left: 50px;
        }
        
        .notification i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
        }
        
        .notification.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .notification.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .notification.info {
            background-color: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }
        
        .error-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        
        .error-list li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .error-list li:last-child {
            border-bottom: none;
        }
        
        .upload-form {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group select, .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            margin-right: 10px;
        }
        
        .btn.primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn.secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .note {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            border-radius: 4px;
        }
    </style>
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
                        <a href="profile.php"><i class="fas fa-user"></i> View Profile</a>
                        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar (reuse from dashboard) -->
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
                <a href="upload_results.php" class="nav-item active">
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
                <a href="elearning.php" class="nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span>E-Learning (Moodle)</span>
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
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>View Profile</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-upload"></i> Upload Results</h1>
            <p>Upload CA or Exam results for your assigned courses</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="notification error">
                <i class="fas fa-exclamation-circle"></i>
                <strong>Upload Failed!</strong>
                <?php if (count($errors) > 1): ?>
                    <ul class="error-list">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p><?php echo htmlspecialchars($errors[0]); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="notification success">
                <i class="fas fa-check-circle"></i>
                <strong>Success!</strong>
                <p><?php echo htmlspecialchars($successMessage); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="upload-form">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="course_id">Select Course:</label>
                    <select name="course_id" id="course_id" required>
                        <option value="">-- Select Course --</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['code'] . ' - ' . $course['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="type">Select Type:</label>
                    <select name="type" id="type" required>
                        <option value="">-- Select Type --</option>
                        <option value="CA">Continuous Assessment (CA)</option>
                        <option value="Exam">Exam</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="results_file">Upload CSV File:</label>
                    <input type="file" name="results_file" id="results_file" accept=".csv" required>
                </div>
                
                <button type="submit" class="btn primary"><i class="fas fa-upload"></i> Upload Results</button>
            </form>
            
            <form method="POST" style="margin-top: 15px;">
                <input type="hidden" name="download_sample" value="1">
                <button type="submit" class="btn secondary"><i class="fas fa-download"></i> Download Sample CSV</button>
            </form>
            
            <div class="note">
                <strong><i class="fas fa-info-circle"></i> Note:</strong>
                <p>The CSV file should have columns: student_id, score. Scores will be updated if already exist for the student/course/type.</p>
                <p>Example format:</p>
                <pre>student_id,score
LSC000001,85
LSC000002,90</pre>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>