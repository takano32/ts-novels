<?php
/**
 * `wp ts sync-pages` / `wp ts export-pages` — 固定ページ (/about/ 等) と content/pages/*.html の往復
 * (T10.5、T27.2、specs/S109.md)。ファイルの形 (題の行・所有者文責の注記・hash・差分の行数) は TS_Page_File。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Pages_Command extends TS_Command_Base {

    /**
     * 固定ページ (/about/ 等) を content/pages/*.html から同期する (T10.5)。
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
            $file = TS_Page_File::parse(file_get_contents($f), $slug);
            $title = $file['title']; $body = $file['body']; $hash = $file['hash'];
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
     * 公開中の固定ページを content/pages/<post_name>.html に書き戻す (T27.2、S109)。
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
            $new = TS_Page_File::render((string) $page->post_title, (string) $page->post_content, $old);
            if ($new === $old) { $n['unchanged']++; continue; }
            if ($dry) {
                [$total, $add, $del] = TS_Page_File::diff_count($old, $new);
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
}
