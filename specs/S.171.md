# S.171 catalog_build: 正規目録のパース (T.7.1、元の 1.1)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の「Phase 1」の 1.1
- 関連: T.7.1, S.8, S.11, S.212

## T.7 — catalog 確立(目安 2〜3 週。ここが土台)

- [x] 1.1 `scripts/wp/catalog_build.py`: lib1〜73 の 2,887 エントリを JSONL 化
      - survey JSON の fields 節の正規表現をそのまま実装(パース率 100% 検証済みの仕様)
      - 出力 `catalog/episodes.jsonl` の必須フィールド: **episode_id / source_path / source_anchor** /
        title / author / homepage(URL のみ。mailto は捨てる) / illustrator(+URL) / date_raw /
        size_kb / files_n / kansou_slug / arasuji / comment / osusume{recommender,refs[]} / suisen /
        nav_links[] / genre[] / type[] / keywords[] / zokusei / catalog_ref /
        **orig_url**(`http://ts.novels.jp/<source_path>`) / **annex_url** /
        **annex_yays_url**(`~yays/library/` に同一相対パスが実在する場合のみ。機械判定・約 1,399 件) /
        **provenance**(リポジトリ直下 `collinfo.json` から回収経路・スナップショット URL・取得日を転記)
      - **受け入れ: エントリ数 2,887・パース失敗 0・mailto 由来の値の残存 0
        (検査は「mailto: リンクから抽出した値が出力に無いこと」をロジックで確認する自動テストを同梱。
        シェル検査を使うなら `! grep -q` 形式にし、`index@08201937.html` 型ファイル名や homepage URL を
        誤検知しないこと)・provenance 被覆率をレポート**
      - **1.1 実測メモ (2026-08-30 実装時。以降のフェーズはこちらが実測値)**:
        2,887 エントリ / パース失敗 0 / mailto 264 アドレスを除去し残存 0 /
        provenance 被覆率 93.35%(2,695/2,887。残り 192 は原本ファイルが未回収の話 186 +
        外部 URL 6 で、回収されていない以上は経路も無い)。
        レポートは `catalog/reports/catalog_build.json`、欄の説明は S.212 (元の `catalog/README.md`)。
      - **タスクリストの記述の訂正 3 点**(実物を見て判明):
        (a) provenance の出所は `collinfo.json` **ではない** — 中身は CommonCrawl の
        コレクション一覧 127 件で来歴情報を持たない。実際の出所は **git 履歴**(回収コミットの
        件名と日付)。スナップショット URL は個別 timestamp の記録が無いため Wayback の照会 URL 形式
        (b) `annex_yays_url` の「約 1,399 件」は **~yays 側ファイル 1,466 のうち同一相対パスが
        直下にもある数**であって目録エントリ数ではない。目録エントリ基準の実測は **724 件**
        (c) 冪等キーは **path+anchor では足りない** — 同じファイルを指す目録エントリが 39 件
        (16 グループ) 実在する(シリーズタイトルページに各話がリンクする「生命戦隊トランスギャルズ」型、
        改訂版の再掲型)。episode_id はこの衝突グループにだけ `+<掲載日 YYYYMMDD>` を付けて
        一意化してある。**T.8 の import は `_ts_source_path`+anchor ではなく episode_id を
        upsert キーにすること**
