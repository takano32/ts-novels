# S.65 サーバ上で使えるツールと WordPress の現状

- 種類: 実測
- 状態: 有効
- 出典: docs/environment.md の「6. サーバ上で使えるツールと WordPress の現状」
- 関連: なし

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
| `permalink_structure` | `/%year%/%monthnum%/%day%/%postname%/` (**既定のまま。設計の `/works/…` 構成にはまだ変えていない** |
| 有効プラグイン | `cloudsecure-wp-security` 1.4.12 のみ |
| 無効プラグイン | `akismet` / `hello` / `xserver-typesquare-webfonts` |
| 有効テーマ | `twentytwentyfive` |
| `mu-plugins/` | **未作成** |
| WP Multibyte Patch | **未導入**(T.8.1 で入れる) |
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
