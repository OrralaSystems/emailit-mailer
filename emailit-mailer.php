<?php
/**
 * Plugin Name: Emailit API WordPress Plugin by Orrala Systems
 * Plugin URI: https://orralasystems.com/plugins/emailit-mailer
 * Description: Reemplaza wp_mail() para enviar correos a través de la API de EmailIT con autenticación por API Key. Incluye panel de configuración, logs de envío y herramienta de prueba.
 * Version: 1.1.0
 * Author: Orrala Systems
 * Author URI: https://orralasystems.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: emailit-mailer
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Constantes del plugin
define('EMAILIT_VERSION', '1.1.0');
define('EMAILIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EMAILIT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EMAILIT_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('EMAILIT_API_ENDPOINT', 'https://api.emailit.com/v1/emails');

/**
 * Autoloader para las clases del plugin
 */
spl_autoload_register(function ($class) {
    $prefix = 'EmailIT\\';
    $base_dir = EMAILIT_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-emailit-' . strtolower(str_replace('\\', '-', $relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Clase principal del plugin
 */
final class EmailIT_Mailer
{

    /**
     * Instancia única del plugin
     *
     * @var EmailIT_Mailer
     */
    private static $instance = null;

    /**
     * Instancia de Settings
     *
     * @var EmailIT\Settings
     */
    public $settings;

    /**
     * Instancia de API
     *
     * @var EmailIT\API
     */
    public $api;

    /**
     * Instancia de Logger
     *
     * @var EmailIT\Logger
     */
    public $logger;

    /**
     * Instancia de Admin
     *
     * @var EmailIT\Admin
     */
    public $admin;

    /**
     * Instancia de Mailer
     *
     * @var EmailIT\Mailer
     */
    public $mailer;

    /**
     * Obtiene la instancia única del plugin (Singleton)
     *
     * @return EmailIT_Mailer
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor privado
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_components();
        $this->init_hooks();
    }

    /**
     * Carga las dependencias del plugin
     */
    private function load_dependencies()
    {
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-settings.php';
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-api.php';
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-logger.php';
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-mailer.php';
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-admin.php';
    }

    /**
     * Inicializa los componentes del plugin
     */
    private function init_components()
    {
        $this->settings = new EmailIT\Settings();
        $this->api = new EmailIT\API($this->settings);
        $this->logger = new EmailIT\Logger($this->settings);
        $this->mailer = new EmailIT\Mailer($this->settings, $this->api, $this->logger);
        $this->admin = new EmailIT\Admin($this->settings, $this->logger);
    }

    /**
     * Registra los hooks del plugin
     */
    private function init_hooks()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Agregar enlace de configuración en la lista de plugins
        add_filter('plugin_action_links_' . EMAILIT_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }

    /**
     * Activación del plugin
     */
    public function activate()
    {
        // Crear tabla de logs
        $this->logger->create_table();

        // Establecer opciones por defecto
        $this->settings->set_defaults();

        // Limpiar cache de rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Desactivación del plugin
     */
    public function deactivate()
    {
        // Limpiar eventos programados
        wp_clear_scheduled_hook('emailit_cleanup_logs');
    }

    /**
     * Agrega enlace de configuración en la página de plugins
     *
     * @param array $links Enlaces existentes
     * @return array Enlaces modificados
     */
    public function add_settings_link($links)
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=emailit-settings'),
            __('Configuración', 'emailit-mailer')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Prevenir clonación
     */
    private function __clone()
    {
    }

    /**
     * Prevenir deserialización
     */
    public function __wakeup()
    {
        throw new \Exception('No se puede deserializar una instancia singleton.');
    }
}

/**
 * Función para obtener la instancia del plugin
 *
 * @return EmailIT_Mailer
 */
function emailit_mailer()
{
    return EmailIT_Mailer::get_instance();
}

// Inicializar el plugin
add_action('plugins_loaded', 'emailit_mailer');

/**
 * Reemplazo de wp_mail() usando la función pluggable
 * Esta función se define antes de que WordPress cargue la función nativa
 */
if (!function_exists('wp_mail')) {
    /**
     * Envía un correo electrónico, similar a la función nativa de WordPress.
     *
     * @param string|string[] $to          Dirección(es) de correo del destinatario.
     * @param string          $subject     Asunto del correo.
     * @param string          $message     Contenido del mensaje.
     * @param string|string[] $headers     Cabeceras adicionales opcionales.
     * @param string|string[] $attachments Archivos adjuntos opcionales.
     * @return bool Si el correo fue enviado exitosamente.
     */
    function wp_mail($to, $subject, $message, $headers = '', $attachments = array())
    {
        // Verificar si el plugin está activo y configurado
        if (!class_exists('EmailIT_Mailer')) {
            // Fallback: cargar la función nativa de WordPress
            require_once ABSPATH . WPINC . '/pluggable.php';
            return \wp_mail($to, $subject, $message, $headers, $attachments);
        }

        $plugin = emailit_mailer();

        // Verificar si el plugin está habilitado
        if (!$plugin->settings->is_enabled()) {
            // Plugin deshabilitado, usar PHPMailer nativo de WordPress
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
            require_once ABSPATH . WPINC . '/pluggable.php';

            // Usar la función de envío de PHPMailer directamente
            return emailit_fallback_wp_mail($to, $subject, $message, $headers, $attachments);
        }

        // Verificar que la API key esté configurada
        if (empty($plugin->settings->get('api_key'))) {
            // Si no hay API key, usar el comportamiento por defecto de WordPress
            // Esto previene errores si el plugin está activo pero no configurado
            do_action('wp_mail_failed', new WP_Error(
                'emailit_not_configured',
                __('EmailIT Mailer no está configurado. Por favor configure su API Key.', 'emailit-mailer')
            ));
            return false;
        }

        return $plugin->mailer->send($to, $subject, $message, $headers, $attachments);
    }
}

/**
 * Función de respaldo para wp_mail cuando el plugin está deshabilitado
 * Implementa la lógica nativa de WordPress para enviar correos
 *
 * @param string|string[] $to          Dirección(es) de correo del destinatario.
 * @param string          $subject     Asunto del correo.
 * @param string          $message     Contenido del mensaje.
 * @param string|string[] $headers     Cabeceras adicionales opcionales.
 * @param string|string[] $attachments Archivos adjuntos opcionales.
 * @return bool Si el correo fue enviado exitosamente.
 */
function emailit_fallback_wp_mail($to, $subject, $message, $headers = '', $attachments = array())
{
    // Usar PHPMailer directamente como lo hace WordPress
    global $phpmailer;

    // Asegurarse de que PHPMailer esté inicializado
    if (!($phpmailer instanceof PHPMailer\PHPMailer\PHPMailer)) {
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        $phpmailer = new PHPMailer\PHPMailer\PHPMailer(true);

        $phpmailer::$validator = static function ($email) {
            return (bool) is_email($email);
        };
    }

    // Vaciar cualquier configuración anterior
    $phpmailer->clearAllRecipients();
    $phpmailer->clearAttachments();
    $phpmailer->clearCustomHeaders();
    $phpmailer->clearReplyTos();
    $phpmailer->Body = '';
    $phpmailer->AltBody = '';

    // Configurar destinatarios
    $to_array = is_array($to) ? $to : explode(',', $to);
    foreach ($to_array as $recipient) {
        try {
            $phpmailer->addAddress(trim($recipient));
        } catch (PHPMailer\PHPMailer\Exception $e) {
            continue;
        }
    }

    // Configurar remitente por defecto
    $from_email = apply_filters('wp_mail_from', get_option('admin_email'));
    $from_name = apply_filters('wp_mail_from_name', get_option('blogname'));

    try {
        $phpmailer->setFrom($from_email, $from_name);
    } catch (PHPMailer\PHPMailer\Exception $e) {
        // Error silencioso
    }

    // Asunto y mensaje
    $phpmailer->Subject = $subject;

    // Determinar tipo de contenido
    $content_type = apply_filters('wp_mail_content_type', 'text/plain');

    if ('text/html' === $content_type) {
        $phpmailer->isHTML(true);
        $phpmailer->Body = $message;
    } else {
        $phpmailer->isHTML(false);
        $phpmailer->Body = $message;
    }

    // Procesar headers
    if (!empty($headers)) {
        $headers_array = is_array($headers) ? $headers : explode("\n", str_replace("\r\n", "\n", $headers));

        foreach ($headers_array as $header) {
            if (empty($header)) {
                continue;
            }

            $parts = explode(':', $header, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $name = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            switch ($name) {
                case 'content-type':
                    if (strpos($value, 'text/html') !== false) {
                        $phpmailer->isHTML(true);
                    }
                    break;
                case 'cc':
                    foreach (explode(',', $value) as $cc) {
                        try {
                            $phpmailer->addCC(trim($cc));
                        } catch (PHPMailer\PHPMailer\Exception $e) {
                            continue;
                        }
                    }
                    break;
                case 'bcc':
                    foreach (explode(',', $value) as $bcc) {
                        try {
                            $phpmailer->addBCC(trim($bcc));
                        } catch (PHPMailer\PHPMailer\Exception $e) {
                            continue;
                        }
                    }
                    break;
                case 'reply-to':
                    try {
                        $phpmailer->addReplyTo(trim($value));
                    } catch (PHPMailer\PHPMailer\Exception $e) {
                        // Error silencioso
                    }
                    break;
            }
        }
    }

    // Procesar adjuntos
    if (!empty($attachments)) {
        $attachments_array = is_array($attachments) ? $attachments : array($attachments);

        foreach ($attachments_array as $attachment) {
            if (file_exists($attachment)) {
                try {
                    $phpmailer->addAttachment($attachment);
                } catch (PHPMailer\PHPMailer\Exception $e) {
                    continue;
                }
            }
        }
    }

    // Permitir que otros plugins modifiquen PHPMailer
    do_action_ref_array('phpmailer_init', array(&$phpmailer));

    // Enviar el correo
    try {
        $result = $phpmailer->send();
        return $result;
    } catch (PHPMailer\PHPMailer\Exception $e) {
        $mail_error_data = compact('to', 'subject', 'message', 'headers', 'attachments');
        $mail_error_data['phpmailer_exception_code'] = $e->getCode();

        do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $e->getMessage(), $mail_error_data));

        return false;
    }
}
