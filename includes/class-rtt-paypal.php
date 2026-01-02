<?php
/**
 * Clase para manejar pagos con PayPal
 *
 * @package RTT_Reservas
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_PayPal {

    /**
     * URLs de API según el entorno
     */
    const SANDBOX_API_URL = 'https://api-m.sandbox.paypal.com';
    const LIVE_API_URL = 'https://api-m.paypal.com';

    /**
     * Verificar si PayPal está habilitado y configurado
     */
    public static function is_enabled() {
        $options = get_option('rtt_reservas_options', []);
        return !empty($options['paypal_enabled'])
            && !empty($options['paypal_client_id'])
            && !empty($options['paypal_secret']);
    }

    /**
     * Verificar si está en modo sandbox
     */
    public static function is_sandbox() {
        $options = get_option('rtt_reservas_options', []);
        return !empty($options['paypal_sandbox']);
    }

    /**
     * Obtener configuración de PayPal
     */
    public static function get_config() {
        $options = get_option('rtt_reservas_options', []);
        return [
            'client_id' => $options['paypal_client_id'] ?? '',
            'secret' => $options['paypal_secret'] ?? '',
            'sandbox' => !empty($options['paypal_sandbox']),
        ];
    }

    /**
     * Obtener URL base de la API
     */
    public static function get_api_url() {
        return self::is_sandbox() ? self::SANDBOX_API_URL : self::LIVE_API_URL;
    }

    /**
     * Obtener Access Token de PayPal (OAuth2)
     *
     * @return string|WP_Error Access token o error
     */
    public static function get_access_token() {
        $config = self::get_config();

        if (empty($config['client_id']) || empty($config['secret'])) {
            return new WP_Error('missing_credentials', __('Credenciales de PayPal no configuradas', 'rtt-reservas'));
        }

        // Intentar obtener token cacheado
        $cache_key = 'rtt_paypal_token_' . md5($config['client_id']);
        $cached_token = get_transient($cache_key);

        if ($cached_token) {
            return $cached_token;
        }

        $api_url = self::get_api_url();
        $auth = base64_encode($config['client_id'] . ':' . $config['secret']);

        $response = wp_remote_post($api_url . '/v1/oauth2/token', [
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials',
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200 || empty($body['access_token'])) {
            $error_msg = $body['error_description'] ?? 'Error al obtener token de PayPal';
            return new WP_Error('token_error', $error_msg);
        }

        // Cachear token (expira en ~9 horas, cacheamos por 8)
        $expires_in = isset($body['expires_in']) ? intval($body['expires_in']) - 3600 : 28800;
        set_transient($cache_key, $body['access_token'], $expires_in);

        return $body['access_token'];
    }

    /**
     * Crear orden de pago en PayPal
     *
     * @param array $data Datos del pago
     * @return array|WP_Error Respuesta de PayPal o error
     */
    public static function create_order($data) {
        $access_token = self::get_access_token();

        if (is_wp_error($access_token)) {
            return $access_token;
        }

        $api_url = self::get_api_url();

        // Preparar datos de la orden
        $order_data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $data['reference_id'] ?? uniqid('RTT-'),
                    'description' => $data['description'] ?? 'Reserva Ready To Travel Peru',
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => number_format((float)$data['amount'], 2, '.', ''),
                    ],
                ],
            ],
            'application_context' => [
                'brand_name' => 'Ready To Travel Peru',
                'landing_page' => 'NO_PREFERENCE',
                'user_action' => 'PAY_NOW',
                'return_url' => $data['return_url'] ?? home_url(),
                'cancel_url' => $data['cancel_url'] ?? home_url(),
            ],
        ];

        $response = wp_remote_post($api_url . '/v2/checkout/orders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
            'body' => json_encode($order_data),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 201 || empty($body['id'])) {
            $error_msg = $body['message'] ?? 'Error al crear orden en PayPal';
            return new WP_Error('order_error', $error_msg);
        }

        return $body;
    }

    /**
     * Capturar pago de una orden aprobada
     *
     * @param string $order_id ID de la orden de PayPal
     * @return array|WP_Error Respuesta de PayPal o error
     */
    public static function capture_order($order_id) {
        $access_token = self::get_access_token();

        if (is_wp_error($access_token)) {
            return $access_token;
        }

        $api_url = self::get_api_url();

        $response = wp_remote_post($api_url . '/v2/checkout/orders/' . $order_id . '/capture', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
            'body' => '{}',
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 201 || empty($body['status'])) {
            $error_msg = $body['message'] ?? 'Error al capturar pago en PayPal';
            return new WP_Error('capture_error', $error_msg);
        }

        return $body;
    }

    /**
     * Obtener detalles de una orden
     *
     * @param string $order_id ID de la orden de PayPal
     * @return array|WP_Error Respuesta de PayPal o error
     */
    public static function get_order($order_id) {
        $access_token = self::get_access_token();

        if (is_wp_error($access_token)) {
            return $access_token;
        }

        $api_url = self::get_api_url();

        $response = wp_remote_get($api_url . '/v2/checkout/orders/' . $order_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200 || empty($body['id'])) {
            $error_msg = $body['message'] ?? 'Error al obtener orden de PayPal';
            return new WP_Error('order_error', $error_msg);
        }

        return $body;
    }

    /**
     * Probar conexión con PayPal
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public static function test_connection() {
        if (!self::is_enabled()) {
            return [
                'success' => false,
                'message' => __('PayPal no está configurado. Ingresa Client ID y Secret.', 'rtt-reservas')
            ];
        }

        $token = self::get_access_token();

        if (is_wp_error($token)) {
            return [
                'success' => false,
                'message' => $token->get_error_message()
            ];
        }

        $mode = self::is_sandbox() ? 'Sandbox' : 'Live';

        return [
            'success' => true,
            'message' => sprintf(__('Conexion exitosa con PayPal (%s)', 'rtt-reservas'), $mode)
        ];
    }

    /**
     * Obtener URL del SDK de JavaScript
     */
    public static function get_sdk_url() {
        $config = self::get_config();
        $client_id = $config['client_id'];

        return 'https://www.paypal.com/sdk/js?client-id=' . urlencode($client_id) . '&currency=USD&intent=capture';
    }

    /**
     * Registrar pago en la base de datos
     *
     * @param int $reserva_id ID de la reserva
     * @param array $payment_data Datos del pago
     * @return bool
     */
    public static function save_payment($reserva_id, $payment_data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_reservas';

        return $wpdb->update(
            $table,
            [
                'payment_method' => 'paypal',
                'payment_status' => $payment_data['status'] ?? 'completed',
                'transaction_id' => $payment_data['transaction_id'] ?? '',
                'payment_amount' => $payment_data['amount'] ?? 0,
                'payment_date' => current_time('mysql'),
                'estado' => 'pagada',
            ],
            ['id' => $reserva_id],
            ['%s', '%s', '%s', '%f', '%s', '%s'],
            ['%d']
        );
    }

    /**
     * Registrar pago de cotización
     *
     * @param int $cotizacion_id ID de la cotización
     * @param array $payment_data Datos del pago
     * @return bool
     */
    public static function save_cotizacion_payment($cotizacion_id, $payment_data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_cotizaciones';

        return $wpdb->update(
            $table,
            [
                'payment_method' => 'paypal',
                'payment_status' => $payment_data['status'] ?? 'completed',
                'transaction_id' => $payment_data['transaction_id'] ?? '',
                'payment_amount' => $payment_data['amount'] ?? 0,
                'payment_date' => current_time('mysql'),
                'estado' => 'pagada',
            ],
            ['id' => $cotizacion_id],
            ['%s', '%s', '%s', '%f', '%s', '%s'],
            ['%d']
        );
    }
}

// AJAX Handlers
add_action('wp_ajax_rtt_paypal_create_order', 'rtt_paypal_create_order_handler');
add_action('wp_ajax_nopriv_rtt_paypal_create_order', 'rtt_paypal_create_order_handler');

function rtt_paypal_create_order_handler() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rtt_paypal_nonce')) {
        wp_send_json_error(['message' => __('Error de seguridad', 'rtt-reservas')]);
    }

    // Rate limiting: máximo 1 creación de orden cada 5 segundos por IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_key = 'rtt_paypal_create_' . md5($ip);

    if (get_transient($rate_key)) {
        wp_send_json_error([
            'message' => __('Por favor espera unos segundos antes de crear otra orden.', 'rtt-reservas')
        ]);
    }

    if (!RTT_PayPal::is_enabled()) {
        wp_send_json_error(['message' => __('PayPal no está habilitado', 'rtt-reservas')]);
    }

    $amount = floatval($_POST['amount'] ?? 0);
    $description = sanitize_text_field($_POST['description'] ?? 'Reserva');
    $reference_id = sanitize_text_field($_POST['reference_id'] ?? uniqid('RTT-'));

    if ($amount <= 0) {
        wp_send_json_error(['message' => __('Monto inválido', 'rtt-reservas')]);
    }

    $result = RTT_PayPal::create_order([
        'amount' => $amount,
        'description' => $description,
        'reference_id' => $reference_id,
    ]);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    // Establecer rate limit por 5 segundos después de creación exitosa
    set_transient($rate_key, true, 5);

    wp_send_json_success([
        'order_id' => $result['id'],
        'status' => $result['status'],
    ]);
}

add_action('wp_ajax_rtt_paypal_capture_order', 'rtt_paypal_capture_order_handler');
add_action('wp_ajax_nopriv_rtt_paypal_capture_order', 'rtt_paypal_capture_order_handler');

function rtt_paypal_capture_order_handler() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rtt_paypal_nonce')) {
        wp_send_json_error(['message' => __('Error de seguridad', 'rtt-reservas')]);
    }

    // Rate limiting: máximo 1 captura cada 3 segundos por IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_key = 'rtt_paypal_capture_' . md5($ip);

    if (get_transient($rate_key)) {
        wp_send_json_error([
            'message' => __('Por favor espera unos segundos antes de procesar otro pago.', 'rtt-reservas')
        ]);
    }

    $order_id = sanitize_text_field($_POST['order_id'] ?? '');
    $reserva_id = intval($_POST['reserva_id'] ?? 0);
    $cotizacion_id = intval($_POST['cotizacion_id'] ?? 0);

    if (empty($order_id)) {
        wp_send_json_error(['message' => __('Order ID requerido', 'rtt-reservas')]);
    }

    $result = RTT_PayPal::capture_order($order_id);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    if ($result['status'] === 'COMPLETED') {
        // Extraer datos de la transacción
        $capture = $result['purchase_units'][0]['payments']['captures'][0] ?? [];
        $payment_data = [
            'status' => 'completed',
            'transaction_id' => $capture['id'] ?? $order_id,
            'amount' => $capture['amount']['value'] ?? 0,
        ];

        // Guardar según el tipo
        if ($reserva_id > 0) {
            RTT_PayPal::save_payment($reserva_id, $payment_data);
        } elseif ($cotizacion_id > 0) {
            RTT_PayPal::save_cotizacion_payment($cotizacion_id, $payment_data);

            // Enviar notificación al vendedor
            do_action('rtt_cotizacion_paid', $cotizacion_id, $payment_data);
        }

        // Establecer rate limit por 3 segundos después de captura exitosa
        set_transient($rate_key, true, 3);

        wp_send_json_success([
            'status' => 'COMPLETED',
            'transaction_id' => $payment_data['transaction_id'],
            'message' => __('Pago completado exitosamente', 'rtt-reservas'),
        ]);
    }

    wp_send_json_error(['message' => __('El pago no pudo ser completado', 'rtt-reservas')]);
}

// Test connection AJAX
add_action('wp_ajax_rtt_test_paypal', function() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rtt_test_paypal')) {
        wp_send_json_error(['message' => __('Error de seguridad', 'rtt-reservas')]);
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Sin permisos', 'rtt-reservas')]);
    }

    $result = RTT_PayPal::test_connection();

    if ($result['success']) {
        wp_send_json_success(['message' => $result['message']]);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
});
