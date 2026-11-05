<?php
/**
 * `wp ts` の各コマンドの共通部分 (T8.2)。WP-CLI からのみ読む (includes/commands/register.php が WP_CLI のときだけ読む)。
 *
 * ここは移築の根幹コード (本番 DB に書く唯一の経路)。設計の要点:
 *  - 冪等: episode_id / work_slug をメタキーに upsert。`_ts_import_hash` が同じなら skip。
 *    受け入れ条件は「2 回連続実行で 2 回目が created=0 / updated=0」
 *  - 手動編集の保護: 前回 import 後に管理画面で編集された投稿は上書きせず警告に出す
 *    (--overwrite-manual でのみ上書き)。post_status には決して触れない (新規作成時のみ publish)
 *  - 追跡: 投入時の catalog の git コミットを option `ts_catalog_commit` に記録
 *  - DB は使い捨て: `reset --yes` は ts_* の投稿と ts_* タクソノミーの term だけを消す
 *    (WP 本体設定には触れない)
 * catalog の読み取り・語彙・索引・期待値の計算は includes/catalog/ (WordPress を使わない) にあり、
 * ここは repo の場所・ファイルの読み取りの失敗の報告・投稿の検索・手動編集の検出・取り込み記録を持つ。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

abstract class TS_Command_Base {

    /** repo ルート (サーバでは ~/novels.xwp.jp/repo を想定。--repo で上書き可) */
    protected function repo_root($assoc) {
        $root = $assoc['repo'] ?? (getenv('TS_REPO') ?: (getenv('HOME') . '/novels.xwp.jp/repo'));
        if (!is_dir($root)) {
            WP_CLI::error("repo が見つかりません: $root (--repo=<path> か TS_REPO を指定)");
        }
        return rtrim($root, '/');
    }

    protected function read_jsonl($path) {
        try {
            return TS_Catalog_Files::read_jsonl($path);
        } catch (RuntimeException $e) {
            WP_CLI::error($e->getMessage());
        }
    }

    protected function read_json($path) {
        try {
            return TS_Catalog_Files::read_json($path);
        } catch (RuntimeException $e) {
            WP_CLI::error($e->getMessage());
        }
    }

    /** catalog/terms.json の語彙 */
    protected function vocabulary($root): TS_Vocabulary {
        return new TS_Vocabulary($this->read_json("$root/catalog/terms.json"));
    }

    /** catalog/authors.json の authors の配列 (古い形の、配列が直接並ぶファイルも読む) */
    protected function authors($root): array {
        $authors = $this->read_json("$root/catalog/authors.json");
        if (isset($authors['authors'])) $authors = $authors['authors'];
        return $authors;
    }

    /** takedown/denylist.yml のエントリ (読めなかったエントリの警告はここで出す) */
    protected function denylist($path): array {
        $r = TS_Denylist::parse($path);
        $this->warn($r['warnings']);
        return $r['entries'];
    }

    /** 警告の文をこの順に出す */
    protected function warn(array $warnings): void {
        foreach ($warnings as $w) WP_CLI::warning($w);
    }

    /** meta キーで投稿を 1 件引く (post_status を問わない) */
    protected function find_by_meta($type, $key, $value) {
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
    protected function manually_edited($post_id) {
        $last = get_post_meta($post_id, '_ts_last_import_gmt', true);
        if (!$last) return false;
        $mod = get_post_field('post_modified_gmt', $post_id);
        return $mod && strtotime($mod) > strtotime($last) + 2; // 2 秒の猶予 (同一トランザクション内更新)
    }

    /** 単発 work は work 側 (_ts_import_hash) と fill 側 (_ts_fill_hash) が別キー —
     *  同じキーを奪い合うと毎回 updated=2 になり冪等が壊れる (S182 の実測: 冪等性の不具合) */
    protected function stamp_import($post_id, $hash, $key = '_ts_import_hash') {
        update_post_meta($post_id, $key, $hash);
        // 直前の update で post_modified が動いた「後」の値を記録する
        clean_post_cache($post_id);
        update_post_meta($post_id, '_ts_last_import_gmt', get_post_field('post_modified_gmt', $post_id));
    }
}
