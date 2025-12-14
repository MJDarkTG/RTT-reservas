<?php
/**
 * Clase para generar PDF de cotización/voucher
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

        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();
        $pdf->SetMargins(15, 15, 15);

        $pdf->renderHeader();
        $pdf->renderClienteInfo();
        $pdf->renderTourDetails();
        $pdf->renderPricing();
        $pdf->renderFormasPago();
        $pdf->renderTerminos();
        $pdf->renderFooter();

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
     * Header del PDF
     */
    private function renderHeader() {
        // Logo
        $logo_url = $this->options['email_logo_url'] ?? '';
        if (!empty($logo_url)) {
            // Intentar descargar el logo temporalmente
            $logo_path = $this->downloadLogo($logo_url);
            if ($logo_path && file_exists($logo_path)) {
                $this->Image($logo_path, 15, 10, 50);
                @unlink($logo_path);
            }
        }

        // Titulo
        $this->SetXY(70, 15);
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(0, 64, 112);
        $this->Cell(0, 10, $this->utf8('COTIZACION'), 0, 1, 'R');

        $this->SetXY(70, 25);
        $this->SetFont('Arial', '', 12);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, $this->utf8($this->cotizacion->codigo), 0, 1, 'R');

        // Fecha
        $peru_tz = new DateTimeZone('America/Lima');
        $now = new DateTime('now', $peru_tz);
        $this->SetXY(70, 32);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, $this->utf8('Fecha: ' . $now->format('d/m/Y')), 0, 1, 'R');

        // Validez
        $validez = date('d/m/Y', strtotime('+' . $this->cotizacion->validez_dias . ' days'));
        $this->SetXY(70, 37);
        $this->Cell(0, 5, $this->utf8('Valida hasta: ' . $validez), 0, 1, 'R');

        $this->Ln(20);
    }

    /**
     * Información del cliente
     */
    private function renderClienteInfo() {
        $this->SetY(55);

        // Box de cliente
        $this->SetFillColor(245, 247, 250);
        $this->Rect(15, $this->GetY(), 180, 30, 'F');

        $this->SetXY(20, $this->GetY() + 5);
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(0, 64, 112);
        $this->Cell(0, 6, $this->utf8('CLIENTE'), 0, 1);

        $this->SetX(20);
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(50, 50, 50);
        $this->Cell(0, 5, $this->utf8($this->cotizacion->cliente_nombre), 0, 1);

        $this->SetX(20);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, $this->utf8($this->cotizacion->cliente_email), 0, 1);

        if (!empty($this->cotizacion->cliente_telefono)) {
            $this->SetX(20);
            $this->Cell(0, 5, $this->utf8('Tel: ' . $this->cotizacion->cliente_telefono), 0, 1);
        }

        $this->Ln(10);
    }

    /**
     * Detalles del tour
     */
    private function renderTourDetails() {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 64, 112);
        $this->Cell(0, 8, $this->utf8('DETALLE DEL SERVICIO'), 0, 1);

        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(50, 50, 50);

        // Tabla
        $this->SetFillColor(0, 64, 112);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(100, 8, $this->utf8(' Tour'), 1, 0, 'L', true);
        $this->Cell(40, 8, $this->utf8(' Fecha'), 1, 0, 'C', true);
        $this->Cell(40, 8, $this->utf8(' Pasajeros'), 1, 1, 'C', true);

        $this->SetTextColor(50, 50, 50);
        $this->SetFont('Arial', '', 10);
        $fecha_tour = date('d/m/Y', strtotime($this->cotizacion->fecha_tour));
        $this->Cell(100, 8, $this->utf8(' ' . $this->cotizacion->tour), 1, 0, 'L');
        $this->Cell(40, 8, $this->utf8($fecha_tour), 1, 0, 'C');
        $this->Cell(40, 8, $this->utf8($this->cotizacion->cantidad_pasajeros), 1, 1, 'C');

        $this->Ln(10);
    }

    /**
     * Precios
     */
    private function renderPricing() {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 64, 112);
        $this->Cell(0, 8, $this->utf8('RESUMEN DE PRECIOS'), 0, 1);

        $moneda = $this->cotizacion->moneda;
        $precio_unitario = number_format($this->cotizacion->precio_unitario, 2);
        $cantidad = $this->cotizacion->cantidad_pasajeros;
        $subtotal = $this->cotizacion->precio_unitario * $cantidad;

        // Tabla de precios
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(50, 50, 50);

        $this->Cell(120, 7, $this->utf8('Precio por persona'), 0, 0, 'L');
        $this->Cell(60, 7, $this->utf8($moneda . ' ' . $precio_unitario), 0, 1, 'R');

        $this->Cell(120, 7, $this->utf8('Cantidad de pasajeros'), 0, 0, 'L');
        $this->Cell(60, 7, $this->utf8('x ' . $cantidad), 0, 1, 'R');

        $this->SetDrawColor(200, 200, 200);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(2);

        $this->Cell(120, 7, $this->utf8('Subtotal'), 0, 0, 'L');
        $this->Cell(60, 7, $this->utf8($moneda . ' ' . number_format($subtotal, 2)), 0, 1, 'R');

        // Descuento
        if ($this->cotizacion->descuento > 0) {
            $descuento_texto = $this->cotizacion->descuento_tipo === 'porcentaje'
                ? $this->cotizacion->descuento . '%'
                : $moneda . ' ' . number_format($this->cotizacion->descuento, 2);

            $this->SetTextColor(39, 174, 96);
            $this->Cell(120, 7, $this->utf8('Descuento (' . $descuento_texto . ')'), 0, 0, 'L');

            $descuento_monto = $this->cotizacion->descuento_tipo === 'porcentaje'
                ? $subtotal * ($this->cotizacion->descuento / 100)
                : $this->cotizacion->descuento;

            $this->Cell(60, 7, $this->utf8('- ' . $moneda . ' ' . number_format($descuento_monto, 2)), 0, 1, 'R');
        }

        // Total
        $this->Ln(3);
        $this->SetFillColor(39, 174, 96);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(120, 12, $this->utf8(' TOTAL A PAGAR'), 1, 0, 'L', true);
        $this->Cell(60, 12, $this->utf8($moneda . ' ' . number_format($this->cotizacion->precio_total, 2) . ' '), 1, 1, 'R', true);

        $this->Ln(10);
    }

    /**
     * Formas de pago
     */
    private function renderFormasPago() {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 64, 112);
        $this->Cell(0, 8, $this->utf8('FORMAS DE PAGO'), 0, 1);

        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(50, 50, 50);

        // Formas de pago predeterminadas
        $formas_pago = !empty($this->cotizacion->formas_pago)
            ? $this->cotizacion->formas_pago
            : $this->getDefaultFormasPago();

        $this->MultiCell(0, 6, $this->utf8($formas_pago), 0, 'L');

        $this->Ln(10);
    }

    /**
     * Formas de pago por defecto
     */
    private function getDefaultFormasPago() {
        return "1. TRANSFERENCIA BANCARIA
   Banco: BCP - Banco de Credito del Peru
   Cuenta Corriente Soles: XXX-XXXXXXX-X-XX
   Cuenta Corriente Dolares: XXX-XXXXXXX-X-XX
   CCI: XXXXXXXXXXXXXXXXXXX
   Titular: Ready To Travel Peru

2. PAYPAL
   Cuenta: pagos@readytotravelperu.com
   (Se aplica comision de 5%)

3. PAGO EN EFECTIVO
   En nuestras oficinas o al momento del tour

* Enviar comprobante de pago a: reservas@readytotravelperu.com";
    }

    /**
     * Terminos y condiciones
     */
    private function renderTerminos() {
        // Verificar si necesita nueva pagina
        if ($this->GetY() > 220) {
            $this->AddPage();
        }

        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 64, 112);
        $this->Cell(0, 8, $this->utf8('TERMINOS Y CONDICIONES'), 0, 1);

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(80, 80, 80);

        $terminos = !empty($this->cotizacion->terminos)
            ? $this->cotizacion->terminos
            : $this->getDefaultTerminos();

        $this->MultiCell(0, 5, $this->utf8($terminos), 0, 'L');
    }

    /**
     * Terminos por defecto
     */
    private function getDefaultTerminos() {
        return "- Esta cotizacion tiene validez de " . $this->cotizacion->validez_dias . " dias a partir de la fecha de emision.
- Los precios estan sujetos a disponibilidad y pueden variar sin previo aviso.
- Para confirmar la reserva se requiere un deposito del 50% del total.
- El saldo restante debe cancelarse 48 horas antes del inicio del tour.
- Cancelaciones con mas de 72 horas: devolucion del 80% del deposito.
- Cancelaciones con menos de 72 horas: no hay devolucion.
- Los tours estan sujetos a condiciones climaticas.
- Es obligatorio presentar documento de identidad el dia del tour.
- Menores de edad deben estar acompanados por un adulto responsable.";
    }

    /**
     * Footer
     */
    private function renderFooter() {
        $this->SetY(-30);
        $this->SetDrawColor(0, 64, 112);
        $this->Line(15, $this->GetY(), 195, $this->GetY());

        $this->Ln(3);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(100, 100, 100);

        $website = $this->options['email_website'] ?? 'www.readytotravelperu.com';
        $email = $this->options['email_contact_email'] ?? 'reservas@readytotravelperu.com';
        $whatsapp = $this->options['email_whatsapp'] ?? '';

        $this->Cell(0, 5, $this->utf8($website . ' | ' . $email . ($whatsapp ? ' | WhatsApp: ' . $whatsapp : '')), 0, 1, 'C');

        $slogan = $this->options['email_slogan_es'] ?? 'Donde cada viaje se convierte en un recuerdo inolvidable';
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, $this->utf8($slogan), 0, 1, 'C');
    }

    /**
     * Descargar logo temporalmente
     */
    private function downloadLogo($url) {
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['basedir'] . '/rtt-temp/logo-temp.jpg';

        // Crear directorio si no existe
        if (!file_exists($upload_dir['basedir'] . '/rtt-temp/')) {
            wp_mkdir_p($upload_dir['basedir'] . '/rtt-temp/');
        }

        $response = wp_remote_get($url, ['timeout' => 10]);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            file_put_contents($temp_file, wp_remote_retrieve_body($response));
            return $temp_file;
        }

        return false;
    }

    /**
     * Convertir UTF-8 a ISO-8859-1 para FPDF
     */
    private function utf8($text) {
        if (empty($text)) return '';
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text) ?: $text;
    }
}
