<?php
/**
 * Plugin Name: GICA Account
 * Description: Plugin para administrar cuentas de usuario con shortcode personalizable
 * Version: 2.0.0
 * Author: Tu Nombre
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GICA_ACCOUNT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GICA_ACCOUNT_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Autoloader para las clases
class GicaAutoloader {
    
    public static function register() {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }
    
    public static function autoload($class_name) {
        if (strpos($class_name, 'Gica') !== 0) {
            return;
        }
        
        // Convert CamelCase to kebab-case
        $class_file = strtolower(preg_replace('/([A-Z])/', '-$1', $class_name));
        $class_file = ltrim($class_file, '-'); // Remove leading dash
        $class_file = 'class-' . $class_file . '.php';
        
        $paths = array(
            GICA_ACCOUNT_PLUGIN_PATH . 'includes/classes/',
            GICA_ACCOUNT_PLUGIN_PATH . 'includes/models/',
            GICA_ACCOUNT_PLUGIN_PATH . 'includes/controllers/'
        );
        
        foreach ($paths as $path) {
            $file = $path . $class_file;
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
        
        // Debug: If file not found, try manual includes as fallback
        $manual_includes = array(
            'GicaAdminManager' => 'includes/classes/class-gica-admin-manager.php',
            'GicaShortcodeHandler' => 'includes/classes/class-gica-shortcode-handler.php',
            'GicaConfigManager' => 'includes/classes/class-gica-config-manager.php',
            'GicaAjaxHandler' => 'includes/classes/class-gica-ajax-handler.php',
            'GicaApiController' => 'includes/classes/class-gica-api-controller.php',
            'GicaFCMService' => 'includes/classes/class-gica-fcm-service.php',
            'GicaUser' => 'includes/models/class-gica-user.php',
            'GicaAdminController' => 'includes/controllers/class-gica-admin-controller.php'
        );
        
        if (isset($manual_includes[$class_name])) {
            $file = GICA_ACCOUNT_PLUGIN_PATH . $manual_includes[$class_name];
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
}

// Registrar el autoloader
GicaAutoloader::register();

class GicaAccount {
    private $admin_manager;
    private $shortcode_handler;
    private $ajax_handler;
    private $api_controller;
    private $fcm_service;
    
    public function __construct() {
        // Initialize components early to register AJAX hooks
        $this->init_components();
        $this->register_hooks();
    }
    
    private function init_components() {
        $this->admin_manager = new GicaAdminManager();
        $this->shortcode_handler = new GicaShortcodeHandler();
        $this->ajax_handler = new GicaAjaxHandler();
        $this->api_controller = new GicaApiController();
        $this->fcm_service = new GicaFCMService();
    }
    
    private function register_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this->shortcode_handler, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this->admin_manager, 'enqueue_admin_scripts'));
        add_action('admin_menu', array($this->admin_manager, 'add_admin_menu'));
        add_action('wp_ajax_gica_account_action', array($this->ajax_handler, 'handle_ajax'));
        add_action('wp_ajax_nopriv_gica_account_action', array($this->ajax_handler, 'handle_ajax'));
        
        // FCM notification hooks
        add_action('user_register', array($this, 'send_fcm_user_registration_notification'));
        
        // Activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        add_shortcode('gica_account', array($this->shortcode_handler, 'render_shortcode'));
    }
    
    /**
     * Send FCM notification when a new user registers
     */
    public function send_fcm_user_registration_notification($user_id) {
        try {
            if (!$this->fcm_service->is_enabled()) {
                return;
            }
            
            $user = get_userdata($user_id);
            if (!$user) {
                return;
            }
            
            $user_data = array(
                'ID' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name
            );
            
            $this->fcm_service->send_user_registration_notification($user_data);
            
        } catch (Exception $e) {
            error_log('GICA FCM Registration Notification Error: ' . $e->getMessage());
        }
    }
    
    public function activate() {
        // Crear tablas o configuraciones necesarias al activar el plugin
        $this->create_user_meta_indexes();
    }
    
    public function deactivate() {
        // Limpiar tareas programadas o configuraciones temporales
    }
    
    private function create_user_meta_indexes() {
        global $wpdb;
        
        // Crear índices para mejorar la búsqueda en user meta
        $meta_keys = array('phone', 'dni', 'city', 'country', 'region');
        
        foreach ($meta_keys as $key) {
            $wpdb->query($wpdb->prepare("
                ALTER IGNORE TABLE {$wpdb->usermeta} 
                ADD INDEX gica_{$key}_idx (meta_key, meta_value(50))
            "));
        }
    }
}

new GicaAccount();