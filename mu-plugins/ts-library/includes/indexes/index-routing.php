<?php
/**
 * 索引ページ群 (T10.3。高品位なモデルとエフォートの所管) のルーティング: /index/{kana,timeline,bunrui,vocabulary,osusume,docs}/
 * と /year/YYYY/。WordPress に触る側。種類と題名は includes/indexes/index-text.php、HTML は index-render.php、
 * 見た目はテーマ (ts-index.php / ts-year.php)。
 *
 * pre_get_posts は includes/site-hooks.php の TS_Library (トップのランダム 12 件) より後に登録されていること
 * (同じ優先度 10 で、ここが posts_per_page と orderby を上書きする)。
 */

if (!defined('ABSPATH')) { exit; }

add_action('init', function () {
    add_rewrite_rule('^index/(kana|timeline|bunrui|vocabulary|osusume|docs)/?$',
        'index.php?ts_index=$matches[1]', 'top');
    add_rewrite_rule('^year/([0-9]{4})/?$', 'index.php?ts_year=$matches[1]', 'top');
}, 6);

/** query var は公開なので配列や任意文字列が来る。索引の種類は許可リストで正規化する
 *  (配列を添字に使うと PHP 8 では TypeError = 500 になる) */
function ts_index_kind() {
    $v = get_query_var('ts_index');
    if (!is_string($v)) return '';
    return in_array($v, TS_Index_Text::KINDS, true) ? $v : '';
}

function ts_index_year() {
    $v = get_query_var('ts_year');
    return is_string($v) && preg_match('/^[0-9]{4}$/', $v) ? (int) $v : 0;
}

add_filter('query_vars', function ($vars) {
    $vars[] = 'ts_index';
    $vars[] = 'ts_year';
    return $vars;
});

add_filter('template_include', function ($template) {
    if (ts_index_kind()) {
        $t = locate_template('ts-index.php');
        if ($t) return $t;
    }
    if (ts_index_year()) {
        $t = locate_template('ts-year.php');
        if ($t) return $t;
    }
    return $template;
});

/** 索引ページを 404 にしない (メインクエリは投稿を引かないので) */
add_action('pre_get_posts', function ($q) {
    if (is_admin() || !$q->is_main_query()) return;
    if (ts_index_kind()) {
        $q->set('posts_per_page', 1);
        add_filter('pre_handle_404', '__return_true');
    }
    if ($y = ts_index_year()) {
        $q->set('post_type', 'ts_work');
        $q->set('post_parent', 0);
        $q->set('posts_per_page', -1);
        $q->set('orderby', 'date');
        $q->set('order', 'ASC');
        $q->set('year', $y);
    }
});

add_filter('document_title_parts', function ($parts) {
    if ($k = ts_index_kind()) $parts['title'] = TS_Index_Text::TITLES[$k] ?? '索引';
    if ($y = ts_index_year()) $parts['title'] = TS_Index_Text::year_title($y);
    return $parts;
});
