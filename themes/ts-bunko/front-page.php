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

echo '<h2 class="ts-archive-title">さくいんから探す</h2>';
echo '<ul class="ts-index-list">';
foreach ([['kana', '作者さくいん', '五十音順の全作者'],
          ['timeline', '年表', '1997〜2021 年を初出順に'],
          ['bunrui', '分類さくいん', 'ジャンル・変身のかたち・共有世界'],
          ['vocabulary', 'キーワードさくいん', '当時の目録の全語彙'],
          ['osusume', 'オススメの環', '当時の読者のオススメの繋がり'],
          ['docs', '運営文書・資料', '運営委員会の記録・コラム・文庫前史・ギャラリー']] as [$slug, $label, $desc]) {
    echo '<li><a href="' . esc_url(home_url("/index/$slug/")) . '">' . esc_html($label)
        . '</a> — ' . esc_html($desc) . '</li>';
}
echo '</ul>';
get_search_form();

get_footer();
