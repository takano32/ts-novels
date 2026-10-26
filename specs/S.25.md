# S.25 インポートの各ステージ仕様

- 種類: 設計
- 状態: 一部失効 (S.35・S.37・S.39 で上書き)
- 出典: docs/wordpress-library-design.md の「各ステージ仕様」
- 関連: S.35, S.37, S.39

## 各ステージ仕様

**(1) catalog_build.py** — 調査で 100% パース確定済みの戦略をそのまま昇格: `<TABLE BORDER=1>` 単位の findall (1 行に長大 HTML のため行単位パース禁止)、4 行×row1=6TD 固定、【】マーカー切り。フィールド regex は調査 spec を採用 (日付 `^(\d{4})/(\d{1,2})/(\d{1,2})(?:[(（]([月火水木金土日])[)）])?$`、サイズ `^(\d+)\s?KB(?:\s*/\s*(\d+)FILES)?$`、感想 `~ts/kansou/bbs@log_([A-Za-z0-9_\-]+)\.cgi`)。堅牢化: タグ大文字小文字無視、単引用符 href 許容、オススメ欄の壊れた入れ子 `<A>` はフラット findall、NFKC、ASCII+U+3000 空白 split、マーカーあり空値は NULL。**mailto はこの段階でリンク・表示テキストとも除去**。library.html (lib1 先頭 10 件の完全重複) と lib-index*.html はスキップ。旧目録 lib01–06 (252 エントリ、非テーブル形式) と lib07–09 (81 作品、【属性】/【作者様コメント】/`***`/2桁年/noteky 感想リンク対応) はパス prefix dedup 後の差分のみ `ts_corpus=legacy` で追加 (差分規模は T.7 で確定)。

**(2) work_builder.py** — シード = series.html **112 行**(当初の設計の「123 行」は誤り。数え方: `catalog/reports/work_builder.json` の `series_html_rows`) + share_world.html + タイトルページ約 175 個 + 推薦文の【シリーズタイトルはこちら】**実測 1,236 リンク**(当初の設計の 696 は誤り)。補完 = 作者×ファイル名 prefix (d_upboy, yama…)×目録タイトル共通接頭辞クラスタ。命名不統一 (dup_boy07 vs d_upboy09) と複数ディレクトリ分散 (城弾の 200209/200907/201009/201212) は `work_overrides.yml` で人手上書き。md5 一致・同文別名は正本 1 つ+ `_ts_alias_paths`。work_slug = `{author_slug}-{作品ローマ字}` (例 `johdan-d-upboy`) で衝突回避。needs_review リスト (推定 100–200 件 → **実測 328 件**。数え方: `work_builder.json` の `needs_review`) が本パイプライン最大の人手工数。

**(3) body_extract.py** — **決定: 本文は当面 raw 一律**。

> **【失効】** S.37 により本文は**無損失証明つき Markdown を正準**に変更(`bodies/{episode_id}.md`。証明に通らない話だけ raw フォールバック)。当初の設計が raw を選んだ根拠「気づかれない本文改変」は、原本アーカイブに無改変原本が原 URL のまま常設されることと、証明の機械化で解消した。実測は 3,644 話中 3,642 話 (99.95%) が MD、raw フォールバックは 2 話。

全審査員が BR 再フロー・mso 剥離の「気づかれない本文改変」リスクを指摘したため、施工は除去系 4 種のみ:
- (a) script 122 件全削除 (全て Geocities ビーコン/カウンタと確認済み)
- (b) mailto をリンク・表示テキストごと除去
- (c) サイト共通クローム剥がし (冒頭「戻る」〜2 番目の `<hr width=70%>`、末尾 `<hr>`〜「□感想はこちらに□」フッタ。87%/73% で定型確認済み) — 剥がした内容はメタに退避し、テーマのナビで置換
- (d) 相対 img src → 原本アーカイブの絶対 URL へ書換

BR 再フロー・MsoNormal 剥離・旧 IE 式 ruby 書換は**行わない** (ruby は互換 CSS `rb{display:inline}` で吸収 — preservation 案)。出力は単一の `<!-- wp:html -->` ブロック。`_ts_reflow_mode` の診断分類だけ全件に付与し、将来「許諾作者分または高信頼定型のみ」の段落ブロック昇格 (T.36 で任意) の作業用の一覧とする。特殊エントリ: toukou01–03.html はアンカー分割で各 Episode に配布、画像作品 21 件 (.jpg/.gif 直リンク) は figure 1 枚の Episode、外部絶対 URL 6 件と special/rb 1 件はリンクスタブ Episode (死にリンクは Wayback 書換)。

**(4) 画像** — **メディアライブラリに複製しない**。挿絵・絵文字 GIF 実体 **1,196 個**(数え方: `git ls-files novel` の `.jpg` 562 + `.gif` 614 + `.png` 19 + `.bmp` 1)は原本アーカイブの正本を絶対 URL 参照 (DB 肥大と再インポート増殖を防ぐ。同一ドメイン配信なので実害なし)。例外は eyecatch のみ featured image として sideload (`_ts_media_source` で dedup)。**当初の設計の「eyecatch.jpg 109 件」は参照回数であって実体数ではない** — 実体は `novel/` 配下に 4 ファイル、参照している本文は 15 話(数え方: `git ls-files novel | grep -i eyecatch` / `grep -rl -i eyecatch novel/ | wc -l`)。

**(5) 投入: `wp ts` コマンド群** (mu-plugin 内、WXR は冪等再実行に不向きのため不採用):

```
wp ts sync-terms --authors=catalog/authors.json --terms=catalog/terms.json
wp ts import --works=catalog/works.jsonl --episodes=catalog/episodes.jsonl \
             [--bodies=bodies/] [--dry-run] [--limit=N] [--author=slug]
wp ts apply-takedown --list=takedown/denylist.yml     # 該当投稿を draft 化
wp ts verify        # 件数照合 (episodes 3,844 / works 1,463 / 作者 342)・孤児・taxonomy被覆率
wp ts export-pathmap > catalog/path_map.json          # 原パス(alias含む)→WP URL
```

upsert は `_ts_source_path`(+anchor) の meta_query 検索 → 無ければ insert、あれば `_ts_import_hash` 一致で skip / 不一致で update。**2 回連続実行で差分ゼロ**が受け入れ条件。comment_status=closed を全投稿に強制。

**(6) `make publish` 一発化** (ops-simple):

```
make catalog   # catalog_build + work_builder + body_extract
make import    # compose up → sync-terms → import → apply-takedown → verify
make export    # Simply Static 書き出し → dist/wp/
make annex     # annex_build.py → dist/annex/
make site      # merge (annex 先・WP 予約名前空間のみ上書き) → pagefind --site dist/site
               #   → gen_headers.py → verify_site.py (衝突検査+lychee サンプル)
make deploy    # wrangler pages deploy dist/site
```

> **【失効】** S.35 によりデプロイ手順を変更 — `make export`(Simply Static)/ `make site`(静的マージ+pagefind)/ `make deploy`(wrangler)は無くなり、**開発機で `make catalog` → `rsync` → サーバ上で `wp ts sync-terms && wp ts import && wp ts apply-takedown && wp ts verify` → nginx キャッシュクリア**になった(改訂後のフローは S.35、定常手順は S.69〜S.71)。
