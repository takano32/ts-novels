# S.149 レビュー資料 cluster_review.py

- 種類: 仕様
- 状態: 有効
- 出典: docs/cluster-overrides-spec.md の「4. レビュー資料」
- 関連: T.24.3

## 4. レビュー資料 `scripts/wp/cluster_review.py`

- 抽出: `works.jsonl` の連載 (`episode_count ≥ 2`) のうち、構成話の題名の芯 (`trim_title`) が 2 種以上あり、芯どうしの共通接頭辞 (NFKC・小文字) が 2 文字未満のもの。2026-10-26 時点で **71 作品** (根拠が規則 5/6 だけのもの 39 を先頭に、強い根拠のあるもの 32 を後に)。
- 出力 `catalog/review/clusters-YYYY-MM.md`: 作品ごとに見出し (`work_slug` / 機械の題名 / 根拠 / 話数) と表 (`episode_id` / 題名 / ファイル名 / 掲載日 / corpus)、末尾に「裁定」欄 (○ / 分割 / 切り離し / 併合 と、分割なら話の振り分け)。
- 一次案: セッションが芯ごとの振り分け案を「裁定」欄に書いておき、所有者は承認・修正だけで済むようにする (S.114 の 13-7 の推奨)。
- `--redirects` サブコマンド: S.151 の付け替え表を作る。
