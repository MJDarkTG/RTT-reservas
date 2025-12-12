<?php
/**
 * Clase para la página de Calendario de Tours en el admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_Admin_Calendar {

    /**
     * Inicializar hooks
     */
    public function init() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('wp_ajax_rtt_get_calendar_data', [$this, 'ajax_get_calendar_data']);
        add_action('wp_ajax_rtt_get_day_reservations', [$this, 'ajax_get_day_reservations']);
    }

    /**
     * Agregar página al menú
     */
    public function add_menu_page() {
        add_submenu_page(
            'rtt-reservas',
            __('Calendario de Tours', 'rtt-reservas'),
            __('Calendario', 'rtt-reservas'),
            'manage_options',
            'rtt-calendar',
            [$this, 'render_page']
        );
    }

    /**
     * Renderizar página del calendario
     */
    public function render_page() {
        $tours = RTT_Database::get_tours_list();
        $alerts = RTT_Database::get_upcoming_pending_alerts(7);
        $status_config = rtt_get_status_config();
        ?>
        <div class="wrap rtt-calendar-wrap">
            <h1><?php echo esc_html__('Calendario de Tours', 'rtt-reservas'); ?></h1>

            <?php if (!empty($alerts)): ?>
            <div class="rtt-alerts-panel">
                <h3><span class="dashicons dashicons-warning"></span> <?php echo esc_html__('Alertas: Reservas pendientes próximas', 'rtt-reservas'); ?></h3>
                <div class="rtt-alerts-list">
                    <?php foreach ($alerts as $alert): ?>
                    <div class="rtt-alert-item <?php echo $alert['dias_restantes'] <= 2 ? 'urgent' : ''; ?>">
                        <span class="alert-date">
                            <?php
                            if ($alert['dias_restantes'] == 0) {
                                echo '<strong>' . esc_html__('HOY', 'rtt-reservas') . '</strong>';
                            } elseif ($alert['dias_restantes'] == 1) {
                                echo '<strong>' . esc_html__('MAÑANA', 'rtt-reservas') . '</strong>';
                            } else {
                                echo sprintf(esc_html__('En %d días', 'rtt-reservas'), $alert['dias_restantes']);
                            }
                            ?>
                        </span>
                        <span class="alert-tour"><?php echo esc_html($alert['tour']); ?></span>
                        <span class="alert-code"><?php echo esc_html($alert['codigo']); ?></span>
                        <span class="alert-passengers"><?php echo esc_html($alert['cantidad_pasajeros']); ?> pax</span>
                        <a href="<?php echo admin_url('admin.php?page=rtt-reservas-list&reserva=' . $alert['id']); ?>" class="button button-small">
                            <?php echo esc_html__('Ver', 'rtt-reservas'); ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="rtt-calendar-controls">
                <div class="rtt-calendar-nav">
                    <button type="button" id="rtt-prev" class="button"><span class="dashicons dashicons-arrow-left-alt2"></span></button>
                    <button type="button" id="rtt-today" class="button"><?php echo esc_html__('Hoy', 'rtt-reservas'); ?></button>
                    <button type="button" id="rtt-next" class="button"><span class="dashicons dashicons-arrow-right-alt2"></span></button>
                    <span id="rtt-current-period" class="rtt-period-label"></span>
                </div>

                <div class="rtt-calendar-views">
                    <button type="button" class="button rtt-view-btn active" data-view="month"><?php echo esc_html__('Mes', 'rtt-reservas'); ?></button>
                    <button type="button" class="button rtt-view-btn" data-view="week"><?php echo esc_html__('Semana', 'rtt-reservas'); ?></button>
                    <button type="button" class="button rtt-view-btn" data-view="day"><?php echo esc_html__('Día', 'rtt-reservas'); ?></button>
                </div>

                <div class="rtt-calendar-filter">
                    <select id="rtt-tour-filter">
                        <option value=""><?php echo esc_html__('Todos los tours', 'rtt-reservas'); ?></option>
                        <?php foreach ($tours as $tour): ?>
                        <option value="<?php echo esc_attr($tour); ?>"><?php echo esc_html($tour); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="rtt-calendar-legend">
                <?php foreach ($status_config as $status => $config): ?>
                <span class="legend-item">
                    <span class="legend-color" style="background-color: <?php echo esc_attr($config['color']); ?>"></span>
                    <?php echo esc_html($config['label']); ?>
                </span>
                <?php endforeach; ?>
            </div>

            <div id="rtt-calendar-container" class="rtt-calendar-container">
                <div class="rtt-calendar-loading">
                    <span class="spinner is-active"></span>
                    <?php echo esc_html__('Cargando calendario...', 'rtt-reservas'); ?>
                </div>
            </div>

            <!-- Modal para detalles del día -->
            <div id="rtt-day-modal" class="rtt-modal" style="display: none;">
                <div class="rtt-modal-content">
                    <span class="rtt-modal-close">&times;</span>
                    <h2 id="rtt-modal-title"></h2>
                    <div id="rtt-modal-body"></div>
                </div>
            </div>
        </div>

        <style>
        <?php echo $this->get_calendar_styles(); ?>
        </style>

        <script>
        jQuery(document).ready(function($) {
            var currentDate = new Date();
            var currentView = 'month';
            var currentFilter = '';

            var statusColors = <?php echo json_encode(array_map(function($c) { return $c['color']; }, $status_config)); ?>;
            var statusLabels = <?php echo json_encode(array_map(function($c) { return $c['label']; }, $status_config)); ?>;
            var dayNames = ['<?php echo esc_js(__('Dom', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Lun', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Mar', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Mié', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Jue', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Vie', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Sáb', 'rtt-reservas')); ?>'];
            var monthNames = ['<?php echo esc_js(__('Enero', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Febrero', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Marzo', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Abril', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Mayo', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Junio', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Julio', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Agosto', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Septiembre', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Octubre', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Noviembre', 'rtt-reservas')); ?>', '<?php echo esc_js(__('Diciembre', 'rtt-reservas')); ?>'];

            function loadCalendar() {
                var container = $('#rtt-calendar-container');
                container.html('<div class="rtt-calendar-loading"><span class="spinner is-active"></span> <?php echo esc_js(__('Cargando calendario...', 'rtt-reservas')); ?></div>');

                var dateRange = getDateRange();
                updatePeriodLabel();

                $.post(ajaxurl, {
                    action: 'rtt_get_calendar_data',
                    nonce: '<?php echo wp_create_nonce('rtt_calendar_nonce'); ?>',
                    start_date: dateRange.start,
                    end_date: dateRange.end,
                    tour_filter: currentFilter
                }, function(response) {
                    if (response.success) {
                        renderCalendar(response.data);
                    } else {
                        container.html('<div class="notice notice-error"><p><?php echo esc_js(__('Error al cargar el calendario', 'rtt-reservas')); ?></p></div>');
                    }
                });
            }

            function getDateRange() {
                var start, end;
                if (currentView === 'month') {
                    start = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
                    end = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
                } else if (currentView === 'week') {
                    var dayOfWeek = currentDate.getDay();
                    start = new Date(currentDate);
                    start.setDate(currentDate.getDate() - dayOfWeek);
                    end = new Date(start);
                    end.setDate(start.getDate() + 6);
                } else {
                    start = new Date(currentDate);
                    end = new Date(currentDate);
                }
                return {
                    start: formatDate(start),
                    end: formatDate(end)
                };
            }

            function formatDate(date) {
                return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
            }

            function updatePeriodLabel() {
                var label = '';
                if (currentView === 'month') {
                    label = monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
                } else if (currentView === 'week') {
                    var range = getDateRange();
                    label = '<?php echo esc_js(__('Semana del', 'rtt-reservas')); ?> ' + range.start + ' <?php echo esc_js(__('al', 'rtt-reservas')); ?> ' + range.end;
                } else {
                    label = dayNames[currentDate.getDay()] + ' ' + currentDate.getDate() + ' ' + monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
                }
                $('#rtt-current-period').text(label);
            }

            function renderCalendar(data) {
                var container = $('#rtt-calendar-container');
                var html = '';

                if (currentView === 'month') {
                    html = renderMonthView(data);
                } else if (currentView === 'week') {
                    html = renderWeekView(data);
                } else {
                    html = renderDayView(data);
                }

                container.html(html);
                bindDayClick();
            }

            function renderMonthView(data) {
                var html = '<table class="rtt-calendar-table month-view"><thead><tr>';
                for (var i = 0; i < 7; i++) {
                    html += '<th>' + dayNames[i] + '</th>';
                }
                html += '</tr></thead><tbody>';

                var firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
                var lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
                var startDay = firstDay.getDay();
                var totalDays = lastDay.getDate();

                var day = 1;
                var today = new Date();
                var todayStr = formatDate(today);

                for (var row = 0; row < 6; row++) {
                    html += '<tr>';
                    for (var col = 0; col < 7; col++) {
                        if ((row === 0 && col < startDay) || day > totalDays) {
                            html += '<td class="empty"></td>';
                        } else {
                            var dateStr = currentDate.getFullYear() + '-' + String(currentDate.getMonth() + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                            var dayData = data[dateStr] || null;
                            var isToday = dateStr === todayStr;
                            var cellClass = 'calendar-day' + (isToday ? ' today' : '') + (dayData ? ' has-events' : '');

                            html += '<td class="' + cellClass + '" data-date="' + dateStr + '">';
                            html += '<div class="day-number">' + day + '</div>';

                            if (dayData) {
                                html += '<div class="day-summary">';
                                html += '<span class="total-reservas">' + dayData.total_reservas + ' res.</span>';
                                html += '<span class="total-pax">' + dayData.total_pasajeros + ' pax</span>';
                                html += '</div>';
                                html += renderStatusDots(dayData);
                            }

                            html += '</td>';
                            day++;
                        }
                    }
                    html += '</tr>';
                    if (day > totalDays) break;
                }

                html += '</tbody></table>';
                return html;
            }

            function renderWeekView(data) {
                var html = '<table class="rtt-calendar-table week-view"><thead><tr>';
                var range = getDateRange();
                var startDate = new Date(range.start);

                for (var i = 0; i < 7; i++) {
                    var d = new Date(startDate);
                    d.setDate(startDate.getDate() + i);
                    html += '<th>' + dayNames[d.getDay()] + '<br><small>' + d.getDate() + '/' + (d.getMonth() + 1) + '</small></th>';
                }
                html += '</tr></thead><tbody><tr>';

                var today = new Date();
                var todayStr = formatDate(today);

                for (var i = 0; i < 7; i++) {
                    var d = new Date(startDate);
                    d.setDate(startDate.getDate() + i);
                    var dateStr = formatDate(d);
                    var dayData = data[dateStr] || null;
                    var isToday = dateStr === todayStr;
                    var cellClass = 'calendar-day week-cell' + (isToday ? ' today' : '') + (dayData ? ' has-events' : '');

                    html += '<td class="' + cellClass + '" data-date="' + dateStr + '">';

                    if (dayData) {
                        html += '<div class="week-events">';
                        html += '<div class="event-count"><strong>' + dayData.total_reservas + '</strong> <?php echo esc_js(__('reservas', 'rtt-reservas')); ?></div>';
                        html += '<div class="event-pax"><strong>' + dayData.total_pasajeros + '</strong> <?php echo esc_js(__('pasajeros', 'rtt-reservas')); ?></div>';
                        html += renderStatusBars(dayData);
                        if (dayData.tours) {
                            var tours = dayData.tours.split('|').slice(0, 3);
                            html += '<div class="tour-list">';
                            tours.forEach(function(t) {
                                html += '<div class="tour-name">' + t.substring(0, 25) + (t.length > 25 ? '...' : '') + '</div>';
                            });
                            html += '</div>';
                        }
                        html += '</div>';
                    } else {
                        html += '<div class="no-events"><?php echo esc_js(__('Sin reservas', 'rtt-reservas')); ?></div>';
                    }

                    html += '</td>';
                }

                html += '</tr></tbody></table>';
                return html;
            }

            function renderDayView(data) {
                var dateStr = formatDate(currentDate);
                var dayData = data[dateStr] || null;

                var html = '<div class="day-view-container">';
                html += '<h3>' + dayNames[currentDate.getDay()] + ' ' + currentDate.getDate() + ' ' + monthNames[currentDate.getMonth()] + '</h3>';

                if (dayData) {
                    html += '<div class="day-stats">';
                    html += '<div class="stat-box"><span class="stat-number">' + dayData.total_reservas + '</span><span class="stat-label"><?php echo esc_js(__('Reservas', 'rtt-reservas')); ?></span></div>';
                    html += '<div class="stat-box"><span class="stat-number">' + dayData.total_pasajeros + '</span><span class="stat-label"><?php echo esc_js(__('Pasajeros', 'rtt-reservas')); ?></span></div>';
                    html += '</div>';
                    html += '<div class="day-status-breakdown">';
                    html += renderStatusBreakdown(dayData);
                    html += '</div>';
                    html += '<button type="button" class="button button-primary view-day-details" data-date="' + dateStr + '"><?php echo esc_js(__('Ver detalles', 'rtt-reservas')); ?></button>';
                } else {
                    html += '<div class="no-events-message"><?php echo esc_js(__('No hay reservas para este día', 'rtt-reservas')); ?></div>';
                }

                html += '</div>';
                return html;
            }

            function renderStatusDots(dayData) {
                var html = '<div class="status-dots">';
                if (dayData.pendientes > 0) html += '<span class="dot" style="background:' + statusColors['pendiente'] + '" title="' + dayData.pendientes + ' pendientes"></span>';
                if (dayData.confirmadas > 0) html += '<span class="dot" style="background:' + statusColors['confirmada'] + '" title="' + dayData.confirmadas + ' confirmadas"></span>';
                if (dayData.pagadas > 0) html += '<span class="dot" style="background:' + statusColors['pagada'] + '" title="' + dayData.pagadas + ' pagadas"></span>';
                if (dayData.completadas > 0) html += '<span class="dot" style="background:' + statusColors['completada'] + '" title="' + dayData.completadas + ' completadas"></span>';
                html += '</div>';
                return html;
            }

            function renderStatusBars(dayData) {
                var total = parseInt(dayData.total_reservas);
                var html = '<div class="status-bars">';
                ['pendiente', 'confirmada', 'pagada', 'completada'].forEach(function(status) {
                    var count = parseInt(dayData[status + 's'] || 0);
                    if (count > 0) {
                        var pct = Math.round((count / total) * 100);
                        html += '<div class="status-bar" style="width:' + pct + '%;background:' + statusColors[status] + '" title="' + count + ' ' + statusLabels[status] + '"></div>';
                    }
                });
                html += '</div>';
                return html;
            }

            function renderStatusBreakdown(dayData) {
                var html = '<div class="status-grid">';
                ['pendiente', 'confirmada', 'pagada', 'completada', 'cancelada'].forEach(function(status) {
                    var count = parseInt(dayData[status + 's'] || 0);
                    html += '<div class="status-item" style="border-left: 4px solid ' + statusColors[status] + '">';
                    html += '<span class="status-count">' + count + '</span>';
                    html += '<span class="status-name">' + statusLabels[status] + '</span>';
                    html += '</div>';
                });
                html += '</div>';
                return html;
            }

            function bindDayClick() {
                $('.calendar-day.has-events, .view-day-details').on('click', function() {
                    var date = $(this).data('date');
                    showDayModal(date);
                });
            }

            function showDayModal(date) {
                var modal = $('#rtt-day-modal');
                var modalBody = $('#rtt-modal-body');
                modalBody.html('<div class="rtt-calendar-loading"><span class="spinner is-active"></span></div>');

                var dateObj = new Date(date + 'T00:00:00');
                $('#rtt-modal-title').text(dayNames[dateObj.getDay()] + ' ' + dateObj.getDate() + ' ' + monthNames[dateObj.getMonth()] + ' ' + dateObj.getFullYear());

                modal.show();

                $.post(ajaxurl, {
                    action: 'rtt_get_day_reservations',
                    nonce: '<?php echo wp_create_nonce('rtt_calendar_nonce'); ?>',
                    date: date,
                    tour_filter: currentFilter
                }, function(response) {
                    if (response.success) {
                        var html = '<table class="widefat striped"><thead><tr>';
                        html += '<th><?php echo esc_js(__('Código', 'rtt-reservas')); ?></th>';
                        html += '<th><?php echo esc_js(__('Tour', 'rtt-reservas')); ?></th>';
                        html += '<th><?php echo esc_js(__('Cliente', 'rtt-reservas')); ?></th>';
                        html += '<th><?php echo esc_js(__('Pax', 'rtt-reservas')); ?></th>';
                        html += '<th><?php echo esc_js(__('Estado', 'rtt-reservas')); ?></th>';
                        html += '<th><?php echo esc_js(__('Acciones', 'rtt-reservas')); ?></th>';
                        html += '</tr></thead><tbody>';

                        response.data.forEach(function(r) {
                            html += '<tr>';
                            html += '<td><strong>' + r.codigo + '</strong></td>';
                            html += '<td>' + r.tour.substring(0, 40) + (r.tour.length > 40 ? '...' : '') + '</td>';
                            html += '<td>' + r.nombre_representante + '<br><small>' + r.email + '</small></td>';
                            html += '<td>' + r.cantidad_pasajeros + '</td>';
                            html += '<td><span class="rtt-status" style="background:' + statusColors[r.estado] + '">' + statusLabels[r.estado] + '</span></td>';
                            html += '<td><a href="<?php echo admin_url('admin.php?page=rtt-reservas-list&reserva='); ?>' + r.id + '" class="button button-small"><?php echo esc_js(__('Ver', 'rtt-reservas')); ?></a></td>';
                            html += '</tr>';
                        });

                        html += '</tbody></table>';
                        modalBody.html(html);
                    }
                });
            }

            // Event handlers
            $('#rtt-prev').on('click', function() {
                if (currentView === 'month') {
                    currentDate.setMonth(currentDate.getMonth() - 1);
                } else if (currentView === 'week') {
                    currentDate.setDate(currentDate.getDate() - 7);
                } else {
                    currentDate.setDate(currentDate.getDate() - 1);
                }
                loadCalendar();
            });

            $('#rtt-next').on('click', function() {
                if (currentView === 'month') {
                    currentDate.setMonth(currentDate.getMonth() + 1);
                } else if (currentView === 'week') {
                    currentDate.setDate(currentDate.getDate() + 7);
                } else {
                    currentDate.setDate(currentDate.getDate() + 1);
                }
                loadCalendar();
            });

            $('#rtt-today').on('click', function() {
                currentDate = new Date();
                loadCalendar();
            });

            $('.rtt-view-btn').on('click', function() {
                $('.rtt-view-btn').removeClass('active');
                $(this).addClass('active');
                currentView = $(this).data('view');
                loadCalendar();
            });

            $('#rtt-tour-filter').on('change', function() {
                currentFilter = $(this).val();
                loadCalendar();
            });

            $('.rtt-modal-close').on('click', function() {
                $('#rtt-day-modal').hide();
            });

            $(window).on('click', function(e) {
                if ($(e.target).is('#rtt-day-modal')) {
                    $('#rtt-day-modal').hide();
                }
            });

            // Cargar calendario inicial
            loadCalendar();
        });
        </script>
        <?php
    }

    /**
     * AJAX: Obtener datos del calendario
     */
    public function ajax_get_calendar_data() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rtt_calendar_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');
        $tour_filter = sanitize_text_field($_POST['tour_filter'] ?? '');

        if (empty($start_date) || empty($end_date)) {
            wp_send_json_error(['message' => 'Invalid dates']);
        }

        $summary = RTT_Database::get_calendar_summary($start_date, $end_date);

        // Convertir a formato indexado por fecha
        $data = [];
        foreach ($summary as $day) {
            $data[$day['fecha_tour']] = $day;
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX: Obtener reservas de un día específico
     */
    public function ajax_get_day_reservations() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rtt_calendar_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $date = sanitize_text_field($_POST['date'] ?? '');
        $tour_filter = sanitize_text_field($_POST['tour_filter'] ?? '');

        if (empty($date)) {
            wp_send_json_error(['message' => 'Invalid date']);
        }

        $reservations = RTT_Database::get_reservas_for_calendar($date, $date, $tour_filter);

        wp_send_json_success($reservations);
    }

    /**
     * Estilos CSS del calendario
     */
    private function get_calendar_styles() {
        return '
        .rtt-calendar-wrap { max-width: 1200px; }

        .rtt-alerts-panel {
            background: #fff8e5;
            border: 1px solid #f0ad4e;
            border-left: 4px solid #f0ad4e;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .rtt-alerts-panel h3 {
            margin: 0 0 10px;
            color: #856404;
        }
        .rtt-alerts-panel h3 .dashicons {
            color: #f0ad4e;
            margin-right: 5px;
        }
        .rtt-alert-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 8px 0;
            border-bottom: 1px solid #f0d78e;
        }
        .rtt-alert-item:last-child { border-bottom: none; }
        .rtt-alert-item.urgent { background: #fff0f0; margin: 0 -10px; padding: 8px 10px; }
        .rtt-alert-item .alert-date {
            min-width: 80px;
            font-size: 12px;
            color: #666;
        }
        .rtt-alert-item.urgent .alert-date { color: #d9534f; }
        .rtt-alert-item .alert-tour { flex: 1; font-weight: 500; }
        .rtt-alert-item .alert-code { color: #666; font-family: monospace; }
        .rtt-alert-item .alert-passengers {
            background: #004070;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }

        .rtt-calendar-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .rtt-calendar-nav {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .rtt-period-label {
            font-size: 18px;
            font-weight: 600;
            margin-left: 15px;
            color: #004070;
        }
        .rtt-calendar-views { display: flex; gap: 5px; }
        .rtt-view-btn.active {
            background: #004070;
            color: white;
            border-color: #004070;
        }
        .rtt-calendar-filter select {
            min-width: 200px;
            padding: 5px 10px;
        }

        .rtt-calendar-legend {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            padding: 10px 15px;
            background: #f5f5f5;
            border-radius: 4px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
        }
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }

        .rtt-calendar-container {
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
            overflow: hidden;
        }
        .rtt-calendar-loading {
            padding: 50px;
            text-align: center;
            color: #666;
        }
        .rtt-calendar-loading .spinner {
            float: none;
            margin: 0 10px 0 0;
        }

        .rtt-calendar-table {
            width: 100%;
            border-collapse: collapse;
        }
        .rtt-calendar-table th {
            background: #004070;
            color: white;
            padding: 12px;
            text-align: center;
            font-weight: 500;
        }
        .rtt-calendar-table td {
            border: 1px solid #e0e0e0;
            vertical-align: top;
            height: 100px;
            width: 14.28%;
        }
        .rtt-calendar-table.month-view td { padding: 5px; }
        .rtt-calendar-table td.empty { background: #f9f9f9; }

        .calendar-day { cursor: pointer; transition: background 0.2s; }
        .calendar-day:hover { background: #f0f8ff; }
        .calendar-day.today { background: #e8f4ff; }
        .calendar-day.today .day-number {
            background: #004070;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .day-number {
            font-weight: 600;
            font-size: 14px;
            color: #333;
            margin-bottom: 5px;
        }
        .day-summary {
            font-size: 11px;
            color: #666;
            margin-bottom: 5px;
        }
        .day-summary .total-reservas {
            background: #004070;
            color: white;
            padding: 1px 5px;
            border-radius: 3px;
            margin-right: 5px;
        }
        .day-summary .total-pax {
            background: #27ae60;
            color: white;
            padding: 1px 5px;
            border-radius: 3px;
        }

        .status-dots {
            display: flex;
            gap: 3px;
            margin-top: 5px;
        }
        .status-dots .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        /* Week view */
        .week-view td { height: 200px; padding: 10px; }
        .week-cell .week-events { font-size: 13px; }
        .week-cell .event-count,
        .week-cell .event-pax {
            margin-bottom: 5px;
        }
        .week-cell .tour-list {
            margin-top: 10px;
            font-size: 11px;
            color: #666;
        }
        .week-cell .tour-name {
            padding: 2px 0;
            border-bottom: 1px dotted #ddd;
        }
        .status-bars {
            display: flex;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }
        .status-bar { height: 100%; }
        .no-events {
            color: #999;
            font-style: italic;
            text-align: center;
            padding-top: 50px;
        }

        /* Day view */
        .day-view-container {
            padding: 30px;
            text-align: center;
        }
        .day-view-container h3 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #004070;
        }
        .day-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: #f5f5f5;
            padding: 20px 40px;
            border-radius: 8px;
        }
        .stat-number {
            display: block;
            font-size: 36px;
            font-weight: 700;
            color: #004070;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        .status-grid {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .status-item {
            background: #f9f9f9;
            padding: 10px 20px;
            text-align: left;
        }
        .status-count {
            font-size: 20px;
            font-weight: 600;
            display: block;
        }
        .status-name {
            font-size: 12px;
            color: #666;
        }
        .no-events-message {
            color: #999;
            font-size: 18px;
            padding: 50px;
        }

        /* Modal */
        .rtt-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .rtt-modal-content {
            background: white;
            padding: 20px 30px;
            border-radius: 8px;
            max-width: 900px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }
        .rtt-modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 28px;
            cursor: pointer;
            color: #666;
        }
        .rtt-modal-close:hover { color: #000; }
        .rtt-modal h2 {
            margin-top: 0;
            color: #004070;
            padding-right: 30px;
        }
        .rtt-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 3px;
            color: white;
            font-size: 12px;
        }
        ';
    }
}
