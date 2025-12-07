<?php
/**
 * Shortcode para botón de reserva con modal
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_Booking_Button {

    /**
     * Inicializar
     */
    public function init() {
        add_shortcode('rtt_booking_button', [$this, 'render_button']);
        add_action('wp_footer', [$this, 'render_modal']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Renderizar botón de reserva
     */
    public function render_button($atts) {
        $atts = shortcode_atts([
            'tour' => '',
            'tour_en' => '',
            'price' => '',
            'price_full' => '',  // Precio del paquete total (segundo precio)
            'price_note' => '',  // Nota del plan accesible (ej: "No incluye entradas")
            'price_note_en' => '',  // Nota en inglés
            'price_from' => '',
            'text' => '',
            'text_en' => '',
            'lang' => '',
            'class' => '',
            'size' => 'normal',  // small, normal, large
            'style' => 'primary', // primary, secondary, accent, outline, outline-green, ghost
            'icon' => 'true',    // true, false
            'pulse' => 'false',  // true, false - efecto de atención
            'full' => 'false',   // true, false - ancho completo
            'display' => 'button', // button, cards - modo de visualización
        ], $atts, 'rtt_booking_button');

        // Si tiene dos precios y display especial, mostrar variante
        if (!empty($atts['price']) && !empty($atts['price_full'])) {
            if ($atts['display'] === 'cards') {
                return $this->render_price_cards($atts);
            } elseif ($atts['display'] === 'pills') {
                return $this->render_price_pills($atts);
            }
        }

        // Detectar idioma
        $lang = $this->get_current_language();
        if (!empty($atts['lang'])) {
            $lang = $atts['lang'];
        }

        // Nombre del tour según idioma
        $tour_name = $lang === 'en' && !empty($atts['tour_en']) ? $atts['tour_en'] : $atts['tour'];

        // Texto del botón
        $button_text = $lang === 'en' ? 'Book Now' : 'Reservar Ahora';
        if (!empty($atts['text'])) {
            $button_text = $lang === 'en' && !empty($atts['text_en']) ? $atts['text_en'] : $atts['text'];
        }

        // Texto de precio
        $price_text = '';
        if (!empty($atts['price']) && !empty($atts['price_full'])) {
            // Dos precios: mostrar rango
            $price_label = $lang === 'en' ? 'From' : 'Desde';
            $price_text = $price_label . ' <strong>$' . esc_html($atts['price']) . ' USD</strong>';
        } elseif (!empty($atts['price'])) {
            $price_label = $lang === 'en' ? 'Price' : 'Precio';
            $price_text = $price_label . ': <strong>$' . esc_html($atts['price']) . ' USD</strong>';
        } elseif (!empty($atts['price_from'])) {
            $price_label = $lang === 'en' ? 'From' : 'Desde';
            $price_text = $price_label . ' <strong>$' . esc_html($atts['price_from']) . ' USD</strong>';
        }

        // Clases del botón
        $btn_classes = ['rtt-booking-btn'];
        $btn_classes[] = 'rtt-booking-btn-' . $atts['size'];
        $btn_classes[] = 'rtt-booking-btn-' . $atts['style'];
        if ($atts['pulse'] === 'true') {
            $btn_classes[] = 'rtt-booking-btn-pulse';
        }
        if ($atts['full'] === 'true') {
            $btn_classes[] = 'rtt-booking-btn-full';
        }
        if (!empty($atts['class'])) {
            $btn_classes[] = $atts['class'];
        }

        // Marcar que se usó el shortcode
        $GLOBALS['rtt_booking_button_used'] = true;
        $GLOBALS['rtt_booking_button_lang'] = $lang;

        // Determinar si mostrar icono
        $show_icon = !isset($atts['icon']) || $atts['icon'] !== 'false';

        ob_start();
        ?>
        <div class="rtt-booking-button-wrapper">
            <?php if ($price_text): ?>
                <div class="rtt-booking-price"><?php echo $price_text; ?></div>
            <?php endif; ?>
            <?php
            $price_note = $lang === 'en' && !empty($atts['price_note_en']) ? $atts['price_note_en'] : $atts['price_note'];
            ?>
            <button type="button"
                    class="<?php echo esc_attr(implode(' ', $btn_classes)); ?>"
                    data-tour="<?php echo esc_attr($tour_name); ?>"
                    data-lang="<?php echo esc_attr($lang); ?>"
                    data-price="<?php echo esc_attr($atts['price']); ?>"
                    data-price-full="<?php echo esc_attr($atts['price_full']); ?>"
                    data-price-note="<?php echo esc_attr($price_note); ?>"
                    data-price-from="<?php echo esc_attr($atts['price_from']); ?>">
                <?php if ($show_icon): ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                    <path d="M8 14h.01"></path>
                    <path d="M12 14h.01"></path>
                    <path d="M16 14h.01"></path>
                    <path d="M8 18h.01"></path>
                    <path d="M12 18h.01"></path>
                </svg>
                <?php endif; ?>
                <span><?php echo esc_html($button_text); ?></span>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderizar modal en el footer
     */
    public function render_modal() {
        // Solo si se usó el shortcode
        if (empty($GLOBALS['rtt_booking_button_used'])) {
            return;
        }

        $lang = $GLOBALS['rtt_booking_button_lang'] ?? 'es';

        $shortcode = new RTT_Shortcode();
        ?>
        <div id="rtt-booking-modal" class="rtt-modal-overlay" style="display: none;">
            <div class="rtt-modal-container">
                <button type="button" class="rtt-modal-close-btn" aria-label="<?php echo $lang === 'en' ? 'Close' : 'Cerrar'; ?>">
                    &times;
                </button>

                <!-- Header con información del tour -->
                <div class="rtt-modal-header" id="rtt-modal-header" style="display: none;">
                    <div class="rtt-modal-header-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8 3l4 8 5-5 5 15H2L8 3z"></path>
                            <circle cx="6" cy="6" r="2"></circle>
                        </svg>
                    </div>
                    <div class="rtt-modal-header-info">
                        <h3 class="rtt-modal-tour-name" id="rtt-modal-tour-name"></h3>
                        <div class="rtt-modal-tour-price" id="rtt-modal-tour-price"></div>
                    </div>
                </div>

                <div class="rtt-modal-body">
                    <?php echo $shortcode->render(['lang' => $lang]); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Cargar assets - siempre los carga para evitar problemas con page builders
     */
    public function enqueue_assets() {
        // Siempre cargar los assets en páginas singulares
        // Esto es necesario porque Elementor y otros page builders
        // pueden no tener el shortcode visible en post_content
        if (!is_singular()) {
            return;
        }

        $lang = $this->get_current_language();

        // CSS del botón y modal
        wp_enqueue_style(
            'rtt-booking-button',
            RTT_RESERVAS_PLUGIN_URL . 'assets/css/booking-button.css',
            [],
            RTT_RESERVAS_VERSION
        );

        // MC Calendar CSS
        wp_enqueue_style(
            'mc-calendar-css',
            RTT_RESERVAS_PLUGIN_URL . 'assets/vendor/mc-calendar.min.css',
            [],
            RTT_RESERVAS_VERSION
        );

        // CSS principal del formulario
        wp_enqueue_style(
            'rtt-reservas-css',
            RTT_RESERVAS_PLUGIN_URL . 'assets/css/rtt-reservas.css',
            ['mc-calendar-css'],
            RTT_RESERVAS_VERSION
        );

        // MC Calendar JS (según idioma)
        $calendar_file = $lang === 'en' ? 'mc-calendar_e.min.js' : 'mc-calendar.min.js';
        wp_enqueue_script(
            'mc-calendar-js',
            RTT_RESERVAS_PLUGIN_URL . 'assets/vendor/' . $calendar_file,
            [],
            RTT_RESERVAS_VERSION,
            true
        );

        // JavaScript principal del formulario
        wp_enqueue_script(
            'rtt-reservas-js',
            RTT_RESERVAS_PLUGIN_URL . 'assets/js/rtt-reservas.js',
            ['jquery', 'mc-calendar-js'],
            RTT_RESERVAS_VERSION,
            true
        );

        // JS del botón y modal (depende de rtt-reservas-js)
        wp_enqueue_script(
            'rtt-booking-button',
            RTT_RESERVAS_PLUGIN_URL . 'assets/js/booking-button.js',
            ['jquery', 'rtt-reservas-js'],
            RTT_RESERVAS_VERSION,
            true
        );

        // Pasar variables a JavaScript
        wp_localize_script('rtt-reservas-js', 'rttReservas', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rtt_reserva_nonce'),
            'lang' => $lang,
            'i18n' => $this->get_js_translations()
        ]);

        wp_localize_script('rtt-booking-button', 'rttBookingButton', [
            'i18n' => [
                'close' => __('Cerrar', 'rtt-reservas'),
            ]
        ]);
    }

    /**
     * Traducciones para JavaScript
     */
    private function get_js_translations() {
        return [
            'step1Title' => __('Tour y Fecha', 'rtt-reservas'),
            'step2Title' => __('Pasajeros', 'rtt-reservas'),
            'step3Title' => __('Representante', 'rtt-reservas'),
            'next' => __('Siguiente', 'rtt-reservas'),
            'previous' => __('Anterior', 'rtt-reservas'),
            'submit' => __('Enviar Reserva', 'rtt-reservas'),
            'processing' => __('Procesando...', 'rtt-reservas'),
            'success' => __('Reserva enviada correctamente. Revisa tu correo.', 'rtt-reservas'),
            'error' => __('Error al enviar la reserva. Intenta nuevamente.', 'rtt-reservas'),
            'requiredField' => __('Este campo es requerido', 'rtt-reservas'),
            'invalidEmail' => __('Email inválido', 'rtt-reservas'),
            'selectTour' => __('Selecciona un tour', 'rtt-reservas'),
            'selectDate' => __('Selecciona una fecha', 'rtt-reservas'),
            'addPassenger' => __('Agregar pasajero', 'rtt-reservas'),
            'removePassenger' => __('Eliminar', 'rtt-reservas'),
            'passenger' => __('Pasajero', 'rtt-reservas'),
            'docType' => __('Tipo de documento', 'rtt-reservas'),
            'docNumber' => __('Número de documento', 'rtt-reservas'),
            'fullName' => __('Nombres y apellidos', 'rtt-reservas'),
            'gender' => __('Género', 'rtt-reservas'),
            'male' => __('Masculino', 'rtt-reservas'),
            'female' => __('Femenino', 'rtt-reservas'),
            'birthDate' => __('Fecha de nacimiento', 'rtt-reservas'),
            'nationality' => __('Nacionalidad', 'rtt-reservas'),
            'allergies' => __('Alergias u observaciones', 'rtt-reservas'),
            'representativeName' => __('Nombre del representante', 'rtt-reservas'),
            'email' => __('Correo electrónico', 'rtt-reservas'),
            'phone' => __('Teléfono', 'rtt-reservas'),
            'country' => __('País', 'rtt-reservas'),
            'dni' => __('DNI', 'rtt-reservas'),
            'passport' => __('Pasaporte', 'rtt-reservas'),
            'minPassengers' => __('Debe agregar al menos un pasajero', 'rtt-reservas'),
        ];
    }

    /**
     * Renderizar cards de precios (dos opciones con botones)
     */
    private function render_price_cards($atts) {
        $lang = $this->get_current_language();
        if (!empty($atts['lang'])) {
            $lang = $atts['lang'];
        }

        $tour_name = $lang === 'en' && !empty($atts['tour_en']) ? $atts['tour_en'] : $atts['tour'];
        $price_note = $lang === 'en' && !empty($atts['price_note_en']) ? $atts['price_note_en'] : $atts['price_note'];

        // Textos según idioma
        $texts = [
            'plan_basic' => $lang === 'en' ? 'Accessible Plan' : 'Plan Accesible',
            'plan_full' => $lang === 'en' ? 'Full Package' : 'Paquete Total',
            'per_person' => $lang === 'en' ? 'per person' : 'por persona',
            'book_now' => $lang === 'en' ? 'Book Now' : 'Reservar Ahora',
            'includes_all' => $lang === 'en' ? 'Includes everything' : 'Incluye todo',
            'recommended' => $lang === 'en' ? 'Recommended' : 'Recomendado',
        ];

        // Marcar que se usó el shortcode
        $GLOBALS['rtt_booking_button_used'] = true;
        $GLOBALS['rtt_booking_button_lang'] = $lang;

        ob_start();
        ?>
        <div class="rtt-price-cards-wrapper">
            <?php if (!empty($tour_name)): ?>
            <h3 class="rtt-price-cards-title"><?php echo esc_html($tour_name); ?></h3>
            <?php endif; ?>

            <div class="rtt-price-cards-container">
                <!-- Card Plan Accesible -->
                <div class="rtt-price-card rtt-price-card-basic">
                    <div class="rtt-price-card-header">
                        <span class="rtt-price-card-label"><?php echo esc_html($texts['plan_basic']); ?></span>
                    </div>
                    <div class="rtt-price-card-body">
                        <div class="rtt-price-card-amount">
                            <span class="rtt-price-card-currency">$</span>
                            <span class="rtt-price-card-value"><?php echo esc_html($atts['price']); ?></span>
                            <span class="rtt-price-card-unit">USD</span>
                        </div>
                        <div class="rtt-price-card-per"><?php echo esc_html($texts['per_person']); ?></div>
                        <?php if (!empty($price_note)): ?>
                        <div class="rtt-price-card-note"><?php echo esc_html($price_note); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="rtt-price-card-footer">
                        <button type="button"
                                class="rtt-booking-btn rtt-booking-btn-outline rtt-price-card-btn"
                                data-tour="<?php echo esc_attr($tour_name); ?>"
                                data-lang="<?php echo esc_attr($lang); ?>"
                                data-price="<?php echo esc_attr($atts['price']); ?>"
                                data-price-full="<?php echo esc_attr($atts['price_full']); ?>"
                                data-price-note="<?php echo esc_attr($price_note); ?>"
                                data-plan="accesible">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <span><?php echo esc_html($texts['book_now']); ?></span>
                        </button>
                    </div>
                </div>

                <!-- Card Paquete Total -->
                <div class="rtt-price-card rtt-price-card-full">
                    <div class="rtt-price-card-badge"><?php echo esc_html($texts['recommended']); ?></div>
                    <div class="rtt-price-card-header">
                        <span class="rtt-price-card-label"><?php echo esc_html($texts['plan_full']); ?></span>
                    </div>
                    <div class="rtt-price-card-body">
                        <div class="rtt-price-card-amount">
                            <span class="rtt-price-card-currency">$</span>
                            <span class="rtt-price-card-value"><?php echo esc_html($atts['price_full']); ?></span>
                            <span class="rtt-price-card-unit">USD</span>
                        </div>
                        <div class="rtt-price-card-per"><?php echo esc_html($texts['per_person']); ?></div>
                        <div class="rtt-price-card-includes"><?php echo esc_html($texts['includes_all']); ?></div>
                    </div>
                    <div class="rtt-price-card-footer">
                        <button type="button"
                                class="rtt-booking-btn rtt-booking-btn-primary rtt-price-card-btn"
                                data-tour="<?php echo esc_attr($tour_name); ?>"
                                data-lang="<?php echo esc_attr($lang); ?>"
                                data-price="<?php echo esc_attr($atts['price']); ?>"
                                data-price-full="<?php echo esc_attr($atts['price_full']); ?>"
                                data-price-note="<?php echo esc_attr($price_note); ?>"
                                data-plan="total">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <span><?php echo esc_html($texts['book_now']); ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderizar pills de precios (versión compacta)
     */
    private function render_price_pills($atts) {
        $lang = $this->get_current_language();
        if (!empty($atts['lang'])) {
            $lang = $atts['lang'];
        }

        $tour_name = $lang === 'en' && !empty($atts['tour_en']) ? $atts['tour_en'] : $atts['tour'];
        $price_note = $lang === 'en' && !empty($atts['price_note_en']) ? $atts['price_note_en'] : $atts['price_note'];

        // Textos según idioma
        $texts = [
            'plan_basic' => $lang === 'en' ? 'Accessible Plan' : 'Plan Accesible',
            'plan_full' => $lang === 'en' ? 'Full Package' : 'Paquete Total',
            'book_now' => $lang === 'en' ? 'Book Now' : 'Reservar Ahora',
            'select_plan' => $lang === 'en' ? 'Select your plan' : 'Selecciona tu plan',
        ];

        // Clases del botón
        $btn_classes = ['rtt-booking-btn', 'rtt-pills-booking-btn'];
        $btn_classes[] = 'rtt-booking-btn-' . $atts['size'];
        $btn_classes[] = 'rtt-booking-btn-' . $atts['style'];
        if ($atts['full'] === 'true') {
            $btn_classes[] = 'rtt-booking-btn-full';
        }

        // Marcar que se usó el shortcode
        $GLOBALS['rtt_booking_button_used'] = true;
        $GLOBALS['rtt_booking_button_lang'] = $lang;

        ob_start();
        ?>
        <div class="rtt-price-pills-wrapper" data-tour="<?php echo esc_attr($tour_name); ?>" data-lang="<?php echo esc_attr($lang); ?>">
            <?php if (!empty($tour_name)): ?>
            <div class="rtt-pills-tour-name"><?php echo esc_html($tour_name); ?></div>
            <?php endif; ?>

            <div class="rtt-pills-label"><?php echo esc_html($texts['select_plan']); ?>:</div>

            <div class="rtt-price-pills-container">
                <!-- Pill Plan Accesible -->
                <button type="button" class="rtt-price-pill rtt-price-pill-basic"
                        data-plan="accesible"
                        data-price="<?php echo esc_attr($atts['price']); ?>"
                        data-price-full="<?php echo esc_attr($atts['price_full']); ?>"
                        data-price-note="<?php echo esc_attr($price_note); ?>">
                    <span class="rtt-pill-label"><?php echo esc_html($texts['plan_basic']); ?></span>
                    <span class="rtt-pill-price">$<?php echo esc_html($atts['price']); ?> USD</span>
                    <?php if (!empty($price_note)): ?>
                    <span class="rtt-pill-note"><?php echo esc_html($price_note); ?></span>
                    <?php endif; ?>
                </button>

                <!-- Pill Paquete Total -->
                <button type="button" class="rtt-price-pill rtt-price-pill-full"
                        data-plan="total"
                        data-price="<?php echo esc_attr($atts['price']); ?>"
                        data-price-full="<?php echo esc_attr($atts['price_full']); ?>"
                        data-price-note="<?php echo esc_attr($price_note); ?>">
                    <span class="rtt-pill-label"><?php echo esc_html($texts['plan_full']); ?></span>
                    <span class="rtt-pill-price">$<?php echo esc_html($atts['price_full']); ?> USD</span>
                    <span class="rtt-pill-badge"><?php echo $lang === 'en' ? 'All included' : 'Todo incluido'; ?></span>
                </button>
            </div>

            <!-- Botón de reserva -->
            <button type="button"
                    class="<?php echo esc_attr(implode(' ', $btn_classes)); ?>"
                    data-tour="<?php echo esc_attr($tour_name); ?>"
                    data-lang="<?php echo esc_attr($lang); ?>"
                    data-price="<?php echo esc_attr($atts['price']); ?>"
                    data-price-full="<?php echo esc_attr($atts['price_full']); ?>"
                    data-price-note="<?php echo esc_attr($price_note); ?>"
                    disabled>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span><?php echo esc_html($texts['book_now']); ?></span>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Obtener idioma actual
     */
    private function get_current_language() {
        // Compatibilidad con WPML
        if (defined('ICL_LANGUAGE_CODE')) {
            return ICL_LANGUAGE_CODE;
        }
        // Compatibilidad con Polylang
        if (function_exists('pll_current_language')) {
            return pll_current_language();
        }
        // Detectar por URL
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/en/') !== false || strpos($uri, 'lang=en') !== false) {
            return 'en';
        }
        return 'es';
    }
}
