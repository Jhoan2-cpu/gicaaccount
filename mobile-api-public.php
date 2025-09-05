<?php
/**
 * Mobile API Public Endpoint - Bypass HTTP Auth
 * Este archivo maneja el registro de tokens FCM sin pasar por admin-ajax.php
 */

// Headers básicos
header('Content-Type: application/json');

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

// Load WordPress
$possible_paths = [
    dirname(__FILE__) . '/../../../../../../wp-load.php',
    dirname(__FILE__) . '/../../../../../wp-load.php',
    dirname(__FILE__) . '/../../../../wp-load.php',
    dirname(__FILE__) . '/../../../wp-load.php',
    dirname(__FILE__) . '/../../wp-load.php',
    $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php'
];

$wp_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'WordPress not found']);
    exit;
}

// Cargar sistema de configuración
require_once(ABSPATH . 'wp-content/plugins/GICAACCOUNT/includes/classes/class-gica-config-manager.php');

// Configurar CORS dinámicamente
GicaConfigManager::set_cors_headers();

try {
    // Get POST data (support both form data and JSON)
    $input = file_get_contents('php://input');
    $data = [];
    
    if (!empty($input)) {
        // Try to decode as JSON first
        $json_data = json_decode($input, true);
        if ($json_data) {
            $data = $json_data;
        } else {
            // Parse as form data
            parse_str($input, $data);
        }
    }
    
    // Merge with $_POST data
    $data = array_merge($_POST, $data);
    
    // Log the request for debugging
    error_log('GICA Mobile API: Request received - ' . json_encode([
        'method' => $_SERVER['REQUEST_METHOD'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'data_keys' => array_keys($data),
        'raw_input_length' => strlen($input)
    ]));
    
    // Validate required fields
    $api_key = sanitize_text_field($data['api_key'] ?? '');
    $fcm_token = sanitize_text_field($data['fcm_token'] ?? $data['token'] ?? '');
    $device_id = sanitize_text_field($data['device_id'] ?? '');
    $app_version = sanitize_text_field($data['app_version'] ?? '1.0');
    $device_info = sanitize_text_field($data['device_info'] ?? '');
    $user_id = intval($data['user_id'] ?? 0);
    
    // Log request si está habilitado
    GicaConfigManager::log_request([
        'api_key_provided' => !empty($api_key),
        'fcm_token_length' => strlen($fcm_token),
        'device_id' => $device_id,
        'user_id' => $user_id
    ]);
    
    // Validar API key dinámicamente
    if (!GicaConfigManager::validate_api_key($api_key)) {
        error_log("GICA Mobile API: Invalid API key - received: $api_key");
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid API key',
            'code' => 'INVALID_API_KEY',
            'environment' => GicaConfigManager::get_environment()
        ]);
        exit;
    }
    
    // Verificar rate limit
    if (!GicaConfigManager::check_rate_limit($device_id)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded',
            'code' => 'RATE_LIMIT_EXCEEDED'
        ]);
        exit;
    }
    
    // Validate required fields
    if (empty($fcm_token)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'FCM token is required',
            'code' => 'MISSING_TOKEN'
        ]);
        exit;
    }
    
    if (empty($device_id)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Device ID is required',
            'code' => 'MISSING_DEVICE_ID'
        ]);
        exit;
    }
    
    // Create/update device record
    global $wpdb;
    $table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
    
    // Ensure table exists
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        device_id varchar(255) NOT NULL,
        fcm_token text NOT NULL,
        user_id bigint(20) DEFAULT NULL,
        app_version varchar(50) DEFAULT NULL,
        device_info text DEFAULT NULL,
        platform varchar(20) DEFAULT 'android',
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_notification_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY device_id (device_id),
        KEY user_id (user_id),
        KEY is_active (is_active),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Check if device already exists
    $existing_device = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE device_id = %s",
        $device_id
    ));
    
    $current_time = current_time('mysql');
    $user_id_to_save = $user_id > 0 ? $user_id : null;
    
    if ($existing_device) {
        // Update existing device
        $result = $wpdb->update(
            $table_name,
            [
                'fcm_token' => $fcm_token,
                'user_id' => $user_id_to_save,
                'app_version' => $app_version,
                'device_info' => $device_info,
                'is_active' => 1,
                'updated_at' => $current_time
            ],
            ['device_id' => $device_id]
        );
        
        $action = 'updated';
        $device_record_id = $existing_device->id;
    } else {
        // Insert new device
        $result = $wpdb->insert(
            $table_name,
            [
                'device_id' => $device_id,
                'fcm_token' => $fcm_token,
                'user_id' => $user_id_to_save,
                'app_version' => $app_version,
                'device_info' => $device_info,
                'platform' => 'android',
                'is_active' => 1,
                'created_at' => $current_time
            ]
        );
        
        $action = 'registered';
        $device_record_id = $wpdb->insert_id;
    }
    
    if ($result === false) {
        error_log('GICA Mobile API: Database error - ' . $wpdb->last_error);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $wpdb->last_error,
            'code' => 'DATABASE_ERROR'
        ]);
        exit;
    }
    
    // Success response con información del ambiente
    $response = [
        'success' => true,
        'message' => "Device {$action} successfully",
        'data' => [
            'device_id' => $device_id,
            'action' => $action,
            'record_id' => $device_record_id,
            'timestamp' => $current_time
        ],
        'server_info' => GicaConfigManager::get_api_info()
    ];
    
    error_log('GICA Mobile API: Success - ' . json_encode($response));
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('GICA Mobile API: Exception - ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage(),
        'code' => 'EXCEPTION'
    ]);
}
?>