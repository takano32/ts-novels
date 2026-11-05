<?php
/**
 * `wp ts verify` の期待値 (T8.2)。catalog から毎回再計算する — importer と独立に数え直し、取り違えを検出する。
 * WordPress を使わない (DB の実数と比べて表示するのは includes/commands/verify.php)。
 *
 *  - 投稿数: works + episodes - 単発 (単発は 1 投稿に畳まれる)
 *  - 語彙: WP の term 集合と比べる slug の集合 (場当たり term ゼロ・未作成ゼロが期待)
 *  - 割当数: taxonomy ごとの term_relationships の期待値 (連載の親 work への合併付与ぶんも import と同じ計算で足す)
 *  - 書誌カード用メタの被覆 (T9.4): catalog の非空数
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Verify_Expect {

    /** 被覆を見る書誌カード用メタ: meta_key => episodes.jsonl の欄 (この順に表示する) */
    public const META_FIELDS = ['_ts_pub_date_raw' => 'date_raw', '_ts_orig_url' => 'orig_url',
                                '_ts_annex_url' => 'annex_url', '_ts_provenance' => 'provenance'];

    /** 話の欄 => taxonomy (import と同じ対応) */
    private const FIELD_TAX = [['genre', 'ts_genre'], ['type', 'ts_type'], ['keywords', 'ts_keyword']];

    private $works; private $episodes; private $vocab; private $taxes;
    private $ep2work; private $single; private $work_author;
    private $vocab_sets;

    /**
     * $authors は catalog/authors.json の authors の配列、$pseudo は擬似作者 (slug => 名)、
     * $taxes は照合する taxonomy の一覧 (TS_Content_Model::TAXONOMIES)。
     */
    public function __construct(array $works, array $episodes, array $authors, array $pseudo, TS_Vocabulary $vocab, array $taxes) {
        $this->works = $works; $this->episodes = $episodes; $this->vocab = $vocab; $this->taxes = $taxes;
        [$this->ep2work, $this->single, $this->work_author] = TS_Work_Index::index($works);
        // WP は slug を小文字化する (karaage_New → karaage_new) ので小文字で照合する
        $sets = ['ts_author' => []];
        foreach ($authors as $a) $sets['ts_author'][strtolower($a['slug'])] = 1;
        foreach ($pseudo as $slug => $name) $sets['ts_author'][$slug] = 1;
        foreach ($vocab->slug_sets() as $tax => $set) foreach ($set as $slug => $one) $sets[$tax][$slug] = 1;
        $this->vocab_sets = $sets;
    }

    /** 投稿数の期待: ['expect', 'works', 'episodes', 'singles'] */
    public function post_count(): array {
        $singles = count($this->single);
        return ['expect' => count($this->works) + count($this->episodes) - $singles,
                'works' => count($this->works), 'episodes' => count($this->episodes), 'singles' => $singles];
    }

    /** 語彙の集合: tax => [slug => 1] (無い taxonomy は無い) */
    public function vocab_sets(): array {
        return $this->vocab_sets;
    }

    /** 割当数の期待: tax => 件数 (catalog から。DB の term_relationships 実数と比べる) */
    public function assignment_counts(): array {
        $vocab = $this->vocab_sets;
        $expect = array_fill_keys($this->taxes, 0);
        foreach ($this->works as $w) {
            if (!empty($w['author_slug']) && isset($vocab['ts_author'][strtolower($w['author_slug'])])) $expect['ts_author']++;
        }
        foreach ($this->episodes as $ep) {
            $w = $this->ep2work[$ep['episode_id']] ?? null;
            if ($w !== null && !isset($this->single[$w])
                && !empty($this->work_author[$w]) && isset($vocab['ts_author'][strtolower($this->work_author[$w])])) {
                $expect['ts_author']++; // 子投稿は作品の作者を継ぐ
            }
            if (!empty($ep['corpus']) && isset($vocab['ts_corpus'][$this->vocab->slug('ts_corpus', $ep['corpus']) ?? ''])) {
                $expect['ts_corpus']++;
            }
            foreach (self::FIELD_TAX as [$f, $tax]) {
                $uniq = [];
                foreach ((array) ($ep[$f] ?? []) as $v) {
                    if (!is_string($v)) continue;   // 文字列でない語は数えない (import の TS_Vocabulary::resolve と同じ。分割前も TypeError にならず進んだ)
                    $s = $this->vocab->slug($tax, $v);
                    if ($s !== null && isset($vocab[$tax][$s])) $uniq[$s] = 1;
                }
                $expect[$tax] += count($uniq);
            }
            $uniq = [];
            foreach ($this->vocab->episode_worlds($ep['episode_id']) as $v) if (isset($vocab['ts_world'][$v])) $uniq[$v] = 1;
            $expect['ts_world'] += count($uniq);
        }
        // 連載の親 work への合併付与ぶん (import の $parent_terms と同じ計算)
        $parent_expect = [];
        foreach ($this->episodes as $ep) {
            $w = $this->ep2work[$ep['episode_id']] ?? null;
            if ($w === null || isset($this->single[$w])) continue;
            foreach (self::FIELD_TAX as [$f, $tax]) {
                foreach ((array) ($ep[$f] ?? []) as $v) {
                    if (!is_string($v)) continue;   // 文字列でない語は数えない (import の TS_Vocabulary::resolve と同じ。分割前も TypeError にならず進んだ)
                    $s = $this->vocab->slug($tax, $v);
                    if ($s !== null && isset($vocab[$tax][$s])) $parent_expect[$w][$tax][$s] = 1;
                }
            }
            foreach ($this->vocab->episode_worlds($ep['episode_id']) as $v) {
                if (isset($vocab['ts_world'][$v])) $parent_expect[$w]['ts_world'][$v] = 1;
            }
            if (!empty($ep['corpus']) && isset($vocab['ts_corpus'][$this->vocab->slug('ts_corpus', $ep['corpus']) ?? ''])) {
                $parent_expect[$w]['ts_corpus'][$this->vocab->slug('ts_corpus', $ep['corpus'])] = 1;
            }
        }
        foreach ($parent_expect as $w => $taxes) {
            foreach ($taxes as $tax => $set) $expect[$tax] += count($set);
        }
        return $expect;
    }

    /** 書誌カード用メタの被覆の期待: meta_key => catalog で値が非空の話の数 (META_FIELDS の順) */
    public function meta_coverage(): array {
        $cnt = array_fill_keys(array_keys(self::META_FIELDS), 0);
        foreach ($this->episodes as $ep) {
            foreach (self::META_FIELDS as $mk => $f) {
                $v = $ep[$f] ?? null;
                if ($v !== null && $v !== '' && $v !== []) $cnt[$mk]++;
            }
        }
        return $cnt;
    }
}
