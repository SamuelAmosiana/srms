<?php
session_start();
require_once '../config.php';
require_once '../auth/auth.php';

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit();
}

// Check if user has admin role or course_registrations permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('course_registrations', $pdo)) {
    header('Location: ../auth/login.php');
    exit();
}

// Get parameters for filtering
$type = $_GET['type'] ?? 'all';
$programme_id = (int)($_GET['programme_id'] ?? 0);

try {
    // Build query based on filters (align with export logic)
    $sql = "SELECT DISTINCT 
                sp.student_number,
                sp.full_name,
                u.email,
                p.name as programme_name,
                CASE 
                    WHEN ps.payment_method IS NOT NULL AND ps.payment_method != '' THEN 'First Time Registration'
                    ELSE 'Regular/Returning'
                END as registration_type,
                COALESCE(cr.submitted_at, ps.updated_at) as registration_date
            FROM student_profile sp
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN programme p ON sp.programme_id = p.id
            LEFT JOIN pending_students ps ON sp.student_number = ps.student_number
            LEFT JOIN course_registration cr ON sp.user_id = cr.student_id
            WHERE (cr.status = 'approved' OR ps.registration_status = 'approved')";
    
    $params = [];
    
    if ($type !== 'all') {
        if ($type === 'first_time') {
            $sql .= " AND ps.payment_method IS NOT NULL AND ps.payment_method != ''";
        } elseif ($type === 'regular') {
            $sql .= " AND (ps.payment_method IS NULL OR ps.payment_method = '')";
        }
    }
    
    if ($programme_id > 0) {
        $sql .= " AND (sp.programme_id = ? OR cr.course_id IN (SELECT id FROM course WHERE programme_id = ?))";
        $params[] = $programme_id;
        $params[] = $programme_id;
    }
    
    $sql .= " ORDER BY registration_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $students = [];
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Students Report - LSC Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: white;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            margin: 0;
            color: #333;
        }
        
        .header p {
            margin: 5px 0;
            color: #666;
        }
        
        .filters {
            margin-bottom: 20px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .summary {
            margin-top: 20px;
            padding: 10px;
            background: #e9f7ef;
            border-left: 4px solid #28a745;
        }
        
        @media print {
            body {
                margin: 0;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Approved Students Report</h1>
        <p>Lusaka South College Management System</p>
        <p>Date: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
    
    <div class="filters no-print">
        <strong>Filters Applied:</strong>
        <ul>
            <li>Registration Status: Approved</li>
            <li>Student Type: <?php echo $type === 'all' ? 'All' : ucfirst(str_replace('_', '/', $type)); ?></li>
            <li>Programme: <?php echo $programme_id > 0 ? $pdo->query("SELECT name FROM programme WHERE id = $programme_id")->fetchColumn() : 'All Programmes'; ?></li>
        </ul>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Student Number</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Programme</th>
                <th>Registration Type</th>
                <th>Registration Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($students)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No approved students found</td>
                </tr>
            <?php else: ?>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                        <td><?php echo htmlspecialchars($student['programme_name']); ?></td>
                        <td><?php echo htmlspecialchars($student['registration_type']); ?></td>
                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($student['registration_date']))); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="summary">
        <strong>Total Approved Students:</strong> <?php echo count($students); ?>
    </div>
    
    <div class="no-print" style="margin-top: 30px; text-align: center;">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Print Report
        </button>
        <button onclick="window.close()" class="btn btn-secondary" style="margin-left: 10px;">
            Close
        </button>
    </div>
</body>
</html>
