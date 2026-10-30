# S.118 読者と運営委員への応答 (410・404・帯)

- 種類: 仕様
- 状態: 有効
- 出典: docs/visibility-roles-spec.md の「読者と運営委員への応答」
- 関連: T.23.3.2, T.23.5, S.221

## 読者と運営委員への応答

| 状態 | 非ログイン / `ts_view_hidden` 無し | 運営委員 (`ts_view_hidden`) |
|---|---|---|
| publish | 200 | 200 |
| withdrawn | **410** + 案内ページ + `nocache_headers()` | 200 + 帯 |
| superseded | **404** (通常の 404 と同じ。新版へは転送しない。S.221) + `nocache_headers()` | 200 + 帯 |
| 取り下げた画像 (`/assets/annex-img/…`) | 410 (`.htaccess`、S.119) | 同じ (区別しない) |

- **解決手順** (`template_redirect`、優先度 0): メインクエリが 404 か private 投稿を指すとき、要求パス (`/novel/<work>/[<episode>/]`) から `get_page_by_path($path, OBJECT, 'ts_work')` で投稿を引く (private は本クエリに乗らないため自前で引く)。該当なしなら通常の 404。
- **410 の案内ページ** (`themes/ts-bunko/ts-410.php`): 題名は出さず「この作品は作者の申し出により掲載していません。」+ [取り下げ済みの一覧](`/removed/`) へのリンク。`status_header(410)`。
- **帯** (`ts_bunko_hidden_banner()`、`single.php` の h1 直後):
  - withdrawn: 「取り下げ済み — 運営委員にのみ表示しています (S.235 / 申請 12)」 (申請の後の数は申請の投稿 ID)
  - superseded: 「旧版 (2002 年の掲載) — 運営委員にのみ表示しています → [新版へ]」
- `get_the_title()` が private に付ける「非公開: 」は `private_title_format` フィルタで `%s` にする (帯で代替)。
- 新版の書誌カード (`render.php` `ts_bunko_biblio_card`) には、運営委員としてログインしているとき (`ts_view_hidden`) だけ「旧版あり (YYYY 年の掲載・運営委員のみ)」のリンクを出す。非ログインの読者には出さない。

## 実装の記録 (2026-10-30、T.23.3.2)

`mu-plugins/ts-library/includes/visibility.php` と `themes/ts-bunko/ts-410.php`。表と解決手順のとおりに作り、次の点は表に無いので決めて作った。

- **`ts_view_hidden` を持たないログイン中の人にも読者と同じ応答**を返す。WP の既定では editor なども `read_private_posts` で private を読めるが、表の「`ts_view_hidden` 無し」に合わせて 410 / 404 にする。T.23.1 の試験ユーザ `ts-probe-t231` は editor なので、T.23.5 で帯を確かめる前に `wp user set-role ts-probe-t231 ts_committee` で運営委員にする。
- 運営委員が見る非公開の作品のページにも `nocache_headers()` を付ける (410・404 と同じく前段のキャッシュに載せない)。
- 移行前の取り下げ (`draft` でメタ無し) は、読者にも運営委員にも 404 (WordPress の既定)。`wp ts apply-takedown` を流し直すと private + withdrawn になり、表のとおりになる (S.121)。
- 運営委員が非公開の作品 (親) を見ているときだけ、目次と話ナビに private の話も並べる (公開中の作品の目次には旧版の話を混ぜない)。
- 410 の案内ページの見出しと `<title>` は「掲載していない作品」。
- 旧版の帯の年は投稿日の年、「新版へ」は `_ts_superseded_by` を `_ts_episode_id` (話と単発作品) か `_ts_work_slug` (連載の親) で引いた投稿の URL。新版の書誌カードの「旧版あり」は T.25.4.2 で作る (旧版のメタを import が書くのも T.25.4.1)。
- 取り下げた話は本文を作らない論理削除 (S.76) なので、運営委員の閲覧でも本文は空で、題名・書誌・帯だけが見える。
- 試験: 手元の WordPress (docker) で、読者に取り下げ 410 (案内あり・`cache-control: no-cache` 系・題名なし)、旧版 404、無関係 200、運営委員に 200 + 帯 (「非公開:」なし)、editor に 410 / 404。判定と帯の文言は `scripts/wp/ts_library_test.php` でも試す。
