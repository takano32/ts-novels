# S.58 生成物・作業用ツリー

- 種類: 実測
- 状態: 有効
- 出典: docs/data-inventory.md の「9. 生成物・作業用ツリー」
- 関連: なし

## 9. 生成物・作業用ツリー(ミラーではないもの)

| パス | 内容 | 優先する文書 |
|---|---|---|
| `catalog/` | 目録の単一真実源(episodes.jsonl / works.jsonl / authors.json / terms.json / 各種 map・overrides・reports) | [`catalog/README.md`](../catalog/README.md) |
| `bodies/` | 本文 Markdown の正準(T.7.6 の生成物)。**実測 3,642 ファイル**(数え方: `ls bodies/*.md \| wc -l`)。変換対象 3,644 のうち無損失証明に合格した 3,642 話ぶんで、raw フォールバック 2 話と本文の原本が未回収の 200 話には `.md` が無い。**`.gitignore` 済みで git 管理外** — `make catalog` で再生成する | S.37 / [`catalog/QA.md`](../catalog/QA.md) の節 8 |
| `reposts/` | 作者本人の pixiv 再掲本文。プレーンテキストなので本文変換の対象外 | T.7.8 |
| `scripts/` | 回収・監査・変換ツール(`scripts/wp/` が WP 移築の実装) | [`scripts/README.md`](../scripts/README.md) |
| `takedown/` | `denylist.yml`(削除の四層を駆動する単一情報源) | S.75〜S.81 |
| `docs/` | 設計・手順・収蔵物の目録 (いまの S.49〜S.58) | — |
| `.github/workflows/deploy-pages.yml` | `master` への push でリポジトリ直下をそのまま GitHub Pages へ配信(ビルドなし) | — |

公開先: GitHub `takano32/ts-novels`(public) → GitHub Pages = 原本アーカイブ /
`https://novels.xwp.jp` = WordPress WP サイト。
