<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in and has enrollment officer role
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit;
}

requireRole('Enrollment Officer', $pdo);

// Get enrollment officer profile
$stmt = $pdo->prepare("SELECT sp.full_name, sp.staff_id FROM staff_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$enrollmentOfficer = $stmt->fetch();

// Handle AJAX requests for student data
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'search_students':
            $search = $_GET['search'] ?? '';
            $intake_filter = $_GET['intake_id'] ?? '';
            $programme_filter = $_GET['programme_id'] ?? '';
            
            $sql = "
                SELECT a.*, p.name as programme_name, i.name as intake_name
                FROM applications a
                LEFT JOIN programme p ON a.programme_id = p.id
                LEFT JOIN intake i ON a.intake_id = i.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if (!empty($search)) {
                $sql .= " AND (a.full_name LIKE ? OR a.email LIKE ? OR a.id LIKE ?)";
                $search_param = "%$search%";
                $params = [$search_param, $search_param, $search_param];
            }
            
            if (!empty($intake_filter)) {
                $sql .= " AND a.intake_id = ?";
                $params[] = $intake_filter;
            }
            
            if (!empty($programme_filter)) {
                $sql .= " AND a.programme_id = ?";
                $params[] = $programme_filter;
            }
            
            $sql .= " ORDER BY a.created_at DESC LIMIT 50";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'students' => $students]);
            exit;
            
        case 'get_student_documents':
            $application_id = $_GET['application_id'] ?? 0;
            
            if (!$application_id) {
                echo json_encode(['success' => false, 'error' => 'Application ID required']);
                exit;
            }
            
            // Get application details
            $stmt = $pdo->prepare("
                SELECT a.*, p.name as programme_name, i.name as intake_name
                FROM applications a
                LEFT JOIN programme p ON a.programme_id = p.id
                LEFT JOIN intake i ON a.intake_id = i.id
                WHERE a.id = ?
            ");
            $stmt->execute([$application_id]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$application) {
                echo json_encode(['success' => false, 'error' => 'Student not found']);
                exit;
            }
            
            // Parse documents from JSON
            $documents_data = !empty($application['documents']) ? json_decode($application['documents'], true) : [];
            
            // Organize documents by type
            $organized_docs = [
                'nrc' => null,
                'academic_results' => null,
                'previous_school' => null
            ];
            
            if (is_array($documents_data)) {
                foreach ($documents_data as $doc) {
                    if (is_array($doc) && isset($doc['name'], $doc['path'])) {
                        if (stripos($doc['name'], 'nrc') !== false || stripos($doc['name'], 'national') !== false) {
                            $organized_docs['nrc'] = $doc;
                        } elseif (stripos($doc['name'], 'grade12') !== false || stripos($doc['name'], 'results') !== false || stripos($doc['name'], 'academic') !== false) {
                            $organized_docs['academic_results'] = $doc;
                        } elseif (stripos($doc['name'], 'previous') !== false || stripos($doc['name'], 'school') !== false) {
                            $organized_docs['previous_school'] = $doc;
                        }
                    }
                }
            }
            
            // Check for acceptance letter
            $acceptance_letter_path = __DIR__ . '/../letters_reports/letters/acceptance_letter_' . $application_id . '.pdf';
            $acceptance_letter_exists = file_exists($acceptance_letter_path);
            
            echo json_encode([
                'success' => true,
                'application' => $application,
                'documents' => [
                    'nrc' => $organized_docs['nrc'],
                    'academic_results' => $organized_docs['academic_results'],
                    'previous_school' => $organized_docs['previous_school'],
                    'acceptance_letter' => $acceptance_letter_exists ? [
                        'name' => 'Acceptance Letter',
                        'path' => 'letters_reports/letters/acceptance_letter_' . $application_id . '.pdf'
                    ] : null
                ]
            ]);
            exit;
    }
}

// Get intakes and programmes for filters
$stmt = $pdo->prepare("SELECT id, name FROM intake ORDER BY name");
$stmt->execute();
$intakes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, name FROM programme ORDER BY name");
$stmt->execute();
$programmes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Access - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .document-card {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        
        .document-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .document-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-available {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-not-available {
            background-color: #e9ecef;
            color: #6c757d;
        }
        
        .document-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .document-actions button {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .btn-view {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-view:hover {
            background-color: #138496;
        }
        
        .btn-print {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-print:hover {
            background-color: #5a6268;
        }
        
        .btn-download {
            background-color: #28a745;
            color: white;
        }
        
        .btn-download:hover {
            background-color: #218838;
        }
        
        .document-info {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .modal-preview {
            width: 100%;
            height: 600px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 600px;
            display: block;
            margin: 0 auto;
        }
        
        .student-info-panel {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .student-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item label {
            font-weight: 600;
            color: #495057;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
            display: block;
        }
        
        .info-item span {
            color: #212529;
            font-size: 14px;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
        }
        
        .tab {
            padding: 10px 20px;
            background: #e9ecef;
            border: none;
            border-radius: 4px 4px 0 0;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            background: #007bff;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
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
            <a href="./dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Applications</h4>
                <a href="./undergraduate_applications.php" class="nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Undergraduate</span>
                </a>
                <a href="./short_courses_applications.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <span>Short Courses</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Documents</h4>
                <a href="./document_access.php" class="nav-item active">
                    <i class="fas fa-file-alt"></i>
                    <span>Document Access</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>My Approvals</h4>
                <a href="./my_approvals.php" class="nav-item">
                    <i class="fas fa-thumbs-up"></i>
                    <span>My Approvals</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Registered Students</h4>
                <a href="./registered_students.php" class="nav-item">
                    <i class="fas fa-user-graduate"></i>
                    <span>Registered Students</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports</h4>
                <a href="./reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Enrollment Reports</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-file-alt"></i> Document Access</h1>
            <p>View, download, and print student application documents</p>
        </div>

        <!-- Search Section -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-search"></i> Search Students</h3>
            </div>
            <div class="panel-content">
                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 15px; align-items: end;">
                    <div class="form-group">
                        <label for="search-input">Search by Name, Email, or Application ID</label>
                        <input type="text" id="search-input" class="form-control" placeholder="Enter search term..." style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>
                    
                    <div class="form-group">
                        <label for="intake-filter">Filter by Intake</label>
                        <select id="intake-filter" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px;">
                            <option value="">All Intakes</option>
                            <?php foreach ($intakes as $intake): ?>
                                <option value="<?php echo $intake['id']; ?>"><?php echo htmlspecialchars($intake['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="programme-filter">Filter by Programme</label>
                        <select id="programme-filter" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px;">
                            <option value="">All Programmes</option>
                            <?php foreach ($programmes as $programme): ?>
                                <option value="<?php echo $programme['id']; ?>"><?php echo htmlspecialchars($programme['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button onclick="searchStudents()" class="btn btn-primary" style="padding: 10px 20px;">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>
        </div>

        <!-- Results Table -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-users"></i> Students</h3>
            </div>
            <div class="panel-content">
                <div id="students-table-container">
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <p>Enter search criteria to find students</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Panel (Hidden by default) -->
        <div class="data-panel" id="document-panel" style="display: none;">
            <div class="panel-header">
                <h3><i class="fas fa-folder-open"></i> Student Documents</h3>
                <button onclick="closeDocumentPanel()" class="btn btn-secondary" style="padding: 8px 16px;">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <div class="panel-content">
                <div id="student-info-container"></div>
                
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('nrc')">
                        <i class="fas fa-id-card"></i> NRC / National ID
                    </button>
                    <button class="tab" onclick="switchTab('academic-results')">
                        <i class="fas fa-graduation-cap"></i> Academic Results
                    </button>
                    <button class="tab" onclick="switchTab('acceptance-letter')">
                        <i class="fas fa-file-contract"></i> Acceptance Letter
                    </button>
                </div>
                
                <div id="nrc-tab" class="tab-content active">
                    <div id="nrc-content"></div>
                </div>
                
                <div id="academic-results-tab" class="tab-content">
                    <div id="academic-results-content"></div>
                </div>
                
                <div id="acceptance-letter-tab" class="tab-content">
                    <div id="acceptance-letter-content"></div>
                </div>
            </div>
        </div>
    </main>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal">
        <div class="modal-content" style="max-width: 90%;">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Document Preview</h3>
                <span class="close" onclick="closeModal('previewModal')">&times;</span>
            </div>
            <div class="modal-body" id="preview-modal-body">
                <!-- Preview content will be inserted here -->
            </div>
            <div class="modal-footer">
                <button onclick="printPreview()" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closeModal('previewModal')" class="btn btn-primary">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        let currentStudentId = null;
        let currentDocuments = {};
        
        function searchStudents() {
            const search = document.getElementById('search-input').value;
            const intake = document.getElementById('intake-filter').value;
            const programme = document.getElementById('programme-filter').value;
            
            fetch(`?action=search_students&search=${encodeURIComponent(search)}&intake_id=${intake}&programme_id=${programme}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.students.length > 0) {
                        renderStudentsTable(data.students);
                    } else {
                        document.getElementById('students-table-container').innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No students found</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while searching for students');
                });
        }
        
        function renderStudentsTable(students) {
            let html = `
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Programme</th>
                                <th>Intake</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            students.forEach(student => {
                html += `
                    <tr>
                        <td>${student.id}</td>
                        <td>${escapeHtml(student.full_name)}</td>
                        <td>${escapeHtml(student.email)}</td>
                        <td>${escapeHtml(student.programme_name || 'N/A')}</td>
                        <td>${escapeHtml(student.intake_name || 'N/A')}</td>
                        <td>
                            <span class="status-badge status-${student.status === 'approved' ? 'available' : 'not-available'}">
                                ${escapeHtml(student.status)}
                            </span>
                        </td>
                        <td>
                            <button onclick="accessDocuments(${student.id})" class="btn btn-sm btn-info">
                                <i class="fas fa-folder-open"></i> Access Documents
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            document.getElementById('students-table-container').innerHTML = html;
        }
        
        function accessDocuments(applicationId) {
            currentStudentId = applicationId;
            
            fetch(`?action=get_student_documents&application_id=${applicationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentDocuments = data.documents;
                        renderStudentInfo(data.application);
                        renderDocumentTabs();
                        document.getElementById('document-panel').style.display = 'block';
                        document.getElementById('document-panel').scrollIntoView({ behavior: 'smooth' });
                    } else {
                        alert('Error loading student documents: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while loading documents');
                });
        }
        
        function renderStudentInfo(application) {
            const html = `
                <div class="student-info-panel">
                    <h3 style="margin-bottom: 15px; color: #333;">
                        <i class="fas fa-user"></i> ${escapeHtml(application.full_name)}
                    </h3>
                    <div class="student-info-grid">
                        <div class="info-item">
                            <label>Application ID</label>
                            <span>#${application.id}</span>
                        </div>
                        <div class="info-item">
                            <label>Email</label>
                            <span>${escapeHtml(application.email)}</span>
                        </div>
                        <div class="info-item">
                            <label>Phone</label>
                            <span>${escapeHtml(application.phone || 'N/A')}</span>
                        </div>
                        <div class="info-item">
                            <label>Programme</label>
                            <span>${escapeHtml(application.programme_name || 'N/A')}</span>
                        </div>
                        <div class="info-item">
                            <label>Intake</label>
                            <span>${escapeHtml(application.intake_name || 'N/A')}</span>
                        </div>
                        <div class="info-item">
                            <label>Status</label>
                            <span class="status-badge status-${application.status === 'approved' ? 'available' : 'not-available'}">
                                ${escapeHtml(application.status)}
                            </span>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('student-info-container').innerHTML = html;
        }
        
        function renderDocumentTabs() {
            // Render NRC tab
            renderDocumentCard('nrc', 'NRC / National ID', 'nrc-content');
            
            // Render Academic Results tab
            renderDocumentCard('academic_results', 'Academic Results', 'academic-results-content');
            
            // Render Acceptance Letter tab
            renderDocumentCard('acceptance_letter', 'Acceptance Letter', 'acceptance-letter-content');
        }
        
        function renderDocumentCard(docType, docTitle, containerId) {
            const doc = currentDocuments[docType];
            const hasDoc = doc !== null;
            
            const html = `
                <div class="document-card">
                    <div class="document-header">
                        <div class="document-title">
                            <i class="fas fa-${getIconForDocType(docType)}"></i>
                            ${docTitle}
                        </div>
                        <span class="status-badge status-${hasDoc ? 'available' : 'not-available'}">
                            ${hasDoc ? 'Available' : 'Not Uploaded'}
                        </span>
                    </div>
                    
                    ${hasDoc ? `
                        <div class="document-info">
                            <strong>File:</strong> ${escapeHtml(doc.name)}<br>
                            <strong>Uploaded:</strong> ${new Date().toLocaleDateString()}
                        </div>
                    ` : ''}
                    
                    <div class="document-actions">
                        ${hasDoc ? `
                            <button onclick="viewDocument('${docType}')" class="btn-view">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button onclick="printDocument('${docType}')" class="btn-print">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button onclick="downloadDocument('${docType}')" class="btn-download">
                                <i class="fas fa-download"></i> Download
                            </button>
                        ` : `
                            <button disabled class="btn-view" style="opacity: 0.5; cursor: not-allowed;">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button disabled class="btn-print" style="opacity: 0.5; cursor: not-allowed;">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button disabled class="btn-download" style="opacity: 0.5; cursor: not-allowed;">
                                <i class="fas fa-download"></i> Download
                            </button>
                        `}
                    </div>
                </div>
            `;
            
            document.getElementById(containerId).innerHTML = html;
        }
        
        function getIconForDocType(docType) {
            const icons = {
                'nrc': 'id-card',
                'academic_results': 'graduation-cap',
                'previous_school': 'school',
                'acceptance_letter': 'file-contract'
            };
            return icons[docType] || 'file';
        }
        
        function switchTab(tabName) {
            // Remove active class from all tabs and contents
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Add active class to selected tab and content
            event.target.closest('.tab').classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        function viewDocument(docType) {
            const doc = currentDocuments[docType];
            if (!doc) return;
            
            const extension = doc.name.split('.').pop().toLowerCase();
            const previewUrl = `../enrollment/download_document.php?file_type=${docType}&application_id=${currentStudentId}&view=1`;
            
            let previewContent = '';
            
            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
                previewContent = `<img src="${previewUrl}" class="preview-image" alt="${escapeHtml(doc.name)}">`;
            } else if (extension === 'pdf') {
                previewContent = `<iframe src="${previewUrl}" class="modal-preview" frameborder="0"></iframe>`;
            } else {
                previewContent = `<p>Preview not available for this file type. Please download to view.</p>`;
            }
            
            document.getElementById('preview-modal-body').innerHTML = previewContent;
            document.getElementById('previewModal').style.display = 'block';
        }
        
        function printDocument(docType) {
            const doc = currentDocuments[docType];
            if (!doc) return;
            
            const previewUrl = `../enrollment/download_document.php?file_type=${docType}&application_id=${currentStudentId}&view=1`;
            
            const printWindow = window.open(previewUrl, '_blank');
            printWindow.onload = function() {
                printWindow.print();
            };
        }
        
        function downloadDocument(docType) {
            const doc = currentDocuments[docType];
            if (!doc) return;
            
            window.location.href = `../enrollment/download_document.php?file_type=${docType}&application_id=${currentStudentId}&download=1`;
        }
        
        function printPreview() {
            const iframe = document.querySelector('#preview-modal-body iframe');
            if (iframe) {
                iframe.contentWindow.print();
            } else {
                window.print();
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function closeDocumentPanel() {
            document.getElementById('document-panel').style.display = 'none';
            currentStudentId = null;
            currentDocuments = {};
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
        
        // Allow Enter key to trigger search
        document.getElementById('search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchStudents();
            }
        });
    </script>
</body>
</html>
