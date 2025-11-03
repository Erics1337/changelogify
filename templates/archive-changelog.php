<?php
/**
 * Archive template for changelog releases
 */

get_header();
?>

<div class="sources-archive-wrapper">
    <header class="page-header">
        <h1 class="page-title"><?php _e('Changelog', 'changelogify'); ?></h1>
    </header>

    <div class="sources-archive-content">
        <?php
        if (have_posts()) {
            echo '<div class="sources-changelog">';

            while (have_posts()) {
                the_post();
                $version = get_post_meta(get_the_ID(), '_changelogify_version', true);
                $sections = get_post_meta(get_the_ID(), '_changelogify_changelog_sections', true);
                ?>

                <article id="post-<?php the_ID(); ?>" <?php post_class('sources-release'); ?>>
                    <header class="sources-release-header">
                        <?php if ($version) : ?>
                            <h2 class="sources-version">
                                <a href="<?php the_permalink(); ?>">
                                    Version <?php echo esc_html($version); ?>
                                </a>
                            </h2>
                        <?php endif; ?>

                        <time class="sources-date" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                            <?php echo esc_html(get_the_date()); ?>
                        </time>
                    </header>

                    <div class="sources-release-content">
                        <?php
                        if (is_array($sections) && !empty($sections)) {
                            $display = Changelogify_Public_Display::get_instance();
                            // Use reflection to call private method for template
                            $reflection = new ReflectionClass($display);
                            $method = $reflection->getMethod('render_sections');
                            $method->setAccessible(true);
                            $method->invoke($display, $sections);
                        } else {
                            the_content();
                        }
                        ?>
                    </div>

                    <footer class="sources-release-footer">
                        <a href="<?php the_permalink(); ?>" class="sources-view-release">
                            <?php _e('View full release', 'changelogify'); ?>
                        </a>
                    </footer>
                </article>

                <?php
            }

            echo '</div>';

            the_posts_pagination();
        } else {
            echo '<p class="sources-no-releases">' . __('No changelog releases found.', 'changelogify') . '</p>';
        }
        ?>
    </div>
</div>

<?php
get_footer();
