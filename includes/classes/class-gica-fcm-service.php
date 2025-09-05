<?php

if (!defined('ABSPATH')) {
    exit;
}

class GicaFCMService {
    
    private $project_id;
    private $service_account_json;
    private $access_token;
    private $device_token;
    
    public function __construct() {
        $this->project_id = get_option('gica_firebase_project_id', '');
        $this->service_account_json = get_option('gica_fcm_service_account_json', '');
        $this->device_token = get_option('gica_fcm_device_token', '');
    }
    
    /**
     * Check if FCM is configured and enabled
     */
    public function is_enabled() {
        return get_option('gica_fcm_enabled', false) && 
               !empty($this->project_id) && 
               !empty($this->service_account_json);
    }
    
    /**
     * Send notification for new user registration
     */
    public function send_user_registration_notification($user_data) {
        if (!$this->is_enabled()) {
            return false;
        }
        
        try {
            $title = '🎉 Nuevo usuario registrado';
            $body = sprintf(
                '%s se ha registrado en %s',
                $user_data['display_name'] ?: $user_data['username'],
                get_bloginfo('name')
            );
            
            $data = array(
                'type' => 'user_registration',
                'user_id' => $user_data['ID'],
                'username' => $user_data['username'],
                'email' => $user_data['email'],
                'registration_time' => current_time('mysql'),
                'site_name' => get_bloginfo('name'),
                'site_url' => get_bloginfo('url')
            );
            
            return $this->send_notification($title, $body, $data);
            
        } catch (Exception $e) {
            error_log('GICA FCM Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send test notification
     */
    public function send_test_notification() {
        if (!$this->is_enabled()) {
            throw new Exception('FCM no está configurado correctamente');
        }
        
        if (empty($this->device_token)) {
            throw new Exception('Token del dispositivo no configurado');
        }
        
        $title = '🧪 Notificación de prueba';
        $body = sprintf('FCM configurado correctamente en %s', get_bloginfo('name'));
        
        $data = array(
            'type' => 'test',
            'timestamp' => current_time('mysql'),
            'site_name' => get_bloginfo('name')
        );
        
        return $this->send_notification($title, $body, $data);
    }
    
    /**
     * Send FCM notification using HTTP v1 API
     */
    private function send_notification($title, $body, $data = array()) {
        try {
            // Get access token
            $access_token = $this->get_access_token();
            if (!$access_token) {
                throw new Exception('No se pudo obtener el token de acceso');
            }
            
            // Prepare the message
            $message = array(
                'message' => array(
                    'token' => $this->device_token,
                    'notification' => array(
                        'title' => $title,
                        'body' => $body
                    ),
                    'data' => array_map('strval', $data), // FCM data must be strings
                    'android' => array(
                        'notification' => array(
                            'icon' => 'ic_notification',
                            'color' => '#FF6B35',
                            'channel_id' => 'gica_notifications'
                        ),
                        'priority' => 'high'
                    ),
                    'apns' => array(
                        'payload' => array(
                            'aps' => array(
                                'alert' => array(
                                    'title' => $title,
                                    'body' => $body
                                ),
                                'sound' => 'default'
                            )
                        )
                    )
                )
            );
            
            // Send the request
            $url = 'https://fcm.googleapis.com/v1/projects/' . $this->project_id . '/messages:send';
            
            $response = wp_remote_post($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($message),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('Error en la petición HTTP: ' . $response->get_error_message());
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($http_code !== 200) {
                $error_data = json_decode($response_body, true);
                $error_message = isset($error_data['error']['message']) 
                    ? $error_data['error']['message'] 
                    : 'Error HTTP ' . $http_code;
                throw new Exception('Error FCM: ' . $error_message);
            }
            
            // Log successful notification
            $this->log_notification_sent($title, $body, $data);
            
            return true;
            
        } catch (Exception $e) {
            error_log('GICA FCM Send Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get OAuth 2.0 access token using service account
     */
    private function get_access_token() {
        try {
            $service_account = json_decode($this->service_account_json, true);
            if (!$service_account) {
                throw new Exception('JSON de cuenta de servicio inválido');
            }
            
            $now = time();
            $token_lifetime = 3600; // 1 hour
            
            // Create JWT header
            $header = array(
                'alg' => 'RS256',
                'typ' => 'JWT'
            );
            
            // Create JWT payload
            $payload = array(
                'iss' => $service_account['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + $token_lifetime
            );
            
            // Create JWT
            $jwt = $this->create_jwt($header, $payload, $service_account['private_key']);
            
            // Exchange JWT for access token
            $token_response = wp_remote_post('https://oauth2.googleapis.com/token', array(
                'body' => array(
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($token_response)) {
                throw new Exception('Error al obtener token: ' . $token_response->get_error_message());
            }
            
            $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
            
            if (!isset($token_data['access_token'])) {
                throw new Exception('No se pudo obtener el access token');
            }
            
            return $token_data['access_token'];
            
        } catch (Exception $e) {
            error_log('GICA FCM Token Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create JWT token for OAuth
     */
    private function create_jwt($header, $payload, $private_key) {
        $header_encoded = $this->base64url_encode(json_encode($header));
        $payload_encoded = $this->base64url_encode(json_encode($payload));
        
        $signature_input = $header_encoded . '.' . $payload_encoded;
        
        $signature = '';
        if (openssl_sign($signature_input, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
            $signature_encoded = $this->base64url_encode($signature);
            return $signature_input . '.' . $signature_encoded;
        }
        
        throw new Exception('Error al crear la firma JWT');
    }
    
    /**
     * Base64 URL encode
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Log notification sent
     */
    private function log_notification_sent($title, $body, $data) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'success' => true
        );
        
        // Get existing logs
        $logs = get_option('gica_fcm_notification_logs', array());
        if (!is_array($logs)) {
            $logs = array();
        }
        
        // Add new log entry
        array_unshift($logs, $log_entry);
        
        // Keep only last 20 entries
        $logs = array_slice($logs, 0, 20);
        
        // Save logs
        update_option('gica_fcm_notification_logs', $logs, false);
        
        // Debug log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GICA FCM: Notificación enviada - ' . $title);
        }
    }
    
    /**
     * Get notification logs
     */
    public function get_notification_logs() {
        return get_option('gica_fcm_notification_logs', array());
    }
    
    /**
     * Clear notification logs
     */
    public function clear_logs() {
        return delete_option('gica_fcm_notification_logs');
    }
    
    /**
     * Test FCM configuration
     */
    public function test_configuration() {
        try {
            if (!$this->is_enabled()) {
                return array('success' => false, 'message' => 'FCM no está configurado');
            }
            
            // Try to get access token
            $access_token = $this->get_access_token();
            if (!$access_token) {
                return array('success' => false, 'message' => 'No se pudo obtener el token de acceso');
            }
            
            return array('success' => true, 'message' => 'Configuración FCM válida');
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
}