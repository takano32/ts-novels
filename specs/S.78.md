# S.78 削除 ③: 原本アーカイブ (GitHub Pages) 側の除外

- 種類: 手順
- 状態: 有効
- 出典: docs/removal-runbook.md の「③ 別館 (GitHub Pages) 側の除外」
- 関連: T.18.3, S.44

## ③ 原本アーカイブ (GitHub Pages) 側の除外 (T+72h まで)

- Pages はヘッダを制御できないため 410 は返せない。**ビルド artifact から対象ファイルを落として 404**
  にするのが最大限 (S.35 の妥協点)。
- **【未実装 2026-08-31】** `scripts/wp/annex_inject.py` (T.35.2) はまだ無く、
  GitHub Pages はリポジトリ直下をそのまま配信するため **denylist は Pages 側に効かない**。
  第 3 層が必要な依頼を受けたら、当面はリポジトリから該当ファイルを削除してコミットする
  (履歴からの除去が要る場合は④へ)。実装までの経緯は S.155。
  **git の原本は消さない**(これは ④ の判断とは別レイヤ)。

```sh
git add takedown/denylist.yml && git commit -m "takedown: TD.1 を追加" && git push
# → deploy-pages.yml が走る。デプロイ完了後に確認
curl -sI "https://takano32.github.io/ts-novels/novel/200209/19204751/d_upboy06.htm" | head -1  # 404
```

- `annex_path` の glob 指定を使ったときは、**意図より広く消えていないか**を
  デプロイ前の artifact ディレクトリで数えて確認する。
