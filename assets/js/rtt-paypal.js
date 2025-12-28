/**
 * RTT Reservas - PayPal Integration
 */

(function($) {
    'use strict';

    var RTTPayPal = {
        initialized: false,
        paypalLoaded: false,
        currentOrder: null,

        /**
         * Initialize PayPal
         */
        init: function() {
            if (this.initialized) return;
            this.initialized = true;

            // Load PayPal SDK when needed
            this.loadPayPalSDK();
        },

        /**
         * Load PayPal SDK dynamically
         */
        loadPayPalSDK: function() {
            var self = this;

            if (this.paypalLoaded || typeof rttPayPal === 'undefined') {
                return;
            }

            var script = document.createElement('script');
            script.src = 'https://www.paypal.com/sdk/js?client-id=' + rttPayPal.clientId + '&currency=USD&intent=capture';
            script.onload = function() {
                self.paypalLoaded = true;
                self.renderButtons();
            };
            script.onerror = function() {
                console.error('Error loading PayPal SDK');
            };
            document.head.appendChild(script);
        },

        /**
         * Render PayPal buttons
         */
        renderButtons: function() {
            var self = this;
            var container = document.getElementById('paypal-button-container');

            if (!container || !window.paypal) {
                return;
            }

            // Clear container
            container.innerHTML = '';

            paypal.Buttons({
                style: {
                    layout: 'vertical',
                    color: 'gold',
                    shape: 'rect',
                    label: 'paypal',
                    height: 45
                },

                // Create order
                createOrder: function(data, actions) {
                    return self.createOrder();
                },

                // On approve
                onApprove: function(data, actions) {
                    return self.captureOrder(data.orderID);
                },

                // On cancel
                onCancel: function(data) {
                    self.showMessage('Pago cancelado. Puedes intentar de nuevo.', 'warning');
                },

                // On error
                onError: function(err) {
                    console.error('PayPal error:', err);
                    self.showMessage('Error al procesar el pago. Intenta de nuevo.', 'error');
                }
            }).render('#paypal-button-container');
        },

        /**
         * Create PayPal order
         */
        createOrder: function() {
            var self = this;
            var amount = this.getAmount();
            var description = this.getDescription();

            if (!amount || amount <= 0) {
                self.showMessage('No se pudo determinar el monto a pagar.', 'error');
                return Promise.reject('Invalid amount');
            }

            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: rttPayPal.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'rtt_paypal_create_order',
                        nonce: rttPayPal.nonce,
                        amount: amount,
                        description: description,
                        reference_id: self.getReferenceId(),
                        reserva_id: rttPayPal.reservaId || '',
                        cotizacion_id: rttPayPal.cotizacionId || ''
                    },
                    success: function(response) {
                        if (response.success && response.data.order_id) {
                            self.currentOrder = response.data;
                            resolve(response.data.order_id);
                        } else {
                            self.showMessage(response.data.message || 'Error al crear la orden', 'error');
                            reject(response.data.message);
                        }
                    },
                    error: function() {
                        self.showMessage('Error de conexión', 'error');
                        reject('Connection error');
                    }
                });
            });
        },

        /**
         * Capture PayPal order
         */
        captureOrder: function(orderID) {
            var self = this;

            self.showMessage('Procesando pago...', 'info');

            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: rttPayPal.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'rtt_paypal_capture_order',
                        nonce: rttPayPal.nonce,
                        order_id: orderID,
                        reserva_id: self.getReservaId(),
                        cotizacion_id: self.getCotizacionId()
                    },
                    success: function(response) {
                        if (response.success) {
                            self.onPaymentSuccess(response.data);
                            resolve(response.data);
                        } else {
                            self.showMessage(response.data.message || 'Error al capturar el pago', 'error');
                            reject(response.data.message);
                        }
                    },
                    error: function() {
                        self.showMessage('Error de conexión', 'error');
                        reject('Connection error');
                    }
                });
            });
        },

        /**
         * Handle successful payment
         */
        onPaymentSuccess: function(data) {
            var self = this;

            // Hide PayPal buttons
            $('#paypal-button-container').hide();

            // Show success message
            self.showMessage('¡Pago completado exitosamente! ID de transacción: ' + data.transaction_id, 'success');

            // Trigger custom event
            $(document).trigger('rtt_payment_success', [data]);

            // If on reservation form, submit the form
            if ($('#rtt-reserva-form').length) {
                // Mark as paid
                $('<input>').attr({
                    type: 'hidden',
                    name: 'payment_completed',
                    value: '1'
                }).appendTo('#rtt-reserva-form');

                $('<input>').attr({
                    type: 'hidden',
                    name: 'transaction_id',
                    value: data.transaction_id
                }).appendTo('#rtt-reserva-form');

                // Auto-submit after short delay
                setTimeout(function() {
                    $('#rtt-reserva-form').submit();
                }, 1500);
            }

            // If on cotizacion payment page, show success and redirect
            if ($('.rtt-cotizacion-payment').length) {
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            }
        },

        /**
         * Get amount to charge
         */
        getAmount: function() {
            // Try to get from form data
            var amount = 0;

            // Check for cotizacion page
            if ($('#paypal-amount').length) {
                amount = parseFloat($('#paypal-amount').val());
                console.log('RTTPayPal getAmount: from #paypal-amount:', amount);
            }
            // Check for reservation form
            else if ($('#rtt-reserva-form').length) {
                var precioText = $('#rtt-precio-tour').val() || '';
                console.log('RTTPayPal getAmount: #rtt-precio-tour value:', precioText);

                var match = precioText.match(/[\d.,]+/);
                if (match) {
                    amount = parseFloat(match[0].replace(',', '.'));
                }

                // Multiply by number of passengers if unit price
                var pasajeros = $('#rtt-reserva-form .rtt-passenger-card').length || 1;
                console.log('RTTPayPal getAmount: pasajeros:', pasajeros, 'unit price:', amount);

                if (amount > 0 && amount < 500) { // Assuming unit price if less than 500
                    amount = amount * pasajeros;
                }
                console.log('RTTPayPal getAmount: total amount:', amount);
            } else {
                console.log('RTTPayPal getAmount: No form found');
            }

            return amount;
        },

        /**
         * Get description for PayPal
         */
        getDescription: function() {
            var tour = $('select[name="tour"]').val() || $('#cotizacion-tour').text() || 'Reserva';
            return 'RTT - ' + tour;
        },

        /**
         * Get reference ID
         */
        getReferenceId: function() {
            var codigo = $('#cotizacion-codigo').text() || '';
            return codigo || 'RTT-' + Date.now();
        },

        /**
         * Get reserva ID (if exists)
         */
        getReservaId: function() {
            // Primero checar variable JavaScript, luego campo hidden
            return rttPayPal.reservaId || $('#reserva-id').val() || 0;
        },

        /**
         * Get cotizacion ID (if exists)
         */
        getCotizacionId: function() {
            // Primero checar variable JavaScript, luego campo hidden
            return rttPayPal.cotizacionId || $('#cotizacion-id').val() || 0;
        },

        /**
         * Show message to user
         */
        showMessage: function(message, type) {
            var $container = $('#paypal-message');
            if (!$container.length) {
                $container = $('<div id="paypal-message"></div>');
                $('#paypal-button-container').before($container);
            }

            var colors = {
                success: '#28a745',
                error: '#dc3545',
                warning: '#ffc107',
                info: '#17a2b8'
            };

            $container.html(message)
                .css({
                    'padding': '12px 16px',
                    'margin-bottom': '15px',
                    'border-radius': '8px',
                    'background-color': colors[type] || colors.info,
                    'color': type === 'warning' ? '#333' : '#fff',
                    'text-align': 'center',
                    'font-weight': '500'
                })
                .show();

            // Auto-hide for non-success messages
            if (type !== 'success') {
                setTimeout(function() {
                    $container.fadeOut();
                }, 5000);
            }
        },

        /**
         * Show PayPal section in form
         */
        showPaymentSection: function() {
            var self = this;

            if (!rttPayPal || !rttPayPal.enabled) {
                console.log('RTTPayPal: PayPal not enabled');
                return;
            }

            var $section = $('#rtt-payment-section');
            if ($section.length) {
                console.log('RTTPayPal: Showing payment section');

                // Calculate and display the amount
                var amount = this.getAmount();
                console.log('RTTPayPal: Calculated amount:', amount);
                $('#rtt-payment-total').text('$' + amount.toFixed(2) + ' USD');

                // Only show if amount is valid
                if (amount <= 0) {
                    console.log('RTTPayPal: Amount is 0, hiding payment section');
                    return;
                }

                $section.show();

                // Wait for SDK to load if not ready
                if (this.paypalLoaded && window.paypal) {
                    this.renderButtons();
                } else {
                    console.log('RTTPayPal: Waiting for SDK to load...');
                    // Check every 500ms for SDK to be ready
                    var checkInterval = setInterval(function() {
                        if (self.paypalLoaded && window.paypal) {
                            clearInterval(checkInterval);
                            console.log('RTTPayPal: SDK loaded, rendering buttons');
                            self.renderButtons();
                        }
                    }, 500);

                    // Timeout after 10 seconds
                    setTimeout(function() {
                        clearInterval(checkInterval);
                        if (!self.paypalLoaded) {
                            console.error('RTTPayPal: SDK failed to load');
                        }
                    }, 10000);
                }
            } else {
                console.log('RTTPayPal: Payment section not found in DOM');
            }
        },

        /**
         * Hide PayPal section
         */
        hidePaymentSection: function() {
            $('#rtt-payment-section').hide();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof rttPayPal !== 'undefined' && rttPayPal.enabled) {
            RTTPayPal.init();
        }
    });

    // Expose to global scope
    window.RTTPayPal = RTTPayPal;

})(jQuery);
