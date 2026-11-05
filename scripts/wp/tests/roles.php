<?php
/**
 * 運営委員ロールの差分と冪等 (specs/S116.md)。scripts/wp/ts_library_test.php から読む。
 */

$spec = TS_Role_Plan::spec();
$admin_default = ['name' => 'Administrator', 'capabilities' => ['read' => true, 'manage_options' => true,
                  'edit_posts' => true, 'edit_pages' => true]];
$roles = ['administrator' => $admin_default, 'editor' => ['name' => 'Editor', 'capabilities' => ['read' => true]]];

/** 計画を配列のロール定義に当てる (WP の add_role / add_cap / remove_cap と同じ結果) */
function apply_plan(array $roles, array $plan, array $spec): array {
    foreach ($plan as $slug => $p) {
        if ($p['action'] === 'created') {
            $roles[$slug] = ['name' => $spec[$slug]['name'], 'capabilities' => array_fill_keys($p['add'], true)];
        } elseif ($p['action'] === 'updated') {
            foreach ($p['add'] as $c) $roles[$slug]['capabilities'][$c] = true;
            foreach ($p['remove'] as $c) unset($roles[$slug]['capabilities'][$c]);
            if ($p['rename'] !== null) $roles[$slug]['name'] = $p['rename'];
        }
    }
    return $roles;
}

$plan1 = TS_Role_Plan::plan($roles, $spec);
check('1 回目の集計', TS_Role_Plan::summary($plan1), 'role-sync: created=2 updated=1 unchanged=0');
check('運営委員の権限', $plan1['ts_committee']['add'],
      ['read', 'read_private_posts', 'ts_view_hidden', 'ts_manage_takedown']);
check('administrator は足すだけ', $plan1['administrator']['remove'], []);
check('administrator に ts_doc の権限', in_array('edit_others_ts_docs', $plan1['administrator']['add'], true), true);
check('運営委員 (編集可) に ts_work の権限が無い',
      array_values(array_filter($plan1['ts_committee_editor']['add'],
          function ($c) { return strpos($c, 'post') !== false && $c !== 'read_private_posts'; })), []);
$roles2 = apply_plan($roles, $plan1, $spec);
check('2 回目は created=0 / updated=0', TS_Role_Plan::summary(TS_Role_Plan::plan($roles2, $spec)),
      'role-sync: created=0 updated=0 unchanged=3');
check('editor には触れない', $roles2['editor'], $roles['editor']);
// 余分な権限・拒否・表示名の違いは exact のロールで直す
$roles3 = $roles2;
$roles3['ts_committee']['capabilities']['edit_posts'] = true;
$roles3['ts_committee']['capabilities']['ts_view_hidden'] = false;
$roles3['ts_committee_editor']['name'] = '古い名前';
$roles3['administrator']['capabilities']['ts_view_hidden'] = false;
$p3 = TS_Role_Plan::plan($roles3, $spec);
check('余分を外す', $p3['ts_committee']['remove'], ['edit_posts']);
check('拒否を許可に', $p3['ts_committee']['add'], ['ts_view_hidden']);
check('表示名を直す', $p3['ts_committee_editor']['rename'], '運営委員 (編集可)');
check('administrator の拒否も許可に', $p3['administrator']['add'], ['ts_view_hidden']);
check('直した後は変更なし', TS_Role_Plan::summary(TS_Role_Plan::plan(apply_plan($roles3, $p3, $spec), $spec)),
      'role-sync: created=0 updated=0 unchanged=3');
check('administrator が無いときは作らない', TS_Role_Plan::plan([], $spec)['administrator']['action'], 'missing');
check('ts_doc の基本権限は 10 種 (map_meta_cap が読む分)', count(TS_Role_Plan::ts_doc_caps()), 10);
