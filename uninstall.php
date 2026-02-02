<?php
/**
 * ITX Cheetah Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package ITX_Cheetah
 */

// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options
delete_option('itx_cheetah_settings');
delete_option('itx_cheetah_db_version');

// Drop custom tables
global $wpdb;
$table_name = $wpdb->prefix . 'itx_cheetah_scans';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Clear any scheduled hooks
wp_clear_scheduled_hook('itx_cheetah_scheduled_scan');
wp_clear_scheduled_hook('itx_cheetah_cleanup');
