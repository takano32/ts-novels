<?php
/**
 * takedown/denylist.yml の読み取りと書き出し (specs/S76.md のスキーマ、S121.md、S132.md)。WordPress を使わない。
 *
 *  - parse: このスキーマ専用の最小パーサ (ブロック形式・フロー形式の両対応)。`wp ts apply-takedown` と
 *    `wp ts verify` / `wp ts export-takedowns` が読む。不完全なエントリ・未知の kind は捨てて warnings に残す
 *    (呼び出し側が WP_CLI::warning で出す)
 *  - export_yaml: 取り下げフォームの 1 申請 → denylist のエントリ (version 1)。parse で読み戻せる形
 *    (scripts/wp/ts_library_test.php が往復を確かめる)
 *  - episode_target / annex_like: `wp ts apply-takedown` が投稿を引く前の value の分解 (DB を引くのは
 *    includes/commands/takedown.php)
 *  - target_of_post / export_start / since_error: `wp ts export-takedowns` の 1 対象の決め方と引数の検査
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Denylist {

    /** エントリの target.kind に書ける値 (S76) */
    public const KINDS = ['work', 'episode', 'annex_path', 'author'];

    /**
     * denylist.yml の entries を読む。返り値: ['entries' => [...], 'warnings' => [文言…]]。
     * エントリ: id / kind / value / status / public_note / requested_at / reason / received_via
     */
    public static function parse(string $path): array {
        return self::parse_lines(file($path) ?: []);   // 読めなければ空 (分割前と同じく黙って進む)
    }

    /** parse の本体 (行の配列から)。試験が文字列から読むときにも使う */
    public static function parse_lines(array $lines): array {
        $out = []; $cur = null; $in_entries = false;
        foreach ($lines as $raw) {
            $line = rtrim($raw, "\r\n");
            if (preg_match('/^\s*#/', $line) || trim($line) === '') continue;
            if (preg_match('/^entries:\s*(\[\s*\])?\s*$/', $line, $m)) {
                $in_entries = true;
                if (!empty($m[1])) return ['entries' => [], 'warnings' => []];   // `entries: []`
                continue;
            }
            if (!$in_entries) continue;
            if (preg_match('/^\s*-\s*id:\s*(.+?)\s*$/', $line, $m)) {   // 新エントリ
                if ($cur) $out[] = $cur;
                $cur = ['id' => trim($m[1], "\"' "), 'kind' => null, 'value' => null, 'status' => '',
                        'public_note' => '', 'requested_at' => '', 'reason' => '', 'received_via' => ''];
                continue;
            }
            if (!$cur) continue;
            // target: { kind: work, value: foo } (フロー形式)
            if (preg_match('/^\s*target:\s*\{(.+)\}\s*$/', $line, $m)) {
                if (preg_match('/kind:\s*([A-Za-z_]+)/', $m[1], $k)) $cur['kind'] = $k[1];
                if (preg_match('/value:\s*["\']?([^"\',}]+)/', $m[1], $v)) $cur['value'] = trim($v[1]);
                continue;
            }
            if (preg_match('/^\s+kind:\s*([A-Za-z_]+)/', $line, $m)) { $cur['kind'] = $m[1]; continue; }
            if (preg_match('/^\s+value:\s*(.*?)\s*$/u', $line, $m)) {
                // 引用符なしの値は「 #」からがコメント。path#anchor の # は値の一部 (export-takedowns が書く話の対象)
                $v = $m[1];
                if ($v !== '' && $v[0] !== '"' && $v[0] !== "'") $v = rtrim(preg_replace('/\s#.*$/u', '', $v));
                $cur['value'] = trim(self::unquote($v));
                continue;
            }
            if (preg_match('/^\s+status:\s*([A-Za-z_]+)/', $line, $m)) { $cur['status'] = $m[1]; continue; }
            if (preg_match('/^\s+requested_at:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/', $line, $m)) { $cur['requested_at'] = $m[1]; continue; }
            if (preg_match('/^\s+received_via:\s*([A-Za-z_]+)/', $line, $m)) { $cur['received_via'] = $m[1]; continue; }
            if (preg_match('/^\s+reason:\s*(.*?)\s*$/u', $line, $m)) { $cur['reason'] = self::unquote($m[1]); continue; }
            // /removed/ の掲示の 1 行 (S121 で投稿のメタ _ts_public_note に写す)。囲みの引用符は外す
            if (preg_match('/^\s+public_note:\s*(.*?)\s*$/u', $line, $m)) {
                $cur['public_note'] = self::unquote($m[1]);
                continue;
            }
        }
        if ($cur) $out[] = $cur;
        $valid = []; $warnings = [];
        foreach ($out as $e) {
            if (!$e['kind'] || !$e['value']) {
                $warnings[] = "denylist の不完全なエントリ: {$e['id']} (kind/value を確認)";
                continue;
            }
            if (!in_array($e['kind'], self::KINDS, true)) {
                $warnings[] = "未知の kind: {$e['kind']} ({$e['id']})";
                continue;
            }
            $valid[] = $e;
        }
        return ['entries' => $valid, 'warnings' => $warnings];
    }

    /** 囲みの引用符を外す (単一引用符の中の '' は ' に。export-takedowns が書く形) */
    public static function unquote(string $v): string {
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
            $q = $v[0];
            $v = substr($v, 1, -1);
            if ($q === "'") $v = str_replace("''", "'", $v);
        }
        return $v;
    }

    /** YAML の 1 値 (denylist.yml に書く形)。そのままで読めない値は単一引用符で囲む */
    public static function scalar(?string $v): string {
        if ($v === null) return 'null';
        if ($v !== '' && !preg_match('/^[\s\-?:,\[\]{}#&*!|>\'"%@`]|: | #|\s$|^(null|true|false|yes|no|~)$/i', $v)) return $v;
        return "'" . str_replace("'", "''", $v) . "'";
    }

    /**
     * 1 申請 → denylist のエントリ (version 1) の YAML (S132)。連絡先・本文・証拠は出さない。
     * $req: id (投稿 ID) / status / confirmed_date / applied_date / via (takedown_form | email)
     * $targets: [['kind' => work|episode, 'value' => …, 'note' => public_note]]
     * $first: 最初に振る S の番号。返り値: ['yaml' => 文字列, 'ids' => [振った S …]]
     */
    public static function export_yaml(array $req, array $targets, int $first): array {
        $out = ''; $ids = []; $n = $first;
        $status = $req['status'] === 'reinstated' ? 'reinstated' : 'applied';
        foreach ($targets as $t) {
            $id = 'S.' . $n++;
            $ids[] = $id;
            $out .= "  - id: $id\n"
                . "    target:\n"
                . "      kind: {$t['kind']}\n"
                . '      value: ' . self::scalar($t['value']) . "\n"
                . "    reason: 取り下げフォーム経由の申し出 (申請 {$req['id']})\n"
                . "    reason_class: author_request\n"
                . "    requested_at: {$req['confirmed_date']}\n"
                . "    received_via: {$req['via']}\n"
                . "    status: $status\n"
                . "    applied:\n"
                . '      wp_draft: ' . self::scalar($req['applied_date'] ?: null) . "\n"
                . '      htaccess_410: ' . self::scalar($req['applied_date'] ?: null) . "\n"
                . "      annex_excluded: null\n"
                . "      git_history_purged: null\n"
                . "      removed_page_listed: null\n"
                . '    public_note: ' . self::scalar($t['note']) . "\n";
        }
        return ['yaml' => $out, 'ids' => $ids];
    }

    /** denylist に書き写した申請: 申請の投稿 ID => [['id' => 記録の id, 'status' => …]] (reason の「(申請 <ID>)」で結ぶ) */
    public static function exported_requests(array $entries): array {
        $out = [];
        foreach ($entries as $e) {
            if (!preg_match('/\(申請 ([0-9]+)\)/u', $e['reason'] ?? '', $m)) continue;
            $out[(int) $m[1]][] = ['id' => $e['id'], 'status' => $e['status']];
        }
        return $out;
    }

    /**
     * kind: episode の value → [source_path, アンカー] (S76)。value は episodes.jsonl の source_path で、
     * アンカー分割話は "path#anchor"。アンカーが無ければ null (source_path が同じ投稿を全部止める)。
     */
    public static function episode_target(string $value): array {
        return array_pad(explode('#', $value, 2), 2, null);
    }

    /**
     * kind: annex_path の value → [前方の文字列, 末尾が * か] (S76)。末尾の * は前方一致 (LIKE の %) で、
     * * を外した前方の文字列を LIKE 用に逃がすのは呼び出し側 ($wpdb->esc_like)。* が無ければ完全一致。
     */
    public static function annex_like(string $value): array {
        return [rtrim($value, '*'), substr($value, -1) === '*'];
    }

    /**
     * export-takedowns の 1 対象 (S132): 申請が指した ts_work の投稿のメタ → エントリの target。work_slug があれば
     * work、無ければ話だけを指した申請として episode (source_path、アンカー分割話は path#anchor)。
     * どちらも無ければ null (呼び出し側が警告を出して飛ばす)。
     */
    public static function target_of_post(string $work_slug, string $source_path, string $anchor): ?array {
        if ($work_slug !== '') return ['kind' => 'work', 'value' => $work_slug];
        if ($source_path === '') return null;
        return ['kind' => 'episode', 'value' => $source_path . ($anchor !== '' ? "#$anchor" : '')];
    }

    /**
     * export-takedowns の最初に振る S の番号 (S132): [番号, 誤りの文 (無ければ null)]。$start は --start の値
     * (省いたら null)、$specs は SPECS.md の中身 (--start を省いたときだけ読む。読めなければ null)。
     */
    public static function export_start(?string $start, ?string $specs): array {
        if ($start !== null) {
            if (!preg_match('/^[0-9]+$/', $start)) return [null, '--start は数字で'];
            return [(int) $start, null];
        }
        if ($specs === null || !preg_match('/次は S\.([0-9]+)/u', $specs, $m)) return [null, 'SPECS.md に「次は Sn」が無い (--start で指定)'];
        return [(int) $m[1], null];
    }

    /** export-takedowns の --since の検査: 空 (省いた) か YYYY-MM-DD なら null、ほかは誤りの文 */
    public static function since_error(string $since): ?string {
        return ($since !== '' && !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $since)) ? '--since は YYYY-MM-DD で' : null;
    }
}
