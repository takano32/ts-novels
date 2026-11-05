<?php
/**
 * コンテンツモデルの登録と、サイト全体の振る舞い (specs/S21.md〜S23.md、改訂 S37・S42)。WordPress に触る側。
 *
 * 根幹コード (高品位なモデルとエフォートの所管)。何を登録するか (名前・URL 基底・引数) は includes/content-model.php の
 * TS_Content_Model にあり、ここは register_* を呼ぶだけ。ほかに:
 *  - S42: サイトは公開前提。恒久 noindex は /boards/ /dojo/ (第三者ハンドルが載る層) のみ
 *  - コメントは全面閉鎖 (rounge の 94% スパム化が根拠)
 *  - トップはリロード毎ランダム、書庫 (taxonomy アーカイブ) は作品単位・発表順
 * boot() はこのファイルの末尾で呼ぶ。pre_get_posts (優先度 10) は includes/indexes/index-routing.php より先に
 * 登録されている必要がある (索引・年別の一覧が posts_per_page と orderby を後から上書きするため)。
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
        add_action('pre_get_posts', [self::class, 'archive_query']);
        add_filter('render_block_core/heading', [self::class, 'home_heading'], 10, 2);
        add_filter('render_block_core/navigation-link', [self::class, 'drop_placeholder_nav'], 10, 2);
        add_filter('render_block_core/paragraph', [self::class, 'drop_empty_meta_row'], 10, 2);
        // 原文尊重: 表示時の字面変換をしない (T9.3 の QA の指摘。書誌カードで「原本と一致」と
        // 宣言している以上、`--`→ダーシ・`...`→省略記号・顔文字→絵文字 の変換は矛盾する)
        add_filter('run_wptexturize', '__return_false');
        add_filter('option_use_smilies', '__return_false');
        // 旧 URL 基底 (/works/ 等) からの 404 推測リダイレクトはしない (ユーザ裁定 2026-08-30。
        // 正規 URL は catalog が決める — 推測は誤誘導のもと)
        add_filter('do_redirect_guess_404_permalink', '__return_false');
    }

    /** 既定テーマ (TT5) の home テンプレが出す「ブログ」見出しを差し替える (T10 で正式トップに) */
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
     *  post_author もコアのカテゴリも使わないため値が空) を出さない (T10 で正式テーマに) */
    public static function drop_empty_meta_row($content, $block) {
        if (!is_singular(TS_Content_Model::POST_TYPES)) return $content;
        $txt = trim(wp_strip_all_tags($content));
        return preg_match('/^(執筆者|カテゴリ|投稿者|タグ)[:：]?$/u', $txt) ? '' : $content;
    }

    /** トップ: 作品 (親投稿) をリロード毎ランダムで見せる (ユーザ裁定 2026-08-30 —
     *  閉架アーカイブに新着順は不向き。定額ホストなので RAND() の負荷は許容)。
     *  「今日の一作」等のセクションは T10 の front-page テンプレートで足す。 */
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

    /** 書庫 (taxonomy アーカイブ) は作品単位・発表順で。検索は ts の各型を対象に */
    public static function archive_query($q): void {
        if (is_admin() || !$q->is_main_query()) return;
        if (!$q->is_search()
            && $q->is_tax(TS_Content_Model::PUBLIC_TAXONOMIES)) {
            $q->set('post_type', 'ts_work');
            $q->set('post_parent', 0);      // 話 (子投稿) でなく作品を並べる
            $q->set('posts_per_page', 60);
            $q->set('orderby', 'date');
            $q->set('order', 'ASC');        // 発表順
        }
        if ($q->is_search()) {
            $q->set('post_type', ['ts_work', 'ts_doc', 'ts_dojo']);
        }
    }

    /** 投稿タイプ・タクソノミー・代表的な post meta を登録する (中身は TS_Content_Model) */
    public static function register(): void {
        foreach (TS_Content_Model::post_types() as $type => $args) {
            register_post_type($type, $args);
        }
        foreach (TS_Content_Model::taxonomies() as $tax => $t) {
            register_taxonomy($tax, $t['object_types'], $t['args']);
        }
        foreach (TS_Content_Model::WORK_META_KEYS as $key) {
            register_post_meta('ts_work', $key, TS_Content_Model::work_meta_args());
        }
    }

    /** コメント・ピンバックは全投稿タイプで閉鎖 (ts_dojo のアーカイブコメントは表示のみ) */
    public static function no_comments($open, $post_id) {
        $type = get_post_type($post_id);
        if (in_array($type, TS_Content_Model::POST_TYPES, true)) return false;
        return $open;
    }

    /** 恒久 noindex 層: /boards/ /dojo/ (S42 — 公開ゲートではなく第三者のプライバシー配慮) */
    public static function noindex_boards(): void {
        if (is_singular(['ts_board_post', 'ts_dojo']) || is_post_type_archive('ts_dojo')) {
            echo '<meta name="robots" content="noindex, follow">' . "\n";
        }
    }
}

TS_Library::boot();
