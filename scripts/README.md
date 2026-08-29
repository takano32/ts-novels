# scripts/ — 回収・監査・変換ツール一式

ミラー本体ではなく、サルベージと検証に使ったツール群。すべて標準ライブラリのみの Python 3
（`workflows/` は Claude Code の Workflow ツール用 JavaScript）。

## 監査・修正

- **audit_full.py** — 全 HTML の内部リンク監査。href/src/action/background を抽出し、
  サイト族ホストの写像・クエリ→`name@query.ext` 規約・ルート相対・拡張子なしルートを解決して
  解決/未解決を集計。未解決ターゲットは `missing_targets.json` に出力。
- **fix_links.py** — 未解決リンクの構造的修正候補（深さずれ・セグメント重複・`&amp;` 二重
  エスケープ変種・雛形ページ等）を生成し、**一意に既存ファイルへ解決する場合のみ**書き換える。
  既定はドライラン、`--apply` で適用。
- **gapfill_basename.py** — 本体ツリーの欠落ファイルを、旧世代ツリー（~yays/library・~ezpe）から
  **basename 一意照合**で補完コピー。雛形ページ（library.html 等）は除外。`--apply` で適用。

## Wayback 回収パイプライン

1. **cdx_dump.py `<outdir>`** — 全ホスト系統（novels.jp ドメイン・~yays・~ezpe・novels.name
   ドメイン・raa0121・ts-novels.jp・sts）の CDX を resumeKey ページングで全量ダンプ。
2. **cdx_recover.py `<dumpdir>` `<manifest.json>`** — ダンプをリポジトリ規約名に写像して
   未収ファイルを列挙し、URL ごとに最良キャプチャ（HTML は length>700 の最新 200）を選んだ
   取得マニフェストを生成。
3. **wb_fetch.py `<manifest>` `<stagedir>`** — keep-alive 持続接続 ×5 で `id_`（raw）モード一括取得。
   既取得スキップ・リダイレクト追従・429 バックオフ・ファイル/ディレクトリ衝突回避。
   逆順マニフェストで 2 プロセス並走可（同一 stage、両端から挟む）。
4. **wb_retry.py `<dumpdir>` `<failed.json>` `<stagedir>`** — 再生 404 を URL の全キャプチャ
   新→旧で再試行。
5. **place_convert.py** — `--place <stagedir>`: エラースタブ（内容マーカー+サイズ）を弾いて
   リポジトリへ配置（**まず raw をコミットすること**）。`--convert <list>`: SJIS/EUC→UTF-8・
   meta charset 書き換え・広告 script 除去・リンクのリポジトリ規約名化。
   `--relink`: 既存全ページの絶対 URL を、現存する回収ファイルへの相対リンクに書き換え。

## 他アーカイブのプローブ

- **megalodon_probe.py `<urls.txt>` `<out.json>`** — ウェブ魚拓の `?url=` 照会を 1.15 秒間隔で
  総当たりし、`/ref/` キャプチャ URL を収集。
- **timetravel_probe.py `<urls.txt>` `<out.json>`** — Memento TimeTravel 横断照会
  （archive.today・NDL WARP 等をまとめて確認）。
- **cc_probe.py `<urls.txt>` `<out.json>` [--colls=…]** — CommonCrawl の per-collection index を
  照会。**非常に遅い**のでバックグラウンド実行前提。
- **live_probe.py `<list.tsv>` `<stagedir>`** — 生存中サーバへの直接プローブ
  （www14.big.or.jp の広告 soft-404 を size+マーカーで除外）。

## workflows/

- **wp-library-understand.js** — WordPress ライブラリ化のための 8 視点並列調査
  （目録スキーマ・作品モデル・分類語彙・作者・コミュニティ層・世代・本文形式・権利/運用）。
- **wp-library-design.js** — 調査結果（`args.survey`）を入力に、4 独立設計案 → 3 審査員 →
  統合設計を生成する設計パネル。

## 典型的な再実行手順

```sh
python3 scripts/cdx_dump.py /tmp/cdx_dumps
python3 scripts/cdx_recover.py /tmp/cdx_dumps /tmp/manifest.json
python3 scripts/wb_fetch.py /tmp/manifest.json /tmp/stage     # 必要なら逆順でもう1本
python3 scripts/place_convert.py --place /tmp/stage           # → git commit (raw)
python3 scripts/place_convert.py --convert /tmp/stage/_placed.txt
python3 scripts/fix_links.py --apply && python3 scripts/place_convert.py --relink
python3 scripts/audit_full.py                                 # 検証
```
