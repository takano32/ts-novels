<?php
/**
 * `wp ts sync-docs` — 運営文書・前史・ギャラリー (ts_doc) を payloads-docs/ から同期する (T10.5、specs/S200.md)。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Sync_Docs_Command extends TS_Command_Base {

    /**
     * 運営文書・前史・ギャラリー (ts_doc) を payloads-docs/ から同期する (T10.5)。
     * scripts/wp/docs_build.py の出力を読む。冪等・手動編集保護。
     *
     * ## OPTIONS
     * [--repo=<path>] / [--overwrite-manual]
     */
    public function sync_docs($args, $assoc) {
        $root = $this->repo_root($assoc);
        $ow = isset($assoc['overwrite-manual']);
        $dir = "$root/payloads-docs";
        $manifest = $this->read_json("$dir/_manifest.json");
        $n = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'manual_skipped' => 0];
        foreach ($manifest as $slug => $m) {
            if (!is_file("$dir/$slug.html")) { WP_CLI::warning("payload なし: $slug"); continue; }
            $body = file_get_contents("$dir/$slug.html");
            $hash = md5($m['title'] . $m['section'] . $body);
            $post_id = $this->find_by_meta('ts_doc', '_ts_doc_slug', $slug);
            if ($post_id && get_post_meta($post_id, '_ts_import_hash', true) === $hash) { $n['skipped']++; continue; }
            if ($post_id && !$ow && $this->manually_edited($post_id)) {
                $n['manual_skipped']++; continue;
            }
            $postarr = ['post_type' => 'ts_doc', 'post_title' => $m['title'], 'post_name' => $slug,
                        'post_content' => $body, 'comment_status' => 'closed', 'ping_status' => 'closed'];
            if ($post_id) { $postarr['ID'] = $post_id; wp_update_post($postarr); $n['updated']++; }
            else { $postarr['post_status'] = 'publish'; $post_id = wp_insert_post($postarr); $n['created']++; }
            if (!$post_id || is_wp_error($post_id)) { WP_CLI::warning("ts_doc 投入失敗: $slug"); continue; }
            update_post_meta($post_id, '_ts_doc_slug', $slug);
            update_post_meta($post_id, '_ts_section', $m['section']);
            update_post_meta($post_id, '_ts_section_label', $m['section_label']);
            update_post_meta($post_id, '_ts_source_path', $m['source_path']);
            $this->stamp_import($post_id, $hash);
        }
        WP_CLI::success(sprintf('sync-docs: created=%d updated=%d skipped=%d manual_skipped=%d',
            $n['created'], $n['updated'], $n['skipped'], $n['manual_skipped']));
    }
}
