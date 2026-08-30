<?php
/** 索引ページ (/index/…/)。中身は mu-plugin の描画部品 */
get_header();
$kind = function_exists('ts_index_kind') ? ts_index_kind() : '';
$titles = ['kana' => '作者索引', 'timeline' => '年表', 'bunrui' => '分類索引',
           'vocabulary' => 'キーワード索引', 'osusume' => 'オススメの環',
           'docs' => '運営文書・資料'];
echo '<h1 class="ts-archive-title">' . esc_html($titles[$kind] ?? '索引') . '</h1>';
$fn = 'ts_bunko_index_' . $kind;
if (function_exists($fn)) {
    $fn();
} else {
    echo '<p>この索引はまだ準備中です。</p>';
}
get_footer();
