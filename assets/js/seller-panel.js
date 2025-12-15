/**
 * RTT Seller Panel - JavaScript
 * Modular JS file for better maintenance
 */

(function($) {
    'use strict';

    // Global namespace
    window.RTTSeller = window.RTTSeller || {};

    /**
     * Initialize mobile sidebar toggle
     */
    RTTSeller.initSidebar = function() {
        var $sidebar = $('.sidebar');
        var $overlay = $('.sidebar-overlay');
        var $toggleBtn = $('.mobile-menu-btn');

        $toggleBtn.on('click', function() {
            $sidebar.toggleClass('open');
            $overlay.toggleClass('active');
        });

        $overlay.on('click', function() {
            $sidebar.removeClass('open');
            $overlay.removeClass('active');
        });

        // Close on ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $sidebar.removeClass('open');
                $overlay.removeClass('active');
            }
        });
    };

    /**
     * Calculate quotation totals
     */
    RTTSeller.calcularTotal = function() {
        var cantidad = parseInt($('#cantidad_pasajeros').val()) || 1;
        var precioUnitario = parseFloat($('#precio_unitario').val()) || 0;
        var descuento = parseFloat($('#descuento').val()) || 0;
        var descuentoTipo = $('#descuento_tipo').val();
        var moneda = $('#moneda').val() || 'USD';

        var simbolo = moneda === 'PEN' ? 'S/' : (moneda === 'EUR' ? '€' : '$');
        var subtotal = cantidad * precioUnitario;
        var descuentoMonto = 0;

        if (descuentoTipo === 'porcentaje') {
            descuentoMonto = subtotal * (descuento / 100);
        } else {
            descuentoMonto = descuento;
        }

        var total = subtotal - descuentoMonto;

        $('#subtotal').text(simbolo + ' ' + subtotal.toFixed(2));
        $('#precio_total_display').text(simbolo + ' ' + total.toFixed(2));
        $('#precio_total').val(total.toFixed(2));

        // Recalculate profit
        RTTSeller.calcularCostos();
    };

    /**
     * Calculate internal costs and profit
     */
    RTTSeller.calcularCostos = function() {
        var costoTotalUSD = 0;
        var costos = [];

        $('.costo-item').each(function() {
            var concepto = $(this).find('.costo-concepto').val().trim();
            var monto = parseFloat($(this).find('.costo-monto').val()) || 0;
            if (concepto || monto > 0) {
                costos.push({ concepto: concepto, monto: monto });
            }
            costoTotalUSD += monto;
        });

        // Save costs as JSON
        $('#costos_json').val(JSON.stringify(costos));

        // Get sale price in USD (assuming sale is in USD)
        var precioTotalVenta = parseFloat($('#precio_total').val()) || 0;
        var tipoCambio = parseFloat($('#tipo_cambio').val()) || 3.70;

        // Convert sale price to USD if needed
        var monedaVenta = $('#moneda').val() || 'USD';
        var precioVentaUSD = precioTotalVenta;
        if (monedaVenta === 'PEN') {
            precioVentaUSD = precioTotalVenta / tipoCambio;
        }

        // Calculate profit in both currencies
        var gananciaUSD = precioVentaUSD - costoTotalUSD;
        var gananciaPEN = gananciaUSD * tipoCambio;

        // Update UI
        $('#costo_total_display').text('$ ' + costoTotalUSD.toFixed(2));
        $('#ganancia_usd').text('$ ' + gananciaUSD.toFixed(2));
        $('#ganancia_pen').text('S/ ' + gananciaPEN.toFixed(2));

        // Percentage
        var gananciaPercent = precioVentaUSD > 0 ? ((gananciaUSD / precioVentaUSD) * 100) : 0;
        $('#ganancia_pct').text(gananciaPercent.toFixed(0) + '%');

        // Update color based on profit
        var $gananciaCard = $('#ganancia-card');
        $gananciaCard.removeClass('negative warning');
        if (gananciaUSD < 0) {
            $gananciaCard.addClass('negative');
        } else if (gananciaPercent < 15) {
            $gananciaCard.addClass('warning');
        }
    };

    /**
     * Initialize dynamic cost items
     */
    RTTSeller.initCostosItems = function() {
        // Add new cost item
        $('#btn-add-costo').on('click', function() {
            var newItem = `
                <div class="costo-item">
                    <div class="costo-drag">⋮⋮</div>
                    <input type="text" name="costo_concepto[]" placeholder="Ej: Guía, Transporte, Entradas..." class="costo-concepto">
                    <div class="costo-monto-wrapper">
                        <span class="costo-prefix">$</span>
                        <input type="number" name="costo_monto[]" placeholder="0.00" min="0" step="0.01" class="costo-monto">
                    </div>
                    <button type="button" class="btn-remove-costo" title="Eliminar">×</button>
                </div>
            `;
            $('#costos-items').append(newItem);
        });

        // Remove cost item
        $(document).on('click', '.btn-remove-costo', function() {
            var $items = $('.costo-item');
            if ($items.length > 1) {
                $(this).closest('.costo-item').remove();
                RTTSeller.calcularCostos();
            }
        });

        // Recalculate on input change
        $(document).on('input change', '.costo-concepto, .costo-monto, #tipo_cambio', function() {
            RTTSeller.calcularCostos();
        });
    };

    /**
     * Auto-fill price when tour is selected
     */
    RTTSeller.initTourSelect = function() {
        $('#tour').on('change', function() {
            var selectedOption = $(this).find('option:selected');
            var price = selectedOption.data('price');
            if (price && parseFloat($('#precio_unitario').val()) == 0) {
                $('#precio_unitario').val(price);
                RTTSeller.calcularTotal();
            }
        });
    };

    /**
     * Initialize form submission
     */
    RTTSeller.initFormSubmit = function() {
        $('#cotizacion-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var submitBtn = form.find('button[type=submit]:focus');
            if (!submitBtn.length) submitBtn = form.find('button[type=submit]:first');
            var action = submitBtn.val() || 'guardar';
            var btnText = submitBtn.html();

            submitBtn.prop('disabled', true).html('<span class="spinner"></span> Procesando...');
            $('#form-message').hide();

            var formData = form.serialize();

            $.post(rttAjax.url, formData + '&action=rtt_save_cotizacion', function(response) {
                if (response.success) {
                    if (action === 'enviar' && response.data.id) {
                        // Send after saving
                        $.post(rttAjax.url, {
                            action: 'rtt_send_cotizacion',
                            id: response.data.id
                        }, function(sendResponse) {
                            if (sendResponse.success) {
                                $('#form-message')
                                    .removeClass('error')
                                    .addClass('success')
                                    .html('<strong>✓ ENVIADO</strong> - Cotización enviada exitosamente a: ' + $('#cliente_email').val())
                                    .show();
                                $('html, body').animate({ scrollTop: $('#form-message').offset().top - 100 }, 500);
                                setTimeout(function() {
                                    window.location.href = rttAjax.dashboardUrl;
                                }, 3000);
                            } else {
                                $('#form-message')
                                    .removeClass('success')
                                    .addClass('error')
                                    .text('Guardada pero error al enviar: ' + sendResponse.data.message)
                                    .show();
                            }
                            submitBtn.prop('disabled', false).html(btnText);
                        });
                    } else {
                        $('#form-message')
                            .removeClass('error')
                            .addClass('success')
                            .html('✓ ' + response.data.message)
                            .show();
                        if (response.data.id) {
                            $('input[name=id]').val(response.data.id);
                            $('.btn-preview-pdf').removeClass('disabled');
                        }
                        submitBtn.prop('disabled', false).html(btnText);
                    }
                } else {
                    $('#form-message')
                        .removeClass('success')
                        .addClass('error')
                        .text(response.data.message)
                        .show();
                    submitBtn.prop('disabled', false).html(btnText);
                }
            }).fail(function() {
                $('#form-message')
                    .removeClass('success')
                    .addClass('error')
                    .text('Error de conexión')
                    .show();
                submitBtn.prop('disabled', false).html(btnText);
            });
        });
    };

    /**
     * Initialize PDF preview button
     */
    RTTSeller.initPreviewPDF = function() {
        $('.btn-preview-pdf').on('click', function() {
            if ($(this).hasClass('disabled')) {
                alert('Primero guarda la cotización para poder previsualizar el PDF');
                return;
            }
            var id = $('input[name=id]').val();
            if (!id || id == '0') {
                alert('Primero guarda la cotización para poder previsualizar el PDF');
                return;
            }
            window.open(rttAjax.url + '?action=rtt_preview_cotizacion_pdf&id=' + id, '_blank');
        });
    };

    /**
     * Initialize delete confirmation
     */
    RTTSeller.initDeleteConfirm = function() {
        $(document).on('click', '.btn-delete-cotizacion', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var codigo = $(this).data('codigo');

            if (confirm('¿Seguro que deseas eliminar la cotización ' + codigo + '?')) {
                $.post(rttAjax.url, {
                    action: 'rtt_delete_cotizacion',
                    id: id
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                });
            }
        });
    };

    /**
     * Initialize login form
     */
    RTTSeller.initLoginForm = function() {
        $('#login-form').on('submit', function(e) {
            e.preventDefault();
            var btn = $(this).find('button');
            btn.prop('disabled', true).text('Ingresando...');
            $('#login-error').hide();

            $.post(rttAjax.url, {
                action: 'rtt_seller_login',
                email: $('#email').val(),
                password: $('#password').val()
            }, function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect;
                } else {
                    $('#login-error').text(response.data.message).show();
                    btn.prop('disabled', false).text('Iniciar Sesión');
                }
            }).fail(function() {
                $('#login-error').text('Error de conexión').show();
                btn.prop('disabled', false).text('Iniciar Sesión');
            });
        });
    };

    /**
     * Initialize provider form
     */
    RTTSeller.initProviderForm = function() {
        // Add provider modal
        $('#btn-add-proveedor').on('click', function() {
            $('#proveedor-form')[0].reset();
            $('input[name=proveedor_id]').val(0);
            $('#modal-proveedor').show();
        });

        // Edit provider
        $(document).on('click', '.btn-edit-proveedor', function() {
            var id = $(this).data('id');
            $.post(rttAjax.url, {
                action: 'rtt_get_proveedores',
                id: id
            }, function(response) {
                if (response.success && response.data.proveedor) {
                    var p = response.data.proveedor;
                    $('input[name=proveedor_id]').val(p.id);
                    $('#proveedor_nombre').val(p.nombre);
                    $('#proveedor_tipo').val(p.tipo);
                    $('#proveedor_telefono').val(p.telefono);
                    $('#proveedor_email').val(p.email);
                    $('#proveedor_notas').val(p.notas);
                    $('#modal-proveedor').show();
                }
            });
        });

        // Close modal
        $('.modal-close, .btn-cancel-modal').on('click', function() {
            $(this).closest('.modal').hide();
        });

        // Save provider
        $('#proveedor-form').on('submit', function(e) {
            e.preventDefault();
            var btn = $(this).find('button[type=submit]');
            btn.prop('disabled', true).text('Guardando...');

            $.post(rttAjax.url, $(this).serialize() + '&action=rtt_save_proveedor', function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    btn.prop('disabled', false).text('Guardar');
                }
            });
        });

        // Delete provider
        $(document).on('click', '.btn-delete-proveedor', function() {
            var id = $(this).data('id');
            if (confirm('¿Seguro que deseas eliminar este proveedor?')) {
                $.post(rttAjax.url, {
                    action: 'rtt_delete_proveedor',
                    id: id
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                });
            }
        });
    };

    /**
     * Initialize configuration form
     */
    RTTSeller.initConfigForm = function() {
        $('#config-form').on('submit', function(e) {
            e.preventDefault();
            var btn = $(this).find('button[type=submit]');
            btn.prop('disabled', true).text('Guardando...');

            $.post(rttAjax.url, $(this).serialize() + '&action=rtt_save_configuracion', function(response) {
                if (response.success) {
                    alert('Configuración guardada');
                } else {
                    alert('Error: ' + response.data.message);
                }
                btn.prop('disabled', false).text('Guardar Configuración');
            });
        });
    };

    /**
     * Document Ready - Initialize all modules
     */
    $(document).ready(function() {
        // Always initialize sidebar
        RTTSeller.initSidebar();

        // Initialize based on page type
        if ($('#login-form').length) {
            RTTSeller.initLoginForm();
        }

        if ($('#cotizacion-form').length) {
            RTTSeller.initTourSelect();
            RTTSeller.initCostosItems();
            RTTSeller.initFormSubmit();
            RTTSeller.initPreviewPDF();

            // Bind calculation events
            $('#cantidad_pasajeros, #precio_unitario, #descuento, #descuento_tipo, #moneda').on('change input', RTTSeller.calcularTotal);

            // Initial calculations
            RTTSeller.calcularTotal();
            RTTSeller.calcularCostos();
        }

        if ($('.btn-delete-cotizacion').length) {
            RTTSeller.initDeleteConfirm();
        }

        // Provider form is handled by inline scripts in render_proveedores()
        // to avoid duplicate handlers

        if ($('#config-form').length) {
            RTTSeller.initConfigForm();
        }
    });

})(jQuery);
