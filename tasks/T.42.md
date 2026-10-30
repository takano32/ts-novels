# T.42 読者に届く文面と配備物に残る内部語・撤回済みの記述 — 2026-10-26 に文書を 3 分類に揃えたときに登録

- 状態: 完了
- 関連: S.15, S.42, S.67, S.173, S.183, S.220, T.37

- [x] T.42.1 ts_corpus の term description に内部語が出ている (`scripts/wp/terms_build.py` の dojo「(Phase 6 で追加)」、uncatalogued「(設計 v1.4 最大掲載原則)」) → 読者向けの文に直し `make catalog && make check` OK、`catalog/terms.json` の description に内部語が無い (S.15, S.173) (2026-10-30。uncatalogued「ファイルは残っているが、どの目録にも載っていなかった収蔵物」、dojo「第二掲示板 (2ndbbs) に投稿された作品」)
- [x] T.42.2 (高野さんの作業) (T.42.1 の後) 本番で `wp ts sync-terms` → /corpus/dojo/ 等に内部語が出ない (curl `?_cb=`) (S.67) — 2026-10-30 全消し再構築の sync-terms で反映
- [x] T.42.3 `scripts/wp/assets/robots.txt` のコメントが撤回済みの「公開解禁までの全域 noindex」のまま → コメントを S.42 に合わせて直し、次の配備で本番の /robots.txt に反映 (S.42, S.183) (リポジトリ側のコメントは 2026-10-30 に直した。ディレクティブは 1 文字も変えていない。残りは配備) — 2026-10-30 配備済み
