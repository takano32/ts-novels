<?php
/**
 * 取り下げフォームと一定期間後の自動適用 (specs/S.124.md〜S.136.md、T.26.3)。
 *
 *  - 申請の投稿タイプ ts_takedown_request とメタ (S.125)。`wp ts reset` の対象に入れない
 *    (TS_Command::TYPES に無い。scripts/wp/ts_library_test.php が確かめる)
 *  - フォーム [ts_takedown_form] → admin-post.php?action=ts_takedown (S.124)
 *  - 確認リンク ?ts_confirm=<token> (S.126)
 *  - cron ts_takedown_tick: 失効 → リマインド → 自動適用 → 依頼者情報の保持期限 (S.126・S.127・S.130・S.125)
 *  - 掲載していない作品の一覧 [ts_removed_list] (S.131)
 *  - 運営委員の操作 4 つは includes/takedown-admin.php (S.128)、
 *    `wp ts export-takedowns` は includes/commands.php (S.132)
 *
 * 裁定待ちのパラメータ (S.135) は既定値で動き、wp-config.php で同名の定数を先に define すれば変えられる
 * (ts_takedown_params)。判定・文面・YAML は WordPress を使わない純粋な関数にして単体で試験する。
 * セキュリティ: 依頼者宛のメールには依頼者の入力した文字列を入れない (他人のアドレスを書いて
 * このサイトから任意の文面を送らせる中継に使わせない)。運営委員宛は書式なしのテキストで、件名には入れない。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

const TS_TAKEDOWN_TYPE = 'ts_takedown_request';

/** 状態 (S.126) と、運営委員の画面に出す呼び名 */
function ts_takedown_status_labels(): array {
    return ['unconfirmed' => '未確認 (確認メール待ち)', 'received' => '受付済み (自動適用待ち)',
            'applied' => '自動適用済み (非公開)', 'approved' => '承認して適用済み (非公開)',
            'rejected' => '却下', 'reinstated' => '復帰', 'expired' => '失効 (未確認)'];
}

/**
 * パラメータ (S.126 の定数と S.135 の裁定待ちの表)。wp-config.php で TS_TAKEDOWN_<名前> を
 * define すると既定値より優先する。
 */
function ts_takedown_params(): array {
    $c = function (string $name, $default) {
        $k = 'TS_TAKEDOWN_' . $name;
        return defined($k) ? constant($k) : $default;
    };
    return [
        'confirm_ttl_days' => (int) $c('CONFIRM_TTL_DAYS', 7),
        'auto_apply_days' => (int) $c('AUTO_APPLY_DAYS', 10),       // S.135: 公開文面に日数を書くか
        'remind_before_days' => (int) $c('REMIND_BEFORE_DAYS', 3),
        'retention_days' => (int) $c('RETENTION_DAYS', 365),        // S.135: 依頼者情報の保持期間
        'require_confirm' => (bool) $c('REQUIRE_CONFIRM', true),    // S.135: 確認メールを必須にするか
        // S.135: 通知先。空なら admin_email + 運営委員全員。カンマ区切りで書けばその宛先だけにする
        'notify_to' => (string) $c('NOTIFY_TO', ''),
        'send_reject_mail' => (bool) $c('SEND_REJECT_MAIL', true),  // S.135: 却下のメールを送るか
        'rate_limit' => (int) $c('RATE_LIMIT', 3),                  // S.124: 1 時間 3 件
        'rate_window' => 3600,
        'min_seconds' => 3,                                         // S.124: 表示から送信まで 3 秒未満は 400
    ];
}

// ====================================================================== 純粋な関数 (単体の試験の対象)

/** 制御文字を除く (改行とタブは $keep_newlines のときだけ残す) */
function ts_takedown_clean(string $s, bool $keep_newlines = false): string {
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $re = $keep_newlines ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
    $out = preg_replace($re, $keep_newlines ? '' : ' ', $s);
    return trim($out ?? '');
}

function ts_takedown_strlen(string $s): int {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : (int) preg_match_all('/./us', $s);
}

/**
 * 対象作品の URL の 1 行 → ts_work のパス (get_page_by_path に渡す形)。本サイトの /novel/… でなければ null。
 * $home_host は本サイトのホスト名。ホストを書かない「/novel/…」も本サイトとみなす。
 */
function ts_takedown_target_path(string $line, string $home_host): ?string {
    $line = trim($line);
    if ($line === '' || strlen($line) > 500) return null;
    // 「novels.xwp.jp/novel/…」のようにスキームを省いた書き方も受ける
    if ($home_host !== '' && stripos($line, $home_host . '/') === 0) $line = 'https://' . $line;
    $u = parse_url($line);
    if ($u === false) return null;
    if (isset($u['host'])) {
        if (strtolower($u['host']) !== strtolower($home_host)) return null;
        if (isset($u['scheme']) && !in_array(strtolower($u['scheme']), ['http', 'https'], true)) return null;
    } elseif (isset($u['scheme']) || $line[0] !== '/') {
        return null;
    }
    $path = (string) ($u['path'] ?? '');
    if (!preg_match('#^/novel/#', $path)) return null;
    // includes/visibility.php と同じ規則 (/novel/ の下のパスを rawurldecode して / でつなぐ)
    $parts = array_values(array_filter(explode('/', substr($path, strlen('/novel/'))), 'strlen'));
    if (!$parts) return null;
    foreach ($parts as $p) if ($p === '.' || $p === '..') return null;
    return implode('/', array_map('rawurldecode', $parts));
}

/**
 * フォームの入力を検証する (S.124 の表)。$in は wp_unslash 済みの $_POST 相当。
 * $resolve(string $path): ?array — ts_work に解決できれば ['post_id' => int, 'work_slug' => ?string]。
 * 返り値: ['errors' => [文言…], 'data' => 保存する値]。data['targets'] は [{url, post_id|null, work_slug|null}]。
 */
function ts_takedown_validate(array $in, callable $resolve, string $home_host): array {
    $err = [];
    $s = function ($k) use ($in) { return is_string($in[$k] ?? null) ? $in[$k] : ''; };
    $pen = ts_takedown_clean($s('pen_name'));
    if ($pen === '') $err[] = '当時のペンネームを書いてください。';
    elseif (ts_takedown_strlen($pen) > 100) $err[] = '当時のペンネームは 100 字までです。';

    $lines = array_values(array_filter(array_map('trim', explode("\n", ts_takedown_clean($s('targets'), true))), 'strlen'));
    $targets = [];
    if (!$lines) $err[] = '対象作品の URL を 1 行に 1 つ書いてください。';
    elseif (count($lines) > 20) $err[] = '対象作品の URL は 20 行までです。';
    else {
        $seen = [];
        foreach ($lines as $line) {
            if (strlen($line) > 500) { $err[] = '対象作品の URL が長すぎます (1 行 500 字まで)。'; break; }
            if (isset($seen[$line])) continue;
            $seen[$line] = true;
            $path = ts_takedown_target_path($line, $home_host);
            $hit = $path !== null ? $resolve($path) : null;
            $targets[] = ['url' => $line, 'post_id' => $hit ? (int) $hit['post_id'] : null,
                          'work_slug' => $hit ? ($hit['work_slug'] ?? null) : null];
        }
    }

    $contact = ts_takedown_clean($s('contact'));
    if ($contact === '' || strlen($contact) > 254 || !ts_takedown_is_email($contact)) {
        $err[] = '連絡先のメールアドレスを正しく書いてください。';
    }
    $relation = $s('relation');
    if (!in_array($relation, ['author', 'heir', 'other'], true)) $err[] = 'お立場を選んでください。';
    $evidence = ts_takedown_clean($s('evidence'), true);
    if (ts_takedown_strlen($evidence) > 1000) $err[] = '本人確認の手がかりは 1,000 字までです。';
    $message = ts_takedown_clean($s('message'), true);
    if (ts_takedown_strlen($message) > 2000) $err[] = 'ご連絡事項は 2,000 字までです。';

    return ['errors' => $err, 'data' => [
        'pen_name' => $pen, 'targets' => $targets, 'contact' => $contact, 'relation' => $relation,
        'evidence' => $evidence, 'message' => $message, 'allow_name' => !empty($in['allow_name']) ? '1' : '0',
    ]];
}

/** WordPress があれば is_email()、無ければ (単体の試験) filter_var */
function ts_takedown_is_email(string $s): bool {
    if (function_exists('is_email')) return (bool) is_email($s);
    return filter_var($s, FILTER_VALIDATE_EMAIL) !== false;
}

/** フォーム表示時刻 t の署名つきの値 (送信者が書き換えて 3 秒の検査を逃れないように) */
function ts_takedown_sign_time(int $t, string $key): string {
    return $t . '.' . substr(hash_hmac('sha256', 'ts_takedown_form|' . $t, $key), 0, 32);
}

/** 署名を確かめて表示時刻を返す。壊れていれば null */
function ts_takedown_verify_time(string $v, string $key): ?int {
    if (!preg_match('/^(\d{1,12})\.[0-9a-f]{32}$/', $v, $m)) return null;
    return hash_equals(ts_takedown_sign_time((int) $m[1], $key), $v) ? (int) $m[1] : null;
}

/**
 * スパム対策の判定 (S.124)。nonce 不一致 403 → honeypot 400 → 3 秒未満 400 → レート制限 429 の順。
 * $t は ts_takedown_verify_time の結果 (null = 壊れている)。$recent はこの送信元の 1 時間内の受付件数。
 * 通れば 0、止めるなら HTTP の状態コード。
 */
function ts_takedown_gate(bool $nonce_ok, string $hp, ?int $t, int $now, int $recent, array $p): int {
    if (!$nonce_ok) return 403;
    if ($hp !== '') return 400;
    if ($t === null || $now - $t < $p['min_seconds']) return 400;
    if ($recent >= $p['rate_limit']) return 429;
    return 0;
}

/** 確認したときに書く時刻 (S.126: 自動適用は確認時刻 + AUTO_APPLY_DAYS) */
function ts_takedown_confirm_times(int $now, array $p): array {
    return ['confirmed_at' => $now, 'auto_apply_at' => $now + $p['auto_apply_days'] * 86400];
}

/** 運営委員の操作 (S.128) の遷移。許されない組み合わせは null */
function ts_takedown_op_next(string $status, string $op): ?string {
    $t = ['approve' => ['received' => 'approved'],
          'reject' => ['unconfirmed' => 'rejected', 'received' => 'rejected'],
          'reinstate' => ['applied' => 'reinstated', 'approved' => 'reinstated'],
          'deadline' => ['received' => 'received']];
    return $t[$op][$status] ?? null;
}

/**
 * cron の 1 件の判定 (S.126)。$m: received_at / auto_apply_at / reminded_at (Unix 秒、無ければ 0)。
 * 返り値: expire | apply | remind | null。自動適用の時刻を過ぎていればリマインドは飛ばす。
 */
function ts_takedown_due(string $status, array $m, int $now, array $p): ?string {
    $day = 86400;
    if ($status === 'unconfirmed') {
        return ($m['received_at'] ?? 0) > 0 && $now >= $m['received_at'] + $p['confirm_ttl_days'] * $day ? 'expire' : null;
    }
    if ($status !== 'received' || empty($m['auto_apply_at'])) return null;
    if ($now >= $m['auto_apply_at']) return 'apply';
    if (empty($m['reminded_at']) && $now >= $m['auto_apply_at'] - $p['remind_before_days'] * $day) return 'remind';
    return null;
}

/** 解決から保持期間を過ぎて依頼者情報を消す時か (S.125) */
function ts_takedown_purge_due(string $status, int $resolved_at, int $now, array $p): bool {
    return in_array($status, ['applied', 'rejected', 'reinstated', 'expired'], true)
        && $resolved_at > 0 && $now >= $resolved_at + $p['retention_days'] * 86400;
}

/**
 * /removed/ に出す 1 行 (S.131 の申請由来の生成規則)。$titles は非公開にした作品の題名、
 * $year・$month はお申し出の時期 (確認時刻)。
 */
function ts_takedown_public_note(array $titles, string $pen_name, bool $allow_name, int $year, int $month): string {
    $when = sprintf('(%d 年 %d 月)', $year, $month);
    $titles = array_values(array_filter(array_map('strval', $titles), 'strlen'));
    if ($allow_name && $titles && $pen_name !== '') {
        $t = implode('', array_map(function ($x) { return '「' . $x . '」'; }, $titles));
        return "{$t}({$pen_name}さん) — 作者の申し出により掲載していません {$when}";
    }
    return sprintf('作者の申し出により %d 作品を掲載していません %s', max(1, count($titles)), $when);
}

/**
 * /removed/ の行 (S.131)。$posts: [['id' => _ts_takedown_id, 'note' => _ts_public_note, 'date' => _ts_takedown_date]]。
 * 取り下げの記録ごとに 1 行、お申し出の日の新しい順 (同じ日は記録の番号の順)。
 * 掲示の 1 行が無い記録 (掲示を保留しているもの) は出さない。
 */
function ts_takedown_removed_rows(array $posts): array {
    $by = [];
    foreach ($posts as $p) {
        $id = (string) ($p['id'] ?? '');
        if ($id === '') continue;
        if (!isset($by[$id])) $by[$id] = ['id' => $id, 'note' => '', 'date' => ''];
        if ($by[$id]['note'] === '' && (string) ($p['note'] ?? '') !== '') $by[$id]['note'] = (string) $p['note'];
        if ($by[$id]['date'] === '' && (string) ($p['date'] ?? '') !== '') $by[$id]['date'] = (string) $p['date'];
    }
    $rows = array_values(array_filter($by, function ($r) { return $r['note'] !== ''; }));
    usort($rows, function ($a, $b) {
        return strcmp($b['date'], $a['date']) ?: strnatcmp($a['id'], $b['id']);
    });
    return $rows;
}

/** YAML の 1 値 (denylist.yml に書く形)。そのままで読めない値は単一引用符で囲む */
function ts_takedown_yaml_scalar(?string $v): string {
    if ($v === null) return 'null';
    if ($v !== '' && !preg_match('/^[\s\-?:,\[\]{}#&*!|>\'"%@`]|: | #|\s$|^(null|true|false|yes|no|~)$/i', $v)) return $v;
    return "'" . str_replace("'", "''", $v) . "'";
}

/**
 * 1 申請 → denylist のエントリ (version 1) の YAML (S.132)。連絡先・本文・証拠は出さない。
 * $req: id (投稿 ID) / status / confirmed_date / applied_date / via (takedown_form | email)
 * $targets: [['kind' => work|episode, 'value' => …, 'note' => public_note]]
 * $first: 最初に振る S の番号。返り値: ['yaml' => 文字列, 'ids' => [振った S …]]
 */
function ts_takedown_export_yaml(array $req, array $targets, int $first): array {
    $out = ''; $ids = []; $n = $first;
    $status = $req['status'] === 'reinstated' ? 'reinstated' : 'applied';
    foreach ($targets as $t) {
        $id = 'S.' . $n++;
        $ids[] = $id;
        $out .= "  - id: $id\n"
            . "    target:\n"
            . "      kind: {$t['kind']}\n"
            . '      value: ' . ts_takedown_yaml_scalar($t['value']) . "\n"
            . "    reason: 取り下げフォーム経由の申し出 (申請 {$req['id']})\n"
            . "    reason_class: author_request\n"
            . "    requested_at: {$req['confirmed_date']}\n"
            . "    received_via: {$req['via']}\n"
            . "    status: $status\n"
            . "    applied:\n"
            . '      wp_draft: ' . ts_takedown_yaml_scalar($req['applied_date'] ?: null) . "\n"
            . '      htaccess_410: ' . ts_takedown_yaml_scalar($req['applied_date'] ?: null) . "\n"
            . "      annex_excluded: null\n"
            . "      git_history_purged: null\n"
            . "      removed_page_listed: null\n"
            . '    public_note: ' . ts_takedown_yaml_scalar($t['note']) . "\n";
    }
    return ['yaml' => $out, 'ids' => $ids];
}

/**
 * メールの件名と本文 (S.129)。$c は文脈 (site / id / confirm_url / ttl_days / when / admin_url /
 * summary / titles / unresolved / listed)。依頼者宛 (receipt / applied_requester / rejected) には
 * 依頼者の入力を入れない。
 */
function ts_takedown_mail_text(string $kind, array $c): array {
    $site = $c['site'] ?? '';
    $sig = "\n\n-- \n{$site}\n";
    $list = function (array $xs) { return $xs ? implode("\n", array_map(function ($x) { return "  - $x"; }, $xs)) : '  (なし)'; };
    switch ($kind) {
        case 'receipt':
            if (!empty($c['confirm_url'])) {
                return ["[{$site}] 掲載の取り下げのお申し出の確認",
                    "掲載の取り下げのお申し出を受け付けました。\n\n"
                    . "次のリンクを開くと、お申し出の受け付けが完了します。\n{$c['confirm_url']}\n\n"
                    . "リンクの有効期限は {$c['ttl_days']} 日です。開かれなかったお申し出は無効になります。\n"
                    . "お心当たりのない場合は、このメールを破棄してください。" . $sig];
            }
            return ["[{$site}] 掲載の取り下げのお申し出を受け付けました",
                "掲載の取り下げのお申し出を受け付けました。運営委員が内容を確認します。\n"
                . "お心当たりのない場合は、このメールを破棄してください。" . $sig];
        case 'confirmed':
            return ["[{$site}] 取り下げの申請 {$c['id']} を受け付けました",
                "取り下げの申請 {$c['id']} の確認リンクが開かれました。\n\n{$c['summary']}\n\n"
                . "{$c['when']} に自動で非公開になります。承認・却下は管理画面から:\n{$c['admin_url']}" . $sig];
        case 'remind':
            return ["[{$site}] 取り下げの申請 {$c['id']} はまもなく自動で非公開になります",
                "取り下げの申請 {$c['id']} は、{$c['when']} に自動で非公開になります。\n\n{$c['summary']}\n\n"
                . "承認・却下・期限の変更は管理画面から:\n{$c['admin_url']}" . $sig];
        case 'applied_requester':
            return ["[{$site}] お申し出の作品を非公開にしました",
                "お申し出に基づき、次の作品を非公開にしました。\n" . $list($c['titles'] ?? []) . "\n\n"
                . (!empty($c['listed'])
                    ? "掲載していない作品の一覧には、作品名とペンネームを載せています。"
                    : "掲載していない作品の一覧には、作品名とペンネームを載せていません。") . $sig];
        case 'applied_committee':
            return ["[{$site}] 取り下げの申請 {$c['id']} を適用しました",
                "取り下げの申請 {$c['id']} を適用し、次の作品を非公開にしました。\n" . $list($c['titles'] ?? []) . "\n\n"
                . (!empty($c['unresolved'])
                    ? "本サイトの作品に結びつかなかった URL があります (適用していません):\n" . $list($c['unresolved']) . "\n\n"
                    : '')
                . "{$c['summary']}\n\n管理画面:\n{$c['admin_url']}" . $sig];
        case 'rejected':
            return ["[{$site}] 掲載の取り下げのお申し出について",
                "掲載の取り下げのお申し出について、確認が取れなかったため、取り下げは行いませんでした。\n"
                . "あらためてお申し出いただく場合は、取り下げのページからお願いします。" . $sig];
    }
    return ['', ''];
}

/** 運営委員宛のメールに載せる申請の要約 (入力値は書式なしのテキストとして並べるだけ) */
function ts_takedown_summary(array $d): string {
    $rel = ['author' => '作者本人', 'heir' => '権利継承者', 'other' => 'その他'];
    $lines = ['ペンネーム: ' . ($d['pen_name'] ?? ''),
              '立場: ' . ($rel[$d['relation'] ?? ''] ?? ''),
              '名前の掲示: ' . (($d['allow_name'] ?? '0') === '1' ? '可' : '不可'),
              '対象:'];
    foreach ((array) ($d['targets'] ?? []) as $t) {
        $lines[] = '  - ' . $t['url'] . ($t['post_id'] ? '' : ' (本サイトの作品に結びつかない)');
    }
    return implode("\n", $lines);
}

if (!defined('ABSPATH')) return; // 単体の試験では以下 (WordPress のフック) を読まない

// ====================================================================== 投稿タイプ (S.125)

add_action('init', function () {
    $cap = 'ts_manage_takedown';
    register_post_type(TS_TAKEDOWN_TYPE, [
        'labels' => ['name' => '取り下げの申請', 'singular_name' => '取り下げの申請',
                     'add_new' => '申請を起票', 'add_new_item' => '取り下げの申請を起票',
                     'edit_item' => '取り下げの申請', 'menu_name' => '取り下げの申請'],
        'public' => false, 'show_ui' => true, 'exclude_from_search' => true, 'show_in_rest' => false,
        'publicly_queryable' => false, 'query_var' => false, 'rewrite' => false,
        'menu_icon' => 'dashicons-hidden', 'supports' => false,
        // 一覧・閲覧・操作は ts_manage_takedown だけで決める (S.116 の運営委員ロールと管理者が持つ)
        'map_meta_cap' => false,
        'capabilities' => array_fill_keys(['edit_post', 'read_post', 'delete_post', 'edit_posts',
            'edit_others_posts', 'publish_posts', 'read_private_posts', 'create_posts',
            'delete_posts', 'delete_others_posts', 'delete_private_posts', 'delete_published_posts',
            'edit_private_posts', 'edit_published_posts'], $cap),
    ]);
}, 6);

/** 申請の投稿はいつも private (管理画面の「公開」で起票しても公開状態にしない) */
add_filter('wp_insert_post_data', function ($data) {
    if (($data['post_type'] ?? '') === TS_TAKEDOWN_TYPE
        && !in_array($data['post_status'], ['auto-draft', 'trash', 'private', 'inherit'], true)) {
        $data['post_status'] = 'private';
    }
    return $data;
}, 10, 1);

/** 申請の値を配列で読む */
function ts_takedown_get(int $id): array {
    $g = function ($k) use ($id) { return get_post_meta($id, $k, true); };
    $targets = json_decode((string) $g('_tr_targets'), true);
    return [
        'id' => $id, 'status' => (string) $g('_tr_status'),
        'pen_name' => (string) $g('_tr_pen_name'), 'contact' => (string) $g('_tr_contact'),
        'relation' => (string) $g('_tr_relation'), 'evidence' => (string) $g('_tr_evidence'),
        'message' => (string) $g('_tr_message'), 'allow_name' => (string) $g('_tr_allow_name') === '1' ? '1' : '0',
        'targets' => is_array($targets) ? $targets : [],
        'received_at' => (int) $g('_tr_received_at'), 'confirmed_at' => (int) $g('_tr_confirmed_at'),
        'auto_apply_at' => (int) $g('_tr_auto_apply_at'), 'reminded_at' => (int) $g('_tr_reminded_at'),
        'resolved_at' => (int) $g('_tr_resolved_at'), 'via' => (string) $g('_tr_via') ?: 'takedown_form',
    ];
}

/** 状態を変え、操作の記録を残す (S.128: 誰が・いつ・メモ)。$by = 0 は cron */
function ts_takedown_set_status(int $id, string $status, int $by, string $note, bool $resolve): void {
    update_post_meta($id, '_tr_status', $status);
    if ($resolve) {
        update_post_meta($id, '_tr_resolved_at', time());
        update_post_meta($id, '_tr_resolved_by', $by);
        update_post_meta($id, '_tr_resolution_note', wp_slash($note));
    }
    ts_takedown_log($id, $status, $by, $note);
}

/** 操作の履歴 (1 操作 1 行。_tr_resolved_* は最後の操作だけを持つので、承認の後の復帰などを追えるように) */
function ts_takedown_log(int $id, string $what, int $by, string $note): void {
    add_post_meta($id, '_tr_log', wp_slash(wp_json_encode(['at' => time(), 'by' => $by, 'what' => $what, 'note' => $note],
        JSON_UNESCAPED_UNICODE)));
}

/** 本サイトの /novel/ のパス → 投稿 */
function ts_takedown_resolve(string $path): ?array {
    $post = get_page_by_path($path, OBJECT, 'ts_work');
    if (!($post instanceof WP_Post) || $post->post_status === 'trash') return null;
    $slug = (string) get_post_meta($post->ID, '_ts_work_slug', true);
    return ['post_id' => $post->ID, 'work_slug' => $slug !== '' ? $slug : null];
}

/** 申請を作る (フォームと、運営委員がメールの申し出を起票するときの共通)。返り値は投稿 ID */
function ts_takedown_store(array $d, string $via, int $post_id = 0): int {
    $p = ts_takedown_params();
    $now = time();
    if ($post_id === 0) {
        $post_id = (int) wp_insert_post(['post_type' => TS_TAKEDOWN_TYPE, 'post_status' => 'private',
                                          'post_title' => '申請', 'post_author' => 0], true);
        if ($post_id <= 0) return 0;
    }
    $meta = ['_tr_pen_name' => $d['pen_name'], '_tr_contact' => $d['contact'], '_tr_relation' => $d['relation'],
             '_tr_evidence' => $d['evidence'], '_tr_message' => $d['message'], '_tr_allow_name' => $d['allow_name'],
             '_tr_targets' => wp_json_encode($d['targets'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
             '_tr_received_at' => $now, '_tr_via' => $via];
    foreach ($meta as $k => $v) update_post_meta($post_id, $k, wp_slash($v));
    $title = '申請 ' . $post_id . ' ' . $d['pen_name'];
    $GLOBALS['ts_takedown_saving'] = true;
    wp_update_post(['ID' => $post_id, 'post_title' => wp_slash($title), 'post_status' => 'private']);
    $GLOBALS['ts_takedown_saving'] = false;
    if ($via === 'takedown_form' && $p['require_confirm']) {
        ts_takedown_set_status($post_id, 'unconfirmed', 0, '', false);
    } else {
        ts_takedown_mark_received($post_id, $now, (int) get_current_user_id());
    }
    return $post_id;
}

/** 確認済みにする (確認リンク、または確認を要しない受け付け) */
function ts_takedown_mark_received(int $id, int $now, int $by): void {
    foreach (ts_takedown_confirm_times($now, ts_takedown_params()) as $k => $v) update_post_meta($id, "_tr_$k", $v);
    ts_takedown_set_status($id, 'received', $by, '', false);
    $r = ts_takedown_get($id);
    ts_takedown_mail_committee('confirmed', $r);
}

// ====================================================================== メール (S.129)

function ts_takedown_site(): string {
    return trim(preg_replace('/[\x00-\x1F\x7F<>"]/', '', wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES)));
}

/** 運営委員全員 + admin_email (S.129)。S.135 の通知先の定数があればそれだけ */
function ts_takedown_committee_emails(): array {
    $p = ts_takedown_params();
    if (trim($p['notify_to']) !== '') {
        $to = array_map('trim', explode(',', $p['notify_to']));
    } else {
        $to = [(string) get_option('admin_email')];
        foreach (get_users(['role__in' => ['ts_committee', 'ts_committee_editor'], 'fields' => ['user_email']]) as $u) {
            $to[] = (string) $u->user_email;
        }
    }
    return array_values(array_unique(array_filter($to, 'is_email')));
}

function ts_takedown_send(array $to, array $mail): void {
    [$subject, $body] = $mail;
    if (!$to || $subject === '') return;
    $from = (string) get_option('admin_email');
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    if (is_email($from)) $headers[] = 'From: ' . ts_takedown_site() . ' <' . $from . '>';
    foreach ($to as $addr) wp_mail($addr, $subject, $body, $headers);
}

function ts_takedown_mail_context(array $r): array {
    return ['site' => ts_takedown_site(), 'id' => $r['id'],
            'when' => $r['auto_apply_at'] ? wp_date('Y-m-d H:i', $r['auto_apply_at']) : '',
            'admin_url' => admin_url('post.php?post=' . $r['id'] . '&action=edit'),
            'summary' => ts_takedown_summary($r)];
}

function ts_takedown_mail_committee(string $kind, array $r, array $extra = []): void {
    ts_takedown_send(ts_takedown_committee_emails(), ts_takedown_mail_text($kind, array_merge(ts_takedown_mail_context($r), $extra)));
}

function ts_takedown_mail_requester(string $kind, array $r, array $extra = []): void {
    if (!is_email($r['contact'])) return;
    ts_takedown_send([$r['contact']], ts_takedown_mail_text($kind, array_merge(['site' => ts_takedown_site()], $extra)));
}

// ====================================================================== フォーム (S.124)

add_shortcode('ts_takedown_form', function () {
    $key = wp_salt('nonce');
    $h = 'esc_attr';
    $f = '<form class="ts-takedown-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
        . '<input type="hidden" name="action" value="ts_takedown">'
        . wp_nonce_field('ts_takedown', '_wpnonce', false, false)
        . '<input type="hidden" name="t" value="' . $h(ts_takedown_sign_time(time(), $key)) . '">'
        . '<p class="ts-tr-hp" aria-hidden="true"><label>この欄は空のままにしてください '
        . '<input type="text" name="hp" value="" tabindex="-1" autocomplete="off"></label></p>'
        . '<p><label for="ts-tr-pen">当時のペンネーム (必須)</label><br>'
        . '<input type="text" id="ts-tr-pen" name="pen_name" required maxlength="100"></p>'
        . '<p><label for="ts-tr-targets">対象作品の URL (必須。1 行に 1 つ、20 行まで)</label><br>'
        . '<textarea id="ts-tr-targets" name="targets" rows="4" required placeholder="' . $h(home_url('/novel/')) . '…"></textarea></p>'
        . '<p><label for="ts-tr-contact">連絡先のメールアドレス (必須)</label><br>'
        . '<input type="email" id="ts-tr-contact" name="contact" required maxlength="254"></p>'
        . '<fieldset><legend>お立場 (必須)</legend>'
        . '<label><input type="radio" name="relation" value="author" required> 作者本人</label> '
        . '<label><input type="radio" name="relation" value="heir"> 権利を継承した方</label> '
        . '<label><input type="radio" name="relation" value="other"> その他</label></fieldset>'
        . '<p><label for="ts-tr-evidence">本人確認の手がかり (任意。1,000 字まで)</label><br>'
        . '<textarea id="ts-tr-evidence" name="evidence" rows="3" maxlength="1000"></textarea></p>'
        . '<p><label for="ts-tr-message">ご連絡事項 (任意。2,000 字まで)</label><br>'
        . '<textarea id="ts-tr-message" name="message" rows="4" maxlength="2000"></textarea></p>'
        . '<p><label><input type="checkbox" name="allow_name" value="1"> 掲載していない作品の一覧に、作品名とペンネームを載せてかまいません</label></p>'
        . '<p><button type="submit">送信する</button></p></form>'
        . '<style>.ts-tr-hp{position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden}</style>';
    return $f;
});

/** フォームや一覧を置いたページは前段のキャッシュに載せない (nonce と表示時刻が古くならないように) */
add_action('template_redirect', function () {
    if (!is_singular()) return;
    $post = get_queried_object();
    if ($post instanceof WP_Post
        && (has_shortcode($post->post_content, 'ts_takedown_form') || has_shortcode($post->post_content, 'ts_removed_list'))) {
        nocache_headers();
    }
}, 5);

/** 応答のページ (WordPress の簡素な画面で、状態コードつき) */
function ts_takedown_respond(int $code, string $title, string $html): void {
    nocache_headers();
    wp_die('<h1>' . esc_html($title) . '</h1>' . $html
        . '<p><a href="' . esc_url(home_url('/')) . '">トップへ戻る</a></p>',
        esc_html($title), ['response' => $code, 'back_link' => false]);
}

/** レート制限のキー (送信元 IP の SHA-256、salt = AUTH_SALT。IP そのものは保存しない) */
function ts_takedown_ip_hash(): string {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return hash('sha256', (defined('AUTH_SALT') ? AUTH_SALT : wp_salt('auth')) . '|' . $ip);
}

function ts_takedown_handle_post(): void {
    $p = ts_takedown_params();
    $in = wp_unslash($_POST);
    $nonce_ok = is_string($in['_wpnonce'] ?? null) && wp_verify_nonce($in['_wpnonce'], 'ts_takedown');
    $t = ts_takedown_verify_time(is_string($in['t'] ?? null) ? $in['t'] : '', wp_salt('nonce'));
    $ip_hash = ts_takedown_ip_hash();
    $rl_key = 'ts_tr_rl_' . substr($ip_hash, 0, 40);
    $rl = get_transient($rl_key);
    $recent = is_array($rl) ? (int) ($rl['n'] ?? 0) : 0;
    $code = ts_takedown_gate($nonce_ok, is_string($in['hp'] ?? null) ? $in['hp'] : 'x', $t, time(), $recent, $p);
    if ($code === 403) ts_takedown_respond(403, '送信できませんでした', '<p>ページの有効期限が切れました。取り下げのページを開き直して、もう一度送信してください。</p>');
    if ($code === 400) ts_takedown_respond(400, '送信できませんでした', '<p>送信を受け付けられませんでした。取り下げのページを開き直して、もう一度送信してください。</p>');
    if ($code === 429) ts_takedown_respond(429, '送信できませんでした', '<p>短い時間に続けて送信されたため、受け付けを止めています。時間をおいてお試しください。</p>');

    $home_host = (string) parse_url(home_url('/'), PHP_URL_HOST);
    $v = ts_takedown_validate($in, 'ts_takedown_resolve', $home_host);
    if ($v['errors']) {
        ts_takedown_respond(400, '入力を確かめてください',
            '<ul>' . implode('', array_map(function ($e) { return '<li>' . esc_html($e) . '</li>'; }, $v['errors']))
            . '</ul><p>ブラウザの「戻る」で入力に戻れます。</p>');
    }
    $id = ts_takedown_store($v['data'], 'takedown_form');
    if (!$id) ts_takedown_respond(500, '送信できませんでした', '<p>受け付けの処理に失敗しました。時間をおいてお試しください。</p>');
    update_post_meta($id, '_tr_ip_hash', $ip_hash);
    update_post_meta($id, '_tr_ua', wp_slash(substr(ts_takedown_clean((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 300)));
    $start = is_array($rl) ? (int) ($rl['start'] ?? time()) : time();
    set_transient($rl_key, ['n' => $recent + 1, 'start' => $start], max(60, $start + $p['rate_window'] - time()));

    $r = ts_takedown_get($id);
    if ($r['status'] === 'unconfirmed') {
        $token = bin2hex(random_bytes(32));
        update_post_meta($id, '_tr_token', wp_hash($token));
        ts_takedown_mail_requester('receipt', $r, ['confirm_url' => add_query_arg('ts_confirm', $token, home_url('/')),
                                                    'ttl_days' => $p['confirm_ttl_days']]);
        ts_takedown_respond(200, '確認メールを送りました',
            '<p>お書きいただいたアドレスに確認メールを送りました。メールのリンクを開くと、お申し出の受け付けが完了します。</p>'
            . '<p>リンクの有効期限は ' . (int) $p['confirm_ttl_days'] . ' 日です。</p>');
    }
    ts_takedown_mail_requester('receipt', $r);
    ts_takedown_respond(200, 'お申し出を受け付けました', '<p>お申し出を受け付けました。</p>');
}
add_action('admin_post_nopriv_ts_takedown', 'ts_takedown_handle_post');
add_action('admin_post_ts_takedown', 'ts_takedown_handle_post');

// ====================================================================== 確認リンク (S.126)

add_action('template_redirect', function () {
    if (!isset($_GET['ts_confirm'])) return;
    $token = is_string($_GET['ts_confirm']) ? $_GET['ts_confirm'] : '';
    $bad = function () {
        ts_takedown_respond(404, 'リンクを確かめてください', '<p>このリンクは正しくないか、すでに使えなくなっています。</p>');
    };
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) $bad();
    $ids = get_posts(['post_type' => TS_TAKEDOWN_TYPE, 'post_status' => 'any', 'posts_per_page' => 1,
                      'fields' => 'ids', 'no_found_rows' => true,
                      'meta_key' => '_tr_token', 'meta_value' => wp_hash($token)]);
    if (!$ids) $bad();
    $r = ts_takedown_get((int) $ids[0]);
    $p = ts_takedown_params();
    if ($r['status'] === 'unconfirmed') {
        if (time() >= $r['received_at'] + $p['confirm_ttl_days'] * 86400) {
            ts_takedown_respond(410, 'リンクの有効期限が切れています', '<p>お手数ですが、取り下げのページからもう一度お申し出ください。</p>');
        }
        ts_takedown_mark_received($r['id'], time(), 0);
        ts_takedown_respond(200, 'お申し出を受け付けました', '<p>確認できました。お申し出を受け付けました。</p>');
    }
    if ($r['status'] === 'expired') {
        ts_takedown_respond(410, 'リンクの有効期限が切れています', '<p>お手数ですが、取り下げのページからもう一度お申し出ください。</p>');
    }
    ts_takedown_respond(200, 'お申し出は受け付け済みです', '<p>このお申し出はすでに受け付けています。</p>');
}, 1);

// ====================================================================== 非公開化と復帰 (S.127・S.128)

/** 申請の対象 (解決できたもの) とその子孫の投稿 ID */
function ts_takedown_target_posts(array $r): array {
    $out = [];
    foreach ($r['targets'] as $t) {
        if (empty($t['post_id']) || get_post_type((int) $t['post_id']) !== 'ts_work') continue;
        $stack = [(int) $t['post_id']];
        while ($stack) {
            $pid = array_pop($stack);
            if (isset($out[$pid])) continue;
            $out[$pid] = true;
            foreach (get_posts(['post_type' => 'ts_work', 'post_parent' => $pid, 'post_status' => 'any',
                                'posts_per_page' => -1, 'fields' => 'ids']) as $cid) $stack[] = (int) $cid;
        }
    }
    return array_keys($out);
}

/** 投稿の取り下げのメタの今の値 (ts_visibility_takedown_change に渡す形) */
function ts_takedown_post_state(int $pid): array {
    return ['status' => (string) get_post_status($pid),
            'visibility' => (string) get_post_meta($pid, '_ts_visibility', true),
            'takedown_id' => (string) get_post_meta($pid, '_ts_takedown_id', true),
            'public_note' => (string) get_post_meta($pid, '_ts_public_note', true),
            'takedown_date' => (string) get_post_meta($pid, '_ts_takedown_date', true),
            'superseded_by' => (string) get_post_meta($pid, '_ts_superseded_by', true)];
}

/** 1 投稿に変化を当てる (apply-takedown と同じく、取り下げを手動編集と誤検知させない) */
function ts_takedown_apply_change(int $pid, array $ch): void {
    if ($ch['status'] !== null) {
        wp_update_post(['ID' => $pid, 'post_status' => $ch['status']]);
        clean_post_cache($pid);
        if (get_post_meta($pid, '_ts_last_import_gmt', true) !== '') {
            update_post_meta($pid, '_ts_last_import_gmt', get_post_field('post_modified_gmt', $pid));
        }
    }
    foreach ($ch['set'] as $k => $v) update_post_meta($pid, $k, wp_slash($v));
    foreach ($ch['delete'] as $k) delete_post_meta($pid, $k);
}

/** 公開時の URL (private のまま get_permalink すると仮 URL になる) */
function ts_takedown_public_url(int $pid): string {
    $post = get_post($pid);
    if (!$post) return '';
    $saved = $post->post_status;
    $post->post_status = 'publish';
    $url = (string) get_permalink($post);
    $post->post_status = $saved;
    return wp_make_link_relative($url);
}

/**
 * 非公開化 (S.127)。$new_status は applied (cron) か approved (運営委員)。
 * 既に別の記録で取り下げ中の投稿は触らない。返り値: 非公開にした作品の題名
 */
function ts_takedown_apply(int $id, string $new_status, int $by, string $note): array {
    $r = ts_takedown_get($id);
    $tid = 'req:' . $id;
    $roots = [];
    foreach ($r['targets'] as $t) if (!empty($t['post_id'])) $roots[(int) $t['post_id']] = true;
    $titles = [];
    foreach (array_keys($roots) as $pid) {
        if (get_post_type($pid) === 'ts_work') $titles[] = wp_specialchars_decode(get_the_title($pid), ENT_QUOTES);
    }
    $at = $r['confirmed_at'] ?: time();
    $public_note = ts_takedown_public_note($titles, $r['pen_name'], $r['allow_name'] === '1',
        (int) wp_date('Y', $at), (int) wp_date('n', $at));
    $want = ['id' => $tid, 'note' => $public_note, 'date' => wp_date('Y-m-d', $at)];
    $urls = (array) get_option('ts_takedown_urls', []);
    $done = [];
    foreach (ts_takedown_target_posts($r) as $pid) {
        $cur = ts_takedown_post_state($pid);
        if ($cur['visibility'] === 'withdrawn' && $cur['takedown_id'] !== $tid) continue; // 既に別の記録で取り下げ中
        ts_takedown_apply_change($pid, ts_visibility_takedown_change($cur, $want));
        $urls[] = ts_takedown_public_url($pid);
        if (isset($roots[$pid])) $done[] = wp_specialchars_decode(get_the_title($pid), ENT_QUOTES);
    }
    update_option('ts_takedown_urls', array_values(array_unique(array_filter($urls))), false);
    ts_takedown_set_status($id, $new_status, $by, $note, true);
    $unresolved = [];
    foreach ($r['targets'] as $t) if (empty($t['post_id'])) $unresolved[] = $t['url'];
    ts_takedown_mail_requester('applied_requester', $r, ['titles' => $done, 'listed' => $r['allow_name'] === '1']);
    ts_takedown_mail_committee('applied_committee', $r, ['titles' => $done, 'unresolved' => $unresolved]);
    return $done;
}

/**
 * 復帰 (S.128)。この申請で取り下げた投稿だけを戻す。返り値: 戻せなかった投稿の数
 * (denylist に書き写した後の取り下げは、denylist を reinstated にして apply-takedown で戻す。S.132)
 */
function ts_takedown_reinstate(int $id, int $by, string $note): int {
    $r = ts_takedown_get($id);
    $tid = 'req:' . $id;
    $urls = (array) get_option('ts_takedown_urls', []);
    $left = 0;
    foreach (ts_takedown_target_posts($r) as $pid) {
        $cur = ts_takedown_post_state($pid);
        if ($cur['takedown_id'] !== $tid) {
            if ($cur['visibility'] === 'withdrawn') $left++;
            continue;
        }
        ts_takedown_apply_change($pid, ts_visibility_takedown_change($cur, null));
        $urls = array_diff($urls, [ts_takedown_public_url($pid)]);
    }
    update_option('ts_takedown_urls', array_values($urls), false);
    ts_takedown_set_status($id, 'reinstated', $by, $note, true);
    return $left;
}

// ====================================================================== cron (S.130)

add_action('init', function () {
    if (!wp_next_scheduled('ts_takedown_tick')) wp_schedule_event(time() + 300, 'hourly', 'ts_takedown_tick');
}, 20);

/** 処理の順は S.126 どおり: 失効 → リマインド → 自動適用。その後に保持期限の消去 (S.125) */
function ts_takedown_tick(): void {
    $p = ts_takedown_params();
    $n = ['expire' => 0, 'remind' => 0, 'apply' => 0, 'purge' => 0];
    try {
        $ids = get_posts(['post_type' => TS_TAKEDOWN_TYPE, 'post_status' => 'any', 'posts_per_page' => -1,
                          'fields' => 'ids', 'no_found_rows' => true]);
        $todo = ['expire' => [], 'remind' => [], 'apply' => []];
        $now = time();
        foreach ($ids as $id) {
            $r = ts_takedown_get((int) $id);
            $due = ts_takedown_due($r['status'], $r, $now, $p);
            if ($due !== null) $todo[$due][] = $r;
        }
        foreach ($todo['expire'] as $r) {
            delete_post_meta($r['id'], '_tr_token');
            ts_takedown_set_status($r['id'], 'expired', 0, '', true);
            $n['expire']++;
        }
        foreach ($todo['remind'] as $r) {
            update_post_meta($r['id'], '_tr_reminded_at', $now);
            ts_takedown_mail_committee('remind', $r);
            $n['remind']++;
        }
        foreach ($todo['apply'] as $r) {
            ts_takedown_apply($r['id'], 'applied', 0, '');
            $n['apply']++;
        }
        foreach ($ids as $id) {
            $r = ts_takedown_get((int) $id);
            if (!ts_takedown_purge_due($r['status'], $r['resolved_at'], $now, $p)) continue;
            if (get_post_meta($r['id'], '_tr_contact', true) === '' && get_post_meta($r['id'], '_tr_ip_hash', true) === '') continue;
            foreach (['_tr_contact', '_tr_evidence', '_tr_message', '_tr_ip_hash', '_tr_ua'] as $k) delete_post_meta($r['id'], $k);
            $n['purge']++;
        }
        update_option('ts_takedown_last_tick', ['time' => time(), 'error' => '', 'counts' => $n], false);
    } catch (Throwable $e) {
        update_option('ts_takedown_last_tick', ['time' => time(), 'error' => $e->getMessage(), 'counts' => $n], false);
        throw $e;
    }
}
add_action('ts_takedown_tick', 'ts_takedown_tick');

// ====================================================================== 掲載していない作品の一覧 (S.131)

add_shortcode('ts_removed_list', function () {
    $q = get_posts(['post_type' => 'ts_work', 'post_status' => 'private', 'posts_per_page' => -1,
                    'meta_key' => '_ts_visibility', 'meta_value' => 'withdrawn', 'no_found_rows' => true]);
    $posts = [];
    foreach ($q as $post) {
        $posts[] = ['id' => (string) get_post_meta($post->ID, '_ts_takedown_id', true),
                    'note' => (string) get_post_meta($post->ID, '_ts_public_note', true),
                    'date' => (string) get_post_meta($post->ID, '_ts_takedown_date', true)];
    }
    $rows = ts_takedown_removed_rows($posts);
    if (!$rows) return '<p class="ts-removed-none">いま掲載していない作品はありません。</p>';
    return '<ul class="ts-removed-list">'
        . implode('', array_map(function ($r) { return '<li>' . esc_html($r['note']) . '</li>'; }, $rows))
        . '</ul>';
});
