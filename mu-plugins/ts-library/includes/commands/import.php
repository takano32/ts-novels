<?php
/**
 * `wp ts import` — works / episodes を投入する (T8.2。冪等 upsert)。
 *
 *  - works を先に (親投稿)、話は子投稿。単発作品は親 (work 投稿) が本文ごと担い、episode の子投稿は作らない
 *  - 連載の親 work には子の term を合併して付ける (書庫に連載を出すため)
 *  - 本文は --bodies のペイロード (<episode_id>.html)。取り下げ対象は payload が無く _meta.json が 'takedown' (S76)
 * 索引と slug は TS_Work_Index、話のメタの写像は TS_Episode_Fields、term の解決は TS_Term_Resolver。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Import_Command extends TS_Command_Base {

    /** 割当ロジックを変えたら上げる (既存投稿が hash 不一致になり、reset なしでも収束し直す) */
    private const HASH_VER = 'v5'; // v5: _ts_episode_count / _ts_alias_paths を書くようになった

    private $vocab;             // TS_Vocabulary (terms.json)
    private $terms;             // TS_Term_Resolver
    private $body_status = [];  // episode_id => converted / raw-fallback / takedown (payloads/_meta.json)

    /**
     * works / episodes を投入する (冪等 upsert)。
     *
     * ## OPTIONS
     * [--repo=<path>] : リポジトリのルート
     * [--bodies=<dir>] : 本文ペイロード (<episode_id>.html の Gutenberg ブロック HTML)
     * [--limit=<n>] / [--author=<slug>] / [--dry-run] / [--overwrite-manual]
     */
    public function import($args, $assoc) {
        $root = $this->repo_root($assoc);
        $dry = isset($assoc['dry-run']);
        $overwrite_manual = isset($assoc['overwrite-manual']);
        $bodies = isset($assoc['bodies']) ? rtrim($assoc['bodies'], '/') : null;
        if ($bodies !== null && is_file("$bodies/_meta.json")) {
            $this->body_status = json_decode(file_get_contents("$bodies/_meta.json"), true) ?: [];
        }
        $limit = isset($assoc['limit']) ? (int) $assoc['limit'] : 0;
        $only_author = $assoc['author'] ?? null;

        $episodes = $this->read_jsonl("$root/catalog/episodes.jsonl");
        $works = $this->read_jsonl("$root/catalog/works.jsonl");
        $this->vocab = $this->vocabulary($root);
        $this->terms = new TS_Term_Resolver($this->vocab);
        $n = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'manual_skipped' => 0];
        $manual = [];

        // works を先に (親投稿)
        [$ep2work, $single_work, $work_author, $ep_order] = TS_Work_Index::index($works);
        $slugs = TS_Work_Index::child_slugs($episodes, $ep2work, $single_work, 'sanitize_title');

        $work_ids = [];
        foreach ($works as $w) {
            if ($only_author && ($w['author_slug'] ?? '') !== $only_author) continue;
            $work_ids[$w['work_slug']] = $this->upsert_work($w, $dry, $overwrite_manual, $n, $manual);
        }

        $done = 0;
        $parent_terms = []; // 連載の親 work へ子の term を合併して付ける (書庫に連載を出すため)
        $parent_values = TS_Work_Index::parent_term_values($episodes, $ep2work, $single_work, $this->vocab);
        foreach ($episodes as $i => $ep) {
            $wslug = $ep2work[$ep['episode_id']] ?? null;
            if ($only_author && !isset($work_ids[$wslug])) continue;
            if ($limit && $done >= $limit) break;
            if (!$dry && isset($parent_values[$i])) {   // 連載の話 (単発と work の無い話は入っていない)
                foreach ($parent_values[$i]['terms'] as $tax => $values) {
                    foreach ($this->terms->term_ids($tax, $values) as $tid) $parent_terms[$wslug][$tax][$tid] = 1;
                }
            }
            // 作者 term は作品の author_slug からのみ付く。kansou_slug は板の slug であって
            // 作者ではない (共有世界板で別人になる — S182 の実測: 共有世界の板を作者と誤認) ので、メタに置くだけ
            $author = $wslug !== null ? ($work_author[$wslug] ?? '') : '';
            // 単発作品は親 (work 投稿) が本文ごと担う — episode の子投稿は作らない
            if ($wslug !== null && isset($single_work[$wslug])) {
                $this->fill_single_work($work_ids[$wslug] ?? null, $ep, $bodies, $dry, $overwrite_manual, $n, $manual);
                $done++;
                continue;
            }
            $parent_id = $wslug !== null ? ($work_ids[$wslug] ?? null) : null;
            $this->upsert_episode($ep, $parent_id, $author, $slugs[$ep['episode_id']] ?? '',
                $ep_order[$ep['episode_id']] ?? 0, $bodies, $dry, $overwrite_manual, $n, $manual);
            $done++;
        }

        foreach ($parent_terms as $wslug => $taxes) {
            $pid = $work_ids[$wslug] ?? null;
            if (!$pid) continue;
            foreach ($taxes as $tax => $ids) {
                wp_set_object_terms($pid, array_map('intval', array_keys($ids)), $tax);
            }
        }

        // 追跡: いま投入した catalog のコミット
        $commit = trim((string) shell_exec("git -C " . escapeshellarg($root) . " rev-parse HEAD 2>/dev/null"));
        if (!$dry && $commit) {
            update_option('ts_catalog_commit', $commit, false);
            update_option('ts_last_import_at', gmdate('c'), false);
        }
        if ($manual) {
            update_option('ts_manual_edit_warnings', $manual, false);
            WP_CLI::warning('手動編集を検出して skip した投稿: ' . count($manual)
                . ' 件 (`wp ts verify` で一覧。上書きするには --overwrite-manual)');
        } elseif (!$dry) {
            delete_option('ts_manual_edit_warnings');
        }
        $term_missing = $this->terms->missing();
        if ($term_missing) {
            arsort($term_missing);
            if (!$dry) update_option('ts_term_warnings', $term_missing, false);
            WP_CLI::warning('term に解決できず割当を落とした値: ' . count($term_missing) . ' 種 (上位: '
                . implode(' ', array_slice(array_keys($term_missing), 0, 5)) . ')');
        } elseif (!$dry && !$limit && !$only_author) {
            delete_option('ts_term_warnings');
        }
        WP_CLI::success(sprintf('import: created=%d updated=%d skipped=%d manual_skipped=%d catalog=%s%s',
            $n['created'], $n['updated'], $n['skipped'], $n['manual_skipped'],
            substr($commit, 0, 12), $dry ? ' (dry-run)' : ''));
    }

    private function upsert_work($w, $dry, $ow, &$n, &$manual) {
        $hash = md5(self::HASH_VER . wp_json_encode($w));
        $post_id = $this->find_by_meta('ts_work', '_ts_work_slug', $w['work_slug']);
        if ($post_id && get_post_meta($post_id, '_ts_import_hash', true) === $hash) { $n['skipped']++; return $post_id; }
        if ($post_id && !$ow && $this->manually_edited($post_id)) {
            $n['manual_skipped']++; $manual[] = ['kind' => 'work', 'key' => $w['work_slug'],
                'modified' => get_post_field('post_modified_gmt', $post_id)];
            return $post_id;
        }
        $postarr = [
            'post_type' => 'ts_work', 'post_title' => $w['title'],
            'post_name' => $w['work_slug'], 'comment_status' => 'closed', 'ping_status' => 'closed',
        ];
        if ($w['first_date'] ?? null) $postarr['post_date'] = $w['first_date'] . ' 00:00:00';
        if ($dry) { $n[$post_id ? 'updated' : 'created']++; return $post_id; }
        if ($post_id) { $postarr['ID'] = $post_id; wp_update_post($postarr); }
        else { $postarr['post_status'] = 'publish'; $post_id = wp_insert_post($postarr); }
        if (!$post_id || is_wp_error($post_id)) { WP_CLI::warning('work 投入失敗: ' . $w['work_slug']); return null; }

        update_post_meta($post_id, '_ts_work_slug', $w['work_slug']);
        update_post_meta($post_id, '_ts_kind', 'work');
        update_post_meta($post_id, '_ts_needs_review', !empty($w['needs_review']) ? 1 : 0);
        update_post_meta($post_id, '_ts_title_pages', $w['title_pages'] ?? []);
        update_post_meta($post_id, '_ts_alias_paths', $w['alias_paths'] ?? []);
        // 話数はカードで出す (毎回 get_children を投げないため)
        update_post_meta($post_id, '_ts_episode_count', (int) ($w['episode_count'] ?? 0));
        wp_set_object_terms($post_id, $this->terms->term_ids('ts_author', [$w['author_slug'] ?? '']), 'ts_author');
        $this->stamp_import($post_id, $hash);
        $n[$postarr['ID'] ?? null ? 'updated' : 'created']++;
        return $post_id;
    }

    /** episode の共通メタ・タクソノミーを投稿に書く。$author_slug=null は作者 term に触らない
     *  (単発 work — upsert_work が付けた作者を消さないため。S182 の実測: 単発作品の作者欠落) */
    private function apply_episode_data($post_id, $ep, $author_slug) {
        foreach (TS_Episode_Fields::meta($ep) as $k => $v) {
            if ($v === null || $v === []) delete_post_meta($post_id, $k);
            else update_post_meta($post_id, $k, $v);
        }
        if ($author_slug !== null) {
            wp_set_object_terms($post_id, $this->terms->term_ids('ts_author', [$author_slug]), 'ts_author');
        }
        wp_set_object_terms($post_id, $this->terms->term_ids('ts_corpus', [$ep['corpus']]), 'ts_corpus');
        foreach ([['genre', 'ts_genre'], ['type', 'ts_type'], ['keywords', 'ts_keyword']] as [$field, $tax]) {
            wp_set_object_terms($post_id, $this->terms->term_ids($tax, $ep[$field] ?? []), $tax);
        }
        wp_set_object_terms($post_id,
            $this->terms->term_ids('ts_world', $this->vocab->episode_worlds($ep['episode_id'])), 'ts_world');
    }

    /** 本文 (選び方は TS_Episode_Fields::body)。ペイロードは取り下げ対象なら読まない */
    private function episode_body($ep, $bodies) {
        if ($bodies === null) return TS_Episode_Fields::body(null, null, false);   // メタのみ投入
        $status = $this->body_status[$ep['episode_id']] ?? null;
        $f = "$bodies/{$ep['episode_id']}.html";
        $payload = ($status !== 'takedown' && is_file($f)) ? file_get_contents($f) : null;
        return TS_Episode_Fields::body($status, $payload);
    }

    private function upsert_episode($ep, $parent_id, $author_slug, $post_name, $order, $bodies, $dry, $ow, &$n, &$manual) {
        $body = $this->episode_body($ep, $bodies);
        $hash = md5(self::HASH_VER . wp_json_encode($ep) . $post_name . $order
            . ($body === null ? '' : md5($body)));
        $post_id = $this->find_by_meta('ts_work', '_ts_episode_id', $ep['episode_id']);
        if ($post_id && get_post_meta($post_id, '_ts_import_hash', true) === $hash) { $n['skipped']++; return; }
        if ($post_id && !$ow && $this->manually_edited($post_id)) {
            $n['manual_skipped']++; $manual[] = ['kind' => 'episode', 'key' => $ep['episode_id'],
                'modified' => get_post_field('post_modified_gmt', $post_id)];
            return;
        }
        if ($dry) { $n[$post_id ? 'updated' : 'created']++; return; }
        $postarr = [
            'post_type' => 'ts_work', 'post_title' => $ep['title'] ?: $ep['episode_id'],
            'comment_status' => 'closed', 'ping_status' => 'closed',
        ];
        // URL slug は原ファイル名の語幹から決定的に (未指定だと題名由来のパーセントエンコードになる)
        if ($post_name !== '') $postarr['post_name'] = $post_name;
        if ($order) $postarr['menu_order'] = $order; // 話ナビの並び順 (works.jsonl の episodes 順)
        if ($parent_id) $postarr['post_parent'] = $parent_id;
        if ($ep['date'] ?? null) $postarr['post_date'] = $ep['date'] . ' 00:00:00';
        if ($body !== null) $postarr['post_content'] = $body;
        $creating = !$post_id;
        if ($post_id) { $postarr['ID'] = $post_id; wp_update_post($postarr); }
        else { $postarr['post_status'] = 'publish'; $post_id = wp_insert_post($postarr); }
        if (!$post_id || is_wp_error($post_id)) { WP_CLI::warning('episode 投入失敗: ' . $ep['episode_id']); return; }
        update_post_meta($post_id, '_ts_kind', 'episode');
        if ($body !== null) {
            update_post_meta($post_id, '_ts_body_status',
                $this->body_status[$ep['episode_id']] ?? 'placeholder');
        }
        $this->apply_episode_data($post_id, $ep, $author_slug);
        $this->stamp_import($post_id, $hash);
        $n[$creating ? 'created' : 'updated']++;
    }

    /** 単発作品: 親 work 投稿に episode の本文とメタを載せる。hash は work 側と別キー (S182 の実測: 冪等性の不具合) */
    private function fill_single_work($post_id, $ep, $bodies, $dry, $ow, &$n, &$manual) {
        if (!$post_id) return;
        $body = $this->episode_body($ep, $bodies);
        $hash = md5(self::HASH_VER . 'single:' . wp_json_encode($ep) . ($body === null ? '' : md5($body)));
        if (get_post_meta($post_id, '_ts_fill_hash', true) === $hash) { $n['skipped']++; return; }
        if (!$ow && $this->manually_edited($post_id)) {
            $n['manual_skipped']++; $manual[] = ['kind' => 'single-work', 'key' => $ep['episode_id'],
                'modified' => get_post_field('post_modified_gmt', $post_id)];
            return;
        }
        if ($dry) { $n['updated']++; return; }
        $up = ['ID' => $post_id];
        if ($ep['date'] ?? null) $up['post_date'] = $ep['date'] . ' 00:00:00';
        if ($body !== null) $up['post_content'] = $body;
        wp_update_post($up);
        update_post_meta($post_id, '_ts_kind', 'work'); // 単発は work=episode の 1 投稿
        if ($body !== null) {
            update_post_meta($post_id, '_ts_body_status',
                $this->body_status[$ep['episode_id']] ?? 'placeholder');
        }
        $this->apply_episode_data($post_id, $ep, null); // 作者は upsert_work が付けた分を保つ
        $this->stamp_import($post_id, $hash, '_ts_fill_hash');
        $n['updated']++;
    }
}
