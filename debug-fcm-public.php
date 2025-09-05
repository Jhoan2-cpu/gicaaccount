<?php
/**
 * Debug público FCM - No requiere autenticación
 */

// Cargar WordPress sin themes
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

// Headers para evitar caché
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Debug FCM Público</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        .section { margin: 30px 0; padding: 20px; border: 2px solid #dee2e6; border-radius: 8px; }
        .btn { background: #007cba; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 10px 5px; }
        .btn:hover { background: #005a87; }
        .btn-success { background: #28a745; } .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; } .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        textarea { width: 100%; height: 200px; font-family: monospace; font-size: 12px; border: 1px solid #ddd; border-radius: 4px; padding: 10px; }
        .status-indicator { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 8px; }
        .status-ok { background: #28a745; }
        .status-error { background: #dc3545; }
        .status-warning { background: #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug FCM - Diagnóstico Público</h1>
        <p><strong>Diagnóstico completo del sistema de notificaciones FCM</strong></p>

        <div class="section">
            <h2>📊 Estado General del Sistema</h2>
            <?php
            $config_issues = [];
            $config_ok = [];
            $critical_issues = [];
            
            // Verificar configuración FCM
            $fcm_enabled = get_option('gica_fcm_enabled', false);
            $firebase_project_id = get_option('gica_firebase_project_id', '');
            $firebase_api_key = get_option('gica_firebase_api_key', '');
            $fcm_vapid_key = get_option('gica_fcm_vapid_key', '');
            $fcm_service_account = get_option('gica_fcm_service_account_json', '');
            
            if ($fcm_enabled) {
                $config_ok[] = 'FCM está habilitado';
            } else {
                $config_issues[] = 'FCM no está habilitado';
            }
            
            if (!empty($firebase_project_id)) {
                $config_ok[] = 'Firebase Project ID: ' . $firebase_project_id;
            } else {
                $critical_issues[] = 'Firebase Project ID faltante';
            }
            
            if (!empty($firebase_api_key)) {
                $config_ok[] = 'Firebase API Key configurada (' . strlen($firebase_api_key) . ' chars)';
            } else {
                $config_issues[] = 'Firebase API Key faltante';
            }
            
            if (!empty($fcm_service_account)) {
                $service_account_data = json_decode($fcm_service_account, true);
                if ($service_account_data) {
                    $config_ok[] = 'Service Account JSON válido';
                    if (isset($service_account_data['project_id'])) {
                        $config_ok[] = 'Service Account Project ID: ' . $service_account_data['project_id'];
                    }
                } else {
                    $critical_issues[] = 'Service Account JSON inválido';
                }
            } else {
                $critical_issues[] = 'Service Account JSON faltante - CRÍTICO';
            }
            ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
                <div style="padding: 20px; border: 2px solid <?php echo empty($critical_issues) ? '#28a745' : '#dc3545'; ?>; border-radius: 8px;">
                    <h3>🔧 Configuración</h3>
                    <?php if (empty($critical_issues)): ?>
                        <div style="color: #28a745; font-weight: bold;">✅ Sistema Configurado</div>
                    <?php else: ?>
                        <div style="color: #dc3545; font-weight: bold;">❌ Faltan Configuraciones Críticas</div>
                    <?php endif; ?>
                </div>
                
                <div style="padding: 20px; border: 2px solid #007cba; border-radius: 8px;">
                    <h3>📱 Dispositivos</h3>
                    <?php
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
                    $device_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_active = 1");
                    ?>
                    <div style="color: #007cba; font-weight: bold;"><?php echo $device_count; ?> dispositivos registrados</div>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>📋 Configuración Detallada</h2>
            <table>
                <tr><th>Componente</th><th>Estado</th><th>Detalle</th></tr>
                <tr>
                    <td>FCM Habilitado</td>
                    <td><span class="status-indicator <?php echo $fcm_enabled ? 'status-ok' : 'status-error'; ?>"></span><?php echo $fcm_enabled ? 'Activo' : 'Inactivo'; ?></td>
                    <td><?php echo $fcm_enabled ? 'Sistema FCM activado' : 'FCM deshabilitado en configuración'; ?></td>
                </tr>
                <tr>
                    <td>Project ID</td>
                    <td><span class="status-indicator <?php echo !empty($firebase_project_id) ? 'status-ok' : 'status-error'; ?>"></span><?php echo !empty($firebase_project_id) ? 'Configurado' : 'Faltante'; ?></td>
                    <td><?php echo !empty($firebase_project_id) ? esc_html($firebase_project_id) : 'No configurado'; ?></td>
                </tr>
                <tr>
                    <td>API Key</td>
                    <td><span class="status-indicator <?php echo !empty($firebase_api_key) ? 'status-ok' : 'status-warning'; ?>"></span><?php echo !empty($firebase_api_key) ? 'Configurada' : 'Faltante'; ?></td>
                    <td><?php echo !empty($firebase_api_key) ? strlen($firebase_api_key) . ' caracteres' : 'No configurada'; ?></td>
                </tr>
                <tr>
                    <td>Service Account</td>
                    <td><span class="status-indicator <?php echo !empty($fcm_service_account) ? 'status-ok' : 'status-error'; ?>"></span><?php echo !empty($fcm_service_account) ? 'Configurada' : 'FALTANTE'; ?></td>
                    <td><?php echo !empty($fcm_service_account) ? strlen($fcm_service_account) . ' caracteres' : 'CRÍTICO - Necesario para FCM HTTP v1'; ?></td>
                </tr>
            </table>
        </div>

        <?php if (!empty($critical_issues)): ?>
        <div class="section">
            <h2>🚨 PROBLEMAS CRÍTICOS ENCONTRADOS</h2>
            <div class="error">
                <h3>❌ Service Account JSON Faltante</h3>
                <p><strong>ESTE ES EL PROBLEMA PRINCIPAL</strong> que impide enviar notificaciones.</p>
                <p>El Service Account JSON es obligatorio para usar FCM HTTP v1 API.</p>
            </div>
            
            <div class="info">
                <h3>🔧 Cómo Solucionarlo (5 minutos):</h3>
                <ol>
                    <li>Ve a <a href="https://console.firebase.google.com" target="_blank">Firebase Console</a></li>
                    <li>Selecciona tu proyecto: <strong><?php echo $firebase_project_id ?: 'gicaform-notifications'; ?></strong></li>
                    <li>Ve a <strong>Configuración del proyecto</strong> (⚙️)</li>
                    <li>Pestaña <strong>"Cuentas de servicio"</strong></li>
                    <li>Haz clic en <strong>"Generar nueva clave privada"</strong></li>
                    <li>Descarga el archivo JSON</li>
                    <li>Copia TODO el contenido del archivo y pégalo abajo:</li>
                </ol>
            </div>
            
            <h3>💾 Configurar Service Account JSON</h3>
            <textarea id="service-account-json" placeholder="Pega aquí el contenido completo del archivo JSON descargado de Firebase Console:

{
  &quot;type&quot;: &quot;service_account&quot;,
  &quot;project_id&quot;: &quot;gicaform-notifications&quot;,
  &quot;private_key_id&quot;: &quot;...&quot;,
  &quot;private_key&quot;: &quot;-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n&quot;,
  &quot;client_email&quot;: &quot;firebase-adminsdk-...@gicaform-notifications.iam.gserviceaccount.com&quot;,
  &quot;client_id&quot;: &quot;...&quot;,
  &quot;auth_uri&quot;: &quot;https://accounts.google.com/o/oauth2/auth&quot;,
  &quot;token_uri&quot;: &quot;https://oauth2.googleapis.com/token&quot;
}"></textarea>
            <br>
            <button class="btn btn-success" onclick="saveServiceAccount()">💾 Guardar y Activar FCM</button>
            <div id="service-account-result"></div>
        </div>
        <?php endif; ?>

        <div class="section">
            <h2>📱 Dispositivos Registrados</h2>
            <?php
            $devices = $wpdb->get_results("SELECT * FROM $table_name WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 10");
            
            if ($devices): ?>
                <table>
                    <tr><th>Device ID</th><th>Info del Dispositivo</th><th>Token Preview</th><th>Última Act.</th><th>Test</th></tr>
                    <?php foreach ($devices as $device): ?>
                        <tr>
                            <td><code><?php echo esc_html($device->device_id); ?></code></td>
                            <td><?php echo esc_html($device->device_info); ?></td>
                            <td><code><?php echo substr($device->fcm_token, 0, 25); ?>...</code></td>
                            <td><?php echo $device->updated_at; ?></td>
                            <td>
                                <?php if (!empty($fcm_service_account)): ?>
                                    <button class="btn" onclick="testDevice('<?php echo esc_js($device->device_id); ?>', '<?php echo esc_js($device->fcm_token); ?>')">🧪 Test</button>
                                <?php else: ?>
                                    <span style="color: #dc3545;">Configura Service Account</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <div class="warning">⚠️ No hay dispositivos móviles registrados. 
                    <br>Asegúrate de que tu app Android esté funcionando y registrando tokens.
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($fcm_service_account)): ?>
        <div class="section">
            <h2>🧪 Test de Notificaciones</h2>
            <div class="success">✅ Sistema configurado correctamente. Puedes probar las notificaciones:</div>
            <button class="btn btn-success" onclick="runCompleteTest()">🚀 Ejecutar Test Completo</button>
            <div id="test-results"></div>
        </div>
        <?php endif; ?>

        <div class="section">
            <h2>📊 Diagnóstico Técnico</h2>
            <button class="btn" onclick="runTechnicalDiagnosis()">🔍 Ejecutar Diagnóstico Técnico</button>
            <div id="technical-results"></div>
        </div>

    </div>

    <script>
        async function saveServiceAccount() {
            const jsonContent = document.getElementById('service-account-json').value.trim();
            const resultDiv = document.getElementById('service-account-result');
            
            if (!jsonContent) {
                resultDiv.innerHTML = '<div class="error">❌ Por favor pega el contenido del JSON</div>';
                return;
            }
            
            try {
                // Validar JSON localmente
                const parsedJson = JSON.parse(jsonContent);
                if (!parsedJson.type || parsedJson.type !== 'service_account') {
                    throw new Error('No es un Service Account JSON válido');
                }
                
                resultDiv.innerHTML = '<div class="info">🔄 Guardando Service Account...</div>';
                
                // Enviar directamente al endpoint mobile-api
                const response = await fetch('/wp-content/plugins/GICAACCOUNT/mobile-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'save_service_account_direct',
                        service_account_json: jsonContent
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = '<div class="success">✅ Service Account guardado exitosamente!<br>📱 Las notificaciones ya deberían funcionar.<br>🔄 Recargando página...</div>';
                    setTimeout(() => location.reload(), 3000);
                } else {
                    resultDiv.innerHTML = '<div class="error">❌ Error: ' + (data.message || 'Error desconocido') + '</div>';
                }
                
            } catch (error) {
                resultDiv.innerHTML = '<div class="error">❌ JSON inválido: ' + error.message + '</div>';
            }
        }
        
        async function testDevice(deviceId, token) {
            const resultDiv = document.createElement('div');
            resultDiv.style.marginTop = '10px';
            event.target.parentNode.appendChild(resultDiv);
            
            resultDiv.innerHTML = '<div class="info">🔄 Enviando notificación de prueba...</div>';
            
            try {
                const response = await fetch('/wp-content/plugins/GICAACCOUNT/mobile-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'send_test_notification_direct',
                        device_id: deviceId,
                        fcm_token: token
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = '<div class="success">✅ Notificación enviada! Verifica tu móvil.</div>';
                } else {
                    resultDiv.innerHTML = '<div class="error">❌ Error: ' + (data.message || 'Error desconocido') + '</div>';
                }
                
            } catch (error) {
                resultDiv.innerHTML = '<div class="error">❌ Error: ' + error.message + '</div>';
            }
        }
        
        async function runTechnicalDiagnosis() {
            const resultDiv = document.getElementById('technical-results');
            resultDiv.innerHTML = '<div class="info">🔄 Ejecutando diagnóstico técnico...</div>';
            
            try {
                const response = await fetch('/wp-content/plugins/GICAACCOUNT/test-fcm-service.php', {
                    method: 'POST'
                });
                
                const data = await response.text();
                resultDiv.innerHTML = '<div class="info"><h3>📊 Diagnóstico Técnico:</h3><pre>' + data + '</pre></div>';
                
            } catch (error) {
                resultDiv.innerHTML = '<div class="error">❌ Error en diagnóstico: ' + error.message + '</div>';
            }
        }
        
        async function runCompleteTest() {
            const resultDiv = document.getElementById('test-results');
            resultDiv.innerHTML = '<div class="info">🔄 Ejecutando test completo...</div>';
            
            try {
                const response = await fetch('/wp-content/plugins/GICAACCOUNT/mobile-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'complete_fcm_test'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = '<div class="success">✅ Test completado exitosamente!<br>' + JSON.stringify(data.data, null, 2) + '</div>';
                } else {
                    resultDiv.innerHTML = '<div class="error">❌ Test falló: ' + (data.message || 'Error desconocido') + '</div>';
                }
                
            } catch (error) {
                resultDiv.innerHTML = '<div class="error">❌ Error en test: ' + error.message + '</div>';
            }
        }
    </script>
</body>
</html>