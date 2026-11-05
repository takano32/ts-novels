<?php
/**
 * コンテンツモデル (specs/S21.md、S181.md): 投稿タイプ・タクソノミーの名前と URL 基底は外から見える振る舞いなので固定する。
 * `wp ts reset` の対象 (S125) もここで見る。scripts/wp/ts_library_test.php から読む。
 */

check('投稿タイプは ts の 4 種', TS_Content_Model::POST_TYPES, ['ts_work', 'ts_doc', 'ts_dojo', 'ts_board_post']);
check('登録の順は POST_TYPES と同じ', array_keys(TS_Content_Model::post_types()), TS_Content_Model::POST_TYPES);
check('タクソノミーは公開 5 種 + ts_corpus', TS_Content_Model::TAXONOMIES, array_merge(TS_Content_Model::PUBLIC_TAXONOMIES, ['ts_corpus']));
check('登録の順は TAXONOMIES と同じ', array_keys(TS_Content_Model::taxonomies()), TS_Content_Model::TAXONOMIES);

$pt = TS_Content_Model::post_types();
check('URL 基底 (S26: /novel/ /docs/ /dojo/ /boards/)',
      array_map(function ($a) { return $a['rewrite']['slug']; }, $pt), ['ts_work' => 'novel', 'ts_doc' => 'docs', 'ts_dojo' => 'dojo', 'ts_board_post' => 'boards']);
check('ts_work は階層 (親が作品・子が話) で URL も階層', [$pt['ts_work']['hierarchical'], $pt['ts_work']['rewrite']['hierarchical']], [true, true]);
check('with_front を付けない', array_values(array_unique(array_map(function ($a) { return $a['rewrite']['with_front']; }, $pt))), [false]);
check('ts_doc は capability_type=ts_doc (運営委員 (編集可) が解題だけ編集。S116)', [$pt['ts_doc']['capability_type'], $pt['ts_doc']['map_meta_cap']], ['ts_doc', true]);
check('ts_board_post は検索から外し REST に出さない', [$pt['ts_board_post']['exclude_from_search'], $pt['ts_board_post']['show_in_rest']], [true, false]);
check('ts_dojo だけアーカイブを持つ', array_keys(array_filter($pt, function ($a) { return $a['has_archive']; })), ['ts_dojo']);
check('ts_work の supports (eyecatch は thumbnail、話順は page-attributes に頼る)', $pt['ts_work']['supports'],
      ['title', 'editor', 'excerpt', 'custom-fields', 'page-attributes', 'thumbnail']);
check('コメントを受けるのは ts_dojo だけ', array_keys(array_filter($pt, function ($a) { return in_array('comments', $a['supports'], true); })), ['ts_dojo']);
check('管理画面のアイコン', array_map(function ($a) { return $a['menu_icon']; }, $pt),
      ['ts_work' => 'dashicons-book', 'ts_doc' => 'dashicons-media-document', 'ts_dojo' => 'dashicons-format-chat', 'ts_board_post' => 'dashicons-testimonial']);
check('管理画面の名前', array_map(function ($a) { return $a['labels']['name']; }, $pt),
      ['ts_work' => '作品', 'ts_doc' => '運営文書', 'ts_dojo' => 'ストーリー道場', 'ts_board_post' => '感想板の投稿']);

$tx = TS_Content_Model::taxonomies();
check('タクソノミーの URL 基底 (作者は authors — コアの author_base と衝突しない複数形)',
      array_map(function ($t) { return $t['args']['rewrite'] === false ? false : $t['args']['rewrite']['slug']; }, $tx),
      ['ts_author' => 'authors', 'ts_genre' => 'genre', 'ts_type' => 'type', 'ts_keyword' => 'keyword', 'ts_world' => 'world', 'ts_corpus' => false]);
check('公開タクソノミーは ts_work / ts_dojo / ts_board_post に付く', $tx['ts_genre']['object_types'], ['ts_work', 'ts_dojo', 'ts_board_post']);
check('ts_corpus は非公開で ts_work だけ', [$tx['ts_corpus']['args']['public'], $tx['ts_corpus']['object_types']], [false, ['ts_work']]);
check('書誌カードのメタ (register_post_meta するキー)', TS_Content_Model::WORK_META_KEYS, ['_ts_episode_id', '_ts_work_slug', '_ts_kind',
      '_ts_source_path', '_ts_corpus', '_ts_pub_date_raw', '_ts_orig_url', '_ts_annex_url', '_ts_annex_yays_url', '_ts_kansou_slug',
      '_ts_kansou_annex_url', '_ts_arasuji', '_ts_author_comment', '_ts_suisen', '_ts_catalog_ref']);
check('書誌カードのメタに episode_id と work_slug', in_array('_ts_episode_id', TS_Content_Model::WORK_META_KEYS, true) && in_array('_ts_work_slug', TS_Content_Model::WORK_META_KEYS, true), true);
check('メタは文字列・単一・REST に出さない・書けない', TS_Content_Model::work_meta_args(),
      ['type' => 'string', 'single' => true, 'show_in_rest' => false, 'auth_callback' => '__return_false']);

// wp ts reset の対象外 (S125)
check('reset の対象 (POST_TYPES) に申請を含まない', in_array(TS_TAKEDOWN_TYPE, TS_Content_Model::POST_TYPES, true), false);
check('申請の投稿タイプ名', TS_TAKEDOWN_TYPE, 'ts_takedown_request');
check('reset の説明文に書く', strpos((new ReflectionMethod('TS_Reset_Command', 'reset'))->getDocComment(), '取り下げの申請 (ts_takedown_request) は消さない') !== false, true);
// reset が消す集合は WordPress の代わりの上で流して見る (scripts/wp/tests/commands-wp.php)
