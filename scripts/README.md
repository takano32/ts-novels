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

## 第4次サルベージ (復元範囲拡張) のツール

- **reverse_missing_urls.py `<missing.json>` `<out.tsv>`** — 監査の欠落ターゲットを元 URL に逆写像
  (既知の @ マングル族 log/res/noteky/karte 等を復元。不明形式はスキップ)。各プローブの共通入力。
- **at_sweep.py `<out.json>` `<prefix>…`** — archive.today のドメインワイルドカード一覧
  (`archive.md/<prefix>*`) を巡回し、捕獲されている元 URL を列挙。CAPTCHA 検出で中断。
- **cc_zipnum_sweep.py `<urls.tsv>` `<out.jsonl>` [collフィルタ]** — CommonCrawl 全コレクションを
  **API を使わず** cluster.idx の HTTP レンジ二分探索 + 該当 cdx ブロックのレンジ取得で照会
  (`COLLINFO=collinfo.json` でコレクション一覧をローカル参照)。同一ドメインの URL は同じブロックに
  固まるため全 127 コレクションでも現実的。
- **cdx_single_host.py `<host>` `<topdir>` `<out.json>`** — 新発見ホストの CDX ダンプ+
  wb_fetch 用マニフェスト生成の一発ツール (aetherworks.org 回収で使用)。

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

## wp/ (WordPress 移築の実装)

- **wp/catalog_build.py** — 正規目録 lib1〜73 の 2,887 エントリと旧目録 lib01〜09 の差分 97 件を
  `catalog/episodes.jsonl` へ正規化 (タスク 1.1 / 1.2)。mailto をこの段階で除去し、受け入れ条件
  (件数・パース失敗 0・mailto 残存 0・survey 実測値との一致)を自己検査として同梱。
  provenance は git 履歴から算出。`--check` で書き込みなしの検査のみ、`--no-legacy` で本館のみ。

- **wp/slugs.py** — 恒久 URL になるローマ字 slug の候補生成 (pykakasi) と
  `catalog/slug_overrides.yml` の往復。`status: confirmed` の行は再生成でも上書きしない。
  機械の読みは幻覚しうる (城弾→shirodan、実際の板 id は johdan) ので候補どまり。
- **wp/terms_build.py** — 分類語彙を `catalog/terms.json` へ (タスク 1.3)。ジャンル/種別/
  キーワードを NFKC・「」外し・？除去・同義語マップ (`catalog/*_map.yml`) で正規化し、
  原表記は raw_variants に保持。ts_world 14 本 (`catalog/world_map.yml` の板/ディレクトリ/
  題名規則) と ts_corpus 4 本もここで生成。当時の語彙定義ページ (genre.html /
  type_of_change.html / keyword.html) を term description に転用する。

- **wp/authors_build.py** — 作者を `catalog/authors.json` へ (タスク 1.4)。感想板 id を
  同定の鍵にし、共有シリーズ板 (華代ちゃん等 11 板) は作者板ではないので表示名で同定する。
  yomi は lib-index-*.html の所属行から。板を持たない作者の slug は pykakasi 候補どまり。

実装プローブ (本実装の手本):

- **wp/md_convert_probe.py `<N>` [seed]** — 本文 HTML→Markdown 変換の実現性プローブ。ブラウザ等価
  preclean(bogus comment・裸の `<`・フォーム除去)と**無損失不変量チェック**(タグ・空白・山括弧除去
  +NFC の完全一致)を実装。本実装 body_convert.py の手本。実測: 800×3 シードで合格 75〜76%。
- **wp/kansou_parse_probe.py** — ~ts/kansou の MiniBBS スナップショット(log/res)の構造化パース
  プローブ。実測: log 97%・res 100%、log∪res 重複排除で約 3,978 投稿。boards_build.py の手本。
- **wp/assets/robots.txt** — 本番 novels.xwp.jp 用 robots.txt の草案(AI 学習クローラ全域
  Disallow・ia_archiver 許可・検索エンジンは**止めない**〔noindex ヘッダを読ませるため〕)。
  配備は deploy.sh(タスク 2.3)。リポジトリ直下の robots.txt は Wayback 誤回収物なので使わない。
