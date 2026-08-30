<?php
/**
 * meta robots の出力 (設計 v1.5)。
 *
 * **全域 noindex は行わない。** サイトは最初から公開 (インデックス可) で構築する。
 * noindex にするのは恒久 noindex 層だけ:
 *   - /boards/ (感想板の近代ビュー、Phase 6)
 *   - /dojo/   (ストーリー道場、Phase 6)
 * これは公開ゲートではなく、当時の掲示板投稿者 (第三者) のプライバシー配慮であり、
 * 公開後も外さない。.htaccess 側 (タスク 2.6) の X-Robots-Tag と二重に掛ける。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wp_robots', 'ts_library_robots' );

function ts_library_robots( array $robots ): array {
	if ( ts_library_is_permanent_noindex() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['index'] );
	}
	return $robots;
}

/** 現在のリクエストが恒久 noindex 層かどうか。 */
function ts_library_is_permanent_noindex(): bool {
	$types = ts_library_noindex_post_types();

	if ( is_singular( $types ) || is_post_type_archive( $types ) ) {
		return true;
	}

	// タクソノミーアーカイブは対象外 (作者ページは index させる)。
	// URL 直判定 — Phase 6 で /boards/ /dojo/ 配下に固定ページやリライトが増えても効くように。
	$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
	if ( is_string( $path ) && preg_match( '#^/(boards|dojo)(/|$)#', $path ) ) {
		return true;
	}

	return false;
}
