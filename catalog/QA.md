# catalog QA レポート

`make catalog` が `scripts/wp/qa_report.py` で自動生成する。数字は各段の `catalog/reports/*.json` からの転記であり、ここで再計算はしない。

## 1. 収蔵件数

| 項目 | 値 |
|---|---:|
| episodes 合計 | 3844 |
| 　正規目録 (`honkan`、lib1–73) | 2887 |
| 　旧目録 (`legacy`、lib01–09 の差分) | 97 |
| 　目録外収蔵 (`uncatalogued`、設計 v1.4) | 859 |
| 　文庫未掲載の作者再掲 (`extern-repost`) | 1 |
| works (作品クラスタ) | 1451 |
| 　単発 / 連載 | 932 / 519 |
| authors (作者) | 322 |
| 　うち感想板を持つ | 300 |

## 2. パースの健全性

| 項目 | 値 |
|---|---:|
| 正規目録 (lib1–73) のパース失敗 | 0 |
| 旧目録ブロック / パース失敗 | 336 / 0 |
| 旧目録: 正規目録と重複で除外 / 追加 | 239 / 97 |
| episode_id の重複 | 0 |
| mailto 由来アドレスの残存 | 0 |
| 自己検査 (catalog_build) | すべて OK |
| 自己検査 (authors_build) | すべて OK |
| 自己検査 (terms_build) | すべて OK |

## 3. 目録外収蔵 (タスク 1.8)

| 項目 | 値 |
|---|---:|
| `novel/` 配下の本文ファイル | 3818 |
| 　既に目録にある | 2724 |
| 　目録外として追加 | 859 |
| 　意図的除外 | 235 |
| 　**取りこぼし** | 0 |
| 題名の解決 | 859 / 859 |
| 作者の解決 | 823 / 859 (不詳 36) |


除外の内訳 (`catalog/uncatalogued_excluded.jsonl` に全件):

| 項目 | 値 |
|---|---:|
| cgi-view | 56 |
| duplicate-of-catalogued | 58 |
| empty | 1 |
| site-template | 3 |
| title-page | 117 |

## 4. 分類語彙 (タスク 1.3)

| タクソノミー | 原表記の異なり | 正規化後 | 中核語 | 中核語の被覆 |
|---|---:|---:|---:|---:|
| `ts_genre` | 244 | 196 | 30 | 94.1% |
| `ts_type` | 187 | 165 | 30 | 93.6% |
| `ts_keyword` | 1087 | 1032 | 74 | 69.4% |


共有世界 (`ts_world`) 14 本 / 該当 408 話。


収蔵区分 (`ts_corpus`) — 語と表示名は `docs/glossary.md` の corpus 表が正典:

| term | 表示名 | 話数 |
|---|---|---:|
| `honkan` | 正規目録 (lib1–73) | 2887 |
| `legacy` | 旧目録 (lib01–09) | 97 |
| `uncatalogued` | 目録外収蔵 | 859 |
| `extern-repost` | 文庫未掲載 | 1 |
| `dojo` | ストーリー道場 | 0 |


- [OK] 全 episode の corpus 値に term がある — 被覆 3844/3844 話 / term の無い値 なし

- [OK] term はあるが実データが 0 件の corpus が無い (Phase 6 予定枠 dojo を除く) — 空 term なし

- [OK] corpus term の slug 重複 0 — 重複 なし

## 5. Work クラスタ (タスク 1.5)

| 項目 | 値 |
|---|---:|
| works | 1451 |
| orphan (どの work にも属さない話) | 0 |
| **needs_review** | 331 |
| 　内訳 | {'multi-directory': 38, 'large-cluster': 22, 'weak-evidence': 271} |
| タイトルページを持つ work | 231 |
| md5 一致の重複ファイル (alias) | 55 |

## 6. 回収経路 (provenance) の被覆率

| 項目 | 値 |
|---|---:|
| provenance あり | 3646 / 3844 (94.85%) |
| 正規目録分の被覆率 | 93.35% |
| 経路の内訳 (正規目録分) | {'wayback': 2466, 'live-site': 103, 'other': 29, 'commoncrawl': 77, 'narou': 3, 'megalodon': 15, 'pixiv': 1, 'yays-gapfill': 1} |
| 出所 | git 履歴 (回収コミットの件名と日付)。collinfo.json ではない |

## 7. 特殊エントリの内訳

| 項目 | 値 |
|---|---:|
| anchor | 8 |
| commented-out-in-source | 12 |
| external | 5 |
| human-override | 3 |
| image | 39 |
| notice | 1 |
| plain-text-repost | 2 |
| series-index | 1 |
| text | 1 |
| unattributed | 36 |


- `image` … 目録が画像ファイルを直接指す CG 作品
- `external` … 当時から外部サイトを指していたエントリ
- `anchor` … 1 ファイルに複数作品が同居する投稿アンソロジー (`toukou01–03.html`)
- `plain-text-repost` … 作者本人の再掲から回収したプレーンテキスト (1.6 の HTML→MD 変換の対象外)
- `commented-out-in-source` … 旧目録の HTML コメント内に隠されていたエントリ
- `notice` / `unattributed` … 作者欄が無い編集部告知 / 作者を特定できなかった目録外収蔵
- `series-index` … 目録の作者欄に人名でない値が入っていたシリーズ目次ページ (作者不詳ではない)
- `human-override` … `catalog/episode_overrides.yml` で原本の誤植を直した話。内訳は `catalog/reports/episode_overrides.json`

## 8. 本文 Markdown 変換 (タスク 1.6)

| 項目 | 値 |
|---|---:|
| 変換対象 (convertible) | 3644 |
| 　MD 正準に昇格 (無損失証明に合格) | 3642 |
| 　raw フォールバック (不合格) | 2 |
| 本文の原本が未回収 (`no-source`) | 200 |
| 証明合格率 | 99.95% |
| 合格分の不変量違反 | 0 |
| 受け入れ (合格率 ≥85%) | OK |
| `bodies/*.md` の実ファイル数 (照合) | 3642 |


変換モードの内訳: {'br-para': 3523, '-': 200, 'table': 86, 'hardwrap': 17, 'image-work': 17, 'plain-text': 1}

## 9. 人間の確認待ち (👤 1.5b)

| 項目 | 値 |
|---|---:|
| slug 確認待ち (作者) | 0 |
| slug 確認待ち (作品) | 1298 |
| slug 確認待ち (分類語彙) | 1407 |
| work_overrides.yml の雛形 | 331 |
| 裁定済み (作者 slug の `status: confirmed`) | 42 |
| 裁定による作者の併合 | 16 組 / 作者 17 名が統合で消えた |


確認するファイル: `catalog/slug_overrides.yml` / `catalog/work_overrides.yml`。`status: confirmed` にした行は再生成しても上書きされない。
作品 slug と分類語彙 slug は**サイト所有者の決定により自動生成のまま採用**する(確認しない)ので、上の「確認待ち」は残ったままでよい。


併合した作者 (裁定 = `catalog/review/authors-slugs.md`):

- `shining_heaven` ← 天爛 / 天爛／絵：ムクゲさん（
- `ryuhju` ← 龍酒 / 龍酒 原案：超！海老寿司
- `aki_michiru` ← 亜希みちる / 亜希みちる＆こうけい
- `dekoi` ← DEKOI / DEKOI（協力　DATTA）
- `yuasano_miki` ← ゆあさのみき / ゆあさのみき 画：もぐたん様
- `two_bit` ← ＴＷＯ−ＢＩＴ / (原作:TWO-BIT)
- `marie` ← Ｍａｒｉｅ / 麗香
- `maki_takashi` ← 薪喬 / 薪喬（まきたかし）
- `hiko` ← HIKO / ＨＩＫＯ（原作：城弾さん）
- `kakusan` ← 角 / 角さん(絵) / オヤジ さん(文)
- `yonezu` ← 米津 / 米津 画：みっしんぐ
- `aizu_rika` ← 会津里花 / 海津里花
- `qqqqq` ← ????? / ？？？？
- `moto` ← ＭＯＴＯ / もと(ＭＯＴＯ)
- `slents` ← スレントス -Slents- / 根室　眞琴 / 根室　眞琴改め、スレントス-Slents-
- `you_` ← you' / you’

## 10. 作者本人の再掲 (タスク 1.9)

| 項目 | 値 |
|---|---:|
| 既存エントリに本文と来歴を追加 | novel__200802__15232641__rainGirl.html |
| 文庫未掲載として新規収録 | repost__pixiv__272830 |
| 入力の欠落 | なし |


holding の他の回収物は検証未完のため対象外 (進行台帳 8.4)
