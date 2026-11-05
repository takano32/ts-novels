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
