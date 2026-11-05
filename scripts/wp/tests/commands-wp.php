<?php
/**
 * wp ts のコマンドを WordPress の代わり (scripts/wp/tests/wp-stubs.php) と WP_CLI の代わり (scripts/wp/ts_library_test.php) の上で
 * 流す (T45.1.7.5。wp ts reset・import の本文の選び方・apply-takedown)。WP_CLI::$out に出力、TS_WP_Stubs::$log に消したもの・書いたものが残る。WP_CLI::error は TS_CLI_Error を投げる。
 * scripts/wp/ts_library_test.php から WP の表を読んだ後に読む。
 */

// ---------------------------------------------------------------- wp ts reset (S73 の C。申請は消さない: S125)
// 消す集合の期待値は分割前 (0e8c7712) の commands.php の TYPES と TAXES (投稿タイプ 4 つと taxonomy 6 つ) で、
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

// ---------------------------------------------------------------- wp ts import の本文の選び方 (T45.6.7)
// payloads/_meta.json の値が文字列でない (配列・数値) ときも止まらず、取り下げでない扱いで進む。期待値は分割前 (0e8c7712) の
// commands.php の TS_Command::episode_body を同じ代わりで動かした出力 (2026-11-05、scripts/wp/php_probe.sh。probe は body_status を
// ReflectionProperty で置き、episode_body を ReflectionMethod で呼んだ)
$body_dir = sys_get_temp_dir() . '/ts-import-body-' . getmypid();
mkdir($body_dir);
file_put_contents("$body_dir/e-file.html", '<p>本文</p>');
$cmd = new TS_Import_Command();
$prop = new ReflectionProperty(TS_Import_Command::class, 'body_status'); $prop->setAccessible(true);
$m = new ReflectionMethod(TS_Import_Command::class, 'episode_body'); $m->setAccessible(true);
$prop->setValue($cmd, ['e-none' => ['takedown'], 'e-file' => ['takedown'], 'e-int' => 1, 'e-td' => 'takedown']);
$got = [];
foreach (['e-none', 'e-file', 'e-int', 'e-td'] as $id) $got[$id] = $m->invoke($cmd, ['episode_id' => $id], $body_dir);
$got['meta-only'] = $m->invoke($cmd, ['episode_id' => 'e-none'], null);
check('import: _meta.json の値が配列・数値でも止まらず、ペイロードか案内文になる', $got, [
    'e-none' => '<!-- wp:paragraph --><p>(本文は原本アーカイブでお読みください)</p><!-- /wp:paragraph -->',
    'e-file' => '<p>本文</p>',
    'e-int' => '<!-- wp:paragraph --><p>(本文は原本アーカイブでお読みください)</p><!-- /wp:paragraph -->',
    'e-td' => '',
    'meta-only' => null,
]);
unlink("$body_dir/e-file.html"); rmdir($body_dir);

// ---------------------------------------------------------------- wp ts apply-takedown (S121。T45.6.4)
// 期待値: 0e8c7712 の includes 7 本 (TS_Command::apply_takedown) を同じ代わりで動かした出力 (2026-11-05、scripts/wp/php_probe.sh、
// probe-paths.php)。場面と流し方は wp-fixtures.php の ts_fixture_apply_takedown_scene と ts_fixture_run_apply_takedown (probe も同じ関数を
// 呼ぶ)。回ごとに [WP_CLI::$out, 投稿の状態, option ts_takedown_urls, wp_update_post の数]。dry-run は何も書かず、適用で連載 4 投稿を
// private に・単発を publish に戻し、2 回目は変更なし。denylist に無い印は S5 を警告し、申請 (req:7) の分は URL を残す
if (!post_type_exists('ts_work')) TS_WP_Stubs::run_init();
$repo = sys_get_temp_dir() . '/ts-apply-takedown-' . getmypid();
mkdir($repo);
$t = ts_fixture_apply_takedown_scene($repo);
check('apply-takedown: 場面の ID (期待値の post 番号はこれに依る)', $t, array (
  'serial' => 1,
  'ep' => 
  array (
    0 => 2,
    1 => 3,
    2 => 4,
  ),
  'nested' => 5,
  'nep' => 
  array (
    0 => 6,
    1 => 7,
  ),
  'grand' => 8,
  'single' => 9,
  'author' => 1,
));
$cmd = new TS_Takedown_Command();
$ren = array_merge([$t['serial']], $t['ep']);
$got = ts_fixture_run_apply_takedown(function ($a, $assoc) use ($cmd) { $cmd->apply_takedown($a, $assoc); }, $repo, [
    [$t['ep'][2], $t['single'], $t['nep'][0], $t['nep'][1]],
    array_merge($ren, [$t['nep'][0], $t['nep'][1]]),
    array_merge($ren, [$t['nep'][0], $t['nep'][1]]),
]);
$same('apply-takedown', $got, array (
  'dry' => 
  array (
    0 => 
    array (
      0 => 
      array (
        0 => 'warning',
        1 => 'denylist の不完全なエントリ: S13 (kind/value を確認)',
      ),
      1 => 
      array (
        0 => 'warning',
        1 => '未知の kind: picture (S14)',
      ),
      2 => 
      array (
        0 => 'warning',
        1 => '該当投稿なし: S12 work=nai-sakuhin',
      ),
      3 => 
      array (
        0 => 'line',
        1 => '(dry-run) private 化: S10 work=amano-tsuki-renzoku → post 1 連続する月 (publish → private・メタ _ts_visibility=withdrawn _ts_takedown_id=S10 _ts_public_note=「連続する月」(天野 月さん) — 作者の申し出により掲載していません _ts_takedown_date=2026-01-02)',
      ),
      4 => 
      array (
        0 => 'line',
        1 => '(dry-run) private 化: S10 work=amano-tsuki-renzoku → post 3 第二話 (publish → private・メタ _ts_visibility=withdrawn _ts_takedown_id=S10 _ts_public_note=「連続する月」(天野 月さん) — 作者の申し出により掲載していません _ts_takedown_date=2026-01-02)',
      ),
      5 => 
      array (
        0 => 'line',
        1 => '(dry-run) private 化: S10 work=amano-tsuki-renzoku → post 2 第一話 (publish → private・メタ _ts_visibility=withdrawn _ts_takedown_id=S10 _ts_public_note=「連続する月」(天野 月さん) — 作者の申し出により掲載していません _ts_takedown_date=2026-01-02)',
      ),
      6 => 
      array (
        0 => 'line',
        1 => '(dry-run) 復帰: S11 work=amano-tsuki-tanpen → post 9 短い月 (private → publish・メタ -_ts_takedown_id -_ts_public_note -_ts_takedown_date -_ts_visibility)',
      ),
      7 => 
      array (
        0 => 'line',
        1 => '取り下げフォームの申請による取り下げ (denylist に未記載。export-takedowns で書き写す): 1 件',
      ),
      8 => 
      array (
        0 => 'warning',
        1 => 'denylist に無い取り下げの投稿 (変更しない): 1 件 (post 6)',
      ),
      9 => 
      array (
        0 => 'success',
        1 => 'apply-takedown: private 化 3 / 復帰 1 / 変更なし 1 (対象 5 件) (dry-run)',
      ),
    ),
    1 => 
    array (
      'amano-tsuki-renzoku' => 'publish vis= id= date= note= sup= imp=2026-10-30 00:00:00',
      'ren01' => 'publish vis= id= date= note= sup= imp=',
      'ren02' => 'publish vis= id= date= note= sup= imp=',
      'ren03' => 'private vis=withdrawn id=S10 date=2026-01-02 note=「連続する月」(天野 月さん) — 作者の申し出により掲載していません sup= imp=2000-01-01 00:00:00',
      'amano-tsuki-iriko' => 'publish vis= id= date= note= sup= imp=',
      'iri01' => 'private vis=withdrawn id=S5 date= note= sup= imp=',
      'iri02' => 'private vis=withdrawn id=req:7 date= note= sup= imp=',
      'iri02a' => 'publish vis= id= date= note= sup= imp=',
      'amano-tsuki-tanpen' => 'private vis=withdrawn id=S11 date=2025-05-05 note=「短い月」— 掲載していません sup= imp=',
    ),
    2 => false,
    3 => 0,
  ),
  'apply' => 
  array (
    0 => 
    array (
      0 => 
      array (
        0 => 'warning',
        1 => 'denylist の不完全なエントリ: S13 (kind/value を確認)',
      ),
      1 => 
      array (
        0 => 'warning',
        1 => '未知の kind: picture (S14)',
      ),
      2 => 
      array (
        0 => 'warning',
        1 => '該当投稿なし: S12 work=nai-sakuhin',
      ),
      3 => 
      array (
        0 => 'line',
        1 => 'private 化: S10 work=amano-tsuki-renzoku → post 1 連続する月 (publish → private・メタ _ts_visibility=withdrawn _ts_takedown_id=S10 _ts_public_note=「連続する月」(天野 月さん) — 作者の申し出により掲載していません _ts_takedown_date=2026-01-02)',
      ),
      4 => 
      array (
        0 => 'line',
        1 => 'private 化: S10 work=amano-tsuki-renzoku → post 3 第二話 (publish → private・メタ _ts_visibility=withdrawn _ts_takedown_id=S10 _ts_public_note=「連続する月」(天野 月さん) — 作者の申し出により掲載していません _ts_takedown_date=2026-01-02)',
      ),
      5 => 
      array (
        0 => 'line',
        1 => 'private 化: S10 work=amano-tsuki-renzoku → post 2 第一話 (publish → private・メタ _ts_visibility=withdrawn _ts_takedown_id=S10 _ts_public_note=「連続する月」(天野 月さん) — 作者の申し出により掲載していません _ts_takedown_date=2026-01-02)',
      ),
      6 => 
      array (
        0 => 'line',
        1 => '復帰: S11 work=amano-tsuki-tanpen → post 9 短い月 (private → publish・メタ -_ts_takedown_id -_ts_public_note -_ts_takedown_date -_ts_visibility)',
      ),
      7 => 
      array (
        0 => 'line',
        1 => '取り下げフォームの申請による取り下げ (denylist に未記載。export-takedowns で書き写す): 1 件',
      ),
      8 => 
      array (
        0 => 'warning',
        1 => 'denylist に無い取り下げの投稿 (変更しない): 1 件 (post 6)',
      ),
      9 => 
      array (
        0 => 'success',
        1 => 'apply-takedown: private 化 3 / 復帰 1 / 変更なし 1 (対象 5 件)',
      ),
    ),
    1 => 
    array (
      'amano-tsuki-renzoku' => 'private vis=withdrawn id=S10 date=2026-01-02 note=「連続する月」(天野 月さん) — 作者の申し出により掲載していません sup= imp=2026-11-05 12:00:00',
      'ren01' => 'private vis=withdrawn id=S10 date=2026-01-02 note=「連続する月」(天野 月さん) — 作者の申し出により掲載していません sup= imp=2026-11-05 12:00:00',
      'ren02' => 'private vis=withdrawn id=S10 date=2026-01-02 note=「連続する月」(天野 月さん) — 作者の申し出により掲載していません sup= imp=2026-11-05 12:00:00',
      'ren03' => 'private vis=withdrawn id=S10 date=2026-01-02 note=「連続する月」(天野 月さん) — 作者の申し出により掲載していません sup= imp=2001-06-03 00:00:00',
      'amano-tsuki-iriko' => 'publish vis= id= date= note= sup= imp=',
      'iri01' => 'private vis=withdrawn id=S5 date= note= sup= imp=',
      'iri02' => 'private vis=withdrawn id=req:7 date= note= sup= imp=',
      'iri02a' => 'publish vis= id= date= note= sup= imp=',
      'amano-tsuki-tanpen' => 'publish vis= id= date= note= sup= imp=2026-11-05 12:00:00',
    ),
    2 => 
    array (
      0 => '/novel/amano-tsuki-renzoku/',
      1 => '/novel/amano-tsuki-renzoku/ren03/',
      2 => '/novel/amano-tsuki-renzoku/ren02/',
      3 => '/novel/amano-tsuki-renzoku/ren01/',
      4 => '/novel/amano-tsuki-iriko/iri02/',
    ),
    3 => 4,
  ),
  'again' => 
  array (
    0 => 
    array (
      0 => 
      array (
        0 => 'warning',
        1 => 'denylist の不完全なエントリ: S13 (kind/value を確認)',
      ),
      1 => 
      array (
        0 => 'warning',
        1 => '未知の kind: picture (S14)',
      ),
      2 => 
      array (
        0 => 'warning',
        1 => '該当投稿なし: S12 work=nai-sakuhin',
      ),
      3 => 
      array (
        0 => 'line',
        1 => '取り下げフォームの申請による取り下げ (denylist に未記載。export-takedowns で書き写す): 1 件',
      ),
      4 => 
      array (
        0 => 'warning',
        1 => 'denylist に無い取り下げの投稿 (変更しない): 1 件 (post 6)',
      ),
      5 => 
      array (
        0 => 'success',
        1 => 'apply-takedown: private 化 0 / 復帰 0 / 変更なし 5 (対象 5 件)',
      ),
    ),
    1 => 
    array (
      'amano-tsuki-renzoku' => 'private vis=withdrawn id=S10 date=2026-01-02 note=「連続する月」(天野 月さん) — 作者の申し出により掲載していません sup= imp=2026-11-05 12:00:00',
      'ren01' => 'private vis=withdrawn id=S10 date=2026-01-02 note=「連続する月」(天野 月さん) — 作者の申し出により掲載していません sup= imp=2026-11-05 12:00:00',
      'ren02' => 'private vis=withdrawn id=S10 date=2026-01-02 note=「連続する月」(天野 月さん) — 作者の申し出により掲載していません sup= imp=2026-11-05 12:00:00',
      'ren03' => 'private vis=withdrawn id=S10 date=2026-01-02 note=「連続する月」(天野 月さん) — 作者の申し出により掲載していません sup= imp=2001-06-03 00:00:00',
      'amano-tsuki-iriko' => 'publish vis= id= date= note= sup= imp=',
      'iri01' => 'private vis=withdrawn id=S5 date= note= sup= imp=',
      'iri02' => 'private vis=withdrawn id=req:7 date= note= sup= imp=',
      'iri02a' => 'publish vis= id= date= note= sup= imp=',
      'amano-tsuki-tanpen' => 'publish vis= id= date= note= sup= imp=2026-11-05 12:00:00',
    ),
    2 => 
    array (
      0 => '/novel/amano-tsuki-renzoku/',
      1 => '/novel/amano-tsuki-renzoku/ren03/',
      2 => '/novel/amano-tsuki-renzoku/ren02/',
      3 => '/novel/amano-tsuki-renzoku/ren01/',
      4 => '/novel/amano-tsuki-iriko/iri02/',
    ),
    3 => 0,
  ),
));
unlink("$repo/takedown/denylist.yml"); rmdir("$repo/takedown"); rmdir($repo);
TS_WP_Stubs::reset();
