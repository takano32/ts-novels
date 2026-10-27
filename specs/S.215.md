# S.215 takedown/denylist.yml の層と運用の約束

- 種類: 仕様
- 状態: 一部失効 (S.43・S.112 で上書き。72 時間の SLA。S.121 で上書き。WP サイトの非公開化 (S.76) の draft → private)
- 出典: takedown/README.md (2026-10-26 にここへ移した)
- 関連: T.6.1, T.21.4, T.23.6, S.75〜S.81, S.112, S.113, S.121, S.123

## takedown/ — 削除依頼の単一情報源

`denylist.yml` 1 本で、削除の**四層 + 掲示**をすべて駆動する。
手順は [`specs/S.75.md`〜`S.81.md`](S.75.md) を優先する。**SLA = 受領から 72 時間**。
用語 (WP サイト = 移築先の WordPress / 原本アーカイブ = 原本を保つ GitHub Pages ミラー / 旧サイト = 消滅した原サイト) は
[`specs/S.14.md`〜`S.19.md`](S.14.md) を参照。**この 3 語は内部用語**で、読者に見せる文面では使わない。

| 層 | 実行主体 | 入力 |
|---|---|---|
| WP 非公開化 (draft) (S.76) | `wp ts apply-takedown --list=takedown/denylist.yml` | `target.kind` = work/episode/author |
| 410 Gone (S.77) | `scripts/wp/gen_htaccess.py` → 本番 `.htaccess` のマーカーブロック | orig パス (episode/annex_path) |
| 原本アーカイブから除外 (S.78) | `scripts/wp/annex_inject.py` (GitHub Pages のビルド注入) | `annex_path` / episode の source_path |
| git 履歴除去 (S.79) | 手作業 (`git filter-repo`)。**依頼があったときのみ** | 対象パス |
| 掲示 (S.80) | 固定ページ `/removed/` | `public_note` |

## 運用の約束

- **ここは public リポジトリ**。依頼者の氏名・メールアドレス・依頼文の原文は書かない。
  受領日 (`requested_at`) と経路の分類 (`received_via`) だけを残し、依頼の実物は私有ストレージへ。
- エントリは**消さない**。復帰した場合も `status: reinstated` にして履歴として残す
  (同じ作品を再収蔵してしまう事故を防ぐため)。
- `id` は再利用しない (`S.n` = その取り下げの記録の S 番号。2026-10-27 から。S.221)。
- 状態は `received → applied → verified`。`applied` の各層の日付が全部埋まる前に
  `verified` にしない。

## スキーマ

`denylist.yml` 冒頭のコメントを優先する (フィールド一覧・enum・記入例)。
現在は `version: 1` / `entries: []`(空)。

## 一貫性チェック

catalog 側の値と付き合わせる簡易検査 (T.8 の `wp ts apply-takedown` にも同等の検査を入れる):

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
