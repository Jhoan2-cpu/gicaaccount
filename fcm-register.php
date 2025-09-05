<?php
/**
 * FCM Token Registration Endpoint - Standalone
 * Este archivo registra tokens FCM sin pasar por WordPress auth
 */

// Headers para CORS y API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get POST data
    $input = file_get_contents('php://input');
    $data = [];
    
    if (!empty($input)) {
        $json_data = json_decode($input, true);
        if ($json_data) {
            $data = $json_data;
        } else {
            parse_str($input, $data);
        }
    }
    
    $data = array_merge($_POST, $data);
    
    // Basic validation
    $api_key = $data['api_key'] ?? '';
    $fcm_token = $data['fcm_token'] ?? $data['token'] ?? '';
    $device_id = $data['device_id'] ?? '';
    
    // Simple API key check
    if ($api_key !== 'gica_mobile_2024') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid API key']);
        exit;
    }
    
    if (empty($fcm_token) || empty($device_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    // Write to a simple log file for now
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'device_id' => $device_id,
        'fcm_token' => substr($fcm_token, 0, 50) . '...',
        'device_info' => $data['device_info'] ?? '',
        'app_version' => $data['app_version'] ?? '1.0'
    ];
    
    $log_file = __DIR__ . '/fcm_registrations.log';
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Device registered successfully',
        'data' => [
            'device_id' => $device_id,
            'timestamp' => $log_entry['timestamp']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>