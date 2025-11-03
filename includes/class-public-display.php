<?php
/**
 * Public Display Features
 * Handles Gutenberg block, shortcode, and archive templates
 */

if (!defined('ABSPATH')) {
    exit;
}

class Changelogify_Public_Display {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register shortcode
        add_shortcode('changelog', [$this, 'changelog_shortcode']);

        // Register Gutenberg block
        add_action('init', [$this, 'register_block']);

        // Enqueue frontend styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);

        // Custom archive template
        add_filter('template_include', [$this, 'custom_archive_template']);
    }

    /**
     * Enqueue frontend styles
     */
    public function enqueue_styles() {
        if (is_post_type_archive('changelog_release') || is_singular('changelog_release')) {
            wp_enqueue_style(
                'changelogify-public',
                CHANGELOGIFY_PLUGIN_URL . 'assets/css/public.css',
                [],
                CHANGELOGIFY_VERSION
            );
        }
    }

    /**
     * Register Gutenberg block
     */
    public function register_block() {
        // Register block script
        wp_register_script(
            'changelogify-block',
            CHANGELOGIFY_PLUGIN_URL . 'assets/js/block.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor'],
            CHANGELOGIFY_VERSION
        );

        // Register block
        register_block_type('sources/changelog', [
            'editor_script' => 'changelogify-block',
            'render_callback' => [$this, 'render_changelog_block'],
            'attributes' => [
                'limit' => [
                    'type' => 'number',
                    'default' => 5
                ],
                'showVersion' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showDate' => [
                    'type' => 'boolean',
                    'default' => true
                ]
            ]
        ]);
    }

    /**
     * Render changelog block
     */
    public function render_changelog_block($attributes) {
        $limit = isset($attributes['limit']) ? intval($attributes['limit']) : 5;
        $show_version = isset($attributes['showVersion']) ? $attributes['showVersion'] : true;
        $show_date = isset($attributes['showDate']) ? $attributes['showDate'] : true;

        return $this->render_changelog([
            'limit' => $limit,
            'show_version' => $show_version,
            'show_date' => $show_date
        ]);
    }

    /**
     * Changelog shortcode
     * Usage: [changelog limit="5" show_version="true" show_date="true"]
     */
    public function changelog_shortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 5,
            'show_version' => true,
            'show_date' => true,
            'version' => '' // Show specific version
        ], $atts);

        return $this->render_changelog($atts);
    }

    /**
     * Render changelog
     */
    private function render_changelog($args = []) {
        $defaults = [
            'limit' => 5,
            'show_version' => true,
            'show_date' => true,
            'version' => ''
        ];

        $args = wp_parse_args($args, $defaults);

        $query_args = [
            'post_type' => 'changelog_release',
            'posts_per_page' => intval($args['limit']),
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        // Filter by specific version
        if (!empty($args['version'])) {
            $query_args['meta_query'] = [
                [
                    'key' => '_changelogify_version',
                    'value' => sanitize_text_field($args['version']),
                    'compare' => '='
                ]
            ];
        }

        $query = new WP_Query($query_args);

        ob_start();

        if ($query->have_posts()) {
            echo '<div class="sources-changelog">';

            while ($query->have_posts()) {
                $query->the_post();
                $this->render_release_item(get_the_ID(), $args);
            }

            echo '</div>';
        } else {
            echo '<p class="sources-no-releases">' . __('No changelog releases found.', 'changelogify') . '</p>';
        }

        wp_reset_postdata();

        return ob_get_clean();
    }

    /**
     * Render single release item
     */
    private function render_release_item($post_id, $args) {
        $version = get_post_meta($post_id, '_changelogify_version', true);
        $date_from = get_post_meta($post_id, '_changelogify_date_from', true);
        $date_to = get_post_meta($post_id, '_changelogify_date_to', true);
        $sections = get_post_meta($post_id, '_changelogify_changelog_sections', true);

        ?>
        <article class="sources-release">
            <header class="sources-release-header">
                <?php if ($args['show_version'] && $version) : ?>
                    <h2 class="sources-version">Version <?php echo esc_html($version); ?></h2>
                <?php endif; ?>

                <?php if ($args['show_date']) : ?>
                    <time class="sources-date" datetime="<?php echo esc_attr(get_the_date('c', $post_id)); ?>">
                        <?php echo esc_html(get_the_date('', $post_id)); ?>
                    </time>
                <?php endif; ?>
            </header>

            <div class="sources-release-content">
                <?php
                if (is_array($sections) && !empty($sections)) {
                    $this->render_sections($sections);
                } else {
                    the_content();
                }
                ?>
            </div>

            <footer class="sources-release-footer">
                <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="sources-view-release">
                    <?php _e('View full release', 'changelogify'); ?>
                </a>
            </footer>
        </article>
        <?php
    }

    /**
     * Render changelog sections
     */
    private function render_sections($sections) {
        $section_labels = [
            'added' => __('Added', 'changelogify'),
            'changed' => __('Changed', 'changelogify'),
            'fixed' => __('Fixed', 'changelogify'),
            'removed' => __('Removed', 'changelogify'),
            'security' => __('Security', 'changelogify')
        ];

        $section_icons = [
            'added' => '✨',
            'changed' => '🔄',
            'fixed' => '🐛',
            'removed' => '🗑️',
            'security' => '🔒'
        ];

        foreach ($sections as $key => $items) {
            if (!empty($items) && is_array($items)) {
                $label = isset($section_labels[$key]) ? $section_labels[$key] : ucfirst($key);
                $icon = isset($section_icons[$key]) ? $section_icons[$key] : '';

                echo '<div class="sources-section sources-section-' . esc_attr($key) . '">';
                echo '<h3 class="sources-section-title">';
                if ($icon) {
                    echo '<span class="sources-section-icon">' . $icon . '</span> ';
                }
                echo esc_html($label);
                echo '</h3>';
                echo '<ul class="sources-section-items">';

                foreach ($items as $item) {
                    echo '<li>' . esc_html($item) . '</li>';
                }

                echo '</ul>';
                echo '</div>';
            }
        }
    }

    /**
     * Custom archive template
     */
    public function custom_archive_template($template) {
        if (is_post_type_archive('changelog_release')) {
            $plugin_template = CHANGELOGIFY_PLUGIN_DIR . 'templates/archive-changelog.php';

            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        if (is_singular('changelog_release')) {
            $plugin_template = CHANGELOGIFY_PLUGIN_DIR . 'templates/single-changelog.php';

            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        return $template;
    }
}
