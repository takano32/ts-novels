# T.19 サイドバーの改修 — 「オススメの環」を外し「オーナー作品集」を足す (S.101, S.104)

- 状態: 未了
- 関連: S.101, S.104, S.114

- [ ] T.19.1 (八重洲さんの判断) 判断: `/index/osusume/` ページ自体の要否 / 項目名 / `/authors/yays/` の説明文 (所有者への質問のうち「オススメの環」と「オーナー作品集」(S.114))
- [ ] T.19.2 render.php `ts_bunko_sidebar()`: 「オススメの環」を外し「オーナー作品集」→ `/authors/yays/` を足す (S.101, S.104 の仕様) → `php -l` OK (2026-10-30 に変更済み。開発機に php が無いので `php -l` は deploy.sh の配備前の構文検査で行う。項目名は仮の「オーナー作品集」)
  - 置き場の事実: 「この文庫について」ブロックの先頭に置いた (S.104 が挙げた置き場のうち、`/index/` 以外の URL を並べるブロック)。もう一方の置き場は索引ブロックの末尾。T.19.1 で項目名と一緒に変えてよい
- [ ] T.19.3 (T.19.1 で一覧も消す場合) indexes.php の索引種別から `osusume` を外す → `/index/osusume/` 404・他の索引 200
- [ ] T.19.4 (T.19.1 で説明文を出す場合) `catalog/author_notes.yml` → authors.json `description` → `sync-terms` (S.104 の仕様) → `make check` OK、`yays` に description
- [ ] T.19.5 配備: deploy.sh --apply (+ `wp ts sync-terms`) → `/about/` の HTML に `オススメの環` 0 回・`オーナー作品集` 1 回以上 (href `/authors/yays/`)、`/authors/yays/` 200 → キャッシュクリア依頼 (高野さんの作業)
