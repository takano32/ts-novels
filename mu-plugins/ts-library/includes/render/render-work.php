<?php
/**
 * テーマ ts-bunko が作品・話のページで呼ぶ表示部品 (設計 specs/S27.md「機能は全て mu-plugin 側」。高品位なモデルとエフォートの所管)。
 *
 * パンくず・メタヘッダ・話ナビ・目次・書誌カード・推薦文・あらすじ・オススメ・関連ページ。関数名はテンプレート
 * (themes/ts-bunko/single.php ほか) が呼ぶので変えない。文言と計算のうち WordPress を使わない部分は
 * includes/render/render-text.php (TS_Render_Text)、原パスや名前の解決は render-lookup.php。
 *
 * 表示文言の規則 (specs/S200.md): 内部語 (「WP サイト」「旧サイト」など S15 の呼び分け) は出さない。リンクは
 * 「無改変の原本を読む」「初出時の姿」「当時の感想を読む」のように中身を書く。
 */

if (!defined('ABSPATH')) { exit; }

/** post meta `_ts_<key>` を読む。空文字・空配列は null */
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
    // 読了目安は**本文の実文字数**から (閾値と速さは TS_Render_Text)
    $chars = mb_strlen(preg_replace('/\s+/u', '', wp_strip_all_tags($post->post_content)));
    $minutes = TS_Render_Text::reading_minutes($chars);
    if ($minutes !== null) {
        $bits[] = '読了 約' . $minutes . '分';
    }
    $il = ts_meta($post->ID, 'illustrator');
    if ($il) {
        // 挿絵を描いた人が作者ページを持つなら、そこへリンクする (specs/S240.md)
        $names = [];
        foreach ((array) $il as $name) {
            $t = ts_bunko_author_term_for_name((string) $name);
            $names[] = $t ? '<a href="' . esc_url(get_term_link($t)) . '">' . esc_html($name) . '</a>'
                          : esc_html($name);
        }
        $bits[] = '挿絵 ' . implode('・', $names);
    }
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
        'post_status' => ts_visibility_child_statuses($post->post_parent),
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
        'post_status' => ts_visibility_child_statuses($post->ID),
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

/** 書誌カード: 初出・回収経路・原本リンク群。本文が整形表示である旨を明示する (設計 S41) */
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
    if ($label = TS_Render_Text::provenance_label($prov)) {
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
    // 当時の感想板ログへのリンクは保留中。理由は 2 つあり、どちらも未解決:
    //   (a) .cgi がブラウザでダウンロード扱いになる / 一部 404 で、読者が読めない
    //   (b) ログに第三者の連絡先が残っている (specs/S155.md の判断待ち)
    // 直すと (b) の露出が増えるので、判断が出るまで出さない
    if (false && ($u = ts_meta($id, 'kansou_annex_url'))) {
        $links[] = '<a href="' . esc_url($u) . '">当時の感想を読む</a>';
    }
    if ($links) echo '<dt>原本アーカイブ</dt><dd>' . implode('<br>', $links) . '</dd>';
    echo '</dl>';
    $note = TS_Render_Text::body_note($status);
    if ($note !== null) {
        echo '<p class="ts-biblio-note">' . $note . '</p>';
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

/** 本文から失われた内部リンクを目録の nav_links から復元して出す。
 *
 *  本文変換の過程で <a> が落ちるため、「…Part2へ」「シリーズタイトルはこちら」等が
 *  押せない文字列として残っていた (実測 472 ページ)。目録側は href を持っているので、
 *  館内の URL に解決できるものだけをリンクとして出す。 */
function ts_bunko_inline_nav($post) {
    $nav = ts_meta($post->ID, 'nav_links');
    if (!is_array($nav)) return;
    $items = [];
    foreach ($nav as $n) {
        $href = is_array($n) ? ($n['href'] ?? '') : '';
        $label = is_array($n) ? trim((string) ($n['label'] ?? '')) : '';
        if ($href === '') continue;
        $url = ts_bunko_url_for_source($href);
        if (!$url) continue;                       // 館内に無いものは出さない
        if ($label === '') $label = get_the_title(url_to_postid($url)) ?: '関連ページ';
        $items[] = '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
    if (!$items) return;
    echo '<nav class="ts-inline-nav" aria-label="この作品の関連ページ">'
        . implode('　·　', $items) . '</nav>';
}
