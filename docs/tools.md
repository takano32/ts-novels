# ツールの使い方

このリポジトリのスクリプトの手引きです。ツールの一覧と引数の正は S.209 (回収・監査・変換。`scripts/` 直下) と
S.210 (WordPress 移築。`scripts/wp/`) にあり、ここではどこから使えばよいかだけを書きます。

## 置き場所

| 場所 | 中身 |
|---|---|
| `scripts/` 直下 | 原本の回収・監査・変換 (サルベージ) のツール。標準ライブラリだけの Python 3 (S.209) |
| `scripts/workflows/` | Claude Code の Workflow ツール用の JavaScript。WordPress 移築の調査と設計に使った (S.209) |
| `scripts/wp/` | WordPress 移築の実装。目録データ (catalog) の生成・本文の変換・配備 (S.210) |
| `scripts/doc_check.py` | 文書 (docs / specs / tasks) の番号・リンク・使わない語の検査 (S.219) |

## よく使う入口 (リポジトリ直下の Makefile)

| コマンド | 何をするか |
|---|---|
| `make venv` | 初回だけ。版を固定したライブラリ入りの `.venv` を作る。版が変わると slug (恒久 URL) が変わるため (S.211、S.82) |
| `make catalog` | `catalog/` を素の状態から作り直し、`catalog/QA.md` まで書く (S.211) |
| `make check` | ファイルを書かずに各段の自己検査を走らせ、最後に文書の検査をする (S.211、S.219) |
| `make qa` | `catalog/reports/*.json` から `catalog/QA.md` だけを書き直す (S.180) |
| `make verify` | 本文 Markdown の無損失を html5lib で検算する (S.210) |

段の順番と、2 回続けて流すと出力が同じになることは S.211 にあります。

## サルベージをやり直すとき

Wayback の全量ダンプから取得・配置・変換・リンク修正・監査までの典型的な流れは S.209 の「典型的な再実行手順」です。
変換の前に生のバイトを一度コミットします (S.209、S.206)。

## 本番へ配備するとき

配備は `scripts/wp/deploy.sh` で、既定は dry-run です (S.183)。手順は S.69〜S.71、踏みやすい注意は S.83、
本番に触る前の確認は S.67 です。流れの説明は [operations.md](operations.md) にあります。

## 新しいスクリプトを足すとき

`scripts/` (移築用は `scripts/wp/`) に置き、S.209 か S.210 に 1 行足します (S.5、S.10)。
