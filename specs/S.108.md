# S.108 アイコン類 (将来)

- 種類: 設計
- 状態: 有効
- 出典: docs/owner-feedback-2026-10.md の「アイコン類 (将来)」
- 関連: T.28

## アイコン類 (将来)

**現状**: テーマ `themes/ts-bunko` は外部依存ゼロ (S.27。Google Fonts 不使用)、`style.css` 10KB、JS は `reader.js` のみ。アイコンは無い。

**仕様 (後回し)**: テーマ内のインライン SVG スプライト (`themes/ts-bunko/assets/icons.svg`、`<use>`) か CSS mask。置き場: 作品カードの題名前 (ブック型)・話ページのパンくず・索引の各項目 (種別)。外部 CDN・アイコンフォントは使わない。`currentColor` でダークモード追随。配備は deploy.sh のテーマ同期で済む。
**未決**: 所有者が素材を用意するか、既製 (MIT ライセンスのアイコン集) か。
**規模**: 半日。T.28 (最後)。
