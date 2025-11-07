<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has enrollment officer role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Enrollment Officer', $pdo);

// Get enrollment officer profile
$stmt = $pdo->prepare("SELECT sp.full_name, sp.staff_id FROM staff_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$enrollmentOfficer = $stmt->fetch();

// Handle application actions (approve/reject) - for dashboard quick actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['application_id'])) {
        $application_id = $_POST['application_id'];
        $current_user_id = currentUserId();
        
        try {
            switch ($_POST['action']) {
                case 'approve':
                    $stmt = $pdo->prepare("UPDATE applications SET status = 'approved', processed_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$current_user_id, $application_id]);
                    $message = "Application approved successfully!";
                    $messageType = "success";
                    break;
                    
                case 'reject':
                    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
                    $stmt = $pdo->prepare("UPDATE applications SET status = 'rejected', rejection_reason = ?, processed_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$rejection_reason, $current_user_id, $application_id]);
                    $message = "Application rejected successfully!";
                    $messageType = "success";
                    break;
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Get dashboard statistics
$stats = [];

// Count pending applications by type
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN p.name LIKE '%Business%' OR p.name LIKE '%Admin%' OR p.name LIKE '%Diploma%' THEN 1 ELSE 0 END) as undergrad,
        SUM(CASE WHEN p.name LIKE '%Computer%' OR p.name LIKE '%IT%' OR p.name LIKE '%Certificate%' THEN 1 ELSE 0 END) as short_courses
    FROM applications a
    LEFT JOIN programme p ON a.programme_id = p.id
    WHERE a.status = 'pending'
");
$stmt->execute();
$stats['pending_applications'] = $stmt->fetch();

// Count approved applications
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE status = 'approved'");
$stmt->execute();
$stats['approved_applications'] = $stmt->fetchColumn();

// Count rejected applications
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE status = 'rejected'");
$stmt->execute();
$stats['rejected_applications'] = $stmt->fetchColumn();

// Get pending applications by category
$stmt = $pdo->prepare("
    SELECT a.*, p.name as programme_name, i.name as intake_name,
           CASE 
               WHEN p.name LIKE '%Business%' OR p.name LIKE '%Admin%' OR p.name LIKE '%Diploma%' THEN 'undergraduate'
               WHEN p.name LIKE '%Computer%' OR p.name LIKE '%IT%' OR p.name LIKE '%Certificate%' THEN 'short_course'
               ELSE 'other'
           END as category
    FROM applications a
    LEFT JOIN programme p ON a.programme_id = p.id
    LEFT JOIN intake i ON a.intake_id = i.id
    WHERE a.status = 'pending'
    ORDER BY a.created_at DESC
    LIMIT 10
");
$stmt->execute();
$pendingApplications = $stmt->fetchAll();

// Group applications by category
$applicationsByCategory = [
    'undergraduate' => [],
    'short_course' => [],
    'other' => []
];

foreach ($pendingApplications as $application) {
    $category = $application['category'];
    if (!isset($applicationsByCategory[$category])) {
        $applicationsByCategory[$category] = [];
    }
    $applicationsByCategory[$category][] = $application;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Dashboard - LSC SRMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
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
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 5px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-body p {
            margin: 10px 0;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        #documents_list {
            list-style-type: none;
            padding: 0;
        }
        
        #documents_list li {
            margin: 5px 0;
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($enrollmentOfficer['full_name'] ?? 'Enrollment Officer'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($enrollmentOfficer['staff_id'] ?? 'N/A'); ?>)</span>
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
            <h3><i class="fas fa-tachometer-alt"></i> Enrollment Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Applications</h4>
                <a href="undergraduate_applications.php" class="nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Undergraduate</span>
                </a>
                <a href="short_courses_applications.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <span>Short Courses</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>My Approvals</h4>
                <a href="my_approvals.php" class="nav-item">
                    <i class="fas fa-thumbs-up"></i>
                    <span>My Approvals</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports</h4>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Enrollment Reports</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-tachometer-alt"></i> Enrollment Dashboard</h1>
            <p>Manage student enrollment applications</p>
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
                <div class="stat-icon orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['pending_applications']['total']); ?></h3>
                    <p>Pending Applications</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['approved_applications']); ?></h3>
                    <p>Approved Applications</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['rejected_applications']); ?></h3>
                    <p>Rejected Applications</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['pending_applications']['undergrad'] ?? 0); ?></h3>
                    <p>Undergraduate</p>
                </div>
            </div>
        </div>
        
        <!-- Applications by Category -->
        <div class="applications-section">
            <h2><i class="fas fa-folder-open"></i> Applications by Category</h2>
            
            <!-- Undergraduate Applications -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-graduation-cap"></i> Undergraduate Applications (<?php echo count($applicationsByCategory['undergraduate']); ?>)</h3>
                </div>
                <div class="panel-content">
                    <?php if (empty($applicationsByCategory['undergraduate'])): ?>
                        <div class="empty-state">
                            <i class="fas fa-graduation-cap"></i>
                            <p>No pending undergraduate applications</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Programme</th>
                                        <th>Intake</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applicationsByCategory['undergraduate'] as $app): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['email']); ?></td>
                                            <td><?php echo htmlspecialchars($app['programme_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['intake_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($app['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick='viewApplication(<?php echo json_encode($app, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="btn btn-sm btn-success" onclick="approveApplication(<?php echo (int)$app['id']; ?>)">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="rejectApplication(<?php echo (int)$app['id']; ?>)">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Short Courses Applications -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-book"></i> Short Courses Applications (<?php echo count($applicationsByCategory['short_course']); ?>)</h3>
                </div>
                <div class="panel-content">
                    <?php if (empty($applicationsByCategory['short_course'])): ?>
                        <div class="empty-state">
                            <i class="fas fa-book"></i>
                            <p>No pending short courses applications</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Programme</th>
                                        <th>Intake</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applicationsByCategory['short_course'] as $app): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['email']); ?></td>
                                            <td><?php echo htmlspecialchars($app['programme_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['intake_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($app['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick='viewApplication(<?php echo json_encode($app, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="btn btn-sm btn-success" onclick="approveApplication(<?php echo (int)$app['id']; ?>)">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="rejectApplication(<?php echo (int)$app['id']; ?>)">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
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
    </main>

    <!-- View Application Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-alt"></i> Application Details</h3>
                <span class="close" onclick="closeModal('viewModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p><strong>Name:</strong> <span id="view_name"></span></p>
                <p><strong>Email:</strong> <span id="view_email"></span></p>
                <p><strong>Programme:</strong> <span id="view_programme"></span></p>
                <p><strong>Intake:</strong> <span id="view_intake"></span></p>
                <p><strong>Submitted:</strong> <span id="view_submitted"></span></p>
                <div id="documents_section">
                    <strong>Documents:</strong>
                    <ul id="documents_list"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-success" onclick="approveFromModal()"><i class="fas fa-check"></i> Approve</button>
                <button class="btn btn-danger" onclick="rejectFromModal()"><i class="fas fa-times"></i> Reject</button>
            </div>
        </div>
    </div>

    <!-- Reject Reason Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Reject Application</h3>
                <span class="close" onclick="closeModal('rejectModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" id="reject_application_id" name="application_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="rejection_reason">Rejection Reason</label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="4" placeholder="Explain why the application is being rejected..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Approval Confirmation Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-check-circle"></i> Approve Application</h3>
                <span class="close" onclick="closeModal('approveModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" id="approve_application_id" name="application_id">
                <div class="modal-body">
                    <p>Are you sure you want to approve this application?</p>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Approve</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        let currentApplicationId = null;
        
        function viewApplication(app) {
            // Set application details in the modal
            document.getElementById('view_name').textContent = app.full_name || 'N/A';
            document.getElementById('view_email').textContent = app.email || 'N/A';
            document.getElementById('view_programme').textContent = app.programme_name || 'N/A';
            document.getElementById('view_intake').textContent = app.intake_name || 'N/A';
            document.getElementById('view_submitted').textContent = app.created_at ? new Date(app.created_at).toLocaleDateString() : 'N/A';
            
            // Handle documents
            const docsList = document.getElementById('documents_list');
            docsList.innerHTML = '';
            
            try {
                // Parse documents JSON
                const documents = app.documents ? JSON.parse(app.documents) : {};
                
                if (Array.isArray(documents)) {
                    if (documents.length > 0) {
                        documents.forEach(doc => {
                            const li = document.createElement('li');
                            if (typeof doc === 'string') {
                                li.textContent = doc;
                            } else if (doc.path && doc.name) {
                                li.innerHTML = `<a href="../${doc.path}" download="${doc.name}">${doc.name}</a>`;
                            } else {
                                li.textContent = JSON.stringify(doc);
                            }
                            docsList.appendChild(li);
                        });
                    } else {
                        docsList.innerHTML = '<li>No documents attached</li>';
                    }
                } else if (typeof documents === 'object' && documents !== null) {
                    // Handle object format
                    let hasDocuments = false;
                    for (const key in documents) {
                        if (typeof documents[key] === 'object' && documents[key].path) {
                            const li = document.createElement('li');
                            li.innerHTML = `<a href="../${documents[key].path}" download="${documents[key].name}">${documents[key].name}</a>`;
                            docsList.appendChild(li);
                            hasDocuments = true;
                        } else if (key !== 'path' && key !== 'name' && documents[key]) {
                            const li = document.createElement('li');
                            // Format the label nicely
                            let label = key.replace('_', ' ');
                            // Capitalize first letter
                            label = label.charAt(0).toUpperCase() + label.slice(1);
                            li.innerHTML = `<strong>${label}:</strong> ${documents[key]}`;
                            docsList.appendChild(li);
                            hasDocuments = true;
                        }
                    }
                    if (!hasDocuments) {
                        docsList.innerHTML = '<li>No documents attached</li>';
                    }
                } else {
                    docsList.innerHTML = '<li>No documents attached</li>';
                }
            } catch (e) {
                // Handle case where documents is not valid JSON
                if (app.documents) {
                    const li = document.createElement('li');
                    li.textContent = app.documents;
                    docsList.appendChild(li);
                } else {
                    docsList.innerHTML = '<li>No documents attached</li>';
                }
            }
            
            document.getElementById('viewModal').style.display = 'block';
        }
        
        function approveApplication(applicationId) {
            currentApplicationId = applicationId;
            document.getElementById('approve_application_id').value = applicationId;
            document.getElementById('approveModal').style.display = 'block';
        }
        
        function approveFromModal() {
            if (currentApplicationId) {
                document.getElementById('approve_application_id').value = currentApplicationId;
                document.querySelector('#approveModal form').submit();
            }
        }
        
        function rejectApplication(applicationId) {
            currentApplicationId = applicationId;
            document.getElementById('reject_application_id').value = applicationId;
            document.getElementById('rejectModal').style.display = 'block';
        }
        
        function rejectFromModal() {
            if (currentApplicationId) {
                document.getElementById('reject_application_id').value = currentApplicationId;
                document.querySelector('#rejectModal form').submit();
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>