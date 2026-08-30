# リポジトリ収蔵物の目録 (data inventory)

このリポジトリに「何が入っているか」を、文脈ゼロの読者が把握するための地図。
**設計や手順は書かない** — 設計は [`wordpress-library-design.md`](wordpress-library-design.md)、
実装の進行は [`wp-implementation-tasks.md`](wp-implementation-tasks.md)、
復元の経緯は [`../README.md`](../README.md) が正。ここは**現物の台帳**だけを扱う。
用語(アネックス/整理版/目録/世代/corpus 区分 ほか)は [`glossary.md`](glossary.md) を参照。

## 0. 計測の基準

| 項目 | 値 |
|---|---|
| 計測日時 | 2026-08-30 15:39 JST |
| 計測対象コミット | `d9520b0c` (catalog: make catalog と QA レポート) |
| 計測方法 | `git ls-files -z` の**実測**(作業ツリーの未追跡ファイル・`.venv/`・`__pycache__` は除く) |
| git 管理ファイル総数 | **21,742** |
| 作業ツリー実サイズ | 443 MB (`.git`・`.venv` を除く) |

**再計測のしかた**(数値が古くなったらこれを流す):

```sh
git ls-files | wc -l                                    # 総数
git ls-files | awk -F/ '{print (NF==1)?"(root)":$1}' | sort | uniq -c | sort -rn   # ツリー別
git ls-files <tree> | grep -ciE '\.s?html?$'            # ツリー内の HTML 数
```

**注意**: `catalog/` `bodies/` `reposts/` `scripts/` `docs/` は**現在も別セッションが更新中**で
件数が動く。ミラー本体(`novel/` 以下の収蔵ツリー)は復元作業が完了しており安定している。

---

## 1. 世代の年表

少年少女文庫は 4 つのホストを渡り歩いた。**リポジトリのディレクトリはこの世代に対応している**。

| # | 期間 | ホスト / URL | リポジトリ上の場所 | 何があるか |
|---|---|---|---|---|
| 0 | 1997.10〜 | `www14.big.or.jp/~yays` (八重洲メディアリサーチ) の一区画として文庫が発足 | `~yays/` | 母体サイト。`~yays/index.html` に「+1500000 visits (since 1/Oct/97)」 |
| 1 | 1997.11〜2001 頃 | `www2.tomato.ne.jp/~ezpe` | `~ezpe/` | **最旧の交流層**。読者の感想・推薦ノート `noteky.cgi`。旧目録 lib07–09 の「感想」リンク先はここ |
| 2 | 1999 頃〜2003 頃 | `www14.big.or.jp/~yays/library/` | `~yays/library/` | 文庫の旧ホーム。ツリー構造は現ミラー本体と**同一**(下記 §4) |
| 3 | 2002〜2021 | **`ts.novels.jp`** (本館) | **リポジトリ直下** | 本文 `novel/`・目録 `lib*.html`・交流層 `~ts/`。**WP 整理版の主対象** |
| 4 | 2015〜2023 頃 | 姉妹・避難ドメイン | `ts.novels.name/` `kirika.novels.name/` `ts.raa0121.info/` `www.novels.name/` | サーバ障害時の仮掲示板群。`headline.html` に「文庫サーバーのトラブル復旧が長期化しているため、仮掲示板の設置を開始しました」(2015.02.15) |
| 5 | 2018 | `ts-novels.jp` | `ts-novels.jp/` | 「少年少女文庫改」の再建入口 3 ページ。骨格のみで固有作品なし |
| — | 1997〜2006 | `www.novels.jp` の関係者ディレクトリ | `~yaji/` `~bbs/` | 親サーバの住人(矢治さん)の個人サイト。文庫の前史資料 |
| — | 1999 | `www2.sts.co.jp/~yaji` | `www2.sts.co.jp/` | 文庫前史の相互批評ボード 2 枚 |
| — | 2009〜2014 | `aetherworks.org` | `aetherworks.org/` | 作者(華村天稀ほか)の個人ドメイン。作品回収のため収蔵 |
| — | 2006〜現在 | `bc-cafe.net`(**現存**) | `bc-cafe.net/` | 作者(きりか進ノ介)の個人ドメイン。`/bcwiki.old/` が第 4 世代 `kirika.novels.name/wiki` の後継として今も公開されており、文庫の未収蔵作品とオフ会記録を含む。2026-08-30 に直接回収 |

**掲載日の実測分布**(`catalog/episodes.jsonl` の `date` 欄、3,844 話):

| 年 | 件 | 年 | 件 | 年 | 件 |
|---|---:|---|---:|---|---:|
| 1997 | 18 | 2004 | 320 | 2011 | 63 |
| 1998 | 113 | 2005 | 282 | 2012 | 63 |
| 1999 | 289 | 2006 | 322 | 2013 | 32 |
| 2000 | 183 | 2007 | 358 | 2014 | 12 |
| 2001 | 412 | 2008 | 208 | | |
| 2002 | 446 | 2009 | 125 | | |
| 2003 | 392 | 2010 | 95 | | |

投稿のピークは 2001〜2007。2014 年で新規投稿が止まり、サイト自体は 2021 年頃まで残った。

---

## 2. ツリー別目録

「WP 扱い」欄の意味 — **整理版**=WordPress (novels.xwp.jp) に投稿として載る /
**アネックス**=GitHub Pages に原パスのまま残す(WP には載せない) /
**参照のみ**=どちらの公開物でもない(生成物・ツール・作業データ)。
設計 v1.4「最大掲載原則」により、**アネックスに残るものも WP に載せられるものは載せる**方針。

| パス | 何か | ファイル数 | 内 HTML | 内 .cgi | 内 画像 | 出所 | WP 扱い |
|---|---|---:|---:|---:|---:|---|---|
| `novel/` | **本文**。ts.novels.jp の作品ファイル本体 | **5,017** | 3,817 | 0 | 1,197 | Wayback 主体 + CommonCrawl・魚拓・作者サイト | 整理版(主対象) |
| `~yays/` | 第 0/2 世代 `www14.big.or.jp/~yays/` 丸ごと | **5,469** | 1,488 | 2,955 | 900 | Wayback + 生存サーバ直接 | アネックス(ギャラリーは整理版へ) |
| `~ts/` | `www.novels.jp/~ts/` = 本館の交流層 | **4,008** | 23 | 3,984 | 1 | Wayback | アネックス(感想板は Phase 6 で整理版へ) |
| `~ezpe/` | 第 1 世代 `www2.tomato.ne.jp/~ezpe/` | **1,562** | 12 | 1,550 | 0 | Wayback | アネックス(静的 8 頁は整理版へ) |
| `aetherworks.org/` | 作者(華村天稀ほか)の個人ドメイン | **470** | 200 | 0 | 38 | Wayback | 参照のみ(作品回収の裏取り資料) |
| `ts.novels.name/` | 姉妹「クリエイターズ・フロア(仮)」ラウンジ BBS | **389** | 2 | 386 | 0 | Wayback + CommonCrawl | アネックス(94% スパムのため除外) |
| `~yaji/` | `www.novels.jp/~yaji/` = 矢治さんの個人サイト | **285** | 190 | 2 | 86 | Wayback | アネックス |
| `bc-cafe.net/` | きりか進ノ介さんの現行サイトの `bcwiki.old/` = `kirika.novels.name/wiki`(青秋桜 Wiki)の**後継**。旧ミラー 88 頁に対し 175 頁 (+ 添付 122・スキン資産 20) | **317** | 178 | 0 | 104 | **生存サーバ直接**(2026-08-30) | アネックス(未収蔵作品「ホーリーメイデンズ外伝」ほかは整理版へ) |
| **(リポジトリ直下)** | 本館のトップ・目録・語彙定義ページ群 | **130** | 122 | 0 | 2 | Wayback | 目録は catalog の入力 / 一部は整理版 |
| `kirika.novels.name/` | 姉妹「喫茶ブルーコスモス」+ 青秋桜 Wiki | **123** | 87 | 15 | 18 | Wayback | アネックス |
| `ts.raa0121.info/` | 姉妹「第二掲示板・ストーリー道場(仮)」 | **69** | 3 | 65 | 0 | Wayback | アネックス(道場作品は Phase 6 で整理版へ) |
| `special/` | 企画・アンソロジー 2 本 | **61** | 19 | 0 | 37 | Wayback | 整理版(タスク 4.8) |
| `cgi-bin/` | 本館の作品検索 CGI `manage2.cgi` のスナップショット | **49** | 0 | 49 | 0 | Wayback | アネックス |
| `scripts/` | 回収・監査・変換ツール一式 | **41** | 0 | 0 | 0 | 自作 | 参照のみ |
| `comittee/` | 運営コンテンツ「編集"好"記」 | **21** | 21 | 0 | 0 | Wayback | 整理版(`ts_doc`) |
| `catalog/` | **生成物**。目録の単一真実源 | 19 | 0 | 0 | 0 | 自動生成 | 参照のみ([`catalog/README.md`](../catalog/README.md)) |
| `~bbs/` | `www.novels.jp/~bbs/` = カウンタ CGI と掲示板入口 | **15** | 3 | 11 | 1 | Wayback | アネックス |
| `docs/` | 設計・手順・本書 | 7 | 0 | 0 | 0 | 自作 | 参照のみ |
| `ts-novels.jp/` | 2018 年の再建版「少年少女文庫改」 | **6** | 3 | 0 | 0 | Wayback | アネックス(`/annex/index-2018.html`) |
| `icons/` | Apache autoindex が参照する標準アイコン | **5** | 0 | 0 | 5 | 実サーバ + Apache 公式ストック | アネックス |
| `www2.sts.co.jp/` | 1999 年の相互批評ボード・トランスギャルズ開発会議室 | **5** | 1 | 4 | 0 | Wayback | アネックス |
| `columns/` | 運営コンテンツ「巻頭言」 | **2** | 2 | 0 | 0 | Wayback | 整理版(`ts_doc`) |
| `reposts/` | 作者本人の再掲・未掲載本文(pixiv 2 + bc-cafe.net の BC-Wiki 3 + 来歴 json 1) | 6 | 1 | 0 | 0 | pixiv / 作者サイト直接 | 整理版(タスク 1.9) |
| `takedown/` | 削除の単一情報源 `denylist.yml` | 2 | 0 | 0 | 0 | 自作 | 参照のみ |
| `www.novels.name/` | 姉妹 `T's☆Heart 情報 Wiki 〜 2nd` | **1** | 1 | 0 | 0 | Wayback | アネックス |
| `library/` | 本館 `library/instruction.html` 1 枚(運営委員募集要項) | **1** | 1 | 0 | 0 | Wayback | 整理版(`ts_doc`) |
| `dialy/` | 運営コンテンツ「Web サイト構築日記」(綴りは原文ママ) | **1** | 1 | 0 | 0 | Wayback | 整理版(`ts_doc`) |
| `.github/` | Pages デプロイワークフロー | 1 | 0 | 0 | 0 | 自作 | 参照のみ |

**合計 21,742**(うち `bodies/` の生成 Markdown 3,640)。うち `.cgi` を basename に含むもの **9,021**(→ §7 の本番制約)。

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

---

## 3. `novel/` の内部構造 — 2 つの時代が同居している

`novel/` は投稿システムの世代交代の跡がそのまま残っており、**3 種類のパス形状が混在**する。

| 形状 | 例 | ファイル数 | 時代 |
|---|---|---:|---|
| **(a) 日付ディレクトリ** `novel/YYYYMM/DDHHMMSS/<作品>.htm[l]` | `novel/200209/19204751/d_upboy06.htm` | **3,917** | `manage2.cgi` 導入後(2000-08〜2014-03) |
| **(b) 作品/シリーズ名ディレクトリ** `novel/<name>/<作品>.html` | `novel/kayo_chan/kayo_chan38.html` | **980** | フラット期(〜2000 頃)と共有世界 |
| **(c) 直下フラット** `novel/<作品>.html` | `novel/a_boy_and_the_ritual.html` | **120** | 最初期 |

- (a) の 2 段目 `YYYYMM` は **105 種**、範囲は `200008`〜`201403`。
- (a) の 3 段目 `DDHHMMSS` は 391 種。**8 桁数字でないものが 1 つだけある**: `novel/200105/milfia/`。
- **`YYYYMM/DDHHMMSS` は「作者の初回投稿バッチ」であって話の日付ではない。**
  例えば `200209/` の下に 2014 年掲載の話が実在する。post_date は必ず目録の日付欄から取る
  (この地雷は台帳 §「目録パースの地雷」にも明記されている)。
- (b) の 56 ディレクトリには共有世界(`kayo_chan` `foster` `rental` `setsubou` `sugar` `mirror_ring`
  `dirty` ほか)と単なる分類(`short` `free` `grp` `fan_fiction`)が混ざる。

### `novel/` のファイル種別

| 種別 | 件数 | 備考 |
|---|---:|---|
| HTML (`.htm` / `.html`) | 3,817 | うち **83 は Apache autoindex のスナップショット**(`index.html`・`index@D_A.html` 等) |
| 拡張子なしの HTML | 1 | `novel/201005/20141534/vistia_index` (`<title>ヴィスティア</title>`) |
| 画像 | 1,197 | 挿絵・アイコン |
| その他 | 3 | `concerto/test3.txt` `oyaji/favicon.ico` `perky_girl/perky_anime.swf` |

**「本文ファイル」の数え方に注意** — 設計 v1.4 とタスク 1.8 の「本文 3,818」は
`3,817 (HTML) + 1 (拡張子なし)` であり、**autoindex 83 枚を含んだ数**。
作品本文だけなら **3,735**(= 3,818 − 83)。タスク 1.8 の受け入れ条件
「`novel/` 配下の本文ファイルで catalog に載らないものが 0」を検査するときは、
autoindex を意図的除外に入れているか必ず確認すること。

---

## 4. `~yays/library/` と本館 `novel/` の関係

`~yays/library/` は**第 2 世代の文庫本体そのもの**で、リポジトリ直下と**同一の相対パス構造**を持つ。

| 比較 | 実測 |
|---|---|
| `~yays/library/novel/` のファイル | 1,466 |
| うち同じ相対パスが `novel/` にも在る | **1,399 (95.4%)** |
| `~yays/` にしか無い | 67 |
| `~yays/library/` 全体 1,598 のうちリポジトリ直下に同名がある | 1,530 (95.7%) |

- この 95.4% の重複が「`~yays/library/` は本館とほぼ同文の祖先スナップショット」(設計 v1.4)の根拠。
- 同じ「1,399」が `_ts_annex_yays_url`(話の「初出時の姿」リンク)の候補数。
  **ただし目録エントリ基準の実測は 724 件**(タスク 1.1 の訂正 (b))。1,399 は*ファイル*の数。
- 本文は同文だが**バイト一致ではない**(例: `kayo_chan38.html` は本館 5,673B / `~yays` 側 6,264B。
  ヘッダ・ナビが世代で違う)。

---

## 5. 目録ファイル 4 系統の区別 — ここを間違えるとパースが壊れる

リポジトリ直下には「目録らしきもの」が 4 系統ある。**互いに別物**で、フォーマットも役割も違う。

| 系統 | ファイル | 実測 | 形式 | 役割 |
|---|---|---|---|---|
| **① 正規目録** | `lib1.html`〜`lib73.html`(**ゼロ埋めなし**) | `<TABLE BORDER=1>` **合計 2,887** | 1 エントリ = `<TABLE BORDER=1>` 1 個・必ず 4 行・1 行目 6 セル | catalog の**第一メタデータ源**。`corpus=honkan` |
| **② 旧目録(前期)** | `lib01.html`〜`lib06.html` | TABLE は **0 個** | **非テーブル**。`24KB(updated 2/14)<b><a href="novel/…">［題名］</a> 作・作者</b><br>` の散文列挙 | 1997.11〜1998 の初期史料。`corpus=legacy` |
| **③ 旧目録(移行期)** | `lib07.html`〜`lib09.html` | TABLE **32 / 23 / 29 = 84** | ①と同じテーブル形式だが **【属性】欄あり・2 桁年(`99/10/2`)・感想リンクが `~ezpe/cgi-bin/noteky/`** | 1999〜2000 の史料。`corpus=legacy` |
| **④ 五十音索引** | `lib-index*.html` (16 ファイル) | 作品リンク **3,788 本** | 題名 + 作者の 2 列テーブル。**あらすじ等のメタは無い** | 目録に載らない話の**第二メタデータ源**(タスク 1.8) |
| (⑤ 重複) | `library.html` | TABLE **10 個** | ①と同形式 | **`lib1.html` 先頭 10 件の完全重複。パース対象外** |

### ② の具体例(`lib01.html` から)

```html
120KB(updated 4/3)<b><a href="novel/deai.html">［出会いは偶然に］</a> 作・Ｔａｓｋ</b><br>
<b>あらすじ：紀平幾斗は霊感体質。…</b><br>
（<b>作者様のお言葉：性転換モノというより…</b>）
```

- **年が無い**(`4/3` だけ)。ページ見出しの収録期間(`旧作品(1997.11.4 - 1998.4.5)`)から年を決める。
- **HTML コメントで隠されたエントリが 12 件**ある(上の例のすぐ上に `<!-- 10KB(updated 4/5) …［花の精霊］… -->`)。
  運営が意図的に伏せた可能性があるため catalog では `commented_out: true` が立ててある。

### ③ の具体例(`lib07.html` から)

```html
<TABLE BORDER=1>
<TR><TD><B><a href="novel/kayo_chan/kayo_chan38.html">華代ちゃん： 水泳嫌い</a></B></TD>
    <TD><B>水谷秋夫</B> さん</TD><TD>（イラストなし）</TD><TD>99/10/2</TD><TD>9 KB</TD>
    <TD BGCOLOR="#E0D0C0"><a href="~ezpe/cgi-bin/noteky/noteky@c_noteread_f_10_id_….cgi">感想</a></TD></TR>
<TR><TD COLSPAN=6><B>【あらすじ】 </B>…<BR><B>【コメント】 </B>…</TD></TR>
<TR><TD COLSPAN=6><B>【推薦文】 </B>…</TD></TR>
<TR><TD COLSPAN=6>【ジャンル】 SS<BR>【種別】 変身<BR>【属性】 <BR>【キーワード】 「華代ちゃん」</TD></TR>
</TABLE>
```

①(`lib1`〜`lib73`)との違いは **【属性】欄の有無**と**感想リンク先**(① は `~ts/kansou/bbs@log_<id>.cgi`)。

### ④ の内訳(実測) — 2 世代が混在している

`lib-index-*.html` はさらに**古い 4 分割版**と**新しい五十音別版**の 2 世代が同居する。

| 世代 | ファイル | `novel/` へのリンク数 |
|---|---|---:|
| 旧(4 分割・小文字タグ) | `lib-index-1〜4.html` | 296 / 137 / 296 / 265 = **994** |
| 新(五十音別) | `lib-index-aa` 392 / `ka` 309 / `sa` 266 / `ta` 105 / `na` 93 / `ha` 240 / `ma` 235 / `ya` 278 / `ra` 204 / `wa` 6 / `en` 642 / `etc` 24 | **2,794** |
| ハブ | `lib-index.html` | 0(五十音への入口のみ) |

新世代の合計 2,794 が、タスク 1.8 の言う「約 2,796 行」に対応する。

### 直下のその他の主要ファイル

| ファイル | 中身 |
|---|---|
| `index.html` / `index@08201937.html` / `index@11031137.html` | 本館トップの**別時点キャプチャ 3 枚**。`index.html`(08/31)・`@08201937`(08/23)・`@11031137`(11/03) |
| `series.html` | 完結シリーズ作品一覧。work クラスタリングのシード(有効行 **112**) |
| `share_world.html` / `.htm` | 共有世界の説明。`ts_world` 語彙の元資料(`.htm` は旧版) |
| `genre.html` / `type_of_change.html` / `keyword.html` | 当時の**語彙定義ページ**(27 / 18 / 38 語)。term description に転用 |
| `bunrui.html` | 97〜98 年度版の分類マトリクス(化石索引) |
| `boshuu.html` / `instruction.html` / `manual.html` / `introduction.html` / `standard_format.html` | 投稿規定・運営委員募集・ドラフト・サイト趣旨・原稿書式 |
| `headline.html` | 2015 年のサーバ障害告知(姉妹ドメイン誕生の一次資料) |
| `master.htm` | **Microsoft Excel 9 が書き出した HTML**(`LastAuthor: k.sahara`)。運営の管理表 |
| `robots.txt` | **archive.org 自身の robots.txt を誤回収したもの。参照しないこと**(本番用の草案は `scripts/wp/assets/robots.txt`) |
| `collinfo.json` | CommonCrawl のコレクション一覧 127 件。**来歴情報は持たない**(タスク 1.1 訂正 (a)) |
| `yj_raw.html` | **Yahoo! 検索の結果ページ**(`"猫目ニボシ"` の検索結果)。作者調査の作業残骸で、ミラーの一部ではない |
| `welcome.gif` / `welcome.png` | トップのバナー画像 |
| `security.htm` | 「Norton Internet Security を御利用の場合」の案内 |
| `entrance.html` / `entrance.shtml` | 入口ページ(`.shtml` 版もキャプチャ済み) |
| `feedback.html` | 作品感想の案内 |

---

## 6. CGI スナップショットの命名規約(`@` マングル)

BBS・CGI ページは**クエリつき URL** なので、そのままではファイルにできない。
本リポジトリは以下の規約でファイル名化してある(実装: `scripts/place_convert.py` の `mangle_q`)。

```
<stem>@<query>.<ext>       query は  % を除去、= & / ? をすべて _ に置換
```

| 元 URL | リポジトリ上のパス |
|---|---|
| `~ts/kansou/bbs.cgi?log=johdan` | `~ts/kansou/bbs@log_johdan.cgi` |
| `~ts/kansou/bbs.cgi?res=123&log=johdan` | `~ts/kansou/bbs@res_123_log_johdan.cgi` |
| `cgi-bin/manage2.cgi?search` | `cgi-bin/manage2@search.cgi` |
| `novel/200312/index.html?D=A` (autoindex の並べ替え) | `novel/200312/index@D_A.html` |
| `~ezpe/cgi-bin/noteky/noteky.cgi?c=noteread&f=9&id=…&ff=on` | `~ezpe/cgi-bin/noteky/noteky@c_noteread_f_9_id_…_ff_on.cgi` |

**注意 1**: `~yays/cgi-bin/` の**クエリなしの基準ページ**(`resbbs4.cgi`・`noteky.cgi` 等)は、
実体ではなく **`www14.big.or.jp` の広告 soft-404 ページ**である
(`<title>1GB レンタルサーバー・プロバイダー Amusement BiG-NET | …</title>`)。
中身が入っているのは `@` つきのスナップショットのほう。

**注意 2**: 同じマングル規約が **3 つのスクリプトで微妙に違う実装**になっている(→ §8 の (5))。

**注意 3**: シェルの `git ls-files` 等で `~` 始まりのパスを扱うときは必ずクォートすること
(`git ls-files '~ts'`)。またクエリ由来の壊れた文字を含むパスが 14 件あり、
`git ls-files` はそれを `"…\357\277\275…"` の形でクォート出力する。
数えるときは `git ls-files -z` を使うのが安全。

---

## 7. 本番ホスト(novels.xwp.jp)側の制約が効くファイル

| 条件 | 件数 | 影響 |
|---|---:|---|
| basename が `.cgi` を含む | **9,021** | 本番では配信できない → アネックス(GitHub Pages)恒久併存の理由 |
| フルパスに `.cgi` セグメントを含む | 9,022 | `~yaji/blog/mt-atom.cgi/weblog/blog_id=1` の 1 件だけディレクトリ名側 |
| `.pl` | 1 | `ts-novels.jp/kantan-cgi/counter@id_sd03205Y.pl` |
| `.shtml` | 2 | `entrance.shtml`・`~yays/library/entrance.shtml` |

詳細と実測は [`environment.md`](environment.md) §4。

---

## 8. 既存文書の数値との食い違い(実測で確認したもの)

| # | 既存文書の記述 | 実測 | 判断 |
|---|---|---|---|
| (1) | README「`~yaopinion` を発見・回収」「`~yaopinion/` = `www.novels.jp/` のユーザディレクトリ」 | **`~yaopinion/` というツリーは存在しない**。`yaopinion` の文字列はリポジトリ中 README.md にしか出てこない | **README が誤り**。実在するのは `~yaji/opinion/`(34 ファイル、「My Opinions」)。第 3 次探索の作業メモが誤って README に残ったものと思われる |
| (2) | 設計 §3.1「`/special/` はアネックス実在パス(**03summer 61 ファイル**)」・v1.4「企画・アンソロジー `special/` 61」 | `special/` **合計 61** は正しいが、内訳は **`rb/` 52 + `03summer/` 9**。03summer は 9 ファイル | **設計の内訳が誤り**。`special/rb/`(Rental Body Re-Mix 祭り)が主たる中身。タスク 4.8 で「03summer 等」として扱うときは rb を落とさないこと |
| (3) | 設計 v1.4「~yays 世代のギャラリー CG **313**」・v1.0「~yays gallery (CG **313 点**)」 | `~yays/gallery/` **全体が 313 ファイル**。うち画像は 157、`cg/` サブディレクトリは **136 点** | **「CG 313 点」は誤り**。313 はギャラリー区画のファイル総数。タスク 4.7 の対象規模は「CG 136 + story 84 + album 12 + kiss 3 + 頁 78」と読み替える |
| (4) | 設計/タスク 1.8「`novel/` 配下の本文 **3,818**」 | HTML 3,817 + 拡張子なし 1 = 3,818。**ただし Apache autoindex 83 枚を含む** | **数としては合うが定義が広い**。作品本文だけなら 3,735。1.8 の受け入れ検査で autoindex を意図的除外に入れる必要がある |
| (5) | (文書化されていない) | クエリ→ファイル名のマングル実装が **3 本で不一致**: `scripts/audit_full.py:37` は `/` を置換しない / `scripts/place_convert.py:96` は unquote 後に `/` `?` も置換 / `scripts/cdx_recover.py:62` は %エスケープを素の hex のまま残して `/` を置換 | **潜在バグ**。`/` を含むクエリ(`~bbs/cgi-bin/npc@L__~yaji_index.htm_…`)で `audit_full.py` が実在ファイルを未解決と誤判定しうる。リンク監査の「未解決 5,067」に混入している可能性がある |
| (6) | README「`ts.novels.name/rounge/`… 閉鎖後 **2023 年まで**稼働したラウンジ BBS」 | 収蔵ファイル中の投稿日付は **2015〜2026**。2022 年以降が急増(2022:918 / 2023:443 / 2024:668 / 2025:737 / 2026:698) | README の期間が古い。設計の言う「94% スパム」がこの 2022 年以降の激増分。**回収時点(2026-08)でも板は稼働中**と読むのが自然 |
| (7) | 設計 v1.0「~yays/library/ は **95.4%** 重複」 | `~yays/library/novel/` 1,466 中 1,399 = **95.4%** | **一致**(確認のみ) |
| (8) | タスク台帳「アネックス = 全 **17,218** ファイル」/ README「全 17,218 ファイル」 | 現在 **21,742**。うち**ミラー相当 18,020** / 非ミラー(`catalog/` `scripts/` `docs/` `takedown/` `reposts/` `.github/`)が残り。非ミラー側は作業中で日々増える | 数字が古いだけ。ただし現在の `deploy-pages.yml` は**リポジトリ直下をそのまま配信**するので、`catalog/` や `scripts/` も Pages 上に出ている。タスク 5.2 で artifact ビルド方式に変える際に除外対象を決めること |

---

## 9. 生成物・作業用ツリー(ミラーではないもの)

| パス | 内容 | 正典 |
|---|---|---|
| `catalog/` | 目録の単一真実源(episodes.jsonl / works.jsonl / authors.json / terms.json / 各種 map・overrides・reports) | [`catalog/README.md`](../catalog/README.md) |
| `bodies/` | 本文 Markdown の正準(タスク 1.6 で生成。**まだ無い**) | 設計 v1.2 A 章 |
| `reposts/` | 作者本人の pixiv 再掲本文。プレーンテキストなので本文変換の対象外 | タスク 1.9 |
| `scripts/` | 回収・監査・変換ツール(`scripts/wp/` が WP 移築の実装) | [`scripts/README.md`](../scripts/README.md) |
| `takedown/` | `denylist.yml`(削除の四層を駆動する単一情報源) | [`removal-runbook.md`](removal-runbook.md) |
| `docs/` | 設計・手順・本書 | — |
| `.github/workflows/deploy-pages.yml` | `master` への push でリポジトリ直下をそのまま GitHub Pages へ配信(ビルドなし) | — |

公開先: GitHub `takano32/ts-novels`(public) → GitHub Pages = アネックス /
`https://novels.xwp.jp` = WordPress 整理版。
