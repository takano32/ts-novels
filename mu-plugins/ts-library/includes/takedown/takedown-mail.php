<?php
/**
 * 取り下げフォームのメールの送信 (specs/S129.md)。WordPress に触る側。
 *
 * 件名と本文は includes/takedown/takedown-text.php (TS_Takedown_Text::mail_text) が作る。ここは宛先
 * (運営委員全員 + admin_email、または S135 の通知先の定数) と wp_mail だけ。
 */

if (!defined('ABSPATH')) { exit; }

/** サイト名 (From と件名に使う。制御文字と <>" は除く) */
function ts_takedown_site(): string {
    return trim(preg_replace('/[\x00-\x1F\x7F<>"]/', '', wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES)));
}

/** 運営委員全員 + admin_email (S129)。S135 の通知先の定数があればそれだけ */
function ts_takedown_committee_emails(): array {
    $p = TS_Takedown_Rules::params();
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
            'summary' => TS_Takedown_Text::summary($r)];
}

function ts_takedown_mail_committee(string $kind, array $r, array $extra = []): void {
    ts_takedown_send(ts_takedown_committee_emails(), TS_Takedown_Text::mail_text($kind, array_merge(ts_takedown_mail_context($r), $extra)));
}

function ts_takedown_mail_requester(string $kind, array $r, array $extra = []): void {
    if (!is_email($r['contact'])) return;
    ts_takedown_send([$r['contact']], TS_Takedown_Text::mail_text($kind, array_merge(['site' => ts_takedown_site()], $extra)));
}
