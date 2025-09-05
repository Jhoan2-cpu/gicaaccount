<?php

if (!defined('ABSPATH')) {
    exit;
}

class GicaUser {
    
    private $user_id;
    private $user_data;
    private $meta_fields = array(
        'phone',
        'address', 
        'dni',
        'city',
        'reference',
        'country',
        'region'
    );
    
    public function __construct($user_id = null) {
        if ($user_id) {
            $this->user_id = $user_id;
            $this->load_user_data();
        }
    }
    
    public static function create_user($data) {
        $required_fields = array('username', 'email', 'password');
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', "Campo requerido: {$field}");
            }
        }
        
        $user_data = array(
            'user_login' => sanitize_user($data['username']),
            'user_email' => sanitize_email($data['email']),
            'user_pass' => $data['password'],
            'first_name' => isset($data['first_name']) ? sanitize_text_field($data['first_name']) : '',
            'last_name' => isset($data['last_name']) ? sanitize_text_field($data['last_name']) : '',
            'display_name' => isset($data['display_name']) ? sanitize_text_field($data['display_name']) : $data['username']
        );
        
        $user_id = wp_insert_user($user_data);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        $user_instance = new self($user_id);
        
        // Save additional meta fields
        $meta_data = array(
            'phone' => isset($data['phone']) ? sanitize_text_field($data['phone']) : '',
            'address' => isset($data['address']) ? sanitize_textarea_field($data['address']) : '',
            'dni' => isset($data['dni']) ? sanitize_text_field($data['dni']) : '',
            'city' => isset($data['city']) ? sanitize_text_field($data['city']) : '',
            'reference' => isset($data['reference']) ? sanitize_textarea_field($data['reference']) : '',
            'country' => isset($data['country']) ? sanitize_text_field($data['country']) : '',
            'region' => isset($data['region']) ? sanitize_text_field($data['region']) : ''
        );
        
        $user_instance->update_meta($meta_data);
        
        return $user_instance;
    }
    
    public static function get_all_users($args = array()) {
        $defaults = array(
            'number' => -1,
            'orderby' => 'registered',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        if (!empty($args['search'])) {
            $args['search'] = '*' . sanitize_text_field($args['search']) . '*';
        }
        
        $users = get_users($args);
        $gica_users = array();
        
        foreach ($users as $user) {
            $gica_users[] = new self($user->ID);
        }
        
        return $gica_users;
    }
    
    public static function search_users($search_term) {
        global $wpdb;
        
        $search_term = sanitize_text_field($search_term);
        
        // Search in user data and meta
        $user_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT u.ID 
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE u.display_name LIKE %s
            OR u.user_login LIKE %s
            OR u.user_email LIKE %s
            OR (um.meta_key IN ('phone', 'dni', 'city', 'country', 'region') AND um.meta_value LIKE %s)
        ", "%{$search_term}%", "%{$search_term}%", "%{$search_term}%", "%{$search_term}%"));
        
        $gica_users = array();
        foreach ($user_ids as $user_id) {
            $gica_users[] = new self($user_id);
        }
        
        return $gica_users;
    }
    
    private function load_user_data() {
        $this->user_data = get_userdata($this->user_id);
        if (!$this->user_data) {
            throw new Exception("Usuario no encontrado");
        }
    }
    
    public function get_id() {
        return $this->user_id;
    }
    
    public function get_user_data() {
        return $this->user_data;
    }
    
    public function get_display_name() {
        return $this->user_data->display_name;
    }
    
    public function get_email() {
        return $this->user_data->user_email;
    }
    
    public function get_username() {
        return $this->user_data->user_login;
    }
    
    public function get_registration_date() {
        return $this->user_data->user_registered;
    }
    
    public function get_meta($key) {
        return get_user_meta($this->user_id, $key, true);
    }
    
    public function get_all_meta() {
        $meta_data = array();
        foreach ($this->meta_fields as $field) {
            $meta_data[$field] = $this->get_meta($field);
        }
        return $meta_data;
    }
    
    public function update_user_data($data) {
        $allowed_fields = array('display_name', 'user_email', 'first_name', 'last_name');
        $user_data = array('ID' => $this->user_id);
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $user_data[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        $result = wp_update_user($user_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $this->load_user_data();
        return true;
    }
    
    public function update_meta($data) {
        $updated_fields = array();
        
        foreach ($this->meta_fields as $field) {
            if (array_key_exists($field, $data)) {
                $value = ($field === 'address' || $field === 'reference') 
                    ? sanitize_textarea_field($data[$field]) 
                    : sanitize_text_field($data[$field]);
                    
                update_user_meta($this->user_id, $field, $value);
                $updated_fields[] = $field;
            }
        }
        
        return $updated_fields;
    }
    
    public function delete() {
        if (!current_user_can('delete_users')) {
            return new WP_Error('permission_denied', 'No tienes permisos para eliminar usuarios');
        }
        
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        return wp_delete_user($this->user_id);
    }
    
    public function to_array() {
        $data = array(
            'ID' => $this->user_id,
            'username' => $this->get_username(),
            'display_name' => $this->get_display_name(),
            'email' => $this->get_email(),
            'registration_date' => $this->get_registration_date(),
            'first_name' => $this->user_data->first_name,
            'last_name' => $this->user_data->last_name
        );
        
        return array_merge($data, $this->get_all_meta());
    }
    
    public function has_complete_profile() {
        $required_fields = array('dni', 'city', 'country', 'region');
        
        foreach ($required_fields as $field) {
            if (empty($this->get_meta($field))) {
                return false;
            }
        }
        
        return true;
    }
    
    public function get_completion_percentage() {
        $all_fields = array_merge(
            array('first_name', 'last_name', 'user_email'),
            $this->meta_fields
        );
        
        $completed = 0;
        $total = count($all_fields);
        
        foreach ($all_fields as $field) {
            if (in_array($field, array('first_name', 'last_name', 'user_email'))) {
                $value = $this->user_data->$field;
            } else {
                $value = $this->get_meta($field);
            }
            
            if (!empty($value) && $field !== 'reference') { // reference is optional
                $completed++;
            }
        }
        
        // Don't count reference as required for percentage
        if (in_array('reference', $all_fields)) {
            $total--;
        }
        
        return round(($completed / $total) * 100);
    }
}