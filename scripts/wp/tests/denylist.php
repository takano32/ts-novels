<?php
/**
 * takedown/denylist.yml の読み取りと書き出し (specs/S76.md・S121.md・S132.md)、/removed/ の行 (S131)、
 * apply-takedown の計画 (S121)。リポジトリの denylist.yml そのものも読む。scripts/wp/ts_library_test.php から読む。
 */

// ---------------------------------------------------------------- 読み取り
$r = TS_Denylist::parse("$root/takedown/denylist.yml");
$entries = $r['entries'];
$byid = [];
foreach ($entries as $e) $byid[$e['id']] = $e;
check('denylist の警告なし', $r['warnings'], []);
check('Marie 氏のエントリ', [$byid['S235']['kind'] ?? null, $byid['S235']['value'] ?? null, $byid['S235']['status'] ?? null],
      ['author', 'marie', 'applied']);
check('public_note を読む', $byid['S235']['public_note'] ?? null,
      'Ｍａｒｉｅさんの作品 (7 作) — 作者の申し出により掲載していません');
check('public_note の無いエントリ', $byid['S231']['public_note'] ?? null, '');
check('id は全部 S 番号 (S221)', array_values(array_filter(array_keys($byid), function ($id) { return !preg_match('/^S\.[0-9]+$/', $id); })), []);
check('kind は許された値だけ', array_values(array_diff(array_unique(array_column($entries, 'kind')), TS_Denylist::KINDS)), []);

$lines = explode("\n", "version: 1\nentries:\n  - id: S1\n    target:\n      kind: work\n      value: w\n"
    . "    status: reinstated\n    public_note: \"引用 # つき\"\n  - id: S2\n    target: { kind: episode, value: novel/a.htm#x }\n");
$e2 = TS_Denylist::parse_lines($lines)['entries'];
check('引用符を外す・復帰を読む', [$e2[0]['public_note'], $e2[0]['status']], ['引用 # つき', 'reinstated']);
check('フロー形式', [$e2[1]['kind'], $e2[1]['value']], ['episode', 'novel/a.htm#x']);
check('引用符なしの値のコメントを落とす (# はアンカーとして残す)',
      TS_Denylist::parse_lines(["entries:", "  - id: S3", "    target:", "      kind: episode", "      value: novel/b.htm#p2 # 注"])['entries'][0]['value'],
      'novel/b.htm#p2');
check('entries: [] は空', TS_Denylist::parse_lines(["version: 1", "entries: []"]), ['entries' => [], 'warnings' => []]);
check('不完全なエントリと未知の kind は捨てて知らせる',
      TS_Denylist::parse_lines(["entries:", "  - id: S4", "    target:", "      kind: work", "  - id: S5", "    target:", "      kind: photo", "      value: x"]),
      ['entries' => [], 'warnings' => ['denylist の不完全なエントリ: S4 (kind/value を確認)', '未知の kind: photo (S5)']]);
check('entries: の前の行は読まない', TS_Denylist::parse_lines(["  - id: S9", "    target: { kind: work, value: w }"])['entries'], []);
check('unquote', array_map([TS_Denylist::class, 'unquote'], ["'a''b'", '"q"', 'plain', "'"]), ["a'b", 'q', 'plain', "'"]);

// ---------------------------------------------------------------- /removed/ の行 (S131): 既存 9 件 + Marie
$posts = [];
foreach ($entries as $e) {
    if ($e['kind'] === 'annex_path') continue; // 写真は投稿が無い
    $posts[] = ['id' => $e['id'], 'note' => $e['public_note'], 'date' => $e['requested_at']];
    $posts[] = ['id' => $e['id'], 'note' => $e['public_note'], 'date' => $e['requested_at']]; // 子の話 (同じ記録)
}
$rows = TS_Takedown_Text::removed_rows($posts);
check('/removed/ は 10 行 (掲示の 1 行が無い記録は出さない)', count($rows), 10);
check('/removed/ の並び (新しい順、同じ日は記録の番号の順)', array_column($rows, 'id'),
      ['S235', 'S222', 'S223', 'S224', 'S225', 'S226', 'S227', 'S228', 'S229', 'S230']);
check('/removed/ の文言は public_note のまま', $rows[1]['note'], $byid['S222']['public_note']);
check('/removed/ は id の無い投稿を飛ばし、最初に見つけた note と date を使う',
      TS_Takedown_Text::removed_rows([['id' => '', 'note' => 'x'], ['id' => 'req:3', 'note' => '', 'date' => '2026-01-01'], ['id' => 'req:3', 'note' => 'n', 'date' => '2026-02-02']]),
      [['id' => 'req:3', 'note' => 'n', 'date' => '2026-01-01']]);

// ---------------------------------------------------------------- export-takedowns (S132) → 読み戻す
$out = TS_Denylist::export_yaml(['id' => 12, 'status' => 'approved', 'via' => 'takedown_form', 'confirmed_date' => '2026-11-01',
    'applied_date' => '2026-11-03'], [
    ['kind' => 'work', 'value' => 'a-foo', 'note' => "「It's: #1」(O'Neil さん) — 作者の申し出により掲載していません (2026 年 11 月)"],
    ['kind' => 'episode', 'value' => 'novel/200202/x.htm#p2', 'note' => '作者の申し出により 1 作品を掲載していません (2026 年 11 月)']], 241);
check('振った id', $out['ids'], ['S241', 'S242']);
check('連絡先などを出さない', preg_match('/contact|evidence|message|@/', $out['yaml']), 0);
$e3 = TS_Denylist::parse_lines(explode("\n", "version: 1\nentries:\n" . $out['yaml']));
check('書き出しを読み戻す (警告なし)', $e3['warnings'], []);
check('書き出しを読み戻す', array_map(function ($e) {
    return [$e['id'], $e['kind'], $e['value'], $e['status'], $e['requested_at'], $e['received_via'], $e['reason'], $e['public_note']];
}, $e3['entries']), [
    ['S241', 'work', 'a-foo', 'applied', '2026-11-01', 'takedown_form', '取り下げフォーム経由の申し出 (申請 12)',
     "「It's: #1」(O'Neil さん) — 作者の申し出により掲載していません (2026 年 11 月)"],
    ['S242', 'episode', 'novel/200202/x.htm#p2', 'applied', '2026-11-01', 'takedown_form', '取り下げフォーム経由の申し出 (申請 12)',
     '作者の申し出により 1 作品を掲載していません (2026 年 11 月)']]);
check('復帰の書き出し', strpos(TS_Denylist::export_yaml(['id' => 3, 'status' => 'reinstated', 'via' => 'email',
      'confirmed_date' => '2026-11-01', 'applied_date' => ''], [['kind' => 'work', 'value' => 'w', 'note' => 'n']], 9)['yaml'],
      "received_via: email\n    status: reinstated\n    applied:\n      wp_draft: null") !== false, true);
check('YAML の値', array_map([TS_Denylist::class, 'scalar'], ['a b', '- x', 'a: b', 'a #b', 'null', '', "'q'", null]),
      ['a b', "'- x'", "'a: b'", "'a #b'", "'null'", "''", "'''q'''", 'null']);
check('書き写した申請 → 記録 (reason の「(申請 n)」で結ぶ)', TS_Denylist::exported_requests(array_merge($e3['entries'], [$byid['S235']])),
      [12 => [['id' => 'S241', 'status' => 'applied'], ['id' => 'S242', 'status' => 'applied']]]);

// ---------------------------------------------------------------- apply-takedown の計画 (S121)
$targets_of = function ($kind, $value) {
    $t = ['work:w1' => [1, 2, 3], 'work:w2' => [4], 'author:a' => [1, 2, 3, 5], 'work:gone' => [], 'work:w9' => [9]];
    return $t["$kind:$value"] ?? [];
};
$takedown_id_of = function ($pid) { return $pid === 9 ? 'req:7' : ($pid === 4 ? 'S2' : ''); };
$warned = [];
$plan = TS_Takedown_Plan::build([
    ['id' => 'S1', 'kind' => 'work', 'value' => 'w1', 'status' => 'applied', 'public_note' => 'n1', 'requested_at' => '2026-01-01'],
    ['id' => 'S2', 'kind' => 'work', 'value' => 'w2', 'status' => 'reinstated', 'public_note' => '', 'requested_at' => ''],
    ['id' => 'S3', 'kind' => 'author', 'value' => 'a', 'status' => 'reinstated'],
    ['id' => 'S4', 'kind' => 'work', 'value' => 'gone', 'status' => 'applied'],
    ['id' => 'S5', 'kind' => 'work', 'value' => 'w9', 'status' => 'reinstated'],
], $targets_of, $takedown_id_of, function ($m) use (&$warned) { $warned[] = $m; });
check('該当投稿の無いエントリを知らせる', $warned, ['該当投稿なし: S4 work=gone']);
check('取り下げの計画 (id・note・date・why)', $plan['plan'][1], ['id' => 'S1', 'note' => 'n1', 'date' => '2026-01-01', 'why' => 'S1 work=w1']);
check('取り下げ中のエントリが復帰より優先 (1〜3 は S3 の復帰で戻さない)', array_keys(array_filter($plan['plan'], 'is_null')), [4, 5]);
check('復帰の理由', $plan['reinstate'], [4 => 'S2 work=w2', 5 => 'S3 author=a']);
check('フォームの申請 (req:) で取り下げ中の投稿は denylist の復帰では戻さない', isset($plan['plan'][9]), false);
check('計画は取り下げ → 復帰の順', array_keys($plan['plan']), [1, 2, 3, 4, 5]);

$cur = ['status' => 'publish', 'visibility' => '', 'takedown_id' => '', 'public_note' => '', 'takedown_date' => '', 'superseded_by' => ''];
$ch = TS_Visibility_Rules::takedown_change($cur, ['id' => 'S1', 'note' => 'n1', 'date' => '']);
check('変化の表示 (private 化)', TS_Takedown_Plan::describe('(dry-run) ', $cur, $ch, 'S1 work=w1', 1, '題'),
      '(dry-run) private 化: S1 work=w1 → post 1 題 (publish → private・メタ _ts_visibility=withdrawn _ts_takedown_id=S1 _ts_public_note=n1)');
$cur2 = array_merge($cur, ['status' => 'private', 'visibility' => 'withdrawn', 'takedown_id' => 'S1', 'public_note' => 'n1']);
check('変化の表示 (復帰)', TS_Takedown_Plan::describe('', $cur2, TS_Visibility_Rules::takedown_change($cur2, null), 'S2 work=w2', 4, 'T'),
      '復帰: S2 work=w2 → post 4 T (private → publish・メタ -_ts_takedown_id -_ts_public_note -_ts_visibility)');
check('変化の表示 (メタだけ)', TS_Takedown_Plan::describe('', $cur2, TS_Visibility_Rules::takedown_change($cur2, ['id' => 'S1', 'note' => '']), 'S1 work=w1', 1, 'T'),
      'private 化: S1 work=w1 → post 1 T (private のまま・メタ -_ts_public_note)');
