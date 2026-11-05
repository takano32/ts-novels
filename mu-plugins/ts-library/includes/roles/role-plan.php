<?php
/**
 * 運営委員ロール (specs/S116.md) の望む姿と差分の計算。WordPress を使わない。
 *
 *  - 望む姿は TS_Role_Plan::spec() の 1 か所だけに書く
 *  - 差分の計算 (plan) は純粋な関数で、scripts/wp/ts_library_test.php が単体で試す
 *  - 冪等: 2 回目は created=0 / updated=0 (S116 の受け入れ条件)
 *  - administrator には足すだけで、WP 既定の権限は外さない
 * 計画を WP のロールに当てるのは includes/commands/role-sync.php (`wp ts role-sync`)。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Role_Plan {

    /** ts_doc の投稿タイプが capability_type=ts_doc で使う基本権限 (map_meta_cap が読む分) */
    public static function ts_doc_caps(): array {
        return ['edit_ts_docs', 'edit_others_ts_docs', 'edit_published_ts_docs', 'edit_private_ts_docs',
                'publish_ts_docs', 'read_private_ts_docs',
                'delete_ts_docs', 'delete_others_ts_docs', 'delete_published_ts_docs', 'delete_private_ts_docs'];
    }

    /**
     * 望むロールの姿 (S116 のロールの表)。
     *  mode=exact: 権限をこの集合ちょうどにする (足りない分を足し、余分を外す)
     *  mode=add  : 足すだけ (administrator。WP 既定の権限には触れない)
     */
    public static function spec(): array {
        $committee = ['read', 'read_private_posts', 'ts_view_hidden', 'ts_manage_takedown'];
        $editor = array_merge($committee, [
            'edit_pages', 'edit_published_pages', 'edit_others_pages', 'publish_pages',
            'edit_ts_docs', 'edit_published_ts_docs', 'edit_others_ts_docs', 'publish_ts_docs',
        ]);
        return [
            'ts_committee' => ['name' => '運営委員', 'mode' => 'exact', 'caps' => $committee],
            'ts_committee_editor' => ['name' => '運営委員 (編集可)', 'mode' => 'exact', 'caps' => $editor],
            // ts_doc を capability_type=ts_doc に切り替えると WP 既定の administrator は ts_doc を
            // 編集できなくなるので、ts_doc の基本権限もここで足す (specs/S116.md の実装の記録)
            'administrator' => ['name' => null, 'mode' => 'add',
                                'caps' => array_merge(['ts_view_hidden', 'ts_manage_takedown'], self::ts_doc_caps())],
        ];
    }

    /**
     * 現在のロール ($current: slug => ['name' => str, 'capabilities' => [cap => bool]]) と
     * 望む姿 ($spec) から、ロールごとの操作を返す。
     * 返り値: slug => ['action' => created|updated|unchanged|missing, 'add' => [..], 'remove' => [..], 'rename' => str|null]
     *  missing は mode=add のロールが無いとき (administrator が無いのは異常なので作らずに知らせる)
     */
    public static function plan(array $current, array $spec): array {
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

    /** 計画の件数を S116 の出力の形にする */
    public static function summary(array $plan): string {
        $n = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
        foreach ($plan as $p) if (isset($n[$p['action']])) $n[$p['action']]++;
        return sprintf('role-sync: created=%d updated=%d unchanged=%d', $n['created'], $n['updated'], $n['unchanged']);
    }
}
