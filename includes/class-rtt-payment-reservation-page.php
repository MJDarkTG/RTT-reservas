<?php
/**
 * Página de pago para reservas
 * URL: /pagar-reserva?codigo=RTT-20250101-1234
 *
 * @package RTT_Reservas
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_Payment_Reservation_Page {

    /**
     * Slug de la página
     */
    const PAGE_SLUG = 'pagar-reserva';

    /**
     * Inicializar
     */
    public function init() {
        add_action('init', [$this, 'add_rewrite_rules']);
        add_action('template_redirect', [$this, 'handle_payment_page']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Agregar reglas de rewrite
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^' . self::PAGE_SLUG . '/?$',
            'index.php?rtt_payment_reservation=1',
            'top'
        );

        add_rewrite_tag('%rtt_payment_reservation%', '1');
    }

    /**
     * Manejar la página de pago
     */
    public function handle_payment_page() {
        if (get_query_var('rtt_payment_reservation')) {
            $this->render_payment_page();
            exit;
        }
    }

    /**
     * Encolar assets
     */
    public function enqueue_assets() {
        if (get_query_var('rtt_payment_reservation')) {
            // CSS
            wp_enqueue_style(
                'rtt-payment-reservation-css',
                RTT_RESERVAS_PLUGIN_URL . 'assets/css/payment-page.css',
                [],
                RTT_RESERVAS_VERSION
            );

            // PayPal JS si está habilitado
            if (class_exists('RTT_PayPal') && RTT_PayPal::is_enabled()) {
                wp_enqueue_script(
                    'rtt-paypal-js',
                    RTT_RESERVAS_PLUGIN_URL . 'assets/js/rtt-paypal.js',
                    ['jquery'],
                    RTT_RESERVAS_VERSION,
                    true
                );

                $paypal_config = RTT_PayPal::get_config();
                wp_localize_script('rtt-paypal-js', 'rttPayPal', [
                    'enabled' => true,
                    'clientId' => $paypal_config['client_id'],
                    'sandbox' => $paypal_config['sandbox'],
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('rtt_paypal_nonce'),
                ]);
            }
        }
    }

    /**
     * Renderizar página de pago
     */
    private function render_payment_page() {
        // Obtener código de reserva de la URL
        $codigo = sanitize_text_field($_GET['codigo'] ?? '');
        $lang = sanitize_text_field($_GET['lang'] ?? 'es');

        if (empty($codigo)) {
            $this->render_error($lang === 'en'
                ? 'No reservation code provided.'
                : 'No se proporcionó código de reserva.'
            );
            return;
        }

        // Buscar la reserva
        $reserva = RTT_Database::get_reserva_by_codigo($codigo);

        if (!$reserva) {
            $this->render_error($lang === 'en'
                ? 'Reservation not found.'
                : 'Reserva no encontrada.'
            );
            return;
        }

        // Verificar si ya está pagada
        if (!empty($reserva->payment_status) && $reserva->payment_status === 'completed') {
            $this->render_already_paid($reserva, $lang);
            return;
        }

        // Renderizar página de pago
        $this->render_payment_form($reserva, $lang);
    }

    /**
     * Renderizar error
     */
    private function render_error($message) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($message); ?></title>
            <?php wp_head(); ?>
        </head>
        <body class="rtt-payment-page">
            <div class="rtt-payment-container">
                <div class="rtt-payment-error">
                    <h2><?php echo esc_html($message); ?></h2>
                </div>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    /**
     * Renderizar mensaje de pago ya realizado
     */
    private function render_already_paid($reserva, $lang) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo $lang === 'en' ? 'Payment Completed' : 'Pago Completado'; ?></title>
            <?php wp_head(); ?>
        </head>
        <body class="rtt-payment-page">
            <div class="rtt-payment-container">
                <div class="rtt-payment-success-message">
                    <div class="success-icon">✓</div>
                    <h2><?php echo $lang === 'en' ? 'Payment Already Completed' : 'Pago Ya Completado'; ?></h2>
                    <p><?php echo $lang === 'en'
                        ? 'This reservation has already been paid and confirmed.'
                        : 'Esta reserva ya ha sido pagada y confirmada.'; ?></p>
                    <div class="reservation-code">
                        <strong><?php echo $lang === 'en' ? 'Reservation Code:' : 'Código de Reserva:'; ?></strong>
                        <?php echo esc_html($reserva->codigo); ?>
                    </div>
                </div>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    /**
     * Renderizar formulario de pago
     */
    private function render_payment_form($reserva, $lang) {
        $paypal_enabled = class_exists('RTT_PayPal') && RTT_PayPal::is_enabled();

        // Calcular comisión PayPal
        $precio_base = floatval($reserva->precio ?? 0);
        $comision_paypal = ($precio_base * 0.044) + 0.30;
        $precio_total = $precio_base + $comision_paypal;

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo $lang === 'en' ? 'Pay Reservation' : 'Pagar Reserva'; ?></title>
            <?php wp_head(); ?>
        </head>
        <body class="rtt-payment-page rtt-reservation-payment">
            <div class="rtt-payment-container">
                <!-- Header -->
                <div class="rtt-payment-header">
                    <h1><?php echo $lang === 'en' ? 'Complete Your Payment' : 'Completa tu Pago'; ?></h1>
                    <p class="rtt-payment-subtitle">
                        <?php echo $lang === 'en'
                            ? 'Confirm your reservation by completing the payment'
                            : 'Confirma tu reserva completando el pago'; ?>
                    </p>
                </div>

                <!-- Detalles de la reserva -->
                <div class="rtt-reservation-details">
                    <h3><?php echo $lang === 'en' ? 'Reservation Details' : 'Detalles de la Reserva'; ?></h3>

                    <div class="rtt-detail-row">
                        <span class="rtt-detail-label"><?php echo $lang === 'en' ? 'Reservation Code:' : 'Código de Reserva:'; ?></span>
                        <span class="rtt-detail-value"><strong><?php echo esc_html($reserva->codigo); ?></strong></span>
                    </div>

                    <div class="rtt-detail-row">
                        <span class="rtt-detail-label"><?php echo $lang === 'en' ? 'Tour:' : 'Tour:'; ?></span>
                        <span class="rtt-detail-value"><?php echo esc_html($reserva->tour); ?></span>
                    </div>

                    <div class="rtt-detail-row">
                        <span class="rtt-detail-label"><?php echo $lang === 'en' ? 'Date:' : 'Fecha:'; ?></span>
                        <span class="rtt-detail-value"><?php echo esc_html(date('d/m/Y', strtotime($reserva->fecha))); ?></span>
                    </div>

                    <div class="rtt-detail-row">
                        <span class="rtt-detail-label"><?php echo $lang === 'en' ? 'Passengers:' : 'Pasajeros:'; ?></span>
                        <span class="rtt-detail-value"><?php echo esc_html($reserva->cantidad_pasajeros); ?></span>
                    </div>

                    <div class="rtt-detail-row">
                        <span class="rtt-detail-label"><?php echo $lang === 'en' ? 'Client:' : 'Cliente:'; ?></span>
                        <span class="rtt-detail-value"><?php echo esc_html($reserva->nombre_representante); ?></span>
                    </div>
                </div>

                <!-- Desglose de precio -->
                <div class="rtt-payment-breakdown">
                    <div class="rtt-payment-line">
                        <span class="rtt-payment-label"><?php echo $lang === 'en' ? 'Tour Price:' : 'Precio del Tour:'; ?></span>
                        <span class="rtt-payment-value">$<?php echo number_format($precio_base, 2); ?> USD</span>
                    </div>
                    <div class="rtt-payment-line rtt-payment-fee">
                        <span class="rtt-payment-label"><?php echo $lang === 'en' ? 'PayPal Fee (4.4% + $0.30):' : 'Comisión PayPal (4.4% + $0.30):'; ?></span>
                        <span class="rtt-payment-value">$<?php echo number_format($comision_paypal, 2); ?> USD</span>
                    </div>
                    <div class="rtt-payment-line rtt-payment-total-line">
                        <span class="rtt-payment-label"><?php echo $lang === 'en' ? 'Total to Pay:' : 'Total a Pagar:'; ?></span>
                        <span class="rtt-payment-value">$<?php echo number_format($precio_total, 2); ?> USD</span>
                    </div>
                </div>

                <!-- Hidden fields for JS -->
                <input type="hidden" id="reserva-id" value="<?php echo esc_attr($reserva->id); ?>">
                <input type="hidden" id="reserva-codigo" value="<?php echo esc_attr($reserva->codigo); ?>">
                <input type="hidden" id="paypal-amount" value="<?php echo esc_attr($precio_base); ?>">

                <?php if ($paypal_enabled): ?>
                <!-- PayPal Payment -->
                <div class="rtt-payment-method">
                    <h3><?php echo $lang === 'en' ? 'Pay with PayPal' : 'Pagar con PayPal'; ?></h3>
                    <p><?php echo $lang === 'en'
                        ? 'Click the button below to pay securely with PayPal or credit card.'
                        : 'Haz clic en el botón para pagar de forma segura con PayPal o tarjeta de crédito.'; ?></p>
                    <div id="paypal-message"></div>
                    <div id="paypal-button-container"></div>
                </div>
                <?php else: ?>
                <!-- PayPal no configurado -->
                <div class="rtt-payment-offline">
                    <h3><?php echo $lang === 'en' ? 'Payment Methods' : 'Formas de Pago'; ?></h3>
                    <p><?php echo $lang === 'en'
                        ? 'Please contact us to complete your payment.'
                        : 'Por favor contáctanos para completar tu pago.'; ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    /**
     * Obtener URL de pago para una reserva
     */
    public static function get_payment_url($codigo, $lang = 'es') {
        return home_url('/' . self::PAGE_SLUG . '/?codigo=' . urlencode($codigo) . '&lang=' . $lang);
    }
}
