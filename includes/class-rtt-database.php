<?php
/**
 * Clase para manejar la base de datos de reservas
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_Database {

    const DB_VERSION = '1.4';

    /**
     * Crear tablas en la base de datos
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Tabla de reservas
        $table_reservas = $wpdb->prefix . 'rtt_reservas';
        $sql_reservas = "CREATE TABLE $table_reservas (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            codigo varchar(50) NOT NULL,
            tour varchar(255) NOT NULL,
            fecha_tour date NOT NULL,
            precio varchar(100) DEFAULT NULL,
            nombre_representante varchar(255) NOT NULL,
            email varchar(100) NOT NULL,
            telefono varchar(50) NOT NULL,
            pais varchar(100) NOT NULL,
            cantidad_pasajeros int(11) NOT NULL DEFAULT 0,
            estado varchar(20) NOT NULL DEFAULT 'pendiente',
            idioma varchar(5) NOT NULL DEFAULT 'es',
            notas text,
            fecha_creacion datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY codigo (codigo),
            KEY estado (estado),
            KEY fecha_tour (fecha_tour),
            KEY fecha_creacion (fecha_creacion),
            KEY tour (tour(100))
        ) $charset_collate;";

        // Tabla de pasajeros
        $table_pasajeros = $wpdb->prefix . 'rtt_pasajeros';
        $sql_pasajeros = "CREATE TABLE $table_pasajeros (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            reserva_id bigint(20) UNSIGNED NOT NULL,
            tipo_documento varchar(20) NOT NULL,
            numero_documento varchar(50) NOT NULL,
            nombre_completo varchar(255) NOT NULL,
            fecha_nacimiento date DEFAULT NULL,
            genero varchar(10) NOT NULL,
            nacionalidad varchar(100) NOT NULL,
            alergias text,
            PRIMARY KEY (id),
            KEY reserva_id (reserva_id)
        ) $charset_collate;";

        // Tabla de tracking de formulario
        $table_tracking = $wpdb->prefix . 'rtt_form_tracking';
        $sql_tracking = "CREATE TABLE $table_tracking (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            ip varchar(45) NOT NULL,
            page_url varchar(500) NOT NULL,
            page_title varchar(255) DEFAULT NULL,
            step int(11) NOT NULL DEFAULT 1,
            event_type varchar(20) NOT NULL DEFAULT 'view',
            tour_selected varchar(255) DEFAULT NULL,
            fecha_selected date DEFAULT NULL,
            pasajeros_count int(11) DEFAULT NULL,
            user_agent varchar(500) DEFAULT NULL,
            referrer varchar(500) DEFAULT NULL,
            lang varchar(5) DEFAULT 'es',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY event_type (event_type),
            KEY step (step),
            KEY created_at (created_at),
            KEY page_url (page_url(100))
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_reservas);
        dbDelta($sql_pasajeros);
        dbDelta($sql_tracking);

        // Agregar índice de tour si no existe (para instalaciones existentes)
        self::maybe_add_tour_index();

        update_option('rtt_db_version', self::DB_VERSION);
    }

    /**
     * Agregar índice de tour si no existe
     */
    private static function maybe_add_tour_index() {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_reservas';

        // Verificar si el índice ya existe
        $index_exists = $wpdb->get_var(
            "SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
             AND table_name = '$table'
             AND index_name = 'tour'"
        );

        if (!$index_exists) {
            $wpdb->query("ALTER TABLE $table ADD INDEX tour (tour(100))");
        }
    }

    /**
     * Generar código único de reserva
     */
    public static function generate_codigo() {
        return 'RTT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
    }

    /**
     * Insertar nueva reserva
     */
    public static function insert_reserva($data) {
        global $wpdb;

        $table = $wpdb->prefix . 'rtt_reservas';
        $codigo = self::generate_codigo();

        $result = $wpdb->insert($table, [
            'codigo' => $codigo,
            'tour' => sanitize_text_field($data['tour']),
            'fecha_tour' => sanitize_text_field($data['fecha']),
            'precio' => sanitize_text_field($data['precio_tour'] ?? ''),
            'nombre_representante' => sanitize_text_field($data['nombre_representante']),
            'email' => sanitize_email($data['email']),
            'telefono' => sanitize_text_field($data['telefono']),
            'pais' => sanitize_text_field($data['pais']),
            'cantidad_pasajeros' => intval($data['cantidad_pasajeros']),
            'estado' => 'pendiente',
            'idioma' => sanitize_text_field($data['lang'] ?? 'es'),
        ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']);

        if ($result === false) {
            return new WP_Error('db_error', 'Error al guardar la reserva');
        }

        // Invalidar caché
        self::invalidate_all_cache();

        return [
            'id' => $wpdb->insert_id,
            'codigo' => $codigo
        ];
    }

    /**
     * Insertar pasajeros de una reserva
     */
    public static function insert_pasajeros($reserva_id, $pasajeros) {
        global $wpdb;

        $table = $wpdb->prefix . 'rtt_pasajeros';

        foreach ($pasajeros as $pasajero) {
            $wpdb->insert($table, [
                'reserva_id' => $reserva_id,
                'tipo_documento' => sanitize_text_field($pasajero['tipo_doc']),
                'numero_documento' => sanitize_text_field($pasajero['nro_doc']),
                'nombre_completo' => sanitize_text_field($pasajero['nombre']),
                'fecha_nacimiento' => !empty($pasajero['fecha_nacimiento']) ? sanitize_text_field($pasajero['fecha_nacimiento']) : null,
                'genero' => sanitize_text_field($pasajero['genero']),
                'nacionalidad' => sanitize_text_field($pasajero['nacionalidad']),
                'alergias' => sanitize_textarea_field($pasajero['alergias'] ?? ''),
            ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
        }

        return true;
    }

    /**
     * Obtener todas las reservas con paginación
     */
    public static function get_reservas($args = []) {
        global $wpdb;

        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'estado' => '',
            'buscar' => '',
            'tour' => '',
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'orderby' => 'fecha_creacion',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);
        $table = $wpdb->prefix . 'rtt_reservas';

        $where = '1=1';
        $values = [];

        if (!empty($args['estado'])) {
            $where .= ' AND estado = %s';
            $values[] = $args['estado'];
        }

        if (!empty($args['buscar'])) {
            $where .= ' AND (codigo LIKE %s OR nombre_representante LIKE %s OR email LIKE %s OR tour LIKE %s)';
            $search = '%' . $wpdb->esc_like($args['buscar']) . '%';
            $values = array_merge($values, [$search, $search, $search, $search]);
        }

        if (!empty($args['tour'])) {
            $where .= ' AND tour LIKE %s';
            $values[] = '%' . $wpdb->esc_like($args['tour']) . '%';
        }

        if (!empty($args['fecha_desde'])) {
            $where .= ' AND fecha_tour >= %s';
            $values[] = $args['fecha_desde'];
        }

        if (!empty($args['fecha_hasta'])) {
            $where .= ' AND fecha_tour <= %s';
            $values[] = $args['fecha_hasta'];
        }

        $orderby = in_array($args['orderby'], ['fecha_creacion', 'fecha_tour', 'codigo', 'estado']) ? $args['orderby'] : 'fecha_creacion';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $offset = ($args['page'] - 1) * $args['per_page'];

        $sql = "SELECT * FROM $table WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $values[] = $args['per_page'];
        $values[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($sql, $values));

        // Total para paginación
        $sql_count = "SELECT COUNT(*) FROM $table WHERE $where";
        if (!empty($values)) {
            $count_values = array_slice($values, 0, -2); // Quitar limit y offset
            $total = $wpdb->get_var(empty($count_values) ? $sql_count : $wpdb->prepare($sql_count, $count_values));
        } else {
            $total = $wpdb->get_var($sql_count);
        }

        return [
            'items' => $results,
            'total' => intval($total),
            'pages' => ceil($total / $args['per_page'])
        ];
    }

    /**
     * Obtener lista de tours únicos (para filtro) - Con caché
     */
    public static function get_tours_list() {
        $cache_key = 'rtt_tours_list';
        $tours = get_transient($cache_key);

        if ($tours === false) {
            global $wpdb;
            $table = $wpdb->prefix . 'rtt_reservas';
            $tours = $wpdb->get_col("SELECT DISTINCT tour FROM $table ORDER BY tour ASC");
            set_transient($cache_key, $tours, HOUR_IN_SECONDS); // Caché por 1 hora
        }

        return $tours;
    }

    /**
     * Invalidar caché de tours
     */
    public static function invalidate_tours_cache() {
        delete_transient('rtt_tours_list');
    }

    /**
     * Obtener una reserva por ID
     */
    public static function get_reserva($id) {
        global $wpdb;

        $table = $wpdb->prefix . 'rtt_reservas';
        $reserva = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if ($reserva) {
            $reserva->pasajeros = self::get_pasajeros($id);
        }

        return $reserva;
    }

    /**
     * Obtener una reserva por código
     */
    public static function get_reserva_by_codigo($codigo) {
        global $wpdb;

        $table = $wpdb->prefix . 'rtt_reservas';
        $reserva = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE codigo = %s", $codigo));

        if ($reserva) {
            $reserva->pasajeros = self::get_pasajeros($reserva->id);
        }

        return $reserva;
    }

    /**
     * Obtener pasajeros de una reserva
     */
    public static function get_pasajeros($reserva_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'rtt_pasajeros';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE reserva_id = %d", $reserva_id));
    }

    /**
     * Actualizar estado de reserva
     */
    public static function update_estado($id, $estado) {
        global $wpdb;

        $estados_validos = ['pendiente', 'confirmada', 'pagada', 'completada', 'cancelada'];
        if (!in_array($estado, $estados_validos)) {
            return new WP_Error('invalid_status', 'Estado no válido');
        }

        $table = $wpdb->prefix . 'rtt_reservas';
        $result = $wpdb->update($table, ['estado' => $estado], ['id' => $id], ['%s'], ['%d']);

        if ($result !== false) {
            self::invalidate_stats_cache(); // Solo stats, no tours
        }

        return $result !== false;
    }

    /**
     * Actualizar notas de reserva
     */
    public static function update_notas($id, $notas) {
        global $wpdb;

        $table = $wpdb->prefix . 'rtt_reservas';
        $result = $wpdb->update($table, ['notas' => sanitize_textarea_field($notas)], ['id' => $id], ['%s'], ['%d']);

        return $result !== false;
    }

    /**
     * Eliminar reserva
     */
    public static function delete_reserva($id) {
        global $wpdb;

        // Eliminar pasajeros primero
        $table_pasajeros = $wpdb->prefix . 'rtt_pasajeros';
        $wpdb->delete($table_pasajeros, ['reserva_id' => $id], ['%d']);

        // Eliminar reserva
        $table_reservas = $wpdb->prefix . 'rtt_reservas';
        $result = $wpdb->delete($table_reservas, ['id' => $id], ['%d']);

        if ($result) {
            self::invalidate_all_cache();
        }

        return $result;
    }

    /**
     * Obtener estadísticas - Optimizado con una sola query y caché
     */
    public static function get_stats() {
        $cache_key = 'rtt_reservas_stats';
        $stats = get_transient($cache_key);

        if ($stats === false) {
            global $wpdb;
            $table = $wpdb->prefix . 'rtt_reservas';

            // Una sola query para todas las estadísticas
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                    SUM(CASE WHEN MONTH(fecha_creacion) = %d AND YEAR(fecha_creacion) = %d THEN 1 ELSE 0 END) as este_mes
                FROM $table",
                date('n'),
                date('Y')
            ));

            $stats = [
                'total' => intval($result->total ?? 0),
                'pendientes' => intval($result->pendientes ?? 0),
                'confirmadas' => intval($result->confirmadas ?? 0),
                'este_mes' => intval($result->este_mes ?? 0),
            ];

            set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS); // Caché por 5 minutos
        }

        return $stats;
    }

    /**
     * Invalidar caché de estadísticas
     */
    public static function invalidate_stats_cache() {
        delete_transient('rtt_reservas_stats');
    }

    /**
     * Invalidar todos los cachés (llamar al insertar/actualizar/eliminar)
     */
    public static function invalidate_all_cache() {
        self::invalidate_tours_cache();
        self::invalidate_stats_cache();
    }

    /**
     * Insertar evento de tracking del formulario
     */
    public static function insert_tracking($data) {
        global $wpdb;

        $table = $wpdb->prefix . 'rtt_form_tracking';

        $result = $wpdb->insert($table, [
            'session_id' => sanitize_text_field($data['session_id'] ?? ''),
            'ip' => sanitize_text_field($data['ip'] ?? ''),
            'page_url' => sanitize_url($data['page_url'] ?? ''),
            'page_title' => sanitize_text_field($data['page_title'] ?? ''),
            'step' => intval($data['step'] ?? 1),
            'event_type' => sanitize_text_field($data['event_type'] ?? 'view'),
            'tour_selected' => sanitize_text_field($data['tour_selected'] ?? ''),
            'fecha_selected' => !empty($data['fecha_selected']) ? sanitize_text_field($data['fecha_selected']) : null,
            'pasajeros_count' => !empty($data['pasajeros_count']) ? intval($data['pasajeros_count']) : null,
            'user_agent' => sanitize_text_field(substr($data['user_agent'] ?? '', 0, 500)),
            'referrer' => sanitize_url($data['referrer'] ?? ''),
            'lang' => sanitize_text_field($data['lang'] ?? 'es'),
        ], ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']);

        return $result !== false;
    }

    /**
     * Obtener estadísticas de tracking
     */
    public static function get_tracking_stats($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_form_tracking';

        $date_filter = $days > 0 ? $wpdb->prepare("AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days) : "";

        // Estadísticas por paso (funnel)
        $funnel = $wpdb->get_results("
            SELECT
                step,
                COUNT(DISTINCT session_id) as sessions,
                COUNT(*) as events
            FROM {$table}
            WHERE event_type IN ('step_view', 'form_open') {$date_filter}
            GROUP BY step
            ORDER BY step ASC
        ", ARRAY_A);

        // Abandonos por paso
        $abandons = $wpdb->get_results("
            SELECT
                step as last_step,
                COUNT(DISTINCT session_id) as abandoned_sessions
            FROM {$table} t1
            WHERE event_type IN ('step_view', 'form_open') {$date_filter}
            AND NOT EXISTS (
                SELECT 1 FROM {$table} t2
                WHERE t2.session_id = t1.session_id
                AND t2.event_type = 'form_submit'
            )
            AND step = (
                SELECT MAX(step) FROM {$table} t3
                WHERE t3.session_id = t1.session_id
            )
            GROUP BY step
            ORDER BY step ASC
        ", ARRAY_A);

        // Top páginas donde se abre el formulario
        $top_pages = $wpdb->get_results("
            SELECT
                page_url,
                page_title,
                COUNT(DISTINCT session_id) as sessions,
                COUNT(*) as opens
            FROM {$table}
            WHERE event_type = 'form_open' {$date_filter}
            GROUP BY page_url, page_title
            ORDER BY sessions DESC
            LIMIT 10
        ", ARRAY_A);

        // Sesiones recientes que no completaron
        $recent_abandoned = $wpdb->get_results("
            SELECT
                t1.session_id,
                t1.ip,
                t1.page_url,
                t1.page_title,
                MAX(t1.step) as last_step,
                MAX(t1.tour_selected) as tour,
                MAX(t1.pasajeros_count) as pasajeros,
                MIN(t1.created_at) as started_at,
                MAX(t1.created_at) as last_activity,
                t1.user_agent
            FROM {$table} t1
            WHERE t1.event_type IN ('step_view', 'form_open') {$date_filter}
            AND NOT EXISTS (
                SELECT 1 FROM {$table} t2
                WHERE t2.session_id = t1.session_id
                AND t2.event_type = 'form_submit'
            )
            GROUP BY t1.session_id, t1.ip, t1.page_url, t1.page_title, t1.user_agent
            ORDER BY last_activity DESC
            LIMIT 50
        ", ARRAY_A);

        // Tasa de conversión
        $total_starts = $wpdb->get_var("
            SELECT COUNT(DISTINCT session_id)
            FROM {$table}
            WHERE event_type = 'form_open' {$date_filter}
        ");

        $total_submits = $wpdb->get_var("
            SELECT COUNT(DISTINCT session_id)
            FROM {$table}
            WHERE event_type = 'form_submit' {$date_filter}
        ");

        return [
            'funnel' => $funnel ?: [],
            'abandons' => $abandons ?: [],
            'top_pages' => $top_pages ?: [],
            'recent_abandoned' => $recent_abandoned ?: [],
            'conversion' => [
                'starts' => (int)$total_starts,
                'submits' => (int)$total_submits,
                'rate' => $total_starts > 0 ? round(($total_submits / $total_starts) * 100, 1) : 0
            ]
        ];
    }

    /**
     * Limpiar tracking antiguo (más de 90 días)
     */
    public static function cleanup_old_tracking() {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_form_tracking';

        $wpdb->query("DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    }

    /**
     * Obtener eventos de tracking recientes (individuales)
     */
    public static function get_tracking_events($days = 7, $limit = 100) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_form_tracking';

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT
                id,
                session_id,
                ip,
                page_url,
                page_title,
                step,
                event_type,
                tour_selected,
                fecha_selected,
                pasajeros_count,
                user_agent,
                lang,
                created_at
            FROM {$table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY created_at DESC
            LIMIT %d
        ", $days, $limit), ARRAY_A);

        return $results ?: [];
    }
}
