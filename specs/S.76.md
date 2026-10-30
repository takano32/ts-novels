# S.76 削除: WP の投稿を非公開 (private) にする

- 種類: 手順
- 状態: 有効
- 出典: docs/removal-runbook.md の「WP を draft 化」
- 関連: T.18.3, T.21.3, T.23.6, S.73, S.116〜S.118, S.121, S.215

## WP サイトの投稿を非公開にする

2026-10-30 (T.23.6) に S.121 の `wp ts apply-takedown` に合わせて書き直した。それまでの版は投稿を `draft` にし、読者への 410 を `.htaccess` で返していた。

(高野さんの作業) ホームで流す。`wp ts apply-takedown` はサーバ側の repo (`~/novels.xwp.jp/repo`) の `takedown/denylist.yml` を読むので、先に denylist の変更をコミットして push し、`scripts/wp/deploy.sh --apply` で配備しておく (deploy.sh は push 済みのコミットしか運ばない)。

```sh
cd ~/GitHub/ts-novels && git pull --ff-only

# 復旧点 (必須。S.67)
scripts/wp/novels-ssh 'cd ~/novels.xwp.jp/public_html && mkdir -p ~/dumps && wp db export ~/dumps/pre-takedown-$(date +%Y%m%d-%H%M).sql'

# dry-run で変わる投稿を見る
scripts/wp/novels-ssh 'cd ~/novels.xwp.jp/public_html && wp ts apply-takedown --dry-run'
#   期待: 新しいエントリの対象 (作品と子の話) が「(dry-run) private 化: …」の行で並び、
#         最後に Success: apply-takedown: private 化 N / 復帰 0 / 変更なし K (対象 T 件) (dry-run)

# 本番。2 回目は変更なしになること。最後に private と draft の件数
scripts/wp/novels-ssh 'cd ~/novels.xwp.jp/public_html && wp ts apply-takedown && wp ts apply-takedown && wp post list --post_type=ts_work --post_status=private --format=count && wp post list --post_type=ts_work --post_status=draft --format=count'
#   期待: 2 回目は private 化 0 / 復帰 0 / 変更なし T。private は前回の件数 + N、draft は 0
```

- 対象 (+ 子孫の話) を `private` にし、メタ `_ts_visibility=withdrawn`・`_ts_takedown_id` (エントリの id)・`_ts_public_note` (エントリの `public_note`) を揃える (S.117・S.121)。delete ではない (復帰の可能性と、再収蔵事故の防止のため投稿自体は残す)。
- 読者 (ログインなし、または `ts_view_hidden` を持たないログイン) には WordPress が **410** と案内ページ (題名は出さず `/removed/` へのリンク) を返し、`nocache_headers()` で前段のキャッシュに載せない (S.118)。WP の URL の 410 は `.htaccess` に書かない (S.119)。
- 運営委員 (`ts_committee` / `ts_committee_editor`) と管理者は、ログイン中だけ 200 で見え、題名の下に帯「取り下げ済み — 運営委員にのみ表示しています」が出る (S.116・S.118)。本文は下の論理削除で空なので、見えるのは題名・書誌・帯だけ。
- 件数・索引・検索結果は publish だけを数えるので、取り下げた作品は読者の一覧から消える (S.117)。
- 確かめ方: 対象 URL をログインなしで `curl -sI "https://novels.xwp.jp/novel/<work_slug>/?_cb=$RANDOM" | head -1` → 410。前に 200 で見えていた URL は nginx のキャッシュに残っていることがあるので、キャッシュの全消しを頼む (S.81)。
- 復帰: エントリを消さずに `status: reinstated` にして同じ手順を流す。取り下げの印を消して publish に戻す (旧版 (`_ts_superseded_by`) なら private + `superseded`)。dry-run の表示は「復帰」。本文を戻すには、payload を作り直して配備し `wp ts import` を流す (下の論理削除の逆)。
- 本文を消す: 開発機で `scripts/wp/payload_build.py` を流すと対象の話の payload が作られず、配備して `wp ts import --bodies=$HOME/novels.xwp.jp/repo/payloads` を流すと WP の本文が空になる (下の論理削除。流れは公開の手順 S.69 と同じ)。

## 本文データを作るときに飛ばす論理削除 (2026-10-30 の高野さんの決定)

取り下げた話の本文は、WP に入れてから止めるのではなく、**本文データを作る段で飛ばす**。開発機の `bodies/` に `.md` が残っていても (`body_convert.py` は古い `.md` を消さない。S.58)、WP に本文が入らない。

- `scripts/wp/payload_build.py` が `takedown/denylist.yml` を読み、status が reinstated でない対象の話の payload を作らない。`payloads/` に前回の payload があれば消す。`payloads/_meta.json` にはその話を `takedown` と書く。
- 対象の範囲は `wp ts apply-takedown` (`mu-plugins/ts-library/includes/commands.php` の takedown_targets) と同じになるように catalog から解決する。kind: work は `catalog/works.jsonl` のその作品の全話、author はその作者の作品の全話、episode は source_path (アンカー分割話は "path#anchor") の一致、annex_path は source_path の前方一致 (値の末尾が `*` のとき) か完全一致。大文字小文字は区別しない (WP 側の MySQL の比較に合わせる)。annex_path の末尾以外に glob 記号があると WP 側と範囲が食い違うので、`payload_build.py` は止まる。
- 飛ばした話数は標準出力と `catalog/reports/payload_build.json` (takedown_skipped・takedown_entries・takedown_episodes) に出る。無損失検査とアドレス残存検査は飛ばした話を除いて従来どおり。
- `wp ts import` は `_meta.json` が `takedown` の話を、サーバに前回の payload が残っていても読まず、本文を空で投入する (`_ts_body_status` は `takedown`)。投稿そのものは作るので件数・メタの被覆を見る `wp ts verify` は変わらず、非公開化はこの S の `wp ts apply-takedown` が行う。全消し再構築 (S.73) では reset → sync-terms → import → apply-takedown の順で、import 直後から apply-takedown までの間も本文は入っていない。
- `scripts/wp/deploy.sh` は `payloads/` を `--delete` なしで rsync する (`rsync` に `--delete` を使わない決まり。S.10)。サーバに前回の payload が残っても、`wp ts import` は取り下げ対象の話の payload を読まず本文を空で入れるので配信されない。
- 2026-10-30 の実行: 43 話を飛ばした (本文の `.md` があったのは 42 話。前回の payload 42 本を消した)。作品の取り下げ (S.222〜S.232) で 15 話、Marie 氏の作者単位の取り下げ (S.235) で 28 話。写真の annex_path (S.233・S.234) は投稿の source_path に当たらないので 0 話 (画像の 410 は S.77)。
