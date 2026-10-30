# S.76 削除: WP の投稿を draft にする

- 種類: 手順
- 状態: 一部失効 (S.121 で上書き。draft → private)
- 出典: docs/removal-runbook.md の「WP を draft 化」
- 関連: T.21.3, S.117, S.121

## WP を draft 化

```sh
scripts/wp/novels-ssh
cd ~/novels.xwp.jp/public_html
wp db export ~/dumps/pre-takedown-$(date +%Y%m%d-%H%M).sql   # 復旧点 (必須)
wp ts apply-takedown --list=../catalog/../takedown/denylist.yml --dry-run
wp ts apply-takedown --list=.../takedown/denylist.yml
wp post list --post_type=ts_work --post_status=draft --format=count
```

- draft 化であって delete ではない (復帰の可能性と、再収蔵事故の防止のため投稿自体は残す)。
- 実行後、対象 URL が 404/410 のどちらを返すかを本番 .htaccess の 410 (S.77) の前に確認しておく (draft のみだと 404)。
