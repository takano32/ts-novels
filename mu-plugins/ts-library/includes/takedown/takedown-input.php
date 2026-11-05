<?php
/**
 * 取り下げフォームの入力の検証 (specs/S124.md の表) と、表示時刻の署名。WordPress を使わない。
 *
 * $resolve (パス → ts_work) は呼び出し側 (includes/takedown/takedown-form.php と takedown-admin.php) が
 * WordPress の関数を渡す。単体の試験 (scripts/wp/ts_library_test.php) は配列を返す無名関数を渡す。
 * メールアドレスの判定だけは WordPress があれば is_email() を使う (無ければ filter_var)。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Takedown_Input {

    /** 制御文字を除く (改行とタブは $keep_newlines のときだけ残す) */
    public static function clean(string $s, bool $keep_newlines = false): string {
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $re = $keep_newlines ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
        $out = preg_replace($re, $keep_newlines ? '' : ' ', $s);
        return trim($out ?? '');
    }

    public static function strlen(string $s): int {
        return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : (int) preg_match_all('/./us', $s);
    }

    /**
     * 対象作品の URL の 1 行 → ts_work のパス (get_page_by_path に渡す形)。本サイトの /novel/… でなければ null。
     * $home_host は本サイトのホスト名。ホストを書かない「/novel/…」も本サイトとみなす。
     */
    public static function target_path(string $line, string $home_host): ?string {
        $line = trim($line);
        if ($line === '' || strlen($line) > 500) return null;
        // 「novels.xwp.jp/novel/…」のようにスキームを省いた書き方も受ける
        if ($home_host !== '' && stripos($line, $home_host . '/') === 0) $line = 'https://' . $line;
        $u = parse_url($line);
        if ($u === false) return null;
        if (isset($u['host'])) {
            if (strtolower($u['host']) !== strtolower($home_host)) return null;
            if (isset($u['scheme']) && !in_array(strtolower($u['scheme']), ['http', 'https'], true)) return null;
        } elseif (isset($u['scheme']) || $line[0] !== '/') {
            return null;
        }
        $path = (string) ($u['path'] ?? '');
        if (!preg_match('#^/novel/#', $path)) return null;
        // TS_Visibility_Rules::page_path と同じ規則 (/novel/ の下のパスを rawurldecode して / でつなぐ)
        $parts = array_values(array_filter(explode('/', substr($path, strlen('/novel/'))), 'strlen'));
        if (!$parts) return null;
        foreach ($parts as $p) if ($p === '.' || $p === '..') return null;
        return implode('/', array_map('rawurldecode', $parts));
    }

    /**
     * フォームの入力を検証する (S124 の表)。$in は wp_unslash 済みの $_POST 相当。
     * $resolve(string $path): ?array — ts_work に解決できれば ['post_id' => int, 'work_slug' => ?string]。
     * 返り値: ['errors' => [文言…], 'data' => 保存する値]。data['targets'] は [{url, post_id|null, work_slug|null}]。
     */
    public static function validate(array $in, callable $resolve, string $home_host): array {
        $err = [];
        $s = function ($k) use ($in) { return is_string($in[$k] ?? null) ? $in[$k] : ''; };
        $pen = self::clean($s('pen_name'));
        if ($pen === '') $err[] = '当時のペンネームを書いてください。';
        elseif (self::strlen($pen) > 100) $err[] = '当時のペンネームは 100 字までです。';

        $lines = array_values(array_filter(array_map('trim', explode("\n", self::clean($s('targets'), true))), 'strlen'));
        $targets = [];
        if (!$lines) $err[] = '対象作品の URL を 1 行に 1 つ書いてください。';
        elseif (count($lines) > 20) $err[] = '対象作品の URL は 20 行までです。';
        else {
            $seen = [];
            foreach ($lines as $line) {
                if (strlen($line) > 500) { $err[] = '対象作品の URL が長すぎます (1 行 500 字まで)。'; break; }
                if (isset($seen[$line])) continue;
                $seen[$line] = true;
                $path = self::target_path($line, $home_host);
                $hit = $path !== null ? $resolve($path) : null;
                $targets[] = ['url' => $line, 'post_id' => $hit ? (int) $hit['post_id'] : null,
                              'work_slug' => $hit ? ($hit['work_slug'] ?? null) : null];
            }
        }

        $contact = self::clean($s('contact'));
        if ($contact === '' || strlen($contact) > 254 || !self::is_email($contact)) {
            $err[] = '連絡先のメールアドレスを正しく書いてください。';
        }
        $relation = $s('relation');
        if (!in_array($relation, ['author', 'heir', 'other'], true)) $err[] = 'お立場を選んでください。';
        $evidence = self::clean($s('evidence'), true);
        if (self::strlen($evidence) > 1000) $err[] = '本人確認の手がかりは 1,000 字までです。';
        $message = self::clean($s('message'), true);
        if (self::strlen($message) > 2000) $err[] = 'ご連絡事項は 2,000 字までです。';

        return ['errors' => $err, 'data' => [
            'pen_name' => $pen, 'targets' => $targets, 'contact' => $contact, 'relation' => $relation,
            'evidence' => $evidence, 'message' => $message, 'allow_name' => !empty($in['allow_name']) ? '1' : '0',
        ]];
    }

    /** WordPress があれば is_email()、無ければ (単体の試験) filter_var */
    public static function is_email(string $s): bool {
        if (function_exists('is_email')) return (bool) is_email($s);
        return filter_var($s, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** フォーム表示時刻 t の署名つきの値 (送信者が書き換えて 3 秒の検査を逃れないように) */
    public static function sign_time(int $t, string $key): string {
        return $t . '.' . substr(hash_hmac('sha256', 'ts_takedown_form|' . $t, $key), 0, 32);
    }

    /** 署名を確かめて表示時刻を返す。壊れていれば null */
    public static function verify_time(string $v, string $key): ?int {
        if (!preg_match('/^(\d{1,12})\.[0-9a-f]{32}$/', $v, $m)) return null;
        return hash_equals(self::sign_time((int) $m[1], $key), $v) ? (int) $m[1] : null;
    }
}
