<?php
/**
 * Configuration API Endpoint
 * Permite a la app Android consultar la configuración del servidor
 */

// Headers básicos
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

try {
    // Cargar sistema de configuración
    require_once(ABSPATH . 'wp-content/plugins/GICAACCOUNT/includes/classes/class-gica-config-manager.php');

    // Configurar CORS
    GicaConfigManager::set_cors_headers();

    // Verificar API key opcional (para info pública vs privada)
    $api_key = $_GET['api_key'] ?? '';
    $is_authenticated = GicaConfigManager::validate_api_key($api_key);

    // Información pública (sin API key)
    $public_info = [
        'success' => true,
        'server_status' => 'online',
        'environment' => GicaConfigManager::get_environment(),
        'version' => '1.0.0',
        'endpoints' => [
            'fcm_register' => '/wp-content/plugins/GICAACCOUNT/mobile-api-public.php',
            'config' => '/wp-content/plugins/GICAACCOUNT/config-api.php'
        ],
        'requirements' => [
            'api_key' => 'required',
            'http_auth' => 'required',
            'content_type' => 'application/x-www-form-urlencoded or application/json'
        ],
        'rate_limits' => [
            'requests_per_hour' => GicaConfigManager::get_config('rate_limit')
        ]
    ];

    // Información privada (con API key válida)
    if ($is_authenticated) {
        $private_info = [
            'config' => GicaConfigManager::get_config(),
            'debug_info' => [
                'server_time' => current_time('mysql'),
                'wp_version' => get_bloginfo('version'),
                'plugin_active' => is_plugin_active('GICAACCOUNT/gica-account.php'),
                'database_tables' => [
                    'fcm_tokens' => $GLOBALS['wpdb']->get_var("SHOW TABLES LIKE '{$GLOBALS['wpdb']->prefix}gica_mobile_fcm_tokens'") !== null
                ]
            ]
        ];
        
        $response = array_merge($public_info, $private_info);
    } else {
        $response = $public_info;
    }

    // Respuesta
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
?>