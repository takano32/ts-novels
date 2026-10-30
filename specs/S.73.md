# S.73 やり直しの再走コマンド

- 種類: 手順
- 状態: 有効
- 出典: docs/rebuild-runbook.md の「再走コマンド」
- 関連: S.72, S.152, T.40.7

## 再走コマンド

### catalog を作り直して WP に反映(判断ファイル・builder を直した場合)

```sh
# 開発機
make catalog          # 全再生成 (実測 41〜51 秒、2026-10-30。2 コア機で 2 回)。QA は catalog/QA.md
make verify           # html5lib による本文の独立検算
git add -A && git commit   # ← 再走の記録。必ずコミットしてから配備する

# 本番 (scripts/wp/novels-ssh)
wp db export ~/dumps/pre-rerun-$(date +%Y%m%d).sql   # 復旧点
wp ts import ...      # 冪等 upsert。既存話は更新される
wp ts verify
# (高野さんの作業) パネルからキャッシュクリア
```

**注意: slug を変えた場合は upsert では旧 URL の投稿が残る**。作者・作品 slug の変更は
「別の投稿になる」ため、部分再走ではなく下の全消し再構築を使うこと(公開解禁前なら
迷わず全消し。解禁後なら旧 URL からのリダイレクトを .htaccess に足す)。

### 本文だけ作り直した場合

```sh
python3 scripts/wp/body_convert.py   # bodies/ を再生成 (git 管理外)
make verify
# 以後 上の「catalog を作り直して WP に反映」の本番手順 (import は --bodies)
```

### WP を全消しして最初から(いちばん確実なやり直し)

**公開解禁前はこれが既定**。DB は使い捨てなので迷ったら全消しする。

```sh
# 本番 (scripts/wp/novels-ssh)
wp db export ~/dumps/pre-reset-$(date +%Y%m%d).sql
wp ts reset --yes     # ts_* の全投稿・term・meta を削除 (mu-plugin のコマンド。WP本体設定は残る)
wp ts sync-terms ... && wp ts import ... && wp ts apply-takedown && wp ts verify
```

`wp ts reset` が未実装の場合の代替: パネルから WordPress を初期化 → mu-plugins を rsync
→ import(初期化すると CloudSecure 等の再設定が要るので reset 実装を優先)。

**⚠ reset の後処理: eyecatch (featured image) を再設定する。** 2026-10-30 から `wp eval-file scripts/wp/eyecatch_restore.php` が下の手順をまとめて行う (中身の md5 が一致する既存の添付を使い回すので、何度流しても添付は増えない)。
`_thumbnail_id` は catalog からの import では復元されない(投入経路の外にある
`wp media import` + `wp post meta update` で作った状態なので、`wp ts reset` で
ts_* の meta ごと消える)。**reset して import し直したら、S.189 後半の手順を必ず再実行する**:

```sh
# 本番 (scripts/wp/novels-ssh)。対応表は catalog/reports/payload_build.json の eyecatch_refs (15 話)
R=$HOME/novels.xwp.jp/repo
wp media import $R/novel/200012/03175506/eyecatch.gif --porcelain   # → attachment ID
wp media import $R/novel/200104/20050818/eyecatch.jpg --porcelain
wp media import $R/novel/200104/20050818/eyecatch.gif --porcelain
# eyecatch_refs の各 episode_id について:
#   pid=$(wp post list --post_type=ts_work --post_status=any \
#           --meta_key=_ts_episode_id --meta_value="$eid" --format=ids)
#   wp post meta update "$pid" _thumbnail_id "$att"
# 検収: wp post list --post_type=ts_work --meta_key=_thumbnail_id --format=count  → 15
```

**画像は 4 パスあるが実体は 3 つ**(`novel/200012/03175506/eyecatch.gif` と
`novel/robot_triathlon/eyecatch.gif` は md5 一致 `74fb5164…`)。
media import は 3 回でよく、同一実体の 2 パスには同じ attachment ID を割り当てる。
**`wp media import` は毎回新しい attachment を作る**ので、
やり直すときは先に古い attachment を消すか、重複を許容するか決めてから流すこと。

### 何がいま本番に載っているかを知る

`wp ts import` は投入時の **catalog の git コミットハッシュ**を `wp option get ts_catalog_commit`
に記録する(S.182 の仕様)。レビューで NG を報告するときは
「`ts_catalog_commit` の値 + NG の内容」を書いてもらえれば、その時点の入力を
`git checkout <hash>` で完全に再現できる。
