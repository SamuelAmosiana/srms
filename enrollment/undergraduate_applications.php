<?php
require '../config.php';
require '../auth.php';

// Include the new acceptance letter with fees function
require_once '../finance/generate_acceptance_letter_with_fees.php';

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

// Handle application actions (approve/reject)
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['application_id'])) {
        $application_id = $_POST['application_id'];
        $current_user_id = currentUserId(); // Get current enrollment officer ID
        
        try {
            switch ($_POST['action']) {
                case 'approve':
                    // First get application details
                    $app_stmt = $pdo->prepare("
                        SELECT a.*, p.name as programme_name, i.name as intake_name, p.id as programme_id
                        FROM applications a
                        LEFT JOIN programme p ON a.programme_id = p.id
                        LEFT JOIN intake i ON a.intake_id = i.id
                        WHERE a.id = ?
                    ");
                    $app_stmt->execute([$application_id]);
                    $application = $app_stmt->fetch();
                    
                    // Update application status
                    $stmt = $pdo->prepare("UPDATE applications SET status = 'approved', processed_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$current_user_id, $application_id]);
                    
                    // Add to awaiting registration
                    $pending_stmt = $pdo->prepare("INSERT INTO pending_students (full_name, email, programme_id, intake_id, documents, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $pending_stmt->execute([
                        $application['full_name'],
                        $application['email'],
                        $application['programme_id'],
                        $application['intake_id'],
                        $application['documents']
                    ]);
                    
                    // Generate acceptance letter with fees
                    $letter_path = generateAcceptanceLetterWithFees($application, $pdo);
                    
                    $message = "Application approved successfully! Acceptance letter with fees generated.";
                    $messageType = "success";
                    break;
                    
                case 'reject':
                    $rejection_reason = trim($_POST['rejection_reason']);
                    if (empty($rejection_reason)) {
                        throw new Exception("Rejection reason is required!");
                    }
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

// Get undergraduate applications - updated logic to match actual programme names
$stmt = $pdo->prepare("
    SELECT a.*, p.name as programme_name, i.name as intake_name
    FROM applications a
    LEFT JOIN programme p ON a.programme_id = p.id
    LEFT JOIN intake i ON a.intake_id = i.id
    WHERE a.status = 'pending' 
    AND (p.name LIKE '%Business%' OR p.name LIKE '%Admin%' OR p.name LIKE '%Diploma%')
    ORDER BY a.created_at DESC
");
$stmt->execute();
$undergraduateApplications = $stmt->fetchAll();

// Get approved undergraduate applications
$stmt = $pdo->prepare("
    SELECT a.*, p.name as programme_name, i.name as intake_name
    FROM applications a
    LEFT JOIN programme p ON a.programme_id = p.id
    LEFT JOIN intake i ON a.intake_id = i.id
    WHERE a.status = 'approved' 
    AND (p.name LIKE '%Business%' OR p.name LIKE '%Admin%' OR p.name LIKE '%Diploma%')
    ORDER BY a.created_at DESC
");
$stmt->execute();
$approvedUndergraduateApplications = $stmt->fetchAll();

// Get rejected undergraduate applications
$stmt = $pdo->prepare("
    SELECT a.*, p.name as programme_name, i.name as intake_name
    FROM applications a
    LEFT JOIN programme p ON a.programme_id = p.id
    LEFT JOIN intake i ON a.intake_id = i.id
    WHERE a.status = 'rejected' 
    AND (p.name LIKE '%Business%' OR p.name LIKE '%Admin%' OR p.name LIKE '%Diploma%')
    ORDER BY a.created_at DESC
");
$stmt->execute();
$rejectedUndergraduateApplications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Undergraduate Applications - LSC SRMS</title>
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
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Applications</h4>
                <a href="undergraduate_applications.php" class="nav-item active">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Undergraduate</span>
                </a>
                <a href="short_courses_applications.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <span>Short Courses</span>
                </a>
                <a href="corporate_training_applications.php" class="nav-item">
                    <i class="fas fa-building"></i>
                    <span>Corporate Training</span>
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
            <h1><i class="fas fa-graduation-cap"></i> Undergraduate Applications</h1>
            <p>Manage undergraduate student applications</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Undergraduate Applications -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-graduation-cap"></i> Pending Undergraduate Applications (<?php echo count($undergraduateApplications); ?>)</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($undergraduateApplications)): ?>
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
                                <?php foreach ($undergraduateApplications as $app): ?>
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

        <!-- Approved Undergraduate Applications -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-check-circle"></i> Approved Undergraduate Applications (<?php echo count($approvedUndergraduateApplications); ?>)</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($approvedUndergraduateApplications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No approved undergraduate applications</p>
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
                                    <th>Approved Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approvedUndergraduateApplications as $app): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['email']); ?></td>
                                        <td><?php echo htmlspecialchars($app['programme_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['intake_name']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($app['created_at'])); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($app['updated_at'] ?? $app['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rejected Undergraduate Applications -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-times-circle"></i> Rejected Undergraduate Applications (<?php echo count($rejectedUndergraduateApplications); ?>)</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($rejectedUndergraduateApplications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-times-circle"></i>
                        <p>No rejected undergraduate applications</p>
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
                                    <th>Rejected Date</th>
                                    <th>Rejection Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rejectedUndergraduateApplications as $app): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['email']); ?></td>
                                        <td><?php echo htmlspecialchars($app['programme_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['intake_name']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($app['created_at'])); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($app['updated_at'] ?? $app['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($app['rejection_reason'] ?? 'No reason provided'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
                        <label for="rejection_reason">Rejection Reason *</label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="4" required placeholder="Explain why the application is being rejected..."></textarea>
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
            document.getElementById('view_name').textContent = app.full_name || 'N/A';
            document.getElementById('view_email').textContent = app.email || 'N/A';
            document.getElementById('view_programme').textContent = app.programme_name || 'N/A';
            document.getElementById('view_intake').textContent = app.intake_name || 'N/A';
            document.getElementById('view_submitted').textContent = app.created_at ? new Date(app.created_at).toLocaleDateString() : 'N/A';
            
            const docsList = document.getElementById('documents_list');
            docsList.innerHTML = '';
            
            try {
                // Parse documents JSON
                const documents = app.documents ? JSON.parse(app.documents) : {};
                
                // Handle file documents
                if (Array.isArray(documents)) {
                    if (documents.length > 0) {
                        documents.forEach(doc => {
                            const li = document.createElement('li');
                            if (typeof doc === 'string') {
                                // Handle legacy format
                                li.textContent = doc;
                            } else if (doc.path && doc.name) {
                                // Handle new format with path and name
                                li.innerHTML = `<a href="../${doc.path}" download="${doc.name}">${doc.name}</a>`;
                            } else {
                                // Handle other formats
                                li.textContent = JSON.stringify(doc);
                            }
                            docsList.appendChild(li);
                        });
                    } else {
                        docsList.innerHTML = '<li>No documents attached</li>';
                    }
                } else if (typeof documents === 'object' && documents !== null) {
                    // Handle object format (new applications with recommended_by)
                    const fileDocs = [];
                    const additionalInfo = [];
                    
                    // Extract file documents and additional info
                    for (const key in documents) {
                        if (key === 'recommended_by') {
                            additionalInfo.push({label: key, value: documents[key]});
                        } else if (typeof documents[key] === 'object' && documents[key].path) {
                            fileDocs.push(documents[key]);
                        } else if (key !== 'path' && key !== 'name' && documents[key]) {
                            additionalInfo.push({label: key, value: documents[key]});
                        }
                    }
                    
                    // Display file documents
                    if (fileDocs.length > 0) {
                        fileDocs.forEach(doc => {
                            const li = document.createElement('li');
                            li.innerHTML = `<a href="../${doc.path}" download="${doc.name}">${doc.name}</a>`;
                            docsList.appendChild(li);
                        });
                    }
                    
                    // Display additional information
                    if (additionalInfo.length > 0) {
                        additionalInfo.forEach(info => {
                            if (info.value) {
                                const li = document.createElement('li');
                                li.innerHTML = `<strong>${info.label.replace('_', ' ')}:</strong> ${info.value}`;
                                docsList.appendChild(li);
                            }
                        });
                    }
                    
                    // If no documents or info, show default message
                    if (fileDocs.length === 0 && additionalInfo.length === 0) {
                        docsList.innerHTML = '<li>No documents attached</li>';
                    }
                } else {
                    // Handle case where documents is a simple string
                    if (app.documents) {
                        const li = document.createElement('li');
                        li.textContent = app.documents;
                        docsList.appendChild(li);
                    } else {
                        docsList.innerHTML = '<li>No documents attached</li>';
                    }
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