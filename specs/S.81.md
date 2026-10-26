# S.81 削除の完了処理とチェックリスト

- 種類: 手順
- 状態: 有効
- 出典: docs/removal-runbook.md の「完了処理」「チェックリスト (印刷用)」
- 関連: S.75

## 完了処理

1. `denylist.yml` の `applied` 各層に実施日を入れ、`status: applied` → 実地確認後 `verified`。
2. **nginx キャッシュのクリアを依頼**する (高野さんの作業) (パネル操作のためセッションからは実行不可)。
   クリア前は 410 が反映されて見えないことがある。
3. 依頼者へ完了連絡 (④の限界を含めて)。
4. 次回 publish 時に `wp ts verify` の件数が減っていることを確認する。

## チェックリスト (印刷用)

- [ ] 当日中に一次返信
- [ ] denylist.yml にエントリ追加 (public に書いてはいけない情報を書いていないか)
- [ ] `wp db export` で復旧点
- [ ] ① `wp ts apply-takedown` → draft を件数で確認
- [ ] ② `.htaccess.bak-YYYYMMDD` → マーカーブロック差し替え → 410 と無関係 URL 200 を curl
- [ ] ③ denylist を push → Pages デプロイ後に 404 を curl
- [ ] ④ (依頼があれば) バックアップ clone → filter-repo → force push → 限界を通知
- [ ] ⑤ `/removed/` 掲載
- [ ] キャッシュクリア依頼 (高野さんの作業)
- [ ] denylist.yml を `verified` に更新してコミット
