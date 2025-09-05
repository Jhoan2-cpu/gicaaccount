<?php
/**
 * Test directo del servicio FCM para debugging
 */

// Cargar WordPress sin themes
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

// Headers para JSON
header('Content-Type: text/plain; charset=utf-8');

echo "🔍 TEST DETALLADO SERVICIO FCM\n";
echo "============================\n\n";

// 1. Verificar configuración
echo "1. 📋 VERIFICAR CONFIGURACIÓN:\n";
echo "------------------------------\n";

$fcm_enabled = get_option('gica_fcm_enabled', false);
$firebase_project_id = get_option('gica_firebase_project_id', '');
$firebase_api_key = get_option('gica_firebase_api_key', '');
$fcm_vapid_key = get_option('gica_fcm_vapid_key', '');
$fcm_service_account = get_option('gica_fcm_service_account_json', '');

echo "✓ FCM Enabled: " . ($fcm_enabled ? 'SÍ' : 'NO') . "\n";
echo "✓ Project ID: " . (!empty($firebase_project_id) ? $firebase_project_id : 'NO CONFIGURADO') . "\n";
echo "✓ API Key: " . (!empty($firebase_api_key) ? 'CONFIGURADA (' . strlen($firebase_api_key) . ' chars)' : 'NO CONFIGURADA') . "\n";
echo "✓ VAPID Key: " . (!empty($fcm_vapid_key) ? 'CONFIGURADA (' . strlen($fcm_vapid_key) . ' chars)' : 'NO CONFIGURADA') . "\n";
echo "✓ Service Account: " . (!empty($fcm_service_account) ? 'CONFIGURADA (' . strlen($fcm_service_account) . ' chars)' : 'NO CONFIGURADA') . "\n";

// 2. Análisis del Service Account
echo "\n2. 🔑 ANÁLISIS SERVICE ACCOUNT:\n";
echo "------------------------------\n";

if (!empty($fcm_service_account)) {
    $service_account_data = json_decode($fcm_service_account, true);
    if ($service_account_data) {
        echo "✅ JSON válido\n";
        echo "✓ Type: " . ($service_account_data['type'] ?? 'NO DEFINIDO') . "\n";
        echo "✓ Project ID: " . ($service_account_data['project_id'] ?? 'NO DEFINIDO') . "\n";
        echo "✓ Client Email: " . ($service_account_data['client_email'] ?? 'NO DEFINIDO') . "\n";
        echo "✓ Private Key: " . (isset($service_account_data['private_key']) ? 'PRESENTE' : 'FALTANTE') . "\n";
        
        // Verificar si coincide con project_id
        if (isset($service_account_data['project_id']) && $service_account_data['project_id'] !== $firebase_project_id) {
            echo "⚠️ ADVERTENCIA: Project ID del Service Account (" . $service_account_data['project_id'] . ") no coincide con el configurado (" . $firebase_project_id . ")\n";
        }
    } else {
        echo "❌ JSON inválido o corrupto\n";
        echo "Error: " . json_last_error_msg() . "\n";
    }
} else {
    echo "❌ NO CONFIGURADO - ESTE ES EL PROBLEMA PRINCIPAL\n";
    echo "\nPARA SOLUCIONARLO:\n";
    echo "1. Ve a https://console.firebase.google.com\n";
    echo "2. Selecciona proyecto: " . $firebase_project_id . "\n";
    echo "3. Ve a Configuración → Cuentas de servicio\n";
    echo "4. Descarga nueva clave privada (JSON)\n";
    echo "5. Copia el contenido en la página de debug\n";
}

// 3. Verificar dispositivos registrados
echo "\n3. 📱 DISPOSITIVOS REGISTRADOS:\n";
echo "------------------------------\n";

global $wpdb;
$table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
$devices = $wpdb->get_results("SELECT * FROM $table_name WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 5");

if ($devices) {
    echo "✅ " . count($devices) . " dispositivo(s) registrado(s):\n";
    foreach ($devices as $device) {
        echo "  • Device ID: " . $device->device_id . "\n";
        echo "    Info: " . $device->device_info . "\n";
        echo "    Token: " . substr($device->fcm_token, 0, 30) . "...\n";
        echo "    Actualizado: " . $device->updated_at . "\n\n";
    }
} else {
    echo "❌ No hay dispositivos registrados\n";
}

// 4. Test de clase FCM
echo "\n4. 🧪 TEST CLASE FCM:\n";
echo "-------------------\n";

// Verificar si existe la clase
$fcm_file = plugin_dir_path(__FILE__) . 'includes/class-fcm-service.php';
if (file_exists($fcm_file)) {
    echo "✅ Archivo class-fcm-service.php existe\n";
    
    require_once $fcm_file;
    
    if (class_exists('GICA_FCM_Service')) {
        echo "✅ Clase GICA_FCM_Service existe\n";
        
        try {
            $fcm_service = new GICA_FCM_Service();
            echo "✅ Instancia de FCM Service creada\n";
            
            // Test método de configuración si existe
            if (method_exists($fcm_service, 'is_configured')) {
                $is_configured = $fcm_service->is_configured();
                echo "✓ FCM configurado: " . ($is_configured ? 'SÍ' : 'NO') . "\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Error creando instancia: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ Clase GICA_FCM_Service no existe\n";
    }
} else {
    echo "❌ Archivo class-fcm-service.php no existe\n";
}

// 5. Test de conectividad FCM
echo "\n5. 🌐 TEST CONECTIVIDAD FCM:\n";
echo "---------------------------\n";

if (!empty($fcm_service_account) && !empty($firebase_project_id)) {
    echo "🔄 Intentando conectar con FCM API...\n";
    
    // Test básico de conectividad
    $test_url = "https://fcm.googleapis.com/v1/projects/" . $firebase_project_id . "/messages:send";
    
    echo "URL destino: " . $test_url . "\n";
    
    // Verificar curl
    if (function_exists('curl_init')) {
        echo "✅ cURL disponible\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "✓ Conectividad FCM: HTTP " . $http_code . "\n";
        
        if ($http_code == 404 || $http_code == 200) {
            echo "✅ Conexión a FCM exitosa\n";
        } else {
            echo "⚠️ Respuesta inesperada de FCM\n";
        }
        
    } else {
        echo "❌ cURL no disponible\n";
    }
} else {
    echo "❌ No se puede testear - falta configuración\n";
}

// 6. Resultado y recomendaciones
echo "\n6. 🎯 DIAGNÓSTICO FINAL:\n";
echo "----------------------\n";

$issues = [];
$critical_issues = [];

if (!$fcm_enabled) {
    $issues[] = "FCM no está habilitado";
}

if (empty($firebase_project_id)) {
    $critical_issues[] = "Project ID faltante";
}

if (empty($firebase_api_key)) {
    $issues[] = "API Key faltante";
}

if (empty($fcm_service_account)) {
    $critical_issues[] = "Service Account JSON faltante - CRÍTICO PARA FCM HTTP v1";
}

if (!empty($devices)) {
    echo "✅ Hay dispositivos para testear\n";
} else {
    $issues[] = "No hay dispositivos registrados";
}

if (empty($critical_issues) && empty($issues)) {
    echo "✅ CONFIGURACIÓN COMPLETA - Las notificaciones deberían funcionar\n";
} else {
    echo "❌ PROBLEMAS ENCONTRADOS:\n";
    
    foreach ($critical_issues as $issue) {
        echo "  🔴 CRÍTICO: " . $issue . "\n";
    }
    
    foreach ($issues as $issue) {
        echo "  🟡 ADVERTENCIA: " . $issue . "\n";
    }
}

echo "\n🔧 PRÓXIMOS PASOS:\n";
echo "-----------------\n";

if (!empty($critical_issues)) {
    echo "1. 🚨 URGENTE: Configura el Service Account JSON\n";
    echo "   - Ve a Firebase Console → Configuración → Cuentas de servicio\n";
    echo "   - Descarga nueva clave privada\n";
    echo "   - Úsala en la página de debug\n\n";
}

if (!empty($issues)) {
    echo "2. ⚙️ Corrige las advertencias mencionadas\n\n";
}

echo "3. 🧪 Ejecuta test de notificación desde la página de debug\n";
echo "4. 📱 Verifica que tu app Android reciba la notificación\n";

echo "\n✅ DIAGNÓSTICO COMPLETADO\n";
?>