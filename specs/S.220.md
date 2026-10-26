# S.220 文書を docs / specs / tasks の 3 分類に揃えた記録 (2026-10-26)

- 種類: 記録
- 状態: 有効
- 出典: 2026-10-26 の作業 (高野さんの決定による文書の最終形)
- 関連: T.22, T.40, T.41, T.42, T.43, S.2, S.4, S.13, S.205〜S.219

## 決定

リポジトリの文書は **docs / specs / tasks の 3 分類だけ**にする (高野さんの決定)。

| 分類 | 置き場所 | 一覧 |
|---|---|---|
| 説明 | `docs/` | ルートの `DOCS.md` |
| 仕様 (S) | `specs/S.n.md` (1 話題 1 ファイル。作業の記録も S) | ルートの `SPECS.md` |
| 作業 (T) | `tasks/T.n.md` (大項目ごとに 1 ファイル) | ルートの `TASKS.md` |

ルートの `README.md` は GitHub の表紙として残し、案内だけを置く。ディレクトリを開いた人が迷わないよう、
説明を移した元の場所には「移した先」の案内を 1〜2 行だけ残した。

## TASKS.md の分割

元の `TASKS.md` の大項目 T.1〜T.39 をそれぞれ `tasks/T.n.md` に分けた (T.15 の旧番号の対応表を含む)。
行はそのまま写し、小・孫タスクの番号と [x]/[ ] も保った。`TASKS.md` は決まりと一覧 (番号・題名・状態・関連 S) と、
着手順と所有者レビューの通過に必須な範囲の説明を持つ一覧にした。

行の突き合わせ (元の空でない 333 行) で、移らなかったのは次の 10 行だけ:
「2 本立て」を書いた決まりの 1 行 (3 分類の決まりに置き換えた)、区切り線 `---` 2 行、
移した説明の場所を指すように張り直した 7 行 (T.16.3.3・T.21.4・T.21.5・T.22.3・T.23.6・T.24.3・T.25.2.4)。

## 移した説明 (元 → 先)

| 元 | 先 |
|---|---|
| ルートの `README.md` の冒頭と「内容」 | S.205 |
| ルートの `README.md` の「復元方法」 | S.206 |
| ルートの `README.md` の「検証結果」「既知の欠落・回収範囲について」 | S.207 |
| ルートの `README.md` の「公開（GitHub Pages）」「リポジトリ構成・履歴」 | S.208 |
| `scripts/README.md` (回収・監査・変換のツールと `workflows/`) | S.209、手引きは `docs/tools.md` |
| `scripts/README.md` の「wp/ (WordPress 移築の実装)」 | S.210、手引きは `docs/tools.md` |
| `catalog/README.md` の冒頭・ファイル・再生成 | S.211 |
| `catalog/README.md` の episodes.jsonl の欄 | S.212 |
| `catalog/README.md` の authors.json の欄 | S.213 |
| `catalog/README.md` の provenance の出所 | S.214 |
| `takedown/README.md` | S.215 |
| `catalog/review/README.md` | S.216 |
| `catalog/review/authors-slugs.md` | S.217 (元のファイルは消した) |
| `catalog/review/works-risk-note.md` | S.218 (元のファイルは消した) |

行は変えずに写し、リンクの行き先だけ `specs/` からの相対に直した。行の突き合わせで、元の空でない行はすべて移った先にある。
移さなかったもの: `catalog/QA.md` と `catalog/reports/` (`make catalog` の生成物。場所を変えない)、
`scripts/workflows/` (Workflow の JavaScript と調査結果の JSON。コードとデータで、説明の文書ではない)、
ミラー本体の `.txt`・`robots.txt` (回収した原本)。

元の場所を指していた参照は張り直した: S.1・S.2・S.4・S.5・S.6・S.10・S.13・S.49・S.51・S.58・S.107・S.112・S.113・
S.123・S.125・S.140・S.146・S.148・S.151・S.171・S.172・S.176、docs/overview.md・docs/architecture.md・docs/operations.md・DOCS.md・SPECS.md、
`scripts/wp/authors_build.py` と `scripts/wp/qa_report.py` のコメント・出力文字列 (`catalog/QA.md` は `make qa` で書き直した)、
`catalog/episode_overrides.yml` のコメント。完了した T の記録 (S.156〜S.159・S.170) と食い違いの記録 (S.57) にある
「README」は当時のことなので直していない。

## 文書の検査

番号・リンク・一覧の一致・使わない語を機械で見る `scripts/doc_check.py` を作り、`make check` に入れた (S.219)。

## 登録した T

この作業で見つけた食い違いと残りを T.40〜T.43 に登録し、文書の保守を T.22.6〜T.22.8 に足した。

| T | 中身 |
|---|---|
| T.22.6 | 大項目を終えるごとに DOCS.md と docs/ の現在地を直す |
| T.22.7 | S.4 の分担表に docs/・DOCS.md・tasks/ の行を足す |
| T.22.8 | 所有者の評「少し修正を入れればロールアウト可」を S に写す |
| T.40 | S の数値・記述の食い違い 11 件 (作者・作品数、`/works/`、pixiv 再掲 2 作、世代の年代、`.cgi` の数、200 話、所要時間、ギャラリーの点数、ファイル数、S.113 の行番号、文庫の期間) |
| T.41 | S.38 の「任意の後続段階」の要否 |
| T.42 | ts_corpus の term description と robots.txt のコメントに残る内部語・撤回済みの記述 |
| T.43 | 判断・実装の主体の表記の扱い (2026-10-26 に「高品位なモデルとエフォート」へ統一と決定、実施済み) |
