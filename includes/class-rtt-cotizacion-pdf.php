<?php
/**
 * Clase para generar PDF de cotización
 * Usa el mismo diseño que el PDF de reservas
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once RTT_RESERVAS_PLUGIN_DIR . 'lib/fpdf/fpdf.php';

class RTT_Cotizacion_PDF extends FPDF {

    protected $cotizacion;
    protected $options;

    /**
     * Generar PDF de cotización
     */
    public static function generate($cotizacion) {
        $pdf = new self();
        $pdf->cotizacion = $cotizacion;
        $pdf->options = get_option('rtt_reservas_options', []);

        $pdf->AliasNbPages();
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 45);
        $pdf->AddPage();

        $pdf->renderTitle();
        $pdf->renderClienteInfo();
        $pdf->renderTourDetails();
        $pdf->renderPricing();
        $pdf->renderFormasPago();
        $pdf->renderTerminos();

        // Guardar en archivo temporal
        $upload_dir = wp_upload_dir();
        $filename = 'cotizacion-' . $cotizacion->codigo . '.pdf';
        $filepath = $upload_dir['basedir'] . '/rtt-temp/' . $filename;

        // Crear directorio si no existe
        if (!file_exists($upload_dir['basedir'] . '/rtt-temp/')) {
            wp_mkdir_p($upload_dir['basedir'] . '/rtt-temp/');
        }

        $pdf->Output('F', $filepath);

        return $filepath;
    }

    /**
     * Header del PDF (logo y barra dorada)
     */
    public function Header() {
        $logo_path = RTT_RESERVAS_PLUGIN_DIR . 'assets/images/logo-ready-largo.png';

        // Fondo claro del header
        $this->SetFillColor(255, 255, 255);
        $this->Rect(0, 0, 210, 35, 'F');

        if (file_exists($logo_path)) {
            $this->Image($logo_path, 30, 5, 150);
        } else {
            $this->SetFont('Helvetica', 'B', 20);
            $this->SetTextColor(212, 160, 23);
            $this->SetY(10);
            $this->Cell(0, 10, 'READY TO TRAVEL PERU', 0, 1, 'C');
        }

        // Barra dorada decorativa
        $this->SetFillColor(212, 160, 23);
        $this->Rect(0, 35, 210, 3, 'F');

        $this->SetY(43);
    }

    /**
     * Footer del PDF
     */
    public function Footer() {
        $this->SetY(-40);

        // Logos de certificaciones
        $logos = array('dircetur-n.png', 'mincetur-n.png', 'gercetur-n.png');
        $x_start = 60;
        $logo_width = 22;
        $spacing = 8;

        foreach ($logos as $i => $logo) {
            $logo_path = RTT_RESERVAS_PLUGIN_DIR . 'assets/images/' . $logo;
            if (file_exists($logo_path)) {
                $x = $x_start + ($i * ($logo_width + $spacing));
                $this->Image($logo_path, $x, $this->GetY(), $logo_width);
            }
        }

        $this->Ln(22);

        // Linea dorada
        $this->SetDrawColor(212, 160, 23);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);

        // Texto de contacto centrado
        $website = $this->options['email_website'] ?? 'www.readytotravelperu.com';
        $email = $this->options['email_contact_email'] ?? 'reservas@readytotravelperu.com';
        $whatsapp = $this->options['email_whatsapp'] ?? '+51 992 515 665';

        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 4, $this->utf8($website . ' | ' . $email . ' | WhatsApp: ' . $whatsapp), 0, 1, 'C');

        // Numero de pagina
        $this->SetY(-10);
        $this->Cell(0, 4, $this->utf8('Pagina ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    /**
     * Título de cotización
     */
    private function renderTitle() {
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetTextColor(212, 160, 23);
        $this->Cell(0, 10, $this->utf8('COTIZACION DE SERVICIOS'), 0, 1, 'C');

        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(124, 179, 66);
        $this->Cell(0, 6, $this->utf8('Codigo: ' . $this->cotizacion->codigo), 0, 1, 'C');

        $this->SetTextColor(128, 128, 128);
        $this->SetFont('Helvetica', '', 9);

        // Fecha de emisión
        $peru_tz = new DateTimeZone('America/Lima');
        $now = new DateTime('now', $peru_tz);
        $this->Cell(0, 5, $this->utf8('Emitida el: ' . $now->format('d/m/Y H:i')), 0, 1, 'C');

        // Validez
        $validez = date('d/m/Y', strtotime('+' . $this->cotizacion->validez_dias . ' days'));
        $this->SetTextColor(231, 76, 60);
        $this->SetFont('Helvetica', 'B', 9);
        $this->Cell(0, 5, $this->utf8('Valida hasta: ' . $validez), 0, 1, 'C');

        $this->Ln(5);
    }

    /**
     * Información del cliente
     */
    private function renderClienteInfo() {
        // Header verde
        $this->SetFillColor(124, 179, 66);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 11);
        $this->Cell(0, 8, $this->utf8('  DATOS DEL CLIENTE'), 0, 1, 'L', true);

        $this->Ln(3);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Helvetica', '', 10);

        // Nombre
        $this->SetFont('Helvetica', 'B', 9);
        $this->Cell(25, 6, 'Cliente:', 0, 0, 'L');
        $this->SetFont('Helvetica', '', 10);
        $this->Cell(70, 6, $this->utf8($this->cotizacion->cliente_nombre), 0, 0, 'L');

        // Email
        $this->SetFont('Helvetica', 'B', 9);
        $this->Cell(20, 6, 'Email:', 0, 0, 'L');
        $this->SetFont('Helvetica', '', 10);
        $this->Cell(0, 6, $this->utf8($this->cotizacion->cliente_email), 0, 1, 'L');

        // Teléfono y país
        if (!empty($this->cotizacion->cliente_telefono)) {
            $this->SetFont('Helvetica', 'B', 9);
            $this->Cell(25, 6, $this->utf8('Telefono:'), 0, 0, 'L');
            $this->SetFont('Helvetica', '', 10);
            $this->Cell(70, 6, $this->utf8($this->cotizacion->cliente_telefono), 0, 0, 'L');
        }

        if (!empty($this->cotizacion->cliente_pais)) {
            $this->SetFont('Helvetica', 'B', 9);
            $this->Cell(20, 6, $this->utf8('Pais:'), 0, 0, 'L');
            $this->SetFont('Helvetica', '', 10);
            $this->Cell(0, 6, $this->utf8($this->cotizacion->cliente_pais), 0, 1, 'L');
        } else {
            $this->Ln();
        }

        $this->Ln(5);
    }

    /**
     * Detalles del tour
     */
    private function renderTourDetails() {
        // Header dorado
        $this->SetFillColor(212, 160, 23);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 11);
        $this->Cell(0, 8, $this->utf8('  DETALLE DEL SERVICIO'), 0, 1, 'L', true);

        // Fondo crema para el contenido
        $this->SetFillColor(255, 249, 230);
        $y_start = $this->GetY();
        $this->Rect(15, $y_start, 180, 20, 'F');

        $this->SetXY(20, $y_start + 3);
        $this->SetTextColor(0, 0, 0);

        // Tour
        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell(25, 6, 'Tour:', 0, 0, 'L');
        $this->SetFont('Helvetica', '', 10);
        $this->MultiCell(150, 6, $this->utf8($this->cotizacion->tour), 0, 'L');

        // Fecha y pasajeros en la misma línea
        $this->SetX(20);
        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell(25, 6, 'Fecha:', 0, 0, 'L');
        $fecha_tour = date('d/m/Y', strtotime($this->cotizacion->fecha_tour));
        $this->SetTextColor(124, 179, 66);
        $this->SetFont('Helvetica', 'B', 11);
        $this->Cell(50, 6, $fecha_tour, 0, 0, 'L');

        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell(30, 6, 'Pasajeros:', 0, 0, 'L');
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(212, 160, 23);
        $this->Cell(0, 6, $this->cotizacion->cantidad_pasajeros . ' persona(s)', 0, 1, 'L');

        $this->SetY($y_start + 22);
        $this->Ln(3);
    }

    /**
     * Resumen de precios
     */
    private function renderPricing() {
        // Header dorado
        $this->SetFillColor(212, 160, 23);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 11);
        $this->Cell(0, 8, $this->utf8('  RESUMEN DE PRECIOS'), 0, 1, 'L', true);

        $this->Ln(3);
        $this->SetTextColor(0, 0, 0);

        $moneda = $this->cotizacion->moneda;
        $simbolo = $moneda === 'PEN' ? 'S/' : ($moneda === 'EUR' ? 'EUR' : 'USD');
        $precio_unitario = number_format($this->cotizacion->precio_unitario, 2);
        $cantidad = $this->cotizacion->cantidad_pasajeros;
        $subtotal = $this->cotizacion->precio_unitario * $cantidad;

        // Tabla de precios
        $this->SetFont('Helvetica', '', 10);

        // Precio por persona
        $this->Cell(120, 7, $this->utf8('Precio por persona'), 0, 0, 'L');
        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell(60, 7, $simbolo . ' ' . $precio_unitario, 0, 1, 'R');

        // Cantidad
        $this->SetFont('Helvetica', '', 10);
        $this->Cell(120, 7, $this->utf8('Cantidad de pasajeros'), 0, 0, 'L');
        $this->Cell(60, 7, 'x ' . $cantidad, 0, 1, 'R');

        // Linea separadora
        $this->SetDrawColor(200, 200, 200);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(2);

        // Subtotal
        $this->Cell(120, 7, 'Subtotal', 0, 0, 'L');
        $this->Cell(60, 7, $simbolo . ' ' . number_format($subtotal, 2), 0, 1, 'R');

        // Descuento si existe
        if ($this->cotizacion->descuento > 0) {
            $descuento_texto = $this->cotizacion->descuento_tipo === 'porcentaje'
                ? $this->cotizacion->descuento . '%'
                : $simbolo . ' ' . number_format($this->cotizacion->descuento, 2);

            $descuento_monto = $this->cotizacion->descuento_tipo === 'porcentaje'
                ? $subtotal * ($this->cotizacion->descuento / 100)
                : $this->cotizacion->descuento;

            $this->SetTextColor(39, 174, 96);
            $this->Cell(120, 7, $this->utf8('Descuento (' . $descuento_texto . ')'), 0, 0, 'L');
            $this->Cell(60, 7, '- ' . $simbolo . ' ' . number_format($descuento_monto, 2), 0, 1, 'R');
        }

        // Total
        $this->Ln(2);
        $this->SetFillColor(124, 179, 66);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 12);
        $this->Cell(120, 10, $this->utf8(' TOTAL A PAGAR'), 1, 0, 'L', true);
        $this->Cell(60, 10, $simbolo . ' ' . number_format($this->cotizacion->precio_total, 2) . ' ', 1, 1, 'R', true);

        $this->Ln(5);
    }

    /**
     * Formas de pago
     */
    private function renderFormasPago() {
        // Salto de página para formas de pago y términos
        $this->AddPage();

        // Header verde
        $this->SetFillColor(124, 179, 66);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 11);
        $this->Cell(0, 8, $this->utf8('  FORMAS DE PAGO'), 0, 1, 'L', true);

        $this->Ln(3);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Helvetica', '', 9);

        // Prioridad: 1) Configuración global, 2) Cotización específica, 3) Default
        $formas_pago = $this->getFormasPago();

        $this->MultiCell(0, 5, $this->utf8($formas_pago), 0, 'L');

        $this->Ln(5);
    }

    /**
     * Obtener formas de pago (desde configuración o default)
     */
    private function getFormasPago() {
        // Primero intentar desde la configuración global
        if (!empty($this->options['cotizacion_formas_pago'])) {
            return trim($this->options['cotizacion_formas_pago']);
        }

        // Luego desde la cotización específica
        if (!empty($this->cotizacion->formas_pago)) {
            return trim($this->cotizacion->formas_pago);
        }

        // Default
        return "1. TRANSFERENCIA BANCARIA - Banco de Credito del Peru (BCP)
2. PAYPAL - pagos@readytotravelperu.com (comision 5%)
3. PAGO EN EFECTIVO - En nuestras oficinas o al momento del tour

* Enviar comprobante de pago a: reservas@readytotravelperu.com";
    }

    /**
     * Términos y condiciones
     */
    private function renderTerminos() {
        // Header gris
        $this->SetFillColor(128, 128, 128);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell(0, 7, $this->utf8('  TERMINOS Y CONDICIONES'), 0, 1, 'L', true);

        $this->Ln(2);
        $this->SetTextColor(80, 80, 80);
        $this->SetFont('Helvetica', '', 8);

        // Prioridad: 1) Configuración global, 2) Cotización específica, 3) Default
        $terminos = $this->getTerminos();

        $this->MultiCell(0, 4, $this->utf8($terminos), 0, 'L');
    }

    /**
     * Obtener términos (desde configuración o default)
     */
    private function getTerminos() {
        // Primero intentar desde la configuración global
        if (!empty($this->options['cotizacion_terminos'])) {
            $terminos = trim($this->options['cotizacion_terminos']);
            // Reemplazar placeholder de días
            return str_replace(
                ['7 días', '7 dias', '{dias}'],
                $this->cotizacion->validez_dias . ' dias',
                $terminos
            );
        }

        // Luego desde la cotización específica
        if (!empty($this->cotizacion->terminos)) {
            return trim($this->cotizacion->terminos);
        }

        // Default
        return "- Cotizacion valida por " . $this->cotizacion->validez_dias . " dias desde la fecha de emision.
- Precios sujetos a disponibilidad. Para confirmar: deposito del 50%.
- Saldo a cancelar 48 horas antes del tour. Cancelaciones con +72h: devolucion del 80%.
- Tours sujetos a condiciones climaticas. Documento de identidad obligatorio.";
    }

    /**
     * Convertir UTF-8 a ISO-8859-1 para FPDF
     */
    private function utf8($text) {
        if (empty($text)) return '';
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text) ?: $text;
    }
}
