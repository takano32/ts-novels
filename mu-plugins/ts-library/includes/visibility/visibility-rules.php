<?php
/**
 * 可視性の判定 (specs/S117.md・S118.md・S121.md)。WordPress を使わない。
 *
 * 読者に見えるかは post_status (publish / private) で、理由は post meta `_ts_visibility`
 * (withdrawn / superseded) で表す。ここにあるのは「要求パスの解決」「応答の判定」「帯の HTML」
 * 「apply-takedown が 1 投稿にすること」の 4 つの純粋な関数で、scripts/wp/ts_library_test.php が単体で試す。
 * WordPress のフック (410 / 404 を返す・テンプレートを選ぶ) は includes/visibility/visibility-hooks.php。
 * 表示文言の規則 (specs/S200.md): 読者に見える文言に内部の呼び分けを出さない。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Visibility_Rules {

    /**
     * 要求パス ($wp->request。先頭・末尾の / なし) → get_page_by_path に渡す ts_work のパス。
     * /novel/ の下でなければ null。
     */
    public static function page_path(string $request): ?string {
        $request = trim($request, '/');
        if (!preg_match('#^novel/(.+)$#', $request, $m)) return null;
        $parts = array_values(array_filter(explode('/', $m[1]), 'strlen'));
        if (!$parts) return null;
        return implode('/', array_map('rawurldecode', $parts));
    }

    /**
     * 投稿の状態と見る人から応答を決める (S118 の表)。
     *  pass        : 何もしない (公開・該当なし・WordPress の既定に任せる)
     *  gone        : 410 + 案内ページ
     *  not_found   : 404 (通常の 404 と同じ)
     *  show_hidden : 運営委員に 200 + 帯
     */
    public static function decide(?string $status, string $visibility, bool $can_view_hidden): string {
        if ($status === null || $status === 'publish') return 'pass';
        if ($can_view_hidden) {
            // private は read_private_posts で本クエリに乗る。draft (移行前の取り下げ) は
            // 運営委員にも出ない (WordPress の既定の 404) — 移行 (apply-takedown) で private にする
            return $status === 'private' ? 'show_hidden' : 'pass';
        }
        if ($visibility === 'withdrawn') return 'gone';
        return 'not_found';
    }

    /** `_ts_takedown_id` の表示: フォームの申請 "req:12" は「申請 12」、denylist の記録の S 番号は画面に出さない (S118) */
    public static function takedown_label(string $takedown_id): string {
        if (preg_match('/^req:(\d+)$/', $takedown_id, $m)) return '申請 ' . $m[1];
        return '';
    }

    /**
     * 帯の HTML (運営委員にだけ出す。S118)。$new_url は旧版の新版の URL (無ければ '')。
     */
    public static function banner_html(string $visibility, string $takedown_id, string $year, string $new_url): string {
        $h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
        if ($visibility === 'withdrawn') {
            $label = self::takedown_label($takedown_id);
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
     * `wp ts apply-takedown` が 1 投稿についてすること (S121)。
     *  $cur  : ['status', 'visibility', 'takedown_id', 'public_note', 'takedown_date', 'superseded_by'] (無いメタは '')
     *  $want : 取り下げるなら ['id' => denylist の id, 'note' => public_note ('' 可),
     *          'date' => お申し出の日 YYYY-MM-DD ('' 可。/removed/ の並び順に使う。specs/S131.md の実装の記録)]、
     *          status: reinstated のエントリの対象 (復帰) なら null
     * 返り値: ['kind' => private|reinstate|none, 'status' => 新しい post_status か null (変えない),
     *          'set' => [meta => 値], 'delete' => [meta, …]]
     */
    public static function takedown_change(array $cur, ?array $want): array {
        $c = array_merge(['status' => '', 'visibility' => '', 'takedown_id' => '', 'public_note' => '',
                          'takedown_date' => '', 'superseded_by' => ''], $cur);
        $out = ['kind' => 'none', 'status' => null, 'set' => [], 'delete' => []];
        if ($want !== null) {
            // 取り下げ: private + withdrawn + 由来の id + 掲示の 1 行。既に private / draft でもメタを揃える
            if ($c['status'] !== 'private') $out['status'] = 'private';
            $meta = ['_ts_visibility' => ['visibility', 'withdrawn'],
                     '_ts_takedown_id' => ['takedown_id', (string) $want['id']],
                     '_ts_public_note' => ['public_note', (string) ($want['note'] ?? '')],
                     '_ts_takedown_date' => ['takedown_date', (string) ($want['date'] ?? '')]];
            foreach ($meta as $key => [$field, $value]) {
                if ($value === '') { if ($c[$field] !== '') $out['delete'][] = $key; }
                elseif ($c[$field] !== $value) $out['set'][$key] = $value;
            }
            if ($out['status'] !== null || $out['set'] || $out['delete']) $out['kind'] = 'private';
            return $out;
        }
        // 復帰: 取り下げの印が無ければ何もしない。draft は移行前の取り下げなので印とみなす
        if ($c['visibility'] !== 'withdrawn' && $c['takedown_id'] === '' && $c['status'] !== 'draft') return $out;
        foreach (['takedown_id' => '_ts_takedown_id', 'public_note' => '_ts_public_note',
                  'takedown_date' => '_ts_takedown_date'] as $field => $key) {
            if ($c[$field] !== '') $out['delete'][] = $key;
        }
        if ($c['superseded_by'] !== '') {
            // 旧版でもあるなら公開には戻さず、旧版として運営委員のみに (優先順位 withdrawn > superseded。S117)
            if ($c['visibility'] !== 'superseded') $out['set']['_ts_visibility'] = 'superseded';
            if ($c['status'] !== 'private') $out['status'] = 'private';
        } else {
            if ($c['visibility'] !== '') $out['delete'][] = '_ts_visibility';
            if ($c['status'] !== 'publish') $out['status'] = 'publish';
        }
        $out['kind'] = 'reinstate';
        return $out;
    }
}
