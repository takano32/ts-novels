<?php
/** 一覧の汎用テンプレート: トップ (リロード毎ランダム)・書庫・検索結果 */
get_header();

if (is_tax()) {
    $term = get_queried_object();
    echo '<h1 class="ts-archive-title">' . esc_html(single_term_title('', false)) . '</h1>';
    if ($term && $term->description) {
        echo '<p class="ts-archive-desc">' . esc_html($term->description) . '</p>';
    }
    if ($term && $term->taxonomy === 'ts_author' && function_exists('ts_bunko_author_links')) {
        ts_bunko_author_links($term);
    }
} elseif (is_search()) {
    echo '<h1 class="ts-archive-title">検索: ' . esc_html(get_search_query()) . '</h1>';
    get_search_form();
} elseif (is_home()) {
    echo '<h1 class="ts-archive-title">' . esc_html__('ひらいたところから読む', 'ts-bunko') . '</h1>';
    echo '<p class="ts-archive-desc">開くたびに違う作品をお出しします。'
        . esc_html(wp_count_posts('ts_work')->publish) . ' 篇からの偶然の出会いをどうぞ。</p>';
}

if (have_posts()) {
    echo '<ul class="ts-worklist">';
    while (have_posts()) {
        the_post();
        ts_bunko_workcard(get_post());
    }
    echo '</ul>';
    echo '<nav class="ts-pagination">';
    posts_nav_link(' ', '← 前のページ', '次のページ →');
    echo '</nav>';
} else {
    echo '<p>該当する作品が見つかりませんでした。</p>';
}

get_footer();
