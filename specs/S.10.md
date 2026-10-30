# S.10 普遍ルール

- 種類: 手順
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の「実行セッションの心得」の「普遍ルール」
- 関連: T.27.6, S.5, S.100, S.109, S.67, S.210

**普遍ルール**:
1. **単一真実源は `catalog/`**。WP DB は使い捨ての派生ビュー。WP 管理画面での手動編集は禁止、
   修正は必ず catalog 側 overrides ファイルで。
   例外は所有者による固定ページ 4 枚 (`content/pages/*.html`) と解題 (`content/docs/*.html`) の編集 (S.100)。
   正は catalog でなく git のこれらのファイルで、所有者の原稿をファイルに入れる経路でも、管理画面の編集を
   git に書き戻す経路 (固定ページは `wp ts export-pages`、S.109) でも、git に入れてから投入する
   (経路の選択は八重洲さんの判断、T.27.1)
2. すべてのインポートは冪等(上記キーで upsert)。**受け入れ条件は「2 回連続実行で 2 回目が
   created=0 / updated=0」**。import コマンドは created/updated/skipped を集計出力すること
3. 新スクリプトは `scripts/wp/` に置き、ツールの一覧 S.210 (元の `scripts/README.md`) に 1 行追記してコミット
4. メールアドレス(mailto 由来の値)は catalog 生成段階で除去し、**WP の DB に入れない**
   (**目録メタだけでなく本文も**。本文は `payload_build.scrub_addresses` が担当し、
   出荷前の自己検査で残存 0 を機械判定する。2026-08-30 のレビューで本文経路の抜けが発覚した)
5. 長時間タスクは着手前に概算 ETA を進行メモに書く。**破壊的・本番操作の前に復旧点を作る**
   (DB は `wp db export`、.htaccess は `.bak-日付` コピー)
6. 本番 `public_html` での実験は `_probe/` ディレクトリで行い、終わったら必ず消す
7. CloudSecure のログイン URL 変更値(.htaccess 内に記載)を文書・コミット・ログ.htaccess 写しに
   転記しない。**本番 .htaccess の全文をリポジトリに入れない**
8. **サイトは最初から公開(インデックス可)で構築する**(S.42)。ただし `/boards/`
   `/dojo/` 等の掲示板層は**恒久 noindex**(第三者のプライバシー配慮 — 公開ゲートとは別物)
9. rsync は **`--delete` 禁止・必ず `-n`(dry-run)を先行**。宛先 `~/novels.xwp.jp/` 直下には
   wp-config.php が居る
