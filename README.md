# 少年少女文庫 — ts.novels.jp 復元ミラー

閉鎖された TS（性転換）小説投稿・収蔵サイト **「少年少女文庫」**（`http://ts.novels.jp/`, 2002–2021 頃）を、
複数の Web アーカイブのスナップショットから復元した静的ミラーです。

元サイトは既に消滅しており、本リポジトリは
[Internet Archive Wayback Machine](https://web.archive.org/) を主軸に、
[CommonCrawl](https://commoncrawl.org/)・[archive.today](https://archive.today/) 等に残された記録を
横断的に収集して再構成したものです。Wayback に存在しなかったページも、他アーカイブから可能な限り回収しています。

## 内容

- **全 3,930 ファイル**（HTML系 3,147 / 画像 780〔GIF 436・JPEG 340・PNG 4〕/ その他）
  - うち **102 ページは CommonCrawl から回収**（Wayback Machine には一度もアーカイブされていなかったもの）
- トップページ `index.html`、目次 `lib*.html` / `lib-index-*.html`、作品本体 `novel/YYYYMM/<投稿ID>/<作品>.html`

## 復元方法

1. **収集** — Wayback CDX API で `ts.novels.jp` 配下の全 URL を列挙し、ステータス 200 のものを対象に選定。
   各 URL は原則として**最新の 200 スナップショット**を採用（末尾が切り詰められていた 3 件のみ、完全な旧スナップショットを使用）。
2. **取得** — Wayback の `id_`（raw）モードで取得し、ツールバー等の混入のない**原本バイト**を保存。
   さらに **CommonCrawl の全インデックス（2013–2021、83 種）を横断**して `ts.novels.jp/*` のキャプチャを集約し、
   Wayback に無かったページを WARC レンジリクエストで本文取得（**102 ページ**を追加回収）。
   archive.today・国立国会図書館 WARP・ウェブ魚拓（megalodon.jp）も照会したが、いずれも追加分は無し
   （archive.today の 7 件はすべて Wayback と重複）。
3. **文字コード変換** — `nkf` による自動判定で UTF-8 へ統一（元は大半が Shift_JIS、一部 ISO-2022-JP、宣言なしのものも含む）。
   `<meta>` の charset 宣言も UTF-8 に書き換え。
4. **リンク書き換え** — サイト内リンク（絶対 `http://ts.novels.jp/...`、ルート絶対 `/...`、`..` を含むものすべて）を
   ブラウザと同じ規則で解決し、各ファイルからの**相対パス**へ変換。
   これにより、ユーザーページ（ルート公開）でもプロジェクトページ（`/<repo>/` のサブパス公開）でも正しく動作します。

## 検証結果

変換後の全 HTML に対するリンク監査:

| 項目 | 件数 |
|---|---|
| 解決する内部リンク | 34,710（約 90%） |
| 内部絶対リンクの書き換え漏れ | 0 |
| サブパスで壊れるルート絶対リンク | 0 |
| ルート外への `..` リンク | 0 |
| 不正な UTF-8 | 0 |

CommonCrawl からの 102 ページ追加により、サイト全体で **+981 本**の内部リンクが新たに解決するようになりました
（例: シリーズ目次から `akazukin` / `santagirl` / `hime_miko` 各話へのリンクなど）。
加えて、ローカル HTTP 配信を `/<repo>/` サブパス下で行い、回収ページが 200 応答し内部リンクも解決することを確認済み。

## 既知の欠落

残る内部リンクの一部は、リンク先が **どの Web アーカイブにも実体として残っていない**ため辿れません
（古い平置き構造の作品 `novel/*.html`、各階層の動的インデックス `library.html`、未取得の画像 `.gif`/`.jpg`、
Word 書き出しの補助ファイル `.xml`/`.mso` など）。
Wayback Machine・CommonCrawl の両クローラを横断確認した結果、これらは**どちらにも 200 で残っておらず**
（捕捉されていた数件も捕捉時点で既に 404）、本ミラーで復元できる範囲外です。
具体例として、シリーズ目次が参照する `sebaschan.htm` と `ama.htm` の 2 話は、
上記いずれのアーカイブにも残っておらず回収できませんでした（同シリーズの他 9 話は復元済み）。

また、`cgi-bin/` 配下の掲示板・新着リスト・カウンタ等の動的ページは、当時生成された HTML スナップショットとして保存していますが、
CGI としては動作しません（静的な記録のみ）。

## 公開（GitHub Pages）

`master` ブランチへの push で `.github/workflows/deploy-pages.yml` が動作し、GitHub Pages へ自動デプロイされます。
初回のみ **Settings → Pages → Build and deployment → Source** を **「GitHub Actions」** に設定してください。

## リポジトリ構成・履歴

- タグ **`raw-original`** … Wayback 由来分について、UTF-8 変換・リンク書き換えを行う**前**の原本バイト（Shift_JIS 等のまま）。
  変換結果と原本を比較したい場合や、再変換のやり直しに使えます。
  CommonCrawl 由来の 102 ページも、変換前の生 Shift_JIS をいったんコミットしてから UTF-8 化しており、
  その raw コミットが履歴に残っています（`Add 102 pages recovered from CommonCrawl (raw Shift_JIS)`）。
- `.nojekyll` … GitHub Pages の Jekyll 処理を無効化（特殊なファイル名の取りこぼし防止）。

---

*本ミラーは資料保存を目的とした非営利のアーカイブです。各作品の著作権は原著者に帰属します。*
