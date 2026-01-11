/**
 * RTT Reservas - JavaScript del botón de reserva y modal
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initBookingButtons();
        initPricePills();
        initModalClose();
    });

    /**
     * Inicializar botones de reserva
     */
    function initBookingButtons() {
        $('.rtt-booking-btn').on('click', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var tour = $btn.data('tour');
            var lang = $btn.data('lang') || 'es';
            var price = $btn.data('price');
            var priceFull = $btn.data('price-full');
            var priceNote = $btn.data('price-note') || '';
            var priceFrom = $btn.data('price-from');
            var preSelectedPlan = $btn.data('plan') || ''; // Plan pre-seleccionado desde cards externas
            var $modal = $('#rtt-booking-modal');

            if ($modal.length === 0) {
                return;
            }

            // Mostrar header con información del tour
            updateModalHeader(tour, price, priceFull, priceFrom, lang);

            // Pre-seleccionar el tour si se especificó
            if (tour) {
                var $select = $modal.find('#rtt-tour');
                if ($select.length) {
                    // Buscar la opción que coincida
                    var found = false;
                    $select.find('option').each(function() {
                        var optionText = $(this).text().toLowerCase().trim();
                        var tourText = tour.toLowerCase().trim();

                        // Buscar coincidencia exacta o parcial
                        if (optionText === tourText || optionText.indexOf(tourText) !== -1 || tourText.indexOf(optionText) !== -1) {
                            $(this).prop('selected', true);
                            found = true;
                            return false; // break
                        }
                    });

                    // Si no encontró, intentar buscar por valor
                    if (!found) {
                        $select.val(tour);
                    }

                    // NO trigger change aquí - lo manejamos manualmente para dos precios
                }
            }

            // Configurar selector de plan si hay dos precios
            setupPlanSelector(price, priceFull, lang, priceNote, preSelectedPlan);

            // Actualizar idioma del formulario
            updateFormLanguage($modal, lang);

            // Abrir modal
            openModal($modal);
        });
    }

    /**
     * Inicializar pills de precios
     */
    function initPricePills() {
        // Seleccionar pill
        $(document).on('click', '.rtt-price-pill', function(e) {
            e.preventDefault();

            var $pill = $(this);
            var $wrapper = $pill.closest('.rtt-price-pills-wrapper');
            var $bookBtn = $wrapper.find('.rtt-pills-booking-btn');

            // Quitar selección de otros pills
            $wrapper.find('.rtt-price-pill').removeClass('selected');

            // Seleccionar este pill
            $pill.addClass('selected');

            // Habilitar botón de reserva
            $bookBtn.prop('disabled', false);

            // Guardar datos del plan seleccionado en el botón
            var plan = $pill.data('plan');
            var price = $pill.data('price');
            var priceFull = $pill.data('price-full');
            var priceNote = $pill.data('price-note');

            $bookBtn.data('selected-plan', plan);
            $bookBtn.data('selected-price', plan === 'accesible' ? price : priceFull);
        });

        // Click en botón de reserva de pills
        $(document).on('click', '.rtt-pills-booking-btn:not(:disabled)', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $wrapper = $btn.closest('.rtt-price-pills-wrapper');
            var tour = $wrapper.data('tour');
            var lang = $wrapper.data('lang') || 'es';
            var price = $btn.data('price');
            var priceFull = $btn.data('price-full');
            var priceNote = $btn.data('price-note') || '';
            var selectedPlan = $btn.data('selected-plan');
            var $modal = $('#rtt-booking-modal');

            if ($modal.length === 0) {
                return;
            }

            // Mostrar header con información del tour
            updateModalHeader(tour, price, priceFull, null, lang);

            // Pre-seleccionar el tour
            if (tour) {
                var $select = $modal.find('#rtt-tour');
                if ($select.length) {
                    var found = false;
                    $select.find('option').each(function() {
                        var optionText = $(this).text().toLowerCase().trim();
                        var tourText = tour.toLowerCase().trim();
                        if (optionText === tourText || optionText.indexOf(tourText) !== -1 || tourText.indexOf(optionText) !== -1) {
                            $(this).prop('selected', true);
                            found = true;
                            return false;
                        }
                    });
                    if (!found) {
                        $select.val(tour);
                    }
                }
            }

            // Configurar selector de plan con el plan pre-seleccionado
            setupPlanSelector(price, priceFull, lang, priceNote, selectedPlan);

            // Actualizar idioma del formulario
            updateFormLanguage($modal, lang);

            // Abrir modal
            openModal($modal);
        });
    }

    /**
     * Actualizar idioma del formulario cuando se abre el modal
     */
    function updateFormLanguage($modal, lang) {
        // Actualizar campo hidden del idioma
        $modal.find('input[name="lang"]').val(lang);

        // Actualizar data-lang del contenedor
        $modal.find('.rtt-reservas-container').attr('data-lang', lang);
    }

    /**
     * Configurar selector de plan cuando hay dos precios
     */
    function setupPlanSelector(price, priceFull, lang, priceNote, preSelectedPlan) {
        var $planSelector = $('#rtt-plan-selector');
        var $priceBasic = $('#rtt-price-basic');
        var $priceFull = $('#rtt-price-full');
        var $priceNote = $('#rtt-price-note');
        var $planInput = $('#rtt-plan-selected');
        var $precioTourInput = $('#rtt-precio-tour');

        if (price && priceFull && priceFull > 0) {
            // Hay dos precios: mostrar cards
            $priceBasic.text('$' + price);
            $priceFull.text('$' + priceFull);

            // Mostrar nota si existe
            if (priceNote) {
                $priceNote.text(priceNote);
            } else {
                $priceNote.text('');
            }

            $planSelector.show();

            // Resetear selección
            $('.rtt-plan-card').removeClass('selected');
            $planInput.val('');
            $precioTourInput.val('');

            // Pre-seleccionar plan si viene desde cards externas
            if (preSelectedPlan === 'accesible') {
                $('.rtt-plan-card[data-plan="accesible"]').addClass('selected');
                $planInput.val('accesible');
                $precioTourInput.val('$' + price + ' USD');
            } else if (preSelectedPlan === 'total') {
                $('.rtt-plan-card[data-plan="total"]').addClass('selected');
                $planInput.val('total');
                $precioTourInput.val('$' + priceFull + ' USD');
            }

            // Marcar que viene del shortcode para no permitir que el change del select lo oculte
            $planSelector.attr('data-from-shortcode', 'true');
        } else if (price) {
            // Solo un precio: ocultar cards y usar precio único
            $planSelector.hide();
            $planSelector.removeAttr('data-from-shortcode');
            $priceNote.text('');
            $planInput.val('unico');
            $precioTourInput.val('$' + price + ' USD');
        } else {
            // Sin precio - dejar que el select del tour maneje esto
            $planSelector.removeAttr('data-from-shortcode');
            $priceNote.text('');
        }
    }

    /**
     * Actualizar header del modal con info del tour
     */
    function updateModalHeader(tour, price, priceFull, priceFrom, lang) {
        var $header = $('#rtt-modal-header');
        var $tourName = $('#rtt-modal-tour-name');
        var $tourPrice = $('#rtt-modal-tour-price');

        // Si hay tour o precio, mostrar header
        if (tour || price || priceFrom) {
            $header.show();

            // Nombre del tour
            if (tour) {
                $tourName.text(tour);
            } else {
                $tourName.text(lang === 'en' ? 'Tour Reservation' : 'Reserva de Tour');
            }

            // Precio en header
            if (price && priceFull && priceFull > 0) {
                // Dos precios: mostrar "desde"
                var fromLabel = lang === 'en' ? 'From' : 'Desde';
                var personLabel = lang === 'en' ? 'per person' : 'por persona';
                $tourPrice.html(fromLabel + ' <strong>$' + price + ' USD</strong> ' + personLabel);
            } else if (price) {
                var priceLabel = lang === 'en' ? 'Price:' : 'Precio:';
                var personLabel = lang === 'en' ? 'per person' : 'por persona';
                $tourPrice.html(priceLabel + ' <strong>$' + price + ' USD</strong> ' + personLabel);
            } else if (priceFrom) {
                var fromLabel = lang === 'en' ? 'From' : 'Desde';
                var personLabel = lang === 'en' ? 'per person' : 'por persona';
                $tourPrice.html(fromLabel + ' <strong>$' + priceFrom + ' USD</strong> ' + personLabel);
            } else {
                $tourPrice.html('');
            }
        } else {
            $header.hide();
        }
    }

    /**
     * Abrir modal
     */
    function openModal($modal) {
        $('body').addClass('rtt-modal-open');
        $modal.addClass('rtt-modal-active').show();

        // Inicializar formulario y datepicker después de que sea visible
        setTimeout(function() {
            // Inicializar el formulario completo (wizard, pasajeros, validación, etc.)
            if (typeof window.rttInitForm === 'function') {
                window.rttInitForm();
            }

            // Inicializar datepicker del modal
            initModalDatePicker();

            $modal.find('input:visible, select:visible').first().focus();
        }, 150);
    }

    /**
     * Inicializar datepicker dentro del modal
     */
    function initModalDatePicker() {
        var fechaInput = document.getElementById('rtt-fecha');

        if (!fechaInput) {
            return;
        }

        // Si ya es type=date (fallback), está funcionando
        if (fechaInput.type === 'date') {
            return;
        }

        // Verificar si MCDatepicker está disponible
        if (typeof MCDatepicker !== 'undefined') {
            try {
                // Destruir instancia anterior si existe
                if (fechaInput._mcdp) {
                    try {
                        fechaInput._mcdp.destroy();
                    } catch(de) {}
                    fechaInput._mcdp = null;
                }

                var picker = MCDatepicker.create({
                    el: '#rtt-fecha',
                    dateFormat: 'dd-MM-YYYY',
                    autoClose: true,
                    minDate: new Date(),
                    disableDates: [new Date()]
                });

                // Guardar referencia
                fechaInput._mcdp = picker;

                // Forzar z-index cuando se abre el calendario
                fechaInput.addEventListener('click', forceCalendarZIndex);
                fechaInput.addEventListener('focus', forceCalendarZIndex);

            } catch (e) {
                useFallbackDateInput(fechaInput);
            }
        } else {
            useFallbackDateInput(fechaInput);
        }
    }

    /**
     * Forzar z-index alto en el calendario
     */
    function forceCalendarZIndex() {
        setTimeout(function() {
            var calendarElements = document.querySelectorAll('.mc-calendar, .mc-calendar__wrapper, .mc-calendar__body, .mc-calendar__header');
            calendarElements.forEach(function(el) {
                el.style.setProperty('z-index', '99999999', 'important');
            });
        }, 50);
    }

    /**
     * Usar input date nativo como fallback
     */
    function useFallbackDateInput(fechaInput) {
        fechaInput.type = 'date';
        fechaInput.readOnly = false;
        fechaInput.removeAttribute('readonly');

        // Establecer fecha mínima (mañana)
        var tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        fechaInput.min = tomorrow.toISOString().split('T')[0];
    }

    /**
     * Cerrar modal
     */
    function closeModal() {
        var $modal = $('#rtt-booking-modal');

        // Cerrar calendario si está abierto
        var fechaInput = document.getElementById('rtt-fecha');
        if (fechaInput && fechaInput._mcdp && typeof fechaInput._mcdp.close === 'function') {
            fechaInput._mcdp.close();
        }

        $('body').removeClass('rtt-modal-open');
        $modal.removeClass('rtt-modal-active').hide();
    }

    /**
     * Inicializar cierre del modal
     */
    function initModalClose() {
        // Cerrar con botón X
        $(document).on('click', '.rtt-modal-close-btn', function(e) {
            e.preventDefault();
            closeModal();
        });

        // Cerrar al hacer clic fuera
        $(document).on('click', '.rtt-modal-overlay', function(e) {
            if ($(e.target).hasClass('rtt-modal-overlay')) {
                closeModal();
            }
        });

        // Cerrar con ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#rtt-booking-modal').is(':visible')) {
                closeModal();
            }
        });
    }

    // Exponer función para cerrar modal globalmente
    window.rttCloseBookingModal = closeModal;

})(jQuery);
