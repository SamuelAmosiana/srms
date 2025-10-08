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
                case 'add_role':
                    $stmt = $pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
                    $stmt->execute([$_POST['role_name'], $_POST['description']]);
                    $message = 'Role created successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'edit_role':
                    $stmt = $pdo->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$_POST['role_name'], $_POST['description'], $_POST['role_id']]);
                    $message = 'Role updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'delete_role':
                    // Check if role is in use
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE role_id = ?");
                    $stmt->execute([$_POST['role_id']]);
                    $inUse = $stmt->fetchColumn();
                    
                    if ($inUse > 0) {
                        $message = 'Cannot delete role: It is currently assigned to users.';
                        $messageType = 'error';
                    } else {
                        // Delete role permissions first, then role
                        $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                        $stmt->execute([$_POST['role_id']]);
                        
                        $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
                        $stmt->execute([$_POST['role_id']]);
                        
                        $message = 'Role deleted successfully!';
                        $messageType = 'success';
                    }
                    break;
                    
                case 'add_permission':
                    $stmt = $pdo->prepare("INSERT INTO permissions (name, description) VALUES (?, ?)");
                    $stmt->execute([$_POST['permission_name'], $_POST['description']]);
                    $message = 'Permission created successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'edit_permission':
                    $stmt = $pdo->prepare("UPDATE permissions SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$_POST['permission_name'], $_POST['description'], $_POST['permission_id']]);
                    $message = 'Permission updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'delete_permission':
                    // Delete from role_permissions and user_permissions first
                    $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE permission_id = ?");
                    $stmt->execute([$_POST['permission_id']]);
                    
                    // Check if user_permissions table exists, if not create it
                    try {
                        $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE permission_id = ?");
                        $stmt->execute([$_POST['permission_id']]);
                    } catch (Exception $e) {
                        // Table doesn't exist, we'll create it later
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM permissions WHERE id = ?");
                    $stmt->execute([$_POST['permission_id']]);
                    
                    $message = 'Permission deleted successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'update_role_permissions':
                    $pdo->beginTransaction();
                    
                    // Delete existing role permissions
                    $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                    $stmt->execute([$_POST['role_id']]);
                    
                    // Insert new permissions
                    if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                        $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                        foreach ($_POST['permissions'] as $permissionId) {
                            $stmt->execute([$_POST['role_id'], $permissionId]);
                        }
                    }
                    
                    $pdo->commit();
                    $message = 'Role permissions updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'update_user_permissions':
                    // Create user_permissions table if it doesn't exist
                    $pdo->exec("CREATE TABLE IF NOT EXISTS user_permissions (
                        user_id INT NOT NULL,
                        permission_id INT NOT NULL,
                        granted TINYINT(1) DEFAULT 1,
                        granted_by INT,
                        granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (user_id, permission_id),
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
                        FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
                    )");
                    
                    $pdo->beginTransaction();
                    
                    // Delete existing user permissions
                    $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    
                    // Insert new permissions
                    if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                        $stmt = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_id, granted, granted_by) VALUES (?, ?, ?, ?)");
                        foreach ($_POST['permissions'] as $permissionId => $granted) {
                            $stmt->execute([$_POST['user_id'], $permissionId, $granted, currentUserId()]);
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
    // Get role permissions for this user
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roles & Permissions - LSC SRMS</title>
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
                                                <?php if ($role['user_count'] == 0): ?>
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

    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>