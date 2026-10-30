# S.81 削除の完了処理とチェックリスト

- 種類: 手順
- 状態: 有効
- 出典: docs/removal-runbook.md の「完了処理」「チェックリスト (印刷用)」
- 関連: T.23.6, S.75, S.76, S.77, S.118

## 完了処理

1. `denylist.yml` の `applied` 各層に実施日を入れ、`status: applied` → 実地確認後 `verified`。
2. **nginx キャッシュのクリアを依頼**する (高野さんの作業) (パネル操作のためセッションからは実行不可)。
   取り下げる前に 200 で見えていたページはキャッシュに残っていることがあり、クリア前は 410 が反映されて見えないことがある
   (WordPress が返す 410 と運営委員向けのページは `nocache_headers()` つきでキャッシュに載らない。S.118)。
3. 依頼者へ完了連絡 (git 履歴からの除去 (S.79) の限界を含めて)。
4. `wp ts verify` が OK のままであることを確かめる (取り下げた投稿も消さずに private で残すので、件数の照合は変わらない)。

## チェックリスト (印刷用)

- [ ] 当日中に一次返信
- [ ] denylist.yml にエントリ追加 (public に書いてはいけない情報を書いていないか)
- [ ] `wp db export` で復旧点
- [ ] WP サイトの非公開化 (S.76): denylist を push・配備 → `wp ts apply-takedown --dry-run` → `wp ts apply-takedown` (2 回目は変更なし) → private の件数が増え draft が 0 → 対象 URL がログインなしで 410
- [ ] 本文を空にする (S.76 の論理削除): `payload_build.py` → 配備 → `wp ts import`
- [ ] 画像の 410 (S.77。`kind: annex_path` があるときだけ): `htaccess_block.py` の表示 → `--install` が [4/4] OK
- [ ] 原本アーカイブ側の除外 (S.78): denylist を push → Pages デプロイ後に 404 を curl
- [ ] git 履歴からの除去 (S.79。依頼があれば) バックアップ clone → filter-repo → force push → 限界を通知
- [ ] /removed/ への掲載 (S.80)
- [ ] キャッシュクリア依頼 (高野さんの作業)
- [ ] denylist.yml を `verified` に更新してコミット
