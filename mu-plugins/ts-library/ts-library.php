<?php
/**
 * Plugin Name: ts-library
 * Description: 少年少女文庫 ライブラリのコンテンツモデル (CPT・タクソノミー・メタ) と wp ts コマンド。
 *              mu-plugins 配置 = 管理画面から無効化できない (コンテンツモデル消失事故の防止)。
 * Version: 0.1.0
 *
 * 根幹コード (Fable 所管)。設計: docs/wordpress-library-design.md §1 (+v1.2/v1.5 改訂)。
 * - 投稿タイプ: ts_work (階層 = 親が作品/子が話)・ts_doc・ts_dojo・ts_board_post
 * - タクソノミー: ts_author/ts_genre/ts_type/ts_keyword/ts_world (公開) + ts_corpus (非公開)
 * - v1.5: サイトは公開前提。恒久 noindex は /boards/ /dojo/ (第三者ハンドルが載る層) のみ
 * - コメントは全面閉鎖 (rounge の 94% スパム化が根拠)
 */

if (!defined('ABSPATH')) { exit; }

final class TS_Library {

    public static function boot(): void {
        add_action('init', [self::class, 'register'], 5);
        add_filter('comments_open', [self::class, 'no_comments'], 20, 2);
        add_filter('pings_open', [self::class, 'no_comments'], 20, 2);
        add_action('wp_head', [self::class, 'noindex_boards'], 1);
        add_action('pre_get_posts', [self::class, 'front_page_query']);
        add_action('template_redirect', [self::class, 'nocache_home']);
        add_filter('render_block_core/heading', [self::class, 'home_heading'], 10, 2);
        add_filter('render_block_core/navigation-link', [self::class, 'drop_placeholder_nav'], 10, 2);
        add_filter('render_block_core/paragraph', [self::class, 'drop_empty_meta_row'], 10, 2);
        // 旧 URL 基底 (/works/ 等) からの 404 推測リダイレクトはしない (ユーザ裁定 2026-08-30。
        // 正規 URL は catalog が決める — 推測は誤誘導のもと)
        add_filter('do_redirect_guess_404_permalink', '__return_false');
    }

    /** 既定テーマ (TT5) の home テンプレが出す「ブログ」見出しを差し替える (Phase 4 で正式トップに) */
    public static function home_heading($content, $block) {
        if (is_home() && strpos($content, '>ブログ<') !== false) {
            return str_replace('>ブログ<', '>作品一覧<', $content);
        }
        return $content;
    }

    /** 既定ナビのプレースホルダ項目 (href="#" のブログ/ショップ等) を出さない */
    public static function drop_placeholder_nav($content, $block) {
        return (strpos($content, 'href="#"') !== false) ? '' : $content;
    }

    /** TT5 の single テンプレが出す空のメタ行 (「執筆者: 」「カテゴリ: 」— ts_work は
     *  post_author もコアのカテゴリも使わないため値が空) を出さない (Phase 4 で正式テーマに) */
    public static function drop_empty_meta_row($content, $block) {
        if (!is_singular(['ts_work', 'ts_doc', 'ts_dojo', 'ts_board_post'])) return $content;
        $txt = trim(wp_strip_all_tags($content));
        return preg_match('/^(執筆者|カテゴリ|投稿者|タグ)[:：]?$/u', $txt) ? '' : $content;
    }

    /** トップ: 作品 (親投稿) をリロード毎ランダムで見せる (ユーザ裁定 2026-08-30 —
     *  閉架アーカイブに新着順は不向き。定額ホストなので RAND() の負荷は許容)。
     *  「今日の一作」等のセクションは Phase 4 の front-page テンプレートで足す。 */
    public static function front_page_query($q): void {
        if (is_admin() || !$q->is_main_query() || !$q->is_home()) return;
        $q->set('post_type', 'ts_work');
        $q->set('post_parent', 0);            // 話 (子投稿) は出さない
        $q->set('posts_per_page', 12);
        $q->set('orderby', 'rand');
        $q->set('ignore_sticky_posts', true);
    }

    /** トップはキャッシュさせない — 前段 nginx がシャッフルを固定しないように */
    public static function nocache_home(): void {
        if (is_home() && !is_admin()) nocache_headers();
    }

    public static function register(): void {
        // ---- 投稿タイプ --------------------------------------------------
        register_post_type('ts_work', [
            'labels' => ['name' => '作品', 'singular_name' => '作品'],
            'public' => true, 'hierarchical' => true, 'has_archive' => false,
            'menu_icon' => 'dashicons-book',
            'supports' => ['title', 'editor', 'excerpt', 'custom-fields', 'page-attributes', 'thumbnail'],
            // URL 基底はユーザ裁定 (2026-08-30) で /novel/ — 旧館の URL 語彙 (ts.novels.jp/novel/…) と揃える
            'rewrite' => ['slug' => 'novel', 'with_front' => false, 'hierarchical' => true],
            'show_in_rest' => true, // 管理画面のエディタ用 (運用上の編集は catalog 経由が正)
        ]);
        register_post_type('ts_doc', [
            'labels' => ['name' => '運営文書', 'singular_name' => '運営文書'],
            'public' => true, 'hierarchical' => false, 'has_archive' => false,
            'menu_icon' => 'dashicons-media-document',
            'supports' => ['title', 'editor', 'custom-fields'],
            'rewrite' => ['slug' => 'docs', 'with_front' => false],
            'show_in_rest' => true,
        ]);
        register_post_type('ts_dojo', [
            'labels' => ['name' => 'ストーリー道場', 'singular_name' => '道場作品'],
            'public' => true, 'hierarchical' => false, 'has_archive' => true,
            'menu_icon' => 'dashicons-format-chat',
            'supports' => ['title', 'editor', 'custom-fields', 'comments'],
            'rewrite' => ['slug' => 'dojo', 'with_front' => false],
            'show_in_rest' => true,
        ]);
        register_post_type('ts_board_post', [
            'labels' => ['name' => '感想板の投稿', 'singular_name' => '感想板の投稿'],
            'public' => true, 'hierarchical' => false, 'has_archive' => false,
            'exclude_from_search' => true, 'menu_icon' => 'dashicons-testimonial',
            'supports' => ['title', 'editor', 'custom-fields'],
            'rewrite' => ['slug' => 'boards', 'with_front' => false],
            'show_in_rest' => false,
        ]);

        // ---- タクソノミー ------------------------------------------------
        $tax = function (string $name, string $slug, string $label, array $extra = []) {
            register_taxonomy($name, ['ts_work', 'ts_dojo', 'ts_board_post'], array_merge([
                'labels' => ['name' => $label], 'public' => true, 'hierarchical' => false,
                'show_admin_column' => true, 'show_in_rest' => true,
                'rewrite' => ['slug' => $slug, 'with_front' => false],
            ], $extra));
        };
        $tax('ts_author', 'authors', '作者');       // コアの author_base=/author/ と衝突しない複数形
        $tax('ts_genre', 'genre', 'ジャンル');
        $tax('ts_type', 'type', '種別');
        $tax('ts_keyword', 'keyword', 'キーワード');
        $tax('ts_world', 'world', '共有世界');
        register_taxonomy('ts_corpus', ['ts_work'], [   // 収蔵区分: 絞込用・URL は持たせない
            'labels' => ['name' => '収蔵区分'], 'public' => false,
            'show_ui' => true, 'show_admin_column' => true, 'show_in_rest' => true,
            'rewrite' => false,
        ]);

        // ---- 代表的な post meta (書誌カード・テーマが読む分) --------------
        foreach (['_ts_episode_id', '_ts_work_slug', '_ts_kind', '_ts_source_path', '_ts_corpus',
                  '_ts_pub_date_raw', '_ts_orig_url', '_ts_annex_url', '_ts_annex_yays_url',
                  '_ts_kansou_slug', '_ts_kansou_annex_url', '_ts_arasuji', '_ts_author_comment',
                  '_ts_suisen', '_ts_catalog_ref'] as $key) {
            register_post_meta('ts_work', $key, ['type' => 'string', 'single' => true,
                'show_in_rest' => false, 'auth_callback' => '__return_false']);
        }
    }

    /** コメント・ピンバックは全投稿タイプで閉鎖 (ts_dojo のアーカイブコメントは表示のみ) */
    public static function no_comments($open, $post_id) {
        $type = get_post_type($post_id);
        if (in_array($type, ['ts_work', 'ts_doc', 'ts_dojo', 'ts_board_post'], true)) return false;
        return $open;
    }

    /** 恒久 noindex 層: /boards/ /dojo/ (v1.5 — 公開ゲートではなく第三者のプライバシー配慮) */
    public static function noindex_boards(): void {
        if (is_singular(['ts_board_post', 'ts_dojo']) || is_post_type_archive('ts_dojo')) {
            echo '<meta name="robots" content="noindex, follow">' . "\n";
        }
    }
}

TS_Library::boot();

require __DIR__ . '/includes/render.php';
require __DIR__ . '/includes/commands.php';
