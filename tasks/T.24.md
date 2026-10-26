# T.24 作品クラスタの手動修正機構とレビュー (S.146〜S.154)

- 状態: 未了
- 関連: S.146〜S.154, S.210

- [ ] T.24.1 `scripts/wp/work_builder.py`: overrides スキーマ v2 (`split` / `detach` / `merge_into` / `confirmed`) と `--check` の検証 (S.146〜S.147) → 既存の work_overrides.yml で `make catalog` の出力が不変 (差分 0)
- [ ] T.24.2 題名の芯の追加規則 (S.148) → `make catalog` 後に末尾が助数詞の作品題名 0 件。slug が変わった作品数を記録
- [ ] T.24.3 `scripts/wp/cluster_review.py` (S.149) → `catalog/review/clusters-2026-10.md` に 71 作品 (弱根拠 39 が先頭)、一次案つき。ツールの一覧 S.210 に 1 行
- [ ] T.24.4 (八重洲さんの判断) 裁定: 71 作品の ○ / 分割 / 切り離し / 併合 (S.150。一次案の承認でよい)
- [ ] T.24.5 (T.24.4 の後) 裁定を work_overrides.yml に転記 (S.150) → `make catalog && make check && make verify` OK、work_builder.json の works・needs_review・orphan を記録 → コミット
- [ ] T.24.6 URL の付け替え表 (S.151): 本番で `wp ts export-pathmap > catalog/path_map.json` を先に取る → 再投入後の差分から `catalog/redirects.yml` → htaccess_block.py が 301 行を出す
- [ ] T.24.7 判断: 再投入の方式を全消し再構築 (高野さんの判断) か `import --prune` か (S.152) → 選んだ方の実装/手順を整える
- [ ] T.24.8 本番 (T.24.7 の後): `wp db export` → deploy.sh --apply → 再投入 → `wp ts verify` OK → `htaccess_block.py --install` → 旧 URL 3 本が 301 で新 URL 200 (S.153) → キャッシュクリア (高野さんの作業)
