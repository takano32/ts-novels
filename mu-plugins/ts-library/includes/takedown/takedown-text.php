<?php
/**
 * 取り下げフォームの文面 (specs/S129.md のメール、S131.md の /removed/ の 1 行と並び)。WordPress を使わない。
 *
 * セキュリティ: 依頼者宛のメール (receipt / applied_requester / rejected) には依頼者の入力した文字列を入れない
 * (他人のアドレスを書いてこのサイトから任意の文面を送らせる中継に使わせない)。運営委員宛は書式なしの
 * テキストで、件名には入れない。scripts/wp/ts_library_test.php がこれを確かめる。
 * 送るのは includes/takedown/takedown-mail.php。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Takedown_Text {

    /**
     * /removed/ に出す 1 行 (S131 の申請由来の生成規則)。$titles は非公開にした作品の題名、
     * $year・$month はお申し出の時期 (確認時刻)。
     */
    public static function public_note(array $titles, string $pen_name, bool $allow_name, int $year, int $month): string {
        $when = sprintf('(%d 年 %d 月)', $year, $month);
        $titles = array_values(array_filter(array_map('strval', $titles), 'strlen'));
        if ($allow_name && $titles && $pen_name !== '') {
            $t = implode('', array_map(function ($x) { return '「' . $x . '」'; }, $titles));
            return "{$t}({$pen_name}さん) — 作者の申し出により掲載していません {$when}";
        }
        return sprintf('作者の申し出により %d 作品を掲載していません %s', max(1, count($titles)), $when);
    }

    /**
     * /removed/ の行 (S131)。$posts: [['id' => _ts_takedown_id, 'note' => _ts_public_note, 'date' => _ts_takedown_date]]。
     * 取り下げの記録ごとに 1 行、お申し出の日の新しい順 (同じ日は記録の番号の順)。
     * 掲示の 1 行が無い記録 (掲示を保留しているもの) は出さない。
     */
    public static function removed_rows(array $posts): array {
        $by = [];
        foreach ($posts as $p) {
            $id = (string) ($p['id'] ?? '');
            if ($id === '') continue;
            if (!isset($by[$id])) $by[$id] = ['id' => $id, 'note' => '', 'date' => ''];
            if ($by[$id]['note'] === '' && (string) ($p['note'] ?? '') !== '') $by[$id]['note'] = (string) $p['note'];
            if ($by[$id]['date'] === '' && (string) ($p['date'] ?? '') !== '') $by[$id]['date'] = (string) $p['date'];
        }
        $rows = array_values(array_filter($by, function ($r) { return $r['note'] !== ''; }));
        usort($rows, function ($a, $b) {
            return strcmp($b['date'], $a['date']) ?: strnatcmp($a['id'], $b['id']);
        });
        return $rows;
    }

    /**
     * メールの件名と本文 (S129)。$c は文脈 (site / id / confirm_url / ttl_days / when / admin_url /
     * summary / titles / unresolved / listed)。依頼者宛 (receipt / applied_requester / rejected) には
     * 依頼者の入力を入れない。
     */
    public static function mail_text(string $kind, array $c): array {
        $site = $c['site'] ?? '';
        $sig = "\n\n-- \n{$site}\n";
        $list = function (array $xs) { return $xs ? implode("\n", array_map(function ($x) { return "  - $x"; }, $xs)) : '  (なし)'; };
        switch ($kind) {
            case 'receipt':
                if (!empty($c['confirm_url'])) {
                    return ["[{$site}] 掲載の取り下げのお申し出の確認",
                        "掲載の取り下げのお申し出を受け付けました。\n\n"
                        . "次のリンクを開くと、お申し出の受け付けが完了します。\n{$c['confirm_url']}\n\n"
                        . "リンクの有効期限は {$c['ttl_days']} 日です。開かれなかったお申し出は無効になります。\n"
                        . "お心当たりのない場合は、このメールを破棄してください。" . $sig];
                }
                return ["[{$site}] 掲載の取り下げのお申し出を受け付けました",
                    "掲載の取り下げのお申し出を受け付けました。運営委員が内容を確認します。\n"
                    . "お心当たりのない場合は、このメールを破棄してください。" . $sig];
            case 'confirmed':
                return ["[{$site}] 取り下げの申請 {$c['id']} を受け付けました",
                    "取り下げの申請 {$c['id']} の確認リンクが開かれました。\n\n{$c['summary']}\n\n"
                    . "{$c['when']} に自動で非公開になります。承認・却下は管理画面から:\n{$c['admin_url']}" . $sig];
            case 'remind':
                return ["[{$site}] 取り下げの申請 {$c['id']} はまもなく自動で非公開になります",
                    "取り下げの申請 {$c['id']} は、{$c['when']} に自動で非公開になります。\n\n{$c['summary']}\n\n"
                    . "承認・却下・期限の変更は管理画面から:\n{$c['admin_url']}" . $sig];
            case 'applied_requester':
                return ["[{$site}] お申し出の作品を非公開にしました",
                    "お申し出に基づき、次の作品を非公開にしました。\n" . $list($c['titles'] ?? []) . "\n\n"
                    . (!empty($c['listed'])
                        ? "掲載していない作品の一覧には、作品名とペンネームを載せています。"
                        : "掲載していない作品の一覧には、作品名とペンネームを載せていません。") . $sig];
            case 'applied_committee':
                return ["[{$site}] 取り下げの申請 {$c['id']} を適用しました",
                    "取り下げの申請 {$c['id']} を適用し、次の作品を非公開にしました。\n" . $list($c['titles'] ?? []) . "\n\n"
                    . (!empty($c['unresolved'])
                        ? "本サイトの作品に結びつかなかった URL があります (適用していません):\n" . $list($c['unresolved']) . "\n\n"
                        : '')
                    . "{$c['summary']}\n\n管理画面:\n{$c['admin_url']}" . $sig];
            case 'rejected':
                return ["[{$site}] 掲載の取り下げのお申し出について",
                    "掲載の取り下げのお申し出について、確認が取れなかったため、取り下げは行いませんでした。\n"
                    . "あらためてお申し出いただく場合は、取り下げのページからお願いします。" . $sig];
        }
        return ['', ''];
    }

    /** 運営委員宛のメールに載せる申請の要約 (入力値は書式なしのテキストとして並べるだけ) */
    public static function summary(array $d): string {
        $rel = ['author' => '作者本人', 'heir' => '権利継承者', 'other' => 'その他'];
        $lines = ['ペンネーム: ' . ($d['pen_name'] ?? ''),
                  '立場: ' . ($rel[$d['relation'] ?? ''] ?? ''),
                  '名前の掲示: ' . (($d['allow_name'] ?? '0') === '1' ? '可' : '不可'),
                  '対象:'];
        foreach ((array) ($d['targets'] ?? []) as $t) {
            $lines[] = '  - ' . $t['url'] . ($t['post_id'] ? '' : ' (本サイトの作品に結びつかない)');
        }
        return implode("\n", $lines);
    }
}
