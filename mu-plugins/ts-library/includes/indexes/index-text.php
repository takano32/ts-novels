<?php
/**
 * 索引ページ群 (T10.3) の種類・題名・並びの規則。WordPress を使わない。
 *
 * ルーティングは includes/indexes/index-routing.php、HTML は index-render.php、見た目はテーマ (ts-index.php / ts-year.php)。
 * ここの規則 (五十音行の並び・いまの一作の選び方) は scripts/wp/ts_library_test.php で試す。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

/** 索引の種類 (/index/<種類>/)。query var は公開なので、この許可リストで正規化する */
const TS_INDEX_KINDS = ['kana', 'timeline', 'bunrui', 'vocabulary', 'osusume', 'docs'];

final class TS_Index_Text {

    /** 索引の題名 (document_title_parts)。テーマの ts-index.php も同じ表を持つ */
    public const TITLES = ['kana' => '作者索引', 'timeline' => '年表', 'bunrui' => '分類索引',
                           'vocabulary' => 'キーワード索引', 'osusume' => 'オススメの環',
                           'docs' => '運営文書・資料'];

    /** 五十音行の並び (catalog の ts_yomi_group の実値) */
    public const KANA_ROWS = ['あ行', 'か行', 'さ行', 'た行', 'な行', 'は行', 'ま行', 'や行', 'ら行', 'わ行'];

    /** 運営文書・資料の区分の並びと見出し */
    public const DOC_SECTIONS = ['gallery' => 'ギャラリー', 'comittee' => '運営委員会', 'columns' => 'コラム',
                                 'dialy' => '運営日誌', 'prehistory' => '文庫前史 (1999)', 'notes' => '解題'];

    /** 年表に出す最初の年 (それより前の値は原本の日付の欠けなので出さない) */
    public const TIMELINE_FROM = 1990;

    /**
     * 作者索引の見出しの並び: 五十音行 → 欧文 A-Z → 想定外の値 (落とさない。最大掲載) → 他。
     * $present は実際に作者のいる見出し (ts_yomi_group の実値。空の値は '他' にしてから渡す)。
     * 見出しを 1 文字の 'あ' 等で決め打ちすると 303 名が無言で消えるので、実値で作る。
     */
    public static function kana_group_order(array $present): array {
        $set = array_fill_keys($present, true);
        $groups = [];
        foreach (self::KANA_ROWS as $g) if (isset($set[$g])) $groups[] = $g;
        $latin = array_filter(array_keys($set), fn($g) => (bool) preg_match('/^[A-Za-z]$/', (string) $g));
        sort($latin);
        $groups = array_merge($groups, $latin);
        foreach (array_keys($set) as $g) {   // 想定外の値も落とさない (最大掲載)
            if (!in_array($g, $groups, true) && $g !== '他') $groups[] = $g;
        }
        if (isset($set['他'])) $groups[] = '他';
        return $groups;
    }

    /**
     * いまの一作の添字 (時間シードの決定的選出 — 毎時交替。ユーザ裁定 2026-08-30: 日替わりは遅すぎる。
     * 同じ 1 時間のあいだは誰が見ても同じ作品 = 「いま読んでる人と同じ一作」の共有性は保つ)。
     * $ymdh は current_time('YmdH') の整数、$count は候補の数 (1 以上)。連続する時間で近い ID が続かないよう軽く攪拌する。
     */
    public static function pick_index(int $ymdh, int $count): int {
        $seed = $ymdh * 2654435761 % 2147483647;
        return $seed % $count;
    }
}
