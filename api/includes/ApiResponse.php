<?php
/**
 * API Response helper class
 */
class ApiResponse {
    
    public static function success($data = null, $message = 'Success', $code = 200) {
        http_response_code($code);
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ]);
        exit();
    }
    
    public static function error($message = 'Error', $code = 400, $errors = null) {
        http_response_code($code);
        $response = [
            'status' => 'error',
            'message' => $message
        ];
        
        if ($errors) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response);
        exit();
    }
    
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }
    
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }
    
    public static function validationError($errors) {
        self::error('Validation failed', 422, $errors);
    }
}
?>