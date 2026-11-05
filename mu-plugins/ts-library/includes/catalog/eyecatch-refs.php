<?php
/**
 * eyecatch (featured image) の再設定 (scripts/wp/eyecatch_restore.php、specs/S73.md・S189) の判断と文面。WordPress を使わない。
 *
 * catalog/reports/payload_build.json の eyecatch_refs (episode_id → 画像パスの配列) の 1 話から使う画像を選び、
 * 中身 (md5) が同じ既存の attachment を使い回すか新しく取り込むかを決める。ファイルと DB に触るのは
 * scripts/wp/eyecatch_restore.php (wp eval-file の中では mu-plugin が読まれている)。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Eyecatch_Refs {

    /**
     * 1 話の候補 → リポジトリの中の相対パス。候補が複数あるときは先頭 (payload の本文で最初に現れる画像) を使い、
     * 公開 URL の接頭辞 /assets/annex-img/ を外す。候補が無ければ null。
     */
    public static function source_rel(array $images): ?string {
        if (!array_key_exists(0, $images)) return null;
        return preg_replace('#^/assets/annex-img/#', '', (string) $images[0]);
    }

    /** 中身の md5 → 'reuse' (同じ中身の attachment がある) か 'import' (wp media import で作る) */
    public static function decide(string $md5, array $by_md5): string {
        return isset($by_md5[$md5]) ? 'reuse' : 'import';
    }

    /** 終わりの 1 行 (WP_CLI::success に渡す) */
    public static function summary(int $set, int $total, bool $dry): string {
        return sprintf('eyecatch: %d 話に設定%s (対象 %d 話)', $set, $dry ? ' (dry-run)' : '', $total);
    }
}
