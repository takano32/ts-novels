# S.181 WP の骨格: 状況と mu-plugin v0.1 (T.8.1、元の 2.1)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の「Phase 2」の状況と 2.1
- 関連: T.8, T.8.1, S.21

## T.8 — WP 骨格+メタ全量投入(目安 2 週)

**状況 (2026-08-30 時点): T.8 完了。** 2.1〜2.5・2.7 は実測で受け入れ条件を満たし、
`wp ts verify` は **OK**(投稿 4,363・orphan 0・語彙外 term 0・taxonomy 割当 6 本すべて期待値と一致)、
`wp ts import` は**実行・dry-run とも created=0 / updated=0 で冪等**。
本番の `ts_catalog_commit` = `ca583444047f681a0e0b59009b1d54be6fa3d3de`。
**2.6 も Fable が実施済み (htaccess_block.py)。既知の未解決バグは無い。**
全 4,363 投稿が ASCII slug(パーセントエンコードの `post_name` 0 件)。

- [x] 2.1 `mu-plugins/ts-library/` v0.1(リポジトリ管理→rsync 配備): CPT `ts_work`(hierarchical,
      rewrite=works)・`ts_doc`・**`ts_dojo`(登録のみ。使用は T.36)**・`ts_board_post`(同)・
      タクソノミー 6 本・`register_post_meta`(`_ts_*`)・meta robots noindex 出力・
      comment_status=closed 強制。PHP 8.0 互換。定義は S.21 の表のとおり。
      あわせて **WP Multibyte Patch を有効化**(`wp plugin install wp-multibyte-patch --activate`)
      - **2.1 実測 (2026-08-30 実行セッション)**: 配備後 `wp post-type list` に
        **ts_work(hierarchical・public)/ ts_doc / ts_dojo / ts_board_post** が、
        `wp taxonomy list` に **ts_author / ts_genre / ts_type / ts_keyword / ts_world /
        ts_corpus の 6 本**が出ることを確認。`wp plugin list --status=must-use` に
        **ts-library-loader** が出る(本体は `ts-library/ts-library.php`。WP は
        mu-plugins 直下の php しか読まないのでローダ経由)。
        **WP Multibyte Patch 2.9.3 を install + activate 済み**。
        `wp rewrite flush --hard` 実行(`.htaccess` 再生成の Warning は wpX 構成では正常)、
        rewrite ルール **185 本**・`works/(.+?)/…` 系が生成されていることを確認
