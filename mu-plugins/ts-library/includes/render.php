<?php
/**
 * テーマ ts-bunko が呼ぶ表示部品 (設計 §4「機能は全て mu-plugin 側」。Fable 所管)。
 *
 * 表示文言の規則 (台帳 4.6): 内部語「本館/別館/旧館」は出さない。リンクは
 * 「無改変の原本を読む」「初出時の姿」「当時の感想を読む」のように中身を書く。
 */

if (!defined('ABSPATH')) { exit; }

function ts_meta($post_id, $key) {
    $v = get_post_meta($post_id, '_ts_' . $key, true);
    return ($v === '' || $v === []) ? null : $v;
}

/** パンくず: 作者 > 作品 > 話 */
function ts_bunko_breadcrumb($post) {
    $items = [];
    $authors = get_the_terms($post->ID, 'ts_author');
    if ($authors && !is_wp_error($authors)) {
        $a = $authors[0];
        $items[] = '<a href="' . esc_url(get_term_link($a)) . '">' . esc_html($a->name) . '</a>';
    }
    if ($post->post_parent) {
        $items[] = '<a href="' . esc_url(get_permalink($post->post_parent)) . '">'
            . esc_html(get_the_title($post->post_parent)) . '</a>';
    }
    $items[] = '<span aria-current="page">' . esc_html(get_the_title($post)) . '</span>';
    echo '<nav class="ts-breadcrumb" aria-label="現在位置">' . implode(' › ', $items) . '</nav>';
}

/** メタヘッダ: 初出日・読了目安・画師・分類チップ */
function ts_bunko_meta_header($post) {
    $bits = [];
    if ($d = ts_meta($post->ID, 'pub_date_raw')) $bits[] = '初出 ' . esc_html($d);
    if ($kb = ts_meta($post->ID, 'size_kb')) {
        $min = max(1, (int) round((float) $kb * 0.8));
        $bits[] = '読了 約' . $min . '分';
    }
    $il = ts_meta($post->ID, 'illustrator');
    if ($il) $bits[] = '挿絵 ' . esc_html(implode('・', (array) $il));
    echo '<div class="ts-meta-header">';
    if ($bits) echo '<p class="ts-meta-bits">' . implode('　·　', $bits) . '</p>';
    $chips = [];
    foreach (['ts_genre', 'ts_type', 'ts_keyword', 'ts_world'] as $tax) {
        $terms = get_the_terms($post->ID, $tax);
        if (!$terms || is_wp_error($terms)) continue;
        foreach ($terms as $t) {
            $chips[] = '<a class="ts-chip ts-chip-' . esc_attr($tax) . '" href="'
                . esc_url(get_term_link($t)) . '">' . esc_html($t->name) . '</a>';
        }
    }
    if ($chips) echo '<p class="ts-chips">' . implode(' ', $chips) . '</p>';
    echo '</div>';
}

/** 話ナビ: [← 前の話 | 作品目次 | 次の話 →] (menu_order 順)。単発作品は出さない */
function ts_bunko_episode_nav($post) {
    if (!$post->post_parent) return;
    $siblings = get_posts([
        'post_type' => 'ts_work', 'post_parent' => $post->post_parent,
        'posts_per_page' => -1, 'orderby' => 'menu_order', 'order' => 'ASC',
        'fields' => 'ids', 'no_found_rows' => true,
    ]);
    $i = array_search($post->ID, $siblings, true);
    $prev = ($i !== false && $i > 0) ? $siblings[$i - 1] : null;
    $next = ($i !== false && $i < count($siblings) - 1) ? $siblings[$i + 1] : null;
    echo '<nav class="ts-episode-nav" aria-label="話の移動">';
    echo $prev
        ? '<a class="ts-nav-prev" rel="prev" href="' . esc_url(get_permalink($prev)) . '">← '
            . esc_html(get_the_title($prev)) . '</a>'
        : '<span class="ts-nav-prev is-empty"></span>';
    echo '<a class="ts-nav-toc" href="' . esc_url(get_permalink($post->post_parent)) . '">作品目次</a>';
    echo $next
        ? '<a class="ts-nav-next" rel="next" href="' . esc_url(get_permalink($next)) . '">'
            . esc_html(get_the_title($next)) . ' →</a>'
        : '<span class="ts-nav-next is-empty"></span>';
    echo '</nav>';
}

/** 作品目次 (親 work ページ): 子投稿一覧 */
function ts_bunko_work_toc($post) {
    $eps = get_posts([
        'post_type' => 'ts_work', 'post_parent' => $post->ID,
        'posts_per_page' => -1, 'orderby' => 'menu_order', 'order' => 'ASC',
        'no_found_rows' => true,
    ]);
    if (!$eps) return;
    echo '<ol class="ts-toc">';
    foreach ($eps as $ep) {
        $d = ts_meta($ep->ID, 'pub_date_raw');
        echo '<li><a href="' . esc_url(get_permalink($ep)) . '">' . esc_html(get_the_title($ep))
            . '</a>' . ($d ? ' <span class="ts-toc-date">' . esc_html($d) . '</span>' : '') . '</li>';
    }
    echo '</ol>';
}

/** 回収経路の人間可読表示 */
function ts_bunko_provenance_label($prov) {
    if (!is_array($prov)) return null;
    if (!empty($prov['route_label'])) return $prov['route_label'];
    $map = ['wayback' => 'Internet Archive Wayback Machine', 'mirror' => 'サイト原本のミラー',
            'author-repost' => '作者本人による再掲', 'megalodon' => 'ウェブ魚拓',
            'commoncrawl' => 'Common Crawl'];
    return $map[$prov['route'] ?? ($prov['method'] ?? '')] ?? ($prov['route'] ?? null);
}

/** 書誌カード: 初出・回収経路・原本リンク群。本文が整形表示である旨を明示する (設計 v1.4) */
function ts_bunko_biblio_card($post) {
    $id = $post->ID;
    $src = ts_meta($id, 'source_path');
    if (!$src && !ts_meta($id, 'annex_url')) return;
    echo '<aside class="ts-biblio" aria-label="書誌情報">';
    echo '<h2 class="ts-biblio-title">この作品について</h2><dl>';
    if ($src) {
        $d = ts_meta($id, 'pub_date_raw');
        echo '<dt>初出</dt><dd>ts.novels.jp/' . esc_html($src)
            . ($d ? ' (' . esc_html($d) . ')' : '') . '</dd>';
    }
    $prov = ts_meta($id, 'provenance');
    if ($label = ts_bunko_provenance_label($prov)) {
        echo '<dt>回収経路</dt><dd>' . esc_html($label) . '</dd>';
    }
    $links = [];
    $status = ts_meta($id, 'body_status');
    if ($u = ts_meta($id, 'annex_url')) {
        $links[] = '<a href="' . esc_url($u) . '">無改変の原本を読む</a>';
    }
    if ($u = ts_meta($id, 'annex_yays_url')) {
        $links[] = '<a href="' . esc_url($u) . '">初出時の姿 (2002年版)</a>';
    }
    if ($u = ts_meta($id, 'kansou_annex_url')) {
        $links[] = '<a href="' . esc_url($u) . '">当時の感想を読む</a>';
    }
    if ($links) echo '<dt>原本アーカイブ</dt><dd>' . implode('<br>', $links) . '</dd>';
    echo '</dl>';
    if ($status === 'converted' || $status === 'converted-text') {
        echo '<p class="ts-biblio-note">この本文は読みやすさのため組版を整えた表示です。'
            . '文章そのものは原本と機械照合で一致しています。</p>';
    } elseif ($status === 'raw-fallback') {
        echo '<p class="ts-biblio-note">この本文は原本の HTML をそのまま表示しています。</p>';
    }
    echo '</aside>';
}

/** 推薦文 (旧目録の編集委員によるもの) */
function ts_bunko_suisen($post) {
    $s = ts_meta($post->ID, 'suisen');
    if (!$s) return;
    echo '<aside class="ts-suisen"><h2 class="ts-suisen-title">目録の推薦文</h2>'
        . '<blockquote>' . esc_html($s) . '</blockquote></aside>';
}

/** あらすじ・作者コメント (目録由来) */
function ts_bunko_catalog_notes($post) {
    $a = ts_meta($post->ID, 'arasuji');
    $c = ts_meta($post->ID, 'author_comment');
    if (!$a && !$c) return;
    echo '<div class="ts-catalog-notes">';
    if ($a) echo '<p class="ts-arasuji"><span class="ts-label">あらすじ</span>' . esc_html($a) . '</p>';
    if ($c) echo '<p class="ts-author-comment"><span class="ts-label">作者より</span>' . esc_html($c) . '</p>';
    echo '</div>';
}

/** 読者からのオススメ (目録の「読者オススメ」欄) */
function ts_bunko_osusume($post) {
    $o = ts_meta($post->ID, 'osusume');
    if (!is_array($o) || empty($o['refs'])) return;
    echo '<aside class="ts-osusume"><h2 class="ts-osusume-title">この作品からのオススメ</h2>';
    if (!empty($o['recommender'])) {
        echo '<p class="ts-osusume-by">' . esc_html($o['recommender']) . ' さんより</p>';
    }
    echo '<ul>';
    foreach ($o['refs'] as $ref) {
        $title = $ref['title'] ?? ($ref['href'] ?? '');
        $url = ts_bunko_url_for_source($ref['href'] ?? '');
        echo '<li>' . ($url ? '<a href="' . esc_url($url) . '">' . esc_html($title) . '</a>'
                            : esc_html($title))
            . (!empty($ref['author']) ? ' <span class="ts-osusume-author">('
                . esc_html($ref['author']) . ')</span>' : '') . '</li>';
    }
    echo '</ul></aside>';
}

/** 原パス → この文庫内の URL (無ければ null)。オススメ等の内部リンク解決に使う */
function ts_bunko_url_for_source($source_path) {
    if (!$source_path) return null;
    static $cache = [];
    if (array_key_exists($source_path, $cache)) return $cache[$source_path];
    $q = new WP_Query([
        'post_type' => 'ts_work', 'post_status' => 'publish', 'posts_per_page' => 1,
        'meta_key' => '_ts_source_path', 'meta_value' => $source_path,
        'fields' => 'ids', 'no_found_rows' => true,
        'update_post_meta_cache' => false, 'update_post_term_cache' => false,
    ]);
    return $cache[$source_path] = ($q->posts ? get_permalink($q->posts[0]) : null);
}

/** 作者ページ冒頭: ホームページリンクは既定で Wayback、現存確認済みのみ生 URL (設計 4.1) */
function ts_bunko_author_links($term) {
    $live = get_term_meta($term->term_id, 'ts_homepage_live', true);
    $wb = get_term_meta($term->term_id, 'ts_homepage_wayback', true);
    $links = [];
    if ($live) $links[] = '<a href="' . esc_url($live) . '" rel="external">ホームページ</a>';
    elseif ($wb) $links[] = '<a href="' . esc_url($wb) . '" rel="external">ホームページ (当時の保存版)</a>';
    if ($u = get_term_meta($term->term_id, 'ts_kansou_annex_url', true)) {
        $links[] = '<a href="' . esc_url($u) . '">当時の感想板ログ</a>';
    }
    if ($links) echo '<p class="ts-author-links">' . implode('　·　', $links) . '</p>';
}
