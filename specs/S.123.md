# S.123 ロールと可視性に合わせて更新する文書

- 種類: 記録
- 状態: 有効
- 出典: docs/visibility-roles-spec.md の「更新する文書」
- 関連: T.23.6, S.18, S.67, S.69〜S.71, S.75〜S.81, S.215

## 更新する文書

S.75〜S.81 の改訂 (WP サイトの非公開化 (S.76) は draft → private、本番 .htaccess の 410 (S.77) は画像資産のみ、運営委員の閲覧、復帰の手順)、
S.14〜S.19 の「denylist 四層」のうち WP サイトの非公開化と 410 の層、S.67 (apply-takedown 後は private の件数を確認)、
S.215 (元の `takedown/README.md`) の層の表、S.69〜S.71 の配備手順に `wp ts role-sync`。

## 更新の記録 (2026-10-30、T.23.6)

上の文書を T.23.3 の実装と T.23.4 の本番の移行に合わせて直した。S.75〜S.81 (S.76 は題名も「削除: WP の投稿を非公開 (private) にする」に)・S.18・S.67・S.215・S.69〜S.71 と、
`takedown/denylist.yml` の冒頭のコメント、docs/architecture.md と docs/operations.md の取り下げの「いまの仕組み」「いまの手順」。
本番で流すコマンドは、権限の都合で高野さんがホームから流す形 (`cd ~/GitHub/ts-novels && scripts/wp/novels-ssh '…'`) にした。
