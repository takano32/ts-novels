# T.25 版違いの整理 — 新しい版を残し旧版は運営委員のみ (S.137〜S.145) (T.23・T.24 の後)

- 状態: 未了
- 関連: S.137〜S.145, S.114, S.49〜S.58, S.92, S.43, S.72, S.210, S.212, T.23, T.24

- [ ] T.25.1 (八重洲さんの判断) 判断: 閾値 (0.8〜0.95 を自動にするか) / 旧目録版の非表示 / 大改稿の扱い (S.139、S.114 の 6)。未回答なら既定で進める
- [ ] T.25.2 `scripts/wp/versions_build.py` (S.138〜S.140) と Makefile への組み込み
  - [ ] T.25.2.1 束ねと判定 (S.138〜S.139) → `catalog/reports/versions_build.json` の件数が S.144 の基準値と整合 (組 303・自動 209・要確認 89、閾値既定)
  - [ ] T.25.2.2 出力と `--check` (S.140) → works.jsonl に `superseded_by` / `all_superseded`、`make check` OK、`versions_overrides.yml` に review の雛形
  - [ ] T.25.2.3 work_builder.py の alias 正本を「最新」の規則に (S.139) → `make catalog` で alias 71 話の正本が規則どおり
  - [ ] T.25.2.4 欄の説明 S.212 (元の catalog/README.md) とツールの一覧 S.210 (元の scripts/README.md) に追記 (S.140)
- [ ] T.25.3 (八重洲さんの判断) 要確認の組 (既定 89) の `decision` を記入 → `make catalog` → 件数を記録してコミット
- [ ] T.25.4 import とテーマ (T.23.3 の後)
  - [ ] T.25.4.1 `commands.php`: `superseded_by` → private + メタ、無ければ publish、`all_superseded` の親、`HASH_VER` v6 (S.141) → `--dry-run` 2 回目が created=0 / updated=0
  - [ ] T.25.4.2 `visibility.php` / `render.php`: 旧版の 301・帯・新版の「旧版あり」(S.141) → `php -l` OK
  - [ ] T.25.4.3 `wp ts verify` に visibility 別の件数 (S.141)
- [ ] T.25.5 本番: `wp db export` → deploy.sh --apply → `wp ts import --bodies=…/payloads` → `wp ts verify` OK → S.143 の表を満たす → キャッシュクリア (高野さんの作業)
- [ ] T.25.6 文書: S.145 の 4 箇所 (S.49〜S.58・S.92 の収録規模、S.43、S.72) を更新
