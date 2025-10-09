<?php
session_start();
require_once '../config.php';
require_once '../auth.php';

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Location: ../login.php');
    exit();
}

// Check if user has admin role or manage_academic_structure permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('manage_academic_structure', $pdo)) {
    header('Location: ../login.php');
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT u.*, ap.full_name, ap.staff_id FROM users u LEFT JOIN admin_profile ap ON u.id = ap.user_id WHERE u.id = ?");
$stmt->execute([currentUserId()]);
$user = $stmt->fetch();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_department':
                $name = trim($_POST['name']);
                $code = trim($_POST['code']);
                $school_id = $_POST['school_id'];
                $description = trim($_POST['description']);
                $head_of_department = trim($_POST['head_of_department']) ?: null;
                
                // Check if department code already exists
                $check_stmt = $pdo->prepare("SELECT id FROM department WHERE code = ?");
                $check_stmt->execute([$code]);
                
                if ($check_stmt->rowCount() > 0) {
                    $error = "Department code already exists!";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO department (name, code, school_id, description, head_of_department, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    if ($stmt->execute([$name, $code, $school_id, $description, $head_of_department])) {
                        $success = "Department added successfully!";
                    } else {
                        $error = "Failed to add department!";
                    }
                }
                break;
                
            case 'edit_department':
                $id = $_POST['department_id'];
                $name = trim($_POST['name']);
                $code = trim($_POST['code']);
                $school_id = $_POST['school_id'];
                $description = trim($_POST['description']);
                $head_of_department = trim($_POST['head_of_department']) ?: null;
                
                // Check if department code already exists (excluding current department)
                $check_stmt = $pdo->prepare("SELECT id FROM department WHERE code = ? AND id != ?");
                $check_stmt->execute([$code, $id]);
                
                if ($check_stmt->rowCount() > 0) {
                    $error = "Department code already exists!";
                } else {
                    $stmt = $pdo->prepare("UPDATE department SET name = ?, code = ?, school_id = ?, description = ?, head_of_department = ?, updated_at = NOW() WHERE id = ?");
                    if ($stmt->execute([$name, $code, $school_id, $description, $head_of_department, $id])) {
                        $success = "Department updated successfully!";
                    } else {
                        $error = "Failed to update department!";
                    }
                }
                break;
                
            case 'delete_department':
                $id = $_POST['department_id'];
                
                // Check if department has courses
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM course WHERE department_id = ?");
                $check_stmt->execute([$id]);
                $course_count = $check_stmt->fetchColumn();
                
                if ($course_count > 0) {
                    $error = "Cannot delete department with existing courses!";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM department WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $success = "Department deleted successfully!";
                    } else {
                        $error = "Failed to delete department!";
                    }
                }
                break;
        }
    }
}

// Get departments with school info and counts
$departments_query = "
    SELECT d.*, s.name as school_name,
           (SELECT COUNT(*) FROM student_profile sp WHERE sp.department_id = d.id) as student_count,
           (SELECT COUNT(*) FROM course WHERE department_id = d.id) as programme_count
    FROM department d 
    LEFT JOIN school s ON d.school_id = s.id 
    ORDER BY s.name, d.name
";
$departments = $pdo->query($departments_query)->fetchAll();

// Get schools for dropdowns
$schools = $pdo->query("SELECT * FROM school ORDER BY name")->fetchAll();

// Add missing columns to department table if they don't exist
try {
    $pdo->exec("ALTER TABLE department ADD COLUMN IF NOT EXISTS code VARCHAR(20) UNIQUE");
    $pdo->exec("ALTER TABLE department ADD COLUMN IF NOT EXISTS description TEXT");
    $pdo->exec("ALTER TABLE department ADD COLUMN IF NOT EXISTS head_of_department VARCHAR(255)");
    $pdo->exec("ALTER TABLE department ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $pdo->exec("ALTER TABLE department ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    
    // Add department_id to programme table for future functionality
    $pdo->exec("ALTER TABLE programme ADD COLUMN IF NOT EXISTS department_id INT");
    $pdo->exec("ALTER TABLE programme ADD CONSTRAINT FK_programme_department FOREIGN KEY (department_id) REFERENCES department(id) ON DELETE SET NULL");
} catch (Exception $e) {
    // Columns might already exist
}

// Get department details if viewing
$viewing_department = null;
$department_students = [];
$department_programmes = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $dept_id = $_GET['view'];
    $dept_stmt = $pdo->prepare("
        SELECT d.*, s.name as school_name,
               (SELECT COUNT(*) FROM student_profile sp WHERE sp.department_id = d.id) as student_count,
               (SELECT COUNT(*) FROM course WHERE department_id = d.id) as programme_count
        FROM department d 
        LEFT JOIN school s ON d.school_id = s.id 
        WHERE d.id = ?
    ");
    $dept_stmt->execute([$dept_id]);
    $viewing_department = $dept_stmt->fetch();
    
    if ($viewing_department) {
        // Get students in this department
        $students_stmt = $pdo->prepare("
            SELECT sp.*, u.username, u.email as user_email, p.name as programme_name
            FROM student_profile sp 
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN programme p ON sp.programme_id = p.id 
            WHERE sp.department_id = ? 
            ORDER BY sp.full_name
        ");
        $students_stmt->execute([$dept_id]);
        $department_students = $students_stmt->fetchAll();
        
        // Get courses in this department (since programmes don't have department_id)
        $programmes_stmt = $pdo->prepare("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM course_enrollment ce WHERE ce.course_id = c.id) as enrollment_count
            FROM course c 
            WHERE c.department_id = ? 
            ORDER BY c.name
        ");
        $programmes_stmt->execute([$dept_id]);
        $department_programmes = $programmes_stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - SRMS Admin</title>
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-graduation-cap"></i> SRMS Admin</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-dashboard"></i> Dashboard</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="manage_roles.php"><i class="fas fa-user-shield"></i> Roles & Permissions</a></li>
                
                <li class="menu-section">
                    <span><i class="fas fa-school"></i> Academic Structure</span>
                    <ul class="submenu">
                        <li><a href="manage_schools.php"><i class="fas fa-university"></i> Schools</a></li>
                        <li><a href="manage_departments.php" class="active"><i class="fas fa-building"></i> Departments</a></li>
                        <li><a href="manage_programmes.php"><i class="fas fa-book"></i> Programmes</a></li>
                        <li><a href="manage_courses.php"><i class="fas fa-bookmark"></i> Courses</a></li>
                    </ul>
                </li>
                
                <li class="menu-section">
                    <span><i class="fas fa-chart-line"></i> Results Management</span>
                    <ul class="submenu">
                        <li><a href="manage_ca.php"><i class="fas fa-tasks"></i> Continuous Assessment</a></li>
                        <li><a href="manage_exams.php"><i class="fas fa-file-alt"></i> Examinations</a></li>
                    </ul>
                </li>
                
                <li><a href="manage_enrollment.php"><i class="fas fa-user-plus"></i> Enrollment</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="upload_users.php"><i class="fas fa-upload"></i> Bulk Upload</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navigation -->
            <header class="top-nav">
                <div class="nav-left">
                    <button class="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>Manage Departments</h1>
                </div>
                
                <div class="nav-right">
                    <button class="theme-toggle" onclick="toggleTheme()">
                        <i class="fas fa-moon"></i>
                    </button>
                    
                    <div class="user-menu">
                        <span>Welcome, <?php echo htmlspecialchars($user['full_name'] ?: $user['first_name']); ?></span>
                        <div class="user-dropdown">
                            <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content-area">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($viewing_department): ?>
                    <!-- Department Details View -->
                    <div class="department-details">
                        <div class="details-header">
                            <div class="header-content">
                                <div class="header-info">
                                    <h2><?php echo htmlspecialchars($viewing_department['name']); ?></h2>
                                    <p class="department-code">Code: <?php echo htmlspecialchars($viewing_department['code']); ?></p>
                                    <p class="school-info">School: <?php echo htmlspecialchars($viewing_department['school_name']); ?></p>
                                    <?php if ($viewing_department['head_of_department']): ?>
                                        <p class="hod-info">Head of Department: <?php echo htmlspecialchars($viewing_department['head_of_department']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="header-actions">
                                    <a href="manage_departments.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Back to Departments
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?php echo $viewing_department['student_count']; ?></h3>
                                    <p>Total Students</p>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?php echo $viewing_department['programme_count']; ?></h3>
                                    <p>Courses</p>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?php echo date('M Y', strtotime($viewing_department['created_at'])); ?></h3>
                                    <p>Established</p>
                                </div>
                            </div>
                        </div>

                        <!-- Courses Section -->
                        <div class="data-panel">
                            <div class="panel-header">
                                <h3><i class="fas fa-book"></i> Courses</h3>
                            </div>
                            <div class="panel-content">
                                <?php if (empty($department_programmes)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-book"></i>
                                        <p>No courses found in this department</p>
                                    </div>
                                <?php else: ?>
                                    <div class="programmes-grid">
                                        <?php foreach ($department_programmes as $programme): ?>
                                            <div class="programme-card">
                                                <div class="programme-header">
                                                    <h4><?php echo htmlspecialchars($programme['name']); ?></h4>
                                                    <span class="programme-code"><?php echo htmlspecialchars($programme['code']); ?></span>
                                                </div>
                                                <div class="programme-stats">
                                                    <span class="enrollment-count">
                                                        <i class="fas fa-users"></i> <?php echo $programme['enrollment_count']; ?> enrollments
                                                    </span>
                                                    <span class="programme-duration">
                                                        <i class="fas fa-star"></i> <?php echo $programme['credits']; ?> credits
                                                    </span>
                                                </div>
                                                <div class="programme-description">
                                                    <p>Course Code: <?php echo htmlspecialchars($programme['code']); ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Students Section -->
                        <div class="data-panel">
                            <div class="panel-header">
                                <h3><i class="fas fa-users"></i> Students</h3>
                                <div class="panel-actions">
                                    <div class="search-box">
                                        <i class="fas fa-search"></i>
                                        <input type="text" id="studentSearch" placeholder="Search students..." onkeyup="filterStudents()">
                                    </div>
                                </div>
                            </div>
                            <div class="panel-content">
                                <?php if (empty($department_students)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <p>No students found in this department</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="data-table" id="studentsTable">
                                            <thead>
                                                <tr>
                                                    <th>Student ID</th>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Programme</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($department_students as $student): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($student['student_number'] ?: 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($student['user_email']); ?></td>
                                                        <td>
                                                            <span class="programme-badge">
                                                                <?php echo htmlspecialchars($student['programme_name']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="action-buttons">
                                                                <a href="view_student.php?id=<?php echo $student['user_id']; ?>" class="btn btn-sm btn-primary" title="View Student">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Main Departments Management View -->
                    <div class="page-header">
                        <div class="header-content">
                            <div class="header-info">
                                <h2>Departments Management</h2>
                                <p>Manage academic departments across all schools</p>
                            </div>
                            <div class="header-actions">
                                <button class="btn btn-primary" onclick="openAddModal()">
                                    <i class="fas fa-plus"></i> Add Department
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="filters-panel">
                        <div class="filter-group">
                            <label for="schoolFilter">Filter by School:</label>
                            <select id="schoolFilter" onchange="filterDepartments()">
                                <option value="">All Schools</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="departmentSearch" placeholder="Search departments..." onkeyup="filterDepartments()">
                            </div>
                        </div>
                    </div>

                    <!-- Departments Table -->
                    <div class="data-panel">
                        <div class="panel-content">
                            <div class="table-responsive">
                                <table class="data-table" id="departmentsTable">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Name</th>
                                            <th>School</th>
                                            <th>Head of Department</th>
                                            <th>Students</th>
                                            <th>Courses</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($departments as $department): ?>
                                            <tr data-school-id="<?php echo $department['school_id']; ?>">
                                                <td>
                                                    <span class="department-code"><?php echo htmlspecialchars($department['code']); ?></span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($department['name']); ?></strong>
                                                    <?php if ($department['description']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($department['description'], 0, 50)) . (strlen($department['description']) > 50 ? '...' : ''); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="school-badge"><?php echo htmlspecialchars($department['school_name']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($department['head_of_department'] ?: 'Not assigned'); ?></td>
                                                <td>
                                                    <span class="count-badge"><?php echo $department['student_count']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="count-badge"><?php echo $department['programme_count']; ?></span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="?view=<?php echo $department['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-warning" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($department)); ?>)" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="deleteDepartment(<?php echo $department['id']; ?>, '<?php echo htmlspecialchars($department['name']); ?>')" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add Department Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Add New Department</h3>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_department">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="add_name">Department Name *</label>
                        <input type="text" id="add_name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="add_code">Department Code *</label>
                        <input type="text" id="add_code" name="code" required placeholder="e.g., CSE, EEE, ME">
                    </div>
                    
                    <div class="form-group">
                        <label for="add_school">School *</label>
                        <select id="add_school" name="school_id" required>
                            <option value="">Select School</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="add_hod">Head of Department</label>
                        <input type="text" id="add_hod" name="head_of_department" placeholder="Optional">
                    </div>
                    
                    <div class="form-group">
                        <label for="add_description">Description</label>
                        <textarea id="add_description" name="description" rows="3" placeholder="Optional department description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Department</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_department">
                <input type="hidden" id="edit_department_id" name="department_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_name">Department Name *</label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_code">Department Code *</label>
                        <input type="text" id="edit_code" name="code" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_school">School *</label>
                        <select id="edit_school" name="school_id" required>
                            <option value="">Select School</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_hod">Head of Department</label>
                        <input type="text" id="edit_hod" name="head_of_department">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
                <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_department">
                <input type="hidden" id="delete_department_id" name="department_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete the department <strong id="delete_department_name"></strong>?</p>
                    <p class="text-danger"><i class="fas fa-warning"></i> This action cannot be undone. Make sure the department has no programmes before deleting.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Department</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function openEditModal(department) {
            document.getElementById('edit_department_id').value = department.id;
            document.getElementById('edit_name').value = department.name;
            document.getElementById('edit_code').value = department.code;
            document.getElementById('edit_school').value = department.school_id;
            document.getElementById('edit_hod').value = department.head_of_department || '';
            document.getElementById('edit_description').value = department.description || '';
            document.getElementById('editModal').style.display = 'block';
        }

        function deleteDepartment(id, name) {
            document.getElementById('delete_department_id').value = id;
            document.getElementById('delete_department_name').textContent = name;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['addModal', 'editModal', 'deleteModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Filter functions
        function filterDepartments() {
            const schoolFilter = document.getElementById('schoolFilter').value;
            const searchTerm = document.getElementById('departmentSearch').value.toLowerCase();
            const table = document.getElementById('departmentsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const schoolId = row.getAttribute('data-school-id');
                const cells = row.getElementsByTagName('td');
                
                let showRow = true;

                // School filter
                if (schoolFilter && schoolId !== schoolFilter) {
                    showRow = false;
                }

                // Search filter
                if (searchTerm) {
                    const code = cells[0].textContent.toLowerCase();
                    const name = cells[1].textContent.toLowerCase();
                    const school = cells[2].textContent.toLowerCase();
                    const hod = cells[3].textContent.toLowerCase();
                    
                    if (!code.includes(searchTerm) && 
                        !name.includes(searchTerm) && 
                        !school.includes(searchTerm) && 
                        !hod.includes(searchTerm)) {
                        showRow = false;
                    }
                }

                row.style.display = showRow ? '' : 'none';
            }
        }

        // Filter students in department view
        function filterStudents() {
            const searchTerm = document.getElementById('studentSearch').value.toLowerCase();
            const table = document.getElementById('studentsTable');
            if (!table) return;
            
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                
                let showRow = false;
                
                // Search in student ID, name, email, programme
                for (let j = 0; j < 4; j++) {
                    if (cells[j] && cells[j].textContent.toLowerCase().includes(searchTerm)) {
                        showRow = true;
                        break;
                    }
                }

                row.style.display = showRow ? '' : 'none';
            }
        }

        // Sidebar toggle
        document.querySelector('.sidebar-toggle').addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('show');
            
            // Add overlay for mobile
            let overlay = document.querySelector('.sidebar-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'sidebar-overlay';
                document.body.appendChild(overlay);
                
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                });
            }
            
            overlay.classList.toggle('show');
        });

        // Theme toggle
        function toggleTheme() {
            document.body.classList.toggle('dark-theme');
            const isDark = document.body.classList.contains('dark-theme');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            
            const icon = document.querySelector('.theme-toggle i');
            icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Load saved theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-theme');
                document.querySelector('.theme-toggle i').className = 'fas fa-sun';
            }
        });

        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>