<?php
/**
 * Página de pago para cotizaciones
 * Shortcode: [rtt_pago_cotizacion]
 *
 * @package RTT_Reservas
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_Payment_Page {

    /**
     * Inicializar
     */
    public function init() {
        add_shortcode('rtt_pago_cotizacion', [$this, 'render']);

        // Estilos para la página de pago
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
    }

    /**
     * Encolar estilos si estamos en una página con el shortcode
     */
    public function enqueue_styles() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'rtt_pago_cotizacion')) {
            wp_enqueue_style(
                'rtt-payment-page-css',
                RTT_RESERVAS_PLUGIN_URL . 'assets/css/payment-page.css',
                [],
                RTT_RESERVAS_VERSION
            );

            // PayPal JS if enabled
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
     * Renderizar el shortcode
     */
    public function render($atts) {
        $atts = shortcode_atts([
            'lang' => 'es'
        ], $atts, 'rtt_pago_cotizacion');

        $lang = sanitize_text_field($atts['lang']);

        // Obtener código de cotización de la URL
        $codigo = sanitize_text_field($_GET['codigo'] ?? '');

        if (empty($codigo)) {
            return $this->render_error($lang === 'en'
                ? 'No quotation code provided.'
                : 'No se proporcionó código de cotización.'
            );
        }

        // Buscar la cotización
        $cotizacion = RTT_Database::get_cotizacion_by_codigo($codigo);

        if (!$cotizacion) {
            return $this->render_error($lang === 'en'
                ? 'Quotation not found.'
                : 'Cotización no encontrada.'
            );
        }

        // Verificar si ya está pagada
        if ($cotizacion->payment_status === 'completed') {
            return $this->render_already_paid($cotizacion, $lang);
        }

        // Verificar si está vencida
        if ($cotizacion->estado === 'vencida') {
            return $this->render_error($lang === 'en'
                ? 'This quotation has expired. Please contact us for a new one.'
                : 'Esta cotización ha vencido. Por favor contáctenos para una nueva.'
            );
        }

        // Verificar si PayPal está habilitado
        $paypal_enabled = class_exists('RTT_PayPal') && RTT_PayPal::is_enabled();

        ob_start();
        ?>
        <div class="rtt-cotizacion-payment" data-lang="<?php echo esc_attr($lang); ?>">
            <div class="rtt-payment-card">
                <!-- Header con logo -->
                <div class="rtt-payment-card-header">
                    <h1><?php echo $lang === 'en' ? 'Payment for Your Quotation' : 'Pago de tu Cotización'; ?></h1>
                    <p class="rtt-codigo"><?php echo esc_html($codigo); ?></p>
                </div>

                <!-- Detalles de la cotización -->
                <div class="rtt-payment-details">
                    <div class="rtt-detail-row">
                        <span class="rtt-detail-label"><?php echo $lang === 'en' ? 'Tour:' : 'Tour:'; ?></span>
                        <span class="rtt-detail-value" id="cotizacion-tour"><?php echo esc_html($cotizacion->tour); ?></span>
                    </div>
                    <div class="rtt-detail-row">
                        <span class="rtt-detail-label"><?php echo $lang === 'en' ? 'Date:' : 'Fecha:'; ?></span>
                        <span class="rtt-detail-value"><?php echo date_i18n('d/m/Y', strtotime($cotizacion->fecha_tour)); ?></span>
                    </div>
                    <div class="rtt-detail-row">
                        <span class="rtt-detail-label"><?php echo $lang === 'en' ? 'Passengers:' : 'Pasajeros:'; ?></span>
                        <span class="rtt-detail-value"><?php echo esc_html($cotizacion->cantidad_pasajeros); ?></span>
                    </div>
                    <div class="rtt-detail-row">
                        <span class="rtt-detail-label"><?php echo $lang === 'en' ? 'Client:' : 'Cliente:'; ?></span>
                        <span class="rtt-detail-value"><?php echo esc_html($cotizacion->cliente_nombre); ?></span>
                    </div>
                </div>

                <!-- Monto total con desglose de comisión PayPal -->
                <?php
                $precio_base = floatval($cotizacion->precio_total);
                $comision_paypal = ($precio_base * 0.044) + 0.30;
                $precio_total_con_comision = $precio_base + $comision_paypal;
                ?>
                <div class="rtt-payment-breakdown">
                    <div class="rtt-payment-line">
                        <span class="rtt-payment-label"><?php echo $lang === 'en' ? 'Quotation Amount:' : 'Monto de Cotización:'; ?></span>
                        <span class="rtt-payment-value">$<?php echo number_format($precio_base, 2); ?> <?php echo esc_html($cotizacion->moneda); ?></span>
                    </div>
                    <div class="rtt-payment-line rtt-payment-fee">
                        <span class="rtt-payment-label"><?php echo $lang === 'en' ? 'PayPal Fee (4.4% + $0.30):' : 'Comisión PayPal (4.4% + $0.30):'; ?></span>
                        <span class="rtt-payment-value">$<?php echo number_format($comision_paypal, 2); ?> USD</span>
                    </div>
                    <div class="rtt-payment-line rtt-payment-total-line">
                        <span class="rtt-payment-label"><?php echo $lang === 'en' ? 'Total to Pay:' : 'Total a Pagar:'; ?></span>
                        <span class="rtt-payment-value">$<?php echo number_format($precio_total_con_comision, 2); ?> USD</span>
                    </div>
                </div>

                <!-- Hidden fields for JS -->
                <input type="hidden" id="cotizacion-id" value="<?php echo esc_attr($cotizacion->id); ?>">
                <input type="hidden" id="cotizacion-codigo" value="<?php echo esc_attr($codigo); ?>">
                <input type="hidden" id="paypal-amount" value="<?php echo esc_attr($cotizacion->precio_total); ?>">

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
                <!-- PayPal not configured -->
                <div class="rtt-payment-offline">
                    <h3><?php echo $lang === 'en' ? 'Payment Methods' : 'Formas de Pago'; ?></h3>
                    <p><?php echo $lang === 'en'
                        ? 'Please contact us to complete your payment.'
                        : 'Por favor contáctanos para completar tu pago.'; ?></p>
                    <?php
                    $options = get_option('rtt_reservas_options', []);
                    if (!empty($options['cotizacion_formas_pago'])):
                    ?>
                    <div class="rtt-formas-pago">
                        <?php echo nl2br(esc_html($options['cotizacion_formas_pago'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Footer con contacto -->
                <div class="rtt-payment-footer">
                    <p>
                        <?php echo $lang === 'en' ? 'Questions?' : '¿Preguntas?'; ?>
                        <a href="mailto:reservas@readytotravelperu.com">reservas@readytotravelperu.com</a>
                    </p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderizar mensaje de error
     */
    private function render_error($message) {
        return '<div class="rtt-payment-error">
            <div class="rtt-error-icon">!</div>
            <p>' . esc_html($message) . '</p>
        </div>';
    }

    /**
     * Renderizar mensaje de ya pagado
     */
    private function render_already_paid($cotizacion, $lang) {
        ob_start();
        ?>
        <div class="rtt-payment-success">
            <div class="rtt-success-icon">✓</div>
            <h2><?php echo $lang === 'en' ? 'Payment Completed!' : '¡Pago Completado!'; ?></h2>
            <p>
                <?php echo $lang === 'en'
                    ? 'This quotation has already been paid. Thank you!'
                    : 'Esta cotización ya ha sido pagada. ¡Gracias!'; ?>
            </p>
            <div class="rtt-payment-details">
                <p><strong><?php echo $lang === 'en' ? 'Transaction ID:' : 'ID de Transacción:'; ?></strong> <?php echo esc_html($cotizacion->transaction_id); ?></p>
                <p><strong><?php echo $lang === 'en' ? 'Amount Paid:' : 'Monto Pagado:'; ?></strong> $<?php echo number_format($cotizacion->payment_amount, 2); ?> USD</p>
                <p><strong><?php echo $lang === 'en' ? 'Date:' : 'Fecha:'; ?></strong> <?php echo date_i18n('d/m/Y H:i', strtotime($cotizacion->payment_date)); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
