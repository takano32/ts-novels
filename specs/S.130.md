# S.130 取り下げの cron

- 種類: 仕様
- 状態: 有効
- 出典: docs/takedown-form-spec.md の「7. cron」
- 関連: T.26.3.4, T.26.4

## 7. cron

- フック `ts_takedown_tick` を 1 時間おきに登録 (`wp_schedule_event`)。処理: 失効 → リマインド → 自動適用 (S.126 の順)。1 回の tick で処理する件数に上限は置かない (件数は少ない)。
- 実行基盤: WP-Cron は閲覧駆動で、前段 nginx がキャッシュから返す閲覧では PHP が動かない (S.62) ため、**サーバの crontab** に
  `*/10 * * * * cd ~/novels.xwp.jp/public_html && wp cron event run --due-now >/dev/null 2>&1` を足し (`crontab -l` の控えを先に)、
  (高野さんの作業) `wp-config.php` (公開ルート外) に `define('DISABLE_WP_CRON', true);` を入れる。
- 監視: `wp cron event list` に `ts_takedown_tick` が出て `next_run_relative` が進むこと。失敗は `ts_takedown_last_tick` option に時刻とエラーを残し、`wp ts verify` が 24 時間以上止まっていれば警告する。
