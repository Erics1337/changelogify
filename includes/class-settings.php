<?php
/**
 * Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class Changelogify_Settings {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=changelog_release',
            __('Settings', 'changelogify'),
            __('Settings', 'changelogify'),
            'manage_options',
            'sources-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('changelogify_settings', 'changelogify_settings', [$this, 'sanitize_settings']);

        // Event Sources Section
        add_settings_section(
            'changelogify_event_sources',
            __('Event Sources', 'changelogify'),
            [$this, 'event_changelogify_section_callback'],
            'sources-settings'
        );

        add_settings_field(
            'enabled_sources',
            __('Enabled Sources', 'changelogify'),
            [$this, 'enabled_changelogify_callback'],
            'sources-settings',
            'changelogify_event_sources'
        );

        // Event Mapping Section
        add_settings_section(
            'changelogify_event_mapping',
            __('Event to Section Mapping', 'changelogify'),
            [$this, 'event_mapping_section_callback'],
            'sources-settings'
        );

        add_settings_field(
            'event_mapping',
            __('Custom Mappings', 'changelogify'),
            [$this, 'event_mapping_callback'],
            'sources-settings',
            'changelogify_event_mapping'
        );

        // Date Range Section
        add_settings_section(
            'changelogify_date_range',
            __('Default Date Range', 'changelogify'),
            [$this, 'date_range_section_callback'],
            'sources-settings'
        );

        add_settings_field(
            'date_range_type',
            __('Range Type', 'changelogify'),
            [$this, 'date_range_type_callback'],
            'sources-settings',
            'changelogify_date_range'
        );

        // Cron Section
        add_settings_section(
            'changelogify_cron',
            __('Automatic Generation', 'changelogify'),
            [$this, 'cron_section_callback'],
            'sources-settings'
        );

        add_settings_field(
            'cron_enabled',
            __('Enable Cron', 'changelogify'),
            [$this, 'cron_enabled_callback'],
            'sources-settings',
            'changelogify_cron'
        );

        add_settings_field(
            'cron_frequency',
            __('Frequency', 'changelogify'),
            [$this, 'cron_frequency_callback'],
            'sources-settings',
            'changelogify_cron'
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('changelogify_settings'); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('changelogify_settings');
                do_settings_sections('sources-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Section callbacks
     */
    public function event_changelogify_section_callback() {
        echo '<p>' . esc_html__('Select which event sources to use for generating changelogs.', 'changelogify') . '</p>';
    }

    public function event_mapping_section_callback() {
        echo '<p>' . esc_html__('Map specific events to changelog sections (Added, Changed, Fixed, Removed, Security).', 'changelogify') . '</p>';
    }

    public function date_range_section_callback() {
        echo '<p>' . esc_html__('Configure the default date range for generating releases.', 'changelogify') . '</p>';
    }

    public function cron_section_callback() {
        echo '<p>' . esc_html__('Configure automatic changelog generation using WP-Cron.', 'changelogify') . '</p>';
    }

    /**
     * Field callbacks
     */
    public function enabled_changelogify_callback() {
        $settings = get_option('changelogify_settings', []);
        $enabled = isset($settings['enabled_sources']) ? $settings['enabled_sources'] : ['native'];

        $event_sources = Changelogify_Event_Sources::get_instance();
        $sources = [
            'simple_history' => [
                'label' => __('Simple History', 'changelogify'),
                'active' => $event_sources->is_simple_history_active()
            ],
            'wp_activity_log' => [
                'label' => __('WP Activity Log', 'changelogify'),
                'active' => $event_sources->is_wp_activity_log_active()
            ],
            'native' => [
                'label' => __('Native WordPress Events', 'changelogify'),
                'active' => true
            ]
        ];

        foreach ($sources as $key => $source) {
            $is_checked = in_array($key, $enabled, true);
            $is_disabled = !$source['active'];
            $has_status = !$source['active'];

            echo '<label>';
            echo '<input type="checkbox" name="changelogify_settings[enabled_sources][]" value="' . esc_attr($key) . '" ' . checked($is_checked, true, false) . ' ' . disabled($is_disabled, true, false) . '>';
            echo ' ' . esc_html($source['label']);
            if ($has_status) {
                echo ' <em>(' . esc_html__('Not installed', 'changelogify') . ')</em>'; // markup is intentional
            }
            echo '</label><br>';
        }
    }

    public function event_mapping_callback() {
        $settings = get_option('changelogify_settings', []);
        $event_mapping = isset($settings['event_mapping']) ? $settings['event_mapping'] : [];

        echo '<div id="event-mapping-container">';

        if (empty($event_mapping)) {
            $event_mapping = [''];
        }

        foreach ($event_mapping as $event => $section) {
            $this->render_mapping_row($event, $section);
        }

        echo '</div>';

        echo '<button type="button" class="button" onclick="addMappingRow()">' . esc_html__('Add Mapping', 'changelogify') . '</button>';

        ?>
        <script>
        function addMappingRow() {
            var container = document.getElementById('event-mapping-container');
            var newRow = document.createElement('div');
            newRow.style.marginBottom = '10px';
            newRow.innerHTML = `
                <input type="text" name="changelogify_settings[event_mapping_keys][]" placeholder="Event action" class="regular-text">
                →
                <select name="changelogify_settings[event_mapping_values][]">
                    <option value="added"><?php esc_html_e('Added', 'changelogify'); ?></option>
                    <option value="changed"><?php esc_html_e('Changed', 'changelogify'); ?></option>
                    <option value="fixed"><?php esc_html_e('Fixed', 'changelogify'); ?></option>
                    <option value="removed"><?php esc_html_e('Removed', 'changelogify'); ?></option>
                    <option value="security"><?php esc_html_e('Security', 'changelogify'); ?></option>
                </select>
                <button type="button" class="button" onclick="this.parentElement.remove()">Remove</button>
            `;
            container.appendChild(newRow);
        }
        </script>
        <?php
    }

    private function render_mapping_row($event, $section) {
        ?>
        <div style="margin-bottom: 10px;">
            <input type="text" name="changelogify_settings[event_mapping_keys][]"
                   value="<?php echo esc_attr($event); ?>"
                   placeholder="Event action"
                   class="regular-text">
            →
            <select name="changelogify_settings[event_mapping_values][]">
                <option value="added" <?php selected($section, 'added'); ?>><?php esc_html_e('Added', 'changelogify'); ?></option>
                <option value="changed" <?php selected($section, 'changed'); ?>><?php esc_html_e('Changed', 'changelogify'); ?></option>
                <option value="fixed" <?php selected($section, 'fixed'); ?>><?php esc_html_e('Fixed', 'changelogify'); ?></option>
                <option value="removed" <?php selected($section, 'removed'); ?>><?php esc_html_e('Removed', 'changelogify'); ?></option>
                <option value="security" <?php selected($section, 'security'); ?>><?php esc_html_e('Security', 'changelogify'); ?></option>
            </select>
            <button type="button" class="button" onclick="this.parentElement.remove()">Remove</button>
        </div>
        <?php
    }

    public function date_range_type_callback() {
        $settings = get_option('changelogify_settings', []);
        $type = isset($settings['date_range_type']) ? $settings['date_range_type'] : 'since_last_release';

        ?>
        <select name="changelogify_settings[date_range_type]">
            <option value="since_last_release" <?php selected($type, 'since_last_release'); ?>>
                <?php esc_html_e('Since last release', 'changelogify'); ?>
            </option>
            <option value="last_7_days" <?php selected($type, 'last_7_days'); ?>>
                <?php esc_html_e('Last 7 days', 'changelogify'); ?>
            </option>
            <option value="last_30_days" <?php selected($type, 'last_30_days'); ?>>
                <?php esc_html_e('Last 30 days', 'changelogify'); ?>
            </option>
        </select>
        <?php
    }

    public function cron_enabled_callback() {
        $settings = get_option('changelogify_settings', []);
        $enabled = isset($settings['cron_enabled']) ? $settings['cron_enabled'] : false;

        ?>
        <label>
            <input type="checkbox" name="changelogify_settings[cron_enabled]" value="1" <?php checked($enabled, 1); ?>>
            <?php esc_html_e('Enable automatic changelog generation', 'changelogify'); ?>
        </label>
        <?php
    }

    public function cron_frequency_callback() {
        $settings = get_option('changelogify_settings', []);
        $frequency = isset($settings['cron_frequency']) ? $settings['cron_frequency'] : 'weekly';

        ?>
        <select name="changelogify_settings[cron_frequency]">
            <option value="daily" <?php selected($frequency, 'daily'); ?>><?php esc_html_e('Daily', 'changelogify'); ?></option>
            <option value="weekly" <?php selected($frequency, 'weekly'); ?>><?php esc_html_e('Weekly', 'changelogify'); ?></option>
        </select>
        <?php
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = [];

        // Enabled sources
        $sanitized['enabled_sources'] = isset($input['enabled_sources']) && is_array($input['enabled_sources'])
            ? array_map('sanitize_text_field', $input['enabled_sources'])
            : ['native'];

        // Event mapping
        $sanitized['event_mapping'] = [];
        if (isset($input['event_mapping_keys']) && isset($input['event_mapping_values'])) {
            $keys = $input['event_mapping_keys'];
            $values = $input['event_mapping_values'];

            for ($i = 0; $i < count($keys); $i++) {
                if (!empty($keys[$i]) && isset($values[$i])) {
                    $sanitized['event_mapping'][sanitize_text_field($keys[$i])] = sanitize_text_field($values[$i]);
                }
            }
        }

        // Date range type
        $sanitized['date_range_type'] = isset($input['date_range_type'])
            ? sanitize_text_field($input['date_range_type'])
            : 'since_last_release';

        // Cron settings
        $sanitized['cron_enabled'] = isset($input['cron_enabled']) ? (bool) $input['cron_enabled'] : false;
        $sanitized['cron_frequency'] = isset($input['cron_frequency'])
            ? sanitize_text_field($input['cron_frequency'])
            : 'weekly';

        return $sanitized;
    }
}
