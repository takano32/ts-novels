# 公開ランブック v0 — catalog 再生成から本番反映まで

対象読者: 運営者本人と、実行セッション。
publish = **catalog(単一真実源)を作り直して、派生ビューである本番 WP を作り直す**こと。
WP DB は使い捨てなので、迷ったら「catalog を直して再インポート」が常に正しい。
WP 管理画面での手動編集は禁止 (修正は catalog 側の overrides ファイルで)。

**v0 の注記**: Phase 1〜2 のスクリプト (`catalog_build.py` 以外) と mu-plugin は未実装のため、
以下の `make catalog` / `wp ts *` / `deploy.sh` は**予定のインタフェース**である。
実装が進むたびに、実際に流したコマンドと所要時間でこのファイルを更新すること。

## 前提

| 役割 | 場所 |
|---|---|
| 開発機 | このリポジトリ (python3.11、pip 可)。catalog / bodies / mu-plugins / theme を生成 |
| 本館 (本番) | `ssh novels` = Xserver for WordPress。`~/novels.xwp.jp/` 直下に wp-config.php、公開ルートは `~/novels.xwp.jp/public_html/` |
| 別館 | GitHub Pages (現行ミラー、18,006 ファイル)。`.cgi` を含む **9,022 ファイル**(フルパス基準。basename 基準なら 9,021) は nginx 403 のため本番には置けない = 恒久併存 |

用語 (本館/別館/旧館 ほか) は [`glossary.md`](glossary.md) を参照。
**本館・別館・旧館は内部用語**で、読者に見せる文面では使わない。

サーバ内で使えるもの: WP-CLI 2.8.1 / mysql / rsync / git / curl / python3.6 (PHP 8.0.30)。

本番環境の詳細 (ディレクトリ構成・ツール版・キャッシュ層・`.cgi` 403 制約・.htaccess の
ブロック構造・危険操作チェックリスト) は [`environment.md`](environment.md) を参照。

## 所要時間の目安 (v0 は未実測。実測値で置き換えること)

| 段 | 目安 |
|---|---|
| `make catalog` (開発機) | **44 秒** (3,844 話 + 本文 MD 変換。2026-08-30 実測 `time make catalog`) |
| rsync | 初回は bodies/ と画像で数分、以後は差分のみ |
| `wp ts import` 全量 | 未実測 (パイロット 100 件で実測してから全量に進む) |
| 反映確認 | nginx キャッシュのため、👤 キャッシュクリアまで見えないことがある |

## 手順

### 1. 開発機: catalog を再生成する

```sh
cd ~/GitHub/ts-novels
make catalog          # catalog_build → terms_build → authors_build → work_builder → body_convert
```

生成物と、その場で見る受け入れ条件:

| 生成物 | 見るもの |
|---|---|
| `catalog/episodes.jsonl` | **3,844 行** (正規目録 2,887 + 旧目録 97 + 目録外収蔵 859 + 文庫未掲載 1)。パース失敗 0 |
| `catalog/authors.json` | **作者 342 名**。全 episode の author が解決 (作者不詳 37 を除く 3,807 話)。※旧記述の「≈399」は表示名の異なり数であって人数ではない |
| `catalog/terms.json` | genre 196 (中核 30) / type 165 (中核 30) / keyword 1,032 (中核 74) / **world 14** / **corpus 5**。corpus は全 episode 分の term がそろっていること (terms_build の自己検査) |
| `catalog/works.jsonl` | orphan 0 (全 episode がどれかの work に属す) |
| `bodies/*.md` + `catalog/convert_report.jsonl` | 無損失証明の合格率 ≥85% (実測 **99.95% = 3,642 / 3,644**)。`bodies/` は git 管理外なので `git diff` には出ない |
| `catalog/QA.md` | 上記の集計。**publish 前に必ず目視する** |

```sh
git diff --stat catalog/ bodies/     # 意図しない大量差分が出ていないか
git add catalog bodies && git commit -m "catalog: 再生成 (YYYY-MM-DD)"
```

**catalog に差分が出ないなら publish する必要はない。**

### 2. 開発機 → 本番: 配備

```sh
scripts/wp/deploy.sh -n        # 必ず dry-run を先に。--delete は使わない
scripts/wp/deploy.sh
```

deploy.sh が運ぶもの: `catalog/` `bodies/` `mu-plugins/` `themes/` `assets/annex-img/` `robots.txt`。
宛先 `~/novels.xwp.jp/` 直下には **wp-config.php が居る**ので、`--delete` は禁止。
mu-plugins は `public_html/wp-content/mu-plugins/` へ。

```sh
ssh novels 'ls -la ~/novels.xwp.jp/public_html/wp-content/mu-plugins/'
curl -s "https://novels.xwp.jp/robots.txt?_cb=$RANDOM" | head -20
```

### 3. 本番: 復旧点を取る

```sh
ssh novels
mkdir -p ~/dumps
cd ~/novels.xwp.jp/public_html
wp db export ~/dumps/pre-import-$(date +%Y%m%d-%H%M).sql
ls -la ~/dumps | tail -3
```

**この段より先はここを戻せば復旧できる。** ダンプは私有ストレージへ退避し、
公開リポジトリには入れない (定常運用 8.1)。

### 4. 本番: インポート

```sh
wp ts sync-terms --authors=../catalog/authors.json --terms=../catalog/terms.json
wp ts import --works=../catalog/works.jsonl --episodes=../catalog/episodes.jsonl --dry-run
wp ts import --works=../catalog/works.jsonl --episodes=../catalog/episodes.jsonl [--bodies=../bodies/]
wp ts apply-takedown --list=../takedown/denylist.yml
```

`import` は created / updated / skipped を集計出力する。
**冪等性の確認 = もう一度そのまま流して created=0 / updated=0**:

```sh
wp ts import --works=../catalog/works.jsonl --episodes=../catalog/episodes.jsonl
# → created=0 updated=0 skipped=N であること。そうでなければインポータのバグ
```

### 5. 本番: verify

```sh
wp ts verify
```

見るもの: 件数照合 (**episodes 3,844 / works 1,463 / 作者 342**) / orphan 0 / taxonomy 被覆率 /
特殊エントリ内訳 (正規目録分 28 件 = 画像作品 21・外部 1・アンカー 5 ほか。全 corpus では 100 件) /
冪等性。

curl 側 (**毎回キャッシュバスターを付ける**):

```sh
for u in / /novel/ /authors/ /index/ /about/ /takedown/; do
  printf '%s ' "$u"; curl -s -o /dev/null -w '%{http_code}\n' "https://novels.xwp.jp$u?_cb=$RANDOM"
done
curl -sI "https://novels.xwp.jp/novel/?_cb=$RANDOM" | grep -i 'x-robots-tag'   # 解禁(7.4)前は noindex
curl -s "https://novels.xwp.jp/novel/<slug>/?_cb=$RANDOM" | grep -o '<title>.*</title>'
```

### 6. 👤 nginx キャッシュのクリアを依頼

wpX のキャッシュ削除はパネル操作のためセッションからは実行できない。
**「反映されない」と見えたら、まずキャッシュを疑う。** .htaccess や mu-plugin を
直し続けるループに入らないこと。キャッシュバスター付き URL で正しい応答が返るなら、
それは反映済みでキャッシュだけが古い。

### 7. 別館 (GitHub Pages) 側

catalog に `path_map.json` の変化があるとき (= WP の URL が変わったとき) だけ再デプロイする。

```sh
wp ts export-pathmap > catalog/path_map.json    # 本番で生成 → 開発機へ持ち帰る
git add catalog/path_map.json && git commit -m "catalog: path_map 更新" && git push
# → deploy-pages.yml が Pages を再デプロイ (バナー・noindex・mailto 除去はビルド注入: Phase 5.2)
```

## 失敗したときの戻し方

| 症状 | 対処 |
|---|---|
| import が途中で落ちた / 変なデータが入った | `wp db import ~/dumps/pre-import-*.sql` |
| .htaccess を変えたら無関係 URL が 500/403 | `cp .htaccess.bak-YYYYMMDD .htaccess` |
| 反映されない | ①キャッシュバスター付き curl で確認 ②👤 キャッシュクリア ③それでも駄目なら調査 |
| catalog が壊れた | git で戻して `make catalog` を再実行 (catalog は生成物であり、手で直さない) |

## publish のたびに残すもの

- `wp db export` を私有ストレージへ (8.1)
- `catalog/QA.md` の数値 (件数・合格率・needs_review 数・provenance 被覆率)
- このランブックの**実測所要時間の更新**

## チェックリスト

- [ ] `make catalog` → `catalog/QA.md` を目視
- [ ] catalog の git diff が意図どおり → コミット
- [ ] `deploy.sh -n` → `deploy.sh` (`--delete` を使っていないこと)
- [ ] `wp db export` で復旧点
- [ ] `sync-terms` → `import --dry-run` → `import` → `apply-takedown`
- [ ] もう一度 `import` して created=0 / updated=0 (冪等性)
- [ ] `wp ts verify` + 主要 URL の curl (バスター付き)
- [ ] 👤 キャッシュクリア依頼
- [ ] ダンプを私有ストレージへ
