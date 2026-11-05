<?php
/**
 * 一覧のページの部品: 1 作品カード (トップ・書庫・検索・作者ページ・年別一覧) と検索結果のファセット。WordPress に触る側。
 * テーマの index.php・front-page.php・ts-year.php が呼ぶので関数名は変えない (T45.1.5.1 でカードをテーマの functions.php から、
 * T45.1.5.3 でファセットを index.php から移した)。添え書きの並び (作者・初出・全N話) は includes/render/render-text.php
 * (TS_Render_Text::workcard_bits)、ファセットの taxonomy と見出しは includes/indexes/index-text.php (TS_Index_Text)。
 */

if (!defined('ABSPATH')) { exit; }

/** 一覧の 1 作品カード */
function ts_bunko_workcard($post) {
    $authors = get_the_terms($post->ID, 'ts_author');
    $author = ($authors && !is_wp_error($authors)) ? esc_html($authors[0]->name) : null;
    $d = ts_meta($post->ID, 'pub_date_raw');
    // 話数は import が書いたメタから (カード 1 枚ごとに get_children を投げない)
    $n = (int) get_post_meta($post->ID, '_ts_episode_count', true);
    if (!$n) {
        $n = count(get_children(['post_parent' => $post->ID, 'post_type' => 'ts_work',
                                 'post_status' => 'publish', 'fields' => 'ids']));
    }
    $bits = TS_Render_Text::workcard_bits($author, $d ? esc_html($d) : null, $n);
    echo '<li class="ts-workcard"><h2 class="ts-workcard-title"><a href="'
        . esc_url(get_permalink($post)) . '">' . esc_html(get_the_title($post)) . '</a></h2>';
    if ($bits) echo '<p class="ts-workcard-meta">' . implode('　·　', $bits) . '</p>';
    echo '</li>';
}

/** 検索結果のファセット絞込 (T10.4): 公開タクソノミーの query var をそのまま使う。選んでいる語は ✕ で外せる */
function ts_bunko_search_facets() {
    foreach (TS_Index_Text::SEARCH_FACETS as $tax) {
        $label = TS_Index_Text::TAX_LABELS[$tax];
        $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => true, 'number' => 14,
                            'orderby' => 'count', 'order' => 'DESC']);
        if (is_wp_error($terms) || !$terms) continue;
        $active = get_query_var($tax);
        echo '<p class="ts-chips"><span class="ts-count">' . esc_html($label) . ':</span> ';
        if ($active) {
            echo '<a class="ts-chip" href="' . esc_url(remove_query_arg($tax)) . '">✕ '
                . esc_html($active) . '</a> ';
        }
        foreach ($terms as $t) {
            if ($t->slug === $active) continue;
            echo '<a class="ts-chip" href="' . esc_url(add_query_arg($tax, $t->slug)) . '">'
                . esc_html($t->name) . '</a> ';
        }
        echo '</p>';
    }
}
