<?php
/**
 * Email logging class
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
 * Logger Class
 * 
 * Manages email logging in the database
 */
class Logger
{

    /**
     * Logs table name (without prefix)
     *
     * @var string
     */
    const TABLE_NAME = 'emailit_logs';

    /**
     * Settings instance
     *
     * @var Settings
     */
    private $settings;

    /**
     * Full table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Constructor
     *
     * @param Settings $settings Settings instance
     */
    public function __construct(Settings $settings)
    {
        global $wpdb;

        $this->settings = $settings;
        $this->table_name = $wpdb->prefix . self::TABLE_NAME;

        // Schedule automatic cleanup
        $this->schedule_cleanup();
    }

    /**
     * Creates the logs table in the database
     */
    public function create_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            to_email VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            status ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
            response TEXT NULL,
            headers TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_to_email (to_email(191))
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Save schema version
        update_option('emailit_db_version', EMAILIT_VERSION);
    }

    /**
     * Drops the logs table
     */
    public function drop_table()
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
    }

    /**
     * Logs an email send
     *
     * @param string $to_email   Recipient email
     * @param string $subject    Email subject
     * @param string $status     Status: 'sent' or 'failed'
     * @param string $response   API response or error message
     * @param array  $headers    Email headers (optional)
     * @return int|false Inserted record ID or false if failed
     */
    public function log(string $to_email, string $subject, string $status, string $response = '', array $headers = array())
    {
        // Check if logging is enabled
        if (!$this->settings->get('enable_logging')) {
            return false;
        }

        global $wpdb;

        $data = array(
            'to_email' => sanitize_email($to_email),
            'subject' => sanitize_text_field(substr($subject, 0, 255)),
            'status' => in_array($status, array('sent', 'failed'), true) ? $status : 'failed',
            'response' => sanitize_textarea_field($response),
            'headers' => !empty($headers) ? wp_json_encode($headers) : null,
            'created_at' => current_time('mysql'),
        );

        $format = array('%s', '%s', '%s', '%s', '%s', '%s');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert($this->table_name, $data, $format);

        if (false === $result) {
            return false;
        }

        $log_id = $wpdb->insert_id;

        // Cleanup old logs if exceeding maximum
        $this->maybe_cleanup_excess_logs();

        return $log_id;
    }

    /**
     * Logs a successful send
     *
     * @param string $to_email Recipient email
     * @param string $subject  Email subject
     * @param array  $response API response
     * @param array  $headers  Email headers
     * @return int|false
     */
    public function log_success(string $to_email, string $subject, array $response = array(), array $headers = array())
    {
        $response_text = !empty($response) ? wp_json_encode($response) : __('Sent successfully', 'emailit-mailer');
        return $this->log($to_email, $subject, 'sent', $response_text, $headers);
    }

    /**
     * Logs a failed send
     *
     * @param string $to_email      Recipient email
     * @param string $subject       Email subject
     * @param string $error_message Error message
     * @param array  $headers       Email headers
     * @return int|false
     */
    public function log_failure(string $to_email, string $subject, string $error_message, array $headers = array())
    {
        return $this->log($to_email, $subject, 'failed', $error_message, $headers);
    }

    /**
     * Gets logs with pagination
     *
     * @param array $args Query arguments
     * @return array Array with 'items' and 'total'
     */
    public function get_logs(array $args = array())
    {
        global $wpdb;

        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);

        // Sanitize order parameters
        $allowed_orderby = array('id', 'to_email', 'subject', 'status', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';
        $order = 'ASC' === strtoupper($args['order']) ? 'ASC' : 'DESC';

        // Build WHERE clause
        $where_clauses = array('1=1');
        $where_values = array();

        if (!empty($args['status']) && in_array($args['status'], array('sent', 'failed'), true)) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if (!empty($args['search'])) {
            $search_like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_clauses[] = '(to_email LIKE %s OR subject LIKE %s)';
            $where_values[] = $search_like;
            $where_values[] = $search_like;
        }

        $where = implode(' AND ', $where_clauses);

        // Query to count total
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if (!empty($where_values)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}", $where_values));
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}");
        }

        // Calculate offset
        $per_page = absint($args['per_page']);
        $page = absint($args['page']);
        $offset = ($page - 1) * $per_page;

        // Query to get items
        $query = "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

        if (!empty($where_values)) {
            $where_values[] = $per_page;
            $where_values[] = $offset;
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $items = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $items = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset));
        }

        return array(
            'items' => $items,
            'total' => absint($total),
        );
    }

    /**
     * Gets a specific log by ID
     *
     * @param int $id Log ID
     * @return object|null
     */
    public function get_log(int $id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Deletes old logs based on retention configuration
     */
    public function cleanup_old_logs()
    {
        $retention_days = $this->settings->get('log_retention_days');

        // 0 means indefinite retention
        if (empty($retention_days)) {
            return;
        }

        global $wpdb;

        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < %s",
                $cutoff_date
            )
        );
    }

    /**
     * Cleans up logs if exceeding maximum configured
     */
    private function maybe_cleanup_excess_logs()
    {
        global $wpdb;

        $max_entries = $this->settings->get('max_log_entries');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $current_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");

        if ($current_count > $max_entries) {
            $to_delete = $current_count - $max_entries;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$this->table_name} ORDER BY created_at ASC LIMIT %d",
                    $to_delete
                )
            );
        }
    }

    /**
     * Deletes all logs
     *
     * @return int Number of deleted rows
     */
    public function clear_all_logs()
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }

    /**
     * Schedules automatic log cleanup
     */
    private function schedule_cleanup()
    {
        if (!wp_next_scheduled('emailit_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'emailit_cleanup_logs');
        }

        add_action('emailit_cleanup_logs', array($this, 'cleanup_old_logs'));
    }

    /**
     * Gets log statistics
     *
     * @return array
     */
    public function get_stats()
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $sent = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
                'sent'
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $failed = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
                'failed'
            )
        );

        // Last 24 hours
        $yesterday = gmdate('Y-m-d H:i:s', strtotime('-24 hours'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $last_24h = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= %s",
                $yesterday
            )
        );

        return array(
            'total' => absint($total),
            'sent' => absint($sent),
            'failed' => absint($failed),
            'last_24h' => absint($last_24h),
        );
    }

    /**
     * Gets the table name
     *
     * @return string
     */
    public function get_table_name()
    {
        return $this->table_name;
    }

    /**
     * Checks if the table exists
     *
     * @return bool
     */
    public function table_exists()
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $this->table_name
            )
        );

        return $result === $this->table_name;
    }
}
