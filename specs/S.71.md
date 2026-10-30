# S.71 publish のたびに残すものとチェックリスト

- 種類: 手順
- 状態: 有効
- 出典: docs/publish-runbook.md の「publish のたびに残すもの」「チェックリスト」
- 関連: T.38.1, T.23.6, S.69, S.116, S.121

## publish のたびに残すもの

- `wp db export` を私有ストレージへ (T.38.1)
- `catalog/QA.md` の数値 (件数・合格率・needs_review 数・provenance 被覆率)
- このランブックの**実測所要時間の更新**

## チェックリスト

- [ ] `make catalog` → `catalog/QA.md` を目視
- [ ] catalog の git diff が意図どおり → コミット
- [ ] `deploy.sh` (dry-run) → `deploy.sh --apply` (`--delete` を使っていないこと)
- [ ] `wp db export` で復旧点
- [ ] `role-sync` → `sync-terms` → `import --dry-run` → `import` → `apply-takedown` (ホームから `scripts/wp/novels-ssh '…'` で。高野さんの作業)
- [ ] private が取り下げの件数・draft が 0
- [ ] もう一度 `import` して created=0 / updated=0 (冪等性)
- [ ] `wp ts verify` + 主要 URL の curl (バスター付き。取り下げた作品はログインなしで 410)
- [ ] 画像の取り下げを足した・変えたときだけ `htaccess_block.py` → `--install` (S.77)
- [ ] キャッシュクリア依頼 (高野さんの作業)
- [ ] ダンプを私有ストレージへ
