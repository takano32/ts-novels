# S.77 削除: 本番 .htaccess に 410 を書く

- 種類: 手順
- 状態: 有効
- 出典: docs/removal-runbook.md の「本番 .htaccess に 410 を追記」
- 関連: T.21.3, T.23.6, S.64, S.67, S.119, S.121

## 本番 .htaccess に 410 を書く (画像だけ)

2026-10-30 (T.23.6) に S.119 の `scripts/wp/htaccess_block.py` に合わせて書き直した。それまでの版は、取り下げた WP の URL ごとに `Redirect gone` を手で組んだブロックに書いていた。

- WP の URL (`/novel/…`) の 410 は WordPress が返す (S.76・S.118)。`.htaccess` には書かない。運営委員にはログイン中だけ見せるため、`.htaccess` で塞ぐと見られなくなる。
- `.htaccess` に書くのは、WordPress を通らない資産 (`/assets/annex-img/…` の画像) の 410 だけ。denylist の `kind: annex_path` から作る (いまは S.233・S.234 の写真の 2 行)。画像は運営委員にも 410 (区別しない。S.118)。
- 書き換えるのは `# BEGIN ts-library` 〜 `# END ts-library` のマーカーブロックの中だけ (恒久 noindex の `/boards/` `/dojo/` もこのブロック。S.64)。

**流すとき**: denylist に `kind: annex_path` のエントリを足した・変えたとき。作品・話・作者だけの取り下げでは `.htaccess` は変わらないので流さなくてよい。

(高野さんの作業) ホームで流す。`htaccess_block.py` は手元の `takedown/denylist.yml` からブロックを組み、`scripts/wp/novels-ssh` で本番に入れる。`--install` の検証は `wp ts apply-takedown` が記録した取り下げ URL を使うので、S.76 の後に流す。

```sh
cd ~/GitHub/ts-novels && git pull --ff-only

# ブロックの表示 (サーバに接続しない)
python3 scripts/wp/htaccess_block.py
#   期待: /novel/ の Redirect gone が無く、annex_path ごとに RedirectMatch gone "^/assets/annex-img/…" の行

# 適用と検証
python3 scripts/wp/htaccess_block.py --install
#   期待: [1/4] バックアップ → [2/4] 差し替え → [3/4] 無関係 URL 200 (X-Robots-Tag なし) / /dojo/ noindex あり /
#         取り下げ (WordPress) の先頭 3 件が 410 / 取り下げ (画像) が 410 → [4/4] OK
```

- `--install` がすること (S.119 の実装の記録): 原本 `.htaccess.orig` (初回だけ) と直前の `.htaccess.bak-<日時>` をサーバに残し、マーカーブロックだけを差し替える。既存の `.htaccess` の中身は表示も保存もしない (CloudSecure のログイン URL の変更値が入っているため。S.10)。検証が NG なら自動で `.bak-<日時>` に戻す。取り下げ URL が取れないときは何も変えずに止まる。
- 検証 NG で戻ったとき、手で戻すとき: `scripts/wp/novels-ssh 'cd ~/novels.xwp.jp/public_html && ls -t .htaccess.bak-* | head -3'` で直前の控えを見て、`cp .htaccess.bak-<日時> .htaccess` で戻す。
- 反映されないように見えたら、まず nginx のキャッシュを疑う (S.62)。`.htaccess` を直し続けない。

生成されるブロックの形:

```apache
# BEGIN ts-library (scripts/wp/htaccess_block.py が管理。中を手で編集しない)
# 恒久 noindex 層: /boards/ /dojo/ (第三者ハンドルが載る層。設計 S.42)
(X-Robots-Tag の設定)
# 取り下げた画像などの 410 (2 件。takedown/denylist.yml の kind: annex_path から)。
# WordPress の URL の 410 は WordPress が返すのでここには書かない (specs/S.119.md)
RedirectMatch gone "^/assets/annex-img/novel/200907/19181513/011230"
RedirectMatch gone "^/assets/annex-img/novel/201009/16215412/011230"
# END ts-library
```
