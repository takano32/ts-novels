<?php
/** 索引ページ (/index/…/)。中身は mu-plugin の描画部品 */
get_header();
$kind = function_exists('ts_index_kind') ? ts_index_kind() : '';
$titles = class_exists('TS_Index_Text') ? TS_Index_Text::TITLES : [];   // 題名の表は mu-plugin (document_title_parts と共有)
echo '<h1 class="ts-archive-title">' . esc_html($titles[$kind] ?? '索引') . '</h1>';
$fn = 'ts_bunko_index_' . $kind;
if (function_exists($fn)) {
    $fn();
} else {
    echo '<p>この索引はまだ準備中です。</p>';
}
get_footer();
