<?php
/**
 * 取り下げフォームの判定と文面 (specs/S124.md〜S132.md、T26.3): パラメータ・入力の検証・スパム対策・
 * 状態の遷移・cron の判定・保持期限・/removed/ の 1 行と並び・メール。scripts/wp/ts_library_test.php から読む。
 */

$P = TS_Takedown_Rules::params();
check('既定のパラメータ', [$P['confirm_ttl_days'], $P['auto_apply_days'], $P['remind_before_days'], $P['retention_days'],
      $P['require_confirm'], $P['notify_to'], $P['send_reject_mail'], $P['rate_limit']], [7, 10, 3, 365, true, '', true, 3]);
// wp-config.php の TS_TAKEDOWN_<名前> での上書き (定数の読み取りを配列で代える。T45.1.7.3)
$consts = ['TS_TAKEDOWN_CONFIRM_TTL_DAYS' => '14', 'TS_TAKEDOWN_AUTO_APPLY_DAYS' => 0, 'TS_TAKEDOWN_REMIND_BEFORE_DAYS' => 1,
           'TS_TAKEDOWN_RETENTION_DAYS' => 30, 'TS_TAKEDOWN_REQUIRE_CONFIRM' => false, 'TS_TAKEDOWN_NOTIFY_TO' => 'a@example.org,b@example.org',
           'TS_TAKEDOWN_SEND_REJECT_MAIL' => 0, 'TS_TAKEDOWN_RATE_LIMIT' => '5', 'TS_TAKEDOWN_RATE_WINDOW' => 1, 'TS_TAKEDOWN_MIN_SECONDS' => 1];
$P2 = TS_Takedown_Rules::params(function ($k, $default) use ($consts) { return array_key_exists($k, $consts) ? $consts[$k] : $default; });
check('定数での上書き (型は既定に揃える。rate_window と min_seconds は変えられない)', $P2,
      ['confirm_ttl_days' => 14, 'auto_apply_days' => 0, 'remind_before_days' => 1, 'retention_days' => 30, 'require_confirm' => false,
       'notify_to' => 'a@example.org,b@example.org', 'send_reject_mail' => false, 'rate_limit' => 5, 'rate_window' => 3600, 'min_seconds' => 3]);
check('一部だけ上書き', TS_Takedown_Rules::params(function ($k, $default) { return $k === 'TS_TAKEDOWN_RATE_LIMIT' ? 10 : $default; }),
      array_merge($P, ['rate_limit' => 10]));
check('状態は 7 つ (S126)', array_keys(TS_Takedown_Rules::status_labels()),
      ['unconfirmed', 'received', 'applied', 'approved', 'rejected', 'reinstated', 'expired']);

// ---------------------------------------------------------------- 入力 (S124)
$H = 'novels.xwp.jp';
check('URL → パス', TS_Takedown_Input::target_path('https://novels.xwp.jp/novel/foo/ep01/', $H), 'foo/ep01');
check('ホストだけ大文字', TS_Takedown_Input::target_path('https://NOVELS.xwp.jp/novel/foo', $H), 'foo');
check('スキームを省いた URL', TS_Takedown_Input::target_path('novels.xwp.jp/novel/foo/', $H), 'foo');
check('パスだけ', TS_Takedown_Input::target_path('/novel/a%20b/', $H), 'a b');
check('他サイト', TS_Takedown_Input::target_path('https://example.com/novel/foo/', $H), null);
check('旧サイトの URL', TS_Takedown_Input::target_path('http://ts.novels.jp/novel/200202/x.html', $H), null);
check('/novel/ の外', TS_Takedown_Input::target_path('https://novels.xwp.jp/about/', $H), null);
check('javascript:', TS_Takedown_Input::target_path('javascript:alert(1)//novels.xwp.jp/novel/x', $H), null);
check('.. を含む', TS_Takedown_Input::target_path('/novel/../wp-admin/', $H), null);
check('可視性の解決と同じ規則', TS_Takedown_Input::target_path('/novel/a%20b/c/', $H), TS_Visibility_Rules::page_path('novel/a%20b/c'));

$resolve = function ($path) { return $path === 'foo' ? ['post_id' => 11, 'work_slug' => 'a-foo'] : null; };
$ok_in = ['pen_name' => " 山田\x07 ", 'targets' => "https://novels.xwp.jp/novel/foo/\r\nhttps://example.com/x\n\nhttps://novels.xwp.jp/novel/foo/",
          'contact' => 'a@example.org', 'relation' => 'author', 'evidence' => "1\n2", 'message' => '', 'allow_name' => '1'];
$v = TS_Takedown_Input::validate($ok_in, $resolve, $H);
check('検証 通る', $v['errors'], []);
check('制御文字を除く', $v['data']['pen_name'], '山田');
check('対象 (重複を除き、他サイトは保存して結びつかない印)', $v['data']['targets'], [
    ['url' => 'https://novels.xwp.jp/novel/foo/', 'post_id' => 11, 'work_slug' => 'a-foo'],
    ['url' => 'https://example.com/x', 'post_id' => null, 'work_slug' => null]]);
check('改行は残す', $v['data']['evidence'], "1\n2");
check('名前の掲示', $v['data']['allow_name'], '1');
check('名前の掲示 (既定は出さない)', TS_Takedown_Input::validate(array_diff_key($ok_in, ['allow_name' => 1]), $resolve, $H)['data']['allow_name'], '0');
check('必須が無い', count(TS_Takedown_Input::validate([], $resolve, $H)['errors']), 4);
check('長すぎる', count(TS_Takedown_Input::validate(array_merge($ok_in, ['pen_name' => str_repeat('あ', 101),
      'evidence' => str_repeat('あ', 1001), 'message' => str_repeat('あ', 2001)]), $resolve, $H)['errors']), 3);
check('100 字ちょうどは通る', TS_Takedown_Input::validate(array_merge($ok_in, ['pen_name' => str_repeat('あ', 100)]), $resolve, $H)['errors'], []);
check('21 行', TS_Takedown_Input::validate(array_merge($ok_in, ['targets' => implode("\n", array_map(function ($i) { return "/novel/w$i/"; }, range(1, 21)))]), $resolve, $H)['errors'],
      ['対象作品の URL は 20 行までです。']);
check('メールの形', TS_Takedown_Input::validate(array_merge($ok_in, ['contact' => "a@example.org\nBcc: b@example.org"]), $resolve, $H)['errors'],
      ['連絡先のメールアドレスを正しく書いてください。']);
check('立場の値', TS_Takedown_Input::validate(array_merge($ok_in, ['relation' => 'admin']), $resolve, $H)['errors'], ['お立場を選んでください。']);
check('配列を渡されても落ちない', TS_Takedown_Input::validate(array_merge($ok_in, ['pen_name' => ['x']]), $resolve, $H)['errors'],
      ['当時のペンネームを書いてください。']);
check('制御文字は空白に (改行を残さないとき)', TS_Takedown_Input::clean("a\tb\x00c"), 'a b c');
check('改行とタブを残す', TS_Takedown_Input::clean("a\r\nb\tc\x01", true), "a\nb\tc");

$key = 'k';
$now = 1800000000;
$tv = TS_Takedown_Input::sign_time($now - 10, $key);
check('表示時刻の署名', TS_Takedown_Input::verify_time($tv, $key), $now - 10);
check('表示時刻の書き換え', TS_Takedown_Input::verify_time(($now - 100) . substr($tv, strpos($tv, '.')), $key), null);
check('表示時刻の形', TS_Takedown_Input::verify_time('abc', $key), null);
check('別の鍵では通らない', TS_Takedown_Input::verify_time($tv, 'other'), null);

// ---------------------------------------------------------------- スパム対策 (S124、S136 の検証手順)
check('スパム: nonce 不一致 → 403', TS_Takedown_Rules::gate(false, '', $now - 10, $now, 0, $P), 403);
check('スパム: honeypot 入り → 400', TS_Takedown_Rules::gate(true, 'http://spam', $now - 10, $now, 0, $P), 400);
check('スパム: 3 秒未満 → 400', TS_Takedown_Rules::gate(true, '', $now - 2, $now, 0, $P), 400);
check('スパム: 3 秒ちょうどは通る', TS_Takedown_Rules::gate(true, '', $now - 3, $now, 0, $P), 0);
check('スパム: 表示時刻が壊れている → 400', TS_Takedown_Rules::gate(true, '', null, $now, 0, $P), 400);
check('スパム: 1 時間 3 件目までは通る', TS_Takedown_Rules::gate(true, '', $now - 10, $now, 2, $P), 0);
check('スパム: 同一 IP 1 時間 4 件目 → 429', TS_Takedown_Rules::gate(true, '', $now - 10, $now, 3, $P), 429);

// ---------------------------------------------------------------- 状態 (S126・S128)
check('確認時刻 + 10 日', TS_Takedown_Rules::confirm_times($now, $P), ['confirmed_at' => $now, 'auto_apply_at' => $now + 864000]);

$ops = [];
foreach (['unconfirmed', 'received', 'applied', 'approved', 'rejected', 'reinstated', 'expired'] as $st) {
    foreach (['approve', 'reject', 'reinstate', 'deadline'] as $op) {
        if (($n = TS_Takedown_Rules::op_next($st, $op)) !== null) $ops[] = "$st:$op>$n";
    }
}
check('運営委員の操作の遷移 (S126)', $ops, ['unconfirmed:reject>rejected', 'received:approve>approved',
      'received:reject>rejected', 'received:deadline>received', 'applied:reinstate>reinstated', 'approved:reinstate>reinstated']);

$d = 86400;
check('cron: 7 日で失効', TS_Takedown_Rules::due('unconfirmed', ['received_at' => $now - 7 * $d], $now, $P), 'expire');
check('cron: 7 日未満は待つ', TS_Takedown_Rules::due('unconfirmed', ['received_at' => $now - 7 * $d + 1], $now, $P), null);
check('cron: 3 日前にリマインド', TS_Takedown_Rules::due('received', ['auto_apply_at' => $now + 3 * $d], $now, $P), 'remind');
check('cron: リマインドは 1 回', TS_Takedown_Rules::due('received', ['auto_apply_at' => $now + 3 * $d, 'reminded_at' => $now - 5], $now, $P), null);
check('cron: 4 日前は何もしない', TS_Takedown_Rules::due('received', ['auto_apply_at' => $now + 4 * $d], $now, $P), null);
check('cron: 時刻で自動適用', TS_Takedown_Rules::due('received', ['auto_apply_at' => $now, 'reminded_at' => 0], $now, $P), 'apply');
check('cron: 却下したものは適用しない', TS_Takedown_Rules::due('rejected', ['auto_apply_at' => $now - $d], $now, $P), null);
check('cron: 受付の時刻が無い未確認は失効させない', TS_Takedown_Rules::due('unconfirmed', [], $now, $P), null);
check('保持期間: 365 日で消す', TS_Takedown_Rules::purge_due('applied', $now - 365 * $d, $now, $P), true);
check('保持期間: 待機中は消さない', TS_Takedown_Rules::purge_due('received', $now - 400 * $d, $now, $P), false);
check('保持期間: 364 日は残す', TS_Takedown_Rules::purge_due('expired', $now - 364 * $d, $now, $P), false);
check('cron の仕分け (失効 → リマインド → 自動適用の組に、組の中は渡した順)', TS_Takedown_Rules::tick_plan([
    ['id' => 1, 'status' => 'received', 'auto_apply_at' => $now - 1, 'reminded_at' => 0],
    ['id' => 2, 'status' => 'unconfirmed', 'received_at' => $now - 8 * $d],
    ['id' => 3, 'status' => 'received', 'auto_apply_at' => $now + $d, 'reminded_at' => 0],
    ['id' => 4, 'status' => 'rejected', 'auto_apply_at' => $now - $d],
    ['id' => 5, 'status' => 'received', 'auto_apply_at' => $now, 'reminded_at' => $now - $d],
], $now, $P), ['expire' => [['id' => 2, 'status' => 'unconfirmed', 'received_at' => $now - 8 * $d]],
               'remind' => [['id' => 3, 'status' => 'received', 'auto_apply_at' => $now + $d, 'reminded_at' => 0]],
               'apply' => [['id' => 1, 'status' => 'received', 'auto_apply_at' => $now - 1, 'reminded_at' => 0],
                           ['id' => 5, 'status' => 'received', 'auto_apply_at' => $now, 'reminded_at' => $now - $d]]]);
check('cron の仕分け (空)', TS_Takedown_Rules::tick_plan([], $now, $P), ['expire' => [], 'remind' => [], 'apply' => []]);
check('保持期限の消去 (連絡先も ip_hash も空なら飛ばす、待機中と期限前は消さない)', TS_Takedown_Rules::purge_plan([
    ['id' => 1, 'status' => 'applied', 'resolved_at' => $now - 400 * $d, 'contact' => 'a@example.org', 'ip_hash' => 'h'],
    ['id' => 2, 'status' => 'rejected', 'resolved_at' => $now - 400 * $d, 'contact' => '', 'ip_hash' => ''],
    ['id' => 3, 'status' => 'expired', 'resolved_at' => $now - 400 * $d, 'contact' => '', 'ip_hash' => 'h'],
    ['id' => 4, 'status' => 'received', 'resolved_at' => $now - 400 * $d, 'contact' => 'a@example.org', 'ip_hash' => 'h'],
    ['id' => 5, 'status' => 'applied', 'resolved_at' => $now - 10 * $d, 'contact' => 'a@example.org', 'ip_hash' => 'h'],
], $now, $P), [1, 3]);

// ---------------------------------------------------------------- 文面 (S129・S131)
check('掲示の 1 行 (名前を出す)', TS_Takedown_Text::public_note(['森の家'], '山田', true, 2026, 11),
      '「森の家」(山田さん) — 作者の申し出により掲載していません (2026 年 11 月)');
check('掲示の 1 行 (名前を出さない)', TS_Takedown_Text::public_note(['森の家'], '山田', false, 2026, 1),
      '作者の申し出により 1 作品を掲載していません (2026 年 1 月)');
check('掲示の 1 行 (複数)', TS_Takedown_Text::public_note(['a', 'b'], '山田', false, 2026, 1),
      '作者の申し出により 2 作品を掲載していません (2026 年 1 月)');
check('掲示の 1 行 (題名が無くても 1 作品)', TS_Takedown_Text::public_note([], '山田', true, 2026, 1),
      '作者の申し出により 1 作品を掲載していません (2026 年 1 月)');

check('メールの受付 (確認リンク・有効 7 日)', strpos(TS_Takedown_Text::mail_text('receipt', ['site' => 'S', 'confirm_url' => 'https://x/?ts_confirm=ab', 'ttl_days' => 7])[1],
      "https://x/?ts_confirm=ab\n\nリンクの有効期限は 7 日です。") !== false, true);
$evil = ['site' => 'S', 'summary' => 'EVIL-TEXT', 'titles' => ['T'], 'id' => 1, 'when' => 'W', 'admin_url' => 'A'];
foreach (['receipt', 'applied_requester', 'rejected'] as $k) {
    check("依頼者宛 ($k) に依頼者の入力を入れない", strpos(implode("\n", TS_Takedown_Text::mail_text($k, $evil)), 'EVIL-TEXT'), false);
}
check('確認完了のメールに時刻', strpos(TS_Takedown_Text::mail_text('confirmed', $evil)[1], 'W に自動で非公開になります') !== false, true);
check('件名に入力を入れない', strpos(TS_Takedown_Text::mail_text('confirmed', $evil)[0], 'EVIL'), false);
check('未知の種類は空', TS_Takedown_Text::mail_text('nope', $evil), ['', '']);
// フォームの応答の題と本文 (S124、T45.1.17.5)。期待値は分割前 (d586f0eb) の takedown-request.php の文言
check('応答: 403 / 400 / 429', [TS_Takedown_Text::respond('forbidden', []), TS_Takedown_Text::respond('rejected', []), TS_Takedown_Text::respond('rate_limited', [])],
      [['送信できませんでした', '<p>ページの有効期限が切れました。取り下げのページを開き直して、もう一度送信してください。</p>'],
       ['送信できませんでした', '<p>送信を受け付けられませんでした。取り下げのページを開き直して、もう一度送信してください。</p>'],
       ['送信できませんでした', '<p>短い時間に続けて送信されたため、受け付けを止めています。時間をおいてお試しください。</p>']]);
check('応答: 入力の誤り (文はエスケープ済みで受ける)', TS_Takedown_Text::respond('invalid', ['items' => ['a', 'b&amp;c']]),
      ['入力を確かめてください', '<ul><li>a</li><li>b&amp;c</li></ul><p>ブラウザの「戻る」で入力に戻れます。</p>']);
check('応答: 500', TS_Takedown_Text::respond('failed', []), ['送信できませんでした', '<p>受け付けの処理に失敗しました。時間をおいてお試しください。</p>']);
check('応答: 確認メール', TS_Takedown_Text::respond('confirm_sent', ['ttl_days' => 7]),
      ['確認メールを送りました', '<p>お書きいただいたアドレスに確認メールを送りました。メールのリンクを開くと、お申し出の受け付けが完了します。</p><p>リンクの有効期限は 7 日です。</p>']);
check('応答: 受付', TS_Takedown_Text::respond('received', []), ['お申し出を受け付けました', '<p>お申し出を受け付けました。</p>']);
check('応答: 未知の種類は空', TS_Takedown_Text::respond('nope', []), ['', '']);
check('レート制限の寿命: 窓の残り', TS_Takedown_Rules::rate_ttl($now - 600, $now, $P), 3000);
check('レート制限の寿命: 60 秒より短くしない', [TS_Takedown_Rules::rate_ttl($now - 3590, $now, $P), TS_Takedown_Rules::rate_ttl($now - 7200, $now, $P)], [60, 60]);
// 管理画面の表と操作の結果の文 (S128、T45.1.17.6)。期待値は分割前 (d586f0eb) の takedown-admin.php の文言
check('立場の呼び名 (管理画面とメールの要約で共有)', TS_Takedown_Rules::RELATION_LABELS, ['author' => '作者本人', 'heir' => '権利継承者', 'other' => 'その他']);
check('操作のボタンの文言と並び', TS_Takedown_Rules::OP_LABELS, ['approve' => '承認して即時適用', 'reject' => '却下', 'reinstate' => '復帰', 'deadline' => '期限を変える']);
check('操作は遷移の表 (op_next) と同じ 4 つ', array_keys(TS_Takedown_Rules::OP_LABELS), ['approve', 'reject', 'reinstate', 'deadline']);
check('操作の結果の文', [TS_Takedown_Text::operate_result('approve', ['works' => 2]), TS_Takedown_Text::operate_result('reject', []),
      TS_Takedown_Text::operate_result('reinstate', ['left' => 0]), TS_Takedown_Text::operate_result('deadline', ['when' => '2026-11-15 09:00'])],
      ['承認して非公開にしました (2 作品)。', '却下しました。', '復帰しました。', '自動適用の時刻を 2026-11-15 09:00 にしました。']);
check('操作の結果の文 (復帰で戻さなかった投稿がある)', TS_Takedown_Text::operate_result('reinstate', ['left' => 3]),
      '復帰しました。 ただし 3 件の投稿は、サイトの取り下げの記録 (denylist) に書き写した後のため、この画面では戻していません。管理者に依頼してください。');
check('操作を止めた理由の文', [TS_Takedown_Text::operate_result('no_permission', []), TS_Takedown_Text::operate_result('bad_state', []),
      TS_Takedown_Text::operate_result('no_deadline', []), TS_Takedown_Text::operate_result('unknown', []), TS_Takedown_Text::operate_result('zzz', [])],
      ['権限がありません。', 'いまの状態ではこの操作はできません。', '新しい自動適用の時刻を入れてください。', '不明な操作です。', '不明な操作です。']);
$ctx = ['site' => 'S', 'id' => 12, 'when' => '2026-11-15 09:00', 'summary' => 'SUM', 'admin_url' => 'https://x/wp-admin/',
        'titles' => ['森の家', '海'], 'unresolved' => ['https://y/a']];
check('メールの予告 (remind)', TS_Takedown_Text::mail_text('remind', $ctx),
      ['[S] 取り下げの申請 12 はまもなく自動で非公開になります',
       "取り下げの申請 12 は、2026-11-15 09:00 に自動で非公開になります。\n\nSUM\n\n承認・却下・期限の変更は管理画面から:\nhttps://x/wp-admin/\n\n-- \nS\n"]);
check('運営委員宛の適用 (結びつかない URL あり)', TS_Takedown_Text::mail_text('applied_committee', $ctx),
      ['[S] 取り下げの申請 12 を適用しました',
       "取り下げの申請 12 を適用し、次の作品を非公開にしました。\n  - 森の家\n  - 海\n\n"
       . "本サイトの作品に結びつかなかった URL があります (適用していません):\n  - https://y/a\n\nSUM\n\n管理画面:\nhttps://x/wp-admin/\n\n-- \nS\n"]);
check('運営委員宛の適用 (結びつかない URL なし・作品なし)', TS_Takedown_Text::mail_text('applied_committee', ['unresolved' => [], 'titles' => []] + $ctx)[1],
      "取り下げの申請 12 を適用し、次の作品を非公開にしました。\n  (なし)\n\nSUM\n\n管理画面:\nhttps://x/wp-admin/\n\n-- \nS\n");
check('依頼者宛の適用 (一覧に載せる・載せない)', [TS_Takedown_Text::mail_text('applied_requester', ['listed' => true] + $ctx)[1],
      TS_Takedown_Text::mail_text('applied_requester', ['listed' => false] + $ctx)[1]],
      ["お申し出に基づき、次の作品を非公開にしました。\n  - 森の家\n  - 海\n\n掲載していない作品の一覧には、作品名とペンネームを載せています。\n\n-- \nS\n",
       "お申し出に基づき、次の作品を非公開にしました。\n  - 森の家\n  - 海\n\n掲載していない作品の一覧には、作品名とペンネームを載せていません。\n\n-- \nS\n"]);
check('文字数はマルチバイトを 1 字と数える', [TS_Takedown_Input::strlen('山田'), TS_Takedown_Input::strlen('ab'), TS_Takedown_Input::strlen(''),
      TS_Takedown_Input::strlen("森\nの家🌲")], [2, 2, 0, 5]);
check('メールアドレスの判定の既定 (filter_var)', [TS_Takedown_Input::is_email('a@example.com'), TS_Takedown_Input::is_email('a@'),
      TS_Takedown_Input::is_email('山田'), TS_Takedown_Input::is_email('')], [true, false, false, false]);
$seen_mail = [];
$v_inj = TS_Takedown_Input::validate($ok_in, $resolve, $H, function ($s) use (&$seen_mail) { $seen_mail[] = $s; return ''; });
check('メールアドレスの判定は渡した関数を使う (WordPress 側は is_email。偽の値なら誤り)', [$seen_mail, $v_inj['errors']],
      [[$ok_in['contact']], ['連絡先のメールアドレスを正しく書いてください。']]);
check('判定を渡さなければ filter_var', TS_Takedown_Input::validate($ok_in, $resolve, $H)['errors'], []);
check('運営委員宛の要約', TS_Takedown_Text::summary(['pen_name' => '山田', 'relation' => 'heir', 'allow_name' => '0',
      'targets' => [['url' => 'https://x/novel/a/', 'post_id' => 3], ['url' => 'https://y/', 'post_id' => null]]]),
      "ペンネーム: 山田\n立場: 権利継承者\n名前の掲示: 不可\n対象:\n  - https://x/novel/a/\n  - https://y/ (本サイトの作品に結びつかない)");

// ---------------------------------------------------------------- 申請の適用と復帰で触る投稿 (S127・S128、T45.1.17.3)
$rq_states = [
    1 => ['status' => 'publish', 'visibility' => '', 'takedown_id' => ''],                 // 公開中 → 止める
    2 => ['status' => 'private', 'visibility' => 'withdrawn', 'takedown_id' => 'S9'],     // 別の記録で取り下げ中 → 触らない
    3 => ['status' => 'private', 'visibility' => 'withdrawn', 'takedown_id' => 'req:12'],  // この申請で取り下げ済み → 揃え直す
    4 => ['status' => 'private', 'visibility' => 'superseded', 'takedown_id' => ''],       // 旧版 → 止める (withdrawn が優先。S117)
    5 => ['status' => 'draft', 'visibility' => '', 'takedown_id' => ''],                   // 移行前の取り下げ → 止める (private に移る)
    6 => ['status' => 'private', 'visibility' => 'withdrawn', 'takedown_id' => ''],        // 印の無い取り下げ → 別の記録とみなして触らない
];
check('適用: 別の記録で取り下げ中 (印が違う withdrawn) の投稿だけ飛ばし、ほかは渡した順に', TS_Takedown_Plan::request_apply($rq_states, 'req:12'), [1, 3, 4, 5]);
check('適用: 対象が無ければ空', TS_Takedown_Plan::request_apply([], 'req:12'), []);
check('復帰: この申請の印の投稿だけ戻し、印が違う withdrawn を left に数える (公開中・旧版・draft は数えない)',
      TS_Takedown_Plan::request_reinstate($rq_states, 'req:12'), ['posts' => [3], 'left' => 2]);
check('復帰: 印が全部この申請なら left は 0', TS_Takedown_Plan::request_reinstate([7 => $rq_states[3], 8 => $rq_states[3]], 'req:12'), ['posts' => [7, 8], 'left' => 0]);
check('復帰: 別の申請の印は戻さない (left に数える)', TS_Takedown_Plan::request_reinstate([9 => $rq_states[3]], 'req:13'), ['posts' => [], 'left' => 1]);
check('復帰: 対象が無ければ空', TS_Takedown_Plan::request_reinstate([], 'req:12'), ['posts' => [], 'left' => 0]);
