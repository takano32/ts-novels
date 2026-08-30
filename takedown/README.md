# takedown/ — 削除依頼の単一情報源

`denylist.yml` 1 本で、削除の**四層 + 掲示**をすべて駆動する。
手順の正典は [`docs/removal-runbook.md`](../docs/removal-runbook.md)。**SLA = 受領から 72 時間**。
用語 (本館 = 移築先の WordPress / 別館 = 原本を保つ GitHub Pages ミラー / 旧館 = 消滅した原サイト) は
[`docs/glossary.md`](../docs/glossary.md) を参照。**この 3 語は内部用語**で、読者に見せる文面では使わない。

| 層 | 実行主体 | 入力 |
|---|---|---|
| ① WP 非公開化 (draft) | `wp ts apply-takedown --list=takedown/denylist.yml` | `target.kind` = work/episode/author |
| ② 410 Gone | `scripts/wp/gen_htaccess.py` → 本番 `.htaccess` のマーカーブロック | orig パス (episode/annex_path) |
| ③ 別館から除外 | `scripts/wp/annex_inject.py` (GitHub Pages のビルド注入) | `annex_path` / episode の source_path |
| ④ git 履歴除去 | 手作業 (`git filter-repo`)。**依頼があったときのみ** | 対象パス |
| ⑤ 掲示 | 固定ページ `/removed/` | `public_note` |

## 運用の約束

- **ここは public リポジトリ**。依頼者の氏名・メールアドレス・依頼文の原文は書かない。
  受領日 (`requested_at`) と経路の分類 (`received_via`) だけを残し、依頼の実物は私有ストレージへ。
- エントリは**消さない**。復帰した場合も `status: reinstated` にして履歴として残す
  (同じ作品を再収蔵してしまう事故を防ぐため)。
- `id` は再利用しない (`TD-YYYY-NNNN`)。
- 状態は `received → applied → verified`。`applied` の各層の日付が全部埋まる前に
  `verified` にしない。

## スキーマ

`denylist.yml` 冒頭のコメントが正典 (フィールド一覧・enum・記入例)。
現在は `version: 1` / `entries: []`(空)。

## 一貫性チェック

catalog 側の値と付き合わせる簡易検査 (Phase 2 の `wp ts apply-takedown` にも同等の検査を入れる):

```sh
python3 - <<'EOF'
import yaml, json, pathlib
d = yaml.safe_load(open('takedown/denylist.yml'))
paths = {json.loads(l)['source_path'] for l in open('catalog/episodes.jsonl')}
for e in d.get('entries') or []:
    t = e['target']
    if t['kind'] == 'episode' and t['value'].split('#')[0] not in paths:
        print('UNKNOWN episode target:', e['id'], t['value'])
EOF
```
