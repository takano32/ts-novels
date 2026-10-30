<?php
/**
 * `wp ts` — catalog → WordPress の投入コマンド群 (T.8.2)。
 *
 * ここは移築の根幹コード (本番 DB に書く唯一の経路)。設計の要点:
 *  - 冪等: episode_id / work_slug をメタキーに upsert。`_ts_import_hash` が同じなら skip。
 *    受け入れ条件は「2 回連続実行で 2 回目が created=0 / updated=0」
 *  - 手動編集の保護: 前回 import 後に管理画面で編集された投稿は上書きせず警告に出す
 *    (--overwrite-manual でのみ上書き)。post_status には決して触れない (新規作成時のみ publish)
 *  - 追跡: 投入時の catalog の git コミットを option `ts_catalog_commit` に記録
 *  - DB は使い捨て: `reset --yes` は ts_* の投稿と ts_* タクソノミーの term だけを消す
 *    (WP 本体設定には触れない)
 *
 * PHP 8.0 互換。WP-CLI からのみ動く (Web リクエストでは何もしない)。
 */

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class TS_Command {

    private const TYPES = ['ts_work', 'ts_doc', 'ts_dojo', 'ts_board_post'];
    private const TAXES = ['ts_author', 'ts_genre', 'ts_type', 'ts_keyword', 'ts_world', 'ts_corpus'];

    /** 固定ページの 2 行目に置く所有者文責の注記 `<!-- owner-authored: YYYY-MM-DD -->` (S.109) */
    private const OWNER_LINE_RE = '/^<!--\s*owner-authored:\s*\d{4}-\d{2}-\d{2}\s*-->[ \t]*\R?/u';

    /** 割当ロジックを変えたら上げる (既存投稿が hash 不一致になり、reset なしでも収束し直す) */
    private const HASH_VER = 'v5'; // v5: _ts_episode_count / _ts_alias_paths を書くようになった

    /** authors.json に載らない擬似作者 (works.jsonl の author_slug に現れる)。sync-terms が term を作る */
    private const PSEUDO_AUTHORS = ['unattributed' => '作者不詳', 'series-index' => '（シリーズ目録）'];

    private $name2slug = [];    // tax => [name / raw variant / slug => slug] (terms.json 由来)
    private $slug2term = [];    // tax => [slug => term_id] (0 = WP に無い)
    private $ep_worlds = [];    // episode_id => [world slug] (terms.json の episode_worlds)
    private $term_missing = []; // "tax:値" => 件数。解決できず割当を落とした値 (場当たり term は作らない)
    private $body_status = [];  // episode_id => converted / raw-fallback / takedown (payloads/_meta.json)

    /** terms.json の語彙を対応表に積む。term は必ず slug→ID で解決する (name 解決は
     *  term_exists() が slug 照合を先にやるため sanitize_title の衝突で誤爆する — S.182 の実測: 語彙外 term の増殖) */
    private function load_vocab($root) {
        $t = $this->read_json("$root/catalog/terms.json");
        foreach ($t['taxonomies'] as $tax => $spec) {
            foreach ($spec['terms'] as $term) {
                $this->name2slug[$tax][$term['slug']] = $term['slug'];
                $this->name2slug[$tax][$term['name']] = $term['slug'];
                foreach ($term['raw_variants'] ?? [] as $rv) $this->name2slug[$tax][$rv] = $term['slug'];
                // NFKC 形 (episodes.jsonl の正規化欄が持つ形)。原表記とも正規化名とも違う中間形
                foreach ($term['lookup_variants'] ?? [] as $lv) $this->name2slug[$tax][$lv] = $term['slug'];
            }
        }
        $this->ep_worlds = $t['episode_worlds'] ?? [];
    }

    /** 値 (name / raw variant / slug) の配列 → term ID の配列。未解決は記録して捨てる */
    private function term_ids($tax, $values) {
        $ids = [];
        foreach ((array) $values as $v) {
            if (!is_string($v) || $v === '') continue;
            if (preg_match('/^[?？()（）\s]+$/u', $v)) continue; // 旧目録の「?」単独 = 指定なしの印
            // 語彙を持つ taxonomy で対応表に無い値は落として記録する (slug とみなす fallback は
            // ts_author のみ — 生値で get_term_by すると sanitize_title の偶然一致で誤割当になる)
            if (isset($this->name2slug[$tax]) && !isset($this->name2slug[$tax][$v])) {
                $this->term_missing["$tax:$v"] = ($this->term_missing["$tax:$v"] ?? 0) + 1;
                continue;
            }
            $slug = $this->name2slug[$tax][$v] ?? $v;
            if (!isset($this->slug2term[$tax][$slug])) {
                $term = get_term_by('slug', $slug, $tax);
                $this->slug2term[$tax][$slug] = $term ? (int) $term->term_id : 0;
            }
            if ($this->slug2term[$tax][$slug]) $ids[] = $this->slug2term[$tax][$slug];
            else $this->term_missing["$tax:$v"] = ($this->term_missing["$tax:$v"] ?? 0) + 1;
        }
        return array_values(array_unique($ids));
    }

    /** works.jsonl の索引: [episode_id→work_slug, 単発 work の集合, work_slug→author_slug] */
    private function index_works($works) {
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

    /** 子投稿 (話) の URL slug を決定的に割り当てる。原ファイル名の語幹 → 作品内で重複したら
     *  親ディレクトリ前置 → 日付後置 → episodes.jsonl 順の連番 (catalog がコミット済みなので決定的) */
    private function child_slugs($episodes, $ep2work, $single_work) {
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
                $clean = sanitize_title($slugs[$eid]);
                $out[$eid] = $clean !== '' ? $clean : sanitize_title($eid);
            }
        }
        return $out;
    }

    /** repo ルート (サーバでは ~/novels.xwp.jp/repo を想定。--repo で上書き可) */
    private function repo_root($assoc) {
        $root = $assoc['repo'] ?? (getenv('TS_REPO') ?: (getenv('HOME') . '/novels.xwp.jp/repo'));
        if (!is_dir($root)) {
            WP_CLI::error("repo が見つかりません: $root (--repo=<path> か TS_REPO を指定)");
        }
        return rtrim($root, '/');
    }

    private function read_jsonl($path) {
        if (!is_file($path)) WP_CLI::error("ファイルがありません: $path");
        $out = [];
        $fh = fopen($path, 'r');
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') continue;
            $row = json_decode($line, true);
            if ($row === null) WP_CLI::error("JSONL の壊れた行: $path");
            $out[] = $row;
        }
        fclose($fh);
        return $out;
    }

    private function read_json($path) {
        if (!is_file($path)) WP_CLI::error("ファイルがありません: $path");
        $d = json_decode(file_get_contents($path), true);
        if ($d === null) WP_CLI::error("JSON が読めません: $path");
        return $d;
    }

    /** meta キーで投稿を 1 件引く (post_status を問わない) */
    private function find_by_meta($type, $key, $value) {
        $q = new WP_Query([
            'post_type' => $type, 'post_status' => 'any', 'posts_per_page' => 2,
            'meta_key' => $key, 'meta_value' => $value,
            'fields' => 'ids', 'no_found_rows' => true,
            'update_post_meta_cache' => false, 'update_post_term_cache' => false,
        ]);
        if (count($q->posts) > 1) {
            WP_CLI::warning("重複検出: $type $key=$value が " . count($q->posts) . " 件");
        }
        return $q->posts[0] ?? null;
    }

    /** 手動編集の検出: 前回 import 記録より post_modified_gmt が新しいか */
    private function manually_edited($post_id) {
        $last = get_post_meta($post_id, '_ts_last_import_gmt', true);
        if (!$last) return false;
        $mod = get_post_field('post_modified_gmt', $post_id);
        return $mod && strtotime($mod) > strtotime($last) + 2; // 2 秒の猶予 (同一トランザクション内更新)
    }

    /** 単発 work は work 側 (_ts_import_hash) と fill 側 (_ts_fill_hash) が別キー —
     *  同じキーを奪い合うと毎回 updated=2 になり冪等が壊れる (S.182 の実測: 冪等性の不具合) */
    private function stamp_import($post_id, $hash, $key = '_ts_import_hash') {
        update_post_meta($post_id, $key, $hash);
        // 直前の update で post_modified が動いた「後」の値を記録する
        clean_post_cache($post_id);
        update_post_meta($post_id, '_ts_last_import_gmt', get_post_field('post_modified_gmt', $post_id));
    }

    // ------------------------------------------------------------------ sync-terms

    /**
     * タクソノミー term を catalog から同期する。
     *
     * ## OPTIONS
     * [--repo=<path>] : リポジトリのルート
     * [--dry-run] : 書き込まない
     */
    public function sync_terms($args, $assoc) {
        $root = $this->repo_root($assoc);
        $dry = isset($assoc['dry-run']);
        $terms = $this->read_json("$root/catalog/terms.json");
        $authors = $this->read_json("$root/catalog/authors.json");
        if (isset($authors['authors'])) $authors = $authors['authors'];
        $n = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($terms['taxonomies'] as $tax => $spec) {
            if (!taxonomy_exists($tax)) WP_CLI::error("タクソノミー未登録: $tax (mu-plugin の登録を確認)");
            foreach ($spec['terms'] as $t) {
                $this->upsert_term($tax, $t['slug'], $t['name'],
                    ['description' => $t['description'] ?? ''],
                    ['ts_raw_variants' => $t['raw_variants'] ?? null,
                     'ts_core' => isset($t['core']) ? (int) $t['core'] : null,
                     'ts_count_catalog' => $t['count'] ?? null],
                    $dry, $n);
            }
        }
        foreach ($authors as $a) {
            $this->upsert_term('ts_author', $a['slug'], $a['display_name'], [],
                ['ts_display_variants' => $a['display_variants'] ?? null,
                 'ts_yomi_group' => $a['yomi_group'] ?? null,
                 'ts_kansou_slug' => $a['kansou_slug'] ?? null,
                 'ts_kansou_annex_url' => $a['kansou_annex_url'] ?? null,
                 'ts_homepage_wayback' => $a['homepage_wayback'] ?? null,
                 'ts_homepage_live' => $a['homepage_live'] ?? null,
                 'ts_active_links' => $a['active_links'] ?? null,
                 'ts_contact_status' => $a['contact_status'] ?? 'uncontacted'],
                $dry, $n);
        }
        foreach (self::PSEUDO_AUTHORS as $slug => $name) {
            $this->upsert_term('ts_author', $slug, $name, [], ['ts_pseudo' => 1], $dry, $n);
        }
        WP_CLI::success(sprintf('sync-terms: created=%d updated=%d skipped=%d%s',
            $n['created'], $n['updated'], $n['skipped'], $dry ? ' (dry-run)' : ''));
    }

    private function upsert_term($tax, $slug, $name, $core, $meta, $dry, &$n) {
        $existing = get_term_by('slug', $slug, $tax);
        $payload_hash = md5(wp_json_encode([$name, $core, $meta]));
        if ($existing) {
            if (get_term_meta($existing->term_id, '_ts_term_hash', true) === $payload_hash) {
                $n['skipped']++; return;
            }
            if (!$dry) {
                wp_update_term($existing->term_id, $tax, array_merge(['name' => $name], $core));
                $this->write_term_meta($existing->term_id, $meta, $payload_hash);
            }
            $n['updated']++;
        } else {
            if (!$dry) {
                $r = wp_insert_term($name, $tax, array_merge(['slug' => $slug], $core));
                if (is_wp_error($r)) { WP_CLI::warning("term 作成失敗 $tax/$slug: " . $r->get_error_message()); return; }
                $this->write_term_meta($r['term_id'], $meta, $payload_hash);
            }
            $n['created']++;
        }
    }

    private function write_term_meta($term_id, $meta, $hash) {
        foreach ($meta as $k => $v) {
            if ($v === null) { delete_term_meta($term_id, $k); continue; }
            update_term_meta($term_id, $k, $v);
        }
        update_term_meta($term_id, '_ts_term_hash', $hash);
    }

    // ------------------------------------------------------------------ import

    /**
     * works / episodes を投入する (冪等 upsert)。
     *
     * ## OPTIONS
     * [--repo=<path>] : リポジトリのルート
     * [--bodies=<dir>] : 本文ペイロード (<episode_id>.html の Gutenberg ブロック HTML)
     * [--limit=<n>] / [--author=<slug>] / [--dry-run] / [--overwrite-manual]
     */
    public function import($args, $assoc) {
        $root = $this->repo_root($assoc);
        $dry = isset($assoc['dry-run']);
        $overwrite_manual = isset($assoc['overwrite-manual']);
        $bodies = isset($assoc['bodies']) ? rtrim($assoc['bodies'], '/') : null;
        if ($bodies !== null && is_file("$bodies/_meta.json")) {
            $this->body_status = json_decode(file_get_contents("$bodies/_meta.json"), true) ?: [];
        }
        $limit = isset($assoc['limit']) ? (int) $assoc['limit'] : 0;
        $only_author = $assoc['author'] ?? null;

        $episodes = $this->read_jsonl("$root/catalog/episodes.jsonl");
        $works = $this->read_jsonl("$root/catalog/works.jsonl");
        $this->load_vocab($root);
        $n = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'manual_skipped' => 0];
        $manual = [];

        // works を先に (親投稿)
        [$ep2work, $single_work, $work_author, $ep_order] = $this->index_works($works);
        $slugs = $this->child_slugs($episodes, $ep2work, $single_work);

        $work_ids = [];
        foreach ($works as $w) {
            if ($only_author && ($w['author_slug'] ?? '') !== $only_author) continue;
            $work_ids[$w['work_slug']] = $this->upsert_work($w, $dry, $overwrite_manual, $n, $manual);
        }

        $done = 0;
        $parent_terms = []; // 連載の親 work へ子の term を合併して付ける (書庫に連載を出すため)
        foreach ($episodes as $ep) {
            $wslug = $ep2work[$ep['episode_id']] ?? null;
            if ($only_author && !isset($work_ids[$wslug])) continue;
            if ($limit && $done >= $limit) break;
            if (!$dry && $wslug !== null && !isset($single_work[$wslug])) {
                foreach ([['genre', 'ts_genre'], ['type', 'ts_type'], ['keywords', 'ts_keyword']] as [$f, $tax]) {
                    foreach ($this->term_ids($tax, $ep[$f] ?? []) as $tid) $parent_terms[$wslug][$tax][$tid] = 1;
                }
                foreach ($this->term_ids('ts_world', $this->ep_worlds[$ep['episode_id']] ?? []) as $tid) {
                    $parent_terms[$wslug]['ts_world'][$tid] = 1;
                }
                foreach ($this->term_ids('ts_corpus', [$ep['corpus']]) as $tid) {
                    $parent_terms[$wslug]['ts_corpus'][$tid] = 1;
                }
            }
            // 作者 term は作品の author_slug からのみ付く。kansou_slug は板の slug であって
            // 作者ではない (共有世界板で別人になる — S.182 の実測: 共有世界の板を作者と誤認) ので、メタに置くだけ
            $author = $wslug !== null ? ($work_author[$wslug] ?? '') : '';
            // 単発作品は親 (work 投稿) が本文ごと担う — episode の子投稿は作らない
            if ($wslug !== null && isset($single_work[$wslug])) {
                $this->fill_single_work($work_ids[$wslug] ?? null, $ep, $bodies, $dry, $overwrite_manual, $n, $manual);
                $done++;
                continue;
            }
            $parent_id = $wslug !== null ? ($work_ids[$wslug] ?? null) : null;
            $this->upsert_episode($ep, $parent_id, $author, $slugs[$ep['episode_id']] ?? '',
                $ep_order[$ep['episode_id']] ?? 0, $bodies, $dry, $overwrite_manual, $n, $manual);
            $done++;
        }

        foreach ($parent_terms as $wslug => $taxes) {
            $pid = $work_ids[$wslug] ?? null;
            if (!$pid) continue;
            foreach ($taxes as $tax => $ids) {
                wp_set_object_terms($pid, array_map('intval', array_keys($ids)), $tax);
            }
        }

        // 追跡: いま投入した catalog のコミット
        $commit = trim((string) shell_exec("git -C " . escapeshellarg($root) . " rev-parse HEAD 2>/dev/null"));
        if (!$dry && $commit) {
            update_option('ts_catalog_commit', $commit, false);
            update_option('ts_last_import_at', gmdate('c'), false);
        }
        if ($manual) {
            update_option('ts_manual_edit_warnings', $manual, false);
            WP_CLI::warning('手動編集を検出して skip した投稿: ' . count($manual)
                . ' 件 (`wp ts verify` で一覧。上書きするには --overwrite-manual)');
        } elseif (!$dry) {
            delete_option('ts_manual_edit_warnings');
        }
        if ($this->term_missing) {
            arsort($this->term_missing);
            if (!$dry) update_option('ts_term_warnings', $this->term_missing, false);
            WP_CLI::warning('term に解決できず割当を落とした値: ' . count($this->term_missing) . ' 種 (上位: '
                . implode(' ', array_slice(array_keys($this->term_missing), 0, 5)) . ')');
        } elseif (!$dry && !$limit && !$only_author) {
            delete_option('ts_term_warnings');
        }
        WP_CLI::success(sprintf('import: created=%d updated=%d skipped=%d manual_skipped=%d catalog=%s%s',
            $n['created'], $n['updated'], $n['skipped'], $n['manual_skipped'],
            substr($commit, 0, 12), $dry ? ' (dry-run)' : ''));
    }

    private function upsert_work($w, $dry, $ow, &$n, &$manual) {
        $hash = md5(self::HASH_VER . wp_json_encode($w));
        $post_id = $this->find_by_meta('ts_work', '_ts_work_slug', $w['work_slug']);
        if ($post_id && get_post_meta($post_id, '_ts_import_hash', true) === $hash) { $n['skipped']++; return $post_id; }
        if ($post_id && !$ow && $this->manually_edited($post_id)) {
            $n['manual_skipped']++; $manual[] = ['kind' => 'work', 'key' => $w['work_slug'],
                'modified' => get_post_field('post_modified_gmt', $post_id)];
            return $post_id;
        }
        $postarr = [
            'post_type' => 'ts_work', 'post_title' => $w['title'],
            'post_name' => $w['work_slug'], 'comment_status' => 'closed', 'ping_status' => 'closed',
        ];
        if ($w['first_date'] ?? null) $postarr['post_date'] = $w['first_date'] . ' 00:00:00';
        if ($dry) { $n[$post_id ? 'updated' : 'created']++; return $post_id; }
        if ($post_id) { $postarr['ID'] = $post_id; wp_update_post($postarr); }
        else { $postarr['post_status'] = 'publish'; $post_id = wp_insert_post($postarr); }
        if (!$post_id || is_wp_error($post_id)) { WP_CLI::warning('work 投入失敗: ' . $w['work_slug']); return null; }

        update_post_meta($post_id, '_ts_work_slug', $w['work_slug']);
        update_post_meta($post_id, '_ts_kind', 'work');
        update_post_meta($post_id, '_ts_needs_review', !empty($w['needs_review']) ? 1 : 0);
        update_post_meta($post_id, '_ts_title_pages', $w['title_pages'] ?? []);
        update_post_meta($post_id, '_ts_alias_paths', $w['alias_paths'] ?? []);
        // 話数はカードで出す (毎回 get_children を投げないため)
        update_post_meta($post_id, '_ts_episode_count', (int) ($w['episode_count'] ?? 0));
        wp_set_object_terms($post_id, $this->term_ids('ts_author', [$w['author_slug'] ?? '']), 'ts_author');
        $this->stamp_import($post_id, $hash);
        $n[$postarr['ID'] ?? null ? 'updated' : 'created']++;
        return $post_id;
    }

    /** episode の共通メタ・タクソノミーを投稿に書く。$author_slug=null は作者 term に触らない
     *  (単発 work — upsert_work が付けた作者を消さないため。S.182 の実測: 単発作品の作者欠落) */
    private function apply_episode_data($post_id, $ep, $author_slug) {
        $meta = [
            '_ts_episode_id' => $ep['episode_id'],
            '_ts_source_path' => $ep['source_path'], '_ts_source_anchor' => $ep['source_anchor'],
            '_ts_corpus' => $ep['corpus'], '_ts_catalog_ref' => $ep['catalog_ref'],
            '_ts_pub_date_raw' => $ep['date_raw'], '_ts_size_kb' => $ep['size_kb'],
            '_ts_files_n' => $ep['files_n'],
            '_ts_illustrator' => $ep['illustrator'], '_ts_illustrator_url' => $ep['illustrator_url'],
            '_ts_kansou_slug' => $ep['kansou_slug'], '_ts_kansou_annex_url' => $ep['kansou_annex_url'],
            '_ts_arasuji' => $ep['arasuji'], '_ts_author_comment' => $ep['comment'],
            '_ts_suisen' => $ep['suisen'], '_ts_osusume' => $ep['osusume'],
            '_ts_nav_links' => $ep['nav_links'],
            '_ts_orig_url' => $ep['orig_url'], '_ts_annex_url' => $ep['annex_url'],
            '_ts_annex_yays_url' => $ep['annex_yays_url'],
            '_ts_provenance' => $ep['provenance'],
            '_ts_raw_genre' => $ep['genre_raw'], '_ts_raw_type' => $ep['type_raw'],
            '_ts_raw_keywords' => $ep['keywords_raw'], '_ts_zokusei' => $ep['zokusei'],
        ];
        foreach ($meta as $k => $v) {
            if ($v === null || $v === []) delete_post_meta($post_id, $k);
            else update_post_meta($post_id, $k, $v);
        }
        if ($author_slug !== null) {
            wp_set_object_terms($post_id, $this->term_ids('ts_author', [$author_slug]), 'ts_author');
        }
        wp_set_object_terms($post_id, $this->term_ids('ts_corpus', [$ep['corpus']]), 'ts_corpus');
        foreach ([['genre', 'ts_genre'], ['type', 'ts_type'], ['keywords', 'ts_keyword']] as [$field, $tax]) {
            wp_set_object_terms($post_id, $this->term_ids($tax, $ep[$field] ?? []), $tax);
        }
        wp_set_object_terms($post_id,
            $this->term_ids('ts_world', $this->ep_worlds[$ep['episode_id']] ?? []), 'ts_world');
    }

    private function episode_body($ep, $bodies) {
        if ($bodies === null) return null;                 // メタのみ投入
        // 取り下げ対象は payload_build.py が payload を作らず _meta.json に 'takedown' を書く
        // (本文データを作るときに飛ばす論理削除。specs/S.76.md)。サーバに前回の payload が
        // 残っていても読まず、案内文も入れずに本文を空で投入する (非公開化は apply-takedown)
        if (($this->body_status[$ep['episode_id']] ?? '') === 'takedown') return '';
        $f = "$bodies/{$ep['episode_id']}.html";
        if (is_file($f)) return file_get_contents($f);
        return "<!-- wp:paragraph --><p>(本文は原本アーカイブでお読みください)</p><!-- /wp:paragraph -->";
    }

    private function upsert_episode($ep, $parent_id, $author_slug, $post_name, $order, $bodies, $dry, $ow, &$n, &$manual) {
        $body = $this->episode_body($ep, $bodies);
        $hash = md5(self::HASH_VER . wp_json_encode($ep) . $post_name . $order
            . ($body === null ? '' : md5($body)));
        $post_id = $this->find_by_meta('ts_work', '_ts_episode_id', $ep['episode_id']);
        if ($post_id && get_post_meta($post_id, '_ts_import_hash', true) === $hash) { $n['skipped']++; return; }
        if ($post_id && !$ow && $this->manually_edited($post_id)) {
            $n['manual_skipped']++; $manual[] = ['kind' => 'episode', 'key' => $ep['episode_id'],
                'modified' => get_post_field('post_modified_gmt', $post_id)];
            return;
        }
        if ($dry) { $n[$post_id ? 'updated' : 'created']++; return; }
        $postarr = [
            'post_type' => 'ts_work', 'post_title' => $ep['title'] ?: $ep['episode_id'],
            'comment_status' => 'closed', 'ping_status' => 'closed',
        ];
        // URL slug は原ファイル名の語幹から決定的に (未指定だと題名由来のパーセントエンコードになる)
        if ($post_name !== '') $postarr['post_name'] = $post_name;
        if ($order) $postarr['menu_order'] = $order; // 話ナビの並び順 (works.jsonl の episodes 順)
        if ($parent_id) $postarr['post_parent'] = $parent_id;
        if ($ep['date'] ?? null) $postarr['post_date'] = $ep['date'] . ' 00:00:00';
        if ($body !== null) $postarr['post_content'] = $body;
        $creating = !$post_id;
        if ($post_id) { $postarr['ID'] = $post_id; wp_update_post($postarr); }
        else { $postarr['post_status'] = 'publish'; $post_id = wp_insert_post($postarr); }
        if (!$post_id || is_wp_error($post_id)) { WP_CLI::warning('episode 投入失敗: ' . $ep['episode_id']); return; }
        update_post_meta($post_id, '_ts_kind', 'episode');
        if ($body !== null) {
            update_post_meta($post_id, '_ts_body_status',
                $this->body_status[$ep['episode_id']] ?? 'placeholder');
        }
        $this->apply_episode_data($post_id, $ep, $author_slug);
        $this->stamp_import($post_id, $hash);
        $n[$creating ? 'created' : 'updated']++;
    }

    /** 単発作品: 親 work 投稿に episode の本文とメタを載せる。hash は work 側と別キー (S.182 の実測: 冪等性の不具合) */
    private function fill_single_work($post_id, $ep, $bodies, $dry, $ow, &$n, &$manual) {
        if (!$post_id) return;
        $body = $this->episode_body($ep, $bodies);
        $hash = md5(self::HASH_VER . 'single:' . wp_json_encode($ep) . ($body === null ? '' : md5($body)));
        if (get_post_meta($post_id, '_ts_fill_hash', true) === $hash) { $n['skipped']++; return; }
        if (!$ow && $this->manually_edited($post_id)) {
            $n['manual_skipped']++; $manual[] = ['kind' => 'single-work', 'key' => $ep['episode_id'],
                'modified' => get_post_field('post_modified_gmt', $post_id)];
            return;
        }
        if ($dry) { $n['updated']++; return; }
        $up = ['ID' => $post_id];
        if ($ep['date'] ?? null) $up['post_date'] = $ep['date'] . ' 00:00:00';
        if ($body !== null) $up['post_content'] = $body;
        wp_update_post($up);
        update_post_meta($post_id, '_ts_kind', 'work'); // 単発は work=episode の 1 投稿
        if ($body !== null) {
            update_post_meta($post_id, '_ts_body_status',
                $this->body_status[$ep['episode_id']] ?? 'placeholder');
        }
        $this->apply_episode_data($post_id, $ep, null); // 作者は upsert_work が付けた分を保つ
        $this->stamp_import($post_id, $hash, '_ts_fill_hash');
        $n['updated']++;
    }

    /**
     * 固定ページ (/about/ 等) を content/pages/*.html から同期する (T.10.5)。
     * 1 行目の <!-- title: … --> が題。冪等 (hash skip)・手動編集は保護。
     *
     * ## OPTIONS
     * [--repo=<path>] : リポジトリのルート
     * [--overwrite-manual] : 管理画面での編集を上書きする
     */
    public function sync_pages($args, $assoc) {
        $root = $this->repo_root($assoc);
        $ow = isset($assoc['overwrite-manual']);
        $files = glob("$root/content/pages/*.html") ?: [];
        if (!$files) WP_CLI::error("content/pages/*.html がありません");
        $n = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'manual_skipped' => 0];
        foreach ($files as $f) {
            $slug = basename($f, '.html');
            $html = file_get_contents($f);
            $title = preg_match('/<!--\s*title:\s*(.+?)\s*-->/u', $html, $m) ? $m[1] : $slug;
            $body = trim(preg_replace('/^<!--\s*title:.*?-->\s*/su', '', $html));
            $body = trim(preg_replace(self::OWNER_LINE_RE, '', $body)); // 所有者文責の注記は本文に入れない (S.109)
            $hash = md5($title . $body);
            $page = get_page_by_path($slug, OBJECT, 'page');
            if ($page && get_post_meta($page->ID, '_ts_import_hash', true) === $hash) { $n['skipped']++; continue; }
            if ($page && !$ow && $this->manually_edited($page->ID)) {
                $n['manual_skipped']++;
                WP_CLI::warning("手動編集を保護して skip: /$slug/ (--overwrite-manual で上書き)");
                continue;
            }
            $postarr = ['post_type' => 'page', 'post_title' => $title, 'post_name' => $slug,
                        'post_content' => $body, 'comment_status' => 'closed', 'ping_status' => 'closed'];
            if ($page) { $postarr['ID'] = $page->ID; $id = wp_update_post($postarr); $n['updated']++; }
            else { $postarr['post_status'] = 'publish'; $id = wp_insert_post($postarr); $n['created']++; }
            if ($id && !is_wp_error($id)) $this->stamp_import($id, $hash);
        }
        WP_CLI::success(sprintf('sync-pages: created=%d updated=%d skipped=%d manual_skipped=%d',
            $n['created'], $n['updated'], $n['skipped'], $n['manual_skipped']));
    }

    /**
     * 公開中の固定ページを content/pages/<post_name>.html に書き戻す (T.27.2、S.109)。
     * 1 行目は <!-- title: … -->、以降は post_content を無加工で書く。既存ファイルの 2 行目に
     * <!-- owner-authored: YYYY-MM-DD --> があればその行を保つ (sync-pages はこの行を本文から除く)。
     * WP には書き込まない。書き戻した直後の sync-pages は、手動編集の無いページなら skipped になる。
     * 管理画面で編集されたページは manual_skipped になるので、git に入れた後は
     * sync-pages --overwrite-manual で取り込み記録を付け直す (内容は同じなので本文は変わらない)。
     *
     * ## OPTIONS
     * [--repo=<path>] : リポジトリのルート
     * [--slug=<name>] : この post_name の 1 枚だけ
     * [--dry-run] : 書き込まず、ページごとの差分の行数だけ表示する
     */
    public function export_pages($args, $assoc) {
        $root = $this->repo_root($assoc);
        $dry = isset($assoc['dry-run']);
        $dir = "$root/content/pages";
        if (!is_dir($dir)) WP_CLI::error("ディレクトリがありません: $dir");
        $q = ['post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1,
              'orderby' => 'name', 'order' => 'ASC'];
        if (isset($assoc['slug'])) $q['name'] = $assoc['slug'];
        $pages = get_posts($q);
        if (!$pages) WP_CLI::error(isset($assoc['slug']) ? "公開中の固定ページがありません: {$assoc['slug']}" : '公開中の固定ページがありません');
        $n = ['written' => 0, 'unchanged' => 0];
        foreach ($pages as $page) {
            $slug = $page->post_name;
            if ($slug === '' || !preg_match('/^[a-z0-9_-]+$/', $slug)) {
                WP_CLI::warning("post_name がファイル名に使えないので skip: ID {$page->ID} ($slug)");
                continue;
            }
            $path = "$dir/$slug.html";
            $old = is_file($path) ? file_get_contents($path) : '';
            $owner = '';
            $old_lines = preg_split('/\R/u', $old);
            if (isset($old_lines[1]) && preg_match(self::OWNER_LINE_RE, $old_lines[1])) $owner = rtrim($old_lines[1]) . "\n";
            $new = '<!-- title: ' . $page->post_title . " -->\n" . $owner . rtrim($page->post_content, "\r\n") . "\n";
            if ($new === $old) { $n['unchanged']++; continue; }
            if ($dry) {
                [$total, $add, $del] = $this->line_diff_count($old, $new);
                WP_CLI::line(sprintf('content/pages/%s.html: 差分 %d 行 (+%d -%d)%s',
                    $slug, $total, $add, $del, $old === '' ? ' 新規' : ''));
            } else {
                if (file_put_contents($path, $new) === false) WP_CLI::error("書き込めません: $path");
                WP_CLI::line("書き戻し: content/pages/$slug.html");
            }
            $n['written']++;
        }
        WP_CLI::success(sprintf('export-pages%s: %s=%d unchanged=%d', $dry ? ' (dry-run)' : '',
            $dry ? 'changed' : 'written', $n['written'], $n['unchanged']));
    }

    /** 2 つの文字列の行単位の差分の行数 [合計, 追加, 削除] (LCS で数える) */
    private function line_diff_count($a, $b) {
        $x = $a === '' ? [] : preg_split('/\R/u', rtrim($a, "\r\n"));
        $y = $b === '' ? [] : preg_split('/\R/u', rtrim($b, "\r\n"));
        $m = count($x); $k = count($y);
        $prev = array_fill(0, $k + 1, 0);
        for ($i = 1; $i <= $m; $i++) {
            $cur = [0];
            for ($j = 1; $j <= $k; $j++) {
                $cur[$j] = $x[$i - 1] === $y[$j - 1] ? $prev[$j - 1] + 1 : max($prev[$j], $cur[$j - 1]);
            }
            $prev = $cur;
        }
        $common = $prev[$k];
        return [$m + $k - 2 * $common, $k - $common, $m - $common];
    }

    /**
     * 運営文書・前史・ギャラリー (ts_doc) を payloads-docs/ から同期する (T.10.5)。
     * scripts/wp/docs_build.py の出力を読む。冪等・手動編集保護。
     *
     * ## OPTIONS
     * [--repo=<path>] / [--overwrite-manual]
     */
    public function sync_docs($args, $assoc) {
        $root = $this->repo_root($assoc);
        $ow = isset($assoc['overwrite-manual']);
        $dir = "$root/payloads-docs";
        $manifest = $this->read_json("$dir/_manifest.json");
        $n = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'manual_skipped' => 0];
        foreach ($manifest as $slug => $m) {
            if (!is_file("$dir/$slug.html")) { WP_CLI::warning("payload なし: $slug"); continue; }
            $body = file_get_contents("$dir/$slug.html");
            $hash = md5($m['title'] . $m['section'] . $body);
            $post_id = $this->find_by_meta('ts_doc', '_ts_doc_slug', $slug);
            if ($post_id && get_post_meta($post_id, '_ts_import_hash', true) === $hash) { $n['skipped']++; continue; }
            if ($post_id && !$ow && $this->manually_edited($post_id)) {
                $n['manual_skipped']++; continue;
            }
            $postarr = ['post_type' => 'ts_doc', 'post_title' => $m['title'], 'post_name' => $slug,
                        'post_content' => $body, 'comment_status' => 'closed', 'ping_status' => 'closed'];
            if ($post_id) { $postarr['ID'] = $post_id; wp_update_post($postarr); $n['updated']++; }
            else { $postarr['post_status'] = 'publish'; $post_id = wp_insert_post($postarr); $n['created']++; }
            if (!$post_id || is_wp_error($post_id)) { WP_CLI::warning("ts_doc 投入失敗: $slug"); continue; }
            update_post_meta($post_id, '_ts_doc_slug', $slug);
            update_post_meta($post_id, '_ts_section', $m['section']);
            update_post_meta($post_id, '_ts_section_label', $m['section_label']);
            update_post_meta($post_id, '_ts_source_path', $m['source_path']);
            $this->stamp_import($post_id, $hash);
        }
        WP_CLI::success(sprintf('sync-docs: created=%d updated=%d skipped=%d manual_skipped=%d',
            $n['created'], $n['updated'], $n['skipped'], $n['manual_skipped']));
    }

    // ------------------------------------------------------------------ verify / takedown / reset / pathmap

    /** 件数照合・orphan・語彙整合・taxonomy 割当数・手動編集警告を検査する。
     *  期待値は catalog から毎回再計算する (importer と独立に数え直し、取り違えを検出する)。 */
    public function verify($args, $assoc) {
        global $wpdb;
        $root = $this->repo_root($assoc);
        $episodes = $this->read_jsonl("$root/catalog/episodes.jsonl");
        $works = $this->read_jsonl("$root/catalog/works.jsonl");
        $this->load_vocab($root);
        $authors = $this->read_json("$root/catalog/authors.json");
        if (isset($authors['authors'])) $authors = $authors['authors'];
        [$ep2work, $single_work, $work_author] = $this->index_works($works);
        $ok = true;

        // 1) 投稿数
        $singles = count($single_work);
        $expect_posts = count($works) + count($episodes) - $singles; // 単発は 1 投稿に畳まれる
        $count = 0;
        foreach (wp_count_posts('ts_work') as $st => $c) if ($st !== 'trash') $count += (int) $c;
        WP_CLI::line(sprintf('投稿数: WP=%d 期待=%d (works %d + episodes %d - 単発 %d)',
            $count, $expect_posts, count($works), count($episodes), $singles));
        if ($count !== $expect_posts) { $ok = false; WP_CLI::warning('件数不一致'); }

        // 2) orphan: 親が消えている/ts_work でない子投稿
        $orphans = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->posts} q ON p.post_parent = q.ID
             WHERE p.post_type = 'ts_work' AND p.post_status <> 'trash' AND p.post_parent <> 0
               AND (q.ID IS NULL OR q.post_type <> 'ts_work')");
        WP_CLI::line("orphan 子投稿: $orphans");
        if ($orphans) { $ok = false; WP_CLI::warning('orphan あり'); }

        // 3) 語彙整合: WP の term 集合 = catalog の語彙 (場当たり term ゼロ・未作成ゼロ)
        $vocab = ['ts_author' => []];
        // WP は slug を小文字化する (karaage_New → karaage_new) ので小文字で照合する
        foreach ($authors as $a) $vocab['ts_author'][strtolower($a['slug'])] = 1;
        foreach (self::PSEUDO_AUTHORS as $slug => $name) $vocab['ts_author'][$slug] = 1;
        foreach ($this->name2slug as $tax => $m) foreach ($m as $slug) $vocab[$tax][$slug] = 1;
        foreach (self::TAXES as $tax) {
            $wp_slugs = get_terms(['taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'id=>slug']);
            if (is_wp_error($wp_slugs)) { $ok = false; WP_CLI::warning("$tax: get_terms 失敗"); continue; }
            $extra = array_diff($wp_slugs, array_keys($vocab[$tax] ?? []));
            $missing = array_diff(array_keys($vocab[$tax] ?? []), $wp_slugs);
            WP_CLI::line(sprintf('%s: WP=%d 語彙=%d 語彙外=%d 未作成=%d',
                $tax, count($wp_slugs), count($vocab[$tax] ?? []), count($extra), count($missing)));
            if ($extra) { $ok = false; WP_CLI::warning("$tax 語彙外 term: "
                . implode(' ', array_slice(array_values($extra), 0, 8))); }
            if ($missing) { $ok = false; WP_CLI::warning("$tax 未作成 term: "
                . implode(' ', array_slice(array_values($missing), 0, 8))); }
        }

        // 4) 割当数: catalog からの期待値 vs DB の term_relationships 実数
        $expect = array_fill_keys(self::TAXES, 0);
        foreach ($works as $w) {
            if (!empty($w['author_slug']) && isset($vocab['ts_author'][strtolower($w['author_slug'])])) $expect['ts_author']++;
        }
        foreach ($episodes as $ep) {
            $w = $ep2work[$ep['episode_id']] ?? null;
            if ($w !== null && !isset($single_work[$w])
                && !empty($work_author[$w]) && isset($vocab['ts_author'][strtolower($work_author[$w])])) {
                $expect['ts_author']++; // 子投稿は作品の作者を継ぐ
            }
            if (!empty($ep['corpus']) && isset($vocab['ts_corpus'][$this->name2slug['ts_corpus'][$ep['corpus']] ?? ''])) {
                $expect['ts_corpus']++;
            }
            foreach ([['genre', 'ts_genre'], ['type', 'ts_type'], ['keywords', 'ts_keyword']] as [$f, $tax]) {
                $uniq = [];
                foreach ((array) ($ep[$f] ?? []) as $v) {
                    $s = $this->name2slug[$tax][$v] ?? null;
                    if ($s !== null && isset($vocab[$tax][$s])) $uniq[$s] = 1;
                }
                $expect[$tax] += count($uniq);
            }
            $uniq = [];
            foreach ($this->ep_worlds[$ep['episode_id']] ?? [] as $v) if (isset($vocab['ts_world'][$v])) $uniq[$v] = 1;
            $expect['ts_world'] += count($uniq);
        }
        // 連載の親 work への合併付与ぶん (import の $parent_terms と同じ計算)
        $parent_expect = [];
        foreach ($episodes as $ep) {
            $w = $ep2work[$ep['episode_id']] ?? null;
            if ($w === null || isset($single_work[$w])) continue;
            foreach ([['genre', 'ts_genre'], ['type', 'ts_type'], ['keywords', 'ts_keyword']] as [$f, $tax]) {
                foreach ((array) ($ep[$f] ?? []) as $v) {
                    $s = $this->name2slug[$tax][$v] ?? null;
                    if ($s !== null && isset($vocab[$tax][$s])) $parent_expect[$w][$tax][$s] = 1;
                }
            }
            foreach ($this->ep_worlds[$ep['episode_id']] ?? [] as $v) {
                if (isset($vocab['ts_world'][$v])) $parent_expect[$w]['ts_world'][$v] = 1;
            }
            if (!empty($ep['corpus']) && isset($vocab['ts_corpus'][$this->name2slug['ts_corpus'][$ep['corpus']] ?? ''])) {
                $parent_expect[$w]['ts_corpus'][$this->name2slug['ts_corpus'][$ep['corpus']]] = 1;
            }
        }
        foreach ($parent_expect as $w => $taxes) {
            foreach ($taxes as $tax => $set) $expect[$tax] += count($set);
        }

        $actual = array_fill_keys(self::TAXES, 0);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT tt.taxonomy, COUNT(*) c FROM {$wpdb->term_relationships} tr
             JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             JOIN {$wpdb->posts} p ON p.ID = tr.object_id
             WHERE p.post_type = 'ts_work' AND p.post_status <> 'trash'
               AND tt.taxonomy IN (%s,%s,%s,%s,%s,%s) GROUP BY tt.taxonomy", self::TAXES));
        foreach ($rows as $r) $actual[$r->taxonomy] = (int) $r->c;
        foreach (self::TAXES as $tax) {
            WP_CLI::line(sprintf('割当 %s: WP=%d 期待=%d', $tax, $actual[$tax], $expect[$tax]));
            if ($actual[$tax] !== $expect[$tax]) { $ok = false; WP_CLI::warning("$tax の割当数不一致"); }
        }

        // 4b) 書誌カード用メタの被覆 (T.9.4): catalog の非空数 = DB の meta 行数
        $meta_expect = ['_ts_pub_date_raw' => 'date_raw', '_ts_orig_url' => 'orig_url',
                        '_ts_annex_url' => 'annex_url', '_ts_provenance' => 'provenance'];
        $cnt = array_fill_keys(array_keys($meta_expect), 0);
        foreach ($episodes as $ep) {
            foreach ($meta_expect as $mk => $f) {
                $v = $ep[$f] ?? null;
                if ($v !== null && $v !== '' && $v !== []) $cnt[$mk]++;
            }
        }
        foreach ($meta_expect as $mk => $f) {
            $actual = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = %s AND p.post_type = 'ts_work' AND p.post_status <> 'trash'",
                $mk));
            WP_CLI::line(sprintf('メタ %s: WP=%d 期待=%d', $mk, $actual, $cnt[$mk]));
            if ($actual !== $cnt[$mk]) { $ok = false; WP_CLI::warning("$mk の被覆不一致"); }
        }
        $rows = $wpdb->get_results(
            "SELECT pm.meta_value v, COUNT(*) c FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_ts_body_status' AND p.post_type = 'ts_work'
               AND p.post_status <> 'trash' GROUP BY pm.meta_value ORDER BY c DESC");
        $dist = [];
        foreach ($rows as $r) $dist[] = "{$r->v} {$r->c}";
        WP_CLI::line('本文 status: ' . ($dist ? implode(' / ', $dist) : '(未投入)'));

        // 5) import が解決できず落とした値 / 手動編集 skip
        $missing_vals = get_option('ts_term_warnings', []);
        if ($missing_vals) {
            $ok = false;
            WP_CLI::warning('term 未解決の値 (' . count($missing_vals) . ' 種): '
                . implode(' ', array_slice(array_keys($missing_vals), 0, 8)));
        }
        $manual = get_option('ts_manual_edit_warnings', []);
        if ($manual) {
            $ok = false;
            WP_CLI::warning('手動編集で skip 中の投稿 (' . count($manual) . ' 件):');
            foreach ($manual as $m) WP_CLI::line("  [{$m['kind']}] {$m['key']} (最終編集 {$m['modified']})");
        }
        // 6) 取り下げフォーム (S.130・S.132)。どちらも警告だけで、verify を NG にはしない
        $tick = get_option('ts_takedown_last_tick');
        if (!is_array($tick) || empty($tick['time'])) {
            WP_CLI::warning('ts_takedown_tick がまだ一度も動いていない (S.130)');
        } else {
            WP_CLI::line('ts_takedown_tick の最終実行: ' . wp_date('Y-m-d H:i', (int) $tick['time'])
                . ($tick['error'] !== '' ? ' (失敗: ' . $tick['error'] . ')' : ''));
            if (time() - (int) $tick['time'] > 86400) WP_CLI::warning('ts_takedown_tick が 24 時間以上止まっている (S.130)');
            if ($tick['error'] !== '') WP_CLI::warning('ts_takedown_tick の最後の実行が失敗している');
        }
        $missing = $this->requests_not_in_denylist($root);
        WP_CLI::line('適用済みの申請で denylist に無いもの: ' . count($missing) . ' 件');
        if ($missing) WP_CLI::warning('denylist に書き写していない申請: ' . implode(' ', array_slice($missing, 0, 10))
            . ' (wp ts export-takedowns。S.132)');

        WP_CLI::line('ts_catalog_commit: ' . get_option('ts_catalog_commit', '(未記録)'));
        WP_CLI::line('(冪等の確認は `wp ts import --dry-run` 2 回目が created=0 updated=0 になること)');
        $ok ? WP_CLI::success('verify OK') : WP_CLI::error('verify NG', false);
    }

    /**
     * denylist を適用して該当投稿を非公開 (private + `_ts_visibility=withdrawn`) にする
     * (WP サイトの非公開化。specs/S.121.md・S.117.md)。
     *
     * takedown/denylist.yml の `entries[].target.{kind,value}` を読む
     * (kind = work | episode | annex_path | author)。**work / author は
     * 子投稿 (話) まで必ず波及させる** — 親だけ止めても子の URL は生きるため。
     *  - 対象は private にし、`_ts_visibility=withdrawn`・`_ts_takedown_id`=エントリの id・
     *    `_ts_public_note`=public_note を揃える。既に private / draft でもメタを揃える (自己修復)。
     *    移行前の取り下げ (draft) は、これを流し直すだけで private に移る
     *  - `status: reinstated` のエントリの対象は印を消し、旧版 (`_ts_superseded_by`) でなければ publish に戻す
     *  - 読者への 410 は WordPress が返す (includes/visibility.php)。止めた URL は監査用に
     *    option `ts_takedown_urls` に残すが、.htaccess の生成には使わない (S.119)
     * 出力: 「private 化 N / 復帰 M / 変更なし K」(S.121)
     *
     * ## OPTIONS
     * [--repo=<path>] : リポジトリのルート
     * [--dry-run] : 何が変わるかだけ表示する
     */
    public function apply_takedown($args, $assoc) {
        global $wpdb;
        $root = $this->repo_root($assoc);
        $dry = isset($assoc['dry-run']);
        $pre = $dry ? '(dry-run) ' : '';
        $path = "$root/takedown/denylist.yml";
        if (!is_file($path)) WP_CLI::error("denylist がありません: $path");
        $entries = $this->parse_denylist($path);

        $want = [];      // post_id => ['id', 'note', 'why'] (取り下げる)
        $reinstate = []; // post_id => why (復帰の対象)
        foreach ($entries as $e) {
            $kind = $e['kind']; $value = $e['value'];
            $targets = $this->takedown_targets($kind, $value);
            if (!$targets) WP_CLI::warning("該当投稿なし: {$e['id']} $kind=$value");
            foreach ($targets as $pid) {
                if (($e['status'] ?? '') === 'reinstated') {
                    $reinstate[$pid] = "{$e['id']} $kind=$value";
                } else {
                    $want[$pid] = ['id' => $e['id'], 'note' => $e['public_note'] ?? '',
                                   'date' => $e['requested_at'] ?? '', 'why' => "{$e['id']} $kind=$value"];
                }
            }
        }
        // 取り下げ中のエントリが優先 (別のエントリで復帰していても、まだ止める対象なら止めたまま)
        $reinstate = array_diff_key($reinstate, $want);
        // 取り下げフォームの申請で取り下げ中の投稿は、denylist の復帰では戻さない (申請の側で戻す。S.128)
        foreach (array_keys($reinstate) as $pid) {
            if (strpos((string) get_post_meta($pid, '_ts_takedown_id', true), 'req:') === 0) unset($reinstate[$pid]);
        }

        $n = ['private' => 0, 'reinstate' => 0, 'none' => 0];
        $urls = [];
        $plan = [];
        foreach ($want as $pid => $w) $plan[$pid] = $w;
        foreach ($reinstate as $pid => $why) $plan[$pid] = null;
        foreach ($plan as $pid => $w) {
            $cur = [
                'status' => (string) get_post_status($pid),
                'visibility' => (string) get_post_meta($pid, '_ts_visibility', true),
                'takedown_id' => (string) get_post_meta($pid, '_ts_takedown_id', true),
                'public_note' => (string) get_post_meta($pid, '_ts_public_note', true),
                'takedown_date' => (string) get_post_meta($pid, '_ts_takedown_date', true),
                'superseded_by' => (string) get_post_meta($pid, '_ts_superseded_by', true),
            ];
            $ch = ts_visibility_takedown_change($cur, $w);
            if ($w !== null) $urls[] = wp_make_link_relative($this->public_permalink($pid));
            $n[$ch['kind']]++;
            if ($ch['kind'] !== 'none') {
                $why = $w !== null ? $w['why'] : $reinstate[$pid];
                WP_CLI::line(sprintf('%s%s: %s → post %d %s (%s%s)', $pre,
                    $ch['kind'] === 'private' ? 'private 化' : '復帰', $why, $pid, get_the_title($pid),
                    $ch['status'] !== null ? "{$cur['status']} → {$ch['status']}" : "{$cur['status']} のまま",
                    ($ch['set'] || $ch['delete']) ? '・メタ ' . implode(' ', array_merge(
                        array_map(function ($k) use ($ch) { return "$k=" . $ch['set'][$k]; }, array_keys($ch['set'])),
                        array_map(function ($k) { return "-$k"; }, $ch['delete']))) : ''));
            }
            if ($dry) continue;
            if ($ch['status'] !== null) {
                wp_update_post(['ID' => $pid, 'post_status' => $ch['status']]);
                // 取り下げ・復帰は import の一部であって「管理画面での手動編集」ではない。
                // 記録を更新しないと post_modified が動いた分を手動編集と誤検知して
                // 以後の import がその投稿を触らなくなる (verify も NG になる)
                clean_post_cache($pid);
                update_post_meta($pid, '_ts_last_import_gmt', get_post_field('post_modified_gmt', $pid));
            } elseif ($this->manually_edited($pid)) {
                // 過去の取り下げが手動編集扱いで残っているのを自己修復する
                update_post_meta($pid, '_ts_last_import_gmt', get_post_field('post_modified_gmt', $pid));
            }
            foreach ($ch['set'] as $k => $v) update_post_meta($pid, $k, $v);
            foreach ($ch['delete'] as $k) delete_post_meta($pid, $k);
        }

        // denylist のどのエントリにも当たらない取り下げの印 (エントリを消した等) は変えずに知らせる
        $stray = array_diff(array_map('intval', $wpdb->get_col(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_ts_visibility' AND pm.meta_value = 'withdrawn' AND p.post_type = 'ts_work'")),
            array_keys($plan));
        // 取り下げフォームの申請によるもの (まだ denylist に書き写していない。S.132) は分けて数える
        $by_request = [];
        foreach ($stray as $i => $pid) {
            if (strpos((string) get_post_meta($pid, '_ts_takedown_id', true), 'req:') === 0) {
                $by_request[] = $pid;
                unset($stray[$i]);
            }
        }
        if ($by_request) {
            WP_CLI::line('取り下げフォームの申請による取り下げ (denylist に未記載。export-takedowns で書き写す): '
                . count($by_request) . ' 件');
            foreach ($by_request as $pid) $urls[] = wp_make_link_relative($this->public_permalink($pid));
        }
        if ($stray) {
            WP_CLI::warning('denylist に無い取り下げの投稿 (変更しない): ' . count($stray) . ' 件 (post '
                . implode(' ', array_slice($stray, 0, 10)) . (count($stray) > 10 ? ' …' : '') . ')');
        }

        if (!$dry) update_option('ts_takedown_urls', array_values(array_unique($urls)), false);
        WP_CLI::success(sprintf('apply-takedown: private 化 %d / 復帰 %d / 変更なし %d (対象 %d 件)%s',
            $n['private'], $n['reinstate'], $n['none'], count($plan), $dry ? ' (dry-run)' : ''));
    }

    /** 公開時の URL を得る。非公開 (private / draft) のまま get_permalink すると `?post_type=…&p=ID` の
     *  仮 URL になり、410 に入れても実 URL を塞げない (実測) */
    private function public_permalink($post_id) {
        $post = get_post($post_id);
        if (!$post) return '';
        if ($post->post_status === 'publish') return get_permalink($post);
        $saved = $post->post_status;
        $post->post_status = 'publish';
        $url = get_permalink($post);
        $post->post_status = $saved;
        return $url;
    }

    /** denylist.yml の entries を読む (このスキーマ専用の最小パーサ。ブロック形式・フロー形式の両対応) */
    private function parse_denylist($path) {
        $out = []; $cur = null; $in_entries = false;
        foreach (file($path) as $raw) {
            $line = rtrim($raw, "\r\n");
            if (preg_match('/^\s*#/', $line) || trim($line) === '') continue;
            if (preg_match('/^entries:\s*(\[\s*\])?\s*$/', $line, $m)) {
                $in_entries = true;
                if (!empty($m[1])) return [];   // `entries: []`
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
                $cur['value'] = trim($this->yaml_unquote($v));
                continue;
            }
            if (preg_match('/^\s+status:\s*([A-Za-z_]+)/', $line, $m)) { $cur['status'] = $m[1]; continue; }
            if (preg_match('/^\s+requested_at:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/', $line, $m)) { $cur['requested_at'] = $m[1]; continue; }
            if (preg_match('/^\s+received_via:\s*([A-Za-z_]+)/', $line, $m)) { $cur['received_via'] = $m[1]; continue; }
            if (preg_match('/^\s+reason:\s*(.*?)\s*$/u', $line, $m)) { $cur['reason'] = $this->yaml_unquote($m[1]); continue; }
            // /removed/ の掲示の 1 行 (S.121 で投稿のメタ _ts_public_note に写す)。囲みの引用符は外す
            if (preg_match('/^\s+public_note:\s*(.*?)\s*$/u', $line, $m)) {
                $cur['public_note'] = $this->yaml_unquote($m[1]);
                continue;
            }
        }
        if ($cur) $out[] = $cur;
        $valid = [];
        foreach ($out as $e) {
            if (!$e['kind'] || !$e['value']) {
                WP_CLI::warning("denylist の不完全なエントリ: {$e['id']} (kind/value を確認)");
                continue;
            }
            if (!in_array($e['kind'], ['work', 'episode', 'annex_path', 'author'], true)) {
                WP_CLI::warning("未知の kind: {$e['kind']} ({$e['id']})");
                continue;
            }
            $valid[] = $e;
        }
        return $valid;
    }

    /** 囲みの引用符を外す (単一引用符の中の '' は ' に。export-takedowns が書く形) */
    private function yaml_unquote($v) {
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
            $q = $v[0];
            $v = substr($v, 1, -1);
            if ($q === "'") $v = str_replace("''", "'", $v);
        }
        return $v;
    }

    /** kind/value → 止める投稿 ID の配列 (子投稿まで波及させる) */
    private function takedown_targets($kind, $value) {
        global $wpdb;
        $ids = [];
        if ($kind === 'work') {
            $pid = $this->find_by_meta('ts_work', '_ts_work_slug', $value);
            if ($pid) $ids[] = $pid;
        } elseif ($kind === 'episode') {
            // episodes.jsonl の source_path (アンカー分割話は "path#anchor")
            [$sp, $anchor] = array_pad(explode('#', $value, 2), 2, null);
            $rows = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_ts_source_path' AND meta_value=%s", $sp));
            foreach ($rows as $pid) {
                if ($anchor !== null && get_post_meta($pid, '_ts_source_anchor', true) !== $anchor) continue;
                $ids[] = (int) $pid;
            }
        } elseif ($kind === 'annex_path') {
            $like = $wpdb->esc_like(rtrim($value, '*'));
            $rows = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_ts_source_path' AND meta_value LIKE %s",
                $like . (substr($value, -1) === '*' ? '%' : '')));
            foreach ($rows as $pid) $ids[] = (int) $pid;
        } elseif ($kind === 'author') {
            $term = get_term_by('slug', $value, 'ts_author');
            if ($term) {
                $ids = get_posts(['post_type' => 'ts_work', 'post_status' => 'any',
                                  'posts_per_page' => -1, 'fields' => 'ids',
                                  'tax_query' => [['taxonomy' => 'ts_author', 'field' => 'term_id',
                                                   'terms' => $term->term_id]]]);
            }
        }
        // 子孫まで波及 (親だけ止めても子の URL は生き続ける)
        $all = [];
        foreach ($ids as $pid) {
            $all[$pid] = true;
            foreach ($this->descendants($pid) as $cid) $all[$cid] = true;
        }
        return array_keys($all);
    }

    /** ts_work の子孫 (話) を再帰的に集める */
    private function descendants($post_id) {
        $out = [];
        $children = get_posts(['post_type' => 'ts_work', 'post_parent' => $post_id,
                               'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids']);
        foreach ($children as $cid) {
            $out[] = $cid;
            foreach ($this->descendants($cid) as $gid) $out[] = $gid;
        }
        return $out;
    }

    /** denylist に書き写した申請: 申請の投稿 ID => [記録の id, status] (reason の「(申請 <ID>)」で結ぶ) */
    private function exported_requests($root) {
        $out = [];
        $path = "$root/takedown/denylist.yml";
        if (!is_file($path)) return $out;
        foreach ($this->parse_denylist($path) as $e) {
            if (!preg_match('/\(申請 ([0-9]+)\)/u', $e['reason'] ?? '', $m)) continue;
            $out[(int) $m[1]][] = ['id' => $e['id'], 'status' => $e['status']];
        }
        return $out;
    }

    /** applied / approved の申請で denylist に対応するエントリが無いものの投稿 ID */
    private function requests_not_in_denylist($root) {
        if (!post_type_exists(TS_TAKEDOWN_TYPE)) return [];
        $done = $this->exported_requests($root);
        $ids = get_posts(['post_type' => TS_TAKEDOWN_TYPE, 'post_status' => 'any', 'posts_per_page' => -1,
                          'fields' => 'ids', 'no_found_rows' => true,
                          'meta_key' => '_tr_status', 'meta_value' => ['applied', 'approved'], 'meta_compare' => 'IN']);
        return array_values(array_filter(array_map('intval', $ids), function ($id) use ($done) { return !isset($done[$id]); }));
    }

    /**
     * 適用済み・復帰済みの取り下げの申請を denylist のエントリ (version 1) として標準出力に出す (S.132)。
     * 連絡先・本文・証拠は出さない。id は次の S 番号から振る (repo の SPECS.md の「次は S.n」か --start)。
     * 振った番号ごとに specs/S.n.md (取り下げの記録の S) を作り、SPECS.md の一覧に足すこと。
     * denylist に書き写し済みの申請は出さない。書き写した後に復帰した申請は、既存のエントリの
     * status を reinstated に直すよう警告で知らせる (新しいエントリは作らない)。
     *
     * ## OPTIONS
     * [--since=<date>] : 適用 (復帰) がこの日 (YYYY-MM-DD) 以後の申請だけ
     * [--start=<n>] : 最初に振る S の番号 (省くと repo の SPECS.md の「次は S.n」)
     * [--repo=<path>] : リポジトリのルート
     */
    public function export_takedowns($args, $assoc) {
        $root = $this->repo_root($assoc);
        if (isset($assoc['start'])) {
            if (!preg_match('/^[0-9]+$/', (string) $assoc['start'])) WP_CLI::error('--start は数字で');
            $n = (int) $assoc['start'];
        } else {
            $specs = @file_get_contents("$root/SPECS.md");
            if ($specs === false || !preg_match('/次は S\.([0-9]+)/u', $specs, $m)) WP_CLI::error('SPECS.md に「次は S.n」が無い (--start で指定)');
            $n = (int) $m[1];
        }
        $since = $assoc['since'] ?? '';
        if ($since !== '' && !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $since)) WP_CLI::error('--since は YYYY-MM-DD で');
        $done = $this->exported_requests($root);
        $ids = get_posts(['post_type' => TS_TAKEDOWN_TYPE, 'post_status' => 'any', 'posts_per_page' => -1,
                          'fields' => 'ids', 'no_found_rows' => true, 'orderby' => 'ID', 'order' => 'ASC',
                          'meta_key' => '_tr_status', 'meta_value' => ['applied', 'approved', 'reinstated'],
                          'meta_compare' => 'IN']);
        $yaml = ''; $all = [];
        foreach ($ids as $id) {
            $r = ts_takedown_get((int) $id);
            if ($since !== '' && wp_date('Y-m-d', $r['resolved_at']) < $since) continue;
            if (isset($done[$r['id']])) {
                foreach ($done[$r['id']] as $d) {
                    if ($r['status'] === 'reinstated' && $d['status'] !== 'reinstated') {
                        WP_CLI::warning("申請 {$r['id']} は復帰した: denylist の {$d['id']} の status を reinstated に直す");
                    }
                }
                continue;
            }
            if ($r['status'] === 'reinstated') continue; // 書き写す前に復帰したものは記録に残すだけで足りる
            $at = $r['confirmed_at'] ?: $r['received_at'];
            $targets = [];
            foreach ($r['targets'] as $t) {
                $pid = (int) ($t['post_id'] ?? 0);
                if (!$pid || get_post_type($pid) !== 'ts_work') continue;
                $slug = (string) get_post_meta($pid, '_ts_work_slug', true);
                if ($slug !== '') {
                    $target = ['kind' => 'work', 'value' => $slug];
                } else {
                    // 話だけを指した申請は episode (source_path、アンカー分割話は path#anchor)
                    $sp = (string) get_post_meta($pid, '_ts_source_path', true);
                    if ($sp === '') { WP_CLI::warning("申請 {$r['id']}: post $pid の work_slug も source_path も無い"); continue; }
                    $anchor = (string) get_post_meta($pid, '_ts_source_anchor', true);
                    $target = ['kind' => 'episode', 'value' => $sp . ($anchor !== '' ? "#$anchor" : '')];
                }
                $target['note'] = ts_takedown_public_note([wp_specialchars_decode(get_the_title($pid), ENT_QUOTES)],
                    $r['pen_name'], $r['allow_name'] === '1', (int) wp_date('Y', $at), (int) wp_date('n', $at));
                $targets[] = $target;
            }
            if (!$targets) { WP_CLI::warning("申請 {$r['id']}: 本サイトの作品に結びつく対象が無い (出さない)"); continue; }
            $out = ts_takedown_export_yaml(['id' => $r['id'], 'status' => $r['status'], 'via' => $r['via'],
                'confirmed_date' => wp_date('Y-m-d', $at),
                'applied_date' => $r['resolved_at'] ? wp_date('Y-m-d', $r['resolved_at']) : ''], $targets, $n);
            $n += count($out['ids']);
            $yaml .= $out['yaml'];
            $all = array_merge($all, $out['ids']);
        }
        if ($yaml !== '') echo $yaml;
        // 案内は標準エラーに (標準出力は denylist にそのまま足せる YAML だけにする)
        fwrite(STDERR, ($all ? '振った id: ' . implode(' ', $all) . ' (specs/S.n.md を作り SPECS.md の一覧と「次は」を直す)'
                             : '書き写す申請はありません') . "\n");
    }

    /** 取り下げ済み URL のパスを 1 行 1 件で出す (監査用と、scripts/wp/htaccess_block.py --install の
     *  検証が読む。.htaccess の生成には使わない — 410 は WordPress が返す。specs/S.119.md) */
    public function export_takedown_urls($args, $assoc) {
        foreach ((array) get_option('ts_takedown_urls', []) as $u) {
            WP_CLI::line($u);
        }
    }

    /**
     * ts_* の投稿・term を全削除する (やり直し用。specs/S.73.md の C)。
     * 取り下げの申請 (ts_takedown_request) は消さない (運用の原本で、catalog から作り直せない。S.125)。
     * ## OPTIONS
     * --yes : 確認なしで実行 (必須)
     */
    public function reset($args, $assoc) {
        if (!isset($assoc['yes'])) WP_CLI::error('全削除には --yes が必要です (事前に wp db export を)');
        $deleted = 0;
        foreach (self::TYPES as $type) {
            if (!post_type_exists($type)) continue;
            $ids = get_posts(['post_type' => $type, 'post_status' => 'any',
                              'numberposts' => -1, 'fields' => 'ids']);
            foreach ($ids as $id) { wp_delete_post($id, true); $deleted++; }
        }
        $terms_deleted = 0;
        foreach (self::TAXES as $tax) {
            if (!taxonomy_exists($tax)) continue;
            foreach (get_terms(['taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids']) as $tid) {
                wp_delete_term($tid, $tax); $terms_deleted++;
            }
        }
        delete_option('ts_catalog_commit');
        delete_option('ts_manual_edit_warnings');
        WP_CLI::success("reset: 投稿 $deleted 件・term $terms_deleted 件を削除 (WP 本体設定は無変更)");
    }

    /** 原パス (alias 含む) → WP サイトの URL の対応表を出力する */
    public function export_pathmap($args, $assoc) {
        $map = [];
        $q = new WP_Query(['post_type' => 'ts_work', 'post_status' => 'any',
                           'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true]);
        foreach ($q->posts as $pid) {
            $url = get_permalink($pid);
            $src = get_post_meta($pid, '_ts_source_path', true);
            if ($src) $map[$src] = $url;
            $aliases = get_post_meta($pid, '_ts_alias_paths', true);
            if (is_array($aliases)) foreach ($aliases as $a) $map[$a] = $url;
        }
        WP_CLI::line(wp_json_encode($map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}

WP_CLI::add_command('ts sync-terms', [new TS_Command(), 'sync_terms']);
WP_CLI::add_command('ts sync-pages', [new TS_Command(), 'sync_pages']);
WP_CLI::add_command('ts export-pages', [new TS_Command(), 'export_pages']);
WP_CLI::add_command('ts sync-docs', [new TS_Command(), 'sync_docs']);
WP_CLI::add_command('ts import', [new TS_Command(), 'import']);
WP_CLI::add_command('ts verify', [new TS_Command(), 'verify']);
WP_CLI::add_command('ts apply-takedown', [new TS_Command(), 'apply_takedown']);
WP_CLI::add_command('ts export-takedown-urls', [new TS_Command(), 'export_takedown_urls']);
WP_CLI::add_command('ts export-takedowns', [new TS_Command(), 'export_takedowns']);
WP_CLI::add_command('ts reset', [new TS_Command(), 'reset']);
WP_CLI::add_command('ts export-pathmap', [new TS_Command(), 'export_pathmap']);
