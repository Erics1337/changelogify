<?php
/**
 * Custom Post Type: changelog_release
 */

if (!defined('ABSPATH')) {
    exit;
}

class Changelogify_CPT_Changelog_Release {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_changelog_release', [$this, 'save_meta_boxes'], 10, 2);
    }

    /**
     * Register the custom post type
     */
    public function register_post_type() {
        $labels = [
            'name'                  => _x('Changelog Releases', 'Post Type General Name', 'changelogify'),
            'singular_name'         => _x('Changelog Release', 'Post Type Singular Name', 'changelogify'),
            'menu_name'             => __('Changelog', 'changelogify'),
            'name_admin_bar'        => __('Changelog Release', 'changelogify'),
            'archives'              => __('Release Archives', 'changelogify'),
            'attributes'            => __('Release Attributes', 'changelogify'),
            'parent_item_colon'     => __('Parent Release:', 'changelogify'),
            'all_items'             => __('All Releases', 'changelogify'),
            'add_new_item'          => __('Add New Release', 'changelogify'),
            'add_new'               => __('Add New', 'changelogify'),
            'new_item'              => __('New Release', 'changelogify'),
            'edit_item'             => __('Edit Release', 'changelogify'),
            'update_item'           => __('Update Release', 'changelogify'),
            'view_item'             => __('View Release', 'changelogify'),
            'view_items'            => __('View Releases', 'changelogify'),
            'search_items'          => __('Search Release', 'changelogify'),
        ];

        $args = [
            'label'                 => __('Changelog Release', 'changelogify'),
            'description'           => __('Versioned changelog releases', 'changelogify'),
            'labels'                => $labels,
            'supports'              => ['title', 'editor', 'revisions'],
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 25,
            'menu_icon'             => 'dashicons-list-view',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
            'rewrite'               => ['slug' => 'changelog', 'with_front' => false],
        ];

        register_post_type('changelog_release', $args);
    }

    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'changelogify_release_details',
            __('Release Details', 'changelogify'),
            [$this, 'render_release_details_meta_box'],
            'changelog_release',
            'normal',
            'high'
        );

        add_meta_box(
            'changelogify_changelog_sections',
            __('Changelog Sections', 'changelogify'),
            [$this, 'render_changelog_sections_meta_box'],
            'changelog_release',
            'normal',
            'high'
        );
    }

    /**
     * Render release details meta box
     */
    public function render_release_details_meta_box($post) {
        wp_nonce_field('changelogify_save_release_details', 'changelogify_release_details_nonce');

        $version = get_post_meta($post->ID, '_changelogify_version', true);
        $date_from = get_post_meta($post->ID, '_changelogify_date_from', true);
        $date_to = get_post_meta($post->ID, '_changelogify_date_to', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="changelogify_version"><?php _e('Version', 'changelogify'); ?></label></th>
                <td>
                    <input type="text" id="changelogify_version" name="changelogify_version"
                           value="<?php echo esc_attr($version); ?>"
                           class="regular-text" placeholder="1.0.0">
                    <p class="description"><?php _e('Release version number (e.g., 1.0.0)', 'changelogify'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="changelogify_date_from"><?php _e('Date Range From', 'changelogify'); ?></label></th>
                <td>
                    <input type="date" id="changelogify_date_from" name="changelogify_date_from"
                           value="<?php echo esc_attr($date_from); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="changelogify_date_to"><?php _e('Date Range To', 'changelogify'); ?></label></th>
                <td>
                    <input type="date" id="changelogify_date_to" name="changelogify_date_to"
                           value="<?php echo esc_attr($date_to); ?>">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render changelog sections meta box
     */
    public function render_changelog_sections_meta_box($post) {
        wp_nonce_field('changelogify_save_changelog_sections', 'changelogify_changelog_sections_nonce');

        $sections = get_post_meta($post->ID, '_changelogify_changelog_sections', true);
        if (!is_array($sections)) {
            $sections = [
                'added' => [],
                'changed' => [],
                'fixed' => [],
                'removed' => [],
                'security' => []
            ];
        }

        $section_labels = [
            'added' => __('Added', 'changelogify'),
            'changed' => __('Changed', 'changelogify'),
            'fixed' => __('Fixed', 'changelogify'),
            'removed' => __('Removed', 'changelogify'),
            'security' => __('Security', 'changelogify')
        ];

        ?>
        <div class="sources-changelog-sections">
            <?php foreach ($section_labels as $key => $label) :
                $items = isset($sections[$key]) ? $sections[$key] : [];
            ?>
            <div class="sources-section" style="margin-bottom: 20px;">
                <h4><?php echo esc_html($label); ?></h4>
                <textarea name="changelogify_sections[<?php echo esc_attr($key); ?>]"
                          rows="5"
                          class="large-text"
                          placeholder="<?php echo esc_attr(sprintf(__('Enter %s items (one per line)', 'changelogify'), strtolower($label))); ?>"><?php
                    if (is_array($items)) {
                        echo esc_textarea(implode("\n", $items));
                    }
                ?></textarea>
            </div>
            <?php endforeach; ?>
            <p class="description">
                <?php _e('Enter changelog items one per line. These will be automatically populated when generating a release.', 'changelogify'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Save meta boxes
     */
    public function save_meta_boxes($post_id, $post) {
        // Check nonces
        if (!isset($_POST['changelogify_release_details_nonce']) ||
            !wp_verify_nonce($_POST['changelogify_release_details_nonce'], 'changelogify_save_release_details')) {
            return;
        }

        if (!isset($_POST['changelogify_changelog_sections_nonce']) ||
            !wp_verify_nonce($_POST['changelogify_changelog_sections_nonce'], 'changelogify_save_changelog_sections')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save version
        if (isset($_POST['changelogify_version'])) {
            update_post_meta($post_id, '_changelogify_version', sanitize_text_field($_POST['changelogify_version']));
        }

        // Save date range
        if (isset($_POST['changelogify_date_from'])) {
            update_post_meta($post_id, '_changelogify_date_from', sanitize_text_field($_POST['changelogify_date_from']));
        }

        if (isset($_POST['changelogify_date_to'])) {
            update_post_meta($post_id, '_changelogify_date_to', sanitize_text_field($_POST['changelogify_date_to']));
        }

        // Save changelog sections
        if (isset($_POST['changelogify_sections']) && is_array($_POST['changelogify_sections'])) {
            $sections = [];
            foreach ($_POST['changelogify_sections'] as $section_key => $section_value) {
                $lines = explode("\n", $section_value);
                $sections[$section_key] = array_filter(array_map('trim', $lines));
            }
            update_post_meta($post_id, '_changelogify_changelog_sections', $sections);
        }
    }
}
