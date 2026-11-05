<?php
/**
 * 読み込むファイルの明示リスト (T45.1、specs/S242.md)。
 *
 * 配備 (scripts/wp/deploy.sh) は rsync に --delete を付けないので、消したり名前を変えたファイルは本番に残る。
 * includes/ を glob で読むと残った古いファイルも読まれて関数の二重定義で落ちるため、読むものはここに書いた
 * ものだけにする。順序は依存の順 (下の表のとおり)。
 *
 * scripts/wp/ts_library_test.php は PURE をそのまま WordPress なしで読んで境目が守られていることを確かめ、
 * includes/ に置いた .php が全部どれかの表に載っていることも見る。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Library_Files {

    /** WordPress を使わない部分 (判断・計算・文面)。単体の試験はこれだけを読む */
    public const PURE = [
        'content-model.php',             // 投稿タイプ・タクソノミー・メタの名前と登録の引数
        'roles/role-plan.php',           // 運営委員ロールの望む姿と差分 (S116)
        'visibility/visibility-rules.php', // 410 / 404 / 帯の判定、apply-takedown の 1 投稿の変化 (S117・S118・S121)
        'takedown/takedown-rules.php',   // 申請の投稿タイプ名・パラメータ・状態の遷移・cron の判定 (S124〜S130)
        'takedown/takedown-input.php',   // フォームの入力の検証・表示時刻の署名 (S124)
        'takedown/takedown-text.php',    // メールの文面・/removed/ の 1 行と並び (S129・S131)
        'takedown/denylist.php',         // takedown/denylist.yml の読み取りと書き出し (S76・S132)
        'takedown/takedown-plan.php',    // apply-takedown の計画と表示文 (S121)
        'render/render-text.php',        // 読了目安・回収経路の表示・サイドバーの表
        'indexes/index-text.php',        // 索引の種類・題名・五十音行の並び・いまの一作の選び方
        'catalog/catalog-files.php',     // catalog の JSON / JSONL の読み取り
        'catalog/vocabulary.php',        // terms.json の語彙 (値 → slug)
        'catalog/work-index.php',        // works.jsonl の索引と話の URL slug
        'catalog/episode-fields.php',    // 話 → post meta の写像
        'catalog/page-file.php',         // content/pages/*.html の形 (sync-pages / export-pages)
        'catalog/verify-expect.php',     // wp ts verify の期待値
    ];

    /** WordPress に触る部分 (フック・DB・出力)。site-hooks.php が最初 (TS_Library::boot の pre_get_posts を索引より先に登録する) */
    public const WP = [
        'site-hooks.php',                // 登録 (TS_Library) とサイト全体の振る舞い
        'render/render-work.php',        // 作品・話のページの表示部品 (テーマが呼ぶ関数)
        'render/render-lookup.php',      // 原パス → 投稿、名前 → 作者 term、作者ページの部品
        'render/render-sidebar.php',     // サイドバー
        'indexes/index-routing.php',     // /index/…/ と /year/YYYY/ のルーティング
        'indexes/index-render.php',      // 索引の描画部品 (ts_bunko_index_<種類>)
        'visibility/visibility-hooks.php', // 410 / 404 を返す・帯を出す
        'takedown/takedown-store.php',   // 申請の投稿タイプと読み書き
        'takedown/takedown-mail.php',    // メールの送信
        'takedown/takedown-apply.php',   // 申請の非公開化と復帰
        'takedown/takedown-form.php',    // フォーム [ts_takedown_form] と確認リンク
        'takedown/takedown-cron.php',    // cron ts_takedown_tick
        'takedown/takedown-removed-list.php', // 一覧 [ts_removed_list]
        'takedown/takedown-admin.php',   // 管理画面 (運営委員の操作)
    ];

    /** WP-CLI のコマンド (WP_CLI のときだけ読む)。最後の register.php が wp ts に登録する */
    public const CLI = [
        'commands/command-base.php',     // 共通部分 (repo の場所・読み取り・投稿の検索・手動編集の検出)
        'commands/term-resolver.php',    // 語彙の値 → term ID
        'commands/sync-terms.php',       // wp ts sync-terms
        'commands/import.php',           // wp ts import
        'commands/pages.php',            // wp ts sync-pages / export-pages
        'commands/sync-docs.php',        // wp ts sync-docs
        'commands/verify.php',           // wp ts verify
        'commands/takedown.php',         // wp ts apply-takedown / export-takedown-urls / export-takedowns
        'commands/reset.php',            // wp ts reset
        'commands/pathmap.php',          // wp ts export-pathmap
        'commands/role-sync.php',        // wp ts role-sync
        'commands/register.php',         // サブコマンドの登録
    ];
}
