# S.122 ロールと可視性の検証

- 種類: 手順
- 状態: 有効
- 出典: docs/visibility-roles-spec.md の「7. 検証」
- 関連: T.23.5

## 7. 検証 (すべて `?_cb=$RANDOM` 付き)

| 確認 | 期待 |
|---|---|
| 取り下げ済みの作品・話 (3 本) を非ログインで | 410、本文に案内、`cache-control: no-cache` 系 |
| 旧版の話 (3 本) を非ログインで | 301 → 新版、新版は 200 |
| 無関係な作品・`/about/` `/removed/` | 200 |
| `/assets/annex-img/novel/200907/19181513/01123002.jpg` (TD-0012) | 410 |
| 運営委員でログインして取り下げ済み作品 | 200 + 帯。`非公開:` の接頭辞が無い |
| `wp post list --post_type=ts_work --post_status=draft --format=count` | 0 |
| `wp ts verify` | OK |
| 原本アーカイブ (GitHub Pages) | 取り下げ分は 404、他は 200 (従来どおり) |
