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

/**
 * テーマのテンプレート (themes/ts-bunko の index.php・front-page.php・ts-year.php・single.php) と一覧の作品カード
 * (ts_bunko_workcard) の場面 (T45.1.5)。作者 2 人 (片方は挿絵も描き、ホームページの保存版を持つ)・ジャンル 2 つ・種別 1 つ。作品:
 *  - 'serial'  連載 (話数メタ 3、話 3 つのうち 2 つ目は private、3 つ目は原本の HTML のまま (raw-fallback)。1 つ目の話の挿絵は 'painter')
 *  - 'nometa'  連載 (話数メタなし。公開の話 2 つと private の話 1 つ → get_children で 2)
 *  - 'one'     話数メタ 1 (全N話を出さない)。作者なし
 *  - 'single'  単発 (話数メタなし・子なし、raw-fallback、日付なし)
 *  - 'bare'    作者も日付も無い
 *  - 'hidden'  非公開の単発 (取り下げ済み)
 *  - 'doc'     運営文書 (ts_doc。single.php の素の表示)
 * 返り値: 名前 => ID ('ep' => [連載の話 3 つ]、'nep' => [nometa の話 3 つ]、作者・語の term も)
 */
function ts_fixture_theme(): array {
    TS_WP_Stubs::reset();
    $ids = [];
    $ids['author'] = TS_WP_Stubs::add_term(['taxonomy' => 'ts_author', 'name' => '星野 灯', 'slug' => 'hoshino-akari', 'description' => '星の話を書く人']);
    TS_WP_Stubs::set_term_meta($ids['author'], ['ts_homepage_wayback' => 'https://web.archive.org/web/2003/http://example.com/hoshino/', 'ts_yomi_group' => 'は行']);
    $ids['painter'] = TS_WP_Stubs::add_term(['taxonomy' => 'ts_author', 'name' => '絵筆 太郎', 'slug' => 'efude-taro']);
    TS_WP_Stubs::set_term_meta($ids['painter'], ['ts_display_variants' => ['えふで'], 'ts_homepage_live' => 'https://example.com/efude/']);
    $ids['gakuen'] = TS_WP_Stubs::add_term(['taxonomy' => 'ts_genre', 'name' => '学園', 'slug' => 'gakuen']);
    $ids['fantasy'] = TS_WP_Stubs::add_term(['taxonomy' => 'ts_genre', 'name' => 'ファンタジー', 'slug' => 'fantasy']);
    $ids['ire'] = TS_WP_Stubs::add_term(['taxonomy' => 'ts_type', 'name' => '入れ替わり', 'slug' => 'irekawari']);
    $work = function (string $key, string $name, string $title, string $date, array $meta, string $status = 'publish') use (&$ids) {
        $ids[$key] = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_name' => $name, 'post_title' => $title, 'post_status' => $status,
                                            'post_date' => "$date 00:00:00", 'post_content' => "<p>{$title}の本文。</p>"]);
        TS_WP_Stubs::set_meta($ids[$key], $meta);
        return $ids[$key];
    };
    $work('serial', 'hikari-no-michi', '光の道', '2003-04-01', ['_ts_pub_date_raw' => '2003/04/01', '_ts_episode_count' => '3', '_ts_work_slug' => 'hikari-no-michi',
                                                              '_ts_arasuji' => '光の道のあらすじ', '_ts_suisen' => '推薦の言葉', '_ts_source_path' => 'novel/200304/hikari.htm']);
    $ids['ep'] = [];
    foreach ([['hikari01', '第一話', '2003-04-01', 'publish', 'converted'], ['hikari02', '第二話', '2003-05-01', 'private', 'converted'],
              ['hikari03', '第三話', '2003-06-01', 'publish', 'raw-fallback']] as $i => [$name, $title, $date, $status, $body]) {
        $ids['ep'][] = $e = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => $ids['serial'], 'post_name' => $name, 'post_title' => $title,
                                                   'post_status' => $status, 'menu_order' => $i + 1, 'post_date' => "$date 00:00:00",
                                                   'post_content' => '<p>' . str_repeat('ほしのひかり ', 50) . '</p>']);
        TS_WP_Stubs::set_meta($e, ['_ts_pub_date_raw' => str_replace('-', '/', $date), '_ts_body_status' => $body, '_ts_source_path' => "novel/200304/$name.htm",
                                   '_ts_annex_url' => "https://example.com/annex/novel/200304/$name.htm"]);
        TS_WP_Stubs::set_terms($e, 'ts_author', [$ids['author']]);
    }
    TS_WP_Stubs::set_meta($ids['ep'][0], ['_ts_illustrator' => ['えふで']]);
    TS_WP_Stubs::set_terms($ids['ep'][0], 'ts_genre', [$ids['gakuen']]);
    $work('nometa', 'yoru-no-kawa', '夜の川', '2004-07-07', ['_ts_pub_date_raw' => '2004/07/07']);
    $ids['nep'] = [];
    foreach (['publish', 'publish', 'private'] as $i => $status) {
        $ids['nep'][] = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => $ids['nometa'], 'post_name' => 'kawa0' . ($i + 1),
                                               'post_title' => '川 ' . ($i + 1), 'post_status' => $status, 'menu_order' => $i + 1,
                                               'post_date' => '2004-07-0' . ($i + 7) . ' 00:00:00']);
    }
    $work('one', 'hitotsu', '一つきり', '2005-01-01', ['_ts_pub_date_raw' => '2005/01/01', '_ts_episode_count' => '1']);
    $work('single', 'hitori-no-yoru', '一人の夜 & 朝', '2002-02-02', ['_ts_body_status' => 'raw-fallback', '_ts_annex_url' => 'https://example.com/annex/novel/hitori.htm']);
    $work('bare', 'nanimonai', '何もない', '2001-01-01', []);
    $work('hidden', 'kieta', '消えた話', '2001-05-05', ['_ts_visibility' => 'withdrawn', '_ts_takedown_id' => 'req:4', '_ts_pub_date_raw' => '2001/05/05'], 'private');
    foreach ([['serial', 'author'], ['nometa', 'painter'], ['single', 'author'], ['hidden', 'author']] as [$w, $a]) TS_WP_Stubs::set_terms($ids[$w], 'ts_author', [$ids[$a]]);
    foreach ([['serial', 'gakuen'], ['nometa', 'fantasy'], ['one', 'fantasy'], ['single', 'fantasy']] as [$w, $g]) TS_WP_Stubs::set_terms($ids[$w], 'ts_genre', [$ids[$g]]);
    TS_WP_Stubs::set_terms($ids['serial'], 'ts_type', [$ids['ire']]);
    $ids['doc'] = TS_WP_Stubs::add_post(['post_type' => 'ts_doc', 'post_name' => 'about-doc', 'post_title' => '運営の文書', 'post_content' => '<p>文書の本文</p>']);
    TS_WP_Stubs::$users = [3 => ['user_email' => 'iin@example.com', 'display_name' => '委員', 'roles' => ['ts_committee'], 'caps' => ['ts_view_hidden']]];
    return $ids;
}

// ---------------------------------------------------------------- 本番で人が触る 3 経路 (T45.6.4): フォームの送信と確認リンク・cron・wp ts apply-takedown
// time() は置き換えられないので、時刻は「いま」からの差 (時間の単位に丸める) で比べる。1 秒のずれは丸めで消える。

/** 時刻 (Unix 秒) を「いま」からの差で: 'now+0h'・'now-192h'。0 は '0' (まだ無い) */
function ts_fixture_rel($v, int $now): string {
    return (int) $v === 0 ? '0' : 'now' . sprintf('%+d', (int) round(((int) $v - $now) / 3600)) . 'h';
}

/** 文中の「Y-m-d H:i」(Asia/Tokyo。メールの自動適用の時刻) を ts_fixture_rel の形に */
function ts_fixture_rel_text(string $s, int $now): string {
    return preg_replace_callback('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', function ($m) use ($now) {
        return ts_fixture_rel((new DateTimeImmutable($m[0], TS_WP_Stubs::tz()))->getTimestamp(), $now);
    }, $s);
}

/**
 * 申請 1 件のメタを鍵の順に (値が 1 つならその値、2 つ以上なら列)。$rel_keys の鍵の値は ts_fixture_rel に、_tr_log は
 * 「what/by/note/時刻」に、_tr_token は確認リンクの $token の wp_hash と同じなら '(token の wp_hash)' にする
 */
function ts_fixture_request_meta(int $id, int $now, array $rel_keys, string $token = ''): array {
    $meta = TS_WP_Stubs::$meta[$id] ?? [];
    ksort($meta);
    $out = [];
    foreach ($meta as $k => $vals) {
        $vals = array_map(function ($v) use ($k, $now, $rel_keys, $token) {
            if (in_array($k, $rel_keys, true)) return ts_fixture_rel($v, $now);
            if ($k === '_tr_log') {
                $l = json_decode((string) $v, true);
                return $l['what'] . '/' . $l['by'] . '/' . $l['note'] . '/' . ts_fixture_rel($l['at'], $now);
            }
            if ($k === '_tr_token' && $token !== '' && $v === wp_hash($token)) return '(token の wp_hash)';
            return $v;
        }, $vals);
        $out[$k] = count($vals) === 1 ? $vals[0] : $vals;
    }
    return $out;
}

/** 送ったメール [宛先, 件名, 本文 (確認リンクの token と自動適用の時刻を伏せる), ヘッダ] */
function ts_fixture_mails(int $now): array {
    return array_map(function ($m) use ($now) {
        $body = preg_replace('/ts_confirm=[0-9a-f]{64}/', 'ts_confirm=(token)', ts_fixture_rel_text($m['body'], $now));
        return [$m['to'], $m['subject'], $body, $m['headers']];
    }, TS_WP_Stubs::$mails);
}

/**
 * 要求を 1 つ流す: $_POST・$_GET と $_SERVER の鍵を置いて $fn を呼び、応答 (ts_takedown_respond → wp_die) を
 * [状態コード, 題, 本文] で返す。応答せずに戻れば null。終わったら 3 つとも元に戻す
 */
function ts_fixture_request_run(callable $fn, array $post = [], array $get = [], array $server = []): ?array {
    $saved = [$_POST, $_GET, $_SERVER];
    $_POST = $post; $_GET = $get; $_SERVER = array_merge($_SERVER, $server);
    try {
        $fn();
        return null;
    } catch (TS_WP_Die $e) {
        return [$e->args['response'] ?? null, $e->title, $e->getMessage()];
    } finally {
        [$_POST, $_GET, $_SERVER] = $saved;
    }
}

/** レート制限の transient: 鍵 => [件数, 数え始め (ts_fixture_rel), 寿命 (分)] */
function ts_fixture_rate_transients(int $now): array {
    return array_map(function ($x) use ($now) {
        return [$x[0]['n'], ts_fixture_rel($x[0]['start'], $now), (int) round($x[1] / 60) . 'm'];
    }, TS_WP_Stubs::$transients);
}

/**
 * 取り下げフォームの送信 (specs/S124.md) と確認リンク (S126) を、ts_fixture_takedown_targets の作品の上で順に流して結果を集める。
 * 試験と probe が同じ順で流すように、流し方もここに置く。$sign は表示時刻の署名 (新版は TS_Takedown_Input::sign_time、
 * 旧版は ts_takedown_sign_time)、$handle はフォームの送信を受ける関数、$confirm は template_redirect@1 の callable (確認リンク)。
 * 最後の「受付」は確認を要しない設定 (TS_TAKEDOWN_REQUIRE_CONFIRM = false) で流す。定数は消せないので、この後に同じプロセスで
 * ts_takedown_store を takedown_form で呼ぶと確認を飛ばす (読むのは ts_takedown_store の 1 か所だけ)。
 * 返り値: 名前 => 結果 (応答・申請のメタ・メール・transient)
 */
function ts_fixture_run_takedown_form(callable $sign, callable $handle, callable $confirm): array {
    ts_fixture_takedown_targets();
    TS_WP_Stubs::$options['admin_email'] = 'admin@example.com';
    TS_WP_Stubs::$users = [3 => ['user_email' => 'iin@example.com', 'display_name' => '委員', 'roles' => ['ts_committee'], 'caps' => ['ts_manage_takedown']]];
    $now = time();
    $key = wp_salt('nonce');
    $in = function (array $over = []) use ($sign, $now, $key) {
        return wp_slash(array_merge([
            'action' => 'ts_takedown', '_wpnonce' => wp_create_nonce('ts_takedown'), 't' => $sign($now - 10, $key), 'hp' => '',
            'pen_name' => '天野 月', 'targets' => "https://novels.xwp.jp/novel/amano-tsuki-renzoku/\nhttps://novels.xwp.jp/novel/nai/",
            'contact' => 'amano@example.com', 'relation' => 'author', 'evidence' => "当時の感想板の名前は\n「月」です",
            'message' => "よろしく\nお願いします", 'allow_name' => '1',
        ], $over));
    };
    $post = function (array $over = [], string $ip = '192.0.2.1') use ($handle, $in) {
        return ts_fixture_request_run($handle, $in($over), [], ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => 'Mozilla/5.0 (作り物)']);
    };
    $last = function () { return max(array_keys(TS_WP_Stubs::$posts)); };
    $token_in = function (int $i) {
        return preg_match('/ts_confirm=([0-9a-f]{64})/', TS_WP_Stubs::$mails[$i]['body'] ?? '', $m) ? $m[1] : '';
    };
    $rel = ['_tr_received_at', '_tr_confirmed_at', '_tr_auto_apply_at', '_tr_reminded_at', '_tr_resolved_at'];
    $out = [];
    $out['nonce'] = $post(['_wpnonce' => 'x']);
    $out['honeypot'] = $post(['hp' => 'spam']);
    $out['fast'] = $post(['t' => $sign($now, $key)]);
    $out['broken_t'] = $post(['t' => 'abc']);
    $out['invalid'] = $post(['pen_name' => '', 'targets' => '', 'contact' => 'not-mail', 'relation' => 'x'], '192.0.2.9');
    $out['blocked_writes'] = [count(TS_WP_Stubs::$posts), TS_WP_Stubs::$mails, TS_WP_Stubs::$transients];

    $out['sent'] = $post();
    $first = $last();
    $token = $token_in(0);
    $p = TS_WP_Stubs::$posts[$first];
    $out['sent_post'] = [$p->post_type, $p->post_status, $p->post_title];
    $out['sent_meta'] = ts_fixture_request_meta($first, $now, $rel, $token);
    $out['sent_mails'] = ts_fixture_mails($now);
    $out['sent_transients'] = ts_fixture_rate_transients($now);
    $out['sent2'] = $post();
    $second = $last();
    $token2 = $token_in(1);
    $out['sent3'] = $post();
    $third = $last();
    $token3 = $token_in(2);
    $out['limited'] = $post();
    $out['other_ip'] = $post(['pen_name' => ''], '192.0.2.2');
    $out['limited_transients'] = ts_fixture_rate_transients($now);

    TS_WP_Stubs::$mails = [];
    $out['confirm_none'] = ts_fixture_request_run($confirm);
    $out['confirm_short'] = ts_fixture_request_run($confirm, [], ['ts_confirm' => 'abc']);
    $out['confirm_array'] = ts_fixture_request_run($confirm, [], ['ts_confirm' => [$token]]);
    $out['confirm_unknown'] = ts_fixture_request_run($confirm, [], ['ts_confirm' => str_repeat('0', 64)]);
    $out['confirm_ok'] = ts_fixture_request_run($confirm, [], ['ts_confirm' => $token]);
    $out['confirm_ok_meta'] = ts_fixture_request_meta($first, $now, $rel, $token);
    $out['confirm_ok_mails'] = ts_fixture_mails($now);
    $out['confirm_again'] = ts_fixture_request_run($confirm, [], ['ts_confirm' => $token]);
    TS_WP_Stubs::set_meta($second, ['_tr_received_at' => $now - 8 * 86400]);
    $out['confirm_late'] = ts_fixture_request_run($confirm, [], ['ts_confirm' => $token2]);
    TS_WP_Stubs::set_meta($third, ['_tr_status' => 'expired']);
    $out['confirm_expired'] = ts_fixture_request_run($confirm, [], ['ts_confirm' => $token3]);
    $out['confirm_late_after'] = [get_post_meta($second, '_tr_status', true), get_post_meta($third, '_tr_status', true), count(TS_WP_Stubs::$mails)];

    TS_WP_Stubs::$mails = [];
    if (!defined('TS_TAKEDOWN_REQUIRE_CONFIRM')) define('TS_TAKEDOWN_REQUIRE_CONFIRM', false);
    $out['received'] = $post([], '192.0.2.3');
    $out['received_meta'] = ts_fixture_request_meta($last(), $now, $rel);
    $out['received_mails'] = ts_fixture_mails($now);
    return $out;
}

/**
 * cron ts_takedown_tick (specs/S130.md) の場面: ts_fixture_takedown_apply_scene の申請 (自動適用の時刻を過ぎた) に、次の 7 件を足す。
 * 時刻は「いま」($now) からの差で置く (確認時刻 _tr_confirmed_at は ts_fixture_request の既定のまま。/removed/ の 1 行の年月に使う)。
 *  'expire'   確認されないまま 8 日 (失効)              'young'    確認されないまま 1 日 (何もしない)
 *  'remind'   自動適用の 2 日前 (リマインド)           'reminded' 2 日前でリマインド済み (何もしない)
 *  'purge'    適用から 366 日 (依頼者情報を消す)        'purged'   却下から 366 日だが連絡先も IP も空 (数えない)
 *  'recent'   失効から 10 日 (消さない)
 * 返り値: ts_fixture_takedown_apply_scene の ID に、上の名前 => 申請の投稿 ID を足したもの
 */
function ts_fixture_takedown_tick_scene(int $now): array {
    $ids = ts_fixture_takedown_apply_scene();
    $day = 86400;
    TS_WP_Stubs::set_meta($ids['request'], ['_tr_received_at' => $now - 11 * $day, '_tr_auto_apply_at' => $now - 3600]);
    $add = function (string $status, array $kv) use ($now) {
        $id = ts_fixture_request([['url' => 'https://novels.xwp.jp/novel/hoka/', 'post_id' => null, 'work_slug' => null]], '星野 空', '0');
        TS_WP_Stubs::set_meta($id, array_merge(['_tr_status' => $status, '_tr_received_at' => $now - 20 * 86400, '_tr_auto_apply_at' => 0], $kv));
        return $id;
    };
    $ids['expire'] = $add('unconfirmed', ['_tr_received_at' => $now - 8 * $day, '_tr_confirmed_at' => 0, '_tr_token' => 'tokenhash']);
    $ids['young'] = $add('unconfirmed', ['_tr_received_at' => $now - $day, '_tr_confirmed_at' => 0, '_tr_token' => 'tokenhash2']);
    $ids['remind'] = $add('received', ['_tr_auto_apply_at' => $now + 2 * $day]);
    $ids['reminded'] = $add('received', ['_tr_auto_apply_at' => $now + 2 * $day, '_tr_reminded_at' => $now - 3600]);
    $ids['purge'] = $add('applied', ['_tr_resolved_at' => $now - 366 * $day, '_tr_ip_hash' => 'iphash', '_tr_ua' => 'UA',
                                     '_tr_evidence' => '手がかり', '_tr_message' => '連絡事項']);
    $ids['purged'] = $add('rejected', ['_tr_resolved_at' => $now - 366 * $day, '_tr_contact' => '', '_tr_evidence' => '残る手がかり']);
    $ids['recent'] = $add('expired', ['_tr_resolved_at' => $now - 10 * $day]);
    return $ids;
}

/**
 * wp ts apply-takedown (specs/S121.md) の場面: ts_fixture_takedown_targets の作品に、$repo/takedown/denylist.yml (エントリ 5 つ) を置く。
 *  S10 連載 (amano-tsuki-renzoku) を取り下げ。第三話 (ren03) は既に同じ印で private (変更なし) で、import の記録が post_modified より
 *      古い (自己修復)。連載の親は import の記録がある (状態を変えたら記録を直す)
 *  S11 単発 (amano-tsuki-tanpen) は status reinstated。いまは S11 の印で private (復帰)
 *  S12 本サイトに無い作品 (該当投稿なし)   S13 target が無い (不完全なエントリ)   S14 未知の kind
 * denylist に無い印: 入れ子の上 (iri01) は S5 (警告)、下 (iri02) は申請 req:7 (申請による取り下げとして数え、URL を残す)。
 * 返り値: ts_fixture_takedown_targets の ID
 */
function ts_fixture_apply_takedown_scene(string $repo): array {
    $t = ts_fixture_takedown_targets();
    $note = '「連続する月」(天野 月さん) — 作者の申し出により掲載していません';
    TS_WP_Stubs::$posts[$t['ep'][2]]->post_status = 'private';
    TS_WP_Stubs::set_meta($t['ep'][2], ['_ts_visibility' => 'withdrawn', '_ts_takedown_id' => 'S10', '_ts_public_note' => $note,
                                        '_ts_takedown_date' => '2026-01-02', '_ts_last_import_gmt' => '2000-01-01 00:00:00']);
    TS_WP_Stubs::set_meta($t['serial'], ['_ts_last_import_gmt' => '2026-10-30 00:00:00']);
    TS_WP_Stubs::$posts[$t['single']]->post_status = 'private';
    TS_WP_Stubs::set_meta($t['single'], ['_ts_visibility' => 'withdrawn', '_ts_takedown_id' => 'S11', '_ts_public_note' => '「短い月」— 掲載していません',
                                         '_ts_takedown_date' => '2025-05-05']);
    TS_WP_Stubs::$posts[$t['nep'][0]]->post_status = 'private';
    TS_WP_Stubs::set_meta($t['nep'][0], ['_ts_visibility' => 'withdrawn', '_ts_takedown_id' => 'S5']);
    TS_WP_Stubs::$posts[$t['nep'][1]]->post_status = 'private';
    TS_WP_Stubs::set_meta($t['nep'][1], ['_ts_visibility' => 'withdrawn', '_ts_takedown_id' => 'req:7']);
    if (!is_dir("$repo/takedown")) mkdir("$repo/takedown", 0777, true);
    file_put_contents("$repo/takedown/denylist.yml", "---\n# 試験の作り物\nversion: 1\nentries:\n"
        . "  - id: S10\n    target:\n      kind: work\n      value: amano-tsuki-renzoku\n    status: applied\n    requested_at: 2026-01-02\n"
        . "    public_note: $note\n\n"
        . "  - id: S11\n    target: { kind: work, value: amano-tsuki-tanpen }\n    status: reinstated\n    requested_at: 2025-05-05\n\n"
        . "  - id: S12\n    target:\n      kind: work\n      value: nai-sakuhin\n    status: applied\n\n"
        . "  - id: S13\n    status: applied\n\n"
        . "  - id: S14\n    target:\n      kind: picture\n      value: x.png\n");
    return $t;
}

/**
 * wp ts apply-takedown を dry-run → 適用 → もう一度適用の順に流す。$apply は [$args, $assoc] を受けてコマンドを呼ぶ
 * (新版は TS_Takedown_Command::apply_takedown、旧版は TS_Command::apply_takedown)。$db_rows は denylist に無い印を探す問い合わせ
 * ($wpdb->get_col) が返す投稿 ID を回ごとに (SQL は解釈しないので、その時点で withdrawn の ts_work を試験が書く)。
 * 返り値: 回ごとの [WP_CLI::$out, 投稿の状態 (ts_fixture_takedown_snapshot の posts), option ts_takedown_urls, wp_update_post の数]
 */
function ts_fixture_run_apply_takedown(callable $apply, string $repo, array $db_rows): array {
    $out = [];
    foreach ([['dry', ['repo' => $repo, 'dry-run' => true]], ['apply', ['repo' => $repo]], ['again', ['repo' => $repo]]] as $i => [$name, $assoc]) {
        WP_CLI::$out = [];
        TS_WP_Stubs::$log = [];
        TS_WP_Stubs::$db_rows = [array_map(function ($id) { return ['post_id' => (string) $id]; }, $db_rows[$i])];
        $apply([], $assoc);
        $updates = count(array_filter(TS_WP_Stubs::$log, function ($l) { return $l[0] === 'wp_update_post'; }));
        $out[$name] = [WP_CLI::$out, ts_fixture_takedown_snapshot(['request' => 0])['posts'], get_option('ts_takedown_urls'), $updates];
    }
    WP_CLI::$out = [];
    return $out;
}

/**
 * cron ts_takedown_tick を ts_fixture_takedown_tick_scene の上で 1 度流す。$tick は ts_takedown_tick (新版と旧版で同じ名前)。
 * 返り値: 申請ごとのメタ (ts_fixture_request_meta。_tr_targets は除く。確認時刻のほかの時刻は「いま」からの差)・投稿の状態・
 * option ts_takedown_urls・送ったメール・option ts_takedown_last_tick (時刻は差)
 */
function ts_fixture_run_takedown_tick(callable $tick): array {
    $now = time();
    $ids = ts_fixture_takedown_tick_scene($now);
    $tick();
    $rel = ['_tr_received_at', '_tr_auto_apply_at', '_tr_reminded_at', '_tr_resolved_at'];
    $requests = [];
    foreach (['request', 'expire', 'young', 'remind', 'reminded', 'purge', 'purged', 'recent'] as $k) {
        $m = ts_fixture_request_meta($ids[$k], $now, $rel);
        unset($m['_tr_targets']);
        $requests[$k] = $m;
    }
    $s = ts_fixture_takedown_snapshot($ids);
    $last = get_option('ts_takedown_last_tick');
    if (is_array($last)) $last['time'] = ts_fixture_rel($last['time'], $now);
    return ['requests' => $requests, 'posts' => $s['posts'], 'urls' => $s['urls'], 'mails' => ts_fixture_mails($now), 'last_tick' => $last];
}

// ---------------------------------------------------------------- 残りの経路 (T45.6.5): 一覧の問い合わせ・410/404・年表とオススメの索引・運営委員の操作・
// denylist に無い申請・wp ts export-takedowns

/**
 * トップと書庫の問い合わせ (TS_Library::front_page_query / archive_query) を、管理画面か・本クエリか・条件 (home・search・tax) の
 * 組ごとに新しい WP_Query で流し、set された query_vars を [front_page_query の後, archive_query の後] で返す。
 * $front・$archive は 2 つのメソッド (新版と旧版で同じ名前)
 */
function ts_fixture_run_site_queries(callable $front, callable $archive): array {
    TS_WP_Stubs::reset();
    $cases = [
        'home' => [false, true, ['home' => true]],
        'home_admin' => [true, true, ['home' => true]],
        'home_not_main' => [false, false, ['home' => true]],
        'tax_author' => [false, true, ['tax' => 'ts_author']],
        'tax_world' => [false, true, ['tax' => 'ts_world']],
        'tax_corpus' => [false, true, ['tax' => 'ts_corpus']],
        'tax_not_main' => [false, false, ['tax' => 'ts_genre']],
        'tax_admin' => [true, true, ['tax' => 'ts_genre']],
        'search' => [false, true, ['search' => true]],
        'search_tax' => [false, true, ['search' => true, 'tax' => 'ts_genre']],
        'search_admin' => [true, true, ['search' => true]],
        'plain' => [false, true, []],
    ];
    $out = [];
    foreach ($cases as $name => [$admin, $main, $flags]) {
        TS_WP_Stubs::$is = $admin ? ['admin' => true] : [];
        $got = [];
        foreach ([$front, $archive] as $fn) {
            $q = new WP_Query();
            $q->is_main = $main;
            $q->flags = $flags;
            $fn($q);
            $got[] = $q->query_vars;
        }
        $out[$name] = $got;
    }
    TS_WP_Stubs::reset();
    return $out;
}

/**
 * 可視性の template_redirect@0 (specs/S117.md・S118.md) を、投稿の状態 (公開・取り下げ・旧版・移行前の draft・印の無い private) と
 * 見る人 (読者・運営委員) の組と、該当しない要求 (管理画面・/novel/ の外・無い作品・ts_work でない) で流す。
 * 本番と同じく、公開の投稿と運営委員の private は本クエリに乗る (is_singular)、ほかは 404 になって $wp->request から引く。
 * $redirect は template_redirect@0 の callable、$gone は 410 の印を読む callable、$clear は印を下ろす callable
 * (新版は TS_Visibility_Request::$gone、旧版は $GLOBALS['ts_visibility_gone'])。
 * 返り値: 名前 => [記録 (set_404・nocache_headers・status_header), 410 の印, document_title_parts の題]
 */
function ts_fixture_run_visibility(callable $redirect, callable $gone, callable $clear): array {
    TS_WP_Stubs::reset();
    $w = function (string $name, string $status, array $meta) {
        $id = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_name' => $name, 'post_title' => $name, 'post_status' => $status]);
        TS_WP_Stubs::set_meta($id, $meta);
        return $id;
    };
    $ids = [
        'publish' => $w('kokai', 'publish', []),
        'withdrawn' => $w('torisage', 'private', ['_ts_visibility' => 'withdrawn', '_ts_takedown_id' => 'S9']),
        'superseded' => $w('kyuban', 'private', ['_ts_visibility' => 'superseded', '_ts_superseded_by' => 'shinban']),
        'draft' => $w('ikozen', 'draft', []),
        'draft_withdrawn' => $w('ikozen-torisage', 'draft', ['_ts_visibility' => 'withdrawn']),
        'private' => $w('hikokai', 'private', []),
    ];
    $doc = TS_WP_Stubs::add_post(['post_type' => 'ts_doc', 'post_name' => 'bunsho', 'post_title' => '文書', 'post_status' => 'private']);
    $saved = [$GLOBALS['wp'] ?? null, $GLOBALS['wp_query'] ?? null];
    $run = function (array $is, $queried, string $request, array $caps) use ($redirect, $gone, $clear) {
        TS_WP_Stubs::$is = $is; TS_WP_Stubs::$queried = $queried; TS_WP_Stubs::$caps = $caps; TS_WP_Stubs::$log = [];
        $GLOBALS['wp'] = (object) ['request' => $request];
        $GLOBALS['wp_query'] = new WP_Query();
        $clear();
        $redirect();
        $log = array_values(array_map(function ($l) { return $l[0] . ($l[1] ? '(' . implode(',', $l[1]) . ')' : ''); },
            array_filter(TS_WP_Stubs::$log, function ($l) { return in_array($l[0], ['set_404', 'nocache_headers', 'status_header'], true); })));
        $res = [$log, (bool) $gone(), apply_filters('document_title_parts', ['title' => '元の題'])['title']];
        $clear();
        return $res;
    };
    $out = [];
    foreach ($ids as $name => $id) {
        $p = get_post($id);
        foreach (['reader' => [], 'committee' => ['ts_view_hidden']] as $who => $caps) {
            $on_query = $p->post_status === 'publish' || ($p->post_status === 'private' && $caps);
            $out["$name/$who"] = $on_query
                ? $run(['singular' => true], $p, 'novel/' . $p->post_name, $caps)
                : $run(['404' => true], null, 'novel/' . $p->post_name . '/', $caps);
        }
    }
    $out['admin'] = $run(['admin' => true, '404' => true], null, 'novel/torisage/', []);
    $out['outside'] = $run(['404' => true], null, 'docs/torisage/', []);
    $out['missing'] = $run(['404' => true], null, 'novel/nai/', []);
    $out['doc'] = $run(['singular' => true], get_post($doc), 'docs/bunsho', []);
    [$GLOBALS['wp'], $GLOBALS['wp_query']] = $saved;
    TS_WP_Stubs::reset();
    return $out;
}

/**
 * 年表の索引 ts_bunko_index_timeline と、オススメの索引 ts_bunko_index_osusume の HTML。年表は $wpdb が返す年ごとの作品数の行
 * (TIMELINE_FROM より前の年を含む) を積む。オススメは ts_fixture_render_parts の場面 (原パスの表は 1 プロセスに 1 度なので、
 * ここで作る。既に作られていればそのまま) で、公開の投稿の _ts_osusume の行 (直列化した値。解決できる参照・できない参照・
 * 題の無い参照、refs が空、配列でない値) を積む。もう 1 度、行が無いとき (0 件) も描く
 */
function ts_fixture_run_index_rows(): array {
    TS_WP_Stubs::reset();
    TS_WP_Stubs::$db_rows = [[['y' => '1985', 'c' => '2'], ['y' => '1990', 'c' => '1'], ['y' => '2001', 'c' => '12'], ['y' => '2003', 'c' => '40']]];
    $out = ['timeline' => TS_WP_Stubs::capture('ts_bunko_index_timeline')];
    TS_WP_Stubs::$db_rows = [[]];
    $out['timeline/0'] = TS_WP_Stubs::capture('ts_bunko_index_timeline');
    $ids = ts_fixture_render_parts();
    ts_bunko_source_map();
    TS_WP_Stubs::$db_rows = [[
        ['post_id' => (string) $ids['ep'][0], 'meta_value' => serialize(get_post_meta($ids['ep'][0], '_ts_osusume', true))],
        ['post_id' => (string) $ids['single'], 'meta_value' => serialize(get_post_meta($ids['single'], '_ts_osusume', true))],
        ['post_id' => (string) $ids['serial'], 'meta_value' => 'not-serialized'],
        ['post_id' => '999', 'meta_value' => serialize(['refs' => [['href' => 'novel/200304/hikari03.htm', 'title' => '無い投稿から']]])],
    ]];
    $out['osusume'] = TS_WP_Stubs::capture('ts_bunko_index_osusume');
    TS_WP_Stubs::$db_rows = [[['post_id' => (string) $ids['single'], 'meta_value' => serialize(['refs' => [['href' => 'x.htm']]])]]];
    $out['osusume/0'] = TS_WP_Stubs::capture('ts_bunko_index_osusume');
    TS_WP_Stubs::reset();
    return $out;
}

/**
 * 運営委員の操作 ts_takedown_operate (specs/S128.md) を ts_fixture_takedown_apply_scene の上で順に: 止める 4 通り (権限の無いユーザ・
 * いまの状態で許されない操作・知らない操作・時刻の無い期限の変更) → 期限の変更 → 承認 → 承認の後の却下 (止まる) → 復帰。
 * 却下は別の申請 (確認待ちと受付済み) で。$now は時刻の差の基準。返り値: 操作ごとの [成否, 知らせの文] と、終わった後の
 * 申請のメタ・投稿の状態・option ts_takedown_urls・送ったメール
 */
function ts_fixture_run_takedown_operate(int $now): array {
    $ids = ts_fixture_takedown_apply_scene();
    $req = $ids['request'];
    $deadline = 1794614400;   // 2026-11-14 09:00 (Asia/Tokyo)
    $other = ts_fixture_request([['url' => 'https://novels.xwp.jp/novel/hoka/', 'post_id' => null, 'work_slug' => null]], '星野 空', '0');
    TS_WP_Stubs::set_meta($other, ['_tr_status' => 'unconfirmed', '_tr_token' => 'tokenhash']);
    $third = ts_fixture_request([['url' => 'https://novels.xwp.jp/novel/hoka/', 'post_id' => null, 'work_slug' => null]], '海野 波', '1');
    TS_WP_Stubs::set_meta($third, ['_tr_reminded_at' => 1794000000]);
    $ops = [];
    $ops['no_permission'] = ts_takedown_operate($req, 'approve', 5, '');
    $ops['bad_state'] = ts_takedown_operate($req, 'reinstate', 3, '');
    $ops['unknown'] = ts_takedown_operate($req, 'delete', 3, '');
    $ops['no_deadline'] = ts_takedown_operate($req, 'deadline', 3, '', 0);
    $ops['deadline'] = ts_takedown_operate($third, 'deadline', 4, "延ばします\x07", $deadline);
    $ops['approve'] = ts_takedown_operate($req, 'approve', 3, '承認のメモ');
    $ops['reject_after_approve'] = ts_takedown_operate($req, 'reject', 3, '');
    $ops['reject'] = ts_takedown_operate($other, 'reject', 3, '確認が取れない');
    $ops['reinstate'] = ts_takedown_operate($req, 'reinstate', 4, '復帰のメモ');
    $rel = ['_tr_resolved_at'];   // 受付と確認の時刻は ts_fixture_request の固定の値
    $meta = [];
    foreach (['request' => $req, 'other' => $other, 'third' => $third] as $k => $id) {
        $m = ts_fixture_request_meta($id, $now, $rel);
        unset($m['_tr_targets']);
        $meta[$k] = $m;
    }
    $s = ts_fixture_takedown_snapshot($ids);
    $out = ['ops' => $ops, 'meta' => $meta, 'posts' => $s['posts'], 'urls' => $s['urls'], 'mails' => ts_fixture_mails($now)];
    TS_WP_Stubs::reset();
    return $out;
}

/**
 * denylist への書き写し (specs/S132.md) の場面: ts_fixture_takedown_targets の作品に、申請 8 件と $repo/takedown/denylist.yml と
 * $repo/SPECS.md (「次は {$s}250」。$s は識別子の S の後の書き方で、9c3119cc より前は 'S.'、後は 'S') を置く。
 *  'applied'    連載 (work) と第一話 (アンカー分割の話 → episode) を対象。名前の掲示可
 *  'approved'   第二話 (話だけ → episode)。名前の掲示不可
 *  'reinstated' 書き写す前に復帰 (出さない)       'moved'    書き写した後に復帰 (denylist の status を直す警告)
 *  'exported'   書き写し済み (出さない)            'bare'     work_slug も source_path も無い投稿だけが対象 (警告 2 つ)
 *  'old'        2020 年に適用 (--since で落ちる)    'received' 受付済み (対象外)
 * denylist には 'moved' と 'exported' のエントリと、kind の無いエントリ (読めない警告) がある。投稿の ID と申請の ID は固定。
 * 返り値: 名前 => 申請の投稿 ID
 */
function ts_fixture_export_scene(string $repo, string $s): array {
    $t = ts_fixture_takedown_targets();
    TS_WP_Stubs::run_init();
    TS_WP_Stubs::set_meta($t['ep'][0], ['_ts_source_path' => 'novel/200104/ren.htm', '_ts_source_anchor' => 'ren01']);
    TS_WP_Stubs::set_meta($t['ep'][1], ['_ts_source_path' => 'novel/200105/ren02.htm']);
    $url = function (int $pid) { return ['url' => get_permalink($pid), 'post_id' => $pid, 'work_slug' => get_post_meta($pid, '_ts_work_slug', true) ?: null]; };
    $applied = 1793923200;   // 2026-11-06 09:00 (Asia/Tokyo)
    $req = function (string $status, array $targets, string $allow, int $resolved) {
        $id = ts_fixture_request($targets, '天野 月', $allow);
        TS_WP_Stubs::set_meta($id, ['_tr_status' => $status, '_tr_resolved_at' => $resolved]);
        return $id;
    };
    $r = [];
    $r['applied'] = $req('applied', [$url($t['serial']), $url($t['ep'][0]), ['url' => 'https://novels.xwp.jp/novel/nai/', 'post_id' => null, 'work_slug' => null]], '1', $applied);
    $r['approved'] = $req('approved', [$url($t['ep'][1])], '0', $applied + 3600);
    $r['reinstated'] = $req('reinstated', [$url($t['single'])], '1', $applied);
    $r['moved'] = $req('reinstated', [$url($t['nested'])], '1', $applied);
    $r['exported'] = $req('applied', [$url($t['single'])], '1', $applied);
    $r['bare'] = $req('applied', [$url($t['grand'])], '1', $applied);
    $r['old'] = $req('applied', [$url($t['single'])], '1', 1590000000);
    $r['received'] = $req('received', [$url($t['single'])], '1', 0);
    if (!is_dir("$repo/takedown")) mkdir("$repo/takedown", 0777, true);
    file_put_contents("$repo/takedown/denylist.yml", "version: 1\nentries:\n"
        . "  - id: {$s}240\n    target:\n      kind: work\n      value: amano-tsuki-iriko\n    reason: 取り下げフォーム経由の申し出 (申請 {$r['moved']})\n    status: applied\n\n"
        . "  - id: {$s}241\n    target:\n      kind: work\n      value: amano-tsuki-tanpen\n    reason: 取り下げフォーム経由の申し出 (申請 {$r['exported']})\n    status: applied\n\n"
        . "  - id: {$s}242\n    reason: kind の無いエントリ\n");
    file_put_contents("$repo/SPECS.md", "# 仕様の一覧\n\nいまの最後は {$s}249、次は {$s}250。\n");
    return $r;
}

/** ts_fixture_export_scene が $repo に置いたファイルを消す */
function ts_fixture_export_scene_clean(string $repo): void {
    foreach (["$repo/takedown/denylist.yml", "$repo/SPECS.md"] as $f) if (is_file($f)) unlink($f);
    if (is_dir("$repo/takedown")) rmdir("$repo/takedown");
}

/**
 * wp ts export-takedowns (specs/S132.md) を ts_fixture_export_scene の上で引数ごとに流す。$export は $assoc を受けてコマンドを呼ぶ。
 * 標準出力 (YAML) は出力のバッファで受け、案内の標準エラーはプロセスの標準エラーにそのまま出る (受けるのは呼び出し側)。
 * 返り値: 名前 => [標準出力, WP_CLI::$out]。WP_CLI::error で止まったものは WP_CLI::$out の最後が error
 */
function ts_fixture_run_export_takedowns(callable $export, string $repo, string $s): array {
    ts_fixture_export_scene($repo, $s);
    $out = [];
    foreach (['start' => ['start' => '300'], 'specs' => [], 'since' => ['start' => '300', 'since' => '2026-01-01'],
              'none' => ['start' => '300', 'since' => '2099-01-01'], 'bad_start' => ['start' => '3x'],
              'bad_since' => ['start' => '300', 'since' => '2026/01/01']] as $name => $assoc) {
        WP_CLI::$out = [];
        ob_start();
        try { $export(array_merge(['repo' => $repo], $assoc)); } catch (TS_CLI_Error $e) {}
        $out[$name] = [ob_get_clean(), WP_CLI::$out];
    }
    WP_CLI::$out = [];
    ts_fixture_export_scene_clean($repo);
    TS_WP_Stubs::reset();
    return $out;
}

/**
 * denylist に書き写していない申請 (applied と approved)。$find は $repo を受けて [投稿 ID の列, 警告の文の列] を返す
 * (新版は TS_Takedown_Export_State::requests_not_in_denylist、旧版は TS_Command の private の同名のメソッドと WP_CLI::$out)。
 * 返り値: ['found' => 場面の上で, 'no_type' => 申請の投稿タイプが登録されていないとき, 'names' => 見つかった ID の場面の名前]
 */
function ts_fixture_run_requests_not_in_denylist(callable $find, string $repo): array {
    $r = ts_fixture_export_scene($repo, 'S');
    $found = $find($repo);
    $types = TS_WP_Stubs::$registered['post_type'];
    unset(TS_WP_Stubs::$registered['post_type']['ts_takedown_request']);
    $no_type = $find($repo);
    TS_WP_Stubs::$registered['post_type'] = $types;
    ts_fixture_export_scene_clean($repo);
    $names = array_map(function ($id) use ($r) { return array_search($id, $r, true); }, $found[0]);
    TS_WP_Stubs::reset();
    return ['found' => $found, 'no_type' => $no_type, 'names' => $names];
}

/** 子のプロセス (scripts/wp/ts_library_test.php --child export-takedowns <repo>) で新版の wp ts export-takedowns を流す。案内は標準エラーに出る */
function ts_fixture_child_export_takedowns(string $repo): array {
    $cmd = new TS_Takedown_Command();
    return ts_fixture_run_export_takedowns(function ($assoc) use ($cmd) { $cmd->export_takedowns([], $assoc); }, $repo, 'S');
}

// ---------------------------------------------------------------- wp ts import と wp ts verify (T8.2。T45.6.6)
// 小さな作り物の catalog を一時ディレクトリに置いて --repo で渡す。import は本番 DB に書く唯一の経路なので、代わりが本物と違う所
// (WP_Query の見る引数・post_modified・get_term_by の slug の掃除・verify の SQL) は wp-stubs.php の先頭と specs/S242.md に書いてある

/** 話 1 つの行 (episodes.jsonl)。TS_Episode_Fields::meta が読む欄を全部持たせ、$over で上書きする */
function ts_fixture_import_episode(string $id, string $path, string $title, string $date, array $over = []): array {
    return array_merge([
        'episode_id' => $id, 'corpus' => 'honkan', 'source_path' => $path, 'source_anchor' => null, 'title' => $title,
        'date' => $date, 'date_raw' => str_replace('-', '/', $date), 'size_kb' => 12, 'files_n' => null,
        'illustrator' => [], 'illustrator_url' => null, 'kansou_slug' => null, 'kansou_annex_url' => null,
        'arasuji' => null, 'comment' => null, 'suisen' => null, 'osusume' => null, 'nav_links' => [],
        'genre' => [], 'genre_raw' => [], 'type' => [], 'type_raw' => [], 'keywords' => [], 'keywords_raw' => [], 'zokusei' => [],
        'catalog_ref' => 'lib1.html#1', 'orig_url' => "http://ts.novels.jp/$path", 'annex_url' => "https://example.com/annex/$path",
        'annex_yays_url' => null, 'provenance' => ['route' => 'wayback', 'route_label' => 'Internet Archive Wayback Machine'],
    ], $over);
}

/**
 * 作り物の catalog を $repo/catalog/ に書く (works.jsonl・episodes.jsonl・terms.json・authors.json)。作品 4 つと話 8 つ:
 *  'amano-tsuki-renzoku' 連載 3 話 (天野 月)。第二話の分類は原表記 (学園？)、第三話は旧目録 (kyukan) で語 (あめ) を持つ
 *  'amano-tsuki-tanpen'  単発 (天野 月)
 *  'hoshino-akari-hoshi' 単発 (星野 灯。--author で絞る。annex_url なし)
 *  'umino-nami-nazo'     連載 2 話 (作者の slug は大文字まじり Umino_Nami。WP の term は小文字)。nazo01 の語に「?」(指定なしの印)
 *  work の無い話 kodoku (親なし・作者なし。orig_url も provenance も無い)
 * 語彙 (terms.json): ts_genre 2 語・ts_type 2 語・ts_keyword 2 語・ts_world 1 語 (共有世界は第一話と nazo01)・ts_corpus 2 語。
 * $changed は catalog を直した後: 連載の題・第一話のあらすじと語彙に無い分類 (ホラー)・第二話の題
 */
function ts_fixture_import_catalog(string $repo, bool $changed = false): void {
    if (!is_dir("$repo/catalog")) mkdir("$repo/catalog", 0777, true);
    $ep = function (string $file, string $title, string $date, array $over = []) {
        return ts_fixture_import_episode("novel__" . str_replace('/', '__', $file), "novel/$file", $title, $date, $over);
    };
    $episodes = [
        $ep('200104/ren01.htm', '第一話', '2001-04-01', ['genre' => $changed ? ['学園', 'ホラー'] : ['学園'], 'genre_raw' => ['学園'], 'type' => ['変身'], 'type_raw' => ['変身'],
            'keywords' => ['ねこ'], 'keywords_raw' => ['ねこ'], 'arasuji' => $changed ? '直したあらすじ' : '月が連なる。', 'illustrator' => ['絵筆 太郎'],
            'kansou_slug' => 'amano', 'kansou_annex_url' => 'https://example.com/annex/bbs/amano.cgi', 'suisen' => '胸が熱くなる', 'comment' => 'ありがとう']),
        $ep('200105/ren02.htm', $changed ? '第二話 (改)' : '第二話', '2001-05-02', ['genre' => ['学園？'], 'genre_raw' => ['学園？'], 'orig_url' => null, 'size_kb' => 8]),
        $ep('200106/ren03.htm', '第三話', '2001-06-03', ['corpus' => 'kyukan', 'genre' => ['コメディ', '学園'], 'genre_raw' => ['コメディ', '学園'], 'type' => ['変身', '入れ替わり'],
            'type_raw' => ['変身', '入れ替わり'], 'keywords' => ['あめ', 'ねこ'], 'keywords_raw' => ['あめ', 'ねこ'], 'catalog_ref' => 'lib01.html#3',
            'osusume' => ['recommender' => '読者 一', 'refs' => [['href' => 'novel/200104/ren01.htm', 'title' => '第一話']]], 'nav_links' => [['href' => 'novel/200104/ren01.htm', 'label' => '最初へ']]]),
        $ep('200107/tanpen.htm', '短い月', '2001-07-07', ['genre' => ['コメディ'], 'genre_raw' => ['コメディ'], 'type' => ['入れ替わり'], 'type_raw' => ['入れ替わり'],
            'annex_yays_url' => 'https://example.com/annex-yays/novel/200107/tanpen.htm', 'zokusei' => ['TS']]),
        $ep('200208/hoshi.htm', '星の歌', '2002-08-08', ['type' => ['変身'], 'type_raw' => ['変身'], 'keywords' => ['ねこ'], 'keywords_raw' => ['ねこ'], 'annex_url' => null,
            'provenance' => ['route' => 'ftp']]),
        $ep('200301/nazo01.htm', '謎の上', '2003-01-01', ['genre' => ['学園'], 'genre_raw' => ['学園'], 'keywords' => ['?', 'ねこ'], 'keywords_raw' => ['?', 'ねこ'], 'files_n' => 2]),
        $ep('200301/nazo02.htm', '謎の下', '2003-01-02', ['type' => ['変身'], 'type_raw' => ['変身']]),
        $ep('200302/kodoku.htm', '孤独', '2003-02-02', ['genre' => ['コメディ'], 'genre_raw' => ['コメディ'], 'orig_url' => null, 'provenance' => null]),
    ];
    $work = function (string $slug, string $title, string $author, string $display, array $eps, array $over = []) use ($episodes) {
        $by_id = array_column($episodes, null, 'episode_id');
        $list = [];
        foreach ($eps as $i => $eid) {
            $e = $by_id[$eid];
            $list[] = ['episode_id' => $eid, 'menu_order' => $i + 1, 'title' => $e['title'], 'source_path' => $e['source_path'], 'date' => $e['date'], 'alias_of' => null];
        }
        return array_merge(['work_slug' => $slug, 'title' => $title, 'author_slug' => $author, 'author_display' => $display, 'episode_count' => count($eps),
                            'first_date' => $list[0]['date'], 'last_date' => $list[count($list) - 1]['date'], 'title_pages' => [], 'alias_paths' => [],
                            'needs_review' => false, 'episodes' => $list], $over);
    };
    $works = [
        $work('amano-tsuki-renzoku', $changed ? '連続する月 (改)' : '連続する月', 'amano-tsuki', '天野 月', ['novel__200104__ren01.htm', 'novel__200105__ren02.htm', 'novel__200106__ren03.htm'],
              ['title_pages' => ['novel/title/renzoku.htm'], 'alias_paths' => ['novel/200104/ren.htm'], 'needs_review' => true]),
        $work('amano-tsuki-tanpen', '短い月', 'amano-tsuki', '天野 月', ['novel__200107__tanpen.htm']),
        $work('hoshino-akari-hoshi', '星の歌', 'hoshino-akari', '星野 灯', ['novel__200208__hoshi.htm']),
        $work('umino-nami-nazo', '謎', 'Umino_Nami', '海野 波', ['novel__200301__nazo01.htm', 'novel__200301__nazo02.htm']),
    ];
    $jsonl = function (array $rows) {
        return implode('', array_map(function ($r) { return json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"; }, $rows));
    };
    file_put_contents("$repo/catalog/works.jsonl", $jsonl($works));
    file_put_contents("$repo/catalog/episodes.jsonl", $jsonl($episodes));
    $term = function (string $slug, string $name, array $raw = [], array $lookup = []) {
        return ['slug' => $slug, 'name' => $name, 'count' => 1, 'core' => true, 'raw_variants' => $raw, 'lookup_variants' => $lookup];
    };
    file_put_contents("$repo/catalog/terms.json", json_encode(['generated_by' => '作り物', 'taxonomies' => [
        'ts_genre' => ['rewrite' => 'genre', 'terms' => [$term('gakuen', '学園', ['学園', '学園？'], ['学園?']), $term('komedi', 'コメディ', ['コメディ', 'ギャグ'])]],
        'ts_type' => ['rewrite' => 'type', 'terms' => [$term('henshin', '変身', ['変身']), $term('irekawari', '入れ替わり', ['入れ替わり', '入れ代わり'])]],
        'ts_keyword' => ['rewrite' => 'keyword', 'terms' => [$term('neko', 'ねこ', ['ねこ', '猫']), $term('ame', 'あめ', ['あめ'])]],
        'ts_world' => ['rewrite' => 'world', 'terms' => [$term('hoshi-no-machi', '星の町')]],
        'ts_corpus' => ['rewrite' => null, 'terms' => [$term('honkan', '正規目録 (lib1–73)'), $term('kyukan', '旧目録 (lib01–09)')]],
    ], 'episode_worlds' => ['novel__200104__ren01.htm' => ['hoshi-no-machi'], 'novel__200301__nazo01.htm' => ['hoshi-no-machi']]],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    file_put_contents("$repo/catalog/authors.json", json_encode(['generated_by' => '作り物', 'authors' => [
        ['slug' => 'amano-tsuki', 'display_name' => '天野 月', 'yomi_group' => 'あ行'],
        ['slug' => 'hoshino-akari', 'display_name' => '星野 灯', 'yomi_group' => 'は行'],
        ['slug' => 'Umino_Nami', 'display_name' => '海野 波', 'yomi_group' => 'あ行'],
    ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

/**
 * 本文のペイロード (<episode_id>.html と _meta.json) を $bodies に置く: 第一話 (converted)・第三話 (raw-fallback)・単発 2 つ (tanpen は
 * converted、hoshi は _meta.json に無い)・nazo01 (converted)。第二話はファイルも _meta.json も無い (案内文)、nazo02 は takedown
 * (ファイル無し・本文は空)、kodoku は _meta.json が converted なのにファイルが無い
 */
function ts_fixture_import_payloads(string $bodies): void {
    if (!is_dir($bodies)) mkdir($bodies, 0777, true);
    foreach (['200104__ren01' => '第一話', '200106__ren03' => '第三話', '200107__tanpen' => '短い月', '200208__hoshi' => '星の歌', '200301__nazo01' => '謎の上'] as $f => $title) {
        file_put_contents("$bodies/novel__$f.htm.html", "<!-- wp:paragraph --><p>{$title}の本文。</p><!-- /wp:paragraph -->");
    }
    file_put_contents("$bodies/_meta.json", json_encode(['novel__200104__ren01.htm' => 'converted', 'novel__200106__ren03.htm' => 'raw-fallback',
        'novel__200107__tanpen.htm' => 'converted', 'novel__200301__nazo01.htm' => 'converted', 'novel__200301__nazo02.htm' => 'takedown',
        'novel__200302__kodoku.htm' => 'converted']));
}

/** sync-terms が作る term を置き場に置く: 語彙の全部と、authors.json の作者 (slug は WP と同じく小文字) と擬似作者 2 つ。返り値: slug => term_id */
function ts_fixture_import_terms(): array {
    $ids = [];
    foreach ([['ts_author', 'amano-tsuki', '天野 月'], ['ts_author', 'hoshino-akari', '星野 灯'], ['ts_author', 'umino_nami', '海野 波'],
              ['ts_author', 'unattributed', '作者不詳'], ['ts_author', 'series-index', '（シリーズ目録）'],
              ['ts_genre', 'gakuen', '学園'], ['ts_genre', 'komedi', 'コメディ'], ['ts_type', 'henshin', '変身'], ['ts_type', 'irekawari', '入れ替わり'],
              ['ts_keyword', 'neko', 'ねこ'], ['ts_keyword', 'ame', 'あめ'], ['ts_world', 'hoshi-no-machi', '星の町'],
              ['ts_corpus', 'honkan', '正規目録 (lib1–73)'], ['ts_corpus', 'kyukan', '旧目録 (lib01–09)']] as [$tax, $slug, $name]) {
        $ids[$slug] = TS_WP_Stubs::add_term(['taxonomy' => $tax, 'name' => $name, 'slug' => $slug]);
    }
    return $ids;
}

/** 置き場を空にして catalog・ペイロード・term を置く (投稿は import が作る)。返り値: ts_fixture_import_terms の slug => term_id */
function ts_fixture_import_scene(string $repo, string $bodies): array {
    TS_WP_Stubs::reset();
    if (!post_type_exists('ts_takedown_request')) TS_WP_Stubs::run_init();
    ts_fixture_import_catalog($repo);
    ts_fixture_import_payloads($bodies);
    return ts_fixture_import_terms();
}

/** ts_fixture_import_scene が $repo に置いたファイルを消す */
function ts_fixture_import_scene_clean(string $repo): void {
    foreach (["$repo/catalog", "$repo/payloads"] as $dir) {
        if (!is_dir($dir)) continue;
        foreach (glob("$dir/*") ?: [] as $f) unlink($f);
        rmdir($dir);
    }
}

/**
 * wp ts verify が $wpdb に投げる 7 つの問い合わせの行を、置き場から同じ条件で数えて積む (代わりは SQL を解釈しない): orphan (親が無いか
 * ts_work でない子投稿の数) → 割当数 (taxonomy ごとの term_relationships の行数) → メタの被覆 4 つ (_ts_pub_date_raw・_ts_orig_url・
 * _ts_annex_url・_ts_provenance の行数) → 本文 status (値ごとの数。多い順、同数は値の昇順)。どれもゴミ箱でない ts_work の投稿だけ
 */
function ts_fixture_verify_db_rows(): void {
    $live = [];
    foreach (TS_WP_Stubs::$posts as $p) if ($p->post_type === 'ts_work' && $p->post_status !== 'trash') $live[$p->ID] = $p;
    $orphans = 0;
    foreach ($live as $p) {
        $parent = TS_WP_Stubs::$posts[$p->post_parent] ?? null;
        if ($p->post_parent && (!$parent || $parent->post_type !== 'ts_work')) $orphans++;
    }
    $assigned = [];
    foreach ($live as $p) {
        foreach (TS_WP_Stubs::$object_terms[$p->ID] ?? [] as $tax => $ids) {
            if (!in_array($tax, ['ts_author', 'ts_genre', 'ts_type', 'ts_keyword', 'ts_world', 'ts_corpus'], true)) continue;
            foreach ($ids as $tid) if (isset(TS_WP_Stubs::$terms[$tid])) $assigned[$tax] = ($assigned[$tax] ?? 0) + 1;
        }
    }
    $rows = function (array $kc, string $k, string $c) {
        return array_map(function ($key, $n) use ($k, $c) { return [$k => (string) $key, $c => (string) $n]; }, array_keys($kc), $kc);
    };
    $coverage = [];
    foreach (['_ts_pub_date_raw', '_ts_orig_url', '_ts_annex_url', '_ts_provenance'] as $mk) {
        $n = 0;
        foreach ($live as $p) $n += count(TS_WP_Stubs::$meta[$p->ID][$mk] ?? []);
        $coverage[] = [['n' => (string) $n]];
    }
    $status = [];
    foreach ($live as $p) foreach (TS_WP_Stubs::$meta[$p->ID]['_ts_body_status'] ?? [] as $v) $status[(string) $v] = ($status[(string) $v] ?? 0) + 1;
    uksort($status, function ($a, $b) use ($status) { return $status[$b] <=> $status[$a] ?: strcmp($a, $b); });
    TS_WP_Stubs::$db_rows = array_merge([[['n' => (string) $orphans]], $rows($assigned, 'taxonomy', 'c')], $coverage, [$rows($status, 'v', 'c')]);
}

/**
 * 投稿 (ts_work) ごとの 1 行 (post_name をキーに): 状態・親の post_name (無い親は #ID)・話順・日付・題・本文・更新時刻 | メタ (キーの順。
 * 配列は JSON、ほかは var_export。import の hash もそのまま: 旧版と同じ hash になることが、本番で全投稿が updated にならない根拠) | term (taxonomy ごとの slug)
 */
function ts_fixture_import_snapshot(): array {
    $out = [];
    foreach (TS_WP_Stubs::$posts as $p) {
        if ($p->post_type !== 'ts_work') continue;
        $parent = $p->post_parent ? (isset(TS_WP_Stubs::$posts[$p->post_parent]) ? TS_WP_Stubs::$posts[$p->post_parent]->post_name : "#{$p->post_parent}") : '';
        $meta = TS_WP_Stubs::$meta[$p->ID] ?? []; ksort($meta);
        $m = [];
        foreach ($meta as $k => $vals) {
            $m[] = "$k=" . implode('|', array_map(function ($v) { return is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : var_export($v, true); }, $vals));
        }
        $terms = TS_WP_Stubs::$object_terms[$p->ID] ?? []; ksort($terms);
        $t = [];
        foreach ($terms as $tax => $ids) $t[] = "$tax=" . implode(',', array_map(function ($id) { return isset(TS_WP_Stubs::$terms[$id]) ? TS_WP_Stubs::$terms[$id]->slug : "#$id"; }, $ids));
        $out[$p->post_name] = "{$p->post_status} parent={$parent} order={$p->menu_order} date={$p->post_date} title={$p->post_title} body=" . var_export($p->post_content, true)
            . " mod={$p->post_modified_gmt} | " . implode(' ', $m) . ' | ' . implode(' ', $t);
    }
    return $out;
}

/**
 * wp ts import と wp ts verify を ts_fixture_import_scene の上で順に流す。$import・$verify は $assoc を受けてコマンドを呼ぶ (新版は
 * TS_Import_Command::import と TS_Verify_Command::verify、旧版は TS_Command の同名)。catalog は $repo/catalog、ペイロードは $repo/payloads。
 * 順: dry0 (空の DB で --dry-run) → first (投入) → verify1 (cron の記録なし) → again (もう一度。全部 skipped) → bodies (--bodies) → bodies_again
 *  → verify2 (cron の記録を 1 時間前に) → author (--author --dry-run) → limit (--limit=1 --dry-run)
 *  → catalog を直し (ts_fixture_import_catalog の $changed)・語 (あめ) の term を消し・第二話を手で直す (post_modified が import の記録より後)
 *  → dry_changed (--dry-run) → manual (手動編集を飛ばす) → verify3 → overwrite (--overwrite-manual)
 *  → はぐれの投稿 (親が無く第三話と同じ episode_id、メタと語つき)・語彙に無い term・失敗した古い cron の記録・denylist に無い申請を置く
 *  → dry_dup (重複の警告) → verify4
 * 返り値: 「段/out」(WP_CLI::$out。cron の最終実行の時刻は ts_fixture_rel)、import の段はさらに「段/writes」(TS_WP_Stubs::$log の関数ごとの数)・
 * 「段/options」(import が書く option 4 つ。ts_last_import_at は有無だけ)・「段/posts」(ts_fixture_import_snapshot の前の段との差: 変わった行と消えた post_name)。
 * catalog のコミットは一時ディレクトリに git が無いので空 (ts_catalog_commit は書かれない)
 */
function ts_fixture_run_import(callable $import, callable $verify, string $repo): array {
    $bodies = "$repo/payloads";
    $now = time();
    $saved_now = TS_WP_Stubs::$now;
    ts_fixture_import_scene($repo, $bodies);
    $out = [];
    $prev = [];
    $step = function (string $name, callable $fn, bool $is_verify = false) use (&$out, &$prev, $now) {
        WP_CLI::$out = []; TS_WP_Stubs::$log = [];
        $fn();
        $out["$name/out"] = array_map(function ($l) use ($now) {
            return strpos($l[1], 'ts_takedown_tick の最終実行') === 0 ? [$l[0], ts_fixture_rel_text($l[1], $now)] : $l;
        }, WP_CLI::$out);
        if ($is_verify) return;
        $writes = array_count_values(array_column(TS_WP_Stubs::$log, 0)); ksort($writes);
        $out["$name/writes"] = $writes;
        $opts = [];
        foreach (['ts_catalog_commit', 'ts_last_import_at', 'ts_manual_edit_warnings', 'ts_term_warnings'] as $o) {
            $opts[$o] = $o === 'ts_last_import_at' ? (array_key_exists($o, TS_WP_Stubs::$options) ? '(ある)' : '(無い)') : get_option($o, '(無い)');
        }
        $out["$name/options"] = $opts;
        $snap = ts_fixture_import_snapshot();
        $out["$name/posts"] = ['changed' => array_diff_assoc($snap, $prev), 'gone' => array_values(array_diff(array_keys($prev), array_keys($snap)))];
        $prev = $snap;
    };
    $imp = function (array $assoc) use ($import, $repo) { $import(array_merge(['repo' => $repo], $assoc)); };
    $ver = function () use ($verify, $repo) { ts_fixture_verify_db_rows(); $verify(['repo' => $repo]); };
    $by_eid = function (string $eid) {
        return get_posts(['post_type' => 'ts_work', 'post_status' => 'any', 'meta_key' => '_ts_episode_id', 'meta_value' => $eid, 'fields' => 'ids'])[0];
    };

    $step('dry0', function () use ($imp) { $imp(['dry-run' => true]); });
    $step('first', function () use ($imp) { $imp([]); });
    $step('verify1', $ver, true);
    $step('again', function () use ($imp) { $imp([]); });
    $step('bodies', function () use ($imp, $bodies) { $imp(['bodies' => $bodies]); });
    $step('bodies_again', function () use ($imp, $bodies) { $imp(['bodies' => $bodies]); });
    TS_WP_Stubs::$options['ts_takedown_last_tick'] = ['time' => $now - 3600, 'error' => ''];
    $step('verify2', $ver, true);
    $step('author', function () use ($imp, $bodies) { $imp(['bodies' => $bodies, 'dry-run' => true, 'author' => 'hoshino-akari']); });
    $step('limit', function () use ($imp, $bodies) { $imp(['bodies' => $bodies, 'dry-run' => true, 'limit' => '1']); });

    ts_fixture_import_catalog($repo, true);
    wp_delete_term(get_term_by('slug', 'ame', 'ts_keyword')->term_id, 'ts_keyword');
    TS_WP_Stubs::$now = '2026-11-06 09:00:00';
    wp_update_post(['ID' => $by_eid('novel__200105__ren02.htm'), 'post_title' => '手で直した題']);
    $step('dry_changed', function () use ($imp, $bodies) { $imp(['bodies' => $bodies, 'dry-run' => true]); });
    $step('manual', function () use ($imp, $bodies) { $imp(['bodies' => $bodies]); });
    $step('verify3', $ver, true);
    $step('overwrite', function () use ($imp, $bodies) { $imp(['bodies' => $bodies, 'overwrite-manual' => true]); });

    $stray = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => 999, 'post_name' => 'hagure', 'post_title' => 'はぐれ', 'post_date' => '1999-01-01 00:00:00']);
    TS_WP_Stubs::set_meta($stray, ['_ts_episode_id' => 'novel__200106__ren03.htm', '_ts_pub_date_raw' => '1999/1/1', '_ts_body_status' => 'converted']);
    TS_WP_Stubs::set_terms($stray, 'ts_genre', [get_term_by('slug', 'gakuen', 'ts_genre')->term_id]);
    TS_WP_Stubs::add_term(['taxonomy' => 'ts_genre', 'name' => '闇', 'slug' => 'yami']);
    TS_WP_Stubs::$options['ts_takedown_last_tick'] = ['time' => $now - 2 * 86400, 'error' => 'DB error'];
    $tanpen = get_posts(['post_type' => 'ts_work', 'post_status' => 'any', 'name' => 'amano-tsuki-tanpen', 'fields' => 'ids'])[0];
    $req = ts_fixture_request([['url' => 'https://novels.xwp.jp/novel/amano-tsuki-tanpen/', 'post_id' => $tanpen, 'work_slug' => 'amano-tsuki-tanpen']]);
    TS_WP_Stubs::set_meta($req, ['_tr_status' => 'applied']);
    $step('dry_dup', function () use ($imp, $bodies) { $imp(['bodies' => $bodies, 'dry-run' => true]); });
    $step('verify4', $ver, true);

    WP_CLI::$out = [];
    TS_WP_Stubs::$now = $saved_now;
    ts_fixture_import_scene_clean($repo);
    TS_WP_Stubs::reset();
    return $out;
}
