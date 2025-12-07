<?php
if (!defined("ABSPATH")) exit;

class RTT_Mail {
    public function send_confirmation($data, $pdf_content) {
        $options = get_option("rtt_reservas_options", []);
        $lang = $data["lang"] ?? "es";
        if (!empty($options["smtp_host"]) && !empty($options["smtp_user"])) {
            add_action("phpmailer_init", function($phpmailer) use ($options) {
                $phpmailer->isSMTP();
                $phpmailer->Host = $options["smtp_host"];
                $phpmailer->SMTPAuth = true;
                $phpmailer->Username = $options["smtp_user"];
                $phpmailer->Password = $options["smtp_pass"];
                $phpmailer->SMTPSecure = $options["smtp_secure"] ?? "ssl";
                $phpmailer->Port = intval($options["smtp_port"] ?? 465);
            });
        }
        $to = $data["representante"]["email"];
        $subject = $lang === "en" ? ($options["email_subject_en"] ?? "Booking Confirmation") : ($options["email_subject_es"] ?? "Confirmacion de Reserva");
        $logo_url = "http://readytotravelperu.com/wp-content/uploads/2022/08/ready-to-travel-peru.jpg";
        $message = $this->get_email_template($data, $lang, $logo_url);
        $from_name = $options["from_name"] ?? "Ready To Travel Peru";
        $from_email = $options["from_email"] ?? get_option("admin_email");
        $headers = array("Content-Type: text/html; charset=UTF-8", "From: " . $from_name . " <" . $from_email . ">");
        if (!empty($options["cc_email"])) $headers[] = "Cc: " . $options["cc_email"];
        $upload_dir = wp_upload_dir();
        $pdf_path = $upload_dir["basedir"] . "/rtt-reservas/reserva_" . time() . ".pdf";
        wp_mkdir_p($upload_dir["basedir"] . "/rtt-reservas");
        file_put_contents($pdf_path, $pdf_content);
        $sent = wp_mail($to, $subject, $message, $headers, array($pdf_path));
        if (file_exists($pdf_path)) unlink($pdf_path);
        return $sent ? true : new WP_Error("email_error", "Error");
    }

    private function get_email_template($data, $lang, $logo_url) {
        $rep = $data["representante"];
        $count = count($data["pasajeros"]);
        $fecha = date("d/m/Y", strtotime($data["fecha"]));
        return $lang === "en" ? $this->get_english_template($data, $rep, $fecha, $count, $logo_url) : $this->get_spanish_template($data, $rep, $fecha, $count, $logo_url);
    }

    private function get_spanish_template($data, $rep, $fecha, $count, $logo_url) {
        $precio = !empty($data["precio_tour"]) ? $data["precio_tour"] : '';
        $h = '<!DOCTYPE html><html><body style="margin:0;font-family:Arial;background:#f5f5f5;"><table width="100%" style="background:#f5f5f5;padding:20px;"><tr><td align="center"><table width="600" style="background:#fff;border-radius:12px;overflow:hidden;">';
        $h .= '<tr><td style="background:#fff;padding:25px;text-align:center;border-bottom:4px solid #D4A017;"><img src="' . esc_url($logo_url) . '" style="max-width:250px;"><p style="color:#7CB342;margin:15px 0 0;font-style:italic;">Donde cada viaje se convierte en un recuerdo inolvidable</p></td></tr>';
        $h .= '<tr><td style="background:#7CB342;padding:20px;text-align:center;"><h1 style="color:#fff;margin:0;font-size:24px;">Reserva Recibida!</h1></td></tr>';
        $h .= '<tr><td style="padding:30px;"><p style="font-size:16px;">Estimado/a <strong style="color:#D4A017;">' . esc_html($rep["nombre"]) . '</strong>,</p><p>Hemos recibido tu solicitud de reserva. Nuestro equipo la revisara y te contactaremos pronto.</p>';
        $h .= '<table style="background:#FFF9E6;border-left:4px solid #D4A017;margin:20px 0;width:100%;"><tr><td style="padding:20px;"><h3 style="color:#D4A017;margin:0 0 10px;">DETALLES DEL TOUR</h3><p style="margin:5px 0;"><strong>' . esc_html($data["tour"]) . '</strong></p><p style="margin:5px 0;color:#7CB342;font-size:18px;"><strong>' . $fecha . '</strong></p>';
        if ($precio) {
            $h .= '<p style="margin:5px 0;color:#D4A017;font-size:16px;"><strong>Precio: ' . esc_html($precio) . '</strong> por persona</p>';
        }
        $h .= '<p style="margin:5px 0;">' . $count . ' pasajero(s)</p></td></tr></table>';
        $h .= '<table style="background:#F0F7E6;border-left:4px solid #7CB342;margin:20px 0;width:100%;"><tr><td style="padding:20px;"><h3 style="color:#7CB342;margin:0 0 10px;">TUS DATOS</h3><p style="margin:5px 0;">' . esc_html($rep["email"]) . '</p><p style="margin:5px 0;">' . esc_html($rep["telefono"]) . '</p><p style="margin:5px 0;">' . esc_html($rep["pais"]) . '</p></td></tr></table>';
        $h .= '<p style="background:#FFF9E6;padding:15px;border-radius:8px;text-align:center;"><strong>Adjuntamos el PDF</strong> con todos los detalles de tu reserva.</p></td></tr>';
        $h .= '<tr><td style="background:#f9f9f9;padding:25px;text-align:center;border-top:4px solid #D4A017;"><p style="color:#D4A017;font-size:18px;font-weight:bold;margin:0 0 10px;">Ready To Travel Peru</p><p style="color:#666;margin:0;font-size:14px;">reservas@readytotravelperu.com</p><p style="color:#666;margin:5px 0;font-size:14px;">WhatsApp: +51 992 515 665</p><p style="color:#999;margin:15px 0 0;font-size:12px;">www.readytotravelperu.com</p></td></tr>';
        $h .= '</table></td></tr></table></body></html>';
        return $h;
    }

    private function get_english_template($data, $rep, $fecha, $count, $logo_url) {
        $precio = !empty($data["precio_tour"]) ? $data["precio_tour"] : '';
        $h = '<!DOCTYPE html><html><body style="margin:0;font-family:Arial;background:#f5f5f5;"><table width="100%" style="background:#f5f5f5;padding:20px;"><tr><td align="center"><table width="600" style="background:#fff;border-radius:12px;overflow:hidden;">';
        $h .= '<tr><td style="background:#fff;padding:25px;text-align:center;border-bottom:4px solid #D4A017;"><img src="' . esc_url($logo_url) . '" style="max-width:250px;"><p style="color:#7CB342;margin:15px 0 0;font-style:italic;">Where every journey becomes an unforgettable memory</p></td></tr>';
        $h .= '<tr><td style="background:#7CB342;padding:20px;text-align:center;"><h1 style="color:#fff;margin:0;font-size:24px;">Booking Received!</h1></td></tr>';
        $h .= '<tr><td style="padding:30px;"><p style="font-size:16px;">Dear <strong style="color:#D4A017;">' . esc_html($rep["nombre"]) . '</strong>,</p><p>We have received your booking request. Our team will review it and contact you soon.</p>';
        $h .= '<table style="background:#FFF9E6;border-left:4px solid #D4A017;margin:20px 0;width:100%;"><tr><td style="padding:20px;"><h3 style="color:#D4A017;margin:0 0 10px;">TOUR DETAILS</h3><p style="margin:5px 0;"><strong>' . esc_html($data["tour"]) . '</strong></p><p style="margin:5px 0;color:#7CB342;font-size:18px;"><strong>' . $fecha . '</strong></p>';
        if ($precio) {
            $h .= '<p style="margin:5px 0;color:#D4A017;font-size:16px;"><strong>Price: ' . esc_html($precio) . '</strong> per person</p>';
        }
        $h .= '<p style="margin:5px 0;">' . $count . ' passenger(s)</p></td></tr></table>';
        $h .= '<table style="background:#F0F7E6;border-left:4px solid #7CB342;margin:20px 0;width:100%;"><tr><td style="padding:20px;"><h3 style="color:#7CB342;margin:0 0 10px;">YOUR DETAILS</h3><p style="margin:5px 0;">' . esc_html($rep["email"]) . '</p><p style="margin:5px 0;">' . esc_html($rep["telefono"]) . '</p><p style="margin:5px 0;">' . esc_html($rep["pais"]) . '</p></td></tr></table>';
        $h .= '<p style="background:#FFF9E6;padding:15px;border-radius:8px;text-align:center;"><strong>PDF attached</strong> with all your booking details.</p></td></tr>';
        $h .= '<tr><td style="background:#f9f9f9;padding:25px;text-align:center;border-top:4px solid #D4A017;"><p style="color:#D4A017;font-size:18px;font-weight:bold;margin:0 0 10px;">Ready To Travel Peru</p><p style="color:#666;margin:0;font-size:14px;">reservas@readytotravelperu.com</p><p style="color:#666;margin:5px 0;font-size:14px;">WhatsApp: +51 992 515 665</p><p style="color:#999;margin:15px 0 0;font-size:12px;">www.readytotravelperu.com</p></td></tr>';
        $h .= '</table></td></tr></table></body></html>';
        return $h;
    }
}
