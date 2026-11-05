<?php
/** 一覧の汎用テンプレート: トップ (リロード毎ランダム)・書庫・検索結果 */
get_header();

// 検索を先に判定する — 検索語 + 分類絞込 (ファセット) の複合時に「検索」の文脈を保つ
if (is_search()) {
    echo '<h1 class="ts-archive-title">検索: ' . esc_html(get_search_query()) . '</h1>';
    get_search_form();
    ts_bunko_search_facets();   // ファセット絞込 (T10.4。mu-plugin の includes/render/render-list.php)
} elseif (is_tax()) {
    $term = get_queried_object();
    echo '<h1 class="ts-archive-title">' . esc_html(single_term_title('', false)) . '</h1>';
    if ($term && $term->description) {
        echo '<p class="ts-archive-desc">' . esc_html($term->description) . '</p>';
    }
    if ($term && $term->taxonomy === 'ts_author') {
        ts_bunko_author_links($term);
    }
} elseif (is_home()) {
    echo '<h1 class="ts-archive-title">' . esc_html__('開いたところから読む', 'ts-bunko') . '</h1>';
    echo '<p class="ts-archive-desc">' . esc_html(TS_Render_Text::home_intro((int) wp_count_posts('ts_work')->publish)) . '</p>';
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

// 作者ページ: この人が挿絵を描いた作品 (1 ページ目だけ。specs/S240.md)
if (is_tax('ts_author') && !is_paged()) {
    $illustrated = ts_bunko_illustrated_work_ids(get_queried_object());
    if ($illustrated) {
        echo '<h2 class="ts-archive-title">挿絵を担当した作品</h2>';
        echo '<ul class="ts-worklist">';
        foreach ($illustrated as $wid) ts_bunko_workcard(get_post($wid));
        echo '</ul>';
    }
}

get_footer();
