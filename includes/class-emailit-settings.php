<?php
/**
 * Clase de gestión de configuraciones
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
 * Clase Settings
 * 
 * Gestiona todas las opciones de configuración del plugin
 */
class Settings
{

    /**
     * Nombre de la opción en la base de datos
     *
     * @var string
     */
    const OPTION_NAME = 'emailit_settings';

    /**
     * Grupo de opciones para la API de Settings
     *
     * @var string
     */
    const OPTION_GROUP = 'emailit_settings_group';

    /**
     * Opciones cargadas
     *
     * @var array
     */
    private $options = array();

    /**
     * Opciones por defecto
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
     * Carga las opciones desde la base de datos
     */
    private function load_options()
    {
        $saved_options = get_option(self::OPTION_NAME, array());
        $this->options = wp_parse_args($saved_options, $this->defaults);
    }

    /**
     * Obtiene una opción específica
     *
     * @param string $key Nombre de la opción
     * @param mixed  $default Valor por defecto si no existe
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
     * Obtiene todas las opciones
     *
     * @return array
     */
    public function get_all()
    {
        return $this->options;
    }

    /**
     * Actualiza una opción específica
     *
     * @param string $key   Nombre de la opción
     * @param mixed  $value Valor a guardar
     * @return bool
     */
    public function set($key, $value)
    {
        $this->options[$key] = $value;
        return update_option(self::OPTION_NAME, $this->options);
    }

    /**
     * Actualiza múltiples opciones
     *
     * @param array $options Opciones a actualizar
     * @return bool
     */
    public function update($options)
    {
        $this->options = wp_parse_args($options, $this->options);
        return update_option(self::OPTION_NAME, $this->options);
    }

    /**
     * Establece los valores por defecto
     */
    public function set_defaults()
    {
        if (false === get_option(self::OPTION_NAME)) {
            // Intentar obtener el email del administrador como valor por defecto
            $admin_email = get_option('admin_email');
            $this->defaults['from_email'] = $admin_email;
            $this->defaults['from_name'] = get_option('blogname', 'WordPress');

            add_option(self::OPTION_NAME, $this->defaults);
            $this->options = $this->defaults;
        }
    }

    /**
     * Registra las configuraciones con la API de WordPress Settings
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

        // Sección: Estado General
        add_settings_section(
            'emailit_general_section',
            __('Estado del Plugin', 'emailit-mailer'),
            array($this, 'render_general_section'),
            'emailit-settings'
        );

        // Campo: Habilitar Plugin
        add_settings_field(
            'enabled',
            __('Habilitar EmailIT Mailer', 'emailit-mailer'),
            array($this, 'render_enabled_field'),
            'emailit-settings',
            'emailit_general_section'
        );

        // Sección: Autenticación API
        add_settings_section(
            'emailit_api_section',
            __('Configuración de API', 'emailit-mailer'),
            array($this, 'render_api_section'),
            'emailit-settings'
        );

        // Campo: API Key
        add_settings_field(
            'api_key',
            __('API Key de EmailIT', 'emailit-mailer'),
            array($this, 'render_api_key_field'),
            'emailit-settings',
            'emailit_api_section'
        );

        // Sección: Remitente
        add_settings_section(
            'emailit_sender_section',
            __('Configuración del Remitente', 'emailit-mailer'),
            array($this, 'render_sender_section'),
            'emailit-settings'
        );

        // Campo: Email del remitente
        add_settings_field(
            'from_email',
            __('Email del Remitente', 'emailit-mailer'),
            array($this, 'render_from_email_field'),
            'emailit-settings',
            'emailit_sender_section'
        );

        // Campo: Nombre del remitente
        add_settings_field(
            'from_name',
            __('Nombre del Remitente', 'emailit-mailer'),
            array($this, 'render_from_name_field'),
            'emailit-settings',
            'emailit_sender_section'
        );

        // Campo: Forzar remitente
        add_settings_field(
            'force_from',
            __('Forzar Remitente', 'emailit-mailer'),
            array($this, 'render_force_from_field'),
            'emailit-settings',
            'emailit_sender_section'
        );

        // Campo: Reply-To
        add_settings_field(
            'reply_to',
            __('Email de Respuesta (Reply-To)', 'emailit-mailer'),
            array($this, 'render_reply_to_field'),
            'emailit-settings',
            'emailit_sender_section'
        );

        // Sección: Logs
        add_settings_section(
            'emailit_logs_section',
            __('Configuración de Logs', 'emailit-mailer'),
            array($this, 'render_logs_section'),
            'emailit-settings'
        );

        // Campo: Habilitar logs
        add_settings_field(
            'enable_logging',
            __('Habilitar Registro', 'emailit-mailer'),
            array($this, 'render_enable_logging_field'),
            'emailit-settings',
            'emailit_logs_section'
        );

        // Campo: Días de retención
        add_settings_field(
            'log_retention_days',
            __('Días de Retención', 'emailit-mailer'),
            array($this, 'render_log_retention_field'),
            'emailit-settings',
            'emailit_logs_section'
        );

        // Campo: Máximo de entradas
        add_settings_field(
            'max_log_entries',
            __('Máximo de Entradas', 'emailit-mailer'),
            array($this, 'render_max_log_entries_field'),
            'emailit-settings',
            'emailit_logs_section'
        );
    }

    /**
     * Sanitiza las configuraciones antes de guardar
     *
     * @param array $input Datos de entrada
     * @return array Datos sanitizados
     */
    public function sanitize_settings($input)
    {
        $sanitized = array();

        // Habilitar plugin
        $sanitized['enabled'] = isset($input['enabled']) && $input['enabled'] ? true : false;

        // API Key - mantener el valor anterior si está vacío (para no perderlo)
        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($input['api_key']);
            if (empty($sanitized['api_key'])) {
                $sanitized['api_key'] = $this->get('api_key');
            }
        }

        // Email del remitente
        if (isset($input['from_email'])) {
            $sanitized['from_email'] = sanitize_email($input['from_email']);
        }

        // Nombre del remitente
        if (isset($input['from_name'])) {
            $sanitized['from_name'] = sanitize_text_field($input['from_name']);
        }

        // Forzar remitente
        $sanitized['force_from'] = isset($input['force_from']) && $input['force_from'] ? true : false;

        // Reply-To
        if (isset($input['reply_to'])) {
            $sanitized['reply_to'] = sanitize_email($input['reply_to']);
        }

        // Habilitar logs
        $sanitized['enable_logging'] = isset($input['enable_logging']) && $input['enable_logging'] ? true : false;

        // Días de retención
        if (isset($input['log_retention_days'])) {
            $sanitized['log_retention_days'] = absint($input['log_retention_days']);
        }

        // Máximo de entradas
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
     * Renderiza la descripción de la sección General
     */
    public function render_general_section()
    {
        echo '<p>' . esc_html__('Controle el estado del plugin. Cuando está deshabilitado, WordPress usará su método de envío de correo predeterminado.', 'emailit-mailer') . '</p>';
    }

    /**
     * Renderiza el campo Habilitar Plugin
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
            <?php echo $enabled ? esc_html__('Activo', 'emailit-mailer') : esc_html__('Inactivo', 'emailit-mailer'); ?>
        </span>
        <p class="description">
            <?php esc_html_e('Cuando está activo, todos los correos de WordPress se enviarán a través de EmailIT. Si lo desactiva, se usará el método de envío predeterminado de WordPress.', 'emailit-mailer'); ?>
        </p>
        <?php
    }

    /**
     * Renderiza la descripción de la sección API
     */
    public function render_api_section()
    {
        echo '<p>' . esc_html__('Configure su clave API de EmailIT. Puede obtenerla desde su panel de control en emailit.com.', 'emailit-mailer') . '</p>';
    }

    /**
     * Renderiza el campo API Key
     */
    public function render_api_key_field()
    {
        $api_key = $this->get('api_key');
        $masked_key = !empty($api_key) ? str_repeat('•', 20) . substr($api_key, -4) : '';
        ?>
        <input type="password" id="emailit_api_key" name="<?php echo esc_attr(self::OPTION_NAME); ?>[api_key]"
            value="<?php echo esc_attr($api_key); ?>" class="regular-text" autocomplete="off"
            placeholder="<?php echo esc_attr($masked_key ?: __('Ingrese su API Key', 'emailit-mailer')); ?>">
        <button type="button" class="button button-secondary" id="emailit-toggle-api-key">
            <?php esc_html_e('Mostrar', 'emailit-mailer'); ?>
        </button>
        <p class="description">
            <?php
            printf(
                /* translators: %s: URL to EmailIT dashboard */
                esc_html__('Obtenga su API Key desde %s', 'emailit-mailer'),
                '<a href="https://emailit.com" target="_blank" rel="noopener noreferrer">EmailIT Dashboard</a>'
            );
            ?>
        </p>
        <?php
    }

    /**
     * Renderiza la descripción de la sección Remitente
     */
    public function render_sender_section()
    {
        echo '<p>' . esc_html__('Configure el remitente predeterminado para todos los correos enviados desde WordPress.', 'emailit-mailer') . '</p>';
    }

    /**
     * Renderiza el campo Email del Remitente
     */
    public function render_from_email_field()
    {
        ?>
        <input type="email" id="emailit_from_email" name="<?php echo esc_attr(self::OPTION_NAME); ?>[from_email]"
            value="<?php echo esc_attr($this->get('from_email')); ?>" class="regular-text" placeholder="correo@tudominio.com">
        <p class="description">
            <?php esc_html_e('Dirección de correo desde la cual se enviarán todos los emails. Debe estar verificada en EmailIT.', 'emailit-mailer'); ?>
        </p>
        <?php
    }

    /**
     * Renderiza el campo Nombre del Remitente
     */
    public function render_from_name_field()
    {
        ?>
        <input type="text" id="emailit_from_name" name="<?php echo esc_attr(self::OPTION_NAME); ?>[from_name]"
            value="<?php echo esc_attr($this->get('from_name')); ?>" class="regular-text"
            placeholder="<?php echo esc_attr(get_option('blogname')); ?>">
        <p class="description">
            <?php esc_html_e('Nombre que aparecerá como remitente de los correos.', 'emailit-mailer'); ?>
        </p>
        <?php
    }

    /**
     * Renderiza el campo Forzar Remitente
     */
    public function render_force_from_field()
    {
        ?>
        <label for="emailit_force_from">
            <input type="checkbox" id="emailit_force_from" name="<?php echo esc_attr(self::OPTION_NAME); ?>[force_from]"
                value="1" <?php checked($this->get('force_from'), true); ?>>
            <?php esc_html_e('Forzar el remitente configurado en todos los correos', 'emailit-mailer'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Si está habilitado, todos los correos usarán el remitente configurado arriba, ignorando el remitente especificado por otros plugins.', 'emailit-mailer'); ?>
        </p>
        <?php
    }

    /**
     * Renderiza el campo Reply-To
     */
    public function render_reply_to_field()
    {
        ?>
        <input type="email" id="emailit_reply_to" name="<?php echo esc_attr(self::OPTION_NAME); ?>[reply_to]"
            value="<?php echo esc_attr($this->get('reply_to')); ?>" class="regular-text"
            placeholder="<?php esc_attr_e('Opcional - Dejar vacío para usar el email del remitente', 'emailit-mailer'); ?>">
        <p class="description">
            <?php esc_html_e('Dirección de correo donde se recibirán las respuestas. Si está vacío, se usará el email del remitente.', 'emailit-mailer'); ?>
        </p>
        <?php
    }

    /**
     * Renderiza la descripción de la sección Logs
     */
    public function render_logs_section()
    {
        echo '<p>' . esc_html__('Configure el registro de correos enviados. Los logs ayudan a diagnosticar problemas de entrega.', 'emailit-mailer') . '</p>';
    }

    /**
     * Renderiza el campo Habilitar Logs
     */
    public function render_enable_logging_field()
    {
        ?>
        <label for="emailit_enable_logging">
            <input type="checkbox" id="emailit_enable_logging" name="<?php echo esc_attr(self::OPTION_NAME); ?>[enable_logging]"
                value="1" <?php checked($this->get('enable_logging'), true); ?>>
            <?php esc_html_e('Registrar los correos enviados', 'emailit-mailer'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Habilita el registro de todos los correos enviados con su estado (enviado/fallido).', 'emailit-mailer'); ?>
        </p>
        <?php
    }

    /**
     * Renderiza el campo Días de Retención
     */
    public function render_log_retention_field()
    {
        ?>
        <input type="number" id="emailit_log_retention_days"
            name="<?php echo esc_attr(self::OPTION_NAME); ?>[log_retention_days]"
            value="<?php echo esc_attr($this->get('log_retention_days')); ?>" class="small-text" min="0" max="365">
        <span>
            <?php esc_html_e('días', 'emailit-mailer'); ?>
        </span>
        <p class="description">
            <?php esc_html_e('Número de días que se conservarán los logs. Escriba 0 para conservarlos indefinidamente.', 'emailit-mailer'); ?>
        </p>
        <?php
    }

    /**
     * Renderiza el campo Máximo de Entradas
     */
    public function render_max_log_entries_field()
    {
        ?>
        <input type="number" id="emailit_max_log_entries" name="<?php echo esc_attr(self::OPTION_NAME); ?>[max_log_entries]"
            value="<?php echo esc_attr($this->get('max_log_entries')); ?>" class="small-text" min="10" max="1000">
        <span>
            <?php esc_html_e('entradas', 'emailit-mailer'); ?>
        </span>
        <p class="description">
            <?php esc_html_e('Número máximo de entradas de log a conservar. Los logs más antiguos serán eliminados automáticamente.', 'emailit-mailer'); ?>
        </p>
        <?php
    }

    /**
     * Verifica si el plugin está habilitado
     *
     * @return bool
     */
    public function is_enabled()
    {
        return (bool) $this->get('enabled', true);
    }

    /**
     * Verifica si el plugin está configurado correctamente
     *
     * @return bool
     */
    public function is_configured()
    {
        return !empty($this->get('api_key')) && !empty($this->get('from_email'));
    }

    /**
     * Obtiene los errores de configuración
     *
     * @return array
     */
    public function get_configuration_errors()
    {
        $errors = array();

        if (empty($this->get('api_key'))) {
            $errors[] = __('No se ha configurado la API Key de EmailIT.', 'emailit-mailer');
        }

        if (empty($this->get('from_email'))) {
            $errors[] = __('No se ha configurado el email del remitente.', 'emailit-mailer');
        }

        return $errors;
    }
}
