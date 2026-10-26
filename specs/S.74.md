# S.74 レビューの受け付け方 (NG が出たとき)

- 種類: 手順
- 状態: 有効
- 出典: docs/rebuild-runbook.md の「レビューの受け付け方」
- 関連: T.29.3, S.95

## レビューの受け付け方(サイト所有者が NG を出すとき)

1 件ずつでよいので「**URL(または episode_id)+ 何が違うか**」の形で。例:
- 「/authors/hirariman/ の slug は hirariiman が良い」→ slug_overrides 1 行+S.73 の全消し再構築
- 「この話の作者が違う」→ episode_overrides 1 行+S.73 の catalog を作り直して反映する再走
- 「この作品は非公開に」→ denylist 1 行+apply-takedown

まとまった NG は 日付つきの記録の S (specs/) として放り込んでもらえれば、
実装セッションが 1 件ずつ上表に振り分けて処理する。
