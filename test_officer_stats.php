<?php
require 'config.php';

// Test the officer statistics query
$officer_stats_query = "
    SELECT 
        u.id as officer_id,
        COALESCE(sp.full_name, u.username) as officer_name,
        sp.staff_id,
        COUNT(CASE WHEN a.status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN a.status = 'rejected' THEN 1 END) as rejected_count,
        COUNT(a.id) as total_processed,
        DATE_FORMAT(MAX(a.updated_at), '%Y-%m-%d') as last_activity
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    LEFT JOIN staff_profile sp ON u.id = sp.user_id
    LEFT JOIN applications a ON u.id = a.processed_by
    WHERE r.name = 'Enrollment Officer'
    GROUP BY u.id, sp.full_name, u.username, sp.staff_id
    ORDER BY total_processed DESC
";

try {
    $stmt = $pdo->query($officer_stats_query);
    $results = $stmt->fetchAll();
    
    echo "Enrollment Officer Statistics:\n";
    echo "============================\n";
    foreach ($results as $row) {
        echo "Officer: " . $row['officer_name'] . " (" . ($row['staff_id'] ?? 'N/A') . ")\n";
        echo "  Approved: " . $row['approved_count'] . "\n";
        echo "  Rejected: " . $row['rejected_count'] . "\n";
        echo "  Total: " . $row['total_processed'] . "\n";
        echo "  Last Activity: " . ($row['last_activity'] ?? 'Never') . "\n\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Test the monthly statistics query
$monthly_officer_stats_query = "
    SELECT 
        u.id as officer_id,
        COALESCE(sp.full_name, u.username) as officer_name,
        DATE_FORMAT(a.updated_at, '%Y-%m') as month,
        COUNT(CASE WHEN a.status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN a.status = 'rejected' THEN 1 END) as rejected_count,
        COUNT(a.id) as total_processed
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    LEFT JOIN staff_profile sp ON u.id = sp.user_id
    LEFT JOIN applications a ON u.id = a.processed_by
    WHERE r.name = 'Enrollment Officer' AND a.updated_at IS NOT NULL
    GROUP BY u.id, sp.full_name, u.username, DATE_FORMAT(a.updated_at, '%Y-%m')
    ORDER BY month DESC, total_processed DESC
";

try {
    $stmt = $pdo->query($monthly_officer_stats_query);
    $results = $stmt->fetchAll();
    
    echo "Monthly Officer Statistics:\n";
    echo "=========================\n";
    foreach ($results as $row) {
        echo "Month: " . $row['month'] . ", Officer: " . $row['officer_name'] . "\n";
        echo "  Approved: " . $row['approved_count'] . "\n";
        echo "  Rejected: " . $row['rejected_count'] . "\n";
        echo "  Total: " . $row['total_processed'] . "\n\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>