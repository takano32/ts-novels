<?php
/**
 * `wp ts` — catalog → WordPress の投入コマンド群 (タスク 2.2)。
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

    /** 割当ロジックを変えたら上げる (既存投稿が hash 不一致になり、reset なしでも収束し直す) */
    private const HASH_VER = 'v4'; // v4: menu_order (話順) を書くようになった

    /** authors.json に載らない擬似作者 (works.jsonl の author_slug に現れる)。sync-terms が term を作る */
    private const PSEUDO_AUTHORS = ['unattributed' => '作者不詳', 'series-index' => '（シリーズ目録）'];

    private $name2slug = [];    // tax => [name / raw variant / slug => slug] (terms.json 由来)
    private $slug2term = [];    // tax => [slug => term_id] (0 = WP に無い)
    private $ep_worlds = [];    // episode_id => [world slug] (terms.json の episode_worlds)
    private $term_missing = []; // "tax:値" => 件数。解決できず割当を落とした値 (場当たり term は作らない)
    private $body_status = [];  // episode_id => converted / raw-fallback (payloads/_meta.json)

    /** terms.json の語彙を対応表に積む。term は必ず slug→ID で解決する (name 解決は
     *  term_exists() が slug 照合を先にやるため sanitize_title の衝突で誤爆する — 実測 (B)) */
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
     *  同じキーを奪い合うと毎回 updated=2 になり冪等が壊れる (実測 (A)) */
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
            // 作者ではない (共有世界板で別人になる — 実測 (C)) ので、メタに置くだけ
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
        wp_set_object_terms($post_id, $this->term_ids('ts_author', [$w['author_slug'] ?? '']), 'ts_author');
        $this->stamp_import($post_id, $hash);
        $n[$postarr['ID'] ?? null ? 'updated' : 'created']++;
        return $post_id;
    }

    /** episode の共通メタ・タクソノミーを投稿に書く。$author_slug=null は作者 term に触らない
     *  (単発 work — upsert_work が付けた作者を消さないため。実測 (D)) */
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

    /** 単発作品: 親 work 投稿に episode の本文とメタを載せる。hash は work 側と別キー (実測 (A)) */
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
     * 固定ページ (/about/ 等) を content/pages/*.html から同期する (タスク 4.6)。
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
     * 運営文書・前史・ギャラリー (ts_doc) を payloads-docs/ から同期する (タスク 4.6/4.7/4.9)。
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

        // 4b) 書誌カード用メタの被覆 (タスク 3.4): catalog の非空数 = DB の meta 行数
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
        WP_CLI::line('ts_catalog_commit: ' . get_option('ts_catalog_commit', '(未記録)'));
        WP_CLI::line('(冪等の確認は `wp ts import --dry-run` 2 回目が created=0 updated=0 になること)');
        $ok ? WP_CLI::success('verify OK') : WP_CLI::error('verify NG', false);
    }

    /**
     * denylist を適用して該当投稿を draft 化する。
     * denylist.yml の `source_path:` / `work_slug:` 行を読む (最小パーサ)。
     */
    public function apply_takedown($args, $assoc) {
        $root = $this->repo_root($assoc);
        $path = "$root/takedown/denylist.yml";
        if (!is_file($path)) WP_CLI::error("denylist がありません: $path");
        $n = 0;
        foreach (file($path) as $line) {
            if (preg_match('/^\s*(?:-\s*)?(source_path|work_slug|episode_id):\s*["\']?([^"\'#\n]+)/', $line, $m)) {
                $key = ['source_path' => '_ts_source_path', 'work_slug' => '_ts_work_slug',
                        'episode_id' => '_ts_episode_id'][$m[1]];
                $pid = $this->find_by_meta('ts_work', $key, trim($m[2]));
                if ($pid && get_post_status($pid) !== 'draft') {
                    wp_update_post(['ID' => $pid, 'post_status' => 'draft']);
                    WP_CLI::line("draft 化: {$m[1]}={$m[2]} (post $pid)");
                    $n++;
                }
            }
        }
        WP_CLI::success("apply-takedown: $n 件を draft 化");
    }

    /**
     * ts_* の投稿・term を全削除する (やり直し用。docs/rebuild-runbook.md §2-C)。
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

    /** 原パス (alias 含む) → 本館 URL の対応表を出力する */
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
WP_CLI::add_command('ts sync-docs', [new TS_Command(), 'sync_docs']);
WP_CLI::add_command('ts import', [new TS_Command(), 'import']);
WP_CLI::add_command('ts verify', [new TS_Command(), 'verify']);
WP_CLI::add_command('ts apply-takedown', [new TS_Command(), 'apply_takedown']);
WP_CLI::add_command('ts reset', [new TS_Command(), 'reset']);
WP_CLI::add_command('ts export-pathmap', [new TS_Command(), 'export_pathmap']);
