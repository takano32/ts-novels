<?php
/**
 * 可視性 (specs/S117.md・S118.md) の WordPress 側: 取り下げは読者に 410、旧版は読者に 404、運営委員は帯つきで閲覧。
 *
 * WordPress が 410 を返す方式 (S119。ログイン Cookie 付きの要求は nginx のキャッシュを素通りする実測 S62 が前提)。
 * 転送 (301) はしない (S221)。判定と帯の文言は includes/visibility/visibility-rules.php (TS_Visibility_Rules) にあり、
 * ここはその結果で status_header とテンプレートを決めるだけ。
 * ts_visibility_child_statuses と ts_bunko_hidden_banner はテーマ (single.php) と表示部品が呼ぶ。
 */

if (!defined('ABSPATH')) { exit; }

/** 1 つの要求の中の可視性の状態 (グローバル変数の代わり) */
final class TS_Visibility_Request {
    /** 410 を返す要求か (template_redirect が立て、template_include・document_title_parts が見る) */
    public static $gone = false;
}

add_action('template_redirect', function () {
    global $wp, $wp_query;
    if (is_admin()) return;
    $post = null;
    if (is_singular('ts_work')) {
        $post = get_queried_object();
    } elseif (is_404()) {
        // private / draft は本クエリに乗らないので自前で引く (S118 の解決手順)
        $path = TS_Visibility_Rules::page_path((string) ($wp->request ?? ''));
        if ($path !== null) $post = get_page_by_path($path, OBJECT, 'ts_work');
    }
    if (!($post instanceof WP_Post) || $post->post_type !== 'ts_work') return;

    $decision = TS_Visibility_Rules::decide($post->post_status,
        (string) get_post_meta($post->ID, '_ts_visibility', true), current_user_can('ts_view_hidden'));
    if ($decision === 'pass') return;
    // 410・404・運営委員向けの表示は、どれも前段のキャッシュに載せない
    nocache_headers();
    if ($decision === 'show_hidden') return;
    // 運営委員でない編集者などに WordPress の既定で private が見えている場合も、読者と同じ応答にする
    $wp_query->set_404();
    if ($decision === 'gone') {
        TS_Visibility_Request::$gone = true;
        status_header(410);
    } else {
        status_header(404);
    }
}, 0);

add_filter('template_include', function ($template) {
    if (!TS_Visibility_Request::$gone) return $template;
    $t = locate_template('ts-410.php');
    return $t ?: $template;
}, 20);

add_filter('document_title_parts', function ($parts) {
    if (TS_Visibility_Request::$gone) $parts['title'] = '掲載していない作品';
    return $parts;
}, 20);

/** private の題名に付く「非公開: 」を付けない (帯で代わりに示す。S118) */
add_filter('private_title_format', function ($format, $post = null) {
    return ($post && get_post_type($post) === 'ts_work') ? '%s' : $format;
}, 10, 2);

/** 運営委員がログイン中に非公開の作品を見ているか */
function ts_visibility_viewing_hidden($post): bool {
    return $post instanceof WP_Post && $post->post_status !== 'publish' && current_user_can('ts_view_hidden');
}

/** 目次・話ナビに並べる子の状態。運営委員が非公開の作品を見ているときだけ private の話も並べる */
function ts_visibility_child_statuses($parent_id): array {
    $parent = get_post($parent_id);
    return ts_visibility_viewing_hidden($parent) ? ['publish', 'private'] : ['publish'];
}

/** 帯 (single.php の h1 の直後で呼ぶ)。読者には何も出さない */
function ts_bunko_hidden_banner($post): void {
    if (!ts_visibility_viewing_hidden($post) || $post->post_type !== 'ts_work') return;
    $vis = (string) get_post_meta($post->ID, '_ts_visibility', true);
    $new_url = '';
    if ($vis === 'superseded') {
        $by = (string) get_post_meta($post->ID, '_ts_superseded_by', true);
        if ($by !== '') {
            // 話 (と単発作品) は新版の episode_id、連載の親 work は新版の work_slug (S117)
            $key = get_post_meta($post->ID, '_ts_episode_id', true) ? '_ts_episode_id' : '_ts_work_slug';
            $ids = get_posts(['post_type' => 'ts_work', 'post_status' => ['publish', 'private'],
                              'meta_key' => $key, 'meta_value' => $by, 'posts_per_page' => 1,
                              'fields' => 'ids', 'no_found_rows' => true]);
            if ($ids) $new_url = (string) get_permalink($ids[0]);
        }
    }
    echo TS_Visibility_Rules::banner_html($vis, (string) get_post_meta($post->ID, '_ts_takedown_id', true),
        (string) get_post_time('Y', false, $post), $new_url);
}
