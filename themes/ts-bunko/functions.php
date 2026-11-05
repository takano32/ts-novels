<?php
/**
 * ts-bunko — 見た目だけのテーマ (機能は mu-plugin ts-library 側。設計 specs/S27.md)。
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
['size','lh','font','dark','orig'].forEach(function(k){if(s[k])h.setAttribute('data-ts-'+k,s[k]);});}catch(e){}</script><?php
}, 0);

// 一覧の 1 作品カード ts_bunko_workcard は mu-plugin (includes/render/render-list.php) にある (T45.1.5.1。ここで定義すると二重定義で落ちる)
