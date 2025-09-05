<?php
/**
 * Debug específico para error de notificación
 */

// Cargar WordPress
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

header('Content-Type: text/plain; charset=utf-8');

echo "🔍 DEBUG ESPECÍFICO - ERROR DE NOTIFICACIÓN\n";
echo "==========================================\n\n";

// Test 1: Verificar configuración básica
echo "1. 📋 CONFIGURACIÓN BÁSICA:\n";
echo "----------------------------\n";
$fcm_enabled = get_option('gica_fcm_enabled', false);
$firebase_project_id = get_option('gica_firebase_project_id', '');
$fcm_service_account = get_option('gica_fcm_service_account_json', '');

echo "✓ FCM Enabled: " . ($fcm_enabled ? 'SÍ' : 'NO') . "\n";
echo "✓ Project ID: " . $firebase_project_id . "\n";
echo "✓ Service Account: " . (strlen($fcm_service_account) > 0 ? 'PRESENTE (' . strlen($fcm_service_account) . ' chars)' : 'FALTANTE') . "\n";

if (!empty($fcm_service_account)) {
    $service_data = json_decode($fcm_service_account, true);
    if ($service_data) {
        echo "✓ JSON válido: SÍ\n";
        echo "✓ Client Email: " . ($service_data['client_email'] ?? 'NO DEFINIDO') . "\n";
    } else {
        echo "❌ JSON inválido\n";
    }
} else {
    echo "❌ Service Account faltante\n";
    exit("ERROR: No se puede continuar sin Service Account\n");
}

// Test 2: Verificar dispositivo específico
echo "\n2. 📱 DISPOSITIVO XIAOMI:\n";
echo "------------------------\n";
global $wpdb;
$table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
$xiaomi = $wpdb->get_row("SELECT * FROM $table_name WHERE device_id = 'f2953f4f7aca2de8'");

if ($xiaomi) {
    echo "✅ Dispositivo encontrado\n";
    echo "✓ Device ID: " . $xiaomi->device_id . "\n";
    echo "✓ Info: " . $xiaomi->device_info . "\n";
    echo "✓ Token: " . substr($xiaomi->fcm_token, 0, 50) . "...\n";
    echo "✓ Activo: " . ($xiaomi->is_active ? 'SÍ' : 'NO') . "\n";
    echo "✓ Última actualización: " . $xiaomi->updated_at . "\n";
    
    $token_to_test = $xiaomi->fcm_token;
} else {
    echo "❌ Dispositivo Xiaomi no encontrado\n";
    exit("ERROR: No se puede continuar sin dispositivo\n");
}

// Test 3: Cargar clase FCM
echo "\n3. 🔧 CARGAR CLASE FCM:\n";
echo "----------------------\n";
$fcm_file = plugin_dir_path(__FILE__) . 'includes/class-fcm-service.php';
echo "✓ Archivo FCM: " . ($fcm_file) . "\n";
echo "✓ Existe: " . (file_exists($fcm_file) ? 'SÍ' : 'NO') . "\n";

if (!file_exists($fcm_file)) {
    exit("ERROR: Archivo class-fcm-service.php no existe\n");
}

require_once $fcm_file;
echo "✓ Archivo cargado: SÍ\n";
echo "✓ Clase existe: " . (class_exists('GICA_FCM_Service') ? 'SÍ' : 'NO') . "\n";

if (!class_exists('GICA_FCM_Service')) {
    exit("ERROR: Clase GICA_FCM_Service no existe\n");
}

// Test 4: Instanciar servicio FCM
echo "\n4. 🚀 INSTANCIAR FCM SERVICE:\n";
echo "----------------------------\n";
try {
    $fcm_service = new GICA_FCM_Service();
    echo "✅ Instancia creada exitosamente\n";
    
    if (method_exists($fcm_service, 'is_configured')) {
        $is_configured = $fcm_service->is_configured();
        echo "✓ Está configurado: " . ($is_configured ? 'SÍ' : 'NO') . "\n";
        
        if (!$is_configured) {
            echo "❌ FCM Service no está configurado correctamente\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error instanciando: " . $e->getMessage() . "\n";
    exit("ERROR: No se puede instanciar FCM Service\n");
}

// Test 5: Probar configuración
echo "\n5. 🧪 TEST CONFIGURACIÓN FCM:\n";
echo "-----------------------------\n";
try {
    if (method_exists($fcm_service, 'test_configuration')) {
        $config_test = $fcm_service->test_configuration();
        
        if ($config_test['success']) {
            echo "✅ Test configuración: EXITOSO\n";
            echo "✓ Project ID: " . ($config_test['project_id'] ?? 'NO DEFINIDO') . "\n";
            echo "✓ Access Token: " . ($config_test['has_access_token'] ? 'SÍ' : 'NO') . "\n";
        } else {
            echo "❌ Test configuración: FALLÓ\n";
            echo "Error: " . ($config_test['error'] ?? 'Error desconocido') . "\n";
        }
    } else {
        echo "⚠️ Método test_configuration no existe\n";
    }
} catch (Exception $e) {
    echo "❌ Error en test configuración: " . $e->getMessage() . "\n";
}

// Test 6: Envío de notificación con debugging detallado
echo "\n6. 📤 TEST ENVÍO NOTIFICACIÓN:\n";
echo "------------------------------\n";
try {
    // Set token temporalmente
    update_option('gica_fcm_device_token', $token_to_test);
    
    echo "🔄 Enviando notificación de prueba...\n";
    
    $result = $fcm_service->send_notification(
        'Test Debug FCM',
        'Notificación de debug detallado. Timestamp: ' . date('H:i:s'),
        ['debug' => true, 'timestamp' => time()]
    );
    
    echo "\n📊 RESULTADO DETALLADO:\n";
    echo "Success: " . ($result['success'] ? 'SÍ' : 'NO') . "\n";
    
    if ($result['success']) {
        echo "✅ NOTIFICACIÓN ENVIADA EXITOSAMENTE!\n";
        echo "Respuesta FCM:\n";
        print_r($result['response'] ?? 'No response data');
    } else {
        echo "❌ ERROR ENVIANDO NOTIFICACIÓN:\n";
        echo "Error: " . ($result['error'] ?? 'Error desconocido') . "\n";
        
        if (isset($result['response_code'])) {
            echo "HTTP Code: " . $result['response_code'] . "\n";
        }
        
        if (isset($result['response'])) {
            echo "Respuesta FCM:\n";
            print_r($result['response']);
        }
    }
    
} catch (Exception $e) {
    echo "❌ EXCEPCIÓN EN ENVÍO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n🎯 DIAGNÓSTICO COMPLETADO\n";
echo "========================\n";

// Test 7: Logs de notificación
echo "\n7. 📝 LOGS DE NOTIFICACIÓN:\n";
echo "---------------------------\n";
if (method_exists($fcm_service, 'get_notification_logs')) {
    $logs = $fcm_service->get_notification_logs();
    if (!empty($logs)) {
        echo "✓ Logs encontrados: " . count($logs) . "\n";
        echo "Último log:\n";
        $last_log = $logs[0] ?? null;
        if ($last_log) {
            echo "  - Timestamp: " . ($last_log['timestamp'] ?? 'N/A') . "\n";
            echo "  - Title: " . ($last_log['title'] ?? 'N/A') . "\n";
            echo "  - Success: " . (isset($last_log['success']) ? ($last_log['success'] ? 'SÍ' : 'NO') : 'N/A') . "\n";
            if (isset($last_log['response'])) {
                echo "  - Response: " . json_encode($last_log['response']) . "\n";
            }
        }
    } else {
        echo "⚠️ No hay logs de notificación\n";
    }
} else {
    echo "⚠️ Método get_notification_logs no existe\n";
}

echo "\n✅ DEBUG TERMINADO\n";
?>