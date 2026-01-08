<?php
/**
 * Plugin administration class
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
 * Admin Class
 * 
 * Manages the plugin admin pages
 */
class Admin
{

    /**
     * Settings page slug
     *
     * @var string
     */
    const SETTINGS_SLUG = 'emailit-settings';

    /**
     * Logs page slug
     *
     * @var string
     */
    const LOGS_SLUG = 'emailit-logs';

    /**
     * Settings instance
     *
     * @var Settings
     */
    private $settings;

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
     * @param Logger   $logger   Logger instance
     */
    public function __construct(Settings $settings, Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;

        $this->init_hooks();
    }

    /**
     * Initialize admin hooks
     */
    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_notices', array($this, 'admin_notices'));

        // AJAX handlers
        add_action('wp_ajax_emailit_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_emailit_clear_logs', array($this, 'ajax_clear_logs'));
    }

    /**
     * Adds admin menu pages
     */
    public function add_admin_menu()
    {
        // Main settings page
        add_options_page(
            __('EmailIT Mailer', 'emailit-mailer'),
            __('EmailIT Mailer', 'emailit-mailer'),
            'manage_options',
            self::SETTINGS_SLUG,
            array($this, 'render_settings_page')
        );

        // Logs subpage
        add_options_page(
            __('EmailIT Logs', 'emailit-mailer'),
            __('EmailIT Logs', 'emailit-mailer'),
            'manage_options',
            self::LOGS_SLUG,
            array($this, 'render_logs_page')
        );
    }

    /**
     * Enqueues admin assets
     *
     * @param string $hook Current page hook
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on our pages
        if (!in_array($hook, array('settings_page_' . self::SETTINGS_SLUG, 'settings_page_' . self::LOGS_SLUG), true)) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'emailit-admin',
            EMAILIT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            EMAILIT_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'emailit-admin',
            EMAILIT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            EMAILIT_VERSION,
            true
        );

        // Data for JavaScript
        wp_localize_script('emailit-admin', 'emailitAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('emailit_admin_nonce'),
            'strings' => array(
                'testing' => __('Sending test email...', 'emailit-mailer'),
                'success' => __('Email sent successfully!', 'emailit-mailer'),
                'error' => __('Error sending email:', 'emailit-mailer'),
                'clearing' => __('Clearing logs...', 'emailit-mailer'),
                'cleared' => __('Logs deleted successfully.', 'emailit-mailer'),
                'clearError' => __('Error deleting logs.', 'emailit-mailer'),
                'confirmClear' => __('Are you sure you want to delete all logs? This action cannot be undone.', 'emailit-mailer'),
                'enterEmail' => __('Please enter a destination email for the test.', 'emailit-mailer'),
            ),
        ));
    }

    /**
     * Displays admin notices
     */
    public function admin_notices()
    {
        // Only show on our pages or plugins list
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('settings_page_' . self::SETTINGS_SLUG, 'plugins'), true)) {
            return;
        }

        // Check if plugin is configured
        $errors = $this->settings->get_configuration_errors();
        if (!empty($errors) && current_user_can('manage_options')) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . esc_html__('EmailIT Mailer requires configuration:', 'emailit-mailer') . '</strong></p>';
            echo '<ul style="list-style: disc; margin-left: 20px;">';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul>';
            echo '<p><a href="' . esc_url(admin_url('options-general.php?page=' . self::SETTINGS_SLUG)) . '" class="button">';
            echo esc_html__('Configure Now', 'emailit-mailer');
            echo '</a></p>';
            echo '</div>';
        }
    }

    /**
     * Renders the settings page
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if options were saved
        $settings_updated = isset($_GET['settings-updated']) && 'true' === $_GET['settings-updated'];

        // Get statistics
        $stats = $this->logger->get_stats();
        ?>
        <div class="wrap emailit-admin-wrap">
            <h1>
                <span class="dashicons dashicons-email"
                    style="font-size: 30px; width: 30px; height: 30px; margin-right: 10px;"></span>
                <?php echo esc_html__('EmailIT Mailer', 'emailit-mailer'); ?>
            </h1>

            <p class="description" style="font-size: 14px; margin-bottom: 20px;">
                <?php echo esc_html__('Plugin developed by Orrala Systems', 'emailit-mailer'); ?>
            </p>

            <?php if ($settings_updated): ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php echo esc_html__('Settings saved successfully.', 'emailit-mailer'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="emailit-admin-container">
                <!-- Main settings panel -->
                <div class="emailit-main-content">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields(Settings::OPTION_GROUP);
                        do_settings_sections('emailit-settings');
                        submit_button(__('Save Settings', 'emailit-mailer'));
                        ?>
                    </form>

                    <!-- Test email section -->
                    <div class="emailit-card" style="margin-top: 30px;">
                        <h2>
                            <?php echo esc_html__('Send Test Email', 'emailit-mailer'); ?>
                        </h2>
                        <p class="description">
                            <?php echo esc_html__('Send a test email to verify that the configuration is correct.', 'emailit-mailer'); ?>
                        </p>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="emailit_test_email">
                                        <?php echo esc_html__('Destination Email', 'emailit-mailer'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="email" id="emailit_test_email" class="regular-text"
                                        value="<?php echo esc_attr(get_option('admin_email')); ?>"
                                        placeholder="test@example.com">
                                    <p class="description">
                                        <?php echo esc_html__('Address where the test email will be sent.', 'emailit-mailer'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p>
                            <button type="button" id="emailit_test_button" class="button button-secondary">
                                <span class="dashicons dashicons-email-alt" style="margin-top: 4px;"></span>
                                <?php echo esc_html__('Send Test Email', 'emailit-mailer'); ?>
                            </button>
                            <span id="emailit_test_result" style="margin-left: 10px;"></span>
                        </p>
                    </div>
                </div>

                <!-- Sidebar with statistics -->
                <div class="emailit-sidebar">
                    <div class="emailit-card emailit-stats-card">
                        <h3>
                            <?php echo esc_html__('Sending Statistics', 'emailit-mailer'); ?>
                        </h3>

                        <div class="emailit-stat">
                            <span class="emailit-stat-number">
                                <?php echo esc_html(number_format_i18n($stats['total'])); ?>
                            </span>
                            <span class="emailit-stat-label">
                                <?php echo esc_html__('Total Emails', 'emailit-mailer'); ?>
                            </span>
                        </div>

                        <div class="emailit-stat emailit-stat-success">
                            <span class="emailit-stat-number">
                                <?php echo esc_html(number_format_i18n($stats['sent'])); ?>
                            </span>
                            <span class="emailit-stat-label">
                                <?php echo esc_html__('Sent', 'emailit-mailer'); ?>
                            </span>
                        </div>

                        <div class="emailit-stat emailit-stat-error">
                            <span class="emailit-stat-number">
                                <?php echo esc_html(number_format_i18n($stats['failed'])); ?>
                            </span>
                            <span class="emailit-stat-label">
                                <?php echo esc_html__('Failed', 'emailit-mailer'); ?>
                            </span>
                        </div>

                        <div class="emailit-stat">
                            <span class="emailit-stat-number">
                                <?php echo esc_html(number_format_i18n($stats['last_24h'])); ?>
                            </span>
                            <span class="emailit-stat-label">
                                <?php echo esc_html__('Last 24h', 'emailit-mailer'); ?>
                            </span>
                        </div>

                        <p style="margin-top: 15px;">
                            <a href="<?php echo esc_url(admin_url('options-general.php?page=' . self::LOGS_SLUG)); ?>"
                                class="button button-secondary">
                                <?php echo esc_html__('View Logs', 'emailit-mailer'); ?>
                            </a>
                        </p>
                    </div>

                    <div class="emailit-card">
                        <h3>
                            <?php echo esc_html__('Useful Links', 'emailit-mailer'); ?>
                        </h3>
                        <ul class="emailit-links-list">
                            <li>
                                <a href="https://emailit.com" target="_blank" rel="noopener noreferrer">
                                    <span class="dashicons dashicons-external"></span>
                                    <?php echo esc_html__('EmailIT Dashboard', 'emailit-mailer'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="https://docs.emailit.com" target="_blank" rel="noopener noreferrer">
                                    <span class="dashicons dashicons-book"></span>
                                    <?php echo esc_html__('API Documentation', 'emailit-mailer'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="https://orralasystems.com" target="_blank" rel="noopener noreferrer">
                                    <span class="dashicons dashicons-admin-home"></span>
                                    <?php echo esc_html__('Orrala Systems', 'emailit-mailer'); ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renders the logs page
     */
    public function render_logs_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get pagination and filter parameters
        $per_page = 20;
        $page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        // Get logs
        $logs_data = $this->logger->get_logs(array(
            'per_page' => $per_page,
            'page' => $page,
            'status' => $status,
            'search' => $search,
        ));

        $logs = $logs_data['items'];
        $total = $logs_data['total'];
        $total_pages = ceil($total / $per_page);

        // Statistics
        $stats = $this->logger->get_stats();
        ?>
        <div class="wrap emailit-admin-wrap">
            <h1>
                <span class="dashicons dashicons-list-view"
                    style="font-size: 30px; width: 30px; height: 30px; margin-right: 10px;"></span>
                <?php echo esc_html__('Email Logs', 'emailit-mailer'); ?>
            </h1>

            <!-- Filters -->
            <div class="emailit-logs-filters">
                <form method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::LOGS_SLUG); ?>">

                    <select name="status">
                        <option value="">
                            <?php echo esc_html__('All statuses', 'emailit-mailer'); ?>
                        </option>
                        <option value="sent" <?php selected($status, 'sent'); ?>>
                            <?php echo esc_html__('Sent', 'emailit-mailer'); ?>
                        </option>
                        <option value="failed" <?php selected($status, 'failed'); ?>>
                            <?php echo esc_html__('Failed', 'emailit-mailer'); ?>
                        </option>
                    </select>

                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>"
                        placeholder="<?php echo esc_attr__('Search by email or subject...', 'emailit-mailer'); ?>"
                        class="regular-text">

                    <?php submit_button(__('Filter', 'emailit-mailer'), 'secondary', 'filter', false); ?>

                    <?php if (!empty($status) || !empty($search)): ?>
                        <a href="<?php echo esc_url(admin_url('options-general.php?page=' . self::LOGS_SLUG)); ?>"
                            class="button">
                            <?php echo esc_html__('Clear Filters', 'emailit-mailer'); ?>
                        </a>
                    <?php endif; ?>
                </form>

                <div class="emailit-logs-actions">
                    <button type="button" id="emailit_clear_logs" class="button button-link-delete">
                        <span class="dashicons dashicons-trash" style="margin-top: 4px;"></span>
                        <?php echo esc_html__('Delete All Logs', 'emailit-mailer'); ?>
                    </button>
                </div>
            </div>

            <!-- Summary -->
            <div class="emailit-logs-summary">
                <span class="emailit-badge">
                    <?php echo esc_html(sprintf(__('Total: %d', 'emailit-mailer'), $stats['total'])); ?>
                </span>
                <span class="emailit-badge emailit-badge-success">
                    <?php echo esc_html(sprintf(__('Sent: %d', 'emailit-mailer'), $stats['sent'])); ?>
                </span>
                <span class="emailit-badge emailit-badge-error">
                    <?php echo esc_html(sprintf(__('Failed: %d', 'emailit-mailer'), $stats['failed'])); ?>
                </span>
            </div>

            <!-- Logs table -->
            <table class="wp-list-table widefat fixed striped emailit-logs-table">
                <thead>
                    <tr>
                        <th scope="col" class="column-id" style="width: 60px;">
                            <?php echo esc_html__('ID', 'emailit-mailer'); ?>
                        </th>
                        <th scope="col" class="column-date" style="width: 150px;">
                            <?php echo esc_html__('Date', 'emailit-mailer'); ?>
                        </th>
                        <th scope="col" class="column-to">
                            <?php echo esc_html__('Recipient', 'emailit-mailer'); ?>
                        </th>
                        <th scope="col" class="column-subject">
                            <?php echo esc_html__('Subject', 'emailit-mailer'); ?>
                        </th>
                        <th scope="col" class="column-status" style="width: 100px;">
                            <?php echo esc_html__('Status', 'emailit-mailer'); ?>
                        </th>
                        <th scope="col" class="column-response">
                            <?php echo esc_html__('Response', 'emailit-mailer'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">
                                <?php echo esc_html__('No records found.', 'emailit-mailer'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="column-id">
                                    <?php echo esc_html($log->id); ?>
                                </td>
                                <td class="column-date">
                                    <?php
                                    $timestamp = strtotime($log->created_at);
                                    echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp));
                                    ?>
                                </td>
                                <td class="column-to">
                                    <code><?php echo esc_html($log->to_email); ?></code>
                                </td>
                                <td class="column-subject">
                                    <?php echo esc_html($log->subject); ?>
                                </td>
                                <td class="column-status">
                                    <?php if ('sent' === $log->status): ?>
                                        <span class="emailit-status emailit-status-success">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <?php echo esc_html__('Sent', 'emailit-mailer'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="emailit-status emailit-status-error">
                                            <span class="dashicons dashicons-warning"></span>
                                            <?php echo esc_html__('Failed', 'emailit-mailer'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-response">
                                    <span class="emailit-response-text">
                                        <?php echo esc_html(wp_trim_words($log->response, 10)); ?>
                                    </span>
                                    <?php if (strlen($log->response) > 80): ?>
                                        <button type="button" class="button-link emailit-view-response"
                                            data-response="<?php echo esc_attr($log->response); ?>">
                                            <?php echo esc_html__('View more', 'emailit-mailer'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php echo esc_html(sprintf(_n('%s item', '%s items', $total, 'emailit-mailer'), number_format_i18n($total))); ?>
                        </span>
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $page,
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Modal for viewing complete response -->
        <div id="emailit-response-modal" class="emailit-modal" style="display: none;">
            <div class="emailit-modal-content">
                <span class="emailit-modal-close">&times;</span>
                <h3>
                    <?php echo esc_html__('Complete Response', 'emailit-mailer'); ?>
                </h3>
                <pre id="emailit-modal-response"></pre>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for connection test
     */
    public function ajax_test_connection()
    {
        // Verify nonce
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'emailit-mailer')));
        }

        // Get test email
        $test_email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

        if (empty($test_email) || !is_email($test_email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'emailit-mailer')));
        }

        // Get API instance
        $plugin = emailit_mailer();
        $result = $plugin->api->test_connection($test_email);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Test email sent successfully! Check your inbox.', 'emailit-mailer')));
    }

    /**
     * AJAX handler for clearing logs
     */
    public function ajax_clear_logs()
    {
        // Verify nonce
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'emailit-mailer')));
        }

        $result = $this->logger->clear_all_logs();

        if (false === $result) {
            wp_send_json_error(array('message' => __('Error deleting logs.', 'emailit-mailer')));
        }

        wp_send_json_success(array('message' => __('All logs have been deleted.', 'emailit-mailer')));
    }
}
