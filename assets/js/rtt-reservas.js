/**
 * RTT Reservas - JavaScript del formulario
 * Ready To Travel Peru
 */

(function($) {
    'use strict';

    // Variables globales
    let currentStep = 1;
    let passengerCount = 0;
    let datePicker = null;
    let initialized = false;

    // Inicialización
    $(document).ready(function() {
        // Solo inicializar si hay un formulario visible (no en modal oculto)
        var $container = $('.rtt-reservas-container');

        if ($container.length && $container.is(':visible')) {
            initForm();
        }
    });

    /**
     * Inicializar formulario (puede ser llamado externamente)
     */
    function initForm() {
        if (initialized) {
            return;
        }

        var $container = $('.rtt-reservas-container');
        var $form = $('#rtt-reserva-form');

        if (!$container.length || !$form.length) {
            return;
        }

        initDatePicker();
        initWizard($form);
        initPassengers($form);
        initFormSubmit($form);
        initValidation($form);
        initFlagSelects();
        initPlanSelector($form);

        initialized = true;
    }

    // Exponer initForm globalmente para el modal
    window.rttInitForm = initForm;

    /**
     * Inicializar MC Calendar Datepicker
     */
    function initDatePicker() {
        var fechaInput = document.getElementById('rtt-fecha');

        if (!fechaInput) {
            return;
        }

        // Si ya tiene un picker, no reinicializar
        if (fechaInput._mcdp || fechaInput.type === 'date') {
            return;
        }

        // Verificar que MCDatepicker existe
        if (typeof MCDatepicker === 'undefined') {
            fechaInput.type = 'date';
            fechaInput.readOnly = false;
            var tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            fechaInput.min = tomorrow.toISOString().split('T')[0];
            return;
        }

        try {
            datePicker = MCDatepicker.create({
                el: '#rtt-fecha',
                dateFormat: 'dd-MM-YYYY',
                autoClose: true,
                minDate: new Date(),
                disableDates: [new Date()]
            });

            fechaInput._mcdp = datePicker;

            // Cerrar al iniciar
            if (datePicker && typeof datePicker.close === 'function') {
                datePicker.close();
            }
        } catch (e) {
            fechaInput.type = 'date';
            fechaInput.readOnly = false;
            var tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            fechaInput.min = tomorrow.toISOString().split('T')[0];
        }
    }

    /**
     * Inicializar wizard de pasos
     */
    function initWizard($form) {
        // Botones siguiente
        $form.on('click', '.rtt-btn-next', function(e) {
            e.preventDefault();
            var nextStep = $(this).data('next');

            if (validateStep(currentStep)) {
                goToStep(nextStep);
            }
        });

        // Botones anterior
        $form.on('click', '.rtt-btn-prev', function(e) {
            e.preventDefault();
            var prevStep = $(this).data('prev');
            goToStep(prevStep);
        });
    }

    /**
     * Ir a un paso específico
     */
    function goToStep(step) {
        var $container = $('.rtt-reservas-container');

        // Ocultar paso actual
        $('.rtt-step-' + currentStep).removeClass('active');
        $('.rtt-progress-step[data-step="' + currentStep + '"]').removeClass('active').addClass('completed');

        // Actualizar línea de progreso
        if (step > currentStep) {
            $('.rtt-progress-line').eq(currentStep - 1).addClass('completed');
        } else {
            $('.rtt-progress-line').eq(step - 1).removeClass('completed');
            $('.rtt-progress-step[data-step="' + currentStep + '"]').removeClass('completed');
        }

        // Mostrar nuevo paso
        currentStep = step;
        $('.rtt-step-' + currentStep).addClass('active');
        $('.rtt-progress-step[data-step="' + currentStep + '"]').addClass('active');

        // Scroll al inicio del formulario (solo si está visible)
        if ($container.is(':visible')) {
            var scrollTarget = $container.offset().top - 50;
            var $modal = $container.closest('.rtt-modal-overlay');
            if ($modal.length) {
                $modal.animate({ scrollTop: 0 }, 300);
            } else {
                $('html, body').animate({ scrollTop: scrollTarget }, 300);
            }
        }
    }

    /**
     * Validar paso actual
     */
    function validateStep(step) {
        var isValid = true;
        var $stepContainer = $('.rtt-step-' + step);

        // Limpiar errores anteriores
        $stepContainer.find('.error').removeClass('error');
        $stepContainer.find('.rtt-field-error').remove();

        // Validar campos requeridos (inputs y selects normales)
        $stepContainer.find('input[required], select[required], textarea[required]').not('[type="hidden"]').each(function() {
            var $field = $(this);
            var value = $field.val();

            if (!value || value.trim() === '') {
                isValid = false;
                showFieldError($field, rttReservas.i18n.requiredField);
            }
        });

        // Validar flag selects (hidden inputs dentro de .rtt-flag-select)
        $stepContainer.find('.rtt-flag-select input[type="hidden"][required]').each(function() {
            var $hiddenInput = $(this);
            var $flagSelect = $hiddenInput.closest('.rtt-flag-select');
            var value = $hiddenInput.val();

            if (!value || value.trim() === '') {
                isValid = false;
                $flagSelect.find('.rtt-flag-select-trigger').addClass('error');
                $flagSelect.after('<div class="rtt-field-error">' + rttReservas.i18n.requiredField + '</div>');
            }
        });

        // Validar email en paso 3
        if (step === 3) {
            var $emailField = $('#rtt-email');
            var email = $emailField.val();
            if (email && !isValidEmail(email)) {
                isValid = false;
                showFieldError($emailField, rttReservas.i18n.invalidEmail);
            }
        }

        // Validar que haya al menos un pasajero en paso 2
        if (step === 2 && passengerCount === 0) {
            isValid = false;
            showMessage(rttReservas.i18n.minPassengers, 'error');
        }

        // Validar selección de plan en paso 1 (si el tour tiene dos precios)
        if (step === 1) {
            var $planSelector = $('#rtt-plan-selector');
            var $planInput = $('#rtt-plan-selected');

            // Si el selector de plan está visible, se debe seleccionar uno
            if ($planSelector.is(':visible') && !$planInput.val()) {
                isValid = false;
                $('.rtt-plan-cards').addClass('error');
                $planSelector.append('<div class="rtt-field-error rtt-plan-error" style="text-align:center;margin-top:10px;">' + (rttReservas.i18n.selectPlan || 'Selecciona un plan') + '</div>');
            }
        }

        return isValid;
    }

    /**
     * Mostrar error en campo
     */
    function showFieldError($field, message) {
        $field.addClass('error');
        $field.after('<div class="rtt-field-error">' + message + '</div>');
    }

    /**
     * Validar formato de email
     */
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    /**
     * Inicializar control de pasajeros
     */
    function initPassengers($form) {
        var $minusBtn = $('.rtt-btn-minus');
        var $plusBtn = $('.rtt-btn-plus');
        var $countInput = $('#rtt-cantidad');
        var $passengersContainer = $('#rtt-passengers-container');

        // Agregar pasajero (máximo configurable desde admin)
        var MAX_PASSENGERS = rttReservas.maxPassengers || 20;

        $plusBtn.on('click', function() {
            if (passengerCount >= MAX_PASSENGERS) {
                alert(rttReservas.lang === 'en'
                    ? 'Maximum ' + MAX_PASSENGERS + ' passengers per reservation.'
                    : 'Máximo ' + MAX_PASSENGERS + ' pasajeros por reserva.');
                return;
            }

            passengerCount++;
            $countInput.val(passengerCount);
            addPassenger(passengerCount);

            // Habilitar botón menos
            $minusBtn.prop('disabled', false);

            // Deshabilitar botón plus si llegamos al máximo
            if (passengerCount >= MAX_PASSENGERS) {
                $plusBtn.prop('disabled', true);
            }
        });

        // Quitar pasajero
        $minusBtn.on('click', function() {
            if (passengerCount > 0) {
                removePassenger(passengerCount);
                passengerCount--;
                $countInput.val(passengerCount);

                // Deshabilitar si llegamos a 0
                if (passengerCount === 0) {
                    $minusBtn.prop('disabled', true);
                }
            }
        });

        // Eliminar pasajero específico
        $passengersContainer.on('click', '.rtt-btn-remove-passenger', function() {
            var $card = $(this).closest('.rtt-passenger-card');
            $card.slideUp(300, function() {
                $card.remove();
                passengerCount--;
                $countInput.val(passengerCount);
                renumberPassengers();

                if (passengerCount === 0) {
                    $minusBtn.prop('disabled', true);
                }
            });
        });
    }

    /**
     * Agregar pasajero al formulario
     */
    function addPassenger(index) {
        var template = $('#rtt-passenger-template').html();
        var html = template
            .replace(/{index}/g, index)
            .replace(/{number}/g, index);

        var $card = $(html);
        $card.hide();
        $('#rtt-passengers-container').append($card);
        $card.slideDown(300);
    }

    /**
     * Quitar último pasajero
     */
    function removePassenger(index) {
        $('.rtt-passenger-card[data-passenger-index="' + index + '"]').slideUp(300, function() {
            $(this).remove();
        });
    }

    /**
     * Renumerar pasajeros después de eliminar uno
     */
    function renumberPassengers() {
        $('#rtt-passengers-container .rtt-passenger-card').each(function(i) {
            var newIndex = i + 1;
            var $card = $(this);

            $card.attr('data-passenger-index', newIndex);
            $card.find('h4').text(rttReservas.i18n.passenger + ' #' + newIndex);

            // Actualizar nombres de campos
            $card.find('[name]').each(function() {
                var name = $(this).attr('name');
                var newName = name.replace(/\[\d+\]/, '[' + newIndex + ']');
                $(this).attr('name', newName);
            });
        });
    }

    /**
     * Inicializar envío del formulario
     */
    function initFormSubmit($form) {
        $form.on('submit', function(e) {
            e.preventDefault();

            // Validar último paso
            if (!validateStep(3)) {
                return;
            }

            // Validar pasajeros
            if (passengerCount === 0) {
                showMessage(rttReservas.i18n.minPassengers, 'error');
                return;
            }

            // Deshabilitar botón y mostrar loading
            var $submitBtn = $form.find('.rtt-btn-submit');
            var originalText = $submitBtn.html();
            $submitBtn.prop('disabled', true).html('<span class="rtt-spinner"></span> ' + rttReservas.i18n.processing);

            // Enviar formulario via AJAX
            $.ajax({
                url: rttReservas.ajaxUrl,
                type: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        $form.find('.rtt-step').hide();

                        // Cerrar modal después de éxito si existe
                        setTimeout(function() {
                            if (typeof window.rttCloseBookingModal === 'function') {
                                window.rttCloseBookingModal();
                            }
                        }, 3000);
                    } else {
                        showMessage(response.data.message, 'error');
                        $submitBtn.prop('disabled', false).html(originalText);
                    }
                },
                error: function() {
                    showMessage(rttReservas.i18n.error, 'error');
                    $submitBtn.prop('disabled', false).html(originalText);
                }
            });
        });
    }

    /**
     * Mostrar mensaje
     */
    function showMessage(message, type) {
        var $messageDiv = $('#rtt-message');
        $messageDiv
            .removeClass('success error')
            .addClass(type)
            .html(message)
            .slideDown(300);

        // Scroll al mensaje
        var $container = $('.rtt-reservas-container');
        if ($container.is(':visible')) {
            var $modal = $container.closest('.rtt-modal-overlay');
            if ($modal.length) {
                $modal.animate({ scrollTop: 0 }, 300);
            } else {
                $('html, body').animate({ scrollTop: $messageDiv.offset().top - 100 }, 300);
            }
        }

        // Ocultar después de 10 segundos si es error
        if (type === 'error') {
            setTimeout(function() {
                $messageDiv.slideUp(300);
            }, 10000);
        }
    }

    /**
     * Inicializar validación en tiempo real
     */
    function initValidation($form) {
        // Limpiar error al escribir
        $form.on('input change', '.error', function() {
            $(this).removeClass('error');
            $(this).siblings('.rtt-field-error').remove();
        });

        // Validar email en blur
        $('#rtt-email').on('blur', function() {
            var email = $(this).val();
            if (email && !isValidEmail(email)) {
                showFieldError($(this), rttReservas.i18n.invalidEmail);
            }
        });
    }

    /**
     * Inicializar Flag Selects personalizados
     */
    function initFlagSelects() {
        // Delegación de eventos para manejar selects dinámicos (pasajeros)
        $(document).off('click.flagselect').on('click.flagselect', '.rtt-flag-select-trigger', function(e) {
            e.stopPropagation();
            var $trigger = $(this);
            var $select = $trigger.closest('.rtt-flag-select');
            var $dropdown = $select.find('.rtt-flag-select-dropdown');
            var wasOpen = $select.hasClass('open');

            // Cerrar todos los demás
            $('.rtt-flag-select').removeClass('open');
            $('.rtt-flag-select-dropdown').css({
                'position': '',
                'top': '',
                'left': '',
                'width': ''
            });

            // Toggle este
            if (!wasOpen) {
                $select.addClass('open');

                // Posicionar con fixed para escapar cualquier overflow:hidden
                var rect = $trigger[0].getBoundingClientRect();
                $dropdown.css({
                    'position': 'fixed',
                    'top': (rect.bottom) + 'px',
                    'left': rect.left + 'px',
                    'width': rect.width + 'px'
                });

                $select.find('.rtt-flag-search').val('').trigger('input').focus();
            }
        });

        // Búsqueda
        $(document).off('input.flagselect').on('input.flagselect', '.rtt-flag-search', function() {
            var search = $(this).val().toLowerCase();
            // Buscar las opciones desde el contenedor del dropdown (el input está dentro de .rtt-flag-search-wrapper)
            var $dropdown = $(this).closest('.rtt-flag-select-dropdown');
            var $options = $dropdown.find('.rtt-flag-options .rtt-flag-option');

            $options.each(function() {
                var text = $(this).find('span').text().toLowerCase();
                if (text.indexOf(search) > -1) {
                    $(this).removeClass('hidden');
                } else {
                    $(this).addClass('hidden');
                }
            });
        });

        // Seleccionar con Enter si hay un solo resultado visible
        $(document).off('keydown.flagselect').on('keydown.flagselect', '.rtt-flag-search', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var $dropdown = $(this).closest('.rtt-flag-select-dropdown');
                var $visibleOptions = $dropdown.find('.rtt-flag-options .rtt-flag-option:not(.hidden)');

                // Si hay exactamente un resultado visible, seleccionarlo
                if ($visibleOptions.length === 1) {
                    $visibleOptions.first().trigger('click');
                }
            }
        });

        // Seleccionar opción
        $(document).off('click.flagoption').on('click.flagoption', '.rtt-flag-option', function() {
            var $option = $(this);
            var $select = $option.closest('.rtt-flag-select');
            var value = $option.data('value');
            var code = $option.data('code');
            var name = $option.find('span').text();

            // Actualizar hidden input
            $select.find('input[type="hidden"]').val(value).trigger('change');

            // Actualizar display
            var $valueDisplay = $select.find('.rtt-flag-select-value');
            $valueDisplay.html(
                '<img src="https://flagcdn.com/24x18/' + code + '.png" ' +
                'srcset="https://flagcdn.com/48x36/' + code + '.png 2x" ' +
                'alt="' + name + '" class="rtt-flag-img">' +
                '<span>' + name + '</span>'
            ).removeClass('placeholder');

            // Marcar como seleccionado
            $select.find('.rtt-flag-option').removeClass('selected');
            $option.addClass('selected');

            // Cerrar dropdown y resetear posición
            $select.removeClass('open');
            $select.find('.rtt-flag-select-dropdown').css({
                'position': '',
                'top': '',
                'left': '',
                'width': ''
            });

            // Quitar error si existe
            $select.find('.rtt-flag-select-trigger').removeClass('error');
            $select.siblings('.rtt-field-error').remove();
        });

        // Cerrar al hacer click fuera
        $(document).off('click.flagclose').on('click.flagclose', function(e) {
            if (!$(e.target).closest('.rtt-flag-select').length && !$(e.target).closest('.rtt-flag-select-dropdown').length) {
                $('.rtt-flag-select').removeClass('open');
                $('.rtt-flag-select-dropdown').css({
                    'position': '',
                    'top': '',
                    'left': '',
                    'width': ''
                });
            }
        });

        // Evitar que el search cierre el dropdown
        $(document).off('click.flagsearch').on('click.flagsearch', '.rtt-flag-search', function(e) {
            e.stopPropagation();
        });
    }

    // Exponer initFlagSelects para reinicializar después de agregar pasajeros
    window.rttInitFlagSelects = initFlagSelects;

    /**
     * Inicializar selector de planes (cards de precio)
     */
    function initPlanSelector($form) {
        var $tourSelect = $('#rtt-tour');
        var $planSelector = $('#rtt-plan-selector');
        var $priceBasic = $('#rtt-price-basic');
        var $priceFull = $('#rtt-price-full');
        var $priceNote = $('#rtt-price-note');
        var $planInput = $('#rtt-plan-selected');
        var $precioTourInput = $('#rtt-precio-tour');

        // Detectar idioma del formulario
        var lang = $form.closest('[data-lang]').data('lang') || 'es';
        if ($('html').attr('lang')) {
            lang = $('html').attr('lang').substring(0, 2);
        }

        // Cuando cambia el tour seleccionado
        $tourSelect.on('change', function() {
            var $selected = $(this).find('option:selected');
            var priceBasic = $selected.data('price') || 0;
            var priceFull = $selected.data('price-full') || 0;
            var priceNoteEs = $selected.data('price-note') || '';
            var priceNoteEn = $selected.data('price-note-en') || '';
            var priceNote = lang === 'en' ? (priceNoteEn || priceNoteEs) : priceNoteEs;

            // Si el tour tiene dos precios, mostrar las cards
            if (priceFull > 0) {
                $priceBasic.text('$' + priceBasic);
                $priceFull.text('$' + priceFull);

                // Mostrar nota si existe
                if (priceNote) {
                    $priceNote.text(priceNote);
                } else {
                    $priceNote.text('');
                }

                $planSelector.slideDown(300);

                // Resetear selección
                $('.rtt-plan-card').removeClass('selected');
                $planInput.val('');
                $precioTourInput.val('');
            } else {
                // Solo un precio, ocultar cards y usar el precio único
                $planSelector.slideUp(300);
                $priceNote.text('');
                $planInput.val('unico');
                $precioTourInput.val(priceBasic > 0 ? '$' + priceBasic + ' USD' : '');
            }
        });

        // Click en las cards de plan
        $form.on('click', '.rtt-plan-card', function() {
            var $card = $(this);
            var plan = $card.data('plan');
            var $tourSelect = $('#rtt-tour');
            var $selected = $tourSelect.find('option:selected');
            var priceBasic = $selected.data('price') || 0;
            var priceFull = $selected.data('price-full') || 0;

            // Marcar como seleccionado
            $('.rtt-plan-card').removeClass('selected');
            $card.addClass('selected');

            // Limpiar errores
            $('.rtt-plan-cards').removeClass('error');
            $('.rtt-plan-error').remove();

            // Guardar el plan y precio seleccionado
            $planInput.val(plan);

            if (plan === 'accesible') {
                $precioTourInput.val('$' + priceBasic + ' USD (Plan Accesible)');
            } else {
                $precioTourInput.val('$' + priceFull + ' USD (Paquete Total)');
            }
        });
    }

})(jQuery);
