<?php
/**
 * Custom Post Type para Tours
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_Tours_CPT {

    /**
     * Inicializar
     */
    public function init() {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomy']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_rtt_tour', [$this, 'save_meta_boxes']);
        add_filter('manage_rtt_tour_posts_columns', [$this, 'set_columns']);
        add_action('manage_rtt_tour_posts_custom_column', [$this, 'render_columns'], 10, 2);
    }

    /**
     * Registrar Custom Post Type
     */
    public function register_post_type() {
        $labels = [
            'name'               => __('Tours', 'rtt-reservas'),
            'singular_name'      => __('Tour', 'rtt-reservas'),
            'menu_name'          => __('Tours', 'rtt-reservas'),
            'add_new'            => __('Añadir Tour', 'rtt-reservas'),
            'add_new_item'       => __('Añadir Nuevo Tour', 'rtt-reservas'),
            'edit_item'          => __('Editar Tour', 'rtt-reservas'),
            'new_item'           => __('Nuevo Tour', 'rtt-reservas'),
            'view_item'          => __('Ver Tour', 'rtt-reservas'),
            'search_items'       => __('Buscar Tours', 'rtt-reservas'),
            'not_found'          => __('No se encontraron tours', 'rtt-reservas'),
            'not_found_in_trash' => __('No hay tours en la papelera', 'rtt-reservas'),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'rtt-reservas',
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'supports'            => ['title'],
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,
        ];

        register_post_type('rtt_tour', $args);
    }

    /**
     * Registrar taxonomía para categorías de duración
     */
    public function register_taxonomy() {
        $labels = [
            'name'              => __('Duración', 'rtt-reservas'),
            'singular_name'     => __('Duración', 'rtt-reservas'),
            'search_items'      => __('Buscar Duración', 'rtt-reservas'),
            'all_items'         => __('Todas las Duraciones', 'rtt-reservas'),
            'edit_item'         => __('Editar Duración', 'rtt-reservas'),
            'update_item'       => __('Actualizar Duración', 'rtt-reservas'),
            'add_new_item'      => __('Añadir Nueva Duración', 'rtt-reservas'),
            'new_item_name'     => __('Nueva Duración', 'rtt-reservas'),
            'menu_name'         => __('Duraciones', 'rtt-reservas'),
        ];

        $args = [
            'labels'            => $labels,
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_menu'      => true,
            'query_var'         => false,
            'rewrite'           => false,
        ];

        register_taxonomy('rtt_tour_duration', ['rtt_tour'], $args);
    }

    /**
     * Añadir meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'rtt_tour_details',
            __('Detalles del Tour', 'rtt-reservas'),
            [$this, 'render_meta_box'],
            'rtt_tour',
            'normal',
            'high'
        );
    }

    /**
     * Renderizar meta box
     */
    public function render_meta_box($post) {
        wp_nonce_field('rtt_tour_meta_box', 'rtt_tour_meta_box_nonce');

        $name_en = get_post_meta($post->ID, '_rtt_tour_name_en', true);
        $duration = get_post_meta($post->ID, '_rtt_tour_duration', true);
        $duration_en = get_post_meta($post->ID, '_rtt_tour_duration_en', true);
        $price = get_post_meta($post->ID, '_rtt_tour_price', true);
        $price_full = get_post_meta($post->ID, '_rtt_tour_price_full', true);
        $price_note = get_post_meta($post->ID, '_rtt_tour_price_note', true);
        $price_note_en = get_post_meta($post->ID, '_rtt_tour_price_note_en', true);
        $active = get_post_meta($post->ID, '_rtt_tour_active', true);

        if ($active === '') $active = '1'; // Por defecto activo
        ?>
        <style>
            .rtt-meta-row { margin-bottom: 15px; }
            .rtt-meta-row label { display: block; font-weight: 600; margin-bottom: 5px; }
            .rtt-meta-row input[type="text"],
            .rtt-meta-row input[type="number"] { width: 100%; max-width: 400px; }
            .rtt-meta-row small { color: #666; display: block; margin-top: 3px; }
            .rtt-meta-columns { display: flex; gap: 30px; flex-wrap: wrap; }
            .rtt-meta-column { flex: 1; min-width: 300px; }
            .rtt-price-section { background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #ddd; margin-top: 20px; }
            .rtt-price-section h4 { margin: 0 0 15px 0; color: #004070; border-bottom: 2px solid #27AE60; padding-bottom: 8px; }
            .rtt-price-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
            .rtt-price-note { color: #d63638; font-size: 12px; }
        </style>

        <div class="rtt-meta-columns">
            <div class="rtt-meta-column">
                <div class="rtt-meta-row">
                    <label for="rtt_tour_name_en"><?php _e('Nombre en Inglés', 'rtt-reservas'); ?></label>
                    <input type="text" id="rtt_tour_name_en" name="rtt_tour_name_en" value="<?php echo esc_attr($name_en); ?>">
                    <small><?php _e('El nombre en español es el título del post', 'rtt-reservas'); ?></small>
                </div>

                <div class="rtt-meta-row">
                    <label for="rtt_tour_duration"><?php _e('Duración (Español)', 'rtt-reservas'); ?></label>
                    <input type="text" id="rtt_tour_duration" name="rtt_tour_duration" value="<?php echo esc_attr($duration); ?>" placeholder="Ej: 1 DIA, 2 DIAS, 1/2 DIA">
                </div>

                <div class="rtt-meta-row">
                    <label for="rtt_tour_duration_en"><?php _e('Duración (Inglés)', 'rtt-reservas'); ?></label>
                    <input type="text" id="rtt_tour_duration_en" name="rtt_tour_duration_en" value="<?php echo esc_attr($duration_en); ?>" placeholder="Ej: 1 DAY, 2 DAYS, HALF DAY">
                </div>

                <div class="rtt-meta-row">
                    <label>
                        <input type="checkbox" name="rtt_tour_active" value="1" <?php checked($active, '1'); ?>>
                        <?php _e('Tour activo (visible en el formulario)', 'rtt-reservas'); ?>
                    </label>
                </div>
            </div>

            <div class="rtt-meta-column">
                <div class="rtt-price-section">
                    <h4><?php _e('Precios del Tour', 'rtt-reservas'); ?></h4>

                    <div class="rtt-price-grid">
                        <div class="rtt-meta-row">
                            <label for="rtt_tour_price"><?php _e('Plan Accesible (USD)', 'rtt-reservas'); ?></label>
                            <input type="number" id="rtt_tour_price" name="rtt_tour_price" value="<?php echo esc_attr($price); ?>" min="0" step="0.01">
                            <small><?php _e('Precio básico del tour', 'rtt-reservas'); ?></small>
                        </div>

                        <div class="rtt-meta-row">
                            <label for="rtt_tour_price_full"><?php _e('Paquete Total (USD)', 'rtt-reservas'); ?></label>
                            <input type="number" id="rtt_tour_price_full" name="rtt_tour_price_full" value="<?php echo esc_attr($price_full); ?>" min="0" step="0.01">
                            <small><?php _e('Dejar vacío si solo hay un precio', 'rtt-reservas'); ?></small>
                        </div>
                    </div>

                    <div class="rtt-meta-row" style="margin-top: 15px;">
                        <label for="rtt_tour_price_note"><?php _e('Nota del Plan Accesible (Español)', 'rtt-reservas'); ?></label>
                        <input type="text" id="rtt_tour_price_note" name="rtt_tour_price_note" value="<?php echo esc_attr($price_note); ?>" placeholder="Ej: No incluye entradas a los complejos">
                        <small class="rtt-price-note"><?php _e('Este mensaje aparecerá debajo del Plan Accesible', 'rtt-reservas'); ?></small>
                    </div>

                    <div class="rtt-meta-row">
                        <label for="rtt_tour_price_note_en"><?php _e('Nota del Plan Accesible (Inglés)', 'rtt-reservas'); ?></label>
                        <input type="text" id="rtt_tour_price_note_en" name="rtt_tour_price_note_en" value="<?php echo esc_attr($price_note_en); ?>" placeholder="Ej: Entrance fees not included">
                    </div>
                </div>
            </div>
        </div>

        <!-- Shortcode generado -->
        <div class="rtt-shortcode-section">
            <h4><?php _e('Shortcode del Tour', 'rtt-reservas'); ?></h4>
            <p class="description"><?php _e('Copia este shortcode para usar en tus páginas:', 'rtt-reservas'); ?></p>

            <div class="rtt-shortcode-box">
                <?php
                // Si tiene dos precios, mostrar cards por defecto
                $default_display = $price_full ? 'cards' : 'button';
                ?>
                <code id="rtt-generated-shortcode"><?php echo esc_html($this->generate_shortcode($post, 'normal', 'es', $default_display)); ?></code>
                <button type="button" class="button rtt-copy-shortcode" onclick="rttCopyShortcode()">
                    <span class="dashicons dashicons-clipboard"></span> <?php _e('Copiar', 'rtt-reservas'); ?>
                </button>
            </div>

            <div class="rtt-shortcode-variants">
                <p><strong><?php _e('Variantes:', 'rtt-reservas'); ?></strong></p>
                <?php if ($price_full): ?>
                <div class="rtt-variant rtt-variant-highlight">
                    <label><?php _e('Cards inglés:', 'rtt-reservas'); ?></label>
                    <code id="rtt-shortcode-cards-en"><?php echo esc_html($this->generate_shortcode($post, 'normal', 'en', 'cards')); ?></code>
                    <button type="button" class="button button-small rtt-copy-variant" onclick="rttCopyVariant('rtt-shortcode-cards-en', this)">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                </div>
                <?php endif; ?>
                <div class="rtt-variant">
                    <label><?php _e('Botón simple:', 'rtt-reservas'); ?></label>
                    <code id="rtt-shortcode-button"><?php echo esc_html($this->generate_shortcode($post, 'normal', 'es', 'button')); ?></code>
                    <button type="button" class="button button-small rtt-copy-variant" onclick="rttCopyVariant('rtt-shortcode-button', this)">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                </div>
                <div class="rtt-variant">
                    <label><?php _e('Botón inglés:', 'rtt-reservas'); ?></label>
                    <code id="rtt-shortcode-en"><?php echo esc_html($this->generate_shortcode($post, 'normal', 'en', 'button')); ?></code>
                    <button type="button" class="button button-small rtt-copy-variant" onclick="rttCopyVariant('rtt-shortcode-en', this)">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                </div>
                <?php if ($price_full): ?>
                <div class="rtt-variant">
                    <label><?php _e('Pills compactos:', 'rtt-reservas'); ?></label>
                    <code id="rtt-shortcode-pills"><?php echo esc_html($this->generate_shortcode($post, 'normal', 'es', 'pills')); ?></code>
                    <button type="button" class="button button-small rtt-copy-variant" onclick="rttCopyVariant('rtt-shortcode-pills', this)">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                </div>
                <div class="rtt-variant">
                    <label><?php _e('Pills inglés:', 'rtt-reservas'); ?></label>
                    <code id="rtt-shortcode-pills-en"><?php echo esc_html($this->generate_shortcode($post, 'normal', 'en', 'pills')); ?></code>
                    <button type="button" class="button button-small rtt-copy-variant" onclick="rttCopyVariant('rtt-shortcode-pills-en', this)">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .rtt-shortcode-section {
                margin-top: 25px;
                padding: 20px;
                background: linear-gradient(135deg, #e8f5e9, #f1f8e9);
                border: 2px solid #27AE60;
                border-radius: 8px;
            }
            .rtt-shortcode-section h4 {
                margin: 0 0 10px 0;
                color: #27AE60;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .rtt-shortcode-section h4::before {
                content: '</>';
                font-family: monospace;
                background: #27AE60;
                color: white;
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 12px;
            }
            .rtt-shortcode-box {
                display: flex;
                gap: 10px;
                align-items: stretch;
                margin: 15px 0;
            }
            .rtt-shortcode-box code {
                flex: 1;
                background: #1d1f21;
                color: #8bc34a;
                padding: 12px 15px;
                border-radius: 6px;
                font-size: 13px;
                font-family: 'Monaco', 'Consolas', monospace;
                display: block;
                word-break: break-all;
                line-height: 1.5;
            }
            .rtt-copy-shortcode {
                display: flex !important;
                align-items: center;
                gap: 5px;
                white-space: nowrap;
            }
            .rtt-copy-shortcode .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
            .rtt-shortcode-variants {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px dashed #27AE60;
            }
            .rtt-shortcode-variants p {
                margin: 0 0 10px 0;
                color: #666;
            }
            .rtt-variant {
                margin-bottom: 8px;
            }
            .rtt-variant label {
                display: inline-block;
                width: 100px;
                font-size: 12px;
                color: #666;
            }
            .rtt-variant code {
                background: #f5f5f5;
                color: #333;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 11px;
            }
            .rtt-variant-highlight {
                background: #e3f2fd;
                padding: 8px 10px;
                border-radius: 6px;
                margin-top: 8px;
                border: 1px dashed #2196f3;
            }
            .rtt-variant-highlight code {
                background: #1565c0;
                color: white;
            }
            .rtt-variant-cards {
                background: #fff3e0;
                padding: 8px 10px;
                border-radius: 6px;
                margin-top: 8px;
                border: 1px dashed #ff9800;
            }
            .rtt-variant-cards code {
                background: #e65100;
                color: white;
            }
            .rtt-copy-variant {
                margin-left: 8px !important;
                padding: 0 6px !important;
                min-height: 24px !important;
            }
            .rtt-copy-variant .dashicons {
                font-size: 14px;
                width: 14px;
                height: 14px;
                line-height: 24px;
            }
            .rtt-copy-success {
                background: #27AE60 !important;
                color: white !important;
                border-color: #27AE60 !important;
            }
        </style>

        <script>
        (function() {
            // Función de copia compatible con todos los navegadores
            window.rttCopyToClipboard = function(text) {
                // Intentar con Clipboard API primero (HTTPS)
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    return navigator.clipboard.writeText(text).then(function() {
                        return true;
                    }).catch(function() {
                        return rttFallbackCopy(text);
                    });
                }
                // Fallback para HTTP
                return Promise.resolve(rttFallbackCopy(text));
            };

            function rttFallbackCopy(text) {
                var textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', '');
                textarea.style.cssText = 'position:absolute;left:-9999px;top:0;';
                document.body.appendChild(textarea);

                // Seleccionar el texto
                if (navigator.userAgent.match(/ipad|iphone/i)) {
                    var range = document.createRange();
                    range.selectNodeContents(textarea);
                    var selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(range);
                    textarea.setSelectionRange(0, 999999);
                } else {
                    textarea.select();
                }

                var success = false;
                try {
                    success = document.execCommand('copy');
                } catch (err) {
                    success = false;
                }
                document.body.removeChild(textarea);
                return success;
            }

            window.rttCopyShortcode = function() {
                var codeEl = document.getElementById('rtt-generated-shortcode');
                if (!codeEl) return;
                var shortcode = codeEl.textContent || codeEl.innerText;
                var btn = document.querySelector('.rtt-copy-shortcode');

                Promise.resolve(rttCopyToClipboard(shortcode)).then(function(success) {
                    if (success && btn) {
                        var originalText = btn.innerHTML;
                        btn.innerHTML = '<span class="dashicons dashicons-yes"></span> ¡Copiado!';
                        btn.classList.add('rtt-copy-success');
                        setTimeout(function() {
                            btn.innerHTML = originalText;
                            btn.classList.remove('rtt-copy-success');
                        }, 2000);
                    }
                });
            };

            window.rttCopyVariant = function(elementId, btn) {
                var codeEl = document.getElementById(elementId);
                if (!codeEl) return;
                var shortcode = codeEl.textContent || codeEl.innerText;

                Promise.resolve(rttCopyToClipboard(shortcode)).then(function(success) {
                    if (success && btn) {
                        btn.classList.add('rtt-copy-success');
                        btn.innerHTML = '<span class="dashicons dashicons-yes"></span>';
                        setTimeout(function() {
                            btn.innerHTML = '<span class="dashicons dashicons-clipboard"></span>';
                            btn.classList.remove('rtt-copy-success');
                        }, 2000);
                    }
                });
            };
        })();
        </script>
        <?php
    }

    /**
     * Generar shortcode basado en datos del tour
     */
    private function generate_shortcode($post, $size = 'normal', $lang = 'es', $display = 'button') {
        $tour_name = $post->post_title;
        $tour_name_en = get_post_meta($post->ID, '_rtt_tour_name_en', true);
        $price = get_post_meta($post->ID, '_rtt_tour_price', true);
        $price_full = get_post_meta($post->ID, '_rtt_tour_price_full', true);
        $price_note = get_post_meta($post->ID, '_rtt_tour_price_note', true);
        $price_note_en = get_post_meta($post->ID, '_rtt_tour_price_note_en', true);

        // Construir shortcode
        $shortcode = '[rtt_booking_button';

        if ($lang === 'en' && $tour_name_en) {
            $shortcode .= ' tour="' . esc_attr($tour_name_en) . '"';
            // También incluir tour en español para referencia
            $shortcode .= ' tour_en="' . esc_attr($tour_name_en) . '"';
        } else {
            $shortcode .= ' tour="' . esc_attr($tour_name) . '"';
            if ($tour_name_en) {
                $shortcode .= ' tour_en="' . esc_attr($tour_name_en) . '"';
            }
        }

        if ($price) {
            $shortcode .= ' price="' . esc_attr($price) . '"';
        }

        if ($price_full) {
            $shortcode .= ' price_full="' . esc_attr($price_full) . '"';
        }

        if ($price_note && $lang === 'es') {
            $shortcode .= ' price_note="' . esc_attr($price_note) . '"';
        } elseif ($price_note_en && $lang === 'en') {
            $shortcode .= ' price_note="' . esc_attr($price_note_en) . '"';
        }

        if ($lang === 'en') {
            $shortcode .= ' lang="en"';
        }

        if ($size !== 'normal') {
            $shortcode .= ' size="' . esc_attr($size) . '"';
        }

        if ($display === 'cards' || $display === 'pills') {
            $shortcode .= ' display="' . esc_attr($display) . '"';
        }

        $shortcode .= ']';

        return $shortcode;
    }

    /**
     * Guardar meta boxes
     */
    public function save_meta_boxes($post_id) {
        if (!isset($_POST['rtt_tour_meta_box_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['rtt_tour_meta_box_nonce'], 'rtt_tour_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Guardar campos
        $fields = ['name_en', 'duration', 'duration_en', 'price', 'price_full', 'price_note', 'price_note_en'];
        foreach ($fields as $field) {
            $key = 'rtt_tour_' . $field;
            if (isset($_POST[$key])) {
                update_post_meta($post_id, '_' . $key, sanitize_text_field($_POST[$key]));
            }
        }

        // Guardar checkbox activo
        $active = isset($_POST['rtt_tour_active']) ? '1' : '0';
        update_post_meta($post_id, '_rtt_tour_active', $active);
    }

    /**
     * Configurar columnas de la tabla
     */
    public function set_columns($columns) {
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = __('Tour (Español)', 'rtt-reservas');
        $new_columns['name_en'] = __('Tour (Inglés)', 'rtt-reservas');
        $new_columns['duration'] = __('Duración', 'rtt-reservas');
        $new_columns['price'] = __('Precio', 'rtt-reservas');
        $new_columns['active'] = __('Estado', 'rtt-reservas');
        $new_columns['date'] = $columns['date'];

        return $new_columns;
    }

    /**
     * Renderizar columnas personalizadas
     */
    public function render_columns($column, $post_id) {
        switch ($column) {
            case 'name_en':
                echo esc_html(get_post_meta($post_id, '_rtt_tour_name_en', true) ?: '-');
                break;
            case 'duration':
                echo esc_html(get_post_meta($post_id, '_rtt_tour_duration', true) ?: '-');
                break;
            case 'price':
                $price = get_post_meta($post_id, '_rtt_tour_price', true);
                echo $price ? '$' . number_format($price, 2) : '-';
                break;
            case 'active':
                $active = get_post_meta($post_id, '_rtt_tour_active', true);
                if ($active === '1' || $active === '') {
                    echo '<span style="color: #46b450;">●</span> ' . __('Activo', 'rtt-reservas');
                } else {
                    echo '<span style="color: #dc3232;">●</span> ' . __('Inactivo', 'rtt-reservas');
                }
                break;
        }
    }

    /**
     * Obtener tours desde CPT
     */
    public static function get_tours_from_cpt() {
        $posts = get_posts([
            'post_type' => 'rtt_tour',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_rtt_tour_active',
                    'value' => '1',
                ],
                [
                    'key' => '_rtt_tour_active',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);

        $tours = [];
        foreach ($posts as $post) {
            $duration = get_post_meta($post->ID, '_rtt_tour_duration', true) ?: '';

            // Determinar categoría basada en duración
            $category = 'other';
            if (stripos($duration, '1/2') !== false || stripos($duration, 'HALF') !== false) {
                $category = 'half_day';
            } elseif (stripos($duration, '1 DIA') !== false || stripos($duration, '1 DAY') !== false) {
                $category = 'full_day';
            } elseif (stripos($duration, '2 DIAS') !== false || stripos($duration, '2 DAYS') !== false) {
                $category = '2_days';
            } elseif (preg_match('/(\d+)\s*(DIAS|DAYS)/i', $duration, $matches)) {
                $category = $matches[1] . '_days';
            }

            $price_full = get_post_meta($post->ID, '_rtt_tour_price_full', true);

            $tour_data = [
                'id' => $post->ID,
                'name' => $post->post_title,
                'name_en' => get_post_meta($post->ID, '_rtt_tour_name_en', true) ?: $post->post_title,
                'duration' => $duration,
                'duration_en' => get_post_meta($post->ID, '_rtt_tour_duration_en', true) ?: '',
                'price' => get_post_meta($post->ID, '_rtt_tour_price', true) ?: '',
                'category' => $category,
            ];

            // Agregar precio completo si existe
            if (!empty($price_full)) {
                $tour_data['price_full'] = $price_full;
                $tour_data['price_note'] = get_post_meta($post->ID, '_rtt_tour_price_note', true) ?: '';
                $tour_data['price_note_en'] = get_post_meta($post->ID, '_rtt_tour_price_note_en', true) ?: '';
            }

            $tours[] = $tour_data;
        }

        return $tours;
    }

    /**
     * Importar tours hardcodeados al CPT
     */
    public static function import_default_tours() {
        // Verificar si ya se importaron
        if (get_option('rtt_tours_imported')) {
            return;
        }

        $default_tours = RTT_Tours::get_default_tours();

        foreach ($default_tours as $tour) {
            // Verificar si ya existe
            $existing = get_posts([
                'post_type' => 'rtt_tour',
                'title' => $tour['name'],
                'post_status' => 'any',
                'numberposts' => 1,
            ]);

            if (!empty($existing)) {
                continue;
            }

            // Crear el tour
            $post_id = wp_insert_post([
                'post_type' => 'rtt_tour',
                'post_title' => $tour['name'],
                'post_status' => 'publish',
            ]);

            if ($post_id && !is_wp_error($post_id)) {
                update_post_meta($post_id, '_rtt_tour_name_en', $tour['name_en']);
                update_post_meta($post_id, '_rtt_tour_duration', $tour['duration']);
                update_post_meta($post_id, '_rtt_tour_duration_en', $tour['duration_en']);
                update_post_meta($post_id, '_rtt_tour_active', '1');
            }
        }

        update_option('rtt_tours_imported', true);
    }
}
