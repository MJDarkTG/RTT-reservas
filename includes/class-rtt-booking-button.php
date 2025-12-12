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
            'tour_id' => '',     // ID del tour en CPT (para obtener datos automáticamente)
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
            'display' => 'auto', // auto, button, card, cards, pills - modo de visualización
        ], $atts, 'rtt_booking_button');

        // Si se proporciona tour_id, obtener datos del CPT
        if (!empty($atts['tour_id'])) {
            $atts = $this->populate_from_tour_id($atts);
        }

        // Determinar display automático si es 'auto'
        if ($atts['display'] === 'auto') {
            if (!empty($atts['price']) && !empty($atts['price_full'])) {
                $atts['display'] = 'cards'; // Dos precios = dos cards
            } elseif (!empty($atts['price'])) {
                $atts['display'] = 'card';  // Un precio = un card
            } else {
                $atts['display'] = 'button'; // Sin precio = botón simple
            }
        }

        // Renderizar según el tipo de display
        if ($atts['display'] === 'cards' && !empty($atts['price']) && !empty($atts['price_full'])) {
            return $this->render_price_cards($atts);
        } elseif ($atts['display'] === 'pills' && !empty($atts['price']) && !empty($atts['price_full'])) {
            return $this->render_price_pills($atts);
        } elseif ($atts['display'] === 'card' && !empty($atts['price'])) {
            return $this->render_single_card($atts);
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
     * Traducciones para JavaScript (usa método compartido de clase principal)
     */
    private function get_js_translations() {
        return RTT_Reservas::get_shared_js_translations();
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
     * Obtener datos del tour desde el CPT
     */
    private function populate_from_tour_id($atts) {
        $tour_id = intval($atts['tour_id']);

        if (!$tour_id) {
            return $atts;
        }

        $post = get_post($tour_id);
        if (!$post || $post->post_type !== 'rtt_tour') {
            return $atts;
        }

        // Obtener idioma actual
        $lang = $this->get_current_language();
        if (!empty($atts['lang'])) {
            $lang = $atts['lang'];
        }

        // Solo sobreescribir si no se proporcionó en el shortcode
        if (empty($atts['tour'])) {
            $atts['tour'] = $post->post_title;
        }
        if (empty($atts['tour_en'])) {
            $atts['tour_en'] = get_post_meta($tour_id, '_rtt_tour_name_en', true) ?: $post->post_title;
        }
        if (empty($atts['price'])) {
            $atts['price'] = get_post_meta($tour_id, '_rtt_tour_price', true);
        }
        if (empty($atts['price_full'])) {
            $atts['price_full'] = get_post_meta($tour_id, '_rtt_tour_price_full', true);
        }
        if (empty($atts['price_note'])) {
            $atts['price_note'] = get_post_meta($tour_id, '_rtt_tour_price_note', true);
        }
        if (empty($atts['price_note_en'])) {
            $atts['price_note_en'] = get_post_meta($tour_id, '_rtt_tour_price_note_en', true);
        }

        return $atts;
    }

    /**
     * Renderizar card único (un solo precio)
     */
    private function render_single_card($atts) {
        $lang = $this->get_current_language();
        if (!empty($atts['lang'])) {
            $lang = $atts['lang'];
        }

        $tour_name = $lang === 'en' && !empty($atts['tour_en']) ? $atts['tour_en'] : $atts['tour'];
        $price_note = $lang === 'en' && !empty($atts['price_note_en']) ? $atts['price_note_en'] : $atts['price_note'];

        // Textos según idioma
        $texts = [
            'price' => $lang === 'en' ? 'Price' : 'Precio',
            'per_person' => $lang === 'en' ? 'per person' : 'por persona',
            'book_now' => $lang === 'en' ? 'Book Now' : 'Reservar Ahora',
        ];

        // Marcar que se usó el shortcode
        $GLOBALS['rtt_booking_button_used'] = true;
        $GLOBALS['rtt_booking_button_lang'] = $lang;

        ob_start();
        ?>
        <div class="rtt-price-cards-wrapper rtt-single-card-wrapper">
            <?php if (!empty($tour_name)): ?>
            <h3 class="rtt-price-cards-title"><?php echo esc_html($tour_name); ?></h3>
            <?php endif; ?>

            <div class="rtt-price-cards-container rtt-single-card-container">
                <!-- Card único -->
                <div class="rtt-price-card rtt-price-card-single">
                    <div class="rtt-price-card-header">
                        <span class="rtt-price-card-label"><?php echo esc_html($texts['price']); ?></span>
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
                                class="rtt-booking-btn rtt-booking-btn-primary rtt-price-card-btn"
                                data-tour="<?php echo esc_attr($tour_name); ?>"
                                data-lang="<?php echo esc_attr($lang); ?>"
                                data-price="<?php echo esc_attr($atts['price']); ?>">
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
