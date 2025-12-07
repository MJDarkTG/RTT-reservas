/**
 * RTT Reservas - JavaScript del panel de administración
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initEstadoChange();
        initDeleteReserva();
        initViewDetail();
        initModal();
        initExportToggle();
        initLazyLoadPasajeros();
    });

    /**
     * Toggle de opciones de exportación
     */
    function initExportToggle() {
        $('#rtt-toggle-export').on('click', function() {
            $('#rtt-export-options').slideToggle(200);
        });
    }

    /**
     * Cambio de estado de reserva
     */
    function initEstadoChange() {
        $('.rtt-estado-select').on('change', function() {
            var $select = $(this);
            var id = $select.data('id');
            var estado = $select.val();

            $select.prop('disabled', true);

            $.post(rttAdminReservas.ajaxUrl, {
                action: 'rtt_update_estado',
                nonce: rttAdminReservas.nonce,
                id: id,
                estado: estado
            }, function(response) {
                $select.prop('disabled', false);

                if (response.success) {
                    showNotice(rttAdminReservas.i18n.updated, 'success');
                    updateEstadoColor($select, estado);
                } else {
                    showNotice(response.data.message || rttAdminReservas.i18n.error, 'error');
                }
            }).fail(function() {
                $select.prop('disabled', false);
                showNotice(rttAdminReservas.i18n.error, 'error');
            });
        });
    }

    /**
     * Actualizar color del estado
     */
    function updateEstadoColor($select, estado) {
        var colors = {
            'pendiente': '#f0ad4e',
            'confirmada': '#5bc0de',
            'pagada': '#5cb85c',
            'completada': '#004070',
            'cancelada': '#d9534f'
        };
        $select.css('border-left-color', colors[estado] || '#ccc');
    }

    /**
     * Eliminar reserva
     */
    function initDeleteReserva() {
        $('.rtt-delete-reserva').on('click', function(e) {
            e.preventDefault();

            if (!confirm(rttAdminReservas.i18n.confirmDelete)) {
                return;
            }

            var $link = $(this);
            var $row = $link.closest('tr');
            var id = $link.data('id');

            $row.css('opacity', '0.5');

            $.post(rttAdminReservas.ajaxUrl, {
                action: 'rtt_delete_reserva',
                nonce: rttAdminReservas.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                    showNotice(rttAdminReservas.i18n.deleted, 'success');
                } else {
                    $row.css('opacity', '1');
                    showNotice(response.data.message || rttAdminReservas.i18n.error, 'error');
                }
            }).fail(function() {
                $row.css('opacity', '1');
                showNotice(rttAdminReservas.i18n.error, 'error');
            });
        });
    }

    /**
     * Ver detalle de reserva
     */
    function initViewDetail() {
        $('.rtt-view-detail').on('click', function(e) {
            e.preventDefault();

            var id = $(this).data('id');
            var $modal = $('#rtt-modal-detail');
            var $body = $('#rtt-modal-body');

            $body.html('<p style="text-align: center; padding: 40px;">Cargando...</p>');
            $modal.show();

            $.get(rttAdminReservas.ajaxUrl, {
                action: 'rtt_get_reserva_detail',
                nonce: rttAdminReservas.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    $body.html(response.data.html);
                } else {
                    $body.html('<p style="text-align: center; color: red;">' + (response.data.message || 'Error') + '</p>');
                }
            }).fail(function() {
                $body.html('<p style="text-align: center; color: red;">Error al cargar</p>');
            });
        });
    }

    /**
     * Inicializar modal
     */
    function initModal() {
        var $modal = $('#rtt-modal-detail');

        // Cerrar con X
        $('.rtt-modal-close').on('click', function() {
            $modal.hide();
        });

        // Cerrar al hacer clic fuera
        $modal.on('click', function(e) {
            if (e.target === this) {
                $modal.hide();
            }
        });

        // Cerrar con ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $modal.hide();
            }
        });
    }

    /**
     * Mostrar notificación
     */
    function showNotice(message, type) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

        $('.wrap h1').first().after($notice);

        // Auto-cerrar después de 3 segundos
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    /**
     * Lazy loading de pasajeros en el modal
     */
    function initLazyLoadPasajeros() {
        // Usar delegación de eventos porque el botón se carga dinámicamente
        $(document).on('click', '.rtt-load-more-pasajeros', function() {
            var $btn = $(this);
            var reservaId = $btn.data('reserva-id');
            var offset = $btn.data('offset');
            var total = $btn.data('total');

            $btn.prop('disabled', true).text('Cargando...');

            $.get(rttAdminReservas.ajaxUrl, {
                action: 'rtt_get_pasajeros',
                nonce: rttAdminReservas.nonce,
                reserva_id: reservaId,
                offset: offset,
                limit: 10
            }, function(response) {
                if (response.success) {
                    // Agregar filas a la tabla
                    $('#rtt-pasajeros-tbody').append(response.data.html);

                    // Actualizar contador
                    $('#rtt-pasajeros-loaded').text(response.data.loaded);

                    if (response.data.has_more) {
                        // Actualizar botón
                        var remaining = total - response.data.loaded;
                        $btn.data('offset', response.data.loaded);
                        $btn.prop('disabled', false).text('Cargar más (' + remaining + ' restantes)');
                    } else {
                        // Ocultar botón
                        $btn.parent().remove();
                    }
                } else {
                    $btn.prop('disabled', false).text('Error - Reintentar');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('Error - Reintentar');
            });
        });
    }

})(jQuery);
