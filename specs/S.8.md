# S.8 結合キー episode_id の定義

- 種類: 仕様
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の「0. 実行セッションの心得」の「結合キーの定義」
- 関連: T.7.1, S.16, S.171

**結合キーの定義(全フェーズ共通。1.1 実測により確定)**:
- **冪等キー(WP upsert キー)= `episode_id`**。path+anchor では足りない — 同一ファイルを指す
  目録エントリが 39 件(16 グループ)実在する(S.171 の訂正 (c) 参照)
- **episode_id** = `_ts_source_path` の `/` を `__` に置換し、anchor があれば `@<anchor>`、
  同一ファイル衝突グループのみさらに `+<掲載日YYYYMMDD>` を付加
  (例: `novel__200209__19204751__d_upboy06.htm`、`novel__toukou01.html@JUNKO`)。
  **優先するのは catalog/episodes.jsonl の episode_id 欄** — 再導出せずこれを使う。
  episodes.jsonl ↔ `bodies/` ↔ convert_report ↔ import の結合はすべてこの id
- 本文 MD の置き場 = リポジトリ直下 **`bodies/`**(設計 S.37 の `catalog/bodies/` 表記は旧版)
