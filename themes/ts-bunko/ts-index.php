<?php
/** 索引ページ (/index/…/)。中身は mu-plugin の描画部品 */
get_header();
// mu-plugin の template_include が種類 (TS_Index_Text::KINDS のどれか) のときだけこのテンプレートを選ぶ。種類ごとに ts_bunko_index_<種類> がある
$kind = ts_index_kind();
echo '<h1 class="ts-archive-title">' . esc_html(TS_Index_Text::TITLES[$kind]) . '</h1>';   // 題名の表は mu-plugin (document_title_parts と共有)
$fn = 'ts_bunko_index_' . $kind;
$fn();
get_footer();
