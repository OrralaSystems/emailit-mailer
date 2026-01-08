<?php
/**
 * Uninstall script for EmailIT Mailer
 *
 * This file runs when the plugin is deleted from WordPress.
 * It cleans up all plugin data from the database.
 *
 * @package EmailIT_Mailer
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('emailit_settings');
delete_option('emailit_db_version');

// Clear scheduled events
wp_clear_scheduled_hook('emailit_cleanup_logs');

// Drop the logs table
global $wpdb;
$table_name = $wpdb->prefix . 'emailit_logs';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Clear any transients
delete_transient('emailit_api_status');

// If multisite, clean up all sites
if (is_multisite()) {
    $sites = get_sites(array('fields' => 'ids'));

    foreach ($sites as $site_id) {
        switch_to_blog($site_id);

        delete_option('emailit_settings');
        delete_option('emailit_db_version');
        wp_clear_scheduled_hook('emailit_cleanup_logs');

        $table_name = $wpdb->prefix . 'emailit_logs';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");

        restore_current_blog();
    }
}
