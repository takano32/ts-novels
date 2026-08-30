<?php
/**
 * ts_work の 3 態をここで捌く:
 *   親 work (子を持つ)  → 作品ページ (目次)
 *   子 episode / 単発    → リーダー (本文 + 話ナビ + 書誌カード)
 * ts_doc / ts_dojo / ts_board_post は素の記事表示。
 */
get_header();

while (have_posts()) {
    the_post();
    $post = get_post();

    if ($post->post_type !== 'ts_work') {
        echo '<article>';
        echo '<h1 class="ts-entry-title">' . esc_html(get_the_title()) . '</h1>';
        echo '<div class="ts-reader">';
        the_content();
        echo '</div></article>';
        continue;
    }

    $children = get_children(['post_parent' => $post->ID, 'post_type' => 'ts_work', 'fields' => 'ids']);

    ts_bunko_breadcrumb($post);
    echo '<article>';
    echo '<h1 class="ts-entry-title">' . esc_html(get_the_title()) . '</h1>';
    ts_bunko_meta_header($post);

    if ($children) {
        // ---- 作品ページ (連載の親): 目次 ----
        ts_bunko_catalog_notes($post);
        echo '<h2 class="ts-biblio-title">目次 (全' . count($children) . '話)</h2>';
        ts_bunko_work_toc($post);
        ts_bunko_suisen($post);
        ts_bunko_biblio_card($post);
        ts_bunko_osusume($post);
    } else {
        // ---- リーダー (話 / 単発作品) ----
        $status = ts_meta($post->ID, 'body_status');
        ?>
        <div class="ts-toolbar" hidden data-ts-toolbar>
          <button type="button" data-ts-set="size:1" aria-pressed="false">字 小</button>
          <button type="button" data-ts-set="size:2" aria-pressed="false">中</button>
          <button type="button" data-ts-set="size:3" aria-pressed="false">大</button>
          <button type="button" data-ts-set="size:4" aria-pressed="false">特大</button>
          <button type="button" data-ts-set="lh:1" aria-pressed="false">行間 標準</button>
          <button type="button" data-ts-set="lh:2" aria-pressed="false">広め</button>
          <button type="button" data-ts-set="font:mincho" aria-pressed="false">明朝</button>
          <button type="button" data-ts-set="font:gothic" aria-pressed="false">ゴシック</button>
          <button type="button" data-ts-set="dark:0" aria-pressed="false">ライト</button>
          <button type="button" data-ts-set="dark:1" aria-pressed="false">ダーク</button>
          <span class="ts-toolbar-label">←/→ で前後の話</span>
        </div>
        <?php
        echo '<div class="ts-reader' . ($status === 'raw-fallback' ? ' is-raw' : '') . '">';
        the_content();
        echo '</div>';
        ts_bunko_episode_nav($post);
        ts_bunko_suisen($post);
        ts_bunko_catalog_notes($post);
        ts_bunko_biblio_card($post);
        ts_bunko_osusume($post);
    }
    echo '</article>';
}

get_footer();
