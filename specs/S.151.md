# S.151 URL の付け替え (catalog/redirects.yml)

- 種類: 仕様
- 状態: 有効
- 出典: docs/cluster-overrides-spec.md の「URL の付け替え」
- 関連: T.24.6, S.119, S.218

## URL の付け替え (`catalog/redirects.yml`)

束ね直した作品・話の URL は変わる (S.218 (元の `catalog/review/works-risk-note.md`) の 0。公開中なので旧 URL を生かす)。

1. 実行前に本番で `wp ts export-pathmap > catalog/path_map.json` (S.44 の前倒し。原パス → 現 URL)。
2. 再投入後にもう一度 `export-pathmap` を取り、`source_path` ごとに URL が変わったものを付け替え表に出す (`cluster_review.py --redirects old.json new.json`):

```yaml
version: 1
redirects:
  - from: /novel/marie-kyaria-dei-97/novel-toukou01-19971209/
    to: /novel/marie-mirai-kara-kita-onna/
    reason: cluster-split 2026-11
```

3. `scripts/wp/htaccess_block.py` がこれを `Redirect 301 <from> <to>` として ts-library ブロックに書く (S.119)。付け替え表は恒久に残す (URL の約束)。`--install` の検証に先頭 3 本の 301 を加える。
