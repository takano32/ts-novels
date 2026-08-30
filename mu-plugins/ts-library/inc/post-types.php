<?php
/**
 * 投稿タイプの登録 (設計 §1.1)。
 *
 * ts_work は階層 CPT 1 本で作品 (親) と話 (子) の両方を表す。
 *   - 親 (post_parent = 0)  = Work。`_ts_kind = work`
 *   - 子                    = Episode。`_ts_kind = episode`、menu_order = 話数
 *   - 単発作品は親 1 投稿だけ (Work = Episode 1:1)。`_ts_kind = work-episode`
 * 階層 CPT にすることで /works/{work}/{ep}/ の入れ子パーマリンクがコア機能で得られる。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'ts_library_register_post_types', 5 );

function ts_library_register_post_types(): void {

	// --- ts_work: 作品と話。公開面の主役。
	register_post_type(
		'ts_work',
		array(
			'labels'             => array(
				'name'          => '作品・話',
				'singular_name' => '作品・話',
				'menu_name'     => '作品・話',
				'all_items'     => 'すべての作品・話',
				'add_new_item'  => '作品・話を追加',
				'edit_item'     => '作品・話を編集',
				'search_items'  => '作品・話を検索',
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'hierarchical'       => true,
			'has_archive'        => false,   // 設計 §1.1。/works/ の索引は Phase 4 で用意する
			'supports'           => array( 'title', 'editor', 'excerpt', 'custom-fields', 'page-attributes', 'thumbnail' ),
			'rewrite'            => array(
				'slug'         => 'works',
				'with_front'   => false,
				'hierarchical' => true,       // /works/{work}/{ep}/ の入れ子を許す
				'feeds'        => false,
				'pages'        => true,
			),
			'menu_icon'          => 'dashicons-book-alt',
			'menu_position'      => 20,
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'delete_with_user'   => false,
		)
	);

	// --- ts_doc: 運営コンテンツ (編集"好"記・巻頭言・構築日記・解題)。Phase 4.6 で投入。
	register_post_type(
		'ts_doc',
		array(
			'labels'             => array(
				'name'          => '資料・運営文書',
				'singular_name' => '資料・運営文書',
				'menu_name'     => '資料・運営文書',
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'hierarchical'       => false,
			'has_archive'        => false,
			'supports'           => array( 'title', 'editor', 'excerpt', 'custom-fields', 'thumbnail' ),
			'rewrite'            => array(
				'slug'       => 'docs',
				'with_front' => false,
				'feeds'      => false,
			),
			'menu_icon'          => 'dashicons-media-document',
			'menu_position'      => 21,
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
		)
	);

	// --- ts_dojo: ストーリー道場 (2ndbbs)。登録のみ。使用は Phase 6.3。恒久 noindex。
	register_post_type(
		'ts_dojo',
		array(
			'labels'             => array(
				'name'          => 'ストーリー道場',
				'singular_name' => 'ストーリー道場',
				'menu_name'     => 'ストーリー道場',
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'hierarchical'       => false,
			'has_archive'        => false,
			// 当時の投稿に埋め込まれた感想を「読み取り専用のアーカイブコメント」として持つため
			// comments を supports に入れる。新規投稿は comments_open フィルタで恒久的に閉じる。
			'supports'           => array( 'title', 'editor', 'excerpt', 'custom-fields', 'comments' ),
			'rewrite'            => array(
				'slug'       => 'dojo',
				'with_front' => false,
				'feeds'      => false,
			),
			'menu_icon'          => 'dashicons-welcome-write-blog',
			'menu_position'      => 22,
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
		)
	);

	// --- ts_board_post: 感想板の投稿。登録のみ。使用は Phase 6.1/6.2。恒久 noindex。
	register_post_type(
		'ts_board_post',
		array(
			'labels'             => array(
				'name'          => '感想板の投稿',
				'singular_name' => '感想板の投稿',
				'menu_name'     => '感想板の投稿',
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_rest'       => false,
			'hierarchical'       => true,   // Re: の親子をそのまま post_parent で表す
			'has_archive'        => false,
			'supports'           => array( 'title', 'editor', 'custom-fields', 'page-attributes' ),
			'rewrite'            => array(
				'slug'         => 'boards',
				'with_front'   => false,
				'hierarchical' => true,
				'feeds'        => false,
			),
			'menu_icon'          => 'dashicons-format-chat',
			'menu_position'      => 23,
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
		)
	);
}

/**
 * コメントは全面的に閉じる。
 * ts_dojo の当時の感想は「読み取り専用のアーカイブ」なので、表示はするが投稿はさせない。
 */
add_filter(
	'comments_open',
	function ( $open, $post_id ) {
		if ( in_array( get_post_type( $post_id ), ts_library_post_types(), true ) ) {
			return false;
		}
		return $open;
	},
	10,
	2
);

add_filter(
	'pings_open',
	function ( $open, $post_id ) {
		if ( in_array( get_post_type( $post_id ), ts_library_post_types(), true ) ) {
			return false;
		}
		return $open;
	},
	10,
	2
);

/** 保存時にも comment_status / ping_status を closed に固定する (DB 側でも閉じる)。 */
add_filter(
	'wp_insert_post_data',
	function ( $data ) {
		if ( isset( $data['post_type'] ) && in_array( $data['post_type'], ts_library_post_types(), true ) ) {
			$data['comment_status'] = 'closed';
			$data['ping_status']    = 'closed';
		}
		return $data;
	},
	10,
	1
);
