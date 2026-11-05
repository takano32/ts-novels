<?php
/**
 * 取り下げの申請の非公開化と復帰 (specs/S127.md・S128.md) を WordPress の代わり (scripts/wp/tests/wp-stubs.php) の上で通して動かす
 * (T45.1.17.3)。期待値は分割前 (d586f0eb) の includes/takedown-request.php と includes/visibility.php を同じ代わりと同じ置き場
 * (wp-fixtures.php の ts_fixture_takedown_apply_scene) で動かした出力から取った (2026-11-05、scripts/wp/php_probe.sh、probe-takedown-apply.php)。
 * 実装の写しではない。対象と子孫の集め方 2 本の順 (T45.1.19.3) もここで見る。scripts/wp/ts_library_test.php から WP の表を読んだ後に読む。
 * 後半はショートコード 2 つ (フォーム [ts_takedown_form] と一覧 [ts_removed_list]) の HTML と、フォームの入力名と読む側の鍵 (T45.1.15.3)。
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
// 期待値は d586f0eb の ts_takedown_target_posts を同じ代わりで動かした順 (probe-takedown-apply.php の order)
check('申請の対象と子孫 (ts_takedown_target_posts): 対象ごとに、対象 → 子孫を stack で (get_posts の逆順 = 日付の古い順)。結びつかない URL は飛ばす',
      ts_takedown_target_posts(['targets' => [['post_id' => $t['nested']], ['post_id' => $t['single']], ['post_id' => null]]]),
      [$t['nested'], $t['nep'][0], $t['nep'][1], $t['grand'], $t['single']]);
// descendants と takedown_targets は分割前 (d586f0eb の commands.php) と同じコード
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
// 期待値: d586f0eb の takedown-request.php、2026-11-05、probe-shortcodes.php。表示時刻の署名 (name="t") は time() に依るので伏せて比べ、
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
