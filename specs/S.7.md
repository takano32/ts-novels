# S.7 本番環境の確定事実 (実行セッション向けの要約)

- 種類: 実測
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の「実行セッションの心得」の「環境の確定事実」
- 関連: S.59〜S.68

**環境の確定事実**(調査済み・再確認不要):
- 本番: `scripts/wp/novels-ssh` = Xserver for WordPress。`https://novels.xwp.jp`、WP 設置済み、
  wp-config.php は `~/novels.xwp.jp/` 直下、公開ルートは `~/novels.xwp.jp/public_html/`
- サーバ内ツール: WP-CLI 2.8.1 / mysql / rsync / git / **PHP 8.0.30**(コードは 8.0 互換)/ python3.6
- 構成: nginx キャッシュ → Apache(.htaccess 有効)→ PHP。`Header set X-Robots-Tag` ✓・
  `Redirect gone`(410)✓・rewrite ✓ は本番検証済み
- **地雷: ファイル名に `.cgi` を含む URL は前段 nginx が一律 403**(回避不能)。だから原本アーカイブ
  (= 現行 GitHub Pages ミラーの**全 18,006 ファイルの URL 空間**。`.cgi` スナップショットは
  **フルパス基準 9,022 / basename 基準 9,021** を含む。数え方は S.49 と S.56)
  は GitHub Pages 恒久併存(S.35)
- **nginx キャッシュの罠**: 変更が反映されないように見えたら、まずキャッシュを疑う。
  検証 curl には毎回キャッシュバスター(`?_cb=<乱数>`)を付ける。パネルからのキャッシュ全消しは
  ブラウザ操作のため人間のタスク (高野さんの作業)。**「反映されない」と誤認して .htaccess や mu-plugin を
  修正し続けるループに入らないこと**
- WP 管理画面もセッションからは開けない(ログイン URL は変更済みで、その値の転記は禁止=規則 7)。
  検収は **WP-CLI(`wp post list` / `wp post meta get` 等)+curl** で行う
- 開発機: このリポジトリ(python3.11、pip 使用可)。catalog 生成は開発機、投入はサーバ上 WP-CLI
