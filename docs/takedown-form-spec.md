# 取り下げフォームと 10 日後の自動適用の仕様 v1 (2026-10-26)

**位置づけ**: 設計書 v1.6 の決定 2 (72 時間 SLA を撤回し、フォーム → 確認メール → 10 日後に自動で非公開 → 運営委員が上書き) の実装仕様。
根拠と現状は [`owner-feedback-2026-10.md`](owner-feedback-2026-10.md) §11、実装タスクは `TASKS.md` の T.26。
非公開化の振る舞い (private・410・帯) は [`visibility-roles-spec.md`](visibility-roles-spec.md) が正典。
所有者の裁定待ちのパラメータは §12 の表にまとめ、**既定値で実装して裁定後に定数を変える**。

## 1. 窓口

- `/takedown/` 固定ページにショートコード `[ts_takedown_form]` (mu-plugin)。送信先は `admin-post.php?action=ts_takedown` (`admin_post_nopriv_ts_takedown`)。外部のフォームプラグインは入れない (設計 §8 プラグイン最小構成)。
- GitHub Issue の案内は**載せない** (設計 v1.0 §5 で不採用。現在のページの記載は誤り)。メール窓口 (👤 のアドレス) を併記し、メール経由の申し出は運営委員が手で申請を起票して同じ状態機械に乗せる。

| 項目 (name) | 必須 | 形 | 検証 |
|---|---|---|---|
| `pen_name` 当時のペンネーム | 必須 | 1〜100 字 | — |
| `targets` 対象作品の URL | 必須 | 1〜20 行 | 本サイトの `/novel/…` で `ts_work` に解決できること。解決できない行は保存して警告フラグ |
| `contact` 連絡先メール | 必須 | email | `is_email()` |
| `relation` 立場 | 必須 | `author` 作者本人 / `heir` 権利継承者 / `other` | enum |
| `evidence` 本人確認の手がかり | 任意 | 1,000 字 | — |
| `message` 自由記述 | 任意 | 2,000 字 | — |
| `allow_name` 名前の掲示 | 任意 | checkbox | `/removed/` にペンネームを出してよいか (既定: 出さない) |
| `hp` | — | honeypot (CSS で隠す) | 非空なら 400 |
| `t` | — | フォーム表示時刻 | 送信まで 3 秒未満なら 400 |
| `_wpnonce` | — | nonce | 不一致は 403 |

- レート制限: 送信元 IP の SHA-256 (salt = `AUTH_SALT`) をキーに transient で **1 時間 3 件**。超過は 429。IP そのものは保存しない。
- 成功時: `unconfirmed` の申請を作り、確認メール (§6 の①) を送って「確認メールを送りました」ページ (200) を返す。

## 2. データ

- CPT **`ts_takedown_request`**: `public=false` / `show_ui=true` / `exclude_from_search=true` / `show_in_rest=false`。管理画面に出す条件は cap `ts_manage_takedown`。`post_title` = 「申請 #<ID> <pen_name>」、本文は使わない。
- **`wp ts reset` の対象に入れない** (`TS_Command::TYPES` に加えない)。運用の原本であり、catalog から再生成できないため。
- post meta:

| meta | 内容 |
|---|---|
| `_tr_status` | §3 の状態 |
| `_tr_pen_name` `_tr_contact` `_tr_relation` `_tr_evidence` `_tr_message` `_tr_allow_name` | 入力値 |
| `_tr_targets` | JSON: `[{url, post_id|null, work_slug|null}]` |
| `_tr_token` | 確認トークン (32 byte random、`wp_hash` で保存) |
| `_tr_received_at` | フォーム送信時刻 |
| `_tr_confirmed_at` | 確認リンクを踏んだ時刻 = **T0** |
| `_tr_auto_apply_at` | T0 + `AUTO_APPLY_DAYS` |
| `_tr_resolved_at` `_tr_resolution_note` `_tr_resolved_by` | 承認・却下・復帰の記録 (理由は内部メモ) |
| `_tr_ip_hash` `_tr_ua` | 受付時の送信元 (ハッシュ) と UA |

- 連絡先は WP の DB にだけ置く。**リポジトリ (`takedown/denylist.yml`) には書かない** (`takedown/README.md` の約束)。DB dump は私有ストレージへ (既存運用 8.1)。
- 保持: 解決 (`applied`/`rejected`/`reinstated`/`expired`) から **`RETENTION_DAYS` (既定 365 日)** 経過で `_tr_contact` `_tr_evidence` `_tr_message` `_tr_ip_hash` `_tr_ua` を消す (申請の骨格と結果は残す)。

## 3. 状態機械

```
[フォーム送信] → unconfirmed ──(確認リンク)──→ received (T0) ──(T0+10 日, cron)──→ applied
                     │ 7 日で未確認                     │                              │
                     └→ expired          運営委員: approved (即時適用) ────────────┘   運営委員: reinstated
                                          運営委員: rejected (自動適用を止める)
```

| 状態 | 意味 |
|---|---|
| `unconfirmed` | 受付済み。確認メール待ち |
| `received` | 本人確認 (メール) 済み。自動適用の待機中 |
| `applied` | 自動適用済み (非公開) |
| `approved` | 運営委員が承認し即時適用 (非公開) |
| `rejected` | 運営委員が却下。自動適用しない。記録は残す |
| `reinstated` | 非公開後に復帰 |
| `expired` | 確認されずに失効 |

| 契機 | 遷移 | 副作用 |
|---|---|---|
| フォーム送信 | → `unconfirmed` | メール① (依頼者) |
| 確認リンク (`?ts_confirm=<token>`) | `unconfirmed` → `received` | T0・`auto_apply_at` を記録。メール② (運営委員) |
| cron: `now ≥ received_at + CONFIRM_TTL_DAYS` | `unconfirmed` → `expired` | なし |
| cron: `now ≥ auto_apply_at - REMIND_BEFORE_DAYS` (1 回) | — | メール③ (運営委員) |
| cron: `now ≥ auto_apply_at` | `received` → `applied` | §4 の処理。メール④ (依頼者・運営委員) |
| 運営委員「承認して即時適用」 | `received` → `approved` | §4 の処理。メール④ |
| 運営委員「却下」 | `unconfirmed`/`received` → `rejected` | メール⑤ (依頼者。§12 の裁定による) |
| 運営委員「復帰」 | `applied`/`approved` → `reinstated` | §5 の復帰処理 |
| 運営委員「期限を変える」 | — | `auto_apply_at` を書き換える (早める・延ばす) |

定数 (既定): `CONFIRM_TTL_DAYS = 7`、`AUTO_APPLY_DAYS = 10`、`REMIND_BEFORE_DAYS = 3`。

## 4. 非公開化の処理 (applied / approved)

1. `_tr_targets` の解決できた投稿ごとに、その投稿と子孫を `private` + `_ts_visibility=withdrawn` + `_ts_takedown_id=req:<ID>` + `_ts_public_note` (§8 の文言) にする (visibility-roles-spec §2)。
2. option `ts_takedown_urls` に公開時の URL を追加 (監査用)。
3. 解決できなかった行があれば運営委員にその旨を通知 (できた分だけ適用する)。
4. 既に `withdrawn` (denylist 由来) の投稿は触らない。`superseded` の投稿は `withdrawn` を上書きで立てる (優先順位どおり)。

## 5. 運営委員の操作 (申請の編集画面のメタボックス)

- **承認して即時適用** / **却下** (理由を内部メモに) / **復帰** / **期限を変える** の 4 つ。
- 復帰: 対象投稿の `_ts_visibility` `_ts_takedown_id` `_ts_public_note` を消し、`superseded` が無ければ `publish` に戻す。`ts_takedown_urls` から除く。`reinstated` と記録。
- すべての操作は `_tr_resolved_by` (ユーザ ID)・時刻・メモを残す。

## 6. 通知 (`wp_mail`、差出人は `admin_email`、HTML なしのテキスト)

| # | 契機 | 宛先 | 要点 |
|---|---|---|---|
| ① | 受付 | 依頼者 | 確認リンク (有効 7 日)。踏まなければ失効する旨 |
| ② | 確認完了 | 運営委員全員 + `admin_email` | 申請の要約と「YYYY-MM-DD HH:MM に自動で非公開になります。承認/却下は管理画面から」 |
| ③ | 自動適用の 3 日前 | 運営委員全員 | リマインド (未処理のもの) |
| ④ | 適用 (自動/承認) | 依頼者 + 運営委員 | 非公開にした作品の一覧、`/removed/` への掲示の有無 |
| ⑤ | 却下 | 依頼者 (§12 の裁定で送るか決める) | 定型文 (「確認が取れなかった」等。詳細な理由は書かない) |

運営委員全員 = ロール `ts_committee` / `ts_committee_editor` のユーザのメール。到達確認は配備時に自分宛に 1 通送って行う (本番の `sendmail_path` は設定済み、送達は未検証)。

## 7. cron

- フック `ts_takedown_tick` を 1 時間おきに登録 (`wp_schedule_event`)。処理: 失効 → リマインド → 自動適用 (§3 の順)。1 回の tick で処理する件数に上限は置かない (件数は少ない)。
- 実行基盤: WP-Cron は閲覧駆動で、前段 nginx がキャッシュから返す閲覧では PHP が動かない (environment §3) ため、**サーバの crontab** に
  `*/10 * * * * cd ~/novels.xwp.jp/public_html && wp cron event run --due-now >/dev/null 2>&1` を足し (`crontab -l` の控えを先に)、
  👤 `wp-config.php` (公開ルート外) に `define('DISABLE_WP_CRON', true);` を入れる。
- 監視: `wp cron event list` に `ts_takedown_tick` が出て `next_run_relative` が進むこと。失敗は `ts_takedown_last_tick` option に時刻とエラーを残し、`wp ts verify` が 24 時間以上止まっていれば警告する。

## 8. `/removed/` の動的生成

- ショートコード `[ts_removed_list]`: `ts_work` の親投稿 (post_parent=0) で `private` かつ `_ts_visibility=withdrawn` のものを `_ts_takedown_id` ごとに 1 行、新しい順に列挙。
- 文言: `_ts_public_note` があればそれ (denylist 由来は `public_note`、申請由来は適用時に生成)。申請由来の生成規則: `allow_name` が真なら「『題名』(ペンネームさん) — 作者の申し出により掲載していません (YYYY 年 MM 月)」、偽なら「作者の申し出により 1 作品を掲載していません (YYYY 年 MM 月)」。理由は公表しない。
- `content/pages/removed.html` は見出し・前置き・ショートコードだけにし、手書きの一覧は消す (現状の手書き 9 行は denylist の `public_note` に移っている)。

## 9. `takedown/denylist.yml` との整合

- **denylist.yml = git に残す確定記録、申請 CPT = 運用の原本。** 手動エントリ (TD-0001〜) は従来どおり denylist → `apply-takedown`。
- `wp ts export-takedowns [--since=<date>]`: `applied` / `approved` / `reinstated` の申請を denylist スキーマ (version 1) のエントリとして標準出力に出す。`id` は既存最大の次番 (`TD-YYYY-NNNN`)、`target` は `kind: work` + `work_slug` (対象が複数なら複数エントリ)、`reason: 取り下げフォーム経由の申し出 (申請 #n)`、`reason_class: author_request`、`requested_at` = T0 の日付、`received_via: takedown_form`、`status: applied` (復帰は `reinstated`)、`applied.wp_draft` = 適用日、`public_note` = §8 の文言。**連絡先・本文・証拠は出さない。**
- [`publish-runbook.md`](publish-runbook.md) に 1 手順: publish の前に `export-takedowns` → denylist に追記 → `make check` → コミット (別館の除外 = Phase 5.2 に伝播する)。
- `wp ts verify` に「`applied`/`approved` の申請で denylist に対応エントリが無いもの」の件数を足す (0 でなければ警告)。

## 10. 別館 (GitHub Pages) との連携 (任意。Phase 5.2 と同時)

- 公開 REST `GET /wp-json/ts/v1/withdrawn` → `{"generated": "<ISO 8601>", "source_paths": ["novel/200202/03004409/pink.html", …]}`。依頼者情報は含めない。`withdrawn` の全投稿の `_ts_source_path` (子孫含む) を返す。
- `deploy-pages.yml` のビルド注入 (`annex_inject.py`) がこれを取得し、`denylist.yml` の除外と合流させる → 人手なしで 10 日後に別館からも消える。取得に失敗したときは denylist.yml だけで続行し、ログに残す。

## 11. 公開文面 (案。T.21.1 で確定)

`/takedown/`:
> 本サイトに収録された作品の作者ご本人 (またはその権利を継承された方) は、下のフォームから掲載の取り下げをお申し出いただけます。
> お申し出は、確認メールに記載のリンクを開いていただいた時点で受け付けとなり、**その 10 日後に自動的に非公開になります**。
> 運営委員が内容を確認し、早めることや、本人確認が取れない場合に保留することがあります。
> フォームが使えない場合はメール (…) でも受け付けます。

`/removed/` の前置き: 「作者の申し出により掲載していない作品の一覧です。作品名とお申し出の時期のみを掲載し、理由は公表しません。」

「10 日」を公開文面に書けない裁定のときは「一定期間の後に自動的に非公開になります」に差し替える。

## 12. 裁定待ちのパラメータ (既定値で実装)

| パラメータ | 既定 | 所有者に聞くこと |
|---|---|---|
| `AUTO_APPLY_DAYS` | 10 | 公開文面に「10 日」と書いてよいか |
| 確認メール | 必須 | 確認なしで受理するか (なりすまし取り下げのリスクを受容するか) |
| `RETENTION_DAYS` | 365 | 依頼者情報の保持期間 |
| 通知先 | `admin_email` + 運営委員全員 | 通知先のメールアドレス |
| 却下時のメール⑤ | 送る (定型文) | 送らない方がよいか |
| 窓口メールアドレス | — | 公開してよいアドレス |

## 13. 検証手順 (配備後)

1. 試験用の公開作品 1 本を対象にフォーム送信 → 申請が `unconfirmed`、メール①が届く。
2. 確認リンク → `received`、`auto_apply_at` = T0 + 10 日、メール②が届く。
3. `auto_apply_at` を WP-CLI で過去に書き換え → `wp cron event run ts_takedown_tick` → `applied`、対象が `private`/410、`/removed/` に出る、メール④。
4. 「復帰」→ `publish`、`/removed/` から消える。
5. スパム系: honeypot 入り → 400、送信 3 秒未満 → 400、同一 IP 1 時間 4 件目 → 429、他サイトの URL → 保存されるが警告フラグ。
6. `wp ts reset --yes` を**使わずに** (本番では流さない) ローカルの同等環境がないため、`TYPES` に含まれないことはコードレビューと `wp ts reset --help` の説明文で確認する。
7. `wp ts export-takedowns` の出力を denylist に追記して `make check` が通る。
