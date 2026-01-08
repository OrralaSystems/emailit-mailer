<?php
/**
 * Settings management class
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
 * Settings Class
 * 
 * Manages all plugin configuration options
 */
class Settings
{

    /**
     * Option name in database
     *
     * @var string
     */
    const OPTION_NAME = 'emailit_settings';

    /**
     * Option group for Settings API
     *
     * @var string
     */
    const OPTION_GROUP = 'emailit_settings_group';

    /**
     * Loaded options
     *
     * @var array
     */
    private $options = array();

    /**
     * Default options
     *
     * @var array
     */
    private $defaults = array(
        'enabled' => true,
        'api_key' => '',
        'from_email' => '',
        'from_name' => '',
        'force_from' => false,
        'reply_to' => '',
        'enable_logging' => true,
        'log_retention_days' => 30,
        'max_log_entries' => 100,
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->load_options();
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Load options from database
     */
    private function load_options()
    {
        $saved_options = get_option(self::OPTION_NAME, array());
        $this->options = wp_parse_args($saved_options, $this->defaults);
    }

    /**
     * Get a specific option
     *
     * @param string $key Option name
     * @param mixed  $default Default value if not exists
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }

        if (null !== $default) {
            return $default;
        }

        return isset($this->defaults[$key]) ? $this->defaults[$key] : null;
    }

    /**
     * Get all options
     *
     * @return array
     */
    public function get_all()
    {
        return $this->options;
    }

    /**
     * Update a specific option
     *
     * @param string $key   Option name
     * @param mixed  $value Value to save
     * @return bool
     */
    public function set($key, $value)
    {
        $this->options[$key] = $value;
        return update_option(self::OPTION_NAME, $this->options);
    }

    /**
     * Update multiple options
     *
     * @param array $options Options to update
     * @return bool
     */
    public function update($options)
    {
        $this->options = wp_parse_args($options, $this->options);
        return update_option(self::OPTION_NAME, $this->options);
    }

    /**
     * Set default values
     */
    public function set_defaults()
    {
        if (false === get_option(self::OPTION_NAME)) {
            // Try to get admin email as default value
            $admin_email = get_option('admin_email');
            $this->defaults['from_email'] = $admin_email;
            $this->defaults['from_name'] = get_option('blogname', 'WordPress');

            add_option(self::OPTION_NAME, $this->defaults);
            $this->options = $this->defaults;
        }
    }

    /**
     * Register settings with WordPress Settings API
     */
    public function register_settings()
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => $this->defaults,
            )
        );

        // Section: General Status
        add_settings_section(
            'emailit_general_section',
            __('Plugin Status', 'emailit-mailer'),
            array($this, 'render_general_section'),
            'emailit-settings'
        );

        // Field: Enable Plugin
        add_settings_field(
            'enabled',
            __('Enable EmailIT Mailer', 'emailit-mailer'),
            array($this, 'render_enabled_field'),
            'emailit-settings',
            'emailit_general_section'
        );

        // Section: API Authentication
        add_settings_section(
            'emailit_api_section',
            __('API Configuration', 'emailit-mailer'),
            array($this, 'render_api_section'),
            'emailit-settings'
        );

        // Field: API Key
        add_settings_field(
            'api_key',
            __('EmailIT API Key', 'emailit-mailer'),
            array($this, 'render_api_key_field'),
            'emailit-settings',
            'emailit_api_section'
        );

        // Section: Sender
        add_settings_section(
            'emailit_sender_section',
            __('Sender Configuration', 'emailit-mailer'),
            array($this, 'render_sender_section'),
            'emailit-settings'
        );

        // Field: Sender Email
        add_settings_field(
            'from_email',
            __('From Email', 'emailit-mailer'),
            array($this, 'render_from_email_field'),
            'emailit-settings',
            'emailit_sender_section'
        );

        // Field: Sender Name
        add_settings_field(
            'from_name',
            __('From Name', 'emailit-mailer'),
            array($this, 'render_from_name_field'),
            'emailit-settings',
            'emailit_sender_section'
        );

        // Field: Force Sender
        add_settings_field(
            'force_from',
            __('Force Sender', 'emailit-mailer'),
            array($this, 'render_force_from_field'),
            'emailit-settings',
            'emailit_sender_section'
        );

        // Field: Reply-To
        add_settings_field(
            'reply_to',
            __('Reply-To Email', 'emailit-mailer'),
            array($this, 'render_reply_to_field'),
            'emailit-settings',
            'emailit_sender_section'
        );

        // Section: Logs
        add_settings_section(
            'emailit_logs_section',
            __('Logging Configuration', 'emailit-mailer'),
            array($this, 'render_logs_section'),
            'emailit-settings'
        );

        // Field: Enable Logs
        add_settings_field(
            'enable_logging',
            __('Enable Logging', 'emailit-mailer'),
            array($this, 'render_enable_logging_field'),
            'emailit-settings',
            'emailit_logs_section'
        );

        // Field: Retention Days
        add_settings_field(
            'log_retention_days',
            __('Retention Days', 'emailit-mailer'),
            array($this, 'render_log_retention_field'),
            'emailit-settings',
            'emailit_logs_section'
        );

        // Field: Maximum Entries
        add_settings_field(
            'max_log_entries',
            __('Maximum Entries', 'emailit-mailer'),
            array($this, 'render_max_log_entries_field'),
            'emailit-settings',
            'emailit_logs_section'
        );
    }

    /**
     * Sanitize settings before saving
     *
     * @param array $input Input data
     * @return array Sanitized data
     */
    public function sanitize_settings($input)
    {
        $sanitized = array();

        // Enable plugin
        $sanitized['enabled'] = isset($input['enabled']) && $input['enabled'] ? true : false;

        // API Key - keep previous value if empty (to not lose it)
        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($input['api_key']);
            if (empty($sanitized['api_key'])) {
                $sanitized['api_key'] = $this->get('api_key');
            }
        }

        // Sender Email
        if (isset($input['from_email'])) {
            $sanitized['from_email'] = sanitize_email($input['from_email']);
        }

        // Sender Name
        if (isset($input['from_name'])) {
            $sanitized['from_name'] = sanitize_text_field($input['from_name']);
        }

        // Force Sender
        $sanitized['force_from'] = isset($input['force_from']) && $input['force_from'] ? true : false;

        // Reply-To
        if (isset($input['reply_to'])) {
            $sanitized['reply_to'] = sanitize_email($input['reply_to']);
        }

        // Enable Logs
        $sanitized['enable_logging'] = isset($input['enable_logging']) && $input['enable_logging'] ? true : false;

        // Retention Days
        if (isset($input['log_retention_days'])) {
            $sanitized['log_retention_days'] = absint($input['log_retention_days']);
        }

        // Maximum Entries
        if (isset($input['max_log_entries'])) {
            $sanitized['max_log_entries'] = absint($input['max_log_entries']);
            if ($sanitized['max_log_entries'] < 10) {
                $sanitized['max_log_entries'] = 10;
            }
            if ($sanitized['max_log_entries'] > 1000) {
                $sanitized['max_log_entries'] = 1000;
            }
        }

        return $sanitized;
    }

    /**
     * Render General section description
     */
    public function render_general_section()
    {
        echo '<p>' . esc_html__('Control the plugin status. When disabled, WordPress will use its default email sending method.', 'emailit-mailer') . '</p>';
    }

    /**
     * Render Enable Plugin field
     */
    public function render_enabled_field()
    {
        $enabled = $this->get('enabled');
        ?>
        <label class="emailit-toggle-switch">
            <input type="checkbox" id="emailit_enabled" name="<?php echo esc_attr(self::OPTION_NAME); ?>[enabled]" value="1"
                <?php checked($enabled, true); ?>>
            <span class="emailit-toggle-slider"></span>
        </label>
        <span class="emailit-toggle-label <?php echo $enabled ? 'enabled' : 'disabled'; ?>" id="emailit_enabled_label">
            <?php echo $enabled ? esc_html__('Active', 'emailit-mailer') : esc_html__('Inactive', 'emailit-mailer'); ?>
        </span>
        <p class="description">
            <?php esc_html_e('When active, all WordPress emails will be sent through EmailIT. If disabled, the default WordPress sending method will be used.', 'emailit-mailer'); ?>
        </p>
        <?php
    }

    /**
     * Render API section description
     */
    public function render_api_section()
    {
        echo '<p>' . esc_html__('Configure your EmailIT API key. You can get it from your control panel at emailit.com.', 'emailit-mailer') . '</p>';
    }

    /**
     * Render API Key field
     */
    public function render_api_key_field()
    {
        $api_key = $this->get('api_key');
        $masked_key = !empty($api_key) ? str_repeat('•', 20) . substr($api_key, -4) : '';
        ?>
        <input type="password" id="emailit_api_key" name="<?php echo esc_attr(self::OPTION_NAME); ?>[api_key]"
            value="<?php echo esc_attr($api_key); ?>" class="regular-text" autocomplete="off"
            placeholder="<?php echo esc_attr($masked_key ?: __('Enter your API Key', 'emailit-mailer')); ?>">
        <button type="button" class="button button-secondary" id="emailit-toggle-api-key">
            <?php esc_html_e('Show', 'emailit-mailer'); ?>
        </button>
        <p class="description">
            <?php
            printf(
                /* translators: %s: URL to EmailIT dashboard */
                esc_html__('Get your API Key from %s', 'emailit-mailer'),
                '<a href="https://emailit.com" target="_blank" rel="noopener noreferrer">EmailIT Dashboard</a>'
            );
            ?>
        </p>
        <?php
    }

    /**
     * Render Sender section description
     */
    public function render_sender_section()
    {
        echo '<p>' . esc_html__('Configure the default sender for all emails sent from WordPress.', 'emailit-mailer') . '</p>';
    }

    /**
     * Render From Email field
     */
    public function render_from_email_field()
    {
        ?>
        <input type="email" id="emailit_from_email" name="<?php echo esc_attr(self::OPTION_NAME); ?>[from_email]"
            value="<?php echo esc_attr($this->get('from_email')); ?>" class="regular-text"
            placeholder="email@yourdomain.com">
        <p class="description">
            <?php esc_html_e('Email address from which all emails will be sent. Must be verified in EmailIT.', 'emailit-mailer'); ?>
        </p>
        <?php
    }

    /**
     * Render From Name field
     */
    public function render_from_name_field()
    {
        ?>
        <input type="text" id="emailit_from_name" name="<?php echo esc_attr(self::OPTION_NAME); ?>[from_name]"
            value="<?php echo esc_attr($this->get('from_name')); ?>" class="regular-text"
            placeholder="<?php echo esc_attr(get_option('blogname')); ?>">
        <p class="description">
            <?php esc_html_e('Name that will appear as the email sender.', 'emailit-mailer'); ?>
        </p>
        <?php
    }

    /**
     * Render Force Sender field
     */
    public function render_force_from_field()
    {
        ?>
        <label for="emailit_force_from">
            <input type="checkbox" id="emailit_force_from" name="<?php echo esc_attr(self::OPTION_NAME); ?>[force_from]"
                value="1" <?php checked($this->get('force_from'), true); ?>>
            <?php esc_html_e('Force configured sender on all emails', 'emailit-mailer'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('If enabled, all emails will use the sender configured above, ignoring the sender specified by other plugins.', 'emailit-mailer'); ?>
        </p>
        <?php
    }

    /**
     * Render Reply-To field
     */
    public function render_reply_to_field()
    {
        ?>
        <input type="email" id="emailit_reply_to" name="<?php echo esc_attr(self::OPTION_NAME); ?>[reply_to]"
            value="<?php echo esc_attr($this->get('reply_to')); ?>" class="regular-text"
            placeholder="<?php esc_attr_e('Optional - Leave empty to use sender email', 'emailit-mailer'); ?>">
        <p class="description">
            <?php esc_html_e('Email address where replies will be received. If empty, the sender email will be used.', 'emailit-mailer'); ?>
        </p>
        <?php
    }

    /**
     * Render Logs section description
     */
    public function render_logs_section()
    {
        echo '<p>' . esc_html__('Configure email logging. Logs help diagnose delivery issues.', 'emailit-mailer') . '</p>';
    }

    /**
     * Render Enable Logging field
     */
    public function render_enable_logging_field()
    {
        ?>
        <label for="emailit_enable_logging">
            <input type="checkbox" id="emailit_enable_logging"
                name="<?php echo esc_attr(self::OPTION_NAME); ?>[enable_logging]" value="1" <?php checked($this->get('enable_logging'), true); ?>>
            <?php esc_html_e('Log sent emails', 'emailit-mailer'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Enable logging of all sent emails with their status (sent/failed).', 'emailit-mailer'); ?>
        </p>
        <?php
    }

    /**
     * Render Retention Days field
     */
    public function render_log_retention_field()
    {
        ?>
        <input type="number" id="emailit_log_retention_days"
            name="<?php echo esc_attr(self::OPTION_NAME); ?>[log_retention_days]"
            value="<?php echo esc_attr($this->get('log_retention_days')); ?>" class="small-text" min="0" max="365">
        <span><?php esc_html_e('days', 'emailit-mailer'); ?></span>
        <p class="description">
            <?php esc_html_e('Number of days to keep logs. Enter 0 to keep them indefinitely.', 'emailit-mailer'); ?>
        </p>
        <?php
    }

    /**
     * Render Maximum Entries field
     */
    public function render_max_log_entries_field()
    {
        ?>
        <input type="number" id="emailit_max_log_entries" name="<?php echo esc_attr(self::OPTION_NAME); ?>[max_log_entries]"
            value="<?php echo esc_attr($this->get('max_log_entries')); ?>" class="small-text" min="10" max="1000">
        <span><?php esc_html_e('entries', 'emailit-mailer'); ?></span>
        <p class="description">
            <?php esc_html_e('Maximum number of log entries to keep. Older logs will be automatically deleted.', 'emailit-mailer'); ?>
        </p>
        <?php
    }

    /**
     * Check if the plugin is enabled
     *
     * @return bool
     */
    public function is_enabled()
    {
        return (bool) $this->get('enabled', true);
    }

    /**
     * Check if the plugin is properly configured
     *
     * @return bool
     */
    public function is_configured()
    {
        return !empty($this->get('api_key')) && !empty($this->get('from_email'));
    }

    /**
     * Get configuration errors
     *
     * @return array
     */
    public function get_configuration_errors()
    {
        $errors = array();

        if (empty($this->get('api_key'))) {
            $errors[] = __('EmailIT API Key is not configured.', 'emailit-mailer');
        }

        if (empty($this->get('from_email'))) {
            $errors[] = __('Sender email is not configured.', 'emailit-mailer');
        }

        return $errors;
    }
}
