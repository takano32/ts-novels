<?php
/**
 * タクソノミー 6 本の登録 (設計 §1.2)。
 *
 *   ts_author  /authors/{slug}/   作者。slug は感想板 author_id が原則
 *   ts_genre   /genre/{slug}/     ジャンル
 *   ts_type    /type/{slug}/      TS の種別 (変化手段)
 *   ts_keyword /keyword/{slug}/   自由タグ
 *   ts_world   /world/{slug}/     共有世界 (シェアワールド)
 *   ts_corpus  (非公開 URL)       収蔵区分。絞り込み専用
 *
 * `ts_author` の rewrite が複数形なのは、コアの author_base (/author/) と衝突させないため。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'ts_library_register_taxonomies', 6 );

function ts_library_register_taxonomies(): void {

	$common = array(
		'public'            => true,
		'publicly_queryable' => true,
		'show_ui'           => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'hierarchical'      => false,
	);

	// --- 作者。ts_dojo / ts_board_post にも付く (設計 §1.2)
	register_taxonomy(
		'ts_author',
		array( 'ts_work', 'ts_dojo', 'ts_board_post' ),
		array_merge(
			$common,
			array(
				'labels'  => array(
					'name'          => '作者',
					'singular_name' => '作者',
					'menu_name'     => '作者',
				),
				'rewrite' => array(
					'slug'       => 'authors',
					'with_front' => false,
				),
			)
		)
	);

	register_taxonomy(
		'ts_genre',
		array( 'ts_work', 'ts_dojo' ),
		array_merge(
			$common,
			array(
				'labels'  => array(
					'name'          => 'ジャンル',
					'singular_name' => 'ジャンル',
					'menu_name'     => 'ジャンル',
				),
				'rewrite' => array(
					'slug'       => 'genre',
					'with_front' => false,
				),
			)
		)
	);

	register_taxonomy(
		'ts_type',
		array( 'ts_work', 'ts_dojo' ),
		array_merge(
			$common,
			array(
				'labels'  => array(
					'name'          => '種別',
					'singular_name' => '種別',
					'menu_name'     => '種別',
				),
				'rewrite' => array(
					'slug'       => 'type',
					'with_front' => false,
				),
			)
		)
	);

	register_taxonomy(
		'ts_keyword',
		array( 'ts_work', 'ts_dojo' ),
		array_merge(
			$common,
			array(
				'labels'  => array(
					'name'          => 'キーワード',
					'singular_name' => 'キーワード',
					'menu_name'     => 'キーワード',
				),
				'rewrite' => array(
					'slug'       => 'keyword',
					'with_front' => false,
				),
			)
		)
	);

	register_taxonomy(
		'ts_world',
		array( 'ts_work', 'ts_dojo' ),
		array_merge(
			$common,
			array(
				'labels'  => array(
					'name'          => '共有世界',
					'singular_name' => '共有世界',
					'menu_name'     => '共有世界',
				),
				'rewrite' => array(
					'slug'       => 'world',
					'with_front' => false,
				),
			)
		)
	);

	// --- 収蔵区分。URL は与えない (設計 §1.2「非公開 URL、絞込用」)。
	register_taxonomy(
		'ts_corpus',
		array( 'ts_work', 'ts_dojo' ),
		array(
			'labels'             => array(
				'name'          => '収蔵区分',
				'singular_name' => '収蔵区分',
				'menu_name'     => '収蔵区分',
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'show_admin_column'  => true,
			'hierarchical'       => false,
			'rewrite'            => false,
			'query_var'          => 'ts_corpus',
		)
	);
}

/**
 * term meta の登録。値は catalog/ が正本で、`wp ts sync-terms` が書き込む。
 */
add_action( 'init', 'ts_library_register_term_meta', 7 );

function ts_library_register_term_meta(): void {

	$author_meta = array(
		'ts_display_variants',   // JSON 配列。表記揺れの全表示名
		'ts_yomi',               // 五十音の行 (lib-index の所属ページ由来)
		'ts_kansou_slug',
		'ts_kansou_slug_alt',    // JSON 配列。旧名義・別名義の板
		'ts_kansou_annex_url',   // 原本アーカイブ側の板ログ URL
		'ts_shared_boards',      // JSON 配列。参加した共有世界の板
		'ts_homepage_wayback',
		'ts_homepage_live',      // 現存確認済みの移転先のみ
		'ts_active_links',       // JSON 配列。なろう / pixiv 等
		'ts_contact_status',     // uncontacted / notified / permitted / declined / takedown / removed
		'ts_episode_count',
		'ts_first_date',
		'ts_last_date',
		'ts_slug_status',
		'ts_pseudo',             // 1 = 擬似作者 (作者不詳・シリーズ目次)
		'ts_sync_hash',
	);
	foreach ( $author_meta as $key ) {
		register_term_meta( 'ts_author', $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
	}

	foreach ( array( 'ts_genre', 'ts_type', 'ts_keyword', 'ts_world', 'ts_corpus' ) as $tax ) {
		foreach ( array( 'ts_raw_variants', 'ts_core', 'ts_tier', 'ts_catalog_count', 'ts_origin_author', 'ts_note', 'ts_sync_hash' ) as $key ) {
			register_term_meta( $tax, $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
		}
	}
}
