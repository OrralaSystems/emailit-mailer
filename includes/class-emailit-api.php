<?php
/**
 * EmailIT API Client Class
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
 * API Class
 * 
 * Handles all communications with the EmailIT API
 */
class API
{

    /**
     * API Endpoint
     *
     * @var string
     */
    const ENDPOINT = 'https://api.emailit.com/v1/emails';

    /**
     * Settings instance
     *
     * @var Settings
     */
    private $settings;

    /**
     * Last error occurred
     *
     * @var string
     */
    private $last_error = '';

    /**
     * Last API response
     *
     * @var array
     */
    private $last_response = array();

    /**
     * Constructor
     *
     * @param Settings $settings Settings instance
     */
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Sends an email through the EmailIT API
     *
     * @param array $email_data Email data
     * @return bool|array True if sending was successful, array with error otherwise
     */
    public function send_email(array $email_data)
    {
        $api_key = $this->settings->get('api_key');

        if (empty($api_key)) {
            $this->last_error = __('API Key not configured', 'emailit-mailer');
            return $this->create_error('api_key_missing', $this->last_error);
        }

        // Validate required fields
        $validation = $this->validate_email_data($email_data);
        if (is_wp_error($validation)) {
            $this->last_error = $validation->get_error_message();
            return $validation;
        }

        // Prepare the payload
        $payload = $this->prepare_payload($email_data);

        // Make the request
        $response = $this->make_request($payload, $api_key);

        return $response;
    }

    /**
     * Validates email data
     *
     * @param array $email_data Email data
     * @return true|\WP_Error True if valid, WP_Error if there are errors
     */
    private function validate_email_data(array $email_data)
    {
        $required_fields = array('from', 'to', 'subject');

        foreach ($required_fields as $field) {
            if (empty($email_data[$field])) {
                return $this->create_error(
                    'missing_field',
                    sprintf(
                        /* translators: %s: field name */
                        __('The "%s" field is required.', 'emailit-mailer'),
                        $field
                    )
                );
            }
        }

        // Must have HTML or plain text content
        if (empty($email_data['html']) && empty($email_data['text'])) {
            return $this->create_error(
                'missing_content',
                __('The email must have HTML or plain text content.', 'emailit-mailer')
            );
        }

        // Validate email format
        if (!$this->is_valid_email_format($email_data['from'])) {
            return $this->create_error(
                'invalid_from',
                __('The sender email format is not valid.', 'emailit-mailer')
            );
        }

        if (!$this->is_valid_email_format($email_data['to'])) {
            return $this->create_error(
                'invalid_to',
                __('The recipient email format is not valid.', 'emailit-mailer')
            );
        }

        return true;
    }

    /**
     * Validates email format (can include name)
     *
     * @param string $email Email to validate
     * @return bool
     */
    private function is_valid_email_format($email)
    {
        // Format: "Name <email@domain.com>" or "email@domain.com"
        if (preg_match('/<([^>]+)>/', $email, $matches)) {
            $email = $matches[1];
        }
        return is_email(trim($email));
    }

    /**
     * Prepares the payload for the API
     *
     * @param array $email_data Email data
     * @return array Prepared payload
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

        // HTML content
        if (!empty($email_data['html'])) {
            $payload['html'] = $email_data['html'];
        }

        // Plain text content
        if (!empty($email_data['text'])) {
            $payload['text'] = $email_data['text'];
        }

        // Additional headers
        if (!empty($email_data['headers']) && is_array($email_data['headers'])) {
            $payload['headers'] = $email_data['headers'];
        }

        // Attachments
        if (!empty($email_data['attachments']) && is_array($email_data['attachments'])) {
            $payload['attachments'] = $this->prepare_attachments($email_data['attachments']);
        }

        /**
         * Filter to modify the payload before sending
         *
         * @param array $payload Prepared payload
         * @param array $email_data Original email data
         */
        return apply_filters('emailit_api_payload', $payload, $email_data);
    }

    /**
     * Prepares attachments for the API
     *
     * @param array $attachments List of file attachments
     * @return array Prepared attachments
     */
    private function prepare_attachments(array $attachments)
    {
        $prepared = array();

        foreach ($attachments as $attachment) {
            // If it's a file path
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
            // If it's already an array with attachment data
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
     * Gets the MIME type of a file
     *
     * @param string $filepath File path
     * @return string MIME type
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

        // Fallback using PHP function if available
        if (function_exists('mime_content_type')) {
            return mime_content_type($filepath);
        }

        return 'application/octet-stream';
    }

    /**
     * Makes the HTTP request to the API
     *
     * @param array  $payload Data to send
     * @param string $api_key API key
     * @return bool|\WP_Error True if successful, WP_Error if failed
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
         * Filter to modify HTTP request arguments
         *
         * @param array $args Arguments for wp_remote_post
         * @param array $payload Email payload
         */
        $args = apply_filters('emailit_api_request_args', $args, $payload);

        $response = wp_remote_post(self::ENDPOINT, $args);

        // Connection error
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

        // Check response code (2xx = success)
        if ($response_code >= 200 && $response_code < 300) {
            $this->last_error = '';

            /**
             * Action after successful send
             *
             * @param array $payload Sent payload
             * @param array $response API response
             */
            do_action('emailit_email_sent', $payload, $this->last_response);

            return true;
        }

        // API error
        $error_message = isset($decoded_body['message'])
            ? $decoded_body['message']
            : sprintf(
                /* translators: %d: HTTP error code */
                __('API error (code %d)', 'emailit-mailer'),
                $response_code
            );

        $this->last_error = $error_message;

        /**
         * Action after send error
         *
         * @param array $payload Sent payload
         * @param array $response API response
         * @param string $error_message Error message
         */
        do_action('emailit_email_failed', $payload, $this->last_response, $error_message);

        return $this->create_error('api_error', $error_message);
    }

    /**
     * Creates a WP_Error object
     *
     * @param string $code Error code
     * @param string $message Error message
     * @return \WP_Error
     */
    private function create_error(string $code, string $message)
    {
        return new \WP_Error('emailit_' . $code, $message);
    }

    /**
     * Gets the last error
     *
     * @return string
     */
    public function get_last_error()
    {
        return $this->last_error;
    }

    /**
     * Gets the last response
     *
     * @return array
     */
    public function get_last_response()
    {
        return $this->last_response;
    }

    /**
     * Tests the connection with the API
     *
     * @param string $test_email Destination email for testing
     * @return bool|\WP_Error True if test was successful
     */
    public function test_connection(string $test_email)
    {
        $from_email = $this->settings->get('from_email');
        $from_name = $this->settings->get('from_name');

        if (empty($from_email)) {
            return $this->create_error('missing_from', __('Please configure the sender email first.', 'emailit-mailer'));
        }

        $from = !empty($from_name)
            ? sprintf('%s <%s>', $from_name, $from_email)
            : $from_email;

        $email_data = array(
            'from' => $from,
            'to' => $test_email,
            'subject' => sprintf(
                /* translators: %s: site name */
                __('[Test] EmailIT Mailer - %s', 'emailit-mailer'),
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
     * Generates the HTML content for test email
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
                    <p style="margin: 10px 0 0 0; opacity: 0.9;">Connection Test Successful</p>
                </div>
                <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-top: none; padding: 30px; border-radius: 0 0 10px 10px;">
                    <h2 style="color: #1f2937; margin-top: 0;">Congratulations! 🎉</h2>
                    <p style="color: #4b5563; line-height: 1.6;">
                        This email confirms that the <strong>EmailIT Mailer</strong> plugin is correctly configured 
                        and connected to the EmailIT API on your site <strong>%2$s</strong>.
                    </p>
                    <p style="color: #4b5563; line-height: 1.6;">
                        All WordPress emails will now be sent through EmailIT, 
                        improving the deliverability of your messages.
                    </p>
                    <div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
                        <strong style="color: #065f46;">Status:</strong>
                        <span style="color: #047857;">Connection established successfully</span>
                    </div>
                    <p style="color: #6b7280; font-size: 14px; margin-bottom: 0;">
                        — Plugin developed by <a href="https://orralasystems.com" style="color: #667eea;">Orrala Systems</a>
                    </p>
                </div>
            </body>
            </html>',
            esc_html__('EmailIT Mailer Test', 'emailit-mailer'),
            esc_html($site_name)
        );
    }

    /**
     * Generates the plain text content for test email
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
            __('✉️ EmailIT Mailer - Connection Test', 'emailit-mailer'),
            sprintf(
                /* translators: %s: site name */
                __('Congratulations! This email confirms that the EmailIT Mailer plugin is correctly configured on your site %s.', 'emailit-mailer'),
                $site_name
            ),
            __('All WordPress emails will now be sent through EmailIT.', 'emailit-mailer'),
            __('Plugin developed by Orrala Systems', 'emailit-mailer')
        );
    }
}
