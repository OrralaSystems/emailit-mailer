<?php
/**
 * Clase de administración del plugin
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
 * Clase Admin
 * 
 * Gestiona las páginas de administración del plugin
 */
class Admin
{

    /**
     * Slug de la página de configuración
     *
     * @var string
     */
    const SETTINGS_SLUG = 'emailit-settings';

    /**
     * Slug de la página de logs
     *
     * @var string
     */
    const LOGS_SLUG = 'emailit-logs';

    /**
     * Instancia de Settings
     *
     * @var Settings
     */
    private $settings;

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
     * @param Logger   $logger   Instancia de Logger
     */
    public function __construct(Settings $settings, Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;

        $this->init_hooks();
    }

    /**
     * Inicializa los hooks de administración
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
     * Añade las páginas de menú de administración
     */
    public function add_admin_menu()
    {
        // Página principal de configuración
        add_options_page(
            __('EmailIT Mailer', 'emailit-mailer'),
            __('EmailIT Mailer', 'emailit-mailer'),
            'manage_options',
            self::SETTINGS_SLUG,
            array($this, 'render_settings_page')
        );

        // Subpágina de logs
        add_options_page(
            __('EmailIT Logs', 'emailit-mailer'),
            __('EmailIT Logs', 'emailit-mailer'),
            'manage_options',
            self::LOGS_SLUG,
            array($this, 'render_logs_page')
        );
    }

    /**
     * Encola los assets de administración
     *
     * @param string $hook Hook de la página actual
     */
    public function enqueue_admin_assets($hook)
    {
        // Solo cargar en nuestras páginas
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

        // Datos para JavaScript
        wp_localize_script('emailit-admin', 'emailitAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('emailit_admin_nonce'),
            'strings' => array(
                'testing' => __('Enviando email de prueba...', 'emailit-mailer'),
                'success' => __('¡Email enviado exitosamente!', 'emailit-mailer'),
                'error' => __('Error al enviar el email:', 'emailit-mailer'),
                'clearing' => __('Limpiando logs...', 'emailit-mailer'),
                'cleared' => __('Logs eliminados correctamente.', 'emailit-mailer'),
                'clearError' => __('Error al eliminar los logs.', 'emailit-mailer'),
                'confirmClear' => __('¿Está seguro de que desea eliminar todos los logs? Esta acción no se puede deshacer.', 'emailit-mailer'),
                'enterEmail' => __('Por favor ingrese un email de destino para la prueba.', 'emailit-mailer'),
            ),
        ));
    }

    /**
     * Muestra avisos de administración
     */
    public function admin_notices()
    {
        // Solo mostrar en nuestras páginas o en la lista de plugins
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('settings_page_' . self::SETTINGS_SLUG, 'plugins'), true)) {
            return;
        }

        // Verificar si el plugin está configurado
        $errors = $this->settings->get_configuration_errors();
        if (!empty($errors) && current_user_can('manage_options')) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . esc_html__('EmailIT Mailer requiere configuración:', 'emailit-mailer') . '</strong></p>';
            echo '<ul style="list-style: disc; margin-left: 20px;">';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul>';
            echo '<p><a href="' . esc_url(admin_url('options-general.php?page=' . self::SETTINGS_SLUG)) . '" class="button">';
            echo esc_html__('Configurar ahora', 'emailit-mailer');
            echo '</a></p>';
            echo '</div>';
        }
    }

    /**
     * Renderiza la página de configuración
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Verificar si se guardaron las opciones
        $settings_updated = isset($_GET['settings-updated']) && 'true' === $_GET['settings-updated'];

        // Obtener estadísticas
        $stats = $this->logger->get_stats();
        ?>
        <div class="wrap emailit-admin-wrap">
            <h1>
                <span class="dashicons dashicons-email"
                    style="font-size: 30px; width: 30px; height: 30px; margin-right: 10px;"></span>
                <?php echo esc_html__('EmailIT Mailer', 'emailit-mailer'); ?>
            </h1>

            <p class="description" style="font-size: 14px; margin-bottom: 20px;">
                <?php echo esc_html__('Plugin desarrollado por Orrala Systems', 'emailit-mailer'); ?>
            </p>

            <?php if ($settings_updated): ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php echo esc_html__('Configuración guardada correctamente.', 'emailit-mailer'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="emailit-admin-container">
                <!-- Panel principal de configuración -->
                <div class="emailit-main-content">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields(Settings::OPTION_GROUP);
                        do_settings_sections('emailit-settings');
                        submit_button(__('Guardar Configuración', 'emailit-mailer'));
                        ?>
                    </form>

                    <!-- Sección de prueba de email -->
                    <div class="emailit-card" style="margin-top: 30px;">
                        <h2>
                            <?php echo esc_html__('Enviar Email de Prueba', 'emailit-mailer'); ?>
                        </h2>
                        <p class="description">
                            <?php echo esc_html__('Envíe un email de prueba para verificar que la configuración es correcta.', 'emailit-mailer'); ?>
                        </p>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="emailit_test_email">
                                        <?php echo esc_html__('Email de Destino', 'emailit-mailer'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="email" id="emailit_test_email" class="regular-text"
                                        value="<?php echo esc_attr(get_option('admin_email')); ?>"
                                        placeholder="test@example.com">
                                    <p class="description">
                                        <?php echo esc_html__('Dirección donde se enviará el correo de prueba.', 'emailit-mailer'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p>
                            <button type="button" id="emailit_test_button" class="button button-secondary">
                                <span class="dashicons dashicons-email-alt" style="margin-top: 4px;"></span>
                                <?php echo esc_html__('Enviar Email de Prueba', 'emailit-mailer'); ?>
                            </button>
                            <span id="emailit_test_result" style="margin-left: 10px;"></span>
                        </p>
                    </div>
                </div>

                <!-- Panel lateral con estadísticas -->
                <div class="emailit-sidebar">
                    <div class="emailit-card emailit-stats-card">
                        <h3>
                            <?php echo esc_html__('Estadísticas de Envío', 'emailit-mailer'); ?>
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
                                <?php echo esc_html__('Enviados', 'emailit-mailer'); ?>
                            </span>
                        </div>

                        <div class="emailit-stat emailit-stat-error">
                            <span class="emailit-stat-number">
                                <?php echo esc_html(number_format_i18n($stats['failed'])); ?>
                            </span>
                            <span class="emailit-stat-label">
                                <?php echo esc_html__('Fallidos', 'emailit-mailer'); ?>
                            </span>
                        </div>

                        <div class="emailit-stat">
                            <span class="emailit-stat-number">
                                <?php echo esc_html(number_format_i18n($stats['last_24h'])); ?>
                            </span>
                            <span class="emailit-stat-label">
                                <?php echo esc_html__('Últimas 24h', 'emailit-mailer'); ?>
                            </span>
                        </div>

                        <p style="margin-top: 15px;">
                            <a href="<?php echo esc_url(admin_url('options-general.php?page=' . self::LOGS_SLUG)); ?>"
                                class="button button-secondary">
                                <?php echo esc_html__('Ver Logs', 'emailit-mailer'); ?>
                            </a>
                        </p>
                    </div>

                    <div class="emailit-card">
                        <h3>
                            <?php echo esc_html__('Enlaces Útiles', 'emailit-mailer'); ?>
                        </h3>
                        <ul class="emailit-links-list">
                            <li>
                                <a href="https://emailit.com" target="_blank" rel="noopener noreferrer">
                                    <span class="dashicons dashicons-external"></span>
                                    <?php echo esc_html__('Panel de EmailIT', 'emailit-mailer'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="https://docs.emailit.com" target="_blank" rel="noopener noreferrer">
                                    <span class="dashicons dashicons-book"></span>
                                    <?php echo esc_html__('Documentación API', 'emailit-mailer'); ?>
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
     * Renderiza la página de logs
     */
    public function render_logs_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Obtener parámetros de paginación y filtrado
        $per_page = 20;
        $page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        // Obtener logs
        $logs_data = $this->logger->get_logs(array(
            'per_page' => $per_page,
            'page' => $page,
            'status' => $status,
            'search' => $search,
        ));

        $logs = $logs_data['items'];
        $total = $logs_data['total'];
        $total_pages = ceil($total / $per_page);

        // Estadísticas
        $stats = $this->logger->get_stats();
        ?>
        <div class="wrap emailit-admin-wrap">
            <h1>
                <span class="dashicons dashicons-list-view"
                    style="font-size: 30px; width: 30px; height: 30px; margin-right: 10px;"></span>
                <?php echo esc_html__('Registro de Emails', 'emailit-mailer'); ?>
            </h1>

            <!-- Filtros -->
            <div class="emailit-logs-filters">
                <form method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::LOGS_SLUG); ?>">

                    <select name="status">
                        <option value="">
                            <?php echo esc_html__('Todos los estados', 'emailit-mailer'); ?>
                        </option>
                        <option value="sent" <?php selected($status, 'sent'); ?>>
                            <?php echo esc_html__('Enviados', 'emailit-mailer'); ?>
                        </option>
                        <option value="failed" <?php selected($status, 'failed'); ?>>
                            <?php echo esc_html__('Fallidos', 'emailit-mailer'); ?>
                        </option>
                    </select>

                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>"
                        placeholder="<?php echo esc_attr__('Buscar por email o asunto...', 'emailit-mailer'); ?>"
                        class="regular-text">

                    <?php submit_button(__('Filtrar', 'emailit-mailer'), 'secondary', 'filter', false); ?>

                    <?php if (!empty($status) || !empty($search)): ?>
                        <a href="<?php echo esc_url(admin_url('options-general.php?page=' . self::LOGS_SLUG)); ?>"
                            class="button">
                            <?php echo esc_html__('Limpiar Filtros', 'emailit-mailer'); ?>
                        </a>
                    <?php endif; ?>
                </form>

                <div class="emailit-logs-actions">
                    <button type="button" id="emailit_clear_logs" class="button button-link-delete">
                        <span class="dashicons dashicons-trash" style="margin-top: 4px;"></span>
                        <?php echo esc_html__('Eliminar Todos los Logs', 'emailit-mailer'); ?>
                    </button>
                </div>
            </div>

            <!-- Resumen -->
            <div class="emailit-logs-summary">
                <span class="emailit-badge">
                    <?php echo esc_html(sprintf(__('Total: %d', 'emailit-mailer'), $stats['total'])); ?>
                </span>
                <span class="emailit-badge emailit-badge-success">
                    <?php echo esc_html(sprintf(__('Enviados: %d', 'emailit-mailer'), $stats['sent'])); ?>
                </span>
                <span class="emailit-badge emailit-badge-error">
                    <?php echo esc_html(sprintf(__('Fallidos: %d', 'emailit-mailer'), $stats['failed'])); ?>
                </span>
            </div>

            <!-- Tabla de logs -->
            <table class="wp-list-table widefat fixed striped emailit-logs-table">
                <thead>
                    <tr>
                        <th scope="col" class="column-id" style="width: 60px;">
                            <?php echo esc_html__('ID', 'emailit-mailer'); ?>
                        </th>
                        <th scope="col" class="column-date" style="width: 150px;">
                            <?php echo esc_html__('Fecha', 'emailit-mailer'); ?>
                        </th>
                        <th scope="col" class="column-to">
                            <?php echo esc_html__('Destinatario', 'emailit-mailer'); ?>
                        </th>
                        <th scope="col" class="column-subject">
                            <?php echo esc_html__('Asunto', 'emailit-mailer'); ?>
                        </th>
                        <th scope="col" class="column-status" style="width: 100px;">
                            <?php echo esc_html__('Estado', 'emailit-mailer'); ?>
                        </th>
                        <th scope="col" class="column-response">
                            <?php echo esc_html__('Respuesta', 'emailit-mailer'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">
                                <?php echo esc_html__('No se encontraron registros.', 'emailit-mailer'); ?>
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
                                            <?php echo esc_html__('Enviado', 'emailit-mailer'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="emailit-status emailit-status-error">
                                            <span class="dashicons dashicons-warning"></span>
                                            <?php echo esc_html__('Fallido', 'emailit-mailer'); ?>
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
                                            <?php echo esc_html__('Ver más', 'emailit-mailer'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Paginación -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php echo esc_html(sprintf(_n('%s elemento', '%s elementos', $total, 'emailit-mailer'), number_format_i18n($total))); ?>
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

        <!-- Modal para ver respuesta completa -->
        <div id="emailit-response-modal" class="emailit-modal" style="display: none;">
            <div class="emailit-modal-content">
                <span class="emailit-modal-close">&times;</span>
                <h3>
                    <?php echo esc_html__('Respuesta Completa', 'emailit-mailer'); ?>
                </h3>
                <pre id="emailit-modal-response"></pre>
            </div>
        </div>
        <?php
    }

    /**
     * Handler AJAX para prueba de conexión
     */
    public function ajax_test_connection()
    {
        // Verificar nonce
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        // Verificar capacidades
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tiene permisos para realizar esta acción.', 'emailit-mailer')));
        }

        // Obtener email de prueba
        $test_email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

        if (empty($test_email) || !is_email($test_email)) {
            wp_send_json_error(array('message' => __('Por favor ingrese un email válido.', 'emailit-mailer')));
        }

        // Obtener instancia de API
        $plugin = emailit_mailer();
        $result = $plugin->api->test_connection($test_email);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('¡Email de prueba enviado exitosamente! Revise su bandeja de entrada.', 'emailit-mailer')));
    }

    /**
     * Handler AJAX para limpiar logs
     */
    public function ajax_clear_logs()
    {
        // Verificar nonce
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        // Verificar capacidades
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tiene permisos para realizar esta acción.', 'emailit-mailer')));
        }

        $result = $this->logger->clear_all_logs();

        if (false === $result) {
            wp_send_json_error(array('message' => __('Error al eliminar los logs.', 'emailit-mailer')));
        }

        wp_send_json_success(array('message' => __('Todos los logs han sido eliminados.', 'emailit-mailer')));
    }
}
