<?php
/**
 * Clase para el panel de administración
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_Admin {

    /**
     * Agregar página al menú
     */
    public function add_menu_page() {
        // Menú principal
        add_menu_page(
            __('RTT Reservas', 'rtt-reservas'),
            __('RTT Reservas', 'rtt-reservas'),
            'manage_options',
            'rtt-reservas',
            [$this, 'render_settings_page'],
            'dashicons-calendar-alt',
            30
        );

        // Submenú de Configuración (debe ser el primero para aparecer arriba)
        add_submenu_page(
            'rtt-reservas',
            __('Configuración', 'rtt-reservas'),
            __('Configuración', 'rtt-reservas'),
            'manage_options',
            'rtt-reservas', // Mismo slug que el padre para que sea la página principal
            [$this, 'render_settings_page']
        );
    }

    /**
     * Registrar configuraciones
     */
    public function register_settings() {
        register_setting('rtt_reservas_settings', 'rtt_reservas_options', [
            'sanitize_callback' => [$this, 'sanitize_options']
        ]);

        // Sección SMTP
        add_settings_section(
            'rtt_smtp_section',
            __('Configuración de Email (SMTP)', 'rtt-reservas'),
            [$this, 'smtp_section_callback'],
            'rtt-reservas'
        );

        // Campos SMTP
        $smtp_fields = [
            'smtp_host' => __('Servidor SMTP', 'rtt-reservas'),
            'smtp_port' => __('Puerto', 'rtt-reservas'),
            'smtp_secure' => __('Seguridad', 'rtt-reservas'),
            'smtp_user' => __('Usuario', 'rtt-reservas'),
            'smtp_pass' => __('Contraseña', 'rtt-reservas'),
            'from_email' => __('Email remitente', 'rtt-reservas'),
            'from_name' => __('Nombre remitente', 'rtt-reservas'),
            'cc_email' => __('Email copia (CC)', 'rtt-reservas'),
        ];

        foreach ($smtp_fields as $field => $label) {
            add_settings_field(
                $field,
                $label,
                [$this, 'render_field'],
                'rtt-reservas',
                'rtt_smtp_section',
                ['field' => $field, 'label' => $label]
            );
        }

        // Sección Asuntos de Email
        add_settings_section(
            'rtt_email_section',
            __('Asuntos de Email', 'rtt-reservas'),
            null,
            'rtt-reservas'
        );

        add_settings_field(
            'email_subject_es',
            __('Asunto (Español)', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_email_section',
            ['field' => 'email_subject_es']
        );

        add_settings_field(
            'email_subject_en',
            __('Asunto (Inglés)', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_email_section',
            ['field' => 'email_subject_en']
        );

        // Sección Personalización del Email
        add_settings_section(
            'rtt_email_template_section',
            __('Personalización del Email', 'rtt-reservas'),
            [$this, 'email_template_section_callback'],
            'rtt-reservas'
        );

        add_settings_field(
            'email_logo_url',
            __('URL del Logo', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_email_template_section',
            ['field' => 'email_logo_url']
        );

        add_settings_field(
            'email_slogan_es',
            __('Slogan (Español)', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_email_template_section',
            ['field' => 'email_slogan_es']
        );

        add_settings_field(
            'email_slogan_en',
            __('Slogan (Inglés)', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_email_template_section',
            ['field' => 'email_slogan_en']
        );

        add_settings_field(
            'email_contact_email',
            __('Email de contacto', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_email_template_section',
            ['field' => 'email_contact_email']
        );

        add_settings_field(
            'email_whatsapp',
            __('WhatsApp', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_email_template_section',
            ['field' => 'email_whatsapp']
        );

        add_settings_field(
            'email_website',
            __('Sitio Web', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_email_template_section',
            ['field' => 'email_website']
        );

        // Sección Configuración de Reservas
        add_settings_section(
            'rtt_booking_section',
            __('Configuración de Reservas', 'rtt-reservas'),
            null,
            'rtt-reservas'
        );

        add_settings_field(
            'max_passengers',
            __('Máximo de pasajeros por reserva', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_booking_section',
            ['field' => 'max_passengers']
        );
    }

    /**
     * Callback de sección SMTP
     */
    public function smtp_section_callback() {
        echo '<p>' . __('Configura los datos SMTP para el envío de correos. Si usas Gmail, usa smtp.gmail.com y genera una contraseña de aplicación.', 'rtt-reservas') . '</p>';
    }

    /**
     * Callback de sección plantilla email
     */
    public function email_template_section_callback() {
        echo '<p>' . __('Personaliza la apariencia del email de confirmación que reciben los clientes.', 'rtt-reservas') . '</p>';
    }

    /**
     * Renderizar campo de configuración
     */
    public function render_field($args) {
        $options = get_option('rtt_reservas_options', []);
        $field = $args['field'];
        $value = $options[$field] ?? '';

        switch ($field) {
            case 'smtp_pass':
                echo '<input type="password" name="rtt_reservas_options[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text">';
                break;

            case 'smtp_secure':
                echo '<select name="rtt_reservas_options[' . esc_attr($field) . ']">';
                echo '<option value="ssl"' . selected($value, 'ssl', false) . '>SSL</option>';
                echo '<option value="tls"' . selected($value, 'tls', false) . '>TLS</option>';
                echo '</select>';
                break;

            case 'smtp_port':
                echo '<input type="number" name="rtt_reservas_options[' . esc_attr($field) . ']" value="' . esc_attr($value ?: '465') . '" class="small-text">';
                echo '<p class="description">' . __('465 para SSL, 587 para TLS', 'rtt-reservas') . '</p>';
                break;

            case 'max_passengers':
                echo '<input type="number" name="rtt_reservas_options[' . esc_attr($field) . ']" value="' . esc_attr($value ?: '20') . '" class="small-text" min="1" max="100">';
                echo '<p class="description">' . __('Número máximo de pasajeros permitidos por reserva (por defecto: 20)', 'rtt-reservas') . '</p>';
                break;

            case 'email_logo_url':
                $default = 'http://readytotravelperu.com/wp-content/uploads/2022/08/ready-to-travel-peru.jpg';
                echo '<input type="url" name="rtt_reservas_options[' . esc_attr($field) . ']" value="' . esc_attr($value ?: $default) . '" class="large-text">';
                echo '<p class="description">' . __('URL completa del logo que aparecerá en el email', 'rtt-reservas') . '</p>';
                break;

            case 'email_slogan_es':
                $default = 'Donde cada viaje se convierte en un recuerdo inolvidable';
                echo '<input type="text" name="rtt_reservas_options[' . esc_attr($field) . ']" value="' . esc_attr($value ?: $default) . '" class="large-text">';
                break;

            case 'email_slogan_en':
                $default = 'Where every journey becomes an unforgettable memory';
                echo '<input type="text" name="rtt_reservas_options[' . esc_attr($field) . ']" value="' . esc_attr($value ?: $default) . '" class="large-text">';
                break;

            case 'email_contact_email':
                $default = 'reservas@readytotravelperu.com';
                echo '<input type="email" name="rtt_reservas_options[' . esc_attr($field) . ']" value="' . esc_attr($value ?: $default) . '" class="regular-text">';
                break;

            case 'email_whatsapp':
                $default = '+51 992 515 665';
                echo '<input type="text" name="rtt_reservas_options[' . esc_attr($field) . ']" value="' . esc_attr($value ?: $default) . '" class="regular-text">';
                echo '<p class="description">' . __('Número con código de país (ej: +51 999 999 999)', 'rtt-reservas') . '</p>';
                break;

            case 'email_website':
                $default = 'www.readytotravelperu.com';
                echo '<input type="text" name="rtt_reservas_options[' . esc_attr($field) . ']" value="' . esc_attr($value ?: $default) . '" class="regular-text">';
                break;

            default:
                echo '<input type="text" name="rtt_reservas_options[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text">';
        }
    }

    /**
     * Sanitizar opciones
     */
    public function sanitize_options($input) {
        $sanitized = [];

        $text_fields = ['smtp_host', 'smtp_user', 'from_name', 'email_subject_es', 'email_subject_en'];
        foreach ($text_fields as $field) {
            $sanitized[$field] = sanitize_text_field($input[$field] ?? '');
        }

        $email_fields = ['from_email', 'cc_email'];
        foreach ($email_fields as $field) {
            $sanitized[$field] = sanitize_email($input[$field] ?? '');
        }

        $sanitized['smtp_port'] = absint($input['smtp_port'] ?? 465);
        $sanitized['smtp_secure'] = in_array($input['smtp_secure'] ?? '', ['ssl', 'tls']) ? $input['smtp_secure'] : 'ssl';

        // La contraseña se guarda tal cual (ya está en la BD encriptada por WP)
        $sanitized['smtp_pass'] = $input['smtp_pass'] ?? '';

        // Máximo de pasajeros (entre 1 y 100, por defecto 20)
        $max_passengers = absint($input['max_passengers'] ?? 20);
        $sanitized['max_passengers'] = max(1, min(100, $max_passengers));

        // Campos de plantilla de email
        $sanitized['email_logo_url'] = esc_url_raw($input['email_logo_url'] ?? '');
        $sanitized['email_slogan_es'] = sanitize_text_field($input['email_slogan_es'] ?? '');
        $sanitized['email_slogan_en'] = sanitize_text_field($input['email_slogan_en'] ?? '');
        $sanitized['email_contact_email'] = sanitize_email($input['email_contact_email'] ?? '');
        $sanitized['email_whatsapp'] = sanitize_text_field($input['email_whatsapp'] ?? '');
        $sanitized['email_website'] = sanitize_text_field($input['email_website'] ?? '');

        return $sanitized;
    }

    /**
     * Renderizar página de configuración
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="rtt-admin-header" style="background: linear-gradient(135deg, #004070, #27AE60); color: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
                <h2 style="color: white; margin: 0 0 10px 0;">RTT Reservas - Ready To Travel Peru</h2>
                <p style="margin: 0;">Sistema de reservas de tours con generación de PDF y envío de emails.</p>
            </div>

            <div class="rtt-shortcode-info" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-left: 4px solid #27AE60; margin-bottom: 20px;">
                <h3 style="margin-top: 0;"><?php _e('Cómo usar el shortcode', 'rtt-reservas'); ?></h3>
                <p><?php _e('Inserta el formulario de reservas en cualquier página usando:', 'rtt-reservas'); ?></p>
                <code style="background: #f0f0f0; padding: 10px; display: block; margin: 10px 0;">[rtt_reserva]</code>
                <p><?php _e('Para versión en inglés:', 'rtt-reservas'); ?></p>
                <code style="background: #f0f0f0; padding: 10px; display: block; margin: 10px 0;">[rtt_reserva lang="en"]</code>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields('rtt_reservas_settings');
                do_settings_sections('rtt-reservas');
                submit_button(__('Guardar Configuración', 'rtt-reservas'));
                ?>
            </form>

            <div class="rtt-test-email" style="background: #fff; padding: 20px; border: 1px solid #ccc; margin-top: 20px;">
                <h3><?php _e('Probar configuración de email', 'rtt-reservas'); ?></h3>
                <p><?php _e('Envía un correo de prueba para verificar la configuración SMTP.', 'rtt-reservas'); ?></p>
                <input type="email" id="rtt-test-email" placeholder="tu@email.com" class="regular-text">
                <button type="button" id="rtt-send-test" class="button button-secondary"><?php _e('Enviar prueba', 'rtt-reservas'); ?></button>
                <span id="rtt-test-result" style="margin-left: 10px;"></span>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#rtt-send-test').on('click', function() {
                var email = $('#rtt-test-email').val();
                if (!email) {
                    alert('<?php _e('Ingresa un email', 'rtt-reservas'); ?>');
                    return;
                }

                $('#rtt-test-result').text('<?php _e('Enviando...', 'rtt-reservas'); ?>');

                $.post(ajaxurl, {
                    action: 'rtt_test_email',
                    email: email,
                    nonce: '<?php echo wp_create_nonce('rtt_test_email'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#rtt-test-result').html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    } else {
                        $('#rtt-test-result').html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Estilos del admin
     */
    public function enqueue_styles($hook) {
        if ('toplevel_page_rtt-reservas' !== $hook) {
            return;
        }

        // Estilos inline para simplicidad
        wp_add_inline_style('wp-admin', '
            .rtt-admin-header h2 { font-size: 24px; }
            .rtt-shortcode-info code { font-size: 14px; }
        ');
    }
}

// Agregar handler para test de email
add_action('wp_ajax_rtt_test_email', function() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rtt_test_email')) {
        wp_send_json_error(['message' => 'Error de seguridad']);
    }

    $email = sanitize_email($_POST['email'] ?? '');
    if (!is_email($email)) {
        wp_send_json_error(['message' => 'Email inválido']);
    }

    $sent = wp_mail(
        $email,
        'Test RTT Reservas',
        'Este es un correo de prueba del plugin RTT Reservas. Si lo recibes, la configuración es correcta.',
        ['Content-Type: text/html; charset=UTF-8']
    );

    if ($sent) {
        wp_send_json_success(['message' => 'Email enviado correctamente']);
    } else {
        wp_send_json_error(['message' => 'Error al enviar email. Revisa la configuración SMTP.']);
    }
});
