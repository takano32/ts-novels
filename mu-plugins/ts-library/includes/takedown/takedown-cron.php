<?php
/**
 * 取り下げの申請の cron ts_takedown_tick (specs/S130.md)。WordPress に触る側。
 *
 * 処理の順は S126 どおり: 失効 → リマインド → 自動適用。その後に保持期限の消去 (S125)。
 * 1 件ごとの判定は TS_Takedown_Rules::due / purge_due。最終実行は option ts_takedown_last_tick に残し、
 * `wp ts verify` が止まっていないかを見る。
 */

if (!defined('ABSPATH')) { exit; }

add_action('init', function () {
    if (!wp_next_scheduled('ts_takedown_tick')) wp_schedule_event(time() + 300, 'hourly', 'ts_takedown_tick');
}, 20);

function ts_takedown_tick(): void {
    $p = TS_Takedown_Rules::params();
    $n = ['expire' => 0, 'remind' => 0, 'apply' => 0, 'purge' => 0];
    try {
        $ids = get_posts(['post_type' => TS_TAKEDOWN_TYPE, 'post_status' => 'any', 'posts_per_page' => -1,
                          'fields' => 'ids', 'no_found_rows' => true]);
        $todo = ['expire' => [], 'remind' => [], 'apply' => []];
        $now = time();
        foreach ($ids as $id) {
            $r = ts_takedown_get((int) $id);
            $due = TS_Takedown_Rules::due($r['status'], $r, $now, $p);
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
            if (!TS_Takedown_Rules::purge_due($r['status'], $r['resolved_at'], $now, $p)) continue;
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
