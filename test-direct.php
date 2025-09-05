<?php
/**
 * Test Direct - Verificación directa de funcionamiento
 */

// Headers básicos
header('Content-Type: application/json');

echo json_encode([
    'test' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'post_data' => $_POST,
    'server_info' => [
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'unknown',
        'HTTP_AUTHORIZATION' => isset($_SERVER['HTTP_AUTHORIZATION']) ? 'present' : 'not present',
        'PHP_AUTH_USER' => $_SERVER['PHP_AUTH_USER'] ?? 'not set',
        'PHP_AUTH_PW' => isset($_SERVER['PHP_AUTH_PW']) ? 'present' : 'not set'
    ]
], JSON_PRETTY_PRINT);
?>