<?php
/**
 * Single template for changelog release
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div class="sources-single-wrapper">
    <?php
    while (have_posts()) {
        the_post();
        $changelogify_version = get_post_meta(get_the_ID(), '_changelogify_version', true);
        $changelogify_date_from = get_post_meta(get_the_ID(), '_changelogify_date_from', true);
        $changelogify_date_to = get_post_meta(get_the_ID(), '_changelogify_date_to', true);
        $changelogify_sections = get_post_meta(get_the_ID(), '_changelogify_changelog_sections', true);
        ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class('sources-release'); ?>>
            <header class="sources-release-header">
                <?php if ($changelogify_version) : ?>
                    <h1 class="sources-version">Version <?php echo esc_html($changelogify_version); ?></h1>
                <?php else : ?>
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                <?php endif; ?>

                <div class="sources-meta">
                    <time class="sources-date" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                        <?php echo esc_html(get_the_date()); ?>
                    </time>

                    <?php if ($changelogify_date_from && $changelogify_date_to) : ?>
                        <span class="sources-date-range">
                            <?php
                            /* translators: 1: start date, 2: end date */
                            $changelogify_format = __('(Changes from %1$s to %2$s)', 'changelogify');
                            echo esc_html(
                                sprintf(
                                    $changelogify_format,
                                    $changelogify_date_from,
                                    $changelogify_date_to
                                )
                            );
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
            </header>

            <div class="entry-content sources-release-content">
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
