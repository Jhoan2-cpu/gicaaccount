<?php
/**
 * Test endpoint directly
 */

// Simular la petición POST
$_POST = array(
    'action' => 'gica_mobile_register_token',
    'api_key' => 'gica_mobile_2024',
    'fcm_token' => 'test_token_from_direct_test',
    'device_id' => 'test_device_direct',
    'device_info' => 'Test Device Direct',
    'app_version' => '1.0',
    'user_id' => ''
);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_USER_AGENT'] = 'Test Direct Call';

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
    die('WordPress not found');
}

echo "<h1>Test Endpoint Direct</h1>\n";

// Check if handler class exists
if (!class_exists('GICA_AJAX_Handler')) {
    echo "<p style='color: red;'>ERROR: GICA_AJAX_Handler class not found</p>\n";
    exit;
}

echo "<p>✅ GICA_AJAX_Handler class found</p>\n";

// Create handler instance
$handler = new GICA_AJAX_Handler();

echo "<p>✅ Handler instance created</p>\n";

// Check if method exists
if (!method_exists($handler, 'mobile_register_token')) {
    echo "<p style='color: red;'>ERROR: mobile_register_token method not found</p>\n";
    exit;
}

echo "<p>✅ mobile_register_token method found</p>\n";

// Test API key option
$api_key_option = get_option('gica_mobile_api_key', 'gica_mobile_2024');
echo "<p>📝 API Key from option: <code>$api_key_option</code></p>\n";

// Execute the handler
echo "<h2>Executing handler...</h2>\n";
ob_start();

try {
    $handler->mobile_register_token();
    echo "<p>✅ Handler executed without throwing exception</p>\n";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>\n";
}

$output = ob_get_clean();
if (!empty($output)) {
    echo "<h3>Handler Output:</h3>\n";
    echo "<pre style='background: #f0f0f0; padding: 10px;'>$output</pre>\n";
}

// Check database
global $wpdb;
$table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
$devices = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 5");

echo "<h3>Database Check:</h3>\n";
echo "<p>Table: $table_name</p>\n";
echo "<p>Recent devices:</p>\n";
if ($devices) {
    echo "<ul>\n";
    foreach ($devices as $device) {
        echo "<li>Device ID: {$device->device_id}, Token: " . substr($device->fcm_token, 0, 30) . "..., Created: {$device->created_at}</li>\n";
    }
    echo "</ul>\n";
} else {
    echo "<p style='color: orange;'>No devices found</p>\n";
}

?>