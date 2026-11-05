<?php
/**
 * サイト全体の振る舞いの値と規則 (includes/site-rules.php、T45.1.18)。期待値は分割前 (0e8c7712) の ts-library.php の値。
 * scripts/wp/ts_library_test.php から読む。
 */

check('トップは作品をランダムに 12 件', TS_Site_Rules::FRONT_PAGE_QUERY,
      ['post_type' => 'ts_work', 'post_parent' => 0, 'posts_per_page' => 12, 'orderby' => 'rand', 'ignore_sticky_posts' => true]);
check('書庫は作品を発表順に 60 件', TS_Site_Rules::ARCHIVE_QUERY,
      ['post_type' => 'ts_work', 'post_parent' => 0, 'posts_per_page' => 60, 'orderby' => 'date', 'order' => 'ASC']);
check('検索は作品・運営文書・道場 (感想板は入れない)', TS_Site_Rules::SEARCH_POST_TYPES, ['ts_work', 'ts_doc', 'ts_dojo']);

check('「ブログ」見出しを「作品一覧」に', TS_Site_Rules::home_heading('<h2 class="wp-block-heading">ブログ</h2>'), '<h2 class="wp-block-heading">作品一覧</h2>');
check('ほかの見出しはそのまま', TS_Site_Rules::home_heading('<h2>ブログについて</h2>'), '<h2>ブログについて</h2>');

foreach (['執筆者:', '執筆者', 'カテゴリ:', '投稿者：', '投稿者', 'タグ'] as $txt) check("空のメタ行: $txt", TS_Site_Rules::is_empty_meta_row($txt), true);
foreach (['執筆者: 山田', 'カテゴリ: SF', '執筆者::', '本文', ''] as $txt) check("空のメタ行でない: $txt", TS_Site_Rules::is_empty_meta_row($txt), false);
