<?php
/**
 * Clase cliente de la API de EmailIT
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
 * Clase API
 * 
 * Maneja todas las comunicaciones con la API de EmailIT
 */
class API
{

    /**
     * Endpoint de la API
     *
     * @var string
     */
    const ENDPOINT = 'https://api.emailit.com/v1/emails';

    /**
     * Instancia de Settings
     *
     * @var Settings
     */
    private $settings;

    /**
     * Último error ocurrido
     *
     * @var string
     */
    private $last_error = '';

    /**
     * Última respuesta de la API
     *
     * @var array
     */
    private $last_response = array();

    /**
     * Constructor
     *
     * @param Settings $settings Instancia de configuraciones
     */
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Envía un correo electrónico a través de la API de EmailIT
     *
     * @param array $email_data Datos del correo
     * @return bool|array True si el envío fue exitoso, array con error en caso contrario
     */
    public function send_email(array $email_data)
    {
        $api_key = $this->settings->get('api_key');

        if (empty($api_key)) {
            $this->last_error = __('API Key no configurada', 'emailit-mailer');
            return $this->create_error('api_key_missing', $this->last_error);
        }

        // Validar campos requeridos
        $validation = $this->validate_email_data($email_data);
        if (is_wp_error($validation)) {
            $this->last_error = $validation->get_error_message();
            return $validation;
        }

        // Preparar el payload
        $payload = $this->prepare_payload($email_data);

        // Realizar la petición
        $response = $this->make_request($payload, $api_key);

        return $response;
    }

    /**
     * Valida los datos del correo electrónico
     *
     * @param array $email_data Datos del correo
     * @return true|\WP_Error True si es válido, WP_Error si hay errores
     */
    private function validate_email_data(array $email_data)
    {
        $required_fields = array('from', 'to', 'subject');

        foreach ($required_fields as $field) {
            if (empty($email_data[$field])) {
                return $this->create_error(
                    'missing_field',
                    sprintf(
                        /* translators: %s: nombre del campo */
                        __('El campo "%s" es requerido.', 'emailit-mailer'),
                        $field
                    )
                );
            }
        }

        // Debe tener contenido HTML o texto plano
        if (empty($email_data['html']) && empty($email_data['text'])) {
            return $this->create_error(
                'missing_content',
                __('El correo debe tener contenido HTML o texto plano.', 'emailit-mailer')
            );
        }

        // Validar formato de email
        if (!$this->is_valid_email_format($email_data['from'])) {
            return $this->create_error(
                'invalid_from',
                __('El formato del email del remitente no es válido.', 'emailit-mailer')
            );
        }

        if (!$this->is_valid_email_format($email_data['to'])) {
            return $this->create_error(
                'invalid_to',
                __('El formato del email del destinatario no es válido.', 'emailit-mailer')
            );
        }

        return true;
    }

    /**
     * Valida el formato de un email (puede incluir nombre)
     *
     * @param string $email Email a validar
     * @return bool
     */
    private function is_valid_email_format($email)
    {
        // Formato: "Name <email@domain.com>" o "email@domain.com"
        if (preg_match('/<([^>]+)>/', $email, $matches)) {
            $email = $matches[1];
        }
        return is_email(trim($email));
    }

    /**
     * Prepara el payload para la API
     *
     * @param array $email_data Datos del correo
     * @return array Payload preparado
     */
    private function prepare_payload(array $email_data)
    {
        $payload = array(
            'from' => $email_data['from'],
            'to' => $email_data['to'],
            'subject' => $email_data['subject'],
        );

        // Reply-To
        if (!empty($email_data['reply_to'])) {
            $payload['reply_to'] = $email_data['reply_to'];
        }

        // Contenido HTML
        if (!empty($email_data['html'])) {
            $payload['html'] = $email_data['html'];
        }

        // Contenido de texto plano
        if (!empty($email_data['text'])) {
            $payload['text'] = $email_data['text'];
        }

        // Headers adicionales
        if (!empty($email_data['headers']) && is_array($email_data['headers'])) {
            $payload['headers'] = $email_data['headers'];
        }

        // Adjuntos
        if (!empty($email_data['attachments']) && is_array($email_data['attachments'])) {
            $payload['attachments'] = $this->prepare_attachments($email_data['attachments']);
        }

        /**
         * Filtro para modificar el payload antes de enviarlo
         *
         * @param array $payload Payload preparado
         * @param array $email_data Datos originales del correo
         */
        return apply_filters('emailit_api_payload', $payload, $email_data);
    }

    /**
     * Prepara los adjuntos para la API
     *
     * @param array $attachments Lista de archivos adjuntos
     * @return array Adjuntos preparados
     */
    private function prepare_attachments(array $attachments)
    {
        $prepared = array();

        foreach ($attachments as $attachment) {
            // Si es una ruta de archivo
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
            // Si ya es un array con los datos del adjunto
            elseif (is_array($attachment) && isset($attachment['filename'], $attachment['content'])) {
                $prepared[] = array(
                    'filename' => sanitize_file_name($attachment['filename']),
                    'content' => base64_encode($attachment['content']),
                    'content_type' => $attachment['content_type'] ?? 'application/octet-stream',
                );
            }
        }

        return $prepared;
    }

    /**
     * Obtiene el tipo MIME de un archivo
     *
     * @param string $filepath Ruta del archivo
     * @return string Tipo MIME
     */
    private function get_mime_type($filepath)
    {
        $mime_types = wp_get_mime_types();
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        foreach ($mime_types as $ext_pattern => $mime) {
            $extensions = explode('|', $ext_pattern);
            if (in_array($extension, $extensions, true)) {
                return $mime;
            }
        }

        // Fallback usando la función de PHP si está disponible
        if (function_exists('mime_content_type')) {
            return mime_content_type($filepath);
        }

        return 'application/octet-stream';
    }

    /**
     * Realiza la petición HTTP a la API
     *
     * @param array  $payload Datos a enviar
     * @param string $api_key Clave de API
     * @return bool|\WP_Error True si fue exitoso, WP_Error si falló
     */
    private function make_request(array $payload, string $api_key)
    {
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        );

        /**
         * Filtro para modificar los argumentos de la petición HTTP
         *
         * @param array $args Argumentos de wp_remote_post
         * @param array $payload Payload del correo
         */
        $args = apply_filters('emailit_api_request_args', $args, $payload);

        $response = wp_remote_post(self::ENDPOINT, $args);

        // Error de conexión
        if (is_wp_error($response)) {
            $this->last_error = $response->get_error_message();
            $this->last_response = array(
                'error' => true,
                'message' => $this->last_error,
            );
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($response_body, true);

        $this->last_response = array(
            'code' => $response_code,
            'body' => $decoded_body,
            'raw' => $response_body,
        );

        // Verificar código de respuesta (2xx = éxito)
        if ($response_code >= 200 && $response_code < 300) {
            $this->last_error = '';

            /**
             * Acción después de un envío exitoso
             *
             * @param array $payload Payload enviado
             * @param array $response Respuesta de la API
             */
            do_action('emailit_email_sent', $payload, $this->last_response);

            return true;
        }

        // Error de la API
        $error_message = isset($decoded_body['message'])
            ? $decoded_body['message']
            : sprintf(
                /* translators: %d: código de error HTTP */
                __('Error de la API (código %d)', 'emailit-mailer'),
                $response_code
            );

        $this->last_error = $error_message;

        /**
         * Acción después de un error de envío
         *
         * @param array $payload Payload enviado
         * @param array $response Respuesta de la API
         * @param string $error_message Mensaje de error
         */
        do_action('emailit_email_failed', $payload, $this->last_response, $error_message);

        return $this->create_error('api_error', $error_message);
    }

    /**
     * Crea un objeto WP_Error
     *
     * @param string $code Código del error
     * @param string $message Mensaje del error
     * @return \WP_Error
     */
    private function create_error(string $code, string $message)
    {
        return new \WP_Error('emailit_' . $code, $message);
    }

    /**
     * Obtiene el último error
     *
     * @return string
     */
    public function get_last_error()
    {
        return $this->last_error;
    }

    /**
     * Obtiene la última respuesta
     *
     * @return array
     */
    public function get_last_response()
    {
        return $this->last_response;
    }

    /**
     * Prueba la conexión con la API
     *
     * @param string $test_email Email de destino para prueba
     * @return bool|\WP_Error True si la prueba fue exitosa
     */
    public function test_connection(string $test_email)
    {
        $from_email = $this->settings->get('from_email');
        $from_name = $this->settings->get('from_name');

        if (empty($from_email)) {
            return $this->create_error('missing_from', __('Configure el email del remitente primero.', 'emailit-mailer'));
        }

        $from = !empty($from_name)
            ? sprintf('%s <%s>', $from_name, $from_email)
            : $from_email;

        $email_data = array(
            'from' => $from,
            'to' => $test_email,
            'subject' => sprintf(
                /* translators: %s: nombre del sitio */
                __('[Prueba] EmailIT Mailer - %s', 'emailit-mailer'),
                get_bloginfo('name')
            ),
            'html' => $this->get_test_email_html(),
            'text' => $this->get_test_email_text(),
        );

        $reply_to = $this->settings->get('reply_to');
        if (!empty($reply_to)) {
            $email_data['reply_to'] = $reply_to;
        }

        return $this->send_email($email_data);
    }

    /**
     * Genera el contenido HTML del email de prueba
     *
     * @return string
     */
    private function get_test_email_html()
    {
        $site_name = get_bloginfo('name');
        $site_url = home_url();

        return sprintf(
            '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>%1$s</title>
            </head>
            <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="background: linear-gradient(135deg, #667eea 0%%, #764ba2 100%%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center;">
                    <h1 style="margin: 0;">✉️ EmailIT Mailer</h1>
                    <p style="margin: 10px 0 0 0; opacity: 0.9;">Prueba de Conexión Exitosa</p>
                </div>
                <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-top: none; padding: 30px; border-radius: 0 0 10px 10px;">
                    <h2 style="color: #1f2937; margin-top: 0;">¡Felicitaciones! 🎉</h2>
                    <p style="color: #4b5563; line-height: 1.6;">
                        Este correo confirma que el plugin <strong>EmailIT Mailer</strong> está correctamente configurado 
                        y conectado a la API de EmailIT en su sitio <strong>%2$s</strong>.
                    </p>
                    <p style="color: #4b5563; line-height: 1.6;">
                        Todos los correos de WordPress ahora serán enviados a través de EmailIT, 
                        mejorando la entregabilidad de sus mensajes.
                    </p>
                    <div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
                        <strong style="color: #065f46;">Estado:</strong>
                        <span style="color: #047857;">Conexión establecida correctamente</span>
                    </div>
                    <p style="color: #6b7280; font-size: 14px; margin-bottom: 0;">
                        — Plugin desarrollado por <a href="https://orralasystems.com" style="color: #667eea;">Orrala Systems</a>
                    </p>
                </div>
            </body>
            </html>',
            esc_html__('Prueba de EmailIT Mailer', 'emailit-mailer'),
            esc_html($site_name)
        );
    }

    /**
     * Genera el contenido de texto plano del email de prueba
     *
     * @return string
     */
    private function get_test_email_text()
    {
        $site_name = get_bloginfo('name');

        return sprintf(
            "%s\n\n" .
            "%s\n\n" .
            "%s\n\n" .
            "-- \n%s",
            __('✉️ EmailIT Mailer - Prueba de Conexión', 'emailit-mailer'),
            sprintf(
                /* translators: %s: nombre del sitio */
                __('¡Felicitaciones! Este correo confirma que el plugin EmailIT Mailer está correctamente configurado en su sitio %s.', 'emailit-mailer'),
                $site_name
            ),
            __('Todos los correos de WordPress ahora serán enviados a través de EmailIT.', 'emailit-mailer'),
            __('Plugin desarrollado por Orrala Systems', 'emailit-mailer')
        );
    }
}
