# S.32 実装ロードマップ

- 種類: 設計
- 状態: 一部失効 (S.35・S.37・S.39 で上書き)
- 出典: docs/wordpress-library-design.md の「実装ロードマップ」
- 関連: S.35, S.37, S.39

## 実装ロードマップ

| 段階 | 期間目安 | 内容 | 成果物 |
|---|---|---|---|
| **方針とガバナンス (T.6)** | 1–2 週 | takedown フロー文書化、denylist.yml スキーマ、robots.txt 草案、ランブック骨子、作者連絡の記録の初期化 (MEMORY 連絡経路)、ドメイン/ホスト決定 | 公開ポリシー文書一式、takedown/denylist.yml (空)、S.75〜S.81 (最初の版) |
| **catalog 確立 (T.7)** | 2–3 週 | parse_lib.py / extract_taxonomy.py / author_inventory.py / inventory.json / board_index.json を scripts/wp/ へ移植・統合。catalog_build + work_builder。**Work クラスタの人手確認 (needs_review 100–200 件) が最重量タスク**。正規化マップ人手レビュー。旧目録差分確定 | catalog/*.jsonl 一式、QA レポート (2,887 件・パース率 100% 検証)、work_overrides.yml |
| **WP 骨格+メタ全量先行投入 (T.8)** | 2 週 | docker 環境、mu-plugin v0.1 (CPT/タクソノミー/メタ/wp ts コマンド)、パイロット 100 件→全量メタ投入、**冪等性テスト (2 連続実行で差分ゼロ)**、annex_build v0.1、make publish 開通 → **全域 noindex で限定公開**。本文なしでも目録・索引・作者ページが成立する **メタのみの案に相当する中間形** (reader-ux 案のマイルストーンを正式採用 — 作者連絡の反応が悪ければここで凍結できる退路) | 動く限定公開サイト (メタポータル+原本アーカイブ)、path_map.json |
| **本文投入 (raw) (T.9)** | 2 週 | body_extract、全話 wp:html 投入、eyecatch sideload (実体 4 ファイル・参照 15 話。「109 件」は参照回数)、無作為 100 件の原本併読 QA、`_ts_reflow_mode` 全件分類 | 全話が読める限定公開サイト、reflow 一覧のレポート |
| **テーマ・索引・読書 UX (T.10)** | 2–3 週 | ts-bunko テーマ、リーダー UI (しおり/左右キー/ダーク)、五十音・年表・bunrui・語彙・osusume 索引、Pagefind ファセット検索、書誌カード | 通読可能な β サイト (noindex のまま) |
| **権利ゲートと段階解禁 (T.33・T.37)** | 作者返信次第 (T.9 以降並行) | 作者連絡発送、現役回収 18 ページの許諾確認 (未許諾は denylist)、/takedown/ 稼働、削除リハーサル (テスト作品で四層削除演習)、完了後に WP サイトのみ index 解禁+sitemap | 最初の公開サイト、連絡の記録、演習記録 |
| **コミュニティ史料層 (順次) (T.36)** | 順次 | ts_dojo 取込 (スパム除外ログ付き)、noteky f_0 非曖昧マッチ表示、/annex/ ギャラリー案内。任意: 許諾作者分の段落ブロック昇格 (_ts_reflow_mode の一覧から)、kansou 構造化タブ (要決定) | community 取込一式 |
| **定常運用** | — | 削除対応 (72h SLA)、随時 publish、年 1 回再構築演習、年 2 回リンク検査 | 運用ログ |

> **【失効】** ロードマップ表のうち 4 か所が後の改訂で変わっている:
> - **catalog 確立 (T.7)** の「QA レポート (2,887 件)」→ 実測は **3,844 話 / works 1,463 / 作者 342**(S.41 の目録外収蔵 859 と S.40 の legacy 全差分投入を含むため)。needs_review も「100–200 件」→ **実測 328 件**
> - **WP 骨格+メタ全量先行投入 (T.8)** の「docker 環境」→ S.35 により「novels への SSH/WP-CLI 疎通 + rsync デプロイ」に置換
> - **本文投入 (T.9)** の「本文投入 (raw)」→ S.37 により「**MD 投入 (証明つき) + raw フォールバック**」に置換
> - **テーマ・索引・読書 UX (T.10)** の Pagefind ファセット検索 → S.39 の「検索方式の改訂」により WP 標準検索+ファセットに置換。**コミュニティ史料層 (T.36)** の「ブロック昇格 (任意)」は S.37 に吸収されて消滅し、代わりに感想板・道場・noteky の近代ビューが入った

**既存資産の再利用対応表**: parse_lib.py→catalog_build の核 / extract_taxonomy.py+taxonomy_report.txt→正規化マップ / author_inventory.py+inventory.json+board_index.json→authors.json と ts_author term / fix_links.py→annex_build の一括書換枠組み / collinfo.json→`_ts_provenance` / audit_full.py→verify の照合枠組み / 既存 Wayback 回収系→今後の欠落回収時の追補インポート / deploy-pages.yml→デプロイワークフローの雛形。
