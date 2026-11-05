<?php
/**
 * `wp ts role-sync` — 運営委員ロール (specs/S116.md) を S116 のロールの表どおりに冪等に適用する。
 * 望む姿と差分の計算は TS_Role_Plan (WordPress を使わない)。ここは add_role / add_cap / remove_cap を呼ぶだけ。
 * 本番 DB のロール定義に書くコード (高品位なモデルとエフォートの所管)。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Role_Sync_Command {

    /**
     * S116 のロールの表どおりに add_role / add_cap / remove_cap を冪等に適用する。
     *
     * ## OPTIONS
     * [--dry-run] : 何が変わるかだけ表示する
     */
    public function role_sync($args, $assoc) {
        $dry = isset($assoc['dry-run']);
        $wp_roles = wp_roles();
        $plan = TS_Role_Plan::plan($wp_roles->roles, TS_Role_Plan::spec());
        $spec = TS_Role_Plan::spec();
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
        WP_CLI::line(TS_Role_Plan::summary($plan) . ($dry ? ' (dry-run)' : ''));
        if ($missing) WP_CLI::error('role-sync: 足すべきロールが無い', false);
        else WP_CLI::success($dry ? 'role-sync: dry-run (何も変えていない)' : 'role-sync 完了');
    }
}
