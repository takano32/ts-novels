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

    // publish 限定 — 非公開 (取り下げ・旧版) の話を「全N話」に数えると件数が目次と食い違う。
    // 運営委員が非公開の作品を見ているときだけ private の話も数える (目次と同じ集合)
    $child_statuses = function_exists('ts_visibility_child_statuses')
        ? ts_visibility_child_statuses($post->ID) : ['publish'];
    $children = get_children(['post_parent' => $post->ID, 'post_type' => 'ts_work',
                              'post_status' => $child_statuses, 'fields' => 'ids']);

    ts_bunko_breadcrumb($post);
    echo '<article>';
    echo '<h1 class="ts-entry-title">' . esc_html(get_the_title()) . '</h1>';
    if (function_exists('ts_bunko_hidden_banner')) ts_bunko_hidden_banner($post); // 運営委員にだけ (S.118)
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
          <?php if ($status === 'raw-fallback') { ?>
          <button type="button" data-ts-set="orig:1" aria-pressed="false">原本配色</button>
          <?php } ?>
          <span class="ts-toolbar-label">←/→ で前後の話</span>
        </div>
        <?php
        echo '<div class="ts-reader' . ($status === 'raw-fallback' ? ' is-raw' : '') . '">';
        the_content();
        echo '</div>';
        ts_bunko_episode_nav($post);
        if (function_exists('ts_bunko_inline_nav')) ts_bunko_inline_nav($post);
        ts_bunko_suisen($post);
        ts_bunko_catalog_notes($post);
        ts_bunko_biblio_card($post);
        ts_bunko_osusume($post);
    }
    echo '</article>';
}

get_footer();
