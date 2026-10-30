# S.19 移築先の環境の用語

- 種類: 用語
- 状態: 有効
- 出典: docs/glossary.md の「移築先の環境」
- 関連: S.59〜S.68, S.109, T.27.2

## 移築先の環境

**xwp** — Xserver for WordPress(`novels.xwp.jp`、`scripts/wp/novels-ssh`)。nginx キャッシュ → Apache →
PHP 8.0 の構成。**ファイル名に `.cgi` を含む URL を前段の nginx が一律 403 にする**制約があり、
これが原本アーカイブを GitHub Pages に残す直接の理由。詳細は S.59〜S.68。

**mu-plugin** — `wp-content/mu-plugins/ts-library/`。投稿タイプ・タクソノミー・メタ・
WP-CLI コマンドを定義する自作プラグイン。管理画面から無効化できない場所に置くことで、
事故でコンテンツモデルが消えるのを防ぐ。

**`wp ts` コマンド** — mu-plugin が提供する WP-CLI サブコマンド群
(`sync-terms` / `import` / `apply-takedown` / `verify` / `export-pathmap`)。
固定ページは `sync-pages` が `content/pages/*.html` から投入し、`export-pages` が管理画面で直したものを
`content/pages/*.html` に書き戻す (S.109、T.27.2)。
インポートは冪等で、**2 回連続実行したときの 2 回目が created=0 / updated=0** になることが
受け入れ条件。
