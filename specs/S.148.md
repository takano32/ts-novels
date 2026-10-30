# S.148 題名の芯 (trim_title) の追加規則

- 種類: 仕様
- 状態: 有効
- 出典: docs/cluster-overrides-spec.md の「題名の芯 (trim_title) の追加規則」
- 関連: T.24.2, S.218, S.238

## 題名の芯 (`trim_title`) の追加規則

`RE_TITLE_TRIM` / `RE_TITLE_CUT` (`work_builder.py:59-69`) に **回 / Lesson N / LESSON N / EPISODE N / episode N / stage / その N / 其の N / № N / No. N** を足す。
効果: 末尾が助数詞で終わる作品題名 10 件 (`イマジン 第`、`本当の自分 第`、`〜その` など。S.218 (元の `catalog/review/works-risk-note.md`) の 1-F) が正しい芯になる。
slug が変わる作品は `catalog/slug_overrides.yml` の差分で数え、`catalog/review/clusters-YYYY-MM.md` の末尾に記録する。

実装 (2026-10-30) では巻号の数字に大字 (壱弐参…) を足し、stage は数字つきに限った。slug の変化は話の所属で突き合わせて数えた (`cluster_review.py --since`)。結果と slug の一覧は S.238。
