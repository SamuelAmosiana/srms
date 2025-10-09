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

if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_school':
                    $stmt = $pdo->prepare("INSERT INTO school (name, description, established_year, contact_email, contact_phone, address) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['school_name'],
                        $_POST['description'] ?? null,
                        $_POST['established_year'] ?? null,
                        $_POST['contact_email'] ?? null,
                        $_POST['contact_phone'] ?? null,
                        $_POST['address'] ?? null
                    ]);
                    $message = 'School created successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'edit_school':
                    $stmt = $pdo->prepare("UPDATE school SET name = ?, description = ?, established_year = ?, contact_email = ?, contact_phone = ?, address = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['school_name'],
                        $_POST['description'] ?? null,
                        $_POST['established_year'] ?? null,
                        $_POST['contact_email'] ?? null,
                        $_POST['contact_phone'] ?? null,
                        $_POST['address'] ?? null,
                        $_POST['school_id']
                    ]);
                    $message = 'School updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'delete_school':
                    // Check if school has departments or students
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM department WHERE school_id = ?");
                    $stmt->execute([$_POST['school_id']]);
                    $deptCount = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_profile WHERE school_id = ?");
                    $stmt->execute([$_POST['school_id']]);
                    $studentCount = $stmt->fetchColumn();
                    
                    if ($deptCount > 0 || $studentCount > 0) {
                        $message = 'Cannot delete school: It has ' . ($deptCount > 0 ? $deptCount . ' departments' : '') . 
                                  ($deptCount > 0 && $studentCount > 0 ? ' and ' : '') . 
                                  ($studentCount > 0 ? $studentCount . ' students' : '') . ' assigned to it.';
                        $messageType = 'error';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM school WHERE id = ?");
                        $stmt->execute([$_POST['school_id']]);
                        $message = 'School deleted successfully!';
                        $messageType = 'success';
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get filters
$searchQuery = $_GET['search'] ?? '';

// Build query with filters
$query = "SELECT s.*, 
          COUNT(DISTINCT d.id) as department_count,
          COUNT(DISTINCT sp.user_id) as student_count
          FROM school s 
          LEFT JOIN department d ON s.id = d.school_id
          LEFT JOIN student_profile sp ON s.id = sp.school_id
          WHERE 1=1";

$params = [];

if ($searchQuery) {
    $query .= " AND (s.name LIKE ? OR s.description LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " GROUP BY s.id ORDER BY s.name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$schools = $stmt->fetchAll();

// Get school for editing if specified
$editSchool = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM school WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editSchool = $stmt->fetch();
}

// Get school details for viewing if specified
$viewSchool = null;
$schoolStudents = [];
$schoolDepartments = [];
if (isset($_GET['view'])) {
    $stmt = $pdo->prepare("SELECT * FROM school WHERE id = ?");
    $stmt->execute([$_GET['view']]);
    $viewSchool = $stmt->fetch();
    
    if ($viewSchool) {
        // Get students in this school
        $stmt = $pdo->prepare("
            SELECT sp.*, u.username, u.email as user_email, u.is_active,
                   p.name as programme_name, d.name as department_name
            FROM student_profile sp
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN programme p ON sp.programme_id = p.id
            LEFT JOIN department d ON sp.department_id = d.id
            WHERE sp.school_id = ?
            ORDER BY sp.full_name
        ");
        $stmt->execute([$_GET['view']]);
        $schoolStudents = $stmt->fetchAll();
        
        // Get departments in this school
        $stmt = $pdo->prepare("
            SELECT d.*, COUNT(sp.user_id) as student_count
            FROM department d
            LEFT JOIN student_profile sp ON d.id = sp.department_id
            WHERE d.school_id = ?
            GROUP BY d.id
            ORDER BY d.name
        ");
        $stmt->execute([$_GET['view']]);
        $schoolDepartments = $stmt->fetchAll();
    }
}

// Update school table structure if needed
try {
    $pdo->exec("ALTER TABLE school ADD COLUMN IF NOT EXISTS description TEXT");
    $pdo->exec("ALTER TABLE school ADD COLUMN IF NOT EXISTS established_year INT");
    $pdo->exec("ALTER TABLE school ADD COLUMN IF NOT EXISTS contact_email VARCHAR(255)");
    $pdo->exec("ALTER TABLE school ADD COLUMN IF NOT EXISTS contact_phone VARCHAR(50)");
    $pdo->exec("ALTER TABLE school ADD COLUMN IF NOT EXISTS address TEXT");
    $pdo->exec("ALTER TABLE school ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
} catch (Exception $e) {
    // Columns might already exist
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schools - LSC SRMS</title>
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
                <a href="upload_users.php" class="nav-item">
                    <i class="fas fa-upload"></i>
                    <span>Bulk Upload</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Academic Structure</h4>
                <a href="manage_schools.php" class="nav-item active">
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
            <h1><i class="fas fa-university"></i> School Management</h1>
            <p>Create, edit, and manage academic schools within the institution</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Navigation Actions -->
        <div class="action-bar">
            <div class="action-left">
                <?php if (isset($_GET['view'])): ?>
                    <a href="manage_schools.php" class="btn btn-orange">
                        <i class="fas fa-arrow-left"></i> Back to Schools
                    </a>
                <?php endif; ?>
            </div>
            <div class="action-right">
                <?php if (!isset($_GET['view'])): ?>
                    <button onclick="showAddForm()" class="btn btn-green">
                        <i class="fas fa-plus"></i> Add New School
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="filters-section">
            <div class="filters-card">
                <form method="GET" class="filters-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="search">Search Schools</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search by name or description">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-green">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <a href="manage_schools.php" class="btn btn-orange">
                                <i class="fas fa-refresh"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add/Edit School Form -->
        <div class="form-section" id="schoolForm" style="<?php echo $editSchool ? 'display: block;' : 'display: none;'; ?>">
            <div class="form-card">
                <h2>
                    <i class="fas fa-<?php echo $editSchool ? 'edit' : 'plus'; ?>"></i>
                    <?php echo $editSchool ? 'Edit School' : 'Add New School'; ?>
                </h2>
                
                <form method="POST" class="school-form">
                    <input type="hidden" name="action" value="<?php echo $editSchool ? 'edit_school' : 'add_school'; ?>">
                    <?php if ($editSchool): ?>
                        <input type="hidden" name="school_id" value="<?php echo $editSchool['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="school_name">School Name *</label>
                            <input type="text" id="school_name" name="school_name" required 
                                   value="<?php echo htmlspecialchars($editSchool['name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="established_year">Established Year</label>
                            <input type="number" id="established_year" name="established_year" min="1800" max="<?php echo date('Y'); ?>"
                                   value="<?php echo htmlspecialchars($editSchool['established_year'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact_email">Contact Email</label>
                            <input type="email" id="contact_email" name="contact_email" 
                                   value="<?php echo htmlspecialchars($editSchool['contact_email'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_phone">Contact Phone</label>
                            <input type="text" id="contact_phone" name="contact_phone" 
                                   value="<?php echo htmlspecialchars($editSchool['contact_phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" 
                                      placeholder="Brief description of the school"><?php echo htmlspecialchars($editSchool['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="2" 
                                      placeholder="School physical address"><?php echo htmlspecialchars($editSchool['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-green">
                            <i class="fas fa-save"></i> <?php echo $editSchool ? 'Update School' : 'Create School'; ?>
                        </button>
                        <button type="button" onclick="hideForm()" class="btn btn-orange">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Schools Table -->
        <div class="table-section">
            <div class="table-card">
                <div class="table-header">
                    <h2><i class="fas fa-list"></i> Schools List (<?php echo count($schools); ?> schools)</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>School Name</th>
                                <th>Description</th>
                                <th>Established</th>
                                <th>Departments</th>
                                <th>Students</th>
                                <th>Contact</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schools as $school): ?>
                                <tr>
                                    <td><?php echo $school['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($school['name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($school['description'] ?? 'No description', 0, 50)) . (strlen($school['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                    <td><?php echo $school['established_year'] ?? 'N/A'; ?></td>
                                    <td>
                                        <span class="count-badge orange"><?php echo $school['department_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="count-badge green"><?php echo $school['student_count']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($school['contact_email']): ?>
                                            <small><?php echo htmlspecialchars($school['contact_email']); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">No contact</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <a href="?view=<?php echo $school['id']; ?>" class="btn-icon btn-view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="?edit=<?php echo $school['id']; ?>" class="btn-icon btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($school['department_count'] == 0 && $school['student_count'] == 0): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this school?');">
                                                <input type="hidden" name="action" value="delete_school">
                                                <input type="hidden" name="school_id" value="<?php echo $school['id']; ?>">
                                                <button type="submit" class="btn-icon btn-delete" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        function showAddForm() {
            document.getElementById('schoolForm').style.display = 'block';
            document.getElementById('school_name').focus();
        }
        
        function hideForm() {
            document.getElementById('schoolForm').style.display = 'none';
        }
        
        // Auto-show form if editing
        <?php if ($editSchool): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showAddForm();
            });
        <?php endif; ?>
    </script>
</body>
</html>