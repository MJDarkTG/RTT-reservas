<?php
/**
 * Clase para el panel de administración de reservas
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_Admin_Reservas {

    /**
     * Inicializar
     */
    public function init() {
        add_action('admin_menu', [$this, 'add_submenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_rtt_update_estado', [$this, 'ajax_update_estado']);
        add_action('wp_ajax_rtt_delete_reserva', [$this, 'ajax_delete_reserva']);
        add_action('wp_ajax_rtt_get_reserva_detail', [$this, 'ajax_get_reserva_detail']);
        add_action('admin_init', [$this, 'handle_export_csv']);
    }

    /**
     * Agregar submenú
     */
    public function add_submenu() {
        add_submenu_page(
            'rtt-reservas',
            __('Listado de Reservas', 'rtt-reservas'),
            __('Ver Reservas', 'rtt-reservas'),
            'manage_options',
            'rtt-reservas-list',
            [$this, 'render_reservas_page']
        );
    }

    /**
     * Cargar assets
     */
    public function enqueue_assets($hook) {
        if (!in_array($hook, ['rtt-reservas_page_rtt-reservas-list'])) {
            return;
        }

        wp_enqueue_style(
            'rtt-admin-reservas',
            RTT_RESERVAS_PLUGIN_URL . 'assets/css/admin-reservas.css',
            [],
            RTT_RESERVAS_VERSION
        );

        wp_enqueue_script(
            'rtt-admin-reservas',
            RTT_RESERVAS_PLUGIN_URL . 'assets/js/admin-reservas.js',
            ['jquery'],
            RTT_RESERVAS_VERSION,
            true
        );

        wp_localize_script('rtt-admin-reservas', 'rttAdminReservas', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rtt_admin_reservas'),
            'i18n' => [
                'confirmDelete' => __('¿Estás seguro de eliminar esta reserva? Esta acción no se puede deshacer.', 'rtt-reservas'),
                'deleted' => __('Reserva eliminada', 'rtt-reservas'),
                'updated' => __('Estado actualizado', 'rtt-reservas'),
                'error' => __('Error al procesar la solicitud', 'rtt-reservas'),
            ]
        ]);
    }

    /**
     * Renderizar página de reservas
     */
    public function render_reservas_page() {
        // Obtener parámetros
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $estado = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : '';
        $buscar = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $tour_filter = isset($_GET['tour']) ? sanitize_text_field($_GET['tour']) : '';
        $fecha_desde = isset($_GET['fecha_desde']) ? sanitize_text_field($_GET['fecha_desde']) : '';
        $fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize_text_field($_GET['fecha_hasta']) : '';

        // Obtener reservas
        $result = RTT_Database::get_reservas([
            'page' => $page,
            'per_page' => 20,
            'estado' => $estado,
            'buscar' => $buscar,
            'tour' => $tour_filter,
            'fecha_desde' => $fecha_desde,
            'fecha_hasta' => $fecha_hasta
        ]);

        $reservas = $result['items'];
        $total_pages = $result['pages'];
        $total_items = $result['total'];

        // Obtener estadísticas
        $stats = RTT_Database::get_stats();

        // Estados disponibles
        $estados = [
            'pendiente' => ['label' => __('Pendiente', 'rtt-reservas'), 'color' => '#f0ad4e'],
            'confirmada' => ['label' => __('Confirmada', 'rtt-reservas'), 'color' => '#5bc0de'],
            'pagada' => ['label' => __('Pagada', 'rtt-reservas'), 'color' => '#5cb85c'],
            'completada' => ['label' => __('Completada', 'rtt-reservas'), 'color' => '#004070'],
            'cancelada' => ['label' => __('Cancelada', 'rtt-reservas'), 'color' => '#d9534f'],
        ];

        ?>
        <div class="wrap rtt-admin-reservas">
            <h1 class="wp-heading-inline"><?php _e('Reservas', 'rtt-reservas'); ?></h1>

            <!-- Estadísticas -->
            <div class="rtt-stats-cards">
                <div class="rtt-stat-card">
                    <span class="rtt-stat-number"><?php echo $stats['total']; ?></span>
                    <span class="rtt-stat-label"><?php _e('Total Reservas', 'rtt-reservas'); ?></span>
                </div>
                <div class="rtt-stat-card rtt-stat-warning">
                    <span class="rtt-stat-number"><?php echo $stats['pendientes']; ?></span>
                    <span class="rtt-stat-label"><?php _e('Pendientes', 'rtt-reservas'); ?></span>
                </div>
                <div class="rtt-stat-card rtt-stat-success">
                    <span class="rtt-stat-number"><?php echo $stats['confirmadas']; ?></span>
                    <span class="rtt-stat-label"><?php _e('Confirmadas', 'rtt-reservas'); ?></span>
                </div>
                <div class="rtt-stat-card rtt-stat-info">
                    <span class="rtt-stat-number"><?php echo $stats['este_mes']; ?></span>
                    <span class="rtt-stat-label"><?php _e('Este Mes', 'rtt-reservas'); ?></span>
                </div>
            </div>

            <!-- Filtros -->
            <?php $tours_list = RTT_Database::get_tours_list(); ?>
            <div class="rtt-filters">
                <form method="get" class="rtt-filter-form">
                    <input type="hidden" name="page" value="rtt-reservas-list">

                    <select name="estado" class="rtt-filter-select">
                        <option value=""><?php _e('Todos los estados', 'rtt-reservas'); ?></option>
                        <?php foreach ($estados as $key => $data): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($estado, $key); ?>>
                                <?php echo esc_html($data['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (!empty($tours_list)): ?>
                    <select name="tour" class="rtt-filter-select">
                        <option value=""><?php _e('Todos los tours', 'rtt-reservas'); ?></option>
                        <?php foreach ($tours_list as $tour_name): ?>
                            <option value="<?php echo esc_attr($tour_name); ?>" <?php selected($tour_filter, $tour_name); ?>>
                                <?php echo esc_html(wp_trim_words($tour_name, 6)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>

                    <input type="date" name="fecha_desde" value="<?php echo esc_attr($fecha_desde); ?>"
                           title="<?php _e('Fecha desde', 'rtt-reservas'); ?>"
                           class="rtt-filter-date">

                    <input type="date" name="fecha_hasta" value="<?php echo esc_attr($fecha_hasta); ?>"
                           title="<?php _e('Fecha hasta', 'rtt-reservas'); ?>"
                           class="rtt-filter-date">

                    <input type="search" name="s" value="<?php echo esc_attr($buscar); ?>"
                           placeholder="<?php _e('Buscar...', 'rtt-reservas'); ?>"
                           class="rtt-search-input">

                    <button type="submit" class="button"><?php _e('Filtrar', 'rtt-reservas'); ?></button>

                    <?php if ($estado || $buscar || $tour_filter || $fecha_desde || $fecha_hasta): ?>
                        <a href="<?php echo admin_url('admin.php?page=rtt-reservas-list'); ?>" class="button">
                            <?php _e('Limpiar', 'rtt-reservas'); ?>
                        </a>
                    <?php endif; ?>
                </form>

                <!-- Exportar CSV -->
                <div class="rtt-export-section">
                    <button type="button" id="rtt-toggle-export" class="button">
                        <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                        <?php _e('Exportar CSV', 'rtt-reservas'); ?>
                    </button>
                    <div id="rtt-export-options" style="display: none; margin-top: 10px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <form method="get" action="<?php echo admin_url('admin.php'); ?>" class="rtt-export-form">
                            <input type="hidden" name="page" value="rtt-reservas-list">
                            <input type="hidden" name="rtt_export_csv" value="1">
                            <?php wp_nonce_field('rtt_export_csv'); ?>

                            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                                <div>
                                    <label style="display: block; font-size: 12px; margin-bottom: 3px;"><?php _e('Estado', 'rtt-reservas'); ?></label>
                                    <select name="estado">
                                        <option value=""><?php _e('Todos', 'rtt-reservas'); ?></option>
                                        <?php foreach ($estados as $key => $data): ?>
                                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($data['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label style="display: block; font-size: 12px; margin-bottom: 3px;"><?php _e('Desde', 'rtt-reservas'); ?></label>
                                    <input type="date" name="fecha_desde">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 12px; margin-bottom: 3px;"><?php _e('Hasta', 'rtt-reservas'); ?></label>
                                    <input type="date" name="fecha_hasta">
                                </div>
                                <button type="submit" class="button button-primary">
                                    <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                                    <?php _e('Descargar', 'rtt-reservas'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tabla de reservas -->
            <table class="wp-list-table widefat fixed striped rtt-reservas-table">
                <thead>
                    <tr>
                        <th class="column-codigo"><?php _e('Código', 'rtt-reservas'); ?></th>
                        <th class="column-tour"><?php _e('Tour', 'rtt-reservas'); ?></th>
                        <th class="column-fecha"><?php _e('Fecha Tour', 'rtt-reservas'); ?></th>
                        <th class="column-precio"><?php _e('Precio', 'rtt-reservas'); ?></th>
                        <th class="column-cliente"><?php _e('Cliente', 'rtt-reservas'); ?></th>
                        <th class="column-pasajeros"><?php _e('Pax', 'rtt-reservas'); ?></th>
                        <th class="column-estado"><?php _e('Estado', 'rtt-reservas'); ?></th>
                        <th class="column-creacion"><?php _e('Creación', 'rtt-reservas'); ?></th>
                        <th class="column-acciones"><?php _e('Acciones', 'rtt-reservas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reservas)): ?>
                        <tr>
                            <td colspan="9" class="rtt-no-items">
                                <?php _e('No se encontraron reservas.', 'rtt-reservas'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reservas as $reserva): ?>
                            <tr data-id="<?php echo $reserva->id; ?>">
                                <td class="column-codigo">
                                    <strong>
                                        <a href="#" class="rtt-view-detail" data-id="<?php echo $reserva->id; ?>">
                                            <?php echo esc_html($reserva->codigo); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td class="column-tour">
                                    <span title="<?php echo esc_attr($reserva->tour); ?>">
                                        <?php echo esc_html(wp_trim_words($reserva->tour, 5)); ?>
                                    </span>
                                </td>
                                <td class="column-fecha">
                                    <?php echo date_i18n('d/m/Y', strtotime($reserva->fecha_tour)); ?>
                                </td>
                                <td class="column-precio">
                                    <?php if (!empty($reserva->precio)): ?>
                                        <span style="color: #D4A017; font-weight: bold;"><?php echo esc_html($reserva->precio); ?></span>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-cliente">
                                    <strong><?php echo esc_html($reserva->nombre_representante); ?></strong><br>
                                    <small><?php echo esc_html($reserva->email); ?></small>
                                </td>
                                <td class="column-pasajeros">
                                    <?php echo intval($reserva->cantidad_pasajeros); ?>
                                </td>
                                <td class="column-estado">
                                    <select class="rtt-estado-select" data-id="<?php echo $reserva->id; ?>"
                                            style="border-left: 3px solid <?php echo $estados[$reserva->estado]['color']; ?>">
                                        <?php foreach ($estados as $key => $data): ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($reserva->estado, $key); ?>>
                                                <?php echo esc_html($data['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="column-creacion">
                                    <?php echo date_i18n('d/m/Y H:i', strtotime($reserva->fecha_creacion)); ?>
                                </td>
                                <td class="column-acciones">
                                    <a href="#" class="rtt-view-detail" data-id="<?php echo $reserva->id; ?>" title="<?php _e('Ver detalle', 'rtt-reservas'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </a>
                                    <a href="#" class="rtt-delete-reserva" data-id="<?php echo $reserva->id; ?>" title="<?php _e('Eliminar', 'rtt-reservas'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Paginación -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(_n('%s elemento', '%s elementos', $total_items, 'rtt-reservas'), number_format_i18n($total_items)); ?>
                        </span>
                        <span class="pagination-links">
                            <?php
                            $base_url = admin_url('admin.php?page=rtt-reservas-list');
                            if ($estado) $base_url .= '&estado=' . urlencode($estado);
                            if ($buscar) $base_url .= '&s=' . urlencode($buscar);
                            if ($tour_filter) $base_url .= '&tour=' . urlencode($tour_filter);
                            if ($fecha_desde) $base_url .= '&fecha_desde=' . urlencode($fecha_desde);
                            if ($fecha_hasta) $base_url .= '&fecha_hasta=' . urlencode($fecha_hasta);

                            if ($page > 1): ?>
                                <a class="prev-page button" href="<?php echo $base_url . '&paged=' . ($page - 1); ?>">‹</a>
                            <?php else: ?>
                                <span class="prev-page button disabled">‹</span>
                            <?php endif; ?>

                            <span class="paging-input">
                                <?php echo $page; ?> de <?php echo $total_pages; ?>
                            </span>

                            <?php if ($page < $total_pages): ?>
                                <a class="next-page button" href="<?php echo $base_url . '&paged=' . ($page + 1); ?>">›</a>
                            <?php else: ?>
                                <span class="next-page button disabled">›</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Modal de detalle -->
        <div id="rtt-modal-detail" class="rtt-modal" style="display: none;">
            <div class="rtt-modal-content">
                <span class="rtt-modal-close">&times;</span>
                <div id="rtt-modal-body">
                    <!-- Contenido cargado via AJAX -->
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Actualizar estado
     */
    public function ajax_update_estado() {
        check_ajax_referer('rtt_admin_reservas', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sin permisos', 'rtt-reservas')]);
        }

        $id = intval($_POST['id'] ?? 0);
        $estado = sanitize_text_field($_POST['estado'] ?? '');

        if (!$id || !$estado) {
            wp_send_json_error(['message' => __('Datos inválidos', 'rtt-reservas')]);
        }

        $result = RTT_Database::update_estado($id, $estado);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Estado actualizado', 'rtt-reservas')]);
    }

    /**
     * AJAX: Eliminar reserva
     */
    public function ajax_delete_reserva() {
        check_ajax_referer('rtt_admin_reservas', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sin permisos', 'rtt-reservas')]);
        }

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(['message' => __('ID inválido', 'rtt-reservas')]);
        }

        $result = RTT_Database::delete_reserva($id);

        if (!$result) {
            wp_send_json_error(['message' => __('Error al eliminar', 'rtt-reservas')]);
        }

        wp_send_json_success(['message' => __('Reserva eliminada', 'rtt-reservas')]);
    }

    /**
     * AJAX: Obtener detalle de reserva
     */
    public function ajax_get_reserva_detail() {
        check_ajax_referer('rtt_admin_reservas', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sin permisos', 'rtt-reservas')]);
        }

        $id = intval($_GET['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(['message' => __('ID inválido', 'rtt-reservas')]);
        }

        $reserva = RTT_Database::get_reserva($id);

        if (!$reserva) {
            wp_send_json_error(['message' => __('Reserva no encontrada', 'rtt-reservas')]);
        }

        ob_start();
        $this->render_reserva_detail($reserva);
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Renderizar detalle de reserva
     */
    private function render_reserva_detail($reserva) {
        $estados = [
            'pendiente' => ['label' => __('Pendiente', 'rtt-reservas'), 'color' => '#f0ad4e'],
            'confirmada' => ['label' => __('Confirmada', 'rtt-reservas'), 'color' => '#5bc0de'],
            'pagada' => ['label' => __('Pagada', 'rtt-reservas'), 'color' => '#5cb85c'],
            'completada' => ['label' => __('Completada', 'rtt-reservas'), 'color' => '#004070'],
            'cancelada' => ['label' => __('Cancelada', 'rtt-reservas'), 'color' => '#d9534f'],
        ];
        ?>
        <div class="rtt-detail-header">
            <h2><?php echo esc_html($reserva->codigo); ?></h2>
            <span class="rtt-detail-estado" style="background: <?php echo $estados[$reserva->estado]['color']; ?>">
                <?php echo esc_html($estados[$reserva->estado]['label']); ?>
            </span>
        </div>

        <div class="rtt-detail-section">
            <h3><?php _e('Información del Tour', 'rtt-reservas'); ?></h3>
            <table class="rtt-detail-table">
                <tr>
                    <th><?php _e('Tour', 'rtt-reservas'); ?></th>
                    <td><?php echo esc_html($reserva->tour); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Fecha del Tour', 'rtt-reservas'); ?></th>
                    <td><strong><?php echo date_i18n('d/m/Y', strtotime($reserva->fecha_tour)); ?></strong></td>
                </tr>
                <?php if (!empty($reserva->precio)): ?>
                <tr>
                    <th><?php _e('Precio', 'rtt-reservas'); ?></th>
                    <td><strong style="color: #D4A017;"><?php echo esc_html($reserva->precio); ?></strong> <?php _e('por persona', 'rtt-reservas'); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php _e('Idioma', 'rtt-reservas'); ?></th>
                    <td><?php echo $reserva->idioma === 'en' ? 'English' : 'Español'; ?></td>
                </tr>
            </table>
        </div>

        <div class="rtt-detail-section">
            <h3><?php _e('Datos del Representante', 'rtt-reservas'); ?></h3>
            <table class="rtt-detail-table">
                <tr>
                    <th><?php _e('Nombre', 'rtt-reservas'); ?></th>
                    <td><?php echo esc_html($reserva->nombre_representante); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Email', 'rtt-reservas'); ?></th>
                    <td><a href="mailto:<?php echo esc_attr($reserva->email); ?>"><?php echo esc_html($reserva->email); ?></a></td>
                </tr>
                <tr>
                    <th><?php _e('Teléfono', 'rtt-reservas'); ?></th>
                    <td>
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $reserva->telefono); ?>" target="_blank">
                            <?php echo esc_html($reserva->telefono); ?> (WhatsApp)
                        </a>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('País', 'rtt-reservas'); ?></th>
                    <td><?php echo esc_html($reserva->pais); ?></td>
                </tr>
            </table>
        </div>

        <div class="rtt-detail-section">
            <h3><?php _e('Pasajeros', 'rtt-reservas'); ?> (<?php echo count($reserva->pasajeros); ?>)</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php _e('Documento', 'rtt-reservas'); ?></th>
                        <th><?php _e('Nombre', 'rtt-reservas'); ?></th>
                        <th><?php _e('F. Nacimiento', 'rtt-reservas'); ?></th>
                        <th><?php _e('Género', 'rtt-reservas'); ?></th>
                        <th><?php _e('Nacionalidad', 'rtt-reservas'); ?></th>
                        <th><?php _e('Observaciones', 'rtt-reservas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reserva->pasajeros as $i => $pasajero): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo esc_html($pasajero->tipo_documento . ': ' . $pasajero->numero_documento); ?></td>
                            <td><strong><?php echo esc_html($pasajero->nombre_completo); ?></strong></td>
                            <td><?php echo $pasajero->fecha_nacimiento ? date_i18n('d/m/Y', strtotime($pasajero->fecha_nacimiento)) : '-'; ?></td>
                            <td><?php echo $pasajero->genero === 'M' ? __('Masculino', 'rtt-reservas') : __('Femenino', 'rtt-reservas'); ?></td>
                            <td><?php echo esc_html($pasajero->nacionalidad); ?></td>
                            <td><?php echo esc_html($pasajero->alergias ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($reserva->notas): ?>
        <div class="rtt-detail-section">
            <h3><?php _e('Notas', 'rtt-reservas'); ?></h3>
            <p><?php echo nl2br(esc_html($reserva->notas)); ?></p>
        </div>
        <?php endif; ?>

        <div class="rtt-detail-footer">
            <small>
                <?php _e('Creado:', 'rtt-reservas'); ?> <?php echo date_i18n('d/m/Y H:i', strtotime($reserva->fecha_creacion)); ?> |
                <?php _e('Actualizado:', 'rtt-reservas'); ?> <?php echo date_i18n('d/m/Y H:i', strtotime($reserva->fecha_actualizacion)); ?>
            </small>
        </div>
        <?php
    }

    /**
     * Manejar exportación CSV
     */
    public function handle_export_csv() {
        if (!isset($_GET['rtt_export_csv']) || $_GET['rtt_export_csv'] !== '1') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Sin permisos', 'rtt-reservas'));
        }

        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'rtt_export_csv')) {
            wp_die(__('Error de seguridad', 'rtt-reservas'));
        }

        // Obtener filtros
        $estado = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : '';
        $fecha_desde = isset($_GET['fecha_desde']) ? sanitize_text_field($_GET['fecha_desde']) : '';
        $fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize_text_field($_GET['fecha_hasta']) : '';

        // Obtener reservas
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_reservas';
        $table_pasajeros = $wpdb->prefix . 'rtt_pasajeros';

        $where = '1=1';
        $values = [];

        if (!empty($estado)) {
            $where .= ' AND estado = %s';
            $values[] = $estado;
        }

        if (!empty($fecha_desde)) {
            $where .= ' AND fecha_creacion >= %s';
            $values[] = $fecha_desde . ' 00:00:00';
        }

        if (!empty($fecha_hasta)) {
            $where .= ' AND fecha_creacion <= %s';
            $values[] = $fecha_hasta . ' 23:59:59';
        }

        $sql = "SELECT * FROM $table WHERE $where ORDER BY fecha_creacion DESC";
        $reservas = empty($values) ? $wpdb->get_results($sql) : $wpdb->get_results($wpdb->prepare($sql, $values));

        // Generar CSV
        $filename = 'reservas-' . date('Y-m-d-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // BOM para Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Encabezados
        fputcsv($output, [
            'Código',
            'Tour',
            'Fecha Tour',
            'Precio',
            'Representante',
            'Email',
            'Teléfono',
            'País',
            'Pasajeros',
            'Estado',
            'Fecha Creación',
            'Pasajero - Documento',
            'Pasajero - Nombre',
            'Pasajero - Nacionalidad',
            'Pasajero - F. Nacimiento',
            'Pasajero - Género',
            'Pasajero - Observaciones'
        ], ';');

        // Datos
        foreach ($reservas as $reserva) {
            // Obtener pasajeros
            $pasajeros = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_pasajeros WHERE reserva_id = %d",
                $reserva->id
            ));

            if (empty($pasajeros)) {
                // Reserva sin pasajeros
                fputcsv($output, [
                    $reserva->codigo,
                    $reserva->tour,
                    date('d/m/Y', strtotime($reserva->fecha_tour)),
                    $reserva->precio ?: '',
                    $reserva->nombre_representante,
                    $reserva->email,
                    $reserva->telefono,
                    $reserva->pais,
                    $reserva->cantidad_pasajeros,
                    ucfirst($reserva->estado),
                    date('d/m/Y H:i', strtotime($reserva->fecha_creacion)),
                    '', '', '', '', '', ''
                ], ';');
            } else {
                // Una fila por pasajero
                foreach ($pasajeros as $i => $pasajero) {
                    fputcsv($output, [
                        $i === 0 ? $reserva->codigo : '',
                        $i === 0 ? $reserva->tour : '',
                        $i === 0 ? date('d/m/Y', strtotime($reserva->fecha_tour)) : '',
                        $i === 0 ? ($reserva->precio ?: '') : '',
                        $i === 0 ? $reserva->nombre_representante : '',
                        $i === 0 ? $reserva->email : '',
                        $i === 0 ? $reserva->telefono : '',
                        $i === 0 ? $reserva->pais : '',
                        $i === 0 ? $reserva->cantidad_pasajeros : '',
                        $i === 0 ? ucfirst($reserva->estado) : '',
                        $i === 0 ? date('d/m/Y H:i', strtotime($reserva->fecha_creacion)) : '',
                        $pasajero->tipo_documento . ': ' . $pasajero->numero_documento,
                        $pasajero->nombre_completo,
                        $pasajero->nacionalidad,
                        $pasajero->fecha_nacimiento ? date('d/m/Y', strtotime($pasajero->fecha_nacimiento)) : '',
                        $pasajero->genero === 'M' ? 'Masculino' : 'Femenino',
                        $pasajero->alergias ?: ''
                    ], ';');
                }
            }
        }

        fclose($output);
        exit;
    }
}
