<?php
/**
 * Plugin Name: RTT Reservas
 * Plugin URI: https://readytotravelperu.com
 * Description: Tour booking system with wizard form, PDF generation and email notifications.
 * Version: 1.3.0
 * Author: Ready To Travel Peru
 * Author URI: https://readytotravelperu.com
 * Text Domain: rtt-reservas
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('RTT_RESERVAS_VERSION', '1.3.0');
define('RTT_RESERVAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RTT_RESERVAS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RTT_RESERVAS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Clase principal del plugin
 */
final class RTT_Reservas {

    /**
     * Instancia única del plugin
     */
    private static $instance = null;

    /**
     * Obtener instancia única
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor privado
     */
    private function __construct() {
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Cargar dependencias
     */
    private function load_dependencies() {
        // Clases del plugin
        require_once RTT_RESERVAS_PLUGIN_DIR . 'includes/class-rtt-database.php';
        require_once RTT_RESERVAS_PLUGIN_DIR . 'includes/class-rtt-tours-cpt.php';
        require_once RTT_RESERVAS_PLUGIN_DIR . 'includes/class-rtt-tours.php';
        require_once RTT_RESERVAS_PLUGIN_DIR . 'includes/class-rtt-pdf.php';
        require_once RTT_RESERVAS_PLUGIN_DIR . 'includes/class-rtt-mail.php';
        require_once RTT_RESERVAS_PLUGIN_DIR . 'includes/class-rtt-shortcode.php';
        require_once RTT_RESERVAS_PLUGIN_DIR . 'includes/class-rtt-booking-button.php';
        require_once RTT_RESERVAS_PLUGIN_DIR . 'includes/class-rtt-ajax.php';
        require_once RTT_RESERVAS_PLUGIN_DIR . 'includes/class-rtt-admin.php';
        require_once RTT_RESERVAS_PLUGIN_DIR . 'includes/class-rtt-admin-reservas.php';
        require_once RTT_RESERVAS_PLUGIN_DIR . 'includes/class-rtt-admin-stats.php';
        require_once RTT_RESERVAS_PLUGIN_DIR . 'includes/class-rtt-manual.php';
    }

    /**
     * Configurar internacionalización
     */
    private function set_locale() {
        add_action('plugins_loaded', function() {
            load_plugin_textdomain(
                'rtt-reservas',
                false,
                dirname(RTT_RESERVAS_PLUGIN_BASENAME) . '/languages/'
            );
        });
    }

    /**
     * Hooks del admin
     */
    private function define_admin_hooks() {
        $admin = new RTT_Admin();
        add_action('admin_menu', [$admin, 'add_menu_page'], 9); // Prioridad 9 para que aparezca primero
        add_action('admin_init', [$admin, 'register_settings']);
        add_action('admin_enqueue_scripts', [$admin, 'enqueue_styles']);

        // Panel de reservas (prioridad 10 por defecto, después de configuración)
        $admin_reservas = new RTT_Admin_Reservas();
        $admin_reservas->init();

        // Custom Post Type de Tours
        $tours_cpt = new RTT_Tours_CPT();
        $tours_cpt->init();

        // Página de manual/documentación
        $manual = new RTT_Manual();
        $manual->init();

        // Panel de estadísticas
        new RTT_Admin_Stats();
    }

    /**
     * Hooks públicos
     */
    private function define_public_hooks() {
        // Registrar shortcode
        $shortcode = new RTT_Shortcode();
        add_shortcode('rtt_reserva', [$shortcode, 'render']);

        // Registrar botón de reserva con modal
        $booking_button = new RTT_Booking_Button();
        $booking_button->init();

        // Registrar assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);

        // Registrar AJAX handlers
        $ajax = new RTT_Ajax();
        add_action('wp_ajax_rtt_submit_reserva', [$ajax, 'submit_reserva']);
        add_action('wp_ajax_nopriv_rtt_submit_reserva', [$ajax, 'submit_reserva']);
        add_action('wp_ajax_rtt_get_tours', [$ajax, 'get_tours']);

        // Cron para envío de emails en segundo plano
        add_action('rtt_send_reservation_email', ['RTT_Ajax', 'send_reservation_email_cron']);
        add_action('wp_ajax_nopriv_rtt_get_tours', [$ajax, 'get_tours']);
    }

    /**
     * Registrar assets públicos
     */
    public function enqueue_public_assets() {
        // Solo cargar si hay shortcode en la página
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'rtt_reserva')) {

            $lang = $this->get_current_language();

            // MC Calendar CSS
            wp_enqueue_style(
                'mc-calendar-css',
                RTT_RESERVAS_PLUGIN_URL . 'assets/vendor/mc-calendar.min.css',
                [],
                RTT_RESERVAS_VERSION
            );

            // CSS principal
            wp_enqueue_style(
                'rtt-reservas-css',
                RTT_RESERVAS_PLUGIN_URL . 'assets/css/rtt-reservas.css',
                ['mc-calendar-css'],
                RTT_RESERVAS_VERSION
            );

            // MC Calendar JS (según idioma)
            $calendar_file = $lang === 'en' ? 'mc-calendar_e.min.js' : 'mc-calendar.min.js';
            wp_enqueue_script(
                'mc-calendar-js',
                RTT_RESERVAS_PLUGIN_URL . 'assets/vendor/' . $calendar_file,
                [],
                RTT_RESERVAS_VERSION,
                true
            );

            // JavaScript principal
            wp_enqueue_script(
                'rtt-reservas-js',
                RTT_RESERVAS_PLUGIN_URL . 'assets/js/rtt-reservas.js',
                ['jquery', 'mc-calendar-js'],
                RTT_RESERVAS_VERSION,
                true
            );

            // Obtener opciones
            $options = get_option('rtt_reservas_options', []);
            $max_passengers = isset($options['max_passengers']) ? absint($options['max_passengers']) : 20;

            // Pasar variables a JavaScript
            wp_localize_script('rtt-reservas-js', 'rttReservas', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rtt_reserva_nonce'),
                'lang' => $this->get_current_language(),
                'maxPassengers' => $max_passengers,
                'i18n' => $this->get_js_translations()
            ]);
        }
    }

    /**
     * Obtener idioma actual
     */
    private function get_current_language() {
        // Compatibilidad con WPML y Polylang
        if (defined('ICL_LANGUAGE_CODE')) {
            return ICL_LANGUAGE_CODE;
        }
        if (function_exists('pll_current_language')) {
            return pll_current_language();
        }
        return substr(get_locale(), 0, 2);
    }

    /**
     * Traducciones para JavaScript
     */
    private function get_js_translations() {
        return [
            'step1Title' => __('Tour y Fecha', 'rtt-reservas'),
            'step2Title' => __('Pasajeros', 'rtt-reservas'),
            'step3Title' => __('Representante', 'rtt-reservas'),
            'next' => __('Siguiente', 'rtt-reservas'),
            'previous' => __('Anterior', 'rtt-reservas'),
            'submit' => __('Enviar Reserva', 'rtt-reservas'),
            'processing' => __('Procesando...', 'rtt-reservas'),
            'success' => __('Reserva enviada correctamente. Revisa tu correo.', 'rtt-reservas'),
            'error' => __('Error al enviar la reserva. Intenta nuevamente.', 'rtt-reservas'),
            'requiredField' => __('Este campo es requerido', 'rtt-reservas'),
            'invalidEmail' => __('Email inválido', 'rtt-reservas'),
            'selectTour' => __('Selecciona un tour', 'rtt-reservas'),
            'selectDate' => __('Selecciona una fecha', 'rtt-reservas'),
            'addPassenger' => __('Agregar pasajero', 'rtt-reservas'),
            'removePassenger' => __('Eliminar', 'rtt-reservas'),
            'passenger' => __('Pasajero', 'rtt-reservas'),
            'docType' => __('Tipo de documento', 'rtt-reservas'),
            'docNumber' => __('Número de documento', 'rtt-reservas'),
            'fullName' => __('Nombres y apellidos', 'rtt-reservas'),
            'gender' => __('Género', 'rtt-reservas'),
            'male' => __('Masculino', 'rtt-reservas'),
            'female' => __('Femenino', 'rtt-reservas'),
            'birthDate' => __('Fecha de nacimiento', 'rtt-reservas'),
            'nationality' => __('Nacionalidad', 'rtt-reservas'),
            'allergies' => __('Alergias u observaciones', 'rtt-reservas'),
            'representativeName' => __('Nombre del representante', 'rtt-reservas'),
            'email' => __('Correo electrónico', 'rtt-reservas'),
            'phone' => __('Teléfono', 'rtt-reservas'),
            'country' => __('País', 'rtt-reservas'),
            'dni' => __('DNI', 'rtt-reservas'),
            'passport' => __('Pasaporte', 'rtt-reservas'),
            'minPassengers' => __('Debe agregar al menos un pasajero', 'rtt-reservas'),
            'selectPlan' => __('Debe seleccionar un plan', 'rtt-reservas'),
        ];
    }
}

/**
 * Activación del plugin
 */
function rtt_reservas_activate() {
    // Cargar clases necesarias
    require_once plugin_dir_path(__FILE__) . 'includes/class-rtt-database.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-rtt-tours-cpt.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-rtt-tours.php';

    // Crear tablas de base de datos
    RTT_Database::create_tables();

    // Registrar CPT antes de importar
    $tours_cpt = new RTT_Tours_CPT();
    $tours_cpt->register_post_type();

    // Importar tours por defecto
    RTT_Tours_CPT::import_default_tours();

    // Crear opciones por defecto
    $default_options = [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 465,
        'smtp_secure' => 'ssl',
        'smtp_user' => '',
        'smtp_pass' => '',
        'from_email' => '',
        'from_name' => 'Ready To Travel Peru',
        'cc_email' => '',
        'email_subject_es' => 'Confirmación de Reserva - Ready To Travel Peru',
        'email_subject_en' => 'Booking Confirmation - Ready To Travel Peru',
    ];

    add_option('rtt_reservas_options', $default_options);

    // Limpiar rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'rtt_reservas_activate');

/**
 * Verificar y actualizar tablas si es necesario
 */
function rtt_reservas_check_db() {
    // Cargar la clase primero
    require_once plugin_dir_path(__FILE__) . 'includes/class-rtt-database.php';

    if (get_option('rtt_db_version') !== RTT_Database::DB_VERSION) {
        RTT_Database::create_tables();
    }
}
add_action('plugins_loaded', 'rtt_reservas_check_db', 5);

/**
 * Desactivación del plugin
 */
function rtt_reservas_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'rtt_reservas_deactivate');

/**
 * Inicializar el plugin
 */
function rtt_reservas_init() {
    return RTT_Reservas::get_instance();
}
add_action('plugins_loaded', 'rtt_reservas_init');
