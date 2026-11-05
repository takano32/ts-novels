<?php
/**
 * WordPress に触る側が読み込み時に登録するもの (specs/S242.md の「フックと優先度」「ショートコード」): フックの名前・優先度・
 * 登録の順・受け取る引数の数、ショートコード 2 つ、init で登録する投稿タイプ・taxonomy・meta・rewrite・cron。
 * WordPress の代わり (scripts/wp/tests/wp-stubs.php) が記録したものを見る。scripts/wp/ts_library_test.php から WP の表を読んだ後に読む。
 * 名前や優先度を変えるときは S242 の表とここを同じコミットで直す。
 */

check('フックの登録 (ファイル・種類・名前・優先度・callable・引数の数。登録の順)', TS_WP_Stubs::hook_list(), [
    'site-hooks.php: action init@5 TS_Library::register',
    'site-hooks.php: filter comments_open@20 TS_Library::no_comments (2)',
    'site-hooks.php: filter pings_open@20 TS_Library::no_comments (2)',
    'site-hooks.php: action wp_head@1 TS_Library::noindex_boards',
    'site-hooks.php: action pre_get_posts@10 TS_Library::front_page_query',
    'site-hooks.php: action template_redirect@10 TS_Library::nocache_home',
    'site-hooks.php: action pre_get_posts@10 TS_Library::archive_query',
    'site-hooks.php: filter render_block_core/heading@10 TS_Library::home_heading (2)',
    'site-hooks.php: filter render_block_core/navigation-link@10 TS_Library::drop_placeholder_nav (2)',
    'site-hooks.php: filter render_block_core/paragraph@10 TS_Library::drop_empty_meta_row (2)',
    'site-hooks.php: filter run_wptexturize@10 __return_false',
    'site-hooks.php: filter option_use_smilies@10 __return_false',
    'site-hooks.php: filter do_redirect_guess_404_permalink@10 __return_false',
    'index-routing.php: action init@6 {closure}',
    'index-routing.php: filter query_vars@10 {closure}',
    'index-routing.php: filter template_include@10 {closure}',
    'index-routing.php: action pre_get_posts@10 {closure}',
    'index-routing.php: filter document_title_parts@10 {closure}',
    'visibility-hooks.php: action template_redirect@0 {closure}',
    'visibility-hooks.php: filter template_include@20 {closure}',
    'visibility-hooks.php: filter document_title_parts@20 {closure}',
    'visibility-hooks.php: filter private_title_format@10 {closure} (2)',
    'takedown-store.php: action init@6 {closure}',
    'takedown-store.php: filter wp_insert_post_data@10 {closure}',
    'takedown-form.php: action template_redirect@5 {closure}',
    'takedown-form.php: action admin_post_nopriv_ts_takedown@10 ts_takedown_handle_post',
    'takedown-form.php: action admin_post_ts_takedown@10 ts_takedown_handle_post',
    'takedown-form.php: action template_redirect@1 {closure}',
    'takedown-cron.php: action init@20 {closure}',
    'takedown-cron.php: action ts_takedown_tick@10 ts_takedown_tick',
    'takedown-admin.php: action add_meta_boxes_ts_takedown_request@10 {closure}',
    'takedown-admin.php: action admin_notices@10 {closure}',
    'takedown-admin.php: action save_post_ts_takedown_request@10 {closure} (2)',
    'takedown-admin.php: filter manage_ts_takedown_request_posts_columns@10 {closure}',
    'takedown-admin.php: action manage_ts_takedown_request_posts_custom_column@10 {closure} (2)',
]);
check('TS_Library の pre_get_posts (トップ・書庫) は索引 (index-routing.php) より先に登録されている',
      array_map(function ($h) { return $h[5]; }, TS_WP_Stubs::callbacks('pre_get_posts')), ['site-hooks.php', 'site-hooks.php', 'index-routing.php']);
check('template_redirect は 410/404 (0) → 確認リンク (1) → フォームの no-cache (5) → トップの no-cache (10) の順',
      array_map(function ($h) { return $h[5] . '@' . $h[2]; }, TS_WP_Stubs::callbacks('template_redirect')),
      ['visibility-hooks.php@0', 'takedown-form.php@1', 'takedown-form.php@5', 'site-hooks.php@10']);
check('ショートコード', array_keys(TS_WP_Stubs::$shortcodes), ['ts_takedown_form', 'ts_removed_list']);

// init で登録するもの (TS_Library::register (5) → 申請の投稿タイプと rewrite (6) → cron の予約 (20))
TS_WP_Stubs::reset();
TS_WP_Stubs::run_init();
check('init で登録する投稿タイプ (登録の順。申請は TS_Content_Model::POST_TYPES の後)', array_keys(TS_WP_Stubs::$registered['post_type']),
      array_merge(TS_Content_Model::POST_TYPES, [TS_TAKEDOWN_TYPE]));
check('init で登録する taxonomy', array_keys(TS_WP_Stubs::$registered['taxonomy']), TS_Content_Model::TAXONOMIES);
check('register_post_type の引数は TS_Content_Model のまま', TS_WP_Stubs::$registered['post_type']['ts_work'], TS_Content_Model::post_types()['ts_work']);
check('register_taxonomy の対象と引数は TS_Content_Model のまま', TS_WP_Stubs::$registered['taxonomy']['ts_corpus'], TS_Content_Model::taxonomies()['ts_corpus']);
check('register_post_meta は ts_work の WORK_META_KEYS', array_keys(TS_WP_Stubs::$registered['meta']['ts_work'] ?? []), TS_Content_Model::WORK_META_KEYS);
check('申請の投稿タイプは公開しない・UI あり・権限は ts_manage_takedown',
      [TS_WP_Stubs::$registered['post_type'][TS_TAKEDOWN_TYPE]['public'], TS_WP_Stubs::$registered['post_type'][TS_TAKEDOWN_TYPE]['show_ui'],
       array_values(array_unique(TS_WP_Stubs::$registered['post_type'][TS_TAKEDOWN_TYPE]['capabilities']))], [false, true, ['ts_manage_takedown']]);
check('rewrite は索引と年別の 2 本', array_map(function ($r) { return $r[0]; }, TS_WP_Stubs::$registered['rewrite']),
      ['^index/(kana|timeline|bunrui|vocabulary|osusume|docs)/?$', '^year/([0-9]{4})/?$']);
check('cron ts_takedown_tick を hourly で予約する', array_filter(TS_WP_Stubs::$log, function ($l) { return $l[0] === 'wp_schedule_event'; }),
      [['wp_schedule_event', ['ts_takedown_tick', 'hourly']]]);
check('URL 基底は登録の引数から (TS_Content_Model と同じ)', [TS_WP_Stubs::$post_type_slugs['ts_work'], TS_WP_Stubs::$post_type_slugs[TS_TAKEDOWN_TYPE],
      TS_WP_Stubs::$taxonomy_slugs['ts_author'], TS_WP_Stubs::$taxonomy_slugs['ts_corpus']], ['novel', false, 'authors', false]);
