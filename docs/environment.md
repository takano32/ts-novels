# 本番環境 (novels.xwp.jp) と作業ガード

WordPress 本館の本番ホストについて、**実際に ssh して確かめた事実**だけを書いた参照文書。
手順は書かない — 公開の流れは [`publish-runbook.md`](publish-runbook.md)、
削除は [`removal-runbook.md`](removal-runbook.md)、実装の進行は
[`wp-implementation-tasks.md`](wp-implementation-tasks.md) が正。
リポジトリに何が入っているかは [`data-inventory.md`](data-inventory.md)。
用語(本館/別館/旧館 ほか)は [`glossary.md`](glossary.md) を参照。
**本館・別館・旧館は内部用語**で、読者に見せる文面では使わない(→ glossary「三館の呼び分け」)。

## 0. 計測の基準

| 項目 | 値 |
|---|---|
| 調査日 | 2026-08-30 |
| 方法 | `ssh novels` での**読み取り専用**コマンド + 外部からの `curl`(HTTP GET / HEAD)のみ。設定変更・ファイル作成は一切行っていない |
| 未検証事項 | §4 の末尾に明記 |

---

## 1. 接続と身元

```sh
ssh novels            # ~/.ssh/config に設定済み。鍵認証・パスフレーズなし
```

| 項目 | 実測値 |
|---|---|
| ログインユーザ | `novels` (グループ `members`)。**一般ユーザ権限。root なし・Docker 不可** |
| ホスト名 | `sv3.xwp.ne.jp` |
| OS | Rocky Linux 8.10 (Green Obsidian) / カーネルは Ubuntu ベースの共有基盤 (`5.15.0-186-bet02-generic`) |
| ホーム | `/home/novels` |
| シェル | `/bin/bash` |
| プロセス上限 | `ulimit -u` = 2000 |
| サービス | Xserver for WordPress (wpX 系)。公開 URL は **`https://novels.xwp.jp`** |

ssh のログインバナーに post-quantum 鍵交換の警告が出るが、接続自体は正常。

---

## 2. ディレクトリ構成

```
/home/novels/
├── .bash_history .bash_profile .bashrc .ssh/
├── ssl/
└── novels.xwp.jp/                 ← ドメイン単位のルート
    ├── wp-config.php              ★ 公開ルートの外側にある(rsync の --delete 禁止の理由)
    ├── public_html/               ★ 公開ルート = WordPress の設置ディレクトリ
    │   ├── .htaccess              (40 行。§5)
    │   ├── .user.ini              (PHP 設定。§3)
    │   ├── index.php  wp-*.php  wp-admin/  wp-includes/
    │   └── wp-content/
    │       ├── plugins/  themes/  languages/  upgrade/  uploads/
    │       └── (mu-plugins/ は**まだ存在しない** — タスク 2.1 で作る)
    ├── autoreply/  htpasswd/  log/  mail/  script/   (いずれも空)
    └── xserver_php/php.ini
```

**重要**:

- **`wp-config.php` は `~/novels.xwp.jp/` 直下**にあり、`public_html/` の中ではない。
  publish-runbook が `rsync --delete` を禁じているのはこのため
  (`~/novels.xwp.jp/` を宛先にして `--delete` すると wp-config.php が消える)。
- `~/dumps` は**まだ存在しない**。`wp db export` の前に `mkdir -p ~/dumps` が要る。
- `~/novels.xwp.jp/log/` は空。**アクセスログ・エラーログはユーザから読めない**
  → 検証は curl のステータスコードとヘッダに頼るしかない(§7)。
- ディスクは `/dev/md0` 56T 中 3.0T 使用(6%)。`public_html` は現在 131MB。
  ミラー全体 443MB を置いても余裕がある(ただしクォータは `quota` コマンドが無く未確認)。

---

## 3. リクエストの通り道と PHP の設定

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
| `GET /` (200) | **無し** ← nginx キャッシュから返っている |
| `GET /nonexistent.html` (404) | `expires: Wed, 11 Jan 1984 …` / `cache-control: no-cache, must-revalidate, max-age=0, no-store, private` ← WordPress が生成したもの |

WordPress が本来付けるはずのヘッダが 200 応答から消えている = 前段でキャッシュされている。

**したがって検証 curl には毎回キャッシュバスターを付ける**:

```sh
curl -sI "https://novels.xwp.jp/works/?_cb=$RANDOM"
```

- キャッシュの全消しは**サーバー管理パネルのブラウザ操作**でしかできない → 👤 タスク。
- **「反映されない」と見えたら、まずキャッシュを疑うこと。**
  キャッシュバスター付き URL で正しい応答が返るなら、それは反映済みでキャッシュだけが古い。
  ここで `.htaccess` や mu-plugin を修正し続けるループに入らない(台帳の心得に明記された既知の罠)。
- 既存の crontab に「3 日より古い `wp-content/cache/*` を毎日 04:37 に削除」が入っている
  (WordPress 側キャッシュの掃除。nginx 側とは別)。

### PHP の実行制限 (`.user.ini` 実測)

| 項目 | 値 | 効き所 |
|---|---|---|
| `memory_limit` | **1G** | 大量インポートでも余裕 |
| `max_execution_time` | **180 秒** | web 経由の重い処理は落ちる。**インポートは必ず WP-CLI(CLI は無制限)で** |
| `post_max_size` / `upload_max_filesize` | 1G | — |
| `display_errors` | On | 画面にエラーが出る。公開前に要確認 |
| `default_charset` | UTF-8 | — |

---

## 4. `.cgi` などの拡張子がブロックされる制約 ★最重要

### 4.1 実測結果 (外部からの curl。すべてキャッシュバスター付き)

| リクエストした URL | 応答 |
|---|---|
| `/` | **200** |
| `/nonexistent-probe.html` (存在しない) | **404** (WordPress の 404) |
| `/probe.cgi` (存在しない) | **403** |
| `/probe.pl` `/probe.py` `/probe.shtml` | **403** |
| `/probe.cgi.html` (`.cgi` の後ろに別拡張子) | **403** ← **改名では回避できない** |
| `/probe@x.cgi` `/x@y.cgi` (`@` マングル名) | **403** |
| `/probe.cgi?a=1` | **403** |
| `/probe.CGI` (大文字) | **404** ← ルールは**大文字小文字を区別する** |
| `/a/probe.cgi` (サブディレクトリ・存在しない) | **404** |
| `/~ts/kansou/bbs@log_johdan.cgi` (存在しない) | **404** |
| `/~x/y.cgi` | **404** |

403 のボディは **XSERVER 製の静的 403 ページ**(EUC-JP、2,843 バイト、`etag` 付き)。

### 4.2 仕組み — `.htaccess` に明示のブロックがある

`public_html/.htaccess` の末尾に、Xserver の WordPress パネルが自動生成したブロックが入っている:

```apache
# BEGIN XS_WPPANEL_WP_ROOT_CGI_SSI
<FilesMatch "\.(cgi|pl|py|sh|phar|shtml|shtm)(\.|/|$)">
  Require all denied
</FilesMatch>
# END XS_WPPANEL_WP_ROOT_CGI_SSI
```

- 正規表現の `(\.|/|$)` が「`.cgi` の直後にドットかスラッシュが来ても一致」を意味する。
  **`foo.cgi.html` への改名が効かない理由はこれ**。
- 対象拡張子は `.cgi .pl .py .sh .phar .shtml .shtm` の 7 種。

### 4.3 これまでの記述との差分(要修正)

台帳と設計 v1.1 は「**前段 nginx が一律 403**。RemoveHandler・改名・RewriteRule のいずれでも
回避不能(**Apache に届かない**)」と書いているが、実測はこれと部分的に食い違う:

| 記述 | 実測 |
|---|---|
| 「前段 nginx が」 | **Apache 層にも明示のブロックがある**(上の `XS_WPPANEL_WP_ROOT_CGI_SSI`)。403 ボディが `etag`/`last-modified` 付きの静的ファイルであることも、ファイルを持つサーバ(Apache)が返した形に見える |
| 「一律 403」 | **観測できる 403 はドキュメントルート直下(パス 1 セグメント)のみ**。`/a/probe.cgi` のようにサブディレクトリにあるものは 404 が返り、WordPress まで到達している |
| 「改名でも回避不能」 | **正しい**。`(\.|/|$)` により `.cgi.html` も拒否される(実測で確認) |

**結論は変わらない** — `.cgi` を含む 9,021 ファイルをこのホストで配信する前提には立てない。
別館を GitHub Pages に恒久併存させる設計 v1.1 の決定はそのまま維持する。
ただし**理由の記述は上のとおり訂正が要る**。

### 4.4 未検証(この調査でできなかったこと)

- **実在する `.cgi` ファイルがサブディレクトリに置かれたときに 403 になるか 200 になるか**。
  `.htaccess` の `FilesMatch` はサブディレクトリにも継承されるので **403 になる見込み**だが、
  現在サーバ上に `.cgi`/`.pl`/`.sh`/`.shtml`/`.py` のファイルは **1 つも存在せず**、
  読み取り専用の制約下ではテストファイルを置けなかったため未確定。
- 確かめるなら台帳の普遍ルール 6 に従い `public_html/_probe/` に 1 ファイルだけ置いて
  curl し、**終わったら必ず消す**こと。
- `XS_WPPANEL_*` はパネル管理のブロックなので、**パネル側にこの機能の ON/OFF がある可能性**がある
  (👤 確認事項)。仮に外せてもルート直下は別ルールで塞がれている可能性が残るため、
  外す判断の前に必ず `_probe/` で実測すること。

### 4.5 リポジトリ側で影響を受けるファイル

| 条件 | 件数 |
|---|---:|
| basename が `.cgi` を含む | 9,021 |
| `.pl` | 1 (`ts-novels.jp/kantan-cgi/counter@id_sd03205Y.pl`) |
| `.shtml` | 2 (`entrance.shtml`・`~yays/library/entrance.shtml`) |

---

## 5. `.htaccess` の構造とガード

`public_html/.htaccess` は **40 行**。マーカーコメントで区切られた 4 つの管理ブロックと、
先頭のキャッシュ制御 2 行からなる。

| 行 | ブロック | 生成者 | 中身(概略) |
|---:|---|---|---|
| 1–2 | (マーカーなし) | Xserver | `SetEnvIf` × 2 — nginx キャッシュのモード指定 |
| 4–14 | `# BEGIN CloudSecure WP Security Settings` … `# END` (内側に `# BEGIN rename_login_page` … `# END`) | CloudSecure WP Security プラグイン | ログイン URL の変更。**★ここに秘匿値がある** |
| 15–24 | `# BEGIN WordPress` … `# END WordPress` | WordPress | 標準のパーマリンク rewrite |
| 26–33 | `# BEGIN XS_WPPANEL_REDIRECT_HTTPS:novels.xwp.jp` … `# END` | Xserver パネル | HTTP → HTTPS の 301 |
| 35–39 | `# BEGIN XS_WPPANEL_WP_ROOT_CGI_SSI` … `# END` | Xserver パネル | §4.2 の CGI/SSI 拒否 |

### 触るときのガード(台帳の普遍ルール 5・7・9 の実務化)

1. **必ずバックアップを先に取る**: `cp .htaccess .htaccess.bak-$(date +%Y%m%d)`
2. **自分のブロックは自分のマーカーの中だけ**に書く(例 `# BEGIN ts-takedown` … `# END ts-takedown`)。
   既存 4 ブロックのマーカー間には**絶対に手を入れない**(パネル/プラグインが上書き再生成する)。
3. **本番 `.htaccess` の全文をリポジトリに持ち込まない**。
   `CloudSecure WP Security Settings` ブロックには**ログイン URL の変更値**が入っており、
   これを文書・コミット・ログ・チャットに転記することは禁止(台帳の普遍ルール 7)。
   本書がブロックの**見出しコメントだけ**を載せて中身を載せていないのはそのため。
   構造を確認したいときは中身を見ずに済む読み方をする:

   ```sh
   ssh novels 'grep -n "^\s*#" ~/novels.xwp.jp/public_html/.htaccess'   # マーカーだけ
   ```
4. 変更直後に **無関係な URL が 200 のままか**を curl で確認する。壊れていたら即 `.bak` を戻す。
5. 検証済みで使える機能(v1.1 で確認済み・本調査でも `.htaccess` に実在を確認):
   `Header set X-Robots-Tag` / `Redirect gone`(真の 410)/ `mod_rewrite` /
   `~` や `@` を含むパス。

---

## 6. サーバ上で使えるツールと WordPress の現状

| ツール | バージョン(実測) | 備考 |
|---|---|---|
| WP-CLI | **2.8.1** | 検収の主力 |
| PHP | **8.0.30** (CLI)。web も同じ | **mu-plugin は PHP 8.0 互換で書く** |
| python3 | **3.6.8** | 古い。f-string は使えるが dataclasses 以降の新機能に注意。**変換処理は開発機(3.11)側でやる** |
| MariaDB クライアント | 10.11.10 | `wp db` 経由で使う |
| rsync | 3.1.3 | 配備用 |
| git | 2.43.7 | — |
| curl | 7.61.1 | — |
| make | 4.2.1 | — |
| composer | 2.5.8 | — |
| node / npm / yarn | **無し** | JS ビルドが要るものはサーバ上で作れない(開発機で作って rsync する) |
| perl | 5.26.3 | — |
| python2 | 2.7.18 | 使わない |
| zip / unzip | あり | — |

### WordPress の現状(まだ素の状態)

| 項目 | 実測値 |
|---|---|
| コア | **WordPress 7.1** |
| `siteurl` / `home` | `https://novels.xwp.jp` |
| `blogname` | **少年少女文庫**(設定済み) |
| `permalink_structure` | `/%year%/%monthnum%/%day%/%postname%/` ← **既定のまま。設計の `/works/…` 構成にはまだ変えていない** |
| 有効プラグイン | `cloudsecure-wp-security` 1.4.12 のみ |
| 無効プラグイン | `akismet` / `hello` / `xserver-typesquare-webfonts` |
| 有効テーマ | `twentytwentyfive` |
| `mu-plugins/` | **未作成** |
| WP Multibyte Patch | **未導入**(タスク 2.1 で入れる) |
| 投稿数 | 3(WordPress 初期投稿) |
| DB | `novels_wp1` @ localhost、prefix `wp_`、約 0.9MB、標準 12 テーブル(単一サイト) |

### WP-CLI の落とし穴

```sh
# ✗ 動かない — 引数の中の ~ はシェルが展開しない
wp --path=~/novels.xwp.jp/public_html core version
#   → Error: This does not seem to be a WordPress installation.

# ✓ どちらかにする
cd ~/novels.xwp.jp/public_html && wp core version
wp --path=$HOME/novels.xwp.jp/public_html core version
```

---

## 7. 検収は WP-CLI + curl で行う(管理画面は使えない)

- **WP 管理画面はセッションから開けない。** ログイン URL は CloudSecure により変更されており、
  その値の転記は禁止(台帳の普遍ルール 7)。ブラウザ操作は 👤 タスク。
- サーバのアクセスログ・エラーログもユーザからは読めない(`~/novels.xwp.jp/log/` は空)。
- したがって**検収の手段は 2 つだけ**:

| 手段 | 見るもの | 例 |
|---|---|---|
| **WP-CLI**(サーバ内) | DB の中身が正しいか | `wp post list --post_type=ts_work --format=count` / `wp post meta get <ID> _ts_source_path` / `wp term list ts_author --format=count` |
| **curl**(外から) | 公開面が正しいか | `curl -s -o /dev/null -w '%{http_code}' "https://novels.xwp.jp/works/?_cb=$RANDOM"` / `curl -sI … \| grep -i x-robots-tag` |

curl は**必ずキャッシュバスター付き**(§3)。ステータスコード・`X-Robots-Tag`・`<title>` の 3 点で
「投入できているか」「noindex の境界が正しいか」を判定する。

---

## 8. 危険操作チェックリスト

作業を始める前に、該当する行を上から順に確認する。

### rsync (配備)

- [ ] **`--delete` を使っていない**。宛先 `~/novels.xwp.jp/` 直下には `wp-config.php` が居る
- [ ] **`-n` (dry-run) を先に流し**、転送されるファイル一覧を目で見た
- [ ] mu-plugins の宛先は `public_html/wp-content/mu-plugins/` になっている
- [ ] 開発機側のパスに `__pycache__` `.venv` が混ざっていない

### DB を変える前 (import / apply-takedown / option 変更)

- [ ] `mkdir -p ~/dumps` してから `wp db export ~/dumps/pre-<用途>-$(date +%Y%m%d-%H%M).sql`
- [ ] ダンプは**私有ストレージへ退避**。公開リポジトリに入れない
- [ ] `--dry-run` があるコマンドは先に dry-run
- [ ] 実行後、**もう一度同じコマンドを流して created=0 / updated=0**(冪等性の受け入れ条件)

### `.htaccess` を変える前

- [ ] `cp .htaccess .htaccess.bak-$(date +%Y%m%d)` を実行した
- [ ] 書き込むのは**自分のマーカーブロックの中だけ**
- [ ] 全文をローカルに保存・コミットしていない(ログイン URL 秘匿値)
- [ ] 変更直後に「対象 URL が期待どおり」+「**無関係な URL が 200 のまま**」を curl で確認
- [ ] 壊れていたら即 `.bak` を戻す

### 本番での実験

- [ ] `public_html/_probe/` の中だけで行う
- [ ] **終わったら必ず消す**
- [ ] 実験結果が「反映されない」ときは、修正を重ねる前に**キャッシュを疑う**(§3)

### 公開に関わるもの

- [ ] 公開解禁(タスク 7.4)まで**全域 noindex** を維持している
- [ ] 感想板 `/boards/` ・道場 `/dojo/` ・`/assets/annex-img/` は**解禁後も恒久 noindex**
- [ ] メールアドレスは catalog 生成段階で除去済み(**DB に入れない**)

---

## 9. 別館 (GitHub Pages) との役割分担

| | 本館 (本番 WordPress) | 別館 |
|---|---|---|
| URL | `https://novels.xwp.jp` | GitHub Pages (`takano32/ts-novels`) |
| 中身 | 目録で体系化したライブラリ(WP 投稿) | 原パス空間のミラーそのもの |
| デプロイ | rsync + `wp ts import` | `master` への push → `.github/workflows/deploy-pages.yml` がリポジトリ直下をそのまま配信 |
| `.cgi` | **配信できない**(§4) | 静的配信なので無害に返せる |
| ヘッダ制御 | `.htaccess` で `X-Robots-Tag` も `Redirect gone`(410) も可 | **不可**。meta noindex と 404 で妥協(設計 v1.1) |
| 削除 | denylist → draft 化 + 410 | denylist → ビルド artifact から除外(404) |

この分業は `.cgi` 403 という**プラットフォーム制約による必然**であり、設計 v1.1 で決定済み。
