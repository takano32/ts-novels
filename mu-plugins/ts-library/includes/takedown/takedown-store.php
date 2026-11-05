<?php
/**
 * 取り下げの申請の投稿タイプと読み書き (specs/S125.md・S126.md、T26.3)。WordPress に触る側。
 *
 *  - 申請の投稿タイプ ts_takedown_request とメタ (S125)。`wp ts reset` の対象に入れない
 *    (TS_Content_Model::POST_TYPES に無い。scripts/wp/ts_library_test.php が確かめる)
 *  - 申請の値を配列で読む (ts_takedown_get)・状態を変えて記録を残す (ts_takedown_set_status / ts_takedown_log)・
 *    申請を作る (ts_takedown_store)・確認済みにする (ts_takedown_mark_received)
 * 判定とパラメータは includes/takedown/takedown-rules.php、メールは takedown-mail.php。
 */

if (!defined('ABSPATH')) { exit; }

/** 申請の保存の状態 (グローバル変数の代わり) */
final class TS_Takedown_Store {
    /** 自分で申請を wp_update_post している最中か (立っている間は管理画面の save_post の処理に入り直さない) */
    public static $saving = false;
}

// ====================================================================== 投稿タイプ (S125)

add_action('init', function () {
    $cap = 'ts_manage_takedown';
    register_post_type(TS_TAKEDOWN_TYPE, [
        'labels' => ['name' => '取り下げの申請', 'singular_name' => '取り下げの申請',
                     'add_new' => '申請を起票', 'add_new_item' => '取り下げの申請を起票',
                     'edit_item' => '取り下げの申請', 'menu_name' => '取り下げの申請'],
        'public' => false, 'show_ui' => true, 'exclude_from_search' => true, 'show_in_rest' => false,
        'publicly_queryable' => false, 'query_var' => false, 'rewrite' => false,
        'menu_icon' => 'dashicons-hidden', 'supports' => false,
        // 一覧・閲覧・操作は ts_manage_takedown だけで決める (S116 の運営委員ロールと管理者が持つ)
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

// ====================================================================== 読み書き

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

/** 状態を変え、操作の記録を残す (S128: 誰が・いつ・メモ)。$by = 0 は cron */
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

/** 本サイトの /novel/ のパス → 投稿 (TS_Takedown_Input::validate の $resolve に渡す) */
function ts_takedown_resolve(string $path): ?array {
    $post = get_page_by_path($path, OBJECT, 'ts_work');
    if (!($post instanceof WP_Post) || $post->post_status === 'trash') return null;
    $slug = (string) get_post_meta($post->ID, '_ts_work_slug', true);
    return ['post_id' => $post->ID, 'work_slug' => $slug !== '' ? $slug : null];
}

/** 申請を作る (フォームと、運営委員がメールの申し出を起票するときの共通)。返り値は投稿 ID */
function ts_takedown_store(array $d, string $via, int $post_id = 0): int {
    $p = TS_Takedown_Rules::params();
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
    TS_Takedown_Store::$saving = true;
    wp_update_post(['ID' => $post_id, 'post_title' => wp_slash($title), 'post_status' => 'private']);
    TS_Takedown_Store::$saving = false;
    if ($via === 'takedown_form' && $p['require_confirm']) {
        ts_takedown_set_status($post_id, 'unconfirmed', 0, '', false);
    } else {
        ts_takedown_mark_received($post_id, $now, (int) get_current_user_id());
    }
    return $post_id;
}

/** 確認済みにする (確認リンク、または確認を要しない受け付け) */
function ts_takedown_mark_received(int $id, int $now, int $by): void {
    foreach (TS_Takedown_Rules::confirm_times($now, TS_Takedown_Rules::params()) as $k => $v) update_post_meta($id, "_tr_$k", $v);
    ts_takedown_set_status($id, 'received', $by, '', false);
    $r = ts_takedown_get($id);
    ts_takedown_mail_committee('confirmed', $r);
}
