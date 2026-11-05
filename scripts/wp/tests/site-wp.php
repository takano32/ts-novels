<?php
/**
 * サイト全体の経路を WordPress の代わり (scripts/wp/tests/wp-stubs.php) の上で流す (T45.6.5): トップと書庫の問い合わせ
 * (TS_Library::front_page_query / archive_query)、可視性の template_redirect (410・404・運営委員。specs/S117.md・S118.md)、
 * 年表とオススメの索引。期待値は分割前の 0e8c7712 の ts-library.php と includes 7 本を同じ代わりで動かした出力 (2026-11-05、scripts/wp/php_probe.sh、probe-rest.php)。
 * 場面と流し方は wp-fixtures.php の ts_fixture_run_* (probe も同じ関数を呼ぶ)。$same は先に読む render-html.php のもの。
 * scripts/wp/ts_library_test.php から WP の表を読んだ後に読む。
 */

// ---------------------------------------------------------------- トップと書庫の問い合わせ (S21・S37)
// 組ごとに [front_page_query の後の query_vars, archive_query の後の query_vars]。管理画面と本クエリでないものは触らない。
// 書庫は公開の taxonomy だけ (ts_corpus は除く)、検索は投稿タイプだけを set する (検索と taxonomy が重なれば検索の側)
$same('一覧の問い合わせ', ts_fixture_run_site_queries([TS_Library::class, 'front_page_query'], [TS_Library::class, 'archive_query']), array (
  'home' => 
  array (
    0 => 
    array (
      'post_type' => 'ts_work',
      'post_parent' => 0,
      'posts_per_page' => 12,
      'orderby' => 'rand',
      'ignore_sticky_posts' => true,
    ),
    1 => 
    array (
    ),
  ),
  'home_admin' => 
  array (
    0 => 
    array (
    ),
    1 => 
    array (
    ),
  ),
  'home_not_main' => 
  array (
    0 => 
    array (
    ),
    1 => 
    array (
    ),
  ),
  'tax_author' => 
  array (
    0 => 
    array (
    ),
    1 => 
    array (
      'post_type' => 'ts_work',
      'post_parent' => 0,
      'posts_per_page' => 60,
      'orderby' => 'date',
      'order' => 'ASC',
    ),
  ),
  'tax_world' => 
  array (
    0 => 
    array (
    ),
    1 => 
    array (
      'post_type' => 'ts_work',
      'post_parent' => 0,
      'posts_per_page' => 60,
      'orderby' => 'date',
      'order' => 'ASC',
    ),
  ),
  'tax_corpus' => 
  array (
    0 => 
    array (
    ),
    1 => 
    array (
    ),
  ),
  'tax_not_main' => 
  array (
    0 => 
    array (
    ),
    1 => 
    array (
    ),
  ),
  'tax_admin' => 
  array (
    0 => 
    array (
    ),
    1 => 
    array (
    ),
  ),
  'search' => 
  array (
    0 => 
    array (
    ),
    1 => 
    array (
      'post_type' => 
      array (
        0 => 'ts_work',
        1 => 'ts_doc',
        2 => 'ts_dojo',
      ),
    ),
  ),
  'search_tax' => 
  array (
    0 => 
    array (
    ),
    1 => 
    array (
      'post_type' => 
      array (
        0 => 'ts_work',
        1 => 'ts_doc',
        2 => 'ts_dojo',
      ),
    ),
  ),
  'search_admin' => 
  array (
    0 => 
    array (
    ),
    1 => 
    array (
    ),
  ),
  'plain' => 
  array (
    0 => 
    array (
    ),
    1 => 
    array (
    ),
  ),
));

// ---------------------------------------------------------------- 可視性の template_redirect@0 (S117・S118)
// 名前 => [記録 (nocache_headers・set_404・status_header), 410 の印 (TS_Visibility_Request::$gone), document_title_parts の題]。
// 読者には取り下げ (withdrawn の印。draft でも) が 410、旧版と印の無い非公開が 404。運営委員は private を帯つきで見る (no-cache だけ)。
// draft は運営委員にも出さない (WordPress の既定の 404 に任せる)。管理画面・/novel/ の外・無い作品・ts_work でないものは触らない
$redirect = null;
foreach (TS_WP_Stubs::callbacks('template_redirect') as $h) if ($h[2] === 0) $redirect = $h[4];
$same('可視性', ts_fixture_run_visibility($redirect, function () { return TS_Visibility_Request::$gone; },
    function () { TS_Visibility_Request::$gone = false; }), array (
  'publish/reader' => 
  array (
    0 => 
    array (
    ),
    1 => false,
    2 => '元の題',
  ),
  'publish/committee' => 
  array (
    0 => 
    array (
    ),
    1 => false,
    2 => '元の題',
  ),
  'withdrawn/reader' => 
  array (
    0 => 
    array (
      0 => 'nocache_headers',
      1 => 'set_404',
      2 => 'status_header(410)',
    ),
    1 => true,
    2 => '掲載していない作品',
  ),
  'withdrawn/committee' => 
  array (
    0 => 
    array (
      0 => 'nocache_headers',
    ),
    1 => false,
    2 => '元の題',
  ),
  'superseded/reader' => 
  array (
    0 => 
    array (
      0 => 'nocache_headers',
      1 => 'set_404',
      2 => 'status_header(404)',
    ),
    1 => false,
    2 => '元の題',
  ),
  'superseded/committee' => 
  array (
    0 => 
    array (
      0 => 'nocache_headers',
    ),
    1 => false,
    2 => '元の題',
  ),
  'draft/reader' => 
  array (
    0 => 
    array (
      0 => 'nocache_headers',
      1 => 'set_404',
      2 => 'status_header(404)',
    ),
    1 => false,
    2 => '元の題',
  ),
  'draft/committee' => 
  array (
    0 => 
    array (
    ),
    1 => false,
    2 => '元の題',
  ),
  'draft_withdrawn/reader' => 
  array (
    0 => 
    array (
      0 => 'nocache_headers',
      1 => 'set_404',
      2 => 'status_header(410)',
    ),
    1 => true,
    2 => '掲載していない作品',
  ),
  'draft_withdrawn/committee' => 
  array (
    0 => 
    array (
    ),
    1 => false,
    2 => '元の題',
  ),
  'private/reader' => 
  array (
    0 => 
    array (
      0 => 'nocache_headers',
      1 => 'set_404',
      2 => 'status_header(404)',
    ),
    1 => false,
    2 => '元の題',
  ),
  'private/committee' => 
  array (
    0 => 
    array (
      0 => 'nocache_headers',
    ),
    1 => false,
    2 => '元の題',
  ),
  'admin' => 
  array (
    0 => 
    array (
    ),
    1 => false,
    2 => '元の題',
  ),
  'outside' => 
  array (
    0 => 
    array (
    ),
    1 => false,
    2 => '元の題',
  ),
  'missing' => 
  array (
    0 => 
    array (
    ),
    1 => false,
    2 => '元の題',
  ),
  'doc' => 
  array (
    0 => 
    array (
    ),
    1 => false,
    2 => '元の題',
  ),
));

// ---------------------------------------------------------------- 年表とオススメの索引 ($wpdb の行から描く 2 つ。T10.3)
// 年表は TIMELINE_FROM (1990) より前の年を出さない。オススメは館内に解決できる参照をリンクに、できない参照を文字に、題の無い参照は
// 出さない。refs が空・直列化されていない値・無い投稿の行は飛ばし、1 行も出なければ「見つかりませんでした」
$same('年表とオススメの索引', ts_fixture_run_index_rows(), array (
  'timeline' => '<ul class="ts-index-list ts-timeline"><li><a href="https://novels.xwp.jp/year/1990/">1990年</a> <span class="ts-count">1 作品</span></li><li><a href="https://novels.xwp.jp/year/2001/">2001年</a> <span class="ts-count">12 作品</span></li><li><a href="https://novels.xwp.jp/year/2003/">2003年</a> <span class="ts-count">40 作品</span></li></ul><p class="ts-archive-desc">年は各作品の初出日 (連載は第 1 話) に基づきます。文庫の始まる前、1999 年の原型サイトの記録は <a href="https://novels.xwp.jp/index/docs/#sec-prehistory">文庫前史</a> にあります。</p>',
  'timeline/0' => '<ul class="ts-index-list ts-timeline"></ul><p class="ts-archive-desc">年は各作品の初出日 (連載は第 1 話) に基づきます。文庫の始まる前、1999 年の原型サイトの記録は <a href="https://novels.xwp.jp/index/docs/#sec-prehistory">文庫前史</a> にあります。</p>',
  'osusume' => '<p class="ts-archive-desc">当時の目録にあった「この作品を読んだ人へのオススメ」の繋がりです。</p><ul class="ts-index-list"><li><a href="https://novels.xwp.jp/novel/hikari-no-michi/hikari01/">第一話</a> → <a href="https://novels.xwp.jp/novel/hikari-no-michi/hikari03/">第三話へ</a>、館内に無い作品</li></ul>',
  'osusume/0' => '<p class="ts-archive-desc">当時の目録にあった「この作品を読んだ人へのオススメ」の繋がりです。</p><ul class="ts-index-list"></ul><p>オススメの記録は見つかりませんでした。</p>',
));
TS_WP_Stubs::reset();
