<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

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

// Include authentication
require_once 'security/Auth.php';

// Route the request
if (empty($request) || $request === '') {
    // API root - return available endpoints
    echo json_encode([
        'message' => 'SRMS API',
        'version' => '1.0',
        'authenticated' => Auth::isAuthenticated(),
        'user' => Auth::getCurrentUser(),
        'endpoints' => [
            'GET /students' => 'Get all students (requires auth)',
            'GET /students/{id}' => 'Get specific student (requires auth)',
            'GET /programmes' => 'Get all programmes (requires auth)',
            'GET /courses' => 'Get all courses (requires auth)',
            'GET /results' => 'Get results (requires auth)',
            'GET /fees' => 'Get fee information (requires auth)',
            'POST /login' => 'Authenticate user',
            'POST /logout' => 'Logout user'
        ]
    ]);
} else {
    $parts = explode('/', $request);
    $resource = $parts[0] ?? '';
    $id = $parts[1] ?? null;
    
    // Include the main API handler
    require_once '../config.php';
    
    // Handle authentication routes separately
    if ($resource === 'login') {
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Username and password required'
                ]);
                exit();
            }
            
            $result = Auth::authenticate($username, $password, $pdo);
            if ($result['success']) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Login successful',
                    'user' => $result['user'],
                    'token' => $result['token']
                ]);
            } else {
                http_response_code(401);
                echo json_encode([
                    'status' => 'error',
                    'message' => $result['message']
                ]);
            }
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        exit();
    } elseif ($resource === 'logout') {
        if ($method === 'POST') {
            Auth::logout();
            echo json_encode([
                'status' => 'success',
                'message' => 'Logged out successfully'
            ]);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        exit();
    }
    
    // Require authentication for all other routes
    if (!Auth::isAuthenticated()) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Authentication required',
            'endpoints' => [
                'POST /login' => 'Authenticate user',
                'GET /' => 'API information'
            ]
        ]);
        exit();
    }
    
    // Now route to controllers with authentication
    switch ($resource) {
        case 'students':
            require_once 'middleware/AuthMiddleware.php';
            AuthMiddleware::requirePermission('students', 'read');
            
            require_once 'controllers/StudentController.php';
            $controller = new StudentController($pdo);
            
            if ($id) {
                // Check if user can access this specific student
                if (!AuthMiddleware::canAccess('students', 'read', $id)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Insufficient permissions to access this student']);
                    exit();
                }
                
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
            require_once 'middleware/AuthMiddleware.php';
            AuthMiddleware::requirePermission('programmes', 'read');
            
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
            require_once 'middleware/AuthMiddleware.php';
            AuthMiddleware::requirePermission('courses', 'read');
            
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
            require_once 'middleware/AuthMiddleware.php';
            AuthMiddleware::requirePermission('results', 'read');
            
            require_once 'controllers/ResultController.php';
            $controller = new ResultController($pdo);
            
            if ($method === 'GET') {
                if ($id) {
                    // Check if user can access results for this student
                    if (!AuthMiddleware::canAccess('results', 'read', $id)) {
                        http_response_code(403);
                        echo json_encode(['error' => 'Insufficient permissions to access these results']);
                        exit();
                    }
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
            require_once 'middleware/AuthMiddleware.php';
            AuthMiddleware::requirePermission('fees', 'read');
            
            require_once 'controllers/FeeController.php';
            $controller = new FeeController($pdo);
            
            if ($method === 'GET') {
                if ($id) {
                    // Check if user can access fees for this student
                    if (!AuthMiddleware::canAccess('fees', 'read', $id)) {
                        http_response_code(403);
                        echo json_encode(['error' => 'Insufficient permissions to access these fees']);
                        exit();
                    }
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