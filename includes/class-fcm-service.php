<?php
/**
 * Firebase Cloud Messaging (FCM) Service Class
 * Handles FCM HTTP v1 API integration with Service Account authentication
 */

if (!defined('ABSPATH')) {
    exit;
}

class GICA_FCM_Service {
    
    private $service_account_data;
    private $project_id;
    private $access_token;
    private $access_token_expires;
    
    public function __construct() {
        $this->load_configuration();
    }
    
    /**
     * Load FCM configuration from WordPress options
     */
    private function load_configuration() {
        $service_account_json = get_option('gica_fcm_service_account_json', '');
        
        if (!empty($service_account_json)) {
            $this->service_account_data = json_decode($service_account_json, true);
            if ($this->service_account_data && isset($this->service_account_data['project_id'])) {
                $this->project_id = $this->service_account_data['project_id'];
            }
        }
    }
    
    /**
     * Check if FCM is properly configured
     */
    public function is_configured() {
        return !empty($this->service_account_data) && 
               !empty($this->project_id) && 
               get_option('gica_fcm_enabled', false);
    }
    
    /**
     * Get OAuth2 access token for FCM HTTP v1 API
     */
    private function get_access_token() {
        // Check if we have a valid cached token
        if ($this->access_token && $this->access_token_expires > time()) {
            return $this->access_token;
        }
        
        if (empty($this->service_account_data)) {
            throw new Exception('Service Account not configured');
        }
        
        // Create JWT
        $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
        $payload = json_encode([
            'iss' => $this->service_account_data['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => time(),
            'exp' => time() + 3600
        ]);
        
        $base64_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64_payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature_input = $base64_header . '.' . $base64_payload;
        
        // Sign with private key
        $private_key = $this->service_account_data['private_key'];
        openssl_sign($signature_input, $signature, $private_key, OPENSSL_ALGO_SHA256);
        $base64_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        $jwt = $signature_input . '.' . $base64_signature;
        
        // Exchange JWT for access token
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Failed to get access token: ' . $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['access_token'])) {
            throw new Exception('Invalid access token response: ' . json_encode($body));
        }
        
        $this->access_token = $body['access_token'];
        $this->access_token_expires = time() + ($body['expires_in'] ?? 3600) - 60; // 1 minute buffer
        
        return $this->access_token;
    }
    
    /**
     * Send notification to a specific device
     */
    public function send_notification($title, $body, $data = [], $token = null) {
        try {
            if (!$this->is_configured()) {
                return [
                    'success' => false,
                    'error' => 'FCM not configured properly'
                ];
            }
            
            // Get target token
            if (empty($token)) {
                $token = get_option('gica_fcm_device_token', '');
            }
            
            if (empty($token)) {
                return [
                    'success' => false,
                    'error' => 'No FCM token available'
                ];
            }
            
            // Get access token
            $access_token = $this->get_access_token();
            
            // Prepare message - FCM HTTP v1 requires all data values to be strings
            $formatted_data = [];
            foreach (array_merge($data, ['click_action' => 'FLUTTER_NOTIFICATION_CLICK']) as $key => $value) {
                $formatted_data[$key] = (string) $value; // Convert all values to strings
            }
            
            $message = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body
                    ],
                    'data' => $formatted_data,
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'sound' => 'default',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                        ]
                    ]
                ]
            ];
            
            // Send to FCM
            $url = "https://fcm.googleapis.com/v1/projects/{$this->project_id}/messages:send";
            
            $response = wp_remote_post($url, [
                'body' => json_encode($message),
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                throw new Exception('HTTP request failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            
            // Log the notification
            $this->log_notification($title, $body, $data, $response_code == 200, $response_data);
            
            if ($response_code == 200) {
                return [
                    'success' => true,
                    'response' => $response_data
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'FCM API error: ' . $response_body,
                    'response_code' => $response_code,
                    'response' => $response_data
                ];
            }
            
        } catch (Exception $e) {
            error_log('GICA FCM Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send notification to all registered devices
     */
    public function send_notification_to_all($title, $body, $data = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
        $devices = $wpdb->get_results("SELECT fcm_token FROM $table_name WHERE is_active = 1");
        
        $results = [];
        $success_count = 0;
        $error_count = 0;
        
        foreach ($devices as $device) {
            $result = $this->send_notification($title, $body, $data, $device->fcm_token);
            $results[] = $result;
            
            if ($result['success']) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
        
        return [
            'success' => $success_count > 0,
            'total_devices' => count($devices),
            'success_count' => $success_count,
            'error_count' => $error_count,
            'results' => $results
        ];
    }
    
    /**
     * Log notification for debugging
     */
    private function log_notification($title, $body, $data, $success, $response) {
        $logs = get_option('gica_fcm_notification_logs', []);
        
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'success' => $success,
            'response' => $response
        ];
        
        // Keep only last 50 logs
        array_unshift($logs, $log_entry);
        $logs = array_slice($logs, 0, 50);
        
        update_option('gica_fcm_notification_logs', $logs);
    }
    
    /**
     * Get notification logs
     */
    public function get_notification_logs() {
        return get_option('gica_fcm_notification_logs', []);
    }
    
    /**
     * Test FCM configuration
     */
    public function test_configuration() {
        try {
            if (!$this->is_configured()) {
                return [
                    'success' => false,
                    'error' => 'FCM not configured'
                ];
            }
            
            // Try to get access token
            $access_token = $this->get_access_token();
            
            return [
                'success' => true,
                'message' => 'FCM configuration is valid',
                'project_id' => $this->project_id,
                'has_access_token' => !empty($access_token)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
?>