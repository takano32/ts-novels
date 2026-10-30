# T.24 作品クラスタの手動修正機構とレビュー (S.146〜S.154)

- 状態: 一部完了
- 関連: S.146〜S.154, S.210, S.238

- [x] T.24.1 `scripts/wp/work_builder.py`: overrides スキーマ v2 (`split` / `detach` / `merge_into` / `confirmed`) と `--check` の検証 (S.146〜S.147) → 既存の work_overrides.yml で `make catalog` の出力が不変 (差分 0) (2026-10-30。記録は S.238)
- [x] T.24.2 題名の芯の追加規則 (S.148) → `make catalog` 後に末尾が助数詞の作品題名 0 件。slug が変わった作品数を記録 (2026-10-30。連載の題名で 0 件、works 1,451 → 1,440、slug が変わった作品 28。旧 slug → 新 slug の一覧は S.238)
  - 配備の注意 (未決): この catalog (works.jsonl) を本番のサーバ側 repo に配備すると、再投入 (T.24.8) までの間 `wp ts verify` の投稿数の期待値が変わり (works + 話 − 単発: 1,451 + 3,844 − 932 = 4,363 → 1,440 + 3,844 − 920 = 4,364) 件数不一致になる。`wp ts import` を走らせると新しい slug の投稿ができ、旧 slug の投稿が残る (S.152)。選択肢: T.24.8 まで本番に配備するコミットの catalog/works.jsonl・slug_overrides.yml を 7d4778f4 のものに保つ / 配備はするが T.24.8 まで import をせず verify の件数不一致を既知として扱う
- [x] T.24.3 `scripts/wp/cluster_review.py` (S.149) → `catalog/review/clusters-2026-10.md` に 71 作品 (弱根拠 39 が先頭)、一次案つき。ツールの一覧 S.210 に 1 行 (2026-10-30。T.24.2 の後のデータで 71 作品・弱根拠 39。一次案 71 件をそのまま当てても検証の誤り 0。`--redirects` は T.24.6 で足す)
- [ ] T.24.4 (八重洲さんの判断) 裁定: 71 作品の ○ / 分割 / 切り離し / 併合 (S.150。一次案の承認でよい)
- [ ] T.24.5 (T.24.4 の後) 裁定を work_overrides.yml に転記 (S.150) → `make catalog && make check && make verify` OK、work_builder.json の works・needs_review・orphan を記録 → コミット
- [x] T.24.6 (2026-10-30 取りやめ: まだ誰もユーザがいないので 301 は不要。高野さんの決定、S.221) URL の付け替え表 (S.151): 本番で `wp ts export-pathmap > catalog/path_map.json` を先に取る → 再投入後の差分から `catalog/redirects.yml` → htaccess_block.py が 301 行を出す
- [ ] T.24.7 判断: 再投入の方式を全消し再構築 (高野さんの判断) か `import --prune` か (S.152) → 選んだ方の実装/手順を整える
- [ ] T.24.8 本番 (T.24.7 の後): `wp db export` → deploy.sh --apply → 再投入 → `wp ts verify` OK → `htaccess_block.py --install` → 新 URL 3 本が 200 (S.153。301 は取りやめ) → キャッシュクリア (高野さんの作業)
