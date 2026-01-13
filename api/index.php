<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Main API router
$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Remove query string and base path
$base_path = '/srms/api';
$request = parse_url($request, PHP_URL_PATH);
$request = str_replace($base_path, '', $request);

// Remove leading slash
$request = trim($request, '/');

// Route the request
if (empty($request) || $request === '') {
    // API root - return available endpoints
    echo json_encode([
        'message' => 'SRMS API',
        'version' => '1.0',
        'endpoints' => [
            'GET /students' => 'Get all students',
            'GET /students/{id}' => 'Get specific student',
            'GET /programmes' => 'Get all programmes',
            'GET /courses' => 'Get all courses',
            'GET /results' => 'Get results',
            'GET /fees' => 'Get fee information'
        ]
    ]);
} else {
    $parts = explode('/', $request);
    $resource = $parts[0] ?? '';
    $id = $parts[1] ?? null;
    
    // Include the main API handler
    require_once '../config.php';
    
    switch ($resource) {
        case 'students':
            require_once 'controllers/StudentController.php';
            $controller = new StudentController($pdo);
            
            if ($id) {
                if ($method === 'GET') {
                    $controller->getStudent($id);
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
            } else {
                if ($method === 'GET') {
                    $controller->getStudents();
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
            }
            break;
            
        case 'programmes':
            require_once 'controllers/ProgrammeController.php';
            $controller = new ProgrammeController($pdo);
            
            if ($method === 'GET') {
                if ($id) {
                    $controller->getProgramme($id);
                } else {
                    $controller->getProgrammes();
                }
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case 'courses':
            require_once 'controllers/CourseController.php';
            $controller = new CourseController($pdo);
            
            if ($method === 'GET') {
                if ($id) {
                    $controller->getCourse($id);
                } else {
                    $controller->getCourses();
                }
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case 'results':
            require_once 'controllers/ResultController.php';
            $controller = new ResultController($pdo);
            
            if ($method === 'GET') {
                if ($id) {
                    $controller->getStudentResults($id);
                } else {
                    $controller->getResults();
                }
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case 'fees':
            require_once 'controllers/FeeController.php';
            $controller = new FeeController($pdo);
            
            if ($method === 'GET') {
                if ($id) {
                    $controller->getStudentFees($id);
                } else {
                    $controller->getFees();
                }
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
}
?>