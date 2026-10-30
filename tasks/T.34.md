# T.34 bc-cafe.net 回収分の catalog への反映 (S.204 の積み残し、S.51)

- 状態: 一部完了
- 関連: S.204, S.51

- [ ] T.34.1 (八重洲さんの判断) 判断: bc-cafe.net の作品ページ (「ホーリーメイデンズ外伝『復活の依代』」ほか) を目録外収蔵として拾うか / `saidai_no_higaisha` に年齢表示が要るか
- [x] T.34.2 `scripts/wp/repost_build.py` の `ITEMS` に bcwiki 回収 3 点を追加 → `make catalog && make check` OK、episodes が 3 増える — 2026-10-30 済み (episodes 3,844 → 3,847・works 1,440 → 1,443、3 点とも作者 `kirika` の単発作品、本文は 3 点とも body_convert の無損失証明に合格)。題名は本文の見出しがあればそれ (「最大の被害者、あるいは」)、無題の 2 点は uncatalogued_build.py と同じく添付のファイル名 (「あま」「番外3Ts」)。日付は Wiki への添付日時。source_path は `reposts/repost__bcwiki__*` で、書誌カードの「初出」は pixiv の分と同じく `ts.novels.jp/<source_path>` と出る。年齢表示 (T.34.1) は未着手
- [ ] T.34.3 T.34.1 で拾うと決めた分を uncatalogued 経路に追加 → `make check` OK
- [ ] T.34.4 payload_build → 配備 → `wp ts import` → `wp ts verify` OK、追加分の URL が 200
