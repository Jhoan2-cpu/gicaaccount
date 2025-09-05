<?php

if (!defined('ABSPATH')) {
    exit;
}

class GicaShortcodeHandler {
    
    public function __construct() {
        // Constructor vacío, la inicialización se hace en el archivo principal
    }
    
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('gica-account-style', GICA_ACCOUNT_PLUGIN_URL . 'assets/style.css', array(), '1.0.0');
        wp_enqueue_script('gica-account-script', GICA_ACCOUNT_PLUGIN_URL . 'assets/script.js', array('jquery'), '1.0.0', true);
        wp_localize_script('gica-account-script', 'gica_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gica_account_nonce')
        ));
        
        // Enqueue Firebase scripts if FCM is enabled
        if (get_option('gica_fcm_enabled', false) && !empty(get_option('gica_firebase_project_id'))) {
            // Firebase SDK scripts
            wp_enqueue_script('firebase-app', 'https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js', array(), '9.23.0', true);
            wp_enqueue_script('firebase-messaging', 'https://www.gstatic.com/firebasejs/9.23.0/firebase-messaging-compat.js', array('firebase-app'), '9.23.0', true);
            
            // Our Firebase initialization script
            wp_enqueue_script('gica-firebase-init', GICA_ACCOUNT_PLUGIN_URL . 'assets/js/firebase-init.js', array('firebase-messaging'), '1.0.0', true);
            
            // Pass Firebase configuration to frontend
            wp_localize_script('gica-firebase-init', 'gicaFirebaseConfig', array(
                'config' => array(
                    'apiKey' => get_option('gica_firebase_api_key', ''),
                    'authDomain' => get_option('gica_firebase_auth_domain', ''),
                    'projectId' => get_option('gica_firebase_project_id', ''),
                    'storageBucket' => get_option('gica_firebase_storage_bucket', ''),
                    'messagingSenderId' => get_option('gica_firebase_messaging_sender_id', ''),
                    'appId' => get_option('gica_firebase_app_id', '')
                ),
                'vapidKey' => get_option('gica_fcm_vapid_key', ''),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gica_firebase_nonce')
            ));
        }
    }
    
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Mi Cuenta',
            'description' => 'Administra tu cuenta desde aquí'
        ), $atts);
        
        ob_start();
        ?>
        <div id="gica-account-container">
            <div class="gica-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
            </div>
            
            <?php if (!is_user_logged_in()): ?>
                <div class="gica-auth-container">
                    <div class="gica-auth-tabs">
                        <button class="gica-tab-btn active" data-tab="login">Acceder</button>
                        <button class="gica-tab-btn" data-tab="register">Registro</button>
                    </div>
                    
                    <div class="gica-auth-content">
                        <div id="gica-login-tab" class="gica-tab-content active">
                            <form id="gica-login-form">
                                <div class="gica-field">
                                    <div class="gica-input-wrapper">
                                        <span class="gica-input-icon">👤</span>
                                        <input type="text" name="username" placeholder="Usuario o correo electrónico" required>
                                    </div>
                                </div>
                                <div class="gica-field">
                                    <div class="gica-input-wrapper">
                                        <span class="gica-input-icon">🔒</span>
                                        <input type="password" name="password" placeholder="Tu contraseña" required>
                                        <button type="button" class="gica-toggle-password">👁</button>
                                    </div>
                                </div>
                                <div class="gica-field-options">
                                    <label class="gica-checkbox">
                                        <input type="checkbox" name="remember">
                                        <span class="gica-checkmark"></span>
                                        <span class="checkbox-text">Recuérdame</span>
                                    </label>
                                    <a href="#" class="gica-forgot-password">¿Olvidaste tu contraseña?</a>
                                </div>
                                <button type="submit" class="gica-btn gica-btn-primary gica-btn-full gica-btn-animated">
                                    <span class="btn-text">ACCEDER</span>
                                    <span class="btn-loader">🔄</span>
                                </button>
                            </form>
                        </div>
                        
                        <div id="gica-register-tab" class="gica-tab-content">
                            <form id="gica-register-form">
                                <div class="gica-field-group">
                                    <div class="gica-field">
                                        <div class="gica-input-wrapper">
                                            <span class="gica-input-icon">👤</span>
                                            <input type="text" name="username" placeholder="Nombre de usuario" required>
                                        </div>
                                    </div>
                                    <div class="gica-field">
                                        <div class="gica-input-wrapper">
                                            <span class="gica-input-icon">📧</span>
                                            <input type="email" name="email" placeholder="Correo electrónico" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="gica-field-group">
                                    <div class="gica-field">
                                        <div class="gica-input-wrapper">
                                            <span class="gica-input-icon">👨</span>
                                            <input type="text" name="first_name" placeholder="Nombre" required>
                                        </div>
                                    </div>
                                    <div class="gica-field">
                                        <div class="gica-input-wrapper">
                                            <span class="gica-input-icon">👥</span>
                                            <input type="text" name="last_name" placeholder="Apellidos" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="gica-field">
                                    <div class="gica-input-wrapper">
                                        <span class="gica-input-icon">🔒</span>
                                        <input type="password" name="password" placeholder="Contraseña (mínimo 6 caracteres)" required>
                                        <button type="button" class="gica-toggle-password">👁</button>
                                    </div>
                                </div>
                                <div class="gica-field">
                                    <div class="gica-input-wrapper">
                                        <span class="gica-input-icon">🔐</span>
                                        <input type="password" name="confirm_password" placeholder="Confirmar contraseña" required>
                                        <button type="button" class="gica-toggle-password">👁</button>
                                    </div>
                                </div>
                                <button type="submit" class="gica-btn gica-btn-primary gica-btn-full gica-btn-animated">
                                    <span class="btn-text">REGISTRARSE</span>
                                    <span class="btn-loader">🔄</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="gica-account-interface">
                    <div class="gica-sidebar">
                        <nav class="gica-nav">
                            <button class="gica-nav-btn active" data-section="account-details">
                                <span class="gica-nav-icon">💻</span>
                                <span class="gica-nav-text">ESCRITORIO</span>
                            </button>
                            <button class="gica-nav-btn" data-section="contact-info">
                                <span class="gica-nav-icon">📋</span>
                                <span class="gica-nav-text">INFORMACIÓN DE CONTACTO</span>
                            </button>
                            <button class="gica-nav-btn" data-section="additional-info">
                                <span class="gica-nav-icon">👤</span>
                                <span class="gica-nav-text">DETALLES DE LA CUENTA</span>
                            </button>
                            <button class="gica-nav-btn" data-section="logout">
                                <span class="gica-nav-icon">🚪</span>
                                <span class="gica-nav-text">SALIR</span>
                            </button>
                        </nav>
                    </div>
                    <div class="gica-content">
                        <div id="gica-account-details" class="gica-section active">
                            <?php echo $this->render_account_details(); ?>
                        </div>
                        <div id="gica-contact-info" class="gica-section">
                            <?php echo $this->render_contact_info(); ?>
                        </div>
                        <div id="gica-additional-info" class="gica-section">
                            <?php echo $this->render_additional_info(); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_account_details() {
        $user = wp_get_current_user();
        ob_start();
        ?>
        <div class="gica-form-section">
            <h3>Detalles de Cuenta</h3>
            <form id="gica-account-form">
                <div class="gica-field">
                    <label for="display_name">Nombre de Usuario:</label>
                    <input type="text" id="display_name" name="display_name" value="<?php echo esc_attr($user->display_name); ?>">
                </div>
                <div class="gica-field">
                    <label for="user_email">Email:</label>
                    <input type="email" id="user_email" name="user_email" value="<?php echo esc_attr($user->user_email); ?>">
                </div>
                <div class="gica-field">
                    <label for="first_name">Nombre:</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($user->first_name); ?>">
                </div>
                <div class="gica-field">
                    <label for="last_name">Apellido:</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($user->last_name); ?>">
                </div>
                <button type="submit" class="gica-btn gica-btn-primary">Actualizar</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_contact_info() {
        $user = wp_get_current_user();
        ob_start();
        ?>
        <div class="gica-form-section">
            <h3>Información de Contacto</h3>
            <form id="gica-contact-form">
                <div class="gica-field">
                    <label for="phone">Teléfono:</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo esc_attr(get_user_meta($user->ID, 'phone', true)); ?>">
                </div>
                <div class="gica-field">
                    <label for="address">Dirección:</label>
                    <textarea id="address" name="address"><?php echo esc_textarea(get_user_meta($user->ID, 'address', true)); ?></textarea>
                </div>
                <button type="submit" class="gica-btn gica-btn-primary">Actualizar</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_additional_info() {
        $user = wp_get_current_user();
        $gica_user = new GicaUser($user->ID);
        $completion = $gica_user->get_completion_percentage();
        
        ob_start();
        ?>
        <div class="gica-form-section">
            <div class="gica-profile-completion">
                <h3>Información Adicional</h3>
                <div class="gica-completion-bar">
                    <div class="gica-completion-label">Perfil completado: <?php echo $completion; ?>%</div>
                    <div class="gica-completion-progress">
                        <div class="gica-completion-fill" style="width: <?php echo $completion; ?>%"></div>
                    </div>
                </div>
            </div>
            
            <form id="gica-additional-form">
                <div class="gica-field-row">
                    <div class="gica-field gica-field-half">
                        <label for="dni">DNI: <span class="required">*</span></label>
                        <input type="text" id="dni" name="dni" value="<?php echo esc_attr(get_user_meta($user->ID, 'dni', true)); ?>" required>
                    </div>
                    <div class="gica-field gica-field-half">
                        <label for="city">Ciudad: <span class="required">*</span></label>
                        <input type="text" id="city" name="city" value="<?php echo esc_attr(get_user_meta($user->ID, 'city', true)); ?>" required>
                    </div>
                </div>
                
                <div class="gica-field-row">
                    <div class="gica-field gica-field-half">
                        <label for="region">Región: <span class="required">*</span></label>
                        <input type="text" id="region" name="region" value="<?php echo esc_attr(get_user_meta($user->ID, 'region', true)); ?>" required>
                    </div>
                    <div class="gica-field gica-field-half">
                        <label for="country">País: <span class="required">*</span></label>
                        <input type="text" id="country" name="country" value="<?php echo esc_attr(get_user_meta($user->ID, 'country', true)); ?>" required>
                    </div>
                </div>
                
                <div class="gica-field location-field">
                    <label for="reference">Ubicación (Referencia):</label>
                    <textarea id="reference" name="reference" placeholder="Puntos de referencia, instrucciones de ubicación, etc."><?php echo esc_textarea(get_user_meta($user->ID, 'reference', true)); ?></textarea>
                </div>
                
                <button type="submit" class="gica-btn gica-btn-primary">Actualizar Información</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}