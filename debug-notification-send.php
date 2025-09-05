<?php
/**
 * Debug específico para envío de notificaciones FCM
 * URL: /wp-content/plugins/GICAACCOUNT/debug-notification-send.php
 */

// Cargar WordPress
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    wp_die('No tienes permisos para acceder a esta página');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Debug Envío Notificaciones FCM</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        .section { margin: 30px 0; padding: 20px; border: 2px solid #dee2e6; border-radius: 8px; }
        .test-button { background: #007cba; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 10px 5px; }
        .test-button:hover { background: #005a87; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
    </style>
</head>
<body>
    <h1>🔍 Debug Envío Notificaciones FCM</h1>
    <p><strong>Diagnóstico completo del sistema de notificaciones</strong></p>

    <div class="section">
        <h2>📋 1. Configuración FCM WordPress</h2>
        <?php
        $config_issues = [];
        $config_ok = [];
        
        // Verificar configuración FCM
        $fcm_enabled = get_option('gica_fcm_enabled', false);
        $firebase_project_id = get_option('gica_firebase_project_id', '');
        $firebase_api_key = get_option('gica_firebase_api_key', '');
        $fcm_vapid_key = get_option('gica_fcm_vapid_key', '');
        $fcm_service_account = get_option('gica_fcm_service_account_json', '');
        
        if ($fcm_enabled) {
            $config_ok[] = '✅ FCM está habilitado';
        } else {
            $config_issues[] = '❌ FCM no está habilitado';
        }
        
        if (!empty($firebase_project_id)) {
            $config_ok[] = '✅ Firebase Project ID configurado: ' . $firebase_project_id;
        } else {
            $config_issues[] = '❌ Firebase Project ID faltante';
        }
        
        if (!empty($firebase_api_key)) {
            $config_ok[] = '✅ Firebase API Key configurada (longitud: ' . strlen($firebase_api_key) . ')';
        } else {
            $config_issues[] = '❌ Firebase API Key faltante';
        }
        
        if (!empty($fcm_vapid_key)) {
            $config_ok[] = '✅ FCM VAPID Key configurada (longitud: ' . strlen($fcm_vapid_key) . ')';
        } else {
            $config_issues[] = '❌ FCM VAPID Key faltante';
        }
        
        if (!empty($fcm_service_account)) {
            $service_account_data = json_decode($fcm_service_account, true);
            if ($service_account_data) {
                $config_ok[] = '✅ Service Account JSON válido';
                if (isset($service_account_data['project_id'])) {
                    $config_ok[] = '✅ Service Account Project ID: ' . $service_account_data['project_id'];
                }
            } else {
                $config_issues[] = '❌ Service Account JSON inválido';
            }
        } else {
            $config_issues[] = '❌ Service Account JSON faltante - ESTO ES CRÍTICO';
        }
        
        ?>
        
        <table>
            <tr><th>Configuración</th><th>Estado</th><th>Valor</th></tr>
            <tr><td>FCM Enabled</td><td><?php echo $fcm_enabled ? '✅' : '❌'; ?></td><td><?php echo $fcm_enabled ? 'true' : 'false'; ?></td></tr>
            <tr><td>Project ID</td><td><?php echo !empty($firebase_project_id) ? '✅' : '❌'; ?></td><td><?php echo esc_html($firebase_project_id); ?></td></tr>
            <tr><td>API Key</td><td><?php echo !empty($firebase_api_key) ? '✅' : '❌'; ?></td><td><?php echo !empty($firebase_api_key) ? substr($firebase_api_key, 0, 20) . '...' : 'No configurada'; ?></td></tr>
            <tr><td>VAPID Key</td><td><?php echo !empty($fcm_vapid_key) ? '✅' : '❌'; ?></td><td><?php echo !empty($fcm_vapid_key) ? substr($fcm_vapid_key, 0, 30) . '...' : 'No configurada'; ?></td></tr>
            <tr><td>Service Account</td><td><?php echo !empty($fcm_service_account) ? '✅' : '❌'; ?></td><td><?php echo !empty($fcm_service_account) ? 'Configurada (' . strlen($fcm_service_account) . ' chars)' : 'No configurada'; ?></td></tr>
        </table>
        
        <?php foreach ($config_ok as $msg): ?>
            <div style="color: green;">• <?php echo $msg; ?></div>
        <?php endforeach; ?>
        
        <?php foreach ($config_issues as $msg): ?>
            <div style="color: red;">• <?php echo $msg; ?></div>
        <?php endforeach; ?>
    </div>

    <div class="section">
        <h2>📱 2. Dispositivos Registrados</h2>
        <?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
        $devices = $wpdb->get_results("SELECT * FROM $table_name WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 10");
        
        if ($devices): ?>
            <table>
                <tr><th>Device ID</th><th>Device Info</th><th>Token Preview</th><th>Última Actualización</th><th>Acciones</th></tr>
                <?php foreach ($devices as $device): ?>
                    <tr>
                        <td><?php echo esc_html($device->device_id); ?></td>
                        <td><?php echo esc_html($device->device_info); ?></td>
                        <td><code><?php echo substr($device->fcm_token, 0, 30); ?>...</code></td>
                        <td><?php echo $device->updated_at; ?></td>
                        <td>
                            <button class="test-button" onclick="testSpecificDevice('<?php echo esc_js($device->device_id); ?>', '<?php echo esc_js($device->fcm_token); ?>')">
                                🧪 Test
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <div class="warning">⚠️ No hay dispositivos móviles registrados</div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>🧪 3. Test Detallado de Notificación</h2>
        <button class="test-button" onclick="runDetailedNotificationTest()">🚀 Ejecutar Test Completo</button>
        <div id="detailed-test-result"></div>
    </div>

    <div class="section">
        <h2>🛠️ 4. Generar Service Account JSON</h2>
        <div class="info">
            <p><strong>PROBLEMA IDENTIFICADO:</strong> Falta el Service Account JSON para autenticación con FCM HTTP v1 API.</p>
            <p><strong>Para obtenerlo:</strong></p>
            <ol>
                <li>Ve a <a href="https://console.firebase.google.com" target="_blank">Firebase Console</a></li>
                <li>Selecciona proyecto: <strong>gicaform-notifications</strong></li>
                <li>Ve a "Configuración del proyecto" (⚙️) → "Cuentas de servicio"</li>
                <li>Haz clic en "Generar nueva clave privada"</li>
                <li>Descarga el archivo JSON</li>
                <li>Copia todo el contenido del archivo JSON aquí:</li>
            </ol>
        </div>
        
        <textarea id="service-account-json" rows="10" style="width: 100%; font-family: monospace;" placeholder='Pega aquí el contenido del archivo JSON descargado de Firebase Console. Ejemplo:
{
  "type": "service_account",
  "project_id": "gicaform-notifications",
  "private_key_id": "...",
  "private_key": "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n",
  "client_email": "firebase-adminsdk-...@gicaform-notifications.iam.gserviceaccount.com",
  "client_id": "...",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://oauth2.googleapis.com/token"
}'></textarea>
        <br>
        <button class="test-button" onclick="saveServiceAccount()">💾 Guardar Service Account</button>
        <div id="service-account-result"></div>
    </div>

    <script>
        async function testSpecificDevice(deviceId, token) {
            const resultDiv = document.createElement('div');
            resultDiv.innerHTML = '<div class="info">🔄 Enviando notificación de prueba...</div>';
            event.target.parentNode.appendChild(resultDiv);
            
            try {
                // Configurar el token del dispositivo para testing
                const setTokenResponse = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'gica_set_test_device_token',
                        device_id: deviceId,
                        fcm_token: token,
                        nonce: '<?php echo wp_create_nonce('gica_test_device'); ?>'
                    })
                });
                
                const setTokenData = await setTokenResponse.json();
                console.log('Set token response:', setTokenData);
                
                if (!setTokenData.success) {
                    throw new Error('Error configurando token: ' + (setTokenData.data?.message || 'Unknown error'));
                }
                
                // Enviar notificación de prueba
                const testResponse = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'gica_send_test_notification',
                        nonce: '<?php echo wp_create_nonce('gica_firebase_nonce'); ?>'
                    })
                });
                
                const testData = await testResponse.json();
                console.log('Test notification response:', testData);
                
                if (testData.success) {
                    resultDiv.innerHTML = '<div class="success">✅ Notificación enviada exitosamente!</div>';
                } else {
                    resultDiv.innerHTML = '<div class="error">❌ Error: ' + (testData.data?.message || 'Error desconocido') + '</div>';
                }
                
            } catch (error) {
                console.error('Test error:', error);
                resultDiv.innerHTML = '<div class="error">❌ Error: ' + error.message + '</div>';
            }
        }
        
        async function runDetailedNotificationTest() {
            const resultDiv = document.getElementById('detailed-test-result');
            resultDiv.innerHTML = '<div class="info">🔄 Ejecutando test detallado...</div>';
            
            try {
                // Test directo del FCM service
                const response = await fetch('/wp-content/plugins/GICAACCOUNT/test-fcm-service.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=detailed_test'
                });
                
                const data = await response.text();
                resultDiv.innerHTML = '<div class="info"><h3>📊 Resultado del Test:</h3><pre>' + data + '</pre></div>';
                
            } catch (error) {
                resultDiv.innerHTML = '<div class="error">❌ Error en test: ' + error.message + '</div>';
            }
        }
        
        async function saveServiceAccount() {
            const jsonContent = document.getElementById('service-account-json').value.trim();
            const resultDiv = document.getElementById('service-account-result');
            
            if (!jsonContent) {
                resultDiv.innerHTML = '<div class="error">❌ Por favor pega el contenido del JSON</div>';
                return;
            }
            
            try {
                // Validar JSON
                const parsedJson = JSON.parse(jsonContent);
                if (!parsedJson.type || parsedJson.type !== 'service_account') {
                    throw new Error('No es un Service Account JSON válido');
                }
                
                resultDiv.innerHTML = '<div class="info">🔄 Guardando Service Account...</div>';
                
                // Guardar en WordPress
                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'gica_save_service_account',
                        service_account_json: jsonContent,
                        nonce: '<?php echo wp_create_nonce('gica_service_account'); ?>'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = '<div class="success">✅ Service Account guardado exitosamente! Ahora las notificaciones deberían funcionar.</div>';
                    setTimeout(() => location.reload(), 2000);
                } else {
                    resultDiv.innerHTML = '<div class="error">❌ Error guardando: ' + (data.data?.message || 'Error desconocido') + '</div>';
                }
                
            } catch (error) {
                resultDiv.innerHTML = '<div class="error">❌ JSON inválido: ' + error.message + '</div>';
            }
        }
    </script>
</body>
</html>