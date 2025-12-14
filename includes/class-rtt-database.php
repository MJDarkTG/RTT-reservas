<?php
/**
 * Clase para manejar la base de datos de reservas
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_Database {

    const DB_VERSION = '1.7';

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
            email_sent_at datetime DEFAULT NULL,
            email_attempts int(11) NOT NULL DEFAULT 0,
            email_error text,
            fecha_creacion datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY codigo (codigo),
            KEY estado (estado),
            KEY fecha_tour (fecha_tour),
            KEY fecha_creacion (fecha_creacion),
            KEY tour (tour(100)),
            KEY email (email(100))
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

        // Tabla de cotizaciones
        $table_cotizaciones = $wpdb->prefix . 'rtt_cotizaciones';
        $sql_cotizaciones = "CREATE TABLE $table_cotizaciones (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            codigo varchar(50) NOT NULL,
            vendedor_id bigint(20) UNSIGNED NOT NULL,
            cliente_nombre varchar(255) NOT NULL,
            cliente_email varchar(100) NOT NULL,
            cliente_telefono varchar(50) DEFAULT NULL,
            cliente_pais varchar(100) DEFAULT NULL,
            tour varchar(255) NOT NULL,
            fecha_tour date NOT NULL,
            cantidad_pasajeros int(11) NOT NULL DEFAULT 1,
            precio_unitario decimal(10,2) NOT NULL DEFAULT 0,
            precio_total decimal(10,2) NOT NULL DEFAULT 0,
            descuento decimal(10,2) DEFAULT 0,
            descuento_tipo varchar(20) DEFAULT 'porcentaje',
            notas text,
            terminos text,
            formas_pago text,
            moneda varchar(10) DEFAULT 'USD',
            validez_dias int(11) DEFAULT 7,
            estado varchar(20) NOT NULL DEFAULT 'borrador',
            enviada_at datetime DEFAULT NULL,
            aceptada_at datetime DEFAULT NULL,
            reserva_id bigint(20) UNSIGNED DEFAULT NULL,
            fecha_creacion datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY codigo (codigo),
            KEY vendedor_id (vendedor_id),
            KEY estado (estado),
            KEY cliente_email (cliente_email(100)),
            KEY fecha_tour (fecha_tour)
        ) $charset_collate;";

        // Tabla de proveedores
        $table_proveedores = $wpdb->prefix . 'rtt_proveedores';
        $sql_proveedores = "CREATE TABLE $table_proveedores (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tipo varchar(50) NOT NULL,
            nombre varchar(255) NOT NULL,
            contacto varchar(255) DEFAULT NULL,
            telefono varchar(50) DEFAULT NULL,
            email varchar(100) DEFAULT NULL,
            costo_base decimal(10,2) DEFAULT 0,
            moneda varchar(10) DEFAULT 'PEN',
            notas text,
            activo tinyint(1) DEFAULT 1,
            fecha_creacion datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY tipo (tipo),
            KEY activo (activo)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_reservas);
        dbDelta($sql_pasajeros);
        dbDelta($sql_tracking);
        dbDelta($sql_cotizaciones);
        dbDelta($sql_proveedores);

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

        $estados_validos = rtt_get_valid_statuses();
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
     * Marcar email como enviado
     */
    public static function update_email_sent($id) {
        global $wpdb;

        $table = $wpdb->prefix . 'rtt_reservas';
        $peru_tz = new DateTimeZone('America/Lima');
        $now = new DateTime('now', $peru_tz);

        $result = $wpdb->update(
            $table,
            [
                'email_sent_at' => $now->format('Y-m-d H:i:s'),
                'email_error' => null
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Actualizar intentos de envío de email
     */
    public static function update_email_attempts($id, $attempts) {
        global $wpdb;

        $table = $wpdb->prefix . 'rtt_reservas';
        $result = $wpdb->update($table, ['email_attempts' => $attempts], ['id' => $id], ['%d'], ['%d']);

        return $result !== false;
    }

    /**
     * Actualizar error de email
     */
    public static function update_email_error($id, $error) {
        global $wpdb;

        $table = $wpdb->prefix . 'rtt_reservas';
        $result = $wpdb->update($table, ['email_error' => sanitize_text_field($error)], ['id' => $id], ['%s'], ['%d']);

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

    /**
     * Obtener reservas para el calendario
     */
    public static function get_reservas_for_calendar($start_date, $end_date, $tour_filter = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_reservas';

        $where = "fecha_tour BETWEEN %s AND %s";
        $values = [$start_date, $end_date];

        if (!empty($tour_filter)) {
            $where .= " AND tour LIKE %s";
            $values[] = '%' . $wpdb->esc_like($tour_filter) . '%';
        }

        $sql = "SELECT
                    id,
                    codigo,
                    tour,
                    fecha_tour,
                    nombre_representante,
                    email,
                    cantidad_pasajeros,
                    estado,
                    fecha_creacion
                FROM {$table}
                WHERE {$where}
                ORDER BY fecha_tour ASC, tour ASC";

        return $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
    }

    /**
     * Obtener resumen de reservas por día para el calendario
     */
    public static function get_calendar_summary($start_date, $end_date) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_reservas';

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT
                fecha_tour,
                COUNT(*) as total_reservas,
                SUM(cantidad_pasajeros) as total_pasajeros,
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN estado = 'pagada' THEN 1 ELSE 0 END) as pagadas,
                SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                GROUP_CONCAT(DISTINCT tour SEPARATOR '|') as tours
            FROM {$table}
            WHERE fecha_tour BETWEEN %s AND %s
            GROUP BY fecha_tour
            ORDER BY fecha_tour ASC
        ", $start_date, $end_date), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Obtener alertas de tours próximos sin confirmación
     */
    public static function get_upcoming_pending_alerts($days_ahead = 7) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_reservas';

        $today = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$days_ahead} days"));

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT
                id,
                codigo,
                tour,
                fecha_tour,
                nombre_representante,
                email,
                cantidad_pasajeros,
                estado,
                DATEDIFF(fecha_tour, %s) as dias_restantes
            FROM {$table}
            WHERE fecha_tour BETWEEN %s AND %s
            AND estado = 'pendiente'
            ORDER BY fecha_tour ASC
        ", $today, $today, $end_date), ARRAY_A);

        return $results ?: [];
    }

    // ==========================================
    // COTIZACIONES
    // ==========================================

    /**
     * Generar código único de cotización
     */
    public static function generate_cotizacion_codigo() {
        return 'COT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
    }

    /**
     * Insertar nueva cotización
     */
    public static function insert_cotizacion($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_cotizaciones';
        $codigo = self::generate_cotizacion_codigo();

        $result = $wpdb->insert($table, [
            'codigo' => $codigo,
            'vendedor_id' => intval($data['vendedor_id']),
            'cliente_nombre' => sanitize_text_field($data['cliente_nombre']),
            'cliente_email' => sanitize_email($data['cliente_email']),
            'cliente_telefono' => sanitize_text_field($data['cliente_telefono'] ?? ''),
            'cliente_pais' => sanitize_text_field($data['cliente_pais'] ?? ''),
            'tour' => sanitize_text_field($data['tour']),
            'fecha_tour' => sanitize_text_field($data['fecha_tour']),
            'cantidad_pasajeros' => intval($data['cantidad_pasajeros']),
            'precio_unitario' => floatval($data['precio_unitario']),
            'precio_total' => floatval($data['precio_total']),
            'descuento' => floatval($data['descuento'] ?? 0),
            'descuento_tipo' => sanitize_text_field($data['descuento_tipo'] ?? 'porcentaje'),
            'notas' => sanitize_textarea_field($data['notas'] ?? ''),
            'terminos' => wp_kses_post($data['terminos'] ?? ''),
            'formas_pago' => wp_kses_post($data['formas_pago'] ?? ''),
            'moneda' => sanitize_text_field($data['moneda'] ?? 'USD'),
            'validez_dias' => intval($data['validez_dias'] ?? 7),
            'estado' => 'borrador',
        ]);

        if ($result === false) {
            return new WP_Error('db_error', 'Error al guardar la cotización');
        }

        return [
            'id' => $wpdb->insert_id,
            'codigo' => $codigo
        ];
    }

    /**
     * Actualizar cotización
     */
    public static function update_cotizacion($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_cotizaciones';

        $update_data = [];
        $format = [];

        $fields = [
            'cliente_nombre' => '%s',
            'cliente_email' => '%s',
            'cliente_telefono' => '%s',
            'cliente_pais' => '%s',
            'tour' => '%s',
            'fecha_tour' => '%s',
            'cantidad_pasajeros' => '%d',
            'precio_unitario' => '%f',
            'precio_total' => '%f',
            'descuento' => '%f',
            'descuento_tipo' => '%s',
            'notas' => '%s',
            'terminos' => '%s',
            'formas_pago' => '%s',
            'moneda' => '%s',
            'validez_dias' => '%d',
            'estado' => '%s',
        ];

        foreach ($fields as $field => $fmt) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
                $format[] = $fmt;
            }
        }

        if (empty($update_data)) {
            return false;
        }

        return $wpdb->update($table, $update_data, ['id' => $id], $format, ['%d']) !== false;
    }

    /**
     * Obtener cotización por ID
     */
    public static function get_cotizacion($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_cotizaciones';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    /**
     * Obtener cotización por código
     */
    public static function get_cotizacion_by_codigo($codigo) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_cotizaciones';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE codigo = %s", $codigo));
    }

    /**
     * Obtener cotizaciones con filtros
     */
    public static function get_cotizaciones($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_cotizaciones';

        $defaults = [
            'vendedor_id' => 0,
            'estado' => '',
            'buscar' => '',
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'fecha_creacion',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $values = [];

        if (!empty($args['vendedor_id'])) {
            $where .= ' AND vendedor_id = %d';
            $values[] = $args['vendedor_id'];
        }

        if (!empty($args['estado'])) {
            $where .= ' AND estado = %s';
            $values[] = $args['estado'];
        }

        if (!empty($args['buscar'])) {
            $where .= ' AND (codigo LIKE %s OR cliente_nombre LIKE %s OR cliente_email LIKE %s OR tour LIKE %s)';
            $search = '%' . $wpdb->esc_like($args['buscar']) . '%';
            $values = array_merge($values, [$search, $search, $search, $search]);
        }

        $orderby = in_array($args['orderby'], ['fecha_creacion', 'fecha_tour', 'codigo', 'estado', 'precio_total'])
            ? $args['orderby'] : 'fecha_creacion';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $offset = ($args['page'] - 1) * $args['per_page'];

        $sql = "SELECT * FROM $table WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $values[] = $args['per_page'];
        $values[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($sql, $values));

        // Total para paginación
        $sql_count = "SELECT COUNT(*) FROM $table WHERE $where";
        $count_values = array_slice($values, 0, -2);
        $total = empty($count_values)
            ? $wpdb->get_var($sql_count)
            : $wpdb->get_var($wpdb->prepare($sql_count, $count_values));

        return [
            'items' => $results,
            'total' => intval($total),
            'pages' => ceil($total / $args['per_page'])
        ];
    }

    /**
     * Marcar cotización como enviada
     */
    public static function mark_cotizacion_enviada($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_cotizaciones';
        $peru_tz = new DateTimeZone('America/Lima');
        $now = new DateTime('now', $peru_tz);

        return $wpdb->update(
            $table,
            [
                'estado' => 'enviada',
                'enviada_at' => $now->format('Y-m-d H:i:s')
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        ) !== false;
    }

    /**
     * Marcar cotización como aceptada
     */
    public static function mark_cotizacion_aceptada($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_cotizaciones';
        $peru_tz = new DateTimeZone('America/Lima');
        $now = new DateTime('now', $peru_tz);

        return $wpdb->update(
            $table,
            [
                'estado' => 'aceptada',
                'aceptada_at' => $now->format('Y-m-d H:i:s')
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        ) !== false;
    }

    /**
     * Eliminar cotización
     */
    public static function delete_cotizacion($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_cotizaciones';
        return $wpdb->delete($table, ['id' => $id], ['%d']);
    }

    /**
     * Estadísticas de cotizaciones por vendedor
     */
    public static function get_cotizaciones_stats($vendedor_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_cotizaciones';

        $where = $vendedor_id ? $wpdb->prepare("WHERE vendedor_id = %d", $vendedor_id) : "";

        $result = $wpdb->get_row("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'borrador' THEN 1 ELSE 0 END) as borradores,
                SUM(CASE WHEN estado = 'enviada' THEN 1 ELSE 0 END) as enviadas,
                SUM(CASE WHEN estado = 'aceptada' THEN 1 ELSE 0 END) as aceptadas,
                SUM(CASE WHEN estado = 'vencida' THEN 1 ELSE 0 END) as vencidas,
                SUM(CASE WHEN estado = 'aceptada' THEN precio_total ELSE 0 END) as total_aceptado
            FROM {$table}
            {$where}
        ");

        return [
            'total' => intval($result->total ?? 0),
            'borradores' => intval($result->borradores ?? 0),
            'enviadas' => intval($result->enviadas ?? 0),
            'aceptadas' => intval($result->aceptadas ?? 0),
            'vencidas' => intval($result->vencidas ?? 0),
            'total_aceptado' => floatval($result->total_aceptado ?? 0),
        ];
    }

    // ==========================================
    // PROVEEDORES
    // ==========================================

    /**
     * Tipos de proveedores predefinidos
     */
    public static function get_tipos_proveedores() {
        return [
            'guia' => 'Guía',
            'transporte' => 'Transporte',
            'hotel' => 'Hotel',
            'restaurante' => 'Restaurante',
            'entrada' => 'Entradas',
            'otro' => 'Otro',
        ];
    }

    /**
     * Insertar proveedor
     */
    public static function insert_proveedor($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_proveedores';

        $result = $wpdb->insert($table, [
            'tipo' => sanitize_text_field($data['tipo']),
            'nombre' => sanitize_text_field($data['nombre']),
            'contacto' => sanitize_text_field($data['contacto'] ?? ''),
            'telefono' => sanitize_text_field($data['telefono'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'costo_base' => floatval($data['costo_base'] ?? 0),
            'moneda' => sanitize_text_field($data['moneda'] ?? 'PEN'),
            'notas' => sanitize_textarea_field($data['notas'] ?? ''),
            'activo' => isset($data['activo']) ? intval($data['activo']) : 1,
        ]);

        if ($result === false) {
            return new WP_Error('db_error', 'Error al guardar el proveedor');
        }

        return $wpdb->insert_id;
    }

    /**
     * Actualizar proveedor
     */
    public static function update_proveedor($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_proveedores';

        $update_data = [];
        $format = [];

        $fields = [
            'tipo' => '%s',
            'nombre' => '%s',
            'contacto' => '%s',
            'telefono' => '%s',
            'email' => '%s',
            'costo_base' => '%f',
            'moneda' => '%s',
            'notas' => '%s',
            'activo' => '%d',
        ];

        foreach ($fields as $field => $fmt) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
                $format[] = $fmt;
            }
        }

        if (empty($update_data)) {
            return false;
        }

        return $wpdb->update($table, $update_data, ['id' => $id], $format, ['%d']) !== false;
    }

    /**
     * Obtener proveedor por ID
     */
    public static function get_proveedor($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_proveedores';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    /**
     * Obtener proveedores con filtros
     */
    public static function get_proveedores($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_proveedores';

        $defaults = [
            'tipo' => '',
            'activo' => null,
            'buscar' => '',
            'orderby' => 'nombre',
            'order' => 'ASC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $values = [];

        if (!empty($args['tipo'])) {
            $where .= ' AND tipo = %s';
            $values[] = $args['tipo'];
        }

        if ($args['activo'] !== null) {
            $where .= ' AND activo = %d';
            $values[] = $args['activo'];
        }

        if (!empty($args['buscar'])) {
            $where .= ' AND (nombre LIKE %s OR contacto LIKE %s)';
            $search = '%' . $wpdb->esc_like($args['buscar']) . '%';
            $values[] = $search;
            $values[] = $search;
        }

        $orderby = in_array($args['orderby'], ['nombre', 'tipo', 'costo_base', 'fecha_creacion'])
            ? $args['orderby'] : 'nombre';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT * FROM $table WHERE $where ORDER BY $orderby $order";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Obtener proveedores por tipo
     */
    public static function get_proveedores_by_tipo($tipo) {
        return self::get_proveedores(['tipo' => $tipo, 'activo' => 1]);
    }

    /**
     * Eliminar proveedor
     */
    public static function delete_proveedor($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtt_proveedores';
        return $wpdb->delete($table, ['id' => $id], ['%d']);
    }
}
