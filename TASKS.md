# TASKS — ts-novels の作業一覧

番号は `T.大.小.孫`。分ける必要の無いものは分けない。日付は主に作業した日 (コミット日)。
**この文書は要約と現在地のためのもの。** 仕様・決定・手順・判定基準・実測値はすべて docs/ 以下に置き、
各行は「何をやるか + 完了条件 + 参照先」にとどめる (この文書を丸ごと消しても失われる情報が無いようにする)。
完了した大項目は docs/worklog.md に写して消してよい (T.1〜T.14 は 2026-10-26 に写し済み)。
WordPress 移築の細部 (受け入れ条件・実測値) は進行台帳 `docs/wp-implementation-tasks.md`
の番号 (例: 台帳 1.5) で引ける。T.15 以降が未了。T.16〜T.28 は 2026-10-26 の所有者レビューへの対応。

---

## T.1 原本ミラーの構築 (第 1 次サルベージ) — 2026-06-29〜07-06

- [x] T.1.1 GitHub Pages の配信ワークフローを置く (07-06 に Node 24 対応版へ更新)
- [x] T.1.2 ts.novels.jp を Wayback から raw (Shift_JIS) で取得し、UTF-8 化とリンク書き換えを行う。README を書く
- [x] T.1.3 CommonCrawl から 102 ページを回収
- [x] T.1.4 www.novels.jp/~ts のコミュニティ層 645 ページを回収 (一時エラーの 8 ページを後で再取得)
- [x] T.1.5 作者サイトからの回収 (計 約 562 作品、全体 5,144 ファイルに)
  - [x] T.1.5.1 第 1 波 10 作 → 並列掃引で 95 作
  - [x] T.1.5.2 第 2 波 13 作 + フラット期・旧作 16 作
  - [x] T.1.5.3 作者が分かっている連載の欠け 123 章を補う
  - [x] T.1.5.4 城弾さんの全作品を回収
- [x] T.1.6 cat2 (本当に失われた作品) を回収。ウォーリアー・ガール、まほろばの虹、
      あむぁい作品 (作者ブログ)、蒼い瞳のミルフィア、妖精的日常生活 第 14 話。欠落ページを網羅的に分類した
- [x] T.1.7 BBS のスレッド固定リンク (res=) 3,357 ページ + 2ndbbs スレッド・ファイル名の別名・チャットのダミー
      (計 8,546 コンテンツ / 内部リンク 100,161 本)
- [x] T.1.8 ヴィスティア 第 11 章を CC-MAIN-2012 (CommonCrawl の旧クロール) から回収

## T.2 全世代を統合した回収 (第 2 次サルベージ) — 2026-08-19〜08-21

- [x] T.2.1 Apache 標準アイコンの復元。フラット期の novel/ URL の別名を張る
- [x] T.2.2 姉妹ドメインのコミュニティ層 (novels.name / ts.raa0121.info) 526 ページ
- [x] T.2.3 作者の移転先サイトから回収: 大原野山城守武里 15 章、天爛「二代目はお嬢さま？」、
      シャイニングヘヴン ミラー 65 点。サーバが差し込んだ広告スクリプトは除去
- [x] T.2.4 ts-novels.jp (2018 年の再建版「少年少女文庫改」) のトップ・ライブラリ・検索
- [x] T.2.5 作者サイトから失われた画像 298 点
- [x] T.2.6 旧世代サイト ~yays (八重洲メディアリサーチ) 4,077 点。フラット期の別名 4 件を本物に置き換え、
      まだ稼働しているサーバから 12 点を追加
- [x] T.2.7 最旧世代 www2.tomato.ne.jp/~ezpe 1,527 ページ (創作ノート noteky ほか) と、そこへのリンクのローカル化
- [x] T.2.8 文庫の前史: www2.sts.co.jp/~yaji の相互批評ボード・トランスギャルズ会議室
- [x] T.2.9 Wayback に無い 20 ページを CommonCrawl から回収
- [x] T.2.10 ウェブ魚拓から回収: メイド執事さん、ヴィスティア第 9 章、かたつむりに砂糖
- [x] T.2.11 探索ワークフローで 18 ページ、大柳朱さんの なろう から 3 作、toshi9 の現存サイトと pixiv 再掲から 6 ページ
- [x] T.2.12 README に反映 (計 15,137 ファイル / 解決した内部リンク 139,834 本)

## T.3 第 3 次サルベージとツールの整備 — 2026-08-29〜08-30

- [x] T.3.1 サルベージ用ツールを scripts/ にまとめる (監査、CDX の取得と回収、Wayback・現存サイト・megalodon の取得器、
      衝突を避ける取得器、再試行、リンクの付け替え、文字コード修正の規則、TimeTravel・CommonCrawl のプローブ)
- [x] T.3.2 Wayback から raw で 2,015 点 (~yays/library 全体・~yaji・~bbs・姉妹ドメインの穴)、変換 1,502 点
- [x] T.3.3 深さ違い・別名のリンク 1,766 本を修正し、367 ページを回収済みファイルに付け直す
- [x] T.3.4 yays の写しで 52 点の穴を埋める + 再試行で 16 点
- [x] T.3.5 厳密な監査を README に反映 (計 17,218 ファイル / 解決 111,121 本、未解決 5,067 本はすべて分類済み)

## T.4 第 4 次サルベージ (作者の現存サイトから直接) — 2026-08-30

- [x] T.4.1 aetherworks.org (うたのつき Wiki の後継) 470 点、EUC-JP の PukiWiki を UTF-8 化
- [x] T.4.2 ツールの集約 (archive.today、CommonCrawl の zipnum、URL の逆写像、単一ホストの CDX)。
      作者サイトの精査・転載探索のワークフローと、喪失作品の対応表
- [x] T.4.3 bc-cafe.net の BC-Wiki 旧データ 297 点 + スキン資産 20 点
- [x] T.4.4 reposts/: きりか進ノ介さんの現行 wiki から未収蔵の 3 本 (台帳 8.4。holding の 13 点を全部同定)
- [x] T.4.5 CommonCrawl の全 127 コレクションを走査し終えたことを記録。README・data-inventory に反映 (全 18,006 ファイル)

## T.5 WordPress 移築の設計 — 2026-08-30

- [x] T.5.1 8 つの視点からの調査で実測データを保全し、最終統合設計 v1.0 を書く
- [x] T.5.2 v1.1: 移築先 Xserver for WordPress (novels.xwp.jp) の実測にもとづいて再設計
      (.cgi は一律 403 なので、原本アーカイブは GitHub Pages に恒久的に併存させる)
- [x] T.5.3 v1.2: 本文を Markdown を正とする形に揃える (無損失を証明し、不合格は raw に戻す) +
      感想板は読み取り専用の近代ビューにする。どちらもプローブで実現性を確認
- [x] T.5.4 v1.3: 原運営者 (八重洲メディアリサーチ) 公認のリブートとして位置付け直す (要決定 Q3/Q4/Q5/Q7 の回答を反映)
- [x] T.5.5 v1.4: 最大掲載原則。事前保留の例外は設けない (所有者決定)
- [x] T.5.6 v1.5: 最初から公開する前提にする (全域 noindex の段階解禁は撤回)
- [x] T.5.7 文書の基盤: ドキュメント地図 (docs/README.md)・用語集・収蔵物の目録 (data-inventory)・本番環境の参照文書 (environment)。
      呼び分け「本館/別館/旧館」を確定 (内部用語で、利用者には見せない)
- [x] T.5.8 作者への連絡と原本照会の計画 (docs/author-outreach.md)。運営移管の経緯を反映し、連絡文を裁定済みの方針に更新
- [x] T.5.9 Opus が実行するための進行台帳 v1.1 (docs/wp-implementation-tasks.md)。コードは Fable の担当、検算は別の書き手、と決める

## T.6 Phase 0 — 方針とガバナンス (台帳 0.x) — 2026-08-30

- [x] T.6.1 takedown/denylist.yml のスキーマと README (台帳 0.1)
- [x] T.6.2 削除ランブック v0: 72h SLA、四層 + 掲示 (台帳 0.2)
- [x] T.6.3 公開ランブック v0 (台帳 0.3)
- [x] T.6.4 本番 robots.txt の草案: AI 学習クローラは拒否、ia_archiver は許可 (台帳 0.4)
- [x] T.6.5 要決定事項への所有者の回答 (台帳 0.5)

## T.7 Phase 1 — catalog の確立 (台帳 1.x) — 2026-08-30

- [x] T.7.1 catalog_build.py: 目録 2,887 話 → episodes.jsonl (台帳 1.1)
- [x] T.7.2 旧目録 lib01〜09 の差分パース (台帳 1.2)
- [x] T.7.3 分類語彙の正規化と terms.json (台帳 1.3)
- [x] T.7.4 作者の同定と authors.json (台帳 1.4)
- [x] T.7.5 Episode → Work のクラスタリング (台帳 1.5)
  - [x] T.7.5.1 1.5b の人間レビュー資料。作者 slug 40 件と同名 2 組を裁定して confirmed にする。作者は 342 → 322 名
- [x] T.7.6 本文の Markdown 変換: 3,641 話、無損失を証明 (台帳 1.6)。
      html5lib の第二実装で独立に検算し、変換器のバグ 4 種を修正 (99.95%)。bodies/ を git 管理から外す
- [x] T.7.7 make catalog と QA レポート (台帳 1.7)
- [x] T.7.8 目録外の収蔵 859 話・作者による再掲 2 作を収録 (台帳 1.8 / 1.9)
- [x] T.7.9 板ログの原本が未回収の作者 32 名をレポートに記録

## T.8 Phase 2 — WP の骨格とメタの全量投入 (台帳 2.x) — 2026-08-30

- [x] T.8.1 mu-plugin の登録部 (CPT・タクソノミー) とサブディレクトリ用ローダ (台帳 2.1)
- [x] T.8.2 `wp ts` コマンド群: sync-terms / import / verify ほか (台帳 2.2)
- [x] T.8.3 deploy.sh: 既定は dry-run、push 済みのコミットに限る、--delete は使わない (台帳 2.3)
- [x] T.8.4 パイロット投入 100 件 → 全量メタ投入。実測で出た不具合 5 件 (A)〜(E) を修正し、子投稿の slug を決定的に生成 (台帳 2.4 / 2.5)
- [x] T.8.5 作品 URL の基底を /works/ から /novel/ に変える (ユーザ裁定)。照合用の異表記 lookup_variants
- [x] T.8.6 .htaccess のマーカーブロック管理 htaccess_block.py (台帳 2.6)
- [x] T.8.7 novel/ の画像 1,196 点を assets/annex-img/ に配備 (台帳 2.7)

## T.9 Phase 3 — 本文の投入 (台帳 3.x) — 2026-08-30

- [x] T.9.1 MD → Gutenberg ペイロード生成器 payload_build.py と本文投入 (台帳 3.1)
- [x] T.9.2 img を annex-img 参照に書き換え、eyecatch を設定。作者再掲のプレーンテキスト経路 (台帳 3.2)
- [x] T.9.3 無作為 100 話を原本と並べて読む QA。指摘 2 件 (画像記法の漏れ 17 話・表示時の字面変換) を直し、再実行で問題率 0.0% (台帳 3.3)
- [x] T.9.4 wp ts verify で書誌メタの被覆を照合する (台帳 3.4)

## T.10 Phase 4 — テーマ・索引・読書 UX (台帳 4.x) — 2026-08-30

- [x] T.10.1 表示部品 render.php (書誌カード・話ナビ・パンくず) と話の並び順 menu_order。連載の親に子の分類を併せて付ける
- [x] T.10.2 テーマ ts-bunko v0.1 とリーダー UI (台帳 4.1 / 4.2)
- [x] T.10.3 索引群と正式トップ (台帳 4.3 / 4.4)。トップはリロードごとにランダム、「いまの一作」は毎時交替 (ユーザ裁定)
- [x] T.10.4 検索のファセット絞り込み (台帳 4.5)
- [x] T.10.5 固定ページ 4 枚と wp ts sync-pages、運営文書・前史・ギャラリー・企画の解題 (台帳 4.6〜4.9)
- [x] T.10.6 検収で出た指摘 3 件を修正 (索引への導線・h1・検索とファセットの文脈保持)
- [x] T.10.7 CP-4 のレビュー資料 docs/cp4-review.md と、やり直しランブック docs/rebuild-runbook.md

## T.11 公開前レビュー 1 回目 — 2026-08-30 夜

- [x] T.11.1 6 つの視点のレビューで 15 件を確定し、すべて修正・配備 (f04d7a5e)。
      取り下げ機構が実質無効だった件、本文 47 本に実アドレスがあった件、索引に 324 名中 21 名しか出なかった件、
      閉じない table、PHP 8 の TypeError、オススメの環のクエリ過多
- [x] T.11.2 設計書の誤った主張を訂正 (mailto は構造的に入らないわけではなかった)

## T.12 公開前レビュー 2 回目 — 2026-08-31 未明

- [x] T.12.1 作者が公開停止を申し出ていた 9 作品を取り下げる (TD-2026-0001〜0009)。
      再発防止に、停止の告知を catalog が構造化して拾うようにし、make check に検査 2 本を足す
- [x] T.12.2 apply-takedown の修正 (draft 化を手動編集と誤検知する件、取り下げ済みの記録の自己修復、公開時の形の URL で記録)
- [x] T.12.3 オフ会レポート 2 件 (私人の顔写真 15 枚) を一時非公開にし、画像も 410 にする (TD-2026-0010〜0013)
- [x] T.12.4 ユーザ裁定: 索引の入口と検索窓を右サイドバーに常設する。表示文言は「索引」「開いた」
- [x] T.12.5 読者視点の指摘 4 件 (読了目安・索引の件数・失われた内部リンク・感想板リンク) と、手順書の破壊的な誤りを訂正
- [x] T.12.6 結果を台帳と CP-4 資料に記録

## T.13 作業環境の移設 — 2026-10-26

- [x] T.13.1 移設手順書 docs/relocation.md。venv の版を固定 (slug = 恒久 URL を守るため)
- [x] T.13.2 新しい機 a1-free で再現を確認。make catalog の差分 0・make check の NG 0・payload は無損失、
      本番とは checksum で 0 件差
- [x] T.13.3 ~/.ssh/config に頼らない本番接続 scripts/wp/novels-ssh。deploy.sh と手順書を切り替える

## T.14 タスク一覧 TASKS.md — 2026-10-26

- [x] T.14.1 これまでの作業を T 番号で整理する (この文書)

## T.15 未了 (これから)

- [ ] T.15.1 👤 CP-4: サイト所有者のレビュー。通るまで Phase 5 以降に進まない
  - [x] T.15.1.1 2026-10-26 レビュー受領 (所有者コメント 11 件 + 質問 1 件)。解析と対応仕様は OF、方針は設計 v1.6。対応は T.16〜T.28。
        「少し修正を入れればロールアウト可」の評
  - [ ] T.15.1.2 👤 必須分 (T.16〜T.21 と T.23) を終えたら通過の可否を所有者に確認
- [ ] T.15.2 👤 未決の判断
  - [ ] T.15.2.1 原本アーカイブに残る第三者のメールアドレス・IP の扱い (docs/annex-privacy-decision.md、推奨は A: 伏せ字)
  - [ ] T.15.2.2 オフ会レポート 2 件の扱いと /removed/ への掲示
- [ ] T.15.3 👤 作者への連絡の発送と完了確認 (台帳 3.5 / 7.1、docs/author-outreach.md)。**発送前に T.21.2 (連絡文から 72 時間を外す) を済ませる**
- [ ] T.15.4 bc-cafe.net の回収分を catalog に反映する (台帳 8.4 の積み残し)
- [ ] T.15.5 Phase 5 — 別館との連携: path_map、Pages へのビルド注入 (mailto 除去・バナー・noindex・denylist) (台帳 5.x)。
      T.16.5 (.cgi のダウンロード解消) と T.26.7 (取り下げ一覧の REST 連携) はここに乗る。T.18.3 (Marie の別館除外) は暫定手順で先行
- [ ] T.15.6 Phase 6 — 感想板の近代ビュー /boards/・道場 /dojo/・創作ノート (台帳 6.x)
- [ ] T.15.7 Phase 7 — 公開ゲート: 削除リハーサル・sitemap・noindex 境界の確認・公開後の verify (台帳 7.x)。削除リハーサル (7.2) は T.26.8 で実流しする
- [ ] T.15.8 定常運用: db export・年 2 回のリンククロール・再構築演習 (台帳 8.1〜8.3)

---

**T.16〜T.28 は 2026-10-26 の所有者レビューへの対応。** 1 大項目 = 1 つのまとまった成果、着手順。
**CP-4 通過に必須なのは T.16〜T.21 と T.23**。T.24〜T.28 は所有者の裁定を得ながら Phase 5 以降と並行してよい。
参照の略記: **OF** = docs/owner-feedback-2026-10.md (解析と決定。§n は節番号)、**VR** = docs/visibility-roles-spec.md、
**TF** = docs/takedown-form-spec.md、**VS** = docs/versions-spec.md、**CO** = docs/cluster-overrides-spec.md。
各行は「やること → 完了条件 (参照)」だけを書き、仕様・判定基準・手順・実測値は参照先の docs に置く (この文書を消しても失われない)。
小タスク T.n.m は 判断 (👤)・実装・配備・検証・文書 のまとまり、孫 T.n.m.k は 1 セッションで着手→検収できる末端。依存は「← T.x.y」。
本番に書く作業は毎回 docs/environment.md §8 (復旧点・dry-run 先行・キャッシュバスター) に従い、配備は `scripts/wp/deploy.sh --apply` (push 済みコミットのみ)。

## T.16 CGI の配信実態の点検と後始末 (OF §10)

- [x] T.16.1 実態の確定 (2026-10-26): 配信物 18,013 ファイルの全数走査で ソース 0・秘密 0。「落ちてくる」= Pages の `application/octet-stream` (OF §10 の表)
- [ ] T.16.2 サーバパスを晒すエラー出力 2 ファイルの削除 (OF §10 仕様 2)
  - [ ] T.16.2.1 `git rm` で 2 ファイルを削除 → `git grep -l -E 'public_html|WWWROOT' -- . ':!scripts' ':!docs'` が 0 件 → コミット
  - [ ] T.16.2.2 push 後に 2 URL が Pages で 404 (curl)。docs/data-inventory.md の `www2.sts.co.jp/` と `~yaji/` の件数を 1 ずつ減らす
- [ ] T.16.3 秘密走査 `scripts/wp/secret_scan.py` の恒久化 (OF §10.5)
  - [ ] T.16.3.1 OF §10.5 の規則どおりに実装 → 現状のリポジトリで 0 件・終了コード 0、`catalog/reports/secret_scan.json` が出る
  - [ ] T.16.3.2 Makefile の `check` に組み込む → `make check` が通る。`#!/usr/bin/perl` 1 行の疑似ファイルを置いた一時ツリー (`--root`) で非 0
  - [ ] T.16.3.3 scripts/README.md に 1 行追記 (台帳 普遍ルール 3)
- [ ] T.16.4 docs/data-inventory.md §7 の `.cgi` の行に点検結果を 1 行追記 (OF §10 仕様 4)
- [ ] T.16.5 `.cgi` 保存画面が Pages でダウンロードになる件の解消 (OF §10 仕様 3) ← T.15.5 (Phase 5.2) と同時
  - [ ] T.16.5.1 検証用の別リポジトリ (GitHub Pages) で `x.cgi/index.html` 方式を試す → 301・`text/html`・`<base href>` で相対リンクが解決することを curl で記録。NG なら代案を OF §10 に追記して判断を仰ぐ
  - [ ] T.16.5.2 ← T.16.5.1: annex_inject に組み込む → Pages で `.cgi` の URL が text/html、画像参照が切れていない (lychee サンプル 50 本)
- [ ] T.16.6 👤 ゲーム EXE 分割 (2 系統) と LZH 5 本の配信可否 (OF §10 未決)。停止なら T.16.2 と同じ手順で削除 (git 履歴には残す)

## T.17 取り下げ窓口の暫定修復 — 公開面 3 枚 (OF §11-a, §12)

- [ ] T.17.1 👤 窓口メールアドレスと暫定文面 (TF §11 の案を期限なしにしたもの) の承認
- [ ] T.17.2 ← T.17.1: content/pages/takedown.html から GitHub Issue の段落を消し、メール窓口と暫定文面に → `grep -c -E 'Issue|72' content/pages/takedown.html` = 0
- [ ] T.17.3 content/pages/about.html と removed.html の「72 時間以内」を暫定文面に → `grep -c 72 content/pages/*.html` が全ファイル 0
- [ ] T.17.4 配備: deploy.sh --apply → 本番 `wp ts sync-pages` が updated=3 → curl (`?_cb=`) で 3 ページに `Issue` と `72` が無くメール窓口が出る → 👤 キャッシュクリア依頼

## T.18 Marie 氏の作品の取り下げ (OF §3)

- [ ] T.18.1 👤 確認: 申し出の年 / 名前の掲示 / 同居 2 ファイルの扱い / 感想板 30 ファイルの扱い / git 履歴の除去 (OF §3 未決・§13-3)
- [ ] T.18.2 WP 側 (既存機構。T.18.1 の「名前の掲示」だけ要る)
  - [ ] T.18.2.1 takedown/denylist.yml に TD-2026-0014 を OF §3 仕様 1 どおりに追加 → `make check` OK
  - [ ] T.18.2.2 scripts/wp/htaccess_block.py の `ssh` 直書きを `scripts/wp/novels-ssh` 経由に (VR §4) → dry-run が現行 18 行の 410 を表示
  - [ ] T.18.2.3 本番: `wp db export` → `wp ts apply-takedown --dry-run` が 33 件 → 実行 → `wp post list --post_type=ts_work --post_status=draft --format=count` = 51
  - [ ] T.18.2.4 本番: `htaccess_block.py --install` OK → 取り下げ URL 2 本が 410・`/authors/marie/` 200 で作品 0 件・`/about/` 200 (OF §3)
  - [ ] T.18.2.5 content/pages/removed.html に 1 行 (T.18.1 の文言) → sync-pages → curl 確認。denylist の `applied` 3 層に日付・`status: applied` → コミット
- [ ] T.18.3 原本アーカイブ側 ← T.18.1 (removal-runbook ③ の暫定手順 = リポジトリから削除)
  - [ ] T.18.3.1 OF §3「取り下げ対象の一覧」の本人ファイル 28 (+ T.18.1 で決めた分) を `git rm` → `make catalog && make check` OK (QA.md で source_exists=False の 28 話を確認)
  - [ ] T.18.3.2 push → Pages で代表 3 本が 404 (curl)。denylist の `annex_excluded` に日付・`status: verified` → コミット
- [ ] T.18.4 👤 git 履歴の除去は依頼があった場合のみ (removal-runbook ④)。T.18.1 で「行う」なら別の大項目として起票

## T.19 サイドバーの改修 — 「オススメの環」を外し「オーナー作品集」を足す (OF §1, §4)

- [ ] T.19.1 👤 判断: `/index/osusume/` ページ自体の要否 / 項目名 / `/authors/yays/` の説明文 (OF §13-2, §13-4)
- [ ] T.19.2 render.php `ts_bunko_sidebar()`: 「オススメの環」を外し「オーナー作品集」→ `/authors/yays/` を足す (OF §1, §4 仕様) → `php -l` OK
- [ ] T.19.3 (T.19.1 で一覧も消す場合) indexes.php の索引種別から `osusume` を外す → `/index/osusume/` 404・他の索引 200
- [ ] T.19.4 (T.19.1 で説明文を出す場合) `catalog/author_notes.yml` → authors.json `description` → `sync-terms` (OF §4 仕様) → `make check` OK、`yays` に description
- [ ] T.19.5 配備: deploy.sh --apply (+ `wp ts sync-terms`) → `/about/` の HTML に `オススメの環` 0 回・`オーナー作品集` 1 回以上 (href `/authors/yays/`)、`/authors/yays/` 200 → 👤 キャッシュクリア依頼

## T.20 「文庫前史 (1999)」→「にせ乙女パラダイス」(OF §5)

- [ ] T.20.1 👤 判断: 年の表記 / URL の変更 / 解題 1 行の文言 / 管理人名義の記載 (OF §5 未決)
- [ ] T.20.2 ← T.20.1: indexes.php の区分見出し・解題 1 行・年表脚注を OF §5 仕様 1〜2 どおりに → `php -l` OK
- [ ] T.20.3 scripts/wp/docs_build.py のラベルを揃え `docs_build.py` を再実行 → `payloads-docs/_manifest.json` の prehistory 8 本の `section_label` が新ラベル (本番 sync-docs は skipped でよい。OF §5)
- [ ] T.20.4 配備: deploy.sh --apply → `/index/docs/` の `sec-prehistory` 見出しが新ラベルで解題が続く、`/index/timeline/` に `文庫前史` が無い、`/docs/prehistory-nyotai/` 200 → 👤 キャッシュクリア依頼
- [ ] T.20.5 docs/glossary.md 世代①・「旧館」行と docs/data-inventory.md の `yasai/` 行を OF §5 仕様 4 どおりに訂正 → `grep -n 前史 docs/glossary.md docs/data-inventory.md` の残りが `~yaji` 関連だけ

## T.21 「72 時間」の文言撤去 — 公開面以外 (OF §11, §12) ← T.17

- [ ] T.21.1 文言の確定 (TF §11 の案。「10 日」は T.26.1 の裁定に従い、未裁定なら「一定期間の後」) → OF §12 に確定文を 1 行追記
- [ ] T.21.2 docs/author-outreach.md の連絡文と説明 (OF §12 の表) を T.21.1 の文に → **T.15.3 の発送前に必ず**
- [ ] T.21.3 docs/removal-runbook.md の SLA 節・見出し・④ の文から時間の約束を外す (OF §12 の表。手順本体の改訂は T.23.6)
- [ ] T.21.4 OF §12 の表の残り (denylist コメント・takedown/README・glossary・docs/README・台帳の現行ルール 2 箇所・commands.php コメント・cp4-review ⑪) を同趣旨に
- [ ] T.21.5 検査: `grep -rn -E '72 ?(時間|h)' docs takedown content mu-plugins TASKS.md` の残りが OF §12 で「残す」とした箇所 (履歴と、撤回を説明する文書) だけ

## T.22 台帳への展開と資料の追従 (OF §14)

- [ ] T.22.1 docs/cp4-review.md: `ts_catalog_commit` を本番値 `6bb57472` に、§3 ⑪ に v1.6 注記、末尾に「7. 所有者レビューの結果」(11 件 + 質問の 1 行要約と OF へのリンク)
- [ ] T.22.2 docs/wp-implementation-tasks.md: CP-4 節の `ts_catalog_commit` 訂正と「対応は TASKS T.16〜T.28」、T.23〜T.27 の受け入れ条件を Phase 4.5「所有者レビュー対応」として展開 (各行に TASKS の番号と VR/TF/VS/CO の節)
- [ ] T.22.3 scripts/README.md: 未記載の `docs_build.py` を追記。新設スクリプト (secret_scan / cluster_review / versions_build) は作った時点で追記
- [ ] T.22.4 docs/environment.md §8 の失効した「公開解禁まで全域 noindex」の行を削除 (v1.5)
- [ ] T.22.5 docs/README.md の地図と「TASKS.md と docs の分担」を、新設した文書に合わせて最新に保つ (大項目を 1 つ終えるごとに見直す)

## T.23 運営委員ロールと可視性の基盤 — 3/6/11 の前提 (VR)

- [ ] T.23.1 実測: nginx キャッシュとログインの関係を VR §5 の手順で → 結果を docs/environment.md §3 に表で追記 (試験ユーザは削除)
- [ ] T.23.2 判断 ← T.23.1: WordPress が 410 を返す方式か `.htaccess` の Cookie 条件方式か (VR §4) → VR §4 と設計 v1.6 に結論を 1 行追記
- [ ] T.23.3 mu-plugin の実装 ← T.23.2
  - [ ] T.23.3.1 `includes/roles.php`: ロール 2 種と `wp ts role-sync` (VR §1) → 2 回目が created=0 / updated=0
  - [ ] T.23.3.2 `includes/visibility.php`: 可視性メタ・410/301・帯・題名接頭辞 (VR §2〜§3)、`themes/ts-bunko/ts-410.php` → `php -l` OK
  - [ ] T.23.3.3 `commands.php` `apply_takedown` を VR §6 どおりに (draft → private、復帰、dry-run 出力) → dry-run の件数が VR §6 の形式
  - [ ] T.23.3.4 `scripts/wp/htaccess_block.py` を VR §4 の v1 の内容に (WP URL の gone を書かない、301 行を読む) → dry-run に `/novel/` の gone 行が無い
- [ ] T.23.4 配備と移行: `wp db export` → deploy.sh --apply → `wp ts role-sync` → `wp ts apply-takedown` → private = 51・draft = 0 → `htaccess_block.py --install` → `wp ts verify` OK
- [ ] T.23.5 検証: VR §7 の表をすべて満たす (試験ユーザで帯を確認し削除)
- [ ] T.23.6 文書: VR §8 の 5 文書 (removal-runbook v1・glossary・environment §8・takedown/README・publish-runbook) を更新
- [ ] T.23.7 👤 所有者のアカウント発行は T.27.5 で (種別の回答が要る)

## T.24 作品クラスタの手動修正機構とレビュー (CO)

- [ ] T.24.1 `scripts/wp/work_builder.py`: overrides スキーマ v2 (`split` / `detach` / `merge_into` / `confirmed`) と `--check` の検証 (CO §1〜§2) → 既存の work_overrides.yml で `make catalog` の出力が不変 (差分 0)
- [ ] T.24.2 題名の芯の追加規則 (CO §3) → `make catalog` 後に末尾が助数詞の作品題名 0 件。slug が変わった作品数を記録
- [ ] T.24.3 `scripts/wp/cluster_review.py` (CO §4) → `catalog/review/clusters-2026-10.md` に 71 作品 (弱根拠 39 が先頭)、一次案つき。scripts/README.md に 1 行
- [ ] T.24.4 👤 裁定: 71 作品の ○ / 分割 / 切り離し / 併合 (CO §5。一次案の承認でよい)
- [ ] T.24.5 ← T.24.4: 裁定を work_overrides.yml に転記 (CO §5) → `make catalog && make check && make verify` OK、work_builder.json の works・needs_review・orphan を記録 → コミット
- [ ] T.24.6 URL の付け替え表 (CO §6): 本番で `wp ts export-pathmap > catalog/path_map.json` を先に取る → 再投入後の差分から `catalog/redirects.yml` → htaccess_block.py が 301 行を出す
- [ ] T.24.7 判断: 再投入の方式 (a) 👤 全消し再構築 か (b) `import --prune` (CO §7) → 選んだ方の実装/手順を整える
- [ ] T.24.8 本番 ← T.24.7: `wp db export` → deploy.sh --apply → 再投入 → `wp ts verify` OK → `htaccess_block.py --install` → 旧 URL 3 本が 301 で新 URL 200 (CO §8) → 👤 キャッシュクリア

## T.25 版違いの整理 — 新しい版を残し旧版は運営委員のみ (VS) ← T.23, T.24

- [ ] T.25.1 👤 判断: 閾値 (0.8〜0.95 を自動にするか) / 旧目録版の非表示 / 大改稿の扱い (VS §3、OF §13-6)。未回答なら既定で進める
- [ ] T.25.2 `scripts/wp/versions_build.py` (VS §2〜§4) と Makefile への組み込み
  - [ ] T.25.2.1 束ねと判定 (VS §2〜§3) → `catalog/reports/versions_build.json` の件数が VS §8 の基準値と整合 (組 303・自動 209・要確認 89、閾値既定)
  - [ ] T.25.2.2 出力と `--check` (VS §4) → works.jsonl に `superseded_by` / `all_superseded`、`make check` OK、`versions_overrides.yml` に review の雛形
  - [ ] T.25.2.3 work_builder.py の alias 正本を「最新」の規則に (VS §3) → `make catalog` で alias 71 話の正本が規則どおり
  - [ ] T.25.2.4 catalog/README.md と scripts/README.md に追記 (VS §4)
- [ ] T.25.3 👤 要確認の組 (既定 89) の `decision` を記入 → `make catalog` → 件数を記録してコミット
- [ ] T.25.4 import とテーマ ← T.23.3
  - [ ] T.25.4.1 `commands.php`: `superseded_by` → private + メタ、無ければ publish、`all_superseded` の親、`HASH_VER` v6 (VS §5) → `--dry-run` 2 回目が created=0 / updated=0
  - [ ] T.25.4.2 `visibility.php` / `render.php`: 旧版の 301・帯・新版の「旧版あり」(VS §5) → `php -l` OK
  - [ ] T.25.4.3 `wp ts verify` に visibility 別の件数 (VS §5)
- [ ] T.25.5 本番: `wp db export` → deploy.sh --apply → `wp ts import --bodies=…/payloads` → `wp ts verify` OK → VS §7 の表を満たす → 👤 キャッシュクリア
- [ ] T.25.6 文書: VS §9 の 4 箇所 (data-inventory・cp4-review の収録規模、設計 v1.6、rebuild-runbook §1)

## T.26 取り下げフォームと 10 日後の自動適用 (TF) ← T.23

- [ ] T.26.1 👤 裁定: TF §12 の 6 パラメータ → TF §12 の表に結果を書き、定数を決める
- [ ] T.26.2 ← T.26.1: TF の既定値を裁定で置き換え、文面 (TF §11) を確定 → OF §12 の確定文と一致
- [ ] T.26.3 mu-plugin の実装 (`includes/takedown-request.php`)
  - [ ] T.26.3.1 CPT `ts_takedown_request` とメタ (TF §2) → `wp ts reset` の TYPES に含まれないことをコードで確認
  - [ ] T.26.3.2 フォームと検証・レート制限 (TF §1) → TF §13-5 のスパム系 4 ケースが期待どおり
  - [ ] T.26.3.3 確認リンクと失効 (TF §3) → `received` と `auto_apply_at` が T0 + 10 日
  - [ ] T.26.3.4 cron フック `ts_takedown_tick` と非公開化の処理 (TF §3〜§4, §7) → `wp cron event run ts_takedown_tick` で `applied`・対象が private/410
  - [ ] T.26.3.5 運営委員の操作 4 つ (TF §5) → 承認・却下・復帰・期限変更が記録つきで動く
  - [ ] T.26.3.6 `/removed/` の動的生成 `[ts_removed_list]` と removed.html の縮約 (TF §8) → 既存 9 件 + Marie が同じ文言で出る
  - [ ] T.26.3.7 `wp ts export-takedowns` (TF §9) → 出力を denylist に追記して `make check` OK。publish-runbook に 1 手順
  - [ ] T.26.3.8 通知 5 通 (TF §6) → 本番で自分宛に 1 通送って到達
- [ ] T.26.4 cron の実行基盤 (TF §7): サーバ crontab (控えを先に) と 👤 `DISABLE_WP_CRON` → `wp cron event list` で `ts_takedown_tick` の次回時刻が進む
- [ ] T.26.5 content/pages/takedown.html を本番用の文面に (TF §11。T.17 の暫定を置き換え) → sync-pages
- [ ] T.26.6 配備と検証: `wp db export` → deploy.sh --apply → `wp ts role-sync` → sync-pages → TF §13 の 1〜5・7 をすべて満たす
- [ ] T.26.7 (任意、Phase 5.2 と同時) REST `/wp-json/ts/v1/withdrawn` と deploy-pages.yml の取り込み (TF §10) ← T.15.5
- [ ] T.26.8 削除リハーサル (台帳 7.2) をこの経路で実流し → docs/removal-runbook.md を実測で更新

## T.27 about の所有者リライトと所有者アカウント (OF §9, §Q)

- [ ] T.27.1 👤 原稿と経路 (A/B)・運営者名義・運営委員会の正式名称・glossary 等への反映可否・アカウント種別と 2FA (OF §13-1, §13-9)
- [ ] T.27.2 `wp ts export-pages` (OF §9 仕様) → export → sync-pages が skipped=4 (往復で差分 0)
- [ ] T.27.3 ← T.27.1: content/pages/about.html に原稿 (経路 B なら T.27.2 で書き戻す) と `owner-authored` 注記 (OF §9) → `grep -c -E '72|本館|別館|旧館'` = 0 → sync-pages → curl 確認
- [ ] T.27.4 ← T.27.1: docs/glossary.md「少年少女文庫」定義と docs/author-outreach.md §5 を所有者の説明 (文庫運営委員会) に訂正
- [ ] T.27.5 👤 所有者アカウントの発行 ← T.23.4, T.27.1 (VR §1 の運用) → 所有者が取り下げ済み作品 1 本を閲覧できる
- [ ] T.27.6 docs/README.md「作業のしかた」2 と台帳 普遍ルール 1 に、所有者の固定ページ・解題編集の例外 (OF §Q) を追記

## T.28 アイコン類 (OF §8) — 最後

- [ ] T.28.1 👤 素材 (所有者提供か既製 MIT か) と置き場 (OF §8)
- [ ] T.28.2 themes/ts-bunko にスプライトと CSS、作品カード・索引への適用 (OF §8 仕様。外部 CDN なし) → `php -l` OK、スプライト 1 本だけ増える
- [ ] T.28.3 配備 (deploy.sh --apply) → トップ・作者ページ・索引で表示、👤 ダークモードで判読できる
