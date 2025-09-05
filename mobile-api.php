<?php
/**
 * Mobile API endpoint que bypassa admin-ajax.php
 * URL: https://tu-sitio.com/wp-content/plugins/GICAACCOUNT/mobile-api.php
 */

// Cargar WordPress sin themes
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

// Headers para CORS y JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST method allowed']);
    exit();
}

try {
    // Log the request for debugging
    error_log('=== GICA Mobile API Request ===');
    error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
    error_log('HTTP_USER_AGENT: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'not set'));
    error_log('CONTENT_TYPE: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    
    // Get POST data
    $input = file_get_contents('php://input');
    error_log('RAW INPUT: ' . $input);
    
    // Parse POST data (puede venir como form-data o JSON)
    $post_data = [];
    if (!empty($_POST)) {
        $post_data = $_POST;
        error_log('POST DATA: ' . print_r($_POST, true));
    } else {
        // Try to parse as form data
        parse_str($input, $post_data);
        error_log('PARSED DATA: ' . print_r($post_data, true));
    }
    
    // Validate action
    $action = $post_data['action'] ?? '';
    if (empty($action)) {
        throw new Exception('Action parameter is required');
    }
    
    // Process based on action
    switch ($action) {
        case 'gica_mobile_register_token':
            $result = handle_mobile_register_token($post_data);
            break;
            
        case 'gica_mobile_get_notifications':
            $result = handle_mobile_get_notifications($post_data);
            break;
            
        case 'test_connection':
            $result = handle_test_connection($post_data);
            break;
            
        case 'save_service_account_direct':
            $result = handle_save_service_account_direct($post_data);
            break;
            
        case 'send_test_notification_direct':
            $result = handle_send_test_notification_direct($post_data);
            break;
            
        case 'complete_fcm_test':
            $result = handle_complete_fcm_test($post_data);
            break;
            
        default:
            throw new Exception('Unknown action: ' . $action);
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    
} catch (Exception $e) {
    error_log('GICA Mobile API Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'timestamp' => current_time('mysql'),
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
        ]
    ]);
}

/**
 * Handle mobile token registration
 */
function handle_mobile_register_token($data) {
    // Validate API key
    $api_key = $data['api_key'] ?? '';
    $expected_api_key = get_option('gica_mobile_api_key', 'gica_mobile_2024');
    
    if ($api_key !== $expected_api_key) {
        throw new Exception('Invalid API key provided');
    }
    
    // Validate required fields
    $token = sanitize_text_field($data['fcm_token'] ?? '');
    $device_id = sanitize_text_field($data['device_id'] ?? '');
    $app_version = sanitize_text_field($data['app_version'] ?? '');
    $device_info = sanitize_text_field($data['device_info'] ?? '');
    $user_id = sanitize_text_field($data['user_id'] ?? null);
    
    if (empty($token)) {
        throw new Exception('FCM token is required');
    }
    
    if (empty($device_id)) {
        throw new Exception('Device ID is required');
    }
    
    global $wpdb;
    
    // Create mobile tokens table if it doesn't exist
    $table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
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
    
    if ($existing_device) {
        // Update existing device
        $result = $wpdb->update(
            $table_name,
            array(
                'fcm_token' => $token,
                'user_id' => $user_id,
                'app_version' => $app_version,
                'device_info' => $device_info,
                'is_active' => 1,
                'updated_at' => current_time('mysql')
            ),
            array('device_id' => $device_id)
        );
        
        $action = 'updated';
        $device_record_id = $existing_device->id;
    } else {
        // Insert new device
        $result = $wpdb->insert(
            $table_name,
            array(
                'device_id' => $device_id,
                'fcm_token' => $token,
                'user_id' => $user_id,
                'app_version' => $app_version,
                'device_info' => $device_info,
                'platform' => 'android',
                'is_active' => 1,
                'created_at' => current_time('mysql')
            )
        );
        
        $action = 'registered';
        $device_record_id = $wpdb->insert_id;
    }
    
    if ($result === false) {
        throw new Exception('Database error occurred: ' . $wpdb->last_error);
    }
    
    // Also save the most recent token for admin testing
    update_option('gica_fcm_device_token', $token);
    update_option('gica_fcm_last_mobile_device', $device_id);
    
    // Log successful registration
    error_log("GICA Mobile API: Device $device_id $action successfully");
    
    return [
        'message' => "Device $action successfully",
        'device_id' => $device_id,
        'record_id' => $device_record_id,
        'token_preview' => substr($token, 0, 50) . '...',
        'timestamp' => current_time('mysql'),
        'action' => $action
    ];
}

/**
 * Handle get notifications
 */
function handle_mobile_get_notifications($data) {
    // Validate API key
    $api_key = $data['api_key'] ?? '';
    $expected_api_key = get_option('gica_mobile_api_key', 'gica_mobile_2024');
    
    if ($api_key !== $expected_api_key) {
        throw new Exception('Invalid API key');
    }
    
    $device_id = sanitize_text_field($data['device_id'] ?? '');
    $limit = intval($data['limit'] ?? 20);
    $limit = min($limit, 50); // Max 50 notifications
    
    if (empty($device_id)) {
        throw new Exception('Device ID is required');
    }
    
    // Get FCM service to access notification logs
    if (class_exists('GicaFCMService')) {
        $fcm_service = new GicaFCMService();
        $all_logs = $fcm_service->get_notification_logs();
    } else {
        $all_logs = get_option('gica_fcm_notification_logs', []);
    }
    
    // Filter and format logs for mobile
    $mobile_notifications = [];
    foreach (array_slice($all_logs, 0, $limit) as $log) {
        $mobile_notifications[] = [
            'id' => md5($log['timestamp'] . $log['title']),
            'title' => $log['title'],
            'body' => $log['body'],
            'data' => $log['data'] ?? [],
            'timestamp' => $log['timestamp'],
            'success' => $log['success'] ?? true
        ];
    }
    
    // Update last notification check time
    global $wpdb;
    $table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
        $wpdb->update(
            $table_name,
            ['last_notification_at' => current_time('mysql')],
            ['device_id' => $device_id]
        );
    }
    
    return [
        'notifications' => $mobile_notifications,
        'total_count' => count($mobile_notifications),
        'timestamp' => current_time('mysql')
    ];
}

/**
 * Handle test connection
 */
function handle_test_connection($data) {
    return [
        'message' => 'Connection test successful',
        'server_time' => current_time('mysql'),
        'wordpress_version' => get_bloginfo('version'),
        'plugin_active' => is_plugin_active('GICAACCOUNT/gica-account.php'),
        'received_data' => array_keys($data)
    ];
}

/**
 * Save Service Account JSON directly
 */
function handle_save_service_account_direct($data) {
    $service_account_json = wp_unslash($data['service_account_json'] ?? '');
    
    if (empty($service_account_json)) {
        throw new Exception('Service Account JSON is required');
    }
    
    // Validate JSON format
    $service_account_data = json_decode($service_account_json, true);
    if (!$service_account_data) {
        throw new Exception('Invalid JSON format');
    }
    
    // Validate required fields
    $required_fields = ['type', 'project_id', 'private_key_id', 'private_key', 'client_email'];
    foreach ($required_fields as $field) {
        if (!isset($service_account_data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Validate it's a service account
    if ($service_account_data['type'] !== 'service_account') {
        throw new Exception('Not a valid Service Account JSON');
    }
    
    // Save the service account JSON
    update_option('gica_fcm_service_account_json', $service_account_json);
    
    // Update project ID if needed
    $current_project_id = get_option('gica_firebase_project_id', '');
    if (empty($current_project_id) || $current_project_id !== $service_account_data['project_id']) {
        update_option('gica_firebase_project_id', $service_account_data['project_id']);
    }
    
    // Enable FCM if not enabled
    if (!get_option('gica_fcm_enabled', false)) {
        update_option('gica_fcm_enabled', true);
    }
    
    error_log('GICA FCM: Service Account JSON saved successfully for project: ' . $service_account_data['project_id']);
    
    return [
        'message' => 'Service Account saved successfully',
        'project_id' => $service_account_data['project_id'],
        'client_email' => $service_account_data['client_email'],
        'fcm_enabled' => true
    ];
}

/**
 * Send test notification directly to a device
 */
function handle_send_test_notification_direct($data) {
    $device_id = sanitize_text_field($data['device_id'] ?? '');
    $fcm_token = sanitize_text_field($data['fcm_token'] ?? '');
    
    if (empty($device_id) || empty($fcm_token)) {
        throw new Exception('Device ID and FCM token are required');
    }
    
    // Set this token as the test token temporarily
    update_option('gica_fcm_device_token', $fcm_token);
    
    // Load FCM service
    $fcm_file = plugin_dir_path(__FILE__) . 'includes/class-fcm-service.php';
    if (file_exists($fcm_file)) {
        require_once $fcm_file;
        
        if (class_exists('GICA_FCM_Service')) {
            $fcm_service = new GICA_FCM_Service();
            
            $result = $fcm_service->send_notification(
                'Test desde Debug FCM',
                'Notificación de prueba enviada desde la página de debug. Si ves esto, ¡FCM funciona correctamente!',
                ['test' => true, 'source' => 'debug_page']
            );
            
            if ($result['success']) {
                return [
                    'message' => 'Test notification sent successfully',
                    'device_id' => $device_id,
                    'response' => $result
                ];
            } else {
                throw new Exception('FCM send failed: ' . ($result['error'] ?? 'Unknown error'));
            }
        } else {
            throw new Exception('FCM Service class not found');
        }
    } else {
        throw new Exception('FCM Service file not found');
    }
}

/**
 * Complete FCM test
 */
function handle_complete_fcm_test($data) {
    $results = [];
    
    // Test 1: Configuration check
    $fcm_enabled = get_option('gica_fcm_enabled', false);
    $firebase_project_id = get_option('gica_firebase_project_id', '');
    $fcm_service_account = get_option('gica_fcm_service_account_json', '');
    
    $results['configuration'] = [
        'fcm_enabled' => $fcm_enabled,
        'project_id_configured' => !empty($firebase_project_id),
        'service_account_configured' => !empty($fcm_service_account)
    ];
    
    // Test 2: Device check
    global $wpdb;
    $table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
    $device_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_active = 1");
    
    $results['devices'] = [
        'total_devices' => intval($device_count),
        'has_devices' => $device_count > 0
    ];
    
    // Test 3: FCM service test
    if (!empty($fcm_service_account)) {
        $fcm_file = plugin_dir_path(__FILE__) . 'includes/class-fcm-service.php';
        if (file_exists($fcm_file)) {
            require_once $fcm_file;
            if (class_exists('GICA_FCM_Service')) {
                $fcm_service = new GICA_FCM_Service();
                $results['fcm_service'] = [
                    'class_exists' => true,
                    'can_instantiate' => true
                ];
            } else {
                $results['fcm_service'] = [
                    'class_exists' => false,
                    'error' => 'FCM Service class not found'
                ];
            }
        } else {
            $results['fcm_service'] = [
                'file_exists' => false,
                'error' => 'FCM Service file not found'
            ];
        }
    } else {
        $results['fcm_service'] = [
            'configured' => false,
            'error' => 'Service Account not configured'
        ];
    }
    
    // Overall status
    $all_ok = $fcm_enabled && !empty($firebase_project_id) && !empty($fcm_service_account) && $device_count > 0;
    
    return [
        'message' => $all_ok ? 'All tests passed successfully' : 'Some issues found',
        'overall_status' => $all_ok ? 'success' : 'issues_found',
        'test_results' => $results,
        'timestamp' => current_time('mysql')
    ];
}
?>