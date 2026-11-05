<?php
/**
 * `wp ts import` の upsert の分岐 (T45.1.16.1)。WordPress を使わない。
 *
 * work・話・単発作品の 3 か所 (includes/commands/import.php の upsert_work・upsert_episode・fill_single_work) が同じ順で判断する:
 * 投稿があって hash が同じなら skipped → 手動編集があり --overwrite-manual が無ければ manual_skipped → --dry-run なら書かずに数えるだけ →
 * 投稿があれば update、無ければ create。集計 (created / updated / skipped / manual_skipped) と書く内容は呼び出し側に残す。
 * 3 か所の違いは呼び出し側の引数にある: 単発作品の fill は親 work が必ずあるので $exists は true (dry-run でも updated に数える)、
 * hash のキーは `_ts_fill_hash`。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Import_Decision {

    /**
     * 1 投稿の upsert をどうするか: skipped・manual_skipped・dry・create・update のどれか。
     *  $exists          : 投稿がある (find_by_meta で見つかった)
     *  $hash_same       : 投稿に記録した hash (`_ts_import_hash`。単発作品の fill は `_ts_fill_hash`) が今回の hash と同じ
     *  $manually_edited : 手動編集の検出 (TS_Command_Base::manually_edited)。DB を読むので callable で受け、分割前と同じく
     *                     「投稿があり、hash が違い、--overwrite-manual が無い」ときだけ呼ぶ
     *  $overwrite       : --overwrite-manual
     *  $dry             : --dry-run。dry のときは呼び出し側が $exists で updated / created に数える
     */
    public static function decide(bool $exists, bool $hash_same, callable $manually_edited, bool $overwrite, bool $dry): string {
        if ($exists && $hash_same) return 'skipped';
        if ($exists && !$overwrite && $manually_edited()) return 'manual_skipped';
        if ($dry) return 'dry';
        return $exists ? 'update' : 'create';
    }
}
