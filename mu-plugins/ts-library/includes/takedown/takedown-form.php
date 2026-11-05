<?php
/**
 * 取り下げフォーム [ts_takedown_form] と確認リンク (specs/S124.md・S126.md)。WordPress に触る側。
 *
 *  - フォーム → admin-post.php?action=ts_takedown。入力の検証は TS_Takedown_Input::validate、
 *    スパム対策の判定は TS_Takedown_Rules::gate (nonce → honeypot → 3 秒 → レート制限)
 *  - 確認リンク ?ts_confirm=<token> (S126)。token はハッシュして保存する
 *  - フォームや一覧を置いたページは前段のキャッシュに載せない
 */

if (!defined('ABSPATH')) { exit; }

// ====================================================================== フォーム (S124)

add_shortcode('ts_takedown_form', function () {
    $key = wp_salt('nonce');
    $h = 'esc_attr';
    $f = '<form class="ts-takedown-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
        . '<input type="hidden" name="action" value="ts_takedown">'
        . wp_nonce_field('ts_takedown', '_wpnonce', false, false)
        . '<input type="hidden" name="t" value="' . $h(TS_Takedown_Input::sign_time(time(), $key)) . '">'
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
    $p = TS_Takedown_Rules::params();
    $in = wp_unslash($_POST);
    $nonce_ok = is_string($in['_wpnonce'] ?? null) && wp_verify_nonce($in['_wpnonce'], 'ts_takedown');
    $t = TS_Takedown_Input::verify_time(is_string($in['t'] ?? null) ? $in['t'] : '', wp_salt('nonce'));
    $ip_hash = ts_takedown_ip_hash();
    $rl_key = 'ts_tr_rl_' . substr($ip_hash, 0, 40);
    $rl = get_transient($rl_key);
    $recent = is_array($rl) ? (int) ($rl['n'] ?? 0) : 0;
    $code = TS_Takedown_Rules::gate($nonce_ok, is_string($in['hp'] ?? null) ? $in['hp'] : 'x', $t, time(), $recent, $p);
    if ($code === 403) ts_takedown_respond(403, ...TS_Takedown_Text::respond('forbidden', []));
    if ($code === 400) ts_takedown_respond(400, ...TS_Takedown_Text::respond('rejected', []));
    if ($code === 429) ts_takedown_respond(429, ...TS_Takedown_Text::respond('rate_limited', []));

    $home_host = (string) parse_url(home_url('/'), PHP_URL_HOST);
    $v = TS_Takedown_Input::validate($in, 'ts_takedown_resolve', $home_host, 'is_email');
    if ($v['errors']) {
        ts_takedown_respond(400, ...TS_Takedown_Text::respond('invalid', ['items' => array_map('esc_html', $v['errors'])]));
    }
    $id = ts_takedown_store($v['data'], 'takedown_form');
    if (!$id) ts_takedown_respond(500, ...TS_Takedown_Text::respond('failed', []));
    update_post_meta($id, '_tr_ip_hash', $ip_hash);
    update_post_meta($id, '_tr_ua', wp_slash(substr(TS_Takedown_Input::clean((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 300)));
    $start = is_array($rl) ? (int) ($rl['start'] ?? time()) : time();
    set_transient($rl_key, ['n' => $recent + 1, 'start' => $start], TS_Takedown_Rules::rate_ttl($start, time(), $p));

    $r = ts_takedown_get($id);
    if ($r['status'] === 'unconfirmed') {
        $token = bin2hex(random_bytes(32));
        update_post_meta($id, '_tr_token', wp_hash($token));
        ts_takedown_mail_requester('receipt', $r, ['confirm_url' => add_query_arg('ts_confirm', $token, home_url('/')),
                                                    'ttl_days' => $p['confirm_ttl_days']]);
        ts_takedown_respond(200, ...TS_Takedown_Text::respond('confirm_sent', ['ttl_days' => $p['confirm_ttl_days']]));
    }
    ts_takedown_mail_requester('receipt', $r);
    ts_takedown_respond(200, ...TS_Takedown_Text::respond('received', []));
}
add_action('admin_post_nopriv_ts_takedown', 'ts_takedown_handle_post');
add_action('admin_post_ts_takedown', 'ts_takedown_handle_post');

// ====================================================================== 確認リンク (S126)

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
    $p = TS_Takedown_Rules::params();
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
