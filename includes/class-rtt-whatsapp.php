<?php
/**
 * Clase para enviar notificaciones por WhatsApp usando CallMeBot
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_WhatsApp {

    /**
     * URL de la API de CallMeBot
     */
    const API_URL = 'https://api.callmebot.com/whatsapp.php';

    /**
     * Verificar si WhatsApp está configurado y habilitado
     */
    public static function is_enabled() {
        $options = get_option('rtt_reservas_options', []);
        return !empty($options['whatsapp_enabled'])
            && !empty($options['whatsapp_phone'])
            && !empty($options['whatsapp_apikey']);
    }

    /**
     * Obtener configuración de WhatsApp
     */
    private static function get_config() {
        $options = get_option('rtt_reservas_options', []);
        return [
            'phone' => $options['whatsapp_phone'] ?? '',
            'apikey' => $options['whatsapp_apikey'] ?? '',
        ];
    }

    /**
     * Enviar mensaje por WhatsApp
     *
     * @param string $message El mensaje a enviar
     * @return array ['success' => bool, 'message' => string]
     */
    public static function send_message($message) {
        if (!self::is_enabled()) {
            return [
                'success' => false,
                'message' => __('WhatsApp no está configurado', 'rtt-reservas')
            ];
        }

        $config = self::get_config();

        // Preparar URL con parámetros
        $url = add_query_arg([
            'phone' => $config['phone'],
            'text' => $message,
            'apikey' => $config['apikey'],
        ], self::API_URL);

        // Hacer la petición
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);

        // CallMeBot devuelve "Message queued" si fue exitoso
        if ($code === 200 && strpos($body, 'Message queued') !== false) {
            return [
                'success' => true,
                'message' => __('Mensaje enviado correctamente', 'rtt-reservas')
            ];
        }

        // Analizar errores comunes de CallMeBot
        if (strpos($body, 'API Key not valid') !== false) {
            return [
                'success' => false,
                'message' => __('API Key no válida. Verifica tu configuración.', 'rtt-reservas')
            ];
        }

        if (strpos($body, 'Phone number not found') !== false) {
            return [
                'success' => false,
                'message' => __('Número de teléfono no registrado en CallMeBot.', 'rtt-reservas')
            ];
        }

        return [
            'success' => false,
            'message' => sprintf(__('Error: %s', 'rtt-reservas'), $body ?: 'Unknown error')
        ];
    }

    /**
     * Enviar notificación de nueva reserva
     *
     * @param object $reserva Datos de la reserva
     * @return array
     */
    public static function send_new_reservation_alert($reserva) {
        // Formatear fecha
        $fecha_tour = date_i18n('d/m/Y', strtotime($reserva->fecha_tour));

        // Construir mensaje
        $message = "*Nueva Reserva RTT*\n\n";
        $message .= "Codigo: {$reserva->codigo}\n";
        $message .= "Tour: {$reserva->tour}\n";
        $message .= "Fecha: {$fecha_tour}\n";
        $message .= "Cliente: {$reserva->nombre_representante}\n";
        $message .= "Email: {$reserva->email}\n";
        $message .= "Tel: {$reserva->telefono}\n";
        $message .= "Pais: {$reserva->pais}\n";
        $message .= "Pasajeros: {$reserva->cantidad_pasajeros}\n\n";
        $message .= "Ver en admin: " . admin_url("admin.php?page=rtt-reservas-list&reserva={$reserva->id}");

        return self::send_message($message);
    }

    /**
     * Enviar mensaje de prueba
     */
    public static function send_test_message() {
        $peru_tz = new DateTimeZone('America/Lima');
        $now = new DateTime('now', $peru_tz);

        $message = "*Test RTT Reservas*\n\n";
        $message .= "Las notificaciones de WhatsApp estan funcionando correctamente.\n\n";
        $message .= "Fecha/Hora: " . $now->format('d/m/Y H:i:s') . "\n";
        $message .= "Sitio: " . get_bloginfo('name');

        return self::send_message($message);
    }
}

// Handler AJAX para test de WhatsApp
add_action('wp_ajax_rtt_test_whatsapp', function() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rtt_test_whatsapp')) {
        wp_send_json_error(['message' => __('Error de seguridad', 'rtt-reservas')]);
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Sin permisos', 'rtt-reservas')]);
    }

    if (!RTT_WhatsApp::is_enabled()) {
        wp_send_json_error(['message' => __('Configura primero el numero y API Key de CallMeBot', 'rtt-reservas')]);
    }

    $result = RTT_WhatsApp::send_test_message();

    if ($result['success']) {
        wp_send_json_success(['message' => $result['message']]);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
});
