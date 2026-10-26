# S.45 感想板の近代ビュー・道場・創作ノートの受け入れ条件

- 種類: 仕様
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の「感想板の近代ビュー」の段 (いまの T.36)
- 関連: T.36, S.38

## T.36 — 感想板の近代ビュー(S.38。目安 1〜2 週)

- [ ] T.36.1 `scripts/wp/boards_build.py`: `~ts/kansou` の log 294+res 2,866 をパース
      (`kansou_parse_probe.py` が手本: log 97%/res 100% 実証済み)。log∪res をタイムスタンプ ID で
      重複排除 → `catalog/board_posts.jsonl`(約 3,978 投稿)。投稿者メール除去。
      Re: 親子は res ファイル名の親 ID から
- [ ] T.36.2 `/boards/{author-slug}/` テンプレート(スレッドツリー・投稿フォームなし・**恒久 noindex**)。
      書誌カードの「当時の感想」を板ページへ差替(原本リンクは板側に残す)
- [ ] T.36.3 道場: 2ndbbs 作品 267 を `ts_dojo` に、埋込感想 1,509 を読み取り専用アーカイブコメントに
      (スパム 13 ページは除外し `catalog/dojo_excluded.jsonl` に記録)。`/dojo/` も恒久 noindex
- [ ] T.36.4 ~yays noteky f_0 の 63 ノートを題名完全一致分のみ該当 Work の史料ブロックに
