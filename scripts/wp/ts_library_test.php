<?php
/**
 * mu-plugins/ts-library の WordPress を使わない部分の単体の試験 (T23.3、T45.1。構成は specs/S242.md)。
 *
 *   make test-php                         # サーバの PHP 8.0 で流す (scripts/wp/php_test.sh。開発機に PHP が無い)
 *   php scripts/wp/ts_library_test.php    # PHP があればどこでも
 *
 * includes/files.php の TS_Library_Files::PURE を WordPress なしで読み (読めること自体が「WordPress を使わない」
 * 境目の検査)、WP-CLI のコマンド (CLI) も登録を受けるだけの WP_CLI の代わりを置いて読む (構文と docblock の検査)。
 * 試験の本体は scripts/wp/tests/*.php に領域ごとに置き、ここに書いた順に読む。
 * 試すもの: ファイルの構成と境目・コンテンツモデルの名前と URL 基底・サイト全体の一覧の値と規則・ロールの差分と冪等 (S116)・
 * 可視性の判定と帯 (S117・S118)・apply-takedown の 1 投稿の変化と計画 (S121)・denylist の読み書き (S76・S132)・
 * 取り下げフォームの判定と文面 (S124〜S132)・catalog の索引と語彙と verify の期待値・固定ページの原本の形 (S109)・
 * eyecatch の再設定の判断 (S189)・表示の文言と索引の並び。
 * その後に WordPress の代わり (tests/wp-stubs.php。T45.1.14) を置いて WP の表を読み、WordPress に触る側も試す:
 * 代わりそのものの決まり・読み込み時に登録するフックと優先度の表 (S242)・表示部品の HTML・申請の非公開化と復帰 (S127・S128)・
 * 取り下げフォームの送信と確認リンクと cron (S124・S126・S130)・wp ts のコマンド (reset・apply-takedown)。
 * 失敗が 1 件でもあれば終了コード 1。
 */

define('TS_LIBRARY_TEST', true);
$root = dirname(__DIR__, 2);
$plugin = "$root/mu-plugins/ts-library";

require "$plugin/includes/files.php";
foreach (TS_Library_Files::PURE as $f) require "$plugin/includes/$f";

$fail = 0; $pass = 0;
function check($name, $got, $want) {
    global $fail, $pass;
    if ($got === $want) { $pass++; return; }
    $fail++;
    echo "NG $name\n  got:  " . var_export($got, true) . "\n  want: " . var_export($want, true) . "\n";
}

// WP-CLI のコマンドも読む (WordPress の関数は読み込み時には呼ばれない)。WP_CLI の代わりは登録を受け、出力 (line・warning・success) を
// $out に [種類, 文] で記録する。error は本物と同じく止まるように TS_CLI_Error を投げる (コマンドを流す試験は tests/commands-wp.php)
define('WP_CLI', true);
final class TS_CLI_Error extends RuntimeException {}
class WP_CLI {
    public static $commands = [];
    public static $out = [];
    public static function add_command($name, $callable) { self::$commands[$name] = $callable; }
    public static function line($msg = '') { self::$out[] = ['line', (string) $msg]; }
    public static function warning($msg) { self::$out[] = ['warning', (string) $msg]; }
    public static function success($msg) { self::$out[] = ['success', (string) $msg]; }
    public static function error($msg, $exit = true) { self::$out[] = ['error', (string) $msg]; throw new TS_CLI_Error((string) $msg); }
}
foreach (TS_Library_Files::CLI as $f) require "$plugin/includes/$f";

foreach (['files', 'content-model', 'site', 'roles', 'visibility', 'takedown', 'denylist', 'catalog', 'eyecatch', 'render', 'indexes'] as $t) {
    require "$root/scripts/wp/tests/$t.php";
}

// WordPress に触る側 (WP の表) は、WordPress の代わり (scripts/wp/tests/wp-stubs.php) を置いてから読む (T45.1.14)。PURE はここより前に
// WordPress なしで読めている (境目の検査)。代わりは出力の構造を固定できる程度のもので、エスケープの忠実さは見ない。
// 読み込み時に登録するフックは代わりが記録し、tests/hooks.php が S242 の表と比べる。置き場の作り方は tests/wp-fixtures.php。
require "$root/scripts/wp/tests/wp-stubs.php";
require "$root/scripts/wp/tests/wp-fixtures.php";
foreach (TS_Library_Files::WP as $f) require "$plugin/includes/$f";
foreach (['wp-stubs-check', 'hooks', 'render-html', 'takedown-wp', 'commands-wp'] as $t) {
    require "$root/scripts/wp/tests/$t.php";
}

echo "ts_library_test: pass=$pass fail=$fail\n";
exit($fail ? 1 : 0);
