<?php
/**
 * Panel de Vendedor - Sistema de Cotizaciones
 * Página independiente con login propio (fuera del admin de WP)
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_Seller_Panel {

    /**
     * Slug de la página
     */
    const PAGE_SLUG = 'vendedor';

    /**
     * Rol del vendedor
     */
    const SELLER_ROLE = 'rtt_vendedor';

    /**
     * Inicializar
     */
    public function init() {
        // Crear rol al activar
        add_action('init', [$this, 'register_seller_role']);

        // Registrar rewrite rules
        add_action('init', [$this, 'add_rewrite_rules']);

        // Manejar la página del panel
        add_action('template_redirect', [$this, 'handle_panel_page']);

        // AJAX handlers
        add_action('wp_ajax_rtt_seller_login', [$this, 'ajax_login']);
        add_action('wp_ajax_nopriv_rtt_seller_login', [$this, 'ajax_login']);
        add_action('wp_ajax_rtt_seller_logout', [$this, 'ajax_logout']);
        add_action('wp_ajax_rtt_save_cotizacion', [$this, 'ajax_save_cotizacion']);
        add_action('wp_ajax_rtt_send_cotizacion', [$this, 'ajax_send_cotizacion']);
        add_action('wp_ajax_rtt_delete_cotizacion', [$this, 'ajax_delete_cotizacion']);
        add_action('wp_ajax_rtt_get_cotizacion', [$this, 'ajax_get_cotizacion']);
        add_action('wp_ajax_rtt_preview_cotizacion_pdf', [$this, 'ajax_preview_pdf']);
    }

    /**
     * Registrar rol de vendedor
     */
    public function register_seller_role() {
        if (!get_role(self::SELLER_ROLE)) {
            add_role(self::SELLER_ROLE, 'Vendedor RTT', [
                'read' => true,
            ]);
        }
    }

    /**
     * Agregar rewrite rules
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^' . self::PAGE_SLUG . '/?$',
            'index.php?rtt_seller_panel=1',
            'top'
        );
        add_rewrite_rule(
            '^' . self::PAGE_SLUG . '/([^/]+)/?$',
            'index.php?rtt_seller_panel=1&rtt_seller_action=$matches[1]',
            'top'
        );

        add_rewrite_tag('%rtt_seller_panel%', '1');
        add_rewrite_tag('%rtt_seller_action%', '([^/]+)');
    }

    /**
     * Verificar si el usuario actual puede acceder al panel
     */
    public function can_access_panel() {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        return in_array(self::SELLER_ROLE, $user->roles) ||
               in_array('administrator', $user->roles);
    }

    /**
     * Manejar la página del panel
     */
    public function handle_panel_page() {
        if (!get_query_var('rtt_seller_panel')) {
            return;
        }

        // No indexar
        header('X-Robots-Tag: noindex, nofollow', true);

        $action = get_query_var('rtt_seller_action', 'dashboard');

        // Si no está logueado, mostrar login
        if (!$this->can_access_panel()) {
            $this->render_login_page();
            exit;
        }

        // Renderizar página según acción
        switch ($action) {
            case 'nueva':
                $this->render_nueva_cotizacion();
                break;
            case 'editar':
                $this->render_editar_cotizacion();
                break;
            case 'ver':
                $this->render_ver_cotizacion();
                break;
            case 'lista':
                $this->render_lista_cotizaciones();
                break;
            case 'logout':
                wp_logout();
                wp_redirect(home_url('/' . self::PAGE_SLUG . '/'));
                exit;
            default:
                $this->render_dashboard();
        }
        exit;
    }

    /**
     * AJAX: Login
     */
    public function ajax_login() {
        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => 'Ingresa email y contraseña']);
        }

        // Buscar usuario por email
        $user = get_user_by('email', $email);
        if (!$user) {
            wp_send_json_error(['message' => 'Credenciales incorrectas']);
        }

        // Verificar que sea vendedor o admin
        if (!in_array(self::SELLER_ROLE, $user->roles) && !in_array('administrator', $user->roles)) {
            wp_send_json_error(['message' => 'No tienes acceso al panel de vendedor']);
        }

        // Autenticar
        $auth = wp_authenticate($user->user_login, $password);
        if (is_wp_error($auth)) {
            wp_send_json_error(['message' => 'Credenciales incorrectas']);
        }

        // Login exitoso
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        wp_send_json_success([
            'message' => 'Login exitoso',
            'redirect' => home_url('/' . self::PAGE_SLUG . '/')
        ]);
    }

    /**
     * AJAX: Logout
     */
    public function ajax_logout() {
        wp_logout();
        wp_send_json_success(['redirect' => home_url('/' . self::PAGE_SLUG . '/')]);
    }

    /**
     * AJAX: Guardar cotización
     */
    public function ajax_save_cotizacion() {
        if (!$this->can_access_panel()) {
            wp_send_json_error(['message' => 'Sin acceso']);
        }

        $id = intval($_POST['id'] ?? 0);
        $user = wp_get_current_user();

        $data = [
            'vendedor_id' => $user->ID,
            'cliente_nombre' => sanitize_text_field($_POST['cliente_nombre'] ?? ''),
            'cliente_email' => sanitize_email($_POST['cliente_email'] ?? ''),
            'cliente_telefono' => sanitize_text_field($_POST['cliente_telefono'] ?? ''),
            'cliente_pais' => sanitize_text_field($_POST['cliente_pais'] ?? ''),
            'tour' => sanitize_text_field($_POST['tour'] ?? ''),
            'fecha_tour' => sanitize_text_field($_POST['fecha_tour'] ?? ''),
            'cantidad_pasajeros' => intval($_POST['cantidad_pasajeros'] ?? 1),
            'precio_unitario' => floatval($_POST['precio_unitario'] ?? 0),
            'precio_total' => floatval($_POST['precio_total'] ?? 0),
            'descuento' => floatval($_POST['descuento'] ?? 0),
            'descuento_tipo' => sanitize_text_field($_POST['descuento_tipo'] ?? 'porcentaje'),
            'notas' => sanitize_textarea_field($_POST['notas'] ?? ''),
            'moneda' => sanitize_text_field($_POST['moneda'] ?? 'USD'),
            'validez_dias' => intval($_POST['validez_dias'] ?? 7),
        ];

        // Validar campos requeridos
        if (empty($data['cliente_nombre']) || empty($data['cliente_email']) || empty($data['tour'])) {
            wp_send_json_error(['message' => 'Completa los campos requeridos']);
        }

        if ($id > 0) {
            // Actualizar
            $result = RTT_Database::update_cotizacion($id, $data);
            if ($result) {
                wp_send_json_success(['message' => 'Cotización actualizada', 'id' => $id]);
            }
        } else {
            // Insertar
            $result = RTT_Database::insert_cotizacion($data);
            if (!is_wp_error($result)) {
                wp_send_json_success([
                    'message' => 'Cotización guardada',
                    'id' => $result['id'],
                    'codigo' => $result['codigo']
                ]);
            }
        }

        wp_send_json_error(['message' => 'Error al guardar la cotización']);
    }

    /**
     * AJAX: Enviar cotización por email
     */
    public function ajax_send_cotizacion() {
        if (!$this->can_access_panel()) {
            wp_send_json_error(['message' => 'Sin acceso']);
        }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(['message' => 'ID inválido']);
        }

        $cotizacion = RTT_Database::get_cotizacion($id);
        if (!$cotizacion) {
            wp_send_json_error(['message' => 'Cotización no encontrada']);
        }

        // Generar PDF
        $pdf_path = RTT_Cotizacion_PDF::generate($cotizacion);

        // Enviar email
        $sent = $this->send_cotizacion_email($cotizacion, $pdf_path);

        // Eliminar PDF temporal
        if (file_exists($pdf_path)) {
            unlink($pdf_path);
        }

        if ($sent) {
            RTT_Database::mark_cotizacion_enviada($id);
            wp_send_json_success(['message' => 'Cotización enviada al cliente']);
        } else {
            wp_send_json_error(['message' => 'Error al enviar el email']);
        }
    }

    /**
     * Preview PDF de cotización
     */
    public function ajax_preview_pdf() {
        if (!$this->can_access_panel()) {
            wp_die('Sin acceso');
        }

        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            wp_die('ID inválido');
        }

        $cotizacion = RTT_Database::get_cotizacion($id);
        if (!$cotizacion) {
            wp_die('Cotización no encontrada');
        }

        // Generar PDF y mostrarlo en el navegador
        $pdf_path = RTT_Cotizacion_PDF::generate($cotizacion);

        if (file_exists($pdf_path)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="cotizacion-' . $cotizacion->codigo . '.pdf"');
            header('Content-Length: ' . filesize($pdf_path));
            readfile($pdf_path);
            unlink($pdf_path);
        } else {
            wp_die('Error al generar el PDF');
        }
        exit;
    }

    /**
     * Enviar email con cotización
     */
    private function send_cotizacion_email($cotizacion, $pdf_path) {
        $options = get_option('rtt_reservas_options', []);

        $to = $cotizacion->cliente_email;
        $subject = sprintf('Cotización %s - %s', $cotizacion->codigo, $cotizacion->tour);

        $message = $this->get_cotizacion_email_template($cotizacion);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        if (!empty($options['from_name']) && !empty($options['from_email'])) {
            $headers[] = 'From: ' . $options['from_name'] . ' <' . $options['from_email'] . '>';
        }

        if (!empty($options['cc_email'])) {
            $headers[] = 'Cc: ' . $options['cc_email'];
        }

        $attachments = [];
        if (file_exists($pdf_path)) {
            $attachments[] = $pdf_path;
        }

        return wp_mail($to, $subject, $message, $headers, $attachments);
    }

    /**
     * Template de email para cotización
     */
    private function get_cotizacion_email_template($cotizacion) {
        $options = get_option('rtt_reservas_options', []);
        $logo = $options['email_logo_url'] ?? '';
        $fecha_tour = date_i18n('d/m/Y', strtotime($cotizacion->fecha_tour));
        $validez = date_i18n('d/m/Y', strtotime("+{$cotizacion->validez_dias} days"));

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; }
                .header { background: linear-gradient(135deg, #004070, #27AE60); padding: 20px; text-align: center; }
                .header img { max-width: 200px; }
                .content { padding: 30px; background: #fff; }
                .footer { padding: 20px; background: #f5f5f5; text-align: center; font-size: 12px; }
                .precio { font-size: 24px; color: #27AE60; font-weight: bold; }
                .detalle { background: #f9f9f9; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .btn { display: inline-block; padding: 12px 30px; background: #27AE60; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <?php if ($logo): ?>
                    <img src="<?php echo esc_url($logo); ?>" alt="Logo">
                    <?php endif; ?>
                </div>
                <div class="content">
                    <h2>Hola <?php echo esc_html($cotizacion->cliente_nombre); ?>,</h2>
                    <p>Gracias por tu interés en nuestros servicios. Adjunto encontrarás la cotización solicitada.</p>

                    <div class="detalle">
                        <p><strong>Cotización:</strong> <?php echo esc_html($cotizacion->codigo); ?></p>
                        <p><strong>Tour:</strong> <?php echo esc_html($cotizacion->tour); ?></p>
                        <p><strong>Fecha:</strong> <?php echo $fecha_tour; ?></p>
                        <p><strong>Pasajeros:</strong> <?php echo esc_html($cotizacion->cantidad_pasajeros); ?></p>
                        <p class="precio">Total: <?php echo esc_html($cotizacion->moneda); ?> <?php echo number_format($cotizacion->precio_total, 2); ?></p>
                    </div>

                    <p><strong>Validez de la cotización:</strong> <?php echo $validez; ?></p>

                    <?php if (!empty($cotizacion->notas)): ?>
                    <p><strong>Notas:</strong><br><?php echo nl2br(esc_html($cotizacion->notas)); ?></p>
                    <?php endif; ?>

                    <p>Para confirmar tu reserva, por favor revisa el PDF adjunto donde encontrarás las formas de pago y términos.</p>

                    <p>¿Tienes preguntas? Responde a este correo o contáctanos por WhatsApp.</p>
                </div>
                <div class="footer">
                    <p><?php echo esc_html($options['email_website'] ?? 'www.readytotravelperu.com'); ?></p>
                    <p>WhatsApp: <?php echo esc_html($options['email_whatsapp'] ?? ''); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Eliminar cotización
     */
    public function ajax_delete_cotizacion() {
        if (!$this->can_access_panel()) {
            wp_send_json_error(['message' => 'Sin acceso']);
        }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(['message' => 'ID inválido']);
        }

        $cotizacion = RTT_Database::get_cotizacion($id);
        if (!$cotizacion) {
            wp_send_json_error(['message' => 'Cotización no encontrada']);
        }

        // Solo puede eliminar sus propias cotizaciones (excepto admin)
        $user = wp_get_current_user();
        if ($cotizacion->vendedor_id != $user->ID && !in_array('administrator', $user->roles)) {
            wp_send_json_error(['message' => 'No puedes eliminar esta cotización']);
        }

        if (RTT_Database::delete_cotizacion($id)) {
            wp_send_json_success(['message' => 'Cotización eliminada']);
        }

        wp_send_json_error(['message' => 'Error al eliminar']);
    }

    /**
     * AJAX: Obtener cotización
     */
    public function ajax_get_cotizacion() {
        if (!$this->can_access_panel()) {
            wp_send_json_error(['message' => 'Sin acceso']);
        }

        $id = intval($_POST['id'] ?? 0);
        $cotizacion = RTT_Database::get_cotizacion($id);

        if ($cotizacion) {
            wp_send_json_success(['cotizacion' => $cotizacion]);
        }

        wp_send_json_error(['message' => 'No encontrada']);
    }

    /**
     * Header común del panel
     */
    private function render_header($title = 'Panel de Vendedor') {
        $user = wp_get_current_user();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title><?php echo esc_html($title); ?> - RTT Reservas</title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
            <style>
                <?php echo $this->get_panel_styles(); ?>
            </style>
        </head>
        <body>
            <nav class="navbar">
                <div class="nav-brand">
                    <span class="brand-icon">RTT</span>
                    <span>Panel de Vendedor</span>
                </div>
                <div class="nav-menu">
                    <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/'); ?>" class="nav-link">Dashboard</a>
                    <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/nueva/'); ?>" class="nav-link">Nueva Cotización</a>
                    <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/lista/'); ?>" class="nav-link">Mis Cotizaciones</a>
                </div>
                <div class="nav-user">
                    <span><?php echo esc_html($user->display_name); ?></span>
                    <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/logout/'); ?>" class="btn-logout">Salir</a>
                </div>
            </nav>
            <main class="main-content">
        <?php
    }

    /**
     * Footer común del panel
     */
    private function render_footer($extra_scripts = '') {
        ?>
            </main>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script>
                var rttAjax = {
                    url: '<?php echo admin_url('admin-ajax.php'); ?>'
                };
            </script>
            <?php if (!empty($extra_scripts)): ?>
            <script>
                jQuery(document).ready(function($) {
                    <?php echo $extra_scripts; ?>
                });
            </script>
            <?php endif; ?>
            <?php echo $this->get_panel_scripts(); ?>
        </body>
        </html>
        <?php
    }

    /**
     * Página de Login
     */
    private function render_login_page() {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Iniciar Sesión - RTT Vendedor</title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
            <style>
                <?php echo $this->get_login_styles(); ?>
            </style>
        </head>
        <body>
            <div class="login-container">
                <div class="login-box">
                    <div class="login-header">
                        <div class="brand-icon">RTT</div>
                        <h1>Panel de Vendedor</h1>
                        <p>Ready To Travel Peru</p>
                    </div>
                    <form id="login-form" class="login-form">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required placeholder="tu@email.com">
                        </div>
                        <div class="form-group">
                            <label for="password">Contraseña</label>
                            <input type="password" id="password" name="password" required placeholder="••••••••">
                        </div>
                        <button type="submit" class="btn-login">Iniciar Sesión</button>
                        <div id="login-error" class="error-message" style="display: none;"></div>
                    </form>
                </div>
            </div>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script>
                $('#login-form').on('submit', function(e) {
                    e.preventDefault();
                    var btn = $(this).find('button');
                    btn.prop('disabled', true).text('Ingresando...');
                    $('#login-error').hide();

                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
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
            </script>
        </body>
        </html>
        <?php
    }

    /**
     * Dashboard
     */
    private function render_dashboard() {
        $user = wp_get_current_user();
        $stats = RTT_Database::get_cotizaciones_stats($user->ID);
        $cotizaciones = RTT_Database::get_cotizaciones([
            'vendedor_id' => in_array('administrator', $user->roles) ? 0 : $user->ID,
            'per_page' => 5,
            'orderby' => 'fecha_creacion',
            'order' => 'DESC'
        ]);

        $this->render_header('Dashboard');
        ?>
        <div class="dashboard">
            <h1>Bienvenido, <?php echo esc_html($user->display_name); ?></h1>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Cotizaciones</div>
                </div>
                <div class="stat-card stat-warning">
                    <div class="stat-number"><?php echo $stats['borradores']; ?></div>
                    <div class="stat-label">Borradores</div>
                </div>
                <div class="stat-card stat-info">
                    <div class="stat-number"><?php echo $stats['enviadas']; ?></div>
                    <div class="stat-label">Enviadas</div>
                </div>
                <div class="stat-card stat-success">
                    <div class="stat-number"><?php echo $stats['aceptadas']; ?></div>
                    <div class="stat-label">Aceptadas</div>
                </div>
            </div>

            <div class="section">
                <div class="section-header">
                    <h2>Cotizaciones Recientes</h2>
                    <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/nueva/'); ?>" class="btn btn-primary">+ Nueva Cotización</a>
                </div>

                <?php if (empty($cotizaciones['items'])): ?>
                <div class="empty-state">
                    <p>No tienes cotizaciones aún.</p>
                    <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/nueva/'); ?>" class="btn btn-primary">Crear primera cotización</a>
                </div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Tour</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cotizaciones['items'] as $cot): ?>
                        <tr>
                            <td><strong><?php echo esc_html($cot->codigo); ?></strong></td>
                            <td><?php echo esc_html($cot->cliente_nombre); ?></td>
                            <td><?php echo esc_html(substr($cot->tour, 0, 30)); ?>...</td>
                            <td><?php echo esc_html($cot->moneda); ?> <?php echo number_format($cot->precio_total, 2); ?></td>
                            <td><span class="badge badge-<?php echo esc_attr($cot->estado); ?>"><?php echo esc_html(ucfirst($cot->estado)); ?></span></td>
                            <td><?php echo date_i18n('d/m/Y', strtotime($cot->fecha_creacion)); ?></td>
                            <td>
                                <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/editar/?id=' . $cot->id); ?>" class="btn btn-sm">Editar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $this->render_footer();
    }

    /**
     * Nueva Cotización
     */
    private function render_nueva_cotizacion() {
        $tours = RTT_Tours::get_tours();
        $options = get_option('rtt_reservas_options', []);

        $this->render_header('Nueva Cotización');
        ?>
        <div class="form-container">
            <h1>Nueva Cotización</h1>

            <form id="cotizacion-form" class="cotizacion-form">
                <input type="hidden" name="id" value="0">

                <div class="form-section">
                    <h3>Datos del Cliente</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cliente_nombre">Nombre completo *</label>
                            <input type="text" id="cliente_nombre" name="cliente_nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="cliente_email">Email *</label>
                            <input type="email" id="cliente_email" name="cliente_email" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cliente_telefono">Teléfono</label>
                            <input type="text" id="cliente_telefono" name="cliente_telefono">
                        </div>
                        <div class="form-group">
                            <label for="cliente_pais">País</label>
                            <input type="text" id="cliente_pais" name="cliente_pais">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Detalles del Tour</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tour">Tour *</label>
                            <select id="tour" name="tour" required>
                                <option value="">Seleccionar tour...</option>
                                <?php foreach ($tours as $tour):
                                    $tour_name = $tour['name'];
                                    $tour_price = floatval($tour['price'] ?? 0);
                                    if ($tour_price <= 0) continue; // Solo mostrar tours con precio
                                ?>
                                <option value="<?php echo esc_attr($tour_name); ?>" data-price="<?php echo esc_attr($tour_price); ?>">
                                    <?php echo esc_html($tour_name); ?> - $<?php echo number_format($tour_price, 0); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fecha_tour">Fecha del tour *</label>
                            <input type="date" id="fecha_tour" name="fecha_tour" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cantidad_pasajeros">Cantidad de pasajeros *</label>
                            <input type="number" id="cantidad_pasajeros" name="cantidad_pasajeros" value="1" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="moneda">Moneda</label>
                            <select id="moneda" name="moneda">
                                <option value="USD">USD ($)</option>
                                <option value="PEN">PEN (S/)</option>
                                <option value="EUR">EUR (€)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Precios</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="precio_unitario">Precio por persona *</label>
                            <input type="number" id="precio_unitario" name="precio_unitario" value="0" min="0" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="descuento">Descuento</label>
                            <div class="input-group">
                                <input type="number" id="descuento" name="descuento" value="0" min="0" step="0.01">
                                <select id="descuento_tipo" name="descuento_tipo">
                                    <option value="porcentaje">%</option>
                                    <option value="monto">Monto</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Subtotal</label>
                            <div class="precio-display" id="subtotal">0.00</div>
                        </div>
                        <div class="form-group">
                            <label>Total</label>
                            <div class="precio-display precio-total" id="precio_total_display">0.00</div>
                            <input type="hidden" id="precio_total" name="precio_total" value="0">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Información adicional</h3>
                    <div class="form-group">
                        <label for="notas">Notas para el cliente</label>
                        <textarea id="notas" name="notas" rows="3" placeholder="Incluye información relevante..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="validez_dias">Validez de la cotización (días)</label>
                        <input type="number" id="validez_dias" name="validez_dias" value="7" min="1" max="30">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" name="action" value="guardar">Guardar borrador</button>
                    <button type="button" class="btn btn-info btn-preview-pdf disabled">📄 Previsualizar PDF</button>
                    <button type="submit" class="btn btn-success" name="action" value="enviar">Guardar y Enviar</button>
                    <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/'); ?>" class="btn btn-secondary">Cancelar</a>
                </div>

                <div id="form-message" class="form-message" style="display: none;"></div>
            </form>
        </div>
        <?php
        $this->render_footer($this->get_cotizacion_form_scripts());
    }

    /**
     * Editar Cotización
     */
    private function render_editar_cotizacion() {
        $id = intval($_GET['id'] ?? 0);
        $cotizacion = RTT_Database::get_cotizacion($id);

        if (!$cotizacion) {
            wp_redirect(home_url('/' . self::PAGE_SLUG . '/'));
            exit;
        }

        $tours = RTT_Tours::get_tours();
        $options = get_option('rtt_reservas_options', []);

        $this->render_header('Editar Cotización');
        ?>
        <div class="form-container">
            <h1>Editar Cotización: <?php echo esc_html($cotizacion->codigo); ?></h1>

            <form id="cotizacion-form" class="cotizacion-form">
                <input type="hidden" name="id" value="<?php echo esc_attr($cotizacion->id); ?>">

                <div class="form-section">
                    <h3>Datos del Cliente</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cliente_nombre">Nombre completo *</label>
                            <input type="text" id="cliente_nombre" name="cliente_nombre" required value="<?php echo esc_attr($cotizacion->cliente_nombre); ?>">
                        </div>
                        <div class="form-group">
                            <label for="cliente_email">Email *</label>
                            <input type="email" id="cliente_email" name="cliente_email" required value="<?php echo esc_attr($cotizacion->cliente_email); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cliente_telefono">Teléfono</label>
                            <input type="text" id="cliente_telefono" name="cliente_telefono" value="<?php echo esc_attr($cotizacion->cliente_telefono); ?>">
                        </div>
                        <div class="form-group">
                            <label for="cliente_pais">País</label>
                            <input type="text" id="cliente_pais" name="cliente_pais" value="<?php echo esc_attr($cotizacion->cliente_pais); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Detalles del Tour</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tour">Tour *</label>
                            <select id="tour" name="tour" required>
                                <option value="">Seleccionar tour...</option>
                                <?php foreach ($tours as $tour):
                                    $tour_name = $tour['name'];
                                    $tour_price = floatval($tour['price'] ?? 0);
                                    if ($tour_price <= 0) continue; // Solo mostrar tours con precio
                                ?>
                                <option value="<?php echo esc_attr($tour_name); ?>" data-price="<?php echo esc_attr($tour_price); ?>" <?php selected($cotizacion->tour, $tour_name); ?>>
                                    <?php echo esc_html($tour_name); ?> - $<?php echo number_format($tour_price, 0); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fecha_tour">Fecha del tour *</label>
                            <input type="date" id="fecha_tour" name="fecha_tour" required value="<?php echo esc_attr($cotizacion->fecha_tour); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cantidad_pasajeros">Cantidad de pasajeros *</label>
                            <input type="number" id="cantidad_pasajeros" name="cantidad_pasajeros" value="<?php echo esc_attr($cotizacion->cantidad_pasajeros); ?>" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="moneda">Moneda</label>
                            <select id="moneda" name="moneda">
                                <option value="USD" <?php selected($cotizacion->moneda, 'USD'); ?>>USD ($)</option>
                                <option value="PEN" <?php selected($cotizacion->moneda, 'PEN'); ?>>PEN (S/)</option>
                                <option value="EUR" <?php selected($cotizacion->moneda, 'EUR'); ?>>EUR (€)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Precios</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="precio_unitario">Precio por persona *</label>
                            <input type="number" id="precio_unitario" name="precio_unitario" value="<?php echo esc_attr($cotizacion->precio_unitario); ?>" min="0" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="descuento">Descuento</label>
                            <div class="input-group">
                                <input type="number" id="descuento" name="descuento" value="<?php echo esc_attr($cotizacion->descuento); ?>" min="0" step="0.01">
                                <select id="descuento_tipo" name="descuento_tipo">
                                    <option value="porcentaje" <?php selected($cotizacion->descuento_tipo, 'porcentaje'); ?>>%</option>
                                    <option value="monto" <?php selected($cotizacion->descuento_tipo, 'monto'); ?>>Monto</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Subtotal</label>
                            <div class="precio-display" id="subtotal">0.00</div>
                        </div>
                        <div class="form-group">
                            <label>Total</label>
                            <div class="precio-display precio-total" id="precio_total_display">0.00</div>
                            <input type="hidden" id="precio_total" name="precio_total" value="<?php echo esc_attr($cotizacion->precio_total); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Información adicional</h3>
                    <div class="form-group">
                        <label for="notas">Notas para el cliente</label>
                        <textarea id="notas" name="notas" rows="3"><?php echo esc_textarea($cotizacion->notas); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="validez_dias">Validez de la cotización (días)</label>
                        <input type="number" id="validez_dias" name="validez_dias" value="<?php echo esc_attr($cotizacion->validez_dias); ?>" min="1" max="30">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" name="action" value="guardar">Guardar cambios</button>
                    <button type="button" class="btn btn-info btn-preview-pdf">📄 Previsualizar PDF</button>
                    <?php if ($cotizacion->estado === 'borrador'): ?>
                    <button type="submit" class="btn btn-success" name="action" value="enviar">Guardar y Enviar</button>
                    <?php endif; ?>
                    <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/'); ?>" class="btn btn-secondary">Cancelar</a>
                </div>

                <div id="form-message" class="form-message" style="display: none;"></div>
            </form>
        </div>

        <?php
        $this->render_footer($this->get_cotizacion_form_scripts());
    }

    /**
     * Lista de Cotizaciones
     */
    private function render_lista_cotizaciones() {
        $user = wp_get_current_user();
        $page = intval($_GET['pag'] ?? 1);
        $estado = sanitize_text_field($_GET['estado'] ?? '');
        $buscar = sanitize_text_field($_GET['s'] ?? '');

        $cotizaciones = RTT_Database::get_cotizaciones([
            'vendedor_id' => in_array('administrator', $user->roles) ? 0 : $user->ID,
            'estado' => $estado,
            'buscar' => $buscar,
            'page' => $page,
            'per_page' => 15
        ]);

        $this->render_header('Mis Cotizaciones');
        ?>
        <div class="lista-container">
            <div class="section-header">
                <h1>Mis Cotizaciones</h1>
                <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/nueva/'); ?>" class="btn btn-primary">+ Nueva Cotización</a>
            </div>

            <div class="filters">
                <form method="get" class="filter-form">
                    <select name="estado">
                        <option value="">Todos los estados</option>
                        <option value="borrador" <?php selected($estado, 'borrador'); ?>>Borrador</option>
                        <option value="enviada" <?php selected($estado, 'enviada'); ?>>Enviada</option>
                        <option value="aceptada" <?php selected($estado, 'aceptada'); ?>>Aceptada</option>
                        <option value="vencida" <?php selected($estado, 'vencida'); ?>>Vencida</option>
                    </select>
                    <input type="text" name="s" value="<?php echo esc_attr($buscar); ?>" placeholder="Buscar...">
                    <button type="submit" class="btn">Filtrar</button>
                </form>
            </div>

            <?php if (empty($cotizaciones['items'])): ?>
            <div class="empty-state">
                <p>No se encontraron cotizaciones.</p>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>Tour</th>
                        <th>Fecha Tour</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Creada</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cotizaciones['items'] as $cot): ?>
                    <tr>
                        <td><strong><?php echo esc_html($cot->codigo); ?></strong></td>
                        <td>
                            <?php echo esc_html($cot->cliente_nombre); ?><br>
                            <small><?php echo esc_html($cot->cliente_email); ?></small>
                        </td>
                        <td><?php echo esc_html(substr($cot->tour, 0, 25)); ?>...</td>
                        <td><?php echo date_i18n('d/m/Y', strtotime($cot->fecha_tour)); ?></td>
                        <td><?php echo esc_html($cot->moneda); ?> <?php echo number_format($cot->precio_total, 2); ?></td>
                        <td><span class="badge badge-<?php echo esc_attr($cot->estado); ?>"><?php echo esc_html(ucfirst($cot->estado)); ?></span></td>
                        <td><?php echo date_i18n('d/m/Y', strtotime($cot->fecha_creacion)); ?></td>
                        <td class="actions">
                            <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/editar/?id=' . $cot->id); ?>" class="btn btn-sm">Editar</a>
                            <?php if ($cot->estado === 'borrador'): ?>
                            <button type="button" class="btn btn-sm btn-success btn-send" data-id="<?php echo $cot->id; ?>">Enviar</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-danger btn-delete" data-id="<?php echo $cot->id; ?>">Eliminar</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($cotizaciones['pages'] > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $cotizaciones['pages']; $i++): ?>
                <a href="?pag=<?php echo $i; ?>&estado=<?php echo esc_attr($estado); ?>&s=<?php echo esc_attr($buscar); ?>"
                   class="<?php echo $page === $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <script>
            $(document).ready(function() {
                $('.btn-delete').on('click', function() {
                    if (!confirm('¿Eliminar esta cotización?')) return;
                    var id = $(this).data('id');
                    var row = $(this).closest('tr');

                    $.post(rttAjax.url, {
                        action: 'rtt_delete_cotizacion',
                        id: id
                    }, function(response) {
                        if (response.success) {
                            row.fadeOut();
                        } else {
                            alert(response.data.message);
                        }
                    });
                });

                $('.btn-send').on('click', function() {
                    if (!confirm('¿Enviar cotización al cliente?')) return;
                    var btn = $(this);
                    var id = btn.data('id');
                    btn.prop('disabled', true).text('Enviando...');

                    $.post(rttAjax.url, {
                        action: 'rtt_send_cotizacion',
                        id: id
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message);
                            btn.prop('disabled', false).text('Enviar');
                        }
                    });
                });
            });
        </script>
        <?php
        $this->render_footer();
    }

    /**
     * Ver Cotización
     */
    private function render_ver_cotizacion() {
        $id = intval($_GET['id'] ?? 0);
        $cotizacion = RTT_Database::get_cotizacion($id);

        if (!$cotizacion) {
            wp_redirect(home_url('/' . self::PAGE_SLUG . '/'));
            exit;
        }

        $this->render_header('Ver Cotización');
        ?>
        <div class="view-container">
            <h1>Cotización: <?php echo esc_html($cotizacion->codigo); ?></h1>
            <!-- Contenido de ver cotización -->
        </div>
        <?php
        $this->render_footer();
    }

    /**
     * Scripts del formulario de cotización
     */
    private function get_cotizacion_form_scripts() {
        return "
        function calcularTotal() {
            var cantidad = parseInt($('#cantidad_pasajeros').val()) || 0;
            var precio = parseFloat($('#precio_unitario').val()) || 0;
            var descuento = parseFloat($('#descuento').val()) || 0;
            var descuentoTipo = $('#descuento_tipo').val();

            var subtotal = cantidad * precio;
            var descuentoMonto = descuentoTipo === 'porcentaje' ? (subtotal * descuento / 100) : descuento;
            var total = subtotal - descuentoMonto;

            $('#subtotal').text(subtotal.toFixed(2));
            $('#precio_total_display').text(total.toFixed(2));
            $('#precio_total').val(total.toFixed(2));
        }

        // Auto-fill price when tour is selected
        $('#tour').on('change', function() {
            var selectedOption = $(this).find('option:selected');
            var price = selectedOption.data('price');
            if (price && parseFloat($('#precio_unitario').val()) == 0) {
                $('#precio_unitario').val(price);
                calcularTotal();
            }
        });

        $('#cantidad_pasajeros, #precio_unitario, #descuento, #descuento_tipo').on('change input', calcularTotal);
        calcularTotal();

        // Preview PDF button
        $('.btn-preview-pdf').on('click', function() {
            var id = $('input[name=id]').val();
            if (!id || id == '0') {
                alert('Primero guarda la cotización para poder previsualizar el PDF');
                return;
            }
            window.open(rttAjax.url + '?action=rtt_preview_cotizacion_pdf&id=' + id, '_blank');
        });

        $('#cotizacion-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var submitBtn = form.find('button[type=submit]:focus');
            if (!submitBtn.length) submitBtn = form.find('button[type=submit]:first');
            var action = submitBtn.val() || 'guardar';
            var btnText = submitBtn.text();

            submitBtn.prop('disabled', true).text('Procesando...');
            $('#form-message').hide();

            var formData = form.serialize();

            $.post(rttAjax.url, formData + '&action=rtt_save_cotizacion', function(response) {
                if (response.success) {
                    if (action === 'enviar' && response.data.id) {
                        // Enviar después de guardar
                        $.post(rttAjax.url, {
                            action: 'rtt_send_cotizacion',
                            id: response.data.id
                        }, function(sendResponse) {
                            if (sendResponse.success) {
                                $('#form-message').removeClass('error').addClass('success')
                                    .html('<strong>✓ ENVIADO</strong><br>Cotización enviada exitosamente a: ' + $('#cliente_email').val()).show();
                                // Scroll to message
                                $('html, body').animate({ scrollTop: $('#form-message').offset().top - 100 }, 500);
                                setTimeout(function() {
                                    window.location.href = '" . home_url('/' . self::PAGE_SLUG . '/') . "';
                                }, 3000);
                            } else {
                                $('#form-message').removeClass('success').addClass('error')
                                    .text('Guardada pero error al enviar: ' + sendResponse.data.message).show();
                            }
                            submitBtn.prop('disabled', false).text(btnText);
                        });
                    } else {
                        $('#form-message').removeClass('error').addClass('success')
                            .text(response.data.message).show();
                        if (response.data.id) {
                            $('input[name=id]').val(response.data.id);
                            // Enable preview PDF button after saving
                            $('.btn-preview-pdf').removeClass('disabled');
                        }
                        submitBtn.prop('disabled', false).text(btnText);
                    }
                } else {
                    $('#form-message').removeClass('success').addClass('error')
                        .text(response.data.message).show();
                    submitBtn.prop('disabled', false).text(btnText);
                }
            }).fail(function() {
                $('#form-message').removeClass('success').addClass('error')
                    .text('Error de conexión').show();
                submitBtn.prop('disabled', false).text(btnText);
            });
        });
        ";
    }

    /**
     * Estilos de login
     */
    private function get_login_styles() {
        return "
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #004070 0%, #27AE60 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container { width: 100%; max-width: 400px; padding: 20px; }
        .login-box {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-header { text-align: center; margin-bottom: 30px; }
        .brand-icon {
            display: inline-block;
            background: linear-gradient(135deg, #004070, #27AE60);
            color: white;
            font-weight: 700;
            font-size: 24px;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .login-header h1 { font-size: 24px; color: #333; margin-bottom: 5px; }
        .login-header p { color: #666; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus { outline: none; border-color: #27AE60; }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #004070, #27AE60);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(39,174,96,0.4); }
        .btn-login:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
        .error-message {
            margin-top: 15px;
            padding: 12px;
            background: #fee;
            color: #c00;
            border-radius: 8px;
            text-align: center;
        }
        ";
    }

    /**
     * Estilos del panel
     */
    private function get_panel_styles() {
        return "
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; min-height: 100vh; }

        .navbar {
            background: linear-gradient(135deg, #004070, #003050);
            padding: 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .nav-brand { display: flex; align-items: center; gap: 10px; color: white; font-weight: 600; }
        .brand-icon {
            background: #27AE60;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: 700;
        }
        .nav-menu { display: flex; gap: 5px; }
        .nav-link {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: all 0.2s;
        }
        .nav-link:hover { background: rgba(255,255,255,0.1); color: white; }
        .nav-user { display: flex; align-items: center; gap: 15px; color: white; }
        .btn-logout {
            color: white;
            text-decoration: none;
            padding: 6px 12px;
            background: rgba(255,255,255,0.1);
            border-radius: 5px;
        }
        .btn-logout:hover { background: rgba(255,255,255,0.2); }

        .main-content { padding: 30px; max-width: 1200px; margin: 0 auto; }

        h1 { color: #333; margin-bottom: 25px; }
        h2 { color: #333; font-size: 18px; }
        h3 { color: #333; font-size: 16px; margin-bottom: 15px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #004070;
        }
        .stat-card.stat-warning { border-left-color: #f0ad4e; }
        .stat-card.stat-info { border-left-color: #5bc0de; }
        .stat-card.stat-success { border-left-color: #27AE60; }
        .stat-number { font-size: 32px; font-weight: 700; color: #333; }
        .stat-label { color: #666; margin-top: 5px; }

        .section { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            border: none;
            font-size: 14px;
            transition: all 0.2s;
        }
        .btn-primary { background: #004070; color: white; }
        .btn-primary:hover { background: #003050; }
        .btn-success { background: #27AE60; color: white; }
        .btn-success:hover { background: #219a52; }
        .btn-info { background: #5bc0de; color: white; }
        .btn-info:hover { background: #46b8da; }
        .btn-info.disabled { background: #ccc; cursor: not-allowed; opacity: 0.6; }
        .btn-secondary { background: #e0e0e0; color: #333; }
        .btn-danger { background: #d9534f; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .data-table th { background: #f9f9f9; font-weight: 600; color: #333; }
        .data-table tr:hover { background: #f5f5f5; }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-borrador { background: #f0ad4e; color: white; }
        .badge-enviada { background: #5bc0de; color: white; }
        .badge-aceptada { background: #27AE60; color: white; }
        .badge-vencida { background: #999; color: white; }

        .empty-state { text-align: center; padding: 50px; color: #666; }

        /* Formularios */
        .form-container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .form-section { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #27AE60;
        }
        .input-group { display: flex; gap: 10px; }
        .input-group input { flex: 1; }
        .input-group select { width: 100px; }

        .precio-display { font-size: 20px; font-weight: 600; color: #333; padding: 10px; background: #f5f5f5; border-radius: 6px; }
        .precio-total { color: #27AE60; font-size: 24px; }

        .form-actions { display: flex; gap: 10px; margin-top: 20px; }
        .form-message { margin-top: 20px; padding: 15px; border-radius: 6px; }
        .form-message.success { background: #d4edda; color: #155724; }
        .form-message.error { background: #f8d7da; color: #721c24; }

        /* Lista */
        .lista-container { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .filters { margin-bottom: 20px; }
        .filter-form { display: flex; gap: 10px; flex-wrap: wrap; }
        .filter-form select, .filter-form input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; }
        .pagination { display: flex; gap: 5px; justify-content: center; margin-top: 20px; }
        .pagination a {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }
        .pagination a.active { background: #004070; color: white; border-color: #004070; }
        .actions { white-space: nowrap; }
        .actions .btn { margin-right: 5px; }

        @media (max-width: 768px) {
            .navbar { padding: 0 15px; }
            .nav-menu { display: none; }
            .main-content { padding: 15px; }
            .form-row { grid-template-columns: 1fr; }
        }
        ";
    }

    /**
     * Scripts generales del panel
     */
    private function get_panel_scripts() {
        return "<script>
            // Scripts generales si necesarios
        </script>";
    }
}
