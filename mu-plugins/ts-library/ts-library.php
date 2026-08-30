<?php
/**
 * ts-library — 少年少女文庫 WordPress ライブラリのコンテンツモデル定義。
 *
 * 進行台帳 (docs/wp-implementation-tasks.md) タスク 2.1 / 2.2。
 * 設計は docs/wordpress-library-design.md §1 (投稿タイプ・タクソノミー・post meta)。
 *
 * ここに置く理由: mu-plugin は管理画面から無効化できない。テーマやプラグインを
 * 触った事故でコンテンツモデルごと消えるのを防ぐ (docs/glossary.md「mu-plugin」)。
 *
 * 方針:
 *   - PHP 8.0 互換 (本番 CLI/web とも 8.0.30)。enum / readonly / never は使わない
 *   - 表示に出る文言に内部用語 (本館・別館・旧館) を書かない (docs/glossary.md)
 *   - WP DB は catalog/ の派生ビュー。管理画面での手編集は想定しない
 *     (ただし緊急対応で手編集された投稿を import が黙って潰さない保護は入れてある。
 *      inc/class-ts-import.php の「手動編集の検出」を参照)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TS_LIBRARY_VERSION', '0.1.0' );
define( 'TS_LIBRARY_DIR', __DIR__ );

/** 本プラグインが管理する投稿タイプ。 */
function ts_library_post_types(): array {
	return array( 'ts_work', 'ts_doc', 'ts_dojo', 'ts_board_post' );
}

/** 本プラグインが管理するタクソノミー (6 本)。 */
function ts_library_taxonomies(): array {
	return array( 'ts_author', 'ts_genre', 'ts_type', 'ts_keyword', 'ts_world', 'ts_corpus' );
}

/**
 * 恒久 noindex 層の投稿タイプ。
 * 設計 v1.5 により全域 noindex は撤回されたが、当時の掲示板投稿者のハンドルが載る層だけは
 * 公開ゲートとは別の理由 (第三者のプライバシー配慮) で恒久的に noindex を維持する。
 */
function ts_library_noindex_post_types(): array {
	return array( 'ts_dojo', 'ts_board_post' );
}

require_once TS_LIBRARY_DIR . '/inc/post-types.php';
require_once TS_LIBRARY_DIR . '/inc/taxonomies.php';
require_once TS_LIBRARY_DIR . '/inc/meta.php';
require_once TS_LIBRARY_DIR . '/inc/robots.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once TS_LIBRARY_DIR . '/inc/class-ts-catalog.php';
	require_once TS_LIBRARY_DIR . '/inc/class-ts-import.php';
	require_once TS_LIBRARY_DIR . '/inc/class-ts-cli.php';
	WP_CLI::add_command( 'ts', 'TS_Library_CLI' );
}
