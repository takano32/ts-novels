<?php
/**
 * episodes.jsonl の 1 話 → 投稿の post meta への写像 (T8.2、書誌カードの被覆 T9.4)。WordPress を使わない。
 *
 * メタのキーの一覧がここの 1 か所にあり、`wp ts import` (includes/commands/import.php) が書き、
 * 書誌カード (includes/render/) が読む。値が null か空配列のキーは import がメタを消す。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Episode_Fields {

    /** 本文ペイロードが無い話に入れる本文 (メタのみ投入のときは入れない) */
    public const PLACEHOLDER_BODY = '<!-- wp:paragraph --><p>(本文は原本アーカイブでお読みください)</p><!-- /wp:paragraph -->';

    /**
     * meta() 以外に ts_work の投稿に書くメタのキー。import (includes/commands/import.php・command-base.php の
     * stamp_import) と取り下げ (includes/commands/takedown.php・includes/takedown/takedown-apply.php、
     * 値は TS_Visibility_Rules::takedown_change) が書く。表示 (includes/render/・visibility-hooks.php・テーマ) が
     * 読むキーは meta() のキーとこの表に全部含まれる (scripts/wp/tests/catalog.php が grep で確かめる)。
     * 書く側のコードはキーを文字列で書いており、この表は照合のための一覧。
     */
    public const WORK_KEYS = [
        // import
        '_ts_work_slug', '_ts_kind', '_ts_needs_review', '_ts_title_pages', '_ts_alias_paths', '_ts_episode_count',
        '_ts_body_status', '_ts_import_hash', '_ts_fill_hash', '_ts_last_import_gmt',
        // 取り下げ (S121)
        '_ts_visibility', '_ts_takedown_id', '_ts_public_note', '_ts_takedown_date',
        // 旧版の印 (S118)。読むのは帯と取り下げ。書く側は mu-plugin に無い (T25.4.1)
        '_ts_superseded_by',
    ];

    /** 話の共通メタ: meta_key => 値 (episodes.jsonl の欄をそのまま) */
    public static function meta(array $ep): array {
        return [
            '_ts_episode_id' => $ep['episode_id'],
            '_ts_source_path' => $ep['source_path'], '_ts_source_anchor' => $ep['source_anchor'],
            '_ts_corpus' => $ep['corpus'], '_ts_catalog_ref' => $ep['catalog_ref'],
            '_ts_pub_date_raw' => $ep['date_raw'], '_ts_size_kb' => $ep['size_kb'],
            '_ts_files_n' => $ep['files_n'],
            '_ts_illustrator' => $ep['illustrator'], '_ts_illustrator_url' => $ep['illustrator_url'],
            '_ts_kansou_slug' => $ep['kansou_slug'], '_ts_kansou_annex_url' => $ep['kansou_annex_url'],
            '_ts_arasuji' => $ep['arasuji'], '_ts_author_comment' => $ep['comment'],
            '_ts_suisen' => $ep['suisen'], '_ts_osusume' => $ep['osusume'],
            '_ts_nav_links' => $ep['nav_links'],
            '_ts_orig_url' => $ep['orig_url'], '_ts_annex_url' => $ep['annex_url'],
            '_ts_annex_yays_url' => $ep['annex_yays_url'],
            '_ts_provenance' => $ep['provenance'],
            '_ts_raw_genre' => $ep['genre_raw'], '_ts_raw_type' => $ep['type_raw'],
            '_ts_raw_keywords' => $ep['keywords_raw'], '_ts_zokusei' => $ep['zokusei'],
        ];
    }
}
