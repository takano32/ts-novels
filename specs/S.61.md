# S.61 本番のディレクトリ構成

- 種類: 実測
- 状態: 有効
- 出典: docs/environment.md の「2. ディレクトリ構成」
- 関連: なし

## 2. ディレクトリ構成

```
/home/novels/
├── .bash_history .bash_profile .bashrc .ssh/
├── ssl/
└── novels.xwp.jp/                 (ドメイン単位のルート)
    ├── wp-config.php              ★ 公開ルートの外側にある(rsync の --delete 禁止の理由)
    ├── public_html/               ★ 公開ルート = WordPress の設置ディレクトリ
    │   ├── .htaccess              (40 行。S.64)
    │   ├── .user.ini              (PHP 設定。S.62)
    │   ├── index.php  wp-*.php  wp-admin/  wp-includes/
    │   └── wp-content/
    │       ├── plugins/  themes/  languages/  upgrade/  uploads/
    │       └── (mu-plugins/ は**まだ存在しない** — T.8.1 で作る)
    ├── autoreply/  htpasswd/  log/  mail/  script/   (いずれも空)
    └── xserver_php/php.ini
```

**重要**:

- **`wp-config.php` は `~/novels.xwp.jp/` 直下**にあり、`public_html/` の中ではない。
  S.69〜S.71 が `rsync --delete` を禁じているのはこのため
  (`~/novels.xwp.jp/` を宛先にして `--delete` すると wp-config.php が消える)。
- `~/dumps` は**まだ存在しない**。`wp db export` の前に `mkdir -p ~/dumps` が要る。
- `~/novels.xwp.jp/log/` は空。**アクセスログ・エラーログはユーザから読めない**
  → 検証は curl のステータスコードとヘッダに頼るしかない(S.66)。
- ディスクは `/dev/md0` 56T 中 3.0T 使用(6%)。`public_html` は現在 131MB。
  ミラー全体 443MB を置いても余裕がある(ただしクォータは `quota` コマンドが無く未確認)。
