<?php
/**
 * ファイルの構成と境目 (specs/S242.md)。scripts/wp/ts_library_test.php から読む。
 *  - includes/ の .php は全部 TS_Library_Files のどれかの表に 1 度だけ載っている (載っていないファイルは読まれない)
 *  - 表のファイルは実在する。読み込みに glob を使っていない
 *  - WP の表は site-hooks.php が先頭 (TS_Library::boot の pre_get_posts を索引より先に登録する)。CLI の表は register.php が最後
 *  - WordPress を使わない表のファイルは、PHP の組み込み関数と PURE の中で定義した関数しか呼ばない (token_get_all で集める境目の検査)
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

// WordPress を使わない表の境目 (T45.1.13): PURE の各ファイルの関数呼び出しを token_get_all で集め、PHP の組み込み関数でも
// PURE の中で定義した関数でもないものが無いことを見る。呼び出しは「直前が -> ?-> :: function new でない T_STRING の直後に (」。
// グローバル変数 $wpdb も使わない。
$ts_calls = function (string $src): array {
    $toks = array_values(array_filter(token_get_all($src), function ($t) {
        return !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }));
    $calls = [];
    foreach ($toks as $i => $t) {
        if (!is_array($t) || $t[0] !== T_STRING || ($toks[$i + 1] ?? null) !== '(') continue;
        $prev = $toks[$i - 1] ?? null;
        if (is_array($prev) && in_array($prev[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) continue;
        $calls[strtolower($t[1])] = true;
    }
    return array_keys($calls);
};
$ts_defined_in = function (string $src): array {
    $toks = array_values(array_filter(token_get_all($src), function ($t) { return !is_array($t) || $t[0] !== T_WHITESPACE; }));
    $names = []; $depth = 0;   // 波括弧の深さ 0 の定義だけ (クラスのメソッド名は関数として呼べないので数えない)
    foreach ($toks as $i => $t) {
        if ($t === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) $depth++;
        elseif ($t === '}') $depth--;
        elseif ($depth === 0 && is_array($t) && in_array($t[0], [T_FUNCTION, T_CLASS], true)
                && is_array($toks[$i + 1] ?? null) && $toks[$i + 1][0] === T_STRING) {
            $names[strtolower($toks[$i + 1][1])] = true;
        }
    }
    return array_keys($names);
};
check('定義の名前は外側の関数とクラスだけ', $ts_defined_in('<?php function f() { $x = "{$y}"; } final class C { public function m() {} } function g() {}'), ['f', 'c', 'g']);
check('境目の検査が呼び出しを拾う (メソッド・静的呼び出し・new・定義は除く)',
      $ts_calls('<?php get_post_meta(1); $a->b(); $a?->c(); X::y(); new Z(); function w() {} strlen("x"); /* esc_html( */'),
      ['get_post_meta', 'strlen']);
// 理由つきの許可: 名前 => 理由 (いまは無い。T45.1.20 で is_email を注入に替えて消した)
$ts_allowed = [];
$ts_internal = array_fill_keys(get_defined_functions()['internal'], true);
$ts_pure_defined = [];
foreach (TS_Library_Files::PURE as $f) foreach ($ts_defined_in(file_get_contents("$plugin/includes/$f")) as $n) $ts_pure_defined[$n] = true;
$ts_seen = 0; $ts_all_calls = [];
foreach (TS_Library_Files::PURE as $f) {
    $src = file_get_contents("$plugin/includes/$f");
    $calls = $ts_calls($src);
    $ts_seen += count($calls);
    foreach ($calls as $n) $ts_all_calls[$n] = true;
    $hits = array_values(array_filter($calls, function ($n) use ($ts_internal, $ts_pure_defined, $ts_allowed) {
        return !isset($ts_internal[$n]) && !isset($ts_pure_defined[$n]) && !isset($ts_allowed[$n]);
    }));
    $globals = array_values(array_filter(token_get_all($src), function ($t) { return is_array($t) && $t[0] === T_VARIABLE && $t[1] === '$wpdb'; }));
    check("WordPress を使わない: $f", [$hits, count($globals)], [[], 0]);
}
check('境目の検査が PURE の呼び出しを拾っている', $ts_seen > 50, true);
check('許可リストは使われているものだけ (外れたら消す)', array_values(array_diff(array_keys($ts_allowed), array_keys($ts_all_calls))), []);

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
foreach (TS_Index_Text::KINDS as $k) $called["ts_bunko_index_$k"] = true;
$undefined = array_values(array_filter(array_keys($called), function ($n) use ($defined) { return !isset($defined[$n]); }));
check('テーマが呼ぶ ts_ の関数は全部定義されている', $undefined, []);
$called_names = array_values(array_diff(array_keys($called), array_map(function ($k) { return "ts_bunko_index_$k"; }, TS_Index_Text::KINDS)));
sort($called_names);
check('テーマが呼ぶ関数の一覧 (変えるときはテーマも直す)', $called_names,
      ['ts_bunko_author_links', 'ts_bunko_biblio_card', 'ts_bunko_breadcrumb', 'ts_bunko_catalog_notes', 'ts_bunko_episode_nav',
       'ts_bunko_hidden_banner', 'ts_bunko_illustrated_work_ids', 'ts_bunko_inline_nav', 'ts_bunko_meta_header', 'ts_bunko_osusume',
       'ts_bunko_search_facets', 'ts_bunko_sidebar', 'ts_bunko_suisen', 'ts_bunko_todays_pick', 'ts_bunko_work_toc', 'ts_bunko_workcard',
       'ts_index_kind', 'ts_index_year', 'ts_meta', 'ts_visibility_child_statuses']);
// テーマが引く mu-plugin のクラスのメソッドと定数 (T45.1.5 で文言と表を TS_ のクラスに移した)。全部あること
$theme_refs = [];
foreach (glob("$root/themes/ts-bunko/*.php") as $f) {
    if (preg_match_all('/\b(TS_[A-Za-z_]+)::([A-Za-z_]+)(\()?/', file_get_contents($f), $m, PREG_SET_ORDER)) {
        foreach ($m as $x) $theme_refs[$x[1] . '::' . $x[2] . (isset($x[3]) ? '()' : '')] = isset($x[3]) ? method_exists($x[1], $x[2]) : defined("$x[1]::$x[2]");
    }
}
check('テーマが引く TS_ のクラスのメソッドと定数は全部ある', array_keys(array_filter($theme_refs, function ($ok) { return !$ok; })), []);
// テーマは mu-plugin が必ずある前提で、無いときの備え (function_exists・class_exists) を持たない (T45.7.1。決めは T45.6.3)
$theme_guards = [];
foreach (glob("$root/themes/ts-bunko/*.php") as $f) {
    if (preg_match('/\b(function_exists|class_exists)\s*\(/', file_get_contents($f))) $theme_guards[] = basename($f);
}
check('テーマに mu-plugin への備えが無い', $theme_guards, []);

// wp ts のサブコマンド名 (登録を受ける WP_CLI の代わりに溜まったもの)
$names = array_keys(WP_CLI::$commands); sort($names);
check('wp ts のサブコマンド名', $names, ['ts apply-takedown', 'ts export-pages', 'ts export-pathmap', 'ts export-takedown-urls',
      'ts export-takedowns', 'ts import', 'ts reset', 'ts role-sync', 'ts sync-docs', 'ts sync-pages', 'ts sync-terms', 'ts verify']);
foreach (WP_CLI::$commands as $name => $callable) {
    check("$name の実装は public なメソッド", is_object($callable[0]) && (new ReflectionMethod($callable[0], $callable[1]))->isPublic(), true);
}
check('import の docblock (wp ts import --help) に OPTIONS がある', strpos((new ReflectionMethod('TS_Import_Command', 'import'))->getDocComment(), '## OPTIONS') !== false, true);

// グローバル変数を使わない (T45.1.19.4。要求の中の状態はクラスの static プロパティに置く)
$with_globals = array_values(array_filter(array_merge(TS_Library_Files::PURE, TS_Library_Files::WP, TS_Library_Files::CLI), function ($f) use ($plugin) {
    foreach (token_get_all(file_get_contents("$plugin/includes/$f")) as $t) if (is_array($t) && $t[0] === T_VARIABLE && $t[1] === '$GLOBALS') return true;
    return false;
}));
check('$GLOBALS を使うファイルは無い', $with_globals, []);
