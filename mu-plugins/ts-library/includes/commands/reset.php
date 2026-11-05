<?php
/**
 * `wp ts reset` — ts_* の投稿・term を全削除する (やり直し用。specs/S73.md の C)。
 * 消す集合は TS_Content_Model::POST_TYPES / TAXONOMIES (取り下げの申請 ts_takedown_request は入っていない。S125)。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Reset_Command extends TS_Command_Base {

    /**
     * ts_* の投稿・term を全削除する (やり直し用。specs/S73.md の C)。
     * 取り下げの申請 (ts_takedown_request) は消さない (運用の原本で、catalog から作り直せない。S125)。
     * ## OPTIONS
     * --yes : 確認なしで実行 (必須)
     */
    public function reset($args, $assoc) {
        if (!isset($assoc['yes'])) WP_CLI::error('全削除には --yes が必要です (事前に wp db export を)');
        $deleted = 0;
        foreach (TS_Content_Model::POST_TYPES as $type) {
            if (!post_type_exists($type)) continue;
            $ids = get_posts(['post_type' => $type, 'post_status' => 'any',
                              'numberposts' => -1, 'fields' => 'ids']);
            foreach ($ids as $id) { wp_delete_post($id, true); $deleted++; }
        }
        $terms_deleted = 0;
        foreach (TS_Content_Model::TAXONOMIES as $tax) {
            if (!taxonomy_exists($tax)) continue;
            foreach (get_terms(['taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids']) as $tid) {
                wp_delete_term($tid, $tax); $terms_deleted++;
            }
        }
        delete_option('ts_catalog_commit');
        delete_option('ts_manual_edit_warnings');
        WP_CLI::success("reset: 投稿 $deleted 件・term $terms_deleted 件を削除 (WP 本体設定は無変更)");
    }
}
