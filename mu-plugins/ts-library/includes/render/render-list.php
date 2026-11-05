<?php
/**
 * 一覧の 1 作品カード (トップ・書庫・検索・作者ページ・年別一覧)。WordPress に触る側。
 * テーマの index.php・front-page.php・ts-year.php が呼ぶので関数名は変えない (T45.1.5.1 でテーマの functions.php から移した)。
 * 添え書きの並び (作者・初出・全N話) は includes/render/render-text.php (TS_Render_Text::workcard_bits)。
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
