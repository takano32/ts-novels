# S.138 版違いの束ね方

- 種類: 仕様
- 状態: 有効
- 出典: docs/versions-spec.md の「2. 束ね方」
- 関連: T.25.2.1

## 2. 束ね方

- 自動: **同一作者** (`catalog/authors.json` の slug) × **正規化題名**。正規化 = NFKC → 空白 (半角・全角) を除去 → 記号 `・ ･ - － — – ~ 〜 「 」 『 』 ( ) （ ） 【 】 ！ ! ？ ? 、 。 . , : ： ; ； ／ /` を除去 → 小文字。
- 手動: **`catalog/versions_overrides.yml`** (git 管理。`make catalog` が読む)

```yaml
version: 1
same:                 # 異題同作: 題名が違うが同じ作品の版 (自動では束ならない)
  - [novel__anime_fan_fiction__pokemon.html, novel__200012__16233506__capsule_monster.html]
distinct:             # 同題別作: 題名が同じでも束ねない
  - [novel__xxx.html, novel__yyy.html]
review:               # 自動判定が保留した組。versions_build が雛形を出し、人 (八重洲さん) が decision を書く
  "aizu_rika|日常":
    episodes: [novel__200008__xxx.html, novel__yyy.html]
    similarity: 0.73
    decision: null    # same | distinct
```

- `same` の組は類似度を問わず同作として扱う。`distinct` の組は束ねない。`review` の `decision` が `same`/`distinct` なら従い、`null` のままなら**別作として扱う** (保留 = 何も隠さない)。
