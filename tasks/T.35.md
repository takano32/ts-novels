# T.35 原本アーカイブとの連携 (S.44) (T.29 の後)

- 状態: 未了
- 関連: S.44, T.16, T.26, T.29, T.31

- [ ] T.35.1 `wp ts export-pathmap > catalog/path_map.json` (原パス + alias → WP URL) (S.44 の 5.1)
- [ ] T.35.2 Pages をビルド注入方式へ: `scripts/wp/annex_inject.py` + deploy-pages.yml の改修 (S.44 の 5.2)
  - [ ] T.35.2.1 mailto 除去・1 行バナー・meta noindex・denylist 除外 → 受け入れ条件は S.44 の 5.2
  - [ ] T.35.2.2 `.cgi` 保存画面の配信形式 (T.16.5) と取り下げ一覧の取り込み (T.26.7) を同じビルドに入れる
  - [ ] T.35.2.3 A' を選んだ場合は T.31.2 の伏せ字もここに入れる
- [ ] T.35.3 WP 書誌カードの「原本を見る」を Pages の実 URL で検証 (サンプル 200) (S.44 の 5.3)
