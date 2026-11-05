<?php
/**
 * `wp ts apply-takedown` / `wp ts export-takedown-urls` / `wp ts export-takedowns` — 取り下げの記録 (takedown/denylist.yml)
 * と WordPress の往復 (specs/S121.md・S119.md・S132.md)。
 *
 * denylist の読み書きは TS_Denylist、計画 (どの投稿を止め、どれを戻すか) は TS_Takedown_Plan、
 * 1 投稿の変化は TS_Visibility_Rules::takedown_change — どれも WordPress を使わない。ここは投稿の検索と書き込みだけ。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Takedown_Command extends TS_Command_Base {

    /**
     * denylist を適用して該当投稿を非公開 (private + `_ts_visibility=withdrawn`) にする
     * (WP サイトの非公開化。specs/S121.md・S117.md)。
     *
     * takedown/denylist.yml の `entries[].target.{kind,value}` を読む
     * (kind = work | episode | annex_path | author)。**work / author は
     * 子投稿 (話) まで必ず波及させる** — 親だけ止めても子の URL は生きるため。
     *  - 対象は private にし、`_ts_visibility=withdrawn`・`_ts_takedown_id`=エントリの id・
     *    `_ts_public_note`=public_note を揃える。既に private / draft でもメタを揃える (自己修復)。
     *    移行前の取り下げ (draft) は、これを流し直すだけで private に移る
     *  - `status: reinstated` のエントリの対象は印を消し、旧版 (`_ts_superseded_by`) でなければ publish に戻す
     *  - 読者への 410 は WordPress が返す (includes/visibility/visibility-hooks.php)。止めた URL は監査用に
     *    option `ts_takedown_urls` に残すが、.htaccess の生成には使わない (S119)
     * 出力: 「private 化 N / 復帰 M / 変更なし K」(S121)
     *
     * ## OPTIONS
     * [--repo=<path>] : リポジトリのルート
     * [--dry-run] : 何が変わるかだけ表示する
     */
    public function apply_takedown($args, $assoc) {
        global $wpdb;
        $root = $this->repo_root($assoc);
        $dry = isset($assoc['dry-run']);
        $pre = $dry ? '(dry-run) ' : '';
        $path = "$root/takedown/denylist.yml";
        if (!is_file($path)) WP_CLI::error("denylist がありません: $path");
        $entries = $this->denylist($path);

        $built = TS_Takedown_Plan::build($entries,
            function ($kind, $value) { return $this->takedown_targets($kind, $value); },
            function ($pid) { return (string) get_post_meta($pid, '_ts_takedown_id', true); },
            function ($msg) { WP_CLI::warning($msg); });
        $plan = $built['plan']; $reinstate = $built['reinstate'];

        $n = ['private' => 0, 'reinstate' => 0, 'none' => 0];
        $urls = [];
        foreach ($plan as $pid => $w) {
            $cur = ts_takedown_post_state($pid);
            $ch = TS_Visibility_Rules::takedown_change($cur, $w);
            if ($w !== null) $urls[] = ts_takedown_public_url($pid);
            $n[$ch['kind']]++;
            if ($ch['kind'] !== 'none') {
                $why = $w !== null ? $w['why'] : $reinstate[$pid];
                WP_CLI::line(TS_Takedown_Plan::describe($pre, $cur, $ch, $why, $pid, (string) get_the_title($pid)));
            }
            if ($dry) continue;
            if ($ch['status'] !== null) {
                wp_update_post(['ID' => $pid, 'post_status' => $ch['status']]);
                // 取り下げ・復帰は import の一部であって「管理画面での手動編集」ではない。
                // 記録を更新しないと post_modified が動いた分を手動編集と誤検知して
                // 以後の import がその投稿を触らなくなる (verify も NG になる)
                clean_post_cache($pid);
                update_post_meta($pid, '_ts_last_import_gmt', get_post_field('post_modified_gmt', $pid));
            } elseif ($this->manually_edited($pid)) {
                // 過去の取り下げが手動編集扱いで残っているのを自己修復する
                update_post_meta($pid, '_ts_last_import_gmt', get_post_field('post_modified_gmt', $pid));
            }
            foreach ($ch['set'] as $k => $v) update_post_meta($pid, $k, $v);
            foreach ($ch['delete'] as $k) delete_post_meta($pid, $k);
        }

        // denylist のどのエントリにも当たらない取り下げの印 (エントリを消した等) は変えずに知らせる
        $stray = array_diff(array_map('intval', $wpdb->get_col(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_ts_visibility' AND pm.meta_value = 'withdrawn' AND p.post_type = 'ts_work'")),
            array_keys($plan));
        // 取り下げフォームの申請によるもの (まだ denylist に書き写していない。S132) は分けて数える
        $by_request = [];
        foreach ($stray as $i => $pid) {
            if (strpos((string) get_post_meta($pid, '_ts_takedown_id', true), 'req:') === 0) {
                $by_request[] = $pid;
                unset($stray[$i]);
            }
        }
        if ($by_request) {
            WP_CLI::line('取り下げフォームの申請による取り下げ (denylist に未記載。export-takedowns で書き写す): '
                . count($by_request) . ' 件');
            foreach ($by_request as $pid) $urls[] = ts_takedown_public_url($pid);
        }
        if ($stray) {
            WP_CLI::warning('denylist に無い取り下げの投稿 (変更しない): ' . count($stray) . ' 件 (post '
                . implode(' ', array_slice($stray, 0, 10)) . (count($stray) > 10 ? ' …' : '') . ')');
        }

        if (!$dry) update_option('ts_takedown_urls', array_values(array_unique($urls)), false);
        WP_CLI::success(sprintf('apply-takedown: private 化 %d / 復帰 %d / 変更なし %d (対象 %d 件)%s',
            $n['private'], $n['reinstate'], $n['none'], count($plan), $dry ? ' (dry-run)' : ''));
    }

    /** kind/value → 止める投稿 ID の配列 (子投稿まで波及させる) */
    private function takedown_targets($kind, $value) {
        global $wpdb;
        $ids = [];
        if ($kind === 'work') {
            $pid = $this->find_by_meta('ts_work', '_ts_work_slug', $value);
            if ($pid) $ids[] = $pid;
        } elseif ($kind === 'episode') {
            // episodes.jsonl の source_path (アンカー分割話は "path#anchor")
            [$sp, $anchor] = TS_Denylist::episode_target($value);
            $rows = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_ts_source_path' AND meta_value=%s", $sp));
            foreach ($rows as $pid) {
                if ($anchor !== null && get_post_meta($pid, '_ts_source_anchor', true) !== $anchor) continue;
                $ids[] = (int) $pid;
            }
        } elseif ($kind === 'annex_path') {
            [$prefix, $wildcard] = TS_Denylist::annex_like($value);
            $rows = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_ts_source_path' AND meta_value LIKE %s",
                $wpdb->esc_like($prefix) . ($wildcard ? '%' : '')));
            foreach ($rows as $pid) $ids[] = (int) $pid;
        } elseif ($kind === 'author') {
            $term = get_term_by('slug', $value, 'ts_author');
            if ($term) {
                $ids = get_posts(['post_type' => 'ts_work', 'post_status' => 'any',
                                  'posts_per_page' => -1, 'fields' => 'ids',
                                  'tax_query' => [['taxonomy' => 'ts_author', 'field' => 'term_id',
                                                   'terms' => $term->term_id]]]);
            }
        }
        // 子孫まで波及 (親だけ止めても子の URL は生き続ける)
        $all = [];
        foreach ($ids as $pid) {
            $all[$pid] = true;
            foreach ($this->descendants($pid) as $cid) $all[$cid] = true;
        }
        return array_keys($all);
    }

    /** ts_work の子孫 (話) を再帰的に集める */
    private function descendants($post_id) {
        $out = [];
        $children = get_posts(['post_type' => 'ts_work', 'post_parent' => $post_id,
                               'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids']);
        foreach ($children as $cid) {
            $out[] = $cid;
            foreach ($this->descendants($cid) as $gid) $out[] = $gid;
        }
        return $out;
    }

    /**
     * 適用済み・復帰済みの取り下げの申請を denylist のエントリ (version 1) として標準出力に出す (S132)。
     * 連絡先・本文・証拠は出さない。id は次の S 番号から振る (repo の SPECS.md の「次は Sn」か --start)。
     * 振った番号ごとに specs/Sn.md (取り下げの記録の S) を作り、SPECS.md の一覧に足すこと。
     * denylist に書き写し済みの申請は出さない。書き写した後に復帰した申請は、既存のエントリの
     * status を reinstated に直すよう警告で知らせる (新しいエントリは作らない)。
     *
     * ## OPTIONS
     * [--since=<date>] : 適用 (復帰) がこの日 (YYYY-MM-DD) 以後の申請だけ
     * [--start=<n>] : 最初に振る S の番号 (省くと repo の SPECS.md の「次は Sn」)
     * [--repo=<path>] : リポジトリのルート
     */
    public function export_takedowns($args, $assoc) {
        $root = $this->repo_root($assoc);
        $start = isset($assoc['start']) ? (string) $assoc['start'] : null;
        $specs = $start === null ? @file_get_contents("$root/SPECS.md") : false;   // --start を省いたときだけ読む
        [$n, $err] = TS_Denylist::export_start($start, $specs === false ? null : $specs);
        if ($err !== null) WP_CLI::error($err);
        $since = $assoc['since'] ?? '';
        if (($err = TS_Denylist::since_error((string) $since)) !== null) WP_CLI::error($err);
        $done = $this->exported_requests($root);
        $ids = get_posts(['post_type' => TS_TAKEDOWN_TYPE, 'post_status' => 'any', 'posts_per_page' => -1,
                          'fields' => 'ids', 'no_found_rows' => true, 'orderby' => 'ID', 'order' => 'ASC',
                          'meta_key' => '_tr_status', 'meta_value' => ['applied', 'approved', 'reinstated'],
                          'meta_compare' => 'IN']);
        $yaml = ''; $all = [];
        foreach ($ids as $id) {
            $r = ts_takedown_get((int) $id);
            if ($since !== '' && wp_date('Y-m-d', $r['resolved_at']) < $since) continue;
            if (isset($done[$r['id']])) {
                foreach ($done[$r['id']] as $d) {
                    if ($r['status'] === 'reinstated' && $d['status'] !== 'reinstated') {
                        WP_CLI::warning("申請 {$r['id']} は復帰した: denylist の {$d['id']} の status を reinstated に直す");
                    }
                }
                continue;
            }
            if ($r['status'] === 'reinstated') continue; // 書き写す前に復帰したものは記録に残すだけで足りる
            $at = $r['confirmed_at'] ?: $r['received_at'];
            $targets = [];
            foreach ($r['targets'] as $t) {
                $pid = (int) ($t['post_id'] ?? 0);
                if (!$pid || get_post_type($pid) !== 'ts_work') continue;
                // work_slug があれば work、話だけを指した申請は episode (source_path、アンカー分割話は path#anchor)
                $target = TS_Denylist::target_of_post((string) get_post_meta($pid, '_ts_work_slug', true),
                    (string) get_post_meta($pid, '_ts_source_path', true), (string) get_post_meta($pid, '_ts_source_anchor', true));
                if ($target === null) { WP_CLI::warning("申請 {$r['id']}: post $pid の work_slug も source_path も無い"); continue; }
                $target['note'] = TS_Takedown_Text::public_note([wp_specialchars_decode(get_the_title($pid), ENT_QUOTES)],
                    $r['pen_name'], $r['allow_name'] === '1', (int) wp_date('Y', $at), (int) wp_date('n', $at));
                $targets[] = $target;
            }
            if (!$targets) { WP_CLI::warning("申請 {$r['id']}: 本サイトの作品に結びつく対象が無い (出さない)"); continue; }
            $out = TS_Denylist::export_yaml(['id' => $r['id'], 'status' => $r['status'], 'via' => $r['via'],
                'confirmed_date' => wp_date('Y-m-d', $at),
                'applied_date' => $r['resolved_at'] ? wp_date('Y-m-d', $r['resolved_at']) : ''], $targets, $n);
            $n += count($out['ids']);
            $yaml .= $out['yaml'];
            $all = array_merge($all, $out['ids']);
        }
        if ($yaml !== '') echo $yaml;
        // 案内は標準エラーに (標準出力は denylist にそのまま足せる YAML だけにする)
        fwrite(STDERR, ($all ? '振った id: ' . implode(' ', $all) . ' (specs/Sn.md を作り SPECS.md の一覧と「次は」を直す)'
                             : '書き写す申請はありません') . "\n");
    }

    /** 取り下げ済み URL のパスを 1 行 1 件で出す (監査用と、scripts/wp/htaccess_block.py --install の
     *  検証が読む。.htaccess の生成には使わない — 410 は WordPress が返す。specs/S119.md) */
    public function export_takedown_urls($args, $assoc) {
        foreach ((array) get_option('ts_takedown_urls', []) as $u) {
            WP_CLI::line($u);
        }
    }
}
