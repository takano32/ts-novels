# S.44 原本アーカイブとの連携の受け入れ条件

- 種類: 仕様
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の「原本アーカイブとの連携」の段 (いまの T.35)
- 関連: T.35, S.29, S.35

## T.35 — 原本アーカイブ連携(目安 1 週)

- [ ] T.35.1 `wp ts export-pathmap > catalog/path_map.json`(原パス+alias → WP URL)
- [ ] T.35.2 GitHub Pages 側デプロイをビルド注入方式へ: `scripts/wp/annex_inject.py` —
      デプロイ artifact 生成時に (a) mailto 除去(リンクとテキスト両方。対象ファイル数は
      ビルド時に再計測 — 参考実測 6,515) (b) 各 HTML 冒頭に 1 行バナー
      (**読者に見える表示文言。「本館」とは書かない**)
      「原本アーカイブ | 少年少女文庫で読む → path_map の該当 URL」 (c) meta noindex 注入
      (d) denylist 除外。**git 原本は無改変**(deploy-pages.yml を artifact ビルドに改修)。
      受け入れ: Pages 上で mailto 0・バナー表示・原 URL 全部生存(lychee サンプル)
- [ ] T.35.3 WP 書誌カードの「原本を見る」リンク先を Pages 実 URL で検証(サンプル 200)
