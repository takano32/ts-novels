<?php
/**
 * ファイルの構成と境目 (specs/S242.md)。scripts/wp/ts_library_test.php から読む。
 *  - includes/ の .php は全部 TS_Library_Files のどれかの表に 1 度だけ載っている (載っていないファイルは読まれない)
 *  - 表のファイルは実在する。読み込みに glob を使っていない
 *  - WP の表は site-hooks.php が先頭 (TS_Library::boot の pre_get_posts を索引より先に登録する)。CLI の表は register.php が最後
 *  - WordPress を使わない表のファイルは、WordPress の関数名を文字列として含まない (境目の機械的な検査)
 *  - テーマ (themes/ts-bunko) が呼ぶ ts_ の関数は、mu-plugin かテーマのどこかで定義されている
 *  - `wp ts` のサブコマンド名の集合は変わっていない
 */

$all = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$plugin/includes", FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (substr($f->getFilename(), -4) === '.php') $all[] = substr($f->getPathname(), strlen("$plugin/includes/"));
}
sort($all);
$listed = array_merge(TS_Library_Files::PURE, TS_Library_Files::WP, TS_Library_Files::CLI);
$listed_sorted = $listed; sort($listed_sorted);
check('includes の全ファイルが明示リストに載っている (files.php 自身を除く)', array_values(array_diff($all, ['files.php'])), $listed_sorted);
check('明示リストに重複が無い', count($listed), count(array_unique($listed)));
foreach ($listed as $f) check("実在する: $f", is_file("$plugin/includes/$f"), true);
$main = file_get_contents("$plugin/ts-library.php");
check('ts-library.php は glob を使わない', strpos($main, 'glob(') === false, true);
check('ts-library.php は files.php の表で読む', strpos($main, 'TS_Library_Files::PURE') !== false && strpos($main, 'TS_Library_Files::WP') !== false
      && strpos($main, 'TS_Library_Files::CLI') !== false, true);
check('WP の表の先頭は site-hooks.php', TS_Library_Files::WP[0], 'site-hooks.php');
check('index-routing.php は site-hooks.php より後', array_search('indexes/index-routing.php', TS_Library_Files::WP, true) > 0, true);
check('CLI の表の最後は register.php', TS_Library_Files::CLI[count(TS_Library_Files::CLI) - 1], 'commands/register.php');

// WordPress を使わない表の境目: WordPress の代表的な関数名が文字列として現れない (コメントも含めて)
$wp_names = ['get_post_meta(', 'update_post_meta(', 'get_option(', 'update_option(', 'add_action(', 'add_filter(',
             'get_posts(', 'wp_insert_post(', 'esc_html(', 'esc_url(', 'home_url(', 'get_permalink(', 'wp_date(', '$wpdb'];
foreach (TS_Library_Files::PURE as $f) {
    $src = file_get_contents("$plugin/includes/$f");
    $hits = array_values(array_filter($wp_names, function ($n) use ($src) { return strpos($src, $n) !== false; }));
    check("WordPress を使わない: $f", $hits, []);
}

// テーマが呼ぶ ts_ の関数は定義されている (関数名を変えたときにテンプレートが落ちないように)
$defined = [];
foreach (array_merge(glob("$plugin/includes/*.php"), glob("$plugin/includes/*/*.php"), glob("$root/themes/ts-bunko/*.php")) as $f) {
    if (preg_match_all('/^\s*function (ts_[a-z_0-9]+)\(/m', file_get_contents($f), $m)) foreach ($m[1] as $n) $defined[$n] = true;
}
$called = [];
foreach (glob("$root/themes/ts-bunko/*.php") as $f) {
    if (preg_match_all('/\b(ts_[a-z_0-9]+)\(/', file_get_contents($f), $m)) foreach ($m[1] as $n) $called[$n] = true;
}
// ts-index.php は 'ts_bunko_index_' . $kind の名前で呼ぶ
foreach (TS_INDEX_KINDS as $k) $called["ts_bunko_index_$k"] = true;
$undefined = array_values(array_filter(array_keys($called), function ($n) use ($defined) { return !isset($defined[$n]); }));
check('テーマが呼ぶ ts_ の関数は全部定義されている', $undefined, []);
$called_names = array_values(array_diff(array_keys($called), array_map(function ($k) { return "ts_bunko_index_$k"; }, TS_INDEX_KINDS)));
sort($called_names);
check('テーマが呼ぶ関数の一覧 (変えるときはテーマも直す)', $called_names,
      ['ts_bunko_author_links', 'ts_bunko_biblio_card', 'ts_bunko_breadcrumb', 'ts_bunko_catalog_notes', 'ts_bunko_episode_nav',
       'ts_bunko_hidden_banner', 'ts_bunko_illustrated_work_ids', 'ts_bunko_inline_nav', 'ts_bunko_meta_header', 'ts_bunko_osusume',
       'ts_bunko_sidebar', 'ts_bunko_suisen', 'ts_bunko_todays_pick', 'ts_bunko_work_toc', 'ts_bunko_workcard', 'ts_index_kind',
       'ts_index_year', 'ts_meta', 'ts_visibility_child_statuses']);

// wp ts のサブコマンド名 (登録を受ける WP_CLI の代わりに溜まったもの)
$names = array_keys(WP_CLI::$commands); sort($names);
check('wp ts のサブコマンド名', $names, ['ts apply-takedown', 'ts export-pages', 'ts export-pathmap', 'ts export-takedown-urls',
      'ts export-takedowns', 'ts import', 'ts reset', 'ts role-sync', 'ts sync-docs', 'ts sync-pages', 'ts sync-terms', 'ts verify']);
foreach (WP_CLI::$commands as $name => $callable) {
    check("$name の実装は public なメソッド", is_object($callable[0]) && (new ReflectionMethod($callable[0], $callable[1]))->isPublic(), true);
}
check('import の docblock (wp ts import --help) に OPTIONS がある', strpos((new ReflectionMethod('TS_Import_Command', 'import'))->getDocComment(), '## OPTIONS') !== false, true);
