# S.49 収蔵物の目録: 計測の基準

- 種類: 実測
- 状態: 有効
- 出典: docs/data-inventory.md の冒頭と「計測の基準」
- 関連: T.4.5, S.50〜S.58, S.205〜S.208

## リポジトリ収蔵物の目録 (data inventory)

このリポジトリに「何が入っているか」を、文脈ゼロの読者が把握するための地図。
**設計や手順は書かない** — 設計は S.20〜S.43、
実装の進行は TASKS.md と S.170〜S.204、
復元の経緯は S.205〜S.208 (元のルート README.md) が正。ここは**現物の目録**だけを扱う。
用語(WP サイト/原本アーカイブ/旧サイト・目録・世代・corpus 区分 ほか)は S.14〜S.19 を参照。
**この呼び分け (S.15) は内部用語**で、読者に見せる文面では使わない(→ S.15)。

### 計測の基準

| 項目 | 値 |
|---|---|
| 計測日時 | 2026-08-30 16:35 JST |
| 計測対象コミット | `931c8a9b` (bodies/ を git 管理から外し、変換器のバグ 4 種を修正) |
| 計測方法 | `git ls-files -z` の**実測**(作業ツリーの未追跡ファイル・`.venv/`・`__pycache__`・**`bodies/`** は除く) |
| git 管理ファイル総数 | **18,093** |
| 　うちミラー相当 | **18,006**(総数 − `catalog/` `scripts/` `docs/` `takedown/` `reposts/` `.github/` の 87) |
| git 管理外の生成物 `bodies/` | **3,642**(本文 Markdown。`.gitignore` 済み・`make catalog` で再生成) |
| 作業ツリー実サイズ | 443 MB (`.git`・`.venv` を除く) |

**再計測のしかた**(数値が古くなったらこれを流す):

```sh
git ls-files -z | tr -cd '\0' | wc -c                   # 総数 (ファイル名の改行に強い数え方)
git ls-files | awk -F/ '{print (NF==1)?"(root)":$1}' | sort | uniq -c | sort -rn   # ツリー別
git ls-files <tree> | grep -ciE '\.s?html?$'            # ツリー内の HTML 数
ls bodies/*.md | wc -l                                  # git 管理外の本文 Markdown
```

**注意**: `catalog/` `bodies/` `reposts/` `scripts/` `docs/` は**現在も別セッションが更新中**で
件数が動く。ミラー本体(`novel/` 以下の収蔵ツリー)は復元作業が完了しており安定している。
