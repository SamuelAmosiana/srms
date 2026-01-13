<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in and has Sub Admin role
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit;
}

requireRole('Sub Admin (Finance)', $pdo);

// Get sub admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Detect optional columns for counterparty info
try {
    $colsStmt = $pdo->query("SHOW COLUMNS FROM finance_transactions");
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
    $hasPartyColumns = in_array('party_type', $cols) && in_array('party_name', $cols);
} catch (Exception $e) {
    $hasPartyColumns = false;
}

// One-click migration to add party columns when missing
if (isset($_GET['action']) && $_GET['action'] === 'enable_party' && !$hasPartyColumns) {
    try {
        // Re-check columns to avoid race conditions
        $colsStmt = $pdo->query("SHOW COLUMNS FROM finance_transactions");
        $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('party_type', $cols)) {
            $pdo->exec("ALTER TABLE finance_transactions ADD COLUMN party_type VARCHAR(50) NULL AFTER description");
        }
        if (!in_array('party_name', $cols)) {
            $pdo->exec("ALTER TABLE finance_transactions ADD COLUMN party_name VARCHAR(255) NULL AFTER party_type");
        }
        // Redirect to refresh state
        header('Location: income_expenses.php?migrated=1');
        exit;
    } catch (Exception $e) {
        $message = "Migration error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_income'])) {
            $description = $_POST['description'];
            $amount = $_POST['amount'];
            $date = $_POST['date'];
            $party_type = $_POST['party_type'] ?? null;
            $party_name = $_POST['party_name'] ?? null;

            if ($hasPartyColumns) {
                $stmt = $pdo->prepare("INSERT INTO finance_transactions (type, amount, description, created_at, party_type, party_name) VALUES ('income', ?, ?, ?, ?, ?)");
                $stmt->execute([$amount, $description, $date, $party_type, $party_name]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO finance_transactions (type, amount, description, created_at) VALUES ('income', ?, ?, ?)");
                $stmt->execute([$amount, $description, $date]);
            }

            $message = "Income recorded successfully!";
            $messageType = "success";
        } elseif (isset($_POST['add_expense'])) {
            $description = $_POST['description'];
            $amount = $_POST['amount'];
            $date = $_POST['date'];
            $party_type = $_POST['party_type'] ?? null;
            $party_name = $_POST['party_name'] ?? null;

            if ($hasPartyColumns) {
                $stmt = $pdo->prepare("INSERT INTO finance_transactions (type, amount, description, created_at, party_type, party_name) VALUES ('expense', ?, ?, ?, ?, ?)");
                $stmt->execute([$amount, $description, $date, $party_type, $party_name]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO finance_transactions (type, amount, description, created_at) VALUES ('expense', ?, ?, ?)");
                $stmt->execute([$amount, $description, $date]);
            }

            $message = "Expense recorded successfully!";
            $messageType = "success";
        } elseif (isset($_POST['upload_csv'])) {
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('CSV upload failed');
            }
            $tmp = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($tmp, 'r');
            if (!$handle) {
                throw new Exception('Unable to read uploaded CSV');
            }
            // Expected header: type,description,amount,date,party_type,party_name
            $header = fgetcsv($handle);
            $count = 0;
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 4) { continue; }
                [$type, $desc, $amt, $dt, $p_type, $p_name] = array_pad($row, 6, null);
                $type = strtolower(trim($type));
                if ($type !== 'income' && $type !== 'expense') { continue; }
                if ($hasPartyColumns) {
                    $stmt = $pdo->prepare("INSERT INTO finance_transactions (type, amount, description, created_at, party_type, party_name) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$type, $amt, $desc, $dt, $p_type, $p_name]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO finance_transactions (type, amount, description, created_at) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$type, $amt, $desc, $dt]);
                }
                $count++;
            }
            fclose($handle);
            $message = "Imported $count transactions from CSV.";
            $messageType = "success";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Handle downloads (export CSV and sample CSV)
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transactions_export.csv"');
    $out = fopen('php://output', 'w');
    $csvHeader = $hasPartyColumns
        ? ['type','description','amount','date','party_type','party_name']
        : ['type','description','amount','date'];
    fputcsv($out, $csvHeader);
    $q = $hasPartyColumns
        ? $pdo->query("SELECT type, description, amount, created_at AS date, party_type, party_name FROM finance_transactions ORDER BY created_at DESC")
        : $pdo->query("SELECT type, description, amount, created_at AS date FROM finance_transactions ORDER BY created_at DESC");
    while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'sample_csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transactions_sample.csv"');
    $out = fopen('php://output', 'w');
    $header = ['type','description','amount','date','party_type','party_name'];
    fputcsv($out, $header);
    fputcsv($out, ['income','Student Fee Payment','1500.00','2025-01-15','Student','John Doe']);
    fputcsv($out, ['expense','Office Supplies','200.00','2025-01-16','Vendor','ABC Stationers']);
    fclose($out);
    exit;
}

// Fetch income and expense data
$income_stmt = $pdo->query("SELECT * FROM finance_transactions WHERE type = 'income' ORDER BY created_at DESC");
$incomes = $income_stmt->fetchAll();

$expense_stmt = $pdo->query("SELECT * FROM finance_transactions WHERE type = 'expense' ORDER BY created_at DESC");
$expenses = $expense_stmt->fetchAll();

// Fetch all transactions for table view
$transactions_stmt = $hasPartyColumns
    ? $pdo->query("SELECT type, description, amount, created_at, party_type, party_name FROM finance_transactions ORDER BY created_at DESC")
    : $pdo->query("SELECT type, description, amount, created_at FROM finance_transactions ORDER BY created_at DESC");
$transactions = $transactions_stmt->fetchAll();

// Calculate totals
$total_income = array_sum(array_column($incomes, 'amount'));
$total_expenses = array_sum(array_column($expenses, 'amount'));
$net_balance = $total_income - $total_expenses;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income & Expenses - LSC SRMS</title>
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($admin['full_name'] ?? 'Finance Administrator'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($admin['staff_id'] ?? 'N/A'); ?>)</span>
            </div>
            
            <div class="nav-actions">
                <a href="manage_programme_fees.php" class="nav-link" title="Programme Fees">
                    <i class="fas fa-file-invoice-dollar"></i>
                </a>
                
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
                <a href="income_expenses.php" class="nav-item active">
                    <i class="fas fa-chart-pie"></i>
                    <span>Income & Expenses</span>
                </a>
                <a href="finance_reports.php" class="nav-item">
                    <i class="fas fa-file-invoice"></i>
                    <span>Finance Reports</span>
                </a>
                <a href="registration_clearance.php" class="nav-item">
                    <i class="fas fa-user-check"></i>
                    <span>Registration Clearance</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-chart-pie"></i> Manage Income & Expenses</h1>
            <p>Record and track financial transactions for income and expenses</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>" id="notification-message">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?>
            </div>
            <script>
                // Auto-hide success messages after 5 seconds
                setTimeout(function() {
                    var msgElement = document.getElementById('notification-message');
                    if (msgElement && '<?php echo $messageType; ?>' === 'success') {
                        msgElement.style.opacity = '0';
                        setTimeout(function() {
                            msgElement.style.display = 'none';
                        }, 300);
                    }
                }, 5000);
            </script>
        <?php endif; ?>
        <?php if (!$hasPartyColumns): ?>
            <div class="alert warning">
                <i class="fas fa-info-circle"></i>
                Party columns are not enabled in the database. Click
                <a href="?action=enable_party" class="link">Enable Party Columns</a>
                to store Source/Destination and include them in CSV exports.
            </div>
        <?php endif; ?>
        
        <!-- Income Form -->
        <div class="form-container">
            <h2>Record Income</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="income_description">Description</label>
                    <input type="text" name="description" id="income_description" required placeholder="e.g., Student Fee Payment">
                </div>
                <div class="form-group">
                    <label for="income_amount">Amount (K)</label>
                    <input type="number" name="amount" id="income_amount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="income_date">Date</label>
                    <input type="date" name="date" id="income_date" required>
                </div>
                <div class="form-group">
                    <label for="income_party_type">Source Type</label>
                    <select name="party_type" id="income_party_type" required>
                        <option value="Student">Student</option>
                        <option value="Company">Company</option>
                        <option value="Vendor">Vendor</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="income_party_name">Source Name</label>
                    <input type="text" name="party_name" id="income_party_name" required placeholder="e.g., John Doe">
                </div>
                <div class="form-actions">
                    <button type="submit" name="add_income" class="btn green">Record Income</button>
                </div>
            </form>
        </div>
        
        <!-- Expense Form -->
        <div class="form-container">
            <h2>Record Expense</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="expense_description">Description</label>
                    <input type="text" name="description" id="expense_description" required placeholder="e.g., Office Supplies">
                </div>
                <div class="form-group">
                    <label for="expense_amount">Amount (K)</label>
                    <input type="number" name="amount" id="expense_amount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="expense_date">Date</label>
                    <input type="date" name="date" id="expense_date" required>
                </div>
                <div class="form-group">
                    <label for="expense_party_type">Destination Type</label>
                    <select name="party_type" id="expense_party_type" required>
                        <option value="Vendor">Vendor</option>
                        <option value="Company">Company</option>
                        <option value="Student">Student</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="expense_party_name">Destination Name</label>
                    <input type="text" name="party_name" id="expense_party_name" required placeholder="e.g., ABC Stationers">
                </div>
                <div class="form-actions">
                    <button type="submit" name="add_expense" class="btn orange">Record Expense</button>
                </div>
            </form>
        </div>

        <!-- CSV Import/Export -->
        <div class="form-container">
            <h2>Import/Export Transactions</h2>
            <div class="form-actions" style="gap:10px;display:flex;flex-wrap:wrap;align-items:center;">
                <a href="?action=export_csv" class="btn">Export CSV</a>
                <a href="?action=sample_csv" class="btn">Download Sample CSV</a>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" style="margin-top:12px;">
                <div class="form-group">
                    <label for="csv_file">Upload CSV</label>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                </div>
                <div class="form-actions">
                    <button type="submit" name="upload_csv" class="btn">Upload CSV</button>
                </div>
            </form>
        </div>
        
        <!-- Recent Transactions Table -->
        <div class="table-container">
            <h2>Recent Transactions</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Amount (K)</th>
                        <th>Date</th>
                        <th>Party Type</th>
                        <th>Source/Destination</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td>
                                <span class="status <?php echo strtolower($transaction['type']) === 'income' ? 'green' : 'orange'; ?>">
                                    <?php echo htmlspecialchars($transaction['type']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                            <td><?php echo number_format($transaction['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($transaction['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($transaction['party_type'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($transaction['party_name'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>
