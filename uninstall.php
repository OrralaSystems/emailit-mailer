<?php
/**
 * Plugin Uninstall
 *
 * Cleans up all data when the plugin is deleted
 *
 * @package EmailIT_Mailer
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up plugin data
 */
function emailit_uninstall_cleanup()
{
    global $wpdb;

    // Delete plugin options
    delete_option('emailit_settings');
    delete_option('emailit_db_version');

    // Delete logs table
    $table_name = $wpdb->prefix . 'emailit_logs';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query("DROP TABLE IF EXISTS {$table_name}");

    // Clear scheduled events
    wp_clear_scheduled_hook('emailit_cleanup_logs');

    // Delete any transients (if any)
    delete_transient('emailit_api_status');
}

// Execute cleanup
emailit_uninstall_cleanup();
