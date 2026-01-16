<?php
// Simple PHP test script
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

echo json_encode([
    'status' => 'success',
    'message' => 'PHP is working correctly!',
    'php_version' => phpversion(),
    'timestamp' => date('Y-m-d H:i:s'),
    'mail_function' => function_exists('mail') ? 'Available' : 'Not Available'
]);
?>