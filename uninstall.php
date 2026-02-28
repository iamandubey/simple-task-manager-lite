<?php
/**
 * Fired when the plugin is uninstalled.
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$tasks_table = $wpdb->prefix . 'stm_tasks';
$logs_table  = $wpdb->prefix . 'stm_reward_logs';
$tasks_table = '`' . str_replace('`', '', $tasks_table) . '`';
$logs_table  = '`' . str_replace('`', '', $logs_table) . '`';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query("DROP TABLE IF EXISTS {$tasks_table}");
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query("DROP TABLE IF EXISTS {$logs_table}");

delete_option('stm_settings');
delete_option('stm_db_version');

$users = get_users(array('fields' => 'ID'));
if (! empty($users)) {
    foreach ($users as $user_id) {
        delete_user_meta((int) $user_id, 'stm_reward_points');
    }
}
