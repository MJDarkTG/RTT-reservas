=== RTT Reservas - Ready To Travel Peru ===
Contributors: readytotravelperu
Tags: reservas, tours, booking, travel, peru, cusco
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sistema de reservas de tours con formulario wizard, generación de PDF y envío de emails.

== Description ==

RTT Reservas es un plugin de WordPress desarrollado para Ready To Travel Peru que permite:

* Formulario de reserva en 3 pasos (wizard)
* Selección de tours con categorías por duración
* Registro de múltiples pasajeros con datos completos
* Generación automática de PDF con la reserva
* Envío de confirmación por email al cliente
* Soporte bilingüe (Español e Inglés)
* Panel de administración para configurar SMTP

== Installation ==

1. Sube la carpeta `rtt-reservas` al directorio `/wp-content/plugins/`
2. Activa el plugin a través del menú 'Plugins' en WordPress
3. Ve a 'RTT Reservas' en el panel de administración
4. Configura los datos SMTP para el envío de correos
5. Inserta el shortcode en cualquier página

== Uso del Shortcode ==

**Versión en Español:**
`[rtt_reserva]`

**Versión en Inglés:**
`[rtt_reserva lang="en"]`

Puedes insertar el shortcode en cualquier página o entrada de WordPress,
o directamente en Elementor usando el widget de "Shortcode".

== Configuración SMTP ==

Para enviar correos correctamente, configura:

1. **Servidor SMTP**: smtp.gmail.com (para Gmail)
2. **Puerto**: 465 (SSL) o 587 (TLS)
3. **Seguridad**: SSL o TLS
4. **Usuario**: tu correo completo
5. **Contraseña**: Contraseña de aplicación (NO tu contraseña normal)

**Para Gmail:**
- Ve a https://myaccount.google.com/apppasswords
- Genera una contraseña de aplicación
- Usa esa contraseña en la configuración

== Frequently Asked Questions ==

= ¿Funciona con Elementor? =

Sí, puedes insertar el shortcode usando el widget de "Shortcode" de Elementor.

= ¿Puedo personalizar los tours? =

Actualmente los tours están definidos en el código.
Contacta al desarrollador para agregar o modificar tours.

= ¿Los PDFs se guardan en el servidor? =

No, los PDFs se generan en memoria, se envían por email y luego se eliminan.
No se almacenan en el servidor.

= ¿Funciona con WPML o Polylang? =

Sí, el plugin detecta automáticamente el idioma activo.

== Changelog ==

= 1.0.0 =
* Versión inicial
* Formulario wizard de 3 pasos
* Generación de PDF con FPDF
* Envío de emails con PHPMailer
* Soporte bilingüe ES/EN
* Panel de administración

== Upgrade Notice ==

= 1.0.0 =
Primera versión del plugin.
