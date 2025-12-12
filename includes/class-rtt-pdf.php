<?php
  /**
   * Clase para generar PDF de reserva
   * Usa FPDF para compatibilidad con cPanel
   */

  if (!defined('ABSPATH')) {
      exit;
  }

  require_once RTT_RESERVAS_PLUGIN_DIR . 'lib/fpdf/fpdf.php';

  /**
   * Función compatible con PHP 8.1+ para convertir UTF-8 a ISO-8859-1
   * Reemplaza rtt_utf8_to_iso() que está deprecated
   */
  function rtt_utf8_to_iso($text) {
      if (empty($text)) return '';
      return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text) ?: $text;
  }

  class RTT_PDF_Table extends FPDF {
      protected $widths;
      protected $aligns;
      protected $lineHeight = 5;
      protected $lang = 'es';

      function SetLang($lang) {
          $this->lang = $lang;
      }

      function SetWidths($w) {
          $this->widths = $w;
      }

      function SetAligns($a) {
          $this->aligns = $a;
      }

      function SetLineHeight($h) {
          $this->lineHeight = $h;
      }

      function Row($data, $fill = false) {
          $nb = 0;
          for ($i = 0; $i < count($data); $i++) {
              $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
          }
          $h = $this->lineHeight * $nb;
          $this->CheckPageBreak($h);

          for ($i = 0; $i < count($data); $i++) {
              $w = $this->widths[$i];
              $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
              $x = $this->GetX();
              $y = $this->GetY();

              if ($fill) {
                  $this->SetFillColor(248, 249, 250);
                  $this->Rect($x, $y, $w, $h, 'F');
              }
              $this->Rect($x, $y, $w, $h);

              $this->MultiCell($w, $this->lineHeight, rtt_utf8_to_iso($data[$i]), 0, $a);
              $this->SetXY($x + $w, $y);
          }
          $this->Ln($h);
      }

      function CheckPageBreak($h) {
          if ($this->GetY() + $h > $this->PageBreakTrigger) {
              $this->AddPage($this->CurOrientation);
          }
      }

      function NbLines($w, $txt) {
          if (!isset($this->CurrentFont['cw'])) {
              return 1;
          }
          $cw = &$this->CurrentFont['cw'];
          if ($w == 0) {
              $w = $this->w - $this->rMargin - $this->x;
          }
          $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
          $s = str_replace("\r", '', $txt);
          $nb = strlen($s);
          if ($nb > 0 && $s[$nb - 1] == "\n") {
              $nb--;
          }
          $sep = -1;
          $i = 0;
          $j = 0;
          $l = 0;
          $nl = 1;
          while ($i < $nb) {
              $c = $s[$i];
              if ($c == "\n") {
                  $i++;
                  $sep = -1;
                  $j = $i;
                  $l = 0;
                  $nl++;
                  continue;
              }
              if ($c == ' ') {
                  $sep = $i;
              }
              $l += isset($cw[$c]) ? $cw[$c] : 0;
              if ($l > $wmax) {
                  if ($sep == -1) {
                      if ($i == $j) {
                          $i++;
                      }
                  } else {
                      $i = $sep + 1;
                  }
                  $sep = -1;
                  $j = $i;
                  $l = 0;
                  $nl++;
              } else {
                  $i++;
              }
          }
          return $nl;
      }

      function Header() {
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

      function Footer() {
          $this->SetY(-40);

          // Logos de certificaciones (más arriba)
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
          $this->SetFont('Helvetica', '', 8);
          $this->SetTextColor(128, 128, 128);
          $this->Cell(0, 4, 'www.readytotravelperu.com | reservas@readytotravelperu.com | WhatsApp: +51 992 515 665', 0, 1, 'C');

          // Numero de pagina a la derecha
          $this->SetY(-10);
          $this->Cell(0, 4, rtt_utf8_to_iso('Pagina ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
      }
  }

  class RTT_PDF {

      public function generate($data) {
          try {
              $lang = $data['lang'] ?? 'es';

              $pdf = new RTT_PDF_Table();
              $pdf->SetLang($lang);
              $pdf->AliasNbPages();
              $pdf->AddPage();
              $pdf->SetMargins(15, 15, 15);
              $pdf->SetAutoPageBreak(true, 40);

              $this->add_title($pdf, $lang);
              $this->add_tour_info($pdf, $data, $lang);
              $this->add_representative_info($pdf, $data, $lang);
              $this->add_passengers_table($pdf, $data, $lang);
              $this->add_thank_you($pdf, $lang);

              return $pdf->Output('S');

          } catch (Exception $e) {
              return new WP_Error('pdf_error', $e->getMessage());
          }
      }

      private function add_title($pdf, $lang) {
          $pdf->SetFont('Helvetica', 'B', 16);
          $pdf->SetTextColor(212, 160, 23);
          $title = $lang === 'en' ? 'BOOKING CONFIRMATION' : 'CONFIRMACION DE RESERVA';
          $pdf->Cell(0, 10, rtt_utf8_to_iso($title), 0, 1, 'C');

          $pdf->SetFont('Helvetica', '', 10);
          $pdf->SetTextColor(124, 179, 66);
          $reservation_num = 'RTT-' . date('Ymd') . '-' . rand(1000, 9999);
          $pdf->Cell(0, 6, ($lang === 'en' ? 'Reservation #: ' : 'Reserva #: ') . $reservation_num, 0, 1, 'C');

          $pdf->SetTextColor(128, 128, 128);
          $pdf->SetFont('Helvetica', '', 9);
          $date_text = $lang === 'en' ? 'Generated on: ' : 'Generado el: ';
          $pdf->Cell(0, 5, $date_text . current_time('d/m/Y H:i'), 0, 1, 'C');

          $pdf->Ln(8);
      }

      private function add_tour_info($pdf, $data, $lang) {
          $pdf->SetFillColor(212, 160, 23);
          $pdf->SetTextColor(255, 255, 255);
          $pdf->SetFont('Helvetica', 'B', 11);
          $title = $lang === 'en' ? '  TOUR DETAILS' : '  DETALLES DEL TOUR';
          $pdf->Cell(0, 8, rtt_utf8_to_iso($title), 0, 1, 'L', true);

          $pdf->SetFillColor(255, 249, 230);
          $pdf->SetTextColor(0, 0, 0);
          $pdf->SetFont('Helvetica', '', 10);

          // Altura del recuadro (más alto si hay precio)
          $has_price = !empty($data['precio_tour']);
          $rect_height = $has_price ? 27 : 20;

          $y_start = $pdf->GetY();
          $pdf->Rect(15, $y_start, 180, $rect_height, 'F');

          $pdf->SetXY(20, $y_start + 3);
          $pdf->SetFont('Helvetica', 'B', 10);
          $pdf->Cell(25, 6, 'Tour:', 0, 0, 'L');
          $pdf->SetFont('Helvetica', '', 10);
          $pdf->MultiCell(150, 6, rtt_utf8_to_iso($data['tour']), 0, 'L');

          $pdf->SetX(20);
          $pdf->SetFont('Helvetica', 'B', 10);
          $pdf->Cell(25, 6, ($lang === 'en' ? 'Date:' : 'Fecha:'), 0, 0, 'L');
          $fecha = date('d/m/Y', strtotime($data['fecha']));
          $pdf->SetTextColor(124, 179, 66);
          $pdf->SetFont('Helvetica', 'B', 11);
          $pdf->Cell(60, 6, $fecha, 0, 0, 'L');

          // Mostrar precio si existe
          if ($has_price) {
              $pdf->SetTextColor(0, 0, 0);
              $pdf->SetFont('Helvetica', 'B', 10);
              $pdf->Cell(25, 6, ($lang === 'en' ? 'Price:' : 'Precio:'), 0, 0, 'L');
              $pdf->SetTextColor(212, 160, 23);
              $pdf->SetFont('Helvetica', 'B', 11);
              $pdf->Cell(0, 6, rtt_utf8_to_iso($data['precio_tour']), 0, 1, 'L');
          } else {
              $pdf->Ln();
          }

          $pdf->SetY($y_start + $rect_height + 2);
          $pdf->Ln(5);
      }

      private function add_representative_info($pdf, $data, $lang) {
          $rep = $data['representante'];

          $pdf->SetFillColor(124, 179, 66);
          $pdf->SetTextColor(255, 255, 255);
          $pdf->SetFont('Helvetica', 'B', 11);
          $title = $lang === 'en' ? '  CONTACT INFORMATION' : '  DATOS DE CONTACTO';
          $pdf->Cell(0, 8, rtt_utf8_to_iso($title), 0, 1, 'L', true);

          $pdf->Ln(3);
          $pdf->SetTextColor(0, 0, 0);
          $pdf->SetFont('Helvetica', '', 10);

          $labels = $lang === 'en'
              ? array('Name:', 'Email:', 'Country:', 'WhatsApp:')
              : array('Nombre:', 'Correo:', 'Nacionalidad:', 'WhatsApp:');

          $pdf->SetFont('Helvetica', 'B', 9);
          $pdf->Cell(25, 6, $labels[0], 0, 0, 'L');
          $pdf->SetFont('Helvetica', '', 10);
          $pdf->Cell(70, 6, rtt_utf8_to_iso($rep['nombre']), 0, 0, 'L');

          $pdf->SetFont('Helvetica', 'B', 9);
          $pdf->Cell(25, 6, $labels[3], 0, 0, 'L');
          $pdf->SetFont('Helvetica', '', 10);
          $pdf->Cell(0, 6, $rep['telefono'], 0, 1, 'L');

          $pdf->SetFont('Helvetica', 'B', 9);
          $pdf->Cell(25, 6, $labels[1], 0, 0, 'L');
          $pdf->SetFont('Helvetica', '', 10);
          $pdf->Cell(70, 6, $rep['email'], 0, 0, 'L');

          $pdf->SetFont('Helvetica', 'B', 9);
          $pdf->Cell(25, 6, $labels[2], 0, 0, 'L');
          $pdf->SetFont('Helvetica', '', 10);
          $pdf->Cell(0, 6, rtt_utf8_to_iso($rep['pais']), 0, 1, 'L');

          $pdf->Ln(8);
      }

      private function add_passengers_table($pdf, $data, $lang) {
          $pdf->SetFillColor(212, 160, 23);
          $pdf->SetTextColor(255, 255, 255);
          $pdf->SetFont('Helvetica', 'B', 11);
          $count = count($data['pasajeros']);
          $title = $lang === 'en' ? '  PASSENGERS (' . $count . ')' : '  PASAJEROS (' . $count . ')';
          $pdf->Cell(0, 8, rtt_utf8_to_iso($title), 0, 1, 'L', true);
          $pdf->Ln(3);

          $headers = $lang === 'en'
              ? array('DOC', 'NUMBER', 'FULL NAME', 'BIRTH DATE', 'G', 'NATIONALITY', 'OBSERVATIONS')
              : array('DOC', 'NRO', 'NOMBRES COMPLETOS', 'F. NACIMIENTO', 'G', 'NACIONALIDAD', 'OBSERVACIONES');

          $widths = array(15, 22, 40, 22, 8, 30, 43);

          $pdf->SetFillColor(124, 179, 66);
          $pdf->SetTextColor(255, 255, 255);
          $pdf->SetFont('Helvetica', 'B', 7);
          $pdf->SetDrawColor(124, 179, 66);

          for ($i = 0; $i < count($headers); $i++) {
              $pdf->Cell($widths[$i], 7, rtt_utf8_to_iso($headers[$i]), 1, 0, 'C', true);
          }
          $pdf->Ln();

          $pdf->SetWidths($widths);
          $pdf->SetLineHeight(5);
          $pdf->SetTextColor(0, 0, 0);
          $pdf->SetFont('Helvetica', '', 8);
          $pdf->SetDrawColor(200, 200, 200);

          $fill = false;
          foreach ($data['pasajeros'] as $passenger) {
              $fecha_nac = !empty($passenger['fecha_nacimiento'])
                  ? date('d/m/Y', strtotime($passenger['fecha_nacimiento']))
                  : '-';

              $pdf->Row(array(
                  $passenger['tipo_doc'],
                  $passenger['nro_doc'],
                  $passenger['nombre'],
                  $fecha_nac,
                  $passenger['genero'],
                  $passenger['nacionalidad'],
                  $passenger['alergias'] ? $passenger['alergias'] : '-'
              ), $fill);

              $fill = !$fill;
          }

          $pdf->Ln(8);
      }

      private function add_thank_you($pdf, $lang) {
          $pdf->SetFillColor(124, 179, 66);
          $y = $pdf->GetY();
          $pdf->Rect(15, $y, 180, 28, 'F');

          $pdf->SetXY(15, $y + 3);
          $pdf->SetFont('Helvetica', 'B', 11);
          $pdf->SetTextColor(255, 255, 255);

          $message = $lang === 'en'
              ? 'Thank you for booking with Ready To Travel Peru!'
              : 'Gracias por reservar con Ready To Travel Peru!';

          $pdf->Cell(180, 5, rtt_utf8_to_iso($message), 0, 1, 'C');

          $pdf->SetFont('Helvetica', '', 9);
          $submessage = $lang === 'en'
              ? 'We will contact you soon to confirm your reservation.'
              : 'Nos pondremos en contacto pronto para confirmar su reserva.';
          $pdf->Cell(180, 5, rtt_utf8_to_iso($submessage), 0, 1, 'C');

          // WhatsApp destacado con link clickeable
          $pdf->Ln(2);
          $pdf->SetFont('Helvetica', 'B', 12);
          $pdf->SetTextColor(255, 255, 255);
          $whatsapp_label = $lang === 'en' ? 'Contact us on WhatsApp: ' : 'Contactanos por WhatsApp: ';
          $whatsapp_number = '+51 992 515 665';
          $whatsapp_link = 'https://wa.me/51992515665';

          // Calcular posición para centrar
          $label_width = $pdf->GetStringWidth(rtt_utf8_to_iso($whatsapp_label));
          $number_width = $pdf->GetStringWidth($whatsapp_number);
          $total_width = $label_width + $number_width;
          $start_x = (210 - $total_width) / 2;

          $pdf->SetX($start_x);
          $pdf->Cell($label_width, 6, rtt_utf8_to_iso($whatsapp_label), 0, 0, 'L');

          // Número con link y subrayado
          $pdf->SetTextColor(255, 255, 100);
          $pdf->SetFont('Helvetica', 'BU', 12);
          $pdf->Cell($number_width, 6, $whatsapp_number, 0, 1, 'L', false, $whatsapp_link);
      }
  }