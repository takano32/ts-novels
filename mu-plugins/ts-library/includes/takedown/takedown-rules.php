<?php
/**
 * 取り下げフォームのパラメータと判定 (specs/S124.md〜S130.md、S135.md)。WordPress を使わない。
 *
 *  - 投稿タイプの名前 TS_TAKEDOWN_TYPE と、状態 (S126) の呼び名
 *  - 裁定待ちのパラメータ (S135) は既定値で動き、wp-config.php で同名の定数を先に define すれば変えられる
 *  - スパム対策の判定 (S124)・確認時刻 (S126)・運営委員の操作の遷移 (S128)・cron の判定 (S126)・
 *    依頼者情報の保持期限 (S125) — どれも時刻と状態を引数で受ける純粋な関数で、
 *    scripts/wp/ts_library_test.php が単体で試す
 * 投稿タイプの登録と申請の読み書きは includes/takedown/takedown-store.php。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

/** 申請の投稿タイプ (S125)。`wp ts reset` の対象 (TS_Content_Model::POST_TYPES) には入れない */
const TS_TAKEDOWN_TYPE = 'ts_takedown_request';

final class TS_Takedown_Rules {

    /** 状態 (S126) と、運営委員の画面に出す呼び名 */
    public static function status_labels(): array {
        return ['unconfirmed' => '未確認 (確認メール待ち)', 'received' => '受付済み (自動適用待ち)',
                'applied' => '自動適用済み (非公開)', 'approved' => '承認して適用済み (非公開)',
                'rejected' => '却下', 'reinstated' => '復帰', 'expired' => '失効 (未確認)'];
    }

    /**
     * パラメータ (S126 の定数と S135 の裁定待ちの表)。wp-config.php で TS_TAKEDOWN_<名前> を
     * define すると既定値より優先する。
     */
    public static function params(): array {
        $c = function (string $name, $default) {
            $k = 'TS_TAKEDOWN_' . $name;
            return defined($k) ? constant($k) : $default;
        };
        return [
            'confirm_ttl_days' => (int) $c('CONFIRM_TTL_DAYS', 7),
            'auto_apply_days' => (int) $c('AUTO_APPLY_DAYS', 10),       // S135: 公開文面に日数を書くか
            'remind_before_days' => (int) $c('REMIND_BEFORE_DAYS', 3),
            'retention_days' => (int) $c('RETENTION_DAYS', 365),        // S135: 依頼者情報の保持期間
            'require_confirm' => (bool) $c('REQUIRE_CONFIRM', true),    // S135: 確認メールを必須にするか
            // S135: 通知先。空なら admin_email + 運営委員全員。カンマ区切りで書けばその宛先だけにする
            'notify_to' => (string) $c('NOTIFY_TO', ''),
            'send_reject_mail' => (bool) $c('SEND_REJECT_MAIL', true),  // S135: 却下のメールを送るか
            'rate_limit' => (int) $c('RATE_LIMIT', 3),                  // S124: 1 時間 3 件
            'rate_window' => 3600,
            'min_seconds' => 3,                                         // S124: 表示から送信まで 3 秒未満は 400
        ];
    }

    /**
     * スパム対策の判定 (S124)。nonce 不一致 403 → honeypot 400 → 3 秒未満 400 → レート制限 429 の順。
     * $t は TS_Takedown_Input::verify_time の結果 (null = 壊れている)。$recent はこの送信元の 1 時間内の受付件数。
     * 通れば 0、止めるなら HTTP の状態コード。
     */
    public static function gate(bool $nonce_ok, string $hp, ?int $t, int $now, int $recent, array $p): int {
        if (!$nonce_ok) return 403;
        if ($hp !== '') return 400;
        if ($t === null || $now - $t < $p['min_seconds']) return 400;
        if ($recent >= $p['rate_limit']) return 429;
        return 0;
    }

    /** 確認したときに書く時刻 (S126: 自動適用は確認時刻 + AUTO_APPLY_DAYS) */
    public static function confirm_times(int $now, array $p): array {
        return ['confirmed_at' => $now, 'auto_apply_at' => $now + $p['auto_apply_days'] * 86400];
    }

    /** 運営委員の操作 (S128) の遷移。許されない組み合わせは null */
    public static function op_next(string $status, string $op): ?string {
        $t = ['approve' => ['received' => 'approved'],
              'reject' => ['unconfirmed' => 'rejected', 'received' => 'rejected'],
              'reinstate' => ['applied' => 'reinstated', 'approved' => 'reinstated'],
              'deadline' => ['received' => 'received']];
        return $t[$op][$status] ?? null;
    }

    /**
     * cron の 1 件の判定 (S126)。$m: received_at / auto_apply_at / reminded_at (Unix 秒、無ければ 0)。
     * 返り値: expire | apply | remind | null。自動適用の時刻を過ぎていればリマインドは飛ばす。
     */
    public static function due(string $status, array $m, int $now, array $p): ?string {
        $day = 86400;
        if ($status === 'unconfirmed') {
            return ($m['received_at'] ?? 0) > 0 && $now >= $m['received_at'] + $p['confirm_ttl_days'] * $day ? 'expire' : null;
        }
        if ($status !== 'received' || empty($m['auto_apply_at'])) return null;
        if ($now >= $m['auto_apply_at']) return 'apply';
        if (empty($m['reminded_at']) && $now >= $m['auto_apply_at'] - $p['remind_before_days'] * $day) return 'remind';
        return null;
    }

    /** 解決から保持期間を過ぎて依頼者情報を消す時か (S125) */
    public static function purge_due(string $status, int $resolved_at, int $now, array $p): bool {
        return in_array($status, ['applied', 'rejected', 'reinstated', 'expired'], true)
            && $resolved_at > 0 && $now >= $resolved_at + $p['retention_days'] * 86400;
    }
}
