# ドキュメント地図

このリポジトリは、閉鎖した TS 小説投稿サイト **少年少女文庫**(`ts.novels.jp`、1997–2021)を
Web アーカイブ等から復元した静的ミラーであり、現在それを **WordPress ライブラリへ移築**する
プロジェクトが進行中です。作業は文脈を持たないセッションが交代で担当するため、
**必要な判断はすべて文書に書いてあります**。この地図はその入口です。

## 立場別・最初に読むもの

| あなたの立場 | 読む順 |
|---|---|
| **実装を担当する**(移築作業) | ① [wp-implementation-tasks.md](wp-implementation-tasks.md) → ② [wordpress-library-design.md](wordpress-library-design.md) → ③ [data-inventory.md](data-inventory.md) → ④ [glossary.md](glossary.md) |
| **回収を担当する**(サルベージの続き) | ① [../README.md](../README.md) → ② [../scripts/README.md](../scripts/README.md) → ③ [data-inventory.md](data-inventory.md) |
| **本番サーバを触る** | ① [environment.md](environment.md) → ② [publish-runbook.md](publish-runbook.md) |
| **削除依頼に対応する** | [removal-runbook.md](removal-runbook.md) |
| **作者・関係者に連絡する** | [author-outreach.md](author-outreach.md) |
| **プロジェクトを俯瞰したい** | [../README.md](../README.md) → このファイル → [wordpress-library-design.md](wordpress-library-design.md) |

## 文書の役割と「正典」

**正典 = その事柄について最終的にどれを信じるか。** 文書間で食い違ったら、下表の正典が勝ちます。

| 文書 | 役割 | 正典であるもの |
|---|---|---|
| [wp-implementation-tasks.md](wp-implementation-tasks.md) | **実装の単一の進行台帳**。タスク・受け入れ条件・進捗(`[x]`)・作業上のガード | **実装の手順と現在地**。設計と食い違う場合、実装のやり方は台帳が正 |
| [wordpress-library-design.md](wordpress-library-design.md) | 移築の設計。v1.0 本文 + v1.1〜v1.4 の改訂章 | **方針と決定**。ただし**後の改訂章が前の記述に優先**(下記) |
| [data-inventory.md](data-inventory.md) | リポジトリ収蔵物の目録(全ツリー・件数・出所・扱い) | **何がどこにいくつあるか** |
| [glossary.md](glossary.md) | 本プロジェクト固有の用語定義 | **用語の意味** |
| [environment.md](environment.md) | 本番環境(`ssh novels`)の構造・制約・作業ガード | **サーバの事実** |
| [publish-runbook.md](publish-runbook.md) | catalog 再生成から本番反映までの定常手順 | **公開手順** |
| [removal-runbook.md](removal-runbook.md) | 削除依頼への対応(72h SLA・四層+掲示) | **削除手順** |
| [author-outreach.md](author-outreach.md) | 作者・元運営者への連絡計画と経路の棚卸し | **連絡の方針と経路** |
| [../scripts/README.md](../scripts/README.md) | 回収・監査・変換ツールの台帳 | **ツールの使い方** |
| [../README.md](../README.md) | 復元の全体像・回収の来歴・既知の欠落 | **ミラーの成り立ち** |
| `catalog/README.md` | 生成データ(episodes.jsonl 等)の欄の説明 | **データの意味** |

### 設計書の読み方(重要)

設計書は**改訂を追記する形式**です。同じ論点について複数の記述がある場合、**新しい章が勝ちます**:

| 章 | 内容 | 前章のどこを覆したか |
|---|---|---|
| v1.0 本文 | 4 設計案 → 3 審査員 → 統合した基本設計 | — |
| **v1.1** | Xserver for WordPress の実測に基づく再設計 | 公開面を静的書き出しからライブ WP へ。アネックスは GitHub Pages 恒久併存 |
| **v1.2** | 本文 Markdown 正準化 / 感想板の近代ビュー | 「本文 raw 一律」「感想板はリンクのみ」を撤回。**章が 2 本あるが後方の A/B 構成が正典** |
| **v1.3** | 原運営者公認のリブートとして再定位 | 「黙認ベースの運用」という前提を更新。要決定事項 Q1〜Q7 の回答を確定 |
| **v1.4** | **最大掲載原則** | 「WP 化は作品層のみ・残り 70% はアネックス凍結」を撤回。掲載しない例外は 3 つだけ |

## 決まっていること・決まっていないこと

**決定済み**(設計 v1.3/v1.4):
- 公開先は `novels.xwp.jp`(Xserver for WordPress)。現行 GitHub Pages ミラーは**アネックス原本として恒久併存**
- 本プロジェクトは**八重洲メディアリサーチ(文庫設立者)の依頼によるリブート**。ただし個別作品の著作権は各原著者にある
- **サルベージしたもの・できるものはすべて掲載する**。掲載しない例外は denylist / スパム / メールアドレスのみ
- 連絡不達の作者の作品も公開し、削除依頼には 72 時間以内に応じる
- 本文は無損失証明つきの Markdown を正準とし、証明に通らない話だけ原本 HTML のまま載せる

**未決・人間待ち**: 台帳の 👤 印のタスク(作者連絡の発送、恒久 URL になるローマ字 slug の確認、
公開解禁の最終判断、本番のキャッシュクリア)。実装セッションは 👤 に当たったらスキップし、
最後に「👤 待ち」として報告してください。

## 作業のしかた(全セッション共通)

1. 担当タスクを台帳で確認し、**受け入れ条件を満たすまで**やる。満たせないときは正直に報告する
   (数値をごまかさない)
2. **単一真実源は `catalog/`**。WordPress の DB は使い捨ての派生ビューで、`wp ts import` で
   いつでも再構築できる。WP 管理画面での手動編集は禁止、修正は catalog 側の overrides で
3. 新しいスクリプトは `scripts/`(移築用は `scripts/wp/`)に置き、`scripts/README.md` に 1 行足す
4. コミットは日本語。タスクを終えたら台帳の `[ ]` を `[x]` にして一緒にコミットする
5. **作者・関係者への連絡は人間が行う**。セッションからは一切しない
6. 本番サーバの危険操作の前に復旧点を作る([environment.md](environment.md) のチェックリスト)
