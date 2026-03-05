<?php
/**
 * Plugin Name: Neura Task Manager
 * Plugin URI:  https://wpneura.com/docs/simple-task-manager/
 * Description: A task management plugin for WordPress admin with assignment, rewards, and role-based access.
 * Version:     1.0.0
 * Author:      Aman Dubey
 * License:     GPL-2.0-or-later
 * Text Domain: neura-task-manager
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
    exit;
}

final class STM_Simple_Task_Management {
    const VERSION           = '1.0.0';
    const OPTION_SETTINGS   = 'neuratm_settings';
    const LEGACY_OPTION_SETTINGS = 'stm_settings';
    const OPTION_DB_VERSION = 'neuratm_db_version';
    const LEGACY_OPTION_DB_VERSION = 'stm_db_version';
    const USER_POINTS_META  = 'neuratm_reward_points';
    const LEGACY_USER_POINTS_META = 'stm_reward_points';
    const LITE_BUILD        = true;

    public static function init() {
        register_activation_hook(__FILE__, array(__CLASS__, 'activate'));

        add_action('admin_init', array(__CLASS__, 'maybe_upgrade'));
        add_action('admin_init', array(__CLASS__, 'maybe_restrict_dashboard_access'));
        add_action('admin_head', array(__CLASS__, 'render_admin_icon_css'));
        add_action('admin_menu', array(__CLASS__, 'register_admin_menu'));
        add_shortcode('stm_frontend_dashboard', array(__CLASS__, 'render_frontend_dashboard_shortcode'));
        add_filter('login_redirect', array(__CLASS__, 'filter_login_redirect'), 10, 3);
        add_filter('logout_redirect', array(__CLASS__, 'filter_logout_redirect'), 10, 3);
        add_action('admin_post_stm_save_task', array(__CLASS__, 'handle_save_task'));
        add_action('admin_post_stm_delete_task', array(__CLASS__, 'handle_delete_task'));
        add_action('admin_post_stm_update_status', array(__CLASS__, 'handle_update_status'));
        add_action('admin_post_stm_save_settings', array(__CLASS__, 'handle_save_settings'));
        add_action('admin_post_stm_manage_leaderboard', array(__CLASS__, 'handle_manage_leaderboard'));
        if (self::is_slack_available()) {
            add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));
        }
        if (self::is_data_tools_available()) {
            add_action('admin_post_stm_task_data_tools', array(__CLASS__, 'handle_task_data_tools'));
        }
        if (self::is_overdue_workflow_available()) {
            add_action('admin_post_stm_submit_overdue_reason', array(__CLASS__, 'handle_submit_overdue_reason'));
            add_action('admin_post_stm_review_overdue_reason', array(__CLASS__, 'handle_review_overdue_reason'));
        }
    }

    private static function is_slack_available() {
        return true;
    }

    private static function is_data_tools_available() {
        return true;
    }

    private static function is_overdue_workflow_available() {
        return true;
    }

    private static function is_leaderboard_reset_available() {
        return true;
    }

    private static function is_pro_feature_available($feature_key) {
        return true;
    }

    private static function pro_upgrade_url() {
        return 'https://wpneura.com/simple-task-manager/';
    }

    private static function menu_icon_url() {
        $icon_url = plugins_url('assets/wp-check-transparent.svg', __FILE__);
        return apply_filters('stm_lite_menu_icon_url', $icon_url);
    }

    public static function render_admin_icon_css() {
        wp_register_style('neura-task-manager-admin-icon', false, array(), self::VERSION);
        wp_enqueue_style('neura-task-manager-admin-icon');
        wp_add_inline_style(
            'neura-task-manager-admin-icon',
            '#adminmenu .toplevel_page_stm-task-manager .wp-menu-image,'
            . '#adminmenu .toplevel_page_stm-task-manager:hover .wp-menu-image,'
            . '#adminmenu .toplevel_page_stm-task-manager.wp-has-current-submenu .wp-menu-image,'
            . '#adminmenu .toplevel_page_stm-task-manager.current .wp-menu-image{filter:none!important;}'
            . '#adminmenu .toplevel_page_stm-task-manager .wp-menu-image img,'
            . '#adminmenu .toplevel_page_stm-task-manager .wp-menu-image::before{width:20px!important;height:20px!important;max-width:20px!important;max-height:20px!important;filter:none!important;}'
            . '#adminmenu .toplevel_page_stm-task-manager:hover .wp-menu-image img,'
            . '#adminmenu .toplevel_page_stm-task-manager.wp-has-current-submenu .wp-menu-image img,'
            . '#adminmenu .toplevel_page_stm-task-manager.current .wp-menu-image img,'
            . '#adminmenu .toplevel_page_stm-task-manager:hover .wp-menu-image::before,'
            . '#adminmenu .toplevel_page_stm-task-manager.wp-has-current-submenu .wp-menu-image::before,'
            . '#adminmenu .toplevel_page_stm-task-manager.current .wp-menu-image::before{filter:none!important;opacity:1!important;}'
        );
    }

    private static function enqueue_frontend_guest_assets() {
        wp_enqueue_style(
            'neuratm-frontend-guest',
            plugins_url('assets/frontend-guest.css', __FILE__),
            array(),
            self::VERSION
        );
    }

    private static function enqueue_frontend_dashboard_assets($open_task_dialog_on_load, $auto_refresh_enabled, $auto_refresh_ms, $auto_refresh_only_visible) {
        wp_enqueue_style(
            'neuratm-frontend-dashboard',
            plugins_url('assets/frontend-dashboard.css', __FILE__),
            array('dashicons'),
            self::VERSION
        );

        wp_enqueue_script(
            'neuratm-frontend-dashboard',
            plugins_url('assets/frontend-dashboard.js', __FILE__),
            array(),
            self::VERSION,
            true
        );

        wp_add_inline_script(
            'neuratm-frontend-dashboard',
            'window.neuratmFrontendConfig = ' . wp_json_encode(
                array(
                    'openTaskDialogOnLoad' => (bool) $open_task_dialog_on_load,
                    'showDetailsText'      => __('Show details', 'neura-task-manager'),
                    'hideDetailsText'      => __('Hide details', 'neura-task-manager'),
                    'autoRefreshEnabled'   => (bool) $auto_refresh_enabled,
                    'autoRefreshMs'        => (int) $auto_refresh_ms,
                    'autoRefreshOnlyVisible' => (bool) $auto_refresh_only_visible,
                )
            ) . ';',
            'before'
        );
    }

    private static function enqueue_admin_dashboard_assets($open_task_dialog_on_load, $auto_refresh_enabled, $auto_refresh_ms, $auto_refresh_only_visible) {
        wp_enqueue_style(
            'neuratm-admin-dashboard',
            plugins_url('assets/admin-dashboard.css', __FILE__),
            array('dashicons'),
            self::VERSION
        );

        wp_enqueue_script(
            'neuratm-admin-dashboard',
            plugins_url('assets/admin-dashboard.js', __FILE__),
            array(),
            self::VERSION,
            true
        );

        wp_add_inline_script(
            'neuratm-admin-dashboard',
            'window.neuratmAdminConfig = ' . wp_json_encode(
                array(
                    'openTaskDialogOnLoad' => (bool) $open_task_dialog_on_load,
                    'autoRefreshEnabled'   => (bool) $auto_refresh_enabled,
                    'autoRefreshMs'        => (int) $auto_refresh_ms,
                    'autoRefreshOnlyVisible' => (bool) $auto_refresh_only_visible,
                )
            ) . ';',
            'before'
        );
    }

    public static function activate() {
        self::run_db_migration();
        self::ensure_default_settings();
    }

    public static function maybe_upgrade() {
        $stored_version = get_option(self::OPTION_DB_VERSION, '0.0.0');
        if ('0.0.0' === $stored_version) {
            $stored_version = get_option(self::LEGACY_OPTION_DB_VERSION, '0.0.0');
        }

        if (version_compare((string) $stored_version, self::VERSION, '<')) {
            self::run_db_migration();
            self::ensure_default_settings();
        }
    }

    private static function run_db_migration() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tasks_table      = self::table_name();
        $logs_table       = self::reward_log_table_name();
        $charset_collate  = $wpdb->get_charset_collate();

        $sql_tasks = "CREATE TABLE {$tasks_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'todo',
            priority VARCHAR(20) NOT NULL DEFAULT 'medium',
            due_date DATE NULL,
            reward_points INT UNSIGNED NOT NULL DEFAULT 0,
            assigned_to BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            overdue_reason TEXT NULL,
            overdue_status VARCHAR(20) NOT NULL DEFAULT 'none',
            overdue_submitted_at DATETIME NULL,
            overdue_reviewed_by BIGINT UNSIGNED NULL,
            overdue_reviewed_at DATETIME NULL,
            overdue_review_note TEXT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY priority (priority),
            KEY due_date (due_date),
            KEY overdue_status (overdue_status),
            KEY assigned_to (assigned_to),
            KEY created_by (created_by)
        ) {$charset_collate};";

        $sql_logs = "CREATE TABLE {$logs_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            task_id BIGINT UNSIGNED NOT NULL,
            points INT UNSIGNED NOT NULL,
            event VARCHAR(30) NOT NULL DEFAULT 'completion',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY task_id (task_id),
            KEY event (event)
        ) {$charset_collate};";

        dbDelta($sql_tasks);
        dbDelta($sql_logs);

        update_option(self::OPTION_DB_VERSION, self::VERSION);
        if (false !== get_option(self::LEGACY_OPTION_DB_VERSION, false)) {
            delete_option(self::LEGACY_OPTION_DB_VERSION);
        }
    }

    private static function table_name() {
        global $wpdb;
        $primary_table = $wpdb->prefix . 'neuratm_tasks';
        $legacy_table = $wpdb->prefix . 'stm_tasks';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $primary_exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $primary_table));
        if ($primary_exists === $primary_table) {
            return $primary_table;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $legacy_exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_table));
        if ($legacy_exists === $legacy_table) {
            return $legacy_table;
        }
        return $primary_table;
    }

    private static function reward_log_table_name() {
        global $wpdb;
        $primary_table = $wpdb->prefix . 'neuratm_reward_logs';
        $legacy_table = $wpdb->prefix . 'stm_reward_logs';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $primary_exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $primary_table));
        if ($primary_exists === $primary_table) {
            return $primary_table;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $legacy_exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_table));
        if ($legacy_exists === $legacy_table) {
            return $legacy_table;
        }
        return $primary_table;
    }

    private static function escaped_table_name($table_name) {
        return '`' . str_replace('`', '', (string) $table_name) . '`';
    }

    private static function unslashed_post_array($key) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is enforced in request handlers before calling this helper.
        if (! isset($_POST[$key]) || ! is_array($_POST[$key])) {
            return array();
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is enforced in request handlers before calling this helper.
        return array_map('sanitize_text_field', wp_unslash($_POST[$key]));
    }

    private static function default_settings() {
        return array(
            'access_roles'      => array('administrator', 'editor'),
            'settings_roles'    => array('administrator', 'editor'),
            'frontend_roles'    => array('administrator', 'editor'),
            'frontend_enabled'  => 0,
            'frontend_page_id'  => 0,
            'frontend_redirect' => 0,
            'frontend_tasks_home_url' => '',
            'frontend_login_url' => '',
            'frontend_login_redirect_param' => 'redirect_to',
            'frontend_logout_redirect_url' => '',
            'guest_login_title' => 'Please Log In To View Tasks',
            'guest_login_subtitle' => 'Access your task dashboard, track progress, and check reward points after signing in.',
            'guest_login_button_text' => 'Login To Continue',
            'guest_login_note' => 'Need access? Contact your administrator for task dashboard permissions.',
            'guest_login_button_color_start' => '#2563eb',
            'guest_login_button_color_end' => '#4f46e5',
            'guest_login_button_text_color' => '#ffffff',
            'guest_login_button_radius' => 12,
            'dashboard_title'   => 'Daily Taks',
            'frontend_force_tasks_only' => 1,
            'employee_own_tasks_only' => 1,
            'admin_employee_filter_enabled' => 1,
            'auto_refresh_enabled'  => 0,
            'auto_refresh_frontend' => 1,
            'auto_refresh_backend'  => 1,
            'auto_refresh_user_scope' => 'all',
            'auto_refresh_only_visible' => 1,
            'auto_refresh_interval' => 60,
            'tasks_per_page' => 10,
            'slack_enabled' => 0,
            'slack_signing_secret' => '',
            'slack_bot_token' => '',
            'slack_channel_id' => '',
            'slack_notify_on_assign' => 1,
            'slack_notify_on_assign_dm' => 1,
            'slack_allow_create_from_command' => 1,
            'slack_user_map' => array(),
            'visibility'        => 'all',
            'default_status'    => 'todo',
            'default_priority'  => 'medium',
            'require_due_date'  => 0,
            'allow_user_edit'   => 0,
            'allow_user_delete' => 0,
            'rewards_enabled'   => 1,
            'reward_low'        => 5,
            'reward_medium'     => 10,
            'reward_high'       => 20,
            'reward_award_mode' => 'once',
        );
    }

    private static function ensure_default_settings() {
        $current = get_option(self::OPTION_SETTINGS, array());
        if (! is_array($current) || empty($current)) {
            $legacy = get_option(self::LEGACY_OPTION_SETTINGS, array());
            if (is_array($legacy) && ! empty($legacy)) {
                $current = $legacy;
            }
        }
        if (! is_array($current)) {
            $current = array();
        }

        $merged = wp_parse_args($current, self::default_settings());
        update_option(self::OPTION_SETTINGS, self::sanitize_settings($merged));
        if (false !== get_option(self::LEGACY_OPTION_SETTINGS, false)) {
            delete_option(self::LEGACY_OPTION_SETTINGS);
        }
    }

    private static function get_settings() {
        $saved = get_option(self::OPTION_SETTINGS, array());
        if (! is_array($saved) || empty($saved)) {
            $legacy = get_option(self::LEGACY_OPTION_SETTINGS, array());
            if (is_array($legacy) && ! empty($legacy)) {
                $saved = $legacy;
            }
        }
        if (! is_array($saved)) {
            $saved = array();
        }

        return self::sanitize_settings(wp_parse_args($saved, self::default_settings()));
    }

    private static function sanitize_settings($settings) {
        $roles = array_keys(self::available_roles());
        $lock_dashboard_title = ! self::is_pro_feature_available('custom_dashboard_title');
        $lock_guest_style     = ! self::is_pro_feature_available('guest_style_controls');
        $lock_auto_refresh    = ! self::is_pro_feature_available('auto_refresh_controls');
        $lock_tasks_per_page  = ! self::is_pro_feature_available('tasks_per_page_control');

        $access_roles = isset($settings['access_roles']) && is_array($settings['access_roles']) ? array_map('sanitize_key', $settings['access_roles']) : array();
        $access_roles = array_values(array_intersect($access_roles, $roles));
        if (empty($access_roles)) {
            $access_roles = array('administrator', 'editor');
        }

        $settings_roles = isset($settings['settings_roles']) && is_array($settings['settings_roles']) ? array_map('sanitize_key', $settings['settings_roles']) : array();
        $settings_roles = array_values(array_intersect($settings_roles, $roles));
        if (empty($settings_roles)) {
            $settings_roles = array('administrator', 'editor');
        }

        $frontend_roles = isset($settings['frontend_roles']) && is_array($settings['frontend_roles']) ? array_map('sanitize_key', $settings['frontend_roles']) : array();
        $frontend_roles = array_values(array_intersect($frontend_roles, $roles));
        if (empty($frontend_roles)) {
            $frontend_roles = $access_roles;
        }

        $visibility = isset($settings['visibility']) ? sanitize_key((string) $settings['visibility']) : 'all';
        if (! in_array($visibility, array('all', 'own'), true)) {
            $visibility = 'all';
        }

        $default_status = isset($settings['default_status']) ? sanitize_key((string) $settings['default_status']) : 'todo';
        if (! in_array($default_status, array_keys(self::allowed_statuses()), true)) {
            $default_status = 'todo';
        }

        $default_priority = isset($settings['default_priority']) ? sanitize_key((string) $settings['default_priority']) : 'medium';
        if (! in_array($default_priority, array_keys(self::allowed_priorities()), true)) {
            $default_priority = 'medium';
        }

        $reward_award_mode = isset($settings['reward_award_mode']) ? sanitize_key((string) $settings['reward_award_mode']) : 'once';
        if (! in_array($reward_award_mode, array('once', 'repeat'), true)) {
            $reward_award_mode = 'once';
        }

        $auto_refresh_user_scope = isset($settings['auto_refresh_user_scope']) ? sanitize_key((string) $settings['auto_refresh_user_scope']) : 'all';
        if (! in_array($auto_refresh_user_scope, array('all', 'admin_editor_only', 'users_only'), true)) {
            $auto_refresh_user_scope = 'all';
        }

        $dashboard_title = isset($settings['dashboard_title']) ? sanitize_text_field((string) $settings['dashboard_title']) : 'Daily Taks';
        if ('' === $dashboard_title) {
            $dashboard_title = 'Daily Taks';
        }
        $frontend_login_redirect_param = isset($settings['frontend_login_redirect_param']) ? (string) $settings['frontend_login_redirect_param'] : 'redirect_to';
        $frontend_login_redirect_param = preg_replace('/[^a-zA-Z0-9_-]/', '', $frontend_login_redirect_param);
        if ('' === $frontend_login_redirect_param) {
            $frontend_login_redirect_param = 'redirect_to';
        }
        $guest_login_title = isset($settings['guest_login_title']) ? sanitize_text_field((string) $settings['guest_login_title']) : 'Please Log In To View Tasks';
        if ('' === $guest_login_title) {
            $guest_login_title = 'Please Log In To View Tasks';
        }
        $guest_login_subtitle = isset($settings['guest_login_subtitle']) ? sanitize_text_field((string) $settings['guest_login_subtitle']) : 'Access your task dashboard, track progress, and check reward points after signing in.';
        if ('' === $guest_login_subtitle) {
            $guest_login_subtitle = 'Access your task dashboard, track progress, and check reward points after signing in.';
        }
        $guest_login_button_text = isset($settings['guest_login_button_text']) ? sanitize_text_field((string) $settings['guest_login_button_text']) : 'Login To Continue';
        if ('' === $guest_login_button_text) {
            $guest_login_button_text = 'Login To Continue';
        }
        $guest_login_note = isset($settings['guest_login_note']) ? sanitize_text_field((string) $settings['guest_login_note']) : 'Need access? Contact your administrator for task dashboard permissions.';
        if ('' === $guest_login_note) {
            $guest_login_note = 'Need access? Contact your administrator for task dashboard permissions.';
        }
        $guest_btn_start = self::sanitize_color_hex(isset($settings['guest_login_button_color_start']) ? (string) $settings['guest_login_button_color_start'] : '', '#2563eb');
        $guest_btn_end = self::sanitize_color_hex(isset($settings['guest_login_button_color_end']) ? (string) $settings['guest_login_button_color_end'] : '', '#4f46e5');
        $guest_btn_text = self::sanitize_color_hex(isset($settings['guest_login_button_text_color']) ? (string) $settings['guest_login_button_text_color'] : '', '#ffffff');
        $guest_btn_radius = isset($settings['guest_login_button_radius']) ? max(0, min(30, absint($settings['guest_login_button_radius']))) : 12;
        $slack_user_map = array();
        if (isset($settings['slack_user_map']) && is_array($settings['slack_user_map'])) {
            foreach ($settings['slack_user_map'] as $user_id => $slack_id_raw) {
                $uid = absint($user_id);
                if ($uid < 1) {
                    continue;
                }
                $sid = strtoupper(trim((string) $slack_id_raw));
                $sid = preg_replace('/[^A-Z0-9]/', '', $sid);
                if ('' !== $sid) {
                    $slack_user_map[$uid] = $sid;
                }
            }
        }

        return array(
            'access_roles'      => $access_roles,
            'settings_roles'    => $settings_roles,
            'frontend_roles'    => $frontend_roles,
            'frontend_enabled'  => ! empty($settings['frontend_enabled']) ? 1 : 0,
            'frontend_page_id'  => isset($settings['frontend_page_id']) ? absint($settings['frontend_page_id']) : 0,
            'frontend_redirect' => ! empty($settings['frontend_redirect']) ? 1 : 0,
            'frontend_tasks_home_url' => isset($settings['frontend_tasks_home_url']) ? esc_url_raw((string) $settings['frontend_tasks_home_url']) : '',
            'frontend_login_url' => isset($settings['frontend_login_url']) ? esc_url_raw((string) $settings['frontend_login_url']) : '',
            'frontend_login_redirect_param' => $frontend_login_redirect_param,
            'frontend_logout_redirect_url' => isset($settings['frontend_logout_redirect_url']) ? esc_url_raw((string) $settings['frontend_logout_redirect_url']) : '',
            'guest_login_title' => $guest_login_title,
            'guest_login_subtitle' => $guest_login_subtitle,
            'guest_login_button_text' => $guest_login_button_text,
            'guest_login_note' => $guest_login_note,
            'guest_login_button_color_start' => $lock_guest_style ? '#2563eb' : $guest_btn_start,
            'guest_login_button_color_end' => $lock_guest_style ? '#4f46e5' : $guest_btn_end,
            'guest_login_button_text_color' => $lock_guest_style ? '#ffffff' : $guest_btn_text,
            'guest_login_button_radius' => $lock_guest_style ? 12 : $guest_btn_radius,
            'dashboard_title'   => $lock_dashboard_title ? 'Daily Taks' : $dashboard_title,
            'frontend_force_tasks_only' => ! empty($settings['frontend_force_tasks_only']) ? 1 : 0,
            'employee_own_tasks_only' => ! empty($settings['employee_own_tasks_only']) ? 1 : 0,
            'admin_employee_filter_enabled' => ! empty($settings['admin_employee_filter_enabled']) ? 1 : 0,
            'auto_refresh_enabled'  => $lock_auto_refresh ? 0 : (! empty($settings['auto_refresh_enabled']) ? 1 : 0),
            'auto_refresh_frontend' => $lock_auto_refresh ? 1 : (! empty($settings['auto_refresh_frontend']) ? 1 : 0),
            'auto_refresh_backend'  => $lock_auto_refresh ? 1 : (! empty($settings['auto_refresh_backend']) ? 1 : 0),
            'auto_refresh_user_scope' => $lock_auto_refresh ? 'all' : $auto_refresh_user_scope,
            'auto_refresh_only_visible' => $lock_auto_refresh ? 1 : (! empty($settings['auto_refresh_only_visible']) ? 1 : 0),
            'auto_refresh_interval' => $lock_auto_refresh ? 60 : (isset($settings['auto_refresh_interval']) ? max(10, min(3600, absint($settings['auto_refresh_interval']))) : 60),
            'tasks_per_page' => $lock_tasks_per_page ? 10 : (isset($settings['tasks_per_page']) ? max(1, min(100, absint($settings['tasks_per_page']))) : 10),
            'slack_enabled' => ! empty($settings['slack_enabled']) ? 1 : 0,
            'slack_signing_secret' => isset($settings['slack_signing_secret']) ? sanitize_text_field((string) $settings['slack_signing_secret']) : '',
            'slack_bot_token' => isset($settings['slack_bot_token']) ? sanitize_text_field((string) $settings['slack_bot_token']) : '',
            'slack_channel_id' => isset($settings['slack_channel_id']) ? sanitize_text_field((string) $settings['slack_channel_id']) : '',
            'slack_notify_on_assign' => ! empty($settings['slack_notify_on_assign']) ? 1 : 0,
            'slack_notify_on_assign_dm' => ! empty($settings['slack_notify_on_assign_dm']) ? 1 : 0,
            'slack_allow_create_from_command' => ! empty($settings['slack_allow_create_from_command']) ? 1 : 0,
            'slack_user_map' => $slack_user_map,
            'visibility'        => $visibility,
            'default_status'    => $default_status,
            'default_priority'  => $default_priority,
            'require_due_date'  => ! empty($settings['require_due_date']) ? 1 : 0,
            'allow_user_edit'   => ! empty($settings['allow_user_edit']) ? 1 : 0,
            'allow_user_delete' => ! empty($settings['allow_user_delete']) ? 1 : 0,
            'rewards_enabled'   => ! empty($settings['rewards_enabled']) ? 1 : 0,
            'reward_low'        => isset($settings['reward_low']) ? max(0, absint($settings['reward_low'])) : 5,
            'reward_medium'     => isset($settings['reward_medium']) ? max(0, absint($settings['reward_medium'])) : 10,
            'reward_high'       => isset($settings['reward_high']) ? max(0, absint($settings['reward_high'])) : 20,
            'reward_award_mode' => $reward_award_mode,
        );
    }

    private static function available_roles() {
        if (! function_exists('wp_roles')) {
            return array();
        }

        $roles = wp_roles()->roles;
        $role_labels = array();

        foreach ($roles as $key => $data) {
            $role_labels[$key] = isset($data['name']) ? (string) $data['name'] : $key;
        }

        return $role_labels;
    }

    private static function sanitize_color_hex($value, $default) {
        $sanitized = sanitize_hex_color((string) $value);
        if ($sanitized) {
            return $sanitized;
        }

        $fallback = sanitize_hex_color((string) $default);
        return $fallback ? $fallback : '#2563eb';
    }

    public static function register_rest_routes() {
        register_rest_route(
            'neura-task-manager/v1',
            '/slack-command',
            array(
                'methods'             => 'POST',
                'callback'            => array(__CLASS__, 'handle_slack_command'),
                'permission_callback' => array(__CLASS__, 'slack_permission_callback'),
            )
        );
    }

    public static function slack_permission_callback($request) {
        $settings = self::get_settings();

        if (empty($settings['slack_enabled']) || empty($settings['slack_allow_create_from_command'])) {
            return false;
        }

        return self::slack_verify_request($request, $settings);
    }

    private static function slack_default_created_by() {
        $admins = get_users(
            array(
                'role'   => 'administrator',
                'fields' => array('ID'),
                'number' => 1,
            )
        );
        if (! empty($admins) && isset($admins[0]->ID)) {
            return (int) $admins[0]->ID;
        }
        return 1;
    }

    private static function slack_user_id_by_slack_id($slack_id, $settings) {
        $target = strtoupper(trim((string) $slack_id));
        if ('' === $target || empty($settings['slack_user_map']) || ! is_array($settings['slack_user_map'])) {
            return 0;
        }
        foreach ($settings['slack_user_map'] as $user_id => $mapped_slack_id) {
            if (strtoupper((string) $mapped_slack_id) === $target) {
                return absint($user_id);
            }
        }
        return 0;
    }

    private static function slack_id_by_user_id($user_id, $settings) {
        $uid = absint($user_id);
        if ($uid < 1 || empty($settings['slack_user_map']) || ! is_array($settings['slack_user_map'])) {
            return '';
        }
        return isset($settings['slack_user_map'][$uid]) ? strtoupper((string) $settings['slack_user_map'][$uid]) : '';
    }

    private static function normalized_identity_token($value) {
        $value = strtolower(trim((string) $value));
        return preg_replace('/[^a-z0-9]/', '', $value);
    }

    private static function slack_lookup_user_id_by_handle($handle, $settings) {
        $token = isset($settings['slack_bot_token']) ? trim((string) $settings['slack_bot_token']) : '';
        $handle = strtolower(trim(ltrim((string) $handle, '@')));
        $handle_norm = self::normalized_identity_token($handle);
        $handle_core = preg_replace('/^[^a-z]+/i', '', $handle);
        $handle_core_norm = self::normalized_identity_token($handle_core);
        if ('' === $token || '' === $handle) {
            return '';
        }

        $cursor = '';
        $pages  = 0;
        while ($pages < 5) {
            $pages++;
            $api_url = 'https://slack.com/api/users.list?limit=200';
            if ('' !== $cursor) {
                $api_url = add_query_arg('cursor', rawurlencode($cursor), $api_url);
            }

            $response = wp_remote_get(
                $api_url,
                array(
                    'timeout' => 15,
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                    ),
                )
            );

            if (is_wp_error($response)) {
                return '';
            }

            $body = json_decode((string) wp_remote_retrieve_body($response), true);
            if (! is_array($body) || empty($body['ok']) || empty($body['members']) || ! is_array($body['members'])) {
                return '';
            }

            foreach ($body['members'] as $member) {
                if (! is_array($member) || empty($member['id'])) {
                    continue;
                }
                $name = isset($member['name']) ? strtolower((string) $member['name']) : '';
                $profile = isset($member['profile']) && is_array($member['profile']) ? $member['profile'] : array();
                $display_name = isset($profile['display_name']) ? strtolower((string) $profile['display_name']) : '';
                $real_name = isset($profile['real_name']) ? strtolower((string) $profile['real_name']) : '';
                $name_norm = self::normalized_identity_token($name);
                $display_norm = self::normalized_identity_token($display_name);
                $real_norm = self::normalized_identity_token($real_name);

                if (
                    $handle === $name ||
                    $handle === $display_name ||
                    $handle === $real_name ||
                    ('' !== $handle_norm && (
                        $handle_norm === $name_norm ||
                        $handle_norm === $display_norm ||
                        $handle_norm === $real_norm ||
                        ('' !== $display_norm && 0 === strpos($display_norm, $handle_norm)) ||
                        ('' !== $real_norm && 0 === strpos($real_norm, $handle_norm))
                    )) ||
                    ('' !== $handle_core_norm && (
                        $handle_core_norm === $name_norm ||
                        $handle_core_norm === $display_norm ||
                        $handle_core_norm === $real_norm ||
                        ('' !== $name_norm && false !== strpos($name_norm, $handle_core_norm)) ||
                        ('' !== $display_norm && false !== strpos($display_norm, $handle_core_norm)) ||
                        ('' !== $real_norm && false !== strpos($real_norm, $handle_core_norm))
                    ))
                ) {
                    return strtoupper((string) $member['id']);
                }
            }

            $cursor = '';
            if (isset($body['response_metadata']) && is_array($body['response_metadata']) && ! empty($body['response_metadata']['next_cursor'])) {
                $cursor = (string) $body['response_metadata']['next_cursor'];
            }
            if ('' === $cursor) {
                break;
            }
        }

        return '';
    }

    private static function slack_resolve_assignee_user($mention, $settings) {
        $mention = trim((string) $mention);
        if ('' === $mention) {
            return 0;
        }

        $resolved_user_id = 0;
        if (preg_match('/^<@([A-Z0-9]+)(?:\\|[^>]+)?>$/', $mention, $m)) {
            $resolved_user_id = self::slack_user_id_by_slack_id($m[1], $settings);
        } elseif (preg_match('/^@?[UW][A-Z0-9]+$/', strtoupper($mention))) {
            $resolved_user_id = self::slack_user_id_by_slack_id(ltrim($mention, '@'), $settings);
        } else {
            // Strict Slack resolution:
            // @handle -> Slack user ID via Slack API -> mapped Slack ID in settings.
            $slack_handle = trim(ltrim($mention, '@'));
            if ('' === $slack_handle) {
                return 0;
            }
            $slack_id_from_handle = self::slack_lookup_user_id_by_handle($slack_handle, $settings);
            if ('' !== $slack_id_from_handle) {
                $resolved_user_id = self::slack_user_id_by_slack_id($slack_id_from_handle, $settings);
            }
        }

        if ($resolved_user_id < 1) {
            return 0;
        }

        $assignable_users = self::get_assignable_users();
        $assignable_ids = wp_list_pluck($assignable_users, 'ID');
        if (! in_array($resolved_user_id, $assignable_ids, true)) {
            return 0;
        }
        return $resolved_user_id;
    }

    private static function slack_verify_request($request, $settings) {
        $secret = isset($settings['slack_signing_secret']) ? trim((string) $settings['slack_signing_secret']) : '';
        if ('' === $secret) {
            return false;
        }

        $timestamp = $request->get_header('x_slack_request_timestamp');
        $signature = $request->get_header('x_slack_signature');
        if ('' === $timestamp || '' === $signature) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $body = (string) $request->get_body();
        $basestring = 'v0:' . $timestamp . ':' . $body;
        $expected = 'v0=' . hash_hmac('sha256', $basestring, $secret);

        return hash_equals($expected, $signature);
    }

    private static function slack_send_message($text, $settings, $channel_id = '') {
        $token = isset($settings['slack_bot_token']) ? trim((string) $settings['slack_bot_token']) : '';
        $channel = '' !== $channel_id ? $channel_id : (isset($settings['slack_channel_id']) ? trim((string) $settings['slack_channel_id']) : '');
        if ('' === $token || '' === $channel || '' === trim((string) $text)) {
            return false;
        }

        $response = wp_remote_post(
            'https://slack.com/api/chat.postMessage',
            array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json; charset=utf-8',
                ),
                'body'    => wp_json_encode(
                    array(
                        'channel' => $channel,
                        'text'    => (string) $text,
                    )
                ),
            )
        );

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($body) && ! empty($body['ok']);
    }

    private static function slack_send_dm_to_user($slack_user_id, $text, $settings) {
        $token = isset($settings['slack_bot_token']) ? trim((string) $settings['slack_bot_token']) : '';
        $slack_user_id = strtoupper(trim((string) $slack_user_id));
        if ('' === $token || '' === $slack_user_id || '' === trim((string) $text)) {
            return false;
        }

        $open_response = wp_remote_post(
            'https://slack.com/api/conversations.open',
            array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json; charset=utf-8',
                ),
                'body'    => wp_json_encode(
                    array(
                        'users' => $slack_user_id,
                    )
                ),
            )
        );
        if (is_wp_error($open_response)) {
            return self::slack_send_message($text, $settings, $slack_user_id);
        }
        $open_body = json_decode((string) wp_remote_retrieve_body($open_response), true);
        if (! is_array($open_body) || empty($open_body['ok']) || empty($open_body['channel']['id'])) {
            return self::slack_send_message($text, $settings, $slack_user_id);
        }

        return self::slack_send_message($text, $settings, (string) $open_body['channel']['id']);
    }

    private static function slack_notify_task_assignment($task, $settings) {
        if (empty($settings['slack_enabled']) || empty($task)) {
            return;
        }

        $assigned_to = isset($task->assigned_to) ? (int) $task->assigned_to : 0;
        if ($assigned_to < 1) {
            return;
        }

        $slack_id = self::slack_id_by_user_id($assigned_to, $settings);
        $assigned_by_name = self::user_name((int) $task->created_by);
        $due = ! empty($task->due_date) ? (string) $task->due_date : 'No due date';
        $mention = '' !== $slack_id ? '<@' . $slack_id . '>' : self::user_name($assigned_to);
        $msg = sprintf(
            'New task assigned to %s by %s: %s (Due: %s, Priority: %s, Reward: +%d)',
            $mention,
            $assigned_by_name,
            (string) $task->title,
            $due,
            ucfirst((string) $task->priority),
            (int) $task->reward_points
        );

        if (! empty($settings['slack_notify_on_assign'])) {
            self::slack_send_message($msg, $settings);
        }

        if (! empty($settings['slack_notify_on_assign_dm']) && '' !== $slack_id) {
            $dm_msg = sprintf(
                'You have a new task from %s: %s (Due: %s, Priority: %s, Reward: +%d)',
                $assigned_by_name,
                (string) $task->title,
                $due,
                ucfirst((string) $task->priority),
                (int) $task->reward_points
            );
            self::slack_send_dm_to_user($slack_id, $dm_msg, $settings);
        }
    }

    public static function handle_slack_command($request) {
        $settings = self::get_settings();
        if (empty($settings['slack_enabled']) || empty($settings['slack_allow_create_from_command'])) {
            return new WP_REST_Response(
                array(
                    'response_type' => 'ephemeral',
                    'text'          => 'Slack task integration is disabled.',
                ),
                200
            );
        }

        if (! self::slack_verify_request($request, $settings)) {
            return new WP_REST_Response(
                array(
                    'response_type' => 'ephemeral',
                    'text'          => 'Invalid Slack signature.',
                ),
                403
            );
        }

        $params = $request->get_params();
        $text = isset($params['text']) ? trim((string) $params['text']) : '';
        $channel_id = isset($params['channel_id']) ? trim((string) $params['channel_id']) : '';
        $from_slack_user_id = isset($params['user_id']) ? trim((string) $params['user_id']) : '';

        $allowed_channel = isset($settings['slack_channel_id']) ? trim((string) $settings['slack_channel_id']) : '';
        $allowed_channel = ltrim($allowed_channel, '#');
        if ('' !== $allowed_channel) {
            $allowed_channel = strtoupper($allowed_channel);
        }
        $incoming_channel = strtoupper(trim((string) $channel_id));
        if ('' !== $allowed_channel && '' !== $incoming_channel && $incoming_channel !== $allowed_channel) {
            return new WP_REST_Response(
                array(
                    'response_type' => 'ephemeral',
                    'text'          => 'This command is not allowed in this channel. Expected channel ID: ' . $allowed_channel . ', got: ' . $incoming_channel,
                ),
                200
            );
        }

        $parts = array_map('trim', explode('|', $text));
        if (count($parts) < 2) {
            return new WP_REST_Response(
                array(
                    'response_type' => 'ephemeral',
                    'text'          => 'Use format: @username|Task title|Description|due:YYYY-MM-DD|priority:low|reward:10',
                ),
                200
            );
        }

        $assignee_token = $parts[0];
        $title = isset($parts[1]) ? sanitize_text_field($parts[1]) : '';
        $description = isset($parts[2]) ? sanitize_textarea_field($parts[2]) : '';
        if ('' === $title) {
            return new WP_REST_Response(
                array(
                    'response_type' => 'ephemeral',
                    'text'          => 'Task title is required.',
                ),
                200
            );
        }

        $assigned_to = self::slack_resolve_assignee_user($assignee_token, $settings);
        if ($assigned_to < 1) {
            return new WP_REST_Response(
                array(
                    'response_type' => 'ephemeral',
                    'text'          => 'Assignee not mapped. Use Slack mention (<@U...>) or map Slack username to Slack User ID in plugin settings (Slack scope users:read required for @username). Provided assignee: ' . $assignee_token,
                ),
                200
            );
        }

        $priority = 'medium';
        $due_date = null;
        $reward_points = self::default_reward_for_priority($priority);
        foreach ($parts as $idx => $part) {
            if ($idx < 3) {
                continue;
            }
            if (0 === stripos($part, 'due:')) {
                $raw_due = trim(substr($part, 4));
                $date = date_create_from_format('Y-m-d', $raw_due);
                if ($date && $date->format('Y-m-d') === $raw_due) {
                    $due_date = $raw_due;
                }
            } elseif (0 === stripos($part, 'priority:')) {
                $raw_priority = sanitize_key(trim(substr($part, 9)));
                if (in_array($raw_priority, array('low', 'medium', 'high'), true)) {
                    $priority = $raw_priority;
                }
            } elseif (0 === stripos($part, 'reward:')) {
                $reward_points = max(0, absint(trim(substr($part, 7))));
            }
        }
        if ($reward_points < 1) {
            $reward_points = self::default_reward_for_priority($priority);
        }

        global $wpdb;
        $created_by = self::slack_user_id_by_slack_id($from_slack_user_id, $settings);
        if ($created_by < 1) {
            $created_by = self::slack_default_created_by();
        }
        $status = isset($settings['default_status']) ? (string) $settings['default_status'] : 'todo';
        $now = current_time('mysql');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert(
            self::table_name(),
            array(
                'title'         => $title,
                'description'   => $description,
                'status'        => $status,
                'priority'      => $priority,
                'due_date'      => $due_date,
                'assigned_to'   => $assigned_to,
                'reward_points' => $reward_points,
                'created_by'    => $created_by,
                'created_at'    => $now,
                'updated_at'    => $now,
                'completed_at'  => 'done' === $status ? $now : null,
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s')
        );

        if (false === $inserted) {
            $db_error = isset($wpdb->last_error) ? (string) $wpdb->last_error : '';
            return new WP_REST_Response(
                array(
                    'response_type' => 'ephemeral',
                    'text'          => 'Task creation failed in WordPress. ' . ($db_error !== '' ? 'DB error: ' . $db_error : 'Please check plugin database migration.'),
                ),
                200
            );
        }

        $task_id = (int) $wpdb->insert_id;
        $task = $task_id > 0 ? self::get_task($task_id) : null;
        if ($task) {
            self::slack_notify_task_assignment($task, $settings);
        }

        return new WP_REST_Response(
            array(
                'response_type' => 'in_channel',
                'text'          => sprintf('Task created and assigned to %s: %s', self::user_name($assigned_to), $title),
            ),
            200
        );
    }

    private static function default_reward_for_priority($priority) {
        $settings = self::get_settings();

        if ('high' === $priority) {
            return (int) $settings['reward_high'];
        }

        if ('low' === $priority) {
            return (int) $settings['reward_low'];
        }

        return (int) $settings['reward_medium'];
    }

    private static function get_assignable_users() {
        $settings = self::get_settings();

        $users = get_users(
            array(
                'role__in' => $settings['access_roles'],
                'orderby'  => 'display_name',
                'order'    => 'ASC',
            )
        );

        return is_array($users) ? $users : array();
    }

    private static function user_name($user_id) {
        $user_id = absint($user_id);
        if ($user_id < 1) {
            return __('Unassigned', 'neura-task-manager');
        }

        $user = get_user_by('id', $user_id);
        if (! $user) {
            return __('Unknown user', 'neura-task-manager');
        }

        return $user->display_name;
    }

    private static function current_user_has_role($allowed_roles) {
        $user = wp_get_current_user();

        if (! $user || empty($user->roles)) {
            return false;
        }

        foreach ($user->roles as $role) {
            if (in_array($role, $allowed_roles, true)) {
                return true;
            }
        }

        return false;
    }

    private static function user_has_any_role($user, $allowed_roles) {
        if (! $user || empty($user->roles)) {
            return false;
        }

        foreach ($user->roles as $role) {
            if (in_array($role, $allowed_roles, true)) {
                return true;
            }
        }

        return false;
    }

    private static function current_user_can_manage_tasks() {
        $settings = self::get_settings();

        return self::current_user_has_role($settings['access_roles']);
    }

    private static function current_user_can_manage_settings() {
        $settings = self::get_settings();

        return self::current_user_has_role($settings['settings_roles']);
    }

    private static function current_user_can_access_frontend_dashboard() {
        $settings = self::get_settings();

        if (empty($settings['frontend_enabled'])) {
            return false;
        }

        return self::current_user_has_role($settings['frontend_roles']);
    }

    private static function auto_refresh_allowed_for_scope($scope, $is_settings_manager) {
        if ('admin_editor_only' === $scope) {
            return (bool) $is_settings_manager;
        }

        if ('users_only' === $scope) {
            return ! $is_settings_manager;
        }

        return true;
    }

    private static function current_user_can_view_task_item($task) {
        if (! self::current_user_can_manage_tasks()) {
            return false;
        }

        $settings = self::get_settings();
        if (self::current_user_can_manage_settings()) {
            return true;
        }

        if (! empty($settings['employee_own_tasks_only'])) {
            $user_id = get_current_user_id();

            return (int) $task->created_by === $user_id || (int) $task->assigned_to === $user_id;
        }

        if ('own' === $settings['visibility']) {
            $user_id = get_current_user_id();

            return (int) $task->created_by === $user_id || (int) $task->assigned_to === $user_id;
        }

        return true;
    }

    private static function current_user_can_edit_task_item($task) {
        if (! self::current_user_can_view_task_item($task)) {
            return false;
        }

        if (self::current_user_can_manage_settings()) {
            return true;
        }

        $settings = self::get_settings();

        return ! empty($settings['allow_user_edit']);
    }

    private static function current_user_can_delete_task_item($task) {
        if (! self::current_user_can_view_task_item($task)) {
            return false;
        }

        if (self::current_user_can_manage_settings()) {
            return true;
        }

        $settings = self::get_settings();

        return ! empty($settings['allow_user_delete']);
    }

    private static function current_user_can_change_task_status_item($task) {
        if (self::current_user_can_view_task_item($task)) {
            return true;
        }

        if (! is_user_logged_in() || ! self::current_user_can_access_frontend_dashboard()) {
            return false;
        }

        $user_id = get_current_user_id();
        return (int) $task->assigned_to === $user_id || (int) $task->created_by === $user_id;
    }

    public static function register_admin_menu() {
        add_menu_page(
            __('Task Manager', 'neura-task-manager'),
            __('Task Manager', 'neura-task-manager'),
            'read',
            'stm-task-manager',
            array(__CLASS__, 'render_admin_page'),
            self::menu_icon_url(),
            26
        );

        add_submenu_page(
            'stm-task-manager',
            __('Task Manager Settings', 'neura-task-manager'),
            __('Settings', 'neura-task-manager'),
            'read',
            'stm-task-manager-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }

    public static function handle_save_settings() {
        if (! self::current_user_can_manage_settings()) {
            wp_die(esc_html__('You are not allowed to manage settings.', 'neura-task-manager'));
        }

        check_admin_referer('stm_save_settings');

        $roles         = self::available_roles();
        $access_roles   = self::unslashed_post_array('access_roles');
        $setting_roles  = self::unslashed_post_array('settings_roles');
        $frontend_roles = self::unslashed_post_array('frontend_roles');
        $slack_user_map = self::unslashed_post_array('slack_user_map');

        $settings = array(
            'access_roles'      => array_values(array_intersect(array_map('sanitize_key', $access_roles), array_keys($roles))),
            'settings_roles'    => array_values(array_intersect(array_map('sanitize_key', $setting_roles), array_keys($roles))),
            'frontend_roles'    => array_values(array_intersect(array_map('sanitize_key', $frontend_roles), array_keys($roles))),
            'frontend_enabled'  => isset($_POST['frontend_enabled']) ? 1 : 0,
            'frontend_page_id'  => isset($_POST['frontend_page_id']) ? absint($_POST['frontend_page_id']) : 0,
            'frontend_redirect' => isset($_POST['frontend_redirect']) ? 1 : 0,
            'frontend_tasks_home_url' => isset($_POST['frontend_tasks_home_url']) ? esc_url_raw(wp_unslash($_POST['frontend_tasks_home_url'])) : '',
            'frontend_login_url' => isset($_POST['frontend_login_url']) ? esc_url_raw(wp_unslash($_POST['frontend_login_url'])) : '',
            'frontend_login_redirect_param' => isset($_POST['frontend_login_redirect_param']) ? sanitize_text_field(wp_unslash($_POST['frontend_login_redirect_param'])) : 'redirect_to',
            'frontend_logout_redirect_url' => isset($_POST['frontend_logout_redirect_url']) ? esc_url_raw(wp_unslash($_POST['frontend_logout_redirect_url'])) : '',
            'guest_login_title' => isset($_POST['guest_login_title']) ? sanitize_text_field(wp_unslash($_POST['guest_login_title'])) : 'Please Log In To View Tasks',
            'guest_login_subtitle' => isset($_POST['guest_login_subtitle']) ? sanitize_text_field(wp_unslash($_POST['guest_login_subtitle'])) : 'Access your task dashboard, track progress, and check reward points after signing in.',
            'guest_login_button_text' => isset($_POST['guest_login_button_text']) ? sanitize_text_field(wp_unslash($_POST['guest_login_button_text'])) : 'Login To Continue',
            'guest_login_note' => isset($_POST['guest_login_note']) ? sanitize_text_field(wp_unslash($_POST['guest_login_note'])) : 'Need access? Contact your administrator for task dashboard permissions.',
            'guest_login_button_color_start' => isset($_POST['guest_login_button_color_start']) ? sanitize_text_field(wp_unslash($_POST['guest_login_button_color_start'])) : '#2563eb',
            'guest_login_button_color_end' => isset($_POST['guest_login_button_color_end']) ? sanitize_text_field(wp_unslash($_POST['guest_login_button_color_end'])) : '#4f46e5',
            'guest_login_button_text_color' => isset($_POST['guest_login_button_text_color']) ? sanitize_text_field(wp_unslash($_POST['guest_login_button_text_color'])) : '#ffffff',
            'guest_login_button_radius' => isset($_POST['guest_login_button_radius']) ? absint($_POST['guest_login_button_radius']) : 12,
            'dashboard_title'   => isset($_POST['dashboard_title']) ? sanitize_text_field(wp_unslash($_POST['dashboard_title'])) : 'Daily Taks',
            'frontend_force_tasks_only' => isset($_POST['frontend_force_tasks_only']) ? 1 : 0,
            'employee_own_tasks_only' => isset($_POST['employee_own_tasks_only']) ? 1 : 0,
            'admin_employee_filter_enabled' => isset($_POST['admin_employee_filter_enabled']) ? 1 : 0,
            'auto_refresh_enabled'  => isset($_POST['auto_refresh_enabled']) ? 1 : 0,
            'auto_refresh_frontend' => isset($_POST['auto_refresh_frontend']) ? 1 : 0,
            'auto_refresh_backend'  => isset($_POST['auto_refresh_backend']) ? 1 : 0,
            'auto_refresh_user_scope' => isset($_POST['auto_refresh_user_scope']) ? sanitize_key(wp_unslash($_POST['auto_refresh_user_scope'])) : 'all',
            'auto_refresh_only_visible' => isset($_POST['auto_refresh_only_visible']) ? 1 : 0,
            'auto_refresh_interval' => isset($_POST['auto_refresh_interval']) ? absint($_POST['auto_refresh_interval']) : 60,
            'tasks_per_page' => isset($_POST['tasks_per_page']) ? absint($_POST['tasks_per_page']) : 10,
            'slack_enabled' => isset($_POST['slack_enabled']) ? 1 : 0,
            'slack_signing_secret' => isset($_POST['slack_signing_secret']) ? sanitize_text_field(wp_unslash($_POST['slack_signing_secret'])) : '',
            'slack_bot_token' => isset($_POST['slack_bot_token']) ? sanitize_text_field(wp_unslash($_POST['slack_bot_token'])) : '',
            'slack_channel_id' => isset($_POST['slack_channel_id']) ? sanitize_text_field(wp_unslash($_POST['slack_channel_id'])) : '',
            'slack_notify_on_assign' => isset($_POST['slack_notify_on_assign']) ? 1 : 0,
            'slack_notify_on_assign_dm' => isset($_POST['slack_notify_on_assign_dm']) ? 1 : 0,
            'slack_allow_create_from_command' => isset($_POST['slack_allow_create_from_command']) ? 1 : 0,
            'slack_user_map' => $slack_user_map,
            'visibility'        => isset($_POST['visibility']) ? sanitize_key(wp_unslash($_POST['visibility'])) : 'all',
            'default_status'    => isset($_POST['default_status']) ? sanitize_key(wp_unslash($_POST['default_status'])) : 'todo',
            'default_priority'  => isset($_POST['default_priority']) ? sanitize_key(wp_unslash($_POST['default_priority'])) : 'medium',
            'require_due_date'  => isset($_POST['require_due_date']) ? 1 : 0,
            'allow_user_edit'   => isset($_POST['allow_user_edit']) ? 1 : 0,
            'allow_user_delete' => isset($_POST['allow_user_delete']) ? 1 : 0,
            'rewards_enabled'   => isset($_POST['rewards_enabled']) ? 1 : 0,
            'reward_low'        => isset($_POST['reward_low']) ? absint($_POST['reward_low']) : 5,
            'reward_medium'     => isset($_POST['reward_medium']) ? absint($_POST['reward_medium']) : 10,
            'reward_high'       => isset($_POST['reward_high']) ? absint($_POST['reward_high']) : 20,
            'reward_award_mode' => isset($_POST['reward_award_mode']) ? sanitize_key(wp_unslash($_POST['reward_award_mode'])) : 'once',
        );

        update_option(self::OPTION_SETTINGS, self::sanitize_settings($settings));

        self::redirect_with_notice('success', 'Settings saved successfully.', 'stm-task-manager-settings');
    }

    public static function handle_manage_leaderboard() {
        if (! self::current_user_can_manage_settings()) {
            wp_die(esc_html__('You are not allowed to manage leaderboard.', 'neura-task-manager'));
        }

        check_admin_referer('stm_manage_leaderboard');

        $operation = isset($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';

        if ('reset_all' === $operation) {
            if (! self::is_leaderboard_reset_available()) {
                self::redirect_with_notice('error', 'Reset leaderboard is available in Pro.', 'stm-task-manager-settings');
            }
            delete_metadata('user', 0, self::USER_POINTS_META, '', true);
            self::redirect_with_notice('success', 'Leaderboard points reset for all users.', 'stm-task-manager-settings');
        }

        if ('remove_user' === $operation) {
            $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
            if ($user_id < 1) {
                self::redirect_with_notice('error', 'Please select a user.', 'stm-task-manager-settings');
            }

            delete_user_meta($user_id, self::USER_POINTS_META);
            self::redirect_with_notice('success', 'User removed from leaderboard.', 'stm-task-manager-settings');
        }

        if ('set_points' === $operation) {
            $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
            $points  = isset($_POST['points']) ? max(0, absint($_POST['points'])) : 0;

            if ($user_id < 1) {
                self::redirect_with_notice('error', 'Please select a user.', 'stm-task-manager-settings');
            }

            update_user_meta($user_id, self::USER_POINTS_META, $points);
            self::redirect_with_notice('success', 'Leaderboard points updated.', 'stm-task-manager-settings');
        }

        self::redirect_with_notice('error', 'Invalid leaderboard action.', 'stm-task-manager-settings');
    }

    public static function handle_task_data_tools() {
        if (! self::is_data_tools_available()) {
            self::redirect_with_notice('error', 'Task Data Tools are available in Pro.');
        }

        if (! self::current_user_can_manage_settings()) {
            wp_die(esc_html__('You are not allowed to manage task data tools.', 'neura-task-manager'));
        }

        check_admin_referer('stm_task_data_tools');

        $operation = isset($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';

        if ('reset_tasks' === $operation) {
            global $wpdb;
            $table_name = self::escaped_table_name(self::table_name());
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $result = $wpdb->query("TRUNCATE TABLE {$table_name}");
            if (false === $result) {
                self::redirect_with_notice('error', 'Failed to reset tasks.', 'stm-task-manager-settings');
            }

            self::redirect_with_notice('success', 'All tasks were reset successfully.', 'stm-task-manager-settings');
        }

        if ('export_tasks' === $operation) {
            $format = isset($_POST['export_format']) ? sanitize_key(wp_unslash($_POST['export_format'])) : 'csv';
            self::download_task_export($format);
        }

        self::redirect_with_notice('error', 'Invalid task data tools operation.', 'stm-task-manager-settings');
    }

    private static function export_task_rows() {
        global $wpdb;
        $table_name = self::escaped_table_name(self::table_name());
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC");
        if (! is_array($rows)) {
            return array();
        }

        $statuses = self::allowed_statuses();
        $priorities = self::allowed_priorities();
        $export_rows = array();

        foreach ($rows as $row) {
            $status_key = isset($row->status) ? (string) $row->status : '';
            $priority_key = isset($row->priority) ? (string) $row->priority : '';
            $export_rows[] = array(
                'ID' => (int) $row->id,
                'Title' => (string) $row->title,
                'Description' => (string) $row->description,
                'Status' => isset($statuses[$status_key]) ? (string) $statuses[$status_key] : $status_key,
                'Priority' => isset($priorities[$priority_key]) ? (string) $priorities[$priority_key] : $priority_key,
                'Due Date' => ! empty($row->due_date) ? (string) $row->due_date : 'No due date',
                'Reward Points' => (int) $row->reward_points,
                'Assigned To' => self::user_name((int) $row->assigned_to),
                'Assigned By' => self::user_name((int) $row->created_by),
                'Created At' => (string) $row->created_at,
                'Updated At' => (string) $row->updated_at,
            );
        }

        return $export_rows;
    }

    private static function download_task_export($format) {
        $rows = self::export_task_rows();
        $headers = array('ID', 'Title', 'Description', 'Status', 'Priority', 'Due Date', 'Reward Points', 'Assigned To', 'Assigned By', 'Created At', 'Updated At');
        $timestamp = current_time('Y-m-d-H-i-s');

        if ('csv' === $format) {
            nocache_headers();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="stm-tasks-' . $timestamp . '.csv"');

            $out = fopen('php://output', 'w');
            if ($out) {
                fputcsv($out, $headers);
                foreach ($rows as $row) {
                    fputcsv($out, array_values($row));
                }
            }
            exit;
        }

        if ('xlsx' === $format) {
            nocache_headers();
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="stm-tasks-' . $timestamp . '.xls"');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The helper returns a complete export document for download.
            echo self::task_export_table_html($headers, $rows, false);
            exit;
        }

        if ('pdf' === $format) {
            nocache_headers();
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: attachment; filename="stm-tasks-' . $timestamp . '-pdf-ready.html"');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The helper returns a complete export document for download.
            echo self::task_export_table_html($headers, $rows, true);
            exit;
        }

        self::redirect_with_notice('error', 'Invalid export format.', 'stm-task-manager-settings');
    }

    private static function task_export_table_html($headers, $rows, $pdf_ready = false) {
        ob_start();
        ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8" />
            <title>Task Export</title>
        </head>
        <body style="font-family:Arial,sans-serif;margin:20px;color:#111827;">
            <h1 style="margin:0 0 10px;font-size:20px;">Simple Task Management - Task Export</h1>
            <p style="margin:0 0 14px;color:#475569;font-size:12px;">Generated: <?php echo esc_html(current_time('Y-m-d H:i:s')); ?></p>
            <?php if ($pdf_ready) : ?>
                <p style="margin-bottom:12px;font-size:12px;color:#334155;">Use your browser Print option and select "Save as PDF".</p>
            <?php endif; ?>
            <table style="border-collapse:collapse;width:100%;table-layout:fixed;">
                <thead>
                    <tr>
                        <?php foreach ($headers as $header_col) : ?>
                            <th style="border:1px solid #cbd5e1;padding:8px;font-size:12px;text-align:left;vertical-align:top;word-wrap:break-word;background:#f1f5f9;font-weight:700;"><?php echo esc_html((string) $header_col); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)) : ?>
                        <tr><td colspan="<?php echo esc_attr((string) count($headers)); ?>" style="border:1px solid #cbd5e1;padding:8px;font-size:12px;text-align:left;vertical-align:top;word-wrap:break-word;">No task data found.</td></tr>
                    <?php else : ?>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <?php foreach ($headers as $header_col) : ?>
                                    <td style="border:1px solid #cbd5e1;padding:8px;font-size:12px;text-align:left;vertical-align:top;word-wrap:break-word;"><?php echo esc_html(isset($row[$header_col]) ? (string) $row[$header_col] : ''); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php

        return (string) ob_get_clean();
    }

    private static function frontend_dashboard_url() {
        $settings = self::get_settings();

        if (empty($settings['frontend_page_id'])) {
            return '';
        }

        $url = get_permalink((int) $settings['frontend_page_id']);

        return $url ? $url : '';
    }

    private static function frontend_tasks_home_url() {
        $settings = self::get_settings();

        if (! empty($settings['frontend_tasks_home_url'])) {
            return (string) $settings['frontend_tasks_home_url'];
        }

        $frontend_page_url = self::frontend_dashboard_url();
        if ('' !== $frontend_page_url) {
            return $frontend_page_url;
        }

        return home_url('/tasks/');
    }

    private static function frontend_login_url($redirect_target = '') {
        $settings = self::get_settings();
        $redirect_target = '' !== $redirect_target ? $redirect_target : self::frontend_tasks_home_url();
        $login_url = ! empty($settings['frontend_login_url']) ? (string) $settings['frontend_login_url'] : '';

        if ('' === $login_url) {
            return wp_login_url($redirect_target);
        }

        $param = isset($settings['frontend_login_redirect_param']) ? (string) $settings['frontend_login_redirect_param'] : 'redirect_to';
        $param = preg_replace('/[^a-zA-Z0-9_-]/', '', $param);
        if ('' === $param) {
            $param = 'redirect_to';
        }

        return add_query_arg($param, $redirect_target, $login_url);
    }

    public static function filter_login_redirect($redirect_to, $requested_redirect_to, $user) {
        if (is_wp_error($user) || ! $user) {
            return $redirect_to;
        }

        $settings = self::get_settings();
        $is_frontend_role = self::user_has_any_role($user, $settings['frontend_roles']);
        $is_settings_role = self::user_has_any_role($user, $settings['settings_roles']);
        $frontend_url = self::frontend_tasks_home_url();

        if (
            ! empty($settings['frontend_enabled']) &&
            ! empty($settings['frontend_force_tasks_only']) &&
            $is_frontend_role &&
            ! $is_settings_role &&
            '' !== $frontend_url
        ) {
            return $frontend_url;
        }

        $requested_redirect_to = is_string($requested_redirect_to) ? trim($requested_redirect_to) : '';
        if ('' !== $requested_redirect_to) {
            $safe_requested = wp_validate_redirect($requested_redirect_to, '');
            if ('' !== $safe_requested) {
                return $safe_requested;
            }
        }

        if (empty($settings['frontend_enabled']) || empty($settings['frontend_redirect'])) {
            return $redirect_to;
        }

        if (! $is_frontend_role) {
            return $redirect_to;
        }

        if ('' === $frontend_url) {
            return $redirect_to;
        }

        return $frontend_url;
    }

    public static function filter_logout_redirect($redirect_to, $requested_redirect_to, $user) {
        $settings = self::get_settings();
        $custom_logout_url = isset($settings['frontend_logout_redirect_url']) ? trim((string) $settings['frontend_logout_redirect_url']) : '';

        if ('' === $custom_logout_url) {
            return $redirect_to;
        }

        $safe_custom = wp_validate_redirect($custom_logout_url, '');
        if ('' !== $safe_custom) {
            return $safe_custom;
        }

        return $redirect_to;
    }

    public static function maybe_restrict_dashboard_access() {
        if (! is_admin() || ! is_user_logged_in()) {
            return;
        }

        if ((function_exists('wp_doing_ajax') && wp_doing_ajax()) || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        global $pagenow;
        if (in_array((string) $pagenow, array('admin-post.php', 'admin-ajax.php'), true)) {
            return;
        }

        $settings = self::get_settings();
        if (empty($settings['frontend_enabled']) || empty($settings['frontend_force_tasks_only'])) {
            return;
        }

        $user = wp_get_current_user();
        if (! $user || ! self::user_has_any_role($user, $settings['frontend_roles'])) {
            return;
        }

        if (self::user_has_any_role($user, $settings['settings_roles'])) {
            return;
        }

        $frontend_url = self::frontend_tasks_home_url();
        if ('' === $frontend_url) {
            return;
        }

        wp_safe_redirect($frontend_url);
        exit;
    }

    public static function handle_save_task() {
        if (! self::current_user_can_manage_tasks()) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'neura-task-manager'));
        }

        check_admin_referer('stm_save_task');

        global $wpdb;

        $task_id       = isset($_POST['task_id']) ? absint($_POST['task_id']) : 0;
        $title         = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $description   = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        $settings      = self::get_settings();
        $priority      = isset($_POST['priority']) ? sanitize_key(wp_unslash($_POST['priority'])) : $settings['default_priority'];
        $due_date_raw  = isset($_POST['due_date']) ? sanitize_text_field(wp_unslash($_POST['due_date'])) : '';
        $assigned_to   = isset($_POST['assigned_to']) ? absint($_POST['assigned_to']) : 0;
        $reward_raw    = isset($_POST['reward_points']) ? sanitize_text_field(wp_unslash($_POST['reward_points'])) : '';

        $allowed_priorities = self::allowed_priorities();
        $assignable_users   = self::get_assignable_users();
        $assignable_ids     = wp_list_pluck($assignable_users, 'ID');

        if ('' === $title) {
            self::redirect_with_notice('error', 'Task title is required.');
        }

        if (! in_array($priority, array_keys($allowed_priorities), true)) {
            $priority = $settings['default_priority'];
        }

        if ($assigned_to > 0 && ! in_array($assigned_to, $assignable_ids, true)) {
            self::redirect_with_notice('error', 'Selected assignee is not allowed for task access roles.');
        }

        $due_date = null;
        if ('' !== $due_date_raw) {
            $date = date_create_from_format('Y-m-d', $due_date_raw);
            if ($date && $date->format('Y-m-d') === $due_date_raw) {
                $due_date = $due_date_raw;
            } else {
                self::redirect_with_notice('error', 'Due date must be in YYYY-MM-DD format.');
            }
        }

        if (! $due_date && ! empty($settings['require_due_date'])) {
            self::redirect_with_notice('error', 'Due date is required by current plugin settings.');
        }

        $reward_points = '' === trim($reward_raw) ? self::default_reward_for_priority($priority) : absint($reward_raw);
        $now           = current_time('mysql');

        if ($task_id < 1 && ! self::current_user_can_manage_settings()) {
            self::redirect_with_notice('error', 'Only admin/editor can create new tasks.');
        }

        if ($task_id > 0) {
            $task = self::get_task($task_id);

            if (! $task) {
                self::redirect_with_notice('error', 'Task not found.');
            }

            if (! self::current_user_can_edit_task_item($task)) {
                self::redirect_with_notice('error', 'You are not allowed to edit this task.');
            }

            $status = $task->status;
            $previous_assigned_to = (int) $task->assigned_to;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                self::table_name(),
                array(
                    'title'         => $title,
                    'description'   => $description,
                    'status'        => $status,
                    'priority'      => $priority,
                    'due_date'      => $due_date,
                    'assigned_to'   => $assigned_to > 0 ? $assigned_to : null,
                    'reward_points' => $reward_points,
                    'updated_at'    => $now,
                ),
                array('id' => $task_id),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s'),
                array('%d')
            );

            $updated_task = self::get_task($task_id);
            if ($updated_task && (int) $updated_task->assigned_to > 0 && (int) $updated_task->assigned_to !== $previous_assigned_to) {
                self::slack_notify_task_assignment($updated_task, $settings);
            }

            self::redirect_with_notice('success', 'Task updated successfully.');
        }

        $status = $settings['default_status'];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            self::table_name(),
            array(
                'title'         => $title,
                'description'   => $description,
                'status'        => $status,
                'priority'      => $priority,
                'due_date'      => $due_date,
                'assigned_to'   => $assigned_to > 0 ? $assigned_to : null,
                'reward_points' => $reward_points,
                'created_by'    => get_current_user_id(),
                'created_at'    => $now,
                'updated_at'    => $now,
                'completed_at'  => 'done' === $status ? $now : null,
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s')
        );

        $new_task = self::get_task((int) $wpdb->insert_id);
        self::maybe_award_points_for_completion(null, $new_task, get_current_user_id());
        if ($new_task && (int) $new_task->assigned_to > 0) {
            self::slack_notify_task_assignment($new_task, $settings);
        }

        self::redirect_with_notice('success', 'Task created successfully.');
    }

    public static function handle_delete_task() {
        if (! self::current_user_can_manage_tasks()) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'neura-task-manager'));
        }

        check_admin_referer('stm_delete_task');

        global $wpdb;

        $task_id = isset($_GET['task_id']) ? absint($_GET['task_id']) : 0;

        if ($task_id < 1) {
            self::redirect_with_notice('error', 'Invalid task id.');
        }

        $task = self::get_task($task_id);
        if (! $task) {
            self::redirect_with_notice('error', 'Task not found or already deleted.');
        }

        if (! self::current_user_can_delete_task_item($task)) {
            self::redirect_with_notice('error', 'You are not allowed to delete this task.');
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->delete(self::table_name(), array('id' => $task_id), array('%d'));

        if ($deleted) {
            self::redirect_with_notice('success', 'Task deleted successfully.');
        }

        self::redirect_with_notice('error', 'Task not found or already deleted.');
    }

    public static function handle_update_status() {
        if (! self::current_user_can_manage_tasks() && ! self::current_user_can_access_frontend_dashboard()) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'neura-task-manager'));
        }

        check_admin_referer('stm_update_status');

        global $wpdb;

        $task_id = isset($_GET['task_id']) ? absint($_GET['task_id']) : 0;
        $status  = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : 'todo';

        if (! in_array($status, array_keys(self::allowed_statuses()), true)) {
            self::redirect_with_notice('error', 'Invalid status value.');
        }

        if ($task_id < 1) {
            self::redirect_with_notice('error', 'Invalid task id.');
        }

        $task = self::get_task($task_id);
        if (! $task) {
            self::redirect_with_notice('error', 'Task not found.');
        }

        if (! self::current_user_can_change_task_status_item($task)) {
            self::redirect_with_notice('error', 'You are not allowed to update this task.');
        }

        if (
            in_array($status, array('in_progress', 'done'), true) &&
            ! empty($task->due_date) &&
            strtotime($task->due_date . ' 23:59:59') < current_time('timestamp')
        ) {
            if (self::supports_overdue_workflow() && ! in_array((string) $task->overdue_status, array('pending', 'approved'), true)) {
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update(
                    self::table_name(),
                    array(
                        'overdue_status' => 'required',
                        'updated_at'     => current_time('mysql'),
                    ),
                    array('id' => $task_id),
                    array('%s', '%s'),
                    array('%d')
                );
            }
            self::redirect_with_notice('error', 'Task is overdue. Please submit the overdue reason first.');
        }

        $update_data = array(
            'status'     => $status,
            'updated_at' => current_time('mysql'),
        );

        if ('done' === $status && 'done' !== $task->status) {
            $update_data['completed_at'] = current_time('mysql');

            if (self::supports_overdue_workflow() && self::is_task_completed_late($task, $update_data['completed_at'])) {
                $update_data['overdue_status'] = 'required';
            }
        }

        $formats = array();
        foreach ($update_data as $v) {
            if (is_int($v)) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(self::table_name(), $update_data, array('id' => $task_id), $formats, array('%d'));

        if (false === $updated) {
            self::redirect_with_notice('error', 'Failed to update task status.');
        }

        $updated_task = self::get_task($task_id);
        self::maybe_award_points_for_completion($task, $updated_task, get_current_user_id());

        self::redirect_with_notice('success', 'Task status updated.');
    }

    public static function handle_submit_overdue_reason() {
        if (! self::is_overdue_workflow_available()) {
            self::redirect_with_notice('error', 'Overdue workflow is available in Pro.');
        }

        if (! self::current_user_can_manage_tasks()) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'neura-task-manager'));
        }

        check_admin_referer('stm_submit_overdue_reason');

        global $wpdb;

        $task_id = isset($_POST['task_id']) ? absint($_POST['task_id']) : 0;
        $reason  = isset($_POST['overdue_reason']) ? sanitize_textarea_field(wp_unslash($_POST['overdue_reason'])) : '';

        if ($task_id < 1 || '' === $reason) {
            self::redirect_with_notice('error', 'Overdue reason is required.');
        }

        $task = self::get_task($task_id);
        if (! $task || ! self::current_user_can_view_task_item($task)) {
            self::redirect_with_notice('error', 'Task not found or access denied.');
        }

        if (! self::supports_overdue_workflow()) {
            self::redirect_with_notice('error', 'Overdue workflow is not available yet. Please open WP admin once to run plugin upgrade.');
        }

        $due_passed = ! empty($task->due_date) && strtotime($task->due_date . ' 23:59:59') < current_time('timestamp');
        $task_overdue_state = isset($task->overdue_status) ? (string) $task->overdue_status : 'none';

        if (! $due_passed && ! in_array($task_overdue_state, array('required', 'rejected', 'pending'), true)) {
            self::redirect_with_notice('error', 'This task is not overdue yet.');
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(
            self::table_name(),
            array(
                'overdue_reason'       => $reason,
                'overdue_status'       => 'pending',
                'overdue_submitted_at' => current_time('mysql'),
                'updated_at'           => current_time('mysql'),
            ),
            array('id' => $task_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        if (false === $updated) {
            self::redirect_with_notice('error', 'Failed to submit overdue reason. Please try again.');
        }

        $return_url = isset($_POST['stm_return']) ? esc_url_raw(wp_unslash($_POST['stm_return'])) : '';
        if ($return_url) {
            $url = remove_query_arg(array('stm_notice', 'stm_msg', 'edit_task'), $return_url);
            $url = add_query_arg(
                array(
                    'stm_notice'        => 'success',
                    'stm_msg'           => rawurlencode('Overdue reason submitted for review.'),
                    'stm_overdue_ok_id' => (int) $task_id,
                ),
                $url
            );
            wp_safe_redirect($url);
            exit;
        }

        self::redirect_with_notice('success', 'Overdue reason submitted for review.');
    }

    public static function handle_review_overdue_reason() {
        if (! self::is_overdue_workflow_available()) {
            self::redirect_with_notice('error', 'Overdue workflow is available in Pro.');
        }

        if (! self::current_user_can_manage_settings()) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'neura-task-manager'));
        }

        check_admin_referer('stm_review_overdue_reason');

        global $wpdb;

        $task_id = isset($_GET['task_id']) ? absint($_GET['task_id']) : 0;
        $decision = isset($_GET['decision']) ? sanitize_key(wp_unslash($_GET['decision'])) : '';
        $note = isset($_GET['note']) ? sanitize_text_field(wp_unslash($_GET['note'])) : '';

        if ($task_id < 1 || ! in_array($decision, array('accept', 'reject'), true)) {
            self::redirect_with_notice('error', 'Invalid overdue review request.');
        }

        if (! self::supports_overdue_workflow()) {
            self::redirect_with_notice('error', 'Overdue workflow is not available yet. Please open WP admin once to run plugin upgrade.');
        }

        $task = self::get_task($task_id);
        if (! $task) {
            self::redirect_with_notice('error', 'Task not found.');
        }

        $new_status = 'accept' === $decision ? 'approved' : 'rejected';
        $now = current_time('mysql');
        $review_update = array(
            'overdue_status'      => $new_status,
            'overdue_reviewed_by' => get_current_user_id(),
            'overdue_reviewed_at' => $now,
            'overdue_review_note' => $note,
            'updated_at'          => $now,
        );
        $review_formats = array('%s', '%d', '%s', '%s', '%s');

        if ('accept' === $decision && 'done' !== $task->status) {
            $review_update['status'] = 'done';
            $review_update['completed_at'] = $now;
            $review_formats[] = '%s';
            $review_formats[] = '%s';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(
            self::table_name(),
            $review_update,
            array('id' => $task_id),
            $review_formats,
            array('%d')
        );

        if (false === $updated) {
            self::redirect_with_notice('error', 'Failed to save overdue review.');
        }

        if ('accept' === $decision) {
            $latest_task = self::get_task($task_id);
            if ($latest_task && 'done' === $latest_task->status) {
                $recipient = self::points_recipient_for_task($latest_task, 0);
                self::award_points_to_user_for_task($latest_task, $recipient, 'overdue_approved');
            }
        }

        self::redirect_with_notice('success', 'Overdue reason review saved.');
    }

    private static function is_task_completed_late($task, $completed_at) {
        if (! $task || empty($task->due_date) || empty($completed_at)) {
            return false;
        }

        $due_ts = strtotime($task->due_date . ' 23:59:59');
        $comp_ts = strtotime($completed_at);

        return $due_ts && $comp_ts && $comp_ts > $due_ts;
    }

    private static function points_recipient_for_task($task, $fallback_user_id = 0) {
        if (! empty($task->assigned_to)) {
            return (int) $task->assigned_to;
        }
        if (! empty($task->created_by)) {
            return (int) $task->created_by;
        }

        return (int) $fallback_user_id;
    }

    private static function has_award_for_task($task_id) {
        global $wpdb;
        $table_name = self::escaped_table_name(self::reward_log_table_name());

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $id = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT id FROM {$table_name} WHERE task_id = %d LIMIT 1",
                (int) $task_id
            )
        );

        return (bool) $id;
    }

    private static function award_points_to_user_for_task($task, $user_id, $event) {
        if (! $task || $user_id < 1) {
            return;
        }

        $settings = self::get_settings();
        if (empty($settings['rewards_enabled'])) {
            return;
        }

        if ('once' === $settings['reward_award_mode'] && self::has_award_for_task($task->id)) {
            return;
        }

        $points = max(0, (int) $task->reward_points);
        if ($points < 1) {
            return;
        }

        global $wpdb;

        $current_points = (int) get_user_meta($user_id, self::USER_POINTS_META, true);
        update_user_meta($user_id, self::USER_POINTS_META, $current_points + $points);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            self::reward_log_table_name(),
            array(
                'user_id'    => $user_id,
                'task_id'    => (int) $task->id,
                'points'     => $points,
                'event'      => $event,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%d', '%s', '%s')
        );
    }

    private static function maybe_award_points_for_completion($old_task, $new_task, $completed_by) {
        $settings = self::get_settings();

        if (empty($settings['rewards_enabled']) || ! $new_task) {
            return;
        }

        $was_done = $old_task && 'done' === $old_task->status;
        $is_done  = 'done' === $new_task->status;

        if (! $is_done || $was_done) {
            return;
        }

        $recipient_user = self::points_recipient_for_task($new_task, (int) $completed_by);
        $completed_at   = ! empty($new_task->completed_at) ? $new_task->completed_at : current_time('mysql');
        $is_late        = self::is_task_completed_late($new_task, $completed_at);

        if ($is_late && (! isset($new_task->overdue_status) || ! in_array($new_task->overdue_status, array('approved'), true))) {
            return;
        }

        self::award_points_to_user_for_task($new_task, $recipient_user, 'completion');
    }

    private static function get_leaderboard($limit = 5) {
        $settings = self::get_settings();

        $users = get_users(
            array(
                'role__in'  => $settings['access_roles'],
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Needed for points leaderboard sorting.
                'meta_key'  => self::USER_POINTS_META,
                'orderby'   => 'meta_value_num',
                'order'     => 'DESC',
                'number'    => absint($limit),
                'fields'    => array('ID', 'display_name', 'user_email'),
            )
        );

        return is_array($users) ? $users : array();
    }

    private static function allowed_statuses() {
        return array(
            'todo'        => __('To Do', 'neura-task-manager'),
            'in_progress' => __('In Progress', 'neura-task-manager'),
            'done'        => __('Done', 'neura-task-manager'),
        );
    }

    private static function allowed_priorities() {
        return array(
            'low'    => __('Low', 'neura-task-manager'),
            'medium' => __('Medium', 'neura-task-manager'),
            'high'   => __('High', 'neura-task-manager'),
        );
    }

    private static function status_badge_class($status) {
        switch ($status) {
            case 'done':
                return 'stm-badge-done';
            case 'in_progress':
                return 'stm-badge-progress';
            default:
                return 'stm-badge-todo';
        }
    }

    private static function priority_badge_class($priority) {
        switch ($priority) {
            case 'high':
                return 'stm-badge-high';
            case 'low':
                return 'stm-badge-low';
            default:
                return 'stm-badge-medium';
        }
    }

    private static function medal_for_rank($rank) {
        if (1 === $rank) {
            return '🥇';
        }
        if (2 === $rank) {
            return '🥈';
        }
        if (3 === $rank) {
            return '🥉';
        }

        return '#';
    }

    private static function short_text($text, $limit = 80) {
        $text = trim((string) $text);
        if ('' === $text) {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text) <= $limit) {
                return $text;
            }
            return rtrim(mb_substr($text, 0, $limit - 1)) . '...';
        }

        if (strlen($text) <= $limit) {
            return $text;
        }
        return rtrim(substr($text, 0, $limit - 1)) . '...';
    }

    private static function redirect_with_notice($type, $text, $page = 'stm-task-manager') {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Used only as optional redirect destination after protected actions.
        $return_url = isset($_REQUEST['stm_return']) ? esc_url_raw(wp_unslash($_REQUEST['stm_return'])) : '';

        if ($return_url) {
            $url = remove_query_arg(array('stm_notice', 'stm_msg', 'edit_task'), $return_url);
            $url = add_query_arg(
                array(
                    'stm_notice' => sanitize_key($type),
                    'stm_msg'    => rawurlencode($text),
                ),
                $url
            );
        } else {
            $url = add_query_arg(
                array(
                    'page'       => $page,
                    'stm_notice' => sanitize_key($type),
                    'stm_msg'    => rawurlencode($text),
                ),
                admin_url('admin.php')
            );
        }

        wp_safe_redirect($url);
        exit;
    }

    private static function current_request_url() {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host   = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $uri    = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';

        return esc_url_raw($scheme . $host . $uri);
    }

    private static function supports_overdue_workflow() {
        static $supported = null;

        if (null !== $supported) {
            return $supported;
        }

        global $wpdb;
        $table_name = self::escaped_table_name(self::table_name());
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $column = $wpdb->get_var("SHOW COLUMNS FROM {$table_name} LIKE 'overdue_status'");
        $supported = ! empty($column);

        return $supported;
    }

    private static function get_task($task_id) {
        global $wpdb;
        $table_name = self::escaped_table_name(self::table_name());
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $task_id)
        );
    }

    private static function get_tasks($status_filter = '', $search_term = '', $assigned_user_id = 0) {
        global $wpdb;
        $table_name = self::escaped_table_name(self::table_name());

        $settings = self::get_settings();
        $where    = array();
        $args     = array();

        if ($status_filter && in_array($status_filter, array_keys(self::allowed_statuses()), true)) {
            $where[] = 'status = %s';
            $args[]  = $status_filter;
        }

        if ('' !== $search_term) {
            $where[] = '(title LIKE %s OR description LIKE %s)';
            $like    = '%' . $wpdb->esc_like($search_term) . '%';
            $args[]  = $like;
            $args[]  = $like;
        }

        if ($assigned_user_id > 0) {
            $where[] = 'assigned_to = %d';
            $args[]  = (int) $assigned_user_id;
        }

        if (! self::current_user_can_manage_settings() && ! empty($settings['employee_own_tasks_only'])) {
            $where[] = '(created_by = %d OR assigned_to = %d)';
            $args[]  = get_current_user_id();
            $args[]  = get_current_user_id();
        } elseif ('own' === $settings['visibility'] && ! self::current_user_can_manage_settings()) {
            $where[] = '(created_by = %d OR assigned_to = %d)';
            $args[]  = get_current_user_id();
            $args[]  = get_current_user_id();
        }

        $sql = "SELECT * FROM {$table_name}";

        if (! empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY FIELD(status, "todo", "in_progress", "done"), due_date IS NULL, due_date ASC, id DESC';

        if (! empty($args)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $sql = $wpdb->prepare($sql, $args);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query conditions are built with placeholders and prepared above when args exist.
        return $wpdb->get_results($sql);
    }

    private static function get_overdue_tasks_for_current_user() {
        if (! self::is_overdue_workflow_available()) {
            return array();
        }

        if (! self::supports_overdue_workflow()) {
            return array();
        }

        $all = self::get_tasks('', '');
        $results = array();
        $now = current_time('timestamp');

        foreach ($all as $task) {
            if (empty($task->due_date)) {
                continue;
            }

            $due_ts = strtotime($task->due_date . ' 23:59:59');
            if (! $due_ts || $due_ts >= $now) {
                continue;
            }

            $status = isset($task->overdue_status) ? (string) $task->overdue_status : 'none';
            if ('done' !== $task->status || in_array($status, array('required', 'pending', 'rejected'), true)) {
                $results[] = $task;
            }
        }

        return $results;
    }

    private static function get_pending_overdue_for_review() {
        if (! self::is_overdue_workflow_available()) {
            return array();
        }

        if (! self::supports_overdue_workflow()) {
            return array();
        }

        global $wpdb;
        $table_name = self::escaped_table_name(self::table_name());

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT * FROM {$table_name} WHERE overdue_status IN ('pending','required') ORDER BY due_date ASC, id DESC"
        );
    }

    public static function render_frontend_dashboard_shortcode() {
        if (! defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();

        $current_url = self::current_request_url();
        $base_return_url = remove_query_arg(array('stm_notice', 'stm_msg', 'edit_task', 'stm_overdue_ok_id', 'stmf_search', 'stmf_status', 'stmf_assigned_user_filter', 'stm_refresh'), $current_url);
        $tasks_page_url = self::frontend_tasks_home_url();
        $frontend_refresh_url = add_query_arg('stm_refresh', time(), $tasks_page_url);
        $settings = self::get_settings();
        $guest_login_title = (string) $settings['guest_login_title'];
        $guest_login_subtitle = (string) $settings['guest_login_subtitle'];
        $guest_login_button_text = (string) $settings['guest_login_button_text'];
        $guest_login_note = (string) $settings['guest_login_note'];
        $guest_btn_start = (string) $settings['guest_login_button_color_start'];
        $guest_btn_end = (string) $settings['guest_login_button_color_end'];
        $guest_btn_text = (string) $settings['guest_login_button_text_color'];
        $guest_btn_radius = (int) $settings['guest_login_button_radius'];

        if (! is_user_logged_in()) {
            self::enqueue_frontend_guest_assets();
            $login_url = self::frontend_login_url($tasks_page_url);
            $guest_style_vars = sprintf(
                '--neuratm-guest-btn-start:%1$s;--neuratm-guest-btn-end:%2$s;--neuratm-guest-btn-text:%3$s;--neuratm-guest-btn-radius:%4$dpx;',
                (string) $guest_btn_start,
                (string) $guest_btn_end,
                (string) $guest_btn_text,
                (int) $guest_btn_radius
            );
            ob_start();
            ?>
            <div class="stm-front stm-front-guest" style="<?php echo esc_attr($guest_style_vars); ?>">
                <div class="stm-guest-wrap">
                    <div class="stm-guest-icon">🔐</div>
                    <h2 class="stm-guest-title"><?php echo esc_html($guest_login_title); ?></h2>
                    <p class="stm-guest-sub"><?php echo esc_html($guest_login_subtitle); ?></p>
                    <a class="stm-guest-btn" href="<?php echo esc_url($login_url); ?>">
                        <span>→</span>
                        <?php echo esc_html($guest_login_button_text); ?>
                    </a>
                    <div class="stm-guest-note"><?php echo esc_html($guest_login_note); ?></div>
                    <div class="stm-guest-credit">
                        Design By - <a href="https://amandubey.com" target="_blank" rel="noopener noreferrer">Aman Dubey</a>
                    </div>
                </div>
            </div>
            <?php
            return (string) ob_get_clean();
        }

        if (! self::current_user_can_access_frontend_dashboard()) {
            return '<div class="stm-front"><p>' . esc_html__('You are not allowed to view this dashboard.', 'neura-task-manager') . '</p></div>';
        }

        $statuses   = self::allowed_statuses();
        $priorities = self::allowed_priorities();
        $settings   = self::get_settings();
        $dashboard_title = ! empty($settings['dashboard_title']) ? (string) $settings['dashboard_title'] : 'Daily Taks';
        wp_enqueue_style('dashicons');
        $is_settings_manager = self::current_user_can_manage_settings();
        $assignable_users = self::get_assignable_users();
        $leaderboard      = self::get_leaderboard(10);

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $editing_id = isset($_GET['edit_task']) ? absint($_GET['edit_task']) : 0;
        $editing_task = $editing_id ? self::get_task($editing_id) : null;
        if ($editing_task && ! self::current_user_can_edit_task_item($editing_task)) {
            return '<div class="stm-front"><p>' . esc_html__('You are not allowed to edit this task.', 'neura-task-manager') . '</p></div>';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $search     = isset($_GET['stmf_search']) ? sanitize_text_field(wp_unslash($_GET['stmf_search'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $status     = isset($_GET['stmf_status']) ? sanitize_key(wp_unslash($_GET['stmf_status'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $assigned_user_filter = isset($_GET['stmf_assigned_user_filter']) ? absint($_GET['stmf_assigned_user_filter']) : 0;
        $show_admin_employee_filter = $is_settings_manager && ! empty($settings['admin_employee_filter_enabled']);
        if (! $show_admin_employee_filter) {
            $assigned_user_filter = 0;
        }
        $all_tasks  = self::get_tasks($status, $search, $assigned_user_filter);
        $per_page = max(1, (int) $settings['tasks_per_page']);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_page = isset($_GET['stmf_page']) ? max(1, absint($_GET['stmf_page'])) : 1;
        $total_tasks = count($all_tasks);
        $total_pages = max(1, (int) ceil($total_tasks / $per_page));
        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }
        $tasks = array_slice($all_tasks, ($current_page - 1) * $per_page, $per_page);
        $frontend_pagination_base_url = remove_query_arg(
            array('stm_notice', 'stm_msg', 'edit_task', 'stm_overdue_ok_id', 'stm_refresh', 'stmf_page'),
            $current_url
        );
        $overdue_tasks = self::get_overdue_tasks_for_current_user();
        $pending_overdue = $is_settings_manager ? self::get_pending_overdue_for_review() : array();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $notice_type = isset($_GET['stm_notice']) ? sanitize_key(wp_unslash($_GET['stm_notice'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $notice_msg  = isset($_GET['stm_msg']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['stm_msg']))) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $overdue_ok_id = isset($_GET['stm_overdue_ok_id']) ? absint($_GET['stm_overdue_ok_id']) : 0;
        $suppress_top_notice = ('Overdue reason submitted for review.' === $notice_msg);

        $todo = 0;
        $progress = 0;
        $done = 0;

        foreach ($all_tasks as $task) {
            if ('todo' === $task->status) {
                $todo++;
            } elseif ('in_progress' === $task->status) {
                $progress++;
            } elseif ('done' === $task->status) {
                $done++;
            }
        }

        $my_points = (int) get_user_meta(get_current_user_id(), self::USER_POINTS_META, true);
        $auto_refresh_enabled = ! empty($settings['auto_refresh_enabled'])
            && ! empty($settings['auto_refresh_frontend'])
            && self::auto_refresh_allowed_for_scope((string) $settings['auto_refresh_user_scope'], $is_settings_manager);
        $auto_refresh_only_visible = ! empty($settings['auto_refresh_only_visible']);
        $auto_refresh_ms = max(10, min(3600, (int) $settings['auto_refresh_interval'])) * 1000;
        $show_assigned_to = $is_settings_manager;
        self::enqueue_frontend_dashboard_assets(
            (bool) ($editing_task && $is_settings_manager),
            (bool) $auto_refresh_enabled,
            (int) $auto_refresh_ms,
            (bool) $auto_refresh_only_visible
        );

        ob_start();
        ?>
        <div class="stm-front">
            <div class="stm-shell">
                <?php if ($notice_type && $notice_msg && ! $suppress_top_notice) : ?>
                    <div class="notice <?php echo esc_attr('success' === $notice_type ? '' : 'error'); ?>"><?php echo esc_html($notice_msg); ?></div>
                <?php endif; ?>
                <div class="stm-head">
                    <h3 class="stm-title"><?php echo esc_html($dashboard_title); ?></h3>
                    <div class="stm-head-right">
                        <a class="stm-top-link" href="<?php echo esc_url($tasks_page_url); ?>"><?php echo esc_html__('Tasks Home', 'neura-task-manager'); ?></a>
                        <a class="stm-top-link" href="<?php echo esc_url($frontend_refresh_url); ?>"><?php echo esc_html__('Refresh', 'neura-task-manager'); ?></a>
                        <div class="stm-user"><?php echo esc_html(wp_get_current_user()->display_name); ?> · <strong><?php echo esc_html__('Points', 'neura-task-manager'); ?>: <?php echo esc_html((string) $my_points); ?></strong></div>
                    </div>
                </div>

                <div class="stm-metrics">
                    <div class="stm-metric m1"><h4><?php echo esc_html__('To Do', 'neura-task-manager'); ?></h4><div class="v"><?php echo esc_html((string) $todo); ?></div></div>
                    <div class="stm-metric m2"><h4><?php echo esc_html__('In Progress', 'neura-task-manager'); ?></h4><div class="v"><?php echo esc_html((string) $progress); ?></div></div>
                    <div class="stm-metric m3"><h4><?php echo esc_html__('Completed', 'neura-task-manager'); ?></h4><div class="v"><?php echo esc_html((string) $done); ?></div></div>
                    <div class="stm-metric m4"><h4><?php echo esc_html__('My Reward Points', 'neura-task-manager'); ?></h4><div class="v"><?php echo esc_html((string) $my_points); ?></div></div>
                </div>

                <div class="stm-quick-actions">
                    <?php if ($is_settings_manager) : ?>
                        <button type="button" class="quick-btn" id="stmf-open-task-dialog">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php echo esc_html($editing_task ? __('Edit Task', 'neura-task-manager') : __('Add New Task', 'neura-task-manager')); ?>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="quick-btn secondary" id="stmf-open-leaderboard-dialog">
                        <span class="dashicons dashicons-awards"></span>
                        <?php echo esc_html__('Leaderboard', 'neura-task-manager'); ?>
                    </button>
                </div>

                <?php if (! empty($overdue_tasks)) : ?>
                    <div class="stm-wrap" style="margin-bottom:12px;">
                        <h4 style="margin:0 0 10px;"><?php echo esc_html__('Overdue Tasks - Submit Reason', 'neura-task-manager'); ?></h4>
                        <?php foreach ($overdue_tasks as $overdue_task) : ?>
                            <div class="overdue-card">
                                <h5><?php echo esc_html($overdue_task->title); ?> (<?php echo esc_html($overdue_task->due_date); ?>)</h5>
                                <?php if ('pending' === $overdue_task->overdue_status) : ?>
                                    <p style="margin:0 0 8px;"><?php echo esc_html__('Reason submitted and pending review by admin/editor.', 'neura-task-manager'); ?></p>
                                    <div style="font-size:13px;color:#475569;"><?php echo esc_html((string) $overdue_task->overdue_reason); ?></div>
                                <?php else : ?>
                                    <p style="margin:0 0 8px;"><?php echo esc_html__('Task is overdue. Submit reason for late completion/review.', 'neura-task-manager'); ?></p>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="stm_submit_overdue_reason">
                                        <input type="hidden" name="task_id" value="<?php echo esc_attr((string) (int) $overdue_task->id); ?>">
                                        <input type="hidden" name="stm_return" value="<?php echo esc_attr($base_return_url); ?>">
                                        <?php wp_nonce_field('stm_submit_overdue_reason'); ?>
                                        <textarea class="overdue-reason" name="overdue_reason" required placeholder="<?php echo esc_attr__('Why was this task delayed?', 'neura-task-manager'); ?>"><?php echo esc_textarea((string) $overdue_task->overdue_reason); ?></textarea>
                                        <div style="margin-top:8px;">
                                            <button type="submit" class="tiny"><?php echo esc_html__('Submit Reason', 'neura-task-manager'); ?></button>
                                            <span style="font-size:12px;color:#64748b;margin-left:8px;"><?php echo esc_html__('Current status:', 'neura-task-manager'); ?> <?php echo esc_html($overdue_task->overdue_status ? $overdue_task->overdue_status : 'none'); ?></span>
                                        </div>
                                        <?php if ($overdue_ok_id === (int) $overdue_task->id) : ?>
                                            <div style="margin-top:8px;color:#15803d;font-size:12px;font-weight:600;"><?php echo esc_html__('Overdue reason submitted for review.', 'neura-task-manager'); ?></div>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (! empty($pending_overdue)) : ?>
                    <div class="stm-wrap" style="margin-bottom:12px;">
                        <h4 style="margin:0 0 10px;"><?php echo esc_html__('Overdue Reviews (Admin/Editor)', 'neura-task-manager'); ?></h4>
                        <?php foreach ($pending_overdue as $pending_task) : ?>
                            <div class="review-card">
                                <strong><?php echo esc_html($pending_task->title); ?></strong>
                                <div style="font-size:12px;color:#475569;margin-top:4px;">
                                    <?php echo esc_html__('Assigned By:', 'neura-task-manager'); ?> <?php echo esc_html(self::user_name((int) $pending_task->created_by)); ?>
                                    · <?php echo esc_html__('Assigned To:', 'neura-task-manager'); ?> <?php echo esc_html(self::user_name((int) $pending_task->assigned_to)); ?>
                                </div>
                                <div style="font-size:13px;margin:6px 0;"><?php echo esc_html__('Reason:', 'neura-task-manager'); ?> <?php echo esc_html((string) $pending_task->overdue_reason); ?></div>
                                <?php
                                $accept_url = wp_nonce_url(
                                    add_query_arg(
                                        array(
                                            'action'     => 'stm_review_overdue_reason',
                                            'task_id'    => (int) $pending_task->id,
                                            'decision'   => 'accept',
                                            'stm_return' => $base_return_url,
                                        ),
                                        admin_url('admin-post.php')
                                    ),
                                    'stm_review_overdue_reason'
                                );
                                $reject_url = wp_nonce_url(
                                    add_query_arg(
                                        array(
                                            'action'     => 'stm_review_overdue_reason',
                                            'task_id'    => (int) $pending_task->id,
                                            'decision'   => 'reject',
                                            'stm_return' => $base_return_url,
                                        ),
                                        admin_url('admin-post.php')
                                    ),
                                    'stm_review_overdue_reason'
                                );
                                ?>
                                <a class="tiny" href="<?php echo esc_url($accept_url); ?>">✅ <?php echo esc_html__('Accept', 'neura-task-manager'); ?></a>
                                <a class="tiny" href="<?php echo esc_url($reject_url); ?>">❌ <?php echo esc_html__('Reject', 'neura-task-manager'); ?></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="stm-layout">
                    <?php if ($is_settings_manager) : ?>
                    <div>
                        <div class="stm-wrap" style="margin-bottom:12px;">
                            <h4 style="margin:0 0 10px;"><?php echo esc_html($editing_task ? 'Edit Task' : 'Add New Task'); ?></h4>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="stm_save_task" />
                                <input type="hidden" name="task_id" value="<?php echo esc_attr($editing_task ? (int) $editing_task->id : 0); ?>" />
                                <input type="hidden" name="stm_return" value="<?php echo esc_attr($base_return_url); ?>" />
                                <?php wp_nonce_field('stm_save_task'); ?>
                                <p><input class="stm-input" name="title" required placeholder="<?php echo esc_attr__('Task title', 'neura-task-manager'); ?>" value="<?php echo esc_attr($editing_task ? $editing_task->title : ''); ?>"></p>
                                <p><textarea class="stm-textarea stm-input" name="description" placeholder="<?php echo esc_attr__('Description', 'neura-task-manager'); ?>"><?php echo esc_textarea($editing_task ? $editing_task->description : ''); ?></textarea></p>
                                <p><select class="stm-select stm-input" name="assigned_to"><option value="0"><?php echo esc_html__('Unassigned', 'neura-task-manager'); ?></option><?php foreach ($assignable_users as $assignable_user) : ?><option value="<?php echo esc_attr((string) $assignable_user->ID); ?>" <?php selected($editing_task ? (int) $editing_task->assigned_to : 0, (int) $assignable_user->ID); ?>><?php echo esc_html($assignable_user->display_name); ?></option><?php endforeach; ?></select></p>
                                <p><input class="stm-input" name="due_date" type="date" value="<?php echo esc_attr($editing_task ? $editing_task->due_date : ''); ?>" <?php echo ! empty($settings['require_due_date']) ? 'required' : ''; ?>></p>
                                <p><select class="stm-select stm-input" name="priority"><?php foreach ($priorities as $priority_key => $priority_label) : ?><option value="<?php echo esc_attr($priority_key); ?>" <?php selected($editing_task ? $editing_task->priority : $settings['default_priority'], $priority_key); ?>><?php echo esc_html($priority_label); ?></option><?php endforeach; ?></select></p>
                                <p><input class="stm-input" type="number" min="0" name="reward_points" placeholder="<?php echo esc_attr__('Reward Points', 'neura-task-manager'); ?>" value="<?php echo esc_attr($editing_task ? (string) $editing_task->reward_points : (string) self::default_reward_for_priority($settings['default_priority'])); ?>"></p>
                                <button type="submit"><?php echo esc_html($editing_task ? __('Update Task', 'neura-task-manager') : __('Create Task', 'neura-task-manager')); ?></button>
                                <?php if ($editing_task) : ?><a class="btn-secondary tiny" href="<?php echo esc_url($base_return_url); ?>"><?php echo esc_html__('Cancel', 'neura-task-manager'); ?></a><?php endif; ?>
                            </form>
                        </div>
                        <?php endif; ?>

                        <div class="stm-wrap">
                            <h4 style="margin:0 0 10px;"><?php echo esc_html__('Leaderboard', 'neura-task-manager'); ?></h4>
                            <ul class="leader">
                                <?php if (empty($leaderboard)) : ?>
                                    <li><?php echo esc_html__('No points yet', 'neura-task-manager'); ?></li>
                                <?php else : ?>
                                    <?php foreach ($leaderboard as $index => $row_user) : ?>
                                        <li><span><?php echo esc_html(self::medal_for_rank($index + 1) . ' ' . $row_user->display_name); ?></span><strong class="points"><?php echo esc_html((string) (int) get_user_meta($row_user->ID, self::USER_POINTS_META, true)); ?></strong></li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php if ($is_settings_manager) : ?>
                    </div>
                    <?php endif; ?>

                    <div class="stm-wrap">
                        <form method="get" action="<?php echo esc_url($base_return_url); ?>" class="stm-filter <?php echo $show_admin_employee_filter ? '' : 'no-employee'; ?>">
                            <input type="search" name="stmf_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('Search tasks...', 'neura-task-manager'); ?>">
                            <select name="stmf_status">
                                <option value=""><?php echo esc_html__('All Status', 'neura-task-manager'); ?></option>
                                <?php foreach ($statuses as $status_key => $status_label) : ?>
                                    <option value="<?php echo esc_attr($status_key); ?>" <?php selected($status, $status_key); ?>><?php echo esc_html($status_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($show_admin_employee_filter) : ?>
                                <select name="stmf_assigned_user_filter">
                                    <option value="0"><?php echo esc_html__('All Employees', 'neura-task-manager'); ?></option>
                                    <?php foreach ($assignable_users as $assignable_user) : ?>
                                        <option value="<?php echo esc_attr((string) $assignable_user->ID); ?>" <?php selected($assigned_user_filter, (int) $assignable_user->ID); ?>>
                                            <?php echo esc_html($assignable_user->display_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            <button type="submit"><?php echo esc_html__('Apply', 'neura-task-manager'); ?></button>
                        </form>

                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html__('Task', 'neura-task-manager'); ?></th>
                                        <th><?php echo esc_html__('Assigned By', 'neura-task-manager'); ?></th>
                                        <?php if ($show_assigned_to) : ?><th><?php echo esc_html__('Assigned To', 'neura-task-manager'); ?></th><?php endif; ?>
                                        <th><?php echo esc_html__('Priority', 'neura-task-manager'); ?></th>
                                        <th><?php echo esc_html__('Status', 'neura-task-manager'); ?></th>
                                        <th><?php echo esc_html__('Due', 'neura-task-manager'); ?></th>
                                        <th><?php echo esc_html__('Reward', 'neura-task-manager'); ?></th>
                                        <th><?php echo esc_html__('Actions', 'neura-task-manager'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tasks)) : ?>
                                        <tr><td colspan="<?php echo esc_attr($show_assigned_to ? '8' : '7'); ?>"><?php echo esc_html__('No tasks found.', 'neura-task-manager'); ?></td></tr>
                                    <?php else : ?>
                                        <?php foreach ($tasks as $task) : ?>
                                            <tr>
                                                <td><?php echo esc_html($task->title); ?><?php if (! empty($task->description)) : ?><span class="desc"><?php echo esc_html(self::short_text($task->description, 80)); ?></span><?php endif; ?></td>
                                                <td><?php echo esc_html(self::user_name((int) $task->created_by)); ?></td>
                                                <?php if ($show_assigned_to) : ?><td><?php echo esc_html(self::user_name((int) $task->assigned_to)); ?></td><?php endif; ?>
                                                <td><span class="stm-badge <?php echo esc_attr(self::priority_badge_class($task->priority)); ?>"><?php echo esc_html(isset($priorities[$task->priority]) ? $priorities[$task->priority] : $task->priority); ?></span></td>
                                                <td><span class="stm-badge <?php echo esc_attr('in_progress' === $task->status ? 'progress' : ('done' === $task->status ? 'done' : 'todo')); ?>"><?php echo esc_html(isset($statuses[$task->status]) ? $statuses[$task->status] : $task->status); ?></span></td>
                                                <td><?php echo esc_html($task->due_date ? $task->due_date : 'No due date'); ?></td>
                                                <td class="points">+<?php echo esc_html((string) (int) $task->reward_points); ?></td>
                                                <td class="table-actions">
                                                    <button type="button" class="tiny view action-icon stmf-view-btn"
                                                        data-tip="<?php echo esc_attr__('View Task', 'neura-task-manager'); ?>"
                                                        title="<?php echo esc_attr__('View Task', 'neura-task-manager'); ?>"
                                                        aria-label="<?php echo esc_attr__('View Task', 'neura-task-manager'); ?>"
                                                        data-title="<?php echo esc_attr($task->title); ?>"
                                                        data-description="<?php echo esc_attr((string) $task->description); ?>"
                                                        data-assignedby="<?php echo esc_attr(self::user_name((int) $task->created_by)); ?>"
                                                        data-assignedto="<?php echo esc_attr(self::user_name((int) $task->assigned_to)); ?>"
                                                        data-priority="<?php echo esc_attr(isset($priorities[$task->priority]) ? $priorities[$task->priority] : $task->priority); ?>"
                                                        data-status="<?php echo esc_attr(isset($statuses[$task->status]) ? $statuses[$task->status] : $task->status); ?>"
                                                        data-due="<?php echo esc_attr($task->due_date ? $task->due_date : 'No due date'); ?>"
                                                        data-reward="<?php echo esc_attr((string) (int) $task->reward_points); ?>">
                                                        <span class="dashicons dashicons-visibility"></span>
                                                    </button>
                                                    <?php
                                                    $base_action_args = array('stm_return' => $base_return_url, 'task_id' => (int) $task->id);
                                                    if ('todo' === $task->status) { $next_status = 'in_progress'; $next_label = __('Start', 'neura-task-manager'); $next_icon = 'dashicons-controls-play'; }
                                                    elseif ('in_progress' === $task->status) { $next_status = 'done'; $next_label = __('Complete', 'neura-task-manager'); $next_icon = 'dashicons-yes-alt'; }
                                                    else { $next_status = 'todo'; $next_label = __('Reopen', 'neura-task-manager'); $next_icon = 'dashicons-update'; }
                                                    $status_url = wp_nonce_url(add_query_arg(array_merge($base_action_args, array('action' => 'stm_update_status', 'status' => $next_status)), admin_url('admin-post.php')), 'stm_update_status');
                                                    $delete_url = wp_nonce_url(add_query_arg(array_merge($base_action_args, array('action' => 'stm_delete_task')), admin_url('admin-post.php')), 'stm_delete_task');
                                                    $edit_url = add_query_arg('edit_task', (int) $task->id, $base_return_url);
                                                    ?>
                                                    <?php if (self::current_user_can_edit_task_item($task)) : ?><a class="tiny edit action-icon" data-tip="<?php echo esc_attr__('Edit Task', 'neura-task-manager'); ?>" title="<?php echo esc_attr__('Edit Task', 'neura-task-manager'); ?>" aria-label="<?php echo esc_attr__('Edit Task', 'neura-task-manager'); ?>" href="<?php echo esc_url($edit_url); ?>"><span class="dashicons dashicons-edit"></span></a><?php endif; ?>
                                                    <?php if (self::current_user_can_change_task_status_item($task)) : ?><a class="tiny status action-icon" data-tip="<?php echo esc_attr($next_label); ?>" title="<?php echo esc_attr($next_label); ?>" aria-label="<?php echo esc_attr($next_label); ?>" href="<?php echo esc_url($status_url); ?>"><span class="dashicons <?php echo esc_attr($next_icon); ?>"></span></a><?php endif; ?>
                                                    <?php if (self::current_user_can_delete_task_item($task)) : ?><a class="tiny delete action-icon" data-tip="<?php echo esc_attr__('Delete Task', 'neura-task-manager'); ?>" title="<?php echo esc_attr__('Delete Task', 'neura-task-manager'); ?>" aria-label="<?php echo esc_attr__('Delete Task', 'neura-task-manager'); ?>" href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('Delete this task?');"><span class="dashicons dashicons-trash"></span></a><?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="task-cards">
                            <?php if (empty($tasks)) : ?>
                                <p><?php echo esc_html__('No tasks found.', 'neura-task-manager'); ?></p>
                            <?php else : ?>
                                <?php foreach ($tasks as $task) : ?>
                                    <?php
                                    $status_badge_class = 'in_progress' === $task->status ? 'progress' : ('done' === $task->status ? 'done' : 'todo');
                                    $status_label = isset($statuses[$task->status]) ? $statuses[$task->status] : $task->status;
                                    ?>
                                    <article class="task-card">
                                        <div class="task-card-head">
                                            <div>
                                                <h5><?php echo esc_html($task->title); ?></h5>
                                                <span class="stm-badge <?php echo esc_attr($status_badge_class); ?>"><?php echo esc_html($status_label); ?></span>
                                            </div>
                                            <button type="button" class="task-toggle stm-task-toggle" aria-expanded="false"><?php echo esc_html__('Show details', 'neura-task-manager'); ?></button>
                                        </div>
                                        <div class="task-details">
                                        <?php if (! empty($task->description)) : ?><div class="desc"><?php echo esc_html($task->description); ?></div><?php endif; ?>
                                        <div class="task-grid">
                                            <div><span class="k"><?php echo esc_html__('Assigned By', 'neura-task-manager'); ?></span><?php echo esc_html(self::user_name((int) $task->created_by)); ?></div>
                                            <?php if ($show_assigned_to) : ?><div><span class="k"><?php echo esc_html__('Assigned To', 'neura-task-manager'); ?></span><?php echo esc_html(self::user_name((int) $task->assigned_to)); ?></div><?php endif; ?>
                                            <div><span class="k"><?php echo esc_html__('Priority', 'neura-task-manager'); ?></span><span class="stm-badge <?php echo esc_attr(self::priority_badge_class($task->priority)); ?>"><?php echo esc_html(isset($priorities[$task->priority]) ? $priorities[$task->priority] : $task->priority); ?></span></div>
                                            <div><span class="k"><?php echo esc_html__('Status', 'neura-task-manager'); ?></span><span class="stm-badge <?php echo esc_attr($status_badge_class); ?>"><?php echo esc_html($status_label); ?></span></div>
                                            <div><span class="k"><?php echo esc_html__('Due', 'neura-task-manager'); ?></span><?php echo esc_html($task->due_date ? $task->due_date : 'No due date'); ?></div>
                                            <div><span class="k"><?php echo esc_html__('Reward', 'neura-task-manager'); ?></span><span class="points">+<?php echo esc_html((string) (int) $task->reward_points); ?></span></div>
                                        </div>
                                        <div class="task-actions">
                                            <button type="button" class="tiny view stmf-view-btn"
                                                data-title="<?php echo esc_attr($task->title); ?>"
                                                data-description="<?php echo esc_attr((string) $task->description); ?>"
                                                data-assignedby="<?php echo esc_attr(self::user_name((int) $task->created_by)); ?>"
                                                data-assignedto="<?php echo esc_attr(self::user_name((int) $task->assigned_to)); ?>"
                                                data-priority="<?php echo esc_attr(isset($priorities[$task->priority]) ? $priorities[$task->priority] : $task->priority); ?>"
                                                data-status="<?php echo esc_attr(isset($statuses[$task->status]) ? $statuses[$task->status] : $task->status); ?>"
                                                data-due="<?php echo esc_attr($task->due_date ? $task->due_date : 'No due date'); ?>"
                                                data-reward="<?php echo esc_attr((string) (int) $task->reward_points); ?>">
                                                <span class="dashicons dashicons-visibility"></span>
                                                <?php echo esc_html__('View', 'neura-task-manager'); ?>
                                            </button>
                                            <?php
                                            $base_action_args_m = array('stm_return' => $base_return_url, 'task_id' => (int) $task->id);
                                            if ('todo' === $task->status) { $next_status_m = 'in_progress'; $next_label_m = __('Start', 'neura-task-manager'); $next_icon_m = 'dashicons-controls-play'; }
                                            elseif ('in_progress' === $task->status) { $next_status_m = 'done'; $next_label_m = __('Complete', 'neura-task-manager'); $next_icon_m = 'dashicons-yes-alt'; }
                                            else { $next_status_m = 'todo'; $next_label_m = __('Reopen', 'neura-task-manager'); $next_icon_m = 'dashicons-update'; }
                                            $status_url_m = wp_nonce_url(add_query_arg(array_merge($base_action_args_m, array('action' => 'stm_update_status', 'status' => $next_status_m)), admin_url('admin-post.php')), 'stm_update_status');
                                            $delete_url_m = wp_nonce_url(add_query_arg(array_merge($base_action_args_m, array('action' => 'stm_delete_task')), admin_url('admin-post.php')), 'stm_delete_task');
                                            $edit_url_m = add_query_arg('edit_task', (int) $task->id, $base_return_url);
                                            ?>
                                            <?php if (self::current_user_can_edit_task_item($task)) : ?><a class="tiny edit" href="<?php echo esc_url($edit_url_m); ?>"><span class="dashicons dashicons-edit"></span><?php echo esc_html__('Edit', 'neura-task-manager'); ?></a><?php endif; ?>
                                            <?php if (self::current_user_can_change_task_status_item($task)) : ?><a class="tiny status" href="<?php echo esc_url($status_url_m); ?>"><span class="dashicons <?php echo esc_attr($next_icon_m); ?>"></span><?php echo esc_html($next_label_m); ?></a><?php endif; ?>
                                            <?php if (self::current_user_can_delete_task_item($task)) : ?><a class="tiny delete" href="<?php echo esc_url($delete_url_m); ?>" onclick="return confirm('Delete this task?');"><span class="dashicons dashicons-trash"></span><?php echo esc_html__('Delete', 'neura-task-manager'); ?></a><?php endif; ?>
                                        </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($total_pages > 1) : ?>
                            <div class="stm-pagination">
                                <?php for ($page_num = 1; $page_num <= $total_pages; $page_num++) : ?>
                                    <?php
                                    $page_url = add_query_arg('stmf_page', $page_num, $frontend_pagination_base_url);
                                    $is_active_page = $page_num === $current_page;
                                    ?>
                                    <a class="stm-page-link <?php echo $is_active_page ? 'is-active' : ''; ?>" href="<?php echo esc_url($page_url); ?>">
                                        <?php echo esc_html((string) $page_num); ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="stm-credit">Design By - <a href="https://amandubey.com" target="_blank" rel="noopener noreferrer">Aman Dubey</a></div>
            <?php if ($is_settings_manager) : ?>
            <dialog id="stmf-task-dialog">
                <div class="stm-dialog-head">
                    <strong><?php echo esc_html($editing_task ? __('Edit Task', 'neura-task-manager') : __('Add New Task', 'neura-task-manager')); ?></strong>
                    <button type="button" class="tiny" id="stmf-task-close"><?php echo esc_html__('Close', 'neura-task-manager'); ?></button>
                </div>
                <div class="stm-dialog-body">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="stm_save_task" />
                        <input type="hidden" name="task_id" value="<?php echo esc_attr($editing_task ? (int) $editing_task->id : 0); ?>" />
                        <input type="hidden" name="stm_return" value="<?php echo esc_attr($base_return_url); ?>" />
                        <?php wp_nonce_field('stm_save_task'); ?>
                        <p><input class="stm-input" name="title" required placeholder="<?php echo esc_attr__('Task title', 'neura-task-manager'); ?>" value="<?php echo esc_attr($editing_task ? $editing_task->title : ''); ?>"></p>
                        <p><textarea class="stm-textarea stm-input" name="description" placeholder="<?php echo esc_attr__('Description', 'neura-task-manager'); ?>"><?php echo esc_textarea($editing_task ? $editing_task->description : ''); ?></textarea></p>
                        <p><select class="stm-select stm-input" name="assigned_to"><option value="0"><?php echo esc_html__('Unassigned', 'neura-task-manager'); ?></option><?php foreach ($assignable_users as $assignable_user) : ?><option value="<?php echo esc_attr((string) $assignable_user->ID); ?>" <?php selected($editing_task ? (int) $editing_task->assigned_to : 0, (int) $assignable_user->ID); ?>><?php echo esc_html($assignable_user->display_name); ?></option><?php endforeach; ?></select></p>
                        <p><input class="stm-input" name="due_date" type="date" value="<?php echo esc_attr($editing_task ? $editing_task->due_date : ''); ?>" <?php echo ! empty($settings['require_due_date']) ? 'required' : ''; ?>></p>
                        <p><select class="stm-select stm-input" name="priority"><?php foreach ($priorities as $priority_key => $priority_label) : ?><option value="<?php echo esc_attr($priority_key); ?>" <?php selected($editing_task ? $editing_task->priority : $settings['default_priority'], $priority_key); ?>><?php echo esc_html($priority_label); ?></option><?php endforeach; ?></select></p>
                        <p><input class="stm-input" type="number" min="0" name="reward_points" placeholder="<?php echo esc_attr__('Reward Points', 'neura-task-manager'); ?>" value="<?php echo esc_attr($editing_task ? (string) $editing_task->reward_points : (string) self::default_reward_for_priority($settings['default_priority'])); ?>"></p>
                        <button type="submit"><?php echo esc_html($editing_task ? __('Update Task', 'neura-task-manager') : __('Create Task', 'neura-task-manager')); ?></button>
                    </form>
                </div>
            </dialog>
            <?php endif; ?>
            <dialog id="stmf-leader-dialog">
                <div class="stm-dialog-head">
                    <strong><?php echo esc_html__('Leaderboard', 'neura-task-manager'); ?></strong>
                    <button type="button" class="tiny" id="stmf-leader-close"><?php echo esc_html__('Close', 'neura-task-manager'); ?></button>
                </div>
                <div class="stm-dialog-body">
                    <ul class="leader">
                        <?php if (empty($leaderboard)) : ?>
                            <li><?php echo esc_html__('No points yet', 'neura-task-manager'); ?></li>
                        <?php else : ?>
                            <?php foreach ($leaderboard as $index => $row_user) : ?>
                                <li><span><?php echo esc_html(self::medal_for_rank($index + 1) . ' ' . $row_user->display_name); ?></span><strong class="points"><?php echo esc_html((string) (int) get_user_meta($row_user->ID, self::USER_POINTS_META, true)); ?></strong></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </dialog>
            <dialog id="stmf-view-dialog">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border-bottom:1px solid #e2e8f0;">
                    <strong id="stmf-v-title"></strong>
                    <button type="button" class="tiny" id="stmf-v-close"><?php echo esc_html__('Close', 'neura-task-manager'); ?></button>
                </div>
                <div style="padding:12px;">
                    <p><strong><?php echo esc_html__('Task To Do:', 'neura-task-manager'); ?></strong></p>
                    <p id="stmf-v-desc"></p>
                </div>
            </dialog>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function render_admin_page() {
        if (! self::current_user_can_manage_tasks()) {
            wp_die(esc_html__('You are not allowed to access this page.', 'neura-task-manager'));
        }

        $statuses         = self::allowed_statuses();
        $priorities       = self::allowed_priorities();
        $settings         = self::get_settings();
        $dashboard_title  = ! empty($settings['dashboard_title']) ? (string) $settings['dashboard_title'] : 'Daily Taks';
        $is_settings_manager = self::current_user_can_manage_settings();
        $assignable_users = self::get_assignable_users();
        $leaderboard      = self::get_leaderboard(10);
        $pending_overdue_admin = $is_settings_manager ? self::get_pending_overdue_for_review() : array();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $editing_id = isset($_GET['edit_task']) ? absint($_GET['edit_task']) : 0;
        $task       = $editing_id ? self::get_task($editing_id) : null;

        if ($task && ! self::current_user_can_edit_task_item($task)) {
            self::redirect_with_notice('error', 'You are not allowed to edit this task.');
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $status_filter = isset($_GET['status_filter']) ? sanitize_key(wp_unslash($_GET['status_filter'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $search_term   = isset($_GET['task_search']) ? sanitize_text_field(wp_unslash($_GET['task_search'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $assigned_user_filter = isset($_GET['assigned_user_filter']) ? absint($_GET['assigned_user_filter']) : 0;
        $show_admin_employee_filter = $is_settings_manager && ! empty($settings['admin_employee_filter_enabled']);
        if (! $show_admin_employee_filter) {
            $assigned_user_filter = 0;
        }
        $all_tasks = self::get_tasks($status_filter, $search_term, $assigned_user_filter);
        $per_page = max(1, (int) $settings['tasks_per_page']);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_page = isset($_GET['stmb_page']) ? max(1, absint($_GET['stmb_page'])) : 1;
        $total_tasks = count($all_tasks);
        $total_pages = max(1, (int) ceil($total_tasks / $per_page));
        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }
        $tasks = array_slice($all_tasks, ($current_page - 1) * $per_page, $per_page);
        $backend_pagination_base_args = array(
            'page' => 'stm-task-manager',
            'task_search' => $search_term,
            'status_filter' => $status_filter,
        );
        if ($show_admin_employee_filter) {
            $backend_pagination_base_args['assigned_user_filter'] = $assigned_user_filter;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $notice_type = isset($_GET['stm_notice']) ? sanitize_key(wp_unslash($_GET['stm_notice'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $notice_msg  = isset($_GET['stm_msg']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['stm_msg']))) : '';

        $count_todo        = 0;
        $count_in_progress = 0;
        $count_done        = 0;

        foreach ($all_tasks as $item) {
            if ('todo' === $item->status) {
                $count_todo++;
            } elseif ('in_progress' === $item->status) {
                $count_in_progress++;
            } elseif ('done' === $item->status) {
                $count_done++;
            }
        }

        $my_points = (int) get_user_meta(get_current_user_id(), self::USER_POINTS_META, true);
        $auto_refresh_enabled = ! empty($settings['auto_refresh_enabled'])
            && ! empty($settings['auto_refresh_backend'])
            && self::auto_refresh_allowed_for_scope((string) $settings['auto_refresh_user_scope'], $is_settings_manager);
        $auto_refresh_only_visible = ! empty($settings['auto_refresh_only_visible']);
        $auto_refresh_ms = max(10, min(3600, (int) $settings['auto_refresh_interval'])) * 1000;
        $show_assigned_to = $is_settings_manager;
        self::enqueue_admin_dashboard_assets(
            (bool) ($task && $is_settings_manager),
            (bool) $auto_refresh_enabled,
            (int) $auto_refresh_ms,
            (bool) $auto_refresh_only_visible
        );

        ?>
        <div class="wrap">

            <?php if ( && ) : ?>
                <div class="notice notice-<?php echo esc_attr('success' === $notice_type ? 'success' : 'error'); ?> is-dismissible">
                    <p><?php echo esc_html($notice_msg); ?></p>
                </div>
            <?php endif; ?>

            <main class="stm-main">
                <div class="stm-top">
                    <h1 class="stm-title"><?php echo esc_html($dashboard_title); ?></h1>
                    <div class="stm-top-right">
                        <a class="stm-top-link" href="<?php echo esc_url(self::frontend_tasks_home_url()); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Tasks Home', 'neura-task-manager'); ?></a>
                        <a class="stm-top-link" href="<?php echo esc_url(add_query_arg('stm_refresh', time(), admin_url('admin.php?page=stm-task-manager'))); ?>"><?php echo esc_html__('Refresh', 'neura-task-manager'); ?></a>
                        <div class="stm-user-chip">
                            <span class="dashicons dashicons-admin-users" style="font-size:16px;line-height:1.1;"></span>
                            <?php echo esc_html(wp_get_current_user()->display_name); ?>
                            · <span class="stm-points-strong"><?php echo esc_html__('Points:', 'neura-task-manager'); ?> <?php echo esc_html((string) $my_points); ?></span>
                        </div>
                    </div>
                </div>

                <div class="stm-metrics">
                    <div class="stm-metric stm-card-todo">
                        <span class="stm-metric-icon dashicons dashicons-clipboard"></span>
                        <span class="stm-metric-label"><?php echo esc_html__('To Do', 'neura-task-manager'); ?></span>
                        <span class="stm-metric-value"><?php echo esc_html((string) $count_todo); ?></span>
                    </div>
                    <div class="stm-metric stm-card-progress">
                        <span class="stm-metric-icon dashicons dashicons-update"></span>
                        <span class="stm-metric-label"><?php echo esc_html__('In Progress', 'neura-task-manager'); ?></span>
                        <span class="stm-metric-value"><?php echo esc_html((string) $count_in_progress); ?></span>
                    </div>
                    <div class="stm-metric stm-card-done">
                        <span class="stm-metric-icon dashicons dashicons-yes-alt"></span>
                        <span class="stm-metric-label"><?php echo esc_html__('Completed', 'neura-task-manager'); ?></span>
                        <span class="stm-metric-value"><?php echo esc_html((string) $count_done); ?></span>
                    </div>
                    <div class="stm-metric stm-card-points">
                        <span class="stm-metric-icon dashicons dashicons-awards"></span>
                        <span class="stm-metric-label"><?php echo esc_html__('My Reward Points', 'neura-task-manager'); ?></span>
                        <span class="stm-metric-value"><?php echo esc_html((string) $my_points); ?></span>
                    </div>
                </div>

                <div class="stm-admin-quick-actions">
                    <?php if ($is_settings_manager) : ?>
                        <button type="button" class="stm-btn stm-btn-primary" id="stm-open-task-dialog">
                            <span class="dashicons dashicons-plus-alt2" style="font-size:14px;line-height:1.2;"></span>
                            <?php echo esc_html($task ? __('Edit Task', 'neura-task-manager') : __('Add New Task', 'neura-task-manager')); ?>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="stm-btn stm-btn-secondary" id="stm-open-leaderboard-dialog">
                        <span class="dashicons dashicons-awards" style="font-size:14px;line-height:1.2;"></span>
                        <?php echo esc_html__('Leaderboard', 'neura-task-manager'); ?>
                    </button>
                </div>

                <?php if (! empty($pending_overdue_admin)) : ?>
                    <div class="stm-card" style="margin-bottom:14px;">
                        <div class="stm-card-header"><?php echo esc_html__('Pending Overdue Reason Reviews', 'neura-task-manager'); ?></div>
                        <div class="stm-card-body">
                            <?php foreach ($pending_overdue_admin as $pending_item) : ?>
                                <div style="border:1px solid #fde68a;background:#fffbeb;border-radius:10px;padding:10px;margin-bottom:8px;">
                                    <strong><?php echo esc_html($pending_item->title); ?></strong>
                                    <div style="font-size:12px;color:#475569;margin-top:4px;">
                                        <?php echo esc_html__('Assigned By:', 'neura-task-manager'); ?> <?php echo esc_html(self::user_name((int) $pending_item->created_by)); ?>
                                        · <?php echo esc_html__('Assigned To:', 'neura-task-manager'); ?> <?php echo esc_html(self::user_name((int) $pending_item->assigned_to)); ?>
                                    </div>
                                    <div style="margin:4px 0 8px;"><?php echo esc_html__('Reason:', 'neura-task-manager'); ?> <?php echo esc_html((string) $pending_item->overdue_reason); ?></div>
                                    <?php
                                    $accept_u = wp_nonce_url(
                                        add_query_arg(
                                            array(
                                                'action'  => 'stm_review_overdue_reason',
                                                'task_id' => (int) $pending_item->id,
                                                'decision'=> 'accept',
                                            ),
                                            admin_url('admin-post.php')
                                        ),
                                        'stm_review_overdue_reason'
                                    );
                                    $reject_u = wp_nonce_url(
                                        add_query_arg(
                                            array(
                                                'action'  => 'stm_review_overdue_reason',
                                                'task_id' => (int) $pending_item->id,
                                                'decision'=> 'reject',
                                            ),
                                            admin_url('admin-post.php')
                                        ),
                                        'stm_review_overdue_reason'
                                    );
                                    ?>
                                    <a class="stm-btn stm-btn-status" href="<?php echo esc_url($accept_u); ?>">✅ <?php echo esc_html__('Accept', 'neura-task-manager'); ?></a>
                                    <a class="stm-btn stm-btn-delete" href="<?php echo esc_url($reject_u); ?>">❌ <?php echo esc_html__('Reject', 'neura-task-manager'); ?></a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="stm-layout">
                    <div>
                        <?php if ($is_settings_manager) : ?>
                        <div class="stm-card" style="margin-bottom:14px;">
                            <div class="stm-card-header"><?php echo esc_html($task ? 'Edit Task' : 'Add New Task'); ?></div>
                            <div class="stm-card-body">
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="stm_save_task" />
                                    <input type="hidden" name="task_id" value="<?php echo esc_attr($task ? (int) $task->id : 0); ?>" />
                                    <?php wp_nonce_field('stm_save_task'); ?>

                                    <div class="stm-form-row">
                                        <label for="stm-title"><?php echo esc_html__('Task Title', 'neura-task-manager'); ?></label>
                                        <input id="stm-title" name="title" type="text" class="stm-input" required value="<?php echo esc_attr($task ? $task->title : ''); ?>" />
                                    </div>

                                    <div class="stm-form-row">
                                        <label for="stm-description"><?php echo esc_html__('Description', 'neura-task-manager'); ?></label>
                                        <textarea id="stm-description" name="description" class="stm-textarea"><?php echo esc_textarea($task ? $task->description : ''); ?></textarea>
                                    </div>

                                    <div class="stm-grid-2">
                                        <div class="stm-form-row">
                                            <label for="stm-assigned-to"><?php echo esc_html__('Assign To', 'neura-task-manager'); ?></label>
                                            <select id="stm-assigned-to" name="assigned_to" class="stm-select">
                                                <option value="0"><?php echo esc_html__('Unassigned', 'neura-task-manager'); ?></option>
                                                <?php foreach ($assignable_users as $assignable_user) : ?>
                                                    <option value="<?php echo esc_attr((string) $assignable_user->ID); ?>" <?php selected($task ? (int) $task->assigned_to : 0, (int) $assignable_user->ID); ?>>
                                                        <?php echo esc_html($assignable_user->display_name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="stm-form-row">
                                            <label for="stm-due-date"><?php echo esc_html__('Due Date', 'neura-task-manager'); ?></label>
                                            <input id="stm-due-date" name="due_date" type="date" class="stm-input" value="<?php echo esc_attr($task ? $task->due_date : ''); ?>" <?php echo ! empty($settings['require_due_date']) ? 'required' : ''; ?> />
                                        </div>
                                    </div>

                                    <div class="stm-grid-2">
                                        <div class="stm-form-row">
                                            <label for="stm-priority"><?php echo esc_html__('Priority', 'neura-task-manager'); ?></label>
                                            <select id="stm-priority" name="priority" class="stm-select">
                                                <?php foreach ($priorities as $priority_key => $priority_label) : ?>
                                                    <option value="<?php echo esc_attr($priority_key); ?>" <?php selected($task ? $task->priority : $settings['default_priority'], $priority_key); ?>>
                                                        <?php echo esc_html($priority_label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="stm-form-row">
                                            <label for="stm-reward-points"><?php echo esc_html__('Reward Points', 'neura-task-manager'); ?></label>
                                            <input id="stm-reward-points" name="reward_points" type="number" min="0" class="stm-input" value="<?php echo esc_attr($task ? (string) $task->reward_points : (string) self::default_reward_for_priority($settings['default_priority'])); ?>" />
                                        </div>
                                    </div>

                                    <button type="submit" class="stm-btn stm-btn-primary">
                                        <span class="dashicons dashicons-plus-alt2" style="font-size:14px;line-height:1.2;"></span>
                                        <?php echo esc_html($task ? __('Update Task', 'neura-task-manager') : __('Create Task', 'neura-task-manager')); ?>
                                    </button>
                                    <?php if ($task) : ?>
                                        <a class="stm-btn stm-btn-secondary" href="<?php echo esc_url(admin_url('admin.php?page=stm-task-manager')); ?>"><?php echo esc_html__('Cancel', 'neura-task-manager'); ?></a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="stm-card">
                            <div class="stm-card-header"><?php echo esc_html__('Leaderboard', 'neura-task-manager'); ?></div>
                            <div class="stm-card-body">
                                <?php if (empty($leaderboard)) : ?>
                                    <p><?php echo esc_html__('No points earned yet.', 'neura-task-manager'); ?></p>
                                <?php else : ?>
                                    <ul class="stm-leader-list">
                                        <?php foreach ($leaderboard as $index => $row_user) : ?>
                                            <?php $rank = $index + 1; ?>
                                            <li class="stm-leader-item">
                                                <span>
                                                    <span class="stm-medal"><?php echo esc_html(self::medal_for_rank($rank)); ?></span>
                                                    <?php if ($rank > 3) : ?>
                                                        <?php echo esc_html('#' . $rank); ?>
                                                    <?php endif; ?>
                                                    <?php echo esc_html($row_user->display_name); ?>
                                                </span>
                                                <strong class="stm-points"><?php echo esc_html((string) (int) get_user_meta($row_user->ID, self::USER_POINTS_META, true)); ?></strong>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="stm-card">
                        <div class="stm-card-header"><?php echo esc_html__('Active Tasks', 'neura-task-manager'); ?></div>
                        <div class="stm-card-body">
                            <form method="get" class="stm-filter-row">
                                <input type="hidden" name="page" value="stm-task-manager" />
                                <input type="search" name="task_search" class="stm-search" value="<?php echo esc_attr($search_term); ?>" placeholder="<?php echo esc_attr__('Search task title or description...', 'neura-task-manager'); ?>" />
                                <select name="status_filter" class="stm-select" style="width:auto;min-width:160px;">
                                    <option value=""><?php echo esc_html__('All Status', 'neura-task-manager'); ?></option>
                                    <?php foreach ($statuses as $status_key => $status_label) : ?>
                                        <option value="<?php echo esc_attr($status_key); ?>" <?php selected($status_filter, $status_key); ?>><?php echo esc_html($status_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($show_admin_employee_filter) : ?>
                                    <select name="assigned_user_filter" class="stm-select" style="width:auto;min-width:200px;">
                                        <option value="0"><?php echo esc_html__('All Employees', 'neura-task-manager'); ?></option>
                                        <?php foreach ($assignable_users as $assignable_user) : ?>
                                            <option value="<?php echo esc_attr((string) $assignable_user->ID); ?>" <?php selected($assigned_user_filter, (int) $assignable_user->ID); ?>>
                                                <?php echo esc_html($assignable_user->display_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                                <button class="stm-btn stm-btn-primary" type="submit">
                                    <span class="dashicons dashicons-search" style="font-size:14px;line-height:1.2;"></span>
                                    <?php echo esc_html__('Apply', 'neura-task-manager'); ?>
                                </button>
                            </form>

                            <div class="stm-table-wrap">
                                <table class="widefat striped">
                                    <thead>
                                        <tr>
                                            <th><?php echo esc_html__('Task Name', 'neura-task-manager'); ?></th>
                                            <th><?php echo esc_html__('Assigned By', 'neura-task-manager'); ?></th>
                                            <?php if ($show_assigned_to) : ?><th><?php echo esc_html__('Assigned To', 'neura-task-manager'); ?></th><?php endif; ?>
                                            <th><?php echo esc_html__('Priority', 'neura-task-manager'); ?></th>
                                            <th><?php echo esc_html__('Status', 'neura-task-manager'); ?></th>
                                            <th><?php echo esc_html__('Due Date', 'neura-task-manager'); ?></th>
                                            <th><?php echo esc_html__('Reward', 'neura-task-manager'); ?></th>
                                            <th><?php echo esc_html__('Actions', 'neura-task-manager'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($tasks)) : ?>
                                            <tr><td colspan="<?php echo esc_attr($show_assigned_to ? '8' : '7'); ?>"><?php echo esc_html__('No tasks found.', 'neura-task-manager'); ?></td></tr>
                                        <?php else : ?>
                                            <?php foreach ($tasks as $item) : ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo esc_html($item->title); ?></strong>
                                                        <?php if (! empty($item->description)) : ?>
                                                            <div class="stm-meta"><?php echo esc_html(self::short_text($item->description, 80)); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo esc_html(self::user_name((int) $item->created_by)); ?></td>
                                                    <?php if ($show_assigned_to) : ?><td><?php echo esc_html(self::user_name((int) $item->assigned_to)); ?></td><?php endif; ?>
                                                    <td><span class="stm-badge <?php echo esc_attr(self::priority_badge_class($item->priority)); ?>"><?php echo esc_html(isset($priorities[$item->priority]) ? $priorities[$item->priority] : $item->priority); ?></span></td>
                                                    <td><span class="stm-badge <?php echo esc_attr(self::status_badge_class($item->status)); ?>"><?php echo esc_html(isset($statuses[$item->status]) ? $statuses[$item->status] : $item->status); ?></span></td>
                                                    <td>
                                                        <?php echo esc_html($item->due_date ? $item->due_date : 'No due date'); ?>
                                                        <div class="stm-meta"><?php echo esc_html(mysql2date('Y-m-d H:i', $item->updated_at)); ?></div>
                                                    </td>
                                                    <td><span class="stm-points">+<?php echo esc_html((string) (int) $item->reward_points); ?></span></td>
                                                    <td>
                                                        <div class="stm-action-group">
                                                            <button type="button" class="stm-btn stm-btn-view action-icon stm-view-btn"
                                                                data-tip="<?php echo esc_attr__('View Task', 'neura-task-manager'); ?>"
                                                                title="<?php echo esc_attr__('View Task', 'neura-task-manager'); ?>"
                                                                aria-label="<?php echo esc_attr__('View Task', 'neura-task-manager'); ?>"
                                                                data-title="<?php echo esc_attr($item->title); ?>"
                                                                data-description="<?php echo esc_attr((string) $item->description); ?>"
                                                                data-assignedby="<?php echo esc_attr(self::user_name((int) $item->created_by)); ?>"
                                                                data-assignedto="<?php echo esc_attr(self::user_name((int) $item->assigned_to)); ?>"
                                                                data-priority="<?php echo esc_attr(isset($priorities[$item->priority]) ? $priorities[$item->priority] : $item->priority); ?>"
                                                                data-status="<?php echo esc_attr(isset($statuses[$item->status]) ? $statuses[$item->status] : $item->status); ?>"
                                                                data-due="<?php echo esc_attr($item->due_date ? $item->due_date : 'No due date'); ?>"
                                                                data-reward="<?php echo esc_attr((string) (int) $item->reward_points); ?>">
                                                                <span class="dashicons dashicons-visibility" style="font-size:14px;line-height:1.2;"></span>
                                                            </button>

                                                            <?php if (self::current_user_can_edit_task_item($item)) : ?>
                                                                <a class="stm-btn stm-btn-edit action-icon" data-tip="<?php echo esc_attr__('Edit Task', 'neura-task-manager'); ?>" title="<?php echo esc_attr__('Edit Task', 'neura-task-manager'); ?>" aria-label="<?php echo esc_attr__('Edit Task', 'neura-task-manager'); ?>" href="<?php echo esc_url(add_query_arg(array('page' => 'stm-task-manager', 'edit_task' => (int) $item->id), admin_url('admin.php'))); ?>">
                                                                    <span class="dashicons dashicons-edit" style="font-size:14px;line-height:1.2;"></span>
                                                                </a>
                                                            <?php endif; ?>

                                                            <?php if (self::current_user_can_view_task_item($item)) : ?>
                                                                <?php
                                                                if ('todo' === $item->status) {
                                                                    $next_status = 'in_progress';
                                                                    $next_label  = __('Start', 'neura-task-manager');
                                                                    $next_icon   = 'dashicons-controls-play';
                                                                } elseif ('in_progress' === $item->status) {
                                                                    $next_status = 'done';
                                                                    $next_label  = __('Complete', 'neura-task-manager');
                                                                    $next_icon   = 'dashicons-yes-alt';
                                                                } else {
                                                                    $next_status = 'todo';
                                                                    $next_label  = __('Reopen', 'neura-task-manager');
                                                                    $next_icon   = 'dashicons-update';
                                                                }

                                                                $status_url = wp_nonce_url(
                                                                    add_query_arg(
                                                                        array(
                                                                            'action'  => 'stm_update_status',
                                                                            'task_id' => (int) $item->id,
                                                                            'status'  => $next_status,
                                                                        ),
                                                                        admin_url('admin-post.php')
                                                                    ),
                                                                    'stm_update_status'
                                                                );
                                                                ?>
                                                                <a class="stm-btn stm-btn-status action-icon" data-tip="<?php echo esc_attr($next_label); ?>" title="<?php echo esc_attr($next_label); ?>" aria-label="<?php echo esc_attr($next_label); ?>" href="<?php echo esc_url($status_url); ?>">
                                                                    <span class="dashicons <?php echo esc_attr($next_icon); ?>" style="font-size:14px;line-height:1.2;"></span>
                                                                </a>
                                                            <?php endif; ?>

                                                            <?php if (self::current_user_can_delete_task_item($item)) : ?>
                                                                <?php
                                                                $delete_url = wp_nonce_url(
                                                                    add_query_arg(
                                                                        array(
                                                                            'action'  => 'stm_delete_task',
                                                                            'task_id' => (int) $item->id,
                                                                        ),
                                                                        admin_url('admin-post.php')
                                                                    ),
                                                                    'stm_delete_task'
                                                                );
                                                                ?>
                                                                <a class="stm-btn stm-btn-delete action-icon" data-tip="<?php echo esc_attr__('Delete Task', 'neura-task-manager'); ?>" title="<?php echo esc_attr__('Delete Task', 'neura-task-manager'); ?>" aria-label="<?php echo esc_attr__('Delete Task', 'neura-task-manager'); ?>" href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('Delete this task?');">
                                                                    <span class="dashicons dashicons-trash" style="font-size:14px;line-height:1.2;"></span>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="stm-task-cards">
                                <?php if (empty($tasks)) : ?>
                                    <p><?php echo esc_html__('No tasks found.', 'neura-task-manager'); ?></p>
                                <?php else : ?>
                                    <?php foreach ($tasks as $item) : ?>
                                        <div class="stm-task-item">
                                            <h4><?php echo esc_html($item->title); ?></h4>
                                            <?php if (! empty($item->description)) : ?>
                                                <div class="stm-meta"><?php echo esc_html($item->description); ?></div>
                                            <?php endif; ?>

                                            <div class="stm-task-grid">
                                                <div><span class="stm-k"><?php echo esc_html__('Assigned By', 'neura-task-manager'); ?></span><br><?php echo esc_html(self::user_name((int) $item->created_by)); ?></div>
                                                <?php if ($show_assigned_to) : ?><div><span class="stm-k"><?php echo esc_html__('Assigned To', 'neura-task-manager'); ?></span><br><?php echo esc_html(self::user_name((int) $item->assigned_to)); ?></div><?php endif; ?>
                                                <div><span class="stm-k"><?php echo esc_html__('Reward', 'neura-task-manager'); ?></span><br><span class="stm-points">+<?php echo esc_html((string) (int) $item->reward_points); ?></span></div>
                                                <div><span class="stm-k"><?php echo esc_html__('Priority', 'neura-task-manager'); ?></span><br><span class="stm-badge <?php echo esc_attr(self::priority_badge_class($item->priority)); ?>"><?php echo esc_html(isset($priorities[$item->priority]) ? $priorities[$item->priority] : $item->priority); ?></span></div>
                                                <div><span class="stm-k"><?php echo esc_html__('Status', 'neura-task-manager'); ?></span><br><span class="stm-badge <?php echo esc_attr(self::status_badge_class($item->status)); ?>"><?php echo esc_html(isset($statuses[$item->status]) ? $statuses[$item->status] : $item->status); ?></span></div>
                                                <div><span class="stm-k"><?php echo esc_html__('Due Date', 'neura-task-manager'); ?></span><br><?php echo esc_html($item->due_date ? $item->due_date : 'No due date'); ?></div>
                                                <div><span class="stm-k"><?php echo esc_html__('Updated', 'neura-task-manager'); ?></span><br><?php echo esc_html(mysql2date('Y-m-d H:i', $item->updated_at)); ?></div>
                                            </div>

                                            <div class="stm-action-group">
                                                <button type="button" class="stm-btn stm-btn-view stm-view-btn"
                                                    data-title="<?php echo esc_attr($item->title); ?>"
                                                    data-description="<?php echo esc_attr((string) $item->description); ?>"
                                                    data-assignedby="<?php echo esc_attr(self::user_name((int) $item->created_by)); ?>"
                                                    data-assignedto="<?php echo esc_attr(self::user_name((int) $item->assigned_to)); ?>"
                                                    data-priority="<?php echo esc_attr(isset($priorities[$item->priority]) ? $priorities[$item->priority] : $item->priority); ?>"
                                                    data-status="<?php echo esc_attr(isset($statuses[$item->status]) ? $statuses[$item->status] : $item->status); ?>"
                                                    data-due="<?php echo esc_attr($item->due_date ? $item->due_date : 'No due date'); ?>"
                                                    data-reward="<?php echo esc_attr((string) (int) $item->reward_points); ?>">
                                                    <?php echo esc_html__('View', 'neura-task-manager'); ?>
                                                </button>

                                                <?php if (self::current_user_can_edit_task_item($item)) : ?>
                                                    <a class="stm-btn stm-btn-edit" href="<?php echo esc_url(add_query_arg(array('page' => 'stm-task-manager', 'edit_task' => (int) $item->id), admin_url('admin.php'))); ?>"><?php echo esc_html__('Edit', 'neura-task-manager'); ?></a>
                                                <?php endif; ?>

                                                <?php if (self::current_user_can_delete_task_item($item)) : ?>
                                                    <?php $delete_url_m = wp_nonce_url(add_query_arg(array('action' => 'stm_delete_task', 'task_id' => (int) $item->id), admin_url('admin-post.php')), 'stm_delete_task'); ?>
                                                    <a class="stm-btn stm-btn-delete" href="<?php echo esc_url($delete_url_m); ?>" onclick="return confirm('Delete this task?');"><?php echo esc_html__('Delete', 'neura-task-manager'); ?></a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($total_pages > 1) : ?>
                                <div class="stm-pagination">
                                    <?php for ($page_num = 1; $page_num <= $total_pages; $page_num++) : ?>
                                        <?php
                                        $page_url = add_query_arg(array_merge($backend_pagination_base_args, array('stmb_page' => $page_num)), admin_url('admin.php'));
                                        $is_active_page = $page_num === $current_page;
                                        ?>
                                        <a class="stm-page-link <?php echo $is_active_page ? 'is-active' : ''; ?>" href="<?php echo esc_url($page_url); ?>">
                                            <?php echo esc_html((string) $page_num); ?>
                                        </a>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>

            <?php if ($is_settings_manager) : ?>
            <dialog id="stm-task-dialog" class="stm-admin-dialog">
                <div class="stm-dialog-head">
                    <strong><?php echo esc_html($task ? __('Edit Task', 'neura-task-manager') : __('Add New Task', 'neura-task-manager')); ?></strong>
                    <button type="button" class="stm-btn stm-btn-secondary" id="stm-close-task-dialog"><?php echo esc_html__('Close', 'neura-task-manager'); ?></button>
                </div>
                <div class="stm-dialog-body">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="stm_save_task" />
                        <input type="hidden" name="task_id" value="<?php echo esc_attr($task ? (int) $task->id : 0); ?>" />
                        <?php wp_nonce_field('stm_save_task'); ?>
                        <div class="stm-form-row">
                            <label><?php echo esc_html__('Task Title', 'neura-task-manager'); ?></label>
                            <input name="title" type="text" class="stm-input" required value="<?php echo esc_attr($task ? $task->title : ''); ?>" />
                        </div>
                        <div class="stm-form-row">
                            <label><?php echo esc_html__('Description', 'neura-task-manager'); ?></label>
                            <textarea name="description" class="stm-textarea"><?php echo esc_textarea($task ? $task->description : ''); ?></textarea>
                        </div>
                        <div class="stm-grid-2">
                            <div class="stm-form-row">
                                <label><?php echo esc_html__('Assign To', 'neura-task-manager'); ?></label>
                                <select name="assigned_to" class="stm-select">
                                    <option value="0"><?php echo esc_html__('Unassigned', 'neura-task-manager'); ?></option>
                                    <?php foreach ($assignable_users as $assignable_user) : ?>
                                        <option value="<?php echo esc_attr((string) $assignable_user->ID); ?>" <?php selected($task ? (int) $task->assigned_to : 0, (int) $assignable_user->ID); ?>>
                                            <?php echo esc_html($assignable_user->display_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="stm-form-row">
                                <label><?php echo esc_html__('Due Date', 'neura-task-manager'); ?></label>
                                <input name="due_date" type="date" class="stm-input" value="<?php echo esc_attr($task ? $task->due_date : ''); ?>" <?php echo ! empty($settings['require_due_date']) ? 'required' : ''; ?> />
                            </div>
                        </div>
                        <div class="stm-grid-2">
                            <div class="stm-form-row">
                                <label><?php echo esc_html__('Priority', 'neura-task-manager'); ?></label>
                                <select name="priority" class="stm-select">
                                    <?php foreach ($priorities as $priority_key => $priority_label) : ?>
                                        <option value="<?php echo esc_attr($priority_key); ?>" <?php selected($task ? $task->priority : $settings['default_priority'], $priority_key); ?>><?php echo esc_html($priority_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="stm-form-row">
                                <label><?php echo esc_html__('Reward Points', 'neura-task-manager'); ?></label>
                                <input name="reward_points" type="number" min="0" class="stm-input" value="<?php echo esc_attr($task ? (string) $task->reward_points : (string) self::default_reward_for_priority($settings['default_priority'])); ?>" />
                            </div>
                        </div>
                        <button type="submit" class="stm-btn stm-btn-primary">
                            <span class="dashicons dashicons-plus-alt2" style="font-size:14px;line-height:1.2;"></span>
                            <?php echo esc_html($task ? __('Update Task', 'neura-task-manager') : __('Create Task', 'neura-task-manager')); ?>
                        </button>
                    </form>
                </div>
            </dialog>
            <?php endif; ?>
            <dialog id="stm-leaderboard-dialog" class="stm-admin-dialog">
                <div class="stm-dialog-head">
                    <strong><?php echo esc_html__('Leaderboard', 'neura-task-manager'); ?></strong>
                    <button type="button" class="stm-btn stm-btn-secondary" id="stm-close-leaderboard-dialog"><?php echo esc_html__('Close', 'neura-task-manager'); ?></button>
                </div>
                <div class="stm-dialog-body">
                    <?php if (empty($leaderboard)) : ?>
                        <p><?php echo esc_html__('No points earned yet.', 'neura-task-manager'); ?></p>
                    <?php else : ?>
                        <ul class="stm-leader-list">
                            <?php foreach ($leaderboard as $index => $row_user) : ?>
                                <?php $rank = $index + 1; ?>
                                <li class="stm-leader-item">
                                    <span>
                                        <span class="stm-medal"><?php echo esc_html(self::medal_for_rank($rank)); ?></span>
                                        <?php if ($rank > 3) : ?><?php echo esc_html('#' . $rank); ?><?php endif; ?>
                                        <?php echo esc_html($row_user->display_name); ?>
                                    </span>
                                    <strong class="stm-points"><?php echo esc_html((string) (int) get_user_meta($row_user->ID, self::USER_POINTS_META, true)); ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </dialog>

            <dialog id="stm-view-dialog">
                <div class="stm-dialog-head">
                    <strong id="stm-view-title"></strong>
                    <button type="button" class="stm-btn stm-btn-secondary" id="stm-close-dialog"><?php echo esc_html__('Close', 'neura-task-manager'); ?></button>
                </div>
                <div class="stm-dialog-body">
                    <p id="stm-view-description"></p>
                    <p><strong><?php echo esc_html__('Assigned By:', 'neura-task-manager'); ?></strong> <span id="stm-view-assignedby"></span></p>
                    <?php if ($show_assigned_to) : ?><p><strong><?php echo esc_html__('Assigned To:', 'neura-task-manager'); ?></strong> <span id="stm-view-assignedto"></span></p><?php endif; ?>
                    <p><strong><?php echo esc_html__('Priority:', 'neura-task-manager'); ?></strong> <span id="stm-view-priority"></span></p>
                    <p><strong><?php echo esc_html__('Status:', 'neura-task-manager'); ?></strong> <span id="stm-view-status"></span></p>
                    <p><strong><?php echo esc_html__('Due Date:', 'neura-task-manager'); ?></strong> <span id="stm-view-due"></span></p>
                    <p><strong><?php echo esc_html__('Reward Points:', 'neura-task-manager'); ?></strong> +<span id="stm-view-reward"></span></p>
                </div>
            </dialog>
        </div>
        <?php
    }

    public static function render_settings_page() {
        if (! self::current_user_can_manage_settings()) {
            wp_die(esc_html__('You are not allowed to access settings.', 'neura-task-manager'));
        }

        $settings   = self::get_settings();
        $roles      = self::available_roles();
        $statuses   = self::allowed_statuses();
        $priorities = self::allowed_priorities();
        $pages      = get_pages(array('sort_column' => 'post_title', 'sort_order' => 'ASC'));
        $leaderboard_users = self::get_leaderboard(200);
        $assignable_users = self::get_assignable_users();
        $slack_command_endpoint = rest_url('neura-task-manager/v1/slack-command');
        $slack_guide_pdf_url = plugins_url('Slack-Integration-Guide.pdf', __FILE__);
        $slack_guide_text_url = plugins_url('Slack-Integration-Guide-Clean.txt', __FILE__);
        $pro_upgrade_url = self::pro_upgrade_url();
        $lock_dashboard_title = ! self::is_pro_feature_available('custom_dashboard_title');
        $lock_guest_style = ! self::is_pro_feature_available('guest_style_controls');
        $lock_auto_refresh = ! self::is_pro_feature_available('auto_refresh_controls');
        $lock_tasks_per_page = ! self::is_pro_feature_available('tasks_per_page_control');
        $lock_leaderboard_reset = ! self::is_leaderboard_reset_available();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $notice_type = isset($_GET['stm_notice']) ? sanitize_key(wp_unslash($_GET['stm_notice'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $notice_msg  = isset($_GET['stm_msg']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['stm_msg']))) : '';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Task Manager Settings', 'neura-task-manager'); ?></h1>

            <?php if ($notice_type && $notice_msg) : ?>
                <div class="notice notice-<?php echo esc_attr('success' === $notice_type ? 'success' : 'error'); ?> is-dismissible">
                    <p><?php echo esc_html($notice_msg); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="stm_save_settings" />
                <?php wp_nonce_field('stm_save_settings'); ?>

                <h2><?php echo esc_html__('Access & Visibility', 'neura-task-manager'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Task Access Roles', 'neura-task-manager'); ?></th>
                        <td>
                            <?php foreach ($roles as $role_key => $role_label) : ?>
                                <label style="display:block;margin-bottom:4px;">
                                    <input type="checkbox" name="access_roles[]" value="<?php echo esc_attr($role_key); ?>" <?php checked(in_array($role_key, $settings['access_roles'], true)); ?> />
                                    <?php echo esc_html($role_label . ' (' . $role_key . ')'); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Settings Access Roles', 'neura-task-manager'); ?></th>
                        <td>
                            <?php foreach ($roles as $role_key => $role_label) : ?>
                                <label style="display:block;margin-bottom:4px;">
                                    <input type="checkbox" name="settings_roles[]" value="<?php echo esc_attr($role_key); ?>" <?php checked(in_array($role_key, $settings['settings_roles'], true)); ?> />
                                    <?php echo esc_html($role_label . ' (' . $role_key . ')'); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-visibility"><?php echo esc_html__('Task Visibility', 'neura-task-manager'); ?></label></th>
                        <td>
                            <select id="stm-visibility" name="visibility">
                                <option value="all" <?php selected($settings['visibility'], 'all'); ?>><?php echo esc_html__('Show all tasks', 'neura-task-manager'); ?></option>
                                <option value="own" <?php selected($settings['visibility'], 'own'); ?>><?php echo esc_html__('Show only created/assigned tasks (except settings managers)', 'neura-task-manager'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Employees See Own Tasks Only', 'neura-task-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="employee_own_tasks_only" value="1" <?php checked(! empty($settings['employee_own_tasks_only'])); ?> />
                                <?php echo esc_html__('Non admin/editor users can only see tasks assigned to them or created by them.', 'neura-task-manager'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Frontend Dashboard', 'neura-task-manager'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable Frontend Dashboard', 'neura-task-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="frontend_enabled" value="1" <?php checked(! empty($settings['frontend_enabled'])); ?> />
                                <?php echo esc_html__('Allow selected roles to use dashboard shortcode on frontend.', 'neura-task-manager'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Use shortcode: [stm_frontend_dashboard]', 'neura-task-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Frontend Roles', 'neura-task-manager'); ?></th>
                        <td>
                            <?php foreach ($roles as $role_key => $role_label) : ?>
                                <label style="display:block;margin-bottom:4px;">
                                    <input type="checkbox" name="frontend_roles[]" value="<?php echo esc_attr($role_key); ?>" <?php checked(in_array($role_key, $settings['frontend_roles'], true)); ?> />
                                    <?php echo esc_html($role_label . ' (' . $role_key . ')'); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-frontend-page-id"><?php echo esc_html__('Frontend Dashboard Page', 'neura-task-manager'); ?></label></th>
                        <td>
                            <select id="stm-frontend-page-id" name="frontend_page_id">
                                <option value="0"><?php echo esc_html__('Select a page', 'neura-task-manager'); ?></option>
                                <?php foreach ($pages as $page) : ?>
                                    <option value="<?php echo esc_attr((string) $page->ID); ?>" <?php selected((int) $settings['frontend_page_id'], (int) $page->ID); ?>>
                                        <?php echo esc_html($page->post_title . ' (#' . $page->ID . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-frontend-tasks-home-url"><?php echo esc_html__('Tasks Home URL', 'neura-task-manager'); ?></label></th>
                        <td>
                            <input id="stm-frontend-tasks-home-url" type="url" name="frontend_tasks_home_url" value="<?php echo esc_attr((string) $settings['frontend_tasks_home_url']); ?>" class="regular-text" placeholder="https://example.com/tasks/" />
                            <p class="description"><?php echo esc_html__('Optional. Used for Tasks Home button and forced user redirects. If empty, Frontend Dashboard Page URL is used.', 'neura-task-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-frontend-login-url"><?php echo esc_html__('Login Page URL', 'neura-task-manager'); ?></label></th>
                        <td>
                            <input id="stm-frontend-login-url" type="url" name="frontend_login_url" value="<?php echo esc_attr((string) $settings['frontend_login_url']); ?>" class="regular-text" placeholder="https://example.com/login/" />
                            <p class="description"><?php echo esc_html__('Optional. Used for frontend "Login To Continue" button. If empty, WordPress default login URL is used.', 'neura-task-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-frontend-login-redirect-param"><?php echo esc_html__('Login Redirect Parameter', 'neura-task-manager'); ?></label></th>
                        <td>
                            <input id="stm-frontend-login-redirect-param" type="text" name="frontend_login_redirect_param" value="<?php echo esc_attr((string) $settings['frontend_login_redirect_param']); ?>" class="regular-text" placeholder="redirect_to" />
                            <p class="description"><?php echo esc_html__('Query parameter name used by your login page for redirect target. Default: redirect_to', 'neura-task-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-frontend-logout-redirect-url"><?php echo esc_html__('Logout Redirect URL', 'neura-task-manager'); ?></label></th>
                        <td>
                            <input id="stm-frontend-logout-redirect-url" type="url" name="frontend_logout_redirect_url" value="<?php echo esc_attr((string) $settings['frontend_logout_redirect_url']); ?>" class="regular-text" placeholder="https://example.com/logged-out/" />
                            <p class="description"><?php echo esc_html__('Optional. If set, users will be redirected to this URL after logout.', 'neura-task-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-guest-login-title"><?php echo esc_html__('Guest Login Title', 'neura-task-manager'); ?></label></th>
                        <td>
                            <input id="stm-guest-login-title" type="text" name="guest_login_title" value="<?php echo esc_attr((string) $settings['guest_login_title']); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-guest-login-subtitle"><?php echo esc_html__('Guest Login Subtitle', 'neura-task-manager'); ?></label></th>
                        <td>
                            <input id="stm-guest-login-subtitle" type="text" name="guest_login_subtitle" value="<?php echo esc_attr((string) $settings['guest_login_subtitle']); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-guest-login-button-text"><?php echo esc_html__('Login Button Text', 'neura-task-manager'); ?></label></th>
                        <td>
                            <input id="stm-guest-login-button-text" type="text" name="guest_login_button_text" value="<?php echo esc_attr((string) $settings['guest_login_button_text']); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-guest-login-note"><?php echo esc_html__('Guest Login Note', 'neura-task-manager'); ?></label></th>
                        <td>
                            <input id="stm-guest-login-note" type="text" name="guest_login_note" value="<?php echo esc_attr((string) $settings['guest_login_note']); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Login Button Style', 'neura-task-manager'); ?></th>
                        <td>
                            <label for="stm-guest-login-btn-start" style="margin-right:12px;">
                                <?php echo esc_html__('Gradient Start', 'neura-task-manager'); ?>
                                <input id="stm-guest-login-btn-start" type="color" name="guest_login_button_color_start" value="<?php echo esc_attr((string) $settings['guest_login_button_color_start']); ?>" <?php disabled($lock_guest_style); ?> />
                            </label>
                            <label for="stm-guest-login-btn-end" style="margin-right:12px;">
                                <?php echo esc_html__('Gradient End', 'neura-task-manager'); ?>
                                <input id="stm-guest-login-btn-end" type="color" name="guest_login_button_color_end" value="<?php echo esc_attr((string) $settings['guest_login_button_color_end']); ?>" <?php disabled($lock_guest_style); ?> />
                            </label>
                            <label for="stm-guest-login-btn-text" style="margin-right:12px;">
                                <?php echo esc_html__('Text Color', 'neura-task-manager'); ?>
                                <input id="stm-guest-login-btn-text" type="color" name="guest_login_button_text_color" value="<?php echo esc_attr((string) $settings['guest_login_button_text_color']); ?>" <?php disabled($lock_guest_style); ?> />
                            </label>
                            <label for="stm-guest-login-btn-radius">
                                <?php echo esc_html__('Radius', 'neura-task-manager'); ?>
                                <input id="stm-guest-login-btn-radius" type="number" min="0" max="30" step="1" name="guest_login_button_radius" value="<?php echo esc_attr((string) (int) $settings['guest_login_button_radius']); ?>" style="width:84px;" <?php disabled($lock_guest_style); ?> />
                            </label>
                            <p class="description"><?php echo esc_html__('Customize the frontend guest login button style.', 'neura-task-manager'); ?></p>
                            <?php if ($lock_guest_style) : ?>
                                <p class="description"><strong><?php echo esc_html__('Pro feature:', 'neura-task-manager'); ?></strong> <a href="<?php echo esc_url($pro_upgrade_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Upgrade to unlock style controls', 'neura-task-manager'); ?></a></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Redirect After Login', 'neura-task-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="frontend_redirect" value="1" <?php checked(! empty($settings['frontend_redirect'])); ?> />
                                <?php echo esc_html__('Redirect selected frontend roles to the frontend dashboard page after login.', 'neura-task-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-dashboard-title"><?php echo esc_html__('Dashboard Title', 'neura-task-manager'); ?></label></th>
                        <td>
                            <input id="stm-dashboard-title" type="text" name="dashboard_title" value="<?php echo esc_attr((string) $settings['dashboard_title']); ?>" class="regular-text" <?php disabled($lock_dashboard_title); ?> />
                            <p class="description"><?php echo esc_html__('Custom title shown on frontend and backend task dashboards.', 'neura-task-manager'); ?></p>
                            <?php if ($lock_dashboard_title) : ?>
                                <p class="description"><strong><?php echo esc_html__('Pro feature:', 'neura-task-manager'); ?></strong> <a href="<?php echo esc_url($pro_upgrade_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Upgrade to unlock custom dashboard title', 'neura-task-manager'); ?></a></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Force Tasks Page Only', 'neura-task-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="frontend_force_tasks_only" value="1" <?php checked(! empty($settings['frontend_force_tasks_only'])); ?> />
                                <?php echo esc_html__('Force selected frontend roles to always land on tasks page and block WordPress dashboard access (except settings managers).', 'neura-task-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable Employee Task Filter', 'neura-task-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="admin_employee_filter_enabled" value="1" <?php checked(! empty($settings['admin_employee_filter_enabled'])); ?> />
                                <?php echo esc_html__('Show employee-name filter for admin/editor task tables (backend and frontend dashboard).', 'neura-task-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable Auto Refresh', 'neura-task-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_refresh_enabled" value="1" <?php checked(! empty($settings['auto_refresh_enabled'])); ?> <?php disabled($lock_auto_refresh); ?> />
                                <?php echo esc_html__('Auto-refresh task dashboards for latest updates.', 'neura-task-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Auto Refresh On', 'neura-task-manager'); ?></th>
                        <td>
                            <label style="display:block;margin-bottom:4px;">
                                <input type="checkbox" name="auto_refresh_frontend" value="1" <?php checked(! empty($settings['auto_refresh_frontend'])); ?> <?php disabled($lock_auto_refresh); ?> />
                                <?php echo esc_html__('Frontend dashboard', 'neura-task-manager'); ?>
                            </label>
                            <label style="display:block;">
                                <input type="checkbox" name="auto_refresh_backend" value="1" <?php checked(! empty($settings['auto_refresh_backend'])); ?> <?php disabled($lock_auto_refresh); ?> />
                                <?php echo esc_html__('Backend dashboard', 'neura-task-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-auto-refresh-user-scope"><?php echo esc_html__('Auto Refresh User Condition', 'neura-task-manager'); ?></label></th>
                        <td>
                            <select id="stm-auto-refresh-user-scope" name="auto_refresh_user_scope" <?php disabled($lock_auto_refresh); ?>>
                                <option value="all" <?php selected($settings['auto_refresh_user_scope'], 'all'); ?>><?php echo esc_html__('All users', 'neura-task-manager'); ?></option>
                                <option value="admin_editor_only" <?php selected($settings['auto_refresh_user_scope'], 'admin_editor_only'); ?>><?php echo esc_html__('Admin/Editor only', 'neura-task-manager'); ?></option>
                                <option value="users_only" <?php selected($settings['auto_refresh_user_scope'], 'users_only'); ?>><?php echo esc_html__('Non admin/editor users only', 'neura-task-manager'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Refresh Only When Tab Is Active', 'neura-task-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_refresh_only_visible" value="1" <?php checked(! empty($settings['auto_refresh_only_visible'])); ?> <?php disabled($lock_auto_refresh); ?> />
                                <?php echo esc_html__('Skip auto refresh when browser tab is in background.', 'neura-task-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-auto-refresh-interval"><?php echo esc_html__('Refresh Interval (seconds)', 'neura-task-manager'); ?></label></th>
                        <td>
                            <input id="stm-auto-refresh-interval" type="number" min="10" max="3600" name="auto_refresh_interval" value="<?php echo esc_attr((string) $settings['auto_refresh_interval']); ?>" <?php disabled($lock_auto_refresh); ?> />
                            <p class="description"><?php echo esc_html__('Recommended: 30 to 120 seconds.', 'neura-task-manager'); ?></p>
                            <?php if ($lock_auto_refresh) : ?>
                                <p class="description"><strong><?php echo esc_html__('Pro feature:', 'neura-task-manager'); ?></strong> <a href="<?php echo esc_url($pro_upgrade_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Upgrade to unlock auto refresh settings', 'neura-task-manager'); ?></a></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-tasks-per-page"><?php echo esc_html__('Tasks Per Page', 'neura-task-manager'); ?></label></th>
                        <td>
                            <input id="stm-tasks-per-page" type="number" min="1" max="100" name="tasks_per_page" value="<?php echo esc_attr((string) $settings['tasks_per_page']); ?>" <?php disabled($lock_tasks_per_page); ?> />
                            <p class="description"><?php echo esc_html__('Controls pagination size on frontend and backend task lists.', 'neura-task-manager'); ?></p>
                            <?php if ($lock_tasks_per_page) : ?>
                                <p class="description"><strong><?php echo esc_html__('Pro feature:', 'neura-task-manager'); ?></strong> <a href="<?php echo esc_url($pro_upgrade_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Upgrade to unlock pagination control', 'neura-task-manager'); ?></a></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Slack Integration', 'neura-task-manager'); ?></h2>
                <?php if (self::is_slack_available()) : ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable Slack Integration', 'neura-task-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="slack_enabled" value="1" <?php checked(! empty($settings['slack_enabled'])); ?> />
                                <?php echo esc_html__('Allow Slack channel task creation and assignment notifications.', 'neura-task-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-slack-command-endpoint"><?php echo esc_html__('Slack Command Endpoint', 'neura-task-manager'); ?></label></th>
                        <td>
                            <input id="stm-slack-command-endpoint" type="text" readonly class="regular-text code" value="<?php echo esc_attr($slack_command_endpoint); ?>" />
                            <p class="description"><?php echo esc_html__('Use this URL in your Slack Slash Command request URL.', 'neura-task-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Integration Guide', 'neura-task-manager'); ?></th>
                        <td>
                            <a class="button button-secondary" href="<?php echo esc_url($slack_guide_pdf_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Open Slack Guide (PDF)', 'neura-task-manager'); ?></a>
                            <a class="button button-link" href="<?php echo esc_url($slack_guide_text_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Open Text Version', 'neura-task-manager'); ?></a>
                            <p class="description"><?php echo esc_html__('Guide files are bundled with this plugin package.', 'neura-task-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-slack-signing-secret"><?php echo esc_html__('Slack Signing Secret', 'neura-task-manager'); ?></label></th>
                        <td>
                            <input id="stm-slack-signing-secret" type="text" name="slack_signing_secret" value="<?php echo esc_attr((string) $settings['slack_signing_secret']); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-slack-bot-token"><?php echo esc_html__('Slack Bot Token', 'neura-task-manager'); ?></label></th>
                        <td>
                            <input id="stm-slack-bot-token" type="text" name="slack_bot_token" value="<?php echo esc_attr((string) $settings['slack_bot_token']); ?>" class="regular-text" />
                            <p class="description"><?php echo esc_html__('Required for posting assignment notifications. Recommended bot scopes: chat:write, im:write.', 'neura-task-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-slack-channel-id"><?php echo esc_html__('Slack Channel ID', 'neura-task-manager'); ?></label></th>
                        <td>
                            <input id="stm-slack-channel-id" type="text" name="slack_channel_id" value="<?php echo esc_attr((string) $settings['slack_channel_id']); ?>" class="regular-text" />
                            <p class="description"><?php echo esc_html__('Example: C0123456789. Restricts command channel and sets notification destination.', 'neura-task-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Allow Task Create From Slack Command', 'neura-task-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="slack_allow_create_from_command" value="1" <?php checked(! empty($settings['slack_allow_create_from_command'])); ?> />
                                <?php echo esc_html__('Create tasks from Slack slash command requests.', 'neura-task-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Notify On Assignment', 'neura-task-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="slack_notify_on_assign" value="1" <?php checked(! empty($settings['slack_notify_on_assign'])); ?> />
                                <?php echo esc_html__('Send Slack channel notification when a task is assigned.', 'neura-task-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Notify Assigned User In DM', 'neura-task-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="slack_notify_on_assign_dm" value="1" <?php checked(! empty($settings['slack_notify_on_assign_dm'])); ?> />
                                <?php echo esc_html__('Send direct bot message to assigned user chat when task is assigned (requires user mapping).', 'neura-task-manager'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('If DM fails, add im:write scope in Slack app and reinstall the app.', 'neura-task-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('User Mapping (WordPress -> Slack User ID)', 'neura-task-manager'); ?></th>
                        <td>
                            <?php foreach ($assignable_users as $assignable_user) : ?>
                                <?php $mapped_id = isset($settings['slack_user_map'][(int) $assignable_user->ID]) ? (string) $settings['slack_user_map'][(int) $assignable_user->ID] : ''; ?>
                                <label style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">
                                    <span style="min-width:180px;"><?php echo esc_html($assignable_user->display_name . ' (#' . $assignable_user->ID . ')'); ?></span>
                                    <input type="text" name="slack_user_map[<?php echo esc_attr((string) (int) $assignable_user->ID); ?>]" value="<?php echo esc_attr($mapped_id); ?>" placeholder="U012ABCDEF" />
                                </label>
                            <?php endforeach; ?>
                            <p class="description"><?php echo esc_html__('Slash command format: @username|Task title|Description|due:YYYY-MM-DD|priority:high|reward:20', 'neura-task-manager'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php else : ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php echo esc_html__('Slack Integration', 'neura-task-manager'); ?></th>
                            <td>
                                <p><?php echo esc_html__('Slack command creation and notifications are available in Pro.', 'neura-task-manager'); ?></p>
                                <p><a class="button button-secondary" href="https://wpneura.com/simple-task-manager/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Upgrade to Pro', 'neura-task-manager'); ?></a></p>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>

                <h2><?php echo esc_html__('Task Permissions', 'neura-task-manager'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Allow User Edit', 'neura-task-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="allow_user_edit" value="1" <?php checked(! empty($settings['allow_user_edit'])); ?> />
                                <?php echo esc_html__('Allow non-settings users to edit tasks.', 'neura-task-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Allow User Delete', 'neura-task-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="allow_user_delete" value="1" <?php checked(! empty($settings['allow_user_delete'])); ?> />
                                <?php echo esc_html__('Allow non-settings users to delete tasks.', 'neura-task-manager'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Task Defaults', 'neura-task-manager'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="stm-default-status"><?php echo esc_html__('Default New Task Status', 'neura-task-manager'); ?></label></th>
                        <td>
                            <select id="stm-default-status" name="default_status">
                                <?php foreach ($statuses as $status_key => $status_label) : ?>
                                    <option value="<?php echo esc_attr($status_key); ?>" <?php selected($settings['default_status'], $status_key); ?>><?php echo esc_html($status_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-default-priority"><?php echo esc_html__('Default New Task Priority', 'neura-task-manager'); ?></label></th>
                        <td>
                            <select id="stm-default-priority" name="default_priority">
                                <?php foreach ($priorities as $priority_key => $priority_label) : ?>
                                    <option value="<?php echo esc_attr($priority_key); ?>" <?php selected($settings['default_priority'], $priority_key); ?>><?php echo esc_html($priority_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Require Due Date', 'neura-task-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_due_date" value="1" <?php checked(! empty($settings['require_due_date'])); ?> />
                                <?php echo esc_html__('Make due date mandatory for all tasks.', 'neura-task-manager'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Rewards', 'neura-task-manager'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable Rewards', 'neura-task-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rewards_enabled" value="1" <?php checked(! empty($settings['rewards_enabled'])); ?> />
                                <?php echo esc_html__('Award points when task is completed.', 'neura-task-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stm-reward-award-mode"><?php echo esc_html__('Award Mode', 'neura-task-manager'); ?></label></th>
                        <td>
                            <select id="stm-reward-award-mode" name="reward_award_mode">
                                <option value="once" <?php selected($settings['reward_award_mode'], 'once'); ?>><?php echo esc_html__('Once per task', 'neura-task-manager'); ?></option>
                                <option value="repeat" <?php selected($settings['reward_award_mode'], 'repeat'); ?>><?php echo esc_html__('Every completion (re-open allowed)', 'neura-task-manager'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Default Points by Priority', 'neura-task-manager'); ?></th>
                        <td>
                            <label><?php echo esc_html__('Low', 'neura-task-manager'); ?> <input type="number" min="0" name="reward_low" value="<?php echo esc_attr((string) $settings['reward_low']); ?>" /></label>
                            &nbsp;&nbsp;
                            <label><?php echo esc_html__('Medium', 'neura-task-manager'); ?> <input type="number" min="0" name="reward_medium" value="<?php echo esc_attr((string) $settings['reward_medium']); ?>" /></label>
                            &nbsp;&nbsp;
                            <label><?php echo esc_html__('High', 'neura-task-manager'); ?> <input type="number" min="0" name="reward_high" value="<?php echo esc_attr((string) $settings['reward_high']); ?>" /></label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'neura-task-manager')); ?>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=stm-task-manager')); ?>"><?php echo esc_html__('Back to Tasks', 'neura-task-manager'); ?></a>
            </form>

            <hr style="margin:24px 0;" />
            <h2><?php echo esc_html__('Leaderboard Management', 'neura-task-manager'); ?></h2>
            <p><?php echo esc_html__('Reset all points, remove a single user from leaderboard, or manually set user points.', 'neura-task-manager'); ?></p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php echo esc_html__('Reset All Points', 'neura-task-manager'); ?></th>
                    <td>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                            <input type="hidden" name="action" value="stm_manage_leaderboard" />
                            <input type="hidden" name="operation" value="reset_all" />
                            <?php wp_nonce_field('stm_manage_leaderboard'); ?>
                            <button type="submit" class="button button-secondary" onclick="return confirm('Reset points for all users?');" <?php disabled($lock_leaderboard_reset); ?>><?php echo esc_html__('Reset Leaderboard', 'neura-task-manager'); ?></button>
                        </form>
                        <?php if ($lock_leaderboard_reset) : ?>
                            <p class="description"><strong><?php echo esc_html__('Pro feature:', 'neura-task-manager'); ?></strong> <a href="<?php echo esc_url($pro_upgrade_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Upgrade to unlock leaderboard reset', 'neura-task-manager'); ?></a></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Remove User', 'neura-task-manager'); ?></th>
                    <td>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <input type="hidden" name="action" value="stm_manage_leaderboard" />
                            <input type="hidden" name="operation" value="remove_user" />
                            <?php wp_nonce_field('stm_manage_leaderboard'); ?>
                            <select name="user_id" required>
                                <option value=""><?php echo esc_html__('Select user from leaderboard', 'neura-task-manager'); ?></option>
                                <?php foreach ($leaderboard_users as $lb_user) : ?>
                                    <option value="<?php echo esc_attr((string) $lb_user->ID); ?>">
                                        <?php echo esc_html($lb_user->display_name . ' (' . (int) get_user_meta($lb_user->ID, self::USER_POINTS_META, true) . ' pts)'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="button button-secondary" onclick="return confirm('Remove this user from leaderboard?');"><?php echo esc_html__('Remove User', 'neura-task-manager'); ?></button>
                        </form>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Set User Points', 'neura-task-manager'); ?></th>
                    <td>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <input type="hidden" name="action" value="stm_manage_leaderboard" />
                            <input type="hidden" name="operation" value="set_points" />
                            <?php wp_nonce_field('stm_manage_leaderboard'); ?>
                            <select name="user_id" required>
                                <option value=""><?php echo esc_html__('Select user', 'neura-task-manager'); ?></option>
                                <?php foreach ($assignable_users as $assignable_user) : ?>
                                    <option value="<?php echo esc_attr((string) $assignable_user->ID); ?>">
                                        <?php echo esc_html($assignable_user->display_name . ' (' . (int) get_user_meta($assignable_user->ID, self::USER_POINTS_META, true) . ' pts)'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" min="0" name="points" value="0" required />
                            <button type="submit" class="button button-primary"><?php echo esc_html__('Update Points', 'neura-task-manager'); ?></button>
                        </form>
                    </td>
                </tr>
            </table>

            <hr style="margin:24px 0;" />
            <h2><?php echo esc_html__('Task Data Tools', 'neura-task-manager'); ?></h2>
            <?php if (self::is_data_tools_available()) : ?>
            <p><?php echo esc_html__('Reset all tasks or export task data in table format.', 'neura-task-manager'); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php echo esc_html__('Reset Tasks', 'neura-task-manager'); ?></th>
                    <td>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                            <input type="hidden" name="action" value="stm_task_data_tools" />
                            <input type="hidden" name="operation" value="reset_tasks" />
                            <?php wp_nonce_field('stm_task_data_tools'); ?>
                            <button type="submit" class="button button-secondary" onclick="return confirm('Reset all tasks? This cannot be undone.');"><?php echo esc_html__('Reset All Tasks', 'neura-task-manager'); ?></button>
                        </form>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Export Tasks', 'neura-task-manager'); ?></th>
                    <td>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <input type="hidden" name="action" value="stm_task_data_tools" />
                            <input type="hidden" name="operation" value="export_tasks" />
                            <?php wp_nonce_field('stm_task_data_tools'); ?>
                            <select name="export_format">
                                <option value="csv"><?php echo esc_html__('CSV', 'neura-task-manager'); ?></option>
                                <option value="xlsx"><?php echo esc_html__('Excel (.xls)', 'neura-task-manager'); ?></option>
                                <option value="pdf"><?php echo esc_html__('PDF Ready (HTML Table)', 'neura-task-manager'); ?></option>
                            </select>
                            <button type="submit" class="button button-primary"><?php echo esc_html__('Download Export', 'neura-task-manager'); ?></button>
                        </form>
                        <p class="description"><?php echo esc_html__('PDF Ready exports a print-friendly table file that can be saved as PDF from browser print dialog.', 'neura-task-manager'); ?></p>
                    </td>
                </tr>
            </table>
            <?php else : ?>
                <p><?php echo esc_html__('Data export and reset tools are available in Pro.', 'neura-task-manager'); ?></p>
                <p><a class="button button-secondary" href="https://wpneura.com/simple-task-manager/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Upgrade to Pro', 'neura-task-manager'); ?></a></p>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (! defined('STM_LITE_VERSION')) {
    define('STM_LITE_VERSION', STM_Simple_Task_Management::VERSION);
}

STM_Simple_Task_Management::init();
