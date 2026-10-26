# S.11 目録パースの地雷

- 種類: 仕様
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の「実行セッションの心得」の「目録パースの地雷」
- 関連: T.7.1, S.54, S.171

**目録パースの地雷**(詳細は survey JSON の catalog 節):
- 正規目録は `lib1.html`〜`lib73.html`(**ゼロ埋めなし**)。`lib01`〜`lib09.html` は**別物**の旧目録
  (lib01–06 = 非テーブル 252 エントリ、lib07–09 = 移行期テーブル 84 ブロック中実作品 81。
  【属性】欄・2桁年・`***` 欠損値あり)
- `library.html` の 10 件は lib1 先頭 10 件の完全重複 — スキップ。`lib-index*.html` は索引でエントリなし
- エントリは `<TABLE BORDER=1>` 単位・必ず 4 行構成・1 行目 6 セル。長大 1 行 HTML なので行単位パース禁止
- タグ大文字小文字混在・単引用符 href あり・オススメ欄に壊れた入れ子 `<A>` → フラット正規表現で
- NFKC 正規化必須(ＳＦ/SF)。多値は ASCII+全角空白で分割。マーカーあり空値は NULL(5〜15%)
- **投稿ディレクトリ名 novel/YYYYMM/DDHHMMSS は「作者の初回投稿バッチ」であり話の日付ではない**
  (200209 配下に 2014 年の話が実在)。post_date は必ず目録の日付欄から
- 本文 HTML: bogus comment `<!　…!!`(没稿隠し)と裸の `<` はブラウザ等価処理(preclean の手本は
  `md_convert_probe.py`)
- series.html の**有効行(リンクを持つ TR)は実測 112**。設計 (S.25) と旧版のタスクリストの「123 行」は誤り
  (数え方: `catalog/reports/work_builder.json` の `series_html_rows`)
