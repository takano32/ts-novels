# S.193 リーダー UI (T.10.2、元の 4.2)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 4.2
- 関連: T.10.2, T.39.2, S.27

- [x] 4.2 リーダー UI(素 JS+localStorage): 文字サイズ/行間/明朝ゴシック/ダーク/しおり/左右キーで話ナビ。
      raw フォールバック話のみ「原本配色」トグル
      - **4.2 実測 (2026-08-30)**: 話ページに **`data-ts-toolbar` の操作列**が出力され、
        **`reader.js` が 200 application/javascript 3,837B** で配信される
        (`themes/ts-bunko/js/reader.js`)。
        **ブラウザ操作を伴う挙動(文字サイズ変更・しおりの localStorage 保存・
        ダークモード切替の実動作)はセッションから検証できない** —
        マークアップと JS の配信までを確認した。実挙動の確認は人間 (所有者レビュー T.29 の時)
