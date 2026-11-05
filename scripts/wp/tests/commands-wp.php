<?php
/**
 * wp ts のコマンドを WordPress の代わり (scripts/wp/tests/wp-stubs.php) と WP_CLI の代わり (scripts/wp/ts_library_test.php) の上で
 * 流す (T45.1.7.5。wp ts reset・import の本文の選び方・apply-takedown・書き写していない申請・export-takedowns・import と verify)。
 * WP_CLI::$out に出力、TS_WP_Stubs::$log に消したもの・書いたものが残る。WP_CLI::error は TS_CLI_Error を投げる ($exit が false なら続ける)。
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

// ---------------------------------------------------------------- denylist に書き写していない申請 (TS_Takedown_Export_State::requests_not_in_denylist。S132。T45.6.5)
// 期待値: 0e8c7712 の ts-library.php と includes 7 本を同じ代わりで動かした出力 (2026-11-05、scripts/wp/php_probe.sh、probe-rest.php)。旧版は TS_Command の private の同名のメソッドで、警告は WP_CLI::warning に直接出していた (新版は返り値の warnings)。
// 場面は wp-fixtures.php の ts_fixture_export_scene (applied と approved のうち、denylist に書き写していないもの)
$repo = sys_get_temp_dir() . '/ts-not-in-denylist-' . getmypid();
mkdir($repo);
$same('denylist に無い申請', ts_fixture_run_requests_not_in_denylist(function ($repo) {
    $r = TS_Takedown_Export_State::requests_not_in_denylist($repo);
    return [$r['ids'], $r['warnings']];
}, $repo), array (
  'found' => 
  array (
    0 => 
    array (
      0 => 10,
      1 => 11,
      2 => 15,
      3 => 16,
    ),
    1 => 
    array (
      0 => 'denylist の不完全なエントリ: S242 (kind/value を確認)',
    ),
  ),
  'no_type' => 
  array (
    0 => 
    array (
    ),
    1 => 
    array (
    ),
  ),
  'names' => 
  array (
    0 => 'applied',
    1 => 'approved',
    2 => 'bare',
    3 => 'old',
  ),
));
rmdir($repo);

// ---------------------------------------------------------------- wp ts export-takedowns (S132。T45.6.5)
// 期待値: 0e8c7712 の ts-library.php と includes 7 本を同じ代わりで動かした出力 (2026-11-05、scripts/wp/php_probe.sh、probe-rest.php)。
// ただし旧版は S の後に点を付けて番号を振っていたので、識別子の決定 (9c3119cc、S221) に合わせて点を除いて読み替えた
// (SPECS.md の「次は」の書き方も、場面の引数で旧版と新版に合わせている)。
// 案内は標準エラーに書くので、新版は子のプロセス (scripts/wp/ts_library_test.php --child export-takedowns) で流し、標準出力の JSON
// (引数ごとの [標準出力の YAML, WP_CLI::$out]) と標準エラーを受ける。止まった 2 つ (--start と --since の誤り) は案内を出さない
$repo = sys_get_temp_dir() . '/ts-export-takedowns-' . getmypid();
mkdir($repo);
$proc = proc_open([PHP_BINARY, "$root/scripts/wp/ts_library_test.php", '--child', 'export-takedowns', $repo], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
check('export-takedowns: 子のプロセスの終了コード', proc_close($proc), 0);
rmdir($repo);
$same('export-takedowns', json_decode($stdout, true) ?? [], array (
  'start' => 
  array (
    0 => '  - id: S300
    target:
      kind: work
      value: amano-tsuki-renzoku
    reason: 取り下げフォーム経由の申し出 (申請 10)
    reason_class: author_request
    requested_at: 2026-11-05
    received_via: takedown_form
    status: applied
    applied:
      wp_draft: 2026-11-06
      htaccess_410: 2026-11-06
      annex_excluded: null
      git_history_purged: null
      removed_page_listed: null
    public_note: 「連続する月」(天野 月さん) — 作者の申し出により掲載していません (2026 年 11 月)
  - id: S301
    target:
      kind: episode
      value: novel/200104/ren.htm#ren01
    reason: 取り下げフォーム経由の申し出 (申請 10)
    reason_class: author_request
    requested_at: 2026-11-05
    received_via: takedown_form
    status: applied
    applied:
      wp_draft: 2026-11-06
      htaccess_410: 2026-11-06
      annex_excluded: null
      git_history_purged: null
      removed_page_listed: null
    public_note: 「第一話」(天野 月さん) — 作者の申し出により掲載していません (2026 年 11 月)
  - id: S302
    target:
      kind: episode
      value: novel/200105/ren02.htm
    reason: 取り下げフォーム経由の申し出 (申請 11)
    reason_class: author_request
    requested_at: 2026-11-05
    received_via: takedown_form
    status: applied
    applied:
      wp_draft: 2026-11-06
      htaccess_410: 2026-11-06
      annex_excluded: null
      git_history_purged: null
      removed_page_listed: null
    public_note: 作者の申し出により 1 作品を掲載していません (2026 年 11 月)
  - id: S303
    target:
      kind: work
      value: amano-tsuki-tanpen
    reason: 取り下げフォーム経由の申し出 (申請 16)
    reason_class: author_request
    requested_at: 2026-11-05
    received_via: takedown_form
    status: applied
    applied:
      wp_draft: 2020-05-21
      htaccess_410: 2020-05-21
      annex_excluded: null
      git_history_purged: null
      removed_page_listed: null
    public_note: 「短い月」(天野 月さん) — 作者の申し出により掲載していません (2026 年 11 月)
',
    1 => 
    array (
      0 => 
      array (
        0 => 'warning',
        1 => 'denylist の不完全なエントリ: S242 (kind/value を確認)',
      ),
      1 => 
      array (
        0 => 'warning',
        1 => '申請 13 は復帰した: denylist の S240 の status を reinstated に直す',
      ),
      2 => 
      array (
        0 => 'warning',
        1 => '申請 15: post 8 の work_slug も source_path も無い',
      ),
      3 => 
      array (
        0 => 'warning',
        1 => '申請 15: 本サイトの作品に結びつく対象が無い (出さない)',
      ),
    ),
  ),
  'specs' => 
  array (
    0 => '  - id: S250
    target:
      kind: work
      value: amano-tsuki-renzoku
    reason: 取り下げフォーム経由の申し出 (申請 10)
    reason_class: author_request
    requested_at: 2026-11-05
    received_via: takedown_form
    status: applied
    applied:
      wp_draft: 2026-11-06
      htaccess_410: 2026-11-06
      annex_excluded: null
      git_history_purged: null
      removed_page_listed: null
    public_note: 「連続する月」(天野 月さん) — 作者の申し出により掲載していません (2026 年 11 月)
  - id: S251
    target:
      kind: episode
      value: novel/200104/ren.htm#ren01
    reason: 取り下げフォーム経由の申し出 (申請 10)
    reason_class: author_request
    requested_at: 2026-11-05
    received_via: takedown_form
    status: applied
    applied:
      wp_draft: 2026-11-06
      htaccess_410: 2026-11-06
      annex_excluded: null
      git_history_purged: null
      removed_page_listed: null
    public_note: 「第一話」(天野 月さん) — 作者の申し出により掲載していません (2026 年 11 月)
  - id: S252
    target:
      kind: episode
      value: novel/200105/ren02.htm
    reason: 取り下げフォーム経由の申し出 (申請 11)
    reason_class: author_request
    requested_at: 2026-11-05
    received_via: takedown_form
    status: applied
    applied:
      wp_draft: 2026-11-06
      htaccess_410: 2026-11-06
      annex_excluded: null
      git_history_purged: null
      removed_page_listed: null
    public_note: 作者の申し出により 1 作品を掲載していません (2026 年 11 月)
  - id: S253
    target:
      kind: work
      value: amano-tsuki-tanpen
    reason: 取り下げフォーム経由の申し出 (申請 16)
    reason_class: author_request
    requested_at: 2026-11-05
    received_via: takedown_form
    status: applied
    applied:
      wp_draft: 2020-05-21
      htaccess_410: 2020-05-21
      annex_excluded: null
      git_history_purged: null
      removed_page_listed: null
    public_note: 「短い月」(天野 月さん) — 作者の申し出により掲載していません (2026 年 11 月)
',
    1 => 
    array (
      0 => 
      array (
        0 => 'warning',
        1 => 'denylist の不完全なエントリ: S242 (kind/value を確認)',
      ),
      1 => 
      array (
        0 => 'warning',
        1 => '申請 13 は復帰した: denylist の S240 の status を reinstated に直す',
      ),
      2 => 
      array (
        0 => 'warning',
        1 => '申請 15: post 8 の work_slug も source_path も無い',
      ),
      3 => 
      array (
        0 => 'warning',
        1 => '申請 15: 本サイトの作品に結びつく対象が無い (出さない)',
      ),
    ),
  ),
  'since' => 
  array (
    0 => '  - id: S300
    target:
      kind: work
      value: amano-tsuki-renzoku
    reason: 取り下げフォーム経由の申し出 (申請 10)
    reason_class: author_request
    requested_at: 2026-11-05
    received_via: takedown_form
    status: applied
    applied:
      wp_draft: 2026-11-06
      htaccess_410: 2026-11-06
      annex_excluded: null
      git_history_purged: null
      removed_page_listed: null
    public_note: 「連続する月」(天野 月さん) — 作者の申し出により掲載していません (2026 年 11 月)
  - id: S301
    target:
      kind: episode
      value: novel/200104/ren.htm#ren01
    reason: 取り下げフォーム経由の申し出 (申請 10)
    reason_class: author_request
    requested_at: 2026-11-05
    received_via: takedown_form
    status: applied
    applied:
      wp_draft: 2026-11-06
      htaccess_410: 2026-11-06
      annex_excluded: null
      git_history_purged: null
      removed_page_listed: null
    public_note: 「第一話」(天野 月さん) — 作者の申し出により掲載していません (2026 年 11 月)
  - id: S302
    target:
      kind: episode
      value: novel/200105/ren02.htm
    reason: 取り下げフォーム経由の申し出 (申請 11)
    reason_class: author_request
    requested_at: 2026-11-05
    received_via: takedown_form
    status: applied
    applied:
      wp_draft: 2026-11-06
      htaccess_410: 2026-11-06
      annex_excluded: null
      git_history_purged: null
      removed_page_listed: null
    public_note: 作者の申し出により 1 作品を掲載していません (2026 年 11 月)
',
    1 => 
    array (
      0 => 
      array (
        0 => 'warning',
        1 => 'denylist の不完全なエントリ: S242 (kind/value を確認)',
      ),
      1 => 
      array (
        0 => 'warning',
        1 => '申請 13 は復帰した: denylist の S240 の status を reinstated に直す',
      ),
      2 => 
      array (
        0 => 'warning',
        1 => '申請 15: post 8 の work_slug も source_path も無い',
      ),
      3 => 
      array (
        0 => 'warning',
        1 => '申請 15: 本サイトの作品に結びつく対象が無い (出さない)',
      ),
    ),
  ),
  'none' => 
  array (
    0 => '',
    1 => 
    array (
      0 => 
      array (
        0 => 'warning',
        1 => 'denylist の不完全なエントリ: S242 (kind/value を確認)',
      ),
    ),
  ),
  'bad_start' => 
  array (
    0 => '',
    1 => 
    array (
      0 => 
      array (
        0 => 'error',
        1 => '--start は数字で',
      ),
    ),
  ),
  'bad_since' => 
  array (
    0 => '',
    1 => 
    array (
      0 => 
      array (
        0 => 'error',
        1 => '--since は YYYY-MM-DD で',
      ),
    ),
  ),
));
check('export-takedowns: 標準エラーの案内 (引数ごとに 1 行)', explode("\n", rtrim($stderr, "\n")), array (
  0 => '振った id: S300 S301 S302 S303 (specs/Sn.md を作り SPECS.md の一覧と「次は」を直す)',
  1 => '振った id: S250 S251 S252 S253 (specs/Sn.md を作り SPECS.md の一覧と「次は」を直す)',
  2 => '振った id: S300 S301 S302 (specs/Sn.md を作り SPECS.md の一覧と「次は」を直す)',
  3 => '書き写す申請はありません',
));

// ---------------------------------------------------------------- wp ts import と wp ts verify (T8.2。T45.6.6)
// 期待値: 0e8c7712 の ts-library.php と includes 7 本を同じ代わりで動かした出力 (2026-11-05、scripts/wp/php_probe.sh、probe-import.php)。
// 場面と流し方は wp-fixtures.php の ts_fixture_import_scene と ts_fixture_run_import (probe も同じ関数を呼ぶ)。段ごとに WP_CLI::$out・
// 書いた関数の数・option・投稿の差 (前の段との差なので、段の間に試験が加えた変化も次の段に出る) を見る:
// 空の DB の --dry-run は何も書かない (単発の本文の載せ替えは数えない) → 1 回目は created (単発 2 つの載せ替えは updated) → verify OK
// → 2 回目は全部 skipped (連載の親 work の term だけは毎回書き直す) → --bodies で話と単発が updated (ペイロード・案内文・takedown の空・
// _ts_body_status は _meta.json の値そのまま) → もう一度は skipped → --author と --limit → catalog を直すと updated、手で直した投稿は
// manual_skipped (--dry-run でも option ts_manual_edit_warnings を書く。語の解決は書くときだけなので --dry-run は term の警告を出さない)、
// 語彙に無い値 (ホラー) と WP に無い term (あめ) は ts_term_warnings → verify NG (未作成・割当数・未解決・手動編集) → --overwrite-manual で
// updated → はぐれの投稿は重複の警告、verify は件数・orphan・語彙外・被覆・cron・denylist を警告する。hash (HASH_VER と wp_json_encode) は
// 投稿の行に入れてそのまま比べる (旧版と同じ hash になることが、配備で全投稿が updated にならない根拠)。
// 1 回ごとに new するのは、本番の wp のプロセスごとに新しいのと同じにするため (旧版は term の表と解決できなかった値を 1 つのオブジェクトに溜める)。
// 代わりが本物と違う所 (meta_value の比較・post_name の重複・get_term_by の slug・verify の SQL) は wp-stubs.php の先頭と specs/S242.md
$repo = sys_get_temp_dir() . '/ts-import-' . getmypid();
mkdir($repo);
$got = ts_fixture_run_import(function ($assoc) { (new TS_Import_Command())->import([], $assoc); },
                             function ($assoc) { (new TS_Verify_Command())->verify([], $assoc); }, $repo);
check('import と verify: 一時ディレクトリの catalog とペイロードは消えている', [is_dir("$repo/catalog"), is_dir("$repo/payloads")], [false, false]);
rmdir($repo);
$same('import と verify', $got, array (
  'dry0/out' => 
  array (
    0 => 
    array (
      0 => 'success',
      1 => 'import: created=10 updated=0 skipped=0 manual_skipped=0 catalog= (dry-run)',
    ),
  ),
  'dry0/writes' => 
  array (
  ),
  'dry0/options' => 
  array (
    'ts_catalog_commit' => '(無い)',
    'ts_last_import_at' => '(無い)',
    'ts_manual_edit_warnings' => '(無い)',
    'ts_term_warnings' => '(無い)',
  ),
  'dry0/posts' => 
  array (
    'changed' => 
    array (
    ),
    'gone' => 
    array (
    ),
  ),
  'first/out' => 
  array (
    0 => 
    array (
      0 => 'success',
      1 => 'import: created=10 updated=2 skipped=0 manual_skipped=0 catalog=',
    ),
  ),
  'first/writes' => 
  array (
    'delete_option' => 2,
    'wp_insert_post' => 10,
    'wp_set_object_terms' => 60,
    'wp_update_post' => 2,
  ),
  'first/options' => 
  array (
    'ts_catalog_commit' => '(無い)',
    'ts_last_import_at' => '(無い)',
    'ts_manual_edit_warnings' => '(無い)',
    'ts_term_warnings' => '(無い)',
  ),
  'first/posts' => 
  array (
    'changed' => 
    array (
      'amano-tsuki-renzoku' => 'publish parent= order=0 date=2001-04-01 00:00:00 title=連続する月 body=\'\' mod=2026-11-05 12:00:00 | _ts_alias_paths=["novel/200104/ren.htm"] _ts_episode_count=3 _ts_import_hash=\'453955fd1c3c845fd8871183d066b588\' _ts_kind=\'work\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_needs_review=1 _ts_title_pages=["novel/title/renzoku.htm"] _ts_work_slug=\'amano-tsuki-renzoku\' | ts_author=amano-tsuki ts_corpus=honkan,kyukan ts_genre=gakuen,komedi ts_keyword=neko,ame ts_type=henshin,irekawari ts_world=hoshi-no-machi',
      'amano-tsuki-tanpen' => 'publish parent= order=0 date=2001-07-07 00:00:00 title=短い月 body=\'\' mod=2026-11-05 12:00:00 | _ts_alias_paths=[] _ts_annex_url=\'https://example.com/annex/novel/200107/tanpen.htm\' _ts_annex_yays_url=\'https://example.com/annex-yays/novel/200107/tanpen.htm\' _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_count=1 _ts_episode_id=\'novel__200107__tanpen.htm\' _ts_fill_hash=\'d13b5f2f0e443e7bbc0105d085bf1190\' _ts_import_hash=\'a9ad9b9fc9e1b66d7a50d947dcf071d1\' _ts_kind=\'work\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_needs_review=0 _ts_orig_url=\'http://ts.novels.jp/novel/200107/tanpen.htm\' _ts_provenance={"route":"wayback","route_label":"Internet Archive Wayback Machine"} _ts_pub_date_raw=\'2001/07/07\' _ts_raw_genre=["コメディ"] _ts_raw_type=["入れ替わり"] _ts_size_kb=12 _ts_source_path=\'novel/200107/tanpen.htm\' _ts_title_pages=[] _ts_work_slug=\'amano-tsuki-tanpen\' _ts_zokusei=["TS"] | ts_author=amano-tsuki ts_corpus=honkan ts_genre=komedi ts_keyword= ts_type=irekawari ts_world=',
      'hoshino-akari-hoshi' => 'publish parent= order=0 date=2002-08-08 00:00:00 title=星の歌 body=\'\' mod=2026-11-05 12:00:00 | _ts_alias_paths=[] _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_count=1 _ts_episode_id=\'novel__200208__hoshi.htm\' _ts_fill_hash=\'b7935fe7abc52060ba104d4fe7e093c8\' _ts_import_hash=\'bc9140e3b510d58c6721a20fc1ac9e81\' _ts_kind=\'work\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_needs_review=0 _ts_orig_url=\'http://ts.novels.jp/novel/200208/hoshi.htm\' _ts_provenance={"route":"ftp"} _ts_pub_date_raw=\'2002/08/08\' _ts_raw_keywords=["ねこ"] _ts_raw_type=["変身"] _ts_size_kb=12 _ts_source_path=\'novel/200208/hoshi.htm\' _ts_title_pages=[] _ts_work_slug=\'hoshino-akari-hoshi\' | ts_author=hoshino-akari ts_corpus=honkan ts_genre= ts_keyword=neko ts_type=henshin ts_world=',
      'umino-nami-nazo' => 'publish parent= order=0 date=2003-01-01 00:00:00 title=謎 body=\'\' mod=2026-11-05 12:00:00 | _ts_alias_paths=[] _ts_episode_count=2 _ts_import_hash=\'4ad336e6ed348ccd52b1085c84d38d4c\' _ts_kind=\'work\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_needs_review=0 _ts_title_pages=[] _ts_work_slug=\'umino-nami-nazo\' | ts_author=umino_nami ts_corpus=honkan ts_genre=gakuen ts_keyword=neko ts_type=henshin ts_world=hoshi-no-machi',
      'ren01' => 'publish parent=amano-tsuki-renzoku order=1 date=2001-04-01 00:00:00 title=第一話 body=\'\' mod=2026-11-05 12:00:00 | _ts_annex_url=\'https://example.com/annex/novel/200104/ren01.htm\' _ts_arasuji=\'月が連なる。\' _ts_author_comment=\'ありがとう\' _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_id=\'novel__200104__ren01.htm\' _ts_illustrator=["絵筆 太郎"] _ts_import_hash=\'cd98b9b7778a7c39529158ed8cfe7b55\' _ts_kansou_annex_url=\'https://example.com/annex/bbs/amano.cgi\' _ts_kansou_slug=\'amano\' _ts_kind=\'episode\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_orig_url=\'http://ts.novels.jp/novel/200104/ren01.htm\' _ts_provenance={"route":"wayback","route_label":"Internet Archive Wayback Machine"} _ts_pub_date_raw=\'2001/04/01\' _ts_raw_genre=["学園"] _ts_raw_keywords=["ねこ"] _ts_raw_type=["変身"] _ts_size_kb=12 _ts_source_path=\'novel/200104/ren01.htm\' _ts_suisen=\'胸が熱くなる\' | ts_author=amano-tsuki ts_corpus=honkan ts_genre=gakuen ts_keyword=neko ts_type=henshin ts_world=hoshi-no-machi',
      'ren02' => 'publish parent=amano-tsuki-renzoku order=2 date=2001-05-02 00:00:00 title=第二話 body=\'\' mod=2026-11-05 12:00:00 | _ts_annex_url=\'https://example.com/annex/novel/200105/ren02.htm\' _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_id=\'novel__200105__ren02.htm\' _ts_import_hash=\'6cf3fd31950803e819ea3d37908f92c2\' _ts_kind=\'episode\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_provenance={"route":"wayback","route_label":"Internet Archive Wayback Machine"} _ts_pub_date_raw=\'2001/05/02\' _ts_raw_genre=["学園？"] _ts_size_kb=8 _ts_source_path=\'novel/200105/ren02.htm\' | ts_author=amano-tsuki ts_corpus=honkan ts_genre=gakuen ts_keyword= ts_type= ts_world=',
      'ren03' => 'publish parent=amano-tsuki-renzoku order=3 date=2001-06-03 00:00:00 title=第三話 body=\'\' mod=2026-11-05 12:00:00 | _ts_annex_url=\'https://example.com/annex/novel/200106/ren03.htm\' _ts_catalog_ref=\'lib01.html#3\' _ts_corpus=\'kyukan\' _ts_episode_id=\'novel__200106__ren03.htm\' _ts_import_hash=\'e6a54499ca43d56e6a5483deef8e7349\' _ts_kind=\'episode\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_nav_links=[{"href":"novel/200104/ren01.htm","label":"最初へ"}] _ts_orig_url=\'http://ts.novels.jp/novel/200106/ren03.htm\' _ts_osusume={"recommender":"読者 一","refs":[{"href":"novel/200104/ren01.htm","title":"第一話"}]} _ts_provenance={"route":"wayback","route_label":"Internet Archive Wayback Machine"} _ts_pub_date_raw=\'2001/06/03\' _ts_raw_genre=["コメディ","学園"] _ts_raw_keywords=["あめ","ねこ"] _ts_raw_type=["変身","入れ替わり"] _ts_size_kb=12 _ts_source_path=\'novel/200106/ren03.htm\' | ts_author=amano-tsuki ts_corpus=kyukan ts_genre=komedi,gakuen ts_keyword=ame,neko ts_type=henshin,irekawari ts_world=',
      'nazo01' => 'publish parent=umino-nami-nazo order=1 date=2003-01-01 00:00:00 title=謎の上 body=\'\' mod=2026-11-05 12:00:00 | _ts_annex_url=\'https://example.com/annex/novel/200301/nazo01.htm\' _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_id=\'novel__200301__nazo01.htm\' _ts_files_n=2 _ts_import_hash=\'ae0e4355a837df04f7e5e7783b0577c9\' _ts_kind=\'episode\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_orig_url=\'http://ts.novels.jp/novel/200301/nazo01.htm\' _ts_provenance={"route":"wayback","route_label":"Internet Archive Wayback Machine"} _ts_pub_date_raw=\'2003/01/01\' _ts_raw_genre=["学園"] _ts_raw_keywords=["?","ねこ"] _ts_size_kb=12 _ts_source_path=\'novel/200301/nazo01.htm\' | ts_author=umino_nami ts_corpus=honkan ts_genre=gakuen ts_keyword=neko ts_type= ts_world=hoshi-no-machi',
      'nazo02' => 'publish parent=umino-nami-nazo order=2 date=2003-01-02 00:00:00 title=謎の下 body=\'\' mod=2026-11-05 12:00:00 | _ts_annex_url=\'https://example.com/annex/novel/200301/nazo02.htm\' _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_id=\'novel__200301__nazo02.htm\' _ts_import_hash=\'51d47e99f08108f3e1108ad961da5a3c\' _ts_kind=\'episode\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_orig_url=\'http://ts.novels.jp/novel/200301/nazo02.htm\' _ts_provenance={"route":"wayback","route_label":"Internet Archive Wayback Machine"} _ts_pub_date_raw=\'2003/01/02\' _ts_raw_type=["変身"] _ts_size_kb=12 _ts_source_path=\'novel/200301/nazo02.htm\' | ts_author=umino_nami ts_corpus=honkan ts_genre= ts_keyword= ts_type=henshin ts_world=',
      'kodoku' => 'publish parent= order=0 date=2003-02-02 00:00:00 title=孤独 body=\'\' mod=2026-11-05 12:00:00 | _ts_annex_url=\'https://example.com/annex/novel/200302/kodoku.htm\' _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_id=\'novel__200302__kodoku.htm\' _ts_import_hash=\'d900e8bf4250bed2afb4abfda75c565f\' _ts_kind=\'episode\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_pub_date_raw=\'2003/02/02\' _ts_raw_genre=["コメディ"] _ts_size_kb=12 _ts_source_path=\'novel/200302/kodoku.htm\' | ts_author= ts_corpus=honkan ts_genre=komedi ts_keyword= ts_type= ts_world=',
    ),
    'gone' => 
    array (
    ),
  ),
  'verify1/out' => 
  array (
    0 => 
    array (
      0 => 'line',
      1 => '投稿数: WP=10 期待=10 (works 4 + episodes 8 - 単発 2)',
    ),
    1 => 
    array (
      0 => 'line',
      1 => 'orphan 子投稿: 0',
    ),
    2 => 
    array (
      0 => 'line',
      1 => 'ts_author: WP=5 語彙=5 語彙外=0 未作成=0',
    ),
    3 => 
    array (
      0 => 'line',
      1 => 'ts_genre: WP=2 語彙=2 語彙外=0 未作成=0',
    ),
    4 => 
    array (
      0 => 'line',
      1 => 'ts_type: WP=2 語彙=2 語彙外=0 未作成=0',
    ),
    5 => 
    array (
      0 => 'line',
      1 => 'ts_keyword: WP=2 語彙=2 語彙外=0 未作成=0',
    ),
    6 => 
    array (
      0 => 'line',
      1 => 'ts_world: WP=1 語彙=1 語彙外=0 未作成=0',
    ),
    7 => 
    array (
      0 => 'line',
      1 => 'ts_corpus: WP=2 語彙=2 語彙外=0 未作成=0',
    ),
    8 => 
    array (
      0 => 'line',
      1 => '割当 ts_author: WP=9 期待=9',
    ),
    9 => 
    array (
      0 => 'line',
      1 => '割当 ts_genre: WP=10 期待=10',
    ),
    10 => 
    array (
      0 => 'line',
      1 => '割当 ts_type: WP=9 期待=9',
    ),
    11 => 
    array (
      0 => 'line',
      1 => '割当 ts_keyword: WP=8 期待=8',
    ),
    12 => 
    array (
      0 => 'line',
      1 => '割当 ts_world: WP=4 期待=4',
    ),
    13 => 
    array (
      0 => 'line',
      1 => '割当 ts_corpus: WP=11 期待=11',
    ),
    14 => 
    array (
      0 => 'line',
      1 => 'メタ _ts_pub_date_raw: WP=8 期待=8',
    ),
    15 => 
    array (
      0 => 'line',
      1 => 'メタ _ts_orig_url: WP=6 期待=6',
    ),
    16 => 
    array (
      0 => 'line',
      1 => 'メタ _ts_annex_url: WP=7 期待=7',
    ),
    17 => 
    array (
      0 => 'line',
      1 => 'メタ _ts_provenance: WP=7 期待=7',
    ),
    18 => 
    array (
      0 => 'line',
      1 => '本文 status: (未投入)',
    ),
    19 => 
    array (
      0 => 'warning',
      1 => 'ts_takedown_tick がまだ一度も動いていない (S130)',
    ),
    20 => 
    array (
      0 => 'line',
      1 => '適用済みの申請で denylist に無いもの: 0 件',
    ),
    21 => 
    array (
      0 => 'line',
      1 => 'ts_catalog_commit: (未記録)',
    ),
    22 => 
    array (
      0 => 'line',
      1 => '(冪等の確認は `wp ts import --dry-run` 2 回目が created=0 updated=0 になること)',
    ),
    23 => 
    array (
      0 => 'success',
      1 => 'verify OK',
    ),
  ),
  'again/out' => 
  array (
    0 => 
    array (
      0 => 'success',
      1 => 'import: created=0 updated=0 skipped=12 manual_skipped=0 catalog=',
    ),
  ),
  'again/writes' => 
  array (
    'delete_option' => 2,
    'wp_set_object_terms' => 10,
  ),
  'again/options' => 
  array (
    'ts_catalog_commit' => '(無い)',
    'ts_last_import_at' => '(無い)',
    'ts_manual_edit_warnings' => '(無い)',
    'ts_term_warnings' => '(無い)',
  ),
  'again/posts' => 
  array (
    'changed' => 
    array (
    ),
    'gone' => 
    array (
    ),
  ),
  'bodies/out' => 
  array (
    0 => 
    array (
      0 => 'success',
      1 => 'import: created=0 updated=8 skipped=4 manual_skipped=0 catalog=',
    ),
  ),
  'bodies/writes' => 
  array (
    'delete_option' => 2,
    'wp_set_object_terms' => 56,
    'wp_update_post' => 8,
  ),
  'bodies/options' => 
  array (
    'ts_catalog_commit' => '(無い)',
    'ts_last_import_at' => '(無い)',
    'ts_manual_edit_warnings' => '(無い)',
    'ts_term_warnings' => '(無い)',
  ),
  'bodies/posts' => 
  array (
    'changed' => 
    array (
      'amano-tsuki-tanpen' => 'publish parent= order=0 date=2001-07-07 00:00:00 title=短い月 body=\'<!-- wp:paragraph --><p>短い月の本文。</p><!-- /wp:paragraph -->\' mod=2026-11-05 12:00:00 | _ts_alias_paths=[] _ts_annex_url=\'https://example.com/annex/novel/200107/tanpen.htm\' _ts_annex_yays_url=\'https://example.com/annex-yays/novel/200107/tanpen.htm\' _ts_body_status=\'converted\' _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_count=1 _ts_episode_id=\'novel__200107__tanpen.htm\' _ts_fill_hash=\'e414c03afaf2458fc37f826e54e187e9\' _ts_import_hash=\'a9ad9b9fc9e1b66d7a50d947dcf071d1\' _ts_kind=\'work\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_needs_review=0 _ts_orig_url=\'http://ts.novels.jp/novel/200107/tanpen.htm\' _ts_provenance={"route":"wayback","route_label":"Internet Archive Wayback Machine"} _ts_pub_date_raw=\'2001/07/07\' _ts_raw_genre=["コメディ"] _ts_raw_type=["入れ替わり"] _ts_size_kb=12 _ts_source_path=\'novel/200107/tanpen.htm\' _ts_title_pages=[] _ts_work_slug=\'amano-tsuki-tanpen\' _ts_zokusei=["TS"] | ts_author=amano-tsuki ts_corpus=honkan ts_genre=komedi ts_keyword= ts_type=irekawari ts_world=',
      'hoshino-akari-hoshi' => 'publish parent= order=0 date=2002-08-08 00:00:00 title=星の歌 body=\'<!-- wp:paragraph --><p>星の歌の本文。</p><!-- /wp:paragraph -->\' mod=2026-11-05 12:00:00 | _ts_alias_paths=[] _ts_body_status=\'placeholder\' _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_count=1 _ts_episode_id=\'novel__200208__hoshi.htm\' _ts_fill_hash=\'beafc7d2ef4f3463f270b9639806f5d6\' _ts_import_hash=\'bc9140e3b510d58c6721a20fc1ac9e81\' _ts_kind=\'work\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_needs_review=0 _ts_orig_url=\'http://ts.novels.jp/novel/200208/hoshi.htm\' _ts_provenance={"route":"ftp"} _ts_pub_date_raw=\'2002/08/08\' _ts_raw_keywords=["ねこ"] _ts_raw_type=["変身"] _ts_size_kb=12 _ts_source_path=\'novel/200208/hoshi.htm\' _ts_title_pages=[] _ts_work_slug=\'hoshino-akari-hoshi\' | ts_author=hoshino-akari ts_corpus=honkan ts_genre= ts_keyword=neko ts_type=henshin ts_world=',
      'ren01' => 'publish parent=amano-tsuki-renzoku order=1 date=2001-04-01 00:00:00 title=第一話 body=\'<!-- wp:paragraph --><p>第一話の本文。</p><!-- /wp:paragraph -->\' mod=2026-11-05 12:00:00 | _ts_annex_url=\'https://example.com/annex/novel/200104/ren01.htm\' _ts_arasuji=\'月が連なる。\' _ts_author_comment=\'ありがとう\' _ts_body_status=\'converted\' _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_id=\'novel__200104__ren01.htm\' _ts_illustrator=["絵筆 太郎"] _ts_import_hash=\'a37f2a1357f899069e151eb786938e48\' _ts_kansou_annex_url=\'https://example.com/annex/bbs/amano.cgi\' _ts_kansou_slug=\'amano\' _ts_kind=\'episode\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_orig_url=\'http://ts.novels.jp/novel/200104/ren01.htm\' _ts_provenance={"route":"wayback","route_label":"Internet Archive Wayback Machine"} _ts_pub_date_raw=\'2001/04/01\' _ts_raw_genre=["学園"] _ts_raw_keywords=["ねこ"] _ts_raw_type=["変身"] _ts_size_kb=12 _ts_source_path=\'novel/200104/ren01.htm\' _ts_suisen=\'胸が熱くなる\' | ts_author=amano-tsuki ts_corpus=honkan ts_genre=gakuen ts_keyword=neko ts_type=henshin ts_world=hoshi-no-machi',
      'ren02' => 'publish parent=amano-tsuki-renzoku order=2 date=2001-05-02 00:00:00 title=第二話 body=\'<!-- wp:paragraph --><p>(本文は原本アーカイブでお読みください)</p><!-- /wp:paragraph -->\' mod=2026-11-05 12:00:00 | _ts_annex_url=\'https://example.com/annex/novel/200105/ren02.htm\' _ts_body_status=\'placeholder\' _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_id=\'novel__200105__ren02.htm\' _ts_import_hash=\'003ccdb931a3572441722f2bcf809fa8\' _ts_kind=\'episode\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_provenance={"route":"wayback","route_label":"Internet Archive Wayback Machine"} _ts_pub_date_raw=\'2001/05/02\' _ts_raw_genre=["学園？"] _ts_size_kb=8 _ts_source_path=\'novel/200105/ren02.htm\' | ts_author=amano-tsuki ts_corpus=honkan ts_genre=gakuen ts_keyword= ts_type= ts_world=',
      'ren03' => 'publish parent=amano-tsuki-renzoku order=3 date=2001-06-03 00:00:00 title=第三話 body=\'<!-- wp:paragraph --><p>第三話の本文。</p><!-- /wp:paragraph -->\' mod=2026-11-05 12:00:00 | _ts_annex_url=\'https://example.com/annex/novel/200106/ren03.htm\' _ts_body_status=\'raw-fallback\' _ts_catalog_ref=\'lib01.html#3\' _ts_corpus=\'kyukan\' _ts_episode_id=\'novel__200106__ren03.htm\' _ts_import_hash=\'2dfcba44f14446c43d4f176a8401f2ae\' _ts_kind=\'episode\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_nav_links=[{"href":"novel/200104/ren01.htm","label":"最初へ"}] _ts_orig_url=\'http://ts.novels.jp/novel/200106/ren03.htm\' _ts_osusume={"recommender":"読者 一","refs":[{"href":"novel/200104/ren01.htm","title":"第一話"}]} _ts_provenance={"route":"wayback","route_label":"Internet Archive Wayback Machine"} _ts_pub_date_raw=\'2001/06/03\' _ts_raw_genre=["コメディ","学園"] _ts_raw_keywords=["あめ","ねこ"] _ts_raw_type=["変身","入れ替わり"] _ts_size_kb=12 _ts_source_path=\'novel/200106/ren03.htm\' | ts_author=amano-tsuki ts_corpus=kyukan ts_genre=komedi,gakuen ts_keyword=ame,neko ts_type=henshin,irekawari ts_world=',
      'nazo01' => 'publish parent=umino-nami-nazo order=1 date=2003-01-01 00:00:00 title=謎の上 body=\'<!-- wp:paragraph --><p>謎の上の本文。</p><!-- /wp:paragraph -->\' mod=2026-11-05 12:00:00 | _ts_annex_url=\'https://example.com/annex/novel/200301/nazo01.htm\' _ts_body_status=\'converted\' _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_id=\'novel__200301__nazo01.htm\' _ts_files_n=2 _ts_import_hash=\'40046a7d936b2ac0f4cefb0e49630c2b\' _ts_kind=\'episode\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_orig_url=\'http://ts.novels.jp/novel/200301/nazo01.htm\' _ts_provenance={"route":"wayback","route_label":"Internet Archive Wayback Machine"} _ts_pub_date_raw=\'2003/01/01\' _ts_raw_genre=["学園"] _ts_raw_keywords=["?","ねこ"] _ts_size_kb=12 _ts_source_path=\'novel/200301/nazo01.htm\' | ts_author=umino_nami ts_corpus=honkan ts_genre=gakuen ts_keyword=neko ts_type= ts_world=hoshi-no-machi',
      'nazo02' => 'publish parent=umino-nami-nazo order=2 date=2003-01-02 00:00:00 title=謎の下 body=\'\' mod=2026-11-05 12:00:00 | _ts_annex_url=\'https://example.com/annex/novel/200301/nazo02.htm\' _ts_body_status=\'takedown\' _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_id=\'novel__200301__nazo02.htm\' _ts_import_hash=\'ef64d6c2eb74186ed895a80817de4f5b\' _ts_kind=\'episode\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_orig_url=\'http://ts.novels.jp/novel/200301/nazo02.htm\' _ts_provenance={"route":"wayback","route_label":"Internet Archive Wayback Machine"} _ts_pub_date_raw=\'2003/01/02\' _ts_raw_type=["変身"] _ts_size_kb=12 _ts_source_path=\'novel/200301/nazo02.htm\' | ts_author=umino_nami ts_corpus=honkan ts_genre= ts_keyword= ts_type=henshin ts_world=',
      'kodoku' => 'publish parent= order=0 date=2003-02-02 00:00:00 title=孤独 body=\'<!-- wp:paragraph --><p>(本文は原本アーカイブでお読みください)</p><!-- /wp:paragraph -->\' mod=2026-11-05 12:00:00 | _ts_annex_url=\'https://example.com/annex/novel/200302/kodoku.htm\' _ts_body_status=\'converted\' _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_id=\'novel__200302__kodoku.htm\' _ts_import_hash=\'7d49a52f0376643d699abd4124e8b2f3\' _ts_kind=\'episode\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_pub_date_raw=\'2003/02/02\' _ts_raw_genre=["コメディ"] _ts_size_kb=12 _ts_source_path=\'novel/200302/kodoku.htm\' | ts_author= ts_corpus=honkan ts_genre=komedi ts_keyword= ts_type= ts_world=',
    ),
    'gone' => 
    array (
    ),
  ),
  'bodies_again/out' => 
  array (
    0 => 
    array (
      0 => 'success',
      1 => 'import: created=0 updated=0 skipped=12 manual_skipped=0 catalog=',
    ),
  ),
  'bodies_again/writes' => 
  array (
    'delete_option' => 2,
    'wp_set_object_terms' => 10,
  ),
  'bodies_again/options' => 
  array (
    'ts_catalog_commit' => '(無い)',
    'ts_last_import_at' => '(無い)',
    'ts_manual_edit_warnings' => '(無い)',
    'ts_term_warnings' => '(無い)',
  ),
  'bodies_again/posts' => 
  array (
    'changed' => 
    array (
    ),
    'gone' => 
    array (
    ),
  ),
  'verify2/out' => 
  array (
    0 => 
    array (
      0 => 'line',
      1 => '投稿数: WP=10 期待=10 (works 4 + episodes 8 - 単発 2)',
    ),
    1 => 
    array (
      0 => 'line',
      1 => 'orphan 子投稿: 0',
    ),
    2 => 
    array (
      0 => 'line',
      1 => 'ts_author: WP=5 語彙=5 語彙外=0 未作成=0',
    ),
    3 => 
    array (
      0 => 'line',
      1 => 'ts_genre: WP=2 語彙=2 語彙外=0 未作成=0',
    ),
    4 => 
    array (
      0 => 'line',
      1 => 'ts_type: WP=2 語彙=2 語彙外=0 未作成=0',
    ),
    5 => 
    array (
      0 => 'line',
      1 => 'ts_keyword: WP=2 語彙=2 語彙外=0 未作成=0',
    ),
    6 => 
    array (
      0 => 'line',
      1 => 'ts_world: WP=1 語彙=1 語彙外=0 未作成=0',
    ),
    7 => 
    array (
      0 => 'line',
      1 => 'ts_corpus: WP=2 語彙=2 語彙外=0 未作成=0',
    ),
    8 => 
    array (
      0 => 'line',
      1 => '割当 ts_author: WP=9 期待=9',
    ),
    9 => 
    array (
      0 => 'line',
      1 => '割当 ts_genre: WP=10 期待=10',
    ),
    10 => 
    array (
      0 => 'line',
      1 => '割当 ts_type: WP=9 期待=9',
    ),
    11 => 
    array (
      0 => 'line',
      1 => '割当 ts_keyword: WP=8 期待=8',
    ),
    12 => 
    array (
      0 => 'line',
      1 => '割当 ts_world: WP=4 期待=4',
    ),
    13 => 
    array (
      0 => 'line',
      1 => '割当 ts_corpus: WP=11 期待=11',
    ),
    14 => 
    array (
      0 => 'line',
      1 => 'メタ _ts_pub_date_raw: WP=8 期待=8',
    ),
    15 => 
    array (
      0 => 'line',
      1 => 'メタ _ts_orig_url: WP=6 期待=6',
    ),
    16 => 
    array (
      0 => 'line',
      1 => 'メタ _ts_annex_url: WP=7 期待=7',
    ),
    17 => 
    array (
      0 => 'line',
      1 => 'メタ _ts_provenance: WP=7 期待=7',
    ),
    18 => 
    array (
      0 => 'line',
      1 => '本文 status: converted 4 / placeholder 2 / raw-fallback 1 / takedown 1',
    ),
    19 => 
    array (
      0 => 'line',
      1 => 'ts_takedown_tick の最終実行: now-1h',
    ),
    20 => 
    array (
      0 => 'line',
      1 => '適用済みの申請で denylist に無いもの: 0 件',
    ),
    21 => 
    array (
      0 => 'line',
      1 => 'ts_catalog_commit: (未記録)',
    ),
    22 => 
    array (
      0 => 'line',
      1 => '(冪等の確認は `wp ts import --dry-run` 2 回目が created=0 updated=0 になること)',
    ),
    23 => 
    array (
      0 => 'success',
      1 => 'verify OK',
    ),
  ),
  'author/out' => 
  array (
    0 => 
    array (
      0 => 'success',
      1 => 'import: created=0 updated=0 skipped=2 manual_skipped=0 catalog= (dry-run)',
    ),
  ),
  'author/writes' => 
  array (
  ),
  'author/options' => 
  array (
    'ts_catalog_commit' => '(無い)',
    'ts_last_import_at' => '(無い)',
    'ts_manual_edit_warnings' => '(無い)',
    'ts_term_warnings' => '(無い)',
  ),
  'author/posts' => 
  array (
    'changed' => 
    array (
    ),
    'gone' => 
    array (
    ),
  ),
  'limit/out' => 
  array (
    0 => 
    array (
      0 => 'success',
      1 => 'import: created=0 updated=0 skipped=5 manual_skipped=0 catalog= (dry-run)',
    ),
  ),
  'limit/writes' => 
  array (
  ),
  'limit/options' => 
  array (
    'ts_catalog_commit' => '(無い)',
    'ts_last_import_at' => '(無い)',
    'ts_manual_edit_warnings' => '(無い)',
    'ts_term_warnings' => '(無い)',
  ),
  'limit/posts' => 
  array (
    'changed' => 
    array (
    ),
    'gone' => 
    array (
    ),
  ),
  'dry_changed/out' => 
  array (
    0 => 
    array (
      0 => 'warning',
      1 => '手動編集を検出して skip した投稿: 1 件 (`wp ts verify` で一覧。上書きするには --overwrite-manual)',
    ),
    1 => 
    array (
      0 => 'success',
      1 => 'import: created=0 updated=2 skipped=9 manual_skipped=1 catalog= (dry-run)',
    ),
  ),
  'dry_changed/writes' => 
  array (
    'update_option' => 1,
  ),
  'dry_changed/options' => 
  array (
    'ts_catalog_commit' => '(無い)',
    'ts_last_import_at' => '(無い)',
    'ts_manual_edit_warnings' => 
    array (
      0 => 
      array (
        'kind' => 'episode',
        'key' => 'novel__200105__ren02.htm',
        'modified' => '2026-11-06 09:00:00',
      ),
    ),
    'ts_term_warnings' => '(無い)',
  ),
  'dry_changed/posts' => 
  array (
    'changed' => 
    array (
      'amano-tsuki-renzoku' => 'publish parent= order=0 date=2001-04-01 00:00:00 title=連続する月 body=\'\' mod=2026-11-05 12:00:00 | _ts_alias_paths=["novel/200104/ren.htm"] _ts_episode_count=3 _ts_import_hash=\'453955fd1c3c845fd8871183d066b588\' _ts_kind=\'work\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_needs_review=1 _ts_title_pages=["novel/title/renzoku.htm"] _ts_work_slug=\'amano-tsuki-renzoku\' | ts_author=amano-tsuki ts_corpus=honkan,kyukan ts_genre=gakuen,komedi ts_keyword=neko ts_type=henshin,irekawari ts_world=hoshi-no-machi',
      'ren02' => 'publish parent=amano-tsuki-renzoku order=2 date=2001-05-02 00:00:00 title=手で直した題 body=\'<!-- wp:paragraph --><p>(本文は原本アーカイブでお読みください)</p><!-- /wp:paragraph -->\' mod=2026-11-06 09:00:00 | _ts_annex_url=\'https://example.com/annex/novel/200105/ren02.htm\' _ts_body_status=\'placeholder\' _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_id=\'novel__200105__ren02.htm\' _ts_import_hash=\'003ccdb931a3572441722f2bcf809fa8\' _ts_kind=\'episode\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_provenance={"route":"wayback","route_label":"Internet Archive Wayback Machine"} _ts_pub_date_raw=\'2001/05/02\' _ts_raw_genre=["学園？"] _ts_size_kb=8 _ts_source_path=\'novel/200105/ren02.htm\' | ts_author=amano-tsuki ts_corpus=honkan ts_genre=gakuen ts_keyword= ts_type= ts_world=',
      'ren03' => 'publish parent=amano-tsuki-renzoku order=3 date=2001-06-03 00:00:00 title=第三話 body=\'<!-- wp:paragraph --><p>第三話の本文。</p><!-- /wp:paragraph -->\' mod=2026-11-05 12:00:00 | _ts_annex_url=\'https://example.com/annex/novel/200106/ren03.htm\' _ts_body_status=\'raw-fallback\' _ts_catalog_ref=\'lib01.html#3\' _ts_corpus=\'kyukan\' _ts_episode_id=\'novel__200106__ren03.htm\' _ts_import_hash=\'2dfcba44f14446c43d4f176a8401f2ae\' _ts_kind=\'episode\' _ts_last_import_gmt=\'2026-11-05 12:00:00\' _ts_nav_links=[{"href":"novel/200104/ren01.htm","label":"最初へ"}] _ts_orig_url=\'http://ts.novels.jp/novel/200106/ren03.htm\' _ts_osusume={"recommender":"読者 一","refs":[{"href":"novel/200104/ren01.htm","title":"第一話"}]} _ts_provenance={"route":"wayback","route_label":"Internet Archive Wayback Machine"} _ts_pub_date_raw=\'2001/06/03\' _ts_raw_genre=["コメディ","学園"] _ts_raw_keywords=["あめ","ねこ"] _ts_raw_type=["変身","入れ替わり"] _ts_size_kb=12 _ts_source_path=\'novel/200106/ren03.htm\' | ts_author=amano-tsuki ts_corpus=kyukan ts_genre=komedi,gakuen ts_keyword=neko ts_type=henshin,irekawari ts_world=',
    ),
    'gone' => 
    array (
    ),
  ),
  'manual/out' => 
  array (
    0 => 
    array (
      0 => 'warning',
      1 => '手動編集を検出して skip した投稿: 1 件 (`wp ts verify` で一覧。上書きするには --overwrite-manual)',
    ),
    1 => 
    array (
      0 => 'warning',
      1 => 'term に解決できず割当を落とした値: 2 種 (上位: ts_genre:ホラー ts_keyword:あめ)',
    ),
    2 => 
    array (
      0 => 'success',
      1 => 'import: created=0 updated=2 skipped=9 manual_skipped=1 catalog=',
    ),
  ),
  'manual/writes' => 
  array (
    'update_option' => 2,
    'wp_set_object_terms' => 17,
    'wp_update_post' => 2,
  ),
  'manual/options' => 
  array (
    'ts_catalog_commit' => '(無い)',
    'ts_last_import_at' => '(無い)',
    'ts_manual_edit_warnings' => 
    array (
      0 => 
      array (
        'kind' => 'episode',
        'key' => 'novel__200105__ren02.htm',
        'modified' => '2026-11-06 09:00:00',
      ),
    ),
    'ts_term_warnings' => 
    array (
      'ts_genre:ホラー' => 2,
      'ts_keyword:あめ' => 1,
    ),
  ),
  'manual/posts' => 
  array (
    'changed' => 
    array (
      'amano-tsuki-renzoku' => 'publish parent= order=0 date=2001-04-01 00:00:00 title=連続する月 (改) body=\'\' mod=2026-11-06 09:00:00 | _ts_alias_paths=["novel/200104/ren.htm"] _ts_episode_count=3 _ts_import_hash=\'b9089e7d5c4aa3a2c22ba13e64b266b7\' _ts_kind=\'work\' _ts_last_import_gmt=\'2026-11-06 09:00:00\' _ts_needs_review=1 _ts_title_pages=["novel/title/renzoku.htm"] _ts_work_slug=\'amano-tsuki-renzoku\' | ts_author=amano-tsuki ts_corpus=honkan,kyukan ts_genre=gakuen,komedi ts_keyword=neko ts_type=henshin,irekawari ts_world=hoshi-no-machi',
      'ren01' => 'publish parent=amano-tsuki-renzoku order=1 date=2001-04-01 00:00:00 title=第一話 body=\'<!-- wp:paragraph --><p>第一話の本文。</p><!-- /wp:paragraph -->\' mod=2026-11-06 09:00:00 | _ts_annex_url=\'https://example.com/annex/novel/200104/ren01.htm\' _ts_arasuji=\'直したあらすじ\' _ts_author_comment=\'ありがとう\' _ts_body_status=\'converted\' _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_id=\'novel__200104__ren01.htm\' _ts_illustrator=["絵筆 太郎"] _ts_import_hash=\'84b92f0f9fbb529cb3b7e442ba0aebd7\' _ts_kansou_annex_url=\'https://example.com/annex/bbs/amano.cgi\' _ts_kansou_slug=\'amano\' _ts_kind=\'episode\' _ts_last_import_gmt=\'2026-11-06 09:00:00\' _ts_orig_url=\'http://ts.novels.jp/novel/200104/ren01.htm\' _ts_provenance={"route":"wayback","route_label":"Internet Archive Wayback Machine"} _ts_pub_date_raw=\'2001/04/01\' _ts_raw_genre=["学園"] _ts_raw_keywords=["ねこ"] _ts_raw_type=["変身"] _ts_size_kb=12 _ts_source_path=\'novel/200104/ren01.htm\' _ts_suisen=\'胸が熱くなる\' | ts_author=amano-tsuki ts_corpus=honkan ts_genre=gakuen ts_keyword=neko ts_type=henshin ts_world=hoshi-no-machi',
    ),
    'gone' => 
    array (
    ),
  ),
  'verify3/out' => 
  array (
    0 => 
    array (
      0 => 'line',
      1 => '投稿数: WP=10 期待=10 (works 4 + episodes 8 - 単発 2)',
    ),
    1 => 
    array (
      0 => 'line',
      1 => 'orphan 子投稿: 0',
    ),
    2 => 
    array (
      0 => 'line',
      1 => 'ts_author: WP=5 語彙=5 語彙外=0 未作成=0',
    ),
    3 => 
    array (
      0 => 'line',
      1 => 'ts_genre: WP=2 語彙=2 語彙外=0 未作成=0',
    ),
    4 => 
    array (
      0 => 'line',
      1 => 'ts_type: WP=2 語彙=2 語彙外=0 未作成=0',
    ),
    5 => 
    array (
      0 => 'line',
      1 => 'ts_keyword: WP=1 語彙=2 語彙外=0 未作成=1',
    ),
    6 => 
    array (
      0 => 'warning',
      1 => 'ts_keyword 未作成 term: ame',
    ),
    7 => 
    array (
      0 => 'line',
      1 => 'ts_world: WP=1 語彙=1 語彙外=0 未作成=0',
    ),
    8 => 
    array (
      0 => 'line',
      1 => 'ts_corpus: WP=2 語彙=2 語彙外=0 未作成=0',
    ),
    9 => 
    array (
      0 => 'line',
      1 => '割当 ts_author: WP=9 期待=9',
    ),
    10 => 
    array (
      0 => 'line',
      1 => '割当 ts_genre: WP=10 期待=10',
    ),
    11 => 
    array (
      0 => 'line',
      1 => '割当 ts_type: WP=9 期待=9',
    ),
    12 => 
    array (
      0 => 'line',
      1 => '割当 ts_keyword: WP=6 期待=8',
    ),
    13 => 
    array (
      0 => 'warning',
      1 => 'ts_keyword の割当数不一致',
    ),
    14 => 
    array (
      0 => 'line',
      1 => '割当 ts_world: WP=4 期待=4',
    ),
    15 => 
    array (
      0 => 'line',
      1 => '割当 ts_corpus: WP=11 期待=11',
    ),
    16 => 
    array (
      0 => 'line',
      1 => 'メタ _ts_pub_date_raw: WP=8 期待=8',
    ),
    17 => 
    array (
      0 => 'line',
      1 => 'メタ _ts_orig_url: WP=6 期待=6',
    ),
    18 => 
    array (
      0 => 'line',
      1 => 'メタ _ts_annex_url: WP=7 期待=7',
    ),
    19 => 
    array (
      0 => 'line',
      1 => 'メタ _ts_provenance: WP=7 期待=7',
    ),
    20 => 
    array (
      0 => 'line',
      1 => '本文 status: converted 4 / placeholder 2 / raw-fallback 1 / takedown 1',
    ),
    21 => 
    array (
      0 => 'warning',
      1 => 'term 未解決の値 (2 種): ts_genre:ホラー ts_keyword:あめ',
    ),
    22 => 
    array (
      0 => 'warning',
      1 => '手動編集で skip 中の投稿 (1 件):',
    ),
    23 => 
    array (
      0 => 'line',
      1 => '  [episode] novel__200105__ren02.htm (最終編集 2026-11-06 09:00:00)',
    ),
    24 => 
    array (
      0 => 'line',
      1 => 'ts_takedown_tick の最終実行: now-1h',
    ),
    25 => 
    array (
      0 => 'line',
      1 => '適用済みの申請で denylist に無いもの: 0 件',
    ),
    26 => 
    array (
      0 => 'line',
      1 => 'ts_catalog_commit: (未記録)',
    ),
    27 => 
    array (
      0 => 'line',
      1 => '(冪等の確認は `wp ts import --dry-run` 2 回目が created=0 updated=0 になること)',
    ),
    28 => 
    array (
      0 => 'error',
      1 => 'verify NG',
    ),
  ),
  'overwrite/out' => 
  array (
    0 => 
    array (
      0 => 'warning',
      1 => 'term に解決できず割当を落とした値: 2 種 (上位: ts_genre:ホラー ts_keyword:あめ)',
    ),
    1 => 
    array (
      0 => 'success',
      1 => 'import: created=0 updated=1 skipped=11 manual_skipped=0 catalog=',
    ),
  ),
  'overwrite/writes' => 
  array (
    'delete_option' => 1,
    'update_option' => 1,
    'wp_set_object_terms' => 16,
    'wp_update_post' => 1,
  ),
  'overwrite/options' => 
  array (
    'ts_catalog_commit' => '(無い)',
    'ts_last_import_at' => '(無い)',
    'ts_manual_edit_warnings' => '(無い)',
    'ts_term_warnings' => 
    array (
      'ts_genre:ホラー' => 1,
      'ts_keyword:あめ' => 1,
    ),
  ),
  'overwrite/posts' => 
  array (
    'changed' => 
    array (
      'ren02' => 'publish parent=amano-tsuki-renzoku order=2 date=2001-05-02 00:00:00 title=第二話 (改) body=\'<!-- wp:paragraph --><p>(本文は原本アーカイブでお読みください)</p><!-- /wp:paragraph -->\' mod=2026-11-06 09:00:00 | _ts_annex_url=\'https://example.com/annex/novel/200105/ren02.htm\' _ts_body_status=\'placeholder\' _ts_catalog_ref=\'lib1.html#1\' _ts_corpus=\'honkan\' _ts_episode_id=\'novel__200105__ren02.htm\' _ts_import_hash=\'c13f98b90c96d756d1c89591af1a2d3a\' _ts_kind=\'episode\' _ts_last_import_gmt=\'2026-11-06 09:00:00\' _ts_provenance={"route":"wayback","route_label":"Internet Archive Wayback Machine"} _ts_pub_date_raw=\'2001/05/02\' _ts_raw_genre=["学園？"] _ts_size_kb=8 _ts_source_path=\'novel/200105/ren02.htm\' | ts_author=amano-tsuki ts_corpus=honkan ts_genre=gakuen ts_keyword= ts_type= ts_world=',
    ),
    'gone' => 
    array (
    ),
  ),
  'dry_dup/out' => 
  array (
    0 => 
    array (
      0 => 'warning',
      1 => '重複検出: ts_work _ts_episode_id=novel__200106__ren03.htm が 2 件',
    ),
    1 => 
    array (
      0 => 'success',
      1 => 'import: created=0 updated=0 skipped=12 manual_skipped=0 catalog= (dry-run)',
    ),
  ),
  'dry_dup/writes' => 
  array (
  ),
  'dry_dup/options' => 
  array (
    'ts_catalog_commit' => '(無い)',
    'ts_last_import_at' => '(無い)',
    'ts_manual_edit_warnings' => '(無い)',
    'ts_term_warnings' => 
    array (
      'ts_genre:ホラー' => 1,
      'ts_keyword:あめ' => 1,
    ),
  ),
  'dry_dup/posts' => 
  array (
    'changed' => 
    array (
      'hagure' => 'publish parent=#999 order=0 date=1999-01-01 00:00:00 title=はぐれ body=\'\' mod=1999-01-01 00:00:00 | _ts_body_status=\'converted\' _ts_episode_id=\'novel__200106__ren03.htm\' _ts_pub_date_raw=\'1999/1/1\' | ts_genre=gakuen',
    ),
    'gone' => 
    array (
    ),
  ),
  'verify4/out' => 
  array (
    0 => 
    array (
      0 => 'line',
      1 => '投稿数: WP=11 期待=10 (works 4 + episodes 8 - 単発 2)',
    ),
    1 => 
    array (
      0 => 'warning',
      1 => '件数不一致',
    ),
    2 => 
    array (
      0 => 'line',
      1 => 'orphan 子投稿: 1',
    ),
    3 => 
    array (
      0 => 'warning',
      1 => 'orphan あり',
    ),
    4 => 
    array (
      0 => 'line',
      1 => 'ts_author: WP=5 語彙=5 語彙外=0 未作成=0',
    ),
    5 => 
    array (
      0 => 'line',
      1 => 'ts_genre: WP=3 語彙=2 語彙外=1 未作成=0',
    ),
    6 => 
    array (
      0 => 'warning',
      1 => 'ts_genre 語彙外 term: yami',
    ),
    7 => 
    array (
      0 => 'line',
      1 => 'ts_type: WP=2 語彙=2 語彙外=0 未作成=0',
    ),
    8 => 
    array (
      0 => 'line',
      1 => 'ts_keyword: WP=1 語彙=2 語彙外=0 未作成=1',
    ),
    9 => 
    array (
      0 => 'warning',
      1 => 'ts_keyword 未作成 term: ame',
    ),
    10 => 
    array (
      0 => 'line',
      1 => 'ts_world: WP=1 語彙=1 語彙外=0 未作成=0',
    ),
    11 => 
    array (
      0 => 'line',
      1 => 'ts_corpus: WP=2 語彙=2 語彙外=0 未作成=0',
    ),
    12 => 
    array (
      0 => 'line',
      1 => '割当 ts_author: WP=9 期待=9',
    ),
    13 => 
    array (
      0 => 'line',
      1 => '割当 ts_genre: WP=11 期待=10',
    ),
    14 => 
    array (
      0 => 'warning',
      1 => 'ts_genre の割当数不一致',
    ),
    15 => 
    array (
      0 => 'line',
      1 => '割当 ts_type: WP=9 期待=9',
    ),
    16 => 
    array (
      0 => 'line',
      1 => '割当 ts_keyword: WP=6 期待=8',
    ),
    17 => 
    array (
      0 => 'warning',
      1 => 'ts_keyword の割当数不一致',
    ),
    18 => 
    array (
      0 => 'line',
      1 => '割当 ts_world: WP=4 期待=4',
    ),
    19 => 
    array (
      0 => 'line',
      1 => '割当 ts_corpus: WP=11 期待=11',
    ),
    20 => 
    array (
      0 => 'line',
      1 => 'メタ _ts_pub_date_raw: WP=9 期待=8',
    ),
    21 => 
    array (
      0 => 'warning',
      1 => '_ts_pub_date_raw の被覆不一致',
    ),
    22 => 
    array (
      0 => 'line',
      1 => 'メタ _ts_orig_url: WP=6 期待=6',
    ),
    23 => 
    array (
      0 => 'line',
      1 => 'メタ _ts_annex_url: WP=7 期待=7',
    ),
    24 => 
    array (
      0 => 'line',
      1 => 'メタ _ts_provenance: WP=7 期待=7',
    ),
    25 => 
    array (
      0 => 'line',
      1 => '本文 status: converted 5 / placeholder 2 / raw-fallback 1 / takedown 1',
    ),
    26 => 
    array (
      0 => 'warning',
      1 => 'term 未解決の値 (2 種): ts_genre:ホラー ts_keyword:あめ',
    ),
    27 => 
    array (
      0 => 'line',
      1 => 'ts_takedown_tick の最終実行: now-48h (失敗: DB error)',
    ),
    28 => 
    array (
      0 => 'warning',
      1 => 'ts_takedown_tick が 24 時間以上止まっている (S130)',
    ),
    29 => 
    array (
      0 => 'warning',
      1 => 'ts_takedown_tick の最後の実行が失敗している',
    ),
    30 => 
    array (
      0 => 'line',
      1 => '適用済みの申請で denylist に無いもの: 1 件',
    ),
    31 => 
    array (
      0 => 'warning',
      1 => 'denylist に書き写していない申請: 12 (wp ts export-takedowns。S132)',
    ),
    32 => 
    array (
      0 => 'line',
      1 => 'ts_catalog_commit: (未記録)',
    ),
    33 => 
    array (
      0 => 'line',
      1 => '(冪等の確認は `wp ts import --dry-run` 2 回目が created=0 updated=0 になること)',
    ),
    34 => 
    array (
      0 => 'error',
      1 => 'verify NG',
    ),
  ),
));
