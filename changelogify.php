<?php
/**
 * Plugin Name: Changelogify
 * Plugin URI: https://www.github.com/erics1337/changelogify
 * Description: Automatic changelog generator from Simple History, WP Activity Log, or native WordPress events. Creates versioned changelog releases with customizable sections.
 * Version: 1.0.1
 * Author: Eric Swanson
 * Author URI: https://www.ericsdevportfolio.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: changelogify
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CHANGELOGIFY_VERSION', '1.0.1');
define('CHANGELOGIFY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHANGELOGIFY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CHANGELOGIFY_PLUGIN_FILE', __FILE__);

/**
 * Main Changelogify plugin class
 */
class Changelogify_Plugin {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once CHANGELOGIFY_PLUGIN_DIR . 'includes/class-cpt-changelog-release.php';
        require_once CHANGELOGIFY_PLUGIN_DIR . 'includes/class-event-sources.php';
        require_once CHANGELOGIFY_PLUGIN_DIR . 'includes/class-release-generator.php';
        require_once CHANGELOGIFY_PLUGIN_DIR . 'includes/class-settings.php';
        require_once CHANGELOGIFY_PLUGIN_DIR . 'includes/class-public-display.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(CHANGELOGIFY_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(CHANGELOGIFY_PLUGIN_FILE, [$this, 'deactivate']);
    }

    /**
     * Initialize plugin components
     */
    public function init() {
        // Initialize components
        Changelogify_CPT_Changelog_Release::get_instance();
        Changelogify_Event_Sources::get_instance();
        Changelogify_Release_Generator::get_instance();
        Changelogify_Settings::get_instance();
        Changelogify_Public_Display::get_instance();
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Register CPT
        Changelogify_CPT_Changelog_Release::get_instance()->register_post_type();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set default options
        if (!get_option('changelogify_settings')) {
            update_option('changelogify_settings', [
                'enabled_sources' => ['native'],
                'event_mapping' => [],
                'date_range_type' => 'since_last_release',
                'cron_enabled' => false,
                'cron_frequency' => 'weekly'
            ]);
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron
        $timestamp = wp_next_scheduled('changelogify_generate_release');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'changelogify_generate_release');
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize the plugin
function changelogify_init() {
    return Changelogify_Plugin::get_instance();
}

changelogify_init();
