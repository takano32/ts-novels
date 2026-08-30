<?php
/** さくいんページ (/index/…/)。中身は mu-plugin の描画部品 */
get_header();
$kind = get_query_var('ts_index');
$titles = ['kana' => '作者さくいん', 'timeline' => '年表', 'bunrui' => '分類さくいん',
           'vocabulary' => 'キーワードさくいん', 'osusume' => 'オススメの環',
           'docs' => '運営文書・資料'];
echo '<h1 class="ts-archive-title">' . esc_html($titles[$kind] ?? 'さくいん') . '</h1>';
$fn = 'ts_bunko_index_' . $kind;
if (function_exists($fn)) {
    $fn();
} else {
    echo '<p>このさくいんはまだ準備中です。</p>';
}
get_footer();
