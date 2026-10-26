# S.214 provenance の出所 (git 履歴)

- 種類: 仕様
- 状態: 有効
- 出典: catalog/README.md の「provenance の出所について」(2026-10-26 にここへ移した)
- 関連: T.7.1, S.23, S.171, S.212

## provenance の出所について (タスクリスト specs/S.171.md との差異)

T.7.1 のタスクリスト (specs/S.171.md) は provenance を「リポジトリ直下 `collinfo.json` から転記」と書いているが、
**`collinfo.json` は CommonCrawl のコレクション一覧 (127 件) であり、ファイル単位の来歴情報を
一切持たない** (`scripts/cc_zipnum_sweep.py` が参照する外部データ)。

ファイルがどの経路で入ったかを実際に記録しているのは **git 履歴**(回収コミットの件名と日付)
なので、`catalog_build.py` はそちらを出所にしている (`provenance.method = "git-history"`)。

- `route` … wayback / commoncrawl / megalodon / live-site / narou / pixiv / yays-gapfill / alias / other
- `acquired_at` … そのファイルがリポジトリに入った日 (回収日そのものではなく、回収後のコミット日)
- `snapshot_query_url` … 個別キャプチャの timestamp は記録が無いため、Wayback の**照会 URL**の形
  (`https://web.archive.org/web/2*/<原URL>`)。書誌カードでは「回収経路: Wayback (照会)」の
  リンク先として使える
- `route_mixed: true` … 1 つのコミットが複数経路をまとめている (件名から一意に決まらない) 印
