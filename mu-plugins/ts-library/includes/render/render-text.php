<?php
/**
 * 表示部品の文言と計算のうち WordPress を使わない部分 (specs/S41.md の書誌カード、S195.md のサイドバー)。
 *
 * 読了目安の分数・回収経路の人間可読表示・サイドバーの項目の表・一覧の作品カードの添え書き・トップの案内文・
 * 作品のページの表示の型 (素表示・目次・リーダー) をここに置き、scripts/wp/ts_library_test.php で試す。
 * HTML を出す側は includes/render/render-work.php・render-sidebar.php・render-list.php とテーマ (themes/ts-bunko)。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Render_Text {

    /** 読了目安を出す本文の最少文字数と、1 分あたりの文字数 */
    public const READING_MIN_CHARS = 200;
    public const CHARS_PER_MINUTE = 550;

    /** サイドバーの索引入口: [ts_index の種類, 表示名] (ユーザ裁定 2026-08-31 — 右側に置く。S195) */
    public const SIDEBAR_INDEXES = [
        ['kana', '作者索引'], ['timeline', '年表'], ['bunrui', '分類索引'],
        ['vocabulary', 'キーワード索引'],
        ['docs', '運営文書・資料'],
    ];

    /** サイドバーの「この文庫について」: [パス, 表示名]。「オーナー作品集」は仮の項目名 (specs/S104.md。T19.1 の判断で直す) */
    public const SIDEBAR_ABOUT = [
        ['/authors/yays/', 'オーナー作品集'],
        ['/about/', 'このサイトについて'], ['/archive/', '原本アーカイブ'],
        ['/takedown/', '掲載の取り下げ'],
    ];

    /**
     * 読了目安 (分)。$chars は本文の空白を除いた実文字数。短い本文 (200 字未満) は出さないので null。
     * 原本ファイルのバイト数で数えると画像だけの話が「読了 約754分」になる (実測 289 ページが破綻値だった) ので文字数で。
     */
    public static function reading_minutes(int $chars): ?int {
        if ($chars < self::READING_MIN_CHARS) return null;
        return max(1, (int) round($chars / self::CHARS_PER_MINUTE));
    }

    /** 回収経路の人間可読表示 (書誌カード)。provenance のメタが配列でなければ null */
    public static function provenance_label($prov): ?string {
        if (!is_array($prov)) return null;
        if (!empty($prov['route_label'])) return $prov['route_label'];
        $map = ['wayback' => 'Internet Archive Wayback Machine', 'mirror' => 'サイト原本のミラー',
                'author-repost' => '作者本人による再掲', 'megalodon' => 'ウェブ魚拓',
                'commoncrawl' => 'Common Crawl'];
        return $map[$prov['route'] ?? ($prov['method'] ?? '')] ?? ($prov['route'] ?? null);
    }

    /** 書誌カードの本文の注記 (本文が整形表示である旨の明示。設計 S41)。$status はメタ _ts_body_status の値。出さない status なら null */
    public static function body_note($status): ?string {
        if ($status === 'converted' || $status === 'converted-text') {
            return 'この本文は読みやすさのため組版を整えた表示です。文章そのものは原本と機械照合で一致しています。';
        }
        if ($status === 'raw-fallback') return 'この本文は原本の HTML をそのまま表示しています。';
        return null;
    }

    /**
     * ts_work の 1 ページの表示の型 (themes/ts-bunko/single.php): ts_work でない投稿 (運営文書など) は 'plain' (素の記事表示)、
     * 子を持つ作品 (連載の親) は 'toc' (目次)、話と単発は 'reader' (本文 + 話ナビ + 書誌カード)。$children は並べる子の数。
     * 'plain' は子の数に依らないので、子を数える前に 0 で呼んで決めてよい。
     */
    public static function reader_mode(string $post_type, int $children): string {
        if ($post_type !== 'ts_work') return 'plain';
        return $children > 0 ? 'toc' : 'reader';
    }

    /**
     * リーダーの本文の枠の class。$status はメタ _ts_body_status の値 (文字列でなければ null)。原本の HTML をそのまま
     * 表示する話 (raw-fallback) は 'is-raw' を足す (テーマはこのときだけ「原本配色」のボタンを出す)。
     */
    public static function reader_classes(?string $status): array {
        return $status === 'raw-fallback' ? ['ts-reader', 'is-raw'] : ['ts-reader'];
    }

    /** 作品ページ (連載の親) の目次の見出し。$n は並べる話の数 */
    public static function toc_heading(int $n): string {
        return '目次 (全' . $n . '話)';
    }

    /** トップと書庫のトップの案内文 (themes/ts-bunko の front-page.php と index.php)。$published は公開の ts_work の数 */
    public static function home_intro(int $published): string {
        return '開くたびに違う作品をお出しします。' . $published . ' 篇からの偶然の出会いをどうぞ。';
    }

    /**
     * 一覧の作品カードの添え書き (「作者　·　初出　·　全N話」の並び)。$author と $date はエスケープ済みの文字列か null (出さない)。
     * $episodes は話数 (話数のメタか、無ければ公開の子の数)。2 以上のときだけ「全N話」を出す (単発と 1 話きりは出さない)。
     */
    public static function workcard_bits(?string $author, ?string $date, int $episodes): array {
        $bits = [];
        if ($author !== null) $bits[] = $author;
        if ($date !== null) $bits[] = $date;
        if ($episodes > 1) $bits[] = '全' . $episodes . '話';
        return $bits;
    }
}
