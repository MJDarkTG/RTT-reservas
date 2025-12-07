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
        // Verificar nonce
        if (!wp_verify_nonce($_POST['rtt_nonce'] ?? '', 'rtt_reserva_nonce')) {
            wp_send_json_error([
                'message' => __('Error de seguridad. Recarga la página e intenta de nuevo.', 'rtt-reservas')
            ]);
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

        // Generar PDF
        $pdf_generator = new RTT_PDF();
        $pdf_content = $pdf_generator->generate($data);

        if (is_wp_error($pdf_content)) {
            wp_send_json_error([
                'message' => $pdf_content->get_error_message()
            ]);
        }

        // Enviar email
        $mailer = new RTT_Mail();
        $email_sent = $mailer->send_confirmation($data, $pdf_content);

        if (is_wp_error($email_sent)) {
            // Aunque falle el email, la reserva ya está guardada
            // Actualizamos el estado para indicar que necesita revisión
            RTT_Database::update_notas($reserva_result['id'], 'Error al enviar email de confirmación');
        }

        // Respuesta exitosa
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

        // Sanitizar pasajeros
        foreach ($post_data['pasajeros'] as $index => $passenger) {
            $data['pasajeros'][] = [
                'tipo_doc' => sanitize_text_field($passenger['tipo_doc'] ?? 'DNI'),
                'nro_doc' => sanitize_text_field($passenger['nro_doc'] ?? ''),
                'nombre' => sanitize_text_field($passenger['nombre'] ?? ''),
                'genero' => sanitize_text_field($passenger['genero'] ?? 'M'),
                'fecha_nacimiento' => sanitize_text_field($passenger['fecha_nacimiento'] ?? ''),
                'nacionalidad' => sanitize_text_field($passenger['nacionalidad'] ?? ''),
                'alergias' => sanitize_textarea_field($passenger['alergias'] ?? '')
            ];
        }

        return $data;
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
