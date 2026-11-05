<?php
/**
 * 語彙の値 → WordPress の term ID (T8.2)。WordPress に触る側 (get_term_by)。
 *
 * 値 → slug は TS_Vocabulary (WordPress を使わない) に任せ、ここは slug → term ID の照合と、
 * 解決できず割当を落とした値の記録 ("tax:値" => 件数。場当たり term は作らない) だけを持つ。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Term_Resolver {

    private $vocab;
    private $slug2term = [];    // tax => [slug => term_id] (0 = WP に無い)
    private $term_missing = []; // "tax:値" => 件数。解決できず割当を落とした値

    public function __construct(TS_Vocabulary $vocab) {
        $this->vocab = $vocab;
    }

    public function vocabulary(): TS_Vocabulary {
        return $this->vocab;
    }

    /** 値 (name / raw variant / slug) の配列 → term ID の配列。未解決は記録して捨てる */
    public function term_ids($tax, $values) {
        $ids = [];
        foreach ($this->vocab->resolve($tax, $values) as $hit) {
            $v = $hit['value'];
            if ($hit['slug'] === null) {
                $this->term_missing["$tax:$v"] = ($this->term_missing["$tax:$v"] ?? 0) + 1;
                continue;
            }
            $slug = $hit['slug'];
            if (!isset($this->slug2term[$tax][$slug])) {
                $term = get_term_by('slug', $slug, $tax);
                $this->slug2term[$tax][$slug] = $term ? (int) $term->term_id : 0;
            }
            if ($this->slug2term[$tax][$slug]) $ids[] = $this->slug2term[$tax][$slug];
            else $this->term_missing["$tax:$v"] = ($this->term_missing["$tax:$v"] ?? 0) + 1;
        }
        return array_values(array_unique($ids));
    }

    /** 解決できず割当を落とした値: "tax:値" => 件数 */
    public function missing(): array {
        return $this->term_missing;
    }
}
