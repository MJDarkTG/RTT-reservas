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

        // Sección Notificaciones WhatsApp (CallMeBot)
        add_settings_section(
            'rtt_whatsapp_section',
            __('Notificaciones WhatsApp (CallMeBot)', 'rtt-reservas'),
            [$this, 'whatsapp_section_callback'],
            'rtt-reservas'
        );

        add_settings_field(
            'whatsapp_enabled',
            __('Activar notificaciones', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_whatsapp_section',
            ['field' => 'whatsapp_enabled']
        );

        add_settings_field(
            'whatsapp_phone',
            __('Número de WhatsApp', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_whatsapp_section',
            ['field' => 'whatsapp_phone']
        );

        add_settings_field(
            'whatsapp_apikey',
            __('API Key de CallMeBot', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_whatsapp_section',
            ['field' => 'whatsapp_apikey']
        );

        // Sección PayPal
        add_settings_section(
            'rtt_paypal_section',
            __('Pagos con PayPal', 'rtt-reservas'),
            [$this, 'paypal_section_callback'],
            'rtt-reservas'
        );

        add_settings_field(
            'paypal_enabled',
            __('Activar PayPal', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_paypal_section',
            ['field' => 'paypal_enabled']
        );

        add_settings_field(
            'paypal_sandbox',
            __('Modo Sandbox', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_paypal_section',
            ['field' => 'paypal_sandbox']
        );

        add_settings_field(
            'paypal_client_id',
            __('Client ID', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_paypal_section',
            ['field' => 'paypal_client_id']
        );

        add_settings_field(
            'paypal_secret',
            __('Secret Key', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_paypal_section',
            ['field' => 'paypal_secret']
        );

        // Sección Cotizaciones
        add_settings_section(
            'rtt_cotizacion_section',
            __('Configuración de Cotizaciones', 'rtt-reservas'),
            [$this, 'cotizacion_section_callback'],
            'rtt-reservas'
        );

        add_settings_field(
            'cotizacion_formas_pago',
            __('Formas de Pago', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_cotizacion_section',
            ['field' => 'cotizacion_formas_pago']
        );

        add_settings_field(
            'cotizacion_terminos',
            __('Términos y Condiciones', 'rtt-reservas'),
            [$this, 'render_field'],
            'rtt-reservas',
            'rtt_cotizacion_section',
            ['field' => 'cotizacion_terminos']
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
     * Callback de sección WhatsApp
     */
    public function whatsapp_section_callback() {
        echo '<p>' . __('Recibe alertas en WhatsApp cuando llegue una nueva reserva usando CallMeBot (gratis).', 'rtt-reservas') . '</p>';
        echo '<p><strong>' . __('Pasos para configurar:', 'rtt-reservas') . '</strong></p>';
        echo '<ol>';
        echo '<li>' . __('Agrega el número <code>+34 623 78 95 80</code> a tus contactos de WhatsApp', 'rtt-reservas') . '</li>';
        echo '<li>' . __('Envía el mensaje <code>I allow callmebot to send me messages</code> desde tu WhatsApp', 'rtt-reservas') . '</li>';
        echo '<li>' . __('Recibirás tu API Key. Cópiala aquí abajo.', 'rtt-reservas') . '</li>';
        echo '</ol>';
        echo '<p><a href="https://www.callmebot.com/blog/free-api-whatsapp-messages/" target="_blank">' . __('Ver instrucciones completas', 'rtt-reservas') . ' →</a></p>';
    }

    /**
     * Callback de sección PayPal
     */
    public function paypal_section_callback() {
        echo '<p>' . __('Acepta pagos con PayPal en reservas y cotizaciones. Los pagos se procesan en USD.', 'rtt-reservas') . '</p>';
        echo '<p><strong>' . __('Pasos para configurar:', 'rtt-reservas') . '</strong></p>';
        echo '<ol>';
        echo '<li>' . __('Crea una app en <a href="https://developer.paypal.com/dashboard/applications/sandbox" target="_blank">PayPal Developer Dashboard</a>', 'rtt-reservas') . '</li>';
        echo '<li>' . __('Copia el Client ID y Secret Key', 'rtt-reservas') . '</li>';
        echo '<li>' . __('Activa "Modo Sandbox" para pruebas, desactívalo en producción', 'rtt-reservas') . '</li>';
        echo '</ol>';
    }

    /**
     * Callback de sección Cotizaciones
     */
    public function cotizacion_section_callback() {
        echo '<p>' . __('Configura la información que aparecerá en los PDFs de cotización generados desde el panel de vendedor.', 'rtt-reservas') . '</p>';
        echo '<p><strong>' . __('Acceso al panel de vendedor:', 'rtt-reservas') . '</strong> <a href="' . home_url('/vendedor/') . '" target="_blank">' . home_url('/vendedor/') . '</a></p>';
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

            case 'whatsapp_enabled':
                echo '<label>';
                echo '<input type="checkbox" name="rtt_reservas_options[' . esc_attr($field) . ']" value="1"' . checked($value, '1', false) . '>';
                echo ' ' . __('Enviar alerta por WhatsApp cuando llegue una nueva reserva', 'rtt-reservas');
                echo '</label>';
                break;

            case 'whatsapp_phone':
                echo '<input type="text" name="rtt_reservas_options[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" placeholder="+51999999999">';
                echo '<p class="description">' . __('Tu número de WhatsApp con código de país, sin espacios (ej: +51999999999)', 'rtt-reservas') . '</p>';
                break;

            case 'whatsapp_apikey':
                echo '<input type="text" name="rtt_reservas_options[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" placeholder="123456">';
                echo '<p class="description">' . __('API Key que te envió CallMeBot por WhatsApp', 'rtt-reservas') . '</p>';
                break;

            // Campos PayPal
            case 'paypal_enabled':
                echo '<label>';
                echo '<input type="checkbox" name="rtt_reservas_options[' . esc_attr($field) . ']" value="1"' . checked($value, '1', false) . '>';
                echo ' ' . __('Permitir pagos con PayPal en reservas y cotizaciones', 'rtt-reservas');
                echo '</label>';
                break;

            case 'paypal_sandbox':
                echo '<label>';
                echo '<input type="checkbox" name="rtt_reservas_options[' . esc_attr($field) . ']" value="1"' . checked($value, '1', false) . '>';
                echo ' ' . __('Usar modo Sandbox (pruebas) - Desactiva esto en producción', 'rtt-reservas');
                echo '</label>';
                break;

            case 'paypal_client_id':
                echo '<input type="text" name="rtt_reservas_options[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="large-text">';
                echo '<p class="description">' . __('Client ID de tu aplicación PayPal', 'rtt-reservas') . '</p>';
                break;

            case 'paypal_secret':
                echo '<input type="password" name="rtt_reservas_options[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="large-text">';
                echo '<p class="description">' . __('Secret Key de tu aplicación PayPal (se guarda encriptado)', 'rtt-reservas') . '</p>';
                break;

            case 'cotizacion_formas_pago':
                $default = "1. TRANSFERENCIA BANCARIA
   Banco: BCP - Banco de Crédito del Perú
   Cuenta Corriente Soles: XXX-XXXXXXX-X-XX
   Cuenta Corriente Dólares: XXX-XXXXXXX-X-XX
   CCI: XXXXXXXXXXXXXXXXXXX
   Titular: Ready To Travel Peru

2. PAYPAL
   Cuenta: pagos@readytotravelperu.com
   (Se aplica comisión de 5%)

3. PAGO EN EFECTIVO
   En nuestras oficinas o al momento del tour

* Enviar comprobante de pago a: reservas@readytotravelperu.com";
                echo '<textarea name="rtt_reservas_options[' . esc_attr($field) . ']" rows="12" class="large-text code">' . esc_textarea($value ?: $default) . '</textarea>';
                echo '<p class="description">' . __('Información de cuentas bancarias, PayPal, etc. que aparecerá en el PDF de cotización.', 'rtt-reservas') . '</p>';
                break;

            case 'cotizacion_terminos':
                $default = "- Esta cotización tiene validez de 7 días a partir de la fecha de emisión.
- Los precios están sujetos a disponibilidad y pueden variar sin previo aviso.
- Para confirmar la reserva se requiere un depósito del 50% del total.
- El saldo restante debe cancelarse 48 horas antes del inicio del tour.
- Cancelaciones con más de 72 horas: devolución del 80% del depósito.
- Cancelaciones con menos de 72 horas: no hay devolución.
- Los tours están sujetos a condiciones climáticas.
- Es obligatorio presentar documento de identidad el día del tour.
- Menores de edad deben estar acompañados por un adulto responsable.";
                echo '<textarea name="rtt_reservas_options[' . esc_attr($field) . ']" rows="10" class="large-text code">' . esc_textarea($value ?: $default) . '</textarea>';
                echo '<p class="description">' . __('Términos y condiciones que aparecerán en el PDF de cotización.', 'rtt-reservas') . '</p>';
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

        // Campos de WhatsApp CallMeBot
        $sanitized['whatsapp_enabled'] = isset($input['whatsapp_enabled']) ? '1' : '';
        $sanitized['whatsapp_phone'] = preg_replace('/[^0-9+]/', '', $input['whatsapp_phone'] ?? '');
        $sanitized['whatsapp_apikey'] = sanitize_text_field($input['whatsapp_apikey'] ?? '');

        // Campos de PayPal
        $sanitized['paypal_enabled'] = isset($input['paypal_enabled']) ? '1' : '';
        $sanitized['paypal_sandbox'] = isset($input['paypal_sandbox']) ? '1' : '';
        $sanitized['paypal_client_id'] = sanitize_text_field($input['paypal_client_id'] ?? '');
        $sanitized['paypal_secret'] = sanitize_text_field($input['paypal_secret'] ?? '');

        // Campos de Cotización (permitir saltos de línea)
        $sanitized['cotizacion_formas_pago'] = sanitize_textarea_field($input['cotizacion_formas_pago'] ?? '');
        $sanitized['cotizacion_terminos'] = sanitize_textarea_field($input['cotizacion_terminos'] ?? '');

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

            <div class="rtt-test-whatsapp" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-left: 4px solid #25D366; margin-top: 20px;">
                <h3 style="color: #25D366;"><?php _e('Probar notificación WhatsApp', 'rtt-reservas'); ?></h3>
                <p><?php _e('Envía un mensaje de prueba para verificar la configuración de CallMeBot.', 'rtt-reservas'); ?></p>
                <button type="button" id="rtt-send-whatsapp-test" class="button button-secondary" style="background: #25D366; color: white; border-color: #25D366;">
                    <span class="dashicons dashicons-whatsapp" style="margin-top: 3px;"></span>
                    <?php _e('Enviar prueba WhatsApp', 'rtt-reservas'); ?>
                </button>
                <span id="rtt-whatsapp-result" style="margin-left: 10px;"></span>
            </div>

            <div class="rtt-test-paypal" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-left: 4px solid #0070ba; margin-top: 20px;">
                <h3 style="color: #0070ba;"><?php _e('Probar conexión PayPal', 'rtt-reservas'); ?></h3>
                <p><?php _e('Verifica que las credenciales de PayPal estén correctas.', 'rtt-reservas'); ?></p>
                <button type="button" id="rtt-test-paypal" class="button button-secondary" style="background: #0070ba; color: white; border-color: #0070ba;">
                    <span class="dashicons dashicons-money-alt" style="margin-top: 3px;"></span>
                    <?php _e('Probar conexión', 'rtt-reservas'); ?>
                </button>
                <span id="rtt-paypal-result" style="margin-left: 10px;"></span>
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

            $('#rtt-send-whatsapp-test').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $('#rtt-whatsapp-result').text('<?php _e('Enviando...', 'rtt-reservas'); ?>');

                $.post(ajaxurl, {
                    action: 'rtt_test_whatsapp',
                    nonce: '<?php echo wp_create_nonce('rtt_test_whatsapp'); ?>'
                }, function(response) {
                    btn.prop('disabled', false);
                    if (response.success) {
                        $('#rtt-whatsapp-result').html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    } else {
                        $('#rtt-whatsapp-result').html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                    }
                }).fail(function() {
                    btn.prop('disabled', false);
                    $('#rtt-whatsapp-result').html('<span style="color: red;">✗ Error de conexión</span>');
                });
            });

            // Test PayPal
            $('#rtt-test-paypal').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $('#rtt-paypal-result').text('<?php _e('Verificando...', 'rtt-reservas'); ?>');

                $.post(ajaxurl, {
                    action: 'rtt_test_paypal',
                    nonce: '<?php echo wp_create_nonce('rtt_test_paypal'); ?>'
                }, function(response) {
                    btn.prop('disabled', false);
                    if (response.success) {
                        $('#rtt-paypal-result').html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    } else {
                        $('#rtt-paypal-result').html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                    }
                }).fail(function() {
                    btn.prop('disabled', false);
                    $('#rtt-paypal-result').html('<span style="color: red;">✗ Error de conexión</span>');
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
