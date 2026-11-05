<?php
/**
 * `wp ts verify` — 件数照合・orphan・語彙整合・taxonomy 割当数・メタの被覆・手動編集警告・取り下げフォームの
 * cron と denylist への書き写しを検査する (T8.2、S130・S132)。
 * 期待値は catalog から毎回再計算する (TS_Verify_Expect。importer と独立に数え直し、取り違えを検出する)。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Verify_Command extends TS_Command_Base {

    /** 件数照合・orphan・語彙整合・taxonomy 割当数・手動編集警告を検査する。
     *  期待値は catalog から毎回再計算する (importer と独立に数え直し、取り違えを検出する)。 */
    public function verify($args, $assoc) {
        global $wpdb;
        $root = $this->repo_root($assoc);
        $episodes = $this->read_jsonl("$root/catalog/episodes.jsonl");
        $works = $this->read_jsonl("$root/catalog/works.jsonl");
        $vocab = $this->vocabulary($root);
        $authors = $this->authors($root);
        $taxes = TS_Content_Model::TAXONOMIES;
        $expect = new TS_Verify_Expect($works, $episodes, $authors, TS_Vocabulary::PSEUDO_AUTHORS, $vocab, $taxes);
        $ok = true;

        // 1) 投稿数
        $p = $expect->post_count();
        $count = 0;
        foreach (wp_count_posts('ts_work') as $st => $c) if ($st !== 'trash') $count += (int) $c;
        WP_CLI::line(sprintf('投稿数: WP=%d 期待=%d (works %d + episodes %d - 単発 %d)',
            $count, $p['expect'], $p['works'], $p['episodes'], $p['singles']));
        if ($count !== $p['expect']) { $ok = false; WP_CLI::warning('件数不一致'); }

        // 2) orphan: 親が消えている/ts_work でない子投稿
        $orphans = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->posts} q ON p.post_parent = q.ID
             WHERE p.post_type = 'ts_work' AND p.post_status <> 'trash' AND p.post_parent <> 0
               AND (q.ID IS NULL OR q.post_type <> 'ts_work')");
        WP_CLI::line("orphan 子投稿: $orphans");
        if ($orphans) { $ok = false; WP_CLI::warning('orphan あり'); }

        // 3) 語彙整合: WP の term 集合 = catalog の語彙 (場当たり term ゼロ・未作成ゼロ)
        $vocab_sets = $expect->vocab_sets();
        foreach ($taxes as $tax) {
            $wp_slugs = get_terms(['taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'id=>slug']);
            if (is_wp_error($wp_slugs)) { $ok = false; WP_CLI::warning("$tax: get_terms 失敗"); continue; }
            $extra = array_diff($wp_slugs, array_keys($vocab_sets[$tax] ?? []));
            $missing = array_diff(array_keys($vocab_sets[$tax] ?? []), $wp_slugs);
            WP_CLI::line(sprintf('%s: WP=%d 語彙=%d 語彙外=%d 未作成=%d',
                $tax, count($wp_slugs), count($vocab_sets[$tax] ?? []), count($extra), count($missing)));
            if ($extra) { $ok = false; WP_CLI::warning("$tax 語彙外 term: "
                . implode(' ', array_slice(array_values($extra), 0, 8))); }
            if ($missing) { $ok = false; WP_CLI::warning("$tax 未作成 term: "
                . implode(' ', array_slice(array_values($missing), 0, 8))); }
        }

        // 4) 割当数: catalog からの期待値 vs DB の term_relationships 実数
        $expected = $expect->assignment_counts();
        $actual = array_fill_keys($taxes, 0);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT tt.taxonomy, COUNT(*) c FROM {$wpdb->term_relationships} tr
             JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             JOIN {$wpdb->posts} p ON p.ID = tr.object_id
             WHERE p.post_type = 'ts_work' AND p.post_status <> 'trash'
               AND tt.taxonomy IN (%s,%s,%s,%s,%s,%s) GROUP BY tt.taxonomy", $taxes));
        foreach ($rows as $r) $actual[$r->taxonomy] = (int) $r->c;
        foreach ($taxes as $tax) {
            WP_CLI::line(sprintf('割当 %s: WP=%d 期待=%d', $tax, $actual[$tax], $expected[$tax]));
            if ($actual[$tax] !== $expected[$tax]) { $ok = false; WP_CLI::warning("$tax の割当数不一致"); }
        }

        // 4b) 書誌カード用メタの被覆 (T9.4): catalog の非空数 = DB の meta 行数
        $cnt = $expect->meta_coverage();
        foreach (TS_Verify_Expect::META_FIELDS as $mk => $f) {
            $actual = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = %s AND p.post_type = 'ts_work' AND p.post_status <> 'trash'",
                $mk));
            WP_CLI::line(sprintf('メタ %s: WP=%d 期待=%d', $mk, $actual, $cnt[$mk]));
            if ($actual !== $cnt[$mk]) { $ok = false; WP_CLI::warning("$mk の被覆不一致"); }
        }
        $rows = $wpdb->get_results(
            "SELECT pm.meta_value v, COUNT(*) c FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_ts_body_status' AND p.post_type = 'ts_work'
               AND p.post_status <> 'trash' GROUP BY pm.meta_value ORDER BY c DESC");
        $dist = [];
        foreach ($rows as $r) $dist[] = "{$r->v} {$r->c}";
        WP_CLI::line('本文 status: ' . ($dist ? implode(' / ', $dist) : '(未投入)'));

        // 5) import が解決できず落とした値 / 手動編集 skip
        $missing_vals = get_option('ts_term_warnings', []);
        if ($missing_vals) {
            $ok = false;
            WP_CLI::warning('term 未解決の値 (' . count($missing_vals) . ' 種): '
                . implode(' ', array_slice(array_keys($missing_vals), 0, 8)));
        }
        $manual = get_option('ts_manual_edit_warnings', []);
        if ($manual) {
            $ok = false;
            WP_CLI::warning('手動編集で skip 中の投稿 (' . count($manual) . ' 件):');
            foreach ($manual as $m) WP_CLI::line("  [{$m['kind']}] {$m['key']} (最終編集 {$m['modified']})");
        }
        // 6) 取り下げフォーム (S130・S132)。どちらも警告だけで、verify を NG にはしない
        $tick = get_option('ts_takedown_last_tick');
        if (!is_array($tick) || empty($tick['time'])) {
            WP_CLI::warning('ts_takedown_tick がまだ一度も動いていない (S130)');
        } else {
            WP_CLI::line('ts_takedown_tick の最終実行: ' . wp_date('Y-m-d H:i', (int) $tick['time'])
                . ($tick['error'] !== '' ? ' (失敗: ' . $tick['error'] . ')' : ''));
            if (time() - (int) $tick['time'] > 86400) WP_CLI::warning('ts_takedown_tick が 24 時間以上止まっている (S130)');
            if ($tick['error'] !== '') WP_CLI::warning('ts_takedown_tick の最後の実行が失敗している');
        }
        $st = TS_Takedown_Export_State::requests_not_in_denylist($root);
        $this->warn($st['warnings']);
        $missing = $st['ids'];
        WP_CLI::line('適用済みの申請で denylist に無いもの: ' . count($missing) . ' 件');
        if ($missing) WP_CLI::warning('denylist に書き写していない申請: ' . implode(' ', array_slice($missing, 0, 10))
            . ' (wp ts export-takedowns。S132)');

        WP_CLI::line('ts_catalog_commit: ' . get_option('ts_catalog_commit', '(未記録)'));
        WP_CLI::line('(冪等の確認は `wp ts import --dry-run` 2 回目が created=0 updated=0 になること)');
        $ok ? WP_CLI::success('verify OK') : WP_CLI::error('verify NG', false);
    }
}
