<?php
/**
 * `wp ts export-pathmap` — 原パス (alias 含む) → WP サイトの URL の対応表を出力する (T8.2)。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Pathmap_Command extends TS_Command_Base {

    /** 原パス (alias 含む) → WP サイトの URL の対応表を出力する */
    public function export_pathmap($args, $assoc) {
        $map = [];
        $q = new WP_Query(['post_type' => 'ts_work', 'post_status' => 'any',
                           'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true]);
        foreach ($q->posts as $pid) {
            $url = get_permalink($pid);
            $src = get_post_meta($pid, '_ts_source_path', true);
            if ($src) $map[$src] = $url;
            $aliases = get_post_meta($pid, '_ts_alias_paths', true);
            if (is_array($aliases)) foreach ($aliases as $a) $map[$a] = $url;
        }
        WP_CLI::line(wp_json_encode($map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
