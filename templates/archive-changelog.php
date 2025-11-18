<?php
/**
 * Archive template for changelog releases
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div class="sources-archive-wrapper">
    <header class="page-header">
        <h1 class="page-title"><?php esc_html_e('Changelog', 'changelogify'); ?></h1>
    </header>

    <div class="sources-archive-content">
        <?php
        if (have_posts()) {
            echo '<div class="sources-changelog">';

            while (have_posts()) {
                the_post();
                $changelogify_version = get_post_meta(get_the_ID(), '_changelogify_version', true);
                $changelogify_sections = get_post_meta(get_the_ID(), '_changelogify_changelog_sections', true);
                ?>

                <article id="post-<?php the_ID(); ?>" <?php post_class('sources-release'); ?>>
                    <header class="sources-release-header">
                        <?php if ($changelogify_version) : ?>
                            <h2 class="sources-version">
                                <a href="<?php the_permalink(); ?>">
                                    <?php
                                    // translators: 1: release version
                                    echo esc_html(sprintf(__('Version %1$s', 'changelogify'), $changelogify_version));
                                    ?>
                                </a>
                            </h2>
                        <?php endif; ?>

                        <time class="sources-date" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                            <?php echo esc_html(get_the_date()); ?>
                        </time>
                    </header>

                    <div class="sources-release-content">
                        <?php
                        if (is_array($changelogify_sections) && !empty($changelogify_sections)) {
                            $changelogify_display = Changelogify_Public_Display::get_instance();
                            // Use reflection to call private method for template
                            $changelogify_reflection = new ReflectionClass($changelogify_display);
                            $changelogify_method = $changelogify_reflection->getMethod('render_sections');
                            $changelogify_method->setAccessible(true);
                            $changelogify_method->invoke($changelogify_display, $changelogify_sections);
                        } else {
                            the_excerpt();
                        }
                        ?>
                    </div>

                    <footer class="sources-release-footer">
                        <a href="<?php the_permalink(); ?>" class="sources-view-release">
                            <?php esc_html_e('View full release', 'changelogify'); ?>
                        </a>
                    </footer>
                </article>

                <?php
            }

            echo '</div>';

            the_posts_pagination();
        } else {
            echo '<p class="sources-no-releases">' . esc_html__('No changelog releases found.', 'changelogify') . '</p>';
        }
        ?>
    </div>
</div>

<?php
get_footer();
?>
