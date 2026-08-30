<?php
/** トップ: 今日の一作 (日付シード) + リロード毎ランダムの出会い + さくいん入口 (設計 4.4) */
get_header();

$pick = function_exists('ts_bunko_todays_pick') ? ts_bunko_todays_pick() : null;
if ($pick) {
    echo '<h1 class="ts-archive-title">いまの一作</h1>';
    echo '<ul class="ts-worklist">';
    ts_bunko_workcard($pick);
    echo '</ul>';
}

echo '<h2 class="ts-archive-title">ひらいたところから読む</h2>';
echo '<p class="ts-archive-desc">開くたびに違う作品をお出しします。'
    . esc_html(wp_count_posts('ts_work')->publish) . ' 篇からの偶然の出会いをどうぞ。</p>';
if (have_posts()) {
    echo '<ul class="ts-worklist">';
    while (have_posts()) {
        the_post();
        if ($pick && get_the_ID() === $pick->ID) continue;
        ts_bunko_workcard(get_post());
    }
    echo '</ul>';
}

// さくいん入口と検索窓は右サイドバーに常設 (ユーザ裁定 2026-08-31)


get_footer();
