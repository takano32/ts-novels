# やり直しランブック — レビューで NG だったときの再走手順

本プロジェクトは**全工程を再走できる**ように作ってある(単一真実源 = `catalog/`、
WordPress の DB は使い捨ての派生ビュー)。このランブックは「構築後のレビューで NG が出て、
判断を変えてやり直す」ときに、**どこを直せば何が変わるか**と**再走のコマンド**をまとめる。
用語は [glossary.md](glossary.md)。

## 0. 大原則

- **手で直してよいのは「入力」だけ**: 判断ファイル(下表)・スクリプト・設計書。
  生成物(`catalog/*.jsonl`・`catalog/QA.md`・`bodies/`・WP の DB)を手で直しても
  次の再生成で消える
- **すべての判断はリポジトリに記録されている**。git 履歴が再走の完全な記録
- 再走は何度でも安全: catalog 生成は決定的(同じ入力なら 2 回流してバイト同一)、
  WP への投入は冪等(episode_id で upsert)

## 1. 「何を直したいか」→「どこを変えるか」対応表

| レビューで NG だったもの | 直す場所 | 直した後の再走 |
|---|---|---|
| 作者・作品の slug(URL) | `catalog/slug_overrides.yml`(該当行を書き換え `status: confirmed`) | §2-A |
| 作品のまとめ方(シリーズ分割・併合) | `catalog/work_overrides.yml` | §2-A |
| 話の作者・帰属の誤り | `catalog/episode_overrides.yml` | §2-A |
| ジャンル・種別・キーワードの正規化 | `catalog/genre_map.yml` / `type_map.yml` / `keyword_map.yml` | §2-A |
| 本文の変換品質(欠落・整形) | `scripts/wp/body_convert.py`(不変量を壊さないこと。`make verify` = html5lib 検算を必ず流す) | §2-B |
| 目録のパース(拾い漏れ・誤読) | `scripts/wp/catalog_build.py` ほか builder | §2-A |
| 特定作品を非公開に | `takedown/denylist.yml` | `wp ts apply-takedown` のみ |
| 方針そのもの(掲載範囲・URL 設計 等) | `docs/wordpress-library-design.md` に改訂章を**追記**(v1.5, v1.6, …。書き換えず追記が慣例) → 台帳へ展開 | 影響範囲による |

## 2. 再走コマンド

### A. catalog を作り直して WP に反映(判断ファイル・builder を直した場合)

```sh
# 開発機
make catalog          # 全再生成 (約20秒)。QA は catalog/QA.md
make verify           # html5lib による本文の独立検算
git add -A && git commit   # ← 再走の記録。必ずコミットしてから配備する

# 本番 (ssh novels)
wp db export ~/dumps/pre-rerun-$(date +%Y%m%d).sql   # 復旧点
wp ts import ...      # 冪等 upsert。既存話は更新される
wp ts verify
# 👤 パネルからキャッシュクリア
```

**注意: slug を変えた場合は upsert では旧 URL の投稿が残る**。作者・作品 slug の変更は
「別の投稿になる」ため、部分再走ではなく §C の全消し再構築を使うこと(公開解禁前なら
迷わず全消し。解禁後なら旧 URL からのリダイレクトを .htaccess に足す)。

### B. 本文だけ作り直した場合

```sh
python3 scripts/wp/body_convert.py   # bodies/ を再生成 (git 管理外)
make verify
# 以後 §A の本番手順 (import は --bodies)
```

### C. WP を全消しして最初から(いちばん確実なやり直し)

**公開解禁前はこれが既定**。DB は使い捨てなので迷ったら全消しする。

```sh
# 本番 (ssh novels)
wp db export ~/dumps/pre-reset-$(date +%Y%m%d).sql
wp ts reset --yes     # ts_* の全投稿・term・meta を削除 (mu-plugin のコマンド。WP本体設定は残る)
wp ts sync-terms ... && wp ts import ... && wp ts apply-takedown && wp ts verify
```

`wp ts reset` が未実装の場合の代替: パネルから WordPress を初期化 → mu-plugins を rsync
→ import(初期化すると CloudSecure 等の再設定が要るので reset 実装を優先)。

### D. 何がいま本番に載っているかを知る

`wp ts import` は投入時の **catalog の git コミットハッシュ**を `wp option get ts_catalog_commit`
に記録する(台帳 2.2 の仕様)。レビューで NG を報告するときは
「`ts_catalog_commit` の値 + NG の内容」を書いてもらえれば、その時点の入力を
`git checkout <hash>` で完全に再現できる。

## 3. レビューの受け付け方(👤 が NG を出すとき)

1 件ずつでよいので「**URL(または episode_id)+ 何が違うか**」の形で。例:
- 「/authors/hirariman/ の slug は hirariiman が良い」→ slug_overrides 1 行+§C
- 「この話の作者が違う」→ episode_overrides 1 行+§A
- 「この作品は非公開に」→ denylist 1 行+apply-takedown

まとまった NG は `docs/review-feedback/` に日付ファイルで放り込んでもらえれば、
実装セッションが 1 件ずつ上表に振り分けて処理する。
