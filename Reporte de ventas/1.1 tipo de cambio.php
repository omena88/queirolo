<?php
// Título: Tipo de Cambio (API ruc.com.pe) - WordPress AJAX
// Descripción: Code snippet para consultar tipo de cambio por rango de fechas usando admin-ajax.php

// Agregar hook de WordPress AJAX
add_action('wp_ajax_get_exchange_rates', 'handle_exchange_rate_api_request');
add_action('wp_ajax_nopriv_get_exchange_rates', 'handle_exchange_rate_api_request');

// Función para manejar la API de consulta de tipos de cambio
function handle_exchange_rate_api_request() {
    // Log de inicio
    error_log("DEBUG: Iniciando handle_exchange_rate_api_request");
    
    try {
        // Leer el JSON de fechas del POST
        $json_dates = isset($_POST['dates']) ? $_POST['dates'] : null;
        error_log("DEBUG: JSON de fechas recibido (con slashes): " . $json_dates);

        if (!$json_dates) {
            throw new Exception('No se recibieron fechas');
        }

        // WordPress a menudo añade slashes a los datos POST. Quitarlos.
        $json_dates_unslashed = wp_unslash($json_dates);
        error_log("DEBUG: JSON de fechas (unslashed): " . $json_dates_unslashed);
        
        // Decodificar el JSON limpio a un array de PHP
        $dates = json_decode($json_dates_unslashed, true);
        
        // Verificar si la decodificación falló
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ERROR: Fallo al decodificar JSON: " . json_last_error_msg());
            throw new Exception('Error al decodificar el JSON de fechas. Crudo: ' . $json_dates_unslashed);
        }
        
        // Log para debuggear
        error_log("DEBUG: Fechas decodificadas: " . print_r($dates, true));
        error_log("DEBUG: Tipo después de json_decode: " . gettype($dates));
        error_log("DEBUG: ¿Es array? " . (is_array($dates) ? 'SÍ' : 'NO'));
        
        if (!$dates || !is_array($dates)) {
            error_log("ERROR: Fechas inválidas después de json_decode");
            throw new Exception("El formato de fechas es inválido - JSON: " . $json_dates_unslashed);
        }
        
        // Validar que cada fecha tenga el formato correcto (DD/MM/AAAA)
        foreach ($dates as $index => $fecha) {
            error_log("DEBUG: Validando fecha $index: '$fecha' (tipo: " . gettype($fecha) . ")");
            if (!is_string($fecha) || !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fecha)) {
                error_log("ERROR: Fecha inválida en posición $index: '$fecha'");
                throw new Exception("Fecha inválida en posición $index: '$fecha' - Formato esperado: DD/MM/AAAA");
            }
            error_log("DEBUG: Fecha $index válida: $fecha");
        }
        
        error_log("DEBUG: Todas las fechas validadas correctamente");
        
        // Array para almacenar los tipos de cambio por fecha
        $exchange_rates = [];
        
        // Endpoint ruc.com.pe con rango de fechas y token proporcionado
        // Token debe tener el formato: "ef5e49a4-f461-4928-8b01-064e522a4a86-49610454-2d87-4152-bf66-2f7cd19f86c1"
        $api_token = '78cdfb10-f584-460b-9bb3-52c6b8073c41-2408ea68-7b93-45ad-92ec-82b43f209381';
        $endpoint = 'https://ruc.com.pe/api/v1/consultas';
        
        error_log("DEBUG: Token API: " . substr($api_token, 0, 10) . "...");
        error_log("DEBUG: Endpoint API: " . $endpoint);
        
        // Determinar rango [min,max] de fechas recibidas (ya están en DD/MM/AAAA)
        $minDate = null; $maxDate = null;
        foreach ($dates as $fecha) {
            if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fecha)) continue;
            
            // Convertir DD/MM/AAAA a timestamp para comparación
            $timestamp = DateTime::createFromFormat('d/m/Y', $fecha)->getTimestamp();
            
            if ($minDate === null || $timestamp < $minDate) $minDate = $timestamp;
            if ($maxDate === null || $timestamp > $maxDate) $maxDate = $timestamp;
        }
        if ($minDate === null || $maxDate === null) {
            error_log("ERROR: No se pudieron determinar fechas min/max válidas");
            throw new Exception('No se recibieron fechas válidas');
        }
        
        // Convertir timestamps de vuelta a DD/MM/AAAA
        $start = date('d/m/Y', $minDate);
        $end = date('d/m/Y', $maxDate);
        
        error_log("DEBUG: Rango de fechas - Start: $start, End: $end");
        
        $payload = [
            'token' => $api_token,
            'tipo_cambio' => [
                'moneda' => 'PEN',
                'fecha_inicio' => $start,
                'fecha_fin' => $end
            ]
        ];
        
        error_log("DEBUG: Payload enviado a API: " . json_encode($payload));
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 15
        ]);
        
        error_log("DEBUG: Iniciando llamada cURL a la API");
        $respuesta = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        curl_close($curl);
        
        error_log("DEBUG: Respuesta cURL - HTTP Code: $http_code");
        if ($curl_error) {
            error_log("ERROR: Error cURL: " . $curl_error);
        }
        error_log("DEBUG: Respuesta cruda de la API: " . $respuesta);
        
        if ($http_code == 200) {
            error_log("DEBUG: API respondió con éxito (HTTP 200)");
            $datos = json_decode($respuesta, true);
            error_log("DEBUG: Respuesta API decodificada: " . print_r($datos, true));
            
            // Verificar estructura exacta que espera la API según la documentación
            if (isset($datos['success']) && $datos['success'] && isset($datos['exchange_rates']) && is_array($datos['exchange_rates'])) {
                error_log("DEBUG: Estructura de respuesta válida, procesando exchange_rates");
                error_log("DEBUG: Cantidad de tipos de cambio recibidos: " . count($datos['exchange_rates']));
                
                foreach ($datos['exchange_rates'] as $index => $item) {
                    error_log("DEBUG: Procesando item $index: " . print_r($item, true));
                    
                    // Verificar estructura del item según documentación: { fecha: 'DD/MM/AAAA', moneda: 'PEN', compra: x, venta: y }
                    if (!isset($item['fecha'])) {
                        error_log("WARNING: Item $index sin fecha, saltando");
                        continue;
                    }
                    
                    if (!isset($item['moneda']) || $item['moneda'] !== 'PEN') {
                        error_log("WARNING: Item $index sin moneda PEN, saltando");
                        continue;
                    }
                    
                    if (!isset($item['venta'])) {
                        error_log("WARNING: Item $index sin precio de venta, saltando");
                        continue;
                    }
                    
                    $dmy = $item['fecha'];
                    error_log("DEBUG: Fecha recibida de API: $dmy");
                    
                    // Mantener fecha en formato DD/MM/AAAA (sin conversión)
                    $exchange_rates[$dmy] = floatval($item['venta']);
                    error_log("DEBUG: Fecha almacenada: $dmy, valor venta: " . $exchange_rates[$dmy]);
                }
            } else {
                error_log("WARNING: Estructura de respuesta inválida o sin exchange_rates");
                error_log("WARNING: success: " . ($datos['success'] ?? 'undefined'));
                error_log("WARNING: exchange_rates existe: " . (isset($datos['exchange_rates']) ? 'SÍ' : 'NO'));
                error_log("WARNING: exchange_rates es array: " . (is_array($datos['exchange_rates']) ? 'SÍ' : 'NO'));
                error_log("WARNING: Estructura completa de respuesta: " . print_r($datos, true));
            }
        } else {
            error_log("ERROR: API respondió con error HTTP: $http_code");
            error_log("ERROR: Respuesta de error: " . $respuesta);
        }
        
        error_log("DEBUG: Tipos de cambio finales: " . print_r($exchange_rates, true));
        
        // Devolver respuesta exitosa con tipos de cambio usando wp_send_json_success
        wp_send_json_success([
            'rates' => $exchange_rates
        ]);
        
    } catch (Exception $e) {
        // Devolver error usando wp_send_json_error
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}
?>