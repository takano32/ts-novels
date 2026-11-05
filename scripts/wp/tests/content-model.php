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

$tx = TS_Content_Model::taxonomies();
check('タクソノミーの URL 基底 (作者は authors — コアの author_base と衝突しない複数形)',
      array_map(function ($t) { return $t['args']['rewrite'] === false ? false : $t['args']['rewrite']['slug']; }, $tx),
      ['ts_author' => 'authors', 'ts_genre' => 'genre', 'ts_type' => 'type', 'ts_keyword' => 'keyword', 'ts_world' => 'world', 'ts_corpus' => false]);
check('公開タクソノミーは ts_work / ts_dojo / ts_board_post に付く', $tx['ts_genre']['object_types'], ['ts_work', 'ts_dojo', 'ts_board_post']);
check('ts_corpus は非公開で ts_work だけ', [$tx['ts_corpus']['args']['public'], $tx['ts_corpus']['object_types']], [false, ['ts_work']]);
check('書誌カードのメタは 15 キー', count(TS_Content_Model::WORK_META_KEYS), 15);
check('書誌カードのメタに episode_id と work_slug', in_array('_ts_episode_id', TS_Content_Model::WORK_META_KEYS, true) && in_array('_ts_work_slug', TS_Content_Model::WORK_META_KEYS, true), true);
check('メタは文字列・単一・REST に出さない・書けない', TS_Content_Model::work_meta_args(),
      ['type' => 'string', 'single' => true, 'show_in_rest' => false, 'auth_callback' => '__return_false']);

// wp ts reset の対象外 (S125)
check('reset の対象 (POST_TYPES) に申請を含まない', in_array(TS_TAKEDOWN_TYPE, TS_Content_Model::POST_TYPES, true), false);
check('申請の投稿タイプ名', TS_TAKEDOWN_TYPE, 'ts_takedown_request');
check('reset の説明文に書く', strpos((new ReflectionMethod('TS_Reset_Command', 'reset'))->getDocComment(), '取り下げの申請 (ts_takedown_request) は消さない') !== false, true);
$reset_src = file_get_contents("$plugin/includes/commands/reset.php");
check('reset は POST_TYPES と TAXONOMIES を消す', strpos($reset_src, 'TS_Content_Model::POST_TYPES as $type') !== false
      && strpos($reset_src, 'TS_Content_Model::TAXONOMIES as $tax') !== false, true);
