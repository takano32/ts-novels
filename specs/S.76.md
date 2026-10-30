# S.76 削除: WP の投稿を draft にする

- 種類: 手順
- 状態: 一部失効 (S.121 で上書き。draft → private)
- 出典: docs/removal-runbook.md の「WP を draft 化」
- 関連: T.18.3, T.21.3, S.73, S.117, S.121, S.215

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

## 本文データを作るときに飛ばす論理削除 (2026-10-30 の高野さんの決定)

取り下げた話の本文は、WP に入れてから止めるのではなく、**本文データを作る段で飛ばす**。開発機の `bodies/` に `.md` が残っていても (`body_convert.py` は古い `.md` を消さない。S.58)、WP に本文が入らない。

- `scripts/wp/payload_build.py` が `takedown/denylist.yml` を読み、status が reinstated でない対象の話の payload を作らない。`payloads/` に前回の payload があれば消す。`payloads/_meta.json` にはその話を `takedown` と書く。
- 対象の範囲は `wp ts apply-takedown` (`mu-plugins/ts-library/includes/commands.php` の takedown_targets) と同じになるように catalog から解決する。kind: work は `catalog/works.jsonl` のその作品の全話、author はその作者の作品の全話、episode は source_path (アンカー分割話は "path#anchor") の一致、annex_path は source_path の前方一致 (値の末尾が `*` のとき) か完全一致。大文字小文字は区別しない (WP 側の MySQL の比較に合わせる)。annex_path の末尾以外に glob 記号があると WP 側と範囲が食い違うので、`payload_build.py` は止まる。
- 飛ばした話数は標準出力と `catalog/reports/payload_build.json` (takedown_skipped・takedown_entries・takedown_episodes) に出る。無損失検査とアドレス残存検査は飛ばした話を除いて従来どおり。
- `wp ts import` は `_meta.json` が `takedown` の話を、サーバに前回の payload が残っていても読まず、本文を空で投入する (`_ts_body_status` は `takedown`)。投稿そのものは作るので件数・メタの被覆を見る `wp ts verify` は変わらず、非公開化はこの S の `wp ts apply-takedown` が行う。全消し再構築 (S.73) では reset → sync-terms → import → apply-takedown の順で、import 直後から apply-takedown までの間も本文は入っていない。
- `scripts/wp/deploy.sh` は `payloads/` を `--delete` つきで rsync する (サーバに前回の payload を残さない)。
- 2026-10-30 の実行: 43 話を飛ばした (本文の `.md` があったのは 42 話。前回の payload 42 本を消した)。作品の取り下げ (S.222〜S.232) で 15 話、Marie 氏の作者単位の取り下げ (S.235) で 28 話。写真の annex_path (S.233・S.234) は投稿の source_path に当たらないので 0 話 (画像の 410 は S.77)。
