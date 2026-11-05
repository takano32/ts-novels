<?php
/**
 * 表示部品の HTML (specs/S41.md の書誌カードほか)。WordPress の代わり (scripts/wp/tests/wp-stubs.php) の上で includes/render/*.php を
 * 動かし、構造 (タグ・class・順・文言) を固定する。scripts/wp/ts_library_test.php から WP の表を読んだ後に読む。
 * 期待値は分割前 (d586f0eb) の includes/render.php を同じ代わりと同じ置き場 (wp-fixtures.php) で動かした出力から取る
 * (scripts/wp/php_probe.sh。実装の写しにしない)。T45.1.15.1 でほかの部品を足す。
 */

// ---------------------------------------------------------------- 書誌カード (S41)。期待値: d586f0eb の render.php、2026-11-05、probe-biblio.php
$ids = ts_fixture_biblio();
$card = function (int $id) { return TS_WP_Stubs::capture(function () use ($id) { ts_bunko_biblio_card(get_post($id)); }); };
check('書誌カード: 初出 (日付つき)・回収経路・原本リンク 2 つ・整形表示の注記。感想板の URL は出さない (S155 の判断待ち)', $card($ids['full']),
      '<aside class="ts-biblio" aria-label="書誌情報"><h2 class="ts-biblio-title">この作品について</h2><dl>'
      . '<dt>初出</dt><dd>ts.novels.jp/novel/200201/tsuki/moon01.htm (2002/01/13)</dd>'
      . '<dt>回収経路</dt><dd>Internet Archive Wayback Machine</dd>'
      . '<dt>原本アーカイブ</dt><dd><a href="https://example.com/annex/novel/200201/tsuki/moon01.htm">無改変の原本を読む</a><br>'
      . '<a href="https://example.com/annex-yays/novel/moon01.htm">初出時の姿 (2002年版)</a></dd></dl>'
      . '<p class="ts-biblio-note">この本文は読みやすさのため組版を整えた表示です。文章そのものは原本と機械照合で一致しています。</p></aside>');
check('書誌カード: source_path も annex_url も無ければ何も出さない', $card($ids['none']), '');
check('書誌カード: 原本リンクだけ・route_label の回収経路・原本の HTML の注記', $card($ids['annex_raw']),
      '<aside class="ts-biblio" aria-label="書誌情報"><h2 class="ts-biblio-title">この作品について</h2><dl>'
      . '<dt>回収経路</dt><dd>作者から提供された複製</dd>'
      . '<dt>原本アーカイブ</dt><dd><a href="https://example.com/annex/novel/200201/tsuki/moon03.htm">無改変の原本を読む</a></dd></dl>'
      . '<p class="ts-biblio-note">この本文は原本の HTML をそのまま表示しています。</p></aside>');
check('書誌カード: 初出のパスだけ (日付なし・知らない route はそのまま・注記なし)', $card($ids['bare']),
      '<aside class="ts-biblio" aria-label="書誌情報"><h2 class="ts-biblio-title">この作品について</h2><dl>'
      . '<dt>初出</dt><dd>ts.novels.jp/novel/200305/hoshi.htm</dd><dt>回収経路</dt><dd>ftp</dd></dl></aside>');
TS_WP_Stubs::reset();
