<?php

if (!defined('ABSPATH')) {
    exit;
}

class GicaApiController {
    
    private $namespace = 'gicaform/v1';
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Registrar las rutas de la API
     */
    public function register_routes() {
        // Ruta de login
        register_rest_route($this->namespace, '/auth/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'api_login'),
            'permission_callback' => '__return_true',
            'args' => array(
                'username' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array($this, 'validate_username')
                ),
                'password' => array(
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => array($this, 'validate_password')
                ),
                'device_info' => array(
                    'required' => false,
                    'type' => 'object',
                    'properties' => array(
                        'type' => array('type' => 'string'),
                        'platform' => array('type' => 'string'),
                        'app_version' => array('type' => 'string'),
                        'device_id' => array('type' => 'string')
                    )
                )
            )
        ));
        
        // Ruta de logout
        register_rest_route($this->namespace, '/auth/logout', array(
            'methods' => 'POST',
            'callback' => array($this, 'api_logout'),
            'permission_callback' => array($this, 'validate_auth_token')
        ));
        
        // Ruta para obtener información del usuario
        register_rest_route($this->namespace, '/user/profile', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_profile'),
            'permission_callback' => array($this, 'validate_auth_token')
        ));
        
        // Ruta para actualizar perfil del usuario
        register_rest_route($this->namespace, '/user/profile', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_user_profile'),
            'permission_callback' => array($this, 'validate_auth_token')
        ));
        
        // Ruta para cambiar contraseña del usuario
        register_rest_route($this->namespace, '/user/change-password', array(
            'methods' => 'POST',
            'callback' => array($this, 'change_user_password'),
            'permission_callback' => array($this, 'validate_auth_token'),
            'args' => array(
                'current_password' => array(
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => array($this, 'validate_password')
                ),
                'new_password' => array(
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => array($this, 'validate_new_password')
                ),
                'confirm_password' => array(
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => array($this, 'validate_password')
                )
            )
        ));
        
        // Ruta para obtener lista de usuarios (solo administradores)
        register_rest_route($this->namespace, '/admin/users', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_users_list'),
            'permission_callback' => array($this, 'validate_admin_permission'),
            'args' => array(
                'page' => array(
                    'default' => 1,
                    'type' => 'integer',
                    'minimum' => 1,
                    'sanitize_callback' => 'absint'
                ),
                'per_page' => array(
                    'default' => 20,
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'sanitize_callback' => 'absint'
                ),
                'search' => array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'role' => array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'orderby' => array(
                    'default' => 'registered',
                    'type' => 'string',
                    'enum' => array('ID', 'display_name', 'user_email', 'registered', 'user_login'),
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'order' => array(
                    'default' => 'DESC',
                    'type' => 'string',
                    'enum' => array('ASC', 'DESC'),
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // Ruta para obtener detalles de un usuario específico (solo administradores)
        register_rest_route($this->namespace, '/admin/users/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_details'),
            'permission_callback' => array($this, 'validate_admin_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                )
            )
        ));
        
        // Ruta para eliminar un usuario específico (solo administradores)
        register_rest_route($this->namespace, '/admin/users/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_user'),
            'permission_callback' => array($this, 'validate_admin_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ),
                'reassign' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'description' => 'ID del usuario al que reasignar el contenido'
                ),
                'force' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Forzar eliminación sin reasignar contenido'
                )
            )
        ));
        
        // Ruta para estadísticas de usuarios (solo administradores)
        register_rest_route($this->namespace, '/admin/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_users_stats'),
            'permission_callback' => array($this, 'validate_admin_permission')
        ));
        
        // Endpoint temporal para debugging
        register_rest_route($this->namespace, '/debug/token', array(
            'methods' => 'GET',
            'callback' => array($this, 'debug_token'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Endpoint de login
     */
    public function api_login($request) {
        $username = $request->get_param('username');
        $password = $request->get_param('password');
        $device_info = $request->get_param('device_info');
        
        // Intentar autenticar usuario
        $credentials = array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => false
        );
        
        $user = wp_authenticate($credentials['user_login'], $credentials['user_password']);
        
        if (is_wp_error($user)) {
            return new WP_Error(
                'authentication_failed',
                $this->get_friendly_error_message($user->get_error_message()),
                array('status' => 401)
            );
        }
        
        // Generar token de autenticación
        $token = $this->generate_auth_token($user->ID);
        
        // Registrar información del dispositivo si se proporciona
        if ($device_info) {
            $this->save_device_info($user->ID, $device_info);
        }
        
        // Registrar el login
        $this->log_user_login($user->ID, $device_info);
        
        // Preparar respuesta
        $response_data = array(
            'success' => true,
            'message' => 'Login exitoso',
            'data' => array(
                'token' => $token,
                'user' => array(
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                    'display_name' => $user->display_name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'roles' => $user->roles
                ),
                'expires_in' => 86400 // 24 horas
            )
        );
        
        return rest_ensure_response($response_data);
    }
    
    /**
     * Endpoint de logout
     */
    public function api_logout($request) {
        $user_id = $this->get_current_user_id($request);
        
        if ($user_id) {
            // Invalidar token
            $this->invalidate_user_tokens($user_id);
            
            // Registrar logout
            $this->log_user_logout($user_id);
            
            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Logout exitoso'
            ));
        }
        
        return new WP_Error(
            'invalid_token',
            'Token inválido',
            array('status' => 401)
        );
    }
    
    /**
     * Obtener perfil del usuario
     */
    public function get_user_profile($request) {
        $user_id = $this->get_current_user_id($request);
        
        if (!$user_id) {
            return new WP_Error('invalid_token', 'Token inválido', array('status' => 401));
        }
        
        $user = get_userdata($user_id);
        $gica_user = new GicaUser($user_id);
        
        $profile_data = array(
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone' => get_user_meta($user_id, 'phone', true),
            'address' => get_user_meta($user_id, 'address', true),
            'dni' => get_user_meta($user_id, 'dni', true),
            'city' => get_user_meta($user_id, 'city', true),
            'region' => get_user_meta($user_id, 'region', true),
            'country' => get_user_meta($user_id, 'country', true),
            'reference' => get_user_meta($user_id, 'reference', true),
            'completion_percentage' => $gica_user->get_completion_percentage()
        );
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $profile_data
        ));
    }
    
    /**
     * Actualizar perfil del usuario
     */
    public function update_user_profile($request) {
        $user_id = $this->get_current_user_id($request);
        
        if (!$user_id) {
            return new WP_Error('invalid_token', 'Token inválido', array('status' => 401));
        }
        
        $params = $request->get_json_params();
        $gica_user = new GicaUser($user_id);
        
        try {
            // Actualizar datos básicos del usuario
            $user_data = array('ID' => $user_id);
            
            if (isset($params['display_name'])) {
                $user_data['display_name'] = sanitize_text_field($params['display_name']);
            }
            if (isset($params['first_name'])) {
                $user_data['first_name'] = sanitize_text_field($params['first_name']);
            }
            if (isset($params['last_name'])) {
                $user_data['last_name'] = sanitize_text_field($params['last_name']);
            }
            
            if (count($user_data) > 1) {
                wp_update_user($user_data);
            }
            
            // Actualizar metadatos
            $meta_fields = array('phone', 'address', 'dni', 'city', 'region', 'country', 'reference');
            $meta_data = array();
            
            foreach ($meta_fields as $field) {
                if (isset($params[$field])) {
                    $meta_data[$field] = sanitize_text_field($params[$field]);
                }
            }
            
            if (!empty($meta_data)) {
                $gica_user->update_meta($meta_data);
            }
            
            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Perfil actualizado correctamente',
                'data' => array(
                    'completion_percentage' => $gica_user->get_completion_percentage()
                )
            ));
            
        } catch (Exception $e) {
            return new WP_Error(
                'update_failed',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Cambiar contraseña del usuario
     */
    public function change_user_password($request) {
        $user_id = $this->get_current_user_id($request);
        
        if (!$user_id) {
            return new WP_Error('invalid_token', 'Token inválido', array('status' => 401));
        }
        
        $current_password = $request->get_param('current_password');
        $new_password = $request->get_param('new_password');
        $confirm_password = $request->get_param('confirm_password');
        
        // Validar nueva contraseña
        if (strlen($new_password) < 8) {
            return new WP_Error(
                'weak_password',
                'La nueva contraseña debe tener al menos 8 caracteres',
                array('status' => 400)
            );
        }
        
        // Verificar que tenga al menos una letra y un número
        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)/', $new_password)) {
            return new WP_Error(
                'weak_password',
                'La nueva contraseña debe contener al menos una letra y un número',
                array('status' => 400)
            );
        }
        
        // Verificar que las contraseñas nuevas coincidan
        if ($new_password !== $confirm_password) {
            return new WP_Error(
                'password_mismatch',
                'Las contraseñas nuevas no coinciden',
                array('status' => 400)
            );
        }
        
        // Obtener datos del usuario
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('user_not_found', 'Usuario no encontrado', array('status' => 404));
        }
        
        // Verificar contraseña actual
        if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
            return new WP_Error(
                'invalid_current_password',
                'La contraseña actual es incorrecta',
                array('status' => 400)
            );
        }
        
        // Verificar que la nueva contraseña sea diferente a la actual
        if (wp_check_password($new_password, $user->user_pass, $user_id)) {
            return new WP_Error(
                'same_password',
                'La nueva contraseña debe ser diferente a la actual',
                array('status' => 400)
            );
        }
        
        try {
            // Cambiar contraseña
            wp_set_password($new_password, $user_id);
            
            // Invalidar todos los tokens existentes para forzar nuevo login
            $this->invalidate_user_tokens($user_id);
            
            // Registrar cambio de contraseña
            $this->log_password_change($user_id);
            
            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Contraseña cambiada correctamente. Debes iniciar sesión nuevamente.',
                'data' => array(
                    'password_changed_at' => current_time('Y-m-d H:i:s'),
                    'tokens_invalidated' => true
                )
            ));
            
        } catch (Exception $e) {
            return new WP_Error(
                'password_change_failed',
                'Error al cambiar la contraseña: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Obtener lista de usuarios (solo administradores)
     */
    public function get_users_list($request) {
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $search = $request->get_param('search');
        $role = $request->get_param('role');
        $orderby = $request->get_param('orderby');
        $order = $request->get_param('order');
        
        // Preparar argumentos para WP_User_Query
        $args = array(
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => $orderby,
            'order' => $order,
            'count_total' => true
        );
        
        // Agregar búsqueda si se especifica
        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = array('user_login', 'user_email', 'display_name');
        }
        
        // Agregar filtro de rol si se especifica
        if (!empty($role)) {
            $args['role'] = $role;
        }
        
        // Ejecutar consulta
        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        $total_users = $user_query->get_total();
        
        // Formatear datos de usuarios
        $formatted_users = array();
        foreach ($users as $user) {
            $gica_user = new GicaUser($user->ID);
            $device_info = get_user_meta($user->ID, '_gica_device_info', true);
            $login_logs = get_user_meta($user->ID, '_gica_login_logs', true);
            $last_login = is_array($login_logs) && !empty($login_logs) ? $login_logs[0]['timestamp'] : null;
            
            $formatted_users[] = array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'roles' => $user->roles,
                'registered' => $user->user_registered,
                'last_login' => $last_login ? date('Y-m-d H:i:s', $last_login) : null,
                'completion_percentage' => $gica_user->get_completion_percentage(),
                'device_info' => $device_info ?: null,
                'avatar_url' => get_avatar_url($user->ID),
                'meta' => array(
                    'phone' => get_user_meta($user->ID, 'phone', true),
                    'city' => get_user_meta($user->ID, 'city', true),
                    'country' => get_user_meta($user->ID, 'country', true)
                )
            );
        }
        
        // Preparar respuesta con paginación
        $response_data = array(
            'success' => true,
            'data' => array(
                'users' => $formatted_users,
                'pagination' => array(
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $total_users,
                    'total_pages' => ceil($total_users / $per_page),
                    'has_more' => ($page * $per_page) < $total_users
                )
            )
        );
        
        return rest_ensure_response($response_data);
    }
    
    /**
     * Obtener detalles completos de un usuario específico (solo administradores)
     */
    public function get_user_details($request) {
        $user_id = $request->get_param('id');
        
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error(
                'user_not_found',
                'Usuario no encontrado',
                array('status' => 404)
            );
        }
        
        $gica_user = new GicaUser($user_id);
        $device_info = get_user_meta($user_id, '_gica_device_info', true);
        $login_logs = get_user_meta($user_id, '_gica_login_logs', true);
        
        // Formatear logs de login
        $formatted_logs = array();
        if (is_array($login_logs)) {
            foreach ($login_logs as $log) {
                $formatted_logs[] = array(
                    'timestamp' => date('Y-m-d H:i:s', $log['timestamp']),
                    'ip_address' => $log['ip_address'] ?? '',
                    'user_agent' => $log['user_agent'] ?? '',
                    'device_info' => $log['device_info'] ?? null,
                    'logout_timestamp' => isset($log['logout_timestamp']) 
                        ? date('Y-m-d H:i:s', $log['logout_timestamp']) 
                        : null
                );
            }
        }
        
        $user_details = array(
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'roles' => $user->roles,
            'registered' => $user->user_registered,
            'avatar_url' => get_avatar_url($user_id),
            'completion_percentage' => $gica_user->get_completion_percentage(),
            'device_info' => $device_info ?: null,
            'login_history' => $formatted_logs,
            'profile_data' => array(
                'phone' => get_user_meta($user_id, 'phone', true),
                'address' => get_user_meta($user_id, 'address', true),
                'dni' => get_user_meta($user_id, 'dni', true),
                'city' => get_user_meta($user_id, 'city', true),
                'region' => get_user_meta($user_id, 'region', true),
                'country' => get_user_meta($user_id, 'country', true),
                'reference' => get_user_meta($user_id, 'reference', true)
            )
        );
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $user_details
        ));
    }
    
    /**
     * Obtener estadísticas de usuarios (solo administradores)
     */
    public function get_users_stats($request) {
        global $wpdb;
        
        // Total de usuarios
        $total_users = count_users();
        
        // Usuarios registrados en los últimos 30 días
        $recent_users = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->users} 
            WHERE user_registered >= %s
        ", date('Y-m-d H:i:s', strtotime('-30 days'))));
        
        // Usuarios que han iniciado sesión en los últimos 30 días
        $active_users = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT user_id) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = '_gica_login_logs' 
            AND meta_value LIKE %s
        ", '%' . time() - (30 * 24 * 60 * 60) . '%'));
        
        // Usuarios con perfiles completos (100%)
        $complete_profiles = 0;
        $all_users = get_users(array('fields' => 'ID'));
        foreach ($all_users as $user_id) {
            $gica_user = new GicaUser($user_id);
            if ($gica_user->get_completion_percentage() == 100) {
                $complete_profiles++;
            }
        }
        
        // Distribución por roles
        $role_distribution = array();
        foreach ($total_users['avail_roles'] as $role => $count) {
            $role_distribution[$role] = $count;
        }
        
        // Dispositivos más utilizados
        $device_stats = $wpdb->get_results("
            SELECT meta_value 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = '_gica_device_info'
        ");
        
        $device_distribution = array();
        foreach ($device_stats as $device_meta) {
            $device_info = maybe_unserialize($device_meta->meta_value);
            if (is_array($device_info) && isset($device_info['platform'])) {
                $platform = $device_info['platform'];
                $device_distribution[$platform] = ($device_distribution[$platform] ?? 0) + 1;
            }
        }
        
        $stats = array(
            'total_users' => $total_users['total_users'],
            'recent_registrations' => intval($recent_users),
            'active_users_30_days' => intval($active_users),
            'complete_profiles' => $complete_profiles,
            'completion_percentage' => $total_users['total_users'] > 0 
                ? round(($complete_profiles / $total_users['total_users']) * 100, 2) 
                : 0,
            'role_distribution' => $role_distribution,
            'device_distribution' => $device_distribution,
            'generated_at' => current_time('Y-m-d H:i:s')
        );
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $stats
        ));
    }
    
    /**
     * Eliminar un usuario específico (solo administradores)
     */
    public function delete_user($request) {
        $user_id_to_delete = $request->get_param('id');
        $reassign_to = $request->get_param('reassign');
        $force = $request->get_param('force');
        $current_user_id = $this->get_current_user_id($request);
        
        // Verificar que el usuario a eliminar existe
        $user_to_delete = get_userdata($user_id_to_delete);
        if (!$user_to_delete) {
            return new WP_Error(
                'user_not_found',
                'El usuario a eliminar no existe',
                array('status' => 404)
            );
        }
        
        // Protección: No permitir eliminarse a sí mismo
        if ($user_id_to_delete == $current_user_id) {
            return new WP_Error(
                'cannot_delete_self',
                'No puedes eliminar tu propia cuenta de administrador',
                array('status' => 403)
            );
        }
        
        // Protección: No eliminar el único administrador
        $admin_users = get_users(array('role' => 'administrator'));
        if (count($admin_users) <= 1 && in_array('administrator', $user_to_delete->roles)) {
            return new WP_Error(
                'cannot_delete_last_admin',
                'No se puede eliminar el último administrador del sitio',
                array('status' => 403)
            );
        }
        
        // Verificar usuario de reasignación si se especifica
        if ($reassign_to) {
            $reassign_user = get_userdata($reassign_to);
            if (!$reassign_user) {
                return new WP_Error(
                    'reassign_user_not_found',
                    'El usuario para reasignar contenido no existe',
                    array('status' => 404)
                );
            }
            
            // No permitir reasignar al usuario que se va a eliminar
            if ($reassign_to == $user_id_to_delete) {
                return new WP_Error(
                    'invalid_reassign',
                    'No se puede reasignar contenido al usuario que se va a eliminar',
                    array('status' => 400)
                );
            }
        }
        
        // Obtener estadísticas del usuario antes de eliminarlo
        $user_stats = array(
            'id' => $user_to_delete->ID,
            'username' => $user_to_delete->user_login,
            'email' => $user_to_delete->user_email,
            'display_name' => $user_to_delete->display_name,
            'roles' => $user_to_delete->roles,
            'registered' => $user_to_delete->user_registered,
            'post_count' => count_user_posts($user_id_to_delete, 'post', true),
            'page_count' => count_user_posts($user_id_to_delete, 'page', true)
        );
        
        // Realizar la eliminación
        if (!function_exists('wp_delete_user')) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
        }
        
        // Si force es true y no hay reassign, eliminar sin reasignar
        if ($force && !$reassign_to) {
            $deleted = wp_delete_user($user_id_to_delete);
        } else {
            // Si no se especifica reassign_to, reasignar al administrador actual
            $reassign_id = $reassign_to ?: $current_user_id;
            $deleted = wp_delete_user($user_id_to_delete, $reassign_id);
        }
        
        if (!$deleted) {
            return new WP_Error(
                'deletion_failed',
                'No se pudo eliminar el usuario. Puede que tenga contenido asociado.',
                array('status' => 500)
            );
        }
        
        // Log de la acción
        $this->log_user_deletion($current_user_id, $user_stats, $reassign_to);
        
        // Respuesta exitosa
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Usuario eliminado correctamente',
            'data' => array(
                'deleted_user' => $user_stats,
                'content_reassigned_to' => $reassign_to,
                'deletion_timestamp' => current_time('Y-m-d H:i:s')
            )
        ));
    }
    
    /**
     * Registrar eliminación de usuario para auditoría
     */
    private function log_user_deletion($admin_user_id, $deleted_user_stats, $reassign_to = null) {
        $log_entry = array(
            'timestamp' => time(),
            'admin_user_id' => $admin_user_id,
            'admin_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'deleted_user' => $deleted_user_stats,
            'content_reassigned_to' => $reassign_to,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        // Obtener logs existentes
        $deletion_logs = get_option('gica_user_deletion_logs', array());
        if (!is_array($deletion_logs)) {
            $deletion_logs = array();
        }
        
        // Agregar nuevo log
        array_unshift($deletion_logs, $log_entry);
        
        // Mantener solo los últimos 50 registros
        $deletion_logs = array_slice($deletion_logs, 0, 50);
        
        // Guardar logs
        update_option('gica_user_deletion_logs', $deletion_logs, false);
        
        // También log en WordPress si debug está activo
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GICA API: Usuario eliminado - Admin: ' . $admin_user_id . ', Usuario eliminado: ' . $deleted_user_stats['username'] . ' (ID: ' . $deleted_user_stats['id'] . ')');
        }
    }
    
    /**
     * Validar permisos de administrador
     */
    public function validate_admin_permission($request) {
        // Debug: Log para debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GICA API: Validando permisos de administrador');
        }
        
        // Obtener el token del request
        $token = $this->get_auth_token_from_request($request);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GICA API: Token obtenido: ' . ($token ? substr($token, 0, 20) . '...' : 'NULL'));
        }
        
        if (!$token) {
            return new WP_Error(
                'no_auth_token',
                'Token de autenticación requerido',
                array('status' => 401)
            );
        }
        
        // Validar el token
        if (!$this->validate_auth_token($request)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GICA API: Token inválido o expirado');
            }
            return new WP_Error(
                'invalid_token',
                'Token inválido o expirado',
                array('status' => 401)
            );
        }
        
        // Obtener el usuario desde el token
        $user_id = $this->get_current_user_id($request);
        if (!$user_id) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GICA API: No se pudo obtener user_id del token');
            }
            return new WP_Error(
                'invalid_user',
                'Usuario no válido',
                array('status' => 401)
            );
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GICA API: User ID obtenido: ' . $user_id);
        }
        
        // Verificar si el usuario tiene permisos de administrador
        $user = get_userdata($user_id);
        if (!$user) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GICA API: Usuario no encontrado');
            }
            return new WP_Error(
                'user_not_found',
                'Usuario no encontrado',
                array('status' => 404)
            );
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GICA API: Roles del usuario: ' . implode(', ', $user->roles));
        }
        
        if (!in_array('administrator', $user->roles)) {
            return new WP_Error(
                'insufficient_permissions',
                'Se requieren permisos de administrador',
                array('status' => 403)
            );
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GICA API: Permisos de administrador validados correctamente');
        }
        
        return true;
    }
    
    /**
     * Endpoint de debugging para verificar tokens
     */
    public function debug_token($request) {
        $token = $this->get_auth_token_from_request($request);
        
        $debug_info = array(
            'token_found' => !empty($token),
            'token_preview' => $token ? substr($token, 0, 20) . '...' : null,
            'headers' => array(
                'authorization' => $request->get_header('Authorization'),
                'x_bearer_token' => $request->get_header('X-Bearer-Token'),
                'x_bearer_token_underscore' => $request->get_header('X_Bearer_Token')
            ),
            'server_headers' => array(
                'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
                'HTTP_X_BEARER_TOKEN' => $_SERVER['HTTP_X_BEARER_TOKEN'] ?? null
            )
        );
        
        if ($token) {
            $user_id = $this->get_user_id_from_token($token);
            $debug_info['user_id_from_token'] = $user_id;
            
            if ($user_id) {
                $user = get_userdata($user_id);
                $debug_info['user_exists'] = !empty($user);
                $debug_info['user_roles'] = $user ? $user->roles : null;
                $debug_info['is_admin'] = $user ? in_array('administrator', $user->roles) : false;
                
                // Verificar token en la base de datos
                $stored_token = get_user_meta($user_id, '_gica_auth_token', true);
                $token_expires = get_user_meta($user_id, '_gica_token_expires', true);
                
                $debug_info['token_matches_stored'] = ($token === $stored_token);
                $debug_info['token_expired'] = $token_expires ? (time() > $token_expires) : false;
                $debug_info['token_expires_at'] = $token_expires ? date('Y-m-d H:i:s', $token_expires) : null;
            }
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'debug_info' => $debug_info
        ));
    }
    
    /**
     * Generar token de autenticación
     */
    private function generate_auth_token($user_id) {
        $token_data = array(
            'user_id' => $user_id,
            'timestamp' => time(),
            'random' => wp_generate_password(32, false)
        );
        
        $token = base64_encode(json_encode($token_data));
        
        // Guardar token en la base de datos con expiración
        update_user_meta($user_id, '_gica_auth_token', $token);
        update_user_meta($user_id, '_gica_token_expires', time() + 86400); // 24 horas
        
        return $token;
    }
    
    /**
     * Validar token de autenticación
     */
    public function validate_auth_token($request) {
        $token = $this->get_auth_token_from_request($request);
        
        if (!$token) {
            return false;
        }
        
        $user_id = $this->get_user_id_from_token($token);
        
        if (!$user_id) {
            return false;
        }
        
        // Verificar si el token ha expirado
        $expires = get_user_meta($user_id, '_gica_token_expires', true);
        if ($expires && time() > $expires) {
            return false;
        }
        
        // Verificar si el token coincide con el almacenado
        $stored_token = get_user_meta($user_id, '_gica_auth_token', true);
        return $token === $stored_token;
    }
    
    /**
     * Obtener token del request
     */
    private function get_auth_token_from_request($request) {
        // Intentar obtener desde Authorization header
        $auth_header = $request->get_header('Authorization');
        if ($auth_header && strpos($auth_header, 'Bearer ') === 0) {
            return substr($auth_header, 7);
        }
        
        // Intentar obtener desde X-Bearer-Token header
        $bearer_token = $request->get_header('X-Bearer-Token');
        if ($bearer_token) {
            return $bearer_token;
        }
        
        // Intentar obtener desde X_Bearer_Token header (por si hay guiones bajos)
        $bearer_token_underscore = $request->get_header('X_Bearer_Token');
        if ($bearer_token_underscore) {
            return $bearer_token_underscore;
        }
        
        // Intentar obtener desde $_SERVER directamente
        if (isset($_SERVER['HTTP_X_BEARER_TOKEN'])) {
            return $_SERVER['HTTP_X_BEARER_TOKEN'];
        }
        
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if (strpos($auth, 'Bearer ') === 0) {
                return substr($auth, 7);
            }
        }
        
        // Como último recurso, intentar desde parámetros
        return $request->get_param('token');
    }
    
    /**
     * Obtener ID de usuario del token
     */
    private function get_user_id_from_token($token) {
        $decoded = json_decode(base64_decode($token), true);
        
        if ($decoded && isset($decoded['user_id'])) {
            return intval($decoded['user_id']);
        }
        
        return false;
    }
    
    /**
     * Obtener ID del usuario actual desde el request
     */
    private function get_current_user_id($request) {
        $token = $this->get_auth_token_from_request($request);
        return $this->get_user_id_from_token($token);
    }
    
    /**
     * Invalidar todos los tokens del usuario
     */
    private function invalidate_user_tokens($user_id) {
        delete_user_meta($user_id, '_gica_auth_token');
        delete_user_meta($user_id, '_gica_token_expires');
    }
    
    /**
     * Guardar información del dispositivo
     */
    private function save_device_info($user_id, $device_info) {
        $device_data = array(
            'type' => sanitize_text_field($device_info['type'] ?? ''),
            'platform' => sanitize_text_field($device_info['platform'] ?? ''),
            'app_version' => sanitize_text_field($device_info['app_version'] ?? ''),
            'device_id' => sanitize_text_field($device_info['device_id'] ?? ''),
            'last_used' => time()
        );
        
        update_user_meta($user_id, '_gica_device_info', $device_data);
    }
    
    /**
     * Registrar login del usuario
     */
    private function log_user_login($user_id, $device_info = null) {
        $login_data = array(
            'user_id' => $user_id,
            'timestamp' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'device_info' => $device_info
        );
        
        // Obtener logs existentes
        $logs = get_user_meta($user_id, '_gica_login_logs', true);
        if (!is_array($logs)) {
            $logs = array();
        }
        
        // Agregar nuevo log
        array_unshift($logs, $login_data);
        
        // Mantener solo los últimos 10 registros
        $logs = array_slice($logs, 0, 10);
        
        update_user_meta($user_id, '_gica_login_logs', $logs);
    }
    
    /**
     * Registrar logout del usuario
     */
    private function log_user_logout($user_id) {
        $logout_data = array(
            'user_id' => $user_id,
            'timestamp' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'action' => 'logout'
        );
        
        $logs = get_user_meta($user_id, '_gica_login_logs', true);
        if (is_array($logs) && !empty($logs)) {
            // Actualizar el último login con la información de logout
            $logs[0]['logout_timestamp'] = time();
            update_user_meta($user_id, '_gica_login_logs', $logs);
        }
    }
    
    /**
     * Registrar cambio de contraseña
     */
    private function log_password_change($user_id) {
        $password_change_data = array(
            'user_id' => $user_id,
            'timestamp' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'action' => 'password_change'
        );
        
        // Obtener logs existentes de cambios de contraseña
        $password_logs = get_user_meta($user_id, '_gica_password_change_logs', true);
        if (!is_array($password_logs)) {
            $password_logs = array();
        }
        
        // Agregar nuevo log
        array_unshift($password_logs, $password_change_data);
        
        // Mantener solo los últimos 5 registros
        $password_logs = array_slice($password_logs, 0, 5);
        
        update_user_meta($user_id, '_gica_password_change_logs', $password_logs);
        
        // También log en WordPress si debug está activo
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GICA API: Contraseña cambiada para usuario ID: ' . $user_id);
        }
    }
    
    /**
     * Obtener mensaje de error amigable
     */
    private function get_friendly_error_message($error_message) {
        if (strpos($error_message, 'Invalid username') !== false) {
            return 'Usuario no encontrado. Verifica tu nombre de usuario o email.';
        }
        
        if (strpos($error_message, 'incorrect password') !== false) {
            return 'Contraseña incorrecta. Inténtalo de nuevo.';
        }
        
        return 'Error de autenticación. Verifica tus credenciales.';
    }
    
    /**
     * Validar username
     */
    public function validate_username($value) {
        return !empty($value) && strlen($value) >= 3;
    }
    
    /**
     * Validar password
     */
    public function validate_password($value) {
        return !empty($value) && strlen($value) >= 6;
    }
    
    /**
     * Validar nueva contraseña
     */
    public function validate_new_password($value) {
        return !empty($value) && strlen($value) >= 8;
    }
}