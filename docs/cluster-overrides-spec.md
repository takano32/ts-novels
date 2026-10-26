# 作品クラスタの手動修正の仕様 v1 (2026-10-26)

**位置づけ**: 設計書 v1.6 の決定 4 (作品クラスタの人手修正を可能にする) の実装仕様。
根拠と実測は [`owner-feedback-2026-10.md`](owner-feedback-2026-10.md) §7、実装タスクは `TASKS.md` の T.24。
版違いの整理 ([`versions-spec.md`](versions-spec.md)) はこの修正の**後**に行う (束ね直すと版グループが変わる)。
背景: `work_builder.py` の規則 5 (同一ディレクトリ・同一作者の連番ファイル名) と規則 6 (題名の芯の一致) が別作品を束ねる例が 71 作品あり、
`work_overrides.yml` は現状 `work_title` と `note` しか効かない。1.5b で確認したのは slug だけで、クラスタ本体は未確認
([`../catalog/review/works-risk-note.md`](../catalog/review/works-risk-note.md))。

## 1. `catalog/work_overrides.yml` のスキーマ (version 2)

鍵は現行どおり `"<作者slug>|<機械が付けた作品名>"`。値に次を足す。

```yaml
works:
  "marie|キャリア・デイ　９７":
    confirmed: true                    # 人手で確認した (needs_review を落とす)
    note: 短編 4 本が toukou01 の衝突群と career* の語幹で束なっていた (レビュー 2026-10)
    work_title: キャリア・デイ         # 残るクラスタの題名 (現行どおり)
    split:                             # 列挙した話を新しい作品に分ける。残りは元のクラスタに残る
      - work_title: 未来から来た女
        episodes: [novel__toukou01.html+19971209, novel__200202__03004409__future.html]
      - work_title: 虹色のイヤリング
        episodes: [novel__toukou01.html+19971221, novel__200202__03004409__earring.html]
    detach: [novel__toukou02.html+19980130]      # 1 話ずつ単独の作品にする (題名は話の題名)
  "yays|奇術":
    merge_into: "yays|奇術"                       # このクラスタの全話を指定した鍵のクラスタへ併合 (作者が同じこと)
```

- `split` の `episodes` に無い話は元のクラスタに残る。`split` 先の slug は `slug_overrides.yml` に confirmed があればそれ、無ければ `work_builder` が候補を作る。
- `detach` は `split` の省略形 (1 話 1 作品、題名は話の題名)。
- `merge_into` の併合先の鍵は、併合先クラスタの**機械の題名**で書く (併合先に `work_title` があってもよい)。
- 検証 (`work_builder.py --check`): 参照する `episode_id` が実在する / 同じ話が複数の指示に現れない / `merge_into` の先が実在し作者が同じ / 適用後も orphan 0 / `split` の `work_title` が非空 / `confirmed: true` のクラスタは `needs_review` false。

## 2. 適用の順序

`cluster()` (union-find) → `groups` → **overrides の適用** (`merge_into` → `split` → `detach` の順) → 題名・slug・alias の決定 (従来どおり)。
適用したクラスタの `evidence` に `override` を足す (設計 §1.3 `_ts_work_seed` の `override` に対応)。雛形の自動出力 (`confirmed: false` の行) は従来どおり needs_review のものだけ。

## 3. 題名の芯 (`trim_title`) の追加規則

`RE_TITLE_TRIM` / `RE_TITLE_CUT` (`work_builder.py:59-69`) に **回 / Lesson N / LESSON N / EPISODE N / episode N / stage / その N / 其の N / № N / No. N** を足す。
効果: 末尾が助数詞で終わる作品題名 10 件 (`イマジン 第`、`本当の自分 第`、`〜その` など。works-risk-note §1-F) が正しい芯になる。
slug が変わる作品は `catalog/slug_overrides.yml` の差分で数え、`catalog/review/clusters-YYYY-MM.md` の末尾に記録する。

## 4. レビュー資料 `scripts/wp/cluster_review.py`

- 抽出: `works.jsonl` の連載 (`episode_count ≥ 2`) のうち、構成話の題名の芯 (`trim_title`) が 2 種以上あり、芯どうしの共通接頭辞 (NFKC・小文字) が 2 文字未満のもの。2026-10-26 時点で **71 作品** (根拠が規則 5/6 だけのもの 39 を先頭に、強い根拠のあるもの 32 を後に)。
- 出力 `catalog/review/clusters-YYYY-MM.md`: 作品ごとに見出し (`work_slug` / 機械の題名 / 根拠 / 話数) と表 (`episode_id` / 題名 / ファイル名 / 掲載日 / corpus)、末尾に「裁定」欄 (○ / 分割 / 切り離し / 併合 と、分割なら話の振り分け)。
- 一次案: セッションが芯ごとの振り分け案を「裁定」欄に書いておき、所有者は承認・修正だけで済むようにする (owner-feedback §13-7 の推奨)。
- `--redirects` サブコマンド: §6 の付け替え表を作る。

## 5. 裁定の書き方 → YAML

| 裁定 | YAML |
|---|---|
| ○ (正しい) | `confirmed: true` だけ |
| 分割 | `split:` に新しい作品ごとの `work_title` と `episodes` |
| 切り離し (別作が 1 話混ざっている) | `detach: [episode_id]` |
| 併合 (別クラスタと同じ作品) | 併合される側に `merge_into: "<鍵>"` |
| 題名だけ違う | `work_title:` (現行) |

転記後に `make catalog` → `catalog/reports/work_builder.json` の `works` / `needs_review` / `orphans` を記録 → `make check` `make verify`。

## 6. URL の付け替え (`catalog/redirects.yml`)

束ね直した作品・話の URL は変わる (works-risk-note §0。公開中なので旧 URL を生かす)。

1. 実行前に本番で `wp ts export-pathmap > catalog/path_map.json` (台帳 5.1 の前倒し。原パス → 現 URL)。
2. 再投入後にもう一度 `export-pathmap` を取り、`source_path` ごとに URL が変わったものを付け替え表に出す (`cluster_review.py --redirects old.json new.json`):

```yaml
version: 1
redirects:
  - from: /novel/marie-kyaria-dei-97/novel-toukou01-19971209/
    to: /novel/marie-mirai-kara-kita-onna/
    reason: cluster-split 2026-11
```

3. `scripts/wp/htaccess_block.py` がこれを `Redirect 301 <from> <to>` として ts-library ブロックに書く (visibility-roles-spec §4)。付け替え表は恒久に残す (URL の約束)。`--install` の検証に先頭 3 本の 301 を加える。

## 7. 再投入の方式 (T.24.7 で決める)

| 方式 | 内容 | 利点 / 欠点 |
|---|---|---|
| (a) 全消し再構築 | 👤 `wp ts reset --yes` → `sync-terms` → `import` → `apply-takedown` → `sync-pages/docs` ([`rebuild-runbook.md`](rebuild-runbook.md) §2-C) | 旧 slug の投稿が確実に消える / 本番 DB を全消しする (所要と復旧点が要る)。取り下げ申請 CPT は reset の対象外なので残る |
| (b) `wp ts import --prune` | catalog に無い `_ts_work_slug` / `_ts_episode_id` の投稿を一覧し、`trash` へ (子孫も) | DB を消さない / 実装が要る。upsert だけだと旧 slug の投稿が残る (rebuild-runbook §2-A の注意) |

どちらでも、再投入後に `wp ts verify` OK と §6 の 301 検証を行う。

## 8. 検証

| 確認 | 期待 |
|---|---|
| `make check` | OK (overrides の参照が全部実在、orphan 0) |
| `catalog/reports/work_builder.json` | `works` が裁定どおり増減、`needs_review` が 331 から減る |
| 末尾が助数詞の作品題名 | 0 件 |
| 旧 URL 3 本 | 301 → 新 URL (200) |
| `wp ts verify` | OK |

## 9. 実測の基準値 (2026-10-26。owner-feedback §7)

連載 519 作品 / 芯 2 種以上 258 / 共通接頭辞なし 71 (弱根拠 39・強根拠 32) / `needs_review` 331 (weak 271・multi-dir 38・large 22) / `work_overrides.yml` の雛形 368 件 (`confirmed: true` 0 件) / 末尾が助数詞の題名 10 件。
