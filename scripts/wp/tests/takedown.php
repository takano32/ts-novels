<?php
/**
 * 取り下げフォームの判定と文面 (specs/S124.md〜S132.md、T26.3): パラメータ・入力の検証・スパム対策・
 * 状態の遷移・cron の判定・保持期限・/removed/ の 1 行と並び・メール。scripts/wp/ts_library_test.php から読む。
 */

$P = TS_Takedown_Rules::params();
check('既定のパラメータ', [$P['confirm_ttl_days'], $P['auto_apply_days'], $P['remind_before_days'], $P['retention_days'],
      $P['require_confirm'], $P['notify_to'], $P['send_reject_mail'], $P['rate_limit']], [7, 10, 3, 365, true, '', true, 3]);
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
check('運営委員宛の要約', TS_Takedown_Text::summary(['pen_name' => '山田', 'relation' => 'heir', 'allow_name' => '0',
      'targets' => [['url' => 'https://x/novel/a/', 'post_id' => 3], ['url' => 'https://y/', 'post_id' => null]]]),
      "ペンネーム: 山田\n立場: 権利継承者\n名前の掲示: 不可\n対象:\n  - https://x/novel/a/\n  - https://y/ (本サイトの作品に結びつかない)");
