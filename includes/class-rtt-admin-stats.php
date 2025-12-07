<?php
/**
 * Clase para estadísticas y gráficas del admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_Admin_Stats {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_submenu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_rtt_get_stats', [$this, 'ajax_get_stats']);
        add_action('wp_ajax_rtt_clear_security_log', [$this, 'ajax_clear_security_log']);
    }

    /**
     * Agregar submenú de estadísticas
     */
    public function add_submenu() {
        add_submenu_page(
            'rtt-reservas',
            __('Estadísticas', 'rtt-reservas'),
            __('Estadísticas', 'rtt-reservas'),
            'manage_options',
            'rtt-estadisticas',
            [$this, 'render_page']
        );

        add_submenu_page(
            'rtt-reservas',
            __('Log de Seguridad', 'rtt-reservas'),
            __('Log de Seguridad', 'rtt-reservas'),
            'manage_options',
            'rtt-security-log',
            [$this, 'render_security_page']
        );
    }

    /**
     * Cargar scripts y estilos
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'rtt-reservas_page_rtt-estadisticas') {
            return;
        }

        // Chart.js desde CDN
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            [],
            '4.4.1',
            true
        );

        // Nuestro script de estadísticas
        wp_enqueue_script(
            'rtt-admin-stats',
            RTT_RESERVAS_PLUGIN_URL . 'assets/js/admin-stats.js',
            ['jquery', 'chartjs'],
            RTT_RESERVAS_VERSION,
            true
        );

        wp_localize_script('rtt-admin-stats', 'rttStats', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rtt_stats_nonce'),
            'i18n' => [
                'reservations' => __('Reservas', 'rtt-reservas'),
                'passengers' => __('Pasajeros', 'rtt-reservas'),
                'countries' => __('Países', 'rtt-reservas'),
                'tours' => __('Tours', 'rtt-reservas'),
                'noData' => __('No hay datos disponibles', 'rtt-reservas'),
            ]
        ]);
    }

    /**
     * Obtener estadísticas vía AJAX
     */
    public function ajax_get_stats() {
        check_ajax_referer('rtt_stats_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sin permisos']);
        }

        $period = sanitize_text_field($_POST['period'] ?? '30');

        $stats = [
            'summary' => $this->get_summary_stats($period),
            'byCountry' => $this->get_stats_by_country($period),
            'byTour' => $this->get_stats_by_tour($period),
            'byMonth' => $this->get_stats_by_month(),
            'byStatus' => $this->get_stats_by_status($period),
            'timeline' => $this->get_timeline_stats($period),
            'topCountries' => $this->get_top_countries($period),
            'recentReservations' => $this->get_recent_reservations(),
        ];

        wp_send_json_success($stats);
    }

    /**
     * Resumen general
     */
    private function get_summary_stats($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_reservas';
        $table_pasajeros = $wpdb->prefix . 'rtt_pasajeros';

        $date_filter = $days > 0 ? $wpdb->prepare("AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days) : "";

        // Total reservas
        $total_reservas = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE 1=1 {$date_filter}");

        // Total pasajeros
        $total_pasajeros = $wpdb->get_var("
            SELECT COALESCE(SUM(r.cantidad_pasajeros), 0)
            FROM {$table} r
            WHERE 1=1 {$date_filter}
        ");

        // Reservas confirmadas
        $confirmadas = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE estado = %s {$date_filter}",
            'confirmada'
        ));

        // Reservas pendientes
        $pendientes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE estado = %s {$date_filter}",
            'pendiente'
        ));

        // Países únicos
        $paises_unicos = $wpdb->get_var("SELECT COUNT(DISTINCT pais) FROM {$table} WHERE pais != '' {$date_filter}");

        // Tours únicos
        $tours_unicos = $wpdb->get_var("SELECT COUNT(DISTINCT tour) FROM {$table} WHERE 1=1 {$date_filter}");

        // Comparación con período anterior
        $prev_date_filter = $days > 0 ? $wpdb->prepare(
            "AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL %d DAY) AND fecha_creacion < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days * 2, $days
        ) : "";

        $prev_reservas = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE 1=1 {$prev_date_filter}") ?: 1;
        $growth = $prev_reservas > 0 ? round((($total_reservas - $prev_reservas) / $prev_reservas) * 100, 1) : 0;

        return [
            'totalReservas' => (int)$total_reservas,
            'totalPasajeros' => (int)$total_pasajeros,
            'confirmadas' => (int)$confirmadas,
            'pendientes' => (int)$pendientes,
            'paisesUnicos' => (int)$paises_unicos,
            'toursUnicos' => (int)$tours_unicos,
            'growth' => $growth,
        ];
    }

    /**
     * Estadísticas por país
     */
    private function get_stats_by_country($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_reservas';

        $date_filter = $days > 0 ? $wpdb->prepare("AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days) : "";

        $results = $wpdb->get_results("
            SELECT
                pais,
                COUNT(*) as total,
                SUM(cantidad_pasajeros) as pasajeros
            FROM {$table}
            WHERE pais != '' {$date_filter}
            GROUP BY pais
            ORDER BY total DESC
            LIMIT 15
        ", ARRAY_A);

        return $results ?: [];
    }

    /**
     * Estadísticas por tour
     */
    private function get_stats_by_tour($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_reservas';

        $date_filter = $days > 0 ? $wpdb->prepare("AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days) : "";

        $results = $wpdb->get_results("
            SELECT
                tour,
                COUNT(*) as total,
                SUM(cantidad_pasajeros) as pasajeros
            FROM {$table}
            WHERE tour != '' {$date_filter}
            GROUP BY tour
            ORDER BY total DESC
            LIMIT 10
        ", ARRAY_A);

        return $results ?: [];
    }

    /**
     * Estadísticas por mes (últimos 12 meses)
     */
    private function get_stats_by_month() {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_reservas';

        $results = $wpdb->get_results("
            SELECT
                DATE_FORMAT(fecha_creacion, '%Y-%m') as mes,
                DATE_FORMAT(fecha_creacion, '%b %Y') as mes_label,
                COUNT(*) as total,
                SUM(cantidad_pasajeros) as pasajeros
            FROM {$table}
            WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY mes
            ORDER BY mes ASC
        ", ARRAY_A);

        return $results ?: [];
    }

    /**
     * Estadísticas por estado
     */
    private function get_stats_by_status($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_reservas';

        $date_filter = $days > 0 ? $wpdb->prepare("AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days) : "";

        $results = $wpdb->get_results("
            SELECT
                estado,
                COUNT(*) as total
            FROM {$table}
            WHERE 1=1 {$date_filter}
            GROUP BY estado
        ", ARRAY_A);

        return $results ?: [];
    }

    /**
     * Timeline de reservas (últimos N días)
     */
    private function get_timeline_stats($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_reservas';

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT
                DATE(fecha_creacion) as fecha,
                COUNT(*) as total,
                SUM(cantidad_pasajeros) as pasajeros
            FROM {$table}
            WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY DATE(fecha_creacion)
            ORDER BY fecha ASC
        ", $days), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Top países con más reservas
     */
    private function get_top_countries($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_reservas';

        $date_filter = $days > 0 ? $wpdb->prepare("AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days) : "";

        $results = $wpdb->get_results("
            SELECT
                pais,
                COUNT(*) as reservas,
                SUM(cantidad_pasajeros) as pasajeros
            FROM {$table}
            WHERE pais != '' {$date_filter}
            GROUP BY pais
            ORDER BY reservas DESC
            LIMIT 5
        ", ARRAY_A);

        return $results ?: [];
    }

    /**
     * Reservas recientes
     */
    private function get_recent_reservations() {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_reservas';

        $results = $wpdb->get_results("
            SELECT
                codigo,
                tour,
                nombre_representante,
                pais,
                cantidad_pasajeros,
                estado,
                fecha_creacion
            FROM {$table}
            ORDER BY fecha_creacion DESC
            LIMIT 5
        ", ARRAY_A);

        return $results ?: [];
    }

    /**
     * Renderizar página de estadísticas
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap rtt-stats-wrap">
            <h1 class="rtt-stats-title">
                <span class="dashicons dashicons-chart-area"></span>
                <?php _e('Estadísticas de Reservas', 'rtt-reservas'); ?>
            </h1>

            <!-- Filtros -->
            <div class="rtt-stats-filters">
                <label for="rtt-period"><?php _e('Período:', 'rtt-reservas'); ?></label>
                <select id="rtt-period" class="rtt-period-select">
                    <option value="7"><?php _e('Últimos 7 días', 'rtt-reservas'); ?></option>
                    <option value="30" selected><?php _e('Últimos 30 días', 'rtt-reservas'); ?></option>
                    <option value="90"><?php _e('Últimos 90 días', 'rtt-reservas'); ?></option>
                    <option value="365"><?php _e('Último año', 'rtt-reservas'); ?></option>
                    <option value="0"><?php _e('Todo el tiempo', 'rtt-reservas'); ?></option>
                </select>
                <button type="button" id="rtt-refresh-stats" class="button">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Actualizar', 'rtt-reservas'); ?>
                </button>
            </div>

            <!-- Tarjetas de resumen -->
            <div class="rtt-stats-cards">
                <div class="rtt-stat-card rtt-stat-primary">
                    <div class="rtt-stat-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="rtt-stat-content">
                        <h3 id="stat-total-reservas">-</h3>
                        <p><?php _e('Total Reservas', 'rtt-reservas'); ?></p>
                    </div>
                    <div class="rtt-stat-growth" id="stat-growth"></div>
                </div>

                <div class="rtt-stat-card rtt-stat-success">
                    <div class="rtt-stat-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="rtt-stat-content">
                        <h3 id="stat-total-pasajeros">-</h3>
                        <p><?php _e('Total Pasajeros', 'rtt-reservas'); ?></p>
                    </div>
                </div>

                <div class="rtt-stat-card rtt-stat-warning">
                    <div class="rtt-stat-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="rtt-stat-content">
                        <h3 id="stat-pendientes">-</h3>
                        <p><?php _e('Pendientes', 'rtt-reservas'); ?></p>
                    </div>
                </div>

                <div class="rtt-stat-card rtt-stat-info">
                    <div class="rtt-stat-icon">
                        <span class="dashicons dashicons-admin-site-alt3"></span>
                    </div>
                    <div class="rtt-stat-content">
                        <h3 id="stat-paises">-</h3>
                        <p><?php _e('Países', 'rtt-reservas'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Gráficas principales -->
            <div class="rtt-stats-row">
                <div class="rtt-stats-chart-container rtt-stats-chart-large">
                    <h3>
                        <span class="dashicons dashicons-chart-bar" style="color:#3b82f6;margin-right:8px;"></span>
                        <?php _e('Actividad Diaria: Reservas y Pasajeros', 'rtt-reservas'); ?>
                    </h3>
                    <p style="color:#64748b;font-size:13px;margin:-10px 0 15px;">
                        <?php _e('Comparativa de reservas (azul) vs pasajeros (verde) por cada día del período seleccionado', 'rtt-reservas'); ?>
                    </p>
                    <canvas id="chart-timeline"></canvas>
                </div>
            </div>

            <div class="rtt-stats-row">
                <div class="rtt-stats-chart-container">
                    <h3><?php _e('Top Países', 'rtt-reservas'); ?></h3>
                    <canvas id="chart-countries"></canvas>
                </div>

                <div class="rtt-stats-chart-container">
                    <h3><?php _e('Tours Más Populares', 'rtt-reservas'); ?></h3>
                    <canvas id="chart-tours"></canvas>
                </div>
            </div>

            <div class="rtt-stats-row">
                <div class="rtt-stats-chart-container">
                    <h3><?php _e('Estado de Reservas', 'rtt-reservas'); ?></h3>
                    <canvas id="chart-status"></canvas>
                </div>

                <div class="rtt-stats-chart-container">
                    <h3><?php _e('Reservas por Mes', 'rtt-reservas'); ?></h3>
                    <canvas id="chart-monthly"></canvas>
                </div>
            </div>

            <!-- Top países lista -->
            <div class="rtt-stats-row">
                <div class="rtt-stats-table-container">
                    <h3><?php _e('Ranking de Países', 'rtt-reservas'); ?></h3>
                    <table class="rtt-stats-table" id="table-countries">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?php _e('País', 'rtt-reservas'); ?></th>
                                <th><?php _e('Reservas', 'rtt-reservas'); ?></th>
                                <th><?php _e('Pasajeros', 'rtt-reservas'); ?></th>
                                <th><?php _e('Porcentaje', 'rtt-reservas'); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div class="rtt-stats-table-container">
                    <h3><?php _e('Últimas Reservas', 'rtt-reservas'); ?></h3>
                    <table class="rtt-stats-table" id="table-recent">
                        <thead>
                            <tr>
                                <th><?php _e('Código', 'rtt-reservas'); ?></th>
                                <th><?php _e('Tour', 'rtt-reservas'); ?></th>
                                <th><?php _e('Cliente', 'rtt-reservas'); ?></th>
                                <th><?php _e('Estado', 'rtt-reservas'); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Loading overlay -->
            <div class="rtt-stats-loading" id="rtt-loading">
                <div class="rtt-stats-spinner"></div>
                <p><?php _e('Cargando estadísticas...', 'rtt-reservas'); ?></p>
            </div>
        </div>

        <style>
        .rtt-stats-wrap {
            padding: 20px;
            max-width: 1600px;
        }

        .rtt-stats-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 28px;
            color: #1d2327;
            margin-bottom: 25px;
        }

        .rtt-stats-title .dashicons {
            font-size: 32px;
            width: 32px;
            height: 32px;
            color: #2db742;
        }

        /* Filtros */
        .rtt-stats-filters {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding: 15px 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .rtt-period-select {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            min-width: 180px;
        }

        .rtt-stats-filters .button {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 16px;
            border-radius: 8px;
        }

        /* Tarjetas de estadísticas */
        .rtt-stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .rtt-stat-card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .rtt-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .rtt-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .rtt-stat-primary::before { background: #3b82f6; }
        .rtt-stat-success::before { background: #2db742; }
        .rtt-stat-warning::before { background: #f59e0b; }
        .rtt-stat-info::before { background: #8b5cf6; }

        .rtt-stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .rtt-stat-primary .rtt-stat-icon { background: #eff6ff; }
        .rtt-stat-success .rtt-stat-icon { background: #f0fdf4; }
        .rtt-stat-warning .rtt-stat-icon { background: #fffbeb; }
        .rtt-stat-info .rtt-stat-icon { background: #f5f3ff; }

        .rtt-stat-primary .rtt-stat-icon .dashicons { color: #3b82f6; }
        .rtt-stat-success .rtt-stat-icon .dashicons { color: #2db742; }
        .rtt-stat-warning .rtt-stat-icon .dashicons { color: #f59e0b; }
        .rtt-stat-info .rtt-stat-icon .dashicons { color: #8b5cf6; }

        .rtt-stat-icon .dashicons {
            font-size: 28px;
            width: 28px;
            height: 28px;
        }

        .rtt-stat-content h3 {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
            color: #1e293b;
        }

        .rtt-stat-content p {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 14px;
        }

        .rtt-stat-growth {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .rtt-stat-growth.positive {
            background: #dcfce7;
            color: #16a34a;
        }

        .rtt-stat-growth.negative {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Filas de gráficas */
        .rtt-stats-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }

        .rtt-stats-chart-large {
            grid-column: span 2;
        }

        .rtt-stats-chart-container,
        .rtt-stats-table-container {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .rtt-stats-chart-container h3,
        .rtt-stats-table-container h3 {
            margin: 0 0 20px;
            font-size: 18px;
            color: #1e293b;
            font-weight: 600;
        }

        .rtt-stats-chart-container canvas {
            max-height: 350px;
        }

        .rtt-stats-chart-large canvas {
            max-height: 300px;
        }

        /* Tablas */
        .rtt-stats-table {
            width: 100%;
            border-collapse: collapse;
        }

        .rtt-stats-table th {
            text-align: left;
            padding: 12px 15px;
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        .rtt-stats-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }

        .rtt-stats-table tr:hover td {
            background: #f8fafc;
        }

        .rtt-stats-table .rtt-progress-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .rtt-stats-table .rtt-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2db742, #22c55e);
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .rtt-status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .rtt-status-pendiente {
            background: #fef3c7;
            color: #b45309;
        }

        .rtt-status-confirmada {
            background: #dcfce7;
            color: #16a34a;
        }

        .rtt-status-cancelada {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Loading */
        .rtt-stats-loading {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .rtt-stats-loading.hidden {
            display: none;
        }

        .rtt-stats-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e2e8f0;
            border-top-color: #2db742;
            border-radius: 50%;
            animation: rttSpin 1s linear infinite;
        }

        @keyframes rttSpin {
            to { transform: rotate(360deg); }
        }

        .rtt-stats-loading p {
            margin-top: 15px;
            color: #64748b;
            font-size: 16px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .rtt-stats-row {
                grid-template-columns: 1fr;
            }

            .rtt-stats-chart-large {
                grid-column: span 1;
            }
        }

        @media (max-width: 768px) {
            .rtt-stats-cards {
                grid-template-columns: 1fr;
            }

            .rtt-stats-filters {
                flex-wrap: wrap;
            }
        }
        </style>
        <?php
    }

    /**
     * Limpiar log de seguridad via AJAX
     */
    public function ajax_clear_security_log() {
        check_ajax_referer('rtt_security_log_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sin permisos']);
        }

        delete_option('rtt_failed_attempts');
        wp_send_json_success(['message' => __('Log limpiado correctamente', 'rtt-reservas')]);
    }

    /**
     * Renderizar página de log de seguridad
     */
    public function render_security_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $attempts = get_option('rtt_failed_attempts', []);
        $attempts = array_reverse($attempts); // Más recientes primero

        // Contar por tipo
        $counts = [
            'honeypot' => 0,
            'rate_limit' => 0,
            'invalid_nonce' => 0,
            'total' => count($attempts)
        ];

        foreach ($attempts as $attempt) {
            if (isset($counts[$attempt['reason']])) {
                $counts[$attempt['reason']]++;
            }
        }

        $nonce = wp_create_nonce('rtt_security_log_nonce');
        ?>
        <div class="wrap rtt-security-wrap">
            <h1 class="rtt-security-title">
                <span class="dashicons dashicons-shield-alt"></span>
                <?php _e('Log de Seguridad', 'rtt-reservas'); ?>
            </h1>

            <!-- Resumen -->
            <div class="rtt-security-cards">
                <div class="rtt-security-card rtt-card-total">
                    <div class="rtt-card-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="rtt-card-info">
                        <h3><?php echo $counts['total']; ?></h3>
                        <p><?php _e('Total Intentos', 'rtt-reservas'); ?></p>
                    </div>
                </div>

                <div class="rtt-security-card rtt-card-honeypot">
                    <div class="rtt-card-icon">
                        <span class="dashicons dashicons-dismiss"></span>
                    </div>
                    <div class="rtt-card-info">
                        <h3><?php echo $counts['honeypot']; ?></h3>
                        <p><?php _e('Bots (Honeypot)', 'rtt-reservas'); ?></p>
                    </div>
                </div>

                <div class="rtt-security-card rtt-card-rate">
                    <div class="rtt-card-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="rtt-card-info">
                        <h3><?php echo $counts['rate_limit']; ?></h3>
                        <p><?php _e('Rate Limit', 'rtt-reservas'); ?></p>
                    </div>
                </div>

                <div class="rtt-security-card rtt-card-nonce">
                    <div class="rtt-card-icon">
                        <span class="dashicons dashicons-lock"></span>
                    </div>
                    <div class="rtt-card-info">
                        <h3><?php echo $counts['invalid_nonce']; ?></h3>
                        <p><?php _e('Nonce Inválido', 'rtt-reservas'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Acciones -->
            <div class="rtt-security-actions">
                <button type="button" id="rtt-clear-log" class="button button-secondary" data-nonce="<?php echo $nonce; ?>">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Limpiar Log', 'rtt-reservas'); ?>
                </button>
                <span class="rtt-security-note">
                    <?php _e('Se muestran los últimos 100 intentos fallidos', 'rtt-reservas'); ?>
                </span>
            </div>

            <!-- Tabla de intentos -->
            <div class="rtt-security-table-wrap">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 160px;"><?php _e('Fecha/Hora', 'rtt-reservas'); ?></th>
                            <th style="width: 130px;"><?php _e('IP', 'rtt-reservas'); ?></th>
                            <th style="width: 130px;"><?php _e('Razón', 'rtt-reservas'); ?></th>
                            <th><?php _e('User Agent', 'rtt-reservas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attempts)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px; color: #666;">
                                    <span class="dashicons dashicons-yes-alt" style="font-size: 40px; color: #2db742; display: block; margin-bottom: 10px;"></span>
                                    <?php _e('No hay intentos fallidos registrados. ¡Todo está bien!', 'rtt-reservas'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attempts as $attempt): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($attempt['time']); ?></strong>
                                    </td>
                                    <td>
                                        <code><?php echo esc_html($attempt['ip']); ?></code>
                                    </td>
                                    <td>
                                        <?php
                                        $reason_labels = [
                                            'honeypot' => '<span class="rtt-reason rtt-reason-honeypot">🤖 Bot</span>',
                                            'rate_limit' => '<span class="rtt-reason rtt-reason-rate">⏱️ Rate Limit</span>',
                                            'invalid_nonce' => '<span class="rtt-reason rtt-reason-nonce">🔒 Nonce</span>',
                                        ];
                                        echo $reason_labels[$attempt['reason']] ?? esc_html($attempt['reason']);
                                        ?>
                                    </td>
                                    <td>
                                        <small style="color: #666; word-break: break-all;">
                                            <?php echo esc_html($attempt['user_agent'] ?? '-'); ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <style>
        .rtt-security-wrap {
            padding: 20px;
            max-width: 1400px;
        }

        .rtt-security-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 28px;
            color: #1d2327;
            margin-bottom: 25px;
        }

        .rtt-security-title .dashicons {
            font-size: 32px;
            width: 32px;
            height: 32px;
            color: #dc2626;
        }

        .rtt-security-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .rtt-security-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #ccc;
        }

        .rtt-card-total { border-left-color: #64748b; }
        .rtt-card-honeypot { border-left-color: #dc2626; }
        .rtt-card-rate { border-left-color: #f59e0b; }
        .rtt-card-nonce { border-left-color: #8b5cf6; }

        .rtt-card-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
        }

        .rtt-card-total .rtt-card-icon .dashicons { color: #64748b; }
        .rtt-card-honeypot .rtt-card-icon .dashicons { color: #dc2626; }
        .rtt-card-rate .rtt-card-icon .dashicons { color: #f59e0b; }
        .rtt-card-nonce .rtt-card-icon .dashicons { color: #8b5cf6; }

        .rtt-card-icon .dashicons {
            font-size: 24px;
            width: 24px;
            height: 24px;
        }

        .rtt-card-info h3 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }

        .rtt-card-info p {
            margin: 5px 0 0;
            font-size: 13px;
            color: #64748b;
        }

        .rtt-security-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .rtt-security-actions .button {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .rtt-security-note {
            color: #64748b;
            font-size: 13px;
        }

        .rtt-security-table-wrap {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .rtt-reason {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .rtt-reason-honeypot {
            background: #fee2e2;
            color: #dc2626;
        }

        .rtt-reason-rate {
            background: #fef3c7;
            color: #b45309;
        }

        .rtt-reason-nonce {
            background: #f3e8ff;
            color: #7c3aed;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('#rtt-clear-log').on('click', function() {
                if (!confirm('<?php _e('¿Estás seguro de limpiar el log de seguridad?', 'rtt-reservas'); ?>')) {
                    return;
                }

                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php _e('Limpiando...', 'rtt-reservas'); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rtt_clear_security_log',
                        nonce: $btn.data('nonce')
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || 'Error');
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> <?php _e('Limpiar Log', 'rtt-reservas'); ?>');
                        }
                    },
                    error: function() {
                        alert('Error de conexión');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> <?php _e('Limpiar Log', 'rtt-reservas'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
