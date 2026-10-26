# S.68 原本アーカイブ (GitHub Pages) との役割分担

- 種類: 実測
- 状態: 有効
- 出典: docs/environment.md の「9. 別館 (GitHub Pages) との役割分担」
- 関連: S.35

## 9. 原本アーカイブ (GitHub Pages) との役割分担

| | WP サイト (本番 WordPress) | 原本アーカイブ |
|---|---|---|
| URL | `https://novels.xwp.jp` | GitHub Pages (`takano32/ts-novels`) |
| 中身 | 目録で体系化したライブラリ(WP 投稿) | 原パス空間のミラーそのもの |
| デプロイ | rsync + `wp ts import` | `master` への push → `.github/workflows/deploy-pages.yml` がリポジトリ直下をそのまま配信 |
| `.cgi` | **配信できない**(S.63) | 静的配信なので無害に返せる |
| ヘッダ制御 | `.htaccess` で `X-Robots-Tag` も `Redirect gone`(410) も可 | **不可**。meta noindex と 404 で妥協(S.35) |
| 削除 | denylist → draft 化 + 410 | denylist → ビルド artifact から除外(404) |

この分業は `.cgi` 403 という**プラットフォーム制約による必然**であり、S.35 で決定済み。
