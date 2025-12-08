<?php
/**
 * Clase para manejar peticiones AJAX
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_Ajax {

    /**
     * Procesar envío de reserva
     */
    public function submit_reserva() {
        // Rate limiting: máximo 1 envío cada 30 segundos por IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rate_key = 'rtt_rate_' . md5($ip);

        if (get_transient($rate_key)) {
            $this->log_failed_attempt($ip, 'rate_limit');
            wp_send_json_error([
                'message' => __('Por favor espera unos segundos antes de enviar otra reserva.', 'rtt-reservas')
            ]);
            return;
        }

        // Establecer rate limit por 30 segundos
        set_transient($rate_key, true, 30);

        // Honeypot anti-spam: si el campo tiene valor, es un bot
        if (!empty($_POST['rtt_website_url'])) {
            // Registrar intento de spam
            $this->log_failed_attempt($ip, 'honeypot');
            wp_send_json_error([
                'message' => __('Error al procesar la solicitud.', 'rtt-reservas')
            ]);
            return;
        }

        // Verificar nonce
        if (!wp_verify_nonce($_POST['rtt_nonce'] ?? '', 'rtt_reserva_nonce')) {
            $this->log_failed_attempt($ip, 'invalid_nonce');
            wp_send_json_error([
                'message' => __('Error de seguridad. Recarga la página e intenta de nuevo.', 'rtt-reservas')
            ]);
            return;
        }

        // Sanitizar y validar datos
        $data = $this->sanitize_form_data($_POST);

        if (is_wp_error($data)) {
            wp_send_json_error([
                'message' => $data->get_error_message()
            ]);
        }

        // Guardar en base de datos
        $db_data = [
            'tour' => $data['tour'],
            'fecha' => $this->format_date_for_db($data['fecha']),
            'precio_tour' => $data['precio_tour'],
            'nombre_representante' => $data['representante']['nombre'],
            'email' => $data['representante']['email'],
            'telefono' => $data['representante']['telefono'],
            'pais' => $data['representante']['pais'],
            'cantidad_pasajeros' => count($data['pasajeros']),
            'lang' => $data['lang']
        ];

        $reserva_result = RTT_Database::insert_reserva($db_data);

        if (is_wp_error($reserva_result)) {
            wp_send_json_error([
                'message' => $reserva_result->get_error_message()
            ]);
        }

        // Guardar pasajeros
        RTT_Database::insert_pasajeros($reserva_result['id'], $data['pasajeros']);

        // Agregar código de reserva a los datos para el PDF
        $data['codigo'] = $reserva_result['codigo'];
        $data['reserva_id'] = $reserva_result['id'];

        // Programar envío de email en segundo plano (más rápido para el usuario)
        $this->schedule_email_send($data);

        // Respuesta exitosa (inmediata, sin esperar el email)
        $lang = sanitize_text_field($_POST['lang'] ?? 'es');
        $success_message = $lang === 'en'
            ? 'Booking sent successfully! Your reservation code is: ' . $reserva_result['codigo']
            : '¡Reserva enviada correctamente! Tu código de reserva es: ' . $reserva_result['codigo'];

        wp_send_json_success([
            'message' => $success_message,
            'codigo' => $reserva_result['codigo']
        ]);
    }

    /**
     * Formatear fecha para base de datos (de dd-MM-YYYY a YYYY-MM-DD)
     */
    private function format_date_for_db($fecha) {
        // Intentar parsear diferentes formatos
        $date = DateTime::createFromFormat('d-m-Y', $fecha);
        if (!$date) {
            $date = DateTime::createFromFormat('Y-m-d', $fecha);
        }
        if (!$date) {
            $date = new DateTime($fecha);
        }
        return $date ? $date->format('Y-m-d') : date('Y-m-d');
    }

    /**
     * Sanitizar datos del formulario
     */
    private function sanitize_form_data($post_data) {
        $lang = sanitize_text_field($post_data['lang'] ?? 'es');

        // Validar campos requeridos
        $required_fields = ['tour', 'fecha', 'nombre_representante', 'email', 'telefono', 'pais'];

        foreach ($required_fields as $field) {
            if (empty($post_data[$field])) {
                $field_name = $this->get_field_label($field, $lang);
                return new WP_Error(
                    'missing_field',
                    sprintf(
                        $lang === 'en' ? 'The field "%s" is required.' : 'El campo "%s" es requerido.',
                        $field_name
                    )
                );
            }
        }

        // Validar email
        $email = sanitize_email($post_data['email']);
        if (!is_email($email)) {
            return new WP_Error(
                'invalid_email',
                $lang === 'en' ? 'Please enter a valid email address.' : 'Por favor ingresa un correo electrónico válido.'
            );
        }

        // Validar pasajeros
        if (empty($post_data['pasajeros']) || !is_array($post_data['pasajeros'])) {
            return new WP_Error(
                'no_passengers',
                $lang === 'en' ? 'You must add at least one passenger.' : 'Debes agregar al menos un pasajero.'
            );
        }

        // Validar límite de pasajeros
        $options = get_option('rtt_reservas_options', []);
        $max_passengers = isset($options['max_passengers']) ? absint($options['max_passengers']) : 20;
        $num_pasajeros = count($post_data['pasajeros']);
        if ($num_pasajeros > $max_passengers) {
            return new WP_Error(
                'too_many_passengers',
                $lang === 'en'
                    ? sprintf('Maximum %d passengers allowed per reservation.', $max_passengers)
                    : sprintf('Máximo %d pasajeros permitidos por reserva.', $max_passengers)
            );
        }

        // Sanitizar datos
        $data = [
            'lang' => $lang,
            'tour' => sanitize_text_field($post_data['tour']),
            'fecha' => sanitize_text_field($post_data['fecha']),
            'precio_tour' => sanitize_text_field($post_data['precio_tour'] ?? ''),
            'representante' => [
                'nombre' => sanitize_text_field($post_data['nombre_representante']),
                'email' => $email,
                'telefono' => sanitize_text_field($post_data['telefono']),
                'pais' => sanitize_text_field($post_data['pais'])
            ],
            'pasajeros' => []
        ];

        // Sanitizar y validar pasajeros
        foreach ($post_data['pasajeros'] as $index => $passenger) {
            $tipo_doc = sanitize_text_field($passenger['tipo_doc'] ?? 'DNI');
            $nro_doc = sanitize_text_field($passenger['nro_doc'] ?? '');
            $nombre = sanitize_text_field($passenger['nombre'] ?? '');

            // Validar que el nombre no esté vacío
            if (empty($nombre)) {
                $passenger_num = $index + 1;
                return new WP_Error(
                    'invalid_passenger_name',
                    $lang === 'en'
                        ? sprintf('Passenger %d: Name is required.', $passenger_num)
                        : sprintf('Pasajero %d: El nombre es requerido.', $passenger_num)
                );
            }

            // Validar formato de documento
            if (!empty($nro_doc) && !$this->validate_document($tipo_doc, $nro_doc)) {
                $passenger_num = $index + 1;
                return new WP_Error(
                    'invalid_document',
                    $lang === 'en'
                        ? sprintf('Passenger %d: Invalid document format. DNI must be 7-9 digits, Passport 6-15 alphanumeric characters.', $passenger_num)
                        : sprintf('Pasajero %d: Formato de documento inválido. DNI debe tener 7-9 dígitos, Pasaporte 6-15 caracteres alfanuméricos.', $passenger_num)
                );
            }

            $data['pasajeros'][] = [
                'tipo_doc' => $tipo_doc,
                'nro_doc' => strtoupper($nro_doc),
                'nombre' => $nombre,
                'genero' => sanitize_text_field($passenger['genero'] ?? 'M'),
                'fecha_nacimiento' => sanitize_text_field($passenger['fecha_nacimiento'] ?? ''),
                'nacionalidad' => sanitize_text_field($passenger['nacionalidad'] ?? ''),
                'alergias' => sanitize_textarea_field($passenger['alergias'] ?? '')
            ];
        }

        return $data;
    }

    /**
     * Validar formato de documento
     */
    private function validate_document($tipo_doc, $nro_doc) {
        $nro_doc = trim($nro_doc);

        if (empty($nro_doc)) {
            return false;
        }

        switch (strtoupper($tipo_doc)) {
            case 'DNI':
                // DNI: 8 dígitos numéricos (Perú) o 7-9 dígitos (otros países)
                return preg_match('/^[0-9]{7,9}$/', $nro_doc);

            case 'PASAPORTE':
                // Pasaporte: alfanumérico, 6-15 caracteres
                return preg_match('/^[A-Z0-9]{6,15}$/i', $nro_doc);

            default:
                // Otros documentos: mínimo 5 caracteres alfanuméricos
                return preg_match('/^[A-Z0-9]{5,20}$/i', $nro_doc);
        }
    }

    /**
     * Registrar intento fallido (para log de seguridad)
     */
    private function log_failed_attempt($ip, $reason) {
        $log_key = 'rtt_failed_attempts';
        $attempts = get_option($log_key, []);

        // Mantener solo los últimos 100 intentos
        if (count($attempts) >= 100) {
            $attempts = array_slice($attempts, -99);
        }

        $attempts[] = [
            'ip' => $ip,
            'reason' => $reason,
            'time' => current_time('mysql'),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200)
        ];

        update_option($log_key, $attempts, false);
    }

    /**
     * Programar envío de email en segundo plano
     */
    private function schedule_email_send($data) {
        // Guardar datos temporalmente para el cron
        $transient_key = 'rtt_email_' . $data['reserva_id'];
        set_transient($transient_key, $data, HOUR_IN_SECONDS);

        // Programar envío inmediato en segundo plano
        wp_schedule_single_event(time(), 'rtt_send_reservation_email', [$data['reserva_id']]);

        // Forzar ejecución del cron si es posible
        spawn_cron();
    }

    /**
     * Enviar email de reserva (llamado por cron)
     */
    public static function send_reservation_email_cron($reserva_id) {
        $transient_key = 'rtt_email_' . $reserva_id;
        $data = get_transient($transient_key);

        if (!$data) {
            return; // Datos expirados o ya procesados
        }

        // Generar PDF
        $pdf_generator = new RTT_PDF();
        $pdf_content = $pdf_generator->generate($data);

        if (is_wp_error($pdf_content)) {
            RTT_Database::update_notas($reserva_id, 'Error al generar PDF: ' . $pdf_content->get_error_message());
            delete_transient($transient_key);
            return;
        }

        // Enviar email
        $mailer = new RTT_Mail();
        $email_sent = $mailer->send_confirmation($data, $pdf_content);

        if (is_wp_error($email_sent)) {
            RTT_Database::update_notas($reserva_id, 'Error al enviar email de confirmación');
        }

        // Limpiar transient
        delete_transient($transient_key);
    }

    /**
     * Obtener etiqueta de campo
     */
    private function get_field_label($field, $lang) {
        $labels = [
            'es' => [
                'tour' => 'Tour',
                'fecha' => 'Fecha de reserva',
                'nombre_representante' => 'Nombre del representante',
                'email' => 'Correo electrónico',
                'telefono' => 'Teléfono',
                'pais' => 'País'
            ],
            'en' => [
                'tour' => 'Tour',
                'fecha' => 'Reservation date',
                'nombre_representante' => 'Representative name',
                'email' => 'Email',
                'telefono' => 'Phone',
                'pais' => 'Country'
            ]
        ];

        return $labels[$lang][$field] ?? $field;
    }

    /**
     * Obtener lista de tours (para autocompletado)
     */
    public function get_tours() {
        $lang = sanitize_text_field($_GET['lang'] ?? 'es');
        $tours = RTT_Tours::get_tours_grouped($lang);

        wp_send_json_success($tours);
    }
}
