<?php
/**
 * Single template for changelog release
 */

get_header();
?>

<div class="sources-single-wrapper">
    <?php
    while (have_posts()) {
        the_post();
        $version = get_post_meta(get_the_ID(), '_changelogify_version', true);
        $date_from = get_post_meta(get_the_ID(), '_changelogify_date_from', true);
        $date_to = get_post_meta(get_the_ID(), '_changelogify_date_to', true);
        $sections = get_post_meta(get_the_ID(), '_changelogify_changelog_sections', true);
        ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class('sources-release'); ?>>
            <header class="sources-release-header">
                <?php if ($version) : ?>
                    <h1 class="sources-version">Version <?php echo esc_html($version); ?></h1>
                <?php else : ?>
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                <?php endif; ?>

                <div class="sources-meta">
                    <time class="sources-date" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                        <?php echo esc_html(get_the_date()); ?>
                    </time>

                    <?php if ($date_from && $date_to) : ?>
                        <span class="sources-date-range">
                            <?php
                            /* translators: 1: start date, 2: end date */
                            printf(
                                '(%s)',
                                sprintf(
                                    esc_html__('Changes from %1$s to %2$s', 'changelogify'),
                                    esc_html($date_from),
                                    esc_html($date_to)
                                )
                            );
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
            </header>

            <div class="entry-content sources-release-content">
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

            <footer class="entry-footer">
                <a href="<?php echo esc_url(get_post_type_archive_link('changelog_release')); ?>">
                    &larr; <?php esc_html_e('Back to all releases', 'changelogify'); ?>
                </a>
            </footer>
        </article>

        <?php
    }
    ?>
</div>

<?php
get_footer();
