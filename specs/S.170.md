# S.170 方針とガバナンスの実施記録 (T.6.1〜T.6.5、元の 0.1〜0.5)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の「Phase 0 — 方針とガバナンス」
- 関連: T.6, S.161

## T.6 — 方針とガバナンス(目安 1 週)

- [x] 0.1 `takedown/denylist.yml` のスキーマを決めて空ファイル+README を作る
      (エントリ: 対象 = work slug / episode の source_path / 原本アーカイブのパス、理由、日付、状態)
- [x] 0.2 S.75〜S.81 v0: 削除依頼受領 → 72h 以内に
      ①WP draft 化 ②.htaccess 410 追記 ③原本アーカイブ(Pages)側除外 ④**git 履歴除去
      (git filter-repo。raw-original タグにも残る事実と対応方針を明記)** ⑤`/removed/` 更新、
      の **四層+掲示** を文書化
- [x] 0.3 S.69〜S.71 v0: catalog 再生成 → rsync → `wp db export`(復旧点)→
      `wp ts import` → verify → キャッシュクリア依頼 (高野さんの作業)、の定常手順(コマンド列)を文書化
- [x] 0.4 robots.txt 草案(AI 学習クローラ Disallow・ia_archiver 許可)を `scripts/wp/assets/` に用意
      ※ リポジトリ直下の現 robots.txt は Wayback 誤回収物なので参照しない。配備は 2.3
- [x] 0.5 (サイト所有者の判断) 要決定事項の判断(2026-08-30 回答済 — S.40 参照)。
      **Q3=public 維持(README で透明化)/ Q4=全量公開+削除即応 / Q5=旧目録は全差分投入 /
      Q7=呼称「少年少女文庫」原題のまま**。
      **重要な前提変更: 本プロジェクトは八重洲メディアリサーチ(文庫設立者)の持ち主の依頼による
      リブートである。**「黙認ベース」ではなく原運営者公認。about ページにその旨と運営者ハンドルを
      明記(4.6)。ただし個別作品の著作権は各原著者にあり、削除 SLA・PII マスク・BBS 層 noindex は維持。
      作者連絡の記録の初期化と発送は T.33/T.37.1 で継続
