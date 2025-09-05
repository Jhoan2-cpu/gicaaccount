<?php

if (!defined('ABSPATH')) {
    exit;
}

class GicaAdminManager {
    
    private $admin_controller;
    
    public function __construct() {
        $this->admin_controller = new GicaAdminController();
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'gica') === false) {
            return;
        }
        
        wp_enqueue_style('gica-admin-style', GICA_ACCOUNT_PLUGIN_URL . 'assets/admin-style.css', array(), '1.0.0');
        wp_enqueue_script('gica-admin-script', GICA_ACCOUNT_PLUGIN_URL . 'assets/admin-script.js', array('jquery'), '1.0.0', true);
        
        // Agregar script específico para FCM
        wp_enqueue_script('gica-admin-fcm', GICA_ACCOUNT_PLUGIN_URL . 'assets/js/admin-fcm.js', array('jquery'), '1.0.0', true);
        
        wp_localize_script('gica-admin-script', 'gica_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gica_admin_nonce'),
            'confirm_delete' => '¿Estás seguro de que deseas eliminar este usuario?',
            'loading_text' => 'Cargando...',
            'no_users_found' => 'No se encontraron usuarios',
            'error_loading' => 'Error al cargar los datos',
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG
        ));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'GICA Account',
            'GICA Account',
            'manage_options',
            'gica-account',
            array($this, 'admin_page'),
            'dashicons-admin-users',
            30
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap gica-admin-wrap">
            <h1 class="gica-admin-title">
                <span class="dashicons dashicons-admin-users"></span>
                GICA Account
            </h1>
            
            <div class="gica-admin-tabs-wrapper">
                <div class="gica-admin-tabs">
                    <button class="gica-admin-tab active" data-tab="dashboard">Panel Principal</button>
                    <button class="gica-admin-tab" data-tab="users">Usuarios</button>
                    <button class="gica-admin-tab" data-tab="preview">Preview</button>
                    <button class="gica-admin-tab" data-tab="fcm">FCM</button>
                </div>
                
                <div class="gica-admin-tab-content">
                    <div id="gica-tab-dashboard" class="gica-tab-panel active">
                        <?php $this->render_dashboard_tab(); ?>
                    </div>
                    
                    <div id="gica-tab-users" class="gica-tab-panel">
                        <?php $this->render_users_tab(); ?>
                    </div>
                    
                    <div id="gica-tab-preview" class="gica-tab-panel">
                        <?php $this->render_preview_tab(); ?>
                    </div>
                    
                    <div id="gica-tab-fcm" class="gica-tab-panel">
                        <?php $this->render_fcm_tab(); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php $this->admin_controller->render_user_modal(); ?>
        <?php
    }
    
    private function render_dashboard_tab() {
        $user_count = count_users()['total_users'];
        $complete_profiles = $this->get_complete_profiles_count();
        ?>
        <div class="gica-dashboard-content">
            <div class="gica-dashboard-stats">
                <div class="gica-stat-card">
                    <div class="gica-stat-icon">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <div class="gica-stat-content">
                        <h3><?php echo number_format($user_count); ?></h3>
                        <p>Usuarios Totales</p>
                    </div>
                </div>
                
                <div class="gica-stat-card">
                    <div class="gica-stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="gica-stat-content">
                        <h3><?php echo number_format($complete_profiles); ?></h3>
                        <p>Perfiles Completos</p>
                    </div>
                </div>
                
                <div class="gica-stat-card">
                    <div class="gica-stat-icon">
                        <span class="dashicons dashicons-shortcode"></span>
                    </div>
                    <div class="gica-stat-content">
                        <h3>[gica_account]</h3>
                        <p>Shortcode Principal</p>
                    </div>
                </div>
                
                <div class="gica-stat-card">
                    <div class="gica-stat-icon">
                        <span class="dashicons dashicons-smartphone"></span>
                    </div>
                    <div class="gica-stat-content">
                        <h3>FCM</h3>
                        <p>Notificaciones Push</p>
                    </div>
                </div>
            </div>
            
            <div class="gica-dashboard-actions">
                <div class="gica-action-card">
                    <h3>Acciones Rápidas</h3>
                    <div class="gica-action-buttons">
                        <button class="button button-primary gica-admin-tab-trigger" data-tab="users">
                            <span class="dashicons dashicons-admin-users"></span>
                            Administrar Usuarios
                        </button>
                        <button class="button button-secondary gica-admin-tab-trigger" data-tab="preview">
                            <span class="dashicons dashicons-visibility"></span>
                            Ver Preview
                        </button>
                        <button class="button button-secondary gica-admin-tab-trigger" data-tab="fcm">
                            <span class="dashicons dashicons-smartphone"></span>
                            Configurar FCM
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function render_users_tab() {
        ?>
        <div class="gica-users-content">
            <div class="gica-section-header">
                <h2><span class="dashicons dashicons-admin-users"></span>Administrar Usuarios</h2>
                <p>Gestiona todos los usuarios registrados en el sistema</p>
            </div>
            
            <div class="gica-users-controls">
                <div class="gica-search-box">
                    <input type="text" id="gica-user-search" placeholder="Buscar por nombre, email, DNI, ciudad..." class="regular-text">
                    <button type="button" id="gica-search-btn" class="button">Buscar</button>
                    <button type="button" id="gica-clear-search" class="button">Limpiar</button>
                </div>
                <div class="gica-users-actions">
                    <button type="button" id="gica-refresh-users" class="button">
                        <span class="dashicons dashicons-update"></span>
                        Actualizar
                    </button>
                </div>
            </div>
            
            <div class="gica-users-table-wrapper">
                <div id="gica-users-loading" class="gica-loading-overlay" style="display: none;">
                    <div class="gica-spinner"></div>
                    <p>Cargando usuarios...</p>
                </div>
                
                <table class="wp-list-table widefat fixed striped gica-users-table" id="gica-users-table">
                    <thead>
                        <tr>
                            <th class="gica-col-id">ID</th>
                            <th class="gica-col-user">Usuario</th>
                            <th class="gica-col-email">Email</th>
                            <th class="gica-col-dni">DNI</th>
                            <th class="gica-col-location">Ubicación</th>
                            <th class="gica-col-completion">Completado</th>
                            <th class="gica-col-date">Registro</th>
                            <th class="gica-col-actions">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="gica-users-tbody">
                        <!-- Los usuarios se cargan dinámicamente via AJAX -->
                    </tbody>
                </table>
                
                <div class="gica-pagination" id="gica-pagination">
                    <!-- La paginación se genera dinámicamente -->
                </div>
            </div>
        </div>
        <?php
    }
    
    private function render_preview_tab() {
        ?>
        <div class="gica-preview-content">
            <div class="gica-section-header">
                <h2><span class="dashicons dashicons-visibility"></span>Preview del Shortcode</h2>
                <p>Visualiza cómo se ve el shortcode en el frontend</p>
            </div>
            
            <div class="gica-shortcode-info">
                <div class="gica-info-card">
                    <h3>Uso del Shortcode</h3>
                    <code class="gica-shortcode">[gica_account]</code>
                    <p>Copia y pega este código en cualquier página o entrada donde desees mostrar el formulario de cuenta de usuario.</p>
                </div>
            </div>
            
            <div class="gica-preview-container">
                <h3>Vista Previa</h3>
                <div class="gica-preview-frame gica-preview-styled">
                    <?php echo $this->render_shortcode_preview(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function render_fcm_tab() {
        // Handle Service Account JSON save
        if (isset($_POST['gica_service_account_submit']) && wp_verify_nonce($_POST['gica_service_account_nonce'], 'gica_service_account_save')) {
            $this->handle_service_account_save();
        }
        
        // Handle test notification
        if (isset($_POST['gica_fcm_test']) && wp_verify_nonce($_POST['gica_fcm_test_nonce'], 'gica_fcm_test')) {
            $this->handle_test_notification();
        }
        
        
        // Procesar formulario FCM (old method for compatibility)
        if (isset($_POST['gica_fcm_submit'])) {
            echo '<div class="notice notice-info"><p>🔍 Formulario recibido, verificando datos...</p></div>';
            
            // Verificar nonce
            if (!wp_verify_nonce($_POST['gica_fcm_nonce'], 'gica_fcm_config')) {
                echo '<div class="notice notice-error is-dismissible"><p>❌ Error de seguridad: Nonce inválido</p></div>';
                error_log('GICA FCM Error: Nonce verification failed');
            } else {
                echo '<div class="notice notice-success"><p>✅ Nonce verificado correctamente</p></div>';
                
                try {
                    // Guardar configuración Firebase con validación
                    $saved_count = 0;
                    
                    if (update_option('gica_fcm_enabled', isset($_POST['fcm_enabled']))) $saved_count++;
                    if (update_option('gica_fcm_debug_mode', isset($_POST['fcm_debug_mode']))) $saved_count++;
                    
                    $firebase_api_key = sanitize_text_field($_POST['firebase_api_key'] ?? '');
                    $firebase_auth_domain = sanitize_text_field($_POST['firebase_auth_domain'] ?? '');
                    $firebase_project_id = sanitize_text_field($_POST['firebase_project_id'] ?? '');
                    $firebase_storage_bucket = sanitize_text_field($_POST['firebase_storage_bucket'] ?? '');
                    $firebase_messaging_sender_id = sanitize_text_field($_POST['firebase_messaging_sender_id'] ?? '');
                    $firebase_app_id = sanitize_text_field($_POST['firebase_app_id'] ?? '');
                    $fcm_vapid_key = sanitize_text_field($_POST['fcm_vapid_key'] ?? '');
                    
                    // Validaciones
                    $errors = array();
                    if (empty($firebase_api_key)) $errors[] = 'API Key es requerido';
                    if (empty($firebase_project_id)) $errors[] = 'Project ID es requerido';
                    if (empty($firebase_messaging_sender_id)) $errors[] = 'Sender ID es requerido';
                    if (empty($firebase_app_id)) $errors[] = 'App ID es requerido';
                    if (empty($fcm_vapid_key)) $errors[] = 'VAPID Key es requerido';
                    
                    if (!empty($errors)) {
                        echo '<div class="notice notice-error is-dismissible"><p>❌ Errores de validación:<br>' . implode('<br>', $errors) . '</p></div>';
                    } else {
                        // Guardar todos los valores
                        update_option('gica_firebase_api_key', $firebase_api_key);
                        update_option('gica_firebase_auth_domain', $firebase_auth_domain);
                        update_option('gica_firebase_project_id', $firebase_project_id);
                        update_option('gica_firebase_storage_bucket', $firebase_storage_bucket);
                        update_option('gica_firebase_messaging_sender_id', $firebase_messaging_sender_id);
                        update_option('gica_firebase_app_id', $firebase_app_id);
                        update_option('gica_fcm_vapid_key', $fcm_vapid_key);
                        
                        // Marcar como configurado
                        update_option('gica_fcm_configured_at', current_time('mysql'));
                        
                        echo '<div class="notice notice-success is-dismissible">';
                        echo '<p>✅ <strong>Configuración FCM guardada correctamente</strong></p>';
                        echo '<ul>';
                        echo '<li>FCM Habilitado: ' . (isset($_POST['fcm_enabled']) ? 'Sí' : 'No') . '</li>';
                        echo '<li>Modo Debug: ' . (isset($_POST['fcm_debug_mode']) ? 'Sí' : 'No') . '</li>';
                        echo '<li>API Key: ' . substr($firebase_api_key, 0, 10) . '...' . '</li>';
                        echo '<li>Project ID: ' . $firebase_project_id . '</li>';
                        echo '<li>VAPID Key: ' . substr($fcm_vapid_key, 0, 10) . '...' . '</li>';
                        echo '</ul>';
                        echo '</div>';
                        
                        // Log de éxito
                        error_log('GICA FCM: Configuración guardada exitosamente para proyecto: ' . $firebase_project_id);
                    }
                } catch (Exception $e) {
                    echo '<div class="notice notice-error is-dismissible"><p>❌ Error inesperado: ' . esc_html($e->getMessage()) . '</p></div>';
                    error_log('GICA FCM Exception: ' . $e->getMessage());
                }
            }
        }
        
        // Handle test notification
        if (isset($_POST['gica_fcm_test']) && wp_verify_nonce($_POST['gica_fcm_test_nonce'], 'gica_fcm_test')) {
            $this->handle_test_notification();
        }
        
        
        // Get current options with default values
        $fcm_enabled = get_option('gica_fcm_enabled', true); // Habilitado por defecto
        $fcm_debug_mode = get_option('gica_fcm_debug_mode', true); // Debug habilitado por defecto
        $firebase_api_key = get_option('gica_firebase_api_key', 'AIzaSyAGududTxCJe5ySMw6lLkpkyE2U09PCOqg');
        $firebase_auth_domain = get_option('gica_firebase_auth_domain', 'gicaform-notifications.firebaseapp.com');
        $firebase_project_id = get_option('gica_firebase_project_id', 'gicaform-notifications');
        $firebase_storage_bucket = get_option('gica_firebase_storage_bucket', 'gicaform-notifications.firebasestorage.app');
        $firebase_messaging_sender_id = get_option('gica_firebase_messaging_sender_id', '714103885883');
        $firebase_app_id = get_option('gica_firebase_app_id', '1:714103885883:web:2f6a575a362d1aa7f50c1e');
        $fcm_vapid_key = get_option('gica_fcm_vapid_key', 'BCicDeGJILLhRQ_5zu_PEIW6-fmkQ2ysnOjpQ2X2cgbADdAPKKn6e-ZbTpIWp7lr3IPaA15tXDC_NgAaIQc15w8');
        ?>
        <div class="gica-fcm-content">
            <div class="gica-section-header">
                <h2><span class="dashicons dashicons-smartphone"></span>Firebase Cloud Messaging</h2>
                <p>Sistema completo de notificaciones push para registro de usuarios</p>
            </div>
            
            <?php
            // Get current configuration status
            $fcm_enabled = get_option('gica_fcm_enabled', false);
            $firebase_project_id = get_option('gica_firebase_project_id', '');
            $fcm_service_account = get_option('gica_fcm_service_account_json', '');
            $is_configured = !empty($fcm_service_account) && $fcm_enabled;
            ?>
            
            <!-- FCM Sub-tabs -->
            <div class="gica-fcm-tabs-wrapper" style="margin-top: 20px;">
                <div class="gica-fcm-tabs" style="border-bottom: 1px solid #ddd; margin-bottom: 20px;">
                    <button type="button" class="gica-fcm-subtab active" data-subtab="config" style="background: #0073aa; color: white; border: none; padding: 10px 20px; margin-right: 5px; cursor: pointer; border-radius: 4px 4px 0 0;">
                        🔑 Configuración
                    </button>
                    <button type="button" class="gica-fcm-subtab" data-subtab="test" style="background: #f1f1f1; color: #333; border: none; padding: 10px 20px; margin-right: 5px; cursor: pointer; border-radius: 4px 4px 0 0;">
                        🧪 Pruebas
                    </button>
                    <button type="button" class="gica-fcm-subtab" data-subtab="instructions" style="background: #f1f1f1; color: #333; border: none; padding: 10px 20px; margin-right: 5px; cursor: pointer; border-radius: 4px 4px 0 0;">
                        📖 Instrucciones
                    </button>
                    <button type="button" class="gica-fcm-subtab" data-subtab="devices" style="background: #f1f1f1; color: #333; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px 4px 0 0;">
                        📱 Dispositivos
                    </button>
                </div>
                
                <!-- Configuration Tab -->
                <div id="gica-fcm-config" class="gica-fcm-subtab-content active">
                    <div class="gica-form-section" style="border: 2px solid <?php echo $is_configured ? '#46b450' : '#dc3232'; ?>; border-radius: 8px; padding: 20px;">
                        <h3>🔑 Configuración Firebase Cloud Messaging</h3>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <h4>📊 Estado Actual:</h4>
                                <p><span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: <?php echo $fcm_enabled ? '#46b450' : '#dc3232'; ?>; margin-right: 8px;"></span>FCM: <?php echo $fcm_enabled ? '✅ Habilitado' : '❌ Deshabilitado'; ?></p>
                                <p><span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: <?php echo !empty($firebase_project_id) ? '#46b450' : '#dc3232'; ?>; margin-right: 8px;"></span>Project ID: <?php echo $firebase_project_id ?: '❌ No configurado'; ?></p>
                                <p><span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: <?php echo !empty($fcm_service_account) ? '#46b450' : '#dc3232'; ?>; margin-right: 8px;"></span>Service Account: <?php echo !empty($fcm_service_account) ? '✅ Configurado' : '❌ Faltante'; ?></p>
                            </div>
                            
                            <?php if (!empty($fcm_service_account)): 
                                $service_data = json_decode($fcm_service_account, true);
                            ?>
                            <div>
                                <h4>🔧 Detalles Service Account:</h4>
                                <p><strong>Project ID:</strong> <?php echo esc_html($service_data['project_id'] ?? 'N/A'); ?></p>
                                <p><strong>Client Email:</strong> <code style="font-size: 11px;"><?php echo esc_html($service_data['client_email'] ?? 'N/A'); ?></code></p>
                                <p><strong>Configurado:</strong> <?php echo date('d/m/Y H:i', strtotime(get_option('gica_fcm_configured_at', 'now'))); ?></p>
                                
                                <?php
                                global $wpdb;
                                $table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
                                $device_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_active = 1");
                                ?>
                                <p><strong>Dispositivos registrados:</strong> <?php echo $device_count; ?> dispositivos activos</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!$is_configured): ?>
                        <div class="notice notice-warning inline">
                            <p><strong>⚠️ FCM HTTP v1 API requerido</strong></p>
                            <p>Para enviar notificaciones, necesitas configurar el Service Account JSON de Firebase. Ve a la pestaña <strong>"📖 Instrucciones"</strong> para ver los pasos detallados.</p>
                        </div>
                        <?php else: ?>
                        <div class="notice notice-success inline">
                            <p><strong>✅ FCM configurado correctamente</strong></p>
                            <p>Las notificaciones se enviarán automáticamente cuando se registren nuevos usuarios usando el shortcode <code>[gica_account]</code>.</p>
                        </div>
                        <?php endif; ?>
                        
                        <form method="post" action="" style="margin-top: 20px;">
                            <?php wp_nonce_field('gica_service_account_save', 'gica_service_account_nonce'); ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Habilitar FCM</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="fcm_enabled" value="1" <?php checked($fcm_enabled); ?>>
                                            ✅ Activar notificaciones push automáticas cuando se registren nuevos usuarios
                                        </label>
                                        <p class="description">Las notificaciones se enviarán al dispositivo más reciente registrado.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gica_service_account_json">Service Account JSON</label>
                                    </th>
                                    <td>
                                        <textarea id="gica_service_account_json" name="gica_service_account_json" 
                                                  rows="8" cols="80" class="large-text code" 
                                                  placeholder='Pega aquí el contenido completo del archivo JSON:
{
  "type": "service_account",
  "project_id": "gicaform-notifications",
  "private_key_id": "...",
  "private_key": "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n",
  "client_email": "firebase-adminsdk-...@gicaform-notifications.iam.gserviceaccount.com",
  ...
}'><?php echo esc_textarea($fcm_service_account); ?></textarea>
                                        <p class="description">
                                            Pega el contenido completo del archivo JSON descargado de Firebase Console.<br>
                                            <strong>Importante:</strong> Este JSON contiene credenciales privadas. No lo compartas públicamente.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <input type="submit" name="gica_service_account_submit" class="button-primary" value="💾 Guardar Configuración FCM">
                                <?php if (!empty($fcm_service_account)): ?>
                                <button type="button" class="button button-secondary" onclick="if(confirm('¿Borrar la configuración del Service Account?')) { document.getElementById('gica_service_account_json').value=''; this.form.submit(); }">
                                    🗑️ Borrar Configuración
                                </button>
                                <?php endif; ?>
                            </p>
                        </form>
                    </div>
                </div>
                
                <!-- Test Tab -->
                <div id="gica-fcm-test" class="gica-fcm-subtab-content" style="display: none;">
                    <?php if ($is_configured): ?>
                    <div class="gica-form-section" style="border: 2px solid #0073aa; border-radius: 8px; padding: 20px; background: #f0f8ff;">
                        <h3>🧪 Probar Notificaciones</h3>
                        <p>FCM está configurado correctamente. Puedes probar el envío de notificaciones.</p>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 20px 0;">
                            <div>
                                <h4>📱 Test General</h4>
                                <form method="post" action="">
                                    <?php wp_nonce_field('gica_fcm_test', 'gica_fcm_test_nonce'); ?>
                                    <input type="submit" name="gica_fcm_test" class="button button-primary button-large" value="📱 Enviar Notificación de Prueba">
                                </form>
                                <p style="color: #666; margin-top: 10px;">
                                    <strong>Nota:</strong> Se enviará al dispositivo más reciente registrado.
                                </p>
                            </div>
                            
                            <div>
                                <h4>📊 Información de Test</h4>
                                <?php
                                global $wpdb;
                                $table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
                                $latest_device = $wpdb->get_row("SELECT * FROM $table_name WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1");
                                ?>
                                
                                <?php if ($latest_device): ?>
                                <p><strong>Dispositivo objetivo:</strong><br>
                                   <code><?php echo esc_html($latest_device->device_info); ?></code></p>
                                <p><strong>Última actualización:</strong><br>
                                   <?php echo human_time_diff(strtotime($latest_device->updated_at), current_time('timestamp')); ?> ago</p>
                                <p><strong>Token preview:</strong><br>
                                   <code style="font-size: 10px;"><?php echo substr($latest_device->fcm_token, 0, 40); ?>...</code></p>
                                <?php else: ?>
                                <div class="notice notice-warning inline">
                                    <p>⚠️ No hay dispositivos registrados para enviar notificaciones de prueba.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="background: white; padding: 15px; border-radius: 4px; margin-top: 20px; border-left: 4px solid #0073aa;">
                            <h4>💡 Tips para Testing:</h4>
                            <ul>
                                <li>Asegúrate de que tu dispositivo móvil tenga la app FCM instalada y funcionando</li>
                                <li>Verifica que los permisos de notificación estén habilitados</li>
                                <li>Las notificaciones pueden tardar unos segundos en aparecer</li>
                                <li>Revisa la pestaña "📱 Dispositivos" para hacer tests específicos por dispositivo</li>
                            </ul>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="notice notice-error">
                        <p><strong>❌ FCM no está configurado</strong></p>
                        <p>Ve a la pestaña <strong>"🔑 Configuración"</strong> para configurar FCM antes de poder hacer pruebas.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Instructions Tab -->
                <div id="gica-fcm-instructions" class="gica-fcm-subtab-content" style="display: none;"><?php echo $this->render_fcm_instructions(); ?></div>
                
                <!-- Devices Tab -->
                <div id="gica-fcm-devices" class="gica-fcm-subtab-content" style="display: none;"><?php echo $this->render_mobile_devices_section(); ?></div>
            </div>
            
            <!-- JavaScript for FCM Sub-tabs -->
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // FCM Sub-tabs functionality
                const fcmSubtabs = document.querySelectorAll('.gica-fcm-subtab');
                const fcmSubtabContents = document.querySelectorAll('.gica-fcm-subtab-content');
                
                fcmSubtabs.forEach(function(tab) {
                    tab.addEventListener('click', function() {
                        const targetSubtab = this.dataset.subtab;
                        
                        // Remove active class from all subtabs and contents
                        fcmSubtabs.forEach(t => {
                            t.classList.remove('active');
                            t.style.background = '#f1f1f1';
                            t.style.color = '#333';
                        });
                        fcmSubtabContents.forEach(c => {
                            c.classList.remove('active');
                            c.style.display = 'none';
                        });
                        
                        // Add active class to clicked subtab and corresponding content
                        this.classList.add('active');
                        this.style.background = '#0073aa';
                        this.style.color = 'white';
                        
                        const targetContent = document.getElementById('gica-fcm-' + targetSubtab);
                        if (targetContent) {
                            targetContent.classList.add('active');
                            targetContent.style.display = 'block';
                        }
                    });
                });
            });
            </script>
        </div>
        
        <style>
        .gica-status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .gica-status-indicator.active {
            background-color: #46b450;
        }
        .gica-status-indicator.inactive {
            background-color: #dc3232;
        }
        .gica-mobile-devices {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .gica-devices-table {
            width: 100%;
            border-collapse: collapse;
        }
        .gica-devices-table th,
        .gica-devices-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .gica-devices-table th {
            background: #f1f1f1;
            font-weight: 600;
        }
        .device-status-active {
            color: #46b450;
            font-weight: bold;
        }
        .device-status-inactive {
            color: #dc3232;
        }
        </style>
        
        <?php
    }
    
    private function send_test_notification() {
        try {
            $fcm_service = new GicaFCMService();
            $result = $fcm_service->send_test_notification();
            
            if ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>✅ Notificación de prueba enviada correctamente</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>❌ Error al enviar la notificación de prueba</p></div>';
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-error is-dismissible"><p>❌ Error: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
    
    
    private function get_complete_profiles_count() {
        $users = get_users(array('number' => -1));
        $complete_count = 0;
        
        foreach ($users as $user) {
            try {
                $gica_user = new GicaUser($user->ID);
                if ($gica_user->has_complete_profile()) {
                    $complete_count++;
                }
            } catch (Exception $e) {
                // Skip this user if there's an error
                continue;
            }
        }
        
        return $complete_count;
    }
    
    /**
     * Render mobile devices table
     */
    private function render_mobile_devices_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'gica_mobile_fcm_tokens';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            return '<div class="notice notice-info inline"><p>📱 No hay dispositivos registrados aún. Los dispositivos aparecerán aquí cuando tu app Android envíe el token FCM.</p></div>';
        }
        
        // Get mobile devices
        $devices = $wpdb->get_results("
            SELECT * FROM $table_name 
            WHERE platform = 'android'
            ORDER BY updated_at DESC
            LIMIT 20
        ");
        
        if (empty($devices)) {
            return '<div class="notice notice-info inline"><p>📱 No hay dispositivos móviles registrados aún.</p>
                    <p><strong>Para registrar tu dispositivo Android:</strong></p>
                    <ol>
                        <li>Instala tu app Android con la implementación FCM</li>
                        <li>Abre la app por primera vez</li>
                        <li>Acepta los permisos de notificación</li>
                        <li>El dispositivo aparecerá automáticamente en esta tabla</li>
                    </ol>
                    </div>';
        }
        
        $html = '<div class="gica-devices-table-wrapper">';
        $html .= '<table class="gica-devices-table wp-list-table widefat fixed striped">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>📱 Dispositivo</th>';
        $html .= '<th>📅 Registrado</th>';
        $html .= '<th>🔄 Última Actualización</th>';
        $html .= '<th>📲 App Version</th>';
        $html .= '<th>🔔 Estado</th>';
        $html .= '<th>🔑 Token (Preview)</th>';
        $html .= '<th>⚡ Acciones</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        foreach ($devices as $device) {
            $is_active = $device->is_active && !empty($device->fcm_token);
            $status_class = $is_active ? 'device-status-active' : 'device-status-inactive';
            $status_text = $is_active ? '✅ Activo' : '❌ Inactivo';
            
            $last_activity = $device->last_notification_at 
                ? human_time_diff(strtotime($device->last_notification_at), current_time('timestamp')) . ' ago'
                : 'Nunca';
                
            $token_preview = !empty($device->fcm_token) 
                ? substr($device->fcm_token, 0, 30) . '...'
                : 'Sin token';
            
            $html .= '<tr>';
            $html .= '<td>';
            $html .= '<strong>' . esc_html($device->device_info ?: 'Android Device') . '</strong><br>';
            $html .= '<small style="color: #666;">ID: ' . esc_html($device->device_id) . '</small>';
            $html .= '</td>';
            $html .= '<td>' . date('d/m/Y H:i', strtotime($device->created_at)) . '</td>';
            $html .= '<td>' . date('d/m/Y H:i', strtotime($device->updated_at)) . '</td>';
            $html .= '<td>' . esc_html($device->app_version ?: 'N/A') . '</td>';
            $html .= '<td><span class="' . $status_class . '">' . $status_text . '</span></td>';
            $html .= '<td><code style="font-size: 11px;">' . $token_preview . '</code></td>';
            $html .= '<td>';
            
            if ($is_active && !empty($device->fcm_token)) {
                $html .= '<button type="button" class="button button-small" onclick="testDeviceNotification(\'' . esc_js($device->device_id) . '\', \'' . esc_js($device->fcm_token) . '\')">';
                $html .= '🧪 Test';
                $html .= '</button>';
            } else {
                $html .= '<span style="color: #999;">Sin token</span>';
            }
            
            $html .= '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        
        // Add JavaScript for testing
        $html .= '<script>
        function testDeviceNotification(deviceId, token) {
            if (!confirm("¿Enviar notificación de prueba a este dispositivo?")) {
                return;
            }
            
            // Save the current device token for testing
            fetch(ajaxurl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: new URLSearchParams({
                    action: "gica_set_test_device_token",
                    device_id: deviceId,
                    fcm_token: token,
                    nonce: "' . wp_create_nonce('gica_test_device') . '"
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Now send test notification
                    return fetch(ajaxurl, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                        },
                        body: new URLSearchParams({
                            action: "gica_send_test_notification",
                            nonce: "' . wp_create_nonce('gica_firebase_nonce') . '"
                        })
                    });
                } else {
                    throw new Error("Error configurando dispositivo de prueba");
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("✅ Notificación de prueba enviada exitosamente!");
                    location.reload();
                } else {
                    alert("❌ Error enviando notificación: " + (data.data?.message || "Error desconocido"));
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("❌ Error: " + error.message);
            });
        }
        </script>';
        
        // Add device statistics
        $total_devices = count($devices);
        $active_devices = count(array_filter($devices, function($d) { return $d->is_active; }));
        
        $html .= '<div style="margin-top: 15px; padding: 10px; background: #fff; border-radius: 4px; border-left: 4px solid #0073aa;">';
        $html .= '<strong>📊 Estadísticas:</strong> ';
        $html .= $total_devices . ' dispositivos registrados, ';
        $html .= $active_devices . ' activos';
        if ($total_devices > 20) {
            $html .= ' (mostrando los 20 más recientes)';
        }
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Handle Service Account JSON save
     */
    private function handle_service_account_save() {
        try {
            // Handle FCM enabled checkbox
            $fcm_enabled = isset($_POST['fcm_enabled']);
            update_option('gica_fcm_enabled', $fcm_enabled);
            
            $service_account_json = wp_unslash($_POST['gica_service_account_json'] ?? '');
            
            // If JSON is empty and FCM is being enabled, require it
            if ($fcm_enabled && empty($service_account_json)) {
                throw new Exception('Service Account JSON es requerido para habilitar FCM');
            }
            
            // If JSON is provided, validate and save it
            if (!empty($service_account_json)) {
                // Validate JSON format
                $service_account_data = json_decode($service_account_json, true);
                if (!$service_account_data) {
                    throw new Exception('Formato JSON inválido');
                }
                
                // Validate required fields
                $required_fields = ['type', 'project_id', 'private_key_id', 'private_key', 'client_email'];
                foreach ($required_fields as $field) {
                    if (!isset($service_account_data[$field])) {
                        throw new Exception("Campo requerido faltante: $field");
                    }
                }
                
                // Validate it's a service account
                if ($service_account_data['type'] !== 'service_account') {
                    throw new Exception('No es un Service Account JSON válido');
                }
                
                // Save the service account JSON
                update_option('gica_fcm_service_account_json', $service_account_json);
                
                // Update project ID
                update_option('gica_firebase_project_id', $service_account_data['project_id']);
                
                // Save configured timestamp
                update_option('gica_fcm_configured_at', current_time('mysql'));
                
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>✅ Configuración FCM guardada exitosamente</strong></p>';
                echo '<ul>';
                echo '<li>FCM: ' . ($fcm_enabled ? 'Habilitado' : 'Deshabilitado') . '</li>';
                echo '<li>Project ID: ' . esc_html($service_account_data['project_id']) . '</li>';
                echo '<li>Client Email: ' . esc_html($service_account_data['client_email']) . '</li>';
                echo '</ul>';
                echo '</div>';
            } else {
                // Only FCM enabled status was changed
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>✅ Estado FCM actualizado:</strong> ' . ($fcm_enabled ? 'Habilitado' : 'Deshabilitado') . '</p>';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>❌ Error guardando configuración:</strong> ' . esc_html($e->getMessage()) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Handle test notification
     */
    private function handle_test_notification() {
        try {
            // Check if FCM is configured
            if (!get_option('gica_fcm_enabled', false)) {
                throw new Exception('FCM no está habilitado');
            }
            
            $service_account = get_option('gica_fcm_service_account_json', '');
            if (empty($service_account)) {
                throw new Exception('Service Account JSON no está configurado');
            }
            
            // Load FCM service
            $fcm_file = plugin_dir_path(__FILE__) . '../class-fcm-service.php';
            if (!file_exists($fcm_file)) {
                throw new Exception('Archivo FCM Service no encontrado');
            }
            
            require_once $fcm_file;
            
            if (!class_exists('GICA_FCM_Service')) {
                throw new Exception('Clase GICA_FCM_Service no encontrada');
            }
            
            $fcm_service = new GICA_FCM_Service();
            
            if (!$fcm_service->is_configured()) {
                throw new Exception('FCM Service no está configurado correctamente');
            }
            
            // Send test notification
            $result = $fcm_service->send_notification(
                'Test desde Panel Admin',
                'Notificación de prueba enviada desde el panel de administración. ¡FCM funciona correctamente!',
                [
                    'source' => 'admin_panel',
                    'test' => 'true',
                    'timestamp' => (string) time()
                ]
            );
            
            if ($result['success']) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>✅ Notificación de prueba enviada exitosamente</strong></p>';
                echo '<p>Verifica tu dispositivo móvil para confirmar la recepción.</p>';
                echo '</div>';
            } else {
                throw new Exception('FCM envío falló: ' . ($result['error'] ?? 'Error desconocido'));
            }
            
        } catch (Exception $e) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>❌ Error enviando notificación de prueba:</strong> ' . esc_html($e->getMessage()) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Render FCM instructions section
     */
    private function render_fcm_instructions() {
        ob_start();
        ?>
        <div class="gica-fcm-help">
            <h3>📖 ¿Cómo obtener el Service Account JSON?</h3>
            <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #0073aa;">
                <p><strong>El Service Account JSON es lo ÚNICO que necesitas</strong> para que FCM funcione con la API HTTP v1 moderna.</p>
                
                <h4>🚀 Pasos para obtenerlo (5 minutos):</h4>
                <ol style="line-height: 1.6;">
                    <li><strong>Ve a la <a href="https://console.firebase.google.com/" target="_blank" rel="noopener">Firebase Console</a></strong></li>
                    <li><strong>Selecciona tu proyecto</strong> (por ejemplo: <code>gicaform-notifications</code>)</li>
                    <li><strong>Ve a Configuración del proyecto</strong> (icono ⚙️ en la esquina superior izquierda)</li>
                    <li><strong>Haz clic en la pestaña "Cuentas de servicio"</strong></li>
                    <li><strong>Haz clic en "Generar nueva clave privada"</strong> (botón azul)</li>
                    <li><strong>Se descargará un archivo JSON</strong> - ábrelo con un editor de texto</li>
                    <li><strong>Copia TODO el contenido del archivo</strong> y pégalo en el campo de la pestaña "🔑 Configuración"</li>
                    <li><strong>Haz clic en "Guardar Configuración FCM"</strong></li>
                </ol>
                
                <div style="background: #d4edda; padding: 15px; margin: 15px 0; border-radius: 4px; border-left: 4px solid #28a745;">
                    <h4 style="color: #155724; margin-top: 0;">✅ ¡Eso es todo!</h4>
                    <p style="color: #155724; margin-bottom: 0;">Una vez guardado el Service Account JSON, FCM estará completamente configurado y las notificaciones funcionarán automáticamente cuando se registren nuevos usuarios.</p>
                </div>
            </div>
            
            <h3>📱 ¿Cómo registrar mi dispositivo móvil?</h3>
            <div style="background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #856404; margin-top: 20px;">
                <p style="color: #856404;"><strong>Para recibir notificaciones necesitas una app Android</strong> que implemente Firebase Cloud Messaging.</p>
                
                <h4>🔧 Opciones para obtener tokens FCM:</h4>
                <ul style="line-height: 1.6;">
                    <li><strong>App Android personalizada:</strong> Si tienes una app Android, implementa FCM y usa el endpoint:<br>
                        <code style="background: #f8f9fa; padding: 2px 6px; border-radius: 3px;">/wp-content/plugins/GICAACCOUNT/mobile-api.php</code>
                    </li>
                    <li><strong>App de prueba FCM:</strong> Busca "FCM Test" o "Firebase Test" en Google Play Store</li>
                    <li><strong>Navegador web:</strong> Usa la <a href="https://firebase.google.com/docs/cloud-messaging/js/client" target="_blank" rel="noopener">Firebase Web SDK</a> para obtener tokens desde el navegador</li>
                    <li><strong>Herramientas online:</strong> Hay servicios web que generan tokens FCM para testing</li>
                </ul>
                
                <p style="margin-bottom: 0;"><strong>💡 Tip:</strong> Una vez que tengas un token FCM, aparecerá automáticamente en la pestaña "📱 Dispositivos".</p>
            </div>
            
            <h3>🔐 Seguridad del Service Account</h3>
            <div style="background: #f8d7da; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545; margin-top: 20px;">
                <p style="color: #721c24;"><strong>⚠️ IMPORTANTE:</strong> El Service Account JSON contiene credenciales privadas.</p>
                <ul style="color: #721c24; line-height: 1.6;">
                    <li><strong>No lo compartas públicamente</strong> ni lo subas a repositorios de código</li>
                    <li><strong>Solo pégalo en este campo seguro</strong> del panel de administración</li>
                    <li><strong>Puedes regenerar una nueva clave</strong> en Firebase Console si se compromete</li>
                    <li><strong>Solo administradores</strong> de WordPress pueden ver esta configuración</li>
                </ul>
            </div>
            
            <h3>🎯 ¿Cómo funciona el sistema completo?</h3>
            <div style="background: #e7f3ff; padding: 20px; border-radius: 8px; border-left: 4px solid #2271b1; margin-top: 20px;">
                <ol style="line-height: 1.8;">
                    <li><strong>Usuario se registra:</strong> Alguien usa el shortcode <code>[gica_account]</code> para registrarse</li>
                    <li><strong>Notificación automática:</strong> El sistema envía una notificación FCM instantáneamente</li>
                    <li><strong>Recibes alerta:</strong> Tu dispositivo móvil recibe: "🎉 Nuevo Usuario Registrado - ¡[Nombre] se ha registrado!"</li>
                    <li><strong>Información completa:</strong> La notificación incluye nombre, email, fecha y más datos</li>
                </ol>
                
                <p><strong>💡 Es completamente automático:</strong> No necesitas hacer nada más después de la configuración inicial.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render mobile devices section
     */
    private function render_mobile_devices_section() {
        ob_start();
        ?>
        <div class="gica-mobile-devices">
            <h3>📱 Dispositivos Móviles Registrados</h3>
            <p>Lista de dispositivos Android que han registrado tokens FCM para recibir notificaciones.</p>
            <?php echo $this->render_mobile_devices_table(); ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render shortcode preview for admin
     */
    private function render_shortcode_preview() {
        ob_start();
        
        // Enqueue the frontend styles for preview
        wp_enqueue_style('gica-account-style', GICA_ACCOUNT_PLUGIN_URL . 'assets/style.css', array(), '1.0.0');
        
        ?>
        <div id="gica-account-container" style="margin: 0; max-width: 100%;">
            <div class="gica-header">
                <h2>Mi Cuenta</h2>
            </div>
            
            <!-- Preview when user is not logged in -->
            <div class="gica-auth-container">
                <div class="gica-auth-tabs">
                    <button class="gica-tab-btn active" data-tab="login" onclick="return false;">Acceder</button>
                    <button class="gica-tab-btn" data-tab="register" onclick="return false;">Registro</button>
                </div>
                
                <div class="gica-auth-content">
                    <div id="gica-login-tab" class="gica-tab-content active">
                        <form id="gica-login-form" onsubmit="return false;">
                            <div class="gica-field">
                                <div class="gica-input-wrapper">
                                    <span class="gica-input-icon">👤</span>
                                    <input type="text" name="username" placeholder="Usuario o correo electrónico" disabled>
                                </div>
                            </div>
                            <div class="gica-field">
                                <div class="gica-input-wrapper">
                                    <span class="gica-input-icon">🔒</span>
                                    <input type="password" name="password" placeholder="Tu contraseña" disabled>
                                    <button type="button" class="gica-toggle-password" onclick="return false;">👁</button>
                                </div>
                            </div>
                            <div class="gica-field-options">
                                <label class="gica-checkbox">
                                    <input type="checkbox" name="remember" disabled>
                                    <span class="gica-checkmark"></span>
                                    <span class="checkbox-text">Recuérdame</span>
                                </label>
                                <a href="#" class="gica-forgot-password" onclick="return false;">¿Olvidaste tu contraseña?</a>
                            </div>
                            <button type="submit" class="gica-btn gica-btn-primary gica-btn-full gica-btn-animated" disabled>
                                <span class="btn-text">ACCEDER</span>
                                <span class="btn-loader">🔄</span>
                            </button>
                        </form>
                    </div>
                    
                    <div id="gica-register-tab" class="gica-tab-content" style="display: none;">
                        <form id="gica-register-form" onsubmit="return false;">
                            <div class="gica-field-group">
                                <div class="gica-field">
                                    <div class="gica-input-wrapper">
                                        <span class="gica-input-icon">👤</span>
                                        <input type="text" name="username" placeholder="Nombre de usuario" disabled>
                                    </div>
                                </div>
                                <div class="gica-field">
                                    <div class="gica-input-wrapper">
                                        <span class="gica-input-icon">📧</span>
                                        <input type="email" name="email" placeholder="Correo electrónico" disabled>
                                    </div>
                                </div>
                            </div>
                            <div class="gica-field-group">
                                <div class="gica-field">
                                    <div class="gica-input-wrapper">
                                        <span class="gica-input-icon">👨</span>
                                        <input type="text" name="first_name" placeholder="Nombre" disabled>
                                    </div>
                                </div>
                                <div class="gica-field">
                                    <div class="gica-input-wrapper">
                                        <span class="gica-input-icon">👥</span>
                                        <input type="text" name="last_name" placeholder="Apellidos" disabled>
                                    </div>
                                </div>
                            </div>
                            <div class="gica-field">
                                <div class="gica-input-wrapper">
                                    <span class="gica-input-icon">🔒</span>
                                    <input type="password" name="password" placeholder="Contraseña (mínimo 6 caracteres)" disabled>
                                    <button type="button" class="gica-toggle-password" onclick="return false;">👁</button>
                                </div>
                            </div>
                            <div class="gica-field">
                                <div class="gica-input-wrapper">
                                    <span class="gica-input-icon">🔐</span>
                                    <input type="password" name="confirm_password" placeholder="Confirmar contraseña" disabled>
                                    <button type="button" class="gica-toggle-password" onclick="return false;">👁</button>
                                </div>
                            </div>
                            <button type="submit" class="gica-btn gica-btn-primary gica-btn-full gica-btn-animated" disabled>
                                <span class="btn-text">REGISTRARSE</span>
                                <span class="btn-loader">🔄</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-radius: 8px; border-left: 4px solid #3498db;">
                <h4 style="margin: 0 0 10px 0; color: #2c3e50;">📝 Vista Previa - Solo Demostración</h4>
                <p style="margin: 0; color: #7f8c8d; font-size: 13px;">
                    Esta es una vista previa del shortcode. Los botones e inputs están deshabilitados para propósitos de demostración.
                    En el frontend real, todos los elementos serán completamente funcionales.
                </p>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add tab switching functionality for preview only
            const previewTabs = document.querySelectorAll('.gica-preview-styled .gica-tab-btn');
            const previewContents = document.querySelectorAll('.gica-preview-styled .gica-tab-content');
            
            previewTabs.forEach(function(tab) {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const targetTab = this.getAttribute('data-tab');
                    
                    // Remove active class from all tabs
                    previewTabs.forEach(t => t.classList.remove('active'));
                    previewContents.forEach(c => {
                        c.classList.remove('active');
                        c.style.display = 'none';
                    });
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Show corresponding content
                    const targetContent = document.getElementById('gica-' + targetTab + '-tab');
                    if (targetContent) {
                        targetContent.classList.add('active');
                        targetContent.style.display = 'block';
                    }
                });
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
}