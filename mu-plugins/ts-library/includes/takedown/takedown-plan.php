<?php
/**
 * `wp ts apply-takedown` の計画 (specs/S121.md): denylist のエントリから「どの投稿を取り下げ、どの投稿を復帰させるか」を
 * 決める部分と、1 投稿の変化の表示文。WordPress を使わない (投稿の検索は callable で受ける)。
 * 1 投稿の変化そのものは TS_Visibility_Rules::takedown_change。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Takedown_Plan {

    /**
     * denylist のエントリ → 計画。
     *  $targets_of(kind, value): 止める投稿 ID の配列 (子投稿まで波及させたもの)
     *  $takedown_id_of(post_id): その投稿の `_ts_takedown_id` (無ければ '')
     *  $warn(文言): 該当投稿の無いエントリを知らせる (エントリの順に呼ぶ)
     * 返り値: ['plan' => post_id => (取り下げるなら ['id', 'note', 'date', 'why']、復帰なら null),
     *          'reinstate' => post_id => 復帰の理由 (why)]
     *  - 取り下げ中のエントリが優先 (別のエントリで復帰していても、まだ止める対象なら止めたまま)
     *  - 取り下げフォームの申請で取り下げ中の投稿 (`_ts_takedown_id` が req:) は denylist の復帰では戻さない (申請の側で戻す。S128)
     */
    public static function build(array $entries, callable $targets_of, callable $takedown_id_of, callable $warn): array {
        $want = [];      // post_id => ['id', 'note', 'date', 'why'] (取り下げる)
        $reinstate = []; // post_id => why (復帰の対象)
        foreach ($entries as $e) {
            $kind = $e['kind']; $value = $e['value'];
            $targets = $targets_of($kind, $value);
            if (!$targets) $warn("該当投稿なし: {$e['id']} $kind=$value");
            foreach ($targets as $pid) {
                if (($e['status'] ?? '') === 'reinstated') {
                    $reinstate[$pid] = "{$e['id']} $kind=$value";
                } else {
                    $want[$pid] = ['id' => $e['id'], 'note' => $e['public_note'] ?? '',
                                   'date' => $e['requested_at'] ?? '', 'why' => "{$e['id']} $kind=$value"];
                }
            }
        }
        $reinstate = array_diff_key($reinstate, $want);
        foreach (array_keys($reinstate) as $pid) {
            if (strpos((string) $takedown_id_of($pid), 'req:') === 0) unset($reinstate[$pid]);
        }
        $plan = [];
        foreach ($want as $pid => $w) $plan[$pid] = $w;
        foreach ($reinstate as $pid => $why) $plan[$pid] = null;
        return ['plan' => $plan, 'reinstate' => $reinstate];
    }

    /**
     * 1 投稿の変化の表示 (S121 の dry-run の出力の形)。$cur は変化前の状態 (status を使う)、
     * $ch は TS_Visibility_Rules::takedown_change の返り値、$why はエントリ (id kind=value)。
     */
    public static function describe(string $pre, array $cur, array $ch, string $why, int $pid, string $title): string {
        return sprintf('%s%s: %s → post %d %s (%s%s)', $pre,
            $ch['kind'] === 'private' ? 'private 化' : '復帰', $why, $pid, $title,
            $ch['status'] !== null ? "{$cur['status']} → {$ch['status']}" : "{$cur['status']} のまま",
            ($ch['set'] || $ch['delete']) ? '・メタ ' . implode(' ', array_merge(
                array_map(function ($k) use ($ch) { return "$k=" . $ch['set'][$k]; }, array_keys($ch['set'])),
                array_map(function ($k) { return "-$k"; }, $ch['delete']))) : '');
    }
}
