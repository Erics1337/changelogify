<?php
/**
 * Release Generator
 * Generates changelog releases from event sources
 */

if (!defined('ABSPATH')) {
    exit;
}

class Changelogify_Release_Generator {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Add admin button
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_changelogify_generate_release', [$this, 'handle_generate_release']);

        // Add WP-Cron
        add_action('changelogify_generate_release', [$this, 'cron_generate_release']);

        // Schedule cron if enabled
        $this->maybe_schedule_cron();
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=changelog_release',
            __('Generate Release', 'changelogify'),
            __('Generate Release', 'changelogify'),
            'manage_options',
            'sources-generate',
            [$this, 'render_generate_page']
        );
    }

    /**
     * Render generate page
     */
    public function render_generate_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'changelogify'));
        }

        $last_release = $this->get_last_release();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Generate Changelog Release', 'changelogify'); ?></h1>

            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET to show admin notice only
            if (isset($_GET['generated']) && sanitize_text_field(wp_unslash($_GET['generated'])) === 'success') : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php esc_html_e('Release generated successfully!', 'changelogify'); ?>
                        <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET to build safe link for convenience
                        if (isset($_GET['post_id'])) : ?>
                            <a href="<?php echo esc_url(get_edit_post_link(absint($_GET['post_id']))); ?>">
                                <?php esc_html_e('View Release', 'changelogify'); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="card">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('changelogify_generate_release', 'changelogify_generate_nonce'); ?>
                    <input type="hidden" name="action" value="changelogify_generate_release">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="version"><?php esc_html_e('Version', 'changelogify'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="version" name="version"
                                       value="<?php echo esc_attr($this->suggest_next_version($last_release)); ?>"
                                       class="regular-text" required>
                                <p class="description">
                                    <?php esc_html_e('Release version number (e.g., 1.0.0)', 'changelogify'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="date_range_type"><?php esc_html_e('Date Range', 'changelogify'); ?></label>
                            </th>
                            <td>
                                <select id="date_range_type" name="date_range_type">
                                    <option value="since_last_release">
                                        <?php esc_html_e('Since last release', 'changelogify'); ?>
                                    </option>
                                    <option value="custom">
                                        <?php esc_html_e('Custom date range', 'changelogify'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>

                        <tr id="custom_date_range" style="display:none;">
                            <th scope="row">
                                <label><?php esc_html_e('Custom Dates', 'changelogify'); ?></label>
                            </th>
                            <td>
                                <label for="date_from"><?php esc_html_e('From:', 'changelogify'); ?></label>
                                <input type="date" id="date_from" name="date_from"
                                       value="<?php echo esc_attr(gmdate('Y-m-d', time() - WEEK_IN_SECONDS)); ?>">
                                <br><br>
                                <label for="date_to"><?php esc_html_e('To:', 'changelogify'); ?></label>
                                <input type="date" id="date_to" name="date_to"
                                       value="<?php echo esc_attr(gmdate('Y-m-d')); ?>">
                            </td>
                        </tr>

                        <?php if ($last_release) : ?>
                        <tr>
                            <th scope="row"><?php esc_html_e('Last Release', 'changelogify'); ?></th>
                            <td>
                                <strong><?php echo esc_html(get_post_meta($last_release->ID, '_changelogify_version', true)); ?></strong>
                                -
                                <?php echo esc_html(get_the_date('', $last_release)); ?>
                                <br>
                                <a href="<?php echo esc_url(get_edit_post_link($last_release->ID)); ?>">
                                    <?php esc_html_e('View last release', 'changelogify'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>

                    <?php submit_button(__('Generate Release', 'changelogify'), 'primary', 'submit', false); ?>
                </form>
            </div>

            <script>
                document.getElementById('date_range_type').addEventListener('change', function() {
                    var customRange = document.getElementById('custom_date_range');
                    if (this.value === 'custom') {
                        customRange.style.display = 'table-row';
                    } else {
                        customRange.style.display = 'none';
                    }
                });
            </script>
        </div>
        <?php
    }

    /**
     * Handle generate release form submission
     */
    public function handle_generate_release() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'changelogify'));
        }

        check_admin_referer('changelogify_generate_release', 'changelogify_generate_nonce');

        $version = isset($_POST['version']) ? sanitize_text_field(wp_unslash($_POST['version'])) : '';
        $date_range_type = isset($_POST['date_range_type']) ? sanitize_text_field(wp_unslash($_POST['date_range_type'])) : 'since_last_release';

        // Determine date range
        if ($date_range_type === 'custom') {
            $date_from = isset($_POST['date_from']) ? sanitize_text_field(wp_unslash($_POST['date_from'])) : gmdate('Y-m-d', time() - WEEK_IN_SECONDS);
            $date_to = isset($_POST['date_to']) ? sanitize_text_field(wp_unslash($_POST['date_to'])) : gmdate('Y-m-d');
        } else {
            $last_release = $this->get_last_release();
            if ($last_release) {
                $date_from = get_post_meta($last_release->ID, '_changelogify_date_to', true);
                if (!$date_from) {
                    $date_from = get_the_date('Y-m-d', $last_release);
                }
            } else {
                $date_from = gmdate('Y-m-d', time() - 30 * DAY_IN_SECONDS);
            }
            $date_to = gmdate('Y-m-d');
        }

        // Generate release
        $post_id = $this->generate_release($version, $date_from, $date_to);

        if ($post_id) {
            wp_redirect(add_query_arg([
                'page' => 'sources-generate',
                'generated' => 'success',
                'post_id' => $post_id
            ], admin_url('edit.php?post_type=changelog_release')));
        } else {
            wp_redirect(add_query_arg([
                'page' => 'sources-generate',
                'generated' => 'error'
            ], admin_url('edit.php?post_type=changelog_release')));
        }
        exit;
    }

    /**
     * Generate a release
     */
    public function generate_release($version, $date_from, $date_to) {
        $event_sources = Changelogify_Event_Sources::get_instance();

        // Convert dates to datetime format
        $date_from_dt = $date_from . ' 00:00:00';
        $date_to_dt = $date_to . ' 23:59:59';

        // Get events
        $events = $event_sources->get_events($date_from_dt, $date_to_dt);

        // Categorize events
        $sections = $this->categorize_events($events);

        // Create post
        $post_id = wp_insert_post([
            // translators: 1: release version number
            'post_title' => sprintf(__('Release %1$s', 'changelogify'), $version),
            'post_status' => 'draft',
            'post_type' => 'changelog_release',
            'post_content' => $this->generate_release_content($sections)
        ]);

        if (!is_wp_error($post_id)) {
            // Save meta
            update_post_meta($post_id, '_changelogify_version', $version);
            update_post_meta($post_id, '_changelogify_date_from', $date_from);
            update_post_meta($post_id, '_changelogify_date_to', $date_to);
            update_post_meta($post_id, '_changelogify_changelog_sections', $sections);

            return $post_id;
        }

        return false;
    }

    /**
     * Categorize events into changelog sections
     */
    private function categorize_events($events) {
        $settings = get_option('changelogify_settings', []);
        $event_mapping = isset($settings['event_mapping']) ? $settings['event_mapping'] : [];

        // Default mapping
        $default_mapping = [
            'post_publish' => 'added',
            'post_modified' => 'changed',
            'plugin_installed' => 'added',
            'plugin_activated' => 'changed',
            'plugin_deactivated' => 'changed',
            'plugin_upgraded' => 'changed',
            'plugin_uninstalled' => 'removed',
            'theme_installed' => 'added',
            'theme_activated' => 'changed',
            'theme_deleted' => 'removed',
            'wordpress_updated' => 'security',
        ];

        $mapping = array_merge($default_mapping, $event_mapping);

        $sections = [
            'added' => [],
            'changed' => [],
            'fixed' => [],
            'removed' => [],
            'security' => []
        ];

        foreach ($events as $event) {
            $action = $event['action'];
            $section = isset($mapping[$action]) ? $mapping[$action] : 'changed';

            if (isset($sections[$section])) {
                $sections[$section][] = $event['message'];
            }
        }

        // Remove duplicates
        foreach ($sections as $key => $items) {
            $sections[$key] = array_unique($items);
        }

        return $sections;
    }

    /**
     * Generate release content
     */
    private function generate_release_content($sections) {
        $content = '';

        $section_labels = [
            'added' => __('Added', 'changelogify'),
            'changed' => __('Changed', 'changelogify'),
            'fixed' => __('Fixed', 'changelogify'),
            'removed' => __('Removed', 'changelogify'),
            'security' => __('Security', 'changelogify')
        ];

        foreach ($sections as $key => $items) {
            if (!empty($items)) {
                $content .= '<h3>' . $section_labels[$key] . '</h3>' . "\n";
                $content .= '<ul>' . "\n";
                foreach ($items as $item) {
                    $content .= '<li>' . esc_html($item) . '</li>' . "\n";
                }
                $content .= '</ul>' . "\n\n";
            }
        }

        return $content;
    }

    /**
     * Get last release
     */
    private function get_last_release() {
        $args = [
            'post_type' => 'changelog_release',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'any'
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            return $query->posts[0];
        }

        return null;
    }

    /**
     * Suggest next version
     */
    private function suggest_next_version($last_release) {
        if (!$last_release) {
            return '1.0.0';
        }

        $last_version = get_post_meta($last_release->ID, '_changelogify_version', true);

        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $last_version, $matches)) {
            $major = (int) $matches[1];
            $minor = (int) $matches[2];
            $patch = (int) $matches[3];

            return sprintf('%d.%d.%d', $major, $minor, $patch + 1);
        }

        return '1.0.0';
    }

    /**
     * Maybe schedule cron
     */
    private function maybe_schedule_cron() {
        $settings = get_option('changelogify_settings', []);

        if (isset($settings['cron_enabled']) && $settings['cron_enabled']) {
            if (!wp_next_scheduled('changelogify_generate_release')) {
                $frequency = isset($settings['cron_frequency']) ? $settings['cron_frequency'] : 'weekly';
                wp_schedule_event(time(), $frequency, 'changelogify_generate_release');
            }
        } else {
            $timestamp = wp_next_scheduled('changelogify_generate_release');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'changelogify_generate_release');
            }
        }
    }

    /**
     * Cron generate release
     */
    public function cron_generate_release() {
        $last_release = $this->get_last_release();

        $date_from = gmdate('Y-m-d', time() - WEEK_IN_SECONDS);
        if ($last_release) {
            $last_date = get_post_meta($last_release->ID, '_changelogify_date_to', true);
            if ($last_date) {
                $date_from = $last_date;
            }
        }

        $date_to = gmdate('Y-m-d');

        $version = $this->suggest_next_version($last_release);

        $this->generate_release($version, $date_from, $date_to);
    }
}
