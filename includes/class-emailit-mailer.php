<?php
/**
 * Mailer Class - wp_mail replacement
 *
 * @package EmailIT_Mailer
 * @since 1.0.0
 */

namespace EmailIT;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mailer Class
 * 
 * Handles email sending by replacing wp_mail()
 */
class Mailer
{

    /**
     * Settings instance
     *
     * @var Settings
     */
    private $settings;

    /**
     * API instance
     *
     * @var API
     */
    private $api;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor
     *
     * @param Settings $settings Settings instance
     * @param API      $api      API instance
     * @param Logger   $logger   Logger instance
     */
    public function __construct(Settings $settings, API $api, Logger $logger)
    {
        $this->settings = $settings;
        $this->api = $api;
        $this->logger = $logger;
    }

    /**
     * Sends an email
     *
     * @param string|array $to          Recipient(s)
     * @param string       $subject     Subject
     * @param string       $message     Message
     * @param string|array $headers     Headers
     * @param string|array $attachments Attachments
     * @return bool
     */
    public function send($to, $subject, $message, $headers = '', $attachments = array())
    {
        // Allow other plugins to modify parameters
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

        // Parse email data
        $parsed_data = $this->parse_email_data($to, $subject, $message, $headers, $attachments);

        // Send to each recipient
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
     * Sends a single email
     *
     * @param array $email_data Email data
     * @return bool
     */
    private function send_single_email(array $email_data)
    {
        $result = $this->api->send_email($email_data);

        if (is_wp_error($result)) {
            // Log the error
            $this->logger->log_failure(
                $email_data['to'],
                $email_data['subject'],
                $result->get_error_message(),
                $email_data['parsed_headers'] ?? array()
            );

            // Trigger WordPress action for compatibility
            do_action('wp_mail_failed', $result);

            return false;
        }

        // Log success
        $this->logger->log_success(
            $email_data['to'],
            $email_data['subject'],
            $this->api->get_last_response(),
            $email_data['parsed_headers'] ?? array()
        );

        return true;
    }

    /**
     * Parses email data
     *
     * @param string|array $to          Recipient(s)
     * @param string       $subject     Subject
     * @param string       $message     Message
     * @param string|array $headers     Headers
     * @param string|array $attachments Attachments
     * @return array
     */
    private function parse_email_data($to, $subject, $message, $headers, $attachments)
    {
        // Parse headers
        $parsed_headers = $this->parse_headers($headers);

        // Determine sender
        $from = $this->get_from_address($parsed_headers);

        // Determine Reply-To
        $reply_to = $this->get_reply_to($parsed_headers);

        // Determine content type
        $content_type = $this->get_content_type($parsed_headers);

        // Prepare message based on content type
        $html_content = '';
        $text_content = '';

        if ('text/html' === $content_type || stripos($content_type, 'text/html') !== false) {
            $html_content = $message;
            // Generate plain text version
            $text_content = $this->html_to_text($message);
        } else {
            $text_content = $message;
            // If message looks like HTML, use it as HTML too
            if (preg_match('/<[^>]+>/', $message)) {
                $html_content = nl2br($message);
            }
        }

        // Prepare attachments
        $prepared_attachments = $this->prepare_attachments($attachments);

        // Prepare additional headers for API
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
     * Parses email headers
     *
     * @param string|array $headers Headers in string or array format
     * @return array Parsed headers
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

        // Convert to array if string
        if (is_string($headers)) {
            $headers = explode("\n", str_replace("\r\n", "\n", $headers));
        }

        foreach ($headers as $header) {
            if (empty($header)) {
                continue;
            }

            // Separate name and value
            $parts = explode(':', $header, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $name = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            switch ($name) {
                case 'from':
                    $parsed['from'] = $value;
                    // Extract name if present
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
     * Parses a comma-separated email list
     *
     * @param string $list Email list
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
     * Gets the sender address
     *
     * @param array $parsed_headers Parsed headers
     * @return string
     */
    private function get_from_address(array $parsed_headers)
    {
        $force_from = $this->settings->get('force_from');

        // If forcing sender, use configured
        if ($force_from || empty($parsed_headers['from'])) {
            $from_email = $this->settings->get('from_email');
            $from_name = $this->settings->get('from_name');

            if (empty($from_email)) {
                // Fallback to admin email
                $from_email = get_option('admin_email');
                $from_name = get_option('blogname');
            }
        } else {
            $from_email = $parsed_headers['from'];
            $from_name = $parsed_headers['from_name'];
        }

        // Format: "Name <email@domain.com>"
        if (!empty($from_name)) {
            return sprintf('%s <%s>', $from_name, $from_email);
        }

        return $from_email;
    }

    /**
     * Gets the Reply-To address
     *
     * @param array $parsed_headers Parsed headers
     * @return string
     */
    private function get_reply_to(array $parsed_headers)
    {
        // Use Reply-To from header if exists
        if (!empty($parsed_headers['reply_to'])) {
            return $parsed_headers['reply_to'];
        }

        // Use configured in settings if exists
        $settings_reply_to = $this->settings->get('reply_to');
        if (!empty($settings_reply_to)) {
            return $settings_reply_to;
        }

        // Fallback: use sender email
        $from_email = $this->settings->get('from_email');
        return !empty($from_email) ? $from_email : get_option('admin_email');
    }

    /**
     * Gets the content type
     *
     * @param array $parsed_headers Parsed headers
     * @return string
     */
    private function get_content_type(array $parsed_headers)
    {
        if (!empty($parsed_headers['content_type'])) {
            return $parsed_headers['content_type'];
        }

        // Apply WordPress filter
        return apply_filters('wp_mail_content_type', 'text/plain');
    }

    /**
     * Normalizes recipients to an array
     *
     * @param string|array $to Recipients
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
     * Converts HTML to plain text
     *
     * @param string $html HTML content
     * @return string Plain text
     */
    private function html_to_text($html)
    {
        // Remove scripts and styles
        $text = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $text = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $text);

        // Convert some elements to text
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        $text = preg_replace('/<\/h[1-6]>/i', "\n\n", $text);
        $text = preg_replace('/<\/li>/i', "\n", $text);
        $text = preg_replace('/<\/tr>/i', "\n", $text);
        $text = preg_replace('/<\/td>/i', "\t", $text);

        // Remove all remaining HTML tags
        $text = wp_strip_all_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Clean excessive whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Prepares attachments for the API
     *
     * @param string|array $attachments Attachments
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
     * Gets the MIME type of a file
     *
     * @param string $filepath File path
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
     * Prepares additional headers for the API
     *
     * @param array $parsed_headers Parsed headers
     * @return array
     */
    private function prepare_api_headers(array $parsed_headers)
    {
        $api_headers = array();

        // Add custom headers
        if (!empty($parsed_headers['custom'])) {
            foreach ($parsed_headers['custom'] as $name => $value) {
                // Capitalize header name
                $formatted_name = implode('-', array_map('ucfirst', explode('-', $name)));
                $api_headers[$formatted_name] = $value;
            }
        }

        return $api_headers;
    }
}
