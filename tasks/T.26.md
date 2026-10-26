# T.26 取り下げフォームと 10 日後の自動適用 (S.124〜S.136) (T.23 の後)

- 状態: 未了
- 関連: S.124〜S.136, S.113, S.69, S.46, S.75〜S.81, T.17, T.23, T.35, T.37

- [ ] T.26.1 (八重洲さんの判断) 裁定: S.135 の 6 パラメータ → S.135 の表に結果を書き、定数を決める
- [ ] T.26.2 (T.26.1 の後) S.124〜S.136 の既定値を裁定で置き換え、文面 (S.134) を確定 → S.113 の確定文と一致
- [ ] T.26.3 mu-plugin の実装 (`includes/takedown-request.php`)
  - [ ] T.26.3.1 CPT `ts_takedown_request` とメタ (S.125) → `wp ts reset` の TYPES に含まれないことをコードで確認
  - [ ] T.26.3.2 フォームと検証・レート制限 (S.124) → S.136 の検証手順のスパム系 4 ケースが期待どおり
  - [ ] T.26.3.3 確認リンクと失効 (S.126) → `received` と `auto_apply_at` が確認時刻 + 10 日
  - [ ] T.26.3.4 cron フック `ts_takedown_tick` と非公開化の処理 (S.126〜S.127, S.130) → `wp cron event run ts_takedown_tick` で `applied`・対象が private/410
  - [ ] T.26.3.5 運営委員の操作 4 つ (S.128) → 承認・却下・復帰・期限変更が記録つきで動く
  - [ ] T.26.3.6 `/removed/` の動的生成 `[ts_removed_list]` と removed.html の縮約 (S.131) → 既存 9 件 + Marie が同じ文言で出る
  - [ ] T.26.3.7 `wp ts export-takedowns` (S.132) → 出力を denylist に追記して `make check` OK。S.69 に 1 手順
  - [ ] T.26.3.8 通知 5 通 (S.129) → 本番で自分宛に 1 通送って到達
- [ ] T.26.4 cron の実行基盤 (S.130): サーバ crontab (控えを先に) と `DISABLE_WP_CRON` (高野さんの作業) → `wp cron event list` で `ts_takedown_tick` の次回時刻が進む
- [ ] T.26.5 content/pages/takedown.html を本番用の文面に (S.134。T.17 の暫定を置き換え) → sync-pages
- [ ] T.26.6 配備と検証: `wp db export` → deploy.sh --apply → `wp ts role-sync` → sync-pages → S.136 の検証手順のうち `wp ts reset` の確認を除く全部を満たす
- [ ] T.26.7 (任意、T.35.2 と同時) REST `/wp-json/ts/v1/withdrawn` と deploy-pages.yml の取り込み (S.133) (T.35.2 の後)
- [ ] T.26.8 削除リハーサル (T.37.2) をこの経路で実流し → S.75〜S.81 を実測で更新
