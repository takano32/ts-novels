# S.210 WP 移築のツール (scripts/wp/) の一覧

- 種類: 仕様
- 状態: 有効
- 出典: scripts/README.md の「wp/ (WordPress 移築の実装)」(2026-10-26 にここへ移した)
- 関連: T.7, T.8, T.9, T.34.2, T.16.3.3, T.22.3, T.24.3, S.171〜S.180, S.183, S.190, S.209, S.211, S.111, S.149

## wp/ (WordPress 移築の実装)

`make catalog` で T.7.1〜T.7.8 の段を一発実行できる (リポジトリ直下の Makefile)。
段の順番には意味がある — episodes.jsonl は catalog_build が新規に書き、
uncatalogued_build と repost_build が**自分の corpus 分だけ差し替えて**追記し、
episode_overrides が最後に人手の上書きを当て、terms/authors/works はその出来上がりを読む。
`make check` は書き込みなしの自己検査、
`make venv` は pykakasi / PyYAML 入りの `.venv` を作る (git 管理外)。
**2 回連続実行で全出力が同一**であることを確認済み。

- **wp/catalog_build.py** — 正規目録 lib1〜73 の 2,887 エントリと旧目録 lib01〜09 の差分 97 件を
  `catalog/episodes.jsonl` へ正規化 (T.7.1 / T.7.2、specs/S.171.md・S.172.md)。mailto をこの段階で除去し、受け入れ条件
  (件数・パース失敗 0・mailto 残存 0・survey 実測値との一致)を自己検査として同梱。
  provenance は git 履歴から算出。`--check` で書き込みなしの検査のみ、`--no-legacy` で
  `corpus=honkan`(正規目録 lib1–73 由来) のみ。

- **wp/slugs.py** — 恒久 URL になるローマ字 slug の候補生成 (pykakasi) と
  `catalog/slug_overrides.yml` の往復。`status: confirmed` の行は再生成でも上書きしない。
  機械の読みは幻覚しうる (城弾→shirodan、実際の板 id は johdan) ので候補どまり。
- **wp/terms_build.py** — 分類語彙を `catalog/terms.json` へ (T.7.3、specs/S.173.md)。ジャンル/種別/
  キーワードを NFKC・「」外し・？除去・同義語マップ (`catalog/*_map.yml`) で正規化し、
  原表記は raw_variants に保持。ts_world 14 本 (`catalog/world_map.yml` の板/ディレクトリ/
  題名規則) と ts_corpus 4 本もここで生成。当時の語彙定義ページ (genre.html /
  type_of_change.html / keyword.html) を term description に転用する。

- **wp/episode_overrides.py** — 話 1 件単位の人手上書きを episodes.jsonl に当てる
  (`catalog/episode_overrides.yml`)。**旧サイトの原本 `lib*.html` の誤植を catalog 側で直す
  唯一の場所**で、原本アーカイブ (Pages) は原本レイヤなので誤植もそのまま保つ、という分担。
  上書き前にあるはずの値を `expect:` に書かせ、合わなければ自己検査が落ちる。
  触れるのは author / kansou_slug / entry_role / title / homepage / date だけで、
  **結合キー episode_id と provenance には触れない**。

- **wp/authors_build.py** — 作者を `catalog/authors.json` へ (T.7.4、specs/S.174.md)。感想板 id を
  同定の鍵にし、共有シリーズ板 (華代ちゃん等 11 板) は作者板ではないので表示名で同定する。
  板 id の情報源は 2 つ — 目録の感想リンクと、**サイト自身の感想板一覧
  `~ts/kansou/bbs@log_.cgi` (290 板の「表示名 → 板 id」対応表)**。後者を足したことで
  「板はあるのに目録からリンクされていない」作者 8 名を機械の読みで採番する事故が消えた
  (自己検査「confirmed slug と板一覧の不一致 0」で再発を防ぐ)。yomi は lib-index-*.html の
  所属行から。板を持たない作者の slug は pykakasi 候補どまり。
  `slug_overrides.yml` で**複数の表示名に同じ slug** を書くと同一人物として併合し、
  `role: not-an-author` を書くとその表示名では作者を作らない。

- **wp/work_builder.py** — Episode → Work クラスタリング (T.7.5、specs/S.175.md)。6 段の根拠
  (シリーズタイトルナビ / series.html / タイトルページ / 話ナビ / ファイル名連番 / 題名接頭辞)
  を強い順に積み、どれで繋がったかを works.jsonl の evidence に残す。**Work は必ず 1 作者に
  閉じる** (共有世界のタイトルページは 69 名の話を 1 つに束ねてしまうため)。
  弱い根拠だけのクラスタは needs_review を立て `catalog/work_overrides.yml` に雛形を出す。
  `work_overrides.yml` の `split` / `detach` / `merge_into` / `confirmed` で人手でクラスタを直せる
  (specs/S.146.md・S.147.md。誤りがあれば catalog を書かずに止まる)。

- **wp/cluster_review.py** — 作品クラスタのレビュー資料 `catalog/review/clusters-YYYY-MM.md` を書く
  (T.24.3、specs/S.149.md)。題名の芯が食い違う連載を弱い根拠のものから並べ、裁定欄に一次案と
  work_overrides.yml に写したときの形を添える。`--since <リビジョン>` で slug が変わった作品の表を足す。

- **wp/secret_scan.py** — 配信される原本に CGI のソースや秘密値が混じっていないかの走査
  (T.16.3、specs/S.111.md)。`make check` が `--check` で走らせ、1 件でもあれば止まる。
  引数なしなら `catalog/reports/secret_scan.json` を書く。検証済みの許容は `scripts/wp/secret_scan_allow.yml`。

- **wp/uncatalogued_build.py** — 目録に載っていない収蔵物を拾う (T.7.8、specs/S.178.md・S.41.md)。
  `novel/` 配下の本文 3,818 のうち既収蔵でない分を `corpus=uncatalogued` で追加。メタは
  lib-index-*.html → 本文の「作：」行 → `<title>` → 同ディレクトリの作者、の順に解決し
  `metadata_source` に記録。落としたものは理由つきで `catalog/uncatalogued_excluded.jsonl` に。
- **wp/repost_build.py** — 作者本人の再掲から回収した本文を catalog に入れる (T.7.8、specs/S.179.md)。
  検証済みの pixiv 2 件 (雨女 = 既存エントリに本文と来歴を追加 / きらいなもの→ＧＷ =
  文庫未掲載として `corpus=extern-repost` で新規収録。本文は `reposts/*.txt`) と、
  bc-cafe.net の 3 点 (文庫未掲載として `corpus=extern-repost` で新規収録。本文はコミット済みの
  `reposts/repost__bcwiki__*.{txt,htm}` を source_path にして body_convert が読む。来歴は
  `reposts/repost__bcwiki__provenance.json`。T.34.2、S.204)。

- **wp/qa_report.py** — `catalog/reports/*.json` を集めて `catalog/QA.md` を書く
  (T.7.7、specs/S.180.md)。数字は各段のレポートからの転記だけで、ここでは再計算しない。

- **wp/docs_build.py** — 運営文書 (`comittee/` 21・`columns/` 2・`dialy/` 1)・`~ezpe/yasai/` 8・
  ギャラリー (`~yays/gallery/` の CG。画像実体 145) と手書きの解題 (`content/docs/*.html`) を
  `ts_doc` 用のペイロード `payloads-docs/<slug>.html` と `payloads-docs/_manifest.json` にする
  (T.10.5、specs/S.197.md)。原本 HTML を wp:html 1 ブロックに包み、相対リンク・画像は原本アーカイブの
  絶対 URL に書き換え、mailto は剥がす。出力 `catalog/reports/docs_build.json`。引数なしで
  `python3 scripts/wp/docs_build.py` (移設時の作り直しは S.82)。本番へは `wp ts sync-docs`。
  区分の表示名「文庫前史 (1999)」は S.105 で「にせ乙女パラダイス」に変える予定 (T.20)。

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
  配備は deploy.sh(T.8.3、specs/S.183.md)。リポジトリ直下の robots.txt は Wayback 誤回収物なので使わない。
- **wp/qa_phase3_check.py** — T.9.3 (specs/S.190.md)「無作為 100 話の原本併読 QA」の**独立検算**。
  本番ページ(キャッシュバスター付き)の本文と、リポジトリの原本 (source_path) を
  **別実装で**抽出して突き合わせる(標準ライブラリの `html.parser` を使い、
  body_convert.py / payload_build.py の正規化は流用しない = 同じバグを二度踏まないため)。
  判定は「本番本文が原本テキストの**連続部分列**か」。WP の表示フィルタ
  (`wptexturize` / `convert_smilies`) による字面差は `ok-texturize` として別勘定にする。
  出力は `catalog/reports/qa_phase3_check.json`。実測 (2026-08-30 最終): 100 話で ok 97 /
  ok-trimmed 1 / ok-image 2(ng 0・問題率 0.0%)。
