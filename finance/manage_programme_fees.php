<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has Sub Admin role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Sub Admin (Finance)', $pdo);

// Get sub admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_fee'])) {
        // Add new programme fee
        $programme_id = $_POST['programme_id'];
        $fee_name = !empty($_POST['fee_name_custom']) ? $_POST['fee_name_custom'] : $_POST['fee_name'];
        $fee_amount = $_POST['fee_amount'];
        $fee_type = $_POST['fee_type'];
        $description = $_POST['description'];
        
        // Validate that we have a fee name
        if (empty($fee_name)) {
            $error_message = "Fee name is required!";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO programme_fees (programme_id, fee_name, fee_amount, fee_type, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$programme_id, $fee_name, $fee_amount, $fee_type, $description]);
                $success_message = "Fee added successfully!";
            } catch (Exception $e) {
                $error_message = "Error adding fee: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_fee'])) {
        // Update existing programme fee
        $fee_id = $_POST['fee_id'];
        $fee_name = $_POST['fee_name'];
        $fee_amount = $_POST['fee_amount'];
        $fee_type = $_POST['fee_type'];
        $description = $_POST['description'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            $stmt = $pdo->prepare("UPDATE programme_fees SET fee_name = ?, fee_amount = ?, fee_type = ?, description = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$fee_name, $fee_amount, $fee_type, $description, $is_active, $fee_id]);
            $success_message = "Fee updated successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating fee: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_fee'])) {
        // Delete programme fee
        $fee_id = $_POST['fee_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM programme_fees WHERE id = ?");
            $stmt->execute([$fee_id]);
            $success_message = "Fee deleted successfully!";
        } catch (Exception $e) {
            $error_message = "Error deleting fee: " . $e->getMessage();
        }
    } elseif (isset($_POST['add_fee_type'])) {
        // Add new fee type
        $fee_type_name = $_POST['fee_type_name'];
        $fee_type_description = $_POST['fee_type_description'];
        
        // Validate that we have a fee type name
        if (empty($fee_type_name)) {
            $error_message = "Fee type name is required!";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO fee_types (name, description) VALUES (?, ?)");
                $stmt->execute([$fee_type_name, $fee_type_description]);
                $success_message = "Fee type added successfully!";
            } catch (Exception $e) {
                $error_message = "Error adding fee type: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_fee_type'])) {
        // Update fee type
        $fee_type_id = $_POST['fee_type_id'];
        $fee_type_name = $_POST['fee_type_name'];
        $fee_type_description = $_POST['fee_type_description'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            $stmt = $pdo->prepare("UPDATE fee_types SET name = ?, description = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$fee_type_name, $fee_type_description, $is_active, $fee_type_id]);
            $success_message = "Fee type updated successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating fee type: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_fee_type'])) {
        // Delete fee type
        $fee_type_id = $_POST['fee_type_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM fee_types WHERE id = ?");
            $stmt->execute([$fee_type_id]);
            $success_message = "Fee type deleted successfully!";
        } catch (Exception $e) {
            $error_message = "Error deleting fee type: " . $e->getMessage();
        }
    }
}

// Fetch all programmes
$stmt = $pdo->query("SELECT id, name FROM programme ORDER BY name");
$programmes = $stmt->fetchAll();

// Fetch all fee types
$stmt = $pdo->query("SELECT * FROM fee_types ORDER BY name");
$fee_types = $stmt->fetchAll();

// Fetch all programme fees with programme names
$stmt = $pdo->query("
    SELECT pf.*, p.name as programme_name 
    FROM programme_fees pf 
    JOIN programme p ON pf.programme_id = p.id 
    ORDER BY p.name, pf.fee_name
");
$programme_fees = $stmt->fetchAll();

// Group fees by programme for easier display
$grouped_fees = [];
foreach ($programme_fees as $fee) {
    if (!isset($grouped_fees[$fee['programme_id']])) {
        $grouped_fees[$fee['programme_id']] = [
            'programme_name' => $fee['programme_name'],
            'fees' => []
        ];
    }
    $grouped_fees[$fee['programme_id']]['fees'][] = $fee;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Programme Fees - LSC SRMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .fee-form {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .fee-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .fee-table th, .fee-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .fee-table th {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
        }
        
        .fee-table tr:last-child td {
            border-bottom: none;
        }
        
        .fee-type-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .badge-one-time {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-per-term {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .badge-per-year {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .fee-actions {
            display: flex;
            gap: 5px;
        }
        
        .fee-actions button {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
        }
        
        .edit-btn {
            background: #ffc107;
            color: #212529;
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
        }
        
        .active-status {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
        }
        
        .inactive-status {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #dc3545;
        }
        
        .programme-section {
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .programme-header {
            background: var(--secondary-bg);
            padding: 15px 20px;
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($admin['full_name'] ?? 'Finance Administrator'); ?></span>
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
            <h3><i class="fas fa-tachometer-alt"></i> Finance Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Student Management</h4>
                <a href="view_students.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>View Students</span>
                </a>
                <a href="manage_fees.php" class="nav-item">
                    <i class="fas fa-money-bill"></i>
                    <span>Manage Fees & Finances</span>
                </a>
                <a href="manage_programme_fees.php" class="nav-item active">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Programme Fees</span>
                </a>
                <a href="results_access.php" class="nav-item">
                    <i class="fas fa-lock"></i>
                    <span>Manage Results Access</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Financial Operations</h4>
                <a href="manage_programme_fees.php" class="nav-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Programme Fees</span>
                </a>
                <a href="income_expenses.php" class="nav-item">
                    <i class="fas fa-chart-pie"></i>
                    <span>Income & Expenses</span>
                </a>
                <a href="finance_reports.php" class="nav-item">
                    <i class="fas fa-file-invoice"></i>
                    <span>Finance Reports</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-file-invoice-dollar"></i> Manage Programme Fees</h1>
            <p>Define and manage fees for each programme</p>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Add New Fee Form -->
        <div class="fee-form">
            <h2>Add New Programme Fee</h2>
            <form method="POST" action="manage_programme_fees.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="programme_id">Programme</label>
                        <select name="programme_id" id="programme_id" required>
                            <option value="">Select Programme</option>
                            <?php foreach ($programmes as $programme): ?>
                                <option value="<?php echo $programme['id']; ?>"><?php echo htmlspecialchars($programme['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="fee_name">Fee Name</label>
                        <select name="fee_name" id="fee_name" required>
                            <option value="">Select or Type Fee Name</option>
                            <?php foreach ($fee_types as $fee_type): ?>
                                <option value="<?php echo htmlspecialchars($fee_type['name']); ?>"><?php echo htmlspecialchars($fee_type['name']); ?></option>
                            <?php endforeach; ?>
                            <option value="other">-- Other --</option>
                        </select>
                        <input type="text" name="fee_name_custom" id="fee_name_custom" placeholder="Enter custom fee name" style="margin-top: 5px; display: none;" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="fee_amount">Amount (K)</label>
                        <input type="number" name="fee_amount" id="fee_amount" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="fee_type">Fee Type</label>
                        <select name="fee_type" id="fee_type" required>
                            <option value="one_time">One Time</option>
                            <option value="per_term" selected>Per Term</option>
                            <option value="per_year">Per Year</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description (Optional)</label>
                    <textarea name="description" id="description" rows="2" placeholder="Brief description of the fee"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="add_fee" class="btn green">
                        <i class="fas fa-plus"></i> Add Fee
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Define Fee Types Section -->
        <div class="fee-form">
            <h2>Define Fee Types</h2>
            <form method="POST" action="manage_programme_fees.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="fee_type_name">Fee Type Name</label>
                        <input type="text" name="fee_type_name" id="fee_type_name" required placeholder="e.g., Application Fee, Tuition Fee">
                    </div>
                    
                    <div class="form-group">
                        <label for="fee_type_description">Description (Optional)</label>
                        <input type="text" name="fee_type_description" id="fee_type_description" placeholder="Brief description of the fee type">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="add_fee_type" class="btn blue">
                        <i class="fas fa-plus"></i> Add Fee Type
                    </button>
                </div>
            </form>
            
            <!-- Existing Fee Types List -->
            <?php if (!empty($fee_types)): ?>
                <h3 style="margin-top: 20px;">Existing Fee Types</h3>
                <table class="fee-table">
                    <thead>
                        <tr>
                            <th>Fee Type</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fee_types as $fee_type): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fee_type['name']); ?></td>
                                <td><?php echo htmlspecialchars($fee_type['description'] ?: '-'); ?></td>
                                <td>
                                    <?php if ($fee_type['is_active']): ?>
                                        <span class="active-status" title="Active"></span> Active
                                    <?php else: ?>
                                        <span class="inactive-status" title="Inactive"></span> Inactive
                                    <?php endif; ?>
                                </td>
                                <td class="fee-actions">
                                    <button class="edit-btn" onclick="editFeeType(<?php echo $fee_type['id']; ?>, '<?php echo htmlspecialchars($fee_type['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($fee_type['description'], ENT_QUOTES); ?>', <?php echo $fee_type['is_active']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" action="manage_programme_fees.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this fee type?')">
                                        <input type="hidden" name="fee_type_id" value="<?php echo $fee_type['id']; ?>">
                                        <button type="submit" name="delete_fee_type" class="delete-btn">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Programme Fees List -->
        <div class="table-container">
            <h2>Programme Fees</h2>
            
            <?php if (empty($grouped_fees)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-invoice-dollar fa-3x"></i>
                    <h3>No Programme Fees Found</h3>
                    <p>Add fees for programmes using the form above.</p>
                </div>
            <?php else: ?>
                <?php foreach ($grouped_fees as $programme_id => $group): ?>
                    <div class="programme-section">
                        <div class="programme-header">
                            <?php echo htmlspecialchars($group['programme_name']); ?>
                        </div>
                        
                        <table class="fee-table">
                            <thead>
                                <tr>
                                    <th>Fee Name</th>
                                    <th>Amount (K)</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($group['fees'] as $fee): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($fee['fee_name']); ?></td>
                                        <td><?php echo number_format($fee['fee_amount'], 2); ?></td>
                                        <td>
                                            <span class="fee-type-badge badge-<?php echo $fee['fee_type']; ?>">
                                                <?php 
                                                switch ($fee['fee_type']) {
                                                    case 'one_time': echo 'One Time'; break;
                                                    case 'per_term': echo 'Per Term'; break;
                                                    case 'per_year': echo 'Per Year'; break;
                                                    default: echo ucfirst(str_replace('_', ' ', $fee['fee_type']));
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($fee['is_active']): ?>
                                                <span class="active-status" title="Active"></span> Active
                                            <?php else: ?>
                                                <span class="inactive-status" title="Inactive"></span> Inactive
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($fee['description'] ?: '-'); ?></td>
                                        <td class="fee-actions">
                                            <button class="edit-btn" onclick="editFee(<?php echo $fee['id']; ?>, <?php echo $fee['programme_id']; ?>, '<?php echo htmlspecialchars($fee['fee_name'], ENT_QUOTES); ?>', <?php echo $fee['fee_amount']; ?>, '<?php echo $fee['fee_type']; ?>', '<?php echo htmlspecialchars($fee['description'], ENT_QUOTES); ?>', <?php echo $fee['is_active']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="manage_programme_fees.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this fee?')">
                                                <input type="hidden" name="fee_id" value="<?php echo $fee['id']; ?>">
                                                <button type="submit" name="delete_fee" class="delete-btn">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Edit Fee Modal -->
        <div id="editModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Programme Fee</h2>
                    <span class="close" onclick="closeEditModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <form method="POST" action="manage_programme_fees.php" id="editFeeForm">
                        <input type="hidden" name="fee_id" id="edit_fee_id">
                        
                        <div class="form-group">
                            <label for="edit_programme_id">Programme</label>
                            <select name="programme_id" id="edit_programme_id" required>
                                <option value="">Select Programme</option>
                                <?php foreach ($programmes as $programme): ?>
                                    <option value="<?php echo $programme['id']; ?>"><?php echo htmlspecialchars($programme['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_fee_name">Fee Name</label>
                            <input type="text" name="fee_name" id="edit_fee_name" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_fee_amount">Amount (K)</label>
                                <input type="number" name="fee_amount" id="edit_fee_amount" step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_fee_type">Fee Type</label>
                                <select name="fee_type" id="edit_fee_type" required>
                                    <option value="one_time">One Time</option>
                                    <option value="per_term">Per Term</option>
                                    <option value="per_year">Per Year</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_description">Description (Optional)</label>
                            <textarea name="description" id="edit_description" rows="2"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" id="edit_is_active" value="1"> Active
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_fee" class="btn green">Update Fee</button>
                            <button type="button" class="btn orange" onclick="closeEditModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Edit Fee Type Modal -->
        <div id="editFeeTypeModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Fee Type</h2>
                    <span class="close" onclick="closeEditFeeTypeModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <form method="POST" action="manage_programme_fees.php" id="editFeeTypeForm">
                        <input type="hidden" name="fee_type_id" id="edit_fee_type_id">
                        
                        <div class="form-group">
                            <label for="edit_fee_type_name">Fee Type Name</label>
                            <input type="text" name="fee_type_name" id="edit_fee_type_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_fee_type_description">Description (Optional)</label>
                            <input type="text" name="fee_type_description" id="edit_fee_type_description">
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" id="edit_fee_type_is_active" value="1"> Active
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_fee_type" class="btn green">Update Fee Type</button>
                            <button type="button" class="btn orange" onclick="closeEditFeeTypeModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Handle fee name selection
        document.getElementById('fee_name').addEventListener('change', function() {
            const customInput = document.getElementById('fee_name_custom');
            if (this.value === 'other') {
                customInput.style.display = 'block';
                customInput.required = true;
                // Clear any previous value
                customInput.value = '';
            } else {
                customInput.style.display = 'none';
                customInput.required = false;
            }
        });
        
        function editFee(id, programmeId, feeName, feeAmount, feeType, description, isActive) {
            document.getElementById('edit_fee_id').value = id;
            document.getElementById('edit_programme_id').value = programmeId;
            document.getElementById('edit_fee_name').value = feeName;
            document.getElementById('edit_fee_amount').value = feeAmount;
            document.getElementById('edit_fee_type').value = feeType;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_is_active').checked = isActive == 1;
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function editFeeType(id, name, description, isActive) {
            document.getElementById('edit_fee_type_id').value = id;
            document.getElementById('edit_fee_type_name').value = name;
            document.getElementById('edit_fee_type_description').value = description;
            document.getElementById('edit_fee_type_is_active').checked = isActive == 1;
            
            document.getElementById('editFeeTypeModal').style.display = 'block';
        }
        
        function closeEditFeeTypeModal() {
            document.getElementById('editFeeTypeModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const editFeeTypeModal = document.getElementById('editFeeTypeModal');
            
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
            
            if (event.target == editFeeTypeModal) {
                editFeeTypeModal.style.display = 'none';
            }
        }
    </script>
    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>