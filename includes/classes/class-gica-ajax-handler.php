<?php

if (!defined('ABSPATH')) {
    exit;
}

class GicaAjaxHandler {
    
    public function __construct() {
        // Add FCM-specific AJAX handlers
        add_action('wp_ajax_gica_save_fcm_token', array($this, 'save_fcm_token'));
        add_action('wp_ajax_nopriv_gica_save_fcm_token', array($this, 'save_fcm_token'));
        add_action('wp_ajax_gica_send_test_notification', array($this, 'send_test_notification'));
        
        // Mobile app specific endpoints
        add_action('wp_ajax_gica_mobile_register_token', array($this, 'mobile_register_token'));
        add_action('wp_ajax_nopriv_gica_mobile_register_token', array($this, 'mobile_register_token'));
        add_action('wp_ajax_gica_mobile_get_notifications', array($this, 'mobile_get_notifications'));
        add_action('wp_ajax_nopriv_gica_mobile_get_notifications', array($this, 'mobile_get_notifications'));
        add_action('wp_ajax_gica_set_test_device_token', array($this, 'set_test_device_token'));
        add_action('wp_ajax_gica_save_service_account', array($this, 'save_service_account'));
        add_action('wp_ajax_gica_get_mobile_devices', array($this, 'get_mobile_devices'));
        
        // Debug log to confirm endpoints are being registered
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GICA FCM: AJAX endpoints registered in constructor');
        }
    }
    
    public function handle_ajax() {
        check_ajax_referer('gica_account_nonce', 'nonce');
        
        $action_type = sanitize_text_field($_POST['action_type']);
        
        switch ($action_type) {
            case 'login_user':
                $this->login_user();
                break;
            case 'update_account':
                $this->update_account_details();
                break;
            case 'update_contact':
                $this->update_contact_info();
                break;
            case 'update_additional':
                $this->update_additional_info();
                break;
            case 'register_user':
                $this->register_new_user();
                break;
            case 'logout':
                wp_logout();
                wp_send_json_success(array('redirect' => home_url()));
                break;
        }
        
        wp_die();
    }
    
    private function login_user() {
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']) ? (bool)$_POST['remember'] : false;
        
        if (empty($username) || empty($password)) {
            wp_send_json_error('Usuario y contraseña son requeridos');
        }
        
        $credentials = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember
        );
        
        $user = wp_signon($credentials, false);
        
        if (is_wp_error($user)) {
            $error_message = $user->get_error_message();
            
            // Personalizar mensajes de error comunes
            if (strpos($error_message, 'Invalid username') !== false) {
                $error_message = 'Usuario no encontrado. Verifica tu email o nombre de usuario.';
            } elseif (strpos($error_message, 'incorrect password') !== false) {
                $error_message = 'Contraseña incorrecta. Inténtalo de nuevo.';
            }
            
            wp_send_json_error($error_message);
        } else {
            wp_set_current_user($user->ID);
            wp_send_json_success('Inicio de sesión exitoso');
        }
    }
    
    private function update_account_details() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Usuario no autenticado');
        }
        
        try {
            $user_id = get_current_user_id();
            $gica_user = new GicaUser($user_id);
            
            $user_data = array(
                'display_name' => sanitize_text_field($_POST['display_name']),
                'user_email' => sanitize_email($_POST['user_email']),
                'first_name' => sanitize_text_field($_POST['first_name']),
                'last_name' => sanitize_text_field($_POST['last_name'])
            );
            
            $result = $gica_user->update_user_data($user_data);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            
            wp_send_json_success('Datos actualizados correctamente');
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    private function update_contact_info() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Usuario no autenticado');
        }
        
        try {
            $user_id = get_current_user_id();
            $gica_user = new GicaUser($user_id);
            
            $meta_data = array(
                'phone' => sanitize_text_field($_POST['phone']),
                'address' => sanitize_textarea_field($_POST['address'])
            );
            
            $gica_user->update_meta($meta_data);
            
            wp_send_json_success('Información de contacto actualizada');
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    private function update_additional_info() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Usuario no autenticado');
        }
        
        try {
            $user_id = get_current_user_id();
            $gica_user = new GicaUser($user_id);
            
            $meta_data = array(
                'dni' => sanitize_text_field($_POST['dni']),
                'city' => sanitize_text_field($_POST['city']),
                'region' => sanitize_text_field($_POST['region']),
                'country' => sanitize_text_field($_POST['country']),
                'reference' => sanitize_textarea_field($_POST['reference'])
            );
            
            // Validate required fields
            $required_fields = array('dni', 'city', 'region', 'country');
            foreach ($required_fields as $field) {
                if (empty($meta_data[$field])) {
                    wp_send_json_error("El campo {$field} es requerido");
                }
            }
            
            $gica_user->update_meta($meta_data);
            
            // Get updated completion percentage
            $completion = $gica_user->get_completion_percentage();
            
            wp_send_json_success(array(
                'message' => 'Información adicional actualizada',
                'completion_percentage' => $completion
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    private function register_new_user() {
        $username = sanitize_user($_POST['username']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        
        // Validaciones básicas
        if (empty($username) || empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            wp_send_json_error('Todos los campos son requeridos');
        }
        
        if (!is_email($email)) {
            wp_send_json_error('El email no es válido');
        }
        
        if (email_exists($email)) {
            wp_send_json_error('Ya existe un usuario con este email');
        }
        
        if (username_exists($username)) {
            wp_send_json_error('Ya existe un usuario con este nombre de usuario');
        }
        
        if (strlen($username) < 3) {
            wp_send_json_error('El nombre de usuario debe tener al menos 3 caracteres');
        }
        
        if (strlen($password) < 6) {
            wp_send_json_error('La contraseña debe tener al menos 6 caracteres');
        }
        
        // Validar que el username solo contenga caracteres permitidos
        if (!validate_username($username)) {
            wp_send_json_error('El nombre de usuario contiene caracteres no válidos');
        }
        
        $user_data = array(
            'user_login'    => $username,
            'user_email'    => $email,
            'user_pass'     => $password,
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'display_name'  => $first_name . ' ' . $last_name,
            'role'          => 'subscriber'
        );
        
        $user_id = wp_insert_user($user_data);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        } else {
            // Enviar email de bienvenida (opcional)
            wp_new_user_notification($user_id, null, 'user');
            
            // Enviar notificación FCM sobre nuevo registro
            $this->send_new_user_notification($user_id, $first_name, $last_name, $email);
            
            wp_send_json_success('Usuario registrado correctamente');
        }
    }
    
    /**
     * Save FCM token from frontend
     */
    public function save_fcm_token() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'gica_firebase_nonce')) {
                wp_send_json_error('Invalid nonce');
                return;
            }
            
            $token = sanitize_text_field($_POST['token']);
            $user_agent = sanitize_text_field($_POST['user_agent']);
            $timestamp = sanitize_text_field($_POST['timestamp']);
            
            if (empty($token)) {
                wp_send_json_error('Token is required');
                return;
            }
            
            global $wpdb;
            
            // Create table if it doesn't exist
            $table_name = $wpdb->prefix . 'gica_fcm_tokens';
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id int(11) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) DEFAULT NULL,
                token text NOT NULL,
                user_agent text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                is_active tinyint(1) DEFAULT 1,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            // Get current user ID (can be null for anonymous users)
            $user_id = is_user_logged_in() ? get_current_user_id() : null;
            $session_id = session_id() ?: wp_generate_uuid4();
            
            // Check if token already exists
            $existing_token = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE token = %s",
                $token
            ));
            
            if ($existing_token) {
                // Update existing token
                $wpdb->update(
                    $table_name,
                    array(
                        'user_id' => $user_id,
                        'user_agent' => $user_agent,
                        'updated_at' => current_time('mysql'),
                        'is_active' => 1
                    ),
                    array('id' => $existing_token->id)
                );
                
                $message = 'Token actualizado correctamente';
            } else {
                // Insert new token
                $wpdb->insert(
                    $table_name,
                    array(
                        'user_id' => $user_id,
                        'token' => $token,
                        'user_agent' => $user_agent,
                        'created_at' => current_time('mysql'),
                        'is_active' => 1
                    )
                );
                
                $message = 'Token guardado correctamente';
            }
            
            // Also save to options for admin testing
            update_option('gica_fcm_device_token', $token);
            
            wp_send_json_success(array(
                'message' => $message,
                'token_preview' => substr($token, 0, 50) . '...',
                'user_id' => $user_id,
                'timestamp' => current_time('mysql')
            ));
            
        } catch (Exception $e) {
            error_log('GICA FCM Save Token Error: ' . $e->getMessage());
            wp_send_json_error('Error saving token: ' . $e->getMessage());
        }
    }
    
    /**
     * Send test notification
     */
    public function send_test_notification() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permission denied');
                return;
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'gica_firebase_nonce')) {
                wp_send_json_error('Invalid nonce');
                return;
            }
            
            $fcm_service = new GicaFCMService();
            
            if (!$fcm_service->is_enabled()) {
                wp_send_json_error('FCM no está configurado correctamente');
                return;
            }
            
            $result = $fcm_service->send_test_notification();
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => 'Notificación de prueba enviada correctamente',
                    'timestamp' => current_time('mysql')
                ));
            } else {
                wp_send_json_error('Error enviando notificación de prueba');
            }
            
        } catch (Exception $e) {
            error_log('GICA FCM Test Notification Error: ' . $e->getMessage());
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Register FCM token from mobile app
     */
    public function mobile_register_token() {
        try {
            // Enhanced logging for debugging
            error_log('=== GICA Mobile Token Registration START ===');
            error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
            error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
            error_log('POST DATA: ' . print_r($_POST, true));
            error_log('CONTENT_TYPE: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
            error_log('USER_AGENT: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'not set'));
            
            // Validate required fields
            $token = sanitize_text_field($_POST['fcm_token'] ?? '');
            $device_id = sanitize_text_field($_POST['device_id'] ?? '');
            $app_version = sanitize_text_field($_POST['app_version'] ?? '');
            $device_info = sanitize_text_field($_POST['device_info'] ?? '');
            $user_id = sanitize_text_field($_POST['user_id'] ?? null);
            
            // Validate API key (simple security)
            $api_key = sanitize_text_field($_POST['api_key'] ?? '');
            $expected_api_key = get_option('gica_mobile_api_key', 'gica_mobile_2024');
            
            if ($api_key !== $expected_api_key) {
                wp_send_json_error(array(
                    'message' => 'Invalid API key',
                    'code' => 'INVALID_API_KEY'
                ));
                return;
            }
            
            if (empty($token)) {
                wp_send_json_error(array(
                    'message' => 'FCM token is required',
                    'code' => 'MISSING_TOKEN'
                ));
                return;
            }
            
            if (empty($device_id)) {
                wp_send_json_error(array(
                    'message' => 'Device ID is required',
                    'code' => 'MISSING_DEVICE_ID'
                ));
                return;
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
                wp_send_json_error(array(
                    'message' => 'Database error occurred',
                    'code' => 'DATABASE_ERROR'
                ));
                return;
            }
            
            // Also save the most recent token for admin testing
            update_option('gica_fcm_device_token', $token);
            update_option('gica_fcm_last_mobile_device', $device_id);
            
            // Log successful registration
            error_log("GICA Mobile FCM: Device $device_id $action successfully");
            
            wp_send_json_success(array(
                'message' => "Device $action successfully",
                'device_id' => $device_id,
                'record_id' => $device_record_id,
                'token_preview' => substr($token, 0, 50) . '...',
                'timestamp' => current_time('mysql'),
                'action' => $action
            ));
            
        } catch (Exception $e) {
            error_log('GICA Mobile Token Registration Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Registration failed: ' . $e->getMessage(),
                'code' => 'REGISTRATION_ERROR'
            ));
        }
    }
    
    /**
     * Get notifications for mobile app
     */
    public function mobile_get_notifications() {
        try {
            // Validate API key
            $api_key = sanitize_text_field($_POST['api_key'] ?? '');
            $expected_api_key = get_option('gica_mobile_api_key', 'gica_mobile_2024');
            
            if ($api_key !== $expected_api_key) {
                wp_send_json_error(array(
                    'message' => 'Invalid API key',
                    'code' => 'INVALID_API_KEY'
                ));
                return;
            }
            
            $device_id = sanitize_text_field($_POST['device_id'] ?? '');
            $limit = intval($_POST['limit'] ?? 20);
            $limit = min($limit, 50); // Max 50 notifications
            
            if (empty($device_id)) {
                wp_send_json_error(array(
                    'message' => 'Device ID is required',
                    'code' => 'MISSING_DEVICE_ID'
                ));
                return;
            }
            
            // Get FCM service to access notification logs
            $fcm_service = new GicaFCMService();
            $all_logs = $fcm_service->get_notification_logs();
            
            // Filter and format logs for mobile
            $mobile_notifications = array();
            foreach (array_slice($all_logs, 0, $limit) as $log) {
                $mobile_notifications[] = array(
                    'id' => md5($log['timestamp'] . $log['title']),
                    'title' => $log['title'],
                    'body' => $log['body'],
                    'data' => $log['data'] ?? array(),
                    'timestamp' => $log['timestamp'],
                    'success' => $log['success'] ?? true
                );
            }
            
            // Update last notification check time
            global $wpdb;
            $table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
            $wpdb->update(
                $table_name,
                array('last_notification_at' => current_time('mysql')),
                array('device_id' => $device_id)
            );
            
            wp_send_json_success(array(
                'notifications' => $mobile_notifications,
                'total_count' => count($mobile_notifications),
                'timestamp' => current_time('mysql')
            ));
            
        } catch (Exception $e) {
            error_log('GICA Mobile Get Notifications Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to get notifications: ' . $e->getMessage(),
                'code' => 'GET_NOTIFICATIONS_ERROR'
            ));
        }
    }
    
    /**
     * Set device token for testing from admin
     */
    public function set_test_device_token() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permission denied');
                return;
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'gica_test_device')) {
                wp_send_json_error('Invalid nonce');
                return;
            }
            
            $device_id = sanitize_text_field($_POST['device_id'] ?? '');
            $fcm_token = sanitize_text_field($_POST['fcm_token'] ?? '');
            
            if (empty($device_id) || empty($fcm_token)) {
                wp_send_json_error('Device ID and FCM token are required');
                return;
            }
            
            // Set the device token as the current test token
            update_option('gica_fcm_device_token', $fcm_token);
            update_option('gica_fcm_test_device_id', $device_id);
            
            wp_send_json_success(array(
                'message' => 'Test device configured successfully',
                'device_id' => $device_id,
                'token_preview' => substr($fcm_token, 0, 50) . '...'
            ));
            
        } catch (Exception $e) {
            error_log('GICA Set Test Device Error: ' . $e->getMessage());
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Save Service Account JSON for FCM HTTP v1 API
     */
    public function save_service_account() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permission denied');
                return;
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'gica_service_account')) {
                wp_send_json_error('Invalid nonce');
                return;
            }
            
            $service_account_json = wp_unslash($_POST['service_account_json'] ?? '');
            
            if (empty($service_account_json)) {
                wp_send_json_error('Service Account JSON is required');
                return;
            }
            
            // Validate JSON format
            $service_account_data = json_decode($service_account_json, true);
            if (!$service_account_data) {
                wp_send_json_error('Invalid JSON format');
                return;
            }
            
            // Validate required fields
            $required_fields = ['type', 'project_id', 'private_key_id', 'private_key', 'client_email'];
            foreach ($required_fields as $field) {
                if (!isset($service_account_data[$field])) {
                    wp_send_json_error("Missing required field: $field");
                    return;
                }
            }
            
            // Validate it's a service account
            if ($service_account_data['type'] !== 'service_account') {
                wp_send_json_error('Not a valid Service Account JSON');
                return;
            }
            
            // Save the service account JSON
            update_option('gica_fcm_service_account_json', $service_account_json);
            
            // Update project ID if it's different
            $current_project_id = get_option('gica_firebase_project_id', '');
            if (empty($current_project_id) || $current_project_id !== $service_account_data['project_id']) {
                update_option('gica_firebase_project_id', $service_account_data['project_id']);
            }
            
            // Log successful save
            error_log('GICA FCM: Service Account JSON saved successfully for project: ' . $service_account_data['project_id']);
            
            wp_send_json_success(array(
                'message' => 'Service Account saved successfully',
                'project_id' => $service_account_data['project_id'],
                'client_email' => $service_account_data['client_email']
            ));
            
        } catch (Exception $e) {
            error_log('GICA Save Service Account Error: ' . $e->getMessage());
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get mobile devices list for debugging
     */
    public function get_mobile_devices() {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
            
            // Create table if it doesn't exist
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
            
            // Get devices
            $devices = $wpdb->get_results(
                "SELECT * FROM $table_name ORDER BY updated_at DESC LIMIT 50"
            );
            
            wp_send_json_success(array(
                'devices' => $devices,
                'total_count' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name"),
                'active_count' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_active = 1")
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error getting devices: ' . $e->getMessage());
        }
    }
    
    /**
     * Send FCM notification when a new user registers
     */
    private function send_new_user_notification($user_id, $first_name, $last_name, $email) {
        try {
            // Check if FCM is enabled and configured
            if (!get_option('gica_fcm_enabled', false)) {
                error_log('GICA FCM: FCM is disabled, skipping new user notification');
                return;
            }
            
            $service_account = get_option('gica_fcm_service_account_json', '');
            if (empty($service_account)) {
                error_log('GICA FCM: Service account not configured, skipping new user notification');
                return;
            }
            
            // Load FCM service
            $fcm_file = plugin_dir_path(__FILE__) . '../class-fcm-service.php';
            if (!file_exists($fcm_file)) {
                error_log('GICA FCM: FCM service file not found: ' . $fcm_file);
                return;
            }
            
            require_once $fcm_file;
            
            if (!class_exists('GICA_FCM_Service')) {
                error_log('GICA FCM: FCM service class not found');
                return;
            }
            
            $fcm_service = new GICA_FCM_Service();
            
            if (!$fcm_service->is_configured()) {
                error_log('GICA FCM: FCM service not properly configured');
                return;
            }
            
            // Prepare notification content
            $full_name = trim($first_name . ' ' . $last_name);
            $title = '🎉 Nuevo Usuario Registrado';
            $body = "¡$full_name se ha registrado en tu sitio web!";
            
            $notification_data = [
                'type' => 'new_user_registration',
                'user_id' => (string) $user_id,
                'user_name' => $full_name,
                'user_email' => $email,
                'registration_time' => current_time('mysql'),
                'site_url' => get_site_url()
            ];
            
            // Check notification settings for new user registrations
            $notify_on_registration = get_option('gica_fcm_notify_on_registration', true);
            if (!$notify_on_registration) {
                error_log('GICA FCM: Registration notifications are disabled');
                return;
            }
            
            // Check if we should send to all devices or just admin devices
            $send_to_all = get_option('gica_fcm_notify_all_devices', false);
            
            if ($send_to_all) {
                // Send to all registered devices
                $result = $fcm_service->send_notification_to_all($title, $body, $notification_data);
                error_log("GICA FCM: New user notification sent to all devices. Success: {$result['success_count']}, Errors: {$result['error_count']}");
            } else {
                // Send to the most recent device (likely admin)
                $result = $fcm_service->send_notification($title, $body, $notification_data);
                if ($result['success']) {
                    error_log("GICA FCM: New user notification sent successfully");
                } else {
                    error_log("GICA FCM: Failed to send new user notification: " . ($result['error'] ?? 'Unknown error'));
                }
            }
            
        } catch (Exception $e) {
            error_log('GICA FCM: Error sending new user notification: ' . $e->getMessage());
        }
    }
}