<?php
require 'config.php';

// Get enrollment officer statistics for commission tracking
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
$officer_statistics = $pdo->query($officer_stats_query)->fetchAll();

// Get monthly statistics by officer
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
$monthly_officer_statistics = $pdo->query($monthly_officer_stats_query)->fetchAll();

echo "<h1>Enrollment Officer Performance (Commission Tracking)</h1>\n";
echo "<table border='1'>\n";
echo "<tr><th>Officer Name</th><th>Staff ID</th><th>Approved</th><th>Rejected</th><th>Total Processed</th><th>Approval Rate</th><th>Last Activity</th></tr>\n";

foreach ($officer_statistics as $stats) {
    $approval_rate = $stats['total_processed'] > 0 ? 
        round(($stats['approved_count'] / $stats['total_processed']) * 100, 2) : 0;
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($stats['officer_name']) . "</td>";
    echo "<td>" . htmlspecialchars($stats['staff_id'] ?? 'N/A') . "</td>";
    echo "<td>" . number_format($stats['approved_count']) . "</td>";
    echo "<td>" . number_format($stats['rejected_count']) . "</td>";
    echo "<td>" . number_format($stats['total_processed']) . "</td>";
    echo "<td>" . $approval_rate . "%</td>";
    echo "<td>" . ($stats['last_activity'] ?? 'Never') . "</td>";
    echo "</tr>\n";
}

echo "</table>\n";

echo "<h2>Monthly Performance by Officer</h2>\n";
echo "<table border='1'>\n";
echo "<tr><th>Month</th><th>Officer Name</th><th>Approved</th><th>Rejected</th><th>Total Processed</th><th>Approval Rate</th></tr>\n";

foreach ($monthly_officer_statistics as $stats) {
    $approval_rate = $stats['total_processed'] > 0 ? 
        round(($stats['approved_count'] / $stats['total_processed']) * 100, 2) : 0;
    
    echo "<tr>";
    echo "<td>" . $stats['month'] . "</td>";
    echo "<td>" . htmlspecialchars($stats['officer_name']) . "</td>";
    echo "<td>" . number_format($stats['approved_count']) . "</td>";
    echo "<td>" . number_format($stats['rejected_count']) . "</td>";
    echo "<td>" . number_format($stats['total_processed']) . "</td>";
    echo "<td>" . $approval_rate . "%</td>";
    echo "</tr>\n";
}

echo "</table>\n";
?>