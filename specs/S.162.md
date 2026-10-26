# S.162 T.7 catalog の確立 の記録

- 種類: 記録
- 状態: 有効
- 出典: docs/worklog.md の「T.7 …」の節
- 関連: T.7

## T.7 catalog の確立 (S.171〜S.180) — 2026-08-30

- [x] T.7.1 catalog_build.py: 目録 2,887 話 → episodes.jsonl (S.171)
- [x] T.7.2 旧目録 lib01〜09 の差分パース (S.172)
- [x] T.7.3 分類語彙の正規化と terms.json (S.173)
- [x] T.7.4 作者の同定と authors.json (S.174)
- [x] T.7.5 Episode → Work のクラスタリング (S.175)
  - [x] T.7.5.1 slug の人間レビュー資料 (S.176)。作者 slug 40 件と同名 2 組を裁定して confirmed にする。作者は 342 → 322 名
- [x] T.7.6 本文の Markdown 変換: 3,641 話、無損失を証明 (S.177)。
      html5lib の第二実装で独立に検算し、変換器のバグ 4 種を修正 (99.95%)。bodies/ を git 管理から外す
- [x] T.7.7 make catalog と QA レポート (S.180)
- [x] T.7.8 目録外の収蔵 859 話・作者による再掲 2 作を収録 (S.178 / S.179)
- [x] T.7.9 板ログの原本が未回収の作者 32 名をレポートに記録
