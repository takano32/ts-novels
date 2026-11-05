<?php
/**
 * 取り下げの申請の非公開化と復帰 (specs/S127.md・S128.md)。WordPress に触る側。
 *
 * どの投稿に触るかは TS_Takedown_Plan::request_apply / request_reinstate、1 投稿の変化は TS_Visibility_Rules::takedown_change
 * (denylist 由来の `wp ts apply-takedown` と同じ規則)、/removed/ の 1 行は TS_Takedown_Text::public_note。
 * cron (takedown-cron.php) と運営委員の操作 (takedown-admin.php) から呼ぶ。
 */

if (!defined('ABSPATH')) { exit; }

/** 取り下げが波及する子 (ts_work の直接の子。状態を問わない。get_posts の既定の順)。申請の適用と `wp ts apply-takedown` が同じ問い合わせを使う */
function ts_takedown_child_ids(int $parent_id): array {
    return get_posts(['post_type' => 'ts_work', 'post_parent' => $parent_id, 'post_status' => 'any',
                      'posts_per_page' => -1, 'fields' => 'ids']);
}

/** 申請の対象 (解決できたもの) とその子孫の投稿 ID。stack で集めるので子は get_posts の逆順に並ぶ (option ts_takedown_urls の順になる。
 *  `wp ts apply-takedown` の再帰 (TS_Takedown_Command::descendants) とは順が違うが、どちらも分割前のまま。T45.1.19.3) */
function ts_takedown_target_posts(array $r): array {
    $out = [];
    foreach ($r['targets'] as $t) {
        if (empty($t['post_id']) || get_post_type((int) $t['post_id']) !== 'ts_work') continue;
        $stack = [(int) $t['post_id']];
        while ($stack) {
            $pid = array_pop($stack);
            if (isset($out[$pid])) continue;
            $out[$pid] = true;
            foreach (ts_takedown_child_ids($pid) as $cid) $stack[] = (int) $cid;
        }
    }
    return array_keys($out);
}

/** 投稿の取り下げのメタの今の値 (TS_Visibility_Rules::takedown_change に渡す形) */
function ts_takedown_post_state(int $pid): array {
    return ['status' => (string) get_post_status($pid),
            'visibility' => (string) get_post_meta($pid, '_ts_visibility', true),
            'takedown_id' => (string) get_post_meta($pid, '_ts_takedown_id', true),
            'public_note' => (string) get_post_meta($pid, '_ts_public_note', true),
            'takedown_date' => (string) get_post_meta($pid, '_ts_takedown_date', true),
            'superseded_by' => (string) get_post_meta($pid, '_ts_superseded_by', true)];
}

/** 申請の対象と子孫の今の値: post_id => ts_takedown_post_state (TS_Takedown_Plan::request_apply / request_reinstate に渡す形。対象と子孫の順) */
function ts_takedown_target_states(array $r): array {
    $states = [];
    foreach (ts_takedown_target_posts($r) as $pid) $states[$pid] = ts_takedown_post_state($pid);
    return $states;
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

/** 公開時の URL (private のまま get_permalink すると `?post_type=…&p=ID` の仮 URL になり、止めた URL の記録に使えない。実測) */
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
 * 非公開化 (S127)。$new_status は applied (cron) か approved (運営委員)。
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
    $public_note = TS_Takedown_Text::public_note($titles, $r['pen_name'], $r['allow_name'] === '1',
        (int) wp_date('Y', $at), (int) wp_date('n', $at));
    $want = ['id' => $tid, 'note' => $public_note, 'date' => wp_date('Y-m-d', $at)];
    $urls = (array) get_option('ts_takedown_urls', []);
    $done = [];
    $states = ts_takedown_target_states($r);
    foreach (TS_Takedown_Plan::request_apply($states, $tid) as $pid) {   // 既に別の記録で取り下げ中の投稿は触らない
        ts_takedown_apply_change($pid, TS_Visibility_Rules::takedown_change($states[$pid], $want));
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
 * 復帰 (S128)。この申請で取り下げた投稿だけを戻す。返り値: 戻せなかった投稿の数
 * (denylist に書き写した後の取り下げは、denylist を reinstated にして apply-takedown で戻す。S132)
 */
function ts_takedown_reinstate(int $id, int $by, string $note): int {
    $r = ts_takedown_get($id);
    $tid = 'req:' . $id;
    $urls = (array) get_option('ts_takedown_urls', []);
    $states = ts_takedown_target_states($r);
    $plan = TS_Takedown_Plan::request_reinstate($states, $tid);   // この申請の印の投稿だけ。別の記録で取り下げ中のものは left に数える
    foreach ($plan['posts'] as $pid) {
        ts_takedown_apply_change($pid, TS_Visibility_Rules::takedown_change($states[$pid], null));
        $urls = array_diff($urls, [ts_takedown_public_url($pid)]);
    }
    update_option('ts_takedown_urls', array_values($urls), false);
    ts_takedown_set_status($id, 'reinstated', $by, $note, true);
    return $plan['left'];
}
