# 所有者レビュー (2026-10-26) への対応仕様 — 八重洲さんのコメント 11 件 + 質問 1 件

**状態: 仕様 v1 (2026-10-26)。CP-4 ([`cp4-review.md`](cp4-review.md)) に対するサイト所有者
(八重洲さん = 文庫設立者 yays、旧八重洲メディアリサーチ) のレビュー結果と、その対応の決定案。**
方針としての位置づけは設計書 [`wordpress-library-design.md`](wordpress-library-design.md) の
**v1.6 改訂章**が持ち、本書はその根拠と細部 (実測・該当コード・規模・未決) を担う。
実装タスクは [`../TASKS.md`](../TASKS.md) の **T.16〜T.28** (1 大項目 = 1 成果)。
実装仕様 (判定基準・閾値・状態遷移・権限・ファイル形式・コマンド) は性質ごとに 4 本に分け、そちらが正典:
[`visibility-roles-spec.md`](visibility-roles-spec.md) (§2)・[`takedown-form-spec.md`](takedown-form-spec.md) (§11)・
[`versions-spec.md`](versions-spec.md) (§6)・[`cluster-overrides-spec.md`](cluster-overrides-spec.md) (§7)。
用語 (本館/別館/旧館 ほか) は [`glossary.md`](glossary.md) が正典。**この 3 語は内部用語**で、
本書が提案する表示文言には使わない。

実測はすべて 2026-10-26 に、本番は `scripts/wp/novels-ssh` の読み取りコマンド (`wp post list` /
`wp option get` / `wp eval` / SELECT) と外部からの `curl` (キャッシュバスター付き GET/HEAD) のみで行った。
**本番・リポジトリへの変更はしていない** (本書と設計書・台帳・TASKS の追記のみ)。

| 本番の現況 (実測) | 値 |
|---|---|
| `ts_catalog_commit` | `6bb57472` (2026-08-31)。cp4-review は `f04d7a5e`、台帳 CP-4 節は `85cc9bf7` と書いており**両方古い** |
| `ts_work` 投稿 | **4,363** (publish 4,345 / draft 18 = 取り下げ済み 18 URL) |
| WP ユーザ | **administrator 3 名のみ**。ロールは WP 既定の 5 種だけ (カスタムロール無し) |
| プラグイン | ts-library (mu) / wp-multibyte-patch / cloudsecure-wp-security。フォーム系は無し |
| cron / mail | `DISABLE_WP_CRON` 未定義 (閲覧駆動の疑似 cron)。`sendmail_path` 設定あり・`admin_email` 設定あり (送達は未検証)。サーバ crontab は使える (既に 1 本) |
| 窓口 | `/wp-json/` 200 (REST 有効)、`xmlrpc.php` 403、`wp-login.php` 403 (CloudSecure)。**GitHub リポジトリの Issue は無効** (`gh repo view`: hasIssuesEnabled=false) |

---

## 0. 全体の優先順位と依存関係

| 順 | 項目 | 緊急度 | 決定案 (1 行) | 規模 | 依存 |
|---|---|---|---|---|---|
| 1 | **10 CGI** | **即時 (実態は確定)** | 配信中の `.cgi` 9,019 本は**すべて CGI が出力した画面の保存**。ソース 0・シェバン 0・パスワード 0。「落ちてくる」のは GitHub Pages が `.cgi` を `application/octet-stream` で返すため。サーバパスを晒すエラー出力 2 本を削除し、秘密走査を `make check` に恒久化 | 小 | — |
| 2 | **11-a 窓口の修復** | **即時** | 公開中の `/takedown/` が案内する唯一の経路 **GitHub Issue が無効** (設計上も不採用)。暫定でメール窓口を明記し 72 時間の文言を外す | 小 | — |
| 3 | **3 Marie** | 即時 | denylist に `kind: author, value: marie` (TD-2026-0014)。WP 側 33 URL は既存機構で即日。原本アーカイブ側 28 ファイル + 共有 2 + 感想板 30 の範囲は所有者確認 | 小 | 運営委員の閲覧は 2 の後 |
| 4 | 1 / 4 / 5 | 小 | サイドバーから「オススメの環」を外し「オーナー作品集」(→ `/authors/yays/`) を足す。「文庫前史 (1999)」→「にせ乙女パラダイス」 | 小 (配備 1 回) | — |
| 5 | **2 運営委員権限** | **3/6/11 の前提** | ロール「運営委員」+ 非表示を draft → **private** に。読者には WP が 410 (取り下げ) / 301 (旧版) を返し、ログイン中の運営委員には本文を表示。`.htaccess` の 410 は画像資産のみに縮小 | 中 | nginx キャッシュとログインの実測 |
| 6 | 7 クラスタ誤混入 | 中 | `work_overrides.yml` に **分割・併合・切り離し**を実装 (現状は題名しか直せない) + 誤混入の疑い 71 作品 (弱根拠 39) の人手レビュー | 中 | 6 より先 |
| 7 | 6 重複掲載 | 中 | 同題 303 組 / 684 話。**新しい掲載日の版を残す**。本文同一・類似度 ≥0.95 の 209 組は自動、89 組は人手確認。旧版は private (運営委員のみ) + 読者は新版へ 301 | 中 | 2, 7 |
| 8 | **11-b フォームと自動取り下げ** | 中〜大 | フォーム → 確認メール → **10 日後に cron が自動で private 化** → 運営委員が承認 (即時) / 却下 (停止) / 復帰で上書き。72 時間の記述 33 箇所を撤去 | 大 | 2 |
| 9 | 9 about / 質問 (編集) | 小〜中 | 所有者の原稿を `content/pages/about.html` (正典) に。WP で直接編集する経路も `wp ts export-pages` で git に書き戻せば可。作品・話は WP で編集しない | 小 | — |
| 10 | 8 アイコン | 後 | インライン SVG スプライト (外部依存ゼロの方針どおり)。Phase 4 の磨きとして最後 | 半日 | — |

依存の要点: **2 が 3 (運営委員の閲覧)・6・11 の共通基盤**。7 (束ね直し) を 6 (版の重複判定) より先にやらないと superseded の計算をやり直すことになる。11 の文言改訂は 9 (about) と作者連絡文 (T.33) に波及する。

---

## Q. 「私の手元で編集を行うことは可能か」

**現状**: 可能だが、何を編集するかで安全性が全く違う。

- 設計原則 1 (設計書 §0、台帳 普遍ルール 1): **単一真実源は `catalog/`、WP の DB は使い捨ての派生ビュー、管理画面での手動編集は禁止**。
- `wp ts import` は前回 import 後に編集された投稿を検知して **上書きせず skip** する (`mu-plugins/ts-library/includes/commands.php:181-187` `manually_edited`、`:358-361` 警告)。ただし `wp ts verify` はその投稿を **NG** として挙げ続ける (`:736-741`)。`--overwrite-manual` を付けると消える。
- `wp ts reset --yes` は `ts_work / ts_doc / ts_dojo / ts_board_post` を全消しする (`:932-951`) → これらへの手編集は**やり直し (rebuild-runbook §2-C) で消える**。
- 固定ページ (`page`) は reset の対象外。`wp ts sync-pages` も手動編集を保護する (`:536-539`)。ただし正典は `content/pages/*.html` (git) なので、WP 側で直した内容は git に無い状態になる。
- アカウントは現在 administrator 3 名のみ。ロールは WP 既定の 5 種 (本番 `wp role list`)。

**仕様 (決定案)**:

| 編集対象 | 可否 | 経路 |
|---|---|---|
| 固定ページ 4 枚 (`/about/` `/archive/` `/takedown/` `/removed/`) と解題 (`content/docs/`) | **可** | A: 原稿をもらってセッションが `content/pages/*.html` に入れコミット → `sync-pages`。B: 所有者が WP 管理画面で編集 → セッションが **`wp ts export-pages` (新設)** で `content/pages/*.html` に書き戻してコミット。どちらでも git が正典のまま |
| 作品・話 (`ts_work`)、作者 term、分類 term | **不可** (次の import/reset で消える・verify NG) | 「この作品のここを直して」と伝えてもらい、セッションが `catalog/episode_overrides.yml` / `work_overrides.yml` / `slug_overrides.yml` (本書 7) / 版の指定 (本書 6) に反映して再投入 |
| 取り下げ申請の確認・承認・却下、取り下げ済み/旧版の閲覧 | **可** | ロール「運営委員」の管理画面 (本書 2・11) |
| テーマ・プラグイン・設定 | 不可 (deploy.sh が git から配備する) | 変更要望として受ける |

アカウントは 2 種を用意する: **運営委員** (閲覧 + 申請処理) と **運営委員 (編集可)** (+ 固定ページ・解題の編集)。
administrator を渡すことも技術的には可能だが、プラグイン追加・テーマ編集が配備で巻き戻る構造なので勧めない。

**未決 (所有者に聞く)**: どの種別のアカウントにするか / ログイン URL の伝え方 (CloudSecure の値は文書に書かない。口頭または別経路) / 2 要素認証の要否。

---

## 1. サイドメニューの「オススメの環」は不要

**現状**: `mu-plugins/ts-library/includes/render.php:244-248` `ts_bunko_sidebar()` の `$items` に
`['osusume', 'オススメの環']`。本番の全ページに表示中 (実測: `/about/` の HTML に 1 箇所)。
索引ページ `/index/osusume/` 本体は `includes/indexes.php:9,12,67,199-240` (200、226KB)。
作品ページ末尾の「この作品からのオススメ」(`render.php:172-189`) は別の部品。

**仕様**: サイドバーの 1 行を外す。`/index/osusume/` の URL は残す (cp4-review の URL 一覧に載せた URL を
いきなり 404 にしない。索引からは辿れなくなる)。作品ページの「この作品からのオススメ」は維持。

**未決**: 一覧ページ自体も不要なら `TS_INDEX_KINDS` から外して 404 にする (1 行)。
**規模**: 1 行 + テーマ/mu-plugin の配備 1 回。

---

## 2. 取り下げ作品を運営委員権限で閲覧できるように

**現状**:

- 取り下げ = ①投稿を **draft** 化 (`commands.php:797`) + ②本番 `.htaccess` に `Redirect gone` (`scripts/wp/htaccess_block.py:33-53`。URL は WP の option `ts_takedown_urls` から。本番 18 URL)。
- draft は編集権限のある人だけがプレビュー URL で見られる形で、**閲覧専用の権限では見えない**。
- `.htaccess` の 410 は **Apache 段で返り PHP に届かない**ので、ログインしていても 410。
- ロールは WP 既定のみ。Editor は `read_private_posts=1` だが `edit_posts=1` (作品を編集できてしまう) (本番 `wp eval` 実測)。`ts_work` は `capability_type=post`・`map_meta_cap=1` → private の閲覧権限は `read_private_posts` (実測)。
- 別館 (GitHub Pages) にはログインの概念が無い。

**仕様 (決定案)**:

1. **ロール** `ts_committee`「運営委員」: `read` + `read_private_posts` + 目印 `ts_view_hidden` (+ 11 の `ts_manage_takedown`)。編集系は付けない。
   `ts_committee_editor`「運営委員 (編集可)」: 上に `edit_pages` 系と `ts_doc` の編集を足す (`ts_doc` の capability_type を専用にする改修が要る)。登録は mu-plugin (`register`) で、`wp ts` に `role-sync` を足して冪等に。
2. **非表示の実装を draft → private に変更**。可視性メタ `_ts_visibility` ∈ {`withdrawn` (取り下げ), `superseded` (旧版。本書 6)}。子 (話) へ波及させる現行ロジック (`commands.php:899-905`) はそのまま。
3. **読者 (非ログイン) への応答は WP が返す**: `template_redirect` で URL から投稿を引き (private は本クエリに乗らず 404 になるため `get_page_by_path` 相当で自前解決)、`withdrawn` → `status_header(410)` + `nocache_headers()` + 短い案内 (「作者の申し出により掲載していません」+ `/removed/` へ)、`superseded` → 新版へ **301**。ログイン中の運営委員 → 本文を表示し、冒頭に帯「取り下げ済み (運営委員にのみ表示)」。
4. `get_the_title()` が private に付ける「非公開: 」接頭辞 (`private_title_format`) は帯に置き換える (filter)。
5. **`.htaccess` の ts-library ブロックから WP の URL の `Redirect gone` を撤去**し、WP を経由しない `/assets/annex-img/` の 410 (`htaccess_block.py:56-80`) だけ残す。
6. 別館: 取り下げ分は Phase 5.2 のビルド除外 (404)。運営委員が旧内容を見たいときは **git (public リポジトリ) で読める**ことを明記。旧版 (6) は別館では全部そのまま (原本レイヤ)。
7. `apply-takedown` は `reinstated` で publish に戻す動作を持つ (現状は止めるだけ)。

**既存の裁定との衝突と解消**:

| 既存 | 衝突 | 解消 (v1.6) |
|---|---|---|
| v1.1「真の 410 は `.htaccess` の `Redirect gone`」 | Apache 段ではログイン判定ができない | 410 を WP 側に移す。mu-plugins は管理画面から無効化できない (glossary) ので「PHP が止まると 410 が消える」リスクは許容。代替案 = `.htaccess` で `wordpress_logged_in_` Cookie の無いときだけ `[G]` を返す RewriteCond (Cookie 名だけで通し、真の判定は WP が行う) も可能。実測で選ぶ |
| 「`wp ts import` は post_status に触れない」(`commands.php` 冒頭) | 旧版 (6) は catalog 由来で private にしたい | catalog 由来の可視性 (`superseded_by`) は import が設定する、に改める。優先順位は **取り下げ > 旧版 > 公開** |
| environment §3 nginx キャッシュ | 読者向け 410/301 がキャッシュされ、ログイン中の運営委員にもキャッシュが返る恐れ | **要実測**: ログイン Cookie 付きリクエストがキャッシュを素通りするか (`_probe/` と試験ユーザで確認)。素通りしないなら代替案 (上) に切り替える |

**規模**: mu-plugin 150〜250 行 (ロール・visibility・template_redirect・title filter) + `apply-takedown` 改修 + `htaccess_block.py` の 410 を資産限定に + removal-runbook v1。実測 半日。
**実装仕様**: [`visibility-roles-spec.md`](visibility-roles-spec.md) (ロールの権限・可視性メタ・410/301 の振る舞い・`.htaccess` ブロック・実測手順・検証表)。

---

## 3. Marie 氏の作品はすべて取り下げ

**現状 (実測)**:

| 項目 | 値 |
|---|---|
| 作者 | `catalog/authors.json` slug **`marie`**、表示名 Ｍａｒｉｅ、異表記 `Ｍａｒｉｅ・Ｒｅｉｋａ` / `Marie` / `麗香` (`novel/reika/` 由来)、感想板 `marie`、`contact_status: uncontacted` |
| 話 | **28** (正規目録 16 + 旧目録 12)。1997-12〜2004-02 |
| 作品 | **7** (`works.jsonl`): `marie-kyaria-dei-97` (10 話) / `marie-haatonopendanto` (6) / `marie-hananoseirei` (2) / `marie-youseinomizuumi` (2) / `marie-puukotowatashi` (1、原本未回収) / `marie-imajin` (1) / `marie-imajin-dai` (6) |
| 本番 WP | **33 投稿すべて publish** (親 7 + 話 26。`wp post list --ts_author=marie` 実測)。`/authors/marie/` 200 |
| 目録の告知 | **無し**。TD-0001〜0009 は目録の「公開停止」文言を機械で拾った (`scripts/wp/catalog_build.py:159-163`、検査 `:1263-1266`) が、Marie 氏の件は目録に記載が無く**所有者の記憶が唯一の一次資料** |
| denylist | 未掲載 |
| 原本アーカイブ (別館) | 本人のファイル **28** (`novel/200202/03004409/` 17・フラット期 `novel/` 直下 6・`novel/reika/imagine/` 1・`~yays/library/novel/200202/03004409/` 4) + **他の作者と同居するファイル 2** (`novel/toukou01.html` = Marie 3 話 + ダイナモ 1 話、`novel/toukou02.html` = Marie 1 + みたま 1) + **作者感想板 30** (`~ts/kansou/bbs@log_marie.cgi` と res 29) + 名前の言及 52 ファイル (目録 lib*.html・series.html 等) |

**取り下げ対象の一覧 (実測 2026-10-26。T.18 が参照する)**:

- WP: 作品 7 (`marie-kyaria-dei-97` / `marie-haatonopendanto` / `marie-hananoseirei` / `marie-youseinomizuumi` / `marie-puukotowatashi` / `marie-imajin` / `marie-imajin-dai`) + 話 26 = **33 投稿** (すべて publish)。`apply-takedown` の `kind: author` が子孫ごと止める
- 原本アーカイブの本人ファイル **28**: `novel/200202/03004409/` の 17 本 (`career2023 career97 earring fairylake future heart01 heart02 ichigo imagine imagine01〜06 pink seirei`.html。`imagine.html` はシリーズのトビラ) / フラット期 `novel/` 直下の 6 本 (`marie01 marie02 heart2 seirei fairylake career23`.html) / `novel/reika/imagine/index.html` / `~yays/library/novel/200202/03004409/` の 4 本 (`career97 earring future pink`.html)
- 他作者と同居 (👤): `novel/toukou01.html` (Marie 3 話 + ダイナモさん 1 話)、`novel/toukou02.html` (Marie 1 + みたまさん 1)
- 作者感想板 (👤): `~ts/kansou/bbs@log_marie.cgi` + `bbs@res_*_log_marie.cgi` 29 本 = 30 ファイル
- 名前の言及 52 ファイル (`lib*.html` `series.html` 等の目録) は原本として残す (取り下げの対象ではない)
- `marie-puukotowatashi` (旧目録のみ、原本未回収) は目録項目だけだが同様に止める

**仕様 (決定案)**:

1. `takedown/denylist.yml` に **TD-2026-0014** `target: {kind: author, value: marie}`、`reason_class: author_request`、`received_via: other` (所有者の証言)、`requested_at` は当時の年が分かれば当時、分からなければ受領日 2026-10-26。`public_note`「Ｍａｒｉｅさんの作品 (7 作) — 作者の申し出により掲載していません」。
2. WP は既存機構で即日: `wp ts apply-takedown` は author 単位に対応済み (`commands.php:890-897`、子へ波及 `:899-905`) → 33 URL を 410 (`htaccess_block.py --install`)。本書 2 が入れば自動的に「運営委員は閲覧可」になる。`/removed/` に掲示。
3. 原本アーカイブ (Phase 5.2 のビルド除外が未実装なので、removal-runbook ③ の暫定手順どおり) 本人のファイル 28 をリポジトリから削除してコミット (`.github/workflows/deploy-pages.yml` はリポジトリ直下をそのまま配信)。**同居ファイル 2 本**は Marie 氏の区画だけを切除した版に差し替えるか、そのまま残すか → 所有者判断。**感想板 30 本**は読者の投稿 (作品ではない) なので既定は残す → 所有者判断。
4. git 履歴の除去 (④) は既定どおり行わない (依頼があれば)。public リポジトリと `raw-original` タグに残る事実は removal-runbook ④ のとおり。
5. 旧館時代の申し出を引き継ぐ扱いは TD-0001〜0009 と同系 (当時の運営が受理した申し出を復元側が引き継ぐ)。

**未決**: 申し出の時期 (年) / `/removed/` に名前を出してよいか / 同居 2 ファイルと感想板 30 ファイルの扱い / 作品 7 のうち `marie-puukotowatashi` は原本未回収 (目録項目のみ) だが同様に止めるか (既定: 止める)。
**規模**: WP 1 時間。原本アーカイブ側はコミット 1 回。

---

## 4. サイドメニューに「オーナー作品集」(八重洲二世 名義の一覧)

**現状**: 作者 slug **`yays`**、表示名「八重洲二世」、異表記「作・不詳／訳・八重洲二世」(翻訳 1 話「ＢＢＳ☆ハプニング！」)、感想板 `yays`。話 **20** / 作品 **12**。本番 `/authors/yays/` は 200・h1「八重洲二世」・作品カード 12 (実測)。
ただし 12 のうち**重複が多い**: `clone01` 旧目録版と正規版が同じ作品に 2 話として並ぶ / 「ＭＹ ＰＵＲＥ ＣＬＯＮＥ−ＤＯＬＬ」は −(U+2212) と －(U+FF0D) の差で 2 作品 / 「奇術」は旧目録版と正規版が 2 作品 (`yays-kijutsu` / `yays-kijutsu-2`。題名 2 文字で題名一致の規則が働かない) / `grangear01` ×2 / アニパロ 3 本 ×2 版 / 「Capsule Monsters」(目録外・英題) と「カプセル モンスター」(目録・類似度 0.987、目録版が長い) で 2 作品。実質 **9〜10 作**。

**仕様**: サイドバーに「オーナー作品集」→ `/authors/yays/` を足す (置き場は「この文庫について」ブロック `render.php:260-265` の先頭か、索引ブロックの末尾)。重複の解消は本書 6/7 で行う (6 が入れば作品カードは自動的に 9〜10 になる)。作者ページ冒頭に説明文「文庫設立者・八重洲二世の作品」を出すなら、`sync-terms` が description を上書きするので **catalog 側に持たせる** (`authors.json` に `description` 欄を足す。`authors_build.py` の出力項目 `:485` 付近)。
形式: `catalog/author_notes.yml` (git 管理) に `notes: { yays: { description: "文庫設立者・八重洲二世の作品" } }` のように作者 slug ごとの `description` を書き、`authors_build.py` が `authors.json` の各作者に `description` (無ければ null) として出す。`sync-terms` は term の description に書く (ハッシュに含まれるので冪等)。

**未決**: ラベルは「オーナー作品集」のままでよいか (「設立者の作品」等) / 説明文の有無と文言 / 犬瓦ハム太 名義 (本書 5) をこの作者ページに結び付けるか。
**規模**: 1〜3 行 + 配備。

---

## 5. 「文庫前史 (1999)」→「にせ乙女パラダイス」

**現状**:

- 生成: `scripts/wp/docs_build.py:37` `('prehistory', '文庫前史 (1999)', ~ezpe/yasai/*.html)` → 8 本の `ts_doc` (slug 接頭辞 `prehistory-`、本番 ID 8764〜8771、URL `/docs/prehistory-nyotai/` 等)。`section_label` は WP メタ `_ts_section_label` に入るが**表示には使われていない**。
- 表示: `includes/indexes.php:247` `'prehistory' => '文庫前史 (1999)'` (「運営文書・資料」の区分見出し。本番 `/index/docs/` の `id="sec-prehistory"` に出ている) と `indexes.php:156-158` 年表の脚注「文庫の始まる前、1999 年の原型サイトの記録は 文庫前史 にあります」。
- 原本 `~ezpe/yasai/` 8 頁の `<title>`: 「にせ乙女パラダイス・入り口」「〜女体化リンク集」「〜ＳＳ置き場」「人類女の子化計画ホーム」「座談会ログ」「リンク」「管理人の連絡先」「にせ乙女パラダイス」 → **所有者の証言と一致**。文庫の前身ではなく、**文庫と並行して運用された女性向け女体化二次創作の姉妹サイト**。
- 内部文書の誤り: glossary「世代 ① 前史 (`~ezpe`、1999 頃)」/ data-inventory `:85`「第 1 世代」`:132`「1999 年の文庫前史の静的ページ」/ 設計 v1.4 表「~ezpe 世代の静的ページ 8 → 文庫前史として `ts_doc` に」/ 台帳 4.9 / TASKS T.2.7。`~ezpe` は文庫の感想ノート `noteky` も載せていた**最旧のホスト**ではあるが「前史」ではない。
- 「犬瓦ハム太」はリポジトリ内で `~ezpe/yasai` 8・`~yays/cgi-bin` 6・`~ezpe/cgi-bin` 1 のファイルに出るだけで、**catalog の作者には居ない** (作品の帰属は変わらない)。

**仕様**:

1. 表示ラベルを **「にせ乙女パラダイス」** に: `indexes.php:247` と `docs_build.py:37` (後者はメタのみだがハッシュが変わり `sync-docs` が 8 本 updated になる)。年表脚注は「文庫と並行して運営されていた姉妹サイト『にせ乙女パラダイス』の記録は…」に。
2. 区分見出しの直下に 1 行の解題 (読者向け): 「文庫と並行して一時期運用されていた、女性向けの女体化二次創作などを扱うサイト」。
3. section キーと slug (`prehistory-*`) は**維持** (URL 不変。変えるなら 8 本の 301 が要る)。
4. 内部文書の訂正: glossary 世代①を「最旧ホスト `~ezpe` (文庫の感想ノート noteky と、姉妹サイト にせ乙女パラダイス)」に、data-inventory `:132` を同様に。設計 v1.4 の表は追記ルールに従い v1.6 で失効注記。

**未決**: ラベルに年 (1999〜) を付けるか / URL (`prehistory-`) を変えるか / 管理人名義「犬瓦ハム太 (八重洲二世と妹氏の共同名義)」を解題に書くか (妹氏は第三者なので既定は書かない)。
**規模**: コード 3 箇所 + 文書 3 箇所 + `sync-docs`。

---

## 6. バージョン違いの同一作品 — 新しいほうを残し、古いほうは運営委員のみ

**現状 (実測。`catalog/episodes.jsonl` 3,844 話を 作者 × 題名 (NFKC・空白と記号を除去) で束ね、別ファイルを指すもの)**:

| 指標 | 値 |
|---|---|
| 同題で別ファイルの組 | **303 組 / 684 話** (全話の 17.8%) |
| うち本文テキスト (タグ・空白除去後) が**完全一致** | **103 組** (うちバイト一致で `work_builder` が alias 認定済み 57 組 / 71 話) |
| うち本文が**相違** | **195 組** — 類似度 ≥0.95: 106 / 0.8〜0.95: 16 / 0.5〜0.8: 13 / **<0.5: 60 (同題の別作の疑い)** |
| 読めず (原本未回収) | 5 組 |
| corpus の組合せ | 正規目録 × 目録外収蔵 **171** (城弾さんの `novel/200703/16205836/` 再投稿群が中心。例 `twins01` 2013 年版 vs 2007 年版、類似度 0.997) / 正規 × 旧目録 **69** (1997–98 版 → 2002–03 の再掲。Marie・八重洲二世・紀音零 ほか。本文同一が多い) / 目録外同士 57 / その他 6 |
| 作品単位の同名 | **20 組 / 40 作品** (例 `yays-kijutsu` / `-2`、`kion_rei-kizuna` / `-2`、`baretto-anarogutokorosan` / `-2`) |
| 1 組 1 版だけ残した場合に非表示になる話 | 上限 **371 話** |

機構の現状:

- `work_builder.py:348-362` は **md5 一致だけ** alias にし、**正本に「最古」を選ぶ** (`:359` `min(dup, key=episode_order_key)` = 掲載日昇順) — 所有者方針「新しいほうを残す」の**逆**。
- `commands.php:314-342` の import は alias を見ずに**全話を投稿する** → alias 71 話も本番で公開中 (例 `marie-youseinomizuumi` の子 `novel-fairylake` と `03004409-fairylake`)。
- 題名の表記差 (−/－、英題/片仮名、「奇術」のような 3 文字以下) は題名一致の規則 (`:250-258`、芯 4 文字以上) を通らない。

**仕様 (決定案)**:

1. **「新しい」の判定**: ① 目録の掲載日 (`date`) が新しい → ② 同日なら corpus 優先 `honkan` > `uncatalogued` > `legacy` (目録に載った版を正) → ③ なお同じなら本文の長い方。alias の正本選定も同じ規則に揃える (最古 → 最新)。
2. 新段 **`scripts/wp/versions_build.py`** (`make catalog` で `work_builder` の後): 作者 × 正規化題名 (+ `catalog/versions_overrides.yml` の手動指定: 異題同作・同題別作) で束ね、本文の正規化テキストの一致/類似度を取り、`works.jsonl` の各話に **`superseded_by: <episode_id>`** を付ける。自動確定 = バイト一致・テキスト一致・類似度 ≥0.95 (**209 組**)。<0.95 の **89 組**は雛形を出して人手確認 (0.5 未満 60 組の既定は「別作品」)。
3. import: `superseded_by` のある話 → `post_status=private` + `_ts_visibility=superseded` + `_ts_superseded_by`。読者は旧 URL から新版へ **301** (URL は生かす)、運営委員は旧版を帯「旧版 (YYYY 年の掲載)。読者には新版を表示しています」付きで閲覧 (本書 2)。新版の書誌カードには運営委員にだけ「旧版あり」を出す。
4. 単発作品が superseded なら作品投稿ごと private。作品単位の同名 20 組は話の superseded で片方が空になるので、その作品も private。索引・件数は publish だけ数える現行実装 (`indexes.php:80-95`) のまま整合。
5. 別館は全版をそのまま置く (原本レイヤ)。書誌カードの「無改変の原本を読む」は新版のものを指す。

**既存の裁定との衝突と解消**: v1.4 最大掲載原則「サルベージしたものはすべて掲載。例外は denylist / スパム / メールアドレスのみ」→ 旧版の非表示は「掲載しない」ではなく**「版の整理」** (旧版は運営委員が閲覧でき、原本アーカイブに常設され、URL は新版へ転送される) と位置づけ、例外 3 つには加えない (v1.6 に明記)。import の「post_status に触れない」は本書 2 の表どおり改める。

**未決**: 類似度 0.8〜0.95 (16 組) を自動にするか / 旧目録 (1997–98) 版を「初出の姿」として残す史料価値を取るか (既定: 非表示。原本アーカイブで読める) / 相違の大きい改稿 (例 城弾 `angelvoice` 2013 vs 2007、類似度 0.24) を「版違い」とみなすか「別作」とみなすか。
**規模**: builder 1 本 (~200 行) + import/テーマ改修 + 人手確認 89 組 (1〜2 時間) + 再投入。**本書 7 の後に実施**。
**実装仕様**: [`versions-spec.md`](versions-spec.md) (束ね方・類似度と閾値・新版の選び方・`versions_overrides.yml` の形式・出力・検証)。

---

## 7. シリーズ判定のバグ (別作品の混入) と手動修正

**現状 (実測)**:

| 指標 | 値 |
|---|---|
| 連載扱いの作品 | 519 (1,451 作品中) |
| 構成話の題名の芯 (巻号を落としたもの) が 2 種以上 | 258 |
| うち芯どうしに共通接頭辞 (2 文字以上) が無い = **別題が混在** | **71** (根拠が弱い規則だけで束ねた **39** / 強い根拠あり 32) |
| `needs_review` (`catalog/reports/work_builder.json`) | 331 (weak-evidence 271 / multi-directory 38 / large-cluster 22) |
| `work_overrides.yml` の雛形 | 368 件、**`confirmed: true` は 0 件** |

誤混入の例 (弱い根拠のみ): `marie-kyaria-dei-97` (10 話 = キャリア・デイ 97/2023 + 未来から来た女 + 虹色のイヤリング + イチゴのお弁当箱。`toukou01.html` の同一ファイル衝突群 `+日付` と `career*` の語幹) / `yays-yuushaouga-…` (勇者王 + セラムン + エヴァの 3 パロディ = `aniparo` 連番) / `yays-awasekagaminokoibitotachi` (`story2` / `story3` = 別作 2 本) / `million-tenshinooshigoto` (14 話に別題 4) / `izumi_miki-gunshuunonakadenokodoku` (`all_alone` 連番に別題 3) / `johdan-hanayochanshiriizubangaihen-hantaashiriizugaiden-hantaagaisuto` (華代ちゃん + ハンター)。強い根拠ありでも疑わしい例: `marie-haatonopendanto` (series.html が ピンクのランドセル と ハートのペンダント を同じ行に列挙)。

原因: 規則 5 **filename-stem** (`work_builder.py:237-248`: 同一ディレクトリ・同一作者で末尾連番を外した語幹が一致) が「作者が同じ接頭辞で別作を並べた」場合に効きすぎ、規則 6 **title-prefix** (`:250-258`) が版違いを束ねる。**1.5b (TASKS T.7.5.1) で裁定したのは作者 slug 40 件と同名 2 組だけ**で、クラスタ本体は `catalog/review/works-risk-note.md` が記録するとおり「自動生成のまま採用」。
**手動修正の機構は不足**: `work_overrides.yml` で効くのは `work_title` と `note` だけ (`:368-371`) → **分割・併合・切り離しはできない**。付随バグ: 題名の芯の切り出しが「第１回」「その」「Lesson」「EPISODE」を落とさず、作品名が「イマジン 第」「本当の自分 第」「〜その」で終わる (10 作品、works-risk-note §1-F)。

**仕様 (決定案)**:

1. `work_overrides.yml` のスキーマ拡張 (鍵は現行どおり `<作者slug>|<機械の題名>`): `split: [{work_title, episodes: [episode_id…]}]` / `detach: [episode_id…]` (単独作品化) / `merge_into: "<鍵>"` / `confirmed: true`。`work_builder` は union-find の後に適用し、`evidence` に `override` を記録。slug は `slug_overrides.yml` の confirmed を保つ。
2. 題名の芯の正規表現 (`:59-69`) に 回・Lesson・EPISODE・stage・その を追加し、末尾が助数詞で終わる題名 (10 件) を直す。
3. レビュー資料 `catalog/review/clusters-2026-10.md` を生成 (71 作品。弱根拠 39 を先頭に、各話の題名・ファイル名・根拠を並べる) → 所有者/運営委員が ○× と正しい束ね方を書く → セッションが overrides に転記 → `make catalog` → 再投入。
4. **URL への影響**: 束ね直した作品・話の URL が変わる (works-risk-note §0)。公開中なので旧 URL → 新 URL の 301 を `.htaccess` の ts-library ブロックに足す (生成は `export-pathmap` の差分から)。

**未決**: レビューを所有者が行うか、セッションが一次案を作って所有者が承認する形か (推奨: 後者)。
**規模**: builder 改修 1 日 / レビュー 71 件 (1〜2 時間) / 再投入 + 301。**本書 6 より先**。
**実装仕様**: [`cluster-overrides-spec.md`](cluster-overrides-spec.md) (`work_overrides.yml` v2 の形式・適用順・題名の芯の規則・レビュー資料・`redirects.yml`・再投入方式)。

---

## 8. アイコン類 (将来)

**現状**: テーマ `themes/ts-bunko` は外部依存ゼロ (設計 §4。Google Fonts 不使用)、`style.css` 10KB、JS は `reader.js` のみ。アイコンは無い。

**仕様 (後回し)**: テーマ内のインライン SVG スプライト (`themes/ts-bunko/assets/icons.svg`、`<use>`) か CSS mask。置き場: 作品カードの題名前 (ブック型)・話ページのパンくず・索引の各項目 (種別)。外部 CDN・アイコンフォントは使わない。`currentColor` でダークモード追随。配備は deploy.sh のテーマ同期で済む。
**未決**: 所有者が素材を用意するか、既製 (MIT ライセンスのアイコン集) か。
**規模**: 半日。T.28 (最後)。

---

## 9. 「このサイトについて」は所有者がリライト

**現状**: `content/pages/about.html` (git) → `wp ts sync-pages` (`commands.php:522-549`。ハッシュで冪等、WP 側の手動編集は保護、`--overwrite-manual` で上書き)。本番 ID 8736、最終更新 2026-08-30 11:02 (= sync 時刻、手編集なし)。現文面「八重洲メディアリサーチのもとで長く運営されたのち、管理が途絶え」は所有者の指摘どおり**独立後の文庫運営委員会に触れていない**。glossary「少年少女文庫」の定義と author-outreach §5 も「別の管理者へ移管」と書いており、所有者の説明 (文庫運営委員会による運営) で訂正が要る。行 17 に「72 時間以内」。

**仕様**: 所有者の原稿を正典 `content/pages/about.html` に入れる (経路 A/B は本書 Q)。ファイル冒頭に `<!-- owner-authored: YYYY-MM-DD -->` を付け、以後セッションは所有者文責の部分を書き換えない (72 時間の差し替えだけは本書 11 の一括改訂で行い、事前に所有者へ知らせる)。glossary の「少年少女文庫」定義と author-outreach §5 の「移管先の管理者」を「文庫運営委員会」に訂正 (所有者確認後)。

**`wp ts export-pages` の仕様 (新設)**: WP の固定ページ (`page`、publish) を `content/pages/<post_name>.html` に書き戻す。1 行目は `<!-- title: <post_title> -->`、以降は `post_content` を無加工で (ブロックコメントも含む)。既存ファイルの 2 行目に `<!-- owner-authored: YYYY-MM-DD -->` があればその行を保ち、`sync-pages` はこのコメント行を本文から除いて投入する。`--slug=<name>` で 1 枚だけ、`--dry-run` で差分の行数だけ表示。受け入れ条件は往復 (export → `sync-pages`) が `skipped` になること。

**未決**: 経路 A/B / 運営者名義の表記 (takano32 のまま・運営委員会名義の併記) / 運営委員会の正式名称。

---

## 10. CGI スクリプトがそのまま落ちてくる — 当時のパスワード等の埋め込み懸念 ★最重要

**現状 (実測 2026-10-26。対象 = リポジトリ = GitHub Pages で配信中の全 18,013 ファイル)**:

| 検査 | 結果 |
|---|---|
| 先頭 2 バイトが `#!` のファイル | **0** |
| Perl 構文 (`use strict` / `sub x {` / `$ENV{` / `print "Content-type` / `open(IN` 等) を含むファイル | **0** |
| 名前に `.cgi` を含むファイル | **9,019**。中身: HTML (CGI が出力した画面の保存) **8,966** / 画像 46 (captcha PNG 37・GIF 9) / 不明バイナリ 7 (`~ezpe/cgi-bin/noteky/` の 7 本) / **サーバのエラー出力 2** |
| エラー出力 2 本 | `www2.sts.co.jp/~yaji/cgi-bin/minibbs2.cgi` (72B「script produced no output」+ Windows のフルパス) と `~yaji/blog/mt-atom.cgi/weblog/blog_id=1` (1,002B Perl の @INC エラー + `/home/<ユーザ>/public_html/…`)。**サーバ側のパスを晒す**が秘密値は無い |
| `password` / `pass` / `パスワード` を含む行 | 掲示板の削除キー**入力欄** (空の `<input type=password>`) と本文の語句のみ |
| hidden 入力で名前が pass/key/admin、値が非空 | `name=KEY` 10 桁 (投稿番号、`special/03summer/` 2 + 写し 2) / `name=key` 14 桁 (タイムスタンプ、`ts.raa0121.info/2ndbbs/` 5) / **`name=admin` 6 文字** (矢治さんの掲示板 `~bbs/cgi-bin/bbs.*`・`yajibbs.*`・`www2.sts.co.jp/~yaji/cgi-bin/hihyou.cgi` の 5 ファイル)。後者は英単語 1 語のモード指定で、同じ語がリポジトリ内 465 ファイルに通常の単語として出現、直前の説明も「削除キー欄にマスターキーを入力」= 鍵は入力式 → **秘密ではない** (値は本書に書かない) |
| `.htaccess` / `.htpasswd` / 設定ファイル | **0** |
| 実行ファイル・アーカイブ | `special/03summer/zetsubou.1〜3` + `~yays/library/special/03summer/zetsubou.1〜3` + `.bat` = NScripter 製ゲーム「絶望未満」の **Windows EXE を 3 分割**したもの (2.1MB × 3、`COPY /B` で結合)。LZH 5 本 (fKiss ビューア Mac 版 + C ソース・KiSS ローダ・KiSS データ・Excel 2 本 (bc-cafe のカードリスト)) |
| git 履歴 | 削除済みの `.pl` / `.pm` / `.cgi` は 0 (履歴にも配信中と同じものしか無い)。public リポジトリ・`raw-original` タグあり |

「そのまま落ちてくる」の正体: GitHub Pages は `.cgi` (と拡張子なし) を **`application/octet-stream`** で返す (実測: `~ts/kansou/bbs@log_marie.cgi` → 200 octet-stream) → ブラウザがダウンロードする。本番 WP には `.cgi` を 1 つも置いていない (environment §4.4)。本館からの導線は、固定ページ `/archive/` の区画リンク → 原本内のリンク、書誌カード「無改変の原本を読む」(これ自体は `.html`) → 原本内のリンク、そして **`.cgi` への直リンクを含む投稿 5 件** (`ts_doc` のにせ乙女パラダイス 3 頁 → `~ezpe/cgi-bin/resbbs4y/resbbs4.cgi` 等、`ts_work` の `shining_heaven-main` と `koromogae1` (原本そのまま表示の 2 話) → `kansou.cgi`)。いずれも中身は HTML の保存画面。

**結論**: **CGI のソースコードの流出・パスワードの露出は確認できなかった。** 所有者が心配した状況ではない。ただし「落ちてくる」体験そのものは本物で、資料館として直す価値がある。

**仕様**:

1. **恒久化**: `scripts/wp/secret_scan.py` を `make check` に組み込み、新規収蔵のたびに 0 件を確認する (規則は §10.5)。
2. **小さな対処 (即時)**: エラー出力 2 ファイルをリポジトリから削除 (史料価値なし、サーバパス露出)。EXE 分割 (2 系統 3 本 + bat) の配信を続けるかは所有者判断 (ゲーム本体は企画の作品だが、実行ファイルの配信はリスク。原本は git 履歴に残る)。
3. **ダウンロード問題の解消**: Phase 5.2 `annex_inject.py` (ビルド注入) で `.cgi` 保存画面を HTML として配信できる形にする。案: 各 `x.cgi` を `x.cgi/index.html` に置き換え + `<base href>` 注入 (GitHub Pages は `/x.cgi` を `/x.cgi/` に 301 して `text/html` で返す。**要検証**)。Phase 6 の `/boards/` ができれば本館側の感想板リンクは `.cgi` に頼らなくなる。
4. 文書: environment §4.3 の「前段 nginx」訂正は既記載。本書の結果を `data-inventory.md` §7 に 1 行で反映。

### 10.5 `secret_scan.py` の仕様

- 対象: `git ls-files` の全ファイルから `scripts/` `mu-plugins/` `themes/` `docs/` `catalog/` `takedown/` `content/` `.github/` を除いたもの (= 配信される原本)。`--root <dir>` で別ツリーを指せる (テスト用)。
- 検査 (いずれも**値は出力しない**):
  ① 先頭 2 バイトが `#!`
  ② Perl/シェル構文: 行頭の `use strict` / `use CGI` / `require "…"` / `sub 名 {` / `$ENV{` / `print "Content-type` / `open(IN|OUT|FH|DAT|LOG`
  ③ `<input type=hidden …>` で name が `pass|pw|passwd|password|admin|adminpass|delkey|secret` かつ value が非空。ただし value が数字のみ (投稿番号・タイムスタンプ)、または英小文字 1 語 (6 字以下) でリポジトリ内の他ファイルに通常語として 50 回以上現れるものは除外
  ④ `password=` `passwd=` `pass=` の直後に 4 字以上の値 (HTML 属性 `type=password` は除外)
  ⑤ ファイル名が `.htaccess` `.htpasswd` `wp-config*` `*.env` `*.pem` `id_rsa*`
- 出力: `catalog/reports/secret_scan.json` = `{"scanned": N, "hits": [{"rule": "③", "path": "…", "line": 46}], "generated": "<ISO 8601>"}` と標準出力の要約。path と行番号だけで、行の内容は書かない。
- 終了コード: hits が 0 なら 0、1 件以上なら 1 (`make check` が止まる)。検証済みの許容 (例: モード語の hidden 値) は `scripts/wp/secret_scan_allow.yml` に `path:行` と理由を書いて除外する。
- 2026-10-26 の全数走査 (§10 の表) がこの規則の初回結果で hits 0。

**未決**: EXE 配信の可否。
**規模**: 走査スクリプト 半日 / 削除 2 ファイル即時 / 5.2 は Phase 5。

---

## 11. 72 時間の明記をやめ、専用フォーム → 10 日後に自動取り下げ → 運営委員がオーバーライド

**現状**:

- 公開中の `/takedown/` (`content/pages/takedown.html:2`) は「申し出の確認から 72 時間以内」と約束し、窓口は **GitHub の Issue** (`:6`)。しかし **リポジトリの Issue は無効** (`gh repo view`) で、**窓口が機能していない**。さらに設計 v1.0 §5・removal-runbook §0 は「GitHub Issue 窓口は不採用 (依頼者の身元と依頼内容を公開の場に晒す)」と決めており、**ページが設計に反している**。runbook の言う「メール + フォーム転送」のフォームは存在せず、メールアドレスも書かれていない。
- 72 時間の記述は **14 ファイル・33 箇所** (§12 の表)。公開面 3 枚 (about `:17` / takedown `:2` / removed `:17`)、作者への連絡文の下書き (author-outreach `:85`) を含む。
- 自動化の下地: WP-Cron は閲覧駆動 (`DISABLE_WP_CRON` 未定義) で、前段キャッシュが PHP を呼ばない閲覧では動かず、低トラフィック時に遅れる。サーバ crontab は使える。`wp_mail` は `sendmail_path` 設定済み (送達未検証)。REST 200 / xmlrpc 403 / wp-login 403 (CloudSecure)。`/removed/` は**手書きの静的ページ** (生成器は無い。runbook ⑤「catalog から再生成」は未実装)。
- `apply-takedown` は denylist.yml (git) を唯一の入力にしており、WP 側で状態が変わる仕組みは無い。

**仕様 (決定案) — 状態機械**:

```
[フォーム送信] → unconfirmed ─(確認メールのリンク)→ received (T0) ─(T0+10日, cron)→ applied
                     │ 7 日で未確認 → expired                    ↑           │
                     │                      運営委員: approved (即時適用) ─┘   reinstated (復帰)
                     │                      運営委員: rejected (自動適用を止める。理由は内部メモ)
```

1. **窓口**: `/takedown/` に mu-plugin のフォーム (`admin-post.php` の nopriv アクション。外部プラグインなし)。項目: 当時のペンネーム / 対象 URL (複数可) / 連絡先メール / 立場 (作者本人・権利継承者・その他) / 本人確認の手がかり (任意) / 自由記述 / 名前の掲示可否。スパム対策: nonce・honeypot・送信所要時間・IP 単位 rate limit (transient)・**確認メール必須**。GitHub Issue の記載は削除し、メール窓口を併記。
2. **保存**: CPT `ts_takedown_request` (`public=false`、管理画面は運営委員のみ)。連絡先は WP の DB にだけ置き (dump は私有ストレージ)、リポジトリには書かない (`takedown/README.md` の約束を維持)。`wp ts reset` の対象に**入れない**。
3. **自動適用** (`applied`): 対象投稿 (+ 子) を `private` + `_ts_visibility=withdrawn`、`ts_takedown_urls` 更新、`/removed/` は **WP の withdrawn 投稿から動的生成**に切り替える (手書きをやめる)。
4. **運営委員の操作**: 申請の編集画面にボタン 3 つ (承認して即時適用 / 却下 / 復帰)。却下は自動適用を止めるだけで記録は残す。
5. **通知** (`wp_mail`): 受付 → 依頼者に確認リンク / 確認 → 運営委員全員 (「YYYY-MM-DD に自動適用」) / T0+7 日 → 運営委員にリマインド / 適用・却下 → 依頼者 + 運営委員。
6. **cron**: サーバ crontab `*/10 * * * * cd ~/novels.xwp.jp/public_html && wp cron event run --due-now` と wp-config `DISABLE_WP_CRON` (👤。`wp-config.php` は公開ルート外にあり、手で入れる)。
7. **denylist.yml との整合**: WP で `applied` になった申請は `wp ts export-takedowns` が denylist 形式で出力 → セッションが追記してコミット → 別館のビルド除外 (5.2) に伝播。**denylist.yml = git に残す確定記録、申請 CPT = 運用の原本**。手動エントリ (TD-0001〜0014) は従来どおり denylist → `apply-takedown`。
8. **別館の自動化 (任意)**: `deploy-pages.yml` が本館の公開 REST `GET /wp-json/ts/v1/withdrawn` (source_path の一覧だけ。依頼者情報は含めない) を取り込んで除外すれば、人手なしで 10 日後に別館からも消える。
9. **文言**: 公開面から「72 時間」を全部外す。案: 「フォームからのお申し出は、確認メールの到達から **10 日後に自動的に非公開**になります。運営委員が内容を確認し、早めることや、本人確認が取れない場合に保留することがあります」。作者連絡文 (author-outreach §3) も同じ趣旨に。removal-runbook は v1 に改訂 (SLA 節 → 自動適用の仕組み、①②③ の時間指定を撤去)。

**既存の裁定との衝突と解消**: 設計 v1.0 §7-4・v1.3・v1.4・v1.5 が維持してきた「72h SLA の宣言」→ **v1.6 で撤回** (安全弁の正体は「人の即応」ではなく「自動適用 + 人のオーバーライド」になる)。「GitHub Issue 窓口は不採用」は**維持** (現ページの記載が誤り)。「本人確認は緩く」は維持しつつ、確認メールで最低限の実在性だけ担保する。

**未決**: 「10 日」を公開文面に書いてよいか / 確認メールなしでも受理するか (なりすまし取り下げのリスクを所有者が受容するか) / 依頼者情報の保持期間 / 運営委員の通知先メール / 却下時に依頼者へ返事するか。
**規模**: mu-plugin 400〜600 行 + テンプレート + メール文 + crontab + 文書改訂 33 箇所 + 削除リハーサル (台帳 7.2) での実流し。2〜3 日。
**実装仕様**: [`takedown-form-spec.md`](takedown-form-spec.md) (入力項目と検証・データ・状態遷移表・通知・cron・`/removed/` の生成規則・`export-takedowns` の形式・裁定待ちパラメータの既定値・検証手順)。

---

## 12. 「72 時間」の記述一覧 (撤去・改訂の対象)

| ファイル | 箇所 | 扱い |
|---|---|---|
| `content/pages/takedown.html` | `:2` (+ `:6` GitHub Issue) | **差し替え** (本書 11 の文言) |
| `content/pages/about.html` | `:17` | 差し替え (所有者リライトと同時) |
| `content/pages/removed.html` | `:17` | 差し替え (動的生成化で消える) |
| `docs/author-outreach.md` | `:85` (連絡文) `:98` `:104` | 差し替え。**T.33 の発送前に必ず** |
| `docs/removal-runbook.md` | `:1 :14 :15 :58 :72 :120 :167` (7) | v1 に改訂 |
| `takedown/denylist.yml` | `:11` (コメント) | 改訂 |
| `takedown/README.md` | `:4` | 改訂 |
| `docs/glossary.md` | `:124` (denylist 四層) | 改訂 |
| `docs/README.md` | `:31 :58` | 改訂 (本書で `:58` は v1.6 の言い方に) |
| `docs/wp-implementation-tasks.md` | `:64 :1182` (現行ルール) / `:127 :1037` (履歴) | 現行ルールの 2 箇所を改訂。履歴は残す |
| `docs/wordpress-library-design.md` | `:259 :265 :300 :572 :634 :657 :664` (7。すべて v1.0〜v1.5 の章) | 追記ルールにより書き換えず、**v1.6 章で失効を宣言** |
| `docs/cp4-review.md` | `:111` (未了 ⑪) / `:143` (履歴) | `:111` に v1.6 への注記。`:143` は履歴 |
| `mu-plugins/ts-library/includes/commands.php` | `:748` (コメント) | 修正 |
| `TASKS.md` / `docs/worklog.md` | T.6.2 の履歴 | 残す |
| 本書・設計書 v1.6 章・`takedown-form-spec.md`・`visibility-roles-spec.md`・`docs/README.md` の分担表 | 撤回の記録・説明として「72 時間」に触れる | 残す (公開面の文言ではない) |

---

## 13. 所有者に確認すること (まとめ)

1. **アカウント** (Q): 「運営委員」(閲覧 + 申請処理) か「運営委員 (編集可)」か。ログイン URL の伝え方。2 要素認証。
2. **オススメの環** (1): 一覧ページ `/index/osusume/` 自体も消すか (既定: URL は残す)。
3. **Marie** (3): 申し出の時期 (年) / `/removed/` に名前を出してよいか / 他作者と同居する `toukou01/02.html` と感想板 30 ファイルの扱い / git 履歴からの除去までするか (既定: しない)。
4. **オーナー作品集** (4): ラベル文言 / 作者ページの説明文 / 犬瓦ハム太 名義との結び付け。
5. **にせ乙女パラダイス** (5): ラベルに年を付けるか / URL (`prehistory-`) を変えるか / 管理人名義 (妹氏との共同) を書くか (既定: 書かない)。
6. **重複** (6): 類似度 0.8〜0.95 を自動にするか / 旧目録 (1997–98) 版を史料として残すか / 大きな改稿 (類似度 0.24 等) を版違いとみなすか。
7. **クラスタ** (7): レビューは所有者が行うか、一次案の承認だけにするか。
8. **アイコン** (8): 素材は所有者提供か既製か。
9. **about** (9): 原稿の渡し方 (A/B) / 運営者名義の表記 / 運営委員会の正式名称。経緯の記述は glossary・author-outreach にも反映してよいか。
10. **CGI** (10): ゲーム EXE (分割 3 本 + bat、2 系統) の配信を続けるか。
11. **取り下げ** (11): 「10 日」を公開文面に書くか / 確認メール必須でよいか / 依頼者情報の保持期間 / 通知先 / 却下時の返事。

---

## 14. 文書・台帳への反映 (本書と同時に行った追記)

- 設計書に **v1.6 改訂章**を追記 (方針レベルの決定 6 点と失効の宣言。本書へリンク)。
- 実装仕様を性質ごとに 4 本に分けて新設: [`visibility-roles-spec.md`](visibility-roles-spec.md) (§2) / [`takedown-form-spec.md`](takedown-form-spec.md) (§11) / [`versions-spec.md`](versions-spec.md) (§6) / [`cluster-overrides-spec.md`](cluster-overrides-spec.md) (§7)。判定基準・閾値・状態遷移・権限・ファイル形式・コマンド仕様はそちらが正典。
- `docs/README.md` のドキュメント地図に本書・4 本の仕様書・`worklog.md` を追加し、「決定済み」の 72 時間の行を v1.6 の言い方に、「TASKS.md と docs の分担」を追記。
- `TASKS.md`: T.15.1 を「レビュー受領」に更新、**T.16〜T.28** を追加 (1 大項目 = 1 成果、着手順。各行は やること + 完了条件 + 参照先 のみ)。完了済み T.1〜T.14 の確定記録を [`worklog.md`](worklog.md) に写した。
- 台帳 (`wp-implementation-tasks.md`) への展開 (受け入れ条件・実測値) は T.22.2 で行う。2026-08-31 のユーザ裁定 (索引と検索窓を右サイドバーに常設、表示文言「索引」「開いた」) が台帳に無かったので 4.4 に追記した。
