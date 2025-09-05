<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GICA Configuration Manager
 * Gestiona configuraciones dinámicas para diferentes ambientes
 */
class GicaConfigManager {
    
    const OPTION_PREFIX = 'gica_config_';
    const DEFAULT_API_KEY = 'gica_mobile_2024';
    
    /**
     * Configuraciones por defecto para diferentes ambientes
     */
    private static $default_configs = [
        'development' => [
            'api_key' => 'gica_mobile_2024',
            'cors_origins' => ['*'],
            'rate_limit' => 1000, // requests per hour
            'debug_mode' => true,
            'log_requests' => true
        ],
        'staging' => [
            'api_key' => 'gica_staging_2024',
            'cors_origins' => ['https://staging.gica.com', 'http://localhost:3000'],
            'rate_limit' => 500,
            'debug_mode' => true,
            'log_requests' => true
        ],
        'production' => [
            'api_key' => 'gica_prod_2024',
            'cors_origins' => ['https://gica.com', 'https://app.gica.com'],
            'rate_limit' => 200,
            'debug_mode' => false,
            'log_requests' => false
        ]
    ];
    
    /**
     * Obtener configuración actual
     */
    public static function get_config($key = null) {
        $current_env = self::get_environment();
        $config = get_option(self::OPTION_PREFIX . $current_env, self::$default_configs[$current_env] ?? self::$default_configs['development']);
        
        if ($key) {
            return $config[$key] ?? null;
        }
        
        return $config;
    }
    
    /**
     * Actualizar configuración
     */
    public static function update_config($key, $value) {
        $current_env = self::get_environment();
        $config = self::get_config();
        $config[$key] = $value;
        
        return update_option(self::OPTION_PREFIX . $current_env, $config);
    }
    
    /**
     * Detectar ambiente actual
     */
    public static function get_environment() {
        // Prioridad 1: Variable de entorno
        $env = getenv('GICA_ENVIRONMENT');
        if ($env) {
            return $env;
        }
        
        // Prioridad 2: Constante PHP
        if (defined('GICA_ENVIRONMENT')) {
            return GICA_ENVIRONMENT;
        }
        
        // Prioridad 3: Configuración de WordPress
        $env = get_option('gica_environment');
        if ($env) {
            return $env;
        }
        
        // Prioridad 4: Detectar por dominio
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        if (strpos($host, 'localhost') !== false || strpos($host, '.local') !== false) {
            return 'development';
        }
        
        if (strpos($host, 'staging') !== false || strpos($host, 'test') !== false) {
            return 'staging';
        }
        
        return 'production';
    }
    
    /**
     * Validar API key
     */
    public static function validate_api_key($provided_key) {
        $valid_keys = [
            self::get_config('api_key'),
            self::DEFAULT_API_KEY // Fallback para compatibilidad
        ];
        
        return in_array($provided_key, array_filter($valid_keys));
    }
    
    /**
     * Verificar rate limit
     */
    public static function check_rate_limit($device_id) {
        $limit = self::get_config('rate_limit');
        if (!$limit || !self::get_config('debug_mode')) {
            return true; // Sin límite en producción por ahora
        }
        
        $cache_key = 'gica_rate_limit_' . md5($device_id);
        $requests = get_transient($cache_key);
        
        if ($requests === false) {
            set_transient($cache_key, 1, HOUR_IN_SECONDS);
            return true;
        }
        
        if ($requests >= $limit) {
            return false;
        }
        
        set_transient($cache_key, $requests + 1, HOUR_IN_SECONDS);
        return true;
    }
    
    /**
     * Log de request si está habilitado
     */
    public static function log_request($data) {
        if (!self::get_config('log_requests')) {
            return;
        }
        
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'environment' => self::get_environment(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'data' => $data
        ];
        
        error_log('GICA Request: ' . json_encode($log_entry));
    }
    
    /**
     * Obtener configuración para respuesta de la API
     */
    public static function get_api_info() {
        return [
            'environment' => self::get_environment(),
            'version' => '1.0.0',
            'api_key_valid' => true,
            'rate_limit' => self::get_config('rate_limit'),
            'debug_mode' => self::get_config('debug_mode'),
            'server_time' => current_time('mysql')
        ];
    }
    
    /**
     * Inicializar configuraciones por defecto
     */
    public static function init_default_configs() {
        foreach (self::$default_configs as $env => $config) {
            $existing = get_option(self::OPTION_PREFIX . $env);
            if (!$existing) {
                update_option(self::OPTION_PREFIX . $env, $config);
            }
        }
    }
    
    /**
     * Verificar si CORS está permitido
     */
    public static function is_cors_allowed($origin) {
        $allowed_origins = self::get_config('cors_origins');
        
        if (in_array('*', $allowed_origins)) {
            return true;
        }
        
        return in_array($origin, $allowed_origins);
    }
    
    /**
     * Generar headers CORS apropiados
     */
    public static function set_cors_headers() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (self::is_cors_allowed($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } else {
            header('Access-Control-Allow-Origin: *'); // Fallback
        }
        
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');
    }
}