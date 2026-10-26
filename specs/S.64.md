# S.64 .htaccess の構造とガード

- 種類: 手順
- 状態: 有効
- 出典: docs/environment.md の「5. .htaccess の構造とガード」
- 関連: S.10, S.119

## 5. `.htaccess` の構造とガード

`public_html/.htaccess` は **40 行**。マーカーコメントで区切られた 4 つの管理ブロックと、
先頭のキャッシュ制御 2 行からなる。

| 行 | ブロック | 生成者 | 中身(概略) |
|---:|---|---|---|
| 1–2 | (マーカーなし) | Xserver | `SetEnvIf` × 2 — nginx キャッシュのモード指定 |
| 4–14 | `# BEGIN CloudSecure WP Security Settings` … `# END` (内側に `# BEGIN rename_login_page` … `# END`) | CloudSecure WP Security プラグイン | ログイン URL の変更。**★ここに秘匿値がある** |
| 15–24 | `# BEGIN WordPress` … `# END WordPress` | WordPress | 標準のパーマリンク rewrite |
| 26–33 | `# BEGIN XS_WPPANEL_REDIRECT_HTTPS:novels.xwp.jp` … `# END` | Xserver パネル | HTTP → HTTPS の 301 |
| 35–39 | `# BEGIN XS_WPPANEL_WP_ROOT_CGI_SSI` … `# END` | Xserver パネル | S.63 の 4.2 の CGI/SSI 拒否 |

### 触るときのガード(S.10 の 5・7・9 の実務化)

1. **必ずバックアップを先に取る**: `cp .htaccess .htaccess.bak-$(date +%Y%m%d)`
2. **自分のブロックは自分のマーカーの中だけ**に書く(例 `# BEGIN ts-takedown` … `# END ts-takedown`)。
   既存 4 ブロックのマーカー間には**絶対に手を入れない**(パネル/プラグインが上書き再生成する)。
3. **本番 `.htaccess` の全文をリポジトリに持ち込まない**。
   `CloudSecure WP Security Settings` ブロックには**ログイン URL の変更値**が入っており、
   これを文書・コミット・ログ・チャットに転記することは禁止(S.10 の 7)。
   この S (と S.59〜S.68) がブロックの**見出しコメントだけ**を載せて中身を載せていないのはそのため。
   構造を確認したいときは中身を見ずに済む読み方をする:

   ```sh
   scripts/wp/novels-ssh 'grep -n "^\s*#" ~/novels.xwp.jp/public_html/.htaccess'   # マーカーだけ
   ```
4. 変更直後に **無関係な URL が 200 のままか**を curl で確認する。壊れていたら即 `.bak` を戻す。
5. 検証済みで使える機能(v1.1 で確認済み・本調査でも `.htaccess` に実在を確認):
   `Header set X-Robots-Tag` / `Redirect gone`(真の 410)/ `mod_rewrite` /
   `~` や `@` を含むパス。
