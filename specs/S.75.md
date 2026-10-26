# S.75 削除の手順 v0: SLA と受領

- 種類: 手順
- 状態: 一部失効 (S.43・S.112 で上書き。72 時間の SLA)
- 出典: docs/removal-runbook.md の冒頭・「SLA」・「0. 受領」
- 関連: T.6.2, T.21.3, T.23.6, S.43, S.112, S.126

## 削除ランブック v0 — 受領から 72 時間で四層 + 掲示

対象読者: 運営者本人と、依頼された作業を代行する実行セッション。
単一情報源は [`takedown/denylist.yml`](../takedown/denylist.yml)。
本番の .htaccess 構造・キャッシュ層・危険操作ガードは S.59〜S.68、
削除対象がリポジトリのどこにあるかは S.49〜S.58、
用語 (WP サイト/原本アーカイブ/旧サイト ほか) は S.14〜S.19 を参照
(**この 3 語は内部用語**で、読者に見せる文面では使わない)。
**このランブックは T.37.2 の削除リハーサル (テスト作品 1 件で実流し) の結果で実測更新する。**
v0 の時点では ①〜⑤ の手順は設計上のものであり、実行時間は未実測。

### SLA

- **受領から 72 時間以内に ①WP draft 化 ②410 ③原本アーカイブ (Pages) からの除外 を完了**する (公開面から消える)。
- ④git 履歴除去 と ⑤/removed/ 掲示は 72h を過ぎてもよいが、⑤は 1 週間以内を目安とする。
- 依頼者への一次返信 (受領しました + 想定日時) は**当日中**。本人確認は緩く運用する
  (当時のサイト・なろう・pixiv 等、作品と結びつくアカウントからの連絡で足りる)。

### 0. 受領 (T+0)

1. 窓口は `/takedown/` (メール + フォーム転送) のみ。GitHub Issue は使わない
   (依頼者の身元と依頼内容を公開の場に晒さないため)。
2. 依頼の実物 (メール本文・フォーム控え) は**私有ストレージに保管**。リポジトリには入れない。
3. `takedown/denylist.yml` にエントリを追加する。この時点では `status: received`、
   `applied` は全て `null`。

```yaml
- id: TD.1
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
   work + その全 episode + それらの alias パス + 原本アーカイブの原パスまで波及する。

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
