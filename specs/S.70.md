# S.70 公開に失敗したときの戻し方

- 種類: 手順
- 状態: 有効
- 出典: docs/publish-runbook.md の「失敗したときの戻し方」
- 関連: S.69

## 失敗したときの戻し方

| 症状 | 対処 |
|---|---|
| import が途中で落ちた / 変なデータが入った | `wp db import ~/dumps/pre-import-*.sql` |
| .htaccess を変えたら無関係 URL が 500/403 | `cp .htaccess.bak-YYYYMMDD .htaccess` |
| 反映されない | 1. キャッシュバスター付き curl で確認 2. キャッシュクリア (高野さんの作業) 3. それでも駄目なら調査 |
| catalog が壊れた | git で戻して `make catalog` を再実行 (catalog は生成物であり、手で直さない) |
