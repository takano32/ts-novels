# S.118 読者と運営委員への応答 (410・301・帯)

- 種類: 仕様
- 状態: 有効
- 出典: docs/visibility-roles-spec.md の「3. 読者と運営委員への応答」
- 関連: T.23.3.2

## 3. 読者と運営委員への応答

| 状態 | 非ログイン / `ts_view_hidden` 無し | 運営委員 (`ts_view_hidden`) |
|---|---|---|
| publish | 200 | 200 |
| withdrawn | **410** + 案内ページ + `nocache_headers()` | 200 + 帯 |
| superseded | **301** → 新版の URL + `nocache_headers()` | 200 + 帯 |
| 取り下げた画像 (`/assets/annex-img/…`) | 410 (`.htaccess`、S.119) | 同じ (区別しない) |

- **解決手順** (`template_redirect`、優先度 0): メインクエリが 404 か private 投稿を指すとき、要求パス (`/novel/<work>/[<episode>/]`) から `get_page_by_path($path, OBJECT, 'ts_work')` で投稿を引く (private は本クエリに乗らないため自前で引く)。該当なしなら通常の 404。
- **410 の案内ページ** (`themes/ts-bunko/ts-410.php`): 題名は出さず「この作品は作者の申し出により掲載していません。」+ [取り下げ済みの一覧](`/removed/`) へのリンク。`status_header(410)`。
- **帯** (`ts_bunko_hidden_banner()`、`single.php` の h1 直後):
  - withdrawn: 「取り下げ済み — 運営委員にのみ表示しています (TD-2026-0014 / 申請 #12)」
  - superseded: 「旧版 (2002 年の掲載) — 読者には新版を表示しています → [新版へ]」
- `get_the_title()` が private に付ける「非公開: 」は `private_title_format` フィルタで `%s` にする (帯で代替)。
- 新版の書誌カード (`render.php` `ts_bunko_biblio_card`) には運営委員にだけ「旧版あり (YYYY 年の掲載・運営委員のみ)」のリンクを出す。
