<?php
if (!defined("ABSPATH")) exit;

class RTT_Mail {

    private $options;

    public function __construct() {
        $this->options = get_option("rtt_reservas_options", []);
    }

    public function send_confirmation($data, $pdf_content) {
        $options = $this->options;
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

        // Asunto según estado de pago
        $payment_completed = !empty($data['payment_completed']);
        if ($payment_completed) {
            $subject = $lang === "en"
                ? ($options["email_subject_confirmed_en"] ?? "Booking Confirmed")
                : ($options["email_subject_confirmed_es"] ?? "Reserva Confirmada");
        } else {
            $subject = $lang === "en"
                ? ($options["email_subject_en"] ?? "Pre-Booking Received")
                : ($options["email_subject_es"] ?? "Pre-Reserva Recibida");
        }

        $message = $this->get_email_template($data, $lang);

        $from_name = $options["from_name"] ?? "Ready To Travel Peru";
        $from_email = $options["from_email"] ?? get_option("admin_email");
        $headers = array(
            "Content-Type: text/html; charset=UTF-8",
            "From: " . $from_name . " <" . $from_email . ">"
        );

        if (!empty($options["cc_email"])) {
            $headers[] = "Cc: " . $options["cc_email"];
        }

        $upload_dir = wp_upload_dir();
        $pdf_path = $upload_dir["basedir"] . "/rtt-reservas/reserva_" . time() . ".pdf";
        wp_mkdir_p($upload_dir["basedir"] . "/rtt-reservas");
        file_put_contents($pdf_path, $pdf_content);

        $sent = wp_mail($to, $subject, $message, $headers, array($pdf_path));

        if (file_exists($pdf_path)) unlink($pdf_path);

        return $sent ? true : new WP_Error("email_error", "Error");
    }

    /**
     * Obtener configuración de plantilla con valores por defecto
     */
    private function get_template_config() {
        return [
            'logo_url' => $this->options['email_logo_url'] ?? 'http://readytotravelperu.com/wp-content/uploads/2022/08/ready-to-travel-peru.jpg',
            'slogan_es' => $this->options['email_slogan_es'] ?? 'Donde cada viaje se convierte en un recuerdo inolvidable',
            'slogan_en' => $this->options['email_slogan_en'] ?? 'Where every journey becomes an unforgettable memory',
            'contact_email' => $this->options['email_contact_email'] ?? 'reservas@readytotravelperu.com',
            'whatsapp' => $this->options['email_whatsapp'] ?? '+51 992 515 665',
            'website' => $this->options['email_website'] ?? 'www.readytotravelperu.com',
            'company_name' => $this->options['from_name'] ?? 'Ready To Travel Peru',
        ];
    }

    private function get_email_template($data, $lang) {
        $rep = $data["representante"];
        $count = count($data["pasajeros"]);
        $fecha = date("d/m/Y", strtotime($data["fecha"]));
        $config = $this->get_template_config();
        $payment_completed = !empty($data['payment_completed']);

        return $lang === "en"
            ? $this->get_english_template($data, $rep, $fecha, $count, $config, $payment_completed)
            : $this->get_spanish_template($data, $rep, $fecha, $count, $config, $payment_completed);
    }

    private function get_spanish_template($data, $rep, $fecha, $count, $config, $payment_completed) {
        $precio = !empty($data["precio_tour"]) ? $data["precio_tour"] : '';

        $h = '<!DOCTYPE html><html><body style="margin:0;font-family:Arial;background:#f5f5f5;">';
        $h .= '<table width="100%" style="background:#f5f5f5;padding:20px;"><tr><td align="center">';
        $h .= '<table width="600" style="background:#fff;border-radius:12px;overflow:hidden;">';

        // Header con logo y slogan
        $h .= '<tr><td style="background:#fff;padding:25px;text-align:center;border-bottom:4px solid #D4A017;">';
        $h .= '<img src="' . esc_url($config['logo_url']) . '" style="max-width:250px;">';
        $h .= '<p style="color:#7CB342;margin:15px 0 0;font-style:italic;">' . esc_html($config['slogan_es']) . '</p>';
        $h .= '</td></tr>';

        // Banner según estado de pago
        if ($payment_completed) {
            $h .= '<tr><td style="background:#4CAF50;padding:20px;text-align:center;">';
            $h .= '<h1 style="color:#fff;margin:0;font-size:24px;">¡Reserva Confirmada!</h1>';
            $h .= '</td></tr>';
        } else {
            $h .= '<tr><td style="background:#7CB342;padding:20px;text-align:center;">';
            $h .= '<h1 style="color:#fff;margin:0;font-size:24px;">¡Pre - Reserva Recibida!</h1>';
            $h .= '</td></tr>';
        }

        // Contenido según estado de pago
        $h .= '<tr><td style="padding:30px;">';
        $h .= '<p style="font-size:16px;">Estimado/a <strong style="color:#D4A017;">' . esc_html($rep["nombre"]) . '</strong>,</p>';
        if ($payment_completed) {
            $h .= '<p>¡Gracias por tu pago! Tu reserva ha sido confirmada exitosamente.</p>';
            if (!empty($data['transaction_id'])) {
                $h .= '<p style="background:#E8F5E9;padding:10px;border-radius:5px;">ID de Transacción: <strong>' . esc_html($data['transaction_id']) . '</strong></p>';
            }
        } else {
            $h .= '<p>Hemos recibido tu solicitud de reserva. Nuestro equipo la revisará y te contactaremos pronto.</p>';
        }

        // Detalles del tour
        $h .= '<table style="background:#FFF9E6;border-left:4px solid #D4A017;margin:20px 0;width:100%;"><tr><td style="padding:20px;">';
        $h .= '<h3 style="color:#D4A017;margin:0 0 10px;">DETALLES DEL TOUR</h3>';
        $h .= '<p style="margin:5px 0;"><strong>' . esc_html($data["tour"]) . '</strong></p>';
        $h .= '<p style="margin:5px 0;color:#7CB342;font-size:18px;"><strong>' . $fecha . '</strong></p>';
        if ($precio) {
            $h .= '<p style="margin:5px 0;color:#D4A017;font-size:16px;"><strong>Precio: ' . esc_html($precio) . '</strong> por persona</p>';
        }
        $h .= '<p style="margin:5px 0;">' . $count . ' pasajero(s)</p>';
        $h .= '</td></tr></table>';

        // Datos del cliente
        $h .= '<table style="background:#F0F7E6;border-left:4px solid #7CB342;margin:20px 0;width:100%;"><tr><td style="padding:20px;">';
        $h .= '<h3 style="color:#7CB342;margin:0 0 10px;">TUS DATOS</h3>';
        $h .= '<p style="margin:5px 0;">' . esc_html($rep["email"]) . '</p>';
        $h .= '<p style="margin:5px 0;">' . esc_html($rep["telefono"]) . '</p>';
        $h .= '<p style="margin:5px 0;">' . esc_html($rep["pais"]) . '</p>';
        $h .= '</td></tr></table>';

        $h .= '<p style="background:#FFF9E6;padding:15px;border-radius:8px;text-align:center;">';
        $h .= '<strong>Adjuntamos el PDF</strong> con todos los detalles de tu reserva.</p>';
        $h .= '</td></tr>';

        // Footer con datos de contacto personalizables
        $h .= '<tr><td style="background:#f9f9f9;padding:25px;text-align:center;border-top:4px solid #D4A017;">';
        $h .= '<p style="color:#D4A017;font-size:18px;font-weight:bold;margin:0 0 10px;">' . esc_html($config['company_name']) . '</p>';
        $h .= '<p style="color:#666;margin:0;font-size:14px;">' . esc_html($config['contact_email']) . '</p>';
        $h .= '<p style="color:#666;margin:5px 0;font-size:14px;">WhatsApp: ' . esc_html($config['whatsapp']) . '</p>';
        $h .= '<p style="color:#999;margin:15px 0 0;font-size:12px;">' . esc_html($config['website']) . '</p>';
        $h .= '</td></tr>';

        $h .= '</table></td></tr></table></body></html>';

        return $h;
    }

    private function get_english_template($data, $rep, $fecha, $count, $config, $payment_completed) {
        $precio = !empty($data["precio_tour"]) ? $data["precio_tour"] : '';

        $h = '<!DOCTYPE html><html><body style="margin:0;font-family:Arial;background:#f5f5f5;">';
        $h .= '<table width="100%" style="background:#f5f5f5;padding:20px;"><tr><td align="center">';
        $h .= '<table width="600" style="background:#fff;border-radius:12px;overflow:hidden;">';

        // Header con logo y slogan
        $h .= '<tr><td style="background:#fff;padding:25px;text-align:center;border-bottom:4px solid #D4A017;">';
        $h .= '<img src="' . esc_url($config['logo_url']) . '" style="max-width:250px;">';
        $h .= '<p style="color:#7CB342;margin:15px 0 0;font-style:italic;">' . esc_html($config['slogan_en']) . '</p>';
        $h .= '</td></tr>';

        // Banner según estado de pago
        if ($payment_completed) {
            $h .= '<tr><td style="background:#4CAF50;padding:20px;text-align:center;">';
            $h .= '<h1 style="color:#fff;margin:0;font-size:24px;">Booking Confirmed!</h1>';
            $h .= '</td></tr>';
        } else {
            $h .= '<tr><td style="background:#7CB342;padding:20px;text-align:center;">';
            $h .= '<h1 style="color:#fff;margin:0;font-size:24px;">Pre - Booking Received!</h1>';
            $h .= '</td></tr>';
        }

        // Contenido según estado de pago
        $h .= '<tr><td style="padding:30px;">';
        $h .= '<p style="font-size:16px;">Dear <strong style="color:#D4A017;">' . esc_html($rep["nombre"]) . '</strong>,</p>';
        if ($payment_completed) {
            $h .= '<p>Thank you for your payment! Your booking has been successfully confirmed.</p>';
            if (!empty($data['transaction_id'])) {
                $h .= '<p style="background:#E8F5E9;padding:10px;border-radius:5px;">Transaction ID: <strong>' . esc_html($data['transaction_id']) . '</strong></p>';
            }
        } else {
            $h .= '<p>We have received your booking request. Our team will review it and contact you soon.</p>';
        }

        // Detalles del tour
        $h .= '<table style="background:#FFF9E6;border-left:4px solid #D4A017;margin:20px 0;width:100%;"><tr><td style="padding:20px;">';
        $h .= '<h3 style="color:#D4A017;margin:0 0 10px;">TOUR DETAILS</h3>';
        $h .= '<p style="margin:5px 0;"><strong>' . esc_html($data["tour"]) . '</strong></p>';
        $h .= '<p style="margin:5px 0;color:#7CB342;font-size:18px;"><strong>' . $fecha . '</strong></p>';
        if ($precio) {
            $h .= '<p style="margin:5px 0;color:#D4A017;font-size:16px;"><strong>Price: ' . esc_html($precio) . '</strong> per person</p>';
        }
        $h .= '<p style="margin:5px 0;">' . $count . ' passenger(s)</p>';
        $h .= '</td></tr></table>';

        // Datos del cliente
        $h .= '<table style="background:#F0F7E6;border-left:4px solid #7CB342;margin:20px 0;width:100%;"><tr><td style="padding:20px;">';
        $h .= '<h3 style="color:#7CB342;margin:0 0 10px;">YOUR DETAILS</h3>';
        $h .= '<p style="margin:5px 0;">' . esc_html($rep["email"]) . '</p>';
        $h .= '<p style="margin:5px 0;">' . esc_html($rep["telefono"]) . '</p>';
        $h .= '<p style="margin:5px 0;">' . esc_html($rep["pais"]) . '</p>';
        $h .= '</td></tr></table>';

        $h .= '<p style="background:#FFF9E6;padding:15px;border-radius:8px;text-align:center;">';
        $h .= '<strong>PDF attached</strong> with all your booking details.</p>';
        $h .= '</td></tr>';

        // Footer con datos de contacto personalizables
        $h .= '<tr><td style="background:#f9f9f9;padding:25px;text-align:center;border-top:4px solid #D4A017;">';
        $h .= '<p style="color:#D4A017;font-size:18px;font-weight:bold;margin:0 0 10px;">' . esc_html($config['company_name']) . '</p>';
        $h .= '<p style="color:#666;margin:0;font-size:14px;">' . esc_html($config['contact_email']) . '</p>';
        $h .= '<p style="color:#666;margin:5px 0;font-size:14px;">WhatsApp: ' . esc_html($config['whatsapp']) . '</p>';
        $h .= '<p style="color:#999;margin:15px 0 0;font-size:12px;">' . esc_html($config['website']) . '</p>';
        $h .= '</td></tr>';

        $h .= '</table></td></tr></table></body></html>';

        return $h;
    }
}
