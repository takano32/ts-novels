# S.62 リクエストの通り道と PHP の設定

- 種類: 実測
- 状態: 有効
- 出典: docs/environment.md の「リクエストの通り道と PHP の設定」
- 関連: T.23.1, S.120, T.40.2

## リクエストの通り道と PHP の設定

```
   ブラウザ / curl
        │  HTTPS (HTTP/2)
        ▼
   ┌──────────┐  すべてのレスポンスに `server: nginx`
   │  nginx   │  ・静的ファイルのキャッシュ層 (wpX の「高速化」機構)
   │ (キャッシュ)│  ・.htaccess の先頭 2 行がこの層を制御している:
   └────┬─────┘      SetEnvIf Request_URI ".*" Ngx_Cache_NoCacheMode=off
        │              SetEnvIf Request_URI ".*" Ngx_Cache_StaticMode
        ▼
   ┌──────────┐  .htaccess 有効 (mod_rewrite / Header / Redirect gone / FilesMatch)
   │  Apache  │
   └────┬─────┘
        ▼
   ┌──────────┐  PHP 8.0.30 (CLI も web も同じ)
   │   PHP    │
   └────┬─────┘
        ▼
     WordPress 7.1  →  MariaDB 10.11 (localhost, DB `novels_wp1`, prefix `wp_`)
```

### キャッシュ層が検証に及ぼす影響

**実測した証拠**: 同じサイトで

| リクエスト | `cache-control` / `expires` ヘッダ |
|---|---|
| `GET /` (200) | **無し** (nginx キャッシュから返っている) |
| `GET /nonexistent.html` (404) | `expires: Wed, 11 Jan 1984 …` / `cache-control: no-cache, must-revalidate, max-age=0, no-store, private` (WordPress が生成したもの) |

WordPress が本来付けるはずのヘッダが 200 応答から消えている = 前段でキャッシュされている。

**したがって検証 curl には毎回キャッシュバスターを付ける**:

```sh
curl -sI "https://novels.xwp.jp/novel/johdan-senotomeseera/?_cb=$RANDOM"
```

- キャッシュの全消しは**サーバー管理パネルのブラウザ操作**でしかできない → 人間のタスク (高野さんの作業)。
- **「反映されない」と見えたら、まずキャッシュを疑うこと。**
  キャッシュバスター付き URL で正しい応答が返るなら、それは反映済みでキャッシュだけが古い。
  ここで `.htaccess` や mu-plugin を修正し続けるループに入らない(S.7 に明記された既知の罠)。
- 既存の crontab に「3 日より古い `wp-content/cache/*` を毎日 04:37 に削除」が入っている
  (WordPress 側キャッシュの掃除。nginx 側とは別)。

### ログイン中の応答とキャッシュ (2026-10-30 実測。T.23.1、手順は S.120)

試験ユーザ (編集者) のログイン Cookie を WP-CLI で発行し、DevTools のブラウザに入れて測った (ログイン URL は使っていない)。ログインなしは curl。

| 対象 | ログインなし・バスターなし | ログインなし・`?_cb=` | ログイン・バスターなし | ログイン・`?_cb=` |
|---|---|---|---|---|
| 公開中の作品 (`/novel/johdan-senotomeseera/`) | 200、cache-control なし | 200、cache-control なし | 200、管理バーあり、`no-cache, must-revalidate, max-age=0, no-store, private` | 同左 |
| 下書きの作品 (`/?post_type=ts_work&p=8881&preview=true`、取り下げ済み) | 404 | 404 | 200 (本文が表示される)、管理バーあり、private | 同左 |

判定 (S.120): ログイン Cookie 付きの要求は、バスターが無くても nginx のキャッシュを素通りして WordPress がその場で描画する。よって **WordPress が 410 を返す方式 (S.119) で進められる**。ログインなしの要求には下書きの中身は出ない。

### PHP の実行制限 (`.user.ini` 実測)

| 項目 | 値 | 効き所 |
|---|---|---|
| `memory_limit` | **1G** | 大量インポートでも余裕 |
| `max_execution_time` | **180 秒** | web 経由の重い処理は落ちる。**インポートは必ず WP-CLI(CLI は無制限)で** |
| `post_max_size` / `upload_max_filesize` | 1G | — |
| `display_errors` | On | 画面にエラーが出る。公開前に要確認 |
| `default_charset` | UTF-8 | — |
