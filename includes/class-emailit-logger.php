<?php
/**
 * Clase de registro de correos
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
 * Clase Logger
 * 
 * Gestiona el registro de correos enviados en la base de datos
 */
class Logger
{

    /**
     * Nombre de la tabla de logs (sin prefijo)
     *
     * @var string
     */
    const TABLE_NAME = 'emailit_logs';

    /**
     * Instancia de Settings
     *
     * @var Settings
     */
    private $settings;

    /**
     * Nombre completo de la tabla
     *
     * @var string
     */
    private $table_name;

    /**
     * Constructor
     *
     * @param Settings $settings Instancia de configuraciones
     */
    public function __construct(Settings $settings)
    {
        global $wpdb;

        $this->settings = $settings;
        $this->table_name = $wpdb->prefix . self::TABLE_NAME;

        // Programar limpieza automática
        $this->schedule_cleanup();
    }

    /**
     * Crea la tabla de logs en la base de datos
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

        // Guardar versión del esquema
        update_option('emailit_db_version', EMAILIT_VERSION);
    }

    /**
     * Elimina la tabla de logs
     */
    public function drop_table()
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
    }

    /**
     * Registra un envío de correo
     *
     * @param string $to_email   Email del destinatario
     * @param string $subject    Asunto del correo
     * @param string $status     Estado: 'sent' o 'failed'
     * @param string $response   Respuesta de la API o mensaje de error
     * @param array  $headers    Headers del correo (opcional)
     * @return int|false ID del registro insertado o false si falló
     */
    public function log(string $to_email, string $subject, string $status, string $response = '', array $headers = array())
    {
        // Verificar si el logging está habilitado
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

        // Limpiar logs antiguos si exceden el máximo
        $this->maybe_cleanup_excess_logs();

        return $log_id;
    }

    /**
     * Registra un envío exitoso
     *
     * @param string $to_email Email del destinatario
     * @param string $subject  Asunto del correo
     * @param array  $response Respuesta de la API
     * @param array  $headers  Headers del correo
     * @return int|false
     */
    public function log_success(string $to_email, string $subject, array $response = array(), array $headers = array())
    {
        $response_text = !empty($response) ? wp_json_encode($response) : __('Enviado exitosamente', 'emailit-mailer');
        return $this->log($to_email, $subject, 'sent', $response_text, $headers);
    }

    /**
     * Registra un envío fallido
     *
     * @param string $to_email      Email del destinatario
     * @param string $subject       Asunto del correo
     * @param string $error_message Mensaje de error
     * @param array  $headers       Headers del correo
     * @return int|false
     */
    public function log_failure(string $to_email, string $subject, string $error_message, array $headers = array())
    {
        return $this->log($to_email, $subject, 'failed', $error_message, $headers);
    }

    /**
     * Obtiene los logs con paginación
     *
     * @param array $args Argumentos de consulta
     * @return array Array con 'items' y 'total'
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

        // Sanitizar parámetros de ordenación
        $allowed_orderby = array('id', 'to_email', 'subject', 'status', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';
        $order = 'ASC' === strtoupper($args['order']) ? 'ASC' : 'DESC';

        // Construir cláusula WHERE
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

        // Query para contar total
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if (!empty($where_values)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}", $where_values));
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}");
        }

        // Calcular offset
        $per_page = absint($args['per_page']);
        $page = absint($args['page']);
        $offset = ($page - 1) * $per_page;

        // Query para obtener items
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
     * Obtiene un log específico por ID
     *
     * @param int $id ID del log
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
     * Elimina logs antiguos basándose en la configuración de retención
     */
    public function cleanup_old_logs()
    {
        $retention_days = $this->settings->get('log_retention_days');

        // 0 significa retención indefinida
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
     * Limpia logs si exceden el máximo configurado
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
     * Elimina todos los logs
     *
     * @return int Número de filas eliminadas
     */
    public function clear_all_logs()
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }

    /**
     * Programa la limpieza automática de logs
     */
    private function schedule_cleanup()
    {
        if (!wp_next_scheduled('emailit_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'emailit_cleanup_logs');
        }

        add_action('emailit_cleanup_logs', array($this, 'cleanup_old_logs'));
    }

    /**
     * Obtiene estadísticas de los logs
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

        // Últimas 24 horas
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
     * Obtiene el nombre de la tabla
     *
     * @return string
     */
    public function get_table_name()
    {
        return $this->table_name;
    }

    /**
     * Verifica si la tabla existe
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
