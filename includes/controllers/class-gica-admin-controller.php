<?php

if (!defined('ABSPATH')) {
    exit;
}

class GicaAdminController {
    
    public function __construct() {
        add_action('wp_ajax_gica_load_users', array($this, 'ajax_load_users'));
        add_action('wp_ajax_gica_search_users', array($this, 'ajax_search_users'));
        add_action('wp_ajax_gica_delete_user', array($this, 'ajax_delete_user'));
        add_action('wp_ajax_gica_get_user', array($this, 'ajax_get_user'));
        add_action('wp_ajax_gica_update_user', array($this, 'ajax_update_user'));
    }
    
    public function ajax_load_users() {
        check_ajax_referer('gica_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? max(1, intval($_POST['per_page'])) : 10;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        $offset = ($page - 1) * $per_page;
        
        if (!empty($search)) {
            $users = GicaUser::search_users($search);
            $total_users = count($users);
            $users = array_slice($users, $offset, $per_page);
        } else {
            $args = array(
                'number' => $per_page,
                'offset' => $offset,
                'orderby' => 'registered',
                'order' => 'DESC'
            );
            
            $users = GicaUser::get_all_users($args);
            $total_users = count_users()['total_users'];
        }
        
        $users_data = array();
        foreach ($users as $user) {
            $users_data[] = $this->format_user_for_table($user);
        }
        
        wp_send_json_success(array(
            'users' => $users_data,
            'pagination' => array(
                'current_page' => $page,
                'per_page' => $per_page,
                'total_users' => $total_users,
                'total_pages' => ceil($total_users / $per_page)
            )
        ));
    }
    
    public function ajax_search_users() {
        check_ajax_referer('gica_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search_term)) {
            wp_send_json_error('Término de búsqueda vacío');
        }
        
        $users = GicaUser::search_users($search_term);
        $users_data = array();
        
        foreach ($users as $user) {
            $users_data[] = $this->format_user_for_table($user);
        }
        
        wp_send_json_success(array(
            'users' => $users_data,
            'count' => count($users_data)
        ));
    }
    
    public function ajax_delete_user() {
        check_ajax_referer('gica_admin_nonce', 'nonce');
        
        if (!current_user_can('delete_users')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        if (!$user_id) {
            wp_send_json_error('ID de usuario inválido');
        }
        
        // Prevent deleting current user
        if ($user_id === get_current_user_id()) {
            wp_send_json_error('No puedes eliminar tu propia cuenta');
        }
        
        try {
            $user = new GicaUser($user_id);
            $result = $user->delete();
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            
            wp_send_json_success('Usuario eliminado correctamente');
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_get_user() {
        check_ajax_referer('gica_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        if (!$user_id) {
            wp_send_json_error('ID de usuario inválido');
        }
        
        try {
            $user = new GicaUser($user_id);
            wp_send_json_success($user->to_array());
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_update_user() {
        check_ajax_referer('gica_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_users')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        if (!$user_id) {
            wp_send_json_error('ID de usuario inválido');
        }
        
        try {
            $user = new GicaUser($user_id);
            
            // Update user basic data
            $user_data = array();
            $fields = array('display_name', 'user_email', 'first_name', 'last_name');
            
            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    $user_data[$field] = sanitize_text_field($_POST[$field]);
                }
            }
            
            if (!empty($user_data)) {
                $result = $user->update_user_data($user_data);
                if (is_wp_error($result)) {
                    wp_send_json_error($result->get_error_message());
                }
            }
            
            // Update user meta
            $meta_data = array();
            $meta_fields = array('phone', 'address', 'dni', 'city', 'reference', 'country', 'region');
            
            foreach ($meta_fields as $field) {
                if (isset($_POST[$field])) {
                    $meta_data[$field] = $_POST[$field];
                }
            }
            
            if (!empty($meta_data)) {
                $user->update_meta($meta_data);
            }
            
            wp_send_json_success(array(
                'message' => 'Usuario actualizado correctamente',
                'user' => $user->to_array()
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    private function format_user_for_table($user) {
        $user_data = $user->to_array();
        
        return array(
            'ID' => $user_data['ID'],
            'username' => $user_data['username'],
            'display_name' => $user_data['display_name'],
            'email' => $user_data['email'],
            'first_name' => $user_data['first_name'],
            'last_name' => $user_data['last_name'],
            'registration_date' => $user_data['registration_date'],
            'phone' => $user_data['phone'],
            'dni' => $user_data['dni'],
            'city' => $user_data['city'],
            'country' => $user_data['country'],
            'region' => $user_data['region'],
            'address' => $user_data['address'],
            'reference' => $user_data['reference'],
            'completion_percentage' => $user->get_completion_percentage(),
            'has_complete_profile' => $user->has_complete_profile(),
            'formatted_date' => date('d/m/Y H:i', strtotime($user_data['registration_date']))
        );
    }
    
    public function render_user_modal() {
        ?>
        <div id="gica-user-modal" class="gica-modal" style="display: none;">
            <div class="gica-modal-backdrop"></div>
            <div class="gica-modal-content">
                <div class="gica-modal-header">
                    <h2 id="gica-modal-title">Editar Usuario</h2>
                    <button type="button" class="gica-modal-close" aria-label="Cerrar">&times;</button>
                </div>
                <div class="gica-modal-body">
                    <form id="gica-user-form">
                        <input type="hidden" id="user-id" name="user_id">
                        
                        <div class="gica-form-row">
                            <div class="gica-form-group">
                                <label for="display-name">Nombre de Usuario *</label>
                                <input type="text" id="display-name" name="display_name" required>
                            </div>
                            <div class="gica-form-group">
                                <label for="user-email">Email *</label>
                                <input type="email" id="user-email" name="user_email" required>
                            </div>
                        </div>
                        
                        <div class="gica-form-row">
                            <div class="gica-form-group">
                                <label for="first-name">Nombre</label>
                                <input type="text" id="first-name" name="first_name">
                            </div>
                            <div class="gica-form-group">
                                <label for="last-name">Apellido</label>
                                <input type="text" id="last-name" name="last_name">
                            </div>
                        </div>
                        
                        <div class="gica-form-row">
                            <div class="gica-form-group">
                                <label for="dni">DNI *</label>
                                <input type="text" id="dni" name="dni" required>
                            </div>
                            <div class="gica-form-group">
                                <label for="phone">Teléfono</label>
                                <input type="tel" id="phone" name="phone">
                            </div>
                        </div>
                        
                        <div class="gica-form-row">
                            <div class="gica-form-group">
                                <label for="city">Ciudad *</label>
                                <input type="text" id="city" name="city" required>
                            </div>
                            <div class="gica-form-group">
                                <label for="region">Región *</label>
                                <input type="text" id="region" name="region" required>
                            </div>
                        </div>
                        
                        <div class="gica-form-row">
                            <div class="gica-form-group full-width">
                                <label for="country">País *</label>
                                <input type="text" id="country" name="country" required>
                            </div>
                        </div>
                        
                        <div class="gica-form-row">
                            <div class="gica-form-group full-width">
                                <label for="address">Dirección</label>
                                <textarea id="address" name="address" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="gica-form-row">
                            <div class="gica-form-group full-width">
                                <label for="reference">Referencia (Opcional)</label>
                                <textarea id="reference" name="reference" rows="2"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="gica-modal-footer">
                    <button type="button" class="button button-secondary gica-modal-cancel">Cancelar</button>
                    <button type="submit" form="gica-user-form" class="button button-primary">Guardar Cambios</button>
                </div>
            </div>
        </div>
        <?php
    }
}