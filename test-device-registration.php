<?php
/**
 * Test Device Registration - Simular registro de dispositivo móvil
 */

// Load WordPress - try multiple possible paths
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
    die('WordPress not found. Tried paths: ' . implode(', ', $possible_paths));
}

if (!defined('ABSPATH')) {
    exit;
}

?><!DOCTYPE html>
<html>
<head>
    <title>🧪 Test Device Registration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #0073aa; color: white; padding: 15px; border-radius: 8px 8px 0 0; margin: -20px -20px 20px; }
        .test-form { background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin: 15px 0; }
        .form-group { margin: 10px 0; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input, textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        button:hover { background: #005a87; }
        .result { margin: 15px 0; padding: 10px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .current-config { background: #fff3cd; color: #856404; padding: 10px; border: 1px solid #ffeaa7; border-radius: 4px; margin: 15px 0; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🧪 Test Device Registration</h1>
        <p>Simular el registro de un dispositivo móvil FCM</p>
    </div>

    <?php
    // Current configuration
    $api_key = get_option('gica_mobile_api_key', 'gica_mobile_2024');
    $fcm_enabled = get_option('gica_fcm_enabled', false);
    $project_id = get_option('gica_firebase_project_id', '');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
    $device_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_active = 1");
    ?>

    <div class="current-config">
        <h3>📋 Configuración Actual:</h3>
        <ul>
            <li><strong>API Key:</strong> <code><?php echo esc_html($api_key); ?></code></li>
            <li><strong>FCM Habilitado:</strong> <?php echo $fcm_enabled ? '✅ SÍ' : '❌ NO'; ?></li>
            <li><strong>Project ID:</strong> <code><?php echo esc_html($project_id); ?></code></li>
            <li><strong>Dispositivos activos:</strong> <?php echo intval($device_count); ?></li>
        </ul>
    </div>

    <div class="test-form">
        <h3>🚀 Test 1: Registro Manual de Dispositivo</h3>
        <p>Simula el registro que haría tu app Android:</p>
        
        <form id="testForm1">
            <div class="form-group">
                <label for="device_id">Device ID (Android ID único):</label>
                <input type="text" id="device_id" name="device_id" value="test_device_<?php echo rand(1000, 9999); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="fcm_token">FCM Token (token del dispositivo):</label>
                <textarea id="fcm_token" name="fcm_token" rows="3" placeholder="ej: eHGxVoC6Q4eX8..." required>d8UQkZvRTwyGHEXAMPLE_FCM_TOKEN_TEST_<?php echo rand(100000, 999999); ?>:APA91bExample_token_for_testing</textarea>
            </div>
            
            <div class="form-group">
                <label for="api_key">API Key:</label>
                <input type="text" id="api_key" name="api_key" value="<?php echo esc_attr($api_key); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="app_version">App Version:</label>
                <input type="text" id="app_version" name="app_version" value="1.0.0">
            </div>
            
            <div class="form-group">
                <label for="device_info">Device Info:</label>
                <input type="text" id="device_info" name="device_info" value="Samsung Galaxy S21, Android 13">
            </div>
            
            <div class="form-group">
                <label for="user_id">User ID (opcional):</label>
                <input type="number" id="user_id" name="user_id" value="1">
            </div>
            
            <button type="submit">📱 Registrar Dispositivo</button>
        </form>
        
        <div id="result1"></div>
    </div>

    <div class="test-form">
        <h3>🔄 Test 2: Generar Token Aleatorio</h3>
        <button onclick="generateRandomToken()">🎲 Generar Token FCM Aleatorio</button>
        <div id="randomToken"></div>
    </div>

    <div class="test-form">
        <h3>📊 Test 3: Ver Dispositivos Registrados</h3>
        <button onclick="loadDevices()">🔍 Cargar Dispositivos</button>
        <div id="devicesList"></div>
    </div>

</div>

<script>
// Test 1: Register device
document.getElementById('testForm1').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const resultDiv = document.getElementById('result1');
    resultDiv.innerHTML = '<div class="info">⏳ Registrando dispositivo...</div>';
    
    const formData = new FormData(this);
    formData.append('action', 'gica_mobile_register_token');
    
    try {
        const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="success">
                    <h4>✅ ¡Dispositivo registrado exitosamente!</h4>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                </div>
            `;
            
            // Auto-reload devices list
            loadDevices();
        } else {
            resultDiv.innerHTML = `
                <div class="error">
                    <h4>❌ Error al registrar dispositivo</h4>
                    <p><strong>Mensaje:</strong> ${data.data?.message || 'Error desconocido'}</p>
                    <p><strong>Código:</strong> ${data.data?.code || 'N/A'}</p>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                </div>
            `;
        }
    } catch (error) {
        resultDiv.innerHTML = `
            <div class="error">
                <h4>❌ Error de conexión</h4>
                <p>${error.message}</p>
            </div>
        `;
    }
});

// Generate random FCM token
function generateRandomToken() {
    const prefixes = ['eHGx', 'd8UQ', 'fKLm', 'cNP9', 'bWx7'];
    const suffixes = ['APA91b', 'AAAA', 'BBBB', 'CCCC'];
    
    const prefix = prefixes[Math.floor(Math.random() * prefixes.length)];
    const middle = Math.random().toString(36).substring(2, 15);
    const suffix = suffixes[Math.floor(Math.random() * suffixes.length)];
    const end = Math.random().toString(36).substring(2, 25);
    
    const token = `${prefix}${middle}:${suffix}${end}`;
    
    document.getElementById('fcm_token').value = token;
    document.getElementById('randomToken').innerHTML = `
        <div class="success">
            <h4>🎲 Token generado:</h4>
            <pre>${token}</pre>
        </div>
    `;
}

// Load devices
async function loadDevices() {
    const devicesDiv = document.getElementById('devicesList');
    devicesDiv.innerHTML = '<div class="info">⏳ Cargando dispositivos...</div>';
    
    try {
        const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=gica_get_mobile_devices'
        });
        
        const data = await response.json();
        
        if (data.success && data.data.devices) {
            const devices = data.data.devices;
            let html = `
                <div class="success">
                    <h4>📱 Dispositivos registrados (${devices.length}):</h4>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f0f0f0;">
                                <th style="border: 1px solid #ddd; padding: 8px;">Device ID</th>
                                <th style="border: 1px solid #ddd; padding: 8px;">Token (preview)</th>
                                <th style="border: 1px solid #ddd; padding: 8px;">Info</th>
                                <th style="border: 1px solid #ddd; padding: 8px;">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            devices.forEach(device => {
                html += `
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 8px;"><code>${device.device_id}</code></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><code>${device.fcm_token.substring(0, 25)}...</code></td>
                        <td style="border: 1px solid #ddd; padding: 8px;">${device.device_info || 'N/A'}</td>
                        <td style="border: 1px solid #ddd; padding: 8px;">${device.is_active == 1 ? '🟢 Activo' : '🔴 Inactivo'}</td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            devicesDiv.innerHTML = html;
        } else {
            devicesDiv.innerHTML = `
                <div class="info">
                    <h4>📱 No hay dispositivos registrados</h4>
                    <p>Registra un dispositivo usando el formulario anterior.</p>
                </div>
            `;
        }
    } catch (error) {
        devicesDiv.innerHTML = `
            <div class="error">
                <h4>❌ Error al cargar dispositivos</h4>
                <p>${error.message}</p>
            </div>
        `;
    }
}

// Auto-load devices on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDevices();
});
</script>

</body>
</html>