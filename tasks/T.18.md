# T.18 Marie 氏の作品の取り下げ (S.103)

- 状態: 一部完了
- 関連: S.103, S.114, S.119, S.78, S.79

- [ ] T.18.1 (八重洲さんの判断) 確認: 申し出の年 / 名前の掲示 / 同居 2 ファイルの扱い / 感想板 30 ファイルの扱い / git 履歴の除去 (S.103 の未決・S.114 の 3)
- [ ] T.18.2 WP 側 (2026-10-27 に T.18.2.1〜T.18.2.4 を実施: 33 投稿 draft・51 URL 410。掲示 T.18.2.5 は回答待ち) (既存機構。T.18.1 の「名前の掲示」だけ要る)
  - [x] T.18.2.1 takedown/denylist.yml に TD.14 を S.103 の仕様 1 どおりに追加 → `make check` OK
  - [x] T.18.2.2 scripts/wp/htaccess_block.py の `ssh` 直書きを `scripts/wp/novels-ssh` 経由に (S.119) → dry-run が現行 18 行の 410 を表示
  - [x] T.18.2.3 本番: `wp db export` → `wp ts apply-takedown --dry-run` が 33 件 → 実行 → `wp post list --post_type=ts_work --post_status=draft --format=count` = 51
  - [x] T.18.2.4 本番: `htaccess_block.py --install` OK → 取り下げ URL 2 本が 410・`/authors/marie/` 200 で作品 0 件・`/about/` 200 (S.103)
  - [ ] T.18.2.5 content/pages/removed.html に 1 行 (T.18.1 の文言) → sync-pages → curl 確認。denylist の `applied` 3 層に日付・`status: applied` → コミット
- [ ] T.18.3 原本アーカイブ側 (T.18.1 の後。S.78 の暫定手順 = リポジトリから削除)
  - [x] T.18.3.1 S.103「取り下げ対象の一覧」の本人ファイル 28 (+ T.18.1 で決めた分) を `git rm` → `make catalog && make check` OK (QA.md で source_exists=False の 28 話を確認)
  - [x] T.18.3.2 (2026-10-27 代表 3 本の 404 を確認。同居 2 ファイル等は回答待ち) push → Pages で代表 3 本が 404 (curl)。denylist の `annex_excluded` に日付・`status: verified` → コミット
- [ ] T.18.4 (八重洲さんの判断) git 履歴の除去は依頼があった場合のみ (S.79)。T.18.1 で「行う」なら別の大項目として起票
