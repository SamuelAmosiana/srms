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
$session_id = (int)($_GET['session_id'] ?? 0);
$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 25);
$offset = ($page - 1) * $limit;

// Validate session_id
if ($session_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid session ID'
    ]);
    exit();
}

try {
    // First, get session information
    $session_stmt = $pdo->prepare("SELECT id, session_name, academic_year, term FROM academic_sessions WHERE id = ?");
    $session_stmt->execute([$session_id]);
    $session_info = $session_stmt->fetch();
    
    if (!$session_info) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Session not found'
        ]);
        exit();
    }
    
    // Build query to get students registered in this session
    // Students can be registered through two paths:
    // 1. Through programme_schedule (student_profile -> intake -> programme_schedule -> session)
    // 2. Direct course_registration entries
    
    $sql = "SELECT DISTINCT 
                sp.student_number,
                sp.full_name,
                u.email,
                p.name as programme_name,
                CASE 
                    WHEN ps.payment_method IS NOT NULL AND ps.payment_method != '' THEN 'First Time Registration'
                    ELSE 'Regular/Returning'
                END as registration_type,
                COALESCE(cr.submitted_at, ps.updated_at, ps.created_at) as registration_date,
                sp.user_id,
                s.session_name
            FROM student_profile sp
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN programme pr ON sp.programme_id = pr.id
            LEFT JOIN pending_students ps ON sp.student_number = ps.student_number
            LEFT JOIN course_registration cr ON sp.user_id = cr.student_id AND cr.status = 'approved'
            LEFT JOIN intake i ON sp.intake_id = i.id
            LEFT JOIN programme_schedule psch ON i.id = psch.intake_id AND psch.session_id = ?
            LEFT JOIN academic_sessions s ON psch.session_id = s.id
            LEFT JOIN programme p ON COALESCE(pr.id, ps.programme_id) = p.id
            WHERE (psch.session_id = ? OR cr.id IS NOT NULL)
              AND (cr.status = 'approved' OR ps.registration_status = 'approved' OR psch.id IS NOT NULL)";
    
    $params = [$session_id, $session_id];
    
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
                 LEFT JOIN course_registration cr ON sp.user_id = cr.student_id AND cr.status = 'approved'
                 LEFT JOIN intake i ON sp.intake_id = i.id
                 LEFT JOIN programme_schedule psch ON i.id = psch.intake_id AND psch.session_id = ?
                 WHERE (psch.session_id = ? OR cr.id IS NOT NULL)
                   AND (cr.status = 'approved' OR ps.registration_status = 'approved' OR psch.id IS NOT NULL)";
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute([$session_id, $session_id]);
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
        'session_info' => [
            'id' => $session_info['id'],
            'session_name' => $session_info['session_name'],
            'academic_year' => $session_info['academic_year'],
            'term' => $session_info['term']
        ]
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching session students: ' . $e->getMessage()
    ]);
}
?>