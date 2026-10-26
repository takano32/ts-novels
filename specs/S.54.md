# S.54 目録ファイル 4 系統の区別

- 種類: 実測
- 状態: 有効
- 出典: docs/data-inventory.md の「目録ファイル 4 系統の区別」
- 関連: S.11, S.16

## 目録ファイル 4 系統の区別 — ここを間違えるとパースが壊れる

リポジトリ直下には「目録らしきもの」が 4 系統ある。**互いに別物**で、フォーマットも役割も違う。

| 系統 | ファイル | 実測 | 形式 | 役割 |
|---|---|---|---|---|
| **正規目録** | `lib1.html`〜`lib73.html`(**ゼロ埋めなし**) | `<TABLE BORDER=1>` **合計 2,887** | 1 エントリ = `<TABLE BORDER=1>` 1 個・必ず 4 行・1 行目 6 セル | catalog の**第一メタデータ源**。`corpus=honkan` |
| **旧目録(前期)** | `lib01.html`〜`lib06.html` | TABLE は **0 個** | **非テーブル**。`24KB(updated 2/14)<b><a href="novel/…">［題名］</a> 作・作者</b><br>` の散文列挙 | 1997.11〜1998 の初期史料。`corpus=legacy` |
| **旧目録(移行期)** | `lib07.html`〜`lib09.html` | TABLE **32 / 23 / 29 = 84** | 正規目録と同じテーブル形式だが **【属性】欄あり・2 桁年(`99/10/2`)・感想リンクが `~ezpe/cgi-bin/noteky/`** | 1999〜2000 の史料。`corpus=legacy` |
| **五十音索引** | `lib-index*.html` (16 ファイル) | 作品リンク **3,788 本** | 題名 + 作者の 2 列テーブル。**あらすじ等のメタは無い** | 目録に載らない話の**第二メタデータ源**(T.7.8) |
| (重複) | `library.html` | TABLE **10 個** | 正規目録と同形式 | **`lib1.html` 先頭 10 件の完全重複。パース対象外** |

### 旧目録(前期)の具体例(`lib01.html` から)

```html
120KB(updated 4/3)<b><a href="novel/deai.html">［出会いは偶然に］</a> 作・Ｔａｓｋ</b><br>
<b>あらすじ：紀平幾斗は霊感体質。…</b><br>
（<b>作者様のお言葉：性転換モノというより…</b>）
```

- **年が無い**(`4/3` だけ)。ページ見出しの収録期間(`旧作品(1997.11.4 - 1998.4.5)`)から年を決める。
- **HTML コメントで隠されたエントリが 12 件**ある(上の例のすぐ上に `<!-- 10KB(updated 4/5) …［花の精霊］… -->`)。
  運営が意図的に伏せた可能性があるため catalog では `commented_out: true` が立ててある。

### 旧目録(移行期)の具体例(`lib07.html` から)

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

正規目録(`lib1`〜`lib73`)との違いは **【属性】欄の有無**と**感想リンク先**(正規目録は `~ts/kansou/bbs@log_<id>.cgi`)。

### 五十音索引の内訳(実測) — 2 世代が混在している

`lib-index-*.html` はさらに**古い 4 分割版**と**新しい五十音別版**の 2 世代が同居する。

| 世代 | ファイル | `novel/` へのリンク数 |
|---|---|---:|
| 旧(4 分割・小文字タグ) | `lib-index-1〜4.html` | 296 / 137 / 296 / 265 = **994** |
| 新(五十音別) | `lib-index-aa` 392 / `ka` 309 / `sa` 266 / `ta` 105 / `na` 93 / `ha` 240 / `ma` 235 / `ya` 278 / `ra` 204 / `wa` 6 / `en` 642 / `etc` 24 | **2,794** |
| ハブ | `lib-index.html` | 0(五十音への入口のみ) |

新世代の合計 2,794 が、T.7.8 の言う「約 2,796 行」に対応する。

### 直下のその他の主要ファイル

| ファイル | 中身 |
|---|---|
| `index.html` / `index@08201937.html` / `index@11031137.html` | 本体期トップの**別時点キャプチャ 3 枚**。`index.html`(08/31)・`@08201937`(08/23)・`@11031137`(11/03) |
| `series.html` | 完結シリーズ作品一覧。work クラスタリングのシード(有効行 **112**) |
| `share_world.html` / `.htm` | 共有世界の説明。`ts_world` 語彙の元資料(`.htm` は旧版) |
| `genre.html` / `type_of_change.html` / `keyword.html` | 当時の**語彙定義ページ**(27 / 18 / 38 語)。term description に転用 |
| `bunrui.html` | 97〜98 年度版の分類マトリクス(化石索引) |
| `boshuu.html` / `instruction.html` / `manual.html` / `introduction.html` / `standard_format.html` | 投稿規定・運営委員募集・ドラフト・サイト趣旨・原稿書式 |
| `headline.html` | 2015 年のサーバ障害告知(姉妹ドメイン誕生の一次資料) |
| `master.htm` | **Microsoft Excel 9 が書き出した HTML**(`LastAuthor: k.sahara`)。運営の管理表 |
| `robots.txt` | **archive.org 自身の robots.txt を誤回収したもの。参照しないこと**(本番用の草案は `scripts/wp/assets/robots.txt`) |
| `collinfo.json` | CommonCrawl のコレクション一覧 127 件。**来歴情報は持たない**(T.7.1 訂正 (a)) |
| `yj_raw.html` | **Yahoo! 検索の結果ページ**(`"猫目ニボシ"` の検索結果)。作者調査の作業残骸で、ミラーの一部ではない |
| `welcome.gif` / `welcome.png` | トップのバナー画像 |
| `security.htm` | 「Norton Internet Security を御利用の場合」の案内 |
| `entrance.html` / `entrance.shtml` | 入口ページ(`.shtml` 版もキャプチャ済み) |
| `feedback.html` | 作品感想の案内 |
