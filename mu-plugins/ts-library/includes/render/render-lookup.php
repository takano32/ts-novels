<?php
/**
 * 表示部品が使う解決表 (原パス → 投稿、名前 → 作者 term) と、作者ページの部品 (specs/S192.md、S240.md)。WordPress に触る側。
 *
 * 解決表は 1 リクエストに 1 度だけ作る (static)。参照 1 件ごとに meta 検索を投げると、オススメの環ページで
 * 数百本のクエリになる (meta_value は無索引)。
 */

if (!defined('ABSPATH')) { exit; }

/** 原パス → 投稿 ID の全対応表 (1 リクエスト 2 クエリで作り置き)。
 *
 *  話の `_ts_source_path` だけでは足りない — 連載を指す参照は作品トビラ (`_ts_title_pages`) の側に
 *  しか無く、全体の約 1/4 を占める。 */
function ts_bunko_source_map() {
    static $map = null;
    if ($map !== null) return $map;
    global $wpdb;
    $map = [];
    $rows = $wpdb->get_results(
        "SELECT pm.post_id, pm.meta_value v FROM {$wpdb->postmeta} pm
         JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_ts_source_path'
           AND p.post_type = 'ts_work' AND p.post_status = 'publish'");
    foreach ($rows as $r) {
        if ($r->v !== '' && !isset($map[$r->v])) $map[$r->v] = (int) $r->post_id;
    }
    $rows = $wpdb->get_results(
        "SELECT pm.post_id, pm.meta_value v FROM {$wpdb->postmeta} pm
         JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_ts_title_pages'
           AND p.post_type = 'ts_work' AND p.post_status = 'publish'");
    foreach ($rows as $r) {
        $paths = maybe_unserialize($r->v);
        foreach ((array) $paths as $p) {
            if (is_string($p) && $p !== '' && !isset($map[$p])) $map[$p] = (int) $r->post_id;
        }
    }
    return $map;
}

/** 原パス → この文庫内の URL (無ければ null)。オススメ等の内部リンク解決に使う */
function ts_bunko_url_for_source($source_path) {
    if (!$source_path) return null;
    $map = ts_bunko_source_map();
    return isset($map[$source_path]) ? get_permalink($map[$source_path]) : null;
}

/** 名前 → 作者 term (無ければ null)。作者の表示名と、目録に現れた表記ゆれ (term meta の
 *  ts_display_variants。wp ts sync-terms が authors.json から書く) の完全一致だけで引く。
 *  「ＭＯＮＤＯさん　角」のような複数人の併記は引かない (specs/S240.md) */
function ts_bunko_author_term_for_name($name) {
    static $map = null;
    if ($map === null) {
        $map = [];
        $terms = get_terms(['taxonomy' => 'ts_author', 'hide_empty' => false]);
        foreach ((is_wp_error($terms) ? [] : $terms) as $t) {
            $variants = get_term_meta($t->term_id, 'ts_display_variants', true);
            foreach (array_merge([$t->name], is_array($variants) ? $variants : []) as $n) {
                if (is_string($n) && $n !== '' && !isset($map[$n])) $map[$n] = $t;
            }
        }
    }
    return $map[$name] ?? null;
}

/** 作者ページの「挿絵を担当した作品」: この人が挿絵を描いた話の作品 (連載なら親) の ID を
 *  発表順で返す。自分の作品は上の一覧に出ているので除く (specs/S240.md) */
function ts_bunko_illustrated_work_ids($term) {
    $variants = get_term_meta($term->term_id, 'ts_display_variants', true);
    $names = array_unique(array_merge([$term->name], is_array($variants) ? $variants : []));
    $mq = ['relation' => 'OR'];
    foreach ($names as $n) {
        if (!is_string($n) || $n === '') continue;
        // _ts_illustrator は名前の配列 (直列化) なので、引用符込みで要素の完全一致にする
        $mq[] = ['key' => '_ts_illustrator', 'value' => '"' . $n . '"', 'compare' => 'LIKE'];
    }
    if (count($mq) < 2) return [];
    $ids = get_posts(['post_type' => 'ts_work', 'post_status' => 'publish',
                      'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
                      'meta_query' => $mq]);
    $works = [];
    foreach ($ids as $id) {
        $w = (int) (wp_get_post_parent_id($id) ?: $id);
        if (isset($works[$w]) || has_term($term->term_id, 'ts_author', $w)) continue;
        $works[$w] = true;
    }
    if (!$works) return [];
    return get_posts(['post_type' => 'ts_work', 'post_status' => 'publish',
                      'post__in' => array_keys($works), 'posts_per_page' => -1,
                      'orderby' => 'date', 'order' => 'ASC', 'fields' => 'ids',
                      'no_found_rows' => true]);
}

/** 作者ページ冒頭: ホームページリンクは既定で Wayback、現存確認済みのみ生 URL (S192、T10.2) */
function ts_bunko_author_links($term) {
    $live = get_term_meta($term->term_id, 'ts_homepage_live', true);
    $wb = get_term_meta($term->term_id, 'ts_homepage_wayback', true);
    $links = [];
    if ($live) $links[] = '<a href="' . esc_url($live) . '" rel="external">ホームページ</a>';
    elseif ($wb) $links[] = '<a href="' . esc_url($wb) . '" rel="external">ホームページ (当時の保存版)</a>';
    // 感想板ログへのリンクは保留 (書誌カードと同じ理由。specs/S155.md)
    if ($links) echo '<p class="ts-author-links">' . implode('　·　', $links) . '</p>';
}
