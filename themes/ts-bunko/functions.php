<?php
/**
 * ts-bunko — 見た目だけのテーマ (機能は mu-plugin ts-library 側。設計 §4)。
 */

if (!defined('ABSPATH')) { exit; }

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
});

add_action('wp_enqueue_scripts', function () {
    $dir = get_template_directory();
    wp_enqueue_style('ts-bunko', get_stylesheet_uri(), [], (string) filemtime("$dir/style.css"));
    if (is_singular('ts_work')) {
        wp_enqueue_script('ts-reader', get_template_directory_uri() . '/js/reader.js',
            [], (string) filemtime("$dir/js/reader.js"), ['in_footer' => true]);
    }
});

/** 保存済みのリーダー設定をちらつきなく適用する (head 内・1KB 未満のインライン) */
add_action('wp_head', function () {
    ?><script>try{var s=JSON.parse(localStorage.getItem('ts-reader')||'{}'),h=document.documentElement;
['size','lh','font','dark'].forEach(function(k){if(s[k])h.setAttribute('data-ts-'+k,s[k]);});}catch(e){}</script><?php
}, 0);

/** 一覧の 1 作品カード */
function ts_bunko_workcard($post) {
    $authors = get_the_terms($post->ID, 'ts_author');
    $bits = [];
    if ($authors && !is_wp_error($authors)) {
        $bits[] = esc_html($authors[0]->name);
    }
    if ($d = ts_meta($post->ID, 'pub_date_raw')) $bits[] = esc_html($d);
    $n = count(get_children(['post_parent' => $post->ID, 'post_type' => 'ts_work', 'fields' => 'ids']));
    if ($n > 1) $bits[] = '全' . $n . '話';
    echo '<li class="ts-workcard"><h2 class="ts-workcard-title"><a href="'
        . esc_url(get_permalink($post)) . '">' . esc_html(get_the_title($post)) . '</a></h2>';
    if ($bits) echo '<p class="ts-workcard-meta">' . implode('　·　', $bits) . '</p>';
    echo '</li>';
}
