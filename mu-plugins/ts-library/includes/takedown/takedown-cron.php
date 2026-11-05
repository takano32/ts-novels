<?php
/**
 * 取り下げの申請の cron ts_takedown_tick (specs/S130.md)。WordPress に触る側。
 *
 * 処理の順は S126 どおり: 失効 → リマインド → 自動適用。その後に保持期限の消去 (S125)。
 * 仕分けは TS_Takedown_Rules::tick_plan / purge_plan (1 件ごとの判定は due / purge_due)。最終実行は option ts_takedown_last_tick に残し、
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
        $now = time();
        $todo = TS_Takedown_Rules::tick_plan(array_map(function ($id) { return ts_takedown_get((int) $id); }, $ids), $now, $p);
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
        // 保持期限の消去は、失効・適用で変わった後の申請を読み直して決める
        $rows = array_map(function ($id) {
            $r = ts_takedown_get((int) $id);
            return ['id' => $r['id'], 'status' => $r['status'], 'resolved_at' => $r['resolved_at'],
                    'contact' => get_post_meta($r['id'], '_tr_contact', true), 'ip_hash' => get_post_meta($r['id'], '_tr_ip_hash', true)];
        }, $ids);
        foreach (TS_Takedown_Rules::purge_plan($rows, $now, $p) as $id) {
            foreach (['_tr_contact', '_tr_evidence', '_tr_message', '_tr_ip_hash', '_tr_ua'] as $k) delete_post_meta($id, $k);
            $n['purge']++;
        }
        update_option('ts_takedown_last_tick', ['time' => time(), 'error' => '', 'counts' => $n], false);
    } catch (Throwable $e) {
        update_option('ts_takedown_last_tick', ['time' => time(), 'error' => $e->getMessage(), 'counts' => $n], false);
        throw $e;
    }
}
add_action('ts_takedown_tick', 'ts_takedown_tick');
