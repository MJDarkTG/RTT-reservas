<?php
/**
 * Clase para el shortcode del formulario
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_Shortcode {

    /**
     * Renderizar el shortcode
     */
    public function render($atts) {
        $atts = shortcode_atts([
            'lang' => 'es'
        ], $atts, 'rtt_reserva');

        $lang = sanitize_text_field($atts['lang']);

        // Obtener datos
        $tours = RTT_Tours::get_tours_grouped($lang);
        $countries = RTT_Tours::get_countries_with_flags();

        ob_start();
        ?>
        <div class="rtt-reservas-container" data-lang="<?php echo esc_attr($lang); ?>">
            <!-- Progress Bar -->
            <div class="rtt-progress-bar">
                <div class="rtt-progress-step active" data-step="1">
                    <div class="rtt-step-number">1</div>
                    <div class="rtt-step-label"><?php echo $lang === 'en' ? 'Tour & Date' : 'Tour y Fecha'; ?></div>
                </div>
                <div class="rtt-progress-line"></div>
                <div class="rtt-progress-step" data-step="2">
                    <div class="rtt-step-number">2</div>
                    <div class="rtt-step-label"><?php echo $lang === 'en' ? 'Passengers' : 'Pasajeros'; ?></div>
                </div>
                <div class="rtt-progress-line"></div>
                <div class="rtt-progress-step" data-step="3">
                    <div class="rtt-step-number">3</div>
                    <div class="rtt-step-label"><?php echo $lang === 'en' ? 'Representative' : 'Representante'; ?></div>
                </div>
            </div>

            <form id="rtt-reserva-form" class="rtt-form" novalidate>
                <?php wp_nonce_field('rtt_reserva_nonce', 'rtt_nonce'); ?>
                <input type="hidden" name="action" value="rtt_submit_reserva">
                <input type="hidden" name="lang" value="<?php echo esc_attr($lang); ?>">
                <input type="hidden" name="precio_tour" id="rtt-precio-tour" value="">

                <!-- Honeypot anti-spam (campo oculto que los bots llenan) -->
                <div class="rtt-hp-field" aria-hidden="true">
                    <label for="rtt_website_url">Website</label>
                    <input type="text" name="rtt_website_url" id="rtt_website_url" value="" tabindex="-1" autocomplete="off">
                </div>

                <!-- Step 1: Tour y Fecha -->
                <div class="rtt-step rtt-step-1 active" data-step="1">
                    <div class="rtt-step-header">
                        <div class="rtt-step-icon">1</div>
                        <div class="rtt-step-info">
                            <h3><?php echo $lang === 'en' ? 'Tour and Date' : 'Tour y Fecha'; ?></h3>
                            <p><?php echo $lang === 'en' ? 'Select the tour and reservation date' : 'Elija el tour y fecha a reservar'; ?></p>
                        </div>
                    </div>

                    <div class="rtt-form-row">
                        <div class="rtt-form-group rtt-col-8">
                            <label for="rtt-tour">
                                <?php echo $lang === 'en' ? 'Which package or tour are you going to book?' : '¿Qué paquete o excursión va a reservar?'; ?>
                                <span class="rtt-required">*</span>
                            </label>
                            <select id="rtt-tour" name="tour" required class="rtt-select">
                                <option value=""><?php echo $lang === 'en' ? 'Select a tour...' : 'Seleccione un tour...'; ?></option>
                                <?php foreach ($tours as $category => $tour_list): ?>
                                    <optgroup label="<?php echo esc_attr($category); ?>">
                                        <?php foreach ($tour_list as $tour): ?>
                                            <?php
                                            $tour_name = is_array($tour) ? $tour['name'] : $tour;
                                            $tour_price = is_array($tour) && isset($tour['price']) ? $tour['price'] : 0;
                                            $tour_price_full = is_array($tour) && isset($tour['price_full']) ? $tour['price_full'] : 0;
                                            $tour_price_note = is_array($tour) && isset($tour['price_note']) ? $tour['price_note'] : '';
                                            $tour_price_note_en = is_array($tour) && isset($tour['price_note_en']) ? $tour['price_note_en'] : '';
                                            ?>
                                            <option value="<?php echo esc_attr($tour_name); ?>"
                                                    data-price="<?php echo esc_attr($tour_price); ?>"
                                                    data-price-full="<?php echo esc_attr($tour_price_full); ?>"
                                                    data-price-note="<?php echo esc_attr($tour_price_note); ?>"
                                                    data-price-note-en="<?php echo esc_attr($tour_price_note_en); ?>">
                                                <?php echo esc_html($tour_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="rtt-form-group rtt-col-4">
                            <label for="rtt-fecha">
                                <?php echo $lang === 'en' ? 'Reservation Date' : 'Fecha de Reserva'; ?>
                                <span class="rtt-required">*</span>
                            </label>
                            <input type="text" id="rtt-fecha" name="fecha" required class="rtt-input rtt-datepicker"
                                   placeholder="<?php echo $lang === 'en' ? 'Select date' : 'Seleccionar fecha'; ?>" readonly>
                        </div>
                    </div>

                    <!-- Cards de selección de plan (solo visible cuando el tour tiene 2 precios) -->
                    <div id="rtt-plan-selector" class="rtt-plan-selector" style="display: none;">
                        <label><?php echo $lang === 'en' ? 'Select your plan' : 'Selecciona tu plan'; ?> <span class="rtt-required">*</span></label>
                        <div class="rtt-plan-cards">
                            <div class="rtt-plan-card" data-plan="accesible">
                                <div class="rtt-plan-card-header">
                                    <h5><?php echo $lang === 'en' ? 'Basic Plan' : 'Plan Accesible'; ?></h5>
                                </div>
                                <div class="rtt-plan-card-price">
                                    <span class="rtt-plan-price" id="rtt-price-basic">$0</span>
                                    <span class="rtt-plan-currency">USD</span>
                                </div>
                                <div class="rtt-plan-card-footer">
                                    <?php echo $lang === 'en' ? 'Per person' : 'Por persona'; ?>
                                </div>
                                <div class="rtt-plan-card-note" id="rtt-price-note"></div>
                            </div>
                            <div class="rtt-plan-card" data-plan="total">
                                <div class="rtt-plan-card-header">
                                    <h5><?php echo $lang === 'en' ? 'Full Package' : 'Paquete Total'; ?></h5>
                                </div>
                                <div class="rtt-plan-card-price">
                                    <span class="rtt-plan-price" id="rtt-price-full">$0</span>
                                    <span class="rtt-plan-currency">USD</span>
                                </div>
                                <div class="rtt-plan-card-footer">
                                    <?php echo $lang === 'en' ? 'Per person' : 'Por persona'; ?>
                                </div>
                                <div class="rtt-plan-card-includes">
                                    <?php echo $lang === 'en' ? 'All included' : 'Incluye todo'; ?>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="rtt-plan-selected" name="plan_selected" value="">
                    </div>

                    <div class="rtt-step-actions">
                        <button type="button" class="rtt-btn rtt-btn-next" data-next="2">
                            <?php echo $lang === 'en' ? 'Next' : 'Siguiente'; ?>
                            <span class="rtt-btn-icon">→</span>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Pasajeros -->
                <div class="rtt-step rtt-step-2" data-step="2">
                    <div class="rtt-step-header">
                        <div class="rtt-step-icon">2</div>
                        <div class="rtt-step-info">
                            <h3><?php echo $lang === 'en' ? 'Passenger Data' : 'Datos del Cliente'; ?></h3>
                            <p><?php echo $lang === 'en' ? 'Enter passenger details as shown in passport' : 'Ingrese los datos de los pasajeros, nombres completos tal como dice en el pasaporte'; ?></p>
                        </div>
                    </div>

                    <div class="rtt-passengers-controls">
                        <label><?php echo $lang === 'en' ? 'Number of passengers' : 'Número de pasajeros'; ?></label>
                        <div class="rtt-counter">
                            <button type="button" class="rtt-btn-counter rtt-btn-minus" disabled>−</button>
                            <input type="number" id="rtt-cantidad" name="cantidad" value="0" min="0" max="20" readonly class="rtt-counter-input">
                            <button type="button" class="rtt-btn-counter rtt-btn-plus">+</button>
                        </div>
                    </div>

                    <div id="rtt-passengers-container" class="rtt-passengers-container">
                        <!-- Los pasajeros se agregan dinámicamente con JS -->
                    </div>

                    <div class="rtt-step-actions">
                        <button type="button" class="rtt-btn rtt-btn-prev" data-prev="1">
                            <span class="rtt-btn-icon">←</span>
                            <?php echo $lang === 'en' ? 'Previous' : 'Anterior'; ?>
                        </button>
                        <button type="button" class="rtt-btn rtt-btn-next" data-next="3">
                            <?php echo $lang === 'en' ? 'Next' : 'Siguiente'; ?>
                            <span class="rtt-btn-icon">→</span>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Representante -->
                <div class="rtt-step rtt-step-3" data-step="3">
                    <div class="rtt-step-header">
                        <div class="rtt-step-icon">3</div>
                        <div class="rtt-step-info">
                            <h3><?php echo $lang === 'en' ? 'Representative Data' : 'Datos del Representante'; ?></h3>
                            <p><?php echo $lang === 'en' ? 'Enter contact information to send the booking confirmation' : 'Ingrese los datos del representante para enviar la ficha de reserva'; ?></p>
                        </div>
                    </div>

                    <div class="rtt-form-row">
                        <div class="rtt-form-group rtt-col-6">
                            <label for="rtt-nombre-rep">
                                <?php echo $lang === 'en' ? 'Full name' : 'Apellidos y nombres'; ?>
                                <span class="rtt-required">*</span>
                            </label>
                            <input type="text" id="rtt-nombre-rep" name="nombre_representante" required class="rtt-input"
                                   placeholder="<?php echo $lang === 'en' ? 'Full name' : 'Nombres y apellidos'; ?>">
                        </div>
                        <div class="rtt-form-group rtt-col-6">
                            <label for="rtt-email">
                                <?php echo $lang === 'en' ? 'Email' : 'Correo electrónico'; ?>
                                <span class="rtt-required">*</span>
                            </label>
                            <input type="email" id="rtt-email" name="email" required class="rtt-input"
                                   placeholder="<?php echo $lang === 'en' ? 'Email address' : 'Correo electrónico'; ?>">
                        </div>
                    </div>

                    <div class="rtt-form-row">
                        <div class="rtt-form-group rtt-col-6">
                            <label for="rtt-telefono">
                                <?php echo $lang === 'en' ? 'Phone / WhatsApp' : 'Teléfono / WhatsApp'; ?>
                                <span class="rtt-required">*</span>
                            </label>
                            <input type="tel" id="rtt-telefono" name="telefono" required class="rtt-input"
                                   placeholder="<?php echo $lang === 'en' ? 'Phone number with country code' : 'Número con código de país'; ?>">
                        </div>
                        <div class="rtt-form-group rtt-col-6">
                            <label for="rtt-pais">
                                <?php echo $lang === 'en' ? 'Country' : 'País'; ?>
                                <span class="rtt-required">*</span>
                            </label>
                            <div class="rtt-flag-select" data-name="pais" data-required="true">
                                <input type="hidden" id="rtt-pais" name="pais" required>
                                <div class="rtt-flag-select-trigger">
                                    <span class="rtt-flag-select-value placeholder" data-placeholder="<?php echo $lang === 'en' ? 'Select country...' : 'Seleccione país...'; ?>">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity:0.4"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                        <?php echo $lang === 'en' ? 'Select country...' : 'Seleccione país...'; ?>
                                    </span>
                                    <span class="rtt-flag-select-arrow">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                                    </span>
                                </div>
                                <div class="rtt-flag-select-dropdown">
                                    <div class="rtt-flag-search-wrapper">
                                        <svg class="rtt-flag-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                        <input type="text" class="rtt-flag-search" placeholder="<?php echo $lang === 'en' ? 'Search country...' : 'Buscar país...'; ?>">
                                    </div>
                                    <div class="rtt-flag-options">
                                        <?php foreach ($countries as $code => $country): ?>
                                            <div class="rtt-flag-option" data-value="<?php echo esc_attr($country['name']); ?>" data-code="<?php echo esc_attr($country['code']); ?>">
                                                <img src="https://flagcdn.com/32x24/<?php echo esc_attr($country['code']); ?>.png"
                                                     srcset="https://flagcdn.com/64x48/<?php echo esc_attr($country['code']); ?>.png 2x"
                                                     alt="<?php echo esc_attr($country['name']); ?>" class="rtt-flag-img" loading="lazy">
                                                <span><?php echo esc_html($country['name']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rtt-step-actions">
                        <button type="button" class="rtt-btn rtt-btn-prev" data-prev="2">
                            <span class="rtt-btn-icon">←</span>
                            <?php echo $lang === 'en' ? 'Previous' : 'Anterior'; ?>
                        </button>
                        <button type="submit" class="rtt-btn rtt-btn-submit">
                            <?php echo $lang === 'en' ? 'Send Booking' : 'Enviar Reserva'; ?>
                            <span class="rtt-btn-icon">✓</span>
                        </button>
                    </div>
                </div>

                <!-- Mensaje de éxito/error -->
                <div id="rtt-message" class="rtt-message" style="display: none;"></div>

                <?php if (class_exists('RTT_PayPal') && RTT_PayPal::is_enabled()): ?>
                <!-- PayPal Payment Section (Optional) -->
                <div id="rtt-payment-section" class="rtt-payment-section" style="display: none;">
                    <div class="rtt-payment-header">
                        <h3><?php echo $lang === 'en' ? 'Complete Your Payment (Optional)' : 'Completar Pago (Opcional)'; ?></h3>
                        <p><?php echo $lang === 'en'
                            ? 'You can pay now to confirm your reservation immediately, or pay later.'
                            : 'Puedes pagar ahora para confirmar tu reserva inmediatamente, o pagar después.'; ?></p>
                    </div>
                    <div class="rtt-payment-breakdown">
                        <div class="rtt-payment-line">
                            <span class="rtt-payment-label"><?php echo $lang === 'en' ? 'Tour Price:' : 'Precio del Tour:'; ?></span>
                            <span id="rtt-payment-base" class="rtt-payment-value">$0.00 USD</span>
                        </div>
                        <div class="rtt-payment-line rtt-payment-fee">
                            <span class="rtt-payment-label"><?php echo $lang === 'en' ? 'PayPal Fee (4.4% + $0.30):' : 'Comisión PayPal (4.4% + $0.30):'; ?></span>
                            <span id="rtt-payment-fee" class="rtt-payment-value">$0.00 USD</span>
                        </div>
                        <div class="rtt-payment-line rtt-payment-total-line">
                            <span class="rtt-payment-label"><?php echo $lang === 'en' ? 'Total to Pay:' : 'Total a Pagar:'; ?></span>
                            <span id="rtt-payment-total" class="rtt-payment-value">$0.00 USD</span>
                        </div>
                    </div>
                    <div id="paypal-button-container"></div>
                    <p class="rtt-payment-skip">
                        <a href="#" id="rtt-skip-payment">
                            <?php echo $lang === 'en' ? 'Skip payment, I will pay later' : 'Omitir pago, pagaré después'; ?>
                        </a>
                    </p>
                </div>
                <?php endif; ?>
            </form>

            <!-- Template para pasajero (usado por JS) -->
            <template id="rtt-passenger-template">
                <div class="rtt-passenger-card" data-passenger-index="{index}">
                    <div class="rtt-passenger-header">
                        <h4><?php echo $lang === 'en' ? 'Passenger' : 'Pasajero'; ?> #{number}</h4>
                        <button type="button" class="rtt-btn-remove-passenger" title="<?php echo $lang === 'en' ? 'Remove' : 'Eliminar'; ?>">×</button>
                    </div>
                    <div class="rtt-form-row">
                        <div class="rtt-form-group rtt-col-3">
                            <label><?php echo $lang === 'en' ? 'Document type' : 'Tipo de documento'; ?></label>
                            <select name="pasajeros[{index}][tipo_doc]" required class="rtt-select">
                                <option value="DNI">DNI</option>
                                <option value="PASAPORTE"><?php echo $lang === 'en' ? 'Passport' : 'Pasaporte'; ?></option>
                            </select>
                        </div>
                        <div class="rtt-form-group rtt-col-3">
                            <label><?php echo $lang === 'en' ? 'Document number' : 'Nro de documento'; ?></label>
                            <input type="text" name="pasajeros[{index}][nro_doc]" required class="rtt-input">
                        </div>
                        <div class="rtt-form-group rtt-col-6">
                            <label><?php echo $lang === 'en' ? 'Full name' : 'Apellidos y nombres'; ?></label>
                            <input type="text" name="pasajeros[{index}][nombre]" required class="rtt-input">
                        </div>
                    </div>
                    <div class="rtt-form-row">
                        <div class="rtt-form-group rtt-col-3">
                            <label><?php echo $lang === 'en' ? 'Gender' : 'Género'; ?></label>
                            <select name="pasajeros[{index}][genero]" required class="rtt-select">
                                <option value="M"><?php echo $lang === 'en' ? 'Male' : 'Masculino'; ?></option>
                                <option value="F"><?php echo $lang === 'en' ? 'Female' : 'Femenino'; ?></option>
                            </select>
                        </div>
                        <div class="rtt-form-group rtt-col-3">
                            <label><?php echo $lang === 'en' ? 'Birth date' : 'Fecha de nacimiento'; ?></label>
                            <input type="date" name="pasajeros[{index}][fecha_nacimiento]" required class="rtt-input rtt-date-native" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="rtt-form-group rtt-col-6">
                            <label><?php echo $lang === 'en' ? 'Nationality' : 'Nacionalidad'; ?></label>
                            <div class="rtt-flag-select" data-name="pasajeros[{index}][nacionalidad]" data-required="true">
                                <input type="hidden" name="pasajeros[{index}][nacionalidad]" required>
                                <div class="rtt-flag-select-trigger">
                                    <span class="rtt-flag-select-value placeholder" data-placeholder="<?php echo $lang === 'en' ? 'Select...' : 'Seleccione...'; ?>">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity:0.4"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                        <?php echo $lang === 'en' ? 'Select...' : 'Seleccione...'; ?>
                                    </span>
                                    <span class="rtt-flag-select-arrow">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                                    </span>
                                </div>
                                <div class="rtt-flag-select-dropdown">
                                    <div class="rtt-flag-search-wrapper">
                                        <svg class="rtt-flag-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                        <input type="text" class="rtt-flag-search" placeholder="<?php echo $lang === 'en' ? 'Search...' : 'Buscar...'; ?>">
                                    </div>
                                    <div class="rtt-flag-options">
                                        <?php foreach ($countries as $code => $country): ?>
                                            <div class="rtt-flag-option" data-value="<?php echo esc_attr($country['name']); ?>" data-code="<?php echo esc_attr($country['code']); ?>">
                                                <img src="https://flagcdn.com/32x24/<?php echo esc_attr($country['code']); ?>.png"
                                                     srcset="https://flagcdn.com/64x48/<?php echo esc_attr($country['code']); ?>.png 2x"
                                                     alt="<?php echo esc_attr($country['name']); ?>" class="rtt-flag-img" loading="lazy">
                                                <span><?php echo esc_html($country['name']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="rtt-form-row">
                        <div class="rtt-form-group rtt-col-12">
                            <label><?php echo $lang === 'en' ? 'Allergies or observations' : '¿Tiene alguna alergia u observación?'; ?></label>
                            <textarea name="pasajeros[{index}][alergias]" class="rtt-textarea" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        <?php
        return ob_get_clean();
    }
}
