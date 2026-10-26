# 運営委員ロールと可視性の仕様 v1 (2026-10-26)

**位置づけ**: 設計書 v1.6 の決定 1 (取り下げ・旧版は `private` + 運営委員ロール、410/301 は WordPress が返す) の実装仕様。
根拠と実測は [`owner-feedback-2026-10.md`](owner-feedback-2026-10.md) §2、実装タスクは `TASKS.md` の T.23
(T.25 版違い・T.26 取り下げフォームがこの仕様を使う)。**ロールの権限と可視性の振る舞いはこの文書が正典。**
用語 (本館/別館/旧館) は内部用語で、読者に見せる文言には使わない。

## 1. ロール

| ロール (slug) | 表示名 | 権限 (capability) | 用途 |
|---|---|---|---|
| `ts_committee` | 運営委員 | `read` / `read_private_posts` / `ts_view_hidden` / `ts_manage_takedown` | 取り下げ済み・旧版の閲覧、取り下げ申請の確認・承認・却下・復帰 |
| `ts_committee_editor` | 運営委員 (編集可) | 上の全部 + `edit_pages` `edit_published_pages` `edit_others_pages` `publish_pages` + `edit_ts_docs` `edit_published_ts_docs` `edit_others_ts_docs` `publish_ts_docs` | 固定ページ (`/about/` 等) と解題 (`ts_doc`) の編集。所有者向け |
| `administrator` | 管理者 | WP 既定 | 運営者 (takano32)。配備・import・ユーザ発行 |

- 運営委員に **作品 (`ts_work`) の編集権限は付けない**。catalog が正典で、WP 上の編集は次の import で skip され verify が NG になり、reset で消えるため (owner-feedback §Q)。
- `ts_doc` は `register_post_type` の `capability_type` を `ts_doc`、`map_meta_cap` を true にする (現状は `post` 相当で、Editor 系に編集を許すと `ts_work` と区別できない)。`wp ts sync-docs` は WP-CLI なので権限の影響を受けない。
- `ts_view_hidden` と `ts_manage_takedown` は本プラグイン独自の cap。administrator にも付ける。
- **`wp ts role-sync`**: 上表のとおりに `add_role` / `add_cap` / `remove_cap` を冪等に適用する。出力は `role-sync: created=N updated=N unchanged=N`。2 回目は created=0 / updated=0 が受け入れ条件。
- アカウント運用: 発行は administrator が WP-CLI で (`wp user create <login> <mail> --role=ts_committee`)。ログイン URL は CloudSecure で変更済みの値を**文書・チャット・コミットに転記しない** (台帳 普遍ルール 7) — 口頭か別経路で伝える。パスワードは生成した強いもの。検証用の試験ユーザは作業後に `wp user delete <id> --yes` (投稿を持たないので再割当は不要)。

## 2. 可視性モデル

- 読者に見えるかどうかは **`post_status`** で表す: `publish` = 見える / `private` = 運営委員のみ。**`draft` は使わない** (現行の取り下げ 18 件 + Marie 氏分は移行で `private` にする)。
- 理由は post meta **`_ts_visibility`** で表す:

| `_ts_visibility` | 意味 | 付随メタ | 設定する主体 |
|---|---|---|---|
| `withdrawn` | 取り下げ (作者の申し出・運営判断) | `_ts_takedown_id` = `TD-YYYY-NNNN` (denylist) または `req:<申請の post ID>` (フォーム) | `wp ts apply-takedown` / 取り下げ申請の自動適用・承認 |
| `superseded` | 旧版 (同じ作品の新しい版がある) | `_ts_superseded_by` = 新版の `episode_id` (親 work なら新版の `work_slug`) | `wp ts import` (catalog の `superseded_by`。[`versions-spec.md`](versions-spec.md)) |
| (無し) | 公開 | — | `wp ts import` |

- **優先順位: withdrawn > superseded > publish。** import は `withdrawn` が立っている投稿の `post_status` に触れない。`superseded` は catalog が決めるので import が付け外しする (無くなれば publish に戻す)。
- 波及: `withdrawn` は親を止めたら子孫 (話) 全部 (`commands.php` `descendants()` の現行ロジック)。`superseded` は話単位。全話が superseded の作品は親も `private` + `superseded`。
- 件数・索引・検索結果・RSS・将来の sitemap は **publish だけ**を数える (現行実装 `indexes.php` `ts_bunko_work_counts` / `render.php` `ts_bunko_source_map` の `post_status = 'publish'` を維持)。運営委員がログイン中は WP の既定で `private` が一覧・検索に混ざる — 帯 (§3) で識別できるので許容する。

## 3. 読者と運営委員への応答

| 状態 | 非ログイン / `ts_view_hidden` 無し | 運営委員 (`ts_view_hidden`) |
|---|---|---|
| publish | 200 | 200 |
| withdrawn | **410** + 案内ページ + `nocache_headers()` | 200 + 帯 |
| superseded | **301** → 新版の URL + `nocache_headers()` | 200 + 帯 |
| 取り下げた画像 (`/assets/annex-img/…`) | 410 (`.htaccess`、§4) | 同じ (区別しない) |

- **解決手順** (`template_redirect`、優先度 0): メインクエリが 404 か private 投稿を指すとき、要求パス (`/novel/<work>/[<episode>/]`) から `get_page_by_path($path, OBJECT, 'ts_work')` で投稿を引く (private は本クエリに乗らないため自前で引く)。該当なしなら通常の 404。
- **410 の案内ページ** (`themes/ts-bunko/ts-410.php`): 題名は出さず「この作品は作者の申し出により掲載していません。」+ [取り下げ済みの一覧](`/removed/`) へのリンク。`status_header(410)`。
- **帯** (`ts_bunko_hidden_banner()`、`single.php` の h1 直後):
  - withdrawn: 「取り下げ済み — 運営委員にのみ表示しています (TD-2026-0014 / 申請 #12)」
  - superseded: 「旧版 (2002 年の掲載) — 読者には新版を表示しています → [新版へ]」
- `get_the_title()` が private に付ける「非公開: 」は `private_title_format` フィルタで `%s` にする (帯で代替)。
- 新版の書誌カード (`render.php` `ts_bunko_biblio_card`) には運営委員にだけ「旧版あり (YYYY 年の掲載・運営委員のみ)」のリンクを出す。

## 4. `.htaccess` の ts-library ブロック

| 行の種類 | 従来 (v0) | v1 以後 |
|---|---|---|
| 恒久 noindex (`/boards/` `/dojo/`) の `X-Robots-Tag` | あり | あり (不変) |
| WordPress の URL の `Redirect gone` | あり (`wp ts export-takedown-urls` から) | **書かない** (WordPress が 410 を返す) |
| 画像資産の 410 (`RedirectMatch gone "^/assets/annex-img/<prefix>"`) | あり (denylist `kind: annex_path`) | あり (不変) |
| 旧 URL → 新 URL の `Redirect 301` | なし | あり ([`cluster-overrides-spec.md`](cluster-overrides-spec.md) §6 の `catalog/redirects.yml` から) |

- 生成は `scripts/wp/htaccess_block.py` のまま。`--install` 時の自動検証に「取り下げ URL が 410 (WordPress 由来)」「redirects の先頭 3 本が 301」を加える。ssh は `scripts/wp/novels-ssh` 経由に直す (現状は `ssh novels` 直書きで `~/.ssh/config` に依存。T.13.3 の積み残し)。
- **代替方式** (§5 の実測で WordPress 方式が成り立たないとき): 取り下げ URL ごとに
  `RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_ [NC]` + `RewriteRule ^novel/<slug>(/|$) - [G]` を生成する。Cookie 名の有無だけで通し、本人性の判定は WordPress に任せる (偽の Cookie を付けても WordPress 側で 404/410 になる)。

## 5. 実測項目: nginx キャッシュとログイン (実装前に必ず)

1. 復旧点 (`wp db export`) → 試験ユーザ (`--role=editor`、強いパスワード) を作る。
2. 👤 ブラウザでログイン (ログイン URL は転記禁止)。
3. 公開中の作品 1 本と取り下げ済み 1 本について、(a) 非ログイン・バスターなし (b) 非ログイン・`?_cb=` (c) ログイン・バスターなし (d) ログイン・`?_cb=` の 4 通りを取り、ステータス・`cache-control` の有無・本文の帯を記録する。
4. 判定: (c) がログイン後の正しい応答 (200 + 帯) なら「ログイン Cookie 付きはキャッシュを素通り」→ WordPress 方式で進む。(c) が 410 やキャッシュ由来の古い本文なら nginx が Cookie を無視している → §4 の代替方式に切り替える。
5. 結果を [`environment.md`](environment.md) §3 に表で追記し、試験ユーザを削除する。

## 6. `wp ts apply-takedown` の変更

- 対象 (+子孫) を `private` + `_ts_visibility=withdrawn` + `_ts_takedown_id=<TD>` + `_ts_public_note` (denylist の `public_note`。`/removed/` の動的生成が読む) にする。既に `private`/`draft` の投稿もメタを揃える (自己修復)。
- `status: reinstated` のエントリ: `_ts_visibility` と `_ts_takedown_id` を消し、`superseded` が無ければ `publish` に戻す。
- option `ts_takedown_urls` は監査用に引き続き記録するが、`.htaccess` の生成には使わない。
- dry-run の出力: 「private 化 N / 復帰 M / 変更なし K」。

## 7. 検証 (すべて `?_cb=$RANDOM` 付き)

| 確認 | 期待 |
|---|---|
| 取り下げ済みの作品・話 (3 本) を非ログインで | 410、本文に案内、`cache-control: no-cache` 系 |
| 旧版の話 (3 本) を非ログインで | 301 → 新版、新版は 200 |
| 無関係な作品・`/about/` `/removed/` | 200 |
| `/assets/annex-img/novel/200907/19181513/01123002.jpg` (TD-0012) | 410 |
| 運営委員でログインして取り下げ済み作品 | 200 + 帯。`非公開:` の接頭辞が無い |
| `wp post list --post_type=ts_work --post_status=draft --format=count` | 0 |
| `wp ts verify` | OK |
| 別館 (GitHub Pages) | 取り下げ分は 404、他は 200 (従来どおり) |

## 8. 更新する文書

[`removal-runbook.md`](removal-runbook.md) を v1 に (① draft → private、② `.htaccess` は画像資産のみ、運営委員の閲覧、復帰の手順)、
[`glossary.md`](glossary.md) の「denylist 四層」の①②、[`environment.md`](environment.md) §8 (apply-takedown 後は private の件数を確認)、
[`../takedown/README.md`](../takedown/README.md) の層の表、[`publish-runbook.md`](publish-runbook.md) の配備手順に `wp ts role-sync`。
