<?php
/**
 * 取り下げの申請の非公開化と復帰 (specs/S127.md・S128.md) を WordPress の代わり (scripts/wp/tests/wp-stubs.php) の上で通して動かす
 * (T45.1.17.3)。期待値は分割前 (0e8c7712) の includes/takedown-request.php と includes/visibility.php を同じ代わりと同じ置き場
 * (wp-fixtures.php の ts_fixture_takedown_apply_scene) で動かした出力から取った (2026-11-05、scripts/wp/php_probe.sh、probe-takedown-apply.php)。
 * 実装の写しではない。対象と子孫の集め方 2 本の順 (T45.1.19.3) もここで見る。scripts/wp/ts_library_test.php から WP の表を読んだ後に読む。
 * 後半はショートコード 2 つ (フォーム [ts_takedown_form] と一覧 [ts_removed_list]) の HTML と、フォームの入力名と読む側の鍵 (T45.1.15.3)。
 * 最後に cron ts_takedown_tick と、フォームの送信 (ts_takedown_handle_post) と確認リンク (T45.6.4。流し方は wp-fixtures.php)。
 */

// ---------------------------------------------------------------- 非公開化 (S127)
$ids = ts_fixture_takedown_apply_scene();
$note = '「連続する月」「入れ子の月」(天野 月さん) — 作者の申し出により掲載していません (2026 年 11 月)';
$done = ts_takedown_apply($ids['request'], 'applied', 0, '');
check('適用: 非公開にした作品の題名 (対象の順。結びつかない URL は無視)', $done, ['連続する月', '入れ子の月']);
$s1 = ts_fixture_takedown_snapshot($ids);
check('適用: 投稿の状態 — 別の記録 (S9) で取り下げ中の話は触らない、draft は private に、旧版の孫は withdrawn が優先、import の記録は元からある投稿だけ更新', $s1['posts'], [
    'amano-tsuki-renzoku' => "private vis=withdrawn id=req:10 date=2026-11-05 note=$note sup= imp=2026-11-05 12:00:00",
    'ren01' => "private vis=withdrawn id=req:10 date=2026-11-05 note=$note sup= imp=2026-11-05 12:00:00",
    'ren02' => 'private vis=withdrawn id=S9 date=2026-01-01 note=S9 の掲示 sup= imp=',
    'ren03' => "private vis=withdrawn id=req:10 date=2026-11-05 note=$note sup= imp=",
    'amano-tsuki-iriko' => "private vis=withdrawn id=req:10 date=2026-11-05 note=$note sup= imp=",
    'iri01' => "private vis=withdrawn id=req:10 date=2026-11-05 note=$note sup= imp=",
    'iri02' => "private vis=withdrawn id=req:10 date=2026-11-05 note=$note sup= imp=",
    'iri02a' => "private vis=withdrawn id=req:10 date=2026-11-05 note=$note sup=iri02a-shinpan imp=",
    'amano-tsuki-tanpen' => 'publish vis= id= date= note= sup= imp=',
]);
check('適用: option ts_takedown_urls は既存の後に、対象 → 子孫 (日付の古い順) を対象ごとに足す', $s1['urls'], [
    '/novel/hoka-no-sakuhin/', '/novel/amano-tsuki-renzoku/ren02/',
    '/novel/amano-tsuki-renzoku/', '/novel/amano-tsuki-renzoku/ren01/', '/novel/amano-tsuki-renzoku/ren03/',
    '/novel/amano-tsuki-iriko/', '/novel/amano-tsuki-iriko/iri01/', '/novel/amano-tsuki-iriko/iri02/', '/novel/amano-tsuki-iriko/iri02/iri02a/']);
check('適用: 申請は applied、cron (by=0) が解決、記録 1 行', [$s1['request'], $s1['log']], [['applied', 0, '', true], ['applied/0/']]);
check('適用: メールは依頼者に 1 通、運営委員 (admin_email と ts_committee・ts_committee_editor) に 1 通ずつ。関係ないユーザには送らない',
      array_map(function ($m) { return [$m[0], $m[1]]; }, $s1['mails']), [
    ['amano@example.com', '[少年少女文庫] お申し出の作品を非公開にしました'],
    ['admin@example.com', '[少年少女文庫] 取り下げの申請 10 を適用しました'],
    ['iin@example.com', '[少年少女文庫] 取り下げの申請 10 を適用しました'],
    ['editor@example.com', '[少年少女文庫] 取り下げの申請 10 を適用しました']]);
check('適用: 依頼者へのメールの本文 (一覧に載せる旨)', $s1['mails'][0][2],
      "お申し出に基づき、次の作品を非公開にしました。\n  - 連続する月\n  - 入れ子の月\n\n掲載していない作品の一覧には、作品名とペンネームを載せています。\n\n-- \n少年少女文庫\n");
check('適用: 運営委員へのメールの本文 (結びつかなかった URL と申請の要約と管理画面の URL。3 通とも同じ)',
      [$s1['mails'][1][2], $s1['mails'][2][2] === $s1['mails'][1][2] && $s1['mails'][3][2] === $s1['mails'][1][2]], [
    "取り下げの申請 10 を適用し、次の作品を非公開にしました。\n  - 連続する月\n  - 入れ子の月\n\n"
    . "本サイトの作品に結びつかなかった URL があります (適用していません):\n  - https://novels.xwp.jp/novel/nai/\n\n"
    . "ペンネーム: 天野 月\n立場: 作者本人\n名前の掲示: 可\n対象:\n  - https://novels.xwp.jp/novel/amano-tsuki-renzoku/\n"
    . "  - https://novels.xwp.jp/novel/amano-tsuki-iriko/\n  - https://novels.xwp.jp/novel/nai/ (本サイトの作品に結びつかない)\n\n"
    . "管理画面:\nhttps://novels.xwp.jp/wp-admin/post.php?post=10&action=edit\n\n-- \n少年少女文庫\n", true]);

// ---------------------------------------------------------------- 復帰 (S128)
$left = ts_takedown_reinstate($ids['request'], 3, '復帰のメモ');
$s2 = ts_fixture_takedown_snapshot($ids);
check('復帰: 戻せなかった数 (別の記録 S9 で取り下げ中の話)', $left, 1);
check('復帰: この申請の印の投稿だけ戻す — 旧版の孫は superseded のまま private に、S9 の話はそのまま、import の記録は元からある投稿だけ更新', $s2['posts'], [
    'amano-tsuki-renzoku' => 'publish vis= id= date= note= sup= imp=2026-11-05 12:00:00',
    'ren01' => 'publish vis= id= date= note= sup= imp=2026-11-05 12:00:00',
    'ren02' => 'private vis=withdrawn id=S9 date=2026-01-01 note=S9 の掲示 sup= imp=',
    'ren03' => 'publish vis= id= date= note= sup= imp=',
    'amano-tsuki-iriko' => 'publish vis= id= date= note= sup= imp=',
    'iri01' => 'publish vis= id= date= note= sup= imp=',
    'iri02' => 'publish vis= id= date= note= sup= imp=',
    'iri02a' => 'private vis=superseded id= date= note= sup=iri02a-shinpan imp=',
    'amano-tsuki-tanpen' => 'publish vis= id= date= note= sup= imp=',
]);
check('復帰: option から戻した URL を外す (別の作品と S9 の話は残る)', $s2['urls'], ['/novel/hoka-no-sakuhin/', '/novel/amano-tsuki-renzoku/ren02/']);
check('復帰: 申請は reinstated、操作した運営委員とメモ、記録 2 行、メールは増えない', [$s2['request'], $s2['log'], count($s2['mails'])],
      [['reinstated', 3, '復帰のメモ', true], ['applied/0/', 'reinstated/3/復帰のメモ'], 4]);

// ---------------------------------------------------------------- 対象と子孫の集め方 2 本 (T45.1.19.3)。子の問い合わせは ts_takedown_child_ids で共有し、集める順はそれぞれ分割前のまま
$t = ts_fixture_takedown_targets();
check('子の問い合わせ (ts_takedown_child_ids): 状態を問わず、get_posts の既定の順 (日付の新しい順)', ts_takedown_child_ids($t['nested']), [$t['nep'][1], $t['nep'][0]]);
// 期待値は 0e8c7712 の ts_takedown_target_posts を同じ代わりで動かした順 (probe-takedown-apply.php の order)
check('申請の対象と子孫 (ts_takedown_target_posts): 対象ごとに、対象 → 子孫を stack で (get_posts の逆順 = 日付の古い順)。結びつかない URL は飛ばす',
      ts_takedown_target_posts(['targets' => [['post_id' => $t['nested']], ['post_id' => $t['single']], ['post_id' => null]]]),
      [$t['nested'], $t['nep'][0], $t['nep'][1], $t['grand'], $t['single']]);
// descendants と takedown_targets は分割前 (0e8c7712 の commands.php) と同じコード
$cmd = new TS_Takedown_Command();
$desc = new ReflectionMethod($cmd, 'descendants'); $desc->setAccessible(true);
$targets = new ReflectionMethod($cmd, 'takedown_targets'); $targets->setAccessible(true);
check('apply-takedown の子孫 (descendants): 再帰で、get_posts の順 (日付の新しい順) に深さ優先', $desc->invoke($cmd, $t['nested']), [$t['nep'][1], $t['grand'], $t['nep'][0]]);
check('apply-takedown の対象 (takedown_targets): 対象 → 子孫 (descendants の順)', $targets->invoke($cmd, 'work', 'amano-tsuki-iriko'),
      [$t['nested'], $t['nep'][1], $t['grand'], $t['nep'][0]]);
$a = ts_takedown_target_posts(['targets' => [['post_id' => $t['nested']]]]); $b = $targets->invoke($cmd, 'work', 'amano-tsuki-iriko');
sort($a); sort($b);
check('2 本の集め方は集合は同じで順が違う (子が 2 つ以上あると逆になる)', [$a === $b, ts_takedown_target_posts(['targets' => [['post_id' => $t['nested']]]]) === $targets->invoke($cmd, 'work', 'amano-tsuki-iriko')], [true, false]);
TS_WP_Stubs::reset();

// ---------------------------------------------------------------- ショートコード 2 つの HTML (S124・S131。T45.1.15.3)
// 期待値: 0e8c7712 の takedown-request.php、2026-11-05、probe-shortcodes.php。表示時刻の署名 (name="t") は time() に依るので伏せて比べ、
// 署名が今の時刻として読めることを別に見る。$same (鍵ごとに比べる) は先に読む render-html.php のもの
ts_fixture_removed(true);
$got = ['removed/0' => TS_WP_Stubs::shortcode('ts_removed_list')];
ts_fixture_removed();
$got['removed/n'] = TS_WP_Stubs::shortcode('ts_removed_list');
TS_WP_Stubs::reset();
$form = TS_WP_Stubs::shortcode('ts_takedown_form');
$got['form'] = preg_replace('/name="t" value="[^"]*"/', 'name="t" value="(表示時刻の署名)"', $form);
$same('ショートコード', $got, array (
  'removed/0' => '<p class="ts-removed-none">いま掲載していない作品はありません。</p>',
  'removed/n' => '<ul class="ts-removed-list"><li>「星の歌」— 2026年11月に取り下げ</li><li>「月の本」(天野 月) — 2026年10月に作者のお申し出により取り下げ</li><li>作品 &lt;題&gt; &amp; 続編 — 2026年1月に取り下げ</li></ul>',
  'form' => '<form class="ts-takedown-form" method="post" action="https://novels.xwp.jp/wp-admin/admin-post.php"><input type="hidden" name="action" value="ts_takedown"><input type="hidden" id="_wpnonce" name="_wpnonce" value="209e640e92" /><input type="hidden" name="t" value="(表示時刻の署名)"><p class="ts-tr-hp" aria-hidden="true"><label>この欄は空のままにしてください <input type="text" name="hp" value="" tabindex="-1" autocomplete="off"></label></p><p><label for="ts-tr-pen">当時のペンネーム (必須)</label><br><input type="text" id="ts-tr-pen" name="pen_name" required maxlength="100"></p><p><label for="ts-tr-targets">対象作品の URL (必須。1 行に 1 つ、20 行まで)</label><br><textarea id="ts-tr-targets" name="targets" rows="4" required placeholder="https://novels.xwp.jp/novel/…"></textarea></p><p><label for="ts-tr-contact">連絡先のメールアドレス (必須)</label><br><input type="email" id="ts-tr-contact" name="contact" required maxlength="254"></p><fieldset><legend>お立場 (必須)</legend><label><input type="radio" name="relation" value="author" required> 作者本人</label> <label><input type="radio" name="relation" value="heir"> 権利を継承した方</label> <label><input type="radio" name="relation" value="other"> その他</label></fieldset><p><label for="ts-tr-evidence">本人確認の手がかり (任意。1,000 字まで)</label><br><textarea id="ts-tr-evidence" name="evidence" rows="3" maxlength="1000"></textarea></p><p><label for="ts-tr-message">ご連絡事項 (任意。2,000 字まで)</label><br><textarea id="ts-tr-message" name="message" rows="4" maxlength="2000"></textarea></p><p><label><input type="checkbox" name="allow_name" value="1"> 掲載していない作品の一覧に、作品名とペンネームを載せてかまいません</label></p><p><button type="submit">送信する</button></p></form><style>.ts-tr-hp{position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden}</style>',
));
preg_match('/name="t" value="([^"]*)"/', $form, $m);
$t = TS_Takedown_Input::verify_time($m[1] ?? '', wp_salt('nonce'));
check('フォーム: 表示時刻の署名は今の時刻として読める', is_int($t) && abs($t - time()) <= 5, true);

// フォームの入力名 = 読む側の鍵。読む側は TS_Takedown_Input::validate ($s('…') と $in['…']) と ts_takedown_handle_post ($in['…'])。
// action は WordPress (admin-post.php) が読み、admin_post_nopriv_<値> と admin_post_<値> のフックを呼ぶ
preg_match_all('/ name="([a-z_]+)"/', $form, $m);
$names = array_values(array_unique($m[1]));
$keys_in = function ($ref) {
    $src = implode('', array_slice(file($ref->getFileName()), $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
    preg_match_all('/\$(?:s\(|in\[)\'([a-z_]+)\'/', $src, $m);
    return array_values(array_unique($m[1]));
};
$validate_keys = $keys_in(new ReflectionMethod('TS_Takedown_Input', 'validate'));
$post_keys = $keys_in(new ReflectionFunction('ts_takedown_handle_post'));
check('フォームの入力名 (S242 の表と同じ 11 個)', $names,
      ['action', '_wpnonce', 't', 'hp', 'pen_name', 'targets', 'contact', 'relation', 'evidence', 'message', 'allow_name']);
check('validate が読む鍵', $validate_keys, ['pen_name', 'targets', 'contact', 'relation', 'evidence', 'message', 'allow_name']);
check('ts_takedown_handle_post が読む鍵', $post_keys, ['_wpnonce', 't', 'hp']);
$read = array_merge($validate_keys, $post_keys, ['action']);
sort($read); $form_names = $names; sort($form_names);
check('入力名と読む側の鍵が一致する (余りも欠けも無い)', $form_names, $read);
check('action の値でフォームの送信を受けるフック', [(bool) preg_match('/name="action" value="ts_takedown"/', $form),
      count(TS_WP_Stubs::callbacks('admin_post_nopriv_ts_takedown')), count(TS_WP_Stubs::callbacks('admin_post_ts_takedown'))], [true, 1, 1]);
TS_WP_Stubs::reset();

// ---------------------------------------------------------------- 運営委員の操作 ts_takedown_operate (S128。T45.6.5)
// 期待値: 0e8c7712 の ts-library.php と includes 7 本を同じ代わりで動かした出力 (2026-11-05、scripts/wp/php_probe.sh、probe-rest.php)。場面と流し方は wp-fixtures.php の ts_fixture_run_takedown_operate。止める 4 通りのうち「知らない操作」は、
// 状態の遷移の表 (op_next) に無いので「いまの状態ではこの操作はできません。」になる (最後の「不明な操作です。」には届かない)
$same('運営委員の操作', ts_fixture_run_takedown_operate(time()), array (
  'ops' => 
  array (
    'no_permission' => 
    array (
      0 => false,
      1 => '権限がありません。',
    ),
    'bad_state' => 
    array (
      0 => false,
      1 => 'いまの状態ではこの操作はできません。',
    ),
    'unknown' => 
    array (
      0 => false,
      1 => 'いまの状態ではこの操作はできません。',
    ),
    'no_deadline' => 
    array (
      0 => false,
      1 => '新しい自動適用の時刻を入れてください。',
    ),
    'deadline' => 
    array (
      0 => true,
      1 => '自動適用の時刻を 2026-11-14 09:00 にしました。',
    ),
    'approve' => 
    array (
      0 => true,
      1 => '承認して非公開にしました (2 作品)。',
    ),
    'reject_after_approve' => 
    array (
      0 => false,
      1 => 'いまの状態ではこの操作はできません。',
    ),
    'reject' => 
    array (
      0 => true,
      1 => '却下しました。',
    ),
    'reinstate' => 
    array (
      0 => true,
      1 => '復帰しました。 ただし 1 件の投稿は、サイトの取り下げの記録 (denylist) に書き写した後のため、この画面では戻していません。管理者に依頼してください。',
    ),
  ),
  'meta' => 
  array (
    'request' => 
    array (
      '_tr_allow_name' => '1',
      '_tr_auto_apply_at' => 1794700800,
      '_tr_confirmed_at' => 1793836800,
      '_tr_contact' => 'amano@example.com',
      '_tr_evidence' => '',
      '_tr_log' => 
      array (
        0 => 'approved/3/承認のメモ/now+0h',
        1 => 'reinstated/4/復帰のメモ/now+0h',
      ),
      '_tr_message' => '',
      '_tr_pen_name' => '天野 月',
      '_tr_received_at' => 1793833200,
      '_tr_relation' => 'author',
      '_tr_resolution_note' => '復帰のメモ',
      '_tr_resolved_at' => 'now+0h',
      '_tr_resolved_by' => 4,
      '_tr_status' => 'reinstated',
      '_tr_via' => 'takedown_form',
    ),
    'other' => 
    array (
      '_tr_allow_name' => '0',
      '_tr_auto_apply_at' => 1794700800,
      '_tr_confirmed_at' => 1793836800,
      '_tr_contact' => 'amano@example.com',
      '_tr_evidence' => '',
      '_tr_log' => 'rejected/3/確認が取れない/now+0h',
      '_tr_message' => '',
      '_tr_pen_name' => '星野 空',
      '_tr_received_at' => 1793833200,
      '_tr_relation' => 'author',
      '_tr_resolution_note' => '確認が取れない',
      '_tr_resolved_at' => 'now+0h',
      '_tr_resolved_by' => 3,
      '_tr_status' => 'rejected',
      '_tr_via' => 'takedown_form',
    ),
    'third' => 
    array (
      '_tr_allow_name' => '1',
      '_tr_auto_apply_at' => 1794614400,
      '_tr_confirmed_at' => 1793836800,
      '_tr_contact' => 'amano@example.com',
      '_tr_evidence' => '',
      '_tr_log' => 'received/4/期限を 2026-11-14 09:00 に変更 — 延ばします/now+0h',
      '_tr_message' => '',
      '_tr_pen_name' => '海野 波',
      '_tr_received_at' => 1793833200,
      '_tr_relation' => 'author',
      '_tr_resolution_note' => '延ばします',
      '_tr_resolved_by' => 4,
      '_tr_status' => 'received',
      '_tr_via' => 'takedown_form',
    ),
  ),
  'posts' => 
  array (
    'amano-tsuki-renzoku' => 'publish vis= id= date= note= sup= imp=2026-11-05 12:00:00',
    'ren01' => 'publish vis= id= date= note= sup= imp=2026-11-05 12:00:00',
    'ren02' => 'private vis=withdrawn id=S9 date=2026-01-01 note=S9 の掲示 sup= imp=',
    'ren03' => 'publish vis= id= date= note= sup= imp=',
    'amano-tsuki-iriko' => 'publish vis= id= date= note= sup= imp=',
    'iri01' => 'publish vis= id= date= note= sup= imp=',
    'iri02' => 'publish vis= id= date= note= sup= imp=',
    'iri02a' => 'private vis=superseded id= date= note= sup=iri02a-shinpan imp=',
    'amano-tsuki-tanpen' => 'publish vis= id= date= note= sup= imp=',
  ),
  'urls' => 
  array (
    0 => '/novel/hoka-no-sakuhin/',
    1 => '/novel/amano-tsuki-renzoku/ren02/',
  ),
  'mails' => 
  array (
    0 => 
    array (
      0 => 'amano@example.com',
      1 => '[少年少女文庫] お申し出の作品を非公開にしました',
      2 => 'お申し出に基づき、次の作品を非公開にしました。
  - 連続する月
  - 入れ子の月

掲載していない作品の一覧には、作品名とペンネームを載せています。

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
    1 => 
    array (
      0 => 'admin@example.com',
      1 => '[少年少女文庫] 取り下げの申請 10 を適用しました',
      2 => '取り下げの申請 10 を適用し、次の作品を非公開にしました。
  - 連続する月
  - 入れ子の月

本サイトの作品に結びつかなかった URL があります (適用していません):
  - https://novels.xwp.jp/novel/nai/

ペンネーム: 天野 月
立場: 作者本人
名前の掲示: 可
対象:
  - https://novels.xwp.jp/novel/amano-tsuki-renzoku/
  - https://novels.xwp.jp/novel/amano-tsuki-iriko/
  - https://novels.xwp.jp/novel/nai/ (本サイトの作品に結びつかない)

管理画面:
https://novels.xwp.jp/wp-admin/post.php?post=10&action=edit

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
    2 => 
    array (
      0 => 'iin@example.com',
      1 => '[少年少女文庫] 取り下げの申請 10 を適用しました',
      2 => '取り下げの申請 10 を適用し、次の作品を非公開にしました。
  - 連続する月
  - 入れ子の月

本サイトの作品に結びつかなかった URL があります (適用していません):
  - https://novels.xwp.jp/novel/nai/

ペンネーム: 天野 月
立場: 作者本人
名前の掲示: 可
対象:
  - https://novels.xwp.jp/novel/amano-tsuki-renzoku/
  - https://novels.xwp.jp/novel/amano-tsuki-iriko/
  - https://novels.xwp.jp/novel/nai/ (本サイトの作品に結びつかない)

管理画面:
https://novels.xwp.jp/wp-admin/post.php?post=10&action=edit

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
    3 => 
    array (
      0 => 'editor@example.com',
      1 => '[少年少女文庫] 取り下げの申請 10 を適用しました',
      2 => '取り下げの申請 10 を適用し、次の作品を非公開にしました。
  - 連続する月
  - 入れ子の月

本サイトの作品に結びつかなかった URL があります (適用していません):
  - https://novels.xwp.jp/novel/nai/

ペンネーム: 天野 月
立場: 作者本人
名前の掲示: 可
対象:
  - https://novels.xwp.jp/novel/amano-tsuki-renzoku/
  - https://novels.xwp.jp/novel/amano-tsuki-iriko/
  - https://novels.xwp.jp/novel/nai/ (本サイトの作品に結びつかない)

管理画面:
https://novels.xwp.jp/wp-admin/post.php?post=10&action=edit

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
    4 => 
    array (
      0 => 'amano@example.com',
      1 => '[少年少女文庫] 掲載の取り下げのお申し出について',
      2 => '掲載の取り下げのお申し出について、確認が取れなかったため、取り下げは行いませんでした。
あらためてお申し出いただく場合は、取り下げのページからお願いします。

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
  ),
));

// ---------------------------------------------------------------- cron ts_takedown_tick (S130。T45.6.4)
// 期待値: 0e8c7712 の includes 7 本を同じ代わりで動かした出力 (2026-11-05、scripts/wp/php_probe.sh、probe-paths.php)。場面と流し方は
// wp-fixtures.php の ts_fixture_takedown_tick_scene と ts_fixture_run_takedown_tick (probe も同じ関数を呼ぶ)。time() は置き換えられないので、
// 時刻は「いま」からの差 (時間の単位) で比べる。失効・リマインド・自動適用・保持期限の消去が 1 件ずつ、何もしない申請が 4 件
$same('cron', ts_fixture_run_takedown_tick('ts_takedown_tick'), array (
  'requests' => 
  array (
    'request' => 
    array (
      '_tr_allow_name' => '1',
      '_tr_auto_apply_at' => 'now-1h',
      '_tr_confirmed_at' => 1793836800,
      '_tr_contact' => 'amano@example.com',
      '_tr_evidence' => '',
      '_tr_log' => 'applied/0//now+0h',
      '_tr_message' => '',
      '_tr_pen_name' => '天野 月',
      '_tr_received_at' => 'now-264h',
      '_tr_relation' => 'author',
      '_tr_resolution_note' => '',
      '_tr_resolved_at' => 'now+0h',
      '_tr_resolved_by' => 0,
      '_tr_status' => 'applied',
      '_tr_via' => 'takedown_form',
    ),
    'expire' => 
    array (
      '_tr_allow_name' => '0',
      '_tr_auto_apply_at' => '0',
      '_tr_confirmed_at' => 0,
      '_tr_contact' => 'amano@example.com',
      '_tr_evidence' => '',
      '_tr_log' => 'expired/0//now+0h',
      '_tr_message' => '',
      '_tr_pen_name' => '星野 空',
      '_tr_received_at' => 'now-192h',
      '_tr_relation' => 'author',
      '_tr_resolution_note' => '',
      '_tr_resolved_at' => 'now+0h',
      '_tr_resolved_by' => 0,
      '_tr_status' => 'expired',
      '_tr_via' => 'takedown_form',
    ),
    'young' => 
    array (
      '_tr_allow_name' => '0',
      '_tr_auto_apply_at' => '0',
      '_tr_confirmed_at' => 0,
      '_tr_contact' => 'amano@example.com',
      '_tr_evidence' => '',
      '_tr_message' => '',
      '_tr_pen_name' => '星野 空',
      '_tr_received_at' => 'now-24h',
      '_tr_relation' => 'author',
      '_tr_status' => 'unconfirmed',
      '_tr_token' => 'tokenhash2',
      '_tr_via' => 'takedown_form',
    ),
    'remind' => 
    array (
      '_tr_allow_name' => '0',
      '_tr_auto_apply_at' => 'now+48h',
      '_tr_confirmed_at' => 1793836800,
      '_tr_contact' => 'amano@example.com',
      '_tr_evidence' => '',
      '_tr_message' => '',
      '_tr_pen_name' => '星野 空',
      '_tr_received_at' => 'now-480h',
      '_tr_relation' => 'author',
      '_tr_reminded_at' => 'now+0h',
      '_tr_status' => 'received',
      '_tr_via' => 'takedown_form',
    ),
    'reminded' => 
    array (
      '_tr_allow_name' => '0',
      '_tr_auto_apply_at' => 'now+48h',
      '_tr_confirmed_at' => 1793836800,
      '_tr_contact' => 'amano@example.com',
      '_tr_evidence' => '',
      '_tr_message' => '',
      '_tr_pen_name' => '星野 空',
      '_tr_received_at' => 'now-480h',
      '_tr_relation' => 'author',
      '_tr_reminded_at' => 'now-1h',
      '_tr_status' => 'received',
      '_tr_via' => 'takedown_form',
    ),
    'purge' => 
    array (
      '_tr_allow_name' => '0',
      '_tr_auto_apply_at' => '0',
      '_tr_confirmed_at' => 1793836800,
      '_tr_pen_name' => '星野 空',
      '_tr_received_at' => 'now-480h',
      '_tr_relation' => 'author',
      '_tr_resolved_at' => 'now-8784h',
      '_tr_status' => 'applied',
      '_tr_via' => 'takedown_form',
    ),
    'purged' => 
    array (
      '_tr_allow_name' => '0',
      '_tr_auto_apply_at' => '0',
      '_tr_confirmed_at' => 1793836800,
      '_tr_contact' => '',
      '_tr_evidence' => '残る手がかり',
      '_tr_message' => '',
      '_tr_pen_name' => '星野 空',
      '_tr_received_at' => 'now-480h',
      '_tr_relation' => 'author',
      '_tr_resolved_at' => 'now-8784h',
      '_tr_status' => 'rejected',
      '_tr_via' => 'takedown_form',
    ),
    'recent' => 
    array (
      '_tr_allow_name' => '0',
      '_tr_auto_apply_at' => '0',
      '_tr_confirmed_at' => 1793836800,
      '_tr_contact' => 'amano@example.com',
      '_tr_evidence' => '',
      '_tr_message' => '',
      '_tr_pen_name' => '星野 空',
      '_tr_received_at' => 'now-480h',
      '_tr_relation' => 'author',
      '_tr_resolved_at' => 'now-240h',
      '_tr_status' => 'expired',
      '_tr_via' => 'takedown_form',
    ),
  ),
  'posts' => 
  array (
    'amano-tsuki-renzoku' => 'private vis=withdrawn id=req:10 date=2026-11-05 note=「連続する月」「入れ子の月」(天野 月さん) — 作者の申し出により掲載していません (2026 年 11 月) sup= imp=2026-11-05 12:00:00',
    'ren01' => 'private vis=withdrawn id=req:10 date=2026-11-05 note=「連続する月」「入れ子の月」(天野 月さん) — 作者の申し出により掲載していません (2026 年 11 月) sup= imp=2026-11-05 12:00:00',
    'ren02' => 'private vis=withdrawn id=S9 date=2026-01-01 note=S9 の掲示 sup= imp=',
    'ren03' => 'private vis=withdrawn id=req:10 date=2026-11-05 note=「連続する月」「入れ子の月」(天野 月さん) — 作者の申し出により掲載していません (2026 年 11 月) sup= imp=',
    'amano-tsuki-iriko' => 'private vis=withdrawn id=req:10 date=2026-11-05 note=「連続する月」「入れ子の月」(天野 月さん) — 作者の申し出により掲載していません (2026 年 11 月) sup= imp=',
    'iri01' => 'private vis=withdrawn id=req:10 date=2026-11-05 note=「連続する月」「入れ子の月」(天野 月さん) — 作者の申し出により掲載していません (2026 年 11 月) sup= imp=',
    'iri02' => 'private vis=withdrawn id=req:10 date=2026-11-05 note=「連続する月」「入れ子の月」(天野 月さん) — 作者の申し出により掲載していません (2026 年 11 月) sup= imp=',
    'iri02a' => 'private vis=withdrawn id=req:10 date=2026-11-05 note=「連続する月」「入れ子の月」(天野 月さん) — 作者の申し出により掲載していません (2026 年 11 月) sup=iri02a-shinpan imp=',
    'amano-tsuki-tanpen' => 'publish vis= id= date= note= sup= imp=',
  ),
  'urls' => 
  array (
    0 => '/novel/hoka-no-sakuhin/',
    1 => '/novel/amano-tsuki-renzoku/ren02/',
    2 => '/novel/amano-tsuki-renzoku/',
    3 => '/novel/amano-tsuki-renzoku/ren01/',
    4 => '/novel/amano-tsuki-renzoku/ren03/',
    5 => '/novel/amano-tsuki-iriko/',
    6 => '/novel/amano-tsuki-iriko/iri01/',
    7 => '/novel/amano-tsuki-iriko/iri02/',
    8 => '/novel/amano-tsuki-iriko/iri02/iri02a/',
  ),
  'mails' => 
  array (
    0 => 
    array (
      0 => 'admin@example.com',
      1 => '[少年少女文庫] 取り下げの申請 13 はまもなく自動で非公開になります',
      2 => '取り下げの申請 13 は、now+48h に自動で非公開になります。

ペンネーム: 星野 空
立場: 作者本人
名前の掲示: 不可
対象:
  - https://novels.xwp.jp/novel/hoka/ (本サイトの作品に結びつかない)

承認・却下・期限の変更は管理画面から:
https://novels.xwp.jp/wp-admin/post.php?post=13&action=edit

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
    1 => 
    array (
      0 => 'iin@example.com',
      1 => '[少年少女文庫] 取り下げの申請 13 はまもなく自動で非公開になります',
      2 => '取り下げの申請 13 は、now+48h に自動で非公開になります。

ペンネーム: 星野 空
立場: 作者本人
名前の掲示: 不可
対象:
  - https://novels.xwp.jp/novel/hoka/ (本サイトの作品に結びつかない)

承認・却下・期限の変更は管理画面から:
https://novels.xwp.jp/wp-admin/post.php?post=13&action=edit

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
    2 => 
    array (
      0 => 'editor@example.com',
      1 => '[少年少女文庫] 取り下げの申請 13 はまもなく自動で非公開になります',
      2 => '取り下げの申請 13 は、now+48h に自動で非公開になります。

ペンネーム: 星野 空
立場: 作者本人
名前の掲示: 不可
対象:
  - https://novels.xwp.jp/novel/hoka/ (本サイトの作品に結びつかない)

承認・却下・期限の変更は管理画面から:
https://novels.xwp.jp/wp-admin/post.php?post=13&action=edit

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
    3 => 
    array (
      0 => 'amano@example.com',
      1 => '[少年少女文庫] お申し出の作品を非公開にしました',
      2 => 'お申し出に基づき、次の作品を非公開にしました。
  - 連続する月
  - 入れ子の月

掲載していない作品の一覧には、作品名とペンネームを載せています。

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
    4 => 
    array (
      0 => 'admin@example.com',
      1 => '[少年少女文庫] 取り下げの申請 10 を適用しました',
      2 => '取り下げの申請 10 を適用し、次の作品を非公開にしました。
  - 連続する月
  - 入れ子の月

本サイトの作品に結びつかなかった URL があります (適用していません):
  - https://novels.xwp.jp/novel/nai/

ペンネーム: 天野 月
立場: 作者本人
名前の掲示: 可
対象:
  - https://novels.xwp.jp/novel/amano-tsuki-renzoku/
  - https://novels.xwp.jp/novel/amano-tsuki-iriko/
  - https://novels.xwp.jp/novel/nai/ (本サイトの作品に結びつかない)

管理画面:
https://novels.xwp.jp/wp-admin/post.php?post=10&action=edit

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
    5 => 
    array (
      0 => 'iin@example.com',
      1 => '[少年少女文庫] 取り下げの申請 10 を適用しました',
      2 => '取り下げの申請 10 を適用し、次の作品を非公開にしました。
  - 連続する月
  - 入れ子の月

本サイトの作品に結びつかなかった URL があります (適用していません):
  - https://novels.xwp.jp/novel/nai/

ペンネーム: 天野 月
立場: 作者本人
名前の掲示: 可
対象:
  - https://novels.xwp.jp/novel/amano-tsuki-renzoku/
  - https://novels.xwp.jp/novel/amano-tsuki-iriko/
  - https://novels.xwp.jp/novel/nai/ (本サイトの作品に結びつかない)

管理画面:
https://novels.xwp.jp/wp-admin/post.php?post=10&action=edit

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
    6 => 
    array (
      0 => 'editor@example.com',
      1 => '[少年少女文庫] 取り下げの申請 10 を適用しました',
      2 => '取り下げの申請 10 を適用し、次の作品を非公開にしました。
  - 連続する月
  - 入れ子の月

本サイトの作品に結びつかなかった URL があります (適用していません):
  - https://novels.xwp.jp/novel/nai/

ペンネーム: 天野 月
立場: 作者本人
名前の掲示: 可
対象:
  - https://novels.xwp.jp/novel/amano-tsuki-renzoku/
  - https://novels.xwp.jp/novel/amano-tsuki-iriko/
  - https://novels.xwp.jp/novel/nai/ (本サイトの作品に結びつかない)

管理画面:
https://novels.xwp.jp/wp-admin/post.php?post=10&action=edit

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
  ),
  'last_tick' => 
  array (
    'time' => 'now+0h',
    'error' => '',
    'counts' => 
    array (
      'expire' => 1,
      'remind' => 1,
      'apply' => 1,
      'purge' => 1,
    ),
  ),
));
TS_WP_Stubs::reset();

// ---------------------------------------------------------------- フォームの送信と確認リンク (S124・S126。T45.6.4)
// 期待値: 上と同じ probe。流し方は wp-fixtures.php の ts_fixture_run_takedown_form (probe は署名に旧版の ts_takedown_sign_time を渡す)。
// 応答は [状態コード, 題, 本文]。止める 4 通り (nonce 403・honeypot 400・3 秒未満と壊れた署名 400・入力誤り 400) は書き込みが無く、
// 確認メール送付 200 は申請のメタ・メール・レート制限の transient (寿命 60 分) を書き、同じ送信元の 4 件目は 429。
// 確認リンク (template_redirect@1) は 200 (受付・受け付け済み)・404 (形が違う・配列・知らない token)・410 (期限切れ・失効済み)。
// 最後の受付 200 は TS_TAKEDOWN_REQUIRE_CONFIRM を false にして流す (定数は消せないので、このファイルの最後に置く)
$confirm = null;
foreach (TS_WP_Stubs::callbacks('template_redirect') as $h) if ($h[2] === 1) $confirm = $h[4];
$got = ts_fixture_run_takedown_form([TS_Takedown_Input::class, 'sign_time'], 'ts_takedown_handle_post', $confirm);
$same('フォームと確認リンク', $got, array (
  'nonce' => 
  array (
    0 => 403,
    1 => '送信できませんでした',
    2 => '<h1>送信できませんでした</h1><p>ページの有効期限が切れました。取り下げのページを開き直して、もう一度送信してください。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'honeypot' => 
  array (
    0 => 400,
    1 => '送信できませんでした',
    2 => '<h1>送信できませんでした</h1><p>送信を受け付けられませんでした。取り下げのページを開き直して、もう一度送信してください。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'fast' => 
  array (
    0 => 400,
    1 => '送信できませんでした',
    2 => '<h1>送信できませんでした</h1><p>送信を受け付けられませんでした。取り下げのページを開き直して、もう一度送信してください。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'broken_t' => 
  array (
    0 => 400,
    1 => '送信できませんでした',
    2 => '<h1>送信できませんでした</h1><p>送信を受け付けられませんでした。取り下げのページを開き直して、もう一度送信してください。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'invalid' => 
  array (
    0 => 400,
    1 => '入力を確かめてください',
    2 => '<h1>入力を確かめてください</h1><ul><li>当時のペンネームを書いてください。</li><li>対象作品の URL を 1 行に 1 つ書いてください。</li><li>連絡先のメールアドレスを正しく書いてください。</li><li>お立場を選んでください。</li></ul><p>ブラウザの「戻る」で入力に戻れます。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'blocked_writes' => 
  array (
    0 => 9,
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'sent' => 
  array (
    0 => 200,
    1 => '確認メールを送りました',
    2 => '<h1>確認メールを送りました</h1><p>お書きいただいたアドレスに確認メールを送りました。メールのリンクを開くと、お申し出の受け付けが完了します。</p><p>リンクの有効期限は 7 日です。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'sent_post' => 
  array (
    0 => 'ts_takedown_request',
    1 => 'private',
    2 => '申請 10 天野 月',
  ),
  'sent_meta' => 
  array (
    '_tr_allow_name' => '1',
    '_tr_contact' => 'amano@example.com',
    '_tr_evidence' => '当時の感想板の名前は
「月」です',
    '_tr_ip_hash' => 'dd5cfbd8edfc0472aa50be6830d3ab311af4507c962a611d7e77523181586b9e',
    '_tr_log' => 'unconfirmed/0//now+0h',
    '_tr_message' => 'よろしく
お願いします',
    '_tr_pen_name' => '天野 月',
    '_tr_received_at' => 'now+0h',
    '_tr_relation' => 'author',
    '_tr_status' => 'unconfirmed',
    '_tr_targets' => '[{"url":"https://novels.xwp.jp/novel/amano-tsuki-renzoku/","post_id":1,"work_slug":"amano-tsuki-renzoku"},{"url":"https://novels.xwp.jp/novel/nai/","post_id":null,"work_slug":null}]',
    '_tr_token' => '(token の wp_hash)',
    '_tr_ua' => 'Mozilla/5.0 (作り物)',
    '_tr_via' => 'takedown_form',
  ),
  'sent_mails' => 
  array (
    0 => 
    array (
      0 => 'amano@example.com',
      1 => '[少年少女文庫] 掲載の取り下げのお申し出の確認',
      2 => '掲載の取り下げのお申し出を受け付けました。

次のリンクを開くと、お申し出の受け付けが完了します。
https://novels.xwp.jp/?ts_confirm=(token)

リンクの有効期限は 7 日です。開かれなかったお申し出は無効になります。
お心当たりのない場合は、このメールを破棄してください。

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
  ),
  'sent_transients' => 
  array (
    'ts_tr_rl_dd5cfbd8edfc0472aa50be6830d3ab311af4507c' => 
    array (
      0 => 1,
      1 => 'now+0h',
      2 => '60m',
    ),
  ),
  'sent2' => 
  array (
    0 => 200,
    1 => '確認メールを送りました',
    2 => '<h1>確認メールを送りました</h1><p>お書きいただいたアドレスに確認メールを送りました。メールのリンクを開くと、お申し出の受け付けが完了します。</p><p>リンクの有効期限は 7 日です。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'sent3' => 
  array (
    0 => 200,
    1 => '確認メールを送りました',
    2 => '<h1>確認メールを送りました</h1><p>お書きいただいたアドレスに確認メールを送りました。メールのリンクを開くと、お申し出の受け付けが完了します。</p><p>リンクの有効期限は 7 日です。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'limited' => 
  array (
    0 => 429,
    1 => '送信できませんでした',
    2 => '<h1>送信できませんでした</h1><p>短い時間に続けて送信されたため、受け付けを止めています。時間をおいてお試しください。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'other_ip' => 
  array (
    0 => 400,
    1 => '入力を確かめてください',
    2 => '<h1>入力を確かめてください</h1><ul><li>当時のペンネームを書いてください。</li></ul><p>ブラウザの「戻る」で入力に戻れます。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'limited_transients' => 
  array (
    'ts_tr_rl_dd5cfbd8edfc0472aa50be6830d3ab311af4507c' => 
    array (
      0 => 3,
      1 => 'now+0h',
      2 => '60m',
    ),
  ),
  'confirm_none' => NULL,
  'confirm_short' => 
  array (
    0 => 404,
    1 => 'リンクを確かめてください',
    2 => '<h1>リンクを確かめてください</h1><p>このリンクは正しくないか、すでに使えなくなっています。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'confirm_array' => 
  array (
    0 => 404,
    1 => 'リンクを確かめてください',
    2 => '<h1>リンクを確かめてください</h1><p>このリンクは正しくないか、すでに使えなくなっています。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'confirm_unknown' => 
  array (
    0 => 404,
    1 => 'リンクを確かめてください',
    2 => '<h1>リンクを確かめてください</h1><p>このリンクは正しくないか、すでに使えなくなっています。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'confirm_ok' => 
  array (
    0 => 200,
    1 => 'お申し出を受け付けました',
    2 => '<h1>お申し出を受け付けました</h1><p>確認できました。お申し出を受け付けました。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'confirm_ok_meta' => 
  array (
    '_tr_allow_name' => '1',
    '_tr_auto_apply_at' => 'now+240h',
    '_tr_confirmed_at' => 'now+0h',
    '_tr_contact' => 'amano@example.com',
    '_tr_evidence' => '当時の感想板の名前は
「月」です',
    '_tr_ip_hash' => 'dd5cfbd8edfc0472aa50be6830d3ab311af4507c962a611d7e77523181586b9e',
    '_tr_log' => 
    array (
      0 => 'unconfirmed/0//now+0h',
      1 => 'received/0//now+0h',
    ),
    '_tr_message' => 'よろしく
お願いします',
    '_tr_pen_name' => '天野 月',
    '_tr_received_at' => 'now+0h',
    '_tr_relation' => 'author',
    '_tr_status' => 'received',
    '_tr_targets' => '[{"url":"https://novels.xwp.jp/novel/amano-tsuki-renzoku/","post_id":1,"work_slug":"amano-tsuki-renzoku"},{"url":"https://novels.xwp.jp/novel/nai/","post_id":null,"work_slug":null}]',
    '_tr_token' => '(token の wp_hash)',
    '_tr_ua' => 'Mozilla/5.0 (作り物)',
    '_tr_via' => 'takedown_form',
  ),
  'confirm_ok_mails' => 
  array (
    0 => 
    array (
      0 => 'admin@example.com',
      1 => '[少年少女文庫] 取り下げの申請 10 を受け付けました',
      2 => '取り下げの申請 10 の確認リンクが開かれました。

ペンネーム: 天野 月
立場: 作者本人
名前の掲示: 可
対象:
  - https://novels.xwp.jp/novel/amano-tsuki-renzoku/
  - https://novels.xwp.jp/novel/nai/ (本サイトの作品に結びつかない)

now+240h に自動で非公開になります。承認・却下は管理画面から:
https://novels.xwp.jp/wp-admin/post.php?post=10&action=edit

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
    1 => 
    array (
      0 => 'iin@example.com',
      1 => '[少年少女文庫] 取り下げの申請 10 を受け付けました',
      2 => '取り下げの申請 10 の確認リンクが開かれました。

ペンネーム: 天野 月
立場: 作者本人
名前の掲示: 可
対象:
  - https://novels.xwp.jp/novel/amano-tsuki-renzoku/
  - https://novels.xwp.jp/novel/nai/ (本サイトの作品に結びつかない)

now+240h に自動で非公開になります。承認・却下は管理画面から:
https://novels.xwp.jp/wp-admin/post.php?post=10&action=edit

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
  ),
  'confirm_again' => 
  array (
    0 => 200,
    1 => 'お申し出は受け付け済みです',
    2 => '<h1>お申し出は受け付け済みです</h1><p>このお申し出はすでに受け付けています。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'confirm_late' => 
  array (
    0 => 410,
    1 => 'リンクの有効期限が切れています',
    2 => '<h1>リンクの有効期限が切れています</h1><p>お手数ですが、取り下げのページからもう一度お申し出ください。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'confirm_expired' => 
  array (
    0 => 410,
    1 => 'リンクの有効期限が切れています',
    2 => '<h1>リンクの有効期限が切れています</h1><p>お手数ですが、取り下げのページからもう一度お申し出ください。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'confirm_late_after' => 
  array (
    0 => 'unconfirmed',
    1 => 'expired',
    2 => 2,
  ),
  'received' => 
  array (
    0 => 200,
    1 => 'お申し出を受け付けました',
    2 => '<h1>お申し出を受け付けました</h1><p>お申し出を受け付けました。</p><p><a href="https://novels.xwp.jp/">トップへ戻る</a></p>',
  ),
  'received_meta' => 
  array (
    '_tr_allow_name' => '1',
    '_tr_auto_apply_at' => 'now+240h',
    '_tr_confirmed_at' => 'now+0h',
    '_tr_contact' => 'amano@example.com',
    '_tr_evidence' => '当時の感想板の名前は
「月」です',
    '_tr_ip_hash' => '8729764572f8e5758eddb8ad871be4949144084e3223190ec7d33749d755fdae',
    '_tr_log' => 'received/0//now+0h',
    '_tr_message' => 'よろしく
お願いします',
    '_tr_pen_name' => '天野 月',
    '_tr_received_at' => 'now+0h',
    '_tr_relation' => 'author',
    '_tr_status' => 'received',
    '_tr_targets' => '[{"url":"https://novels.xwp.jp/novel/amano-tsuki-renzoku/","post_id":1,"work_slug":"amano-tsuki-renzoku"},{"url":"https://novels.xwp.jp/novel/nai/","post_id":null,"work_slug":null}]',
    '_tr_ua' => 'Mozilla/5.0 (作り物)',
    '_tr_via' => 'takedown_form',
  ),
  'received_mails' => 
  array (
    0 => 
    array (
      0 => 'admin@example.com',
      1 => '[少年少女文庫] 取り下げの申請 13 を受け付けました',
      2 => '取り下げの申請 13 の確認リンクが開かれました。

ペンネーム: 天野 月
立場: 作者本人
名前の掲示: 可
対象:
  - https://novels.xwp.jp/novel/amano-tsuki-renzoku/
  - https://novels.xwp.jp/novel/nai/ (本サイトの作品に結びつかない)

now+240h に自動で非公開になります。承認・却下は管理画面から:
https://novels.xwp.jp/wp-admin/post.php?post=13&action=edit

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
    1 => 
    array (
      0 => 'iin@example.com',
      1 => '[少年少女文庫] 取り下げの申請 13 を受け付けました',
      2 => '取り下げの申請 13 の確認リンクが開かれました。

ペンネーム: 天野 月
立場: 作者本人
名前の掲示: 可
対象:
  - https://novels.xwp.jp/novel/amano-tsuki-renzoku/
  - https://novels.xwp.jp/novel/nai/ (本サイトの作品に結びつかない)

now+240h に自動で非公開になります。承認・却下は管理画面から:
https://novels.xwp.jp/wp-admin/post.php?post=13&action=edit

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
    2 => 
    array (
      0 => 'amano@example.com',
      1 => '[少年少女文庫] 掲載の取り下げのお申し出を受け付けました',
      2 => '掲載の取り下げのお申し出を受け付けました。運営委員が内容を確認します。
お心当たりのない場合は、このメールを破棄してください。

-- 
少年少女文庫
',
      3 => 
      array (
        0 => 'Content-Type: text/plain; charset=UTF-8',
        1 => 'From: 少年少女文庫 <admin@example.com>',
      ),
    ),
  ),
));
check('フォームと確認リンク: 流した後に $_POST・$_GET は空に戻り、$_SERVER に送信元が残らない', [$_POST, $_GET, $_SERVER['REMOTE_ADDR'] ?? null], [[], [], null]);
TS_WP_Stubs::reset();
