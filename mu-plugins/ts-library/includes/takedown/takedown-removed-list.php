<?php
/**
 * 掲載していない作品の一覧 [ts_removed_list] (specs/S131.md)。WordPress に触る側。
 *
 * private + `_ts_visibility=withdrawn` の投稿のメタ (_ts_takedown_id / _ts_public_note / _ts_takedown_date) を集め、
 * 行の作り方 (記録ごとに 1 行、新しい順、掲示の無い記録は出さない) は TS_Takedown_Text::removed_rows に任せる。
 */

if (!defined('ABSPATH')) { exit; }

add_shortcode('ts_removed_list', function () {
    $q = get_posts(['post_type' => 'ts_work', 'post_status' => 'private', 'posts_per_page' => -1,
                    'meta_key' => '_ts_visibility', 'meta_value' => 'withdrawn', 'no_found_rows' => true]);
    $posts = [];
    foreach ($q as $post) {
        $posts[] = ['id' => (string) get_post_meta($post->ID, '_ts_takedown_id', true),
                    'note' => (string) get_post_meta($post->ID, '_ts_public_note', true),
                    'date' => (string) get_post_meta($post->ID, '_ts_takedown_date', true)];
    }
    $rows = TS_Takedown_Text::removed_rows($posts);
    if (!$rows) return '<p class="ts-removed-none">いま掲載していない作品はありません。</p>';
    return '<ul class="ts-removed-list">'
        . implode('', array_map(function ($r) { return '<li>' . esc_html($r['note']) . '</li>'; }, $rows))
        . '</ul>';
});
