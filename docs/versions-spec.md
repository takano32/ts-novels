# 版違いの整理の仕様 v1 (2026-10-26)

**位置づけ**: 設計書 v1.6 の決定 3 (同一作品の版違いは掲載日の新しい版を残し、旧版は運営委員のみ閲覧) の実装仕様。
根拠と実測は [`owner-feedback-2026-10.md`](owner-feedback-2026-10.md) §6、実装タスクは `TASKS.md` の T.25
(前提: T.23 可視性の基盤、T.24 クラスタの手動修正)。非公開の振る舞いは [`visibility-roles-spec.md`](visibility-roles-spec.md)。
これは「掲載しない」ではなく**版の整理**で、v1.4 最大掲載原則の例外 3 つには加えない (旧版は原本アーカイブに常設、URL は新版へ転送)。

## 1. 用語

- **版グループ**: 同じ作者の同じ作品を指す話の集合 (ファイルは別)。
- **新版** (canonical): グループの中で読者に見せる 1 話。**旧版** (superseded): それ以外。
- **alias**: バイト一致 (md5 同一) の重複。`work_builder` が従来から `alias_of` で表しているもの (71 話)。本仕様では alias も版グループの一種として同じ規則で正本を決める。

## 2. 束ね方

- 自動: **同一作者** (`catalog/authors.json` の slug) × **正規化題名**。正規化 = NFKC → 空白 (半角・全角) を除去 → 記号 `・ ･ - － — – ~ 〜 「 」 『 』 ( ) （ ） 【 】 ！ ! ？ ? 、 。 . , : ： ; ； ／ /` を除去 → 小文字。
- 手動: **`catalog/versions_overrides.yml`** (git 管理。`make catalog` が読む)

```yaml
version: 1
same:                 # 異題同作: 題名が違うが同じ作品の版 (自動では束ならない)
  - [novel__anime_fan_fiction__pokemon.html, novel__200012__16233506__capsule_monster.html]
distinct:             # 同題別作: 題名が同じでも束ねない
  - [novel__xxx.html, novel__yyy.html]
review:               # 自動判定が保留した組。versions_build が雛形を出し、👤 が decision を書く
  "aizu_rika|日常":
    episodes: [novel__200008__xxx.html, novel__yyy.html]
    similarity: 0.73
    decision: null    # same | distinct
```

- `same` の組は類似度を問わず同作として扱う。`distinct` の組は束ねない。`review` の `decision` が `same`/`distinct` なら従い、`null` のままなら**別作として扱う** (保留 = 何も隠さない)。

## 3. 判定

- 本文テキスト: `body_convert.read_text` → `<script>` `<style>` コメントを除去 → タグ除去 → 空白除去 → NFC。
- 同一性の順: ① バイト一致 (md5) ② テキスト一致 ③ 類似度 `difflib.SequenceMatcher(None, a[:20000], b[:20000]).ratio()` (グループ内の全対の最小値)。
- 閾値 (既定。所有者の裁定で変える): **`AUTO_SAME ≥ 0.95`** → 同作 (自動)、**`0.50 ≤ r < 0.95`** → `review` に雛形、**`r < 0.50`** → 別作 (出力しない)。裁定で 0.8〜0.95 を自動にするなら `AUTO_SAME = 0.80`。
- 原本未回収 (`source_exists` false) の話は本文比較ができない → 題名一致だけなら `review` へ。
- **新版の選び方** (決定的): ① `date` (目録の掲載日) が新しい → ② corpus の優先度 `honkan` (3) > `uncatalogued` (2) > `legacy` (1) → ③ 本文の文字数が多い → ④ `episode_id` の辞書順。
  - 旧目録 (1997–98) 版 vs 正規目録 (2002–03) 版は、本文が同じでも正規目録版が新版になる (旧目録版は原本アーカイブで読める)。
  - alias の正本も同じ規則にする (従来は `episode_order_key` の最小 = **最古**を正本にしていた。`work_builder.py:359`)。

## 4. 出力

- `catalog/works.jsonl` の `episodes[]` に **`superseded_by`** (新版の `episode_id`、新版自身と単独の話は `null`)。親 work に **`all_superseded`** (bool)。
- `catalog/reports/versions_build.json`: `groups_total` / `auto_same` / `review_pending` / `review_decided` / `distinct` / `superseded_episodes` / `by_corpus_pair` (組合せ別の件数) / `threshold`。
- `--check` (make check): `superseded_by` が循環しない・新版自身が superseded でない・`same`/`distinct` の episode_id が実在・`superseded_episodes` が works.jsonl から数え直した値と一致。`review` に `decision: null` が残っていれば件数を**警告** (エラーにしない)。
- `catalog/README.md` に `superseded_by` / `all_superseded` の欄を追記。

## 5. `wp ts import` とテーマ

- import (`upsert_episode` / `fill_single_work`): `superseded_by` があれば `post_status=private` + `_ts_visibility=superseded` + `_ts_superseded_by`。無ければ `withdrawn` でない限り `publish` (= catalog が可視性を決める。優先順位は visibility-roles-spec §2)。`all_superseded` の親も `private` + `superseded` (+ `_ts_superseded_by` = 新版の `work_slug`)。`HASH_VER` を上げる (v6)。
- テーマ/mu-plugin: 非ログインの旧版 URL は新版へ 301、運営委員には帯「旧版 (YYYY 年の掲載) — 読者には新版を表示しています」。新版の書誌カードに運営委員だけ「旧版あり」リンク。
- `wp ts verify`: 投稿数の式は不変 (private も数える)。「本文 status」の行に `_ts_visibility` 別の件数 (withdrawn / superseded) を足す。
- 話の URL は slug から決定的に作られる (`child_slugs`) ので、新版・旧版の入れ替えで URL は変わらない。

## 6. 別館 (GitHub Pages)

全版をそのまま置く (原本レイヤ)。新版の書誌カードの「無改変の原本を読む」は新版の `annex_url`、「初出時の姿 (2002年版)」は従来どおり。

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

## 8. 実測の基準値 (2026-10-26。owner-feedback §6)

同題で別ファイルの組 303 / 684 話。本文一致 103 組 (うち alias 57 組 / 71 話)、相違 195 組 (類似度 ≥0.95: 106、0.8〜0.95: 16、0.5〜0.8: 13、<0.5: 60)、読めず 5 組。
既定の閾値では自動 209 組・要確認 89 組、1 組 1 版なら非表示は最大 371 話。作品単位の同名 20 組 / 40 作品。

## 9. 更新する文書

`docs/data-inventory.md` と `docs/cp4-review.md` の収録規模を「公開 N / 旧版 M」の形に、`docs/wordpress-library-design.md` v1.6 の決定 3 に実測値を 1 行、`docs/rebuild-runbook.md` §1 の表に「版違いの判定 → `catalog/versions_overrides.yml`」を 1 行。
