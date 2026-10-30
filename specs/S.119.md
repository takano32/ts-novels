# S.119 .htaccess の ts-library ブロック

- 種類: 仕様
- 状態: 有効
- 出典: docs/visibility-roles-spec.md の「.htaccess の ts-library ブロック」
- 関連: T.23.2, T.23.3.4, S.64, S.77, S.221

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
