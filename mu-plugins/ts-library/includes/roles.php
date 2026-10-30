<?php
/**
 * 運営委員ロール (specs/S.116.md) と `wp ts role-sync`。
 *
 * 本番 DB のロール定義に書くコード (高品位なモデルとエフォートの所管)。設計の要点:
 *  - 望む姿は ts_library_role_spec() の 1 か所だけに書く
 *  - 差分の計算 (ts_library_role_plan) は WordPress を使わない純粋な関数にして、単体で試験する
 *    (scripts/wp/ts_library_test.php)
 *  - 冪等: 2 回目は created=0 / updated=0 (S.116 の受け入れ条件)
 *  - administrator には足すだけで、WP 既定の権限は外さない
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

/** ts_doc の投稿タイプが capability_type=ts_doc で使う基本権限 (map_meta_cap が読む分) */
function ts_library_ts_doc_caps(): array {
    return ['edit_ts_docs', 'edit_others_ts_docs', 'edit_published_ts_docs', 'edit_private_ts_docs',
            'publish_ts_docs', 'read_private_ts_docs',
            'delete_ts_docs', 'delete_others_ts_docs', 'delete_published_ts_docs', 'delete_private_ts_docs'];
}

/**
 * 望むロールの姿 (S.116 のロールの表)。
 *  mode=exact: 権限をこの集合ちょうどにする (足りない分を足し、余分を外す)
 *  mode=add  : 足すだけ (administrator。WP 既定の権限には触れない)
 */
function ts_library_role_spec(): array {
    $committee = ['read', 'read_private_posts', 'ts_view_hidden', 'ts_manage_takedown'];
    $editor = array_merge($committee, [
        'edit_pages', 'edit_published_pages', 'edit_others_pages', 'publish_pages',
        'edit_ts_docs', 'edit_published_ts_docs', 'edit_others_ts_docs', 'publish_ts_docs',
    ]);
    return [
        'ts_committee' => ['name' => '運営委員', 'mode' => 'exact', 'caps' => $committee],
        'ts_committee_editor' => ['name' => '運営委員 (編集可)', 'mode' => 'exact', 'caps' => $editor],
        // ts_doc を capability_type=ts_doc に切り替えると WP 既定の administrator は ts_doc を
        // 編集できなくなるので、ts_doc の基本権限もここで足す (specs/S.116.md の実装の記録)
        'administrator' => ['name' => null, 'mode' => 'add',
                            'caps' => array_merge(['ts_view_hidden', 'ts_manage_takedown'], ts_library_ts_doc_caps())],
    ];
}

/**
 * 現在のロール ($current: slug => ['name' => str, 'capabilities' => [cap => bool]]) と
 * 望む姿 ($spec) から、ロールごとの操作を返す (WordPress を使わない)。
 * 返り値: slug => ['action' => created|updated|unchanged|missing, 'add' => [..], 'remove' => [..], 'rename' => str|null]
 *  missing は mode=add のロールが無いとき (administrator が無いのは異常なので作らずに知らせる)
 */
function ts_library_role_plan(array $current, array $spec): array {
    $plan = [];
    foreach ($spec as $slug => $want) {
        $caps = array_values(array_unique($want['caps']));
        if (!isset($current[$slug])) {
            $plan[$slug] = $want['mode'] === 'add'
                ? ['action' => 'missing', 'add' => [], 'remove' => [], 'rename' => null]
                : ['action' => 'created', 'add' => $caps, 'remove' => [], 'rename' => null];
            continue;
        }
        $have = [];
        foreach ((array) ($current[$slug]['capabilities'] ?? []) as $cap => $granted) {
            if ($granted) $have[] = (string) $cap;
        }
        $add = array_values(array_diff($caps, $have));
        $remove = $want['mode'] === 'exact' ? array_values(array_diff($have, $caps)) : [];
        // 権限を false で持っている (明示の拒否) ものは「無い」扱いで足す。外す対象は true のものだけ
        $denied = [];
        foreach ((array) ($current[$slug]['capabilities'] ?? []) as $cap => $granted) {
            if (!$granted && $want['mode'] === 'exact' && !in_array((string) $cap, $caps, true)) $denied[] = (string) $cap;
        }
        $remove = array_values(array_unique(array_merge($remove, $denied)));
        $rename = ($want['name'] !== null && ($current[$slug]['name'] ?? null) !== $want['name']) ? $want['name'] : null;
        sort($add); sort($remove);
        $plan[$slug] = ['action' => ($add || $remove || $rename !== null) ? 'updated' : 'unchanged',
                        'add' => $add, 'remove' => $remove, 'rename' => $rename];
    }
    return $plan;
}

/** 計画の件数を S.116 の出力の形にする */
function ts_library_role_summary(array $plan): string {
    $n = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
    foreach ($plan as $p) if (isset($n[$p['action']])) $n[$p['action']]++;
    return sprintf('role-sync: created=%d updated=%d unchanged=%d', $n['created'], $n['updated'], $n['unchanged']);
}

if (defined('WP_CLI') && WP_CLI) {
    /**
     * S.116 のロールの表どおりに add_role / add_cap / remove_cap を冪等に適用する。
     *
     * ## OPTIONS
     * [--dry-run] : 何が変わるかだけ表示する
     */
    WP_CLI::add_command('ts role-sync', function ($args, $assoc) {
        $dry = isset($assoc['dry-run']);
        $wp_roles = wp_roles();
        $plan = ts_library_role_plan($wp_roles->roles, ts_library_role_spec());
        $spec = ts_library_role_spec();
        $missing = false;
        foreach ($plan as $slug => $p) {
            $pre = $dry ? '(dry-run) ' : '';
            if ($p['action'] === 'missing') {
                WP_CLI::warning("ロール $slug がありません (作らずに残します。サイトの設定を確認してください)");
                $missing = true;
                continue;
            }
            if ($p['action'] === 'unchanged') { WP_CLI::line("{$pre}$slug: 変更なし"); continue; }
            WP_CLI::line(sprintf('%s%s: %s add=[%s] remove=[%s]%s', $pre, $slug, $p['action'],
                implode(' ', $p['add']), implode(' ', $p['remove']),
                $p['rename'] !== null ? " 表示名→{$p['rename']}" : ''));
            if ($dry) continue;
            if ($p['action'] === 'created') {
                add_role($slug, $spec[$slug]['name'], array_fill_keys($p['add'], true));
                continue;
            }
            $role = get_role($slug);
            foreach ($p['add'] as $cap) $role->add_cap($cap, true);
            foreach ($p['remove'] as $cap) $role->remove_cap($cap);
            if ($p['rename'] !== null) {
                // 表示名を変える API が WP に無いので、ロール定義の option を直接直す
                $wp_roles = wp_roles();
                $wp_roles->roles[$slug]['name'] = $p['rename'];
                $wp_roles->role_names[$slug] = $p['rename'];
                if ($wp_roles->use_db) update_option($wp_roles->role_key, $wp_roles->roles);
            }
        }
        WP_CLI::line(ts_library_role_summary($plan) . ($dry ? ' (dry-run)' : ''));
        if ($missing) WP_CLI::error('role-sync: 足すべきロールが無い', false);
        else WP_CLI::success($dry ? 'role-sync: dry-run (何も変えていない)' : 'role-sync 完了');
    });
}
