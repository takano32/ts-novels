<?php
/**
 * post meta の登録 (設計 §1.3)。全キーが `_ts_` prefix。
 *
 * 値の正本は catalog/ で、書き込むのは `wp ts import` だけ。
 * JSON を入れる欄は「JSON 文字列」として保存する (WP の serialize を使わない —
 * mysql から直接読んでも人が読める形にしておくため)。
 *
 * mailto 由来のメールアドレスは catalog 生成段階で除去済みで、ここには構造的に入らない。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 登録する post meta のキー一覧。 */
function ts_library_meta_keys(): array {
	return array(
		// --- 共通
		'_ts_kind',              // work / episode / work-episode
		'_ts_import_hash',       // catalog レコードのハッシュ (冪等 upsert 用)
		'_ts_imported_at',
		'_ts_import_modified',   // import 直後の post_modified_gmt (手動編集の検出用)
		'_ts_catalog_commit',
		'_ts_work_slug',
		'_ts_author_slug',
		'_ts_author_display',

		// --- Work
		'_ts_episode_count',
		'_ts_first_date',
		'_ts_last_date',
		'_ts_title_pages',       // JSON
		'_ts_work_seed',         // JSON (evidence)
		'_ts_multi_directory',
		'_ts_alias_paths',       // JSON
		'_ts_slug_source',
		'_ts_slug_status',
		'_ts_corpora',           // JSON

		// --- Episode (話 = 目録エントリ)
		'_ts_episode_id',        // 冪等キー
		'_ts_source_path',
		'_ts_source_anchor',
		'_ts_source_kind',
		'_ts_source_exists',
		'_ts_entry_type',        // html / image / external / text
		'_ts_entry_role',        // unattributed / series-index 等
		'_ts_corpus',
		'_ts_catalog_ref',       // lib17.html#12
		'_ts_orig_url',
		'_ts_annex_url',
		'_ts_annex_yays_url',
		'_ts_pub_date_raw',
		'_ts_date_precision',
		'_ts_weekday',
		'_ts_size_kb',
		'_ts_files_count',
		'_ts_illustrator',       // JSON 配列
		'_ts_illustrator_url',
		'_ts_illustrator_raw',
		'_ts_homepage',
		'_ts_kansou_slug',
		'_ts_kansou_annex_url',
		'_ts_arasuji',
		'_ts_author_comment',
		'_ts_suisen',
		'_ts_osusume',           // JSON (読者オススメ 出リンク)
		'_ts_osusume_in',        // JSON (被推薦の逆引き。import が構築)
		'_ts_nav_links',         // JSON
		'_ts_inline_links',      // JSON
		'_ts_raw_genre',         // JSON
		'_ts_raw_type',          // JSON
		'_ts_raw_keywords',      // JSON
		'_ts_zokusei',           // JSON (旧目録の【属性】)
		'_ts_provenance',        // JSON (回収経路)
		'_ts_metadata_source',   // 目録外収蔵のメタの出所
		'_ts_text_chars',
		'_ts_commented_out',     // 旧目録で HTML コメントに隠されていた
		'_ts_noteky_url',
		'_ts_published_in_bunko',
		'_ts_repost_url',
		'_ts_alias_of',
		'_ts_episode_no',        // = menu_order
		'_ts_needs_review',
		'_ts_body_status',       // md / raw-fallback / no-source
		'_ts_reflow_mode',       // br-para / br-hardwrap / p / mso / custom
		'_ts_body_sha256',
		'_ts_notice',            // 旧目録の編集部注記
		'_ts_override_note',     // episode_overrides.yml による上書きの説明
	);
}

add_action( 'init', 'ts_library_register_post_meta', 8 );

function ts_library_register_post_meta(): void {
	$types = ts_library_post_types();
	foreach ( $types as $post_type ) {
		foreach ( ts_library_meta_keys() as $key ) {
			register_post_meta(
				$post_type,
				$key,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}
}
