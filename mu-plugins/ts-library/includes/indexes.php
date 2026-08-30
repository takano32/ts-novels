<?php
/**
 * 索引ページ群 (タスク 4.3/4.4。Fable 所管): /index/{kana,timeline,bunrui,vocabulary,osusume}/
 * と /year/YYYY/。ルーティングはここ、見た目はテーマ (ts-index.php / ts-year.php)。
 */

if (!defined('ABSPATH')) { exit; }

add_action('init', function () {
    add_rewrite_rule('^index/(kana|timeline|bunrui|vocabulary|osusume|docs)/?$',
        'index.php?ts_index=$matches[1]', 'top');
    add_rewrite_rule('^year/([0-9]{4})/?$', 'index.php?ts_year=$matches[1]', 'top');
}, 6);

add_filter('query_vars', function ($vars) {
    $vars[] = 'ts_index';
    $vars[] = 'ts_year';
    return $vars;
});

add_filter('template_include', function ($template) {
    if (get_query_var('ts_index')) {
        $t = locate_template('ts-index.php');
        if ($t) return $t;
    }
    if (get_query_var('ts_year')) {
        $t = locate_template('ts-year.php');
        if ($t) return $t;
    }
    return $template;
});

/** 索引ページを 404 にしない (メインクエリは投稿を引かないので) */
add_action('pre_get_posts', function ($q) {
    if (is_admin() || !$q->is_main_query()) return;
    if (get_query_var('ts_index')) {
        $q->set('posts_per_page', 1);
        add_filter('pre_handle_404', '__return_true');
    }
    if ($y = get_query_var('ts_year')) {
        $q->set('post_type', 'ts_work');
        $q->set('post_parent', 0);
        $q->set('posts_per_page', -1);
        $q->set('orderby', 'date');
        $q->set('order', 'ASC');
        $q->set('year', (int) $y);
    }
});

add_filter('document_title_parts', function ($parts) {
    $titles = ['kana' => '作者さくいん', 'timeline' => '年表', 'bunrui' => '分類さくいん',
               'vocabulary' => 'キーワードさくいん', 'osusume' => 'オススメの環',
               'docs' => '運営文書・資料'];
    if ($k = get_query_var('ts_index')) $parts['title'] = $titles[$k] ?? 'さくいん';
    if ($y = get_query_var('ts_year')) $parts['title'] = $y . '年の作品';
    return $parts;
});

// ------------------------------------------------------------------ 描画部品

/** 五十音行ごとの作者一覧 (term meta ts_yomi_group で分類) */
function ts_bunko_index_kana() {
    $rows = ['あ', 'か', 'さ', 'た', 'な', 'は', 'ま', 'や', 'ら', 'わ'];
    $terms = get_terms(['taxonomy' => 'ts_author', 'hide_empty' => true]);
    if (is_wp_error($terms)) return;
    $by = [];
    foreach ($terms as $t) {
        $g = get_term_meta($t->term_id, 'ts_yomi_group', true) ?: '他';
        $by[$g][] = $t;
    }
    echo '<nav class="ts-kana-rows">';
    foreach (array_merge($rows, ['他']) as $r) {
        if (!empty($by[$r])) echo '<a href="#kana-' . esc_attr($r) . '">' . esc_html($r) . '</a> ';
    }
    echo '</nav>';
    foreach (array_merge($rows, ['他']) as $r) {
        if (empty($by[$r])) continue;
        usort($by[$r], fn($a, $b) => strcmp($a->slug, $b->slug));
        echo '<h2 id="kana-' . esc_attr($r) . '" class="ts-archive-title">' . esc_html($r) . '</h2>';
        echo '<ul class="ts-index-list">';
        foreach ($by[$r] as $t) {
            echo '<li><a href="' . esc_url(get_term_link($t)) . '">' . esc_html($t->name)
                . '</a> <span class="ts-count">(' . (int) $t->count . ')</span></li>';
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
        if ((int) $r->y < 1990) continue;
        echo '<li><a href="' . esc_url(home_url('/year/' . (int) $r->y . '/')) . '">'
            . (int) $r->y . '年</a> <span class="ts-count">' . (int) $r->c . ' 作品</span></li>';
    }
    echo '</ul>';
    echo '<p class="ts-archive-desc">年は各作品の初出日 (連載は第 1 話) に基づきます。'
        . '文庫の始まる前、1999 年の原型サイトの記録は <a href="'
        . esc_url(home_url('/index/docs/#sec-prehistory')) . '">文庫前史</a> にあります。</p>';
}

/** 分類さくいん: ジャンル・種別・共有世界の全 term */
function ts_bunko_index_bunrui() {
    foreach ([['ts_genre', 'ジャンル'], ['ts_type', '変身のかたち'], ['ts_world', '共有世界']] as [$tax, $label]) {
        $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => true,
                            'orderby' => 'count', 'order' => 'DESC']);
        if (is_wp_error($terms) || !$terms) continue;
        echo '<h2 class="ts-archive-title">' . esc_html($label) . '</h2><ul class="ts-index-list">';
        foreach ($terms as $t) {
            echo '<li><a href="' . esc_url(get_term_link($t)) . '">' . esc_html($t->name)
                . '</a> <span class="ts-count">(' . (int) $t->count . ')</span>'
                . ($t->description ? ' — ' . esc_html($t->description) : '') . '</li>';
        }
        echo '</ul>';
    }
}

/** キーワードさくいん: 旧目録の語彙全部 (件数順) */
function ts_bunko_index_vocabulary() {
    $terms = get_terms(['taxonomy' => 'ts_keyword', 'hide_empty' => true,
                        'orderby' => 'count', 'order' => 'DESC']);
    if (is_wp_error($terms)) return;
    echo '<p class="ts-archive-desc">当時の目録で使われていたキーワードの全語彙です。</p>';
    echo '<p class="ts-chips">';
    foreach ($terms as $t) {
        echo '<a class="ts-chip" href="' . esc_url(get_term_link($t)) . '">'
            . esc_html($t->name) . ' ' . (int) $t->count . '</a> ';
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

/** 運営文書・資料のさくいん (ts_doc を区分ごとに) */
function ts_bunko_index_docs() {
    $docs = get_posts(['post_type' => 'ts_doc', 'post_status' => 'publish',
                       'posts_per_page' => -1, 'orderby' => 'name', 'order' => 'ASC']);
    $order = ['gallery' => 'ギャラリー', 'comittee' => '運営委員会', 'columns' => 'コラム',
              'dialy' => '運営日誌', 'prehistory' => '文庫前史 (1999)', 'notes' => '解題'];
    $by = [];
    foreach ($docs as $d) {
        $s = get_post_meta($d->ID, '_ts_section', true) ?: 'notes';
        $by[$s][] = $d;
    }
    foreach ($order as $sec => $label) {
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

/** 今日の一作 (日付シードの決定的選出。設計 4.4) */
function ts_bunko_todays_pick() {
    $ids = get_posts(['post_type' => 'ts_work', 'post_parent' => 0, 'post_status' => 'publish',
                      'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC',
                      'no_found_rows' => true]);
    if (!$ids) return null;
    $seed = (int) current_time('Ymd');
    return get_post($ids[$seed % count($ids)]);
}
