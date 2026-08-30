# scripts/ — 回収・監査・変換ツール一式

ミラー本体ではなく、サルベージと検証に使ったツール群。すべて標準ライブラリのみの Python 3
（`workflows/` は Claude Code の Workflow ツール用 JavaScript）。

用語（**本館** = 移築先の WordPress / **別館** = 原本を保つ GitHub Pages ミラー /
**旧館** = 消滅した原サイト、ほか）は [`../docs/glossary.md`](../docs/glossary.md) が正典。
**この 3 語は内部用語**で、生成物のうち読者の目に触れる文言には使わない。

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
- **bcwiki_fetch.py `<stagedir>` [--attach] [--media] [--relink]** — 現存する作者サイト
  `bc-cafe.net/bcwiki.old/` (きりか進ノ介さん。既収蔵 `kirika.novels.name/wiki` の後継で
  今も稼働) の PukiWiki を丸ごと回収。`?cmd=list` でページ一覧、`?plugin=attach&pcmd=list` で
  添付一覧を取り、`?plugin=attach&pcmd=open` 経由で添付を落とす (`attach/` 直下は 403)。
  ファイル名は kirika.novels.name/wiki と同じ `index@<クエリの % 除去>` 規約なので
  旧ミラーとページ単位で突き合わせられる。`--relink` は収蔵後に、ページ内の絶対 URL を
  ミラー内の相対パスへ書き換える (place_convert の一般写像はクエリを unquote するため
  EUC-JP の %XX 名と食い違う。ここでは回収時の URL→ファイル名対応表をそのまま使う)。

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

`make catalog` で 1.1〜1.9 を一発実行できる (リポジトリ直下の Makefile)。
段の順番には意味がある — episodes.jsonl は catalog_build が新規に書き、
uncatalogued_build と repost_build が**自分の corpus 分だけ差し替えて**追記し、
episode_overrides が最後に人手の上書きを当て、terms/authors/works はその出来上がりを読む。
`make check` は書き込みなしの自己検査、
`make venv` は pykakasi / PyYAML 入りの `.venv` を作る (git 管理外)。
**2 回連続実行で全出力が同一**であることを確認済み。

- **wp/catalog_build.py** — 正規目録 lib1〜73 の 2,887 エントリと旧目録 lib01〜09 の差分 97 件を
  `catalog/episodes.jsonl` へ正規化 (タスク 1.1 / 1.2)。mailto をこの段階で除去し、受け入れ条件
  (件数・パース失敗 0・mailto 残存 0・survey 実測値との一致)を自己検査として同梱。
  provenance は git 履歴から算出。`--check` で書き込みなしの検査のみ、`--no-legacy` で
  `corpus=honkan`(正規目録 lib1–73 由来) のみ。

- **wp/slugs.py** — 恒久 URL になるローマ字 slug の候補生成 (pykakasi) と
  `catalog/slug_overrides.yml` の往復。`status: confirmed` の行は再生成でも上書きしない。
  機械の読みは幻覚しうる (城弾→shirodan、実際の板 id は johdan) ので候補どまり。
- **wp/terms_build.py** — 分類語彙を `catalog/terms.json` へ (タスク 1.3)。ジャンル/種別/
  キーワードを NFKC・「」外し・？除去・同義語マップ (`catalog/*_map.yml`) で正規化し、
  原表記は raw_variants に保持。ts_world 14 本 (`catalog/world_map.yml` の板/ディレクトリ/
  題名規則) と ts_corpus 4 本もここで生成。当時の語彙定義ページ (genre.html /
  type_of_change.html / keyword.html) を term description に転用する。

- **wp/episode_overrides.py** — 話 1 件単位の人手上書きを episodes.jsonl に当てる
  (`catalog/episode_overrides.yml`)。**旧館の原本 `lib*.html` の誤植を catalog 側で直す
  唯一の場所**で、別館 (Pages) は原本レイヤなので誤植もそのまま保つ、という分担。
  上書き前にあるはずの値を `expect:` に書かせ、合わなければ自己検査が落ちる。
  触れるのは author / kansou_slug / entry_role / title / homepage / date だけで、
  **結合キー episode_id と provenance には触れない**。

- **wp/authors_build.py** — 作者を `catalog/authors.json` へ (タスク 1.4)。感想板 id を
  同定の鍵にし、共有シリーズ板 (華代ちゃん等 11 板) は作者板ではないので表示名で同定する。
  板 id の情報源は 2 つ — 目録の感想リンクと、**サイト自身の感想板一覧
  `~ts/kansou/bbs@log_.cgi` (290 板の「表示名 → 板 id」対応表)**。後者を足したことで
  「板はあるのに目録からリンクされていない」作者 8 名を機械の読みで採番する事故が消えた
  (自己検査「confirmed slug と板一覧の不一致 0」で再発を防ぐ)。yomi は lib-index-*.html の
  所属行から。板を持たない作者の slug は pykakasi 候補どまり。
  `slug_overrides.yml` で**複数の表示名に同じ slug** を書くと同一人物として併合し、
  `role: not-an-author` を書くとその表示名では作者を作らない。

- **wp/work_builder.py** — Episode → Work クラスタリング (タスク 1.5)。6 段の根拠
  (シリーズタイトルナビ / series.html / タイトルページ / 話ナビ / ファイル名連番 / 題名接頭辞)
  を強い順に積み、どれで繋がったかを works.jsonl の evidence に残す。**Work は必ず 1 作者に
  閉じる** (共有世界のタイトルページは 69 名の話を 1 つに束ねてしまうため)。
  弱い根拠だけのクラスタは needs_review を立て `catalog/work_overrides.yml` に雛形を出す。

- **wp/uncatalogued_build.py** — 目録に載っていない収蔵物を拾う (タスク 1.8、設計 v1.4)。
  `novel/` 配下の本文 3,818 のうち既収蔵でない分を `corpus=uncatalogued` で追加。メタは
  lib-index-*.html → 本文の「作：」行 → `<title>` → 同ディレクトリの作者、の順に解決し
  `metadata_source` に記録。落としたものは理由つきで `catalog/uncatalogued_excluded.jsonl` に。
- **wp/repost_build.py** — 作者本人の再掲から回収した本文を catalog に入れる (タスク 1.9)。
  検証済みの pixiv 2 件のみ (雨女 = 既存エントリに本文と来歴を追加 / きらいなもの→ＧＷ =
  文庫未掲載として `corpus=extern-repost` で新規収録)。本文は `reposts/*.txt`。

- **wp/qa_report.py** — `catalog/reports/*.json` を集めて `catalog/QA.md` を書く
  (タスク 1.7)。数字は各段のレポートからの転記だけで、ここでは再計算しない。

- **wp/verify_bodies_html5.py** — `bodies/*.md` の無損失性を **html5lib で検算**する
  (`make verify`。要 `make venv`)。body_convert.py は正規表現で HTML を解釈し、無損失証明も
  同じ正規表現で書かれているので「両側を同じ思い込みで間違える」余地がある。そこで HTML5 の
  パース仕様どおりに解釈する html5lib を**独立した第二実装**として使い、原本の可視テキストに
  md の可視テキストが**連続部分文字列として収まるか**を見る (前後にはみ出す分 = 意図的に
  落としたクローム・ナビ。それが地の文を巻き込んでいないかも検査する)。
  解釈差は梯子で切り分ける (`strict` / `noscript` / `no-form` / `rawtext`)。
  出力 `catalog/reports/verify_html5.json`。`--explain <episode_id>` で 1 話の差分を全部並べる。

実装プローブ (本実装の手本):

- **wp/md_convert_probe.py `<N>` [seed]** — 本文 HTML→Markdown 変換の実現性プローブ。ブラウザ等価
  preclean(bogus comment・裸の `<`・フォーム除去)と**無損失不変量チェック**(タグ・空白・山括弧除去
  +NFC の完全一致)を実装。本実装 body_convert.py の手本。実測: 800×3 シードで合格 75〜76%。
- **wp/kansou_parse_probe.py** — ~ts/kansou の MiniBBS スナップショット(log/res)の構造化パース
  プローブ。実測: log 97%・res 100%、log∪res 重複排除で約 3,978 投稿。boards_build.py の手本。
- **wp/assets/robots.txt** — 本番 novels.xwp.jp 用 robots.txt の草案(AI 学習クローラ全域
  Disallow・ia_archiver 許可・検索エンジンは**止めない**〔noindex ヘッダを読ませるため〕)。
  配備は deploy.sh(タスク 2.3)。リポジトリ直下の robots.txt は Wayback 誤回収物なので使わない。
- **wp/qa_phase3_check.py** — タスク 3.3「無作為 100 話の原本併読 QA」の**独立検算**。
  本番ページ(キャッシュバスター付き)の本文と、リポジトリの原本 (source_path) を
  **別実装で**抽出して突き合わせる(標準ライブラリの `html.parser` を使い、
  body_convert.py / payload_build.py の正規化は流用しない = 同じバグを二度踏まないため)。
  判定は「本番本文が原本テキストの**連続部分列**か」。WP の表示フィルタ
  (`wptexturize` / `convert_smilies`) による字面差は `ok-texturize` として別勘定にする。
  出力は `catalog/reports/qa_phase3_check.json`。実測 (2026-08-30 最終): 100 話で ok 97 /
  ok-trimmed 1 / ok-image 2(ng 0・問題率 0.0%)。
