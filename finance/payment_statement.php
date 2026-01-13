<?php
require '../config.php';
require '../auth.php';

if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Sub Admin (Finance)', $pdo);

// Accept student_number or fallback aliases commonly used in links
$student_number = $_GET['student_number'] ?? ($_GET['student_id'] ?? '');

// If not provided but user_id is given, resolve to student_number
if ($student_number === '' && isset($_GET['user_id']) && ctype_digit((string)$_GET['user_id'])) {
    $tmpStmt = $pdo->prepare("SELECT student_number FROM student_profile WHERE user_id = ?");
    $tmpStmt->execute([$_GET['user_id']]);
    $student_number = (string)($tmpStmt->fetchColumn() ?: '');
}

if ($student_number === '') {
    http_response_code(400);
    echo 'Missing student_number';
    exit;
}

// Fetch student profile and related info
$student_stmt = $pdo->prepare("SELECT sp.user_id, sp.student_number, sp.full_name, sp.balance, u.email, p.name AS programme
                               FROM student_profile sp
                               LEFT JOIN users u ON sp.user_id = u.id
                               LEFT JOIN programme p ON sp.programme_id = p.id
                               WHERE sp.student_number = ?");
$student_stmt->execute([$student_number]);
$student = $student_stmt->fetch();

if (!$student) {
    http_response_code(404);
    echo 'Student not found';
    exit;
}

// Determine if party columns exist for fallback
$partyCols = $pdo->query("SHOW COLUMNS FROM finance_transactions LIKE 'party_type'")->rowCount() > 0
    && $pdo->query("SHOW COLUMNS FROM finance_transactions LIKE 'party_name'")->rowCount() > 0;

// Build transactions query: prefer direct link via student_user_id; fallback to party fields matching student name
$transactions = [];
if ($student['user_id']) {
    $stmt = $pdo->prepare("SELECT created_at, description, amount, type
                            FROM finance_transactions
                            WHERE student_user_id = ? AND type = 'income'
                            ORDER BY created_at DESC");
    $stmt->execute([$student['user_id']]);
    $transactions = $stmt->fetchAll();
}

if (empty($transactions) && $partyCols) {
    $stmt = $pdo->prepare("SELECT created_at, description, amount, type
                            FROM finance_transactions
                            WHERE type = 'income' AND party_type = 'Student' AND party_name = ?
                            ORDER BY created_at DESC");
    $stmt->execute([$student['full_name']]);
    $transactions = $stmt->fetchAll();
}

// Totals derived from transactions and profile balance
$total_due = null; $total_paid = null; $computed_balance = null; // not using student_fees here

// If CSV export requested
if (isset($_GET['action']) && $_GET['action'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payment_statement_' . preg_replace('/[^A-Za-z0-9_-]/','_',$student['student_number']) . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student Number','Full Name','Programme','Email']);
    fputcsv($out, [$student['student_number'], $student['full_name'], $student['programme'] ?? '', $student['email'] ?? '']);
    fputcsv($out, []);
    fputcsv($out, ['Date','Description','Amount (K)']);
    foreach ($transactions as $t) {
        fputcsv($out, [date('Y-m-d', strtotime($t['created_at'])), $t['description'], number_format((float)$t['amount'], 2, '.', '')]);
    }
    fputcsv($out, []);
    $current_balance = isset($student['balance']) ? (float)$student['balance'] : ($computed_balance ?? null);
    fputcsv($out, ['Totals']);
    if ($total_due !== null) {
        fputcsv($out, ['Total Due (K)', number_format($total_due, 2, '.', '')]);
        fputcsv($out, ['Total Paid (K)', number_format($total_paid, 2, '.', '')]);
    }
    if ($current_balance !== null) {
        fputcsv($out, ['Current Balance (K)', number_format($current_balance, 2, '.', '')]);
    }
    fclose($out);
    exit;
}

// Calculate sum paid from transactions for display
$sum_paid = 0.0;
foreach ($transactions as $t) { $sum_paid += (float)$t['amount']; }
$current_balance = isset($student['balance']) ? (float)$student['balance'] : ($computed_balance ?? null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Statement - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .statement-container { max-width: 1000px; margin: 0 auto; background: var(--card-bg); padding: 20px; border-radius: 8px; }
        .statement-header { text-align: center; margin-bottom: 20px; }
        .statement-header h1 { margin: 6px 0 0; font-size: 1.4rem; }
        .school-logo { max-width: 140px; display: block; margin: 0 auto; }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap: 10px; margin: 15px 0; }
        .meta-item { background: var(--secondary-bg); padding: 10px; border-radius: 6px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 10px 12px; border-bottom: 1px solid var(--border-color); text-align: left; }
        .table th { background: var(--primary-color); color: #fff; }
        .actions { display: flex; gap: 10px; margin: 15px 0 25px; }
        .actions .btn { text-decoration: none; }
        .print-header { display: none; text-align: center; margin-bottom: 10px; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .print-header { display: block !important; }
        }
    </style>
</head>
<body class="admin-layout" data-theme="light">
    <div class="statement-container">
        <div class="print-header">
            <img src="../assets/images/school_logo.jpg" alt="School Logo" class="school-logo">
        </div>
        <div class="statement-header">
            <img src="../assets/images/school_logo.jpg" alt="School Logo" class="school-logo no-print">
            <h1>Payment Statement</h1>
            <div>LSC SRMS Finance Module</div>
        </div>

        <div class="actions no-print">
            <a class="btn green" href="javascript:window.print()"><i class="fas fa-print"></i> Print / Save as PDF</a>
            <a class="btn blue" href="payment_statement.php?student_number=<?php echo urlencode($student['student_number']); ?>&action=csv">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
            <a class="btn" href="view_students.php"><i class="fas fa-arrow-left"></i> Back to Students</a>
        </div>

        <div class="meta-grid">
            <div class="meta-item"><strong>Student Name:</strong><br><?php echo htmlspecialchars($student['full_name']); ?></div>
            <div class="meta-item"><strong>Student Number:</strong><br><?php echo htmlspecialchars($student['student_number']); ?></div>
            <div class="meta-item"><strong>Programme:</strong><br><?php echo htmlspecialchars($student['programme'] ?? 'N/A'); ?></div>
            <div class="meta-item"><strong>Email:</strong><br><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></div>
            <?php if ($total_due !== null): ?>
                <div class="meta-item"><strong>Total Due (K):</strong><br><?php echo number_format($total_due, 2); ?></div>
                <div class="meta-item"><strong>Total Paid (K):</strong><br><?php echo number_format($total_paid, 2); ?></div>
            <?php endif; ?>
            <?php if ($current_balance !== null): ?>
                <div class="meta-item"><strong>Current Balance (K):</strong><br><?php echo number_format($current_balance, 2); ?></div>
            <?php endif; ?>
        </div>

        <h2>Payment History</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Amount (K)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr><td colspan="3">No payment transactions found for this student.</td></tr>
                <?php else: ?>
                    <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($t['created_at']))); ?></td>
                            <td><?php echo htmlspecialchars($t['description']); ?></td>
                            <td><?php echo number_format((float)$t['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($transactions)): ?>
            <tfoot>
                <tr>
                    <th colspan="2" style="text-align:right;">Total Paid:</th>
                    <th><?php echo number_format($sum_paid, 2); ?></th>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>

        <div style="margin-top: 20px; font-size: 0.9rem; color: var(--text-muted);">
            Generated on <?php echo date('Y-m-d H:i'); ?> by Finance Module.
        </div>
    </div>
</body>
</html>
