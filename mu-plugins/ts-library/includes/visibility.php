<?php
/**
 * 可視性 (specs/S.117.md・S.118.md): 取り下げは読者に 410、旧版は読者に 404、運営委員は帯つきで閲覧。
 *
 * 読者に見えるかは post_status (publish / private) で、理由は post meta `_ts_visibility`
 * (withdrawn / superseded) で表す。WordPress が 410 を返す方式 (S.119。ログイン Cookie 付きの
 * 要求は nginx のキャッシュを素通りする実測 S.62 が前提)。転送 (301) はしない (S.221)。
 *
 * 判定は WordPress を使わない純粋な関数 (ts_visibility_page_path / ts_visibility_decide /
 * ts_visibility_banner_html) に寄せ、scripts/wp/ts_library_test.php で単体の試験をする。
 * 表示文言の規則 (specs/S.200.md): 読者に見える文言に内部の呼び分けを出さない。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

/**
 * 要求パス ($wp->request。先頭・末尾の / なし) → get_page_by_path に渡す ts_work のパス。
 * /novel/ の下でなければ null。
 */
function ts_visibility_page_path(string $request): ?string {
    $request = trim($request, '/');
    if (!preg_match('#^novel/(.+)$#', $request, $m)) return null;
    $parts = array_values(array_filter(explode('/', $m[1]), 'strlen'));
    if (!$parts) return null;
    return implode('/', array_map('rawurldecode', $parts));
}

/**
 * 投稿の状態と見る人から応答を決める (S.118 の表)。
 *  pass        : 何もしない (公開・該当なし・WordPress の既定に任せる)
 *  gone        : 410 + 案内ページ
 *  not_found   : 404 (通常の 404 と同じ)
 *  show_hidden : 運営委員に 200 + 帯
 */
function ts_visibility_decide(?string $status, string $visibility, bool $can_view_hidden): string {
    if ($status === null || $status === 'publish') return 'pass';
    if ($can_view_hidden) {
        // private は read_private_posts で本クエリに乗る。draft (移行前の取り下げ) は
        // 運営委員にも出ない (WordPress の既定の 404) — 移行 (apply-takedown) で private にする
        return $status === 'private' ? 'show_hidden' : 'pass';
    }
    if ($visibility === 'withdrawn') return 'gone';
    return 'not_found';
}

/** `_ts_takedown_id` の表示: "S.235" はそのまま、"req:12" は「申請 12」(S.118) */
function ts_visibility_takedown_label(string $takedown_id): string {
    if (preg_match('/^req:(\d+)$/', $takedown_id, $m)) return '申請 ' . $m[1];
    return $takedown_id;
}

/**
 * 帯の HTML (運営委員にだけ出す。S.118)。$new_url は旧版の新版の URL (無ければ '')。
 */
function ts_visibility_banner_html(string $visibility, string $takedown_id, string $year, string $new_url): string {
    $h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
    if ($visibility === 'withdrawn') {
        $label = ts_visibility_takedown_label($takedown_id);
        $text = '取り下げ済み — 運営委員にのみ表示しています' . ($label !== '' ? ' (' . $h($label) . ')' : '');
    } elseif ($visibility === 'superseded') {
        $text = '旧版' . ($year !== '' ? ' (' . $h($year) . ' 年の掲載)' : '') . ' — 運営委員にのみ表示しています'
            . ($new_url !== '' ? ' → <a href="' . $h($new_url) . '">新版へ</a>' : '');
    } else {
        $text = '運営委員にのみ表示しています';
    }
    return '<p class="ts-hidden-banner" role="note">' . $text . '</p>';
}

/**
 * `wp ts apply-takedown` が 1 投稿についてすること (S.121)。WordPress を使わない。
 *  $cur  : ['status', 'visibility', 'takedown_id', 'public_note', 'superseded_by'] (無いメタは '')
 *  $want : 取り下げるなら ['id' => denylist の id, 'note' => public_note ('' 可)]、
 *          status: reinstated のエントリの対象 (復帰) なら null
 * 返り値: ['kind' => private|reinstate|none, 'status' => 新しい post_status か null (変えない),
 *          'set' => [meta => 値], 'delete' => [meta, …]]
 */
function ts_visibility_takedown_change(array $cur, ?array $want): array {
    $c = array_merge(['status' => '', 'visibility' => '', 'takedown_id' => '', 'public_note' => '',
                      'superseded_by' => ''], $cur);
    $out = ['kind' => 'none', 'status' => null, 'set' => [], 'delete' => []];
    if ($want !== null) {
        // 取り下げ: private + withdrawn + 由来の id + 掲示の 1 行。既に private / draft でもメタを揃える
        if ($c['status'] !== 'private') $out['status'] = 'private';
        $meta = ['_ts_visibility' => ['visibility', 'withdrawn'],
                 '_ts_takedown_id' => ['takedown_id', (string) $want['id']],
                 '_ts_public_note' => ['public_note', (string) ($want['note'] ?? '')]];
        foreach ($meta as $key => [$field, $value]) {
            if ($value === '') { if ($c[$field] !== '') $out['delete'][] = $key; }
            elseif ($c[$field] !== $value) $out['set'][$key] = $value;
        }
        if ($out['status'] !== null || $out['set'] || $out['delete']) $out['kind'] = 'private';
        return $out;
    }
    // 復帰: 取り下げの印が無ければ何もしない。draft は移行前の取り下げなので印とみなす
    if ($c['visibility'] !== 'withdrawn' && $c['takedown_id'] === '' && $c['status'] !== 'draft') return $out;
    foreach (['takedown_id' => '_ts_takedown_id', 'public_note' => '_ts_public_note'] as $field => $key) {
        if ($c[$field] !== '') $out['delete'][] = $key;
    }
    if ($c['superseded_by'] !== '') {
        // 旧版でもあるなら公開には戻さず、旧版として運営委員のみに (優先順位 withdrawn > superseded。S.117)
        if ($c['visibility'] !== 'superseded') $out['set']['_ts_visibility'] = 'superseded';
        if ($c['status'] !== 'private') $out['status'] = 'private';
    } else {
        if ($c['visibility'] !== '') $out['delete'][] = '_ts_visibility';
        if ($c['status'] !== 'publish') $out['status'] = 'publish';
    }
    $out['kind'] = 'reinstate';
    return $out;
}

if (!defined('ABSPATH')) return; // 単体の試験では以下 (WordPress のフック) を読まない

/** 410 を返す要求か (template_include・document_title_parts が見る) */
$GLOBALS['ts_visibility_gone'] = false;

add_action('template_redirect', function () {
    global $wp, $wp_query;
    if (is_admin()) return;
    $post = null;
    if (is_singular('ts_work')) {
        $post = get_queried_object();
    } elseif (is_404()) {
        // private / draft は本クエリに乗らないので自前で引く (S.118 の解決手順)
        $path = ts_visibility_page_path((string) ($wp->request ?? ''));
        if ($path !== null) $post = get_page_by_path($path, OBJECT, 'ts_work');
    }
    if (!($post instanceof WP_Post) || $post->post_type !== 'ts_work') return;

    $decision = ts_visibility_decide($post->post_status,
        (string) get_post_meta($post->ID, '_ts_visibility', true), current_user_can('ts_view_hidden'));
    if ($decision === 'pass') return;
    // 410・404・運営委員向けの表示は、どれも前段のキャッシュに載せない
    nocache_headers();
    if ($decision === 'show_hidden') return;
    // 運営委員でない編集者などに WordPress の既定で private が見えている場合も、読者と同じ応答にする
    $wp_query->set_404();
    if ($decision === 'gone') {
        $GLOBALS['ts_visibility_gone'] = true;
        status_header(410);
    } else {
        status_header(404);
    }
}, 0);

add_filter('template_include', function ($template) {
    if (empty($GLOBALS['ts_visibility_gone'])) return $template;
    $t = locate_template('ts-410.php');
    return $t ?: $template;
}, 20);

add_filter('document_title_parts', function ($parts) {
    if (!empty($GLOBALS['ts_visibility_gone'])) $parts['title'] = '掲載していない作品';
    return $parts;
}, 20);

/** private の題名に付く「非公開: 」を付けない (帯で代わりに示す。S.118) */
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
            // 話 (と単発作品) は新版の episode_id、連載の親 work は新版の work_slug (S.117)
            $key = get_post_meta($post->ID, '_ts_episode_id', true) ? '_ts_episode_id' : '_ts_work_slug';
            $ids = get_posts(['post_type' => 'ts_work', 'post_status' => ['publish', 'private'],
                              'meta_key' => $key, 'meta_value' => $by, 'posts_per_page' => 1,
                              'fields' => 'ids', 'no_found_rows' => true]);
            if ($ids) $new_url = (string) get_permalink($ids[0]);
        }
    }
    echo ts_visibility_banner_html($vis, (string) get_post_meta($post->ID, '_ts_takedown_id', true),
        (string) get_post_time('Y', false, $post), $new_url);
}
