# S.143 版違いの検証

- 種類: 手順
- 状態: 有効
- 出典: docs/versions-spec.md の「7. 検証」
- 関連: T.25.5

## 7. 検証

| 確認 | 期待 |
|---|---|
| `make catalog && make check` | OK。`versions_build.json` の `superseded_episodes` を記録 |
| `wp ts import` → `wp ts verify` | OK。visibility 別件数が報告値と一致 |
| 旧版 URL 3 本 (非ログイン) | 301 → 新版 (200) |
| 運営委員でログインして旧版 URL | 200 + 帯 |
| `/authors/yays/` の作品カード | 12 → 9〜10 |
| `/authors/marie/` | 取り下げ済みのまま (T.18) |
| トップの「N 篇」・索引の件数 | publish だけを数えて減る |
