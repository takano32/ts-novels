<?php
/**
 * WordPress の代わり (scripts/wp/tests/wp-stubs.php) に置く作り物のデータ (T45.1.14)。
 *
 * 試験 (scripts/wp/tests/*.php) と、旧版のコードを同じ代わりで動かして期待値を取る probe (scripts/wp/php_probe.sh) が同じ関数を呼ぶ。
 * 期待値を取ったときと試験のときで置き場が同じであることが「実装の写しにしない」(T45.1.15) の根拠になる。
 * 関数は置き場を reset() してから作り、作ったものの ID を名前つきの配列で返す。値は作り物 (人名・URL・メールアドレスは実在しないもの)。
 * 新しい部品の試験に要る置き場はここに関数を足す (既存の関数の中身は変えない — 変えると期待値を取り直すことになる)。
 */

/**
 * 書誌カード (specs/S41.md) の 4 通り。返り値: ['full' => 全部の欄がある話, 'none' => source_path も annex_url も無い話,
 * 'annex_raw' => 原本リンクだけで本文は原本の HTML (route_label つきの回収経路、感想板の URL は出さない), 'bare' => 初出のパスだけ]
 */
function ts_fixture_biblio(): array {
    TS_WP_Stubs::reset();
    $work = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_name' => 'tsuki-no-hon', 'post_title' => '月の本', 'post_date' => '2002-01-13 00:00:00']);
    $full = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => $work, 'post_name' => 'moon01', 'post_title' => '第一話', 'menu_order' => 1,
                                   'post_date' => '2002-01-13 00:00:00']);
    TS_WP_Stubs::set_meta($full, [
        '_ts_source_path' => 'novel/200201/tsuki/moon01.htm', '_ts_pub_date_raw' => '2002/01/13',
        '_ts_provenance' => ['route' => 'wayback', 'snapshot' => '20020301000000'],
        '_ts_annex_url' => 'https://example.com/annex/novel/200201/tsuki/moon01.htm',
        '_ts_annex_yays_url' => 'https://example.com/annex-yays/novel/moon01.htm',
        '_ts_kansou_annex_url' => 'https://example.com/annex/bbs/tsuki.cgi',
        '_ts_body_status' => 'converted',
    ]);
    $none = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => $work, 'post_name' => 'moon02', 'post_title' => '第二話', 'menu_order' => 2,
                                   'post_date' => '2002-02-10 00:00:00']);
    TS_WP_Stubs::set_meta($none, ['_ts_pub_date_raw' => '2002/02/10', '_ts_body_status' => 'placeholder']);
    $annex_raw = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => $work, 'post_name' => 'moon03', 'post_title' => '第三話', 'menu_order' => 3,
                                        'post_date' => '2002-03-03 00:00:00']);
    TS_WP_Stubs::set_meta($annex_raw, [
        '_ts_provenance' => ['route' => 'mirror', 'route_label' => '作者から提供された複製'],
        '_ts_annex_url' => 'https://example.com/annex/novel/200201/tsuki/moon03.htm',
        '_ts_kansou_annex_url' => 'https://example.com/annex/bbs/tsuki.cgi',
        '_ts_body_status' => 'raw-fallback',
    ]);
    $bare = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_name' => 'hoshi', 'post_title' => "星の歌 'n' <夜>", 'post_date' => '2003-05-05 00:00:00']);
    TS_WP_Stubs::set_meta($bare, ['_ts_source_path' => 'novel/200305/hoshi.htm', '_ts_provenance' => ['route' => 'ftp']]);
    return ['work' => $work, 'full' => $full, 'none' => $none, 'annex_raw' => $annex_raw, 'bare' => $bare];
}

/**
 * 取り下げの対象になる連載 2 作と単発 1 作 (specs/S127.md・S121.md の子孫への波及を見る)。
 * 話の日付は全部違う (get_posts の既定の並び = 日付の新しい順が決まるように)。「nested」は話の下にさらに子を持つ (3 段)。
 * 返り値: ['serial' => 親, 'ep' => [話 3 つ (menu_order の順)], 'nested' => 親, 'nep' => [話 2 つ], 'grand' => 2 つ目の話の子,
 *          'single' => 単発, 'author' => 作者 term]
 */
function ts_fixture_takedown_targets(): array {
    TS_WP_Stubs::reset();
    $author = TS_WP_Stubs::add_term(['taxonomy' => 'ts_author', 'name' => '天野 月', 'slug' => 'amano-tsuki']);
    $serial = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_name' => 'amano-tsuki-renzoku', 'post_title' => '連続する月', 'post_date' => '2001-04-01 00:00:00']);
    $ep = [];
    foreach ([['ren01', '一', '2001-04-01'], ['ren02', '二', '2001-05-02'], ['ren03', '三', '2001-06-03']] as $i => [$name, $title, $date]) {
        $ep[] = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => $serial, 'post_name' => $name, 'post_title' => "第{$title}話",
                                       'menu_order' => $i + 1, 'post_date' => "$date 00:00:00"]);
    }
    $nested = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_name' => 'amano-tsuki-iriko', 'post_title' => '入れ子の月', 'post_date' => '2002-01-01 00:00:00']);
    $nep = [TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => $nested, 'post_name' => 'iri01', 'post_title' => '上', 'menu_order' => 1, 'post_date' => '2002-01-01 00:00:00']),
            TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => $nested, 'post_name' => 'iri02', 'post_title' => '下', 'menu_order' => 2, 'post_date' => '2002-02-02 00:00:00'])];
    $grand = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => $nep[1], 'post_name' => 'iri02a', 'post_title' => '下の補遺', 'menu_order' => 1, 'post_date' => '2002-03-03 00:00:00']);
    $single = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_name' => 'amano-tsuki-tanpen', 'post_title' => '短い月', 'post_date' => '2003-07-07 00:00:00']);
    foreach ([$serial, $nested, $single] as $w) TS_WP_Stubs::set_terms($w, 'ts_author', [$author]);
    foreach (array_merge($ep, $nep, [$grand]) as $e) TS_WP_Stubs::set_terms($e, 'ts_author', [$author]);
    foreach ([$serial, $nested, $single] as $w) TS_WP_Stubs::set_meta($w, ['_ts_work_slug' => TS_WP_Stubs::$posts[$w]->post_name, '_ts_kind' => 'work']);
    return ['serial' => $serial, 'ep' => $ep, 'nested' => $nested, 'nep' => $nep, 'grand' => $grand, 'single' => $single, 'author' => $author];
}

/**
 * 取り下げフォームの申請 1 件 (specs/S125.md のメタ)。$targets は [['url' => …, 'post_id' => … か null, 'work_slug' => … か null]]。
 * 状態は received (確認済み、自動適用待ち)。$confirmed_at の既定は 2026-11-05 09:00 (Asia/Tokyo)。返り値: 申請の投稿 ID
 */
function ts_fixture_request(array $targets, string $pen_name = '天野 月', string $allow_name = '1', int $confirmed_at = 1793836800): int {
    $id = TS_WP_Stubs::add_post(['post_type' => 'ts_takedown_request', 'post_status' => 'private', 'post_title' => '申請']);
    TS_WP_Stubs::set_meta($id, [
        '_tr_status' => 'received', '_tr_pen_name' => $pen_name, '_tr_contact' => 'amano@example.com', '_tr_relation' => 'author',
        '_tr_evidence' => '', '_tr_message' => '', '_tr_allow_name' => $allow_name,
        '_tr_targets' => json_encode($targets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        '_tr_received_at' => $confirmed_at - 3600, '_tr_confirmed_at' => $confirmed_at, '_tr_auto_apply_at' => $confirmed_at + 10 * 86400,
        '_tr_via' => 'takedown_form',
    ]);
    return $id;
}

/**
 * 申請の非公開化と復帰 (S127・S128) の場面: ts_fixture_takedown_targets の上に、別の記録 (S9) で取り下げ中の話・移行前の取り下げ (draft)・
 * 旧版 (superseded) の孫・import の記録 (_ts_last_import_gmt) のある投稿を置き、連載 2 作と結びつかない URL 1 つを対象にした申請を作る。
 * option ts_takedown_urls には別の作品の URL と S9 の URL が先にある。運営委員 2 人 (committee と editor) と関係ないユーザ 1 人。
 * 返り値: ts_fixture_takedown_targets の ID に 'request' (申請の投稿 ID) を足したもの
 */
function ts_fixture_takedown_apply_scene(): array {
    $t = ts_fixture_takedown_targets();
    $s9 = $t['ep'][1];
    TS_WP_Stubs::$posts[$s9]->post_status = 'private';
    TS_WP_Stubs::set_meta($s9, ['_ts_visibility' => 'withdrawn', '_ts_takedown_id' => 'S9', '_ts_public_note' => 'S9 の掲示', '_ts_takedown_date' => '2026-01-01']);
    TS_WP_Stubs::$posts[$t['nep'][0]]->post_status = 'draft';
    TS_WP_Stubs::$posts[$t['grand']]->post_status = 'private';
    TS_WP_Stubs::set_meta($t['grand'], ['_ts_visibility' => 'superseded', '_ts_superseded_by' => 'iri02a-shinpan']);
    foreach ([$t['serial'], $t['ep'][0]] as $pid) TS_WP_Stubs::set_meta($pid, ['_ts_last_import_gmt' => '2026-10-30 00:00:00']);
    TS_WP_Stubs::$options['ts_takedown_urls'] = ['/novel/hoka-no-sakuhin/', '/novel/amano-tsuki-renzoku/ren02/'];
    TS_WP_Stubs::$options['admin_email'] = 'admin@example.com';
    TS_WP_Stubs::$users = [
        3 => ['user_email' => 'iin@example.com', 'display_name' => '委員', 'roles' => ['ts_committee'], 'caps' => ['ts_manage_takedown']],
        4 => ['user_email' => 'editor@example.com', 'display_name' => '編集', 'roles' => ['ts_committee_editor'], 'caps' => ['ts_manage_takedown']],
        5 => ['user_email' => 'dokusha@example.com', 'display_name' => '読者', 'roles' => ['subscriber'], 'caps' => []],
    ];
    $t['request'] = ts_fixture_request([
        ['url' => 'https://novels.xwp.jp/novel/amano-tsuki-renzoku/', 'post_id' => $t['serial'], 'work_slug' => 'amano-tsuki-renzoku'],
        ['url' => 'https://novels.xwp.jp/novel/amano-tsuki-iriko/', 'post_id' => $t['nested'], 'work_slug' => 'amano-tsuki-iriko'],
        ['url' => 'https://novels.xwp.jp/novel/nai/', 'post_id' => null, 'work_slug' => null],
    ]);
    return $t;
}

/**
 * 申請の適用と復帰の後の状態を比べやすい形に: ts_work ごとの「状態 vis= id= date= note= sup= imp=」の 1 行 (post_name をキーに)、
 * option ts_takedown_urls、申請の状態・resolved_by・メモ・resolved_at の有無、操作の記録 (what/by/note。時刻は見ない)、送ったメール。
 */
function ts_fixture_takedown_snapshot(array $ids): array {
    $posts = [];
    foreach (TS_WP_Stubs::$posts as $p) {
        if ($p->post_type !== 'ts_work') continue;
        $m = function ($k) use ($p) { return (string) get_post_meta($p->ID, $k, true); };
        $posts[$p->post_name] = $p->post_status . ' vis=' . $m('_ts_visibility') . ' id=' . $m('_ts_takedown_id') . ' date=' . $m('_ts_takedown_date')
            . ' note=' . $m('_ts_public_note') . ' sup=' . $m('_ts_superseded_by') . ' imp=' . $m('_ts_last_import_gmt');
    }
    $req = $ids['request'];
    $log = array_map(function ($j) { $l = json_decode((string) $j, true); return $l['what'] . '/' . $l['by'] . '/' . $l['note']; }, get_post_meta($req, '_tr_log'));
    return ['posts' => $posts, 'urls' => get_option('ts_takedown_urls'),
            'request' => [get_post_meta($req, '_tr_status', true), get_post_meta($req, '_tr_resolved_by', true),
                          get_post_meta($req, '_tr_resolution_note', true), get_post_meta($req, '_tr_resolved_at', true) !== ''],
            'log' => $log,
            'mails' => array_map(function ($m) { return [$m['to'], $m['subject'], $m['body']]; }, TS_WP_Stubs::$mails)];
}

/**
 * 作品・話のページの表示部品 (パンくず・メタヘッダ・話ナビ・目次・推薦文・あらすじ・オススメ・関連ページ) の場面。
 * 連載 1 作 (話 3 つ。2 つ目は private) と単発 1 作。1 つ目の話に、挿絵 (作者ページを表記ゆれで引ける人と引けない人)・分類 4 種・
 * 推薦文・あらすじ・作者コメント・オススメ (館内に解決できる参照・できない参照・題の無い参照)・関連ページ (ラベルあり・なし・
 * 館内に無い・href が空・配列でない) を置く。原パスの表 (ts_bunko_source_map) が $wpdb から読む行は、本番と同じく公開の話の
 * _ts_source_path と作品トビラの _ts_title_pages (直列化) を $db_rows に積む (この表は 1 プロセスに 1 度しか作られないので、
 * 最初に原パスを引く試験がこの場面で呼ぶ)。
 * 返り値: ['author', 'painter' (挿絵の人の作者 term), 'serial', 'ep' => [話 3 つ], 'single']
 */
function ts_fixture_render_parts(): array {
    TS_WP_Stubs::reset();
    $author = TS_WP_Stubs::add_term(['taxonomy' => 'ts_author', 'name' => '星野 灯', 'slug' => 'hoshino-akari']);
    $painter = TS_WP_Stubs::add_term(['taxonomy' => 'ts_author', 'name' => '絵筆 太郎', 'slug' => 'efude-taro']);
    TS_WP_Stubs::set_term_meta($painter, ['ts_display_variants' => ['えふで']]);
    $genre = TS_WP_Stubs::add_term(['taxonomy' => 'ts_genre', 'name' => '学園', 'slug' => 'gakuen']);
    $type = TS_WP_Stubs::add_term(['taxonomy' => 'ts_type', 'name' => '入れ替わり', 'slug' => 'irekawari']);
    $kw1 = TS_WP_Stubs::add_term(['taxonomy' => 'ts_keyword', 'name' => 'ねこ', 'slug' => 'neko']);
    $kw2 = TS_WP_Stubs::add_term(['taxonomy' => 'ts_keyword', 'name' => 'あめ', 'slug' => 'ame']);
    $world = TS_WP_Stubs::add_term(['taxonomy' => 'ts_world', 'name' => '星の町 & 海', 'slug' => 'hoshi-no-machi']);
    $serial = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_name' => 'hikari-no-michi', 'post_title' => '光の道', 'post_date' => '2003-04-01 00:00:00']);
    $ep = [];
    foreach ([['hikari01', '第一話', '2003-04-01', 'publish'], ['hikari02', '第二話', '2003-05-01', 'private'],
              ['hikari03', '第三話 <後編>', '2003-06-01', 'publish']] as $i => [$name, $title, $date, $status]) {
        $ep[] = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => $serial, 'post_name' => $name, 'post_title' => $title,
                                       'post_status' => $status, 'menu_order' => $i + 1, 'post_date' => "$date 00:00:00"]);
        TS_WP_Stubs::set_meta($ep[$i], ['_ts_pub_date_raw' => str_replace('-', '/', $date), '_ts_source_path' => "novel/200304/$name.htm"]);
    }
    TS_WP_Stubs::$posts[$ep[0]]->post_content = '<p>' . str_repeat('あいうえお かきくけこ', 70) . '</p>';
    TS_WP_Stubs::set_meta($ep[0], [
        '_ts_illustrator' => ['えふで', '名無しの絵師'],
        '_ts_suisen' => '胸が「熱く」なる <推薦>',
        '_ts_arasuji' => 'ある朝、光の道が現れた。',
        '_ts_author_comment' => '読んでくれてありがとう & またね',
        '_ts_osusume' => ['recommender' => '読者 一', 'refs' => [
            ['href' => 'novel/200304/hikari03.htm', 'title' => '第三話へ', 'author' => '星野 灯'],
            ['href' => 'novel/zzz/nai.htm', 'title' => '館内に無い作品'],
            ['href' => 'novel/title/hikari.htm'],
        ]],
        '_ts_nav_links' => [
            ['href' => 'novel/200304/hikari03.htm', 'label' => ' 次の話へ '],
            ['href' => 'novel/title/hikari.htm', 'label' => ''],
            ['href' => 'novel/nai.htm', 'label' => '無い'],
            ['href' => '', 'label' => '空'],
            '配列でない',
        ],
    ]);
    foreach ([[$serial, 'ts_author', [$author]], [$ep[0], 'ts_author', [$author]], [$ep[0], 'ts_genre', [$genre]],
              [$ep[0], 'ts_type', [$type]], [$ep[0], 'ts_keyword', [$kw1, $kw2]], [$ep[0], 'ts_world', [$world]]] as [$pid, $tax, $ids]) {
        TS_WP_Stubs::set_terms($pid, $tax, $ids);
    }
    TS_WP_Stubs::set_meta($serial, ['_ts_title_pages' => ['novel/title/hikari.htm']]);
    $single = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_name' => 'hitori-no-yoru', 'post_title' => '一人の夜', 'post_content' => '短い。',
                                     'post_date' => '2004-01-01 00:00:00']);
    TS_WP_Stubs::set_meta($single, ['_ts_osusume' => ['recommender' => '読者 二', 'refs' => []], '_ts_nav_links' => [['href' => 'novel/nai.htm']]]);
    // ts_bunko_source_map の 2 つの問い合わせ (公開の話の _ts_source_path、作品トビラの _ts_title_pages) が返す行
    TS_WP_Stubs::$db_rows = [
        [['post_id' => (string) $ep[0], 'v' => 'novel/200304/hikari01.htm'], ['post_id' => (string) $ep[2], 'v' => 'novel/200304/hikari03.htm']],
        [['post_id' => (string) $serial, 'v' => serialize(['novel/title/hikari.htm'])]],
    ];
    return ['author' => $author, 'painter' => $painter, 'serial' => $serial, 'ep' => $ep, 'single' => $single];
}

/**
 * 運営委員にだけ見える作品の帯 (specs/S118.md) と目次に並べる子の状態。非公開の連載 (取り下げ済み、S9) と、その公開の話と
 * 非公開の話、旧版 (superseded) の話 2 つ (新版の episode_id が引けるものと引けないもの)、旧版の連載の親 (新版の work_slug)、
 * 印の無い非公開の単発、公開の単発。運営委員 (ts_view_hidden) はユーザ 3。いまのユーザは置かない (読者)。
 * 返り値: ['withdrawn', 'w_pub', 'w_priv', 'sup_ep', 'sup_ep_none', 'new_ep', 'sup_work', 'new_work', 'plain', 'public']
 */
function ts_fixture_hidden(): array {
    TS_WP_Stubs::reset();
    $ids = [];
    $ids['withdrawn'] = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_status' => 'private', 'post_name' => 'kieta-tsuki', 'post_title' => '消えた月',
                                               'post_date' => '2001-02-03 00:00:00']);
    TS_WP_Stubs::set_meta($ids['withdrawn'], ['_ts_visibility' => 'withdrawn', '_ts_takedown_id' => 'S9', '_ts_work_slug' => 'kieta-tsuki']);
    $ids['w_pub'] = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => $ids['withdrawn'], 'post_name' => 'k01', 'post_title' => '前編',
                                           'menu_order' => 1, 'post_date' => '2001-02-03 00:00:00']);
    $ids['w_priv'] = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => $ids['withdrawn'], 'post_status' => 'private', 'post_name' => 'k02',
                                            'post_title' => '後編', 'menu_order' => 2, 'post_date' => '2001-03-04 00:00:00']);
    TS_WP_Stubs::set_meta($ids['w_priv'], ['_ts_visibility' => 'withdrawn', '_ts_takedown_id' => 'req:12', '_ts_pub_date_raw' => '2001/03/04']);
    $ids['new_ep'] = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_name' => 'shinpan', 'post_title' => '新版', 'post_date' => '2005-01-01 00:00:00']);
    TS_WP_Stubs::set_meta($ids['new_ep'], ['_ts_episode_id' => 'ep-shinpan', '_ts_work_slug' => 'shinpan']);
    $ids['sup_ep'] = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_status' => 'private', 'post_name' => 'kyuhan', 'post_title' => '旧版',
                                            'post_date' => '1999-09-09 00:00:00']);
    TS_WP_Stubs::set_meta($ids['sup_ep'], ['_ts_visibility' => 'superseded', '_ts_superseded_by' => 'ep-shinpan', '_ts_episode_id' => 'ep-kyuhan']);
    $ids['sup_ep_none'] = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_status' => 'private', 'post_name' => 'kyuhan2', 'post_title' => '旧版 2',
                                                 'post_date' => '2000-10-10 00:00:00']);
    TS_WP_Stubs::set_meta($ids['sup_ep_none'], ['_ts_visibility' => 'superseded', '_ts_superseded_by' => 'ep-nai', '_ts_episode_id' => 'ep-kyuhan2']);
    $ids['new_work'] = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_name' => 'atarashii-renzoku', 'post_title' => '新しい連載', 'post_date' => '2006-01-01 00:00:00']);
    TS_WP_Stubs::set_meta($ids['new_work'], ['_ts_work_slug' => 'atarashii-renzoku']);
    $ids['sup_work'] = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_status' => 'private', 'post_name' => 'furui-renzoku', 'post_title' => '古い連載',
                                              'post_date' => '2000-01-01 00:00:00']);
    TS_WP_Stubs::set_meta($ids['sup_work'], ['_ts_visibility' => 'superseded', '_ts_superseded_by' => 'atarashii-renzoku', '_ts_work_slug' => 'furui-renzoku']);
    $ids['plain'] = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_status' => 'private', 'post_name' => 'shirushi-nashi', 'post_title' => '印なし',
                                           'post_date' => '2002-02-02 00:00:00']);
    $ids['public'] = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_name' => 'koukai', 'post_title' => '公開', 'post_date' => '2002-02-02 00:00:00']);
    TS_WP_Stubs::$users = [3 => ['user_email' => 'iin@example.com', 'display_name' => '委員', 'roles' => ['ts_committee'], 'caps' => ['ts_view_hidden']]];
    return $ids;
}

/**
 * 掲載していない作品の一覧 [ts_removed_list] (specs/S131.md) の場面。取り下げ済み (private + withdrawn) の投稿 5 つ:
 * 同じ記録 (req:3) の 2 投稿 (掲示は片方だけ、日付も片方だけ)、別の日付の記録 2 つ (S9 と req:12。掲示に < と & を含む)、
 * 掲示の無い記録 (出さない)、記録の無い投稿 (出さない)。公開のまま withdrawn の印だけある投稿と、private の旧版 (superseded) も
 * 置く (どちらも一覧の対象外)。$empty なら何も置かない (0 件)。返り値: 置いた投稿 ID の列
 */
function ts_fixture_removed(bool $empty = false): array {
    TS_WP_Stubs::reset();
    if ($empty) return [];
    $ids = [];
    foreach ([['a01', 'private', 'withdrawn', 'req:3', '「月の本」(天野 月) — 2026年10月に作者のお申し出により取り下げ', ''],
              ['a02', 'private', 'withdrawn', 'req:3', '', '2026-10-20'],
              ['b01', 'private', 'withdrawn', 'S9', '作品 <題> & 続編 — 2026年1月に取り下げ', '2026-01-01'],
              ['c01', 'private', 'withdrawn', 'req:12', '「星の歌」— 2026年11月に取り下げ', '2026-11-02'],
              ['d01', 'private', 'withdrawn', 'req:20', '', '2026-11-03'],
              ['e01', 'private', 'withdrawn', '', '記録の無い掲示', '2026-11-04'],
              ['f01', 'publish', 'withdrawn', 'req:30', '公開のまま', '2026-11-05'],
              ['g01', 'private', 'superseded', 'req:40', '旧版', '2026-11-05']] as [$name, $status, $vis, $tid, $note, $date]) {
        $id = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_status' => $status, 'post_name' => $name, 'post_title' => $name]);
        $m = ['_ts_visibility' => $vis];
        if ($tid !== '') $m['_ts_takedown_id'] = $tid;
        if ($note !== '') $m['_ts_public_note'] = $note;
        if ($date !== '') $m['_ts_takedown_date'] = $date;
        TS_WP_Stubs::set_meta($id, $m);
        $ids[] = $id;
    }
    return $ids;
}

/**
 * 索引 (T10.3) の作者索引・分類索引・キーワード索引・運営文書の索引の場面。term の「空でない」は add_term の count で決め
 * (投稿は置かない)、作品数 (ts_bunko_work_counts が $wpdb から数える行) は $db_rows に、呼ぶ順 (作者索引 → 分類索引 →
 * キーワード索引) の問い合わせの順に積む: ts_author → ts_genre → ts_type → ts_world → ts_keyword。作品数は taxonomy ごとに
 * 1 プロセスに 1 度しか数えない (static) ので、最初に索引を描く試験がこの場面で呼ぶ。
 * 作者: 読みの行 (あ行・か行・A・想定外の値・空 = 他) と、作品数 0 の作者 (出さない)。分類: 説明つきの語、作品数 0 の語、
 * 作品数の無い共有世界 (見出しだけ)。キーワード: 件数順と作品数 0。運営文書: 区分ごと、区分なし (解題)、表に無い区分 (出さない)、
 * 下書き (出さない)。返り値: 作った term と投稿の ID (名前 => ID)
 */
function ts_fixture_indexes(): array {
    TS_WP_Stubs::reset();
    $ids = [];
    $term = function (string $key, string $tax, string $name, string $slug, int $count, string $desc = '') use (&$ids) {
        return $ids[$key] = TS_WP_Stubs::add_term(['taxonomy' => $tax, 'name' => $name, 'slug' => $slug, 'count' => $count, 'description' => $desc]);
    };
    foreach ([['a_aoi', '青井 空', 'aoi-sora', 'あ行', 3], ['a_ichi', '市川 一', 'ichikawa-hajime', 'あ行', 1], ['a_kawa', '川辺 <流>', 'kawabe-nagare', 'か行', 2],
              ['a_zero', '零の人', 'zero-no-hito', 'か行', 4], ['a_bob', 'Bob', 'bob', 'B', 1], ['a_odd', '記号 ☆', 'kigou', '☆', 1],
              ['a_none', '読みなし', 'yomi-nashi', '', 1]] as [$k, $name, $slug, $group, $count]) {
        $term($k, 'ts_author', $name, $slug, $count);
        if ($group !== '') TS_WP_Stubs::set_term_meta($ids[$k], ['ts_yomi_group' => $group]);
    }
    $term('g_gakuen', 'ts_genre', '学園', 'gakuen', 5, '学校が舞台 & 寮');
    $term('g_fantasy', 'ts_genre', 'ファンタジー', 'fantasy', 9);
    $term('g_zero', 'ts_genre', '空の語', 'kara', 2);
    $term('t_ire', 'ts_type', '入れ替わり', 'irekawari', 4);
    $term('w_hoshi', 'ts_world', '星の町', 'hoshi-no-machi', 1);
    $term('k_neko', 'ts_keyword', 'ねこ', 'neko', 7);
    $term('k_ame', 'ts_keyword', 'あめ', 'ame', 12);
    $term('k_zero', 'ts_keyword', 'ゼロ', 'zero', 3);
    $term('k_empty', 'ts_keyword', '使われない語', 'tsukawarenai', 0);
    $rows = function (array $kc) use (&$ids) {
        return array_map(function ($k, $c) use (&$ids) { return ['term_id' => (string) $ids[$k], 'c' => (string) $c]; }, array_keys($kc), $kc);
    };
    TS_WP_Stubs::$db_rows = [
        $rows(['a_aoi' => 3, 'a_ichi' => 1, 'a_kawa' => 2, 'a_bob' => 1, 'a_odd' => 1, 'a_none' => 1]),
        $rows(['g_gakuen' => 2, 'g_fantasy' => 6]),
        $rows(['t_ire' => 3]),
        [],
        $rows(['k_neko' => 5, 'k_ame' => 8]),
    ];
    foreach ([['d_gal', 'gallery-1', '挿絵の部屋', 'gallery'], ['d_col2', 'column-b', 'コラム B', 'columns'], ['d_col1', 'column-a', 'コラム A & 序', 'columns'],
              ['d_pre', 'prehistory', '1999 年の原型', 'prehistory'], ['d_note', 'kaidai', '解題 (区分なし)', ''], ['d_odd', 'odd', '表に無い区分', 'misc']] as [$k, $slug, $title, $sec]) {
        $ids[$k] = TS_WP_Stubs::add_post(['post_type' => 'ts_doc', 'post_name' => $slug, 'post_title' => $title]);
        if ($sec !== '') TS_WP_Stubs::set_meta($ids[$k], ['_ts_section' => $sec]);
    }
    $ids['d_draft'] = TS_WP_Stubs::add_post(['post_type' => 'ts_doc', 'post_status' => 'draft', 'post_name' => 'draft', 'post_title' => '下書き']);
    TS_WP_Stubs::set_meta($ids['d_draft'], ['_ts_section' => 'columns']);
    return $ids;
}
