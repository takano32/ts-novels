<?php
/**
 * mu-plugins/ts-library の WordPress を使わない部分の単体の試験 (T.23.3)。
 *
 *   docker run --rm -v "$PWD":/w -w /w php:8.2-cli php scripts/wp/ts_library_test.php
 *
 * 試すもの: ロールの差分と冪等 (S.116)・可視性の判定と帯 (S.117・S.118)・
 * apply-takedown の 1 投稿の変化 (S.121)・denylist の読み取り (public_note を含む)。
 * 失敗が 1 件でもあれば終了コード 1。
 */

define('TS_LIBRARY_TEST', true);
$root = dirname(__DIR__, 2);
require "$root/mu-plugins/ts-library/includes/roles.php";
require "$root/mu-plugins/ts-library/includes/visibility.php";

$fail = 0; $pass = 0;
function check($name, $got, $want) {
    global $fail, $pass;
    if ($got === $want) { $pass++; return; }
    $fail++;
    echo "NG $name\n  got:  " . var_export($got, true) . "\n  want: " . var_export($want, true) . "\n";
}

// ---------------------------------------------------------------- ロール (S.116)
$spec = ts_library_role_spec();
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

$plan1 = ts_library_role_plan($roles, $spec);
check('1 回目の集計', ts_library_role_summary($plan1), 'role-sync: created=2 updated=1 unchanged=0');
check('運営委員の権限', $plan1['ts_committee']['add'],
      ['read', 'read_private_posts', 'ts_view_hidden', 'ts_manage_takedown']);
check('administrator は足すだけ', $plan1['administrator']['remove'], []);
check('administrator に ts_doc の権限', in_array('edit_others_ts_docs', $plan1['administrator']['add'], true), true);
check('運営委員 (編集可) に ts_work の権限が無い',
      array_values(array_filter($plan1['ts_committee_editor']['add'],
          function ($c) { return strpos($c, 'post') !== false && $c !== 'read_private_posts'; })), []);
$roles2 = apply_plan($roles, $plan1, $spec);
check('2 回目は created=0 / updated=0', ts_library_role_summary(ts_library_role_plan($roles2, $spec)),
      'role-sync: created=0 updated=0 unchanged=3');
check('editor には触れない', $roles2['editor'], $roles['editor']);
// 余分な権限・拒否・表示名の違いは exact のロールで直す
$roles3 = $roles2;
$roles3['ts_committee']['capabilities']['edit_posts'] = true;
$roles3['ts_committee']['capabilities']['ts_view_hidden'] = false;
$roles3['ts_committee_editor']['name'] = '古い名前';
$roles3['administrator']['capabilities']['ts_view_hidden'] = false;
$p3 = ts_library_role_plan($roles3, $spec);
check('余分を外す', $p3['ts_committee']['remove'], ['edit_posts']);
check('拒否を許可に', $p3['ts_committee']['add'], ['ts_view_hidden']);
check('表示名を直す', $p3['ts_committee_editor']['rename'], '運営委員 (編集可)');
check('administrator の拒否も許可に', $p3['administrator']['add'], ['ts_view_hidden']);
check('直した後は変更なし', ts_library_role_summary(ts_library_role_plan(apply_plan($roles3, $p3, $spec), $spec)),
      'role-sync: created=0 updated=0 unchanged=3');
check('administrator が無いときは作らない', ts_library_role_plan([], $spec)['administrator']['action'], 'missing');

// ---------------------------------------------------------------- 要求パス (S.118 の解決手順)
check('作品', ts_visibility_page_path('novel/marie-foo'), 'marie-foo');
check('話', ts_visibility_page_path('novel/marie-foo/ep01/'), 'marie-foo/ep01');
check('エンコード', ts_visibility_page_path('novel/a%20b/c'), 'a b/c');
check('/novel/ の外', ts_visibility_page_path('about'), null);
check('/novel/ だけ', ts_visibility_page_path('novel'), null);
check('前方一致しない', ts_visibility_page_path('novels/x'), null);

// ---------------------------------------------------------------- 応答の判定 (S.118 の表)
$cases = [
    // [status, visibility, 運営委員か, 期待]
    ['publish', '', false, 'pass'], ['publish', '', true, 'pass'],
    ['private', 'withdrawn', false, 'gone'], ['private', 'withdrawn', true, 'show_hidden'],
    ['private', 'superseded', false, 'not_found'], ['private', 'superseded', true, 'show_hidden'],
    ['private', '', false, 'not_found'], ['private', '', true, 'show_hidden'],
    ['draft', 'withdrawn', false, 'gone'], ['draft', '', false, 'not_found'], ['draft', 'withdrawn', true, 'pass'],
    [null, '', false, 'pass'],
];
foreach ($cases as [$st, $vis, $c, $want]) {
    check("decide $st/$vis/" . ($c ? '運営委員' : '読者'), ts_visibility_decide($st, $vis, $c), $want);
}

// ---------------------------------------------------------------- 帯 (S.118)
check('帯 取り下げ (S)', ts_visibility_banner_html('withdrawn', 'S.235', '2001', ''),
      '<p class="ts-hidden-banner" role="note">取り下げ済み — 運営委員にのみ表示しています</p>');
check('帯 取り下げ (申請)', ts_visibility_banner_html('withdrawn', 'req:12', '2001', ''),
      '<p class="ts-hidden-banner" role="note">取り下げ済み — 運営委員にのみ表示しています (申請 12)</p>');
check('帯 旧版', ts_visibility_banner_html('superseded', '', '2002', 'https://novels.xwp.jp/novel/a/?x=1&y=2'),
      '<p class="ts-hidden-banner" role="note">旧版 (2002 年の掲載) — 運営委員にのみ表示しています'
      . ' → <a href="https://novels.xwp.jp/novel/a/?x=1&amp;y=2">新版へ</a></p>');
check('帯 理由なし', ts_visibility_banner_html('', '', '', ''),
      '<p class="ts-hidden-banner" role="note">運営委員にのみ表示しています</p>');

// ---------------------------------------------------------------- apply-takedown の変化 (S.121)
$w = ['id' => 'S.235', 'note' => 'Ｍａｒｉｅさんの作品 — 作者の申し出により掲載していません'];
check('draft → private (移行)', ts_visibility_takedown_change(['status' => 'draft'], $w), [
    'kind' => 'private', 'status' => 'private',
    'set' => ['_ts_visibility' => 'withdrawn', '_ts_takedown_id' => 'S.235', '_ts_public_note' => $w['note']],
    'delete' => []]);
check('publish → private', ts_visibility_takedown_change(['status' => 'publish'], $w)['status'], 'private');
$done = ['status' => 'private', 'visibility' => 'withdrawn', 'takedown_id' => 'S.235', 'public_note' => $w['note']];
check('揃っていれば変更なし', ts_visibility_takedown_change($done, $w),
      ['kind' => 'none', 'status' => null, 'set' => [], 'delete' => []]);
check('メタだけ直す', ts_visibility_takedown_change(array_merge($done, ['takedown_id' => 'S.1']), $w),
      ['kind' => 'private', 'status' => null, 'set' => ['_ts_takedown_id' => 'S.235'], 'delete' => []]);
check('public_note が無いエントリは掲示のメタを消す',
      ts_visibility_takedown_change($done, ['id' => 'S.235', 'note' => '']),
      ['kind' => 'private', 'status' => null, 'set' => [], 'delete' => ['_ts_public_note']]);
check('旧版の投稿を取り下げると withdrawn が優先',
      ts_visibility_takedown_change(['status' => 'private', 'visibility' => 'superseded', 'superseded_by' => 'e1'],
          ['id' => 'S.9', 'note' => ''])['set'], ['_ts_visibility' => 'withdrawn', '_ts_takedown_id' => 'S.9']);
check('復帰 → publish', ts_visibility_takedown_change($done, null), [
    'kind' => 'reinstate', 'status' => 'publish', 'set' => [],
    'delete' => ['_ts_takedown_id', '_ts_public_note', '_ts_visibility']]);
check('復帰 (旧版でもある) → private のまま旧版に',
      ts_visibility_takedown_change(array_merge($done, ['superseded_by' => 'e9']), null), [
    'kind' => 'reinstate', 'status' => null, 'set' => ['_ts_visibility' => 'superseded'],
    'delete' => ['_ts_takedown_id', '_ts_public_note']]);
check('復帰 (移行前の draft)', ts_visibility_takedown_change(['status' => 'draft'], null),
      ['kind' => 'reinstate', 'status' => 'publish', 'set' => [], 'delete' => []]);
check('復帰の対象でも印が無ければ変更なし', ts_visibility_takedown_change(['status' => 'publish'], null)['kind'], 'none');
check('旧版だけの投稿は復帰で触らない',
      ts_visibility_takedown_change(['status' => 'private', 'visibility' => 'superseded', 'superseded_by' => 'e1'], null)['kind'],
      'none');

// ---------------------------------------------------------------- denylist の読み取り (commands.php)
define('WP_CLI', true);
class WP_CLI {
    public static $warnings = [];
    public static function add_command(...$a) {}
    public static function warning($m) { self::$warnings[] = $m; }
}
require "$root/mu-plugins/ts-library/includes/commands.php";
$m = new ReflectionMethod('TS_Command', 'parse_denylist');
$m->setAccessible(true);
$entries = $m->invoke(new TS_Command(), "$root/takedown/denylist.yml");
$byid = [];
foreach ($entries as $e) $byid[$e['id']] = $e;
check('denylist の警告なし', WP_CLI::$warnings, []);
check('Marie 氏のエントリ', [$byid['S.235']['kind'] ?? null, $byid['S.235']['value'] ?? null, $byid['S.235']['status'] ?? null],
      ['author', 'marie', 'applied']);
check('public_note を読む', $byid['S.235']['public_note'] ?? null,
      'Ｍａｒｉｅさんの作品 (7 作) — 作者の申し出により掲載していません');
check('public_note の無いエントリ', $byid['S.231']['public_note'] ?? null, '');
$tmp = tempnam(sys_get_temp_dir(), 'dl');
file_put_contents($tmp, "version: 1\nentries:\n  - id: S.1\n    target:\n      kind: work\n      value: w\n"
    . "    status: reinstated\n    public_note: \"引用 # つき\"\n  - id: S.2\n    target: { kind: episode, value: novel/a.htm#x }\n");
$e2 = $m->invoke(new TS_Command(), $tmp);
unlink($tmp);
check('引用符を外す・復帰を読む', [$e2[0]['public_note'], $e2[0]['status']], ['引用 # つき', 'reinstated']);
check('フロー形式', [$e2[1]['kind'], $e2[1]['value']], ['episode', 'novel/a.htm#x']);

echo "ts_library_test: pass=$pass fail=$fail\n";
exit($fail ? 1 : 0);
