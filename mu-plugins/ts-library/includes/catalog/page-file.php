<?php
/**
 * 固定ページの原本 content/pages/<slug>.html の読み書きの形 (T10.5 の sync-pages、T27.2・S109 の export-pages)。
 * WordPress を使わない。
 *
 *  - 1 行目の <!-- title: … --> が題。2 行目の <!-- owner-authored: YYYY-MM-DD --> (所有者文責の注記。S109) は
 *    本文に入れず、書き戻しのときは既存ファイルのその行を保つ
 *  - 冪等の鍵 hash = md5(題 + 本文)。往復 (export → sync-pages) が skipped になるのが受け入れ条件
 *  - dry-run の差分の行数は LCS で数える
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Page_File {

    /** 固定ページの 2 行目に置く所有者文責の注記 `<!-- owner-authored: YYYY-MM-DD -->` (S109) */
    public const OWNER_LINE_RE = '/^<!--\s*owner-authored:\s*\d{4}-\d{2}-\d{2}\s*-->[ \t]*\R?/u';

    /** ファイルの中身 → ['title' => 題 (無ければ slug), 'body' => 投入する本文, 'hash' => 冪等の鍵] */
    public static function parse(string $html, string $slug): array {
        $title = preg_match('/<!--\s*title:\s*(.+?)\s*-->/u', $html, $m) ? $m[1] : $slug;
        $body = trim(preg_replace('/^<!--\s*title:.*?-->\s*/su', '', $html));
        $body = trim(preg_replace(self::OWNER_LINE_RE, '', $body)); // 所有者文責の注記は本文に入れない (S109)
        return ['title' => $title, 'body' => $body, 'hash' => md5($title . $body)];
    }

    /**
     * 書き戻すファイルの中身。1 行目は <!-- title: … -->、以降は post_content を無加工で。
     * 既存ファイル $old の 2 行目に owner-authored の注記があればその行を保つ。
     */
    public static function render(string $title, string $content, string $old): string {
        $owner = '';
        $old_lines = preg_split('/\R/u', $old);
        if (isset($old_lines[1]) && preg_match(self::OWNER_LINE_RE, $old_lines[1])) $owner = rtrim($old_lines[1]) . "\n";
        return '<!-- title: ' . $title . " -->\n" . $owner . rtrim($content, "\r\n") . "\n";
    }

    /** 2 つの文字列の行単位の差分の行数 [合計, 追加, 削除] (LCS で数える) */
    public static function diff_count(string $a, string $b): array {
        $x = $a === '' ? [] : preg_split('/\R/u', rtrim($a, "\r\n"));
        $y = $b === '' ? [] : preg_split('/\R/u', rtrim($b, "\r\n"));
        $m = count($x); $k = count($y);
        $prev = array_fill(0, $k + 1, 0);
        for ($i = 1; $i <= $m; $i++) {
            $cur = [0];
            for ($j = 1; $j <= $k; $j++) {
                $cur[$j] = $x[$i - 1] === $y[$j - 1] ? $prev[$j - 1] + 1 : max($prev[$j], $cur[$j - 1]);
            }
            $prev = $cur;
        }
        $common = $prev[$k];
        return [$m + $k - 2 * $common, $k - $common, $m - $common];
    }
}
