<?php
get_header();
while (have_posts()) {
    the_post();
    echo '<article>';
    echo '<h1 class="ts-entry-title">' . esc_html(get_the_title()) . '</h1>';
    echo '<div class="ts-reader">';
    the_content();
    echo '</div></article>';
}
get_footer();
