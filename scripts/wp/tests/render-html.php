<?php
/**
 * 表示部品の HTML (specs/S41.md の書誌カードほか)。WordPress の代わり (scripts/wp/tests/wp-stubs.php) の上で includes/render/*.php を
 * 動かし、構造 (タグ・class・順・文言) を固定する。scripts/wp/ts_library_test.php から WP の表を読んだ後に読む。
 * 期待値は分割前 (0e8c7712) の includes/render.php を同じ代わりと同じ置き場 (wp-fixtures.php) で動かした出力から取る
 * (scripts/wp/php_probe.sh。実装の写しにしない)。
 *
 * 部品が多いものは、場面 (wp-fixtures.php の関数) の上で部品 × 投稿の出力を「部品/投稿」の鍵の配列に集め、旧版の出力を
 * var_export したものと鍵ごとに比べる ($same)。probe は同じ集め方のコードを旧版のファイルの上で流したもの。
 */

// ---------------------------------------------------------------- 書誌カード (S41)。期待値: 0e8c7712 の render.php、2026-11-05、probe-biblio.php
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

/** 鍵の集合と、鍵ごとの値を比べる (1 鍵 1 件) */
$same = function (string $label, array $got, array $want) {
    check("$label: 鍵の集合", array_keys($got), array_keys($want));
    foreach ($want as $k => $v) check("$label: $k", $got[$k] ?? null, $v);
};
/** 部品 $fn を投稿 $id で呼んだ出力 */
$part = function (string $fn, int $id) { return TS_WP_Stubs::capture(function () use ($fn, $id) { $fn(get_post($id)); }); };

// ---------------------------------------------------------------- 作品・話のページの部品 8 つとサイドバー (T45.1.15.1)
// 期待値: 0e8c7712 の render.php と visibility.php (ts_visibility_child_statuses)、2026-11-05、probe-render-parts.php。
// 原パスの表と作者名の表 (render-lookup.php の static) はこの場面で初めて作られる (オススメ・関連ページ・挿絵のリンク)。
// サイドバーの検索窓はテーマの searchform.php なので、probe にも themes/ts-bunko を送った
$ids = ts_fixture_render_parts();
[$e0, $e1, $e2] = $ids['ep'];
$got = [];
foreach (['breadcrumb' => 'ts_bunko_breadcrumb', 'meta_header' => 'ts_bunko_meta_header', 'episode_nav' => 'ts_bunko_episode_nav',
          'work_toc' => 'ts_bunko_work_toc', 'suisen' => 'ts_bunko_suisen', 'catalog_notes' => 'ts_bunko_catalog_notes',
          'osusume' => 'ts_bunko_osusume', 'inline_nav' => 'ts_bunko_inline_nav'] as $k => $fn) {
    foreach (['e0' => $e0, 'e1' => $e1, 'e2' => $e2, 'serial' => $ids['serial'], 'single' => $ids['single']] as $pk => $pid) {
        $got["$k/$pk"] = $part($fn, $pid);
    }
}
$got['sidebar/none'] = TS_WP_Stubs::capture('ts_bunko_sidebar');
TS_WP_Stubs::$query_vars['ts_index'] = 'kana';
$got['sidebar/kana'] = TS_WP_Stubs::capture('ts_bunko_sidebar');
$same('表示部品', $got, array (
  'breadcrumb/e0' => '<nav class="ts-breadcrumb" aria-label="現在位置"><a href="https://novels.xwp.jp/authors/hoshino-akari/">星野 灯</a> › <a href="https://novels.xwp.jp/novel/hikari-no-michi/">光の道</a> › <span aria-current="page">第一話</span></nav>',
  'breadcrumb/e1' => '<nav class="ts-breadcrumb" aria-label="現在位置"><a href="https://novels.xwp.jp/novel/hikari-no-michi/">光の道</a> › <span aria-current="page">第二話</span></nav>',
  'breadcrumb/e2' => '<nav class="ts-breadcrumb" aria-label="現在位置"><a href="https://novels.xwp.jp/novel/hikari-no-michi/">光の道</a> › <span aria-current="page">第三話 &lt;後編&gt;</span></nav>',
  'breadcrumb/serial' => '<nav class="ts-breadcrumb" aria-label="現在位置"><a href="https://novels.xwp.jp/authors/hoshino-akari/">星野 灯</a> › <span aria-current="page">光の道</span></nav>',
  'breadcrumb/single' => '<nav class="ts-breadcrumb" aria-label="現在位置"><span aria-current="page">一人の夜</span></nav>',
  'meta_header/e0' => '<div class="ts-meta-header"><p class="ts-meta-bits">初出 2003/04/01　·　読了 約1分　·　挿絵 <a href="https://novels.xwp.jp/authors/efude-taro/">えふで</a>・名無しの絵師</p><p class="ts-chips"><a class="ts-chip ts-chip-ts_genre" href="https://novels.xwp.jp/genre/gakuen/">学園</a> <a class="ts-chip ts-chip-ts_type" href="https://novels.xwp.jp/type/irekawari/">入れ替わり</a> <a class="ts-chip ts-chip-ts_keyword" href="https://novels.xwp.jp/keyword/ame/">あめ</a> <a class="ts-chip ts-chip-ts_keyword" href="https://novels.xwp.jp/keyword/neko/">ねこ</a> <a class="ts-chip ts-chip-ts_world" href="https://novels.xwp.jp/world/hoshi-no-machi/">星の町 &amp; 海</a></p></div>',
  'meta_header/e1' => '<div class="ts-meta-header"><p class="ts-meta-bits">初出 2003/05/01</p></div>',
  'meta_header/e2' => '<div class="ts-meta-header"><p class="ts-meta-bits">初出 2003/06/01</p></div>',
  'meta_header/serial' => '<div class="ts-meta-header"></div>',
  'meta_header/single' => '<div class="ts-meta-header"></div>',
  'episode_nav/e0' => '<nav class="ts-episode-nav" aria-label="話の移動"><span class="ts-nav-prev is-empty"></span><a class="ts-nav-toc" href="https://novels.xwp.jp/novel/hikari-no-michi/">作品目次</a><a class="ts-nav-next" rel="next" href="https://novels.xwp.jp/novel/hikari-no-michi/hikari03/">第三話 &lt;後編&gt; →</a></nav>',
  'episode_nav/e1' => '<nav class="ts-episode-nav" aria-label="話の移動"><span class="ts-nav-prev is-empty"></span><a class="ts-nav-toc" href="https://novels.xwp.jp/novel/hikari-no-michi/">作品目次</a><span class="ts-nav-next is-empty"></span></nav>',
  'episode_nav/e2' => '<nav class="ts-episode-nav" aria-label="話の移動"><a class="ts-nav-prev" rel="prev" href="https://novels.xwp.jp/novel/hikari-no-michi/hikari01/">← 第一話</a><a class="ts-nav-toc" href="https://novels.xwp.jp/novel/hikari-no-michi/">作品目次</a><span class="ts-nav-next is-empty"></span></nav>',
  'episode_nav/serial' => '',
  'episode_nav/single' => '',
  'work_toc/e0' => '',
  'work_toc/e1' => '',
  'work_toc/e2' => '',
  'work_toc/serial' => '<ol class="ts-toc"><li><a href="https://novels.xwp.jp/novel/hikari-no-michi/hikari01/">第一話</a> <span class="ts-toc-date">2003/04/01</span></li><li><a href="https://novels.xwp.jp/novel/hikari-no-michi/hikari03/">第三話 &lt;後編&gt;</a> <span class="ts-toc-date">2003/06/01</span></li></ol>',
  'work_toc/single' => '',
  'suisen/e0' => '<aside class="ts-suisen"><h2 class="ts-suisen-title">目録の推薦文</h2><blockquote>胸が「熱く」なる &lt;推薦&gt;</blockquote></aside>',
  'suisen/e1' => '',
  'suisen/e2' => '',
  'suisen/serial' => '',
  'suisen/single' => '',
  'catalog_notes/e0' => '<div class="ts-catalog-notes"><p class="ts-arasuji"><span class="ts-label">あらすじ</span>ある朝、光の道が現れた。</p><p class="ts-author-comment"><span class="ts-label">作者より</span>読んでくれてありがとう &amp; またね</p></div>',
  'catalog_notes/e1' => '',
  'catalog_notes/e2' => '',
  'catalog_notes/serial' => '',
  'catalog_notes/single' => '',
  'osusume/e0' => '<aside class="ts-osusume"><h2 class="ts-osusume-title">この作品からのオススメ</h2><p class="ts-osusume-by">読者 一 さんより</p><ul><li><a href="https://novels.xwp.jp/novel/hikari-no-michi/hikari03/">第三話へ</a> <span class="ts-osusume-author">(星野 灯)</span></li><li>館内に無い作品</li><li><a href="https://novels.xwp.jp/novel/hikari-no-michi/">novel/title/hikari.htm</a></li></ul></aside>',
  'osusume/e1' => '',
  'osusume/e2' => '',
  'osusume/serial' => '',
  'osusume/single' => '',
  'inline_nav/e0' => '<nav class="ts-inline-nav" aria-label="この作品の関連ページ"><a href="https://novels.xwp.jp/novel/hikari-no-michi/hikari03/">次の話へ</a>　·　<a href="https://novels.xwp.jp/novel/hikari-no-michi/">光の道</a></nav>',
  'inline_nav/e1' => '',
  'inline_nav/e2' => '',
  'inline_nav/serial' => '',
  'inline_nav/single' => '',
  'sidebar/none' => '<aside class="ts-sidebar" aria-label="さがす"><div class="ts-sidebar-block"><h2 class="ts-sidebar-title">さがす</h2><form role="search" method="get" class="ts-searchform" action="https://novels.xwp.jp/">
  <label><span class="screen-reader-text">検索語</span>
    <input type="search" name="s" value="" placeholder="題名・本文を検索">
  </label>
  <button type="submit">検索</button>
</form>
</div><nav class="ts-sidebar-block"><h2 class="ts-sidebar-title">索引</h2><ul><li><a href="https://novels.xwp.jp/index/kana/">作者索引</a></li><li><a href="https://novels.xwp.jp/index/timeline/">年表</a></li><li><a href="https://novels.xwp.jp/index/bunrui/">分類索引</a></li><li><a href="https://novels.xwp.jp/index/vocabulary/">キーワード索引</a></li><li><a href="https://novels.xwp.jp/index/docs/">運営文書・資料</a></li></ul></nav><div class="ts-sidebar-block"><h2 class="ts-sidebar-title">この文庫について</h2><ul><li><a href="https://novels.xwp.jp/authors/yays/">オーナー作品集</a></li><li><a href="https://novels.xwp.jp/about/">このサイトについて</a></li><li><a href="https://novels.xwp.jp/archive/">原本アーカイブ</a></li><li><a href="https://novels.xwp.jp/takedown/">掲載の取り下げ</a></li></ul></div></aside>',
  'sidebar/kana' => '<aside class="ts-sidebar" aria-label="さがす"><div class="ts-sidebar-block"><h2 class="ts-sidebar-title">さがす</h2><form role="search" method="get" class="ts-searchform" action="https://novels.xwp.jp/">
  <label><span class="screen-reader-text">検索語</span>
    <input type="search" name="s" value="" placeholder="題名・本文を検索">
  </label>
  <button type="submit">検索</button>
</form>
</div><nav class="ts-sidebar-block"><h2 class="ts-sidebar-title">索引</h2><ul><li><a href="https://novels.xwp.jp/index/kana/" aria-current="page">作者索引</a></li><li><a href="https://novels.xwp.jp/index/timeline/">年表</a></li><li><a href="https://novels.xwp.jp/index/bunrui/">分類索引</a></li><li><a href="https://novels.xwp.jp/index/vocabulary/">キーワード索引</a></li><li><a href="https://novels.xwp.jp/index/docs/">運営文書・資料</a></li></ul></nav><div class="ts-sidebar-block"><h2 class="ts-sidebar-title">この文庫について</h2><ul><li><a href="https://novels.xwp.jp/authors/yays/">オーナー作品集</a></li><li><a href="https://novels.xwp.jp/about/">このサイトについて</a></li><li><a href="https://novels.xwp.jp/archive/">原本アーカイブ</a></li><li><a href="https://novels.xwp.jp/takedown/">掲載の取り下げ</a></li></ul></div></aside>',
));
TS_WP_Stubs::reset();

// ---------------------------------------------------------------- 運営委員にだけ見える帯と、目次・話ナビに並べる子の状態 (S118。T45.1.15.2)
// 期待値: 0e8c7712 の visibility.php と render.php、2026-11-05、probe-hidden.php。読者 (権限なし) と運営委員 (ts_view_hidden) で、
// 帯 (取り下げ済み・申請の番号つき・旧版で新版あり・なし・連載の親の旧版・印なし) と ts_visibility_child_statuses と目次・話ナビ・パンくず
$h = ts_fixture_hidden();
$got = [];
foreach (['reader', 'committee'] as $who) {
    if ($who === 'committee') { TS_WP_Stubs::$current_user = 3; TS_WP_Stubs::$caps = ['ts_view_hidden']; }
    foreach ($h as $k => $pid) {
        $got["banner/$who/$k"] = $part('ts_bunko_hidden_banner', $pid);
        $got["child_statuses/$who/$k"] = ts_visibility_child_statuses($pid);
    }
    $got["toc/$who/withdrawn"] = $part('ts_bunko_work_toc', $h['withdrawn']);
    $got["nav/$who/w_pub"] = $part('ts_bunko_episode_nav', $h['w_pub']);
    $got["breadcrumb/$who/w_priv"] = $part('ts_bunko_breadcrumb', $h['w_priv']);
}
$page = TS_WP_Stubs::add_post(['post_type' => 'page', 'post_status' => 'private', 'post_name' => 'p', 'post_title' => '固定']);
$got['banner/committee/page'] = $part('ts_bunko_hidden_banner', $page);
$same('帯と子の状態', $got, array (
  'banner/reader/withdrawn' => '',
  'child_statuses/reader/withdrawn' => 
  array (
    0 => 'publish',
  ),
  'banner/reader/w_pub' => '',
  'child_statuses/reader/w_pub' => 
  array (
    0 => 'publish',
  ),
  'banner/reader/w_priv' => '',
  'child_statuses/reader/w_priv' => 
  array (
    0 => 'publish',
  ),
  'banner/reader/new_ep' => '',
  'child_statuses/reader/new_ep' => 
  array (
    0 => 'publish',
  ),
  'banner/reader/sup_ep' => '',
  'child_statuses/reader/sup_ep' => 
  array (
    0 => 'publish',
  ),
  'banner/reader/sup_ep_none' => '',
  'child_statuses/reader/sup_ep_none' => 
  array (
    0 => 'publish',
  ),
  'banner/reader/new_work' => '',
  'child_statuses/reader/new_work' => 
  array (
    0 => 'publish',
  ),
  'banner/reader/sup_work' => '',
  'child_statuses/reader/sup_work' => 
  array (
    0 => 'publish',
  ),
  'banner/reader/plain' => '',
  'child_statuses/reader/plain' => 
  array (
    0 => 'publish',
  ),
  'banner/reader/public' => '',
  'child_statuses/reader/public' => 
  array (
    0 => 'publish',
  ),
  'toc/reader/withdrawn' => '<ol class="ts-toc"><li><a href="https://novels.xwp.jp/novel/kieta-tsuki/k01/">前編</a></li></ol>',
  'nav/reader/w_pub' => '<nav class="ts-episode-nav" aria-label="話の移動"><span class="ts-nav-prev is-empty"></span><a class="ts-nav-toc" href="https://novels.xwp.jp/novel/kieta-tsuki/">作品目次</a><span class="ts-nav-next is-empty"></span></nav>',
  'breadcrumb/reader/w_priv' => '<nav class="ts-breadcrumb" aria-label="現在位置"><a href="https://novels.xwp.jp/novel/kieta-tsuki/">消えた月</a> › <span aria-current="page">後編</span></nav>',
  'banner/committee/withdrawn' => '<p class="ts-hidden-banner" role="note">取り下げ済み — 運営委員にのみ表示しています</p>',
  'child_statuses/committee/withdrawn' => 
  array (
    0 => 'publish',
    1 => 'private',
  ),
  'banner/committee/w_pub' => '',
  'child_statuses/committee/w_pub' => 
  array (
    0 => 'publish',
  ),
  'banner/committee/w_priv' => '<p class="ts-hidden-banner" role="note">取り下げ済み — 運営委員にのみ表示しています (申請 12)</p>',
  'child_statuses/committee/w_priv' => 
  array (
    0 => 'publish',
    1 => 'private',
  ),
  'banner/committee/new_ep' => '',
  'child_statuses/committee/new_ep' => 
  array (
    0 => 'publish',
  ),
  'banner/committee/sup_ep' => '<p class="ts-hidden-banner" role="note">旧版 (1999 年の掲載) — 運営委員にのみ表示しています → <a href="https://novels.xwp.jp/novel/shinpan/">新版へ</a></p>',
  'child_statuses/committee/sup_ep' => 
  array (
    0 => 'publish',
    1 => 'private',
  ),
  'banner/committee/sup_ep_none' => '<p class="ts-hidden-banner" role="note">旧版 (2000 年の掲載) — 運営委員にのみ表示しています</p>',
  'child_statuses/committee/sup_ep_none' => 
  array (
    0 => 'publish',
    1 => 'private',
  ),
  'banner/committee/new_work' => '',
  'child_statuses/committee/new_work' => 
  array (
    0 => 'publish',
  ),
  'banner/committee/sup_work' => '<p class="ts-hidden-banner" role="note">旧版 (2000 年の掲載) — 運営委員にのみ表示しています → <a href="https://novels.xwp.jp/novel/atarashii-renzoku/">新版へ</a></p>',
  'child_statuses/committee/sup_work' => 
  array (
    0 => 'publish',
    1 => 'private',
  ),
  'banner/committee/plain' => '<p class="ts-hidden-banner" role="note">運営委員にのみ表示しています</p>',
  'child_statuses/committee/plain' => 
  array (
    0 => 'publish',
    1 => 'private',
  ),
  'banner/committee/public' => '',
  'child_statuses/committee/public' => 
  array (
    0 => 'publish',
  ),
  'toc/committee/withdrawn' => '<ol class="ts-toc"><li><a href="https://novels.xwp.jp/novel/kieta-tsuki/k01/">前編</a></li><li><a href="https://novels.xwp.jp/novel/kieta-tsuki/k02/">後編</a> <span class="ts-toc-date">2001/03/04</span></li></ol>',
  'nav/committee/w_pub' => '<nav class="ts-episode-nav" aria-label="話の移動"><span class="ts-nav-prev is-empty"></span><a class="ts-nav-toc" href="https://novels.xwp.jp/novel/kieta-tsuki/">作品目次</a><a class="ts-nav-next" rel="next" href="https://novels.xwp.jp/novel/kieta-tsuki/k02/">後編 →</a></nav>',
  'breadcrumb/committee/w_priv' => '<nav class="ts-breadcrumb" aria-label="現在位置"><a href="https://novels.xwp.jp/novel/kieta-tsuki/">消えた月</a> › <span aria-current="page">後編</span></nav>',
  'banner/committee/page' => '',
));
TS_WP_Stubs::reset();

// ---------------------------------------------------------------- 索引の描画部品 4 つ (T10.3。T45.1.15.4)
// 期待値: 0e8c7712 の indexes.php、2026-11-05、probe-indexes.php。作品数 (ts_bunko_work_counts) は taxonomy ごとに 1 プロセスに 1 度
// だけ $wpdb から数えるので、この場面で初めて数える。積んだ行の組が意図した taxonomy に当たったことを問い合わせの列で見る
ts_fixture_indexes();
$got = [];
foreach (['kana', 'bunrui', 'vocabulary', 'docs'] as $k) $got["index/$k"] = TS_WP_Stubs::capture('ts_bunko_index_' . $k);
$got['queries'] = array_map(function ($q) { return preg_match("/tt\\.taxonomy = '([a-z_]+)'/", $q, $m) ? $m[1] : '?'; }, TS_WP_Stubs::$db_queries);
$same('索引', $got, array (
  'index/kana' => '<nav class="ts-kana-rows"><a href="#kana-%E3%81%82%E8%A1%8C">あ行</a> <a href="#kana-%E3%81%8B%E8%A1%8C">か行</a> <a href="#kana-B">B</a> <a href="#kana-%E2%98%86">☆</a> <a href="#kana-%E4%BB%96">他</a> </nav><h2 id="kana-%E3%81%82%E8%A1%8C" class="ts-archive-title">あ行</h2><ul class="ts-index-list"><li><a href="https://novels.xwp.jp/authors/aoi-sora/">青井 空</a> <span class="ts-count">(3)</span></li><li><a href="https://novels.xwp.jp/authors/ichikawa-hajime/">市川 一</a> <span class="ts-count">(1)</span></li></ul><h2 id="kana-%E3%81%8B%E8%A1%8C" class="ts-archive-title">か行</h2><ul class="ts-index-list"><li><a href="https://novels.xwp.jp/authors/kawabe-nagare/">川辺 &lt;流&gt;</a> <span class="ts-count">(2)</span></li></ul><h2 id="kana-B" class="ts-archive-title">B</h2><ul class="ts-index-list"><li><a href="https://novels.xwp.jp/authors/bob/">Bob</a> <span class="ts-count">(1)</span></li></ul><h2 id="kana-%E2%98%86" class="ts-archive-title">☆</h2><ul class="ts-index-list"><li><a href="https://novels.xwp.jp/authors/kigou/">記号 ☆</a> <span class="ts-count">(1)</span></li></ul><h2 id="kana-%E4%BB%96" class="ts-archive-title">他</h2><ul class="ts-index-list"><li><a href="https://novels.xwp.jp/authors/yomi-nashi/">読みなし</a> <span class="ts-count">(1)</span></li></ul>',
  'index/bunrui' => '<h2 class="ts-archive-title">ジャンル</h2><ul class="ts-index-list"><li><a href="https://novels.xwp.jp/genre/fantasy/">ファンタジー</a> <span class="ts-count">(6)</span></li><li><a href="https://novels.xwp.jp/genre/gakuen/">学園</a> <span class="ts-count">(2)</span> — 学校が舞台 &amp; 寮</li></ul><h2 class="ts-archive-title">変身のかたち</h2><ul class="ts-index-list"><li><a href="https://novels.xwp.jp/type/irekawari/">入れ替わり</a> <span class="ts-count">(3)</span></li></ul><h2 class="ts-archive-title">共有世界</h2><ul class="ts-index-list"></ul>',
  'index/vocabulary' => '<p class="ts-archive-desc">当時の目録で使われていたキーワードの全語彙です。数字はそのキーワードが付いた作品の数です。</p><p class="ts-chips"><a class="ts-chip" href="https://novels.xwp.jp/keyword/ame/">あめ 8</a> <a class="ts-chip" href="https://novels.xwp.jp/keyword/neko/">ねこ 5</a> </p>',
  'index/docs' => '<h2 id="sec-gallery" class="ts-archive-title">ギャラリー</h2><ul class="ts-index-list"><li><a href="https://novels.xwp.jp/docs/gallery-1/">挿絵の部屋</a></li></ul><h2 id="sec-columns" class="ts-archive-title">コラム</h2><ul class="ts-index-list"><li><a href="https://novels.xwp.jp/docs/column-a/">コラム A &amp; 序</a></li><li><a href="https://novels.xwp.jp/docs/column-b/">コラム B</a></li></ul><h2 id="sec-prehistory" class="ts-archive-title">文庫前史 (1999)</h2><ul class="ts-index-list"><li><a href="https://novels.xwp.jp/docs/prehistory/">1999 年の原型</a></li></ul><h2 id="sec-notes" class="ts-archive-title">解題</h2><ul class="ts-index-list"><li><a href="https://novels.xwp.jp/docs/kaidai/">解題 (区分なし)</a></li></ul>',
  'queries' => 
  array (
    0 => 'ts_author',
    1 => 'ts_genre',
    2 => 'ts_type',
    3 => 'ts_world',
    4 => 'ts_keyword',
  ),
));
TS_WP_Stubs::reset();

// ---------------------------------------------------------------- 一覧の作品カード (T45.1.5.1 でテーマから includes/render/render-list.php へ)
// 期待値: 0e8c7712 の themes/ts-bunko/functions.php (と render.php・visibility.php)、2026-11-05、probe-workcard.php。
// 話数メタ 3・メタなしで公開の子 2 (private の子は数えない)・メタ 1・単発・何も無い・非公開 (題名に「非公開: 」を付けない)
$ids = ts_fixture_theme();
$got = [];
foreach (['serial', 'nometa', 'one', 'single', 'bare', 'hidden'] as $k) {
    $got["workcard/$k"] = TS_WP_Stubs::capture(function () use ($ids, $k) { ts_bunko_workcard(get_post($ids[$k])); });
}
$same('作品カード', $got, array (
  'workcard/serial' => '<li class="ts-workcard"><h2 class="ts-workcard-title"><a href="https://novels.xwp.jp/novel/hikari-no-michi/">光の道</a></h2><p class="ts-workcard-meta">星野 灯　·　2003/04/01　·　全3話</p></li>',
  'workcard/nometa' => '<li class="ts-workcard"><h2 class="ts-workcard-title"><a href="https://novels.xwp.jp/novel/yoru-no-kawa/">夜の川</a></h2><p class="ts-workcard-meta">絵筆 太郎　·　2004/07/07　·　全2話</p></li>',
  'workcard/one' => '<li class="ts-workcard"><h2 class="ts-workcard-title"><a href="https://novels.xwp.jp/novel/hitotsu/">一つきり</a></h2><p class="ts-workcard-meta">2005/01/01</p></li>',
  'workcard/single' => '<li class="ts-workcard"><h2 class="ts-workcard-title"><a href="https://novels.xwp.jp/novel/hitori-no-yoru/">一人の夜 &amp; 朝</a></h2><p class="ts-workcard-meta">星野 灯</p></li>',
  'workcard/bare' => '<li class="ts-workcard"><h2 class="ts-workcard-title"><a href="https://novels.xwp.jp/novel/nanimonai/">何もない</a></h2></li>',
  'workcard/hidden' => '<li class="ts-workcard"><h2 class="ts-workcard-title"><a href="https://novels.xwp.jp/novel/kieta/">消えた話</a></h2><p class="ts-workcard-meta">星野 灯　·　2001/05/05</p></li>',
));
TS_WP_Stubs::reset();

// ---------------------------------------------------------------- 検索結果のファセット (T10.4。T45.1.5.3 で index.php から includes/render/render-list.php へ)
// 期待値: 0e8c7712 の themes/ts-bunko/index.php:9-26 (移す前まで変わっていない) を同じ代わりと置き場 (ts_fixture_theme) で描いた検索結果の
// ページから、検索窓と一覧の間を切り出したもの (2026-11-05、probe-theme.php)。選んでいる語 (✕ で外す) あり・なし
ts_fixture_theme();
$_SERVER['REQUEST_URI'] = '/?s=%E5%85%89&ts_genre=gakuen';
TS_WP_Stubs::$query_vars = ['s' => '光 & <影>', 'ts_genre' => 'gakuen'];
check('ファセット: ジャンルを選んでいる', TS_WP_Stubs::capture('ts_bunko_search_facets'),
      '<p class="ts-chips"><span class="ts-count">ジャンル:</span> <a class="ts-chip" href="/?s=%E5%85%89">✕ gakuen</a> '
      . '<a class="ts-chip" href="/?s=%E5%85%89&#038;ts_genre=fantasy">ファンタジー</a> </p>'
      . '<p class="ts-chips"><span class="ts-count">変身のかたち:</span> <a class="ts-chip" href="/?s=%E5%85%89&#038;ts_genre=gakuen&#038;ts_type=irekawari">入れ替わり</a> </p>');
TS_WP_Stubs::$query_vars = ['s' => 'なし'];
check('ファセット: 何も選んでいない (件数の多い順)', TS_WP_Stubs::capture('ts_bunko_search_facets'),
      '<p class="ts-chips"><span class="ts-count">ジャンル:</span> <a class="ts-chip" href="/?s=%E5%85%89&#038;ts_genre=fantasy">ファンタジー</a> '
      . '<a class="ts-chip" href="/?s=%E5%85%89&#038;ts_genre=gakuen">学園</a> </p>'
      . '<p class="ts-chips"><span class="ts-count">変身のかたち:</span> <a class="ts-chip" href="/?s=%E5%85%89&#038;ts_genre=gakuen&#038;ts_type=irekawari">入れ替わり</a> </p>');
$_SERVER['REQUEST_URI'] = '/';
TS_WP_Stubs::reset();

// ---------------------------------------------------------------- 索引と年別一覧の題名 (document_title_parts。T45.1.5.4 で TS_Index_Text::TITLES と year_title に)
// 期待値: 0e8c7712 の indexes.php と visibility.php の document_title_parts、2026-11-05、probe-title.php
$titles = [];
foreach ([['ts_index' => 'kana'], ['ts_index' => 'osusume'], ['ts_index' => 'nai'], ['ts_year' => '2003'], ['ts_year' => '03'], []] as $qv) {
    TS_WP_Stubs::reset();
    TS_WP_Stubs::$query_vars = $qv;
    $titles[] = apply_filters('document_title_parts', ['title' => '元の題', 'site' => '少年少女文庫'])['title'];
}
check('題名: 索引 2 つ・知らない種類・年・4 桁でない年・どちらでもない', $titles, ['作者索引', 'オススメの環', '元の題', '2003年の作品', '元の題', '元の題']);
TS_WP_Stubs::reset();
