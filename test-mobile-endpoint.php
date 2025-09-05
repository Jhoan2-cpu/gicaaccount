<?php
/**
 * Test script para verificar el endpoint móvil
 * Usar desde línea de comandos o navegador
 */

// Cargar WordPress
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

// Token real desde fcm.txt
$real_token = 'f0-du7sKSo-LyfDM3Ls-YY:APA91bHv1O60-v0mtM9yTEi12CyDQKrjmCgLLe7n4uuLsYGRgfeWGsPId6zFQ6put_W9VdZYsubLeJ_3jQ0iMS_c2ytt5gjhfiM61TBup6Neyb3Dmahk_Ok';

// Simular POST request como lo hace la app Android
$_POST = array(
    'action' => 'gica_mobile_register_token',
    'api_key' => 'gica_mobile_2024',
    'fcm_token' => $real_token,
    'device_id' => 'TEST_ANDROID_' . time(),
    'app_version' => '1.0.0',
    'device_info' => 'Test Android Device',
    'user_id' => '1'
);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

echo "<h2>🧪 Test Endpoint Mobile Register Token</h2>\n";
echo "<pre>\n";
echo "POST Data siendo enviado:\n";
print_r($_POST);
echo "\n";

// Ejecutar directamente la función
try {
    $ajax_handler = new GicaAjaxHandler();
    
    echo "Ejecutando mobile_register_token()...\n\n";
    
    // Capturar output
    ob_start();
    $ajax_handler->mobile_register_token();
    $output = ob_get_clean();
    
    echo "Output capturado:\n";
    echo $output;
    echo "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>\n";

// También verificar que la tabla se haya creado
global $wpdb;
$table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

if ($table_exists) {
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    echo "<p><strong>✅ Tabla creada:</strong> $table_name ($count registros)</p>\n";
    
    $devices = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 5");
    if ($devices) {
        echo "<h3>📱 Últimos dispositivos registrados:</h3>\n";
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Device ID</th><th>Token (preview)</th><th>Version</th><th>Creado</th></tr>\n";
        foreach ($devices as $device) {
            echo "<tr>";
            echo "<td>" . esc_html($device->device_id) . "</td>";
            echo "<td>" . esc_html(substr($device->fcm_token, 0, 30)) . "...</td>";
            echo "<td>" . esc_html($device->app_version) . "</td>";
            echo "<td>" . esc_html($device->created_at) . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
} else {
    echo "<p><strong>⚠️ Tabla no existe:</strong> $table_name</p>\n";
}
?>