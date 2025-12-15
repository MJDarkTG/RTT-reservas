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

        // Registrar rewrite rules (backup, por si funciona)
        add_action('init', [$this, 'add_rewrite_rules']);

        // Manejar la página del panel (método antiguo)
        add_action('template_redirect', [$this, 'handle_panel_page']);

        // Registrar shortcode [rtt_seller_panel]
        add_shortcode('rtt_seller_panel', [$this, 'render_shortcode']);

        // AJAX handlers
        add_action('wp_ajax_rtt_seller_login', [$this, 'ajax_login']);
        add_action('wp_ajax_nopriv_rtt_seller_login', [$this, 'ajax_login']);
        add_action('wp_ajax_rtt_seller_logout', [$this, 'ajax_logout']);
        add_action('wp_ajax_rtt_save_cotizacion', [$this, 'ajax_save_cotizacion']);
        add_action('wp_ajax_rtt_send_cotizacion', [$this, 'ajax_send_cotizacion']);
        add_action('wp_ajax_rtt_delete_cotizacion', [$this, 'ajax_delete_cotizacion']);
        add_action('wp_ajax_rtt_get_cotizacion', [$this, 'ajax_get_cotizacion']);
        add_action('wp_ajax_rtt_preview_cotizacion_pdf', [$this, 'ajax_preview_pdf']);
        add_action('wp_ajax_rtt_save_configuracion', [$this, 'ajax_save_configuracion']);
        add_action('wp_ajax_rtt_save_proveedor', [$this, 'ajax_save_proveedor']);
        add_action('wp_ajax_rtt_delete_proveedor', [$this, 'ajax_delete_proveedor']);
        add_action('wp_ajax_rtt_get_proveedores', [$this, 'ajax_get_proveedores']);
    }

    /**
     * Shortcode [rtt_seller_panel]
     * Permite embeber el panel en cualquier página de WordPress
     */
    public function render_shortcode($atts = []) {
        ob_start();

        // Usar parámetro GET para la acción
        $action = sanitize_text_field($_GET['panel'] ?? 'dashboard');

        // Si no está logueado, mostrar login
        if (!$this->can_access_panel()) {
            $this->render_login_shortcode();
        } else {
            // Renderizar página según acción
            switch ($action) {
                case 'nueva':
                    $this->render_nueva_cotizacion_shortcode();
                    break;
                case 'editar':
                    $this->render_editar_cotizacion_shortcode();
                    break;
                case 'ver':
                    $this->render_ver_cotizacion_shortcode();
                    break;
                case 'lista':
                    $this->render_lista_cotizaciones_shortcode();
                    break;
                case 'configuracion':
                    $this->render_configuracion_shortcode();
                    break;
                case 'proveedores':
                    $this->render_proveedores_shortcode();
                    break;
                case 'logout':
                    wp_logout();
                    $redirect_url = get_permalink();
                    echo '<script>window.location.href = "' . esc_url($redirect_url) . '";</script>';
                    echo '<p style="text-align:center;padding:40px;">Cerrando sesión...</p>';
                    break;
                default:
                    $this->render_dashboard_shortcode();
            }
        }

        return ob_get_clean();
    }

    /**
     * Obtener URL base para shortcode
     */
    private function get_shortcode_url($action = '') {
        $base_url = get_permalink();
        if (empty($action) || $action === 'dashboard') {
            return $base_url;
        }
        return add_query_arg('panel', $action, $base_url);
    }

    /**
     * Header para shortcode (sin DOCTYPE, usa CSS/JS inline)
     */
    private function render_header_shortcode($title = 'Panel de Vendedor', $active_page = '') {
        $user = wp_get_current_user();
        $initials = strtoupper(substr($user->display_name, 0, 2));
        $is_admin = in_array('administrator', $user->roles);

        // Determinar página activa
        if (empty($active_page)) {
            $active_page = sanitize_text_field($_GET['panel'] ?? 'dashboard');
        }

        // Cargar CSS minificado para mejor rendimiento
        $css_file = defined('WP_DEBUG') && WP_DEBUG ? 'seller-shortcode.css' : 'seller-shortcode.min.css';
        $css_url = RTT_RESERVAS_PLUGIN_URL . 'assets/css/' . $css_file . '?v=' . RTT_RESERVAS_VERSION;
        echo '<link rel="stylesheet" href="' . esc_url($css_url) . '" type="text/css" media="all" />';
        ?>

        <div class="rtt-panel-embedded">
            <div class="rtt-panel-header">
                <div class="rtt-panel-brand">
                    <span class="logo">RTT</span>
                    <h2>Panel de Cotizaciones</h2>
                </div>
                <div class="rtt-panel-user">
                    <span class="avatar"><?php echo esc_html($initials); ?></span>
                    <span><?php echo esc_html($user->display_name); ?></span>
                    <a href="<?php echo esc_url($this->get_shortcode_url('logout')); ?>" class="btn-logout-link">Salir</a>
                </div>
            </div>

            <nav class="rtt-panel-nav">
                <a href="<?php echo esc_url($this->get_shortcode_url()); ?>" class="<?php echo $active_page === 'dashboard' ? 'active' : ''; ?>">
                    <span>📊</span><span class="nav-text">Dashboard</span>
                </a>
                <a href="<?php echo esc_url($this->get_shortcode_url('nueva')); ?>" class="<?php echo $active_page === 'nueva' || $active_page === 'editar' ? 'active' : ''; ?>">
                    <span>➕</span><span class="nav-text">Nueva</span>
                </a>
                <a href="<?php echo esc_url($this->get_shortcode_url('lista')); ?>" class="<?php echo $active_page === 'lista' || $active_page === 'ver' ? 'active' : ''; ?>">
                    <span>📋</span><span class="nav-text">Cotizaciones</span>
                </a>
                <a href="<?php echo esc_url($this->get_shortcode_url('proveedores')); ?>" class="<?php echo $active_page === 'proveedores' ? 'active' : ''; ?>">
                    <span>🏢</span><span class="nav-text">Proveedores</span>
                </a>
                <?php if ($is_admin): ?>
                <a href="<?php echo esc_url($this->get_shortcode_url('configuracion')); ?>" class="<?php echo $active_page === 'configuracion' ? 'active' : ''; ?>">
                    <span>⚙️</span><span class="nav-text">Config</span>
                </a>
                <?php endif; ?>
            </nav>

            <div class="rtt-panel-content">
                <h1 class="rtt-panel-title"><?php echo esc_html($title); ?></h1>
        <?php
    }

    /**
     * Footer para shortcode
     */
    private function render_footer_shortcode($extra_scripts = '') {
        $dashboard_url = $this->get_shortcode_url();
        ?>
            </div><!-- .rtt-panel-content -->
        </div><!-- .rtt-panel-embedded -->

        <script>
            var rttAjax = {
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                dashboardUrl: '<?php echo esc_url($dashboard_url); ?>'
            };
        </script>
        <?php
        wp_enqueue_script('jquery');
        // Cargar JS minificado para mejor rendimiento
        $js_file = defined('WP_DEBUG') && WP_DEBUG ? 'seller-panel.js' : 'seller-panel.min.js';
        wp_enqueue_script('rtt-seller-panel-js', RTT_RESERVAS_PLUGIN_URL . 'assets/js/' . $js_file, ['jquery'], RTT_RESERVAS_VERSION, true);

        if (!empty($extra_scripts)):
        ?>
        <script>
            jQuery(document).ready(function($) {
                <?php echo $extra_scripts; ?>
            });
        </script>
        <?php
        endif;
    }

    /**
     * Renderizar campos del formulario de cotización (compartido entre nueva y editar)
     */
    private function render_cotizacion_form_fields($tours, $cotizacion = null, $tipo_cambio = 3.70) {
        $is_edit = !is_null($cotizacion);
        ?>
        <div class="form-section">
            <h3>Datos del Cliente</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="cliente_nombre">Nombre completo *</label>
                    <input type="text" id="cliente_nombre" name="cliente_nombre" required value="<?php echo $is_edit ? esc_attr($cotizacion->cliente_nombre) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="cliente_email">Email *</label>
                    <input type="email" id="cliente_email" name="cliente_email" required value="<?php echo $is_edit ? esc_attr($cotizacion->cliente_email) : ''; ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="cliente_telefono">Teléfono</label>
                    <input type="text" id="cliente_telefono" name="cliente_telefono" value="<?php echo $is_edit ? esc_attr($cotizacion->cliente_telefono) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="cliente_pais">País</label>
                    <input type="text" id="cliente_pais" name="cliente_pais" value="<?php echo $is_edit ? esc_attr($cotizacion->cliente_pais) : ''; ?>">
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
                            $price_display = $tour_price > 0 ? ' - $' . number_format($tour_price, 0) : '';
                            $selected = $is_edit && $cotizacion->tour === $tour_name ? 'selected' : '';
                        ?>
                        <option value="<?php echo esc_attr($tour_name); ?>" data-price="<?php echo esc_attr($tour_price); ?>" <?php echo $selected; ?>>
                            <?php echo esc_html($tour_name); ?><?php echo $price_display; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="fecha_tour">Fecha del tour *</label>
                    <input type="date" id="fecha_tour" name="fecha_tour" required value="<?php echo $is_edit ? esc_attr($cotizacion->fecha_tour) : ''; ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="cantidad_pasajeros">Cantidad de pasajeros *</label>
                    <input type="number" id="cantidad_pasajeros" name="cantidad_pasajeros" value="<?php echo $is_edit ? esc_attr($cotizacion->cantidad_pasajeros) : '1'; ?>" min="1" required>
                </div>
                <div class="form-group">
                    <label for="moneda">Moneda</label>
                    <select id="moneda" name="moneda">
                        <option value="USD" <?php echo $is_edit && $cotizacion->moneda === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                        <option value="PEN" <?php echo $is_edit && $cotizacion->moneda === 'PEN' ? 'selected' : ''; ?>>PEN (S/)</option>
                        <option value="EUR" <?php echo $is_edit && $cotizacion->moneda === 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>Precios</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="precio_unitario">Precio por persona *</label>
                    <input type="number" id="precio_unitario" name="precio_unitario" value="<?php echo $is_edit ? esc_attr($cotizacion->precio_unitario) : '0'; ?>" min="0" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="descuento">Descuento</label>
                    <div class="input-group">
                        <input type="number" id="descuento" name="descuento" value="<?php echo $is_edit ? esc_attr($cotizacion->descuento) : '0'; ?>" min="0" step="0.01">
                        <select id="descuento_tipo" name="descuento_tipo">
                            <option value="porcentaje" <?php echo $is_edit && $cotizacion->descuento_tipo === 'porcentaje' ? 'selected' : ''; ?>>%</option>
                            <option value="monto" <?php echo $is_edit && $cotizacion->descuento_tipo === 'monto' ? 'selected' : ''; ?>>Monto</option>
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
                    <input type="hidden" id="precio_total" name="precio_total" value="<?php echo $is_edit ? esc_attr($cotizacion->precio_total) : '0'; ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>Información adicional</h3>
            <div class="form-group">
                <label for="notas">Notas para el cliente</label>
                <textarea id="notas" name="notas" rows="3" placeholder="Incluye información relevante..."><?php echo $is_edit ? esc_textarea($cotizacion->notas) : ''; ?></textarea>
            </div>
            <div class="form-group">
                <label for="validez_dias">Validez de la cotización (días)</label>
                <input type="number" id="validez_dias" name="validez_dias" value="<?php echo $is_edit ? esc_attr($cotizacion->validez_dias) : '7'; ?>" min="1" max="30">
            </div>
        </div>

        <div class="form-section costos-internos-section">
            <div class="costos-header">
                <div class="costos-header-icon">💰</div>
                <div class="costos-header-text">
                    <h3>Costos Internos</h3>
                    <span class="costos-header-badge">Privado</span>
                </div>
                <div class="tipo-cambio-mini">
                    <label>T/C:</label>
                    <input type="number" id="tipo_cambio" name="tipo_cambio" step="0.01" min="1" value="<?php echo esc_attr($is_edit && isset($cotizacion->tipo_cambio) ? $cotizacion->tipo_cambio : $tipo_cambio); ?>">
                </div>
            </div>

            <div class="costos-body">
                <div class="costos-list" id="costos-items">
                    <?php
                    $costos_guardados = [];
                    if ($is_edit && !empty($cotizacion->costos_json)) {
                        $costos_guardados = json_decode($cotizacion->costos_json, true) ?: [];
                    }
                    if (empty($costos_guardados)) {
                        $costos_guardados = [['concepto' => '', 'monto' => '']];
                    }
                    foreach ($costos_guardados as $costo):
                    ?>
                    <div class="costo-item">
                        <div class="costo-drag">⋮⋮</div>
                        <input type="text" name="costo_concepto[]" placeholder="Ej: Guía, Transporte, Entradas..." class="costo-concepto" value="<?php echo esc_attr($costo['concepto'] ?? ''); ?>">
                        <div class="costo-monto-wrapper">
                            <span class="costo-prefix">$</span>
                            <input type="number" name="costo_monto[]" placeholder="0.00" min="0" step="0.01" class="costo-monto" value="<?php echo esc_attr($costo['monto'] ?? ''); ?>">
                        </div>
                        <button type="button" class="btn-remove-costo" title="Eliminar">×</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-add-costo-new" id="btn-add-costo">
                    <span class="btn-add-icon">+</span> Agregar costo
                </button>
                <input type="hidden" id="costos_json" name="costos_json" value="<?php echo $is_edit ? esc_attr($cotizacion->costos_json ?? '') : ''; ?>">
            </div>

            <div class="costos-summary">
                <div class="summary-card summary-costo">
                    <div class="summary-icon">📊</div>
                    <div class="summary-content">
                        <span class="summary-label">Costo Total</span>
                        <span class="summary-value" id="costo_total_display">$ 0.00</span>
                    </div>
                </div>
                <div class="summary-card summary-ganancia" id="ganancia-card">
                    <div class="summary-icon">📈</div>
                    <div class="summary-content">
                        <span class="summary-label">Ganancia</span>
                        <span class="summary-value-main" id="ganancia_usd">$ 0.00</span>
                        <span class="summary-value-secondary" id="ganancia_pen">S/ 0.00</span>
                    </div>
                    <div class="summary-badge" id="ganancia_pct">0%</div>
                </div>
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label for="notas_internas">📝 Notas internas</label>
                <textarea id="notas_internas" name="notas_internas" rows="2" placeholder="Notas privadas (no aparecen en la cotización)..."><?php echo $is_edit ? esc_textarea($cotizacion->notas_internas ?? '') : ''; ?></textarea>
            </div>
        </div>
        <?php
    }

    /**
     * Login para shortcode
     */
    private function render_login_shortcode() {
        wp_enqueue_style('rtt-seller-panel-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', [], null);
        wp_enqueue_style('rtt-seller-panel-css', RTT_RESERVAS_PLUGIN_URL . 'assets/css/seller-panel.css', [], RTT_RESERVAS_VERSION);
        ?>
        <div class="rtt-seller-panel-wrapper">
            <div class="login-box" style="margin: 40px auto;">
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
                    <div id="login-error" class="login-error" style="display: none;"></div>
                </form>
            </div>
        </div>
        <script>
            var rttAjax = { url: '<?php echo admin_url('admin-ajax.php'); ?>' };
        </script>
        <?php
        wp_enqueue_script('jquery');
        wp_enqueue_script('rtt-seller-panel-js', RTT_RESERVAS_PLUGIN_URL . 'assets/js/seller-panel.js', ['jquery'], RTT_RESERVAS_VERSION, true);
    }

    /**
     * Dashboard para shortcode
     */
    private function render_dashboard_shortcode() {
        $user = wp_get_current_user();
        $stats = RTT_Database::get_cotizaciones_stats($user->ID);
        $result = RTT_Database::get_cotizaciones(['per_page' => 5, 'vendedor_id' => $user->ID]);
        $recientes = $result['items'] ?? [];

        $this->render_header_shortcode('Dashboard', 'dashboard');
        ?>
        <div class="dashboard-welcome">
            <h2>¡Bienvenido, <?php echo esc_html($user->display_name); ?>!</h2>
            <p>Resumen de tus cotizaciones</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-content">
                    <span class="stat-number"><?php echo intval($stats['total']); ?></span>
                    <span class="stat-label">Total Cotizaciones</span>
                </div>
            </div>
            <div class="stat-card stat-warning">
                <div class="stat-icon">📤</div>
                <div class="stat-content">
                    <span class="stat-number"><?php echo intval($stats['enviadas']); ?></span>
                    <span class="stat-label">Enviadas</span>
                </div>
            </div>
            <div class="stat-card stat-success">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <span class="stat-number"><?php echo intval($stats['aceptadas']); ?></span>
                    <span class="stat-label">Aceptadas</span>
                </div>
            </div>
            <div class="stat-card stat-info">
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                    <span class="stat-number">$<?php echo number_format($stats['total_aceptado'] ?? 0, 0); ?></span>
                    <span class="stat-label">Monto Aceptado</span>
                </div>
            </div>
        </div>

        <div class="quick-actions">
            <a href="<?php echo esc_url($this->get_shortcode_url('nueva')); ?>" class="quick-action-card">
                <span class="quick-action-icon">➕</span>
                <span class="quick-action-text">Nueva Cotización</span>
            </a>
            <a href="<?php echo esc_url($this->get_shortcode_url('lista')); ?>" class="quick-action-card">
                <span class="quick-action-icon">📋</span>
                <span class="quick-action-text">Ver Todas</span>
            </a>
            <a href="<?php echo esc_url($this->get_shortcode_url('proveedores')); ?>" class="quick-action-card">
                <span class="quick-action-icon">🏢</span>
                <span class="quick-action-text">Proveedores</span>
            </a>
        </div>

        <?php if (!empty($recientes)): ?>
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <span class="section-title-icon">🕒</span>
                    Cotizaciones Recientes
                </div>
                <a href="<?php echo esc_url($this->get_shortcode_url('lista')); ?>" class="btn btn-sm btn-secondary">Ver todas</a>
            </div>
            <div class="section-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Tour</th>
                            <th>Total</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recientes as $cot): ?>
                        <tr>
                            <td><a href="<?php echo esc_url($this->get_shortcode_url('ver') . '&id=' . $cot->id); ?>" class="table-code"><?php echo esc_html($cot->codigo); ?></a></td>
                            <td>
                                <div class="table-client">
                                    <span class="table-client-name"><?php echo esc_html($cot->cliente_nombre); ?></span>
                                    <span class="table-client-email"><?php echo esc_html($cot->cliente_email); ?></span>
                                </div>
                            </td>
                            <td class="table-tour"><?php echo esc_html($cot->tour); ?></td>
                            <td class="table-price"><?php echo esc_html($cot->moneda); ?> <?php echo number_format($cot->precio_total, 2); ?></td>
                            <td><span class="badge badge-<?php echo esc_attr($cot->estado); ?>"><?php echo esc_html(ucfirst($cot->estado)); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        <?php
        $this->render_footer_shortcode();
    }

    /**
     * Nueva cotización para shortcode
     */
    private function render_nueva_cotizacion_shortcode() {
        $tours = RTT_Tours::get_tours();
        $options = get_option('rtt_reservas_options', []);
        $tipo_cambio = $options['tipo_cambio'] ?? 3.70;

        $this->render_header_shortcode('Nueva Cotización', 'nueva');
        ?>
        <div class="form-container">
            <form id="cotizacion-form" class="cotizacion-form">
                <input type="hidden" name="id" value="0">

                <?php $this->render_cotizacion_form_fields($tours, null, $tipo_cambio); ?>

                <div class="form-actions">
                    <button type="submit" name="accion" value="guardar" class="btn btn-secondary">💾 Guardar Borrador</button>
                    <button type="submit" name="accion" value="enviar" class="btn btn-primary">📧 Guardar y Enviar</button>
                    <a href="<?php echo esc_url($this->get_shortcode_url()); ?>" class="btn btn-outline">Cancelar</a>
                </div>

                <div id="form-message" class="form-message" style="display: none;"></div>
            </form>
        </div>
        <?php
        $this->render_footer_shortcode($this->get_cotizacion_form_scripts_shortcode());
    }

    /**
     * Editar cotización para shortcode
     */
    private function render_editar_cotizacion_shortcode() {
        $id = intval($_GET['id'] ?? 0);
        $cotizacion = RTT_Database::get_cotizacion($id);

        if (!$cotizacion) {
            $this->render_header_shortcode('Error', 'editar');
            echo '<div class="empty-state"><h3>Cotización no encontrada</h3><a href="' . esc_url($this->get_shortcode_url('lista')) . '" class="btn btn-primary">Volver a la lista</a></div>';
            $this->render_footer_shortcode();
            return;
        }

        $tours = RTT_Tours::get_tours();
        $options = get_option('rtt_reservas_options', []);
        $tipo_cambio = $options['tipo_cambio'] ?? 3.70;

        $this->render_header_shortcode('Editar: ' . $cotizacion->codigo, 'editar');
        ?>
        <div class="form-container">
            <form id="cotizacion-form" class="cotizacion-form">
                <input type="hidden" name="id" value="<?php echo $cotizacion->id; ?>">

                <?php $this->render_cotizacion_form_fields($tours, $cotizacion, $tipo_cambio); ?>

                <div class="form-actions">
                    <button type="submit" name="accion" value="guardar" class="btn btn-secondary">💾 Guardar</button>
                    <button type="submit" name="accion" value="enviar" class="btn btn-primary">📧 Guardar y Enviar</button>
                    <button type="button" class="btn btn-outline btn-preview-pdf">👁️ Ver PDF</button>
                    <a href="<?php echo esc_url($this->get_shortcode_url('lista')); ?>" class="btn btn-outline">Cancelar</a>
                </div>

                <div id="form-message" class="form-message" style="display: none;"></div>
            </form>
        </div>
        <?php
        $this->render_footer_shortcode($this->get_cotizacion_form_scripts_shortcode());
    }

    /**
     * Ver cotización para shortcode
     */
    private function render_ver_cotizacion_shortcode() {
        $id = intval($_GET['id'] ?? 0);
        $cotizacion = RTT_Database::get_cotizacion($id);

        if (!$cotizacion) {
            $this->render_header_shortcode('Error', 'ver');
            echo '<div class="empty-state"><h3>Cotización no encontrada</h3><a href="' . esc_url($this->get_shortcode_url('lista')) . '" class="btn btn-primary">Volver a la lista</a></div>';
            $this->render_footer_shortcode();
            return;
        }

        $this->render_header_shortcode('Cotización: ' . $cotizacion->codigo, 'ver');
        ?>
        <div class="cotizacion-detail">
            <div class="detail-actions" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="<?php echo esc_url($this->get_shortcode_url('editar') . '&id=' . $cotizacion->id); ?>" class="btn btn-primary">✏️ Editar</a>
                <a href="<?php echo admin_url('admin-ajax.php?action=rtt_preview_cotizacion_pdf&id=' . $cotizacion->id); ?>" target="_blank" class="btn btn-secondary">📄 Ver PDF</a>
                <a href="<?php echo esc_url($this->get_shortcode_url('lista')); ?>" class="btn btn-outline">← Volver</a>
            </div>

            <div class="detail-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div class="detail-card" style="background: #f8f9fa; padding: 20px; border-radius: 10px;">
                    <h3 style="margin-bottom: 15px; color: #333;">📋 Información General</h3>
                    <p><strong>Código:</strong> <?php echo esc_html($cotizacion->codigo); ?></p>
                    <p><strong>Estado:</strong> <span class="badge badge-<?php echo esc_attr($cotizacion->estado); ?>"><?php echo esc_html(ucfirst($cotizacion->estado)); ?></span></p>
                    <p><strong>Fecha creación:</strong> <?php echo date('d/m/Y H:i', strtotime($cotizacion->fecha_creacion)); ?></p>
                    <p><strong>Validez:</strong> <?php echo esc_html($cotizacion->validez_dias); ?> días</p>
                </div>

                <div class="detail-card" style="background: #f8f9fa; padding: 20px; border-radius: 10px;">
                    <h3 style="margin-bottom: 15px; color: #333;">👤 Cliente</h3>
                    <p><strong>Nombre:</strong> <?php echo esc_html($cotizacion->cliente_nombre); ?></p>
                    <p><strong>Email:</strong> <?php echo esc_html($cotizacion->cliente_email); ?></p>
                    <p><strong>Teléfono:</strong> <?php echo esc_html($cotizacion->cliente_telefono ?: '-'); ?></p>
                    <p><strong>País:</strong> <?php echo esc_html($cotizacion->cliente_pais ?: '-'); ?></p>
                </div>

                <div class="detail-card" style="background: #f8f9fa; padding: 20px; border-radius: 10px;">
                    <h3 style="margin-bottom: 15px; color: #333;">🗺️ Tour</h3>
                    <p><strong>Tour:</strong> <?php echo esc_html($cotizacion->tour); ?></p>
                    <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($cotizacion->fecha_tour)); ?></p>
                    <p><strong>Pasajeros:</strong> <?php echo esc_html($cotizacion->cantidad_pasajeros); ?></p>
                </div>

                <div class="detail-card" style="background: #e8f5e9; padding: 20px; border-radius: 10px;">
                    <h3 style="margin-bottom: 15px; color: #333;">💰 Precios</h3>
                    <p><strong>Precio unitario:</strong> <?php echo esc_html($cotizacion->moneda); ?> <?php echo number_format($cotizacion->precio_unitario, 2); ?></p>
                    <p><strong>Descuento:</strong> <?php echo esc_html($cotizacion->descuento); ?><?php echo $cotizacion->descuento_tipo === 'porcentaje' ? '%' : ' ' . $cotizacion->moneda; ?></p>
                    <p style="font-size: 1.2em; font-weight: bold; color: #27ae60;"><strong>Total:</strong> <?php echo esc_html($cotizacion->moneda); ?> <?php echo number_format($cotizacion->precio_total, 2); ?></p>
                </div>
            </div>

            <?php if (!empty($cotizacion->notas)): ?>
            <div class="detail-card" style="background: #fff3cd; padding: 20px; border-radius: 10px; margin-top: 20px;">
                <h3 style="margin-bottom: 10px; color: #333;">📝 Notas para el cliente</h3>
                <p style="white-space: pre-line;"><?php echo esc_html($cotizacion->notas); ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty($cotizacion->notas_internas)): ?>
            <div class="detail-card" style="background: #f8d7da; padding: 20px; border-radius: 10px; margin-top: 20px;">
                <h3 style="margin-bottom: 10px; color: #333;">🔒 Notas internas (privado)</h3>
                <p style="white-space: pre-line;"><?php echo esc_html($cotizacion->notas_internas); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php
        $this->render_footer_shortcode();
    }

    /**
     * Lista de cotizaciones para shortcode
     */
    private function render_lista_cotizaciones_shortcode() {
        $user = wp_get_current_user();
        $estado = sanitize_text_field($_GET['estado'] ?? '');
        $buscar = sanitize_text_field($_GET['buscar'] ?? '');

        $args = ['vendedor_id' => $user->ID, 'per_page' => 50];
        if (!empty($estado)) $args['estado'] = $estado;
        if (!empty($buscar)) $args['buscar'] = $buscar;

        $result = RTT_Database::get_cotizaciones($args);
        $cotizaciones = $result['items'] ?? [];

        $this->render_header_shortcode('Mis Cotizaciones', 'lista');
        ?>
        <div class="lista-container">
            <div class="section-header">
                <div class="section-title">
                    <span class="section-title-icon">📋</span>
                    Lista de Cotizaciones
                </div>
                <a href="<?php echo esc_url($this->get_shortcode_url('nueva')); ?>" class="btn btn-primary">+ Nueva</a>
            </div>

            <div class="filters">
                <form method="get" class="filter-form">
                    <input type="hidden" name="panel" value="lista">
                    <select name="estado" onchange="this.form.submit()">
                        <option value="">Todos los estados</option>
                        <option value="borrador" <?php selected($estado, 'borrador'); ?>>Borrador</option>
                        <option value="enviada" <?php selected($estado, 'enviada'); ?>>Enviada</option>
                        <option value="aceptada" <?php selected($estado, 'aceptada'); ?>>Aceptada</option>
                        <option value="vencida" <?php selected($estado, 'vencida'); ?>>Vencida</option>
                    </select>
                    <input type="text" name="buscar" placeholder="Buscar..." value="<?php echo esc_attr($buscar); ?>">
                    <?php if (!empty($estado) || !empty($buscar)): ?>
                    <a href="<?php echo esc_url($this->get_shortcode_url('lista')); ?>" class="btn btn-sm btn-secondary">Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($cotizaciones)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <h3>No hay cotizaciones</h3>
                <p>Crea tu primera cotización</p>
                <a href="<?php echo esc_url($this->get_shortcode_url('nueva')); ?>" class="btn btn-primary">+ Nueva Cotización</a>
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
                    <?php foreach ($cotizaciones as $cot): ?>
                    <tr>
                        <td data-label="Código"><span class="table-code"><?php echo esc_html($cot->codigo); ?></span></td>
                        <td data-label="Cliente">
                            <div class="table-client">
                                <span class="table-client-name"><?php echo esc_html($cot->cliente_nombre); ?></span>
                                <span class="table-client-email"><?php echo esc_html($cot->cliente_email); ?></span>
                            </div>
                        </td>
                        <td data-label="Tour" class="table-tour"><?php echo esc_html($cot->tour); ?></td>
                        <td data-label="Total" class="table-price"><?php echo esc_html($cot->moneda); ?> <?php echo number_format($cot->precio_total, 2); ?></td>
                        <td data-label="Estado"><span class="badge badge-<?php echo esc_attr($cot->estado); ?>"><?php echo esc_html(ucfirst($cot->estado)); ?></span></td>
                        <td data-label="Fecha"><?php echo date('d/m/Y', strtotime($cot->fecha_creacion)); ?></td>
                        <td data-label="">
                            <div class="table-actions">
                                <a href="<?php echo esc_url($this->get_shortcode_url('ver') . '&id=' . $cot->id); ?>" class="btn-icon" title="Ver">👁️</a>
                                <a href="<?php echo esc_url($this->get_shortcode_url('editar') . '&id=' . $cot->id); ?>" class="btn-icon" title="Editar">✏️</a>
                                <button type="button" class="btn-icon btn-icon-delete btn-delete-cotizacion" data-id="<?php echo $cot->id; ?>" data-codigo="<?php echo esc_attr($cot->codigo); ?>" title="Eliminar">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
        $this->render_footer_shortcode();
    }

    /**
     * Configuración para shortcode
     */
    private function render_configuracion_shortcode() {
        if (!current_user_can('manage_options')) {
            $this->render_header_shortcode('Sin acceso', 'configuracion');
            echo '<div class="empty-state"><h3>No tienes permisos</h3></div>';
            $this->render_footer_shortcode();
            return;
        }

        $options = get_option('rtt_reservas_options', []);

        $this->render_header_shortcode('Configuración', 'configuracion');
        ?>
        <div class="form-container">
            <form id="config-form" class="cotizacion-form">
                <div class="form-section">
                    <h3>Tipo de Cambio</h3>
                    <p class="section-description">Tipo de cambio USD → PEN para cálculo de ganancias.</p>
                    <div class="form-row">
                        <div class="form-group" style="max-width: 200px;">
                            <label for="tipo_cambio">1 USD =</label>
                            <div class="input-group">
                                <input type="number" id="tipo_cambio" name="tipo_cambio" step="0.01" min="1" value="<?php echo esc_attr($options['tipo_cambio'] ?? '3.70'); ?>">
                                <span style="padding: 10px; background: #f5f5f5; border-radius: 0 6px 6px 0; border: 1px solid #ddd; border-left: 0;">PEN</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Formas de Pago</h3>
                    <p class="section-description">Esta información aparecerá en los PDFs de cotización.</p>
                    <div class="form-group">
                        <textarea id="cotizacion_formas_pago" name="cotizacion_formas_pago" rows="12" class="large-textarea"><?php
                            echo esc_textarea($options['cotizacion_formas_pago'] ?? '1. TRANSFERENCIA BANCARIA
   Banco: BCP - Banco de Crédito del Perú
   Cuenta Corriente Soles: XXX-XXXXXXX-X-XX
   Cuenta Corriente Dólares: XXX-XXXXXXX-X-XX
   CCI: XXXXXXXXXXXXXXXXXXX
   Titular: Ready To Travel Peru

2. PAYPAL
   Cuenta: pagos@readytotravelperu.com
   (Se aplica comisión de 5%)

3. PAGO EN EFECTIVO
   En nuestras oficinas o al momento del tour

* Enviar comprobante de pago a: reservas@readytotravelperu.com');
                        ?></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Términos y Condiciones</h3>
                    <div class="form-group">
                        <textarea id="cotizacion_terminos" name="cotizacion_terminos" rows="10" class="large-textarea"><?php
                            echo esc_textarea($options['cotizacion_terminos'] ?? '- Esta cotización tiene validez de 7 días a partir de la fecha de emisión.
- Los precios están sujetos a disponibilidad y pueden variar sin previo aviso.
- Para confirmar la reserva se requiere un depósito del 50% del total.
- El saldo restante debe cancelarse 48 horas antes del inicio del tour.
- Cancelaciones con más de 72 horas: devolución del 80% del depósito.
- Cancelaciones con menos de 72 horas: no hay devolución.
- Los tours están sujetos a condiciones climáticas.
- Es obligatorio presentar documento de identidad el día del tour.
- Menores de edad deben estar acompañados por un adulto responsable.');
                        ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar Configuración</button>
                </div>

                <div id="form-message" class="form-message" style="display: none;"></div>
            </form>
        </div>
        <?php
        $this->render_footer_shortcode("
            $('#config-form').on('submit', function(e) {
                e.preventDefault();
                var btn = $(this).find('button[type=submit]');
                btn.prop('disabled', true).text('Guardando...');
                $('#form-message').hide();

                $.post(rttAjax.url, {
                    action: 'rtt_save_configuracion',
                    tipo_cambio: $('#tipo_cambio').val(),
                    cotizacion_formas_pago: $('#cotizacion_formas_pago').val(),
                    cotizacion_terminos: $('#cotizacion_terminos').val()
                }, function(response) {
                    if (response.success) {
                        $('#form-message').removeClass('error').addClass('success').text('Configuración guardada').show();
                    } else {
                        $('#form-message').removeClass('success').addClass('error').text(response.data.message).show();
                    }
                    btn.prop('disabled', false).text('Guardar Configuración');
                });
            });
        ");
    }

    /**
     * Proveedores para shortcode
     */
    private function render_proveedores_shortcode() {
        $tipos = RTT_Database::get_tipos_proveedores();
        $tipo_filtro = sanitize_text_field($_GET['tipo'] ?? '');
        $buscar = sanitize_text_field($_GET['buscar'] ?? '');
        $proveedores = RTT_Database::get_proveedores(['tipo' => $tipo_filtro]);

        // Filtrar por búsqueda
        if (!empty($buscar)) {
            $proveedores = array_filter($proveedores, function($p) use ($buscar) {
                $buscar_lower = strtolower($buscar);
                return strpos(strtolower($p->nombre), $buscar_lower) !== false ||
                       strpos(strtolower($p->contacto), $buscar_lower) !== false;
            });
        }

        // Stats
        $all_proveedores = RTT_Database::get_proveedores([]);
        $stats = ['total' => count($all_proveedores)];
        foreach ($tipos as $key => $label) {
            $stats[$key] = 0;
        }
        foreach ($all_proveedores as $p) {
            if (isset($stats[$p->tipo])) {
                $stats[$p->tipo]++;
            }
        }

        $tipo_icons = [
            'guia' => '👨‍🏫', 'transporte' => '🚐', 'restaurante' => '🍽️',
            'hotel' => '🏨', 'actividad' => '🎯', 'entrada' => '🎟️', 'otro' => '📦'
        ];

        $this->render_header_shortcode('Proveedores', 'proveedores');
        ?>
        <div class="lista-container">
            <div class="section-header">
                <div class="section-title">
                    <span class="section-title-icon">👥</span>
                    Directorio de Proveedores
                </div>
                <button type="button" class="btn btn-primary" id="btn-nuevo-proveedor">+ Nuevo Proveedor</button>
            </div>

            <?php
            // URL base para filtros (sin ?panel= si estamos en vista fija)
            $filter_base_url = $this->get_shortcode_url('proveedores');
            ?>
            <div class="provider-stats">
                <a href="<?php echo esc_url($filter_base_url); ?>" class="provider-stat <?php echo empty($tipo_filtro) ? 'active' : ''; ?>">
                    <div class="provider-stat-icon">📊</div>
                    <div class="provider-stat-count"><?php echo $stats['total']; ?></div>
                    <div class="provider-stat-label">Total</div>
                </a>
                <?php foreach ($tipos as $key => $label): ?>
                <a href="<?php echo esc_url(add_query_arg('tipo', $key, $filter_base_url)); ?>" class="provider-stat <?php echo $tipo_filtro === $key ? 'active' : ''; ?>">
                    <div class="provider-stat-icon"><?php echo $tipo_icons[$key] ?? '📦'; ?></div>
                    <div class="provider-stat-count"><?php echo $stats[$key] ?? 0; ?></div>
                    <div class="provider-stat-label"><?php echo esc_html($label); ?></div>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="filters">
                <form method="get" class="filter-form provider-filters">
                    <input type="hidden" name="panel" value="proveedores">
                    <div class="filter-group">
                        <label>Tipo:</label>
                        <select name="tipo" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <?php foreach ($tipos as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($tipo_filtro, $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group search-input-wrapper">
                        <input type="text" name="buscar" placeholder="Buscar..." value="<?php echo esc_attr($buscar); ?>">
                    </div>
                </form>
            </div>

            <?php if (empty($proveedores)): ?>
            <div class="empty-state-providers">
                <div class="empty-icon">👥</div>
                <h3>No hay proveedores</h3>
                <p>Agrega tu primer proveedor</p>
                <button type="button" class="btn btn-primary" id="btn-nuevo-proveedor-empty">+ Agregar</button>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Proveedor</th>
                        <th>Contacto</th>
                        <th>Teléfono</th>
                        <th>Costo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proveedores as $prov): ?>
                    <tr data-id="<?php echo $prov->id; ?>">
                        <td data-label="Tipo"><span class="badge badge-<?php echo esc_attr($prov->tipo); ?>"><?php echo $tipo_icons[$prov->tipo] ?? ''; ?> <?php echo esc_html($tipos[$prov->tipo] ?? $prov->tipo); ?></span></td>
                        <td data-label="Proveedor"><strong><?php echo esc_html($prov->nombre); ?></strong></td>
                        <td data-label="Contacto"><?php echo esc_html($prov->contacto ?: '—'); ?></td>
                        <td data-label="Teléfono"><?php echo esc_html($prov->telefono ?: '—'); ?></td>
                        <td data-label="Costo"><?php echo $prov->costo_base > 0 ? esc_html($prov->moneda) . ' ' . number_format($prov->costo_base, 2) : '—'; ?></td>
                        <td data-label="Estado"><?php echo $prov->activo ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-danger">Inactivo</span>'; ?></td>
                        <td data-label="">
                            <div class="table-actions">
                                <button type="button" class="btn-icon btn-edit" data-id="<?php echo $prov->id; ?>">✏️</button>
                                <button type="button" class="btn-icon btn-icon-delete btn-delete" data-id="<?php echo $prov->id; ?>">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Modal Proveedor -->
        <div id="modal-proveedor" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="modal-title">Nuevo Proveedor</h2>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <form id="proveedor-form">
                    <input type="hidden" name="id" value="0">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipo *</label>
                            <select id="prov_tipo" name="tipo" required>
                                <?php foreach ($tipos as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo ($tipo_icons[$key] ?? '') . ' ' . esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Nombre *</label>
                            <input type="text" id="prov_nombre" name="nombre" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Contacto</label>
                            <input type="text" id="prov_contacto" name="contacto">
                        </div>
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="text" id="prov_telefono" name="telefono">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="prov_email" name="email">
                        </div>
                        <div class="form-group">
                            <label>Costo base</label>
                            <div class="input-group">
                                <select id="prov_moneda" name="moneda">
                                    <option value="PEN">S/</option>
                                    <option value="USD">$</option>
                                </select>
                                <input type="number" id="prov_costo" name="costo_base" value="0" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notas</label>
                        <textarea id="prov_notas" name="notas" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="prov_activo" name="activo" value="1" checked>
                            <span>Proveedor activo</span>
                        </label>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $this->render_footer_shortcode($this->get_proveedores_scripts());
    }

    /**
     * Scripts de cotización para shortcode
     */
    private function get_cotizacion_form_scripts_shortcode() {
        return $this->get_cotizacion_form_scripts();
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
            case 'configuracion':
                $this->render_configuracion();
                break;
            case 'proveedores':
                $this->render_proveedores();
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

        // Procesar costos JSON
        $costos_json = stripslashes($_POST['costos_json'] ?? '[]');
        $costos = json_decode($costos_json, true) ?: [];
        $costo_total = 0;
        foreach ($costos as $costo) {
            $costo_total += floatval($costo['monto'] ?? 0);
        }

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
            // Costos internos (nuevo formato JSON)
            'costos_json' => $costos_json,
            'costo_total' => $costo_total,
            'tipo_cambio' => floatval($_POST['tipo_cambio'] ?? 3.70),
            'notas_internas' => sanitize_textarea_field($_POST['notas_internas'] ?? ''),
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
     * Header común del panel con sidebar moderno
     */
    private function render_header($title = 'Panel de Vendedor', $active_page = '') {
        $user = wp_get_current_user();
        $initials = strtoupper(substr($user->display_name, 0, 2));
        $is_admin = in_array('administrator', $user->roles);

        // Determinar página activa
        if (empty($active_page)) {
            $action = get_query_var('rtt_seller_action', 'dashboard');
            $active_page = $action;
        }
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title><?php echo esc_html($title); ?> - RTT Reservas</title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="<?php echo RTT_RESERVAS_PLUGIN_URL; ?>assets/css/seller-panel.css?v=<?php echo RTT_RESERVAS_VERSION; ?>">
        </head>
        <body>
            <div class="app-layout">
                <!-- Sidebar -->
                <aside class="sidebar">
                    <div class="sidebar-header">
                        <div class="sidebar-logo">RTT</div>
                        <div class="sidebar-brand">
                            <h1>Cotizador</h1>
                            <span>Ready To Travel</span>
                        </div>
                    </div>

                    <nav class="sidebar-nav">
                        <div class="nav-section">
                            <span class="nav-section-title">Principal</span>
                            <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/'); ?>" class="nav-item <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>">
                                <span class="nav-icon">📊</span>
                                <span class="nav-text">Dashboard</span>
                            </a>
                            <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/nueva/'); ?>" class="nav-item <?php echo $active_page === 'nueva' ? 'active' : ''; ?>">
                                <span class="nav-icon">➕</span>
                                <span class="nav-text">Nueva Cotización</span>
                            </a>
                            <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/lista/'); ?>" class="nav-item <?php echo $active_page === 'lista' ? 'active' : ''; ?>">
                                <span class="nav-icon">📋</span>
                                <span class="nav-text">Mis Cotizaciones</span>
                            </a>
                        </div>

                        <div class="nav-section">
                            <span class="nav-section-title">Gestión</span>
                            <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/proveedores/'); ?>" class="nav-item <?php echo $active_page === 'proveedores' ? 'active' : ''; ?>">
                                <span class="nav-icon">🏢</span>
                                <span class="nav-text">Proveedores</span>
                            </a>
                            <?php if ($is_admin): ?>
                            <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/configuracion/'); ?>" class="nav-item <?php echo $active_page === 'configuracion' ? 'active' : ''; ?>">
                                <span class="nav-icon">⚙️</span>
                                <span class="nav-text">Configuración</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </nav>

                    <div class="sidebar-user">
                        <div class="user-avatar"><?php echo esc_html($initials); ?></div>
                        <div class="user-info">
                            <span class="user-name"><?php echo esc_html($user->display_name); ?></span>
                            <span class="user-role"><?php echo $is_admin ? 'Administrador' : 'Vendedor'; ?></span>
                        </div>
                        <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/logout/'); ?>" class="btn-logout-small" title="Cerrar sesión">🚪</a>
                    </div>
                </aside>

                <!-- Overlay for mobile -->
                <div class="sidebar-overlay"></div>

                <!-- Main Content -->
                <div class="main-wrapper">
                    <header class="header-bar">
                        <button class="mobile-menu-btn" type="button">☰</button>
                        <h1 class="header-title"><?php echo esc_html($title); ?></h1>
                        <div class="header-actions">
                            <?php if ($active_page !== 'nueva'): ?>
                            <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/nueva/'); ?>" class="btn btn-success btn-sm">+ Nueva</a>
                            <?php endif; ?>
                        </div>
                    </header>
                    <main class="main-content">
        <?php
    }

    /**
     * Footer común del panel
     */
    private function render_footer($extra_scripts = '') {
        ?>
                    </main>
                </div><!-- .main-wrapper -->
            </div><!-- .app-layout -->

            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script>
                var rttAjax = {
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    dashboardUrl: '<?php echo home_url('/' . self::PAGE_SLUG . '/'); ?>'
                };
            </script>
            <script src="<?php echo RTT_RESERVAS_PLUGIN_URL; ?>assets/js/seller-panel.js?v=<?php echo RTT_RESERVAS_VERSION; ?>"></script>
            <?php if (!empty($extra_scripts)): ?>
            <script>
                jQuery(document).ready(function($) {
                    <?php echo $extra_scripts; ?>
                });
            </script>
            <?php endif; ?>
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
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="<?php echo RTT_RESERVAS_PLUGIN_URL; ?>assets/css/seller-panel.css?v=<?php echo RTT_RESERVAS_VERSION; ?>">
        </head>
        <body class="login-page">
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
                    <div id="login-error" class="login-error" style="display: none;"></div>
                </form>
            </div>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script>
                var rttAjax = { url: '<?php echo admin_url('admin-ajax.php'); ?>' };
            </script>
            <script src="<?php echo RTT_RESERVAS_PLUGIN_URL; ?>assets/js/seller-panel.js?v=<?php echo RTT_RESERVAS_VERSION; ?>"></script>
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

        $this->render_header('Dashboard', 'dashboard');
        ?>
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1 class="welcome-title">Bienvenido, <?php echo esc_html($user->display_name); ?></h1>
            <p class="welcome-subtitle">Aquí tienes un resumen de tus cotizaciones</p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Cotizaciones</div>
                </div>
            </div>
            <div class="stat-card stat-warning">
                <div class="stat-icon">📝</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['borradores']; ?></div>
                    <div class="stat-label">Borradores</div>
                </div>
            </div>
            <div class="stat-card stat-info">
                <div class="stat-icon">📧</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['enviadas']; ?></div>
                    <div class="stat-label">Enviadas</div>
                </div>
            </div>
            <div class="stat-card stat-success">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['aceptadas']; ?></div>
                    <div class="stat-label">Aceptadas</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/nueva/'); ?>" class="quick-action-card">
                <div class="quick-action-icon">➕</div>
                <div class="quick-action-text">
                    <h3>Nueva Cotización</h3>
                    <p>Crear cotización para cliente</p>
                </div>
            </a>
            <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/lista/'); ?>" class="quick-action-card secondary">
                <div class="quick-action-icon">📋</div>
                <div class="quick-action-text">
                    <h3>Ver Todas</h3>
                    <p>Gestionar mis cotizaciones</p>
                </div>
            </a>
        </div>

        <!-- Recent Quotations -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title">
                    <span class="section-title-icon">📋</span>
                    Cotizaciones Recientes
                </h2>
                <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/lista/'); ?>" class="btn btn-outline btn-sm">Ver todas</a>
            </div>

            <?php if (empty($cotizaciones['items'])): ?>
            <div class="section-body">
                <div class="empty-state">
                    <div class="empty-state-icon">📄</div>
                    <h3>No tienes cotizaciones aún</h3>
                    <p>Crea tu primera cotización para comenzar</p>
                    <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/nueva/'); ?>" class="btn btn-primary">Crear primera cotización</a>
                </div>
            </div>
            <?php else: ?>
            <div class="section-body" style="padding: 0;">
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
                            <td><span class="table-code"><?php echo esc_html($cot->codigo); ?></span></td>
                            <td>
                                <div class="table-client">
                                    <span class="table-client-name"><?php echo esc_html($cot->cliente_nombre); ?></span>
                                    <span class="table-client-email"><?php echo esc_html($cot->cliente_email); ?></span>
                                </div>
                            </td>
                            <td><span class="table-tour"><?php echo esc_html(mb_substr($cot->tour, 0, 35)); ?><?php echo strlen($cot->tour) > 35 ? '...' : ''; ?></span></td>
                            <td><span class="table-price"><?php echo esc_html($cot->moneda); ?> <?php echo number_format($cot->precio_total, 2); ?></span></td>
                            <td><span class="badge badge-<?php echo esc_attr($cot->estado); ?>"><?php echo esc_html(ucfirst($cot->estado)); ?></span></td>
                            <td><?php echo date_i18n('d/m/Y', strtotime($cot->fecha_creacion)); ?></td>
                            <td class="table-actions">
                                <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/editar/?id=' . $cot->id); ?>" class="btn btn-sm btn-outline">Editar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
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
                                    $price_display = $tour_price > 0 ? ' - $' . number_format($tour_price, 0) : '';
                                ?>
                                <option value="<?php echo esc_attr($tour_name); ?>" data-price="<?php echo esc_attr($tour_price); ?>">
                                    <?php echo esc_html($tour_name); ?><?php echo $price_display; ?>
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

                <div class="form-section costos-internos-section">
                    <div class="costos-header">
                        <div class="costos-header-icon">💰</div>
                        <div class="costos-header-text">
                            <h3>Costos Internos</h3>
                            <span class="costos-header-badge">Privado</span>
                        </div>
                        <div class="tipo-cambio-mini">
                            <label>T/C:</label>
                            <input type="number" id="tipo_cambio" name="tipo_cambio" step="0.01" min="1" value="<?php echo esc_attr($options['tipo_cambio'] ?? '3.70'); ?>">
                        </div>
                    </div>

                    <div class="costos-body">
                        <div class="costos-list" id="costos-items">
                            <div class="costo-item">
                                <div class="costo-drag">⋮⋮</div>
                                <input type="text" name="costo_concepto[]" placeholder="Ej: Guía, Transporte, Entradas..." class="costo-concepto">
                                <div class="costo-monto-wrapper">
                                    <span class="costo-prefix">$</span>
                                    <input type="number" name="costo_monto[]" placeholder="0.00" min="0" step="0.01" class="costo-monto">
                                </div>
                                <button type="button" class="btn-remove-costo" title="Eliminar">×</button>
                            </div>
                        </div>
                        <button type="button" class="btn-add-costo-new" id="btn-add-costo">
                            <span class="btn-add-icon">+</span> Agregar costo
                        </button>
                        <input type="hidden" id="costos_json" name="costos_json" value="">
                    </div>

                    <div class="costos-summary">
                        <div class="summary-card summary-costo">
                            <div class="summary-icon">📊</div>
                            <div class="summary-content">
                                <span class="summary-label">Costo Total</span>
                                <span class="summary-value" id="costo_total_display">$ 0.00</span>
                            </div>
                        </div>
                        <div class="summary-card summary-ganancia" id="ganancia-card">
                            <div class="summary-icon">📈</div>
                            <div class="summary-content">
                                <span class="summary-label">Ganancia</span>
                                <span class="summary-value-main" id="ganancia_usd">$ 0.00</span>
                                <span class="summary-value-secondary" id="ganancia_pen">S/ 0.00</span>
                            </div>
                            <div class="summary-badge" id="ganancia_pct">0%</div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 15px;">
                        <label for="notas_internas">📝 Notas internas</label>
                        <textarea id="notas_internas" name="notas_internas" rows="2" placeholder="Notas privadas (no aparecen en la cotización)..."></textarea>
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
                                    $price_display = $tour_price > 0 ? ' - $' . number_format($tour_price, 0) : '';
                                ?>
                                <option value="<?php echo esc_attr($tour_name); ?>" data-price="<?php echo esc_attr($tour_price); ?>" <?php selected($cotizacion->tour, $tour_name); ?>>
                                    <?php echo esc_html($tour_name); ?><?php echo $price_display; ?>
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

                <div class="form-section costos-internos-section">
                    <div class="costos-header">
                        <div class="costos-header-icon">💰</div>
                        <div class="costos-header-text">
                            <h3>Costos Internos</h3>
                            <span class="costos-header-badge">Privado</span>
                        </div>
                        <div class="tipo-cambio-mini">
                            <label>T/C:</label>
                            <input type="number" id="tipo_cambio" name="tipo_cambio" step="0.01" min="1" value="<?php echo esc_attr($cotizacion->tipo_cambio ?? $options['tipo_cambio'] ?? '3.70'); ?>">
                        </div>
                    </div>

                    <div class="costos-body">
                        <div class="costos-list" id="costos-items">
                            <?php
                            $costos_guardados = [];
                            if (!empty($cotizacion->costos_json)) {
                                $costos_guardados = json_decode($cotizacion->costos_json, true) ?: [];
                            }
                            if (empty($costos_guardados)) {
                                $costos_guardados = [['concepto' => '', 'monto' => '']];
                            }
                            foreach ($costos_guardados as $costo):
                            ?>
                            <div class="costo-item">
                                <div class="costo-drag">⋮⋮</div>
                                <input type="text" name="costo_concepto[]" placeholder="Ej: Guía, Transporte, Entradas..." class="costo-concepto" value="<?php echo esc_attr($costo['concepto'] ?? ''); ?>">
                                <div class="costo-monto-wrapper">
                                    <span class="costo-prefix">$</span>
                                    <input type="number" name="costo_monto[]" placeholder="0.00" min="0" step="0.01" class="costo-monto" value="<?php echo esc_attr($costo['monto'] ?? ''); ?>">
                                </div>
                                <button type="button" class="btn-remove-costo" title="Eliminar">×</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn-add-costo-new" id="btn-add-costo">
                            <span class="btn-add-icon">+</span> Agregar costo
                        </button>
                        <input type="hidden" id="costos_json" name="costos_json" value="<?php echo esc_attr($cotizacion->costos_json ?? ''); ?>">
                    </div>

                    <div class="costos-summary">
                        <div class="summary-card summary-costo">
                            <div class="summary-icon">📊</div>
                            <div class="summary-content">
                                <span class="summary-label">Costo Total</span>
                                <span class="summary-value" id="costo_total_display">$ 0.00</span>
                            </div>
                        </div>
                        <div class="summary-card summary-ganancia" id="ganancia-card">
                            <div class="summary-icon">📈</div>
                            <div class="summary-content">
                                <span class="summary-label">Ganancia</span>
                                <span class="summary-value-main" id="ganancia_usd">$ 0.00</span>
                                <span class="summary-value-secondary" id="ganancia_pen">S/ 0.00</span>
                            </div>
                            <div class="summary-badge" id="ganancia_pct">0%</div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 15px;">
                        <label for="notas_internas">📝 Notas internas</label>
                        <textarea id="notas_internas" name="notas_internas" rows="2" placeholder="Notas privadas (no aparecen en la cotización)..."><?php echo esc_textarea($cotizacion->notas_internas ?? ''); ?></textarea>
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
                        <td data-label="Código"><strong><?php echo esc_html($cot->codigo); ?></strong></td>
                        <td data-label="Cliente">
                            <span class="table-client-name"><?php echo esc_html($cot->cliente_nombre); ?></span><br>
                            <small class="table-client-email"><?php echo esc_html($cot->cliente_email); ?></small>
                        </td>
                        <td data-label="Tour"><?php echo esc_html(substr($cot->tour, 0, 25)); ?>...</td>
                        <td data-label="Fecha Tour"><?php echo date_i18n('d/m/Y', strtotime($cot->fecha_tour)); ?></td>
                        <td data-label="Total"><?php echo esc_html($cot->moneda); ?> <?php echo number_format($cot->precio_total, 2); ?></td>
                        <td data-label="Estado"><span class="badge badge-<?php echo esc_attr($cot->estado); ?>"><?php echo esc_html(ucfirst($cot->estado)); ?></span></td>
                        <td data-label="Creada"><?php echo date_i18n('d/m/Y', strtotime($cot->fecha_creacion)); ?></td>
                        <td data-label="" class="actions">
                            <div class="table-actions">
                                <a href="<?php echo home_url('/' . self::PAGE_SLUG . '/editar/?id=' . $cot->id); ?>" class="btn-icon" title="Editar">✏️</a>
                                <?php if ($cot->estado === 'borrador'): ?>
                                <button type="button" class="btn-icon btn-send" data-id="<?php echo $cot->id; ?>" title="Enviar">📤</button>
                                <?php endif; ?>
                                <button type="button" class="btn-icon btn-icon-delete btn-delete" data-id="<?php echo $cot->id; ?>" title="Eliminar">🗑️</button>
                            </div>
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
     * Nota: La lógica principal está en assets/js/seller-panel.js
     */
    private function get_cotizacion_form_scripts() {
        // Scripts manejados por seller-panel.js
        return "";
    }

    /**
     * Estilos de login (obsoleto - usar assets/css/seller-panel.css)
     * @deprecated
     */
    private function get_login_styles() {
        return "";
    }

    /**
     * Estilos del panel (obsoleto - usar assets/css/seller-panel.css)
     * @deprecated
     */
    private function get_panel_styles() {
        return ""; // Estilos movidos a archivo externo
    }

    /**
     * LEGACY STYLES - TODO: Eliminar en siguiente versión
     * Mantenido temporalmente por compatibilidad
     */
    private function get_panel_styles_legacy() {
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

        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 20px;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .modal-header h2 { margin: 0; }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        /* Large textarea */
        .large-textarea {
            width: 100%;
            font-family: monospace;
            font-size: 13px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .section-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        /* Badges extra */
        .badge-guia { background: #3498db; color: white; }
        .badge-transporte { background: #e67e22; color: white; }
        .badge-hotel { background: #9b59b6; color: white; }
        .badge-restaurante { background: #e74c3c; color: white; }
        .badge-entrada { background: #1abc9c; color: white; }
        .badge-otro { background: #95a5a6; color: white; }
        .badge-success { background: #27ae60; color: white; }
        .badge-danger { background: #e74c3c; color: white; }

        /* Costos Internos - Nuevo diseño */
        .costos-internos-section {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border: none;
            border-radius: 16px;
            overflow: hidden;
        }
        .costos-header {
            display: flex;
            align-items: center;
            padding: 20px;
            gap: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .costos-header-icon {
            font-size: 32px;
            filter: grayscale(0);
        }
        .costos-header-text h3 {
            margin: 0;
            color: white;
            font-size: 18px;
        }
        .costos-header-badge {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .tipo-cambio-mini {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.1);
            padding: 8px 12px;
            border-radius: 8px;
        }
        .tipo-cambio-mini label {
            color: rgba(255,255,255,0.7);
            font-size: 12px;
            margin: 0;
        }
        .tipo-cambio-mini input {
            width: 70px;
            padding: 5px 8px;
            border: none;
            border-radius: 5px;
            background: rgba(255,255,255,0.9);
            font-size: 14px;
            font-weight: 600;
        }
        .costos-body {
            padding: 20px;
        }
        .costos-list {
            margin-bottom: 15px;
        }
        .costo-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.05);
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 8px;
            transition: all 0.2s;
        }
        .costo-item:hover {
            background: rgba(255,255,255,0.1);
        }
        .costo-drag {
            color: rgba(255,255,255,0.3);
            cursor: grab;
            font-size: 14px;
            padding: 0 5px;
        }
        .costo-item .costo-concepto {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            background: rgba(255,255,255,0.95);
            font-size: 14px;
        }
        .costo-monto-wrapper {
            display: flex;
            align-items: center;
            background: rgba(255,255,255,0.95);
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            overflow: hidden;
        }
        .costo-prefix {
            padding: 10px 8px 10px 12px;
            color: #666;
            font-weight: 600;
        }
        .costo-item .costo-monto {
            width: 90px;
            padding: 10px 12px 10px 0;
            border: none;
            background: transparent;
            font-size: 14px;
            font-weight: 600;
        }
        .btn-remove-costo {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            border: none;
            border-radius: 8px;
            width: 36px;
            height: 36px;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.2s;
        }
        .btn-remove-costo:hover {
            background: #e74c3c;
            color: white;
        }
        .btn-add-costo-new {
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.1);
            border: 2px dashed rgba(255,255,255,0.2);
            border-radius: 10px;
            color: rgba(255,255,255,0.7);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-add-costo-new:hover {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.4);
            color: white;
        }
        .btn-add-icon {
            font-size: 20px;
            font-weight: 300;
        }

        /* Summary Cards */
        .costos-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            padding: 0 20px 20px;
        }
        .summary-card {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .summary-icon {
            font-size: 28px;
        }
        .summary-content {
            flex: 1;
        }
        .summary-label {
            display: block;
            color: rgba(255,255,255,0.6);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-value {
            display: block;
            color: white;
            font-size: 22px;
            font-weight: 700;
        }
        .summary-ganancia {
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.2) 0%, rgba(39, 174, 96, 0.1) 100%);
            position: relative;
        }
        .summary-value-main {
            display: block;
            color: #2ecc71;
            font-size: 24px;
            font-weight: 700;
        }
        .summary-value-secondary {
            display: block;
            color: rgba(255,255,255,0.5);
            font-size: 13px;
        }
        .summary-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #2ecc71;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .summary-ganancia.negative .summary-value-main { color: #e74c3c; }
        .summary-ganancia.negative .summary-badge { background: #e74c3c; }
        .summary-ganancia.warning .summary-value-main { color: #f39c12; }
        .summary-ganancia.warning .summary-badge { background: #f39c12; }

        .costos-internos-section .form-group label {
            color: rgba(255,255,255,0.8);
        }
        .costos-internos-section textarea {
            background: rgba(255,255,255,0.95);
            border: 1px solid rgba(255,255,255,0.2);
        }

        @media (max-width: 768px) {
            .navbar { padding: 0 15px; }
            .nav-menu { display: none; }
            .main-content { padding: 15px; }
            .form-row { grid-template-columns: 1fr; }
            .costos-header { flex-wrap: wrap; }
            .tipo-cambio-mini { width: 100%; margin-top: 10px; justify-content: center; }
            .costos-summary { grid-template-columns: 1fr; }
            .costo-drag { display: none; }
        }
        ";
    }

    /**
     * Scripts generales del panel (obsoleto - usar assets/js/seller-panel.js)
     * @deprecated
     */
    private function get_panel_scripts() {
        return ""; // Scripts movidos a archivo externo
    }

    /**
     * Página de Configuración
     */
    private function render_configuracion() {
        // Solo admin puede acceder a configuración
        $user = wp_get_current_user();
        if (!in_array('administrator', $user->roles)) {
            wp_redirect(home_url('/' . self::PAGE_SLUG . '/'));
            exit;
        }

        $options = get_option('rtt_reservas_options', []);

        $this->render_header('Configuración');
        ?>
        <div class="form-container">
            <h1>Configuración de Cotizaciones</h1>

            <form id="config-form" class="cotizacion-form">
                <div class="form-section">
                    <h3>Tipo de Cambio</h3>
                    <p class="section-description">Tipo de cambio USD → PEN para cálculo de ganancias.</p>
                    <div class="form-row">
                        <div class="form-group" style="max-width: 200px;">
                            <label for="tipo_cambio">1 USD =</label>
                            <div class="input-group">
                                <input type="number" id="tipo_cambio" name="tipo_cambio" step="0.01" min="1" value="<?php echo esc_attr($options['tipo_cambio'] ?? '3.70'); ?>">
                                <span style="padding: 10px; background: #f5f5f5; border-radius: 0 6px 6px 0; border: 1px solid #ddd; border-left: 0;">PEN</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Formas de Pago</h3>
                    <p class="section-description">Esta información aparecerá en los PDFs de cotización.</p>
                    <div class="form-group">
                        <textarea id="cotizacion_formas_pago" name="cotizacion_formas_pago" rows="12" class="large-textarea"><?php
                            echo esc_textarea($options['cotizacion_formas_pago'] ?? '1. TRANSFERENCIA BANCARIA
   Banco: BCP - Banco de Crédito del Perú
   Cuenta Corriente Soles: XXX-XXXXXXX-X-XX
   Cuenta Corriente Dólares: XXX-XXXXXXX-X-XX
   CCI: XXXXXXXXXXXXXXXXXXX
   Titular: Ready To Travel Peru

2. PAYPAL
   Cuenta: pagos@readytotravelperu.com
   (Se aplica comisión de 5%)

3. PAGO EN EFECTIVO
   En nuestras oficinas o al momento del tour

* Enviar comprobante de pago a: reservas@readytotravelperu.com');
                        ?></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Términos y Condiciones</h3>
                    <div class="form-group">
                        <textarea id="cotizacion_terminos" name="cotizacion_terminos" rows="10" class="large-textarea"><?php
                            echo esc_textarea($options['cotizacion_terminos'] ?? '- Esta cotización tiene validez de 7 días a partir de la fecha de emisión.
- Los precios están sujetos a disponibilidad y pueden variar sin previo aviso.
- Para confirmar la reserva se requiere un depósito del 50% del total.
- El saldo restante debe cancelarse 48 horas antes del inicio del tour.
- Cancelaciones con más de 72 horas: devolución del 80% del depósito.
- Cancelaciones con menos de 72 horas: no hay devolución.
- Los tours están sujetos a condiciones climáticas.
- Es obligatorio presentar documento de identidad el día del tour.
- Menores de edad deben estar acompañados por un adulto responsable.');
                        ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar Configuración</button>
                </div>

                <div id="form-message" class="form-message" style="display: none;"></div>
            </form>
        </div>
        <?php
        $this->render_footer("
            $('#config-form').on('submit', function(e) {
                e.preventDefault();
                var btn = $(this).find('button[type=submit]');
                btn.prop('disabled', true).text('Guardando...');
                $('#form-message').hide();

                $.post(rttAjax.url, {
                    action: 'rtt_save_configuracion',
                    tipo_cambio: $('#tipo_cambio').val(),
                    cotizacion_formas_pago: $('#cotizacion_formas_pago').val(),
                    cotizacion_terminos: $('#cotizacion_terminos').val()
                }, function(response) {
                    if (response.success) {
                        $('#form-message').removeClass('error').addClass('success').text('Configuración guardada').show();
                    } else {
                        $('#form-message').removeClass('success').addClass('error').text(response.data.message).show();
                    }
                    btn.prop('disabled', false).text('Guardar Configuración');
                });
            });
        ");
    }

    /**
     * AJAX: Guardar configuración
     */
    public function ajax_save_configuracion() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sin permisos - Solo administradores']);
        }

        $options = get_option('rtt_reservas_options', []);

        // Tipo de cambio
        $options['tipo_cambio'] = floatval($_POST['tipo_cambio'] ?? 3.70);

        // Formas de pago - preservar saltos de línea
        $formas_pago = isset($_POST['cotizacion_formas_pago']) ? $_POST['cotizacion_formas_pago'] : '';
        $options['cotizacion_formas_pago'] = wp_kses_post($formas_pago);

        // Términos - preservar saltos de línea
        $terminos = isset($_POST['cotizacion_terminos']) ? $_POST['cotizacion_terminos'] : '';
        $options['cotizacion_terminos'] = wp_kses_post($terminos);

        $result = update_option('rtt_reservas_options', $options);

        if ($result) {
            wp_send_json_success(['message' => 'Configuración guardada correctamente']);
        } else {
            // Si no hubo cambios, igual es éxito
            wp_send_json_success(['message' => 'Configuración guardada']);
        }
    }

    /**
     * Página de Proveedores
     */
    private function render_proveedores() {
        $tipos = RTT_Database::get_tipos_proveedores();
        $tipo_filtro = sanitize_text_field($_GET['tipo'] ?? '');
        $buscar = sanitize_text_field($_GET['buscar'] ?? '');
        $proveedores = RTT_Database::get_proveedores(['tipo' => $tipo_filtro]);

        // Filtrar por búsqueda si existe
        if (!empty($buscar)) {
            $proveedores = array_filter($proveedores, function($p) use ($buscar) {
                $buscar_lower = strtolower($buscar);
                return strpos(strtolower($p->nombre), $buscar_lower) !== false ||
                       strpos(strtolower($p->contacto), $buscar_lower) !== false ||
                       strpos(strtolower($p->telefono), $buscar_lower) !== false;
            });
        }

        // Contar por tipo para estadísticas
        $all_proveedores = RTT_Database::get_proveedores([]);
        $stats = ['total' => count($all_proveedores)];
        foreach ($tipos as $key => $label) {
            $stats[$key] = 0;
        }
        foreach ($all_proveedores as $p) {
            if (isset($stats[$p->tipo])) {
                $stats[$p->tipo]++;
            }
        }

        // Iconos por tipo
        $tipo_icons = [
            'guia' => '👨‍🏫',
            'transporte' => '🚐',
            'restaurante' => '🍽️',
            'hotel' => '🏨',
            'actividad' => '🎯',
            'entrada' => '🎟️',
            'otro' => '📦'
        ];

        $this->render_header('Proveedores', 'proveedores');
        ?>
        <div class="lista-container">
            <div class="section-header">
                <div class="section-title">
                    <span class="section-title-icon">👥</span>
                    Directorio de Proveedores
                </div>
                <button type="button" class="btn btn-primary" id="btn-nuevo-proveedor">
                    <span>+</span> Nuevo Proveedor
                </button>
            </div>

            <!-- Stats por tipo -->
            <div class="provider-stats">
                <a href="?tipo=" class="provider-stat <?php echo empty($tipo_filtro) ? 'active' : ''; ?>">
                    <div class="provider-stat-icon">📊</div>
                    <div class="provider-stat-count"><?php echo $stats['total']; ?></div>
                    <div class="provider-stat-label">Total</div>
                </a>
                <?php foreach ($tipos as $key => $label): ?>
                <a href="?tipo=<?php echo esc_attr($key); ?>" class="provider-stat <?php echo $tipo_filtro === $key ? 'active' : ''; ?>">
                    <div class="provider-stat-icon"><?php echo $tipo_icons[$key] ?? '📦'; ?></div>
                    <div class="provider-stat-count"><?php echo $stats[$key] ?? 0; ?></div>
                    <div class="provider-stat-label"><?php echo esc_html($label); ?></div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Filtros -->
            <div class="filters">
                <form method="get" class="filter-form provider-filters">
                    <div class="filter-group">
                        <label>Tipo:</label>
                        <select name="tipo" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <?php foreach ($tipos as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($tipo_filtro, $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group search-input-wrapper">
                        <input type="text" name="buscar" placeholder="Buscar proveedor..." value="<?php echo esc_attr($buscar); ?>">
                    </div>
                    <?php if (!empty($buscar) || !empty($tipo_filtro)): ?>
                    <a href="?" class="btn btn-sm btn-secondary">Limpiar filtros</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($proveedores)): ?>
            <div class="empty-state-providers">
                <div class="empty-icon">👥</div>
                <h3>No hay proveedores</h3>
                <p><?php echo !empty($buscar) || !empty($tipo_filtro) ? 'No se encontraron proveedores con los filtros aplicados.' : 'Empieza agregando tu primer proveedor.'; ?></p>
                <?php if (empty($buscar) && empty($tipo_filtro)): ?>
                <button type="button" class="btn btn-primary" id="btn-nuevo-proveedor-empty">+ Agregar Proveedor</button>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Proveedor</th>
                        <th>Contacto</th>
                        <th>Teléfono</th>
                        <th>Costo Base</th>
                        <th>Estado</th>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proveedores as $prov): ?>
                    <tr data-id="<?php echo $prov->id; ?>">
                        <td data-label="Tipo">
                            <span class="badge badge-<?php echo esc_attr($prov->tipo); ?>">
                                <?php echo $tipo_icons[$prov->tipo] ?? ''; ?> <?php echo esc_html($tipos[$prov->tipo] ?? $prov->tipo); ?>
                            </span>
                        </td>
                        <td data-label="Proveedor">
                            <div class="provider-name">
                                <strong><?php echo esc_html($prov->nombre); ?></strong>
                                <?php if (!empty($prov->notas)): ?>
                                <small title="<?php echo esc_attr($prov->notas); ?>"><?php echo esc_html(mb_substr($prov->notas, 0, 40)); ?><?php echo mb_strlen($prov->notas) > 40 ? '...' : ''; ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Contacto">
                            <div class="provider-contact">
                                <?php if (!empty($prov->contacto)): ?>
                                <span class="provider-contact-name"><?php echo esc_html($prov->contacto); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($prov->email)): ?>
                                <span class="provider-contact-email"><?php echo esc_html($prov->email); ?></span>
                                <?php endif; ?>
                                <?php if (empty($prov->contacto) && empty($prov->email)): ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Teléfono">
                            <?php if (!empty($prov->telefono)): ?>
                            <div class="provider-phone">
                                <span class="provider-phone-icon">📱</span>
                                <?php echo esc_html($prov->telefono); ?>
                            </div>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Costo Base">
                            <?php if ($prov->costo_base > 0): ?>
                            <span class="provider-price <?php echo strtolower($prov->moneda); ?>">
                                <?php echo $prov->moneda === 'PEN' ? 'S/' : '$'; ?> <?php echo number_format($prov->costo_base, 2); ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Estado">
                            <?php if ($prov->activo): ?>
                            <span class="badge badge-success">✓ Activo</span>
                            <?php else: ?>
                            <span class="badge badge-danger">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="" class="actions">
                            <div class="table-actions">
                                <?php if (!empty($prov->telefono)): ?>
                                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $prov->telefono); ?>" target="_blank" class="btn-icon btn-icon-whatsapp" title="WhatsApp">💬</a>
                                <?php endif; ?>
                                <button type="button" class="btn-icon btn-icon-edit btn-edit" data-id="<?php echo $prov->id; ?>" title="Editar">✏️</button>
                                <button type="button" class="btn-icon btn-icon-delete btn-delete" data-id="<?php echo $prov->id; ?>" title="Eliminar">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Modal Proveedor -->
        <div id="modal-proveedor" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="modal-title">Nuevo Proveedor</h2>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <form id="proveedor-form">
                    <input type="hidden" name="id" value="0">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prov_tipo">Tipo de proveedor *</label>
                            <select id="prov_tipo" name="tipo" required>
                                <?php foreach ($tipos as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo ($tipo_icons[$key] ?? '') . ' ' . esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="prov_nombre">Nombre del proveedor *</label>
                            <input type="text" id="prov_nombre" name="nombre" placeholder="Ej: Transportes Cusco SAC" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prov_contacto">Persona de contacto</label>
                            <input type="text" id="prov_contacto" name="contacto" placeholder="Ej: Juan Pérez">
                        </div>
                        <div class="form-group">
                            <label for="prov_telefono">Teléfono / WhatsApp</label>
                            <input type="text" id="prov_telefono" name="telefono" placeholder="Ej: +51 984 123 456">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prov_email">Correo electrónico</label>
                            <input type="email" id="prov_email" name="email" placeholder="Ej: contacto@proveedor.com">
                        </div>
                        <div class="form-group">
                            <label for="prov_costo">Costo base referencial</label>
                            <div class="input-group">
                                <select id="prov_moneda" name="moneda">
                                    <option value="PEN">S/</option>
                                    <option value="USD">$</option>
                                </select>
                                <input type="number" id="prov_costo" name="costo_base" value="0" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="prov_notas">Notas adicionales</label>
                        <textarea id="prov_notas" name="notas" rows="3" placeholder="Información adicional sobre el proveedor, condiciones, horarios, etc."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="prov_activo" name="activo" value="1" checked>
                            <span>Proveedor activo (aparece en las listas)</span>
                        </label>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
                        <button type="submit" class="btn btn-primary">💾 Guardar Proveedor</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $this->render_footer($this->get_proveedores_scripts());
    }

    /**
     * Scripts de proveedores
     */
    private function get_proveedores_scripts() {
        return "
        // Función para abrir modal nuevo
        function openNewProviderModal() {
            $('#modal-title').text('Nuevo Proveedor');
            $('#proveedor-form')[0].reset();
            $('input[name=id]').val(0);
            $('#prov_activo').prop('checked', true);
            $('#modal-proveedor').fadeIn(200);
        }

        // Abrir modal nuevo (botón header)
        $('#btn-nuevo-proveedor, #btn-nuevo-proveedor-empty').on('click', openNewProviderModal);

        // Cerrar modal con ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('#modal-proveedor').fadeOut(200);
            }
        });

        // Cerrar modal click fuera
        $('#modal-proveedor').on('click', function(e) {
            if ($(e.target).hasClass('modal')) {
                $(this).fadeOut(200);
            }
        });

        // Cerrar modal botón X
        $('.modal-close').on('click', function() {
            $('#modal-proveedor').fadeOut(200);
        });

        // Editar proveedor
        $('.btn-edit').on('click', function() {
            var id = $(this).data('id');
            var btn = $(this);
            btn.css('opacity', '0.5');

            $('#modal-title').text('Editar Proveedor');
            $('input[name=id]').val(id);

            // Cargar datos via AJAX
            $.get(rttAjax.url, { action: 'rtt_get_proveedores', id: id }, function(response) {
                btn.css('opacity', '1');
                if (response.success && response.data) {
                    var p = response.data;
                    $('#prov_tipo').val(p.tipo);
                    $('#prov_nombre').val(p.nombre);
                    $('#prov_contacto').val(p.contacto);
                    $('#prov_telefono').val(p.telefono);
                    $('#prov_email').val(p.email);
                    $('#prov_moneda').val(p.moneda);
                    $('#prov_costo').val(p.costo_base);
                    $('#prov_notas').val(p.notas);
                    $('#prov_activo').prop('checked', p.activo == 1);
                    $('#modal-proveedor').fadeIn(200);
                }
            });
        });

        // Eliminar proveedor
        $('.btn-delete').on('click', function() {
            if (!confirm('¿Eliminar este proveedor? Esta acción no se puede deshacer.')) return;
            var id = $(this).data('id');
            var row = $(this).closest('tr');
            var btn = $(this);

            btn.css('opacity', '0.5');

            $.post(rttAjax.url, { action: 'rtt_delete_proveedor', id: id }, function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                        // Actualizar contador total
                        var currentCount = parseInt($('.provider-stat:first .provider-stat-count').text()) - 1;
                        $('.provider-stat:first .provider-stat-count').text(currentCount);
                    });
                } else {
                    btn.css('opacity', '1');
                    alert(response.data.message);
                }
            });
        });

        // Guardar proveedor
        $('#proveedor-form').on('submit', function(e) {
            e.preventDefault();
            var formData = $(this).serialize();
            var btn = $(this).find('button[type=submit]');
            var btnText = btn.html();

            btn.prop('disabled', true).html('Guardando...');

            $.post(rttAjax.url, formData + '&action=rtt_save_proveedor', function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    btn.prop('disabled', false).html(btnText);
                    alert(response.data.message);
                }
            }).fail(function() {
                btn.prop('disabled', false).html(btnText);
                alert('Error de conexión');
            });
        });

        // Búsqueda con Enter
        $('input[name=buscar]').on('keypress', function(e) {
            if (e.which === 13) {
                $(this).closest('form').submit();
            }
        });
        ";
    }

    /**
     * AJAX: Guardar proveedor
     */
    public function ajax_save_proveedor() {
        if (!$this->can_access_panel()) {
            wp_send_json_error(['message' => 'Sin acceso']);
        }

        $id = intval($_POST['id'] ?? 0);
        $data = [
            'tipo' => sanitize_text_field($_POST['tipo'] ?? ''),
            'nombre' => sanitize_text_field($_POST['nombre'] ?? ''),
            'contacto' => sanitize_text_field($_POST['contacto'] ?? ''),
            'telefono' => sanitize_text_field($_POST['telefono'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'costo_base' => floatval($_POST['costo_base'] ?? 0),
            'moneda' => sanitize_text_field($_POST['moneda'] ?? 'PEN'),
            'notas' => sanitize_textarea_field($_POST['notas'] ?? ''),
            'activo' => isset($_POST['activo']) ? 1 : 0,
        ];

        if (empty($data['nombre']) || empty($data['tipo'])) {
            wp_send_json_error(['message' => 'Nombre y tipo son requeridos']);
        }

        if ($id > 0) {
            $result = RTT_Database::update_proveedor($id, $data);
        } else {
            $result = RTT_Database::insert_proveedor($data);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['id' => $id > 0 ? $id : $result]);
    }

    /**
     * AJAX: Eliminar proveedor
     */
    public function ajax_delete_proveedor() {
        if (!$this->can_access_panel()) {
            wp_send_json_error(['message' => 'Sin acceso']);
        }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(['message' => 'ID inválido']);
        }

        RTT_Database::delete_proveedor($id);
        wp_send_json_success();
    }

    /**
     * AJAX: Obtener proveedor
     */
    public function ajax_get_proveedores() {
        if (!$this->can_access_panel()) {
            wp_send_json_error(['message' => 'Sin acceso']);
        }

        $id = intval($_GET['id'] ?? 0);
        if ($id) {
            $proveedor = RTT_Database::get_proveedor($id);
            wp_send_json_success($proveedor);
        }

        $proveedores = RTT_Database::get_proveedores(['activo' => 1]);
        wp_send_json_success($proveedores);
    }
}
