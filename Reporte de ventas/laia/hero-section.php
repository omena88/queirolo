<?php
// SISTEMA COMPLETO DE HERO SECTION CON LOGIN Y REDIRECCIONES PERSONALIZADAS

// ============================================================================
// PANEL DE ADMINISTRACIÓN PARA REDIRECCIONES PERSONALIZADAS POR USUARIO
// ============================================================================

// Agregar campo personalizado en el perfil de usuario
if (!function_exists('add_custom_redirect_field')) {
    function add_custom_redirect_field($user) {
        $custom_redirect = get_user_meta($user->ID, 'custom_redirect_url', true);
        ?>
        <h3>Configuración de Redirección</h3>
        <table class="form-table">
            <tr>
                <th><label for="custom_redirect_url">URL de Redirección Personalizada</label></th>
                <td>
                    <input type="url" name="custom_redirect_url" id="custom_redirect_url" 
                           value="<?php echo esc_attr($custom_redirect); ?>" 
                           class="regular-text" 
                           placeholder="https://ejemplo.com/mi-pagina" />
                    <p class="description">
                        Ingresa la URL completa a la que será redirigido este usuario después de iniciar sesión. 
                        Si se deja vacío, el usuario permanecerá en la página actual.
                    </p>
                    <p class="description">
                        <strong>Ejemplos:</strong><br>
                        • <?php echo home_url('/agentes'); ?> - Para ir a la página de agentes<br>
                        • <?php echo home_url('/dashboard'); ?> - Para ir al dashboard<br>
                        • <?php echo home_url('/mi-cuenta'); ?> - Para ir a mi cuenta
                    </p>
                </td>
            </tr>
        </table>
        <style>
            .form-table th[scope="row"] { width: 200px; }
            #custom_redirect_url { width: 100%; max-width: 500px; }
            .description { margin-top: 5px; font-style: italic; }
        </style>
        <?php
    }
    add_action('show_user_profile', 'add_custom_redirect_field');
    add_action('edit_user_profile', 'add_custom_redirect_field');
}

// Guardar el campo personalizado
if (!function_exists('save_custom_redirect_field')) {
    function save_custom_redirect_field($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        $custom_redirect = isset($_POST['custom_redirect_url']) ? sanitize_url($_POST['custom_redirect_url']) : '';
        
        // Validar que la URL sea válida si no está vacía
        if (!empty($custom_redirect) && !filter_var($custom_redirect, FILTER_VALIDATE_URL)) {
            add_action('user_profile_update_errors', function($errors) {
                $errors->add('custom_redirect_error', 'La URL de redirección no es válida.');
            });
            return false;
        }
        
        update_user_meta($user_id, 'custom_redirect_url', $custom_redirect);
    }
    add_action('personal_options_update', 'save_custom_redirect_field');
    add_action('edit_user_profile_update', 'save_custom_redirect_field');
}

// Agregar columna en la lista de usuarios
if (!function_exists('add_redirect_column_to_users')) {
    function add_redirect_column_to_users($columns) {
        $columns['custom_redirect'] = 'Redirección';
        return $columns;
    }
    add_filter('manage_users_columns', 'add_redirect_column_to_users');
}

if (!function_exists('show_redirect_column_content')) {
    function show_redirect_column_content($value, $column_name, $user_id) {
        if ($column_name == 'custom_redirect') {
            $custom_redirect = get_user_meta($user_id, 'custom_redirect_url', true);
            if (!empty($custom_redirect)) {
                return '<a href="' . esc_url($custom_redirect) . '" target="_blank" title="' . esc_attr($custom_redirect) . '">' . 
                       (strlen($custom_redirect) > 30 ? substr($custom_redirect, 0, 30) . '...' : $custom_redirect) . '</a>';
            } else {
                return '<span style="color: #999;">Sin configurar</span>';
            }
        }
        return $value;
    }
    add_filter('manage_users_custom_column', 'show_redirect_column_content', 10, 3);
}

// ============================================================================
// LÓGICA DE LOGIN Y VERIFICACIÓN DE USUARIO
// ============================================================================

// Verificar si el usuario está logueado
$is_logged_in = is_user_logged_in();
$current_user = wp_get_current_user();

// Verificación adicional para asegurar que el usuario esté realmente logueado
if ($is_logged_in && (!$current_user || $current_user->ID == 0)) {
    $is_logged_in = false;
}

// Forzar verificación más robusta
if (function_exists('is_user_logged_in') && is_user_logged_in()) {
    $is_logged_in = true;
} else {
    $is_logged_in = false;
}

// Debug: Verificar estado de login (remover en producción)
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Hero Section - Usuario logueado: ' . ($is_logged_in ? 'Sí' : 'No'));
    if ($is_logged_in) {
        error_log('Usuario actual: ' . $current_user->user_login . ' (ID: ' . $current_user->ID . ')');
    }
}

// Hook personalizado para manejar redirección después del login
if (!function_exists('hero_section_login_redirect')) {
    function hero_section_login_redirect($redirect_to, $request, $user) {
        // Si hay errores en el login, redirigir de vuelta con error
        if (is_wp_error($user)) {
            $error_code = $user->get_error_code();
            return home_url($_SERVER['REQUEST_URI'] . '?login=failed&error=' . $error_code);
        }
        
        // Si el login es exitoso, verificar redirección personalizada por usuario
        if (isset($user->user_login)) {
            $custom_redirect = get_user_meta($user->ID, 'custom_redirect_url', true);
            if (!empty($custom_redirect)) {
                return $custom_redirect;
            }
            // Si no hay redirección personalizada, mantener en la misma página
            return home_url($_SERVER['REQUEST_URI']);
        }
        return $redirect_to;
    }
    add_filter('login_redirect', 'hero_section_login_redirect', 10, 3);
}

// Hook para interceptar errores de login y evitar redirección a wp-login
if (!function_exists('hero_section_login_failed')) {
    function hero_section_login_failed($username) {
        $referrer = wp_get_referer();
        if ($referrer && !strstr($referrer, 'wp-login') && !strstr($referrer, 'wp-admin')) {
            wp_redirect($referrer . '?login=failed&username=' . urlencode($username));
            exit;
        }
    }
    add_action('wp_login_failed', 'hero_section_login_failed');
}

// Hook para manejar campos vacíos
if (!function_exists('hero_section_authenticate_empty_fields')) {
    function hero_section_authenticate_empty_fields($user, $username, $password) {
        if (empty($username) || empty($password)) {
            $referrer = wp_get_referer();
            if ($referrer && !strstr($referrer, 'wp-login') && !strstr($referrer, 'wp-admin')) {
                wp_redirect($referrer . '?login=empty');
                exit;
            }
        }
        return $user;
    }
    add_filter('authenticate', 'hero_section_authenticate_empty_fields', 1, 3);
}

// Preparar datos para el frontend
$hero_data = array(
    'is_logged_in' => $is_logged_in,
    'agentes_url' => home_url('/agentes'),
    'login_url' => wp_login_url(),
    'lostpassword_url' => wp_lostpassword_url(),
    'current_url' => home_url($_SERVER['REQUEST_URI'])
);

// Manejar errores de login
$login_error = '';
if (isset($_GET['login'])) {
    switch ($_GET['login']) {
        case 'failed':
            $error_code = isset($_GET['error']) ? $_GET['error'] : '';
            switch ($error_code) {
                case 'invalid_username':
                    $login_error = 'Error: El usuario ingresado no existe.';
                    break;
                case 'incorrect_password':
                    $login_error = 'Error: La contraseña es incorrecta.';
                    break;
                case 'invalid_email':
                    $login_error = 'Error: El email ingresado no es válido.';
                    break;
                default:
                    $login_error = 'Error: Usuario o contraseña incorrectos.';
                    break;
            }
            break;
        case 'empty':
            $login_error = 'Error: Por favor, completa todos los campos.';
            break;
        case 'invalid_username':
            $login_error = 'Error: Usuario no válido.';
            break;
        case 'incorrect_password':
            $login_error = 'Error: Contraseña incorrecta.';
            break;
    }
}

$hero_data['login_error'] = $login_error;
$hero_data['show_logout_message'] = isset($_GET['loggedout']) && $_GET['loggedout'] === 'true';

// Asegurar que jQuery esté disponible
wp_enqueue_script('jquery');

// Crear un script inline para pasar los datos
echo '<script type="text/javascript">';
echo 'window.heroData = ' . json_encode($hero_data) . ';';
echo 'console.log("[PHP] Estado de login:", ' . json_encode($is_logged_in) . ');';
echo 'console.log("[PHP] Datos completos:", window.heroData);';
echo '</script>';

// Agregar HTML del toast para errores de login
if (!empty($login_error)) {
    echo '
    <!-- Toast de Error de Login -->
    <div id="login-error-toast" class="hs-removing:translate-x-5 hs-removing:opacity-0 transition duration-300 max-w-xs bg-white border border-gray-200 rounded-xl shadow-lg dark:bg-gray-800 dark:border-gray-700 fixed top-4 right-4 z-50" role="alert">
        <div class="flex p-4">
            <div class="flex-shrink-0">
                <svg class="flex-shrink-0 size-4 text-red-500 mt-0.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/>
                </svg>
            </div>
            <div class="ms-3">
                <p class="text-sm text-gray-700 dark:text-gray-400">
                    ' . esc_html($login_error) . '
                </p>
            </div>
            <div class="ms-auto">
                <button type="button" class="inline-flex flex-shrink-0 justify-center items-center size-5 rounded-lg text-gray-800 opacity-50 hover:opacity-100 focus:outline-none focus:opacity-100 dark:text-white" data-hs-remove-element="#login-error-toast">
                    <span class="sr-only">Cerrar</span>
                    <svg class="flex-shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m18 6-12 12"/>
                        <path d="m6 6 12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-ocultar el toast después de 5 segundos
        setTimeout(function() {
            const toast = document.getElementById("login-error-toast");
            if (toast) {
                toast.classList.add("hs-removing");
                setTimeout(function() {
                    toast.remove();
                }, 300);
            }
        }, 5000);
        
        // Limpiar URL de parámetros de error
        if (window.location.search.includes("login=")) {
            const url = new URL(window.location);
            url.searchParams.delete("login");
            url.searchParams.delete("error");
            url.searchParams.delete("username");
            window.history.replaceState({}, document.title, url.pathname + url.search);
        }
    </script>';
}

// ============================================================================
// OCULTAR BARRA DE ADMINISTRACIÓN PARA USUARIOS NO ADMIN
// ============================================================================

// Ocultar la barra de administración para usuarios que no sean administradores
if (!function_exists('hide_admin_bar_for_non_admins')) {
    function hide_admin_bar_for_non_admins() {
        if (!current_user_can('administrator') && !is_admin()) {
            show_admin_bar(false);
        }
    }
    add_action('after_setup_theme', 'hide_admin_bar_for_non_admins');
}

// Remover la barra de administración del frontend para usuarios no admin
if (!function_exists('remove_admin_bar_for_non_admins')) {
    function remove_admin_bar_for_non_admins() {
        if (!current_user_can('administrator')) {
            add_filter('show_admin_bar', '__return_false');
        }
    }
    add_action('wp_loaded', 'remove_admin_bar_for_non_admins');
}

// Remover estilos CSS de la barra de administración para usuarios no admin
if (!function_exists('remove_admin_bar_styles_for_non_admins')) {
    function remove_admin_bar_styles_for_non_admins() {
        if (!current_user_can('administrator')) {
            wp_dequeue_style('admin-bar');
            wp_deregister_style('admin-bar');
        }
    }
    add_action('wp_enqueue_scripts', 'remove_admin_bar_styles_for_non_admins', 999);
}

// Agregar CSS personalizado para asegurar que no aparezca la barra
if (!function_exists('add_custom_admin_bar_css')) {
    function add_custom_admin_bar_css() {
        if (!current_user_can('administrator')) {
            echo '<style type="text/css">
                #wpadminbar { display: none !important; }
                html { margin-top: 0 !important; }
                body { margin-top: 0 !important; }
                body.admin-bar { padding-top: 0 !important; }
            </style>';
        }
    }
    add_action('wp_head', 'add_custom_admin_bar_css');
}

// ============================================================================
// FUNCIONES DE UTILIDAD PARA REDIRECCIONES PERSONALIZADAS
// ============================================================================

// Función para obtener la URL de redirección de un usuario
if (!function_exists('get_user_redirect_url')) {
    function get_user_redirect_url($user_id) {
        return get_user_meta($user_id, 'custom_redirect_url', true);
    }
}

// Función para establecer la URL de redirección de un usuario
if (!function_exists('set_user_redirect_url')) {
    function set_user_redirect_url($user_id, $url) {
        $sanitized_url = sanitize_url($url);
        if (empty($sanitized_url) || filter_var($sanitized_url, FILTER_VALIDATE_URL)) {
            return update_user_meta($user_id, 'custom_redirect_url', $sanitized_url);
        }
        return false;
    }
}

// ============================================================================
// INCLUIR INTERFAZ HTML
// ============================================================================

// Incluir el archivo HTML
include(__DIR__ . '/hero-section.html');
?>