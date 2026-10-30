# T.23 運営委員ロールと可視性の基盤 — Marie 氏の取り下げ・版違い・取り下げフォームの前提 (S.116〜S.123)

- 状態: 一部完了
- 関連: S.116〜S.123, S.62, S.43, S.75〜S.81, S.18, S.67, S.69〜S.71, S.215, T.27

- [ ] T.23.1 実測: nginx キャッシュとログインの関係を S.120 の手順で → 結果を S.62 に表で追記 (試験ユーザは削除) — 2026-10-30 実測済み・S.62 に記録 (ログイン Cookie はキャッシュを素通り)。試験ユーザ ts-probe-t231 は T.23.5 の検証で使うので、その後に削除 (高野さんの作業)
- [x] T.23.2 判断 (T.23.1 の後): WordPress が 410 を返す方式か `.htaccess` の Cookie 条件方式か (S.119) → S.119 と S.43 に結論を 1 行追記 — 2026-10-30 S.120 の判定の決まりどおり WordPress が 410 を返す方式 (ログイン Cookie はキャッシュを素通り。S.62)
- [ ] T.23.3 mu-plugin の実装 (T.23.2 の後)
  - [ ] T.23.3.1 `includes/roles.php`: ロール 2 種と `wp ts role-sync` (S.116) → 2 回目が created=0 / updated=0
  - [ ] T.23.3.2 `includes/visibility.php`: 可視性メタ・410/404・帯・題名接頭辞 (S.117〜S.118)、`themes/ts-bunko/ts-410.php` → `php -l` OK
  - [ ] T.23.3.3 `commands.php` `apply_takedown` を S.121 どおりに (draft → private、復帰、dry-run 出力) → dry-run の件数が S.121 の形式
  - [ ] T.23.3.4 `scripts/wp/htaccess_block.py` を S.119 の改訂後の内容に (WP URL の gone を書かない、転送の行は書かない) → dry-run に `/novel/` の gone 行が無い
- [ ] T.23.4 配備と移行: `wp db export` → deploy.sh --apply → `wp ts role-sync` → `wp ts apply-takedown` → private = 51・draft = 0 → `htaccess_block.py --install` → `wp ts verify` OK
- [ ] T.23.5 検証: S.122 の表をすべて満たす (試験ユーザで帯を確認し削除)
- [ ] T.23.6 文書: S.123 の 5 文書 (S.75〜S.81 の改訂・S.18・S.67・S.215 (元の takedown/README)・S.69〜S.71) を更新
- [ ] T.23.7 (高野さんの作業) 所有者のアカウント発行は T.27.5 で (種別の回答が要る)
