<?php
/**
 * Event Sources Handler
 * Manages integrations with Simple History, WP Activity Log, and native WordPress events
 */

if (!defined('ABSPATH')) {
    exit;
}

class Changelogify_Event_Sources {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize hooks for native events
        $this->init_native_hooks();
    }

    /**
     * Get events from all enabled sources
     *
     * @param string $date_from Start date (Y-m-d H:i:s)
     * @param string $date_to End date (Y-m-d H:i:s)
     * @return array Array of events
     */
    public function get_events($date_from, $date_to) {
        $settings = get_option('changelogify_settings', []);
        $enabled_sources = isset($settings['enabled_sources']) ? $settings['enabled_sources'] : ['native'];

        $all_events = [];

        // Try Simple History first
        if (in_array('simple_history', $enabled_sources) && $this->is_simple_history_active()) {
            $all_events = array_merge($all_events, $this->get_simple_history_events($date_from, $date_to));
        }

        // Try WP Activity Log
        if (in_array('wp_activity_log', $enabled_sources) && $this->is_wp_activity_log_active()) {
            $all_events = array_merge($all_events, $this->get_wp_activity_log_events($date_from, $date_to));
        }

        // Always include native events if enabled
        if (in_array('native', $enabled_sources)) {
            $all_events = array_merge($all_events, $this->get_native_events($date_from, $date_to));
        }

        // Sort by date
        usort($all_events, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return $all_events;
    }

    /**
     * Check if Simple History is active
     */
    public function is_simple_history_active() {
        return class_exists('SimpleHistory');
    }

    /**
     * Check if WP Activity Log is active
     */
    public function is_wp_activity_log_active() {
        return class_exists('WpSecurityAuditLog') || function_exists('wsal_freemius');
    }

    /**
     * Get events from Simple History
     */
    private function get_simple_history_events($date_from, $date_to) {
        if (!$this->is_simple_history_active()) {
            return [];
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'simple_history';

        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) != $table_name) {
            return [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is derived from $wpdb->prefix and is safe
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name
                 WHERE date >= %s AND date <= %s
                 ORDER BY date DESC",
                $date_from,
                $date_to
            )
        );
        $events = [];

        foreach ($results as $row) {
            $events[] = [
                'date' => $row->date,
                'type' => $row->logger ?? 'unknown',
                'action' => $row->action ?? 'unknown',
                'message' => $row->message ?? '',
                'user_id' => $row->initiator_id ?? 0,
                'source' => 'simple_history',
                'raw' => $row
            ];
        }

        return $events;
    }

    /**
     * Get events from WP Activity Log
     */
    private function get_wp_activity_log_events($date_from, $date_to) {
        if (!$this->is_wp_activity_log_active()) {
            return [];
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wsal_occurrences';

        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) != $table_name) {
            return [];
        }

        $timestamp_from = strtotime($date_from);
        $timestamp_to = strtotime($date_to);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is derived from $wpdb->prefix and is safe
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name
                 WHERE created_on >= %d AND created_on <= %d
                 ORDER BY created_on DESC",
                $timestamp_from,
                $timestamp_to
            )
        );
        $events = [];

        foreach ($results as $row) {
            $events[] = [
                'date' => gmdate('Y-m-d H:i:s', (int) $row->created_on),
                'type' => 'alert_' . ($row->alert_id ?? 'unknown'),
                'action' => $this->get_wsal_action_name($row->alert_id ?? 0),
                'message' => $this->get_wsal_message($row),
                'user_id' => $row->user_id ?? 0,
                'source' => 'wp_activity_log',
                'raw' => $row
            ];
        }

        return $events;
    }

    /**
     * Get action name for WSAL alert ID
     */
    private function get_wsal_action_name($alert_id) {
        // Common WSAL alert IDs mapped to actions
        $alert_map = [
            // Posts
            2001 => 'post_published',
            2002 => 'post_modified',
            2008 => 'post_trashed',

            // Plugins
            5000 => 'plugin_installed',
            5001 => 'plugin_activated',
            5002 => 'plugin_deactivated',
            5003 => 'plugin_uninstalled',
            5004 => 'plugin_upgraded',

            // Themes
            5005 => 'theme_installed',
            5006 => 'theme_activated',
            5007 => 'theme_deleted',

            // WordPress
            6004 => 'wordpress_updated',

            // Users
            4000 => 'user_created',
            4001 => 'user_role_changed',
        ];

        return isset($alert_map[$alert_id]) ? $alert_map[$alert_id] : 'unknown_' . $alert_id;
    }

    /**
     * Get message for WSAL event
     */
    private function get_wsal_message($row) {
        // This would need to fetch metadata from wsal_metadata table
        // For now, return a basic message
        /* translators: 1: WSAL event id */
        return sprintf(__('Activity Log Event #%1$d', 'changelogify'), $row->id ?? 0);
    }

    /**
     * Initialize native WordPress event hooks
     */
    private function init_native_hooks() {
        // Post events
        add_action('transition_post_status', [$this, 'log_post_status_change'], 10, 3);

        // Plugin events
        add_action('activated_plugin', [$this, 'log_plugin_activated'], 10, 2);
        add_action('deactivated_plugin', [$this, 'log_plugin_deactivated'], 10, 2);

        // Theme events
        add_action('switch_theme', [$this, 'log_theme_switched'], 10, 3);

        // WordPress update
        add_action('_core_updated_successfully', [$this, 'log_wp_updated']);
    }

    /**
     * Get native events from custom log
     */
    private function get_native_events($date_from, $date_to) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'changelogify_native_events';

        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) != $table_name) {
            $this->create_native_events_table();
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is derived from $wpdb->prefix and is safe
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name
                 WHERE event_date >= %s AND event_date <= %s
                 ORDER BY event_date DESC",
                $date_from,
                $date_to
            )
        );
        $events = [];

        foreach ($results as $row) {
            $events[] = [
                'date' => $row->event_date,
                'type' => $row->event_type,
                'action' => $row->action,
                'message' => $row->message,
                'user_id' => $row->user_id,
                'source' => 'native',
                'raw' => $row
            ];
        }

        return $events;
    }

    /**
     * Create native events table
     */
    public function create_native_events_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'changelogify_native_events';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_date datetime NOT NULL,
            event_type varchar(50) NOT NULL,
            action varchar(100) NOT NULL,
            message text NOT NULL,
            user_id bigint(20) DEFAULT 0,
            object_id bigint(20) DEFAULT 0,
            object_type varchar(50) DEFAULT '',
            metadata longtext,
            PRIMARY KEY (id),
            KEY event_date (event_date),
            KEY event_type (event_type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Log event to native events table
     */
    private function log_event($event_type, $action, $message, $object_id = 0, $object_type = '', $metadata = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'changelogify_native_events';

        $wpdb->insert(
            $table_name,
            [
                'event_date' => current_time('mysql'),
                'event_type' => $event_type,
                'action' => $action,
                'message' => $message,
                'user_id' => get_current_user_id(),
                'object_id' => $object_id,
                'object_type' => $object_type,
                'metadata' => maybe_serialize($metadata)
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );
    }

    /**
     * Log post status change
     */
    public function log_post_status_change($new_status, $old_status, $post) {
        if ($new_status === $old_status) {
            return;
        }

        if ($new_status === 'publish' && $old_status !== 'publish') {
            /* translators: 1: post type, 2: post title */
            $message = sprintf(
                __('Published %1$s: %2$s', 'changelogify'),
                $post->post_type,
                $post->post_title
            );
            $this->log_event('post', 'publish', $message, $post->ID, $post->post_type);
        } elseif ($old_status === 'publish' && $new_status === 'trash') {
            /* translators: 1: post type, 2: post title */
            $message = sprintf(
                __('Trashed %1$s: %2$s', 'changelogify'),
                $post->post_type,
                $post->post_title
            );
            $this->log_event('post', 'trash', $message, $post->ID, $post->post_type);
        }
    }

    /**
     * Log plugin activation
     */
    public function log_plugin_activated($plugin, $network_wide) {
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false);
        /* translators: 1: plugin name */
        $message = sprintf(__('Activated plugin: %1$s', 'changelogify'), $plugin_data['Name']);
        $this->log_event('plugin', 'activated', $message, 0, 'plugin', ['plugin' => $plugin]);
    }

    /**
     * Log plugin deactivation
     */
    public function log_plugin_deactivated($plugin, $network_wide) {
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false);
        /* translators: 1: plugin name */
        $message = sprintf(__('Deactivated plugin: %1$s', 'changelogify'), $plugin_data['Name']);
        $this->log_event('plugin', 'deactivated', $message, 0, 'plugin', ['plugin' => $plugin]);
    }

    /**
     * Log theme switch
     */
    public function log_theme_switched($new_name, $new_theme, $old_theme) {
        /* translators: 1: theme name */
        $message = sprintf(__('Switched theme to: %1$s', 'changelogify'), $new_name);
        $this->log_event('theme', 'switched', $message, 0, 'theme');
    }

    /**
     * Log WordPress update
     */
    public function log_wp_updated($wp_version) {
        /* translators: 1: WordPress version */
        $message = sprintf(__('Updated WordPress to version %1$s', 'changelogify'), $wp_version);
        $this->log_event('wordpress', 'updated', $message);
    }
}
