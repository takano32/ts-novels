# T.26 取り下げフォームと 10 日後の自動適用 (S.124〜S.136) (T.23 の後)

- 状態: 一部完了
- 関連: S.124〜S.136, S.113, S.69, S.46, S.75〜S.81, T.17, T.23, T.35, T.37

- [ ] T.26.1 (八重洲さんの判断) 裁定: S.135 の 6 パラメータ → S.135 の表に結果を書き、定数を決める
- [ ] T.26.2 (T.26.1 の後) S.124〜S.136 の既定値を裁定で置き換え、文面 (S.134) を確定 → S.113 の確定文と一致
- [ ] T.26.3 mu-plugin の実装 (`includes/takedown-request.php`) — 2026-10-30 T.26.3.1〜T.26.3.7 を実装 (`includes/takedown-request.php`・`includes/takedown-admin.php`・`includes/commands.php`)。PHP 8.0 / 8.2 の `php -l` と `scripts/wp/ts_library_test.php` (WordPress を使わない部分) で試験済み。手元に WordPress を立てた試験はしていない。決めて作った所は S.124・S.125・S.127・S.128・S.130〜S.132・S.121 の実装の記録
  - [x] T.26.3.1 CPT `ts_takedown_request` とメタ (S.125) → `wp ts reset` の TYPES に含まれないことをコードで確認 — `scripts/wp/ts_library_test.php` が TYPES と `wp ts reset --help` の説明文で確かめる
  - [ ] T.26.3.2 フォームと検証・レート制限 (S.124) → S.136 の検証手順のスパム系 4 ケースが期待どおり — 実装済み。4 ケースの判定 (honeypot 400・3 秒未満 400・4 件目 429・他サイトの URL は保存して結びつかない印) は単体の試験で OK。残りは配備後に本番で確かめる (T.26.6) (実際の送信と状態コード、`REMOTE_ADDR`・nginx のキャッシュ・`/wp-admin/` の接続制限。S.124 の実装の記録)
  - [ ] T.26.3.3 確認リンクと失効 (S.126) → `received` と `auto_apply_at` が確認時刻 + 10 日 — 実装済み。時刻の計算と 7 日の失効の判定は単体の試験で OK。残りは配備後に本番で確かめる (T.26.6)
  - [ ] T.26.3.4 cron フック `ts_takedown_tick` と非公開化の処理 (S.126〜S.127, S.130) → `wp cron event run ts_takedown_tick` で `applied`・対象が private/410 — 実装済み。失効・リマインド・自動適用・保持期限の判定は単体の試験で OK。残りは配備後に本番で確かめる (T.26.6)
  - [ ] T.26.3.5 運営委員の操作 4 つ (S.128) → 承認・却下・復帰・期限変更が記録つきで動く — 実装済み。状態の遷移の表は単体の試験で OK。残りは配備後に本番で確かめる (T.26.6) (管理画面のボタン)
  - [ ] T.26.3.6 `/removed/` の動的生成 `[ts_removed_list]` と removed.html の縮約 (S.131) → 既存 9 件 + Marie が同じ文言で出る — ショートコードは実装済み。いまの denylist から 10 行が `public_note` の文言のまま出ることは単体の試験で OK。removed.html の縮約は前置きの文面 (S.134 は案) と Marie 氏の名前の掲示 (denylist の S.235 の notes で所有者の回答待ち) の判断が要るので残す (T.26.5 と同時)
  - [ ] T.26.3.7 `wp ts export-takedowns` (S.132) → 出力を denylist に追記して `make check` OK。S.69 に 1 手順 — 実装済み・S.69 に手順を追加。書き出した YAML を denylist の読み取りで読み戻す試験は OK。残りは配備後に本番で確かめる (T.26.6) (実際の申請の出力を足して `make check`)
  - [ ] T.26.3.8 通知 5 通 (S.129) → 本番で自分宛に 1 通送って到達
- [ ] T.26.4 cron の実行基盤 (S.130): サーバ crontab (控えを先に) と `DISABLE_WP_CRON` (高野さんの作業) → `wp cron event list` で `ts_takedown_tick` の次回時刻が進む
- [ ] T.26.5 content/pages/takedown.html を本番用の文面に (S.134。T.17 の暫定を置き換え) → sync-pages
- [ ] T.26.6 配備と検証: `wp db export` → deploy.sh --apply → `wp ts role-sync` → sync-pages → S.136 の検証手順のうち `wp ts reset` の確認を除く全部を満たす
- [ ] T.26.7 (任意、T.35.2 と同時) REST `/wp-json/ts/v1/withdrawn` と deploy-pages.yml の取り込み (S.133) (T.35.2 の後)
- [ ] T.26.8 削除リハーサル (T.37.2) をこの経路で実流し → S.75〜S.81 を実測で更新
