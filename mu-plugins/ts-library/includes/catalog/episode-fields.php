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
