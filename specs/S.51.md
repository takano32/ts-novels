# S.51 ツリー別目録

- 種類: 実測
- 状態: 有効
- 出典: docs/data-inventory.md の「2. ツリー別目録」
- 関連: T.16.2.2, T.34, S.211

## 2. ツリー別目録

「WP 扱い」欄の意味 — **WP サイト**=WordPress (novels.xwp.jp) に投稿として載る /
**原本アーカイブ**=GitHub Pages に原パスのまま残す(WP には載せない) /
**参照のみ**=どちらの公開物でもない(生成物・ツール・作業データ)。
S.41「最大掲載原則」により、**原本アーカイブに残るものも WP に載せられるものは載せる**方針。

| パス | 何か | ファイル数 | 内 HTML | 内 .cgi | 内 画像 | 出所 | WP 扱い |
|---|---|---:|---:|---:|---:|---|---|
| `novel/` | **本文**。ts.novels.jp の作品ファイル本体 | **5,017** | 3,817 | 0 | 1,196 | Wayback 主体 + CommonCrawl・魚拓・作者サイト | WP サイト(主対象) |
| `~yays/` | 第 0/2 世代 `www14.big.or.jp/~yays/` 丸ごと | **5,469** | 1,488 | 2,955 | 900 | Wayback + 生存サーバ直接 | 原本アーカイブ(ギャラリーはWP サイトへ) |
| `~ts/` | `www.novels.jp/~ts/` = 本体期の交流層 | **4,008** | 23 | 3,984 | 1 | Wayback | 原本アーカイブ(感想板は T.36 でWP サイトへ) |
| `~ezpe/` | 第 1 世代 `www2.tomato.ne.jp/~ezpe/` | **1,562** | 12 | 1,550 | 0 | Wayback | 原本アーカイブ(静的 8 頁はWP サイトへ) |
| `aetherworks.org/` | 作者(華村天稀ほか)の個人ドメイン | **470** | 200 | 0 | 38 | Wayback | 参照のみ(作品回収の裏取り資料) |
| `ts.novels.name/` | 姉妹「クリエイターズ・フロア(仮)」ラウンジ BBS | **389** | 2 | 386 | 0 | Wayback + CommonCrawl | 原本アーカイブ(94% スパムのため除外) |
| `~yaji/` | `www.novels.jp/~yaji/` = 矢治さんの個人サイト | **285** | 190 | 2 | 86 | Wayback | 原本アーカイブ |
| `bc-cafe.net/` | きりか進ノ介さんの現行サイトの `bcwiki.old/` = `kirika.novels.name/wiki`(青秋桜 Wiki)の**後継**。旧ミラー 88 頁に対し 175 頁 (+ 添付 122・スキン資産 20) | **317** | 178 | 0 | 104 | **生存サーバ直接**(2026-08-30) | 原本アーカイブ(未収蔵作品「ホーリーメイデンズ外伝」ほかはWP サイトへ) |
| **(リポジトリ直下)** | 本体期のトップ・目録・語彙定義ページ群 | **130** | 122 | 0 | 2 | Wayback | 目録は catalog の入力 / 一部はWP サイト |
| `kirika.novels.name/` | 姉妹「喫茶ブルーコスモス」+ 青秋桜 Wiki | **123** | 87 | 15 | 18 | Wayback | 原本アーカイブ |
| `ts.raa0121.info/` | 姉妹「第二掲示板・ストーリー道場(仮)」 | **69** | 3 | 65 | 0 | Wayback | 原本アーカイブ(道場作品は T.36 でWP サイトへ) |
| `special/` | 企画・アンソロジー 2 本 | **61** | 19 | 0 | 37 | Wayback | WP サイト(T.10.5) |
| `cgi-bin/` | 本体期の作品検索 CGI `manage2.cgi` のスナップショット | **49** | 0 | 49 | 0 | Wayback | 原本アーカイブ |
| `scripts/` | 回収・監査・変換ツール一式 | **44** | 0 | 0 | 0 | 自作 | 参照のみ(うち 4 件は誤って追跡されている `__pycache__/*.pyc`) |
| `comittee/` | 運営コンテンツ「編集"好"記」 | **21** | 21 | 0 | 0 | Wayback | WP サイト(`ts_doc`) |
| `catalog/` | **生成物**。目録の単一真実源 | 25 | 0 | 0 | 0 | 自動生成 | 参照のみ(S.211〜S.214。元の `catalog/README.md`) |
| `~bbs/` | `www.novels.jp/~bbs/` = カウンタ CGI と掲示板入口 | **15** | 3 | 11 | 1 | Wayback | 原本アーカイブ |
| `docs/` | 設計・手順・収蔵物の目録 (いまの S.49〜S.58) | 9 | 0 | 0 | 0 | 自作 | 参照のみ |
| `ts-novels.jp/` | 2018 年の再建版「少年少女文庫改」 | **6** | 3 | 0 | 0 | Wayback | 原本アーカイブ(`/annex/index-2018.html`) |
| `icons/` | Apache autoindex が参照する標準アイコン | **5** | 0 | 0 | 5 | 実サーバ + Apache 公式ストック | 原本アーカイブ |
| `www2.sts.co.jp/` | 1999 年の相互批評ボード・トランスギャルズ開発会議室 | **5** | 1 | 4 | 0 | Wayback | 原本アーカイブ |
| `columns/` | 運営コンテンツ「巻頭言」 | **2** | 2 | 0 | 0 | Wayback | WP サイト(`ts_doc`) |
| `reposts/` | 作者本人の再掲・未掲載本文(pixiv 2 + bc-cafe.net の BC-Wiki 3 + 来歴 json 1) | 6 | 1 | 0 | 0 | pixiv / 作者サイト直接 | WP サイト(T.7.8) |
| `takedown/` | 削除の単一情報源 `denylist.yml` | 2 | 0 | 0 | 0 | 自作 | 参照のみ |
| `www.novels.name/` | 姉妹 `T's☆Heart 情報 Wiki 〜 2nd` | **1** | 1 | 0 | 0 | Wayback | 原本アーカイブ |
| `library/` | 本体期の `library/instruction.html` 1 枚(運営委員募集要項) | **1** | 1 | 0 | 0 | Wayback | WP サイト(`ts_doc`) |
| `dialy/` | 運営コンテンツ「Web サイト構築日記」(綴りは原文ママ) | **1** | 1 | 0 | 0 | Wayback | WP サイト(`ts_doc`) |
| `.github/` | Pages デプロイワークフロー | 1 | 0 | 0 | 0 | 自作 | 参照のみ |
| *(`bodies/`)* | **git 管理外**。本文 Markdown の正準(`make catalog` の生成物) | *(3,642)* | 0 | 0 | 0 | 自動生成 | 参照のみ(投入用の素材) |

**合計 18,093**(数え方: `git ls-files -z | tr -cd '\0' | wc -c`)。`bodies/` の 3,642 は `.gitignore` 済みで
**この合計に入っていない**(数え方: `ls bodies/*.md | wc -l`)。
うち `.cgi` を **basename** に含むもの **9,021**(→ S.56 の本番制約。**フルパスに `.cgi` セグメントを
含む数え方だと 9,022** — 差の 1 件は S.56 のとおりディレクトリ名側)。

### 主要ツリーの内訳(実測)

| ツリー | サブツリー | 件数 | 中身(代表ファイルを開いて確認) |
|---|---|---:|---|
| `~yays/` | `cgi-bin/` | 3,505 | 旧世代の交流 CGI。`resbbs4a` 1,635(感想 BBS)・`noteky` 1,307(創作ノート)・`newinfo` 237(新着)・`paintbbs` 217 + `paintbbs2` 65(お絵かき) |
| | `library/` | 1,598 | **第 2 世代の文庫本体まるごと**。`library/novel/` 1,466・`library/special/` 62・`library/comittee/` 21 + 直下 49(**目録 `lib01〜09` と `lib1〜lib23` まで**・`lib-index.html`・`library.html`・語彙定義ページ) |
| | `gallery/` | 313 | 少年少女ギャラリー。`cg/` 136(JPG)・`story/` 84・`album/` 12・`kiss/` 3・トップ層 78 |
| | (トップ層) | 39 | 八重洲メディアリサーチのサイト本体 (`index.html` = "■ YAYS MEDIA RESEARCH ■") |
| | `reviews/` | 12 | 2002-2003 の作品レビュー頁と挿絵 |
| `~ts/` | `kansou/` | 3,161 | **作品感想板**。`bbs@log_<作者id>.cgi` 293 板 + `bbs@log_.cgi` 1 + `bbs@res_<N>_log_<id>.cgi` 2,866(個別スレッド) + `bbs.cgi` 1 |
| | `bbs/` | 823 | クリエーターズ・フロア(535: `index@res_<N>[_log_dataN].cgi`)+ `2ndbbs/` 288(ストーリー道場) |
| | `link/` | 20 | 自由登録制リンク集 `weblink.cgi` とその `karte_wlNNNN` 詳細 |
| | `chat/` | 4 | チャット室の枠 HTML |
| `~ezpe/` | `cgi-bin/noteky/` | 1,522 | 「私のオススメ」= 読者の推薦ノート。旧目録の感想リンク先 |
| | `cgi-bin/` その他 | 31 | `resbbs4` 20・`resbbs2b` 4・`newentry` 3・`resbbs2` 2・`topics` 1・`resbbs4y` 1 |
| | `yasai/` | 8 | 1999 年の文庫前史の静的ページ(`girlise.html` `nyotai.html` ほか) |
| `special/` | `rb/` | 52 | 「Rental Body Re-Mix 完成記念!! 夏だ一番！ ＲＢ祭り」= 用語辞典・パロディ漫画・イラスト・インタビュー |
| | `03summer/` | **9** | 2003 年夏の企画「絶望未満」。`.txt`/`zetsubou.1-3` は **NScripter のシナリオスクリプト**(HTML ではない) |
| `aetherworks.org/` | `towns/` | 188 | 「黄金航海補佐システム」= 大航海時代 Online 用ツール。**文庫と無関係** |
| | `article/`+`archives/` | 113 | Seesaa ブログ「迷想主義工房エーテルワークス」 |
| | (トップ層) | 103 | PukiWiki「迷走主義工房エーテルワークス」。`index.html` は**ドメインパーキングの転送スクリプト**(現在の持ち主は別) |
| `~yaji/` | `dosv/` `tannowa/` `nippon3/` `opinion/` ほか | 285 | 「日本橋から50km!」= 自作 PC・日本三周記・阪南市事情。文庫本体とは別内容 |
