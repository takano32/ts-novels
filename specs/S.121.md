# S.121 wp ts apply-takedown の変更

- 種類: 仕様
- 状態: 有効
- 出典: docs/visibility-roles-spec.md の「wp ts apply-takedown の変更」
- 関連: T.23.3.3, T.23.4, S.76, S.67

## `wp ts apply-takedown` の変更

- 対象 (+子孫) を `private` + `_ts_visibility=withdrawn` + `_ts_takedown_id=<TD>` + `_ts_public_note` (denylist の `public_note`。`/removed/` の動的生成が読む) にする。既に `private`/`draft` の投稿もメタを揃える (自己修復)。
- `status: reinstated` のエントリ: `_ts_visibility` と `_ts_takedown_id` を消し、`superseded` が無ければ `publish` に戻す。
- option `ts_takedown_urls` は監査用に引き続き記録するが、`.htaccess` の生成には使わない。
- dry-run の出力: 「private 化 N / 復帰 M / 変更なし K」。

## 実装の記録 (2026-10-30、T.23.3.3)

`mu-plugins/ts-library/includes/commands.php` の `apply_takedown`。1 投稿の変化は `includes/visibility.php` の `ts_visibility_takedown_change` (WordPress を使わない関数。`scripts/wp/ts_library_test.php` で試す)。

- 取り下げ: post_status が private でなければ private に (draft も publish も)。メタ `_ts_visibility=withdrawn`・`_ts_takedown_id`=エントリの id・`_ts_public_note`=public_note を揃える。public_note の無いエントリ (S.231 など) は `_ts_public_note` を消す。いまの draft を private に移す移行は、これを流し直すだけで済む。
- 復帰 (`status: reinstated`): 取り下げの印 (`_ts_visibility=withdrawn`・`_ts_takedown_id`・移行前の draft) があるときだけ、`_ts_takedown_id`・`_ts_public_note` を消して publish に戻す。`_ts_superseded_by` があれば publish にせず private + `_ts_visibility=superseded` にする (優先順位 withdrawn > superseded。S.117)。同じ投稿が取り下げ中の別のエントリにも当たるなら止めたまま。
- 出力: 変わる投稿ごとに 1 行 (エントリ・投稿 ID・題名・状態の変化・メタの変化) と、最後に `apply-takedown: private 化 N / 復帰 M / 変更なし K (対象 T 件)` (dry-run は末尾に ` (dry-run)`)。
- denylist のどのエントリにも当たらない `_ts_visibility=withdrawn` の投稿 (エントリを消した等) は変えずに警告で知らせる。
- 状態を変えたときは従来どおり `_ts_last_import_gmt` を合わせる (手動編集と誤検知させない)。option `ts_takedown_urls` は監査用に引き続き書く。
- 試験: 手元の WordPress (docker) で draft 2 件・publish 2 件を private 化 4、2 回目は変更なし 4、エントリを reinstated にすると復帰 1。

## 本番への配備と移行の手順 (T.23.4)

(高野さんの作業) ホームで、T.23.3 の変更がコミットされて origin/master に push された後に流す (deploy.sh は push 済みのコミットしか配備しない)。各段の出力を見て、期待と違えば次へ進まずに止める。出力はすべて `$LOG` のファイルに残る。

```sh
cd ~/GitHub/ts-novels && git pull --ff-only && LOG=~/t23.4-$(date +%Y%m%d-%H%M).log && echo "$LOG"

# 1. 復旧点 (S.67)。最後の数は移行前の draft の件数
scripts/wp/novels-ssh 'cd ~/novels.xwp.jp/public_html && mkdir -p ~/dumps && wp db export ~/dumps/pre-t23.4-$(date +%Y%m%d-%H%M).sql && wp post list --post_type=ts_work --post_status=draft --format=count' 2>&1 | tee -a "$LOG"
#   期待: Success: Exported to '…/dumps/pre-t23.4-….sql'. と 51

# 2. 配備 (dry-run を見てから --apply)
scripts/wp/deploy.sh 2>&1 | tee -a "$LOG"
scripts/wp/deploy.sh --apply 2>&1 | tee -a "$LOG"
#   期待: 「-- 構文 OK」、「repo @ <push したコミット>」、配備後の再検査で何も出ない、「== 配備完了。」

# 3. ロール (2 回流して冪等を確かめる)
scripts/wp/novels-ssh 'cd ~/novels.xwp.jp/public_html && wp ts role-sync && wp ts role-sync' 2>&1 | tee -a "$LOG"
#   期待: 1 回目 role-sync: created=2 updated=1 unchanged=0 / 2 回目 role-sync: created=0 updated=0 unchanged=3

# 4. 取り下げの移行 (dry-run)
scripts/wp/novels-ssh 'cd ~/novels.xwp.jp/public_html && wp ts apply-takedown --dry-run' 2>&1 | tee -a "$LOG"
#   期待: Warning: 該当投稿なし が S.233 と S.234 (写真。投稿は無い) の 2 行、
#         「(dry-run) private 化: … (draft → private・メタ …)」が 51 行、
#         Success: apply-takedown: private 化 51 / 復帰 0 / 変更なし 0 (対象 51 件) (dry-run)

# 5. 取り下げの移行 (本番。2 回目は変更なしになること) と件数
scripts/wp/novels-ssh 'cd ~/novels.xwp.jp/public_html && wp ts apply-takedown && wp ts apply-takedown && wp post list --post_type=ts_work --post_status=private --format=count && wp post list --post_type=ts_work --post_status=draft --format=count' 2>&1 | tee -a "$LOG"
#   期待: private 化 51 / 復帰 0 / 変更なし 0 (対象 51 件) → private 化 0 / 復帰 0 / 変更なし 51 (対象 51 件) → 51 → 0

# 6. .htaccess のブロック (表示を見てから --install)
python3 scripts/wp/htaccess_block.py 2>&1 | tee -a "$LOG"
python3 scripts/wp/htaccess_block.py --install 2>&1 | tee -a "$LOG"
#   期待 (表示): /novel/ の Redirect gone が無く、RedirectMatch gone "^/assets/annex-img/…" が 2 行
#   期待 (--install): 無関係 URL 200 (X-Robots-Tag なし) / /dojo/ noindex あり /
#         取り下げ (WordPress) の 3 行が 410 / 取り下げ (画像) の 2 行が 410 / [4/4] OK

# 7. 照合
scripts/wp/novels-ssh 'cd ~/novels.xwp.jp/public_html && wp ts verify | tail -1' 2>&1 | tee -a "$LOG"
#   期待: Success: verify OK
```

- 止めたとき: 6 は NG なら自動で直前の `.htaccess` に戻る。2〜5 で戻すなら `scripts/wp/novels-ssh 'cd ~/novels.xwp.jp/public_html && wp db import ~/dumps/pre-t23.4-….sql'` (1 で作ったファイル名) の後、前のコミットで deploy.sh --apply をやり直す。
- 配備の後は、読者には取り下げ 51 件が 410 (WordPress の案内ページ)、運営委員 (と管理者) にはログイン中だけ 200 + 帯。旧版はまだ無い (T.25)。
- T.23.5 の帯の確認の前に、試験ユーザ `ts-probe-t231` (editor) を `wp user set-role ts-probe-t231 ts_committee` で運営委員にする (editor のままだと 410 になる。S.118)。
