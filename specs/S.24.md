# S.24 インポートパイプラインのスクリプト構成

- 種類: 設計
- 状態: 一部失効 (S.35・S.37 で上書き)
- 出典: docs/wordpress-library-design.md の「インポートパイプライン」の「スクリプト構成」
- 関連: S.35, S.37

## インポートパイプライン

### スクリプト構成 (`scripts/wp/`、全段冪等)

```
scripts/wp/
  catalog_build.py      # lib1–73 (2,887) + 旧目録差分 → catalog/episodes.jsonl, authors.json,
                        #   terms.json, recommendations.jsonl。QAレポート付き
  work_builder.py       # Work クラスタリング → catalog/works.jsonl (+needs_review)
  body_extract.py       # 本文切り出し+最小施工 → bodies/{ep_id}.html (wp:html ペイロード)
  annex_build.py        # 原本ツリー → dist/annex/ (マスク+バナー+noindex+除外)
  gen_headers.py        # _headers / _redirects / robots.txt / sitemap.xml 生成
  verify_site.py        # マージ衝突検査・件数照合・lychee 起動
catalog/
  episodes.jsonl  works.jsonl  authors.json  terms.json  recommendations.jsonl
  work_overrides.yml  genre_map.yml  type_map.yml  keyword_map.yml  path_map.json
takedown/
  denylist.yml          # 削除の単一情報源
mu-plugins/ts-library/  # CPT/タクソノミー/メタ登録 + WP-CLI コマンド群 + テンプレタグ
```

> **【失効】** S.37 により本文の切り出しは `body_extract.py`(HTML 出力)から **`body_convert.py`**(Markdown 正準+無損失証明、出力はリポジトリ直下の `bodies/`)に変更。S.35 により静的マージ用の `annex_build.py` / `gen_headers.py` / `verify_site.py` は使わない(公開面がライブ WP になり `dist/` を作らないため)。実在するスクリプトの一覧は `scripts/README.md`。
