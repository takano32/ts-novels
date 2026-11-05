<?php
/**
 * 索引ページ群 (T10.3) の描画部品。WordPress に触る側。テーマの ts-index.php が `ts_bunko_index_<種類>` の名前で
 * 呼ぶので関数名は変えない。並びの規則と表は includes/indexes/index-text.php (TS_Index_Text)。
 */

if (!defined('ABSPATH')) { exit; }

/** term ごとの**作品**数 (書庫が並べる単位に合わせる)。
 *
 *  term->count は作品と話の両方を数えるので、索引に 340 と出して書庫には 111 しか
 *  並ばない、という食い違いになっていた (実測で約 3 倍のずれ)。 */
function ts_bunko_work_counts($tax) {
    global $wpdb;
    static $cache = [];
    if (isset($cache[$tax])) return $cache[$tax];
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT tt.term_id, COUNT(DISTINCT p.ID) c
         FROM {$wpdb->term_taxonomy} tt
         JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
         JOIN {$wpdb->posts} p ON p.ID = tr.object_id
         WHERE tt.taxonomy = %s AND p.post_type = 'ts_work'
           AND p.post_status = 'publish' AND p.post_parent = 0
         GROUP BY tt.term_id", $tax));
    $out = [];
    foreach ($rows as $r) $out[(int) $r->term_id] = (int) $r->c;
    return $cache[$tax] = $out;
}

/** 五十音行ごとの作者一覧。
 *  見出しは catalog の ts_yomi_group の**実値**で作る (authors_build の値は「あ行」…「わ行」と
 *  欧文名の「A」…「Z」。並びの規則は TS_Index_Text::kana_group_order)。 */
function ts_bunko_index_kana() {
    $terms = get_terms(['taxonomy' => 'ts_author', 'hide_empty' => true]);
    $counts = ts_bunko_work_counts('ts_author');
    if (is_wp_error($terms) || !$terms) return;
    $by = [];
    foreach ($terms as $t) {
        $g = get_term_meta($t->term_id, 'ts_yomi_group', true);
        $by[$g !== '' && $g !== false ? $g : '他'][] = $t;
    }
    $groups = TS_Index_Text::kana_group_order(array_keys($by));

    echo '<nav class="ts-kana-rows">';
    foreach ($groups as $g) {
        echo '<a href="#kana-' . esc_attr(rawurlencode($g)) . '">' . esc_html($g) . '</a> ';
    }
    echo '</nav>';
    foreach ($groups as $g) {
        usort($by[$g], fn($a, $b) => strcmp($a->slug, $b->slug));
        echo '<h2 id="kana-' . esc_attr(rawurlencode($g)) . '" class="ts-archive-title">'
            . esc_html($g) . '</h2>';
        echo '<ul class="ts-index-list">';
        foreach ($by[$g] as $t) {
            $n = $counts[$t->term_id] ?? 0;
            if (!$n) continue;                     // 書庫に 1 作も並ばない term は出さない
            echo '<li><a href="' . esc_url(get_term_link($t)) . '">' . esc_html($t->name)
                . '</a> <span class="ts-count">(' . $n . ')</span></li>';
        }
        echo '</ul>';
    }
}

/** 年表: 年ごとの作品数と /year/YYYY/ へのリンク */
function ts_bunko_index_timeline() {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT YEAR(post_date) y, COUNT(*) c FROM {$wpdb->posts}
         WHERE post_type = 'ts_work' AND post_status = 'publish' AND post_parent = 0
         GROUP BY y ORDER BY y ASC");
    echo '<ul class="ts-index-list ts-timeline">';
    foreach ($rows as $r) {
        if ((int) $r->y < TS_Index_Text::TIMELINE_FROM) continue;
        echo '<li><a href="' . esc_url(home_url('/year/' . (int) $r->y . '/')) . '">'
            . (int) $r->y . '年</a> <span class="ts-count">' . (int) $r->c . ' 作品</span></li>';
    }
    echo '</ul>';
    echo '<p class="ts-archive-desc">年は各作品の初出日 (連載は第 1 話) に基づきます。'
        . '文庫の始まる前、1999 年の原型サイトの記録は <a href="'
        . esc_url(home_url('/index/docs/#sec-prehistory')) . '">文庫前史</a> にあります。</p>';
}

/** 分類索引: ジャンル・種別・共有世界の全 term (並びと見出しは TS_Index_Text::TAX_LABELS) */
function ts_bunko_index_bunrui() {
    foreach (TS_Index_Text::TAX_LABELS as $tax => $label) {
        $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => true,
                            'orderby' => 'count', 'order' => 'DESC']);
        if (is_wp_error($terms) || !$terms) continue;
        $counts = ts_bunko_work_counts($tax);
        echo '<h2 class="ts-archive-title">' . esc_html($label) . '</h2><ul class="ts-index-list">';
        foreach ($terms as $t) {
            $n = $counts[$t->term_id] ?? 0;
            if (!$n) continue;
            echo '<li><a href="' . esc_url(get_term_link($t)) . '">' . esc_html($t->name)
                . '</a> <span class="ts-count">(' . $n . ')</span>'
                . ($t->description ? ' — ' . esc_html($t->description) : '') . '</li>';
        }
        echo '</ul>';
    }
}

/** キーワード索引: 旧目録の語彙全部 (件数順) */
function ts_bunko_index_vocabulary() {
    $terms = get_terms(['taxonomy' => 'ts_keyword', 'hide_empty' => true,
                        'orderby' => 'count', 'order' => 'DESC']);
    if (is_wp_error($terms)) return;
    echo '<p class="ts-archive-desc">当時の目録で使われていたキーワードの全語彙です。'
        . '数字はそのキーワードが付いた作品の数です。</p>';
    $counts = ts_bunko_work_counts('ts_keyword');
    echo '<p class="ts-chips">';
    foreach ($terms as $t) {
        $n = $counts[$t->term_id] ?? 0;
        if (!$n) continue;
        echo '<a class="ts-chip" href="' . esc_url(get_term_link($t)) . '">'
            . esc_html($t->name) . ' ' . $n . '</a> ';
    }
    echo '</p>';
}

/** オススメの環: 目録の読者オススメの繋がりを一覧に */
function ts_bunko_index_osusume() {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm
         JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_ts_osusume' AND p.post_status = 'publish'");
    echo '<p class="ts-archive-desc">当時の目録にあった「この作品を読んだ人へのオススメ」の繋がりです。</p>';
    // 投稿を 1 件ずつ引くと 700 本以上のクエリになるので、必要な ID をまとめて温める
    $map = ts_bunko_source_map();
    $need = [];
    foreach ($rows as $r) {
        $need[(int) $r->post_id] = true;
        $o = maybe_unserialize($r->meta_value);
        foreach ((array) ($o['refs'] ?? []) as $ref) {
            $h = $ref['href'] ?? '';
            if ($h !== '' && isset($map[$h])) $need[$map[$h]] = true;
        }
    }
    if ($need) _prime_post_caches(array_keys($need), false, false);
    echo '<ul class="ts-index-list">';
    $n = 0;
    foreach ($rows as $r) {
        $o = maybe_unserialize($r->meta_value);
        if (!is_array($o) || empty($o['refs'])) continue;
        $from = get_post($r->post_id);
        if (!$from) continue;
        $tos = [];
        foreach ($o['refs'] as $ref) {
            $url = ts_bunko_url_for_source($ref['href'] ?? '');
            $title = $ref['title'] ?? '';
            if ($title === '') continue;
            $tos[] = $url ? '<a href="' . esc_url($url) . '">' . esc_html($title) . '</a>'
                          : esc_html($title);
        }
        if (!$tos) continue;
        echo '<li><a href="' . esc_url(get_permalink($from)) . '">' . esc_html(get_the_title($from))
            . '</a> → ' . implode('、', $tos) . '</li>';
        $n++;
    }
    echo '</ul>';
    if (!$n) echo '<p>オススメの記録は見つかりませんでした。</p>';
}

/** 運営文書・資料の索引 (ts_doc を区分ごとに。区分の並びと見出しは TS_Index_Text::DOC_SECTIONS) */
function ts_bunko_index_docs() {
    $docs = get_posts(['post_type' => 'ts_doc', 'post_status' => 'publish',
                       'posts_per_page' => -1, 'orderby' => 'name', 'order' => 'ASC']);
    $by = [];
    foreach ($docs as $d) {
        $s = get_post_meta($d->ID, '_ts_section', true) ?: 'notes';
        $by[$s][] = $d;
    }
    foreach (TS_Index_Text::DOC_SECTIONS as $sec => $label) {
        if (empty($by[$sec])) continue;
        echo '<h2 id="sec-' . esc_attr($sec) . '" class="ts-archive-title">' . esc_html($label) . '</h2>';
        echo '<ul class="ts-index-list">';
        foreach ($by[$sec] as $d) {
            echo '<li><a href="' . esc_url(get_permalink($d)) . '">'
                . esc_html(get_the_title($d)) . '</a></li>';
        }
        echo '</ul>';
    }
}

/** いまの一作 (時間シードの決定的選出 — 毎時交替。選び方は TS_Index_Text::pick_index) */
function ts_bunko_todays_pick() {
    $ids = get_posts(['post_type' => 'ts_work', 'post_parent' => 0, 'post_status' => 'publish',
                      'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC',
                      'no_found_rows' => true]);
    if (!$ids) return null;
    return get_post($ids[TS_Index_Text::pick_index((int) current_time('YmdH'), count($ids))]);
}
