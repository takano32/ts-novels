<?php
/**
 * wp ts のコマンドを WordPress の代わり (scripts/wp/tests/wp-stubs.php) と WP_CLI の代わり (scripts/wp/ts_library_test.php) の上で
 * 流す (T45.1.7.5)。WP_CLI::$out に出力、TS_WP_Stubs::$log に消したもの・書いたものが残る。WP_CLI::error は TS_CLI_Error を投げる。
 * scripts/wp/ts_library_test.php から WP の表を読んだ後に読む。
 */

// ---------------------------------------------------------------- wp ts reset (S73 の C。申請は消さない: S125)
// 消す集合の期待値は分割前 (d586f0eb) の commands.php の TYPES と TAXES (投稿タイプ 4 つと taxonomy 6 つ) で、
// TS_Content_Model の定数とも一致することを見る
if (!post_type_exists('ts_work')) TS_WP_Stubs::run_init();
TS_WP_Stubs::reset();
foreach (['ts_work', 'ts_work', 'ts_doc', 'ts_dojo', 'ts_board_post', 'ts_takedown_request', 'page', 'post'] as $i => $type) {
    TS_WP_Stubs::add_post(['post_type' => $type, 'post_status' => $i % 2 ? 'private' : 'publish', 'post_title' => "$type $i"]);
}
foreach (['ts_author', 'ts_genre', 'ts_type', 'ts_keyword', 'ts_world', 'ts_corpus', 'ts_corpus', 'category'] as $i => $tax) {
    TS_WP_Stubs::add_term(['taxonomy' => $tax, 'name' => "$tax $i"]);
}
foreach (['ts_catalog_commit', 'ts_manual_edit_warnings', 'ts_term_warnings', 'ts_takedown_urls'] as $o) TS_WP_Stubs::$options[$o] = 'x';
$cmd = new TS_Reset_Command();

WP_CLI::$out = [];
$stopped = null;
try { $cmd->reset([], []); } catch (TS_CLI_Error $e) { $stopped = $e->getMessage(); }
check('reset: --yes が無ければ止まり、何も消さない', [$stopped, count(TS_WP_Stubs::$posts), count(TS_WP_Stubs::$terms), TS_WP_Stubs::$log],
      ['全削除には --yes が必要です (事前に wp db export を)', 8, 8, []]);

WP_CLI::$out = [];
$types = function () { return array_values(array_map(function ($p) { return $p->post_type; }, TS_WP_Stubs::$posts)); };
$before = TS_WP_Stubs::$posts;
$cmd->reset([], ['yes' => true]);
$deleted_types = []; $deleted_taxes = [];
foreach (TS_WP_Stubs::$log as [$fn, $a]) {
    if ($fn === 'wp_delete_post') $deleted_types[] = $before[$a[0]]->post_type . ($a[1] ? '' : ' (ゴミ箱)');
    if ($fn === 'wp_delete_term') $deleted_taxes[] = $a[1];
}
check('reset: 消した投稿の投稿タイプ (完全に消す)', $deleted_types, ['ts_work', 'ts_work', 'ts_doc', 'ts_dojo', 'ts_board_post']);
check('reset: 消した term の taxonomy', $deleted_taxes, ['ts_author', 'ts_genre', 'ts_type', 'ts_keyword', 'ts_world', 'ts_corpus', 'ts_corpus']);
check('reset: 消した集合 = TS_Content_Model::POST_TYPES と TAXONOMIES',
      [array_values(array_unique($deleted_types)), array_values(array_unique($deleted_taxes))],
      [TS_Content_Model::POST_TYPES, TS_Content_Model::TAXONOMIES]);
check('reset: 申請・固定ページ・投稿は残る', $types(), ['ts_takedown_request', 'page', 'post']);
check('reset: ts_ でない term は残る', array_values(array_map(function ($t) { return $t->taxonomy; }, TS_WP_Stubs::$terms)), ['category']);
check('reset: 消す option は 2 つ (語の警告と取り下げの URL は残る)', array_keys(TS_WP_Stubs::$options), ['ts_term_warnings', 'ts_takedown_urls']);
check('reset: 出力', WP_CLI::$out, [['success', 'reset: 投稿 5 件・term 7 件を削除 (WP 本体設定は無変更)']]);
WP_CLI::$out = [];
TS_WP_Stubs::reset();
