<?php
/**
 * Script de debugging para verificar endpoints FCM
 * Acceder via: tu-sitio.com/wp-content/plugins/GICAACCOUNT/debug-endpoints.php
 */

// Cargar WordPress
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

// Verificar si es admin
if (!current_user_can('manage_options')) {
    wp_die('No tienes permisos para acceder a esta página');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Debug FCM Endpoints - GICA</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .section { margin: 30px 0; padding: 20px; border: 2px solid #dee2e6; border-radius: 8px; }
        .test-button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 10px 5px; }
        .test-button:hover { background: #005a87; }
    </style>
</head>
<body>
    <h1>🔍 Debug FCM Endpoints - GICA Account</h1>
    <p><strong>Fecha:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

    <div class="section">
        <h2>📋 1. Verificación del Plugin</h2>
        <?php
        $plugin_active = is_plugin_active('GICAACCOUNT/gica-account.php');
        if ($plugin_active): ?>
            <div class="success">✅ Plugin GICA Account está ACTIVO</div>
        <?php else: ?>
            <div class="error">❌ Plugin GICA Account NO está activo</div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>🔌 2. Verificación de Endpoints AJAX</h2>
        <?php
        global $wp_filter;
        
        $endpoints_to_check = [
            'wp_ajax_gica_mobile_register_token',
            'wp_ajax_nopriv_gica_mobile_register_token',
            'wp_ajax_gica_send_test_notification',
            'wp_ajax_gica_save_fcm_token'
        ];
        
        $registered_endpoints = [];
        $missing_endpoints = [];
        
        foreach ($endpoints_to_check as $endpoint) {
            if (isset($wp_filter[$endpoint]) && !empty($wp_filter[$endpoint]->callbacks)) {
                $registered_endpoints[] = $endpoint;
            } else {
                $missing_endpoints[] = $endpoint;
            }
        }
        ?>
        
        <?php if (!empty($registered_endpoints)): ?>
            <div class="success">
                <h3>✅ Endpoints Registrados:</h3>
                <ul>
                    <?php foreach ($registered_endpoints as $endpoint): ?>
                        <li><code><?php echo $endpoint; ?></code></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($missing_endpoints)): ?>
            <div class="error">
                <h3>❌ Endpoints Faltantes:</h3>
                <ul>
                    <?php foreach ($missing_endpoints as $endpoint): ?>
                        <li><code><?php echo $endpoint; ?></code></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>🔑 3. Verificación de API Key</h2>
        <?php
        $api_key = get_option('gica_mobile_api_key', 'gica_mobile_2024');
        $expected_key = 'gica_mobile_2024';
        ?>
        <p><strong>API Key configurada:</strong> <code><?php echo esc_html($api_key); ?></code></p>
        <p><strong>API Key esperada:</strong> <code><?php echo esc_html($expected_key); ?></code></p>
        
        <?php if ($api_key === $expected_key): ?>
            <div class="success">✅ API Key coincide correctamente</div>
        <?php else: ?>
            <div class="error">❌ API Key no coincide - actualizando...</div>
            <?php 
            update_option('gica_mobile_api_key', $expected_key);
            ?>
            <div class="success">✅ API Key actualizada correctamente</div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>🗃️ 4. Verificación de Base de Datos</h2>
        <?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        ?>
        <p><strong>Tabla:</strong> <code><?php echo $table_name; ?></code></p>
        
        <?php if ($table_exists): ?>
            <div class="success">✅ Tabla existe en la base de datos</div>
            <?php
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            ?>
            <p><strong>Registros:</strong> <?php echo $count; ?> dispositivos móviles</p>
        <?php else: ?>
            <div class="warning">⚠️ Tabla no existe - se creará automáticamente al registrar primer dispositivo</div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>🔧 5. Verificación de Configuración FCM</h2>
        <?php
        $fcm_enabled = get_option('gica_fcm_enabled', false);
        $project_id = get_option('gica_firebase_project_id', '');
        $vapid_key = get_option('gica_fcm_vapid_key', '');
        ?>
        
        <table style="width: 100%; border-collapse: collapse;">
            <tr style="background: #f8f9fa;">
                <th style="padding: 10px; text-align: left; border: 1px solid #dee2e6;">Configuración</th>
                <th style="padding: 10px; text-align: left; border: 1px solid #dee2e6;">Estado</th>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #dee2e6;">FCM Habilitado</td>
                <td style="padding: 10px; border: 1px solid #dee2e6;">
                    <?php echo $fcm_enabled ? '<span style="color: green;">✅ Sí</span>' : '<span style="color: red;">❌ No</span>'; ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #dee2e6;">Project ID</td>
                <td style="padding: 10px; border: 1px solid #dee2e6;">
                    <?php echo !empty($project_id) ? '<span style="color: green;">✅ Configurado</span>' : '<span style="color: red;">❌ Faltante</span>'; ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #dee2e6;">VAPID Key</td>
                <td style="padding: 10px; border: 1px solid #dee2e6;">
                    <?php echo !empty($vapid_key) ? '<span style="color: green;">✅ Configurada</span>' : '<span style="color: red;">❌ Faltante</span>'; ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>🧪 6. Test de Endpoint</h2>
        <p>Simular request desde app Android:</p>
        
        <button class="test-button" onclick="testEndpoint()">🚀 Probar Endpoint</button>
        <button class="test-button" onclick="testWithRealToken()">📱 Probar con Token Real</button>
        
        <div id="test-results" style="margin-top: 20px;"></div>
    </div>

    <div class="section">
        <h2>📊 7. Información del Sistema</h2>
        <pre><?php
        echo "WordPress Version: " . get_bloginfo('version') . "\n";
        echo "PHP Version: " . PHP_VERSION . "\n";
        echo "Plugin Path: " . GICA_ACCOUNT_PLUGIN_PATH . "\n";
        echo "Plugin URL: " . GICA_ACCOUNT_PLUGIN_URL . "\n";
        echo "Current Time: " . current_time('mysql') . "\n";
        echo "Site URL: " . site_url() . "\n";
        echo "Admin AJAX URL: " . admin_url('admin-ajax.php') . "\n";
        ?></pre>
    </div>

    <script>
        function testEndpoint() {
            const resultsDiv = document.getElementById('test-results');
            resultsDiv.innerHTML = '<div class="info">🔄 Probando endpoint...</div>';
            
            const testData = new FormData();
            testData.append('action', 'gica_mobile_register_token');
            testData.append('api_key', 'gica_mobile_2024');
            testData.append('fcm_token', 'TEST_TOKEN_123456789');
            testData.append('device_id', 'DEBUG_DEVICE_' + Date.now());
            testData.append('app_version', '1.0.0');
            testData.append('device_info', 'Debug Device (Chrome Browser)');
            testData.append('user_id', '<?php echo get_current_user_id(); ?>');
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: testData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(data => {
                console.log('Response data:', data);
                try {
                    const json = JSON.parse(data);
                    if (json.success) {
                        resultsDiv.innerHTML = '<div class="success">✅ Endpoint funcionando correctamente!<pre>' + JSON.stringify(json, null, 2) + '</pre></div>';
                    } else {
                        resultsDiv.innerHTML = '<div class="error">❌ Error en endpoint:<pre>' + JSON.stringify(json, null, 2) + '</pre></div>';
                    }
                } catch (e) {
                    resultsDiv.innerHTML = '<div class="error">❌ Respuesta no válida:<pre>' + data + '</pre></div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultsDiv.innerHTML = '<div class="error">❌ Error de red: ' + error.message + '</div>';
            });
        }
        
        function testWithRealToken() {
            const resultsDiv = document.getElementById('test-results');
            const realToken = 'f0-du7sKSo-LyfDM3Ls-YY:APA91bHv1O60-v0mtM9yTEi12CyDQKrjmCgLLe7n4uuLsYGRgfeWGsPId6zFQ6put_W9VdZYsubLeJ_3jQ0iMS_c2ytt5gjhfiM61TBup6Neyb3Dmahk_Ok';
            
            resultsDiv.innerHTML = '<div class="info">🔄 Probando con token real de tu app Android...</div>';
            
            const testData = new FormData();
            testData.append('action', 'gica_mobile_register_token');
            testData.append('api_key', 'gica_mobile_2024');
            testData.append('fcm_token', realToken);
            testData.append('device_id', 'ANDROID_REAL_DEVICE');
            testData.append('app_version', '1.0.0');
            testData.append('device_info', 'Android Real Device from fcm.txt');
            testData.append('user_id', '<?php echo get_current_user_id(); ?>');
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: testData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(data => {
                console.log('Response data:', data);
                try {
                    const json = JSON.parse(data);
                    if (json.success) {
                        resultsDiv.innerHTML = '<div class="success">✅ ¡Token real registrado exitosamente!<pre>' + JSON.stringify(json, null, 2) + '</pre><p><strong>Ahora puedes enviar notificaciones de prueba desde WordPress Admin.</strong></p></div>';
                    } else {
                        resultsDiv.innerHTML = '<div class="error">❌ Error registrando token real:<pre>' + JSON.stringify(json, null, 2) + '</pre></div>';
                    }
                } catch (e) {
                    resultsDiv.innerHTML = '<div class="error">❌ Respuesta no válida:<pre>' + data + '</pre></div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultsDiv.innerHTML = '<div class="error">❌ Error de red: ' + error.message + '</div>';
            });
        }
    </script>
</body>
</html>