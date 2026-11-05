<?php
/**
 * 可視性の判定と帯 (specs/S117.md・S118.md)、apply-takedown の 1 投稿の変化 (S121)。scripts/wp/ts_library_test.php から読む。
 */

// ---------------------------------------------------------------- 要求パス (S118 の解決手順)
check('作品', TS_Visibility_Rules::page_path('novel/marie-foo'), 'marie-foo');
check('話', TS_Visibility_Rules::page_path('novel/marie-foo/ep01/'), 'marie-foo/ep01');
check('エンコード', TS_Visibility_Rules::page_path('novel/a%20b/c'), 'a b/c');
check('/novel/ の外', TS_Visibility_Rules::page_path('about'), null);
check('/novel/ だけ', TS_Visibility_Rules::page_path('novel'), null);
check('前方一致しない', TS_Visibility_Rules::page_path('novels/x'), null);

// ---------------------------------------------------------------- 応答の判定 (S118 の表)
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
    check("decide $st/$vis/" . ($c ? '運営委員' : '読者'), TS_Visibility_Rules::decide($st, $vis, $c), $want);
}

// ---------------------------------------------------------------- 帯 (S118)
check('帯 取り下げ (S)', TS_Visibility_Rules::banner_html('withdrawn', 'S235', '2001', ''),
      '<p class="ts-hidden-banner" role="note">取り下げ済み — 運営委員にのみ表示しています</p>');
check('帯 取り下げ (申請)', TS_Visibility_Rules::banner_html('withdrawn', 'req:12', '2001', ''),
      '<p class="ts-hidden-banner" role="note">取り下げ済み — 運営委員にのみ表示しています (申請 12)</p>');
check('帯 旧版', TS_Visibility_Rules::banner_html('superseded', '', '2002', 'https://novels.xwp.jp/novel/a/?x=1&y=2'),
      '<p class="ts-hidden-banner" role="note">旧版 (2002 年の掲載) — 運営委員にのみ表示しています'
      . ' → <a href="https://novels.xwp.jp/novel/a/?x=1&amp;y=2">新版へ</a></p>');
check('帯 理由なし', TS_Visibility_Rules::banner_html('', '', '', ''),
      '<p class="ts-hidden-banner" role="note">運営委員にのみ表示しています</p>');
check('S 番号は画面に出さない', TS_Visibility_Rules::takedown_label('S235'), '');

// ---------------------------------------------------------------- apply-takedown の変化 (S121)
$w = ['id' => 'S235', 'note' => 'Ｍａｒｉｅさんの作品 — 作者の申し出により掲載していません'];
check('draft → private (移行)', TS_Visibility_Rules::takedown_change(['status' => 'draft'], $w), [
    'kind' => 'private', 'status' => 'private',
    'set' => ['_ts_visibility' => 'withdrawn', '_ts_takedown_id' => 'S235', '_ts_public_note' => $w['note']],
    'delete' => []]);
check('publish → private', TS_Visibility_Rules::takedown_change(['status' => 'publish'], $w)['status'], 'private');
$done = ['status' => 'private', 'visibility' => 'withdrawn', 'takedown_id' => 'S235', 'public_note' => $w['note']];
check('揃っていれば変更なし', TS_Visibility_Rules::takedown_change($done, $w),
      ['kind' => 'none', 'status' => null, 'set' => [], 'delete' => []]);
check('メタだけ直す', TS_Visibility_Rules::takedown_change(array_merge($done, ['takedown_id' => 'S1']), $w),
      ['kind' => 'private', 'status' => null, 'set' => ['_ts_takedown_id' => 'S235'], 'delete' => []]);
check('public_note が無いエントリは掲示のメタを消す',
      TS_Visibility_Rules::takedown_change($done, ['id' => 'S235', 'note' => '']),
      ['kind' => 'private', 'status' => null, 'set' => [], 'delete' => ['_ts_public_note']]);
check('旧版の投稿を取り下げると withdrawn が優先',
      TS_Visibility_Rules::takedown_change(['status' => 'private', 'visibility' => 'superseded', 'superseded_by' => 'e1'],
          ['id' => 'S9', 'note' => ''])['set'], ['_ts_visibility' => 'withdrawn', '_ts_takedown_id' => 'S9']);
check('復帰 → publish', TS_Visibility_Rules::takedown_change($done, null), [
    'kind' => 'reinstate', 'status' => 'publish', 'set' => [],
    'delete' => ['_ts_takedown_id', '_ts_public_note', '_ts_visibility']]);
check('復帰 (旧版でもある) → private のまま旧版に',
      TS_Visibility_Rules::takedown_change(array_merge($done, ['superseded_by' => 'e9']), null), [
    'kind' => 'reinstate', 'status' => null, 'set' => ['_ts_visibility' => 'superseded'],
    'delete' => ['_ts_takedown_id', '_ts_public_note']]);
check('復帰 (移行前の draft)', TS_Visibility_Rules::takedown_change(['status' => 'draft'], null),
      ['kind' => 'reinstate', 'status' => 'publish', 'set' => [], 'delete' => []]);
check('復帰の対象でも印が無ければ変更なし', TS_Visibility_Rules::takedown_change(['status' => 'publish'], null)['kind'], 'none');
check('旧版だけの投稿は復帰で触らない',
      TS_Visibility_Rules::takedown_change(['status' => 'private', 'visibility' => 'superseded', 'superseded_by' => 'e1'], null)['kind'],
      'none');

// ---------------------------------------------------------------- 取り下げの日 (S131 の並び順)
check('取り下げの日を書く', TS_Visibility_Rules::takedown_change($done, array_merge($w, ['date' => '2026-10-26']))['set'],
      ['_ts_takedown_date' => '2026-10-26']);
check('復帰で取り下げの日も消す', TS_Visibility_Rules::takedown_change(array_merge($done, ['takedown_date' => '2026-10-26']), null)['delete'],
      ['_ts_takedown_id', '_ts_public_note', '_ts_takedown_date', '_ts_visibility']);
