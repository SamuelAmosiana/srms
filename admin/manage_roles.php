<?php
require '../config.php';
require '../auth/auth.php';

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
                case 'add_role':
                    $role_name = trim($_POST['role_name']);
                    $description = trim($_POST['description']);
                    if (empty($role_name)) {
                        throw new Exception('Role name is required!');
                    }
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE name = ?");
                    $stmt->execute([$role_name]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('Role name already exists!');
                    }
                    $stmt = $pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
                    $stmt->execute([$role_name, $description]);
                    $message = 'Role created successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'edit_role':
                    $role_id = $_POST['role_id'];
                    $role_name = trim($_POST['role_name']);
                    $description = trim($_POST['description']);
                    if (empty($role_name)) {
                        throw new Exception('Role name is required!');
                    }
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE name = ? AND id != ?");
                    $stmt->execute([$role_name, $role_id]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('Role name already exists!');
                    }
                    $stmt = $pdo->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$role_name, $description, $role_id]);
                    $message = 'Role updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'delete_role':
                    $role_id = $_POST['role_id'];
                    $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
                    $stmt->execute([$role_id]);
                    $role = $stmt->fetch();
                    $protected_roles = ['lecturer', 'super_admin', 'student', 'sub_admin_finance'];
                    if (in_array($role['name'], $protected_roles)) {
                        throw new Exception('Cannot delete protected role!');
                    }
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE role_id = ?");
                    $stmt->execute([$role_id]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('Cannot delete role: It is currently assigned to users.');
                    }
                    $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                    $stmt->execute([$role_id]);
                    $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
                    $stmt->execute([$role_id]);
                    $message = 'Role deleted successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'add_permission':
                    $perm_name = trim($_POST['permission_name']);
                    $description = trim($_POST['description']);
                    if (empty($perm_name)) {
                        throw new Exception('Permission name is required!');
                    }
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE name = ?");
                    $stmt->execute([$perm_name]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('Permission name already exists!');
                    }
                    $stmt = $pdo->prepare("INSERT INTO permissions (name, description) VALUES (?, ?)");
                    $stmt->execute([$perm_name, $description]);
                    $message = 'Permission created successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'edit_permission':
                    $perm_id = $_POST['permission_id'];
                    $perm_name = trim($_POST['permission_name']);
                    $description = trim($_POST['description']);
                    if (empty($perm_name)) {
                        throw new Exception('Permission name is required!');
                    }
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE name = ? AND id != ?");
                    $stmt->execute([$perm_name, $perm_id]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('Permission name already exists!');
                    }
                    $stmt = $pdo->prepare("UPDATE permissions SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$perm_name, $description, $perm_id]);
                    $message = 'Permission updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'delete_permission':
                    $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE permission_id = ?");
                    $stmt->execute([$_POST['permission_id']]);
                    $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE permission_id = ?");
                    $stmt->execute([$_POST['permission_id']]);
                    $stmt = $pdo->prepare("DELETE FROM permissions WHERE id = ?");
                    $stmt->execute([$_POST['permission_id']]);
                    $message = 'Permission deleted successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'update_role_permissions':
                    $pdo->beginTransaction();
                    $role_id = $_POST['role_id'];
                    $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                    $stmt->execute([$role_id]);
                    if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                        $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                        foreach ($_POST['permissions'] as $permission_id) {
                            $stmt->execute([$role_id, $permission_id]);
                        }
                    }
                    $pdo->commit();
                    $message = 'Role permissions updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'update_user_permissions':
                    $pdo->beginTransaction();
                    $user_id = $_POST['user_id'];
                    $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                        $stmt = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_id, granted, granted_by) VALUES (?, ?, ?, ?)");
                        foreach ($_POST['permissions'] as $permission_id => $granted) {
                            $stmt->execute([$user_id, $permission_id, $granted, currentUserId()]);
                        }
                    }
                    $pdo->commit();
                    $message = 'User permissions updated successfully!';
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

// Get current tab
$activeTab = $_GET['tab'] ?? 'roles';

// Get roles
$stmt = $pdo->query("SELECT r.*, COUNT(ur.user_id) as user_count 
                     FROM roles r 
                     LEFT JOIN user_roles ur ON r.id = ur.role_id 
                     GROUP BY r.id 
                     ORDER BY r.name");
$roles = $stmt->fetchAll();

// Get permissions
$stmt = $pdo->query("SELECT * FROM permissions ORDER BY name");
$permissions = $stmt->fetchAll();

// Get role for editing if specified
$editRole = null;
if (isset($_GET['edit_role'])) {
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$_GET['edit_role']]);
    $editRole = $stmt->fetch();
}

// Get permission for editing if specified
$editPermission = null;
if (isset($_GET['edit_permission'])) {
    $stmt = $pdo->prepare("SELECT * FROM permissions WHERE id = ?");
    $stmt->execute([$_GET['edit_permission']]);
    $editPermission = $stmt->fetch();
}

// Get role permissions for management
$rolePermissions = [];
if (isset($_GET['manage_role'])) {
    $stmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $stmt->execute([$_GET['manage_role']]);
    $rolePermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get users for permission management
$users = [];
if ($activeTab === 'user_permissions') {
    $stmt = $pdo->query("SELECT u.id, u.username, 
                         COALESCE(ap.full_name, sp.full_name, stp.full_name) as full_name,
                         r.name as role_name
                         FROM users u 
                         JOIN user_roles ur ON u.id = ur.user_id
                         JOIN roles r ON ur.role_id = r.id
                         LEFT JOIN admin_profile ap ON u.id = ap.user_id
                         LEFT JOIN student_profile sp ON u.id = sp.user_id
                         LEFT JOIN staff_profile stp ON u.id = stp.user_id
                         ORDER BY full_name");
    $users = $stmt->fetchAll();
}

// Get user permissions if managing specific user
$userPermissions = [];
if (isset($_GET['manage_user'])) {
    try {
        $stmt = $pdo->prepare("SELECT p.id, p.name, p.description, 
                               CASE WHEN rp.permission_id IS NOT NULL THEN 1 ELSE 0 END as role_has,
                               COALESCE(up.granted, 
                                   CASE WHEN rp.permission_id IS NOT NULL THEN 1 ELSE 0 END
                               ) as user_has
                               FROM permissions p
                               LEFT JOIN role_permissions rp ON p.id = rp.permission_id 
                               AND rp.role_id = (SELECT role_id FROM user_roles WHERE user_id = ?)
                               LEFT JOIN user_permissions up ON p.id = up.permission_id AND up.user_id = ?
                               ORDER BY p.name");
        $stmt->execute([$_GET['manage_user'], $_GET['manage_user']]);
        $userPermissions = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Handle case where user_permissions table doesn't exist
        if (strpos($e->getMessage(), 'user_permissions') !== false) {
            // Fallback query without user_permissions table
            $stmt = $pdo->prepare("SELECT p.id, p.name, p.description, 
                                   CASE WHEN rp.permission_id IS NOT NULL THEN 1 ELSE 0 END as role_has,
                                   CASE WHEN rp.permission_id IS NOT NULL THEN 1 ELSE 0 END as user_has
                                   FROM permissions p
                                   LEFT JOIN role_permissions rp ON p.id = rp.permission_id 
                                   AND rp.role_id = (SELECT role_id FROM user_roles WHERE user_id = ?)
                                   ORDER BY p.name");
            $stmt->execute([$_GET['manage_user']]);
            $userPermissions = $stmt->fetchAll();
        } else {
            throw $e; // Re-throw if it's a different error
        }
    }
}

// Seed default roles and permissions
try {
    $default_roles = [
        ['lecturer', 'Lecturer role'],
        ['super_admin', 'Super Admin role'],
        ['student', 'Student role'],
        ['sub_admin_finance', 'Sub Admin (Finance) role']
    ];
    foreach ($default_roles as $role) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE name = ?");
        $stmt->execute([$role[0]]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
            $stmt->execute($role);
        }
    }
    
    $default_permissions = [
        ['manage_users', 'Manage user accounts and profiles'],
        ['manage_roles', 'Create, edit, and delete roles and permissions'],
        ['manage_academic_structure', 'Manage schools, departments, programmes, courses'],
        ['manage_results', 'Enter and manage student results and grades'],
        ['enrollment_approvals', 'Review and approve enrollment applications'],
        ['course_registrations', 'Approve student course registrations'],
        ['reports', 'Generate and download system reports'],
        ['profile_access', 'View and edit personal profile']
    ];
    foreach ($default_permissions as $perm) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE name = ?");
        $stmt->execute([$perm[0]]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO permissions (name, description) VALUES (?, ?)");
            $stmt->execute($perm);
        }
    }
    
    // Seed default role permissions
    $default_role_permissions = [
        'super_admin' => ['manage_users', 'manage_roles', 'manage_academic_structure', 'manage_results', 'enrollment_approvals', 'course_registrations', 'reports', 'profile_access'],
        'sub_admin_finance' => ['manage_users', 'reports', 'profile_access'],
        'lecturer' => ['manage_results', 'profile_access'],
        'student' => ['profile_access']
    ];
    foreach ($default_role_permissions as $role_name => $perm_names) {
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
        $stmt->execute([$role_name]);
        $role_id = $stmt->fetchColumn();
        if ($role_id) {
            foreach ($perm_names as $perm_name) {
                $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ?");
                $stmt->execute([$perm_name]);
                $perm_id = $stmt->fetchColumn();
                if ($perm_id) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?");
                    $stmt->execute([$role_id, $perm_id]);
                    if ($stmt->fetchColumn() == 0) {
                        $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                        $stmt->execute([$role_id, $perm_id]);
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    // Tables might already exist
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roles & Permissions - LSC SRMS</title>
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
                <a href="manage_roles.php" class="nav-item active">
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
            <h1><i class="fas fa-shield-alt"></i> Roles & Permissions Management</h1>
            <p>Manage system roles, permissions, and user-specific access controls</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <a href="?tab=roles" class="tab-item <?php echo $activeTab === 'roles' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i> Roles
            </a>
            <a href="?tab=permissions" class="tab-item <?php echo $activeTab === 'permissions' ? 'active' : ''; ?>">
                <i class="fas fa-key"></i> Permissions
            </a>
            <a href="?tab=role_permissions" class="tab-item <?php echo $activeTab === 'role_permissions' ? 'active' : ''; ?>">
                <i class="fas fa-link"></i> Role Permissions
            </a>
            <a href="?tab=user_permissions" class="tab-item <?php echo $activeTab === 'user_permissions' ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i> User Permissions
            </a>
        </div>

        <!-- Roles Tab -->
        <?php if ($activeTab === 'roles'): ?>
            <div class="tab-content">
                <!-- Add/Edit Role Form -->
                <div class="form-section">
                    <div class="form-card">
                        <h2>
                            <i class="fas fa-<?php echo $editRole ? 'edit' : 'plus'; ?>"></i>
                            <?php echo $editRole ? 'Edit Role' : 'Add New Role'; ?>
                        </h2>
                        
                        <form method="POST" class="role-form">
                            <input type="hidden" name="action" value="<?php echo $editRole ? 'edit_role' : 'add_role'; ?>">
                            <?php if ($editRole): ?>
                                <input type="hidden" name="role_id" value="<?php echo $editRole['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="role_name">Role Name *</label>
                                    <input type="text" id="role_name" name="role_name" required 
                                           value="<?php echo htmlspecialchars($editRole['name'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <input type="text" id="description" name="description" 
                                           value="<?php echo htmlspecialchars($editRole['description'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-green">
                                    <i class="fas fa-save"></i> <?php echo $editRole ? 'Update Role' : 'Create Role'; ?>
                                </button>
                                <?php if ($editRole): ?>
                                    <a href="?tab=roles" class="btn btn-orange">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Roles Table -->
                <div class="table-section">
                    <div class="table-card">
                        <div class="table-header">
                            <h2><i class="fas fa-list"></i> System Roles (<?php echo count($roles); ?> roles)</h2>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Role Name</th>
                                        <th>Description</th>
                                        <th>Users</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roles as $role): ?>
                                        <tr>
                                            <td><?php echo $role['id']; ?></td>
                                            <td>
                                                <span class="role-badge role-<?php echo strtolower(str_replace([' ', '(', ')'], ['-', '', ''], $role['name'])); ?>">
                                                    <?php echo htmlspecialchars($role['name']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($role['description'] ?? 'No description'); ?></td>
                                            <td>
                                                <span class="user-count"><?php echo $role['user_count']; ?> users</span>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($role['created_at'] ?? 'now')); ?></td>
                                            <td class="actions">
                                                <a href="?tab=roles&edit_role=<?php echo $role['id']; ?>" class="btn-icon btn-edit" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?tab=role_permissions&manage_role=<?php echo $role['id']; ?>" class="btn-icon btn-permissions" title="Manage Permissions">
                                                    <i class="fas fa-key"></i>
                                                </a>
                                                <?php if ($role['user_count'] == 0 && !in_array($role['name'], ['lecturer', 'super_admin', 'student', 'sub_admin_finance'])): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this role?');">
                                                        <input type="hidden" name="action" value="delete_role">
                                                        <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
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
            </div>
        <?php endif; ?>

        <!-- Permissions Tab -->
        <?php if ($activeTab === 'permissions'): ?>
            <div class="tab-content">
                <!-- Add/Edit Permission Form -->
                <div class="form-section">
                    <div class="form-card">
                        <h2>
                            <i class="fas fa-<?php echo $editPermission ? 'edit' : 'plus'; ?>"></i>
                            <?php echo $editPermission ? 'Edit Permission' : 'Add New Permission'; ?>
                        </h2>
                        
                        <form method="POST" class="permission-form">
                            <input type="hidden" name="action" value="<?php echo $editPermission ? 'edit_permission' : 'add_permission'; ?>">
                            <?php if ($editPermission): ?>
                                <input type="hidden" name="permission_id" value="<?php echo $editPermission['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="permission_name">Permission Name *</label>
                                    <input type="text" id="permission_name" name="permission_name" required 
                                           value="<?php echo htmlspecialchars($editPermission['name'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <input type="text" id="description" name="description" 
                                           value="<?php echo htmlspecialchars($editPermission['description'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-green">
                                    <i class="fas fa-save"></i> <?php echo $editPermission ? 'Update Permission' : 'Create Permission'; ?>
                                </button>
                                <?php if ($editPermission): ?>
                                    <a href="?tab=permissions" class="btn btn-orange">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Permissions Table -->
                <div class="table-section">
                    <div class="table-card">
                        <div class="table-header">
                            <h2><i class="fas fa-list"></i> System Permissions (<?php echo count($permissions); ?> permissions)</h2>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Permission Name</th>
                                        <th>Description</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($permissions as $permission): ?>
                                        <tr>
                                            <td><?php echo $permission['id']; ?></td>
                                            <td>
                                                <span class="permission-badge">
                                                    <?php echo htmlspecialchars($permission['name']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($permission['description'] ?? 'No description'); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($permission['created_at'] ?? 'now')); ?></td>
                                            <td class="actions">
                                                <a href="?tab=permissions&edit_permission=<?php echo $permission['id']; ?>" class="btn-icon btn-edit" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this permission?');">
                                                    <input type="hidden" name="action" value="delete_permission">
                                                    <input type="hidden" name="permission_id" value="<?php echo $permission['id']; ?>">
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
            </div>
        <?php endif; ?>

        <!-- Role Permissions Tab -->
        <?php if ($activeTab === 'role_permissions'): ?>
            <div class="tab-content">
                <div class="form-section">
                    <div class="form-card">
                        <h2><i class="fas fa-link"></i> Assign Role Permissions</h2>
                        <form method="GET">
                            <div class="form-group">
                                <label for="manage_role">Select Role *</label>
                                <select id="manage_role" name="manage_role" onchange="this.form.submit()" required>
                                    <option value="">-- Select Role --</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" <?php echo isset($_GET['manage_role']) && $_GET['manage_role'] == $role['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="tab" value="role_permissions">
                            </div>
                        </form>
                        
                        <?php if (isset($_GET['manage_role'])): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_role_permissions">
                                <input type="hidden" name="role_id" value="<?php echo $_GET['manage_role']; ?>">
                                <div class="table-responsive">
                                    <table class="users-table">
                                        <thead>
                                            <tr>
                                                <th>Permission</th>
                                                <th>Description</th>
                                                <th>Assign</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($permissions as $perm): ?>
                                                <?php
                                                // Restrict certain permissions for specific roles
                                                $restricted = false;
                                                $role_id = $_GET['manage_role'];
                                                $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
                                                $stmt->execute([$role_id]);
                                                $role_name = $stmt->fetchColumn();
                                                if ($role_name === 'student' && !in_array($perm['name'], ['profile_access'])) {
                                                    $restricted = true;
                                                } elseif ($role_name === 'lecturer' && !in_array($perm['name'], ['manage_results', 'profile_access'])) {
                                                    $restricted = true;
                                                }
                                                ?>
                                                <tr <?php echo $restricted ? 'class="disabled"' : ''; ?>>
                                                    <td><?php echo htmlspecialchars($perm['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($perm['description'] ?? 'No description'); ?></td>
                                                    <td>
                                                        <input type="checkbox" name="permissions[]" value="<?php echo $perm['id']; ?>" 
                                                               <?php echo in_array($perm['id'], $rolePermissions) ? 'checked' : ''; ?>
                                                               <?php echo $restricted ? 'disabled' : ''; ?>>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-green"><i class="fas fa-save"></i> Save Permissions</button>
                                    <a href="?tab=role_permissions" class="btn btn-orange"><i class="fas fa-times"></i> Cancel</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- User Permissions Tab -->
        <?php if ($activeTab === 'user_permissions'): ?>
            <div class="tab-content">
                <div class="table-section">
                    <div class="table-card">
                        <div class="table-header">
                            <h2><i class="fas fa-user-shield"></i> Manage User Permissions</h2>
                        </div>
                        <div class="table-responsive">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td>
                                                <span class="role-badge role-<?php echo strtolower(str_replace([' ', '(', ')'], ['-', '', ''], $user['role_name'])); ?>">
                                                    <?php echo htmlspecialchars($user['role_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?tab=user_permissions&manage_user=<?php echo $user['id']; ?>" class="btn-icon btn-permissions" title="Manage Permissions">
                                                    <i class="fas fa-key"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($_GET['manage_user'])): ?>
                    <div class="form-section">
                        <div class="form-card">
                            <h2><i class="fas fa-user-shield"></i> Manage Permissions for <?php echo htmlspecialchars($users[array_search($_GET['manage_user'], array_column($users, 'id'))]['full_name'] ?? 'User'); ?></h2>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_user_permissions">
                                <input type="hidden" name="user_id" value="<?php echo $_GET['manage_user']; ?>">
                                <div class="table-responsive">
                                    <table class="users-table">
                                        <thead>
                                            <tr>
                                                <th>Permission</th>
                                                <th>Description</th>
                                                <th>From Role</th>
                                                <th>Grant</th>
                                                <th>Deny</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($userPermissions as $perm): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($perm['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($perm['description'] ?? 'No description'); ?></td>
                                                    <td>
                                                        <span class="status status-<?php echo $perm['role_has'] ? 'success' : 'danger'; ?>">
                                                            <?php echo $perm['role_has'] ? 'Yes' : 'No'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <input type="radio" name="permissions[<?php echo $perm['id']; ?>]" value="1" 
                                                               <?php echo $perm['user_has'] == 1 ? 'checked' : ''; ?>>
                                                    </td>
                                                    <td>
                                                        <input type="radio" name="permissions[<?php echo $perm['id']; ?>]" value="0" 
                                                               <?php echo $perm['user_has'] == 0 ? 'checked' : ''; ?>>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-green"><i class="fas fa-save"></i> Save User Permissions</button>
                                    <a href="?tab=user_permissions" class="btn btn-orange"><i class="fas fa-times"></i> Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <style>
        .tab-navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab-item {
            padding: 10px 20px;
            background: var(--gray);
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .tab-item.active {
            background: var(--primary-green);
            color: white;
        }
        
        .form-card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 2px 8px var(--shadow);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .table-card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 2px 8px var(--shadow);
            padding: 20px;
        }
        
        .users-table tr.disabled {
            opacity: 0.5;
            background: var(--gray-light);
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        .status-success {
            background: var(--success-bg);
            color: var(--success-text);
        }
        
        .status-danger {
            background: var(--danger-bg);
            color: var(--danger-text);
        }
    </style>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>