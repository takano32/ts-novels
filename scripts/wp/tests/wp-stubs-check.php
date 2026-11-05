<?php
/**
 * WordPress の代わり (scripts/wp/tests/wp-stubs.php) そのものの検査: 置き場の読み書きが WordPress の決まり (meta の single と複数、
 * get_posts の既定と並び、get_the_terms の無いとき false、階層の permalink、wp_die は止まる、フックは priority の順) に合っていること。
 * 代わりの振る舞いが変わると WP の表の試験の期待値が動くので、ここで固定する。scripts/wp/ts_library_test.php から読む。
 */

TS_WP_Stubs::reset();
$w = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_name' => 'oya', 'post_title' => '親', 'post_date' => '2001-01-01 00:00:00']);
$c1 = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => $w, 'post_name' => 'ko1', 'post_title' => '子 1', 'menu_order' => 2, 'post_date' => '2001-02-01 00:00:00']);
$c2 = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => $w, 'post_name' => 'ko2', 'post_title' => '子 2', 'menu_order' => 1, 'post_date' => '2001-03-01 00:00:00']);
$c3 = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => $w, 'post_name' => 'ko3', 'post_title' => '子 3', 'menu_order' => 3, 'post_date' => '2001-03-01 00:00:00', 'post_status' => 'private']);
$d = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_name' => 'shitagaki', 'post_title' => '下書き', 'post_status' => 'draft']);
$doc = TS_WP_Stubs::add_post(['post_type' => 'ts_doc', 'post_name' => 'kaidai', 'post_title' => '解題']);

// ---------------------------------------------------------------- meta
check('meta: 無いキーは single で空文字、複数で空配列', [get_post_meta($w, '_ts_x', true), get_post_meta($w, '_ts_x')], ['', []]);
update_post_meta($w, '_ts_x', ['a' => 1]);
check('meta: 配列はそのまま返る (直列化しない)', get_post_meta($w, '_ts_x', true), ['a' => 1]);
update_post_meta($w, '_ts_y', wp_slash("O'Neil"));
check('meta: 書くときに slash を外す (WordPress と同じ)', get_post_meta($w, '_ts_y', true), "O'Neil");
add_post_meta($w, '_ts_log', 'one'); add_post_meta($w, '_ts_log', 'two');
check('meta: add_post_meta は増える・single は最初の値', [get_post_meta($w, '_ts_log'), get_post_meta($w, '_ts_log', true)], [['one', 'two'], 'one']);
check('meta: update は値が同じなら false、違えば true', [update_post_meta($w, '_ts_y', "O'Neil"), update_post_meta($w, '_ts_y', 'x')], [false, true]);
delete_post_meta($w, '_ts_y');
check('meta: delete で消える', get_post_meta($w, '_ts_y', true), '');
check('meta: 全部 (キー => 値の配列)', get_post_meta($w), ['_ts_x' => [['a' => 1]], '_ts_log' => ['one', 'two']]);

// ---------------------------------------------------------------- 投稿と問い合わせ
check('get_post は複製を返す (変えても置き場は変わらない)', (function () use ($w) { $p = get_post($w); $p->post_status = 'private'; return get_post_status($w); })(), 'publish');
check('get_post: 無い ID は null、オブジェクトはそのまま', [get_post(999), get_post(TS_WP_Stubs::$posts[$w]) === TS_WP_Stubs::$posts[$w]], [null, true]);
check('get_posts の既定: publish だけ・日付の新しい順・5 件', array_map(function ($p) { return $p->ID; }, get_posts(['post_type' => 'ts_work'])), [$c2, $c1, $w]);
check('get_posts: 同じ日付は ID の昇順 (本番では決まらない)', get_posts(['post_type' => 'ts_work', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1]),
      [$d, $c2, $c3, $c1, $w]);
check('get_posts: fields=ids は int', get_posts(['post_type' => 'ts_work', 'post_parent' => $w, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids']), [$c2, $c3, $c1]);
check('get_posts: menu_order の昇順', get_posts(['post_type' => 'ts_work', 'post_parent' => $w, 'post_status' => ['publish', 'private'], 'posts_per_page' => -1,
      'orderby' => 'menu_order', 'order' => 'ASC', 'fields' => 'ids']), [$c2, $c1, $c3]);
check('get_posts: post_parent=0 は親だけ', get_posts(['post_type' => 'ts_work', 'post_parent' => 0, 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC']), [$w]);
TS_WP_Stubs::set_meta($c1, ['_ts_visibility' => 'withdrawn', '_ts_illustrator' => ['絵師 A', '絵師 B']]);
check('get_posts: meta_key と meta_value', get_posts(['post_type' => 'ts_work', 'post_status' => 'any', 'meta_key' => '_ts_visibility', 'meta_value' => 'withdrawn', 'fields' => 'ids']), [$c1]);
check('get_posts: meta_compare IN', get_posts(['post_type' => 'ts_work', 'post_status' => 'any', 'meta_key' => '_ts_visibility', 'meta_value' => ['x', 'withdrawn'], 'meta_compare' => 'IN', 'fields' => 'ids']), [$c1]);
check('get_posts: meta_query の LIKE は直列化した形で見る (配列のメタの要素の完全一致)',
      [get_posts(['post_type' => 'ts_work', 'fields' => 'ids', 'meta_query' => [['key' => '_ts_illustrator', 'value' => '"絵師 A"', 'compare' => 'LIKE']]]),
       get_posts(['post_type' => 'ts_work', 'fields' => 'ids', 'meta_query' => [['key' => '_ts_illustrator', 'value' => '"絵師"', 'compare' => 'LIKE']]])], [[$c1], []]);
check('get_posts: post__in', get_posts(['post_type' => 'ts_work', 'post__in' => [$w, $c2], 'fields' => 'ids', 'orderby' => 'date', 'order' => 'ASC']), [$w, $c2]);
check('get_children: fields なしは ID をキーに', array_keys(get_children(['post_parent' => $w, 'post_type' => 'ts_work', 'post_status' => 'publish'])), [$c2, $c1]);
check('WP_Query: posts と件数', (function () use ($w) { $q = new WP_Query(['post_type' => 'ts_work', 'post_status' => 'any', 'posts_per_page' => 2, 'meta_key' => '_ts_x', 'fields' => 'ids']);
      return [$q->posts, $q->post_count, $q->is_main_query()]; })(), [[$w], 1, false]);
check('wp_count_posts', [wp_count_posts('ts_work')->publish, wp_count_posts('ts_work')->private, wp_count_posts('ts_work')->draft], [3, 1, 1]);

// ---------------------------------------------------------------- permalink とパス
check('permalink: 階層 (親/子) と基底 /novel/', [get_permalink($w), get_permalink($c1)], ['https://novels.xwp.jp/novel/oya/', 'https://novels.xwp.jp/novel/oya/ko1/']);
check('permalink: private は整った URL のまま、draft は仮の URL', [get_permalink($c3), get_permalink($d)],
      ['https://novels.xwp.jp/novel/oya/ko3/', 'https://novels.xwp.jp/?post_type=ts_work&p=' . $d]);
check('permalink: 階層の無い投稿タイプと URL を持たない投稿タイプ', [get_permalink($doc), get_permalink(TS_WP_Stubs::add_post(['post_type' => 'ts_takedown_request', 'post_status' => 'private']))],
      ['https://novels.xwp.jp/docs/kaidai/', 'https://novels.xwp.jp/?post_type=ts_takedown_request&p=7']);
check('get_page_by_path は状態を問わない・パーセント符号化を外す', [get_page_by_path('oya/ko3', OBJECT, 'ts_work')->ID, get_page_by_path('oya/ko%31', OBJECT, 'ts_work')->ID,
      get_page_by_path('oya/ko9', OBJECT, 'ts_work'), get_page_by_path('oya/ko1', OBJECT, 'page')], [$c3, $c1, null, null]);
check('url_to_postid', [url_to_postid('https://novels.xwp.jp/novel/oya/ko2/'), url_to_postid('https://novels.xwp.jp/novel/nai/')], [$c2, 0]);
check('wp_make_link_relative', wp_make_link_relative('https://novels.xwp.jp/novel/oya/?x=1'), '/novel/oya/?x=1');
check('home_url と admin_url と add_query_arg', [home_url(), home_url('/index/kana/'), home_url('?s='), admin_url('admin-post.php'),
      add_query_arg('ts_confirm', 'abc', home_url('/')), add_query_arg(['a' => '1', 'b' => false], 'https://x.test/p/?b=2#f')],
      ['https://novels.xwp.jp', 'https://novels.xwp.jp/index/kana/', 'https://novels.xwp.jp/?s=', 'https://novels.xwp.jp/wp-admin/admin-post.php',
       'https://novels.xwp.jp/?ts_confirm=abc', 'https://x.test/p/?a=1#f']);

// ---------------------------------------------------------------- term
$t_b = TS_WP_Stubs::add_term(['taxonomy' => 'ts_author', 'name' => 'ぶ', 'slug' => 'bu']);
$t_a = TS_WP_Stubs::add_term(['taxonomy' => 'ts_author', 'name' => 'あ', 'slug' => 'a']);
$t_g = TS_WP_Stubs::add_term(['taxonomy' => 'ts_genre', 'name' => 'ＳＦ', 'slug' => 'sf', 'description' => '説明']);
check('get_the_terms: 無ければ false', get_the_terms($w, 'ts_author'), false);
TS_WP_Stubs::set_terms($w, 'ts_author', [$t_b, $t_a]);
TS_WP_Stubs::set_terms($c1, 'ts_genre', [$t_g]);
check('get_the_terms: 名前の順・count は付いている公開投稿の数', array_map(function ($t) { return [$t->slug, $t->count]; }, get_the_terms($w, 'ts_author')), [['a', 1], ['bu', 1]]);
check('get_terms: hide_empty の既定と orderby count', [array_map(function ($t) { return $t->slug; }, get_terms(['taxonomy' => 'ts_author'])),
      array_map(function ($t) { return $t->slug; }, get_terms(['taxonomy' => ['ts_author', 'ts_genre'], 'hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC']))],
      [['a', 'bu'], ['bu', 'a', 'sf']]);
check('get_term_link と get_term_by と has_term', [get_term_link(get_term($t_a)), get_term_link('sf', 'ts_genre'), get_term_by('slug', 'nai', 'ts_author'),
      has_term($t_a, 'ts_author', $w), has_term('sf', 'ts_genre', $w), is_wp_error(get_term_link(999))],
      ['https://novels.xwp.jp/authors/a/', 'https://novels.xwp.jp/genre/sf/', false, true, false, true]);
check('get_term_by の slug は sanitize_title してから引く (大文字・空)', [get_term_by('slug', 'SF', 'ts_genre')->term_id, get_term_by('slug', 'Bu ', 'ts_author')->term_id, get_term_by('slug', '', 'ts_genre')],
      [$t_g, $t_b, false]);
check('get_terms: fields の id=>slug と id=>name', [get_terms(['taxonomy' => 'ts_genre', 'hide_empty' => false, 'fields' => 'id=>slug']),
      get_terms(['taxonomy' => 'ts_author', 'hide_empty' => false, 'fields' => 'id=>name'])], [[$t_g => 'sf'], [$t_a => 'あ', $t_b => 'ぶ']]);
check('wp_set_object_terms: ID と slug と新しい名前', (function () use ($c2, $t_a) { wp_set_object_terms($c2, [$t_a, 'bu', '新しい人'], 'ts_author');
      return array_map(function ($t) { return $t->name; }, get_the_terms($c2, 'ts_author')); })(), ['あ', 'ぶ', '新しい人']);
check('tax_query', get_posts(['post_type' => 'ts_work', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC',
      'tax_query' => [['taxonomy' => 'ts_author', 'field' => 'term_id', 'terms' => $t_a]]]), [$w, $c2]);
check('term meta', (function () use ($t_a) { update_term_meta($t_a, 'ts_yomi_group', 'あ行'); return [get_term_meta($t_a, 'ts_yomi_group', true), get_term_meta($t_a, 'nai', true)]; })(), ['あ行', '']);

// ---------------------------------------------------------------- 書き込みの記録・option・応答
TS_WP_Stubs::$log = [];
wp_update_post(['ID' => $c3, 'post_status' => 'publish']);
check('wp_update_post: 状態が変わり post_modified は $now、記録が残る', [get_post_status($c3), get_post_field('post_modified_gmt', $c3), TS_WP_Stubs::$log[0][0]],
      ['publish', '2026-11-05 12:00:00', 'wp_update_post']);
check('wp_insert_post: 新しい ID、既定は draft、題名から post_name', (function () { $id = wp_insert_post(['post_type' => 'ts_doc', 'post_title' => 'New Doc']);
      return [$id, get_post_status($id), get_post_field('post_name', $id)]; })(), [8, 'draft', 'new-doc']);
check('wp_insert_post は wp_insert_post_data のフィルタを通す (申請はいつも private)',
      get_post_status(wp_insert_post(['post_type' => TS_Takedown_Rules::POST_TYPE, 'post_status' => 'publish', 'post_title' => '申請'])), 'private');
check('option: 既定・書く・消す', (function () { $a = get_option('ts_x', 'none'); update_option('ts_x', [1]); $b = get_option('ts_x'); delete_option('ts_x'); return [$a, $b, get_option('ts_x')]; })(),
      ['none', [1], false]);
check('transient', (function () { set_transient('k', ['n' => 1], 60); $v = get_transient('k'); delete_transient('k'); return [$v, get_transient('k'), TS_WP_Stubs::$transients]; })(),
      [['n' => 1], false, []]);
check('wp_die は TS_WP_Die を投げる (本番は exit)', (function () { try { wp_die('<p>x</p>', '題', ['response' => 403]); return null; }
      catch (TS_WP_Die $e) { return [$e->getMessage(), $e->title, $e->args['response']]; } })(), ['<p>x</p>', '題', 403]);
check('status_header と nocache_headers は記録', (function () { TS_WP_Stubs::$log = []; status_header(410); nocache_headers(); return [TS_WP_Stubs::$status, TS_WP_Stubs::$log]; })(),
      [410, [['status_header', [410]], ['nocache_headers', []]]]);
check('capture は echo を文字列で受ける', TS_WP_Stubs::capture(function () { echo '<b>', 'x', '</b>'; }), '<b>x</b>');

// ---------------------------------------------------------------- フック
$ts_hooks_saved = TS_WP_Stubs::$hooks;
TS_WP_Stubs::$hooks = [];
add_filter('ts_test', function ($v) { return $v . 'b'; }, 20);
add_filter('ts_test', function ($v) { return $v . 'a'; });
add_filter('ts_test', function ($v, $x) { return $v . $x; }, 10, 2);
check('apply_filters は priority の順 (同じなら登録の順)、accepted_args の分だけ渡す', apply_filters('ts_test', '', 'c'), 'acb');
check('フックの記録 (ファイル名つき)', TS_WP_Stubs::hook_list()[2], 'wp-stubs-check.php: filter ts_test@10 {closure} (2)');
check('has_filter は priority', [has_filter('ts_test'), has_filter('nai')], [true, false]);
TS_WP_Stubs::$hooks = $ts_hooks_saved;
check('ショートコードを呼ぶ', (function () { add_shortcode('ts_x', function ($atts, $content, $tag) { return "[$tag]"; }); $r = TS_WP_Stubs::shortcode('ts_x'); remove_shortcode('ts_x'); return $r; })(), '[ts_x]');

// ---------------------------------------------------------------- 権限・条件・日時・エスケープ
TS_WP_Stubs::$caps = ['ts_view_hidden']; TS_WP_Stubs::$users[3] = ['caps' => ['ts_manage_takedown'], 'roles' => ['ts_committee'], 'user_email' => 'c@example.com', 'display_name' => '委員'];
check('権限', [current_user_can('ts_view_hidden'), current_user_can('ts_manage_takedown'), user_can(3, 'ts_manage_takedown'), user_can(4, 'ts_manage_takedown')], [true, false, true, false]);
check('get_users の role__in', array_map(function ($u) { return $u->user_email; }, get_users(['role__in' => ['ts_committee', 'ts_committee_editor']])), ['c@example.com']);
TS_WP_Stubs::$is = ['singular' => true, 'tax' => 'ts_author']; TS_WP_Stubs::$queried = get_post($w);
check('条件タグ', [is_singular(), is_singular('ts_work'), is_singular(['ts_doc']), is_tax(), is_tax('ts_author'), is_tax(['ts_genre']), is_home(), is_admin()],
      [true, true, false, true, true, false, false, false]);
check('get_the_title: private の題に「非公開: 」が付くのを private_title_format のフィルタ (visibility-hooks.php) が ts_work では外す',
      [get_the_title($c1), get_the_title(TS_WP_Stubs::add_post(['post_type' => 'ts_doc', 'post_status' => 'private', 'post_title' => '秘'])),
       get_the_title(TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_status' => 'private', 'post_title' => '秘作']))], ['子 1', '非公開: 秘', '秘作']);
check('wp_date は Asia/Tokyo、$time で「いま」を固定できる', [wp_date('Y-m-d H:i', 1793836800), (function () { TS_WP_Stubs::$time = 1793836800; $v = current_time('YmdH'); TS_WP_Stubs::$time = null; return $v; })()],
      ['2026-11-05 09:00', '2026110509']);
check('esc_html / esc_attr / esc_url / wp_strip_all_tags / wp_specialchars_decode',
      [esc_html('<a href="x">&amp; \'q\''), esc_attr('"'), esc_url('https://x.test/?a=1&b=2'), esc_url(''), wp_strip_all_tags("<p>a</p>\n<script>x</script> b"),
       wp_specialchars_decode('&lt;&#039;&quot;', ENT_QUOTES)],
      ['&lt;a href=&quot;x&quot;&gt;&amp; &#039;q&#039;', '&quot;', 'https://x.test/?a=1&#038;b=2', '', "a\n b", '<\'"']);
check('sanitize_title', [sanitize_title('Moon 01.htm'), sanitize_title('あ b'), sanitize_title('!!', 'fb')], ['moon-01-htm', '%e3%81%82-b', 'fb']);
check('wp_nonce_field と wp_verify_nonce', (function () { $f = wp_nonce_field('ts_takedown', '_wpnonce', false, false); preg_match('/value="([0-9a-f]{10})"/', $f, $m);
      return [strpos($f, '<input type="hidden" id="_wpnonce" name="_wpnonce" value="') === 0, wp_verify_nonce($m[1], 'ts_takedown'), wp_verify_nonce($m[1], 'other')]; })(), [true, 1, false]);
check('is_email と wp_slash と maybe_unserialize', [is_email('a@example.com'), is_email("a@example.com\nb"), wp_slash(["O'N" => "a\\b"]), maybe_unserialize(serialize([1])), maybe_unserialize('plain')],
      ['a@example.com', false, ["O'N" => 'a\\\\b'], [1], 'plain']);
check('$wpdb: prepare と行の受け渡し (SQL は解釈しない)', (function () { global $wpdb; TS_WP_Stubs::$db_rows = [[['post_id' => 5, 'v' => 'x']], [7, 8]];
      $sql = $wpdb->prepare("SELECT 1 WHERE a=%s AND b=%d", "it's", '3x'); $rows = $wpdb->get_results('q1'); $col = $wpdb->get_col('q2'); $none = $wpdb->get_col('q3');
      return [$sql, $rows[0]->v, $col, $none, TS_WP_Stubs::$db_queries, $wpdb->esc_like('a_b%')]; })(),
      ["SELECT 1 WHERE a='it\\'s' AND b=3", 'x', [7, 8], [], ['q1', 'q2', 'q3'], 'a\\_b\\%']);
TS_WP_Stubs::reset();
