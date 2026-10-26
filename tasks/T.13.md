# T.13 作業環境の移設 — 2026-10-26

- 状態: 完了
- 関連: S.168, S.82〜S.84, S.60

- [x] T.13.1 移設手順 S.82〜S.84。venv の版を固定 (slug = 恒久 URL を守るため)
- [x] T.13.2 新しい機 a1-free で再現を確認。make catalog の差分 0・make check の NG 0・payload は無損失、
      本番とは checksum で 0 件差
- [x] T.13.3 ~/.ssh/config に頼らない本番接続 scripts/wp/novels-ssh。deploy.sh と手順書を切り替える (S.60)
