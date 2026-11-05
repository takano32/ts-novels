<?php
/**
 * コンテンツモデルの定義 (specs/S21.md〜S23.md、改訂 S37・S42、登録の実測 S181)。
 *
 * 投稿タイプ・タクソノミー・書誌カードの post meta の「名前と登録の引数」をここに集める。WordPress の関数は
 * 呼ばず、配列を返すだけ (register_post_type などを呼ぶのは includes/site-hooks.php の TS_Library)。
 * URL 基底 (/novel/ /docs/ /dojo/ /boards/ と authors/genre/type/keyword/world) と名前は外から見える振る舞いなので、
 * scripts/wp/ts_library_test.php が固定する。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Content_Model {

    /** 文庫の投稿タイプ 4 種。コメント閉鎖・空のメタ行の抑止・`wp ts reset` が消す集合はこれ
     *  (取り下げの申請 ts_takedown_request はここに入れない — 運用の原本で catalog から作り直せない。S125) */
    public const POST_TYPES = ['ts_work', 'ts_doc', 'ts_dojo', 'ts_board_post'];

    /** 公開タクソノミー 5 種 (URL を持ち、書庫のアーカイブになる) */
    public const PUBLIC_TAXONOMIES = ['ts_author', 'ts_genre', 'ts_type', 'ts_keyword', 'ts_world'];

    /** 全タクソノミー (公開 5 種 + 非公開の収蔵区分 ts_corpus)。sync-terms / verify / reset が見る */
    public const TAXONOMIES = ['ts_author', 'ts_genre', 'ts_type', 'ts_keyword', 'ts_world', 'ts_corpus'];

    /** 代表的な post meta (書誌カード・テーマが読む分)。ts_work に register_post_meta する */
    public const WORK_META_KEYS = ['_ts_episode_id', '_ts_work_slug', '_ts_kind', '_ts_source_path', '_ts_corpus',
        '_ts_pub_date_raw', '_ts_orig_url', '_ts_annex_url', '_ts_annex_yays_url',
        '_ts_kansou_slug', '_ts_kansou_annex_url', '_ts_arasuji', '_ts_author_comment',
        '_ts_suisen', '_ts_catalog_ref'];

    /** register_post_type の引数: 投稿タイプ => 引数 (登録の順に) */
    public static function post_types(): array {
        return [
            'ts_work' => [
                'labels' => ['name' => '作品', 'singular_name' => '作品'],
                'public' => true, 'hierarchical' => true, 'has_archive' => false,
                'menu_icon' => 'dashicons-book',
                'supports' => ['title', 'editor', 'excerpt', 'custom-fields', 'page-attributes', 'thumbnail'],
                // URL 基底はユーザ裁定 (2026-08-30) で /novel/ — 旧サイトの URL 語彙 (ts.novels.jp/novel/…) と揃える
                'rewrite' => ['slug' => 'novel', 'with_front' => false, 'hierarchical' => true],
                'show_in_rest' => true, // 管理画面のエディタ用 (運用上の編集は catalog 経由が正)
            ],
            'ts_doc' => [
                'labels' => ['name' => '運営文書', 'singular_name' => '運営文書'],
                'public' => true, 'hierarchical' => false, 'has_archive' => false,
                'menu_icon' => 'dashicons-media-document',
                'supports' => ['title', 'editor', 'custom-fields'],
                // 運営委員 (編集可) に解題だけを編集させ、作品 (ts_work) とは区別するため (specs/S116.md)。
                // 権限は wp ts role-sync が付ける (administrator にも ts_doc の基本権限を足す)
                'capability_type' => 'ts_doc', 'map_meta_cap' => true,
                'rewrite' => ['slug' => 'docs', 'with_front' => false],
                'show_in_rest' => true,
            ],
            'ts_dojo' => [
                'labels' => ['name' => 'ストーリー道場', 'singular_name' => '道場作品'],
                'public' => true, 'hierarchical' => false, 'has_archive' => true,
                'menu_icon' => 'dashicons-format-chat',
                'supports' => ['title', 'editor', 'custom-fields', 'comments'],
                'rewrite' => ['slug' => 'dojo', 'with_front' => false],
                'show_in_rest' => true,
            ],
            'ts_board_post' => [
                'labels' => ['name' => '感想板の投稿', 'singular_name' => '感想板の投稿'],
                'public' => true, 'hierarchical' => false, 'has_archive' => false,
                'exclude_from_search' => true, 'menu_icon' => 'dashicons-testimonial',
                'supports' => ['title', 'editor', 'custom-fields'],
                'rewrite' => ['slug' => 'boards', 'with_front' => false],
                'show_in_rest' => false,
            ],
        ];
    }

    /** register_taxonomy の引数: タクソノミー => ['object_types' => 対象の投稿タイプ, 'args' => 引数] (登録の順に) */
    public static function taxonomies(): array {
        $public = function (string $slug, string $label): array {
            return ['object_types' => ['ts_work', 'ts_dojo', 'ts_board_post'], 'args' => [
                'labels' => ['name' => $label], 'public' => true, 'hierarchical' => false,
                'show_admin_column' => true, 'show_in_rest' => true,
                'rewrite' => ['slug' => $slug, 'with_front' => false],
            ]];
        };
        return [
            'ts_author' => $public('authors', '作者'),       // コアの author_base=/author/ と衝突しない複数形
            'ts_genre' => $public('genre', 'ジャンル'),
            'ts_type' => $public('type', '種別'),
            'ts_keyword' => $public('keyword', 'キーワード'),
            'ts_world' => $public('world', '共有世界'),
            'ts_corpus' => ['object_types' => ['ts_work'], 'args' => [   // 収蔵区分: 絞込用・URL は持たせない
                'labels' => ['name' => '収蔵区分'], 'public' => false,
                'show_ui' => true, 'show_admin_column' => true, 'show_in_rest' => true,
                'rewrite' => false,
            ]],
        ];
    }

    /** register_post_meta の引数 (全キー共通) */
    public static function work_meta_args(): array {
        return ['type' => 'string', 'single' => true, 'show_in_rest' => false, 'auth_callback' => '__return_false'];
    }
}
