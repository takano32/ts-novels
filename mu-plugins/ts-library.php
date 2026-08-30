<?php
/**
 * Plugin Name: ts-library loader
 * Description: mu-plugins 直下の *.php しか WordPress は自動ロードしない。本体は ts-library/ にある。
 * Version:     0.1.0
 * Requires PHP: 8.0
 *
 * 進行台帳 タスク 2.1。リポジトリ mu-plugins/ が正本で、本番へは scripts/wp/deploy.sh が rsync する。
 * サーバ上で直接編集しないこと (次の deploy で消える)。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ts_library_main = __DIR__ . '/ts-library/ts-library.php';
if ( file_exists( $ts_library_main ) ) {
	require_once $ts_library_main;
}
