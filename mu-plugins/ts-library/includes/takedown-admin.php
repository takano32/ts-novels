<?php
/**
 * 取り下げの申請の管理画面 (specs/S.128.md、T.26.3.5)。
 *
 *  - 申請の編集画面のメタボックス: 申請の内容と、運営委員の操作 4 つ
 *    (承認して即時適用 / 却下 / 復帰 / 期限を変える)。どの操作も ts_manage_takedown と nonce を確かめ、
 *    S.126 の遷移に無い組み合わせは何もしない。誰が・いつ・メモを残す (ts_takedown_set_status)
 *  - メールで届いた申し出の起票 (S.124): 運営委員が「申請を起票」で入力し、確認済み (received) として
 *    同じ状態機械に乗せる
 *  - 一覧の列: 状態・受付・自動適用の時刻・本サイトの作品に結びつかない URL の有無
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH')) { exit; }

add_action('add_meta_boxes_' . TS_TAKEDOWN_TYPE, function ($post) {
    add_meta_box('ts_takedown_box', '取り下げの申請', 'ts_takedown_admin_box', TS_TAKEDOWN_TYPE, 'normal', 'high');
});

/** 操作の結果の知らせ (リダイレクト後に 1 度だけ出す) */
add_action('admin_notices', function () {
    $key = 'ts_tr_notice_' . get_current_user_id();
    $msg = get_transient($key);
    if (!is_array($msg)) return;
    delete_transient($key);
    printf('<div class="notice notice-%s"><p>%s</p></div>', esc_attr($msg[0]), esc_html($msg[1]));
});

function ts_takedown_admin_notice(string $type, string $text): void {
    set_transient('ts_tr_notice_' . get_current_user_id(), [$type, $text], 120);
}

function ts_takedown_admin_box(WP_Post $post): void {
    if (!current_user_can('ts_manage_takedown')) return;
    wp_nonce_field('ts_tr_admin_' . $post->ID, 'ts_tr_nonce');
    $r = ts_takedown_get($post->ID);
    $e = 'esc_html';
    if ($r['status'] === '') { ts_takedown_admin_new_fields(); return; }

    $labels = ts_takedown_status_labels();
    $rel = ['author' => '作者本人', 'heir' => '権利継承者', 'other' => 'その他'];
    $t = function ($ts) { return $ts ? wp_date('Y-m-d H:i', $ts) : '—'; };
    echo '<table class="form-table" role="presentation">';
    $row = function ($k, $v) { echo '<tr><th scope="row">' . esc_html($k) . '</th><td>' . $v . '</td></tr>'; };
    $row('状態', '<strong>' . $e($labels[$r['status']] ?? $r['status']) . '</strong>');
    $row('受け付けた経路', $r['via'] === 'email' ? 'メール (運営委員が起票)' : '取り下げフォーム');
    $row('受付', $e($t($r['received_at'])));
    $row('確認', $e($t($r['confirmed_at'])));
    $row('自動適用', $e($t($r['auto_apply_at'])));
    $row('ペンネーム', $e($r['pen_name']));
    $row('立場', $e($rel[$r['relation']] ?? $r['relation']));
    $row('名前の掲示', $r['allow_name'] === '1' ? '可' : '不可');
    $row('連絡先', $r['contact'] !== '' ? $e($r['contact']) : '(保持期間を過ぎて消去済み)');
    $items = '';
    foreach ($r['targets'] as $tg) {
        $line = $e($tg['url']);
        if (!empty($tg['post_id']) && get_post((int) $tg['post_id'])) {
            $pid = (int) $tg['post_id'];
            $line .= ' → <a href="' . esc_url(get_permalink($pid)) . '">' . $e(get_the_title($pid)) . '</a> ('
                . $e((string) get_post_status($pid)) . ')';
        } else {
            $line .= ' <strong>本サイトの作品に結びつかない</strong>';
        }
        $items .= '<li>' . $line . '</li>';
    }
    $row('対象', '<ul style="margin:0">' . $items . '</ul>');
    $row('本人確認の手がかり', nl2br($e($r['evidence'])));
    $row('ご連絡事項', nl2br($e($r['message'])));
    $log = '';
    foreach (get_post_meta($post->ID, '_tr_log') as $j) {
        $l = json_decode((string) $j, true);
        if (!is_array($l)) continue;
        $u = !empty($l['by']) ? get_userdata((int) $l['by']) : null;
        $log .= '<li>' . $e($t((int) $l['at'])) . ' ' . $e($labels[$l['what']] ?? $l['what']) . ' — '
            . $e($u ? $u->display_name : '自動') . ($l['note'] !== '' ? ': ' . $e($l['note']) : '') . '</li>';
    }
    $row('記録', '<ul style="margin:0">' . $log . '</ul>');
    echo '</table>';

    $ops = [];
    foreach (['approve' => '承認して即時適用', 'reject' => '却下', 'reinstate' => '復帰', 'deadline' => '期限を変える'] as $op => $label) {
        if (ts_takedown_op_next($r['status'], $op) !== null) $ops[$op] = $label;
    }
    if (!$ops) return;
    echo '<h3>操作</h3><p><label>内部メモ (却下の理由など。依頼者には送らない)<br>'
        . '<textarea name="ts_tr_note" rows="2" class="large-text"></textarea></label></p>';
    if (isset($ops['deadline'])) {
        $v = $r['auto_apply_at'] ? wp_date('Y-m-d\TH:i', $r['auto_apply_at']) : '';
        echo '<p><label>新しい自動適用の時刻 <input type="datetime-local" name="ts_tr_deadline" value="' . esc_attr($v) . '"></label></p>';
    }
    echo '<p>';
    foreach ($ops as $op => $label) {
        printf('<button type="submit" name="ts_tr_op" value="%s" class="button%s">%s</button> ',
            esc_attr($op), $op === 'approve' ? ' button-primary' : '', esc_html($label));
    }
    echo '</p>';
}

/** メールで届いた申し出を起票する欄 (S.124。項目はフォームと同じ) */
function ts_takedown_admin_new_fields(): void {
    echo '<p>メールで届いた取り下げの申し出を起票します。保存すると受付済み (自動適用待ち) になり、運営委員に確認完了のメールが届きます。</p>'
        . '<table class="form-table" role="presentation">'
        . '<tr><th><label for="ts-tr-pen">当時のペンネーム</label></th><td><input type="text" id="ts-tr-pen" name="ts_tr[pen_name]" maxlength="100" class="regular-text" required></td></tr>'
        . '<tr><th><label for="ts-tr-targets">対象作品の URL (1 行に 1 つ)</label></th><td><textarea id="ts-tr-targets" name="ts_tr[targets]" rows="4" class="large-text" required></textarea></td></tr>'
        . '<tr><th><label for="ts-tr-contact">連絡先のメールアドレス</label></th><td><input type="email" id="ts-tr-contact" name="ts_tr[contact]" class="regular-text" required></td></tr>'
        . '<tr><th>立場</th><td><label><input type="radio" name="ts_tr[relation]" value="author" required> 作者本人</label> '
        . '<label><input type="radio" name="ts_tr[relation]" value="heir"> 権利継承者</label> '
        . '<label><input type="radio" name="ts_tr[relation]" value="other"> その他</label></td></tr>'
        . '<tr><th><label for="ts-tr-evidence">本人確認の手がかり</label></th><td><textarea id="ts-tr-evidence" name="ts_tr[evidence]" rows="3" class="large-text"></textarea></td></tr>'
        . '<tr><th><label for="ts-tr-message">自由記述</label></th><td><textarea id="ts-tr-message" name="ts_tr[message]" rows="3" class="large-text"></textarea></td></tr>'
        . '<tr><th>名前の掲示</th><td><label><input type="checkbox" name="ts_tr[allow_name]" value="1"> 掲載していない作品の一覧に作品名とペンネームを出してよい</label></td></tr>'
        . '</table>';
}

/**
 * 運営委員の操作 (S.128) の本体。管理画面のほか WP-CLI (wp eval) からも呼べる。
 * $deadline は操作が deadline のときの新しい時刻 (Unix 秒)。返り値: [成否, 知らせの文]
 */
function ts_takedown_operate(int $id, string $op, int $by, string $note, int $deadline = 0): array {
    if (!user_can($by, 'ts_manage_takedown')) return [false, '権限がありません。'];
    $r = ts_takedown_get($id);
    $next = ts_takedown_op_next($r['status'], $op);
    if ($next === null) return [false, 'いまの状態ではこの操作はできません。'];
    $note = ts_takedown_clean($note, true);
    switch ($op) {
        case 'approve':
            $done = ts_takedown_apply($id, 'approved', $by, $note);
            return [true, '承認して非公開にしました (' . count($done) . ' 作品)。'];
        case 'reject':
            delete_post_meta($id, '_tr_token');
            ts_takedown_set_status($id, 'rejected', $by, $note, true);
            if (ts_takedown_params()['send_reject_mail']) ts_takedown_mail_requester('rejected', $r);
            return [true, '却下しました。'];
        case 'reinstate':
            $left = ts_takedown_reinstate($id, $by, $note);
            return [true, '復帰しました。' . ($left
                ? " ただし {$left} 件の投稿は、サイトの取り下げの記録 (denylist) に書き写した後のため、この画面では戻していません。管理者に依頼してください。"
                : '')];
        case 'deadline':
            if ($deadline <= 0) return [false, '新しい自動適用の時刻を入れてください。'];
            update_post_meta($id, '_tr_auto_apply_at', $deadline);
            delete_post_meta($id, '_tr_reminded_at'); // 延ばしたときにリマインドをもう一度送る
            ts_takedown_set_status($id, 'received', $by, '期限を ' . wp_date('Y-m-d H:i', $deadline) . ' に変更'
                . ($note !== '' ? ' — ' . $note : ''), false);
            update_post_meta($id, '_tr_resolved_by', $by);
            update_post_meta($id, '_tr_resolution_note', wp_slash($note));
            return [true, '自動適用の時刻を ' . wp_date('Y-m-d H:i', $deadline) . ' にしました。'];
    }
    return [false, '不明な操作です。'];
}

/** 保存 (起票と操作)。自分で wp_update_post するときは ts_takedown_saving で入り直さない */
add_action('save_post_' . TS_TAKEDOWN_TYPE, function ($post_id, $post) {
    if (!empty($GLOBALS['ts_takedown_saving']) || wp_is_post_revision($post_id) || $post->post_status === 'auto-draft') return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['ts_tr_nonce']) || !wp_verify_nonce(wp_unslash($_POST['ts_tr_nonce']), 'ts_tr_admin_' . $post_id)) return;
    if (!current_user_can('ts_manage_takedown')) return;
    $by = get_current_user_id();

    if ((string) get_post_meta($post_id, '_tr_status', true) === '') {
        $in = is_array($_POST['ts_tr'] ?? null) ? wp_unslash($_POST['ts_tr']) : [];
        $v = ts_takedown_validate($in, 'ts_takedown_resolve', (string) parse_url(home_url('/'), PHP_URL_HOST));
        if ($v['errors']) {
            ts_takedown_admin_notice('error', '起票できませんでした: ' . implode(' ', $v['errors']));
            return;
        }
        ts_takedown_store($v['data'], 'email', (int) $post_id);
        ts_takedown_admin_notice('success', '起票しました。');
        return;
    }

    $op = is_string($_POST['ts_tr_op'] ?? null) ? wp_unslash($_POST['ts_tr_op']) : '';
    if ($op === '') return;
    $deadline = 0;
    if ($op === 'deadline') {
        $raw = is_string($_POST['ts_tr_deadline'] ?? null) ? wp_unslash($_POST['ts_tr_deadline']) : '';
        $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $raw, wp_timezone());
        $deadline = $dt ? $dt->getTimestamp() : 0;
    }
    $note = is_string($_POST['ts_tr_note'] ?? null) ? wp_unslash($_POST['ts_tr_note']) : '';
    $GLOBALS['ts_takedown_saving'] = true;
    [$ok, $msg] = ts_takedown_operate((int) $post_id, $op, $by, $note, $deadline);
    $GLOBALS['ts_takedown_saving'] = false;
    ts_takedown_admin_notice($ok ? 'success' : 'error', $msg);
}, 10, 2);

// ---------------------------------------------------------------- 一覧の列

add_filter('manage_' . TS_TAKEDOWN_TYPE . '_posts_columns', function ($cols) {
    return ['cb' => $cols['cb'] ?? '', 'title' => '申請', 'ts_tr_status' => '状態', 'ts_tr_received' => '受付',
            'ts_tr_apply' => '自動適用', 'ts_tr_warn' => '注意'];
});

add_action('manage_' . TS_TAKEDOWN_TYPE . '_posts_custom_column', function ($col, $post_id) {
    $r = ts_takedown_get((int) $post_id);
    $t = function ($ts) { return $ts ? wp_date('Y-m-d H:i', $ts) : '—'; };
    if ($col === 'ts_tr_status') echo esc_html(ts_takedown_status_labels()[$r['status']] ?? $r['status']);
    if ($col === 'ts_tr_received') echo esc_html($t($r['received_at']));
    if ($col === 'ts_tr_apply') echo esc_html($r['status'] === 'received' ? $t($r['auto_apply_at']) : '—');
    if ($col === 'ts_tr_warn') {
        foreach ($r['targets'] as $tg) {
            if (empty($tg['post_id'])) { echo '本サイトの作品に結びつかない URL あり'; break; }
        }
    }
}, 10, 2);
