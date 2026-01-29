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

// Get parameters for filtering and pagination
$page = (int)($_GET['page'] ?? 1);
$type = $_GET['type'] ?? 'all';
$programme_id = (int)($_GET['programme_id'] ?? 0);
$limit = (int)($_GET['limit'] ?? 25);
$offset = ($page - 1) * $limit;

try {
    // Build query based on filters
    $sql = "SELECT DISTINCT 
                sp.student_number,
                sp.full_name,
                u.email,
                p.name as programme_name,
                CASE 
                    WHEN ps.payment_method IS NOT NULL AND ps.payment_method != '' THEN 'First Time Registration'
                    ELSE 'Regular/Returning'
                END as registration_type,
                COALESCE(cr.submitted_at, ps.updated_at) as registration_date,
                sp.user_id
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
        $sql .= " AND (sp.programme_id = ? OR cr.course_id IN (SELECT course_id FROM course WHERE programme_id = ?))";
        $params[] = $programme_id;
        $params[] = $programme_id;
    }
    
    $sql .= " ORDER BY registration_date DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count total records for pagination
    $countSql = "SELECT COUNT(DISTINCT sp.student_number) as total_count
                 FROM student_profile sp
                 LEFT JOIN pending_students ps ON sp.student_number = ps.student_number
                 LEFT JOIN course_registration cr ON sp.user_id = cr.student_id
                 WHERE (cr.status = 'approved' OR ps.registration_status = 'approved')";
    
    $countParams = [];
    
    if ($type !== 'all') {
        if ($type === 'first_time') {
            $countSql .= " AND ps.payment_method IS NOT NULL AND ps.payment_method != ''";
        } elseif ($type === 'regular') {
            $countSql .= " AND (ps.payment_method IS NULL OR ps.payment_method = '')";
        }
    }
    
    if ($programme_id > 0) {
        $countSql .= " AND (sp.programme_id = ? OR cr.course_id IN (SELECT course_id FROM course WHERE programme_id = ?))";
        $countParams[] = $programme_id;
        $countParams[] = $programme_id;
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalRecords = $countStmt->fetch()['total_count'];
    
    // Calculate pagination
    $lastPage = max(1, ceil($totalRecords / $limit));
    $currentPage = min($lastPage, max(1, $page));
    
    // Create pagination array
    $pages = [];
    $range = 2; // Number of pages to show around current page
    
    $start = max(1, $currentPage - $range);
    $end = min($lastPage, $currentPage + $range);
    
    if ($start > 1) {
        $pages[] = 1;
        if ($start > 2) {
            $pages[] = '...';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $pages[] = $i;
    }
    
    if ($end < $lastPage) {
        if ($end < $lastPage - 1) {
            $pages[] = '...';
        }
        $pages[] = $lastPage;
    }
    
    $pagination = [
        'current_page' => $currentPage,
        'last_page' => $lastPage,
        'per_page' => $limit,
        'total' => $totalRecords,
        'pages' => $pages
    ];
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'students' => $students,
        'pagination' => $pagination,
        'filters' => [
            'type' => $type,
            'programme_id' => $programme_id
        ]
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching approved students: ' . $e->getMessage()
    ]);
}
?>