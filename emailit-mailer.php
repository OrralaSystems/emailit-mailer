<?php
/**
 * Plugin Name: Emailit API WordPress Plugin by Orrala Systems
 * Plugin URI: https://orralasystems.com/plugins/emailit-mailer
 * Description: Replaces wp_mail() to send emails through the EmailIT API with API Key authentication. Includes settings panel, email logs, and test email functionality.
 * Version: 1.2.0
 * Author: Orrala Systems
 * Author URI: https://orralasystems.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: emailit-mailer
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('EMAILIT_VERSION', '1.2.0');
define('EMAILIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EMAILIT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EMAILIT_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('EMAILIT_API_ENDPOINT', 'https://api.emailit.com/v1/emails');

/**
 * Autoloader for plugin classes
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
 * Main plugin class
 */
final class EmailIT_Mailer
{

    /**
     * Single instance of the plugin
     *
     * @var EmailIT_Mailer
     */
    private static $instance = null;

    /**
     * Settings instance
     *
     * @var EmailIT\Settings
     */
    public $settings;

    /**
     * API instance
     *
     * @var EmailIT\API
     */
    public $api;

    /**
     * Logger instance
     *
     * @var EmailIT\Logger
     */
    public $logger;

    /**
     * Admin instance
     *
     * @var EmailIT\Admin
     */
    public $admin;

    /**
     * Mailer instance
     *
     * @var EmailIT\Mailer
     */
    public $mailer;

    /**
     * Get the singleton instance (Singleton pattern)
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
     * Private constructor
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_components();
        $this->init_hooks();
    }

    /**
     * Load plugin dependencies
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
     * Initialize plugin components
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
     * Register plugin hooks
     */
    private function init_hooks()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Add settings link on plugins page
        add_filter('plugin_action_links_' . EMAILIT_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        // Create logs table
        $this->logger->create_table();

        // Set default options
        $this->settings->set_defaults();

        // Flush rewrite rules cache
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        // Clear scheduled events
        wp_clear_scheduled_hook('emailit_cleanup_logs');
    }

    /**
     * Add settings link on plugins page
     *
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_settings_link($links)
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=emailit-settings'),
            __('Settings', 'emailit-mailer')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize a singleton instance.');
    }
}

/**
 * Function to get the plugin instance
 *
 * @return EmailIT_Mailer
 */
function emailit_mailer()
{
    return EmailIT_Mailer::get_instance();
}

// Initialize the plugin
add_action('plugins_loaded', 'emailit_mailer');

/**
 * wp_mail() replacement using pluggable function
 * This function is defined before WordPress loads the native function
 */
if (!function_exists('wp_mail')) {
    /**
     * Sends an email, similar to the native WordPress function.
     *
     * @param string|string[] $to          Recipient email address(es).
     * @param string          $subject     Email subject.
     * @param string          $message     Message content.
     * @param string|string[] $headers     Optional additional headers.
     * @param string|string[] $attachments Optional file attachments.
     * @return bool Whether the email was sent successfully.
     */
    function wp_mail($to, $subject, $message, $headers = '', $attachments = array())
    {
        // Check if the plugin is active and configured
        if (!class_exists('EmailIT_Mailer')) {
            // Fallback: load the native WordPress function
            require_once ABSPATH . WPINC . '/pluggable.php';
            return \wp_mail($to, $subject, $message, $headers, $attachments);
        }

        $plugin = emailit_mailer();

        // Check if the plugin is enabled
        if (!$plugin->settings->is_enabled()) {
            // Plugin disabled, use native WordPress PHPMailer
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
            require_once ABSPATH . WPINC . '/pluggable.php';

            // Use PHPMailer send function directly
            return emailit_fallback_wp_mail($to, $subject, $message, $headers, $attachments);
        }

        // Check that the API key is configured
        if (empty($plugin->settings->get('api_key'))) {
            // No API key, use default WordPress behavior
            // This prevents errors if the plugin is active but not configured
            do_action('wp_mail_failed', new WP_Error(
                'emailit_not_configured',
                __('EmailIT Mailer is not configured. Please configure your API Key.', 'emailit-mailer')
            ));
            return false;
        }

        return $plugin->mailer->send($to, $subject, $message, $headers, $attachments);
    }
}

/**
 * Fallback function for wp_mail when plugin is disabled
 * Implements native WordPress email sending logic
 *
 * @param string|string[] $to          Recipient email address(es).
 * @param string          $subject     Email subject.
 * @param string          $message     Message content.
 * @param string|string[] $headers     Optional additional headers.
 * @param string|string[] $attachments Optional file attachments.
 * @return bool Whether the email was sent successfully.
 */
function emailit_fallback_wp_mail($to, $subject, $message, $headers = '', $attachments = array())
{
    // Use PHPMailer directly as WordPress does
    global $phpmailer;

    // Ensure PHPMailer is initialized
    if (!($phpmailer instanceof PHPMailer\PHPMailer\PHPMailer)) {
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        $phpmailer = new PHPMailer\PHPMailer\PHPMailer(true);

        $phpmailer::$validator = static function ($email) {
            return (bool) is_email($email);
        };
    }

    // Clear any previous configuration
    $phpmailer->clearAllRecipients();
    $phpmailer->clearAttachments();
    $phpmailer->clearCustomHeaders();
    $phpmailer->clearReplyTos();
    $phpmailer->Body = '';
    $phpmailer->AltBody = '';

    // Configure recipients
    $to_array = is_array($to) ? $to : explode(',', $to);
    foreach ($to_array as $recipient) {
        try {
            $phpmailer->addAddress(trim($recipient));
        } catch (PHPMailer\PHPMailer\Exception $e) {
            continue;
        }
    }

    // Configure default sender
    $from_email = apply_filters('wp_mail_from', get_option('admin_email'));
    $from_name = apply_filters('wp_mail_from_name', get_option('blogname'));

    try {
        $phpmailer->setFrom($from_email, $from_name);
    } catch (PHPMailer\PHPMailer\Exception $e) {
        // Silent error
    }

    // Subject and message
    $phpmailer->Subject = $subject;

    // Determine content type
    $content_type = apply_filters('wp_mail_content_type', 'text/plain');

    if ('text/html' === $content_type) {
        $phpmailer->isHTML(true);
        $phpmailer->Body = $message;
    } else {
        $phpmailer->isHTML(false);
        $phpmailer->Body = $message;
    }

    // Process headers
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
                        // Silent error
                    }
                    break;
            }
        }
    }

    // Process attachments
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

    // Allow other plugins to modify PHPMailer
    do_action_ref_array('phpmailer_init', array(&$phpmailer));

    // Send the email
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
