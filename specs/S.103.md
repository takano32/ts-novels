# S.103 Marie 氏の作品をすべて取り下げる

- 種類: 設計
- 状態: 有効
- 出典: docs/owner-feedback-2026-10.md の「Marie 氏の作品はすべて取り下げ」
- 関連: T.18

## Marie 氏の作品はすべて取り下げ

**現状 (実測)**:

| 項目 | 値 |
|---|---|
| 作者 | `catalog/authors.json` slug **`marie`**、表示名 Ｍａｒｉｅ、異表記 `Ｍａｒｉｅ・Ｒｅｉｋａ` / `Marie` / `麗香` (`novel/reika/` 由来)、感想板 `marie`、`contact_status: uncontacted` |
| 話 | **28** (正規目録 16 + 旧目録 12)。1997-12〜2004-02 |
| 作品 | **7** (`works.jsonl`): `marie-kyaria-dei-97` (10 話) / `marie-haatonopendanto` (6) / `marie-hananoseirei` (2) / `marie-youseinomizuumi` (2) / `marie-puukotowatashi` (1、原本未回収) / `marie-imajin` (1) / `marie-imajin-dai` (6) |
| 本番 WP | **33 投稿すべて publish** (親 7 + 話 26。`wp post list --ts_author=marie` 実測)。`/authors/marie/` 200 |
| 目録の告知 | **無し**。TD.1〜TD.9 は目録の「公開停止」文言を機械で拾った (`scripts/wp/catalog_build.py:159-163`、検査 `:1263-1266`) が、Marie 氏の件は目録に記載が無く**所有者の記憶が唯一の一次資料** |
| denylist | 未掲載 |
| 原本アーカイブ (原本アーカイブ) | 本人のファイル **28** (`novel/200202/03004409/` 17・フラット期 `novel/` 直下 6・`novel/reika/imagine/` 1・`~yays/library/novel/200202/03004409/` 4) + **他の作者と同居するファイル 2** (`novel/toukou01.html` = Marie 3 話 + ダイナモ 1 話、`novel/toukou02.html` = Marie 1 + みたま 1) + **作者感想板 30** (`~ts/kansou/bbs@log_marie.cgi` と res 29) + 名前の言及 52 ファイル (目録 lib*.html・series.html 等) |

**取り下げ対象の一覧 (実測 2026-10-26。T.18 が参照する)**:

- WP: 作品 7 (`marie-kyaria-dei-97` / `marie-haatonopendanto` / `marie-hananoseirei` / `marie-youseinomizuumi` / `marie-puukotowatashi` / `marie-imajin` / `marie-imajin-dai`) + 話 26 = **33 投稿** (すべて publish)。`apply-takedown` の `kind: author` が子孫ごと止める
- 原本アーカイブの本人ファイル **28**: `novel/200202/03004409/` の 17 本 (`career2023 career97 earring fairylake future heart01 heart02 ichigo imagine imagine01〜06 pink seirei`.html。`imagine.html` はシリーズのトビラ) / フラット期 `novel/` 直下の 6 本 (`marie01 marie02 heart2 seirei fairylake career23`.html) / `novel/reika/imagine/index.html` / `~yays/library/novel/200202/03004409/` の 4 本 (`career97 earring future pink`.html)
- 他作者と同居 (八重洲さんの判断): `novel/toukou01.html` (Marie 3 話 + ダイナモさん 1 話)、`novel/toukou02.html` (Marie 1 + みたまさん 1)
- 作者感想板 (八重洲さんの判断): `~ts/kansou/bbs@log_marie.cgi` + `bbs@res_*_log_marie.cgi` 29 本 = 30 ファイル
- 名前の言及 52 ファイル (`lib*.html` `series.html` 等の目録) は原本として残す (取り下げの対象ではない)
- `marie-puukotowatashi` (旧目録のみ、原本未回収) は目録項目だけだが同様に止める

**仕様 (決定案)**:

1. `takedown/denylist.yml` に **TD.14** `target: {kind: author, value: marie}`、`reason_class: author_request`、`received_via: other` (所有者の証言)、`requested_at` は当時の年が分かれば当時、分からなければ受領日 2026-10-26。`public_note`「Ｍａｒｉｅさんの作品 (7 作) — 作者の申し出により掲載していません」。
2. WP は既存機構で即日: `wp ts apply-takedown` は author 単位に対応済み (`commands.php:890-897`、子へ波及 `:899-905`) → 33 URL を 410 (`htaccess_block.py --install`)。S.102 が入れば自動的に「運営委員は閲覧可」になる。`/removed/` に掲示。
3. 原本アーカイブ (T.35.2 のビルド除外が未実装なので、原本アーカイブ側の除外 (S.78) の暫定手順どおり) 本人のファイル 28 をリポジトリから削除してコミット (`.github/workflows/deploy-pages.yml` はリポジトリ直下をそのまま配信)。**同居ファイル 2 本**は Marie 氏の区画だけを切除した版に差し替えるか、そのまま残すか → 所有者判断。**感想板 30 本**は読者の投稿 (作品ではない) なので既定は残す → 所有者判断。
4. git 履歴からの除去 (S.79) は既定どおり行わない (依頼があれば)。public リポジトリと `raw-original` タグに残る事実は S.79 のとおり。
5. 旧サイト時代の申し出を引き継ぐ扱いは TD.1〜TD.9 と同系 (当時の運営が受理した申し出を復元側が引き継ぐ)。

**未決**: 申し出の時期 (年) / `/removed/` に名前を出してよいか / 同居 2 ファイルと感想板 30 ファイルの扱い / 作品 7 のうち `marie-puukotowatashi` は原本未回収 (目録項目のみ) だが同様に止めるか (既定: 止める)。
**規模**: WP 1 時間。原本アーカイブ側はコミット 1 回。
