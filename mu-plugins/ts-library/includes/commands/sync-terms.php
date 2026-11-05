<?php
/**
 * `wp ts sync-terms` — タクソノミー term を catalog (terms.json・authors.json) から同期する (T8.2)。
 * term meta の `_ts_term_hash` で冪等 (同じ中身なら skipped)。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Sync_Terms_Command extends TS_Command_Base {

    /**
     * タクソノミー term を catalog から同期する。
     *
     * ## OPTIONS
     * [--repo=<path>] : リポジトリのルート
     * [--dry-run] : 書き込まない
     */
    public function sync_terms($args, $assoc) {
        $root = $this->repo_root($assoc);
        $dry = isset($assoc['dry-run']);
        $terms = $this->read_json("$root/catalog/terms.json");
        $authors = $this->authors($root);
        $n = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($terms['taxonomies'] as $tax => $spec) {
            if (!taxonomy_exists($tax)) WP_CLI::error("タクソノミー未登録: $tax (mu-plugin の登録を確認)");
            foreach ($spec['terms'] as $t) {
                $this->upsert_term($tax, $t['slug'], $t['name'],
                    ['description' => $t['description'] ?? ''],
                    ['ts_raw_variants' => $t['raw_variants'] ?? null,
                     'ts_core' => isset($t['core']) ? (int) $t['core'] : null,
                     'ts_count_catalog' => $t['count'] ?? null],
                    $dry, $n);
            }
        }
        foreach ($authors as $a) {
            $this->upsert_term('ts_author', $a['slug'], $a['display_name'], [],
                ['ts_display_variants' => $a['display_variants'] ?? null,
                 'ts_yomi_group' => $a['yomi_group'] ?? null,
                 'ts_kansou_slug' => $a['kansou_slug'] ?? null,
                 'ts_kansou_annex_url' => $a['kansou_annex_url'] ?? null,
                 'ts_homepage_wayback' => $a['homepage_wayback'] ?? null,
                 'ts_homepage_live' => $a['homepage_live'] ?? null,
                 'ts_active_links' => $a['active_links'] ?? null,
                 'ts_contact_status' => $a['contact_status'] ?? 'uncontacted'],
                $dry, $n);
        }
        foreach (self::PSEUDO_AUTHORS as $slug => $name) {
            $this->upsert_term('ts_author', $slug, $name, [], ['ts_pseudo' => 1], $dry, $n);
        }
        WP_CLI::success(sprintf('sync-terms: created=%d updated=%d skipped=%d%s',
            $n['created'], $n['updated'], $n['skipped'], $dry ? ' (dry-run)' : ''));
    }

    private function upsert_term($tax, $slug, $name, $core, $meta, $dry, &$n) {
        $existing = get_term_by('slug', $slug, $tax);
        $payload_hash = md5(wp_json_encode([$name, $core, $meta]));
        if ($existing) {
            if (get_term_meta($existing->term_id, '_ts_term_hash', true) === $payload_hash) {
                $n['skipped']++; return;
            }
            if (!$dry) {
                wp_update_term($existing->term_id, $tax, array_merge(['name' => $name], $core));
                $this->write_term_meta($existing->term_id, $meta, $payload_hash);
            }
            $n['updated']++;
        } else {
            if (!$dry) {
                $r = wp_insert_term($name, $tax, array_merge(['slug' => $slug], $core));
                if (is_wp_error($r)) { WP_CLI::warning("term 作成失敗 $tax/$slug: " . $r->get_error_message()); return; }
                $this->write_term_meta($r['term_id'], $meta, $payload_hash);
            }
            $n['created']++;
        }
    }

    private function write_term_meta($term_id, $meta, $hash) {
        foreach ($meta as $k => $v) {
            if ($v === null) { delete_term_meta($term_id, $k); continue; }
            update_term_meta($term_id, $k, $v);
        }
        update_term_meta($term_id, '_ts_term_hash', $hash);
    }
}
