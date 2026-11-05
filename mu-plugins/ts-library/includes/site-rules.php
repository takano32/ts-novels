<?php
/**
 * サイト全体の振る舞いの値と規則 (specs/S21.md〜S23.md、改訂 S37・S42)。WordPress を使わない。
 *
 * トップと書庫と検索の一覧の問い合わせの値、既定テーマの見出しの差し替え、空のメタ行の判定をここに置き、
 * includes/site-hooks.php の TS_Library がフックの中で当てる (is_home などの判定と $q->set は向こう)。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Site_Rules {

    /** トップ: 作品 (親投稿) をリロード毎ランダムで 12 件 (ユーザ裁定 2026-08-30 — 閉架アーカイブに新着順は不向き。
     *  定額ホストなので RAND() の負荷は許容)。話 (子投稿) は出さない。$q->set にこの順で渡す */
    public const FRONT_PAGE_QUERY = ['post_type' => 'ts_work', 'post_parent' => 0, 'posts_per_page' => 12,
                                     'orderby' => 'rand', 'ignore_sticky_posts' => true];

    /** 書庫 (公開 taxonomy のアーカイブ): 話でなく作品を、発表順に 60 件 */
    public const ARCHIVE_QUERY = ['post_type' => 'ts_work', 'post_parent' => 0, 'posts_per_page' => 60,
                                  'orderby' => 'date', 'order' => 'ASC'];

    /** 検索の対象の投稿タイプ (感想板の投稿 ts_board_post は入れない) */
    public const SEARCH_POST_TYPES = ['ts_work', 'ts_doc', 'ts_dojo'];

    /** 既定テーマ (TT5) の home テンプレが出す「ブログ」見出しを「作品一覧」にする (トップのときだけ当てる) */
    public static function home_heading(string $content): string {
        return strpos($content, '>ブログ<') !== false ? str_replace('>ブログ<', '>作品一覧<', $content) : $content;
    }

    /** タグを外して前後の空白を除いた段落が、値の空のメタ行 (「執筆者: 」「カテゴリ: 」「投稿者：」「タグ」) か */
    public static function is_empty_meta_row(string $text): bool {
        return (bool) preg_match('/^(執筆者|カテゴリ|投稿者|タグ)[:：]?$/u', $text);
    }
}
