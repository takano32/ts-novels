<?php
/**
 * 取り下げの申請と takedown/denylist.yml の突き合わせ (specs/S132.md)。WordPress に触る側。
 *
 * `wp ts verify` (書き写していない申請の数) と `wp ts export-takedowns` (書き写し済みの申請を出さない) が使う。
 * denylist の読み取りと申請 → 記録の結び方は TS_Denylist (WordPress を使わない)。読めなかったエントリの警告は
 * 返り値の warnings に入れて返し、出すのは呼び出し側 (WP_CLI::warning)。
 */

if (!defined('ABSPATH')) { exit; }

final class TS_Takedown_Export_State {

    /**
     * denylist に書き写した申請: ['done' => [申請の投稿 ID => [['id' => 記録の id, 'status' => …]]], 'warnings' => [文…]]。
     * reason の「(申請 <ID>)」で結ぶ。denylist.yml が無ければ空。
     */
    public static function exported_requests(string $root): array {
        $path = "$root/takedown/denylist.yml";
        if (!is_file($path)) return ['done' => [], 'warnings' => []];
        $r = TS_Denylist::parse($path);
        return ['done' => TS_Denylist::exported_requests($r['entries']), 'warnings' => $r['warnings']];
    }

    /** applied / approved の申請で denylist に対応するエントリが無いもの: ['ids' => [投稿 ID…], 'warnings' => [文…]] */
    public static function requests_not_in_denylist(string $root): array {
        if (!post_type_exists(TS_TAKEDOWN_TYPE)) return ['ids' => [], 'warnings' => []];
        $st = self::exported_requests($root);
        $done = $st['done'];
        $ids = get_posts(['post_type' => TS_TAKEDOWN_TYPE, 'post_status' => 'any', 'posts_per_page' => -1,
                          'fields' => 'ids', 'no_found_rows' => true,
                          'meta_key' => '_tr_status', 'meta_value' => ['applied', 'approved'], 'meta_compare' => 'IN']);
        return ['ids' => array_values(array_filter(array_map('intval', $ids), function ($id) use ($done) { return !isset($done[$id]); })),
                'warnings' => $st['warnings']];
    }
}
