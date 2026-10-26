# S.102 取り下げ作品を運営委員権限で閲覧できるようにする

- 種類: 設計
- 状態: 有効
- 出典: docs/owner-feedback-2026-10.md の「取り下げ作品を運営委員権限で閲覧できるように」
- 関連: T.23, S.116〜S.123

## 取り下げ作品を運営委員権限で閲覧できるように

**現状**:

- 取り下げ = 投稿を **draft** 化 (`commands.php:797`。S.76) + 本番 `.htaccess` に `Redirect gone` (`scripts/wp/htaccess_block.py:33-53`。URL は WP の option `ts_takedown_urls` から。本番 18 URL。S.77)。
- draft は編集権限のある人だけがプレビュー URL で見られる形で、**閲覧専用の権限では見えない**。
- `.htaccess` の 410 は **Apache 段で返り PHP に届かない**ので、ログインしていても 410。
- ロールは WP 既定のみ。Editor は `read_private_posts=1` だが `edit_posts=1` (作品を編集できてしまう) (本番 `wp eval` 実測)。`ts_work` は `capability_type=post`・`map_meta_cap=1` → private の閲覧権限は `read_private_posts` (実測)。
- 原本アーカイブ (GitHub Pages) にはログインの概念が無い。

**仕様 (決定案)**:

1. **ロール** `ts_committee`「運営委員」: `read` + `read_private_posts` + 目印 `ts_view_hidden` (+ 11 の `ts_manage_takedown`)。編集系は付けない。
   `ts_committee_editor`「運営委員 (編集可)」: 上に `edit_pages` 系と `ts_doc` の編集を足す (`ts_doc` の capability_type を専用にする改修が要る)。登録は mu-plugin (`register`) で、`wp ts` に `role-sync` を足して冪等に。
2. **非表示の実装を draft → private に変更**。可視性メタ `_ts_visibility` ∈ {`withdrawn` (取り下げ), `superseded` (旧版。S.106)}。子 (話) へ波及させる現行ロジック (`commands.php:899-905`) はそのまま。
3. **読者 (非ログイン) への応答は WP が返す**: `template_redirect` で URL から投稿を引き (private は本クエリに乗らず 404 になるため `get_page_by_path` 相当で自前解決)、`withdrawn` → `status_header(410)` + `nocache_headers()` + 短い案内 (「作者の申し出により掲載していません」+ `/removed/` へ)、`superseded` → 新版へ **301**。ログイン中の運営委員 → 本文を表示し、冒頭に帯「取り下げ済み (運営委員にのみ表示)」。
4. `get_the_title()` が private に付ける「非公開: 」接頭辞 (`private_title_format`) は帯に置き換える (filter)。
5. **`.htaccess` の ts-library ブロックから WP の URL の `Redirect gone` を撤去**し、WP を経由しない `/assets/annex-img/` の 410 (`htaccess_block.py:56-80`) だけ残す。
6. 原本アーカイブ: 取り下げ分は T.35.2 のビルド除外 (404)。運営委員が旧内容を見たいときは **git (public リポジトリ) で読める**ことを明記。旧版 (S.106) は原本アーカイブでは全部そのまま (原本レイヤ)。
7. `apply-takedown` は `reinstated` で publish に戻す動作を持つ (現状は止めるだけ)。

**既存の裁定との衝突と解消**:

| 既存 | 衝突 | 解消 (S.43) |
|---|---|---|
| S.35「真の 410 は `.htaccess` の `Redirect gone`」 | Apache 段ではログイン判定ができない | 410 を WP 側に移す。mu-plugins は管理画面から無効化できない (S.14〜S.19) ので「PHP が止まると 410 が消える」リスクは許容。代替案 = `.htaccess` で `wordpress_logged_in_` Cookie の無いときだけ `[G]` を返す RewriteCond (Cookie 名だけで通し、真の判定は WP が行う) も可能。実測で選ぶ |
| 「`wp ts import` は post_status に触れない」(`commands.php` 冒頭) | 旧版 (S.106) は catalog 由来で private にしたい | catalog 由来の可視性 (`superseded_by`) は import が設定する、に改める。優先順位は **取り下げ > 旧版 > 公開** |
| S.62 nginx キャッシュ | 読者向け 410/301 がキャッシュされ、ログイン中の運営委員にもキャッシュが返る恐れ | **要実測**: ログイン Cookie 付きリクエストがキャッシュを素通りするか (`_probe/` と試験ユーザで確認)。素通りしないなら代替案 (上) に切り替える |

**規模**: mu-plugin 150〜250 行 (ロール・visibility・template_redirect・title filter) + `apply-takedown` 改修 + `htaccess_block.py` の 410 を資産限定に + S.75〜S.81 の改訂。実測 半日。
**実装仕様**: S.116〜S.123 (ロールの権限・可視性メタ・410/301 の振る舞い・`.htaccess` ブロック・実測手順・検証表)。
