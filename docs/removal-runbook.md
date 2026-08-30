# 削除ランブック v0 — 受領から 72 時間で四層 + 掲示

対象読者: 運営者本人と、依頼された作業を代行する実行セッション。
単一情報源は [`takedown/denylist.yml`](../takedown/denylist.yml)。
本番の .htaccess 構造・キャッシュ層・危険操作ガードは [`environment.md`](environment.md)、
削除対象がリポジトリのどこにあるかは [`data-inventory.md`](data-inventory.md)、
用語 (本館/別館/旧館 ほか) は [`glossary.md`](glossary.md) を参照
(**この 3 語は内部用語**で、読者に見せる文面では使わない)。
**このランブックは Phase 7.2 の削除リハーサル (テスト作品 1 件で実流し) の結果で実測更新する。**
v0 の時点では ①〜⑤ の手順は設計上のものであり、実行時間は未実測。

## SLA

- **受領から 72 時間以内に ①WP draft 化 ②410 ③別館 (Pages) からの除外 を完了**する (公開面から消える)。
- ④git 履歴除去 と ⑤/removed/ 掲示は 72h を過ぎてもよいが、⑤は 1 週間以内を目安とする。
- 依頼者への一次返信 (受領しました + 想定日時) は**当日中**。本人確認は緩く運用する
  (当時のサイト・なろう・pixiv 等、作品と結びつくアカウントからの連絡で足りる)。

## 0. 受領 (T+0)

1. 窓口は `/takedown/` (メール + フォーム転送) のみ。GitHub Issue は使わない
   (依頼者の身元と依頼内容を公開の場に晒さないため)。
2. 依頼の実物 (メール本文・フォーム控え) は**私有ストレージに保管**。リポジトリには入れない。
3. `takedown/denylist.yml` にエントリを追加する。この時点では `status: received`、
   `applied` は全て `null`。

```yaml
- id: TD-2026-0001
  target: { kind: work, value: <work_slug> }      # or episode / annex_path / author
  reason: <1 行の内部向け理由>
  reason_class: author_request
  requested_at: 2026-09-01
  received_via: takedown_form
  status: received
  applied:
    wp_draft: null
    htaccess_410: null
    annex_excluded: null
    git_history_purged: null
    removed_page_listed: null
  public_note: 「作品名」(作者名さん) — 作者の申し出により削除
```

4. **対象の同定**を先に済ませる。work 単位の依頼でも、消える URL は
   work + その全 episode + それらの alias パス + 別館の原パスまで波及する。

```sh
# 対象 work に属する episode の source_path と alias を全部出す (開発機)
python3 - "$WORK_SLUG" <<'EOF'
import json, sys
slug = sys.argv[1]
works = {json.loads(l)['work_slug']: json.loads(l) for l in open('catalog/works.jsonl')}
w = works[slug]
print('\n'.join(w['episode_ids']))
EOF
```

## ① WP を draft 化 (T+72h まで)

```sh
ssh novels
cd ~/novels.xwp.jp/public_html
wp db export ~/dumps/pre-takedown-$(date +%Y%m%d-%H%M).sql   # 復旧点 (必須)
wp ts apply-takedown --list=../catalog/../takedown/denylist.yml --dry-run
wp ts apply-takedown --list=.../takedown/denylist.yml
wp post list --post_type=ts_work --post_status=draft --format=count
```

- draft 化であって delete ではない (復帰の可能性と、再収蔵事故の防止のため投稿自体は残す)。
- 実行後、対象 URL が 404/410 のどちらを返すかを②の前に確認しておく (draft のみだと 404)。

## ② 本番 .htaccess に 410 を追記 (T+72h まで)

**ガード (進行台帳の普遍ルール 5・7・9)**:
- 必ず `cp .htaccess .htaccess.bak-YYYYMMDD` を先に取る。
- ローカルで生成するのは**マーカーコメント間の追記ブロックだけ**。
  既存 `.htaccess` の全文をローカルに保存・コミットしない
  (CloudSecure のログイン URL 変更値が入っているため)。
- 検証 curl には毎回キャッシュバスター (`?_cb=$RANDOM`) を付ける。
  反映されないように見えたら**まず nginx キャッシュを疑う**。.htaccess を直し続けない。

```sh
# 開発機: 追記ブロックだけを生成
python3 scripts/wp/gen_htaccess.py --denylist takedown/denylist.yml > /tmp/ts-410-block.conf
scp /tmp/ts-410-block.conf novels:/tmp/

# 本番: バックアップ → マーカー間を差し替え
ssh novels
cd ~/novels.xwp.jp/public_html
cp .htaccess .htaccess.bak-$(date +%Y%m%d)
python3 - <<'EOF'   # マーカー間だけを置換する最小スクリプト (全文は表示しない)
import re, pathlib
p = pathlib.Path('.htaccess'); s = p.read_text()
blk = pathlib.Path('/tmp/ts-410-block.conf').read_text()
new = "# BEGIN ts-takedown\n" + blk + "# END ts-takedown\n"
if "# BEGIN ts-takedown" in s:
    s = re.sub(r"# BEGIN ts-takedown.*?# END ts-takedown\n", new, s, flags=re.S)
else:
    s = s + "\n" + new
p.write_text(s)
EOF

# 検証: 対象は 410、無関係な URL は 200 のまま
curl -sI "https://novels.xwp.jp/novel/200209/19204751/d_upboy06.htm?_cb=$RANDOM" | head -1
curl -sI "https://novels.xwp.jp/about/?_cb=$RANDOM" | head -1     # 200 であること
curl -sI "https://novels.xwp.jp/novel/?_cb=$RANDOM" | grep -i x-robots-tag
```

- 無関係 URL が 200 を返さない / X-Robots-Tag が消えた場合は**即座に .bak を戻す**。
- 生成ブロックの形 (`Redirect gone /path` は本番検証済み):

```apache
# BEGIN ts-takedown
Redirect gone /novel/200209/19204751/d_upboy06.htm
Redirect gone /novel/example-author-example-work/
# END ts-takedown
```

## ③ 別館 (GitHub Pages) 側の除外 (T+72h まで)

- Pages はヘッダを制御できないため 410 は返せない。**ビルド artifact から対象ファイルを落として 404**
  にするのが最大限 (設計 v1.1 の妥協点)。
- `scripts/wp/annex_inject.py` (Phase 5.2) が `denylist.yml` を読んで artifact 生成時に除外する。
  **git の原本は消さない**(これは ④ の判断とは別レイヤ)。

```sh
git add takedown/denylist.yml && git commit -m "takedown: TD-2026-0001 を追加" && git push
# → deploy-pages.yml が走る。デプロイ完了後に確認
curl -sI "https://takano32.github.io/ts-novels/novel/200209/19204751/d_upboy06.htm" | head -1  # 404
```

- `annex_path` の glob 指定を使ったときは、**意図より広く消えていないか**を
  デプロイ前の artifact ディレクトリで数えて確認する。

## ④ git 履歴からの除去 (依頼があったときのみ)

**既定では実施しない。** 依頼者が「配布そのものを止めてほしい」と明示した場合、
または高 PII・法的要求の場合に限る。理由: 履歴の書き換えは全 commit hash を変え、
他の全クローンとの互換を壊す破壊的操作であるため。

```sh
# 事前に必ず: バックアップ clone を私有ストレージに取る
git clone --mirror . /path/to/private/ts-novels-backup-$(date +%Y%m%d).git

pip install git-filter-repo
git filter-repo --invert-paths --path 'novel/200209/19204751/d_upboy06.htm' --force
git push --force origin master
git push --force origin --tags
```

### `raw-original` タグに残る事実 (重要)

このリポジトリには**回収直後の無変換状態を指す `raw-original` タグ**があり、
削除対象ファイルはそのタグの指すツリーにも含まれる。`git filter-repo` の
`--invert-paths` は**タグの指す履歴も書き換える**が、以下の残留経路がある:

1. **他者の既存クローン / fork**。すでに配布された分は回収不能。
2. **GitHub 側のダングリングオブジェクト**。force push 後も一定期間 API/`?w=` 経由で
   到達できることがある。GitHub サポートに GC を依頼するのが確実。
3. **Wayback / Software Heritage 等のアーカイブ**が公開リポジトリを取得している場合。

**対応方針**: 依頼者には ④ の限界を正直に伝える —
「公開面 (WP・Pages) は 72 時間で確実に止める。git 履歴は書き換えるが、
既に配布されたクローンと第三者アーカイブまでは遡及できない」。
この文言は `/takedown/` ページにも常設する (README の「マスクは秘匿でなく礼儀」と同じ姿勢)。
`raw-original` タグを削除して打ち直した場合は、その事実を `denylist.yml` の `notes` に記録する。

## ⑤ `/removed/` に掲載 (1 週間以内)

- 掲載するのは **作者名 + 作品名 + 理由の要約 (1 行) + 削除日**のみ。
  依頼者の身元・依頼文は載せない。作者本人が名前の掲載も望まない場合は
  「作者の申し出により 1 作品を削除 (YYYY-MM)」の形に匿名化する。
- 目的は**再収蔵の防止**。将来の回収作業者 (人間・実行セッション問わず) が
  「なぜここに穴があるのか」を確認できる場所を 1 つにする。

```sh
ssh novels && wp post list --post_type=page --name=removed
# /removed/ の本文を catalog 側から再生成して更新 (手動編集はしない)
```

## 完了処理

1. `denylist.yml` の `applied` 各層に実施日を入れ、`status: applied` → 実地確認後 `verified`。
2. 👤 **nginx キャッシュのクリアを依頼**する (パネル操作のためセッションからは実行不可)。
   クリア前は 410 が反映されて見えないことがある。
3. 依頼者へ完了連絡 (④の限界を含めて)。
4. 次回 publish 時に `wp ts verify` の件数が減っていることを確認する。

## チェックリスト (印刷用)

- [ ] 当日中に一次返信
- [ ] denylist.yml にエントリ追加 (public に書いてはいけない情報を書いていないか)
- [ ] `wp db export` で復旧点
- [ ] ① `wp ts apply-takedown` → draft を件数で確認
- [ ] ② `.htaccess.bak-YYYYMMDD` → マーカーブロック差し替え → 410 と無関係 URL 200 を curl
- [ ] ③ denylist を push → Pages デプロイ後に 404 を curl
- [ ] ④ (依頼があれば) バックアップ clone → filter-repo → force push → 限界を通知
- [ ] ⑤ `/removed/` 掲載
- [ ] 👤 キャッシュクリア依頼
- [ ] denylist.yml を `verified` に更新してコミット
