<?php
/**
 * Clase para manejar la base de datos de reservas
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_Database {

    const DB_VERSION = '1.2';

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
            KEY fecha_creacion (fecha_creacion)
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

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_reservas);
        dbDelta($sql_pasajeros);

        update_option('rtt_db_version', self::DB_VERSION);
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
        return $wpdb->delete($table_reservas, ['id' => $id], ['%d']);
    }

    /**
     * Obtener estadísticas
     */
    public static function get_stats() {
        global $wpdb;

        $table = $wpdb->prefix . 'rtt_reservas';

        $stats = [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'pendientes' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE estado = 'pendiente'"),
            'confirmadas' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE estado = 'confirmada'"),
            'este_mes' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE MONTH(fecha_creacion) = %d AND YEAR(fecha_creacion) = %d",
                date('n'),
                date('Y')
            )),
        ];

        return $stats;
    }
}
