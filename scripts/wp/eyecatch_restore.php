<?php
/**
 * eyecatch (featured image) の再設定 (specs/S.73.md の全消し再構築の後処理、S.189)。
 *
 * 使い方 (本番。WP-CLI の eval-file で読む):
 *   wp eval-file eyecatch_restore.php [--dry-run]
 *
 * catalog/reports/payload_build.json の eyecatch_refs (episode_id → 画像パス) を読み、
 * 各話の投稿に _thumbnail_id を付ける。添付は中身 (md5) が一致する既存の attachment を
 * 使い回し、無ければ wp media import で作る (同じ中身の画像に attachment を重ねて作らない)。
 * 何度流しても結果は同じ。
 */
$dry = in_array('--dry-run', $args ?? [], true);
$repo = getenv('TS_REPO') ?: (getenv('HOME') . '/novels.xwp.jp/repo');
$report = json_decode(file_get_contents("$repo/catalog/reports/payload_build.json"), true);
$refs = $report['eyecatch_refs'] ?? [];
if (!$refs) {
    WP_CLI::error('eyecatch_refs が空です');
}

// 既存の attachment を中身の md5 で引けるようにする
$by_md5 = [];
foreach (get_posts(['post_type' => 'attachment', 'post_status' => 'any', 'numberposts' => -1,
                    'fields' => 'ids']) as $aid) {
    $file = get_attached_file($aid);
    if ($file && is_file($file)) {
        $by_md5[md5_file($file)] = $aid;
    }
}

$set = 0; $missing = [];
foreach ($refs as $episode_id => $images) {
    // 1 話に候補が複数あるときは先頭 (payload の本文で最初に現れる画像) を使う
    $rel = preg_replace('#^/assets/annex-img/#', '', $images[0]);
    $src = "$repo/$rel";
    if (!is_file($src)) {
        $missing[] = "$episode_id: 画像が無い $src";
        continue;
    }
    $sum = md5_file($src);
    if (!isset($by_md5[$sum])) {
        if ($dry) {
            WP_CLI::line("(dry-run) media import $src");
            $by_md5[$sum] = 0;
        } else {
            $aid = (int) WP_CLI::runcommand('media import ' . escapeshellarg($src) . ' --porcelain',
                                            ['return' => true]);
            $by_md5[$sum] = $aid;
        }
    }
    $pids = get_posts(['post_type' => 'ts_work', 'post_status' => 'any', 'numberposts' => -1,
                       'fields' => 'ids', 'meta_key' => '_ts_episode_id', 'meta_value' => $episode_id]);
    if (count($pids) !== 1) {
        $missing[] = "$episode_id: 投稿が " . count($pids) . ' 件';
        continue;
    }
    if (!$dry) {
        update_post_meta($pids[0], '_thumbnail_id', $by_md5[$sum]);
    }
    $set++;
}
foreach ($missing as $m) {
    WP_CLI::warning($m);
}
WP_CLI::success(sprintf('eyecatch: %d 話に設定%s (対象 %d 話)', $set, $dry ? ' (dry-run)' : '', count($refs)));
