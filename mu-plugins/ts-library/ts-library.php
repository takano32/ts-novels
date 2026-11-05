<?php
/**
 * Plugin Name: ts-library
 * Description: 少年少女文庫 ライブラリのコンテンツモデル (CPT・タクソノミー・メタ) と wp ts コマンド。
 *              mu-plugins 配置 = 管理画面から無効化できない (コンテンツモデル消失事故の防止)。
 * Version: 0.1.0
 *
 * 根幹コード (高品位なモデルとエフォートの所管)。設計: specs/S21.md〜S23.md (+改訂 specs/S37.md・S42.md)。
 * - 投稿タイプ: ts_work (階層 = 親が作品/子が話)・ts_doc・ts_dojo・ts_board_post
 * - タクソノミー: ts_author/ts_genre/ts_type/ts_keyword/ts_world (公開) + ts_corpus (非公開)
 * - S42: サイトは公開前提。恒久 noindex は /boards/ /dojo/ (第三者ハンドルが載る層) のみ
 * - コメントは全面閉鎖 (rounge の 94% スパム化が根拠)
 *
 * ファイル構成 (specs/S242.md): includes/ を、WordPress を使わない部分 (判断・計算・文面) と WordPress に触る部分
 * (フック・DB・出力)、WP-CLI のコマンドに分け、読むファイルは includes/files.php の明示リストだけ (glob は使わない —
 * 配備は rsync --delete 無しで、古いファイルが本番に残るため)。
 */

if (!defined('ABSPATH')) { exit; }

require __DIR__ . '/includes/files.php';
foreach (TS_Library_Files::PURE as $ts_library_file) {
    require __DIR__ . '/includes/' . $ts_library_file;
}
foreach (TS_Library_Files::WP as $ts_library_file) {   // site-hooks.php が TS_Library::boot() を呼ぶ
    require __DIR__ . '/includes/' . $ts_library_file;
}
if (defined('WP_CLI') && WP_CLI) {                      // wp ts コマンド (Web リクエストでは読まない)
    foreach (TS_Library_Files::CLI as $ts_library_file) {
        require __DIR__ . '/includes/' . $ts_library_file;
    }
}
unset($ts_library_file);
