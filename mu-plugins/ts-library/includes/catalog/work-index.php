<?php
/**
 * catalog/works.jsonl と episodes.jsonl の索引と、話 (子投稿) の URL slug の割り当て (T8.2)。WordPress を使わない。
 *
 * `wp ts import` と `wp ts verify` が同じ索引を使う (importer と独立に数え直して取り違えを検出するのが verify の役)。
 * slug の掃除 (sanitize_title) は callable で受け、単体の試験では簡素な代替を渡す。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Work_Index {

    /** works.jsonl の索引: [episode_id→work_slug, 単発 work の集合, work_slug→author_slug, episode_id→話順] */
    public static function index(array $works): array {
        $ep2work = []; $single = []; $author = []; $order = [];
        foreach ($works as $w) {
            $i = 0;
            foreach ($w['episodes'] as $e) {
                $eid = is_array($e) ? $e['episode_id'] : $e;
                $ep2work[$eid] = $w['work_slug'];
                $order[$eid] = ++$i; // 話順 = works.jsonl の episodes 並び (menu_order に書く)
            }
            if ((int) $w['episode_count'] === 1) $single[$w['work_slug']] = true;
            $author[$w['work_slug']] = $w['author_slug'] ?? '';
        }
        return [$ep2work, $single, $author, $order];
    }

    /**
     * 連載の親 work に合併して付ける term の値 (`wp ts import`。書庫に連載を出すため)。単発の話と work の無い話は除く。
     * 返り値: $episodes の添字 => ['work' => work_slug, 'terms' => [taxonomy => 値の配列]] (episodes の順)。
     * taxonomy は ts_genre・ts_type・ts_keyword・ts_world・ts_corpus の順で、値は話の欄そのまま (語彙での解決と
     * term ID への照合は呼び出し側の TS_Term_Resolver::term_ids)。work ごとに束ねずに話ごとに返すのは、
     * term_ids が解決できなかった値をその都度数える (ts_term_warnings の件数と並び) のを分割前と同じにするため。
     * wp ts verify の期待値 (TS_Verify_Expect::assignment_counts) は importer と独立に数えるため、これを使わない。
     */
    public static function parent_term_values(array $episodes, array $ep2work, array $single, TS_Vocabulary $vocab): array {
        $out = [];
        foreach ($episodes as $i => $ep) {
            $w = $ep2work[$ep['episode_id']] ?? null;
            if ($w === null || isset($single[$w])) continue;
            $terms = [];
            foreach ([['genre', 'ts_genre'], ['type', 'ts_type'], ['keywords', 'ts_keyword']] as [$f, $tax]) $terms[$tax] = $ep[$f] ?? [];
            $terms['ts_world'] = $vocab->episode_worlds($ep['episode_id']);
            $terms['ts_corpus'] = [$ep['corpus'] ?? null];
            $out[$i] = ['work' => $w, 'terms' => $terms];
        }
        return $out;
    }

    /**
     * 子投稿 (話) の URL slug を決定的に割り当てる。原ファイル名の語幹 → 作品内で重複したら
     * 親ディレクトリ前置 → 日付後置 → episodes.jsonl 順の連番 (catalog がコミット済みなので決定的)。
     * $sanitize(string): string は WordPress の sanitize_title。
     */
    public static function child_slugs(array $episodes, array $ep2work, array $single_work, callable $sanitize): array {
        $groups = []; $by_eid = [];
        foreach ($episodes as $ep) {
            $by_eid[$ep['episode_id']] = $ep;
            $w = $ep2work[$ep['episode_id']] ?? '';
            if (isset($single_work[$w])) continue;   // 単発は子投稿を作らない
            $s = preg_replace('/\.[A-Za-z0-9]+$/', '', basename($ep['source_path']));
            if (!empty($ep['source_anchor'])) $s .= '-' . $ep['source_anchor'];
            // 全角英数→半角はアンカー連結の「後」に (anchor=ＢＢＳ のような全角アンカーのため)
            if (function_exists('mb_convert_kana')) $s = mb_convert_kana($s, 'as');
            $s = strtolower($s);
            $groups[$w][$ep['episode_id']] = $s;
        }
        $out = [];
        foreach ($groups as $w => $slugs) {
            $dups = array_count_values($slugs);
            foreach ($slugs as $eid => $s) {
                if ($dups[$s] > 1) {
                    $parts = explode('/', $by_eid[$eid]['source_path']);
                    $dir = count($parts) >= 2 ? strtolower($parts[count($parts) - 2]) : '';
                    $slugs[$eid] = ($dir !== '' ? "$dir-" : '') . $s;
                }
            }
            $dups = array_count_values($slugs);
            foreach ($slugs as $eid => $s) {
                if ($dups[$s] > 1) {
                    $d = preg_replace('/\D/', '', (string) ($by_eid[$eid]['date'] ?? ''));
                    if ($d !== '') $slugs[$eid] = "$s-$d";
                }
            }
            $seen = [];
            foreach ($slugs as $eid => $s) {
                if (isset($seen[$s])) $slugs[$eid] = $s . '-' . (++$seen[$s]);
                else $seen[$s] = 1;
                $clean = $sanitize($slugs[$eid]);
                $out[$eid] = $clean !== '' ? $clean : $sanitize($eid);
            }
        }
        return $out;
    }
}
