# S.211 catalog/ のファイルと再生成

- 種類: 仕様
- 状態: 有効
- 出典: catalog/README.md の冒頭・「ファイル」・「再生成」(2026-10-26 にここへ移した)
- 関連: T.7, T.7.7, T.25.2.4, S.16, S.20, S.180, S.212, S.213, S.214

## catalog/ — 単一真実源

WordPress は `wp ts import` でいつでも作り直せる**使い捨ての派生ビュー**であり、
正本はここにある JSONL / JSON。WP 管理画面での手動編集は禁止で、修正は必ずこちら側で行う
(設計 `specs/S.20.md` の原則「単一真実源は `catalog/`」)。
用語は [`specs/S.14.md`〜`S.19.md`](S.14.md) を優先する
(**WP サイト** = 移築先の WordPress / **原本アーカイブ** = 原本を保つ GitHub Pages ミラー / **旧サイト** = 消滅した原サイト。
いずれも内部用語で、読者に見せる文面では使わない。S.15)。
**注意**: corpus の値 `honkan` は「**正規目録 lib1–73 由来**」という収蔵区分の名前であって、
移築先の WordPress (WP サイト) とは関係が無い。混同を避けるため、本書と `catalog/QA.md` では
`honkan` を日本語で書くとき必ず「正規目録 (lib1–73)」と表記する([S.16](S.16.md) の corpus 表を優先する)。

**本書の数値は 2026-08-30 の実測**(数え方は各行に添えてある)。生成物が正で、食い違ったら
`make catalog` を流し直して本書を直すこと。

## ファイル

| ファイル | 生成元 | 内容 | 状態 |
|---|---|---|---|
| `episodes.jsonl` | `catalog_build.py` → `uncatalogued_build.py` → `repost_build.py` → `episode_overrides.py` | 全 3,844 話 = 正規目録 2,887 + 旧目録 97 + 目録外収蔵 859 + 文庫未掲載の再掲 1(数え方: `episodes.jsonl` の行数と `corpus` 欄の内訳) | 完了 (T.7.1 / T.7.2 / T.7.8) |
| `uncatalogued_excluded.jsonl` | `uncatalogued_build.py` | 目録外走査で「作品ではない」と判定して落としたファイルと理由 | 完了 |
| `reports/catalog_build.json` | 同上 | 件数・被覆率・自己検査結果の機械可読レポート | 完了 |
| `terms.json` | `terms_build.py` | 分類語彙 5 本 (genre 196 / type 165 / keyword 1,032 / world 14 / corpus 5)。数え方: `terms.json` の各 taxonomy の `terms` 配列長。WP のタクソノミーは `ts_author` を足して 6 本だが、作者語彙は `authors.json` 側 | 完了 (T.7.3) |
| `genre_map.yml` / `type_map.yml` / `keyword_map.yml` / `world_map.yml` | 同上 (初回生成→以後は人手で編集) | 正規化マップと ts_world の判定規則 | 完了 |
| `slug_overrides.yml` | `terms_build.py` / `authors_build.py` / `work_builder.py` | 恒久 URL slug の確認表 (T.7.5.1、specs/S.176.md)。**作者 42 件は `status: confirmed` で確定**、作品 1,451 件と分類語彙 1,407 件は自動生成のまま採用 | 完了 (T.7.5.1 で確定) |
| `episode_overrides.yml` | 人手で編集 (`episode_overrides.py` が読む) | 話 1 件単位の上書き。**旧サイトの原本 `lib*.html` の誤植を catalog 側で直す唯一の場所**。現状 3 行 (神川綾乃の板の誤植 / コーディーの板の誤植 / シェアワールド = シリーズ目次) | 完了 |
| `authors.json` | `authors_build.py` | 作者 **322 名** (表示名 427 種を感想板 id と T.7.5.1 の裁定で統合。板あり 300 / 板なし 22)。数え方: `authors.json` の `authors` 配列長 / `reports/authors_build.json` の `display_name_strings_distinct` / `authors_with_board` | 完了 (T.7.4 / T.7.5.1) |
| `works.jsonl` / `work_overrides.yml` | `work_builder.py` | Episode → Work クラスタ **1,451 件** (単発 932 / 連載 519、orphan 0、needs_review 331)。数え方: `works.jsonl` の行数 | 完了 (T.7.5) |
| `convert_report.jsonl` | `body_convert.py` | 本文 MD 変換の無損失証明ログ (全 3,844 話分の判定) | 完了 (T.7.6) |
| `QA.md` | `qa_report.py` (`make catalog` の最後) | 全体の QA レポート | 完了 (T.7.7) |

本文 Markdown の置き場はリポジトリ直下の `bodies/`(catalog の下ではない)。
**実測 3,642 ファイル**(数え方: `ls bodies/*.md | wc -l`。変換対象 3,644 のうち証明合格
3,642 = 99.95%、raw フォールバック 2。本文の原本が未回収の 200 話には `.md` が無い)。
`bodies/` は `.gitignore` 済みで **git 管理外**(`make catalog` でいつでも再生成できる派生物)。

## 再生成

```sh
make venv        # 初回のみ: pykakasi / PyYAML 入りの .venv を作る
make catalog     # T.7.1〜T.7.8 の段を順に実行して catalog/ を作り直し、QA.md まで書く
make check       # ファイルを書かずに各段の自己検査だけ
```

段の順番には意味がある。`episodes.jsonl` は `catalog_build` が新規に書き、
`uncatalogued_build` と `repost_build` が**自分の corpus 分だけ差し替えて**追記し、
`episode_overrides` が最後に人手の上書き (原本の誤植の訂正) を当て、
`terms_build` / `authors_build` / `work_builder` はその出来上がりを読む。
2 回連続実行で全出力が同一になることを確認済み。

自己検査は受け入れ条件をそのままコード化してある (エントリ数 2,887 / パース失敗 0 /
mailto 由来の値の残存 0 / survey 実測値との突き合わせ)。1 つでも落ちると終了コード 1。
