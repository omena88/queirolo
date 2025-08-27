<?php
/**
 * Snippet para cargar Preline UI globalmente en WordPress
 * Para usar con Code Snippets Plugin
 * 
 * Instrucciones:
 * 1. Copia este código en Code Snippets Plugin
 * 2. Configúralo para ejecutarse en "Frontend" 
 * 3. Actívalo
 * 4. Preline UI estará disponible en toda la web
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cargar Preline UI CSS y JS en el frontend
 */
function load_preline_ui_assets() {
    // Solo cargar en el frontend, no en admin
    if (is_admin()) {
        return;
    }
    
    // Cargar CSS de Preline UI
    wp_enqueue_style(
        'preline-ui-css',
        'https://preline.co/assets/css/main.min.css',
        array(),
        '1.0.0',
        'all'
    );
    
    // Cargar JavaScript de Preline UI
    wp_enqueue_script(
        'preline-ui-js',
        'https://preline.co/assets/js/hs-ui.bundle.js',
        array(),
        '1.0.0',
        true // Cargar en el footer
    );
}

// Hook para cargar los assets
add_action('wp_enqueue_scripts', 'load_preline_ui_assets');

/**
 * Inicializar Preline UI después de que se cargue el DOM
 */
function init_preline_ui_script() {
    if (is_admin()) {
        return;
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar Preline UI si está disponible
        if (typeof HSStaticMethods !== 'undefined') {
            HSStaticMethods.autoInit();
        }
        
        // Re-inicializar cuando se carga contenido dinámico
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                if (typeof HSStaticMethods !== 'undefined') {
                    HSStaticMethods.autoInit();
                }
            }, 100);
        });
    </script>
    <?php
}

// Hook para agregar el script de inicialización
add_action('wp_footer', 'init_preline_ui_script');

/**
 * Agregar clases de Tailwind CSS si no están disponibles
 * (Opcional - solo si no tienes Tailwind CSS cargado)
 */
function load_tailwind_css() {
    if (is_admin()) {
        return;
    }
    
    // Verificar si Tailwind ya está cargado
    global $wp_styles;
    $tailwind_loaded = false;
    
    if (isset($wp_styles->registered)) {
        foreach ($wp_styles->registered as $handle => $style) {
            if (strpos($style->src, 'tailwind') !== false) {
                $tailwind_loaded = true;
                break;
            }
        }
    }
    
    // Si Tailwind no está cargado, cargar desde CDN
    if (!$tailwind_loaded) {
        wp_enqueue_style(
            'tailwind-css',
            'https://cdn.tailwindcss.com',
            array(),
            '3.3.0',
            'all'
        );
    }
}

// Cargar Tailwind CSS (necesario para Preline UI)
add_action('wp_enqueue_scripts', 'load_tailwind_css');

/**
 * Función helper para verificar si Preline UI está cargado
 * Puedes usar esta función en tus snippets
 */
function is_preline_ui_loaded() {
    global $wp_scripts;
    
    if (isset($wp_scripts->registered['preline-ui-js'])) {
        return true;
    }
    
    return false;
}

/**
 * Agregar meta tag para viewport (necesario para responsive)
 */
function add_preline_viewport_meta() {
    if (is_admin()) {
        return;
    }
    
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
}

// Hook para agregar viewport meta
add_action('wp_head', 'add_preline_viewport_meta', 1);

/**
 * Mensaje de confirmación en el admin (solo para debugging)
 * Elimina esta función en producción
 */
function preline_ui_admin_notice() {
    if (current_user_can('manage_options')) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>Preline UI:</strong> Cargado correctamente en el frontend.</p>';
        echo '</div>';
    }
}

// Mensaje de confirmación en admin
add_action('admin_notices', 'preline_ui_admin_notice');

?>