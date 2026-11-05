<?php
/**
 * catalog/terms.json の語彙 (specs/S173.md) の対応表。WordPress を使わない。
 *
 * term は必ず slug で解決する (name 解決は term_exists() が slug 照合を先にやるため sanitize_title の衝突で
 * 誤爆する — S182 の実測: 語彙外 term の増殖)。値 (name / 原表記 / NFKC 形 / slug) → slug の対応表をここで持ち、
 * slug → term ID は WordPress に触る includes/commands/term-resolver.php が引く。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Vocabulary {

    private $name2slug = [];    // tax => [name / raw variant / lookup variant / slug => slug]
    private $ep_worlds = [];    // episode_id => [world slug] (terms.json の episode_worlds)

    /** $terms は catalog/terms.json を配列にしたもの */
    public function __construct(array $terms) {
        foreach ($terms['taxonomies'] as $tax => $spec) {
            foreach ($spec['terms'] as $term) {
                $this->name2slug[$tax][$term['slug']] = $term['slug'];
                $this->name2slug[$tax][$term['name']] = $term['slug'];
                foreach ($term['raw_variants'] ?? [] as $rv) $this->name2slug[$tax][$rv] = $term['slug'];
                // NFKC 形 (episodes.jsonl の正規化欄が持つ形)。原表記とも正規化名とも違う中間形
                foreach ($term['lookup_variants'] ?? [] as $lv) $this->name2slug[$tax][$lv] = $term['slug'];
            }
        }
        $this->ep_worlds = $terms['episode_worlds'] ?? [];
    }

    /** この taxonomy の語彙を terms.json が持つか (持たない ts_author は値をそのまま slug とみなす) */
    public function has(string $tax): bool {
        return isset($this->name2slug[$tax]);
    }

    /** 値 (name / 原表記 / slug) → slug。語彙に無ければ null */
    public function slug(string $tax, string $value): ?string {
        return $this->name2slug[$tax][$value] ?? null;
    }

    /** 全 taxonomy の slug の集合: tax => [slug => 1] (verify の語彙整合が WP の term 集合と比べる) */
    public function slug_sets(): array {
        $out = [];
        foreach ($this->name2slug as $tax => $m) foreach ($m as $slug) $out[$tax][$slug] = 1;
        return $out;
    }

    /** 話に付く共有世界の slug (terms.json の episode_worlds。無ければ空) */
    public function episode_worlds(string $episode_id): array {
        return $this->ep_worlds[$episode_id] ?? [];
    }

    /**
     * 値 (name / raw variant / slug) の配列 → [['value' => 元の値, 'slug' => slug か null], …] (値の順のまま)。
     * slug が null は、語彙を持つ taxonomy で対応表に無い値 (場当たり term は作らず、呼び出し側が記録して捨てる)。
     * 文字列でないもの・空文字・旧目録の「?」単独 (指定なしの印) は黙って飛ばす。
     * 語彙を持たない taxonomy (ts_author) は値を slug とみなす — 生値で get_term_by すると sanitize_title の
     * 偶然一致で誤割当になるので、この fallback は ts_author に限る。
     */
    public function resolve(string $tax, $values): array {
        $out = [];
        foreach ((array) $values as $v) {
            if (!is_string($v) || $v === '') continue;
            if (preg_match('/^[?？()（）\s]+$/u', $v)) continue; // 旧目録の「?」単独 = 指定なしの印
            if (isset($this->name2slug[$tax]) && !isset($this->name2slug[$tax][$v])) {
                $out[] = ['value' => $v, 'slug' => null];
                continue;
            }
            $out[] = ['value' => $v, 'slug' => $this->name2slug[$tax][$v] ?? $v];
        }
        return $out;
    }
}
