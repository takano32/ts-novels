# T.34 bc-cafe.net 回収分の catalog への反映 (S.204 の積み残し、S.51)

- 状態: 未了
- 関連: S.204, S.51

- [ ] T.34.1 (八重洲さんの判断) 判断: bc-cafe.net の作品ページ (「ホーリーメイデンズ外伝『復活の依代』」ほか) を目録外収蔵として拾うか / `saidai_no_higaisha` に年齢表示が要るか
- [ ] T.34.2 `scripts/wp/repost_build.py` の `ITEMS` に bcwiki 回収 3 点を追加 → `make catalog && make check` OK、episodes が 3 増える
- [ ] T.34.3 T.34.1 で拾うと決めた分を uncatalogued 経路に追加 → `make check` OK
- [ ] T.34.4 payload_build → 配備 → `wp ts import` → `wp ts verify` OK、追加分の URL が 200
