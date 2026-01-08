<?php
/**
 * Clase Mailer - Reemplazo de wp_mail
 *
 * @package EmailIT_Mailer
 * @since 1.0.0
 */

namespace EmailIT;

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase Mailer
 * 
 * Maneja el envío de correos electrónicos reemplazando wp_mail()
 */
class Mailer
{

    /**
     * Instancia de Settings
     *
     * @var Settings
     */
    private $settings;

    /**
     * Instancia de API
     *
     * @var API
     */
    private $api;

    /**
     * Instancia de Logger
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor
     *
     * @param Settings $settings Instancia de configuraciones
     * @param API      $api      Instancia de API
     * @param Logger   $logger   Instancia de Logger
     */
    public function __construct(Settings $settings, API $api, Logger $logger)
    {
        $this->settings = $settings;
        $this->api = $api;
        $this->logger = $logger;
    }

    /**
     * Envía un correo electrónico
     *
     * @param string|array $to          Destinatario(s)
     * @param string       $subject     Asunto
     * @param string       $message     Mensaje
     * @param string|array $headers     Cabeceras
     * @param string|array $attachments Adjuntos
     * @return bool
     */
    public function send($to, $subject, $message, $headers = '', $attachments = array())
    {
        // Permitir que otros plugins modifiquen los parámetros
        $atts = apply_filters('wp_mail', compact('to', 'subject', 'message', 'headers', 'attachments'));

        if (isset($atts['to'])) {
            $to = $atts['to'];
        }
        if (isset($atts['subject'])) {
            $subject = $atts['subject'];
        }
        if (isset($atts['message'])) {
            $message = $atts['message'];
        }
        if (isset($atts['headers'])) {
            $headers = $atts['headers'];
        }
        if (isset($atts['attachments'])) {
            $attachments = $atts['attachments'];
        }

        // Parsear los datos del email
        $parsed_data = $this->parse_email_data($to, $subject, $message, $headers, $attachments);

        // Enviar a cada destinatario
        $success = true;
        $recipients = $this->normalize_recipients($parsed_data['to']);

        foreach ($recipients as $recipient) {
            $email_data = array_merge($parsed_data, array('to' => $recipient));
            $result = $this->send_single_email($email_data);

            if (!$result) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Envía un correo individual
     *
     * @param array $email_data Datos del email
     * @return bool
     */
    private function send_single_email(array $email_data)
    {
        $result = $this->api->send_email($email_data);

        if (is_wp_error($result)) {
            // Registrar el error
            $this->logger->log_failure(
                $email_data['to'],
                $email_data['subject'],
                $result->get_error_message(),
                $email_data['parsed_headers'] ?? array()
            );

            // Disparar acción de WordPress para compatibilidad
            do_action('wp_mail_failed', $result);

            return false;
        }

        // Registrar el éxito
        $this->logger->log_success(
            $email_data['to'],
            $email_data['subject'],
            $this->api->get_last_response(),
            $email_data['parsed_headers'] ?? array()
        );

        return true;
    }

    /**
     * Parsea los datos del correo electrónico
     *
     * @param string|array $to          Destinatario(s)
     * @param string       $subject     Asunto
     * @param string       $message     Mensaje
     * @param string|array $headers     Cabeceras
     * @param string|array $attachments Adjuntos
     * @return array
     */
    private function parse_email_data($to, $subject, $message, $headers, $attachments)
    {
        // Parsear headers
        $parsed_headers = $this->parse_headers($headers);

        // Determinar el remitente
        $from = $this->get_from_address($parsed_headers);

        // Determinar Reply-To
        $reply_to = $this->get_reply_to($parsed_headers);

        // Determinar tipo de contenido
        $content_type = $this->get_content_type($parsed_headers);

        // Preparar el mensaje según el tipo de contenido
        $html_content = '';
        $text_content = '';

        if ('text/html' === $content_type || stripos($content_type, 'text/html') !== false) {
            $html_content = $message;
            // Generar versión de texto plano
            $text_content = $this->html_to_text($message);
        } else {
            $text_content = $message;
            // Si el mensaje parece HTML, usarlo también como HTML
            if (preg_match('/<[^>]+>/', $message)) {
                $html_content = nl2br($message);
            }
        }

        // Preparar adjuntos
        $prepared_attachments = $this->prepare_attachments($attachments);

        // Preparar headers adicionales para la API
        $api_headers = $this->prepare_api_headers($parsed_headers);

        return array(
            'to' => $to,
            'from' => $from,
            'reply_to' => $reply_to,
            'subject' => $subject,
            'html' => $html_content,
            'text' => $text_content,
            'attachments' => $prepared_attachments,
            'headers' => $api_headers,
            'parsed_headers' => $parsed_headers,
        );
    }

    /**
     * Parsea los headers del correo
     *
     * @param string|array $headers Headers en formato string o array
     * @return array Headers parseados
     */
    private function parse_headers($headers)
    {
        $parsed = array(
            'from' => '',
            'from_name' => '',
            'reply_to' => '',
            'cc' => array(),
            'bcc' => array(),
            'content_type' => '',
            'charset' => '',
            'custom' => array(),
        );

        if (empty($headers)) {
            return $parsed;
        }

        // Convertir a array si es string
        if (is_string($headers)) {
            $headers = explode("\n", str_replace("\r\n", "\n", $headers));
        }

        foreach ($headers as $header) {
            if (empty($header)) {
                continue;
            }

            // Separar nombre y valor
            $parts = explode(':', $header, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $name = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            switch ($name) {
                case 'from':
                    $parsed['from'] = $value;
                    // Extraer nombre si está presente
                    if (preg_match('/^(.+?)\s*<(.+?)>$/', $value, $matches)) {
                        $parsed['from_name'] = trim($matches[1], ' "');
                        $parsed['from'] = $matches[2];
                    }
                    break;

                case 'reply-to':
                    $parsed['reply_to'] = $value;
                    break;

                case 'cc':
                    $parsed['cc'] = array_merge($parsed['cc'], $this->parse_email_list($value));
                    break;

                case 'bcc':
                    $parsed['bcc'] = array_merge($parsed['bcc'], $this->parse_email_list($value));
                    break;

                case 'content-type':
                    if (preg_match('/^([^;]+)/', $value, $matches)) {
                        $parsed['content_type'] = strtolower(trim($matches[1]));
                    }
                    if (preg_match('/charset=([^\s;]+)/i', $value, $matches)) {
                        $parsed['charset'] = trim($matches[1], '"');
                    }
                    break;

                default:
                    $parsed['custom'][$name] = $value;
                    break;
            }
        }

        return $parsed;
    }

    /**
     * Parsea una lista de emails separados por coma
     *
     * @param string $list Lista de emails
     * @return array
     */
    private function parse_email_list($list)
    {
        $emails = array();
        $parts = explode(',', $list);

        foreach ($parts as $part) {
            $email = trim($part);
            if (!empty($email)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /**
     * Obtiene la dirección del remitente
     *
     * @param array $parsed_headers Headers parseados
     * @return string
     */
    private function get_from_address(array $parsed_headers)
    {
        $force_from = $this->settings->get('force_from');

        // Si se fuerza el remitente, usar el configurado
        if ($force_from || empty($parsed_headers['from'])) {
            $from_email = $this->settings->get('from_email');
            $from_name = $this->settings->get('from_name');

            if (empty($from_email)) {
                // Fallback al correo del administrador
                $from_email = get_option('admin_email');
                $from_name = get_option('blogname');
            }
        } else {
            $from_email = $parsed_headers['from'];
            $from_name = $parsed_headers['from_name'];
        }

        // Formatear: "Nombre <email@dominio.com>"
        if (!empty($from_name)) {
            return sprintf('%s <%s>', $from_name, $from_email);
        }

        return $from_email;
    }

    /**
     * Obtiene la dirección Reply-To
     *
     * @param array $parsed_headers Headers parseados
     * @return string
     */
    private function get_reply_to(array $parsed_headers)
    {
        // Usar Reply-To del header si existe
        if (!empty($parsed_headers['reply_to'])) {
            return $parsed_headers['reply_to'];
        }

        // Usar el configurado en settings si existe
        $settings_reply_to = $this->settings->get('reply_to');
        if (!empty($settings_reply_to)) {
            return $settings_reply_to;
        }

        // Fallback: usar el email del remitente
        $from_email = $this->settings->get('from_email');
        return !empty($from_email) ? $from_email : get_option('admin_email');
    }

    /**
     * Obtiene el tipo de contenido
     *
     * @param array $parsed_headers Headers parseados
     * @return string
     */
    private function get_content_type(array $parsed_headers)
    {
        if (!empty($parsed_headers['content_type'])) {
            return $parsed_headers['content_type'];
        }

        // Aplicar filtro de WordPress
        return apply_filters('wp_mail_content_type', 'text/plain');
    }

    /**
     * Normaliza los destinatarios a un array
     *
     * @param string|array $to Destinatarios
     * @return array
     */
    private function normalize_recipients($to)
    {
        if (is_array($to)) {
            return $to;
        }

        return array_map('trim', explode(',', $to));
    }

    /**
     * Convierte HTML a texto plano
     *
     * @param string $html Contenido HTML
     * @return string Texto plano
     */
    private function html_to_text($html)
    {
        // Remover scripts y estilos
        $text = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $text = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $text);

        // Convertir algunos elementos a texto
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        $text = preg_replace('/<\/h[1-6]>/i', "\n\n", $text);
        $text = preg_replace('/<\/li>/i', "\n", $text);
        $text = preg_replace('/<\/tr>/i', "\n", $text);
        $text = preg_replace('/<\/td>/i', "\t", $text);

        // Remover todas las etiquetas HTML restantes
        $text = wp_strip_all_tags($text);

        // Decodificar entidades HTML
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Limpiar espacios en blanco excesivos
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Prepara los adjuntos para la API
     *
     * @param string|array $attachments Adjuntos
     * @return array
     */
    private function prepare_attachments($attachments)
    {
        if (empty($attachments)) {
            return array();
        }

        if (is_string($attachments)) {
            $attachments = array($attachments);
        }

        $prepared = array();

        foreach ($attachments as $attachment) {
            if (is_string($attachment) && file_exists($attachment)) {
                $content = file_get_contents($attachment);
                if (false !== $content) {
                    $prepared[] = array(
                        'filename' => basename($attachment),
                        'content' => base64_encode($content),
                        'content_type' => $this->get_mime_type($attachment),
                    );
                }
            }
        }

        return $prepared;
    }

    /**
     * Obtiene el tipo MIME de un archivo
     *
     * @param string $filepath Ruta del archivo
     * @return string
     */
    private function get_mime_type($filepath)
    {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        $mime_types = wp_get_mime_types();

        foreach ($mime_types as $ext_pattern => $mime) {
            $extensions = explode('|', $ext_pattern);
            if (in_array($extension, $extensions, true)) {
                return $mime;
            }
        }

        if (function_exists('mime_content_type')) {
            return mime_content_type($filepath);
        }

        return 'application/octet-stream';
    }

    /**
     * Prepara headers adicionales para la API
     *
     * @param array $parsed_headers Headers parseados
     * @return array
     */
    private function prepare_api_headers(array $parsed_headers)
    {
        $api_headers = array();

        // Agregar headers personalizados
        if (!empty($parsed_headers['custom'])) {
            foreach ($parsed_headers['custom'] as $name => $value) {
                // Capitalizar el nombre del header
                $formatted_name = implode('-', array_map('ucfirst', explode('-', $name)));
                $api_headers[$formatted_name] = $value;
            }
        }

        return $api_headers;
    }
}
