# S.119 .htaccess の ts-library ブロック

- 種類: 仕様
- 状態: 有効
- 出典: docs/visibility-roles-spec.md の「.htaccess の ts-library ブロック」
- 関連: T.23.2, T.23.3.4, T.23.4, S.64, S.77, S.221

## `.htaccess` の ts-library ブロック

| 行の種類 | 従来 | 改訂後 |
|---|---|---|
| 恒久 noindex (`/boards/` `/dojo/`) の `X-Robots-Tag` | あり | あり (不変) |
| WordPress の URL の `Redirect gone` | あり (`wp ts export-takedown-urls` から) | **書かない** (WordPress が 410 を返す) |
| 画像資産の 410 (`RedirectMatch gone "^/assets/annex-img/<prefix>"`) | あり (denylist `kind: annex_path`) | あり (不変) |
| 旧 URL → 新 URL の転送 | なし | **なし** (S.151 は失効。301 は使わない。S.221) |

- 生成は `scripts/wp/htaccess_block.py` のまま。`--install` 時の自動検証に「取り下げ URL が 410 (WordPress 由来)」を加える。ssh は `scripts/wp/novels-ssh` 経由に直す (現状は `ssh novels` 直書きで `~/.ssh/config` に依存。T.13.3 の積み残し)。
- **代替方式** (S.120 の実測で WordPress 方式が成り立たないとき): 取り下げ URL ごとに
  `RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_ [NC]` + `RewriteRule ^novel/<slug>(/|$) - [G]` を生成する。Cookie 名の有無だけで通し、本人性の判定は WordPress に任せる (偽の Cookie を付けても WordPress 側で 404/410 になる)。

2026-10-30: S.120 の実測 (S.62) でログイン Cookie 付きの要求はキャッシュを素通りすると確かめたので、WordPress が 410 を返す方式で進める (T.23.2)。

## 実装の記録 (2026-10-30、T.23.3.4)

`scripts/wp/htaccess_block.py` を改訂後の表のとおりにした。WordPress の URL の `Redirect gone` も転送の行も書かず、恒久 noindex と画像資産 (`/assets/annex-img/`) の 410 だけを書く。

- ブロックの組み立て (引数なしの表示) は**サーバに接続しない** (denylist の `kind: annex_path` だけから作る)。いまの denylist では画像の 410 が 2 行 (S.233・S.234) で、`/novel/` の gone 行は無い。
- `--install` は、書き換えの前に `wp ts export-takedown-urls` で取り下げ URL を取る (取れなければ何も変えずに止まる)。書き換えの後の検証は、無関係 URL が 200・`/dojo/` に noindex・取り下げ URL の先頭 3 件が 410 (WordPress 由来)・annex_path ごとに配備される実在の画像 1 点が 410。取り下げ URL が 0 件のときも NG にする (apply-takedown を流し忘れた疑い)。NG なら直前の `.bak` に戻す。
- ssh は前から `scripts/wp/novels-ssh` 経由になっている (T.13.3 の積み残しは解消済み)。
