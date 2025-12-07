<?php
/**
 * Página de Manual/Documentación del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTT_Manual {

    /**
     * Inicializar
     */
    public function init() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
    }

    /**
     * Agregar página al menú
     */
    public function add_menu_page() {
        add_submenu_page(
            'rtt-reservas',
            __('Manual de Uso', 'rtt-reservas'),
            __('Manual', 'rtt-reservas'),
            'manage_options',
            'rtt-reservas-manual',
            [$this, 'render_page']
        );
    }

    /**
     * Cargar estilos
     */
    public function enqueue_styles($hook) {
        if ($hook !== 'rtt-reservas_page_rtt-reservas-manual') {
            return;
        }

        wp_add_inline_style('wp-admin', $this->get_styles());
    }

    /**
     * Obtener estilos CSS
     */
    private function get_styles() {
        return '
            .rtt-manual-wrap {
                max-width: 1000px;
                margin: 20px auto;
                padding: 0 20px;
            }
            .rtt-manual-header {
                background: linear-gradient(135deg, #004070, #27AE60);
                color: white;
                padding: 30px;
                border-radius: 10px;
                margin-bottom: 30px;
            }
            .rtt-manual-header h1 {
                color: white;
                margin: 0 0 10px 0;
                font-size: 28px;
            }
            .rtt-manual-header p {
                margin: 0;
                opacity: 0.9;
                font-size: 16px;
            }
            .rtt-manual-nav {
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 30px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            }
            .rtt-manual-nav h3 {
                margin-top: 0;
                color: #004070;
            }
            .rtt-manual-nav ul {
                list-style: none;
                padding: 0;
                margin: 0;
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
            .rtt-manual-nav a {
                display: inline-block;
                padding: 8px 16px;
                background: #f0f0f0;
                border-radius: 5px;
                text-decoration: none;
                color: #333;
                font-weight: 500;
                transition: all 0.2s;
            }
            .rtt-manual-nav a:hover {
                background: #27AE60;
                color: white;
            }
            .rtt-manual-section {
                background: #fff;
                padding: 25px 30px;
                border-radius: 8px;
                margin-bottom: 25px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            }
            .rtt-manual-section h2 {
                color: #004070;
                border-bottom: 2px solid #27AE60;
                padding-bottom: 10px;
                margin-top: 0;
            }
            .rtt-manual-section h3 {
                color: #333;
                margin-top: 25px;
            }
            .rtt-manual-code {
                background: #1d1f21;
                color: #c5c8c6;
                padding: 15px 20px;
                border-radius: 6px;
                font-family: "Monaco", "Consolas", monospace;
                font-size: 13px;
                overflow-x: auto;
                margin: 15px 0;
            }
            .rtt-manual-code-inline {
                background: #f4f4f4;
                color: #c7254e;
                padding: 2px 8px;
                border-radius: 4px;
                font-family: monospace;
                font-size: 13px;
            }
            .rtt-manual-table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
            }
            .rtt-manual-table th,
            .rtt-manual-table td {
                padding: 12px 15px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            .rtt-manual-table th {
                background: #f8f9fa;
                font-weight: 600;
                color: #004070;
            }
            .rtt-manual-table tr:hover {
                background: #f8f9fa;
            }
            .rtt-manual-tip {
                background: #e7f7ed;
                border-left: 4px solid #27AE60;
                padding: 15px 20px;
                border-radius: 0 6px 6px 0;
                margin: 15px 0;
            }
            .rtt-manual-tip strong {
                color: #27AE60;
            }
            .rtt-manual-warning {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px 20px;
                border-radius: 0 6px 6px 0;
                margin: 15px 0;
            }
            .rtt-manual-warning strong {
                color: #856404;
            }
            .rtt-manual-example {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                padding: 20px;
                border-radius: 6px;
                margin: 15px 0;
            }
            .rtt-manual-example h4 {
                margin-top: 0;
                color: #666;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
        ';
    }

    /**
     * Renderizar página
     */
    public function render_page() {
        ?>
        <div class="wrap rtt-manual-wrap">
            <!-- Header -->
            <div class="rtt-manual-header">
                <h1>Manual de RTT Reservas</h1>
                <p>Guía completa para configurar y usar el sistema de reservas de Ready To Travel Peru</p>
            </div>

            <!-- Navegación -->
            <div class="rtt-manual-nav">
                <h3>Índice</h3>
                <ul>
                    <li><a href="#shortcodes">Shortcodes</a></li>
                    <li><a href="#boton-reserva">Botón de Reserva</a></li>
                    <li><a href="#tours">Gestión de Tours</a></li>
                    <li><a href="#dos-precios">Tours con Dos Precios</a></li>
                    <li><a href="#reservas">Panel de Reservas</a></li>
                    <li><a href="#exportar">Exportar CSV</a></li>
                    <li><a href="#configuracion">Configuración SMTP</a></li>
                    <li><a href="#elementor">Uso con Elementor</a></li>
                </ul>
            </div>

            <!-- Shortcodes -->
            <div class="rtt-manual-section" id="shortcodes">
                <h2>1. Shortcodes Disponibles</h2>
                <p>El plugin incluye dos shortcodes principales:</p>

                <h3>Formulario Completo</h3>
                <div class="rtt-manual-code">[rtt_reserva]</div>
                <p>Muestra el formulario de reservas completo con los 3 pasos.</p>

                <table class="rtt-manual-table">
                    <thead>
                        <tr>
                            <th>Parámetro</th>
                            <th>Descripción</th>
                            <th>Ejemplo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code class="rtt-manual-code-inline">lang</code></td>
                            <td>Idioma del formulario (es/en)</td>
                            <td><code class="rtt-manual-code-inline">[rtt_reserva lang="en"]</code></td>
                        </tr>
                    </tbody>
                </table>

                <h3>Botón con Modal</h3>
                <div class="rtt-manual-code">[rtt_booking_button tour="NOMBRE DEL TOUR" price="150"]</div>
                <p>Muestra un botón que abre el formulario en un popup con el tour pre-seleccionado.</p>
            </div>

            <!-- Botón de Reserva -->
            <div class="rtt-manual-section" id="boton-reserva">
                <h2>2. Botón de Reserva (Modal)</h2>
                <p>Este shortcode es ideal para páginas individuales de tours.</p>

                <h3>Parámetros Disponibles</h3>
                <table class="rtt-manual-table">
                    <thead>
                        <tr>
                            <th>Parámetro</th>
                            <th>Descripción</th>
                            <th>Valores</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code class="rtt-manual-code-inline">tour</code></td>
                            <td>Nombre del tour en español</td>
                            <td>Texto</td>
                        </tr>
                        <tr>
                            <td><code class="rtt-manual-code-inline">tour_en</code></td>
                            <td>Nombre del tour en inglés</td>
                            <td>Texto</td>
                        </tr>
                        <tr>
                            <td><code class="rtt-manual-code-inline">price</code></td>
                            <td>Precio fijo del tour</td>
                            <td>Número (ej: 150)</td>
                        </tr>
                        <tr>
                            <td><code class="rtt-manual-code-inline">price_from</code></td>
                            <td>Precio "desde" (mínimo)</td>
                            <td>Número (ej: 99)</td>
                        </tr>
                        <tr>
                            <td><code class="rtt-manual-code-inline">text</code></td>
                            <td>Texto personalizado del botón</td>
                            <td>Texto</td>
                        </tr>
                        <tr>
                            <td><code class="rtt-manual-code-inline">lang</code></td>
                            <td>Idioma (es/en)</td>
                            <td>es, en</td>
                        </tr>
                        <tr>
                            <td><code class="rtt-manual-code-inline">size</code></td>
                            <td>Tamaño del botón</td>
                            <td>small, normal, large</td>
                        </tr>
                        <tr>
                            <td><code class="rtt-manual-code-inline">style</code></td>
                            <td>Estilo del botón</td>
                            <td>primary, secondary, outline</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Ejemplos de Uso</h3>

                <div class="rtt-manual-example">
                    <h4>Básico con precio</h4>
                    <div class="rtt-manual-code">[rtt_booking_button tour="CITY TOUR MEDIO DIA" price="45"]</div>
                </div>

                <div class="rtt-manual-example">
                    <h4>Con precio "desde"</h4>
                    <div class="rtt-manual-code">[rtt_booking_button tour="TOUR MACHUPICCHU" price_from="150"]</div>
                </div>

                <div class="rtt-manual-example">
                    <h4>Botón grande en inglés</h4>
                    <div class="rtt-manual-code">[rtt_booking_button tour="RAINBOW MOUNTAIN" price="65" lang="en" size="large"]</div>
                </div>

                <div class="rtt-manual-example">
                    <h4>Estilo outline con texto personalizado</h4>
                    <div class="rtt-manual-code">[rtt_booking_button tour="VALLE SAGRADO" price="85" style="outline" text="¡Reserva tu aventura!"]</div>
                </div>

                <div class="rtt-manual-tip">
                    <strong>Tip:</strong> El nombre del tour debe coincidir (o ser similar) con uno de los tours registrados en el sistema para que se pre-seleccione automáticamente.
                </div>
            </div>

            <!-- Tours -->
            <div class="rtt-manual-section" id="tours">
                <h2>3. Gestión de Tours</h2>
                <p>Los tours se gestionan desde <strong>RTT Reservas → Tours</strong> en el menú de WordPress.</p>

                <h3>Crear un Tour</h3>
                <ol>
                    <li>Ve a <strong>RTT Reservas → Tours → Añadir Tour</strong></li>
                    <li>Ingresa el nombre del tour en español (título)</li>
                    <li>Completa los campos adicionales:
                        <ul>
                            <li><strong>Nombre en Inglés:</strong> Traducción del nombre</li>
                            <li><strong>Duración (ES/EN):</strong> Ej: "1 DIA" / "1 DAY"</li>
                            <li><strong>Precio:</strong> Precio de referencia (opcional)</li>
                            <li><strong>Tour activo:</strong> Si está visible en el formulario</li>
                        </ul>
                    </li>
                    <li>Haz clic en <strong>Publicar</strong></li>
                </ol>

                <div class="rtt-manual-tip">
                    <strong>Tip:</strong> Al activar el plugin por primera vez, se importan automáticamente los tours predefinidos.
                </div>

                <h3 id="dos-precios">Tours con Dos Precios (Plan Accesible y Paquete Total)</h3>
                <p>Algunos tours pueden ofrecer dos opciones de precio. Cuando el usuario selecciona un tour con dos precios, aparecerán automáticamente <strong>cards visuales</strong> para elegir entre:</p>
                <ul>
                    <li><strong>Plan Accesible:</strong> Precio básico del tour</li>
                    <li><strong>Paquete Total:</strong> Precio completo con servicios adicionales</li>
                </ul>

                <h4>Configurar un Tour con Dos Precios</h4>
                <p>Para agregar un segundo precio a un tour, edita el archivo <code class="rtt-manual-code-inline">includes/class-rtt-tours.php</code> y agrega el campo <code class="rtt-manual-code-inline">price_full</code>:</p>

                <div class="rtt-manual-code">[
    'name' => 'Tour Montana 7 Colores Todo El Dia',
    'name_en' => 'Rainbow Mountain Full Day Tour',
    'duration' => '1 DIA',
    'duration_en' => '1 DAY',
    'price' => 25,           // Plan Accesible
    'price_full' => 35,      // Paquete Total
    'category' => 'full_day'
],</div>

                <div class="rtt-manual-example">
                    <h4>Comportamiento</h4>
                    <ul>
                        <li><strong>Con <code>price_full</code>:</strong> Se muestran las cards de selección de plan</li>
                        <li><strong>Sin <code>price_full</code>:</strong> Solo se muestra el precio único (comportamiento normal)</li>
                    </ul>
                </div>

                <div class="rtt-manual-tip">
                    <strong>Tip:</strong> El usuario debe seleccionar obligatoriamente un plan antes de continuar con la reserva. El plan seleccionado se incluye en el email de confirmación.
                </div>
            </div>

            <!-- Reservas -->
            <div class="rtt-manual-section" id="reservas">
                <h2>4. Panel de Reservas</h2>
                <p>Accede desde <strong>RTT Reservas → Ver Reservas</strong></p>

                <h3>Funcionalidades</h3>
                <ul>
                    <li><strong>Estadísticas:</strong> Total de reservas, pendientes, confirmadas y del mes actual</li>
                    <li><strong>Filtros:</strong> Por estado y búsqueda por código, nombre o email</li>
                    <li><strong>Cambiar Estado:</strong> Selecciona el nuevo estado directamente en la tabla</li>
                    <li><strong>Ver Detalle:</strong> Haz clic en el código para ver toda la información</li>
                    <li><strong>Eliminar:</strong> Usa el ícono de papelera (requiere confirmación)</li>
                </ul>

                <h3>Estados de Reserva</h3>
                <table class="rtt-manual-table">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Color</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Pendiente</td>
                            <td><span style="color: #f0ad4e;">●</span> Amarillo</td>
                            <td>Reserva recién creada, esperando confirmación</td>
                        </tr>
                        <tr>
                            <td>Confirmada</td>
                            <td><span style="color: #5bc0de;">●</span> Azul</td>
                            <td>Reserva confirmada por el operador</td>
                        </tr>
                        <tr>
                            <td>Pagada</td>
                            <td><span style="color: #5cb85c;">●</span> Verde</td>
                            <td>Cliente ha realizado el pago</td>
                        </tr>
                        <tr>
                            <td>Completada</td>
                            <td><span style="color: #004070;">●</span> Azul oscuro</td>
                            <td>Tour realizado exitosamente</td>
                        </tr>
                        <tr>
                            <td>Cancelada</td>
                            <td><span style="color: #d9534f;">●</span> Rojo</td>
                            <td>Reserva cancelada</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Exportar -->
            <div class="rtt-manual-section" id="exportar">
                <h2>5. Exportar a CSV</h2>
                <p>Puedes exportar las reservas a un archivo CSV compatible con Excel.</p>

                <h3>Cómo Exportar</h3>
                <ol>
                    <li>Ve a <strong>RTT Reservas → Ver Reservas</strong></li>
                    <li>Haz clic en el botón <strong>"Exportar CSV"</strong></li>
                    <li>Selecciona los filtros (opcional):
                        <ul>
                            <li>Estado (todos, pendiente, confirmada, etc.)</li>
                            <li>Fecha desde</li>
                            <li>Fecha hasta</li>
                        </ul>
                    </li>
                    <li>Haz clic en <strong>"Descargar"</strong></li>
                </ol>

                <div class="rtt-manual-tip">
                    <strong>Tip:</strong> El archivo CSV incluye todos los datos de la reserva y cada pasajero en filas separadas.
                </div>
            </div>

            <!-- Configuración -->
            <div class="rtt-manual-section" id="configuracion">
                <h2>6. Configuración SMTP</h2>
                <p>Configura el envío de emails desde <strong>RTT Reservas → RTT Reservas</strong> (página principal del plugin).</p>

                <h3>Configuración para Gmail</h3>
                <table class="rtt-manual-table">
                    <tbody>
                        <tr>
                            <td><strong>Servidor SMTP</strong></td>
                            <td>smtp.gmail.com</td>
                        </tr>
                        <tr>
                            <td><strong>Puerto</strong></td>
                            <td>465 (SSL) o 587 (TLS)</td>
                        </tr>
                        <tr>
                            <td><strong>Seguridad</strong></td>
                            <td>SSL</td>
                        </tr>
                        <tr>
                            <td><strong>Usuario</strong></td>
                            <td>tu-email@gmail.com</td>
                        </tr>
                        <tr>
                            <td><strong>Contraseña</strong></td>
                            <td>Contraseña de aplicación (no la contraseña normal)</td>
                        </tr>
                    </tbody>
                </table>

                <div class="rtt-manual-warning">
                    <strong>Importante:</strong> Para Gmail, debes generar una "Contraseña de aplicación" desde la configuración de seguridad de tu cuenta Google. No uses tu contraseña normal.
                </div>

                <h3>Probar Configuración</h3>
                <p>Usa el formulario "Probar configuración de email" al final de la página de configuración para verificar que todo funciona correctamente.</p>
            </div>

            <!-- Elementor -->
            <div class="rtt-manual-section" id="elementor">
                <h2>7. Uso con Elementor</h2>
                <p>El plugin es totalmente compatible con Elementor.</p>

                <h3>Agregar el Formulario Completo</h3>
                <ol>
                    <li>Edita la página con Elementor</li>
                    <li>Busca el widget <strong>"Shortcode"</strong></li>
                    <li>Arrastra el widget a la página</li>
                    <li>Escribe: <code class="rtt-manual-code-inline">[rtt_reserva]</code></li>
                </ol>

                <h3>Agregar Botón de Reserva en Página de Tour</h3>
                <ol>
                    <li>Edita la página del tour con Elementor</li>
                    <li>Agrega un widget <strong>"Shortcode"</strong></li>
                    <li>Escribe el shortcode con los datos del tour:</li>
                </ol>
                <div class="rtt-manual-code">[rtt_booking_button tour="NOMBRE DE TU TOUR" price="PRECIO"]</div>

                <div class="rtt-manual-tip">
                    <strong>Tip:</strong> Puedes usar el widget de Elementor "Sección" con fondo de color para destacar el botón de reserva.
                </div>

                <h3>Solución de Problemas</h3>
                <p>Si el formulario no se muestra correctamente:</p>
                <ul>
                    <li>Asegúrate de que no haya conflictos de CSS con tu tema</li>
                    <li>Verifica que jQuery esté cargado en la página</li>
                    <li>Limpia la caché del navegador y de plugins de caché</li>
                </ul>
            </div>

            <!-- Footer -->
            <div class="rtt-manual-section" style="text-align: center; background: #f8f9fa;">
                <p><strong>RTT Reservas</strong> - Ready To Travel Peru</p>
                <p style="color: #666; font-size: 13px;">
                    ¿Necesitas ayuda? Contacta al desarrollador<br>
                    Versión <?php echo RTT_RESERVAS_VERSION; ?>
                </p>
            </div>
        </div>
        <?php
    }
}
