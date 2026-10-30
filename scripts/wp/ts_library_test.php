<?php
/**
 * mu-plugins/ts-library の WordPress を使わない部分の単体の試験 (T.23.3)。
 *
 *   docker run --rm -v "$PWD":/w -w /w php:8.2-cli php scripts/wp/ts_library_test.php
 *
 * 試すもの: ロールの差分と冪等 (S.116)・可視性の判定と帯 (S.117・S.118)・
 * apply-takedown の 1 投稿の変化 (S.121)・denylist の読み取り (public_note を含む)・
 * 取り下げフォームの判定と文面 (S.124〜S.132。T.26.3): 入力の検証・スパム対策・状態の遷移・cron の判定・
 * /removed/ の行・denylist への書き出し・`wp ts reset` の対象外。
 * 失敗が 1 件でもあれば終了コード 1。
 */

define('TS_LIBRARY_TEST', true);
$root = dirname(__DIR__, 2);
require "$root/mu-plugins/ts-library/includes/roles.php";
require "$root/mu-plugins/ts-library/includes/visibility.php";
require "$root/mu-plugins/ts-library/includes/takedown-request.php";

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


// ---------------------------------------------------------------- 取り下げの日 (S.131 の並び順)
check('取り下げの日を書く', ts_visibility_takedown_change($done, array_merge($w, ['date' => '2026-10-26']))['set'],
      ['_ts_takedown_date' => '2026-10-26']);
check('復帰で取り下げの日も消す', ts_visibility_takedown_change(array_merge($done, ['takedown_date' => '2026-10-26']), null)['delete'],
      ['_ts_takedown_id', '_ts_public_note', '_ts_takedown_date', '_ts_visibility']);

// ---------------------------------------------------------------- 取り下げフォーム (S.124〜S.132)
$P = ts_takedown_params();
check('既定のパラメータ', [$P['confirm_ttl_days'], $P['auto_apply_days'], $P['remind_before_days'], $P['retention_days'],
      $P['require_confirm'], $P['notify_to'], $P['send_reject_mail'], $P['rate_limit']], [7, 10, 3, 365, true, '', true, 3]);

$H = 'novels.xwp.jp';
check('URL → パス', ts_takedown_target_path('https://novels.xwp.jp/novel/foo/ep01/', $H), 'foo/ep01');
check('ホストだけ大文字', ts_takedown_target_path('https://NOVELS.xwp.jp/novel/foo', $H), 'foo');
check('スキームを省いた URL', ts_takedown_target_path('novels.xwp.jp/novel/foo/', $H), 'foo');
check('パスだけ', ts_takedown_target_path('/novel/a%20b/', $H), 'a b');
check('他サイト', ts_takedown_target_path('https://example.com/novel/foo/', $H), null);
check('旧サイトの URL', ts_takedown_target_path('http://ts.novels.jp/novel/200202/x.html', $H), null);
check('/novel/ の外', ts_takedown_target_path('https://novels.xwp.jp/about/', $H), null);
check('javascript:', ts_takedown_target_path('javascript:alert(1)//novels.xwp.jp/novel/x', $H), null);
check('.. を含む', ts_takedown_target_path('/novel/../wp-admin/', $H), null);

$resolve = function ($path) { return $path === 'foo' ? ['post_id' => 11, 'work_slug' => 'a-foo'] : null; };
$ok_in = ['pen_name' => " 山田\x07 ", 'targets' => "https://novels.xwp.jp/novel/foo/\r\nhttps://example.com/x\n\nhttps://novels.xwp.jp/novel/foo/",
          'contact' => 'a@example.org', 'relation' => 'author', 'evidence' => "1\n2", 'message' => '', 'allow_name' => '1'];
$v = ts_takedown_validate($ok_in, $resolve, $H);
check('検証 通る', $v['errors'], []);
check('制御文字を除く', $v['data']['pen_name'], '山田');
check('対象 (重複を除き、他サイトは保存して結びつかない印)', $v['data']['targets'], [
    ['url' => 'https://novels.xwp.jp/novel/foo/', 'post_id' => 11, 'work_slug' => 'a-foo'],
    ['url' => 'https://example.com/x', 'post_id' => null, 'work_slug' => null]]);
check('改行は残す', $v['data']['evidence'], "1\n2");
check('名前の掲示', $v['data']['allow_name'], '1');
check('名前の掲示 (既定は出さない)', ts_takedown_validate(array_diff_key($ok_in, ['allow_name' => 1]), $resolve, $H)['data']['allow_name'], '0');
check('必須が無い', count(ts_takedown_validate([], $resolve, $H)['errors']), 4);
check('長すぎる', count(ts_takedown_validate(array_merge($ok_in, ['pen_name' => str_repeat('あ', 101),
      'evidence' => str_repeat('あ', 1001), 'message' => str_repeat('あ', 2001)]), $resolve, $H)['errors']), 3);
check('100 字ちょうどは通る', ts_takedown_validate(array_merge($ok_in, ['pen_name' => str_repeat('あ', 100)]), $resolve, $H)['errors'], []);
check('21 行', ts_takedown_validate(array_merge($ok_in, ['targets' => implode("\n", array_map(function ($i) { return "/novel/w$i/"; }, range(1, 21)))]), $resolve, $H)['errors'],
      ['対象作品の URL は 20 行までです。']);
check('メールの形', ts_takedown_validate(array_merge($ok_in, ['contact' => "a@example.org\nBcc: b@example.org"]), $resolve, $H)['errors'],
      ['連絡先のメールアドレスを正しく書いてください。']);
check('立場の値', ts_takedown_validate(array_merge($ok_in, ['relation' => 'admin']), $resolve, $H)['errors'], ['お立場を選んでください。']);
check('配列を渡されても落ちない', ts_takedown_validate(array_merge($ok_in, ['pen_name' => ['x']]), $resolve, $H)['errors'],
      ['当時のペンネームを書いてください。']);

$key = 'k';
$now = 1800000000;
$tv = ts_takedown_sign_time($now - 10, $key);
check('表示時刻の署名', ts_takedown_verify_time($tv, $key), $now - 10);
check('表示時刻の書き換え', ts_takedown_verify_time(($now - 100) . substr($tv, strpos($tv, '.')), $key), null);
check('表示時刻の形', ts_takedown_verify_time('abc', $key), null);
// S.136 の検証手順のスパム系 (他サイトの URL は上の「対象」で確かめた)
check('スパム: nonce 不一致 → 403', ts_takedown_gate(false, '', $now - 10, $now, 0, $P), 403);
check('スパム: honeypot 入り → 400', ts_takedown_gate(true, 'http://spam', $now - 10, $now, 0, $P), 400);
check('スパム: 3 秒未満 → 400', ts_takedown_gate(true, '', $now - 2, $now, 0, $P), 400);
check('スパム: 3 秒ちょうどは通る', ts_takedown_gate(true, '', $now - 3, $now, 0, $P), 0);
check('スパム: 表示時刻が壊れている → 400', ts_takedown_gate(true, '', null, $now, 0, $P), 400);
check('スパム: 1 時間 3 件目までは通る', ts_takedown_gate(true, '', $now - 10, $now, 2, $P), 0);
check('スパム: 同一 IP 1 時間 4 件目 → 429', ts_takedown_gate(true, '', $now - 10, $now, 3, $P), 429);

check('確認時刻 + 10 日', ts_takedown_confirm_times($now, $P), ['confirmed_at' => $now, 'auto_apply_at' => $now + 864000]);

$ops = [];
foreach (['unconfirmed', 'received', 'applied', 'approved', 'rejected', 'reinstated', 'expired'] as $st) {
    foreach (['approve', 'reject', 'reinstate', 'deadline'] as $op) {
        if (($n = ts_takedown_op_next($st, $op)) !== null) $ops[] = "$st:$op>$n";
    }
}
check('運営委員の操作の遷移 (S.126)', $ops, ['unconfirmed:reject>rejected', 'received:approve>approved',
      'received:reject>rejected', 'received:deadline>received', 'applied:reinstate>reinstated', 'approved:reinstate>reinstated']);

$d = 86400;
check('cron: 7 日で失効', ts_takedown_due('unconfirmed', ['received_at' => $now - 7 * $d], $now, $P), 'expire');
check('cron: 7 日未満は待つ', ts_takedown_due('unconfirmed', ['received_at' => $now - 7 * $d + 1], $now, $P), null);
check('cron: 3 日前にリマインド', ts_takedown_due('received', ['auto_apply_at' => $now + 3 * $d], $now, $P), 'remind');
check('cron: リマインドは 1 回', ts_takedown_due('received', ['auto_apply_at' => $now + 3 * $d, 'reminded_at' => $now - 5], $now, $P), null);
check('cron: 4 日前は何もしない', ts_takedown_due('received', ['auto_apply_at' => $now + 4 * $d], $now, $P), null);
check('cron: 時刻で自動適用', ts_takedown_due('received', ['auto_apply_at' => $now, 'reminded_at' => 0], $now, $P), 'apply');
check('cron: 却下したものは適用しない', ts_takedown_due('rejected', ['auto_apply_at' => $now - $d], $now, $P), null);
check('保持期間: 365 日で消す', ts_takedown_purge_due('applied', $now - 365 * $d, $now, $P), true);
check('保持期間: 待機中は消さない', ts_takedown_purge_due('received', $now - 400 * $d, $now, $P), false);
check('保持期間: 364 日は残す', ts_takedown_purge_due('expired', $now - 364 * $d, $now, $P), false);

check('掲示の 1 行 (名前を出す)', ts_takedown_public_note(['森の家'], '山田', true, 2026, 11),
      '「森の家」(山田さん) — 作者の申し出により掲載していません (2026 年 11 月)');
check('掲示の 1 行 (名前を出さない)', ts_takedown_public_note(['森の家'], '山田', false, 2026, 1),
      '作者の申し出により 1 作品を掲載していません (2026 年 1 月)');
check('掲示の 1 行 (複数)', ts_takedown_public_note(['a', 'b'], '山田', false, 2026, 1),
      '作者の申し出により 2 作品を掲載していません (2026 年 1 月)');

check('メールの受付 (確認リンク・有効 7 日)', strpos(ts_takedown_mail_text('receipt', ['site' => 'S', 'confirm_url' => 'https://x/?ts_confirm=ab', 'ttl_days' => 7])[1],
      "https://x/?ts_confirm=ab\n\nリンクの有効期限は 7 日です。") !== false, true);
$evil = ['site' => 'S', 'summary' => 'EVIL-TEXT', 'titles' => ['T'], 'id' => 1, 'when' => 'W', 'admin_url' => 'A'];
foreach (['receipt', 'applied_requester', 'rejected'] as $k) {
    check("依頼者宛 ($k) に依頼者の入力を入れない", strpos(implode("\n", ts_takedown_mail_text($k, $evil)), 'EVIL-TEXT'), false);
}
check('確認完了のメールに時刻', strpos(ts_takedown_mail_text('confirmed', $evil)[1], 'W に自動で非公開になります') !== false, true);
check('件名に入力を入れない', strpos(ts_takedown_mail_text('confirmed', $evil)[0], 'EVIL'), false);

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


// ---------------------------------------------------------------- /removed/ の行 (S.131): 既存 9 件 + Marie
$posts = [];
foreach ($entries as $e) {
    if ($e['kind'] === 'annex_path') continue; // 写真は投稿が無い
    $posts[] = ['id' => $e['id'], 'note' => $e['public_note'], 'date' => $e['requested_at']];
    $posts[] = ['id' => $e['id'], 'note' => $e['public_note'], 'date' => $e['requested_at']]; // 子の話 (同じ記録)
}
$rows = ts_takedown_removed_rows($posts);
check('/removed/ は 10 行 (掲示の 1 行が無い記録は出さない)', count($rows), 10);
check('/removed/ の並び (新しい順、同じ日は記録の番号の順)', array_column($rows, 'id'),
      ['S.235', 'S.222', 'S.223', 'S.224', 'S.225', 'S.226', 'S.227', 'S.228', 'S.229', 'S.230']);
check('/removed/ の文言は public_note のまま', $rows[1]['note'], $byid['S.222']['public_note']);

// ---------------------------------------------------------------- export-takedowns (S.132) → denylist の読み取りで読み戻す
$out = ts_takedown_export_yaml(['id' => 12, 'status' => 'approved', 'via' => 'takedown_form', 'confirmed_date' => '2026-11-01',
    'applied_date' => '2026-11-03'], [
    ['kind' => 'work', 'value' => 'a-foo', 'note' => "「It's: #1」(O'Neil さん) — 作者の申し出により掲載していません (2026 年 11 月)"],
    ['kind' => 'episode', 'value' => 'novel/200202/x.htm#p2', 'note' => '作者の申し出により 1 作品を掲載していません (2026 年 11 月)']], 241);
check('振った id', $out['ids'], ['S.241', 'S.242']);
check('連絡先などを出さない', preg_match('/contact|evidence|message|@/', $out['yaml']), 0);
$tmp = tempnam(sys_get_temp_dir(), 'dl');
file_put_contents($tmp, "version: 1\nentries:\n" . $out['yaml']);
WP_CLI::$warnings = [];
$e3 = $m->invoke(new TS_Command(), $tmp);
unlink($tmp);
check('書き出しを読み戻す (警告なし)', WP_CLI::$warnings, []);
check('書き出しを読み戻す', array_map(function ($e) {
    return [$e['id'], $e['kind'], $e['value'], $e['status'], $e['requested_at'], $e['received_via'], $e['reason'], $e['public_note']];
}, $e3), [
    ['S.241', 'work', 'a-foo', 'applied', '2026-11-01', 'takedown_form', '取り下げフォーム経由の申し出 (申請 12)',
     "「It's: #1」(O'Neil さん) — 作者の申し出により掲載していません (2026 年 11 月)"],
    ['S.242', 'episode', 'novel/200202/x.htm#p2', 'applied', '2026-11-01', 'takedown_form', '取り下げフォーム経由の申し出 (申請 12)',
     '作者の申し出により 1 作品を掲載していません (2026 年 11 月)']]);
check('復帰の書き出し', strpos(ts_takedown_export_yaml(['id' => 3, 'status' => 'reinstated', 'via' => 'email',
      'confirmed_date' => '2026-11-01', 'applied_date' => ''], [['kind' => 'work', 'value' => 'w', 'note' => 'n']], 9)['yaml'],
      "received_via: email\n    status: reinstated\n    applied:\n      wp_draft: null") !== false, true);
check('YAML の値', array_map('ts_takedown_yaml_scalar', ['a b', '- x', 'a: b', 'a #b', 'null', '', "'q'"]),
      ['a b', "'- x'", "'a: b'", "'a #b'", "'null'", "''", "'''q'''"]);

// ---------------------------------------------------------------- wp ts reset の対象外 (S.125)
$types = (new ReflectionClassConstant('TS_Command', 'TYPES'))->getValue();
check('reset の TYPES に申請を含まない', in_array(TS_TAKEDOWN_TYPE, $types, true), false);
check('reset の TYPES は ts の 4 種', $types, ['ts_work', 'ts_doc', 'ts_dojo', 'ts_board_post']);
check('reset の説明文に書く', strpos((new ReflectionMethod('TS_Command', 'reset'))->getDocComment(), '取り下げの申請 (ts_takedown_request) は消さない') !== false, true);

echo "ts_library_test: pass=$pass fail=$fail\n";
exit($fail ? 1 : 0);
