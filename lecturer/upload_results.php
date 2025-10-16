<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has lecturer role
if (!currentUserId()) {
    header('Location: ../login.php');
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
                                    INSERT INTO results (student_id, course_id, type, score, upload_date) 
                                    VALUES (?, ?, ?, ?, NOW()) 
                                    ON DUPLICATE KEY UPDATE score = ?
                                ");
                                while (($data = fgetcsv($handle)) !== false) {
                                    if (count($data) === 2) {
                                        $student_id = trim($data[0]);
                                        $score = trim($data[1]);
                                        // Validate student enrolled (assuming enrollments table)
                                        $enrollStmt = $pdo->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ?");
                                        $enrollStmt->execute([$student_id, $course_id]);
                                        if ($enrollStmt->fetch()) {
                                            $stmt->execute([$student_id, $course_id, $type, $score, $score]);
                                        } else {
                                            $errors[] = "Student $student_id not enrolled in course.";
                                        }
                                    }
                                }
                                $pdo->commit();
                                $success = true;
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
    WHERE ca.lecturer_id = ?
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
            <div class="error-messages">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                <p>Results uploaded successfully!</p>
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
                
                <button type="submit" class="btn primary">Upload Results</button>
            </form>
            
            <form method="POST">
                <input type="hidden" name="download_sample" value="1">
                <button type="submit" class="btn secondary">Download Sample CSV</button>
            </form>
            
            <p class="note">Note: The CSV file should have columns: student_id, score. Scores will be updated if already exist for the student/course/type.</p>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>