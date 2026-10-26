# S.77 削除 ②: 本番 .htaccess に 410 を書く

- 種類: 手順
- 状態: 一部失効 (S.119 で上書き。WP の URL の 410 は WordPress が返す)
- 出典: docs/removal-runbook.md の「② 本番 .htaccess に 410 を追記」
- 関連: S.64, S.119

## ② 本番 .htaccess に 410 を追記 (T+72h まで)

**ガード (S.10 の 5・7・9)**:
- 必ず `cp .htaccess .htaccess.bak-YYYYMMDD` を先に取る。
- ローカルで生成するのは**マーカーコメント間の追記ブロックだけ**。
  既存 `.htaccess` の全文をローカルに保存・コミットしない
  (CloudSecure のログイン URL 変更値が入っているため)。
- 検証 curl には毎回キャッシュバスター (`?_cb=$RANDOM`) を付ける。
  反映されないように見えたら**まず nginx キャッシュを疑う**。.htaccess を直し続けない。

```sh
# 開発機: 追記ブロックだけを生成
python3 scripts/wp/htaccess_block.py            # 生成されるブロックを表示 (dry-run)
python3 scripts/wp/htaccess_block.py --install  # 原本 .orig 保存 → 差し替え → curl 検証 → NG なら自動復元
rsync -e scripts/wp/novels-ssh /tmp/ts-410-block.conf novels:/tmp/

# 本番: バックアップ → マーカー間を差し替え
scripts/wp/novels-ssh
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
