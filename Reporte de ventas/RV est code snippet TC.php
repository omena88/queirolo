<?php
// Título del snippet: Tipo de Cambio SUNAT
// Descripción: Shortcode para mostrar tipo de cambio SUNAT y API para consulta AJAX

// Verificar si es una petición AJAX o invocación normal
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dates'])) {
    handle_exchange_rate_api_request();
} else {
    // Registrar shortcode para uso en Elementor/WordPress
    add_shortcode('tipo_cambio_sunat', 'mostrar_tipo_cambio_sunat');
}

// Función para manejar la API de consulta de tipos de cambio
function handle_exchange_rate_api_request() {
    // Configurar cabeceras para respuesta JSON
    header('Content-Type: application/json');
    
    // Obtener el array de fechas recibido por POST
    $dates_json = $_POST['dates'];
    
    try {
        // Decodificar el JSON de fechas
        $dates = json_decode($dates_json, true);
        
        if (!$dates || !is_array($dates)) {
            throw new Exception("El formato de fechas es inválido");
        }
        
        // Array para almacenar los tipos de cambio por fecha
        $exchange_rates = [];
        $api_token = 'apis-token-12950.udHumwDUX2jFHWzATs6Ao8NDPGuq3qx1'; // ¡Mantener seguro este token!
        
        // Consultar cada fecha
        foreach ($dates as $fecha) {
            // Validar formato de fecha YYYY-MM-DD
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                continue; // Saltamos las fechas con formato incorrecto
            }
            
            $url = 'https://api.apis.net.pe/v2/sunat/tipo-cambio?date=' . $fecha;
            
            // Inicializar cURL
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $api_token,
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 10
            ]);
            
            $respuesta = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($http_code == 200) {
                $datos = json_decode($respuesta, true);
                // Guardar solo el precio de venta
                $exchange_rates[$fecha] = $datos['precioVenta'];
            }
        }
        
        // Devolver respuesta exitosa con tipos de cambio
        echo json_encode([
            'success' => true,
            'data' => [
                'rates' => $exchange_rates
            ]
        ]);
        
    } catch (Exception $e) {
        // Devolver error en formato JSON
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
    exit; // Terminar la ejecución
}

// Función original para el shortcode
function mostrar_tipo_cambio_sunat($atts) {
    // Parámetros del shortcode
    $atributos = shortcode_atts(array(
        'fecha' => date('Y-m-d') // Fecha actual por defecto
    ), $atts);
    
    $fecha = $atributos['fecha'];
    $api_token = 'apis-token-12950.udHumwDUX2jFHWzATs6Ao8NDPGuq3qx1';
    $url = 'https://api.apis.net.pe/v2/sunat/tipo-cambio?date=' . $fecha;
    
    // Inicializar cURL
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $api_token,
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $respuesta = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($http_code == 200) {
        $datos = json_decode($respuesta, true);
        $html = '<div class="tipo-cambio-sunat">';
        $html .= '<p>Fecha: ' . $fecha . '</p>';
        $html .= '<p>Compra: S/ ' . $datos['precioCompra'] . '</p>';
        $html .= '<p>Venta: S/ ' . $datos['precioVenta'] . '</p>';
        $html .= '</div>';
        return $html;
    } else {
        return '<p>Error al obtener tipo de cambio.</p>';
    }
}
?>