<?php
/**
 * サイドバー (specs/S195.md、ユーザ裁定 2026-08-31 — 右側に置く)。WordPress に触る側。
 * 項目の表は includes/render/render-text.php (TS_Render_Text::SIDEBAR_INDEXES / SIDEBAR_ABOUT)。
 * themes/ts-bunko/footer.php が呼ぶ。
 */

if (!defined('ABSPATH')) { exit; }

/** サイドバー: 索引入口 + 検索窓 + この文庫について */
function ts_bunko_sidebar() {
    echo '<aside class="ts-sidebar" aria-label="さがす">';
    echo '<div class="ts-sidebar-block"><h2 class="ts-sidebar-title">さがす</h2>';
    get_search_form();
    echo '</div>';
    echo '<nav class="ts-sidebar-block"><h2 class="ts-sidebar-title">索引</h2><ul>';
    foreach (TS_Render_Text::SIDEBAR_INDEXES as [$slug, $label]) {
        $here = get_query_var('ts_index') === $slug;
        echo '<li><a href="' . esc_url(home_url("/index/$slug/")) . '"'
            . ($here ? ' aria-current="page"' : '') . '>' . esc_html($label) . '</a></li>';
    }
    echo '</ul></nav>';
    echo '<div class="ts-sidebar-block"><h2 class="ts-sidebar-title">この文庫について</h2><ul>';
    foreach (TS_Render_Text::SIDEBAR_ABOUT as [$path, $label]) {
        echo '<li><a href="' . esc_url(home_url($path)) . '">' . esc_html($label) . '</a></li>';
    }
    echo '</ul></div>';
    echo '</aside>';
}
