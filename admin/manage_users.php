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
                case 'add_user':
                    $pdo->beginTransaction();
                    
                    // Insert into users table
                    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, contact, is_active) VALUES (?, ?, ?, ?, 1)");
                    $stmt->execute([
                        $_POST['username'],
                        password_hash($_POST['password'], PASSWORD_DEFAULT),
                        $_POST['email'],
                        $_POST['phone'] ?? null
                    ]);
                    
                    $userId = $pdo->lastInsertId();
                    
                    // Insert role assignment
                    $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    $stmt->execute([$userId, $_POST['role_id']]);
                    
                    // Insert into appropriate profile table based on role
                    $roleStmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
                    $roleStmt->execute([$_POST['role_id']]);
                    $roleName = $roleStmt->fetchColumn();
                    
                    if ($roleName == 'Super Admin') {
                        $stmt = $pdo->prepare("INSERT INTO admin_profile (user_id, full_name, staff_id) VALUES (?, ?, ?)");
                        $stmt->execute([$userId, $_POST['full_name'], $_POST['staff_id']]);
                    } elseif ($roleName == 'Student') {
                        $stmt = $pdo->prepare("INSERT INTO student_profile (user_id, full_name, student_number, NRC, gender) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$userId, $_POST['full_name'], $_POST['student_id'], $_POST['nrc'] ?? null, $_POST['gender'] ?? null]);
                    } elseif ($roleName == 'Lecturer' || $roleName == 'Sub Admin (Finance)') {
                        $stmt = $pdo->prepare("INSERT INTO staff_profile (user_id, full_name, staff_id, NRC, gender, qualification) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$userId, $_POST['full_name'], $_POST['staff_id'], $_POST['nrc'] ?? null, $_POST['gender'] ?? null, $_POST['qualification'] ?? null]);
                    }
                    
                    $pdo->commit();
                    $message = 'User created successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'update_user':
                    $pdo->beginTransaction();
                    
                    // Update users table
                    if (!empty($_POST['password'])) {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, password_hash = ?, email = ?, contact = ?, is_active = ? WHERE id = ?");
                        $stmt->execute([
                            $_POST['username'],
                            password_hash($_POST['password'], PASSWORD_DEFAULT),
                            $_POST['email'],
                            $_POST['phone'] ?? null,
                            $_POST['is_active'],
                            $_POST['user_id']
                        ]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, contact = ?, is_active = ? WHERE id = ?");
                        $stmt->execute([
                            $_POST['username'],
                            $_POST['email'],
                            $_POST['phone'] ?? null,
                            $_POST['is_active'],
                            $_POST['user_id']
                        ]);
                    }
                    
                    // Update role assignment
                    $stmt = $pdo->prepare("UPDATE user_roles SET role_id = ? WHERE user_id = ?");
                    $stmt->execute([$_POST['role_id'], $_POST['user_id']]);
                    
                    // Update profile based on role
                    $roleStmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
                    $roleStmt->execute([$_POST['role_id']]);
                    $roleName = $roleStmt->fetchColumn();
                    
                    if ($roleName == 'Super Admin') {
                        $stmt = $pdo->prepare("UPDATE admin_profile SET full_name = ?, staff_id = ? WHERE user_id = ?");
                        $stmt->execute([$_POST['full_name'], $_POST['staff_id'], $_POST['user_id']]);
                    } elseif ($roleName == 'Student') {
                        $stmt = $pdo->prepare("UPDATE student_profile SET full_name = ?, student_number = ?, NRC = ?, gender = ? WHERE user_id = ?");
                        $stmt->execute([$_POST['full_name'], $_POST['student_id'], $_POST['nrc'] ?? null, $_POST['gender'] ?? null, $_POST['user_id']]);
                    } elseif ($roleName == 'Lecturer' || $roleName == 'Sub Admin (Finance)') {
                        $stmt = $pdo->prepare("UPDATE staff_profile SET full_name = ?, staff_id = ?, NRC = ?, gender = ?, qualification = ? WHERE user_id = ?");
                        $stmt->execute([$_POST['full_name'], $_POST['staff_id'], $_POST['nrc'] ?? null, $_POST['gender'] ?? null, $_POST['qualification'] ?? null, $_POST['user_id']]);
                    }
                    
                    $pdo->commit();
                    $message = 'User updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'delete_user':
                    $pdo->beginTransaction();
                    
                    // Get user role first to determine which profile table to delete from
                    $stmt = $pdo->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    $roleName = $stmt->fetchColumn();
                    
                    // Delete from profile table first
                    if ($roleName == 'Super Admin') {
                        $stmt = $pdo->prepare("DELETE FROM admin_profile WHERE user_id = ?");
                    } elseif ($roleName == 'Student') {
                        $stmt = $pdo->prepare("DELETE FROM student_profile WHERE user_id = ?");
                    } elseif ($roleName == 'Lecturer' || $roleName == 'Sub Admin (Finance)') {
                        $stmt = $pdo->prepare("DELETE FROM staff_profile WHERE user_id = ?");
                    }
                    $stmt->execute([$_POST['user_id']]);
                    
                    // Delete from user_roles
                    $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    
                    // Delete from users table
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    
                    $pdo->commit();
                    $message = 'User deleted successfully!';
                    $messageType = 'success';
                    break;
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get filters
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query with filters
$query = "SELECT u.id as user_id, u.username, u.is_active, u.created_at, r.name as role_name,
          COALESCE(ap.full_name, sp.full_name, stp.full_name) as full_name,
          u.email,
          COALESCE(ap.staff_id, sp.student_number, stp.staff_id) as identifier
          FROM users u 
          JOIN user_roles ur ON u.id = ur.user_id
          JOIN roles r ON ur.role_id = r.id 
          LEFT JOIN admin_profile ap ON u.id = ap.user_id
          LEFT JOIN student_profile sp ON u.id = sp.user_id
          LEFT JOIN staff_profile stp ON u.id = stp.user_id
          WHERE 1=1";

$params = [];

if ($roleFilter) {
    $query .= " AND r.name = ?";
    $params[] = $roleFilter;
}

if ($statusFilter !== '') {
    $query .= " AND u.is_active = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $query .= " AND (u.username LIKE ? OR COALESCE(ap.full_name, sp.full_name, stp.full_name) LIKE ? OR u.email LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get roles for dropdown
$stmt = $pdo->query("SELECT id as role_id, name as role_name FROM roles ORDER BY name");
$roles = $stmt->fetchAll();

// Get user for editing if specified
$editUser = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT u.*, r.id as role_id, r.name as role_name,
                          COALESCE(ap.full_name, sp.full_name, stp.full_name) as full_name,
                          COALESCE(ap.staff_id, sp.student_number, stp.staff_id) as identifier,
                          sp.NRC as student_nrc, sp.gender as student_gender,
                          stp.NRC as staff_nrc, stp.gender as staff_gender, stp.qualification
                          FROM users u 
                          JOIN user_roles ur ON u.id = ur.user_id
                          JOIN roles r ON ur.role_id = r.id
                          LEFT JOIN admin_profile ap ON u.id = ap.user_id
                          LEFT JOIN student_profile sp ON u.id = sp.user_id
                          LEFT JOIN staff_profile stp ON u.id = stp.user_id
                          WHERE u.id = ?");
    $stmt->execute([$_GET['edit']]);
    $editUser = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - LSC SRMS</title>
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
                <a href="manage_users.php" class="nav-item active">
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
                <a href="manage_intakes.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Intakes</span>
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
            <h1><i class="fas fa-users"></i> User Management</h1>
            <p>Create, edit, and manage user accounts across the system</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filters and Search -->
        <div class="filters-section">
            <div class="filters-card">
                <form method="GET" class="filters-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="search">Search Users</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search by name, username, or email">
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Filter by Role</label>
                            <select id="role" name="role">
                                <option value="">All Roles</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo htmlspecialchars($role['role_name']); ?>" 
                                            <?php echo $roleFilter === $role['role_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Filter by Status</label>
                            <select id="status" name="status">
                                <option value="">All Status</option>
                                <option value="1" <?php echo $statusFilter === '1' ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo $statusFilter === '0' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-green">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <a href="manage_users.php" class="btn btn-orange">
                                <i class="fas fa-refresh"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add/Edit User Form -->
        <div class="form-section">
            <div class="form-card">
                <h2>
                    <i class="fas fa-<?php echo $editUser ? 'edit' : 'plus'; ?>"></i>
                    <?php echo $editUser ? 'Edit User' : 'Add New User'; ?>
                </h2>
                
                <form method="POST" class="user-form">
                    <input type="hidden" name="action" value="<?php echo $editUser ? 'update_user' : 'add_user'; ?>">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="user_id" value="<?php echo $editUser['user_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" required 
                                   value="<?php echo htmlspecialchars($editUser['username'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password <?php echo $editUser ? '(leave blank to keep current)' : '*'; ?></label>
                            <input type="password" id="password" name="password" <?php echo $editUser ? '' : 'required'; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label for="role_id">Role *</label>
                            <select id="role_id" name="role_id" required onchange="toggleFields()">
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['role_id']; ?>" 
                                            <?php echo ($editUser && $editUser['role_id'] == $role['role_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($editUser): ?>
                        <div class="form-group">
                            <label for="is_active">Status *</label>
                            <select id="is_active" name="is_active" required>
                                <option value="1" <?php echo $editUser['is_active'] ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo !$editUser['is_active'] ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" required 
                                   value="<?php echo htmlspecialchars($editUser['full_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($editUser['email'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group" id="staff_id_group">
                            <label for="staff_id">Staff ID</label>
                            <input type="text" id="staff_id" name="staff_id" 
                                   value="<?php echo htmlspecialchars($editUser['identifier'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group" id="student_id_group" style="display: none;">
                            <label for="student_id">Student Number</label>
                            <input type="text" id="student_id" name="student_id" 
                                   value="<?php echo htmlspecialchars($editUser['identifier'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group" id="phone_group">
                            <label for="phone">Phone/Contact</label>
                            <input type="text" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($editUser['contact'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row" id="additional_fields">
                        <div class="form-group">
                            <label for="nrc">NRC (Optional)</label>
                            <input type="text" id="nrc" name="nrc" 
                                   value="<?php echo htmlspecialchars($editUser['student_nrc'] ?? $editUser['staff_nrc'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="gender">Gender (Optional)</label>
                            <select id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (($editUser['student_gender'] ?? $editUser['staff_gender']) == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (($editUser['student_gender'] ?? $editUser['staff_gender']) == 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (($editUser['student_gender'] ?? $editUser['staff_gender']) == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="qualification_group" style="display: none;">
                            <label for="qualification">Qualification (Optional)</label>
                            <input type="text" id="qualification" name="qualification" 
                                   value="<?php echo htmlspecialchars($editUser['qualification'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-green">
                            <i class="fas fa-save"></i> <?php echo $editUser ? 'Update User' : 'Create User'; ?>
                        </button>
                        <?php if ($editUser): ?>
                            <a href="manage_users.php" class="btn btn-orange">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="table-section">
            <div class="table-card">
                <div class="table-header">
                    <h2><i class="fas fa-list"></i> Users List (<?php echo count($users); ?> users)</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Identifier</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['user_id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo strtolower(str_replace([' ', '(', ')'], ['-', '', ''], $user['role_name'])); ?>">
                                            <?php echo htmlspecialchars($user['role_name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['identifier'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                    <td class="actions">
                                        <a href="?edit=<?php echo $user['user_id']; ?>" class="btn-icon btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" class="btn-icon btn-delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
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
        function toggleFields() {
            const roleSelect = document.getElementById('role_id');
            const staffIdGroup = document.getElementById('staff_id_group');
            const studentIdGroup = document.getElementById('student_id_group');
            const phoneGroup = document.getElementById('phone_group');
            const qualificationGroup = document.getElementById('qualification_group');
            
            // Hide all fields first
            staffIdGroup.style.display = 'none';
            studentIdGroup.style.display = 'none';
            qualificationGroup.style.display = 'none';
            
            // Show phone field for all roles
            phoneGroup.style.display = 'block';
            
            // Show appropriate fields based on role
            const selectedRole = roleSelect.options[roleSelect.selectedIndex].text;
            
            if (selectedRole === 'Student') {
                studentIdGroup.style.display = 'block';
            } else if (selectedRole === 'Lecturer' || selectedRole === 'Sub Admin (Finance)') {
                staffIdGroup.style.display = 'block';
                qualificationGroup.style.display = 'block';
            } else if (selectedRole === 'Super Admin') {
                staffIdGroup.style.display = 'block';
            }
        }
        
        // Initialize fields on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleFields();
        });
    </script>
</body>
</html>