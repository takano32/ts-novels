<?php
/** 年別一覧 (/year/YYYY/) */
get_header();
$y = (int) get_query_var('ts_year');
echo '<h1 class="ts-archive-title">' . $y . '年の作品</h1>';
echo '<p class="ts-archive-desc"><a href="' . esc_url(home_url('/index/timeline/')) . '">← 年表へ戻る</a></p>';
if (have_posts()) {
    echo '<ul class="ts-worklist">';
    while (have_posts()) {
        the_post();
        ts_bunko_workcard(get_post());
    }
    echo '</ul>';
} else {
    echo '<p>この年の作品は見つかりませんでした。</p>';
}
get_footer();
