# S.66 検収は WP-CLI と curl で行う

- 種類: 手順
- 状態: 有効
- 出典: docs/environment.md の「検収は WP-CLI + curl で行う」
- 関連: なし, T.40.2

## 検収は WP-CLI + curl で行う(管理画面は使えない)

- **WP 管理画面はセッションから開けない。** ログイン URL は CloudSecure により変更されており、
  その値の転記は禁止(S.10 のログイン URL の変更値を転記しない決まり)。ブラウザ操作は人間のタスク (高野さんの作業)。
- サーバのアクセスログ・エラーログもユーザからは読めない(`~/novels.xwp.jp/log/` は空)。
- したがって**検収の手段は 2 つだけ**:

| 手段 | 見るもの | 例 |
|---|---|---|
| **WP-CLI**(サーバ内) | DB の中身が正しいか | `wp post list --post_type=ts_work --format=count` / `wp post meta get <ID> _ts_source_path` / `wp term list ts_author --format=count` |
| **curl**(外から) | 公開面が正しいか | `curl -s -o /dev/null -w '%{http_code}' "https://novels.xwp.jp/novel/johdan-senotomeseera/?_cb=$RANDOM"` / `curl -sI … \| grep -i x-robots-tag` |

curl は**必ずキャッシュバスター付き**(S.62)。ステータスコード・`X-Robots-Tag`・`<title>` の 3 点で
「投入できているか」「noindex の境界が正しいか」を判定する。
