<?php
/**
 * Script de prueba para la API GICA Account
 * 
 * IMPORTANTE: Este archivo es solo para pruebas durante desarrollo.
 * Elimínalo en producción por seguridad.
 */

// Configuración
$base_url = 'https://ornery-fog.localsite.io'; // Cambiar por tu dominio
$api_base = $base_url . '/wp-json/gicaform/v1';

// Función para hacer peticiones HTTP
function make_request($url, $method = 'GET', $data = null, $headers = []) {
    $curl = curl_init();
    
    $default_headers = [
        'Content-Type: application/json',
        'User-Agent: GICA-API-Test/1.0'
    ];
    
    $headers = array_merge($default_headers, $headers);
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false, // Solo para desarrollo local
        CURLOPT_TIMEOUT => 30
    ]);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    
    curl_close($curl);
    
    if ($error) {
        return ['error' => $error, 'http_code' => 0];
    }
    
    return [
        'data' => json_decode($response, true),
        'http_code' => $http_code,
        'raw_response' => $response
    ];
}

// Función para mostrar resultados
function show_result($test_name, $result) {
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "PRUEBA: $test_name\n";
    echo str_repeat("=", 50) . "\n";
    
    if (isset($result['error'])) {
        echo "❌ ERROR: " . $result['error'] . "\n";
        return false;
    }
    
    echo "📡 HTTP Status: " . $result['http_code'] . "\n";
    
    if ($result['http_code'] >= 200 && $result['http_code'] < 300) {
        echo "✅ Respuesta exitosa\n";
        echo "📄 Datos: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";
        return $result['data'];
    } else {
        echo "❌ Error HTTP\n";
        echo "📄 Respuesta: " . $result['raw_response'] . "\n";
        return false;
    }
}

echo "🚀 INICIANDO PRUEBAS DE LA API GICA ACCOUNT\n";
echo "Base URL: $api_base\n";

// Variables globales para las pruebas
$token = null;
$user_id = null;
$admin_token = null;
$is_admin = false;

// PRUEBA 1: Login exitoso
echo "\n🔐 Probando login con credenciales válidas...\n";
$login_data = [
    'username' => 'var', // Cambiar por un usuario válido
    'password' => '123456', // Cambiar por la contraseña correcta
    'device_info' => [
        'type' => 'mobile',
        'platform' => 'android',
        'app_version' => '1.0.0',
        'device_id' => 'TEST_DEVICE_' . uniqid()
    ]
];

$login_result = make_request($api_base . '/auth/login', 'POST', $login_data);
$login_response = show_result('LOGIN EXITOSO', $login_result);

if ($login_response && isset($login_response['data']['token'])) {
    $token = $login_response['data']['token'];
    $user_id = $login_response['data']['user']['id'];
    
    // Verificar si el usuario logueado es administrador
    $user_roles = $login_response['data']['user']['roles'] ?? [];
    $is_admin = in_array('administrator', $user_roles);
    if ($is_admin) {
        $admin_token = $token;
        echo "👑 Usuario administrador detectado\n";
    }
    
    echo "🎫 Token obtenido: " . substr($token, 0, 20) . "...\n";
}

// PRUEBA 2: Login con credenciales incorrectas
echo "\n🔐 Probando login con credenciales incorrectas...\n";
$bad_login_data = [
    'username' => 'usuario_inexistente',
    'password' => 'password_incorrecto'
];

$bad_login_result = make_request($api_base . '/auth/login', 'POST', $bad_login_data);
$bad_login_response = show_result('LOGIN CON ERROR', $bad_login_result);

// PRUEBA 3: Obtener perfil de usuario (requiere token)
if ($token) {
    echo "\n👤 Probando obtener perfil de usuario...\n";
    $headers = ['Authorization: Bearer ' . $token];
    
    $profile_result = make_request($api_base . '/user/profile', 'GET', null, $headers);
    $profile_response = show_result('OBTENER PERFIL', $profile_result);
}

// PRUEBA 4: Actualizar perfil de usuario
if ($token) {
    echo "\n✏️ Probando actualizar perfil de usuario...\n";
    $update_data = [
        'phone' => '+1234567890',
        'city' => 'Ciudad de Prueba',
        'country' => 'País de Prueba'
    ];
    
    $headers = ['Authorization: Bearer ' . $token];
    
    $update_result = make_request($api_base . '/user/profile', 'PUT', $update_data, $headers);
    $update_response = show_result('ACTUALIZAR PERFIL', $update_result);
}

// PRUEBA 5: Cambiar contraseña (simulación)
if ($token) {
    echo "\n🔐 Probando cambio de contraseña...\n";
    echo "⚠️ Nota: Esta prueba NO cambiará la contraseña real para evitar problemas.\n";
    echo "Para probar el cambio real, usar credenciales de prueba.\n";
    
    // Probar con contraseña actual incorrecta (debe fallar)
    echo "\n🚫 Probando con contraseña actual incorrecta...\n";
    $wrong_password_data = [
        'current_password' => 'contraseña_incorrecta',
        'new_password' => 'NuevaPassword123',
        'confirm_password' => 'NuevaPassword123'
    ];
    
    $headers = ['Authorization: Bearer ' . $token];
    $wrong_password_result = make_request($api_base . '/user/change-password', 'POST', $wrong_password_data, $headers);
    show_result('CAMBIO CONTRASEÑA - CONTRASEÑA INCORRECTA', $wrong_password_result);
    
    // Probar con contraseñas que no coinciden (debe fallar)
    echo "\n🚫 Probando con contraseñas nuevas que no coinciden...\n";
    $mismatch_data = [
        'current_password' => '123456', // Usar la contraseña actual real
        'new_password' => 'NuevaPassword123',
        'confirm_password' => 'OtraPassword456'
    ];
    
    $mismatch_result = make_request($api_base . '/user/change-password', 'POST', $mismatch_data, $headers);
    show_result('CAMBIO CONTRASEÑA - NO COINCIDEN', $mismatch_result);
    
    // Probar con nueva contraseña débil (debe fallar)
    echo "\n🚫 Probando con contraseña débil...\n";
    $weak_password_data = [
        'current_password' => '123456',
        'new_password' => '123', // Muy corta
        'confirm_password' => '123'
    ];
    
    $weak_password_result = make_request($api_base . '/user/change-password', 'POST', $weak_password_data, $headers);
    show_result('CAMBIO CONTRASEÑA - CONTRASEÑA DÉBIL', $weak_password_result);
    
    // Mostrar ejemplo de cambio exitoso (sin ejecutar)
    echo "\n✅ Ejemplo de cambio exitoso (NO EJECUTADO):\n";
    echo "📋 Endpoint: POST $api_base/user/change-password\n";
    echo "📋 Headers: Authorization: Bearer {token}\n";
    echo "📋 Body: {\n";
    echo "  'current_password': 'contraseña_actual',\n";
    echo "  'new_password': 'NuevaPassword123',\n";
    echo "  'confirm_password': 'NuevaPassword123'\n";
    echo "}\n";
}

// PRUEBA 5: Acceso sin token (debe fallar)
echo "\n🚫 Probando acceso sin token (debe fallar)...\n";
$no_token_result = make_request($api_base . '/user/profile', 'GET');
show_result('ACCESO SIN TOKEN', $no_token_result);

// PRUEBA 6: Logout
if ($token) {
    echo "\n🚪 Probando logout...\n";
    $headers = ['Authorization: Bearer ' . $token];
    
    $logout_result = make_request($api_base . '/auth/logout', 'POST', null, $headers);
    $logout_response = show_result('LOGOUT', $logout_result);
}

// PRUEBA 7: Acceso con token invalidado (debe fallar)
if ($token) {
    echo "\n🚫 Probando acceso con token invalidado después del logout...\n";
    $headers = ['Authorization: Bearer ' . $token];
    
    $invalid_token_result = make_request($api_base . '/user/profile', 'GET', null, $headers);
    show_result('ACCESO CON TOKEN INVALIDADO', $invalid_token_result);
}

// PRUEBA 8: Probar endpoints que no existen
echo "\n❓ Probando endpoint inexistente...\n";
$not_found_result = make_request($api_base . '/endpoint/inexistente', 'GET');
show_result('ENDPOINT INEXISTENTE', $not_found_result);

// PRUEBAS DE ADMINISTRADOR (Solo si el usuario es admin)
if ($admin_token) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "👑 INICIANDO PRUEBAS DE ADMINISTRADOR\n";
    echo str_repeat("=", 60) . "\n";
    
    // PRUEBA ADMIN 1: Obtener lista de usuarios
    echo "\n👥 Probando obtener lista de usuarios...\n";
    $admin_headers = ['Authorization: Bearer ' . $admin_token];
    
    $users_list_result = make_request($api_base . '/admin/users?page=1&per_page=5', 'GET', null, $admin_headers);
    $users_list_response = show_result('LISTA DE USUARIOS', $users_list_result);
    
    // PRUEBA ADMIN 2: Buscar usuarios
    echo "\n🔍 Probando búsqueda de usuarios...\n";
    $search_users_result = make_request($api_base . '/admin/users?search=admin&per_page=3', 'GET', null, $admin_headers);
    show_result('BÚSQUEDA DE USUARIOS', $search_users_result);
    
    // PRUEBA ADMIN 3: Filtrar por rol
    echo "\n🎭 Probando filtro por rol...\n";
    $role_filter_result = make_request($api_base . '/admin/users?role=administrator&per_page=10', 'GET', null, $admin_headers);
    show_result('FILTRO POR ROL', $role_filter_result);
    
    // PRUEBA ADMIN 4: Obtener detalles de usuario específico
    if ($users_list_response && isset($users_list_response['data']['users'][0]['id'])) {
        $test_user_id = $users_list_response['data']['users'][0]['id'];
        echo "\n🔍 Probando obtener detalles del usuario ID: $test_user_id...\n";
        
        $user_details_result = make_request($api_base . '/admin/users/' . $test_user_id, 'GET', null, $admin_headers);
        show_result('DETALLES DE USUARIO', $user_details_result);
    }
    
    // PRUEBA ADMIN 5: Obtener estadísticas
    echo "\n📊 Probando obtener estadísticas...\n";
    $stats_result = make_request($api_base . '/admin/stats', 'GET', null, $admin_headers);
    show_result('ESTADÍSTICAS DE USUARIOS', $stats_result);
    
    // PRUEBA ADMIN 6: Acceso no autorizado (sin token de admin)
    echo "\n🚫 Probando acceso de admin sin permisos...\n";
    
    // Crear un token falso o usar un token de usuario normal
    $fake_headers = ['Authorization: Bearer fake_token_123'];
    $unauthorized_result = make_request($api_base . '/admin/users', 'GET', null, $fake_headers);
    show_result('ACCESO NO AUTORIZADO A ADMIN', $unauthorized_result);
    
    // PRUEBA ADMIN 7: Intentar eliminar usuario (simulación segura)
    echo "\n🗑️ Probando endpoint de eliminación (simulación)...\n";
    echo "⚠️ Nota: Para seguridad, esta prueba NO eliminará usuarios reales.\n";
    echo "Para probar eliminación real, usar herramientas como Postman o cURL directamente.\n";
    
    // Solo mostrar el endpoint disponible sin ejecutar la eliminación
    echo "📋 Endpoint disponible: DELETE $api_base/admin/users/{id}\n";
    echo "📋 Parámetros opcionales: ?reassign={id} o ?force=true\n";
    
    // Probar con usuario inexistente (seguro)
    echo "\n🧪 Probando eliminación de usuario inexistente (ID: 99999)...\n";
    $delete_test_result = make_request($api_base . '/admin/users/99999', 'DELETE', null, $admin_headers);
    show_result('ELIMINACIÓN USUARIO INEXISTENTE', $delete_test_result);
    
    // Probar eliminación sin permisos
    echo "\n🚫 Probando eliminación sin permisos de admin...\n";
    $delete_unauthorized = make_request($api_base . '/admin/users/1', 'DELETE', null, $fake_headers);
    show_result('ELIMINACIÓN SIN PERMISOS', $delete_unauthorized);
    
} else {
    echo "\n⚠️ SALTANDO PRUEBAS DE ADMINISTRADOR\n";
    echo "El usuario actual no tiene permisos de administrador.\n";
    echo "Para probar los endpoints de admin, usa credenciales de administrador.\n";
}

// PRUEBA FINAL: Login como administrador específico (opcional)
echo "\n👑 Intentando login como administrador específico...\n";
$admin_login_data = [
    'username' => 'admin', // Cambiar por un admin válido
    'password' => 'admin', // Cambiar por la contraseña correcta
    'device_info' => [
        'type' => 'desktop',
        'platform' => 'windows',
        'app_version' => '1.0.0',
        'device_id' => 'ADMIN_TEST_' . uniqid()
    ]
];

$admin_login_result = make_request($api_base . '/auth/login', 'POST', $admin_login_data);
$admin_login_response = show_result('LOGIN COMO ADMIN', $admin_login_result);

if ($admin_login_response && isset($admin_login_response['data']['token'])) {
    $temp_admin_token = $admin_login_response['data']['token'];
    echo "👑 Token de admin obtenido: " . substr($temp_admin_token, 0, 20) . "...\n";
    
    // Probar rápidamente un endpoint de admin
    echo "\n📋 Probando endpoint de admin con token válido...\n";
    $temp_admin_headers = ['Authorization: Bearer ' . $temp_admin_token];
    $quick_admin_test = make_request($api_base . '/admin/stats', 'GET', null, $temp_admin_headers);
    show_result('TEST RÁPIDO DE ADMIN', $quick_admin_test);
    
    // Logout del admin temporal
    echo "\n🚪 Logout del administrador temporal...\n";
    $temp_logout_result = make_request($api_base . '/auth/logout', 'POST', null, $temp_admin_headers);
    show_result('LOGOUT ADMIN TEMPORAL', $temp_logout_result);
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🏁 PRUEBAS COMPLETADAS\n";
echo str_repeat("=", 50) . "\n";

echo "\n📋 RESUMEN DE ENDPOINTS DISPONIBLES:\n";
echo "🔓 PÚBLICOS:\n";
echo "POST   $api_base/auth/login\n";
echo "\n🔐 AUTENTICADOS:\n";
echo "POST   $api_base/auth/logout\n";
echo "GET    $api_base/user/profile\n";
echo "PUT    $api_base/user/profile\n";
echo "POST   $api_base/user/change-password\n";
echo "\n👑 ADMINISTRADORES:\n";
echo "GET    $api_base/admin/users\n";
echo "GET    $api_base/admin/users/{id}\n";
echo "DELETE $api_base/admin/users/{id}\n";
echo "GET    $api_base/admin/stats\n";

echo "\n⚠️ IMPORTANTE - ENDPOINT DELETE:\n";
echo "El endpoint DELETE /admin/users/{id} requiere precauciones especiales:\n";
echo "- No se puede eliminar la propia cuenta de administrador\n";
echo "- No se puede eliminar el último administrador del sitio\n";
echo "- Usar parámetro 'reassign' para reasignar contenido\n";
echo "- Usar parámetro 'force=true' para eliminar sin reasignar\n";
echo "\n📖 Para más información, consulta API_DOCUMENTATION.md\n";

// Mostrar ejemplo de uso con JavaScript
echo "\n💻 EJEMPLOS DE USO CON JAVASCRIPT:\n";
echo "\n🔐 LOGIN:\n";
echo "```javascript\n";
echo "const response = await fetch('$api_base/auth/login', {\n";
echo "  method: 'POST',\n";
echo "  headers: { 'Content-Type': 'application/json' },\n";
echo "  body: JSON.stringify({\n";
echo "    username: 'tu_usuario',\n";
echo "    password: 'tu_password',\n";
echo "    device_info: {\n";
echo "      type: 'mobile',\n";
echo "      platform: 'android',\n";
echo "      app_version: '1.0.0',\n";
echo "      device_id: 'DEVICE123'\n";
echo "    }\n";
echo "  })\n";
echo "});\n";
echo "const data = await response.json();\n";
echo "```\n";
echo "\n🗑️ ELIMINAR USUARIO (ADMIN):\n";
echo "```javascript\n";
echo "// Eliminar y reasignar contenido\n";
echo "const deleteResponse = await fetch('$api_base/admin/users/123?reassign=456', {\n";
echo "  method: 'DELETE',\n";
echo "  headers: { 'Authorization': 'Bearer ' + adminToken }\n";
echo "});\n";
echo "\n// Eliminar forzado (sin reasignar)\n";
echo "const forceDeleteResponse = await fetch('$api_base/admin/users/123?force=true', {\n";
echo "  method: 'DELETE',\n";
echo "  headers: { 'Authorization': 'Bearer ' + adminToken }\n";
echo "});\n";
echo "```\n";

?>