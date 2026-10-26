# S.52 novel/ の内部構造

- 種類: 実測
- 状態: 有効
- 出典: docs/data-inventory.md の「novel/ の内部構造」
- 関連: なし

## `novel/` の内部構造 — 2 つの時代が同居している

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
  (この地雷は S.11 にも明記されている)。
- (b) の 56 ディレクトリには共有世界(`kayo_chan` `foster` `rental` `setsubou` `sugar` `mirror_ring`
  `dirty` ほか)と単なる分類(`short` `free` `grp` `fan_fiction`)が混ざる。

### `novel/` のファイル種別

| 種別 | 件数 | 備考 |
|---|---:|---|
| HTML (`.htm` / `.html`) | 3,817 | うち **83 は Apache autoindex のスナップショット**(`index.html`・`index@D_A.html` 等) |
| 拡張子なしの HTML | 1 | `novel/201005/20141534/vistia_index` (`<title>ヴィスティア</title>`) |
| 画像 | 1,196 | 挿絵・アイコン(数え方: `.jpg` 562 + `.gif` 614 + `.png` 19 + `.bmp` 1) |
| その他 | 3 | `concerto/test3.txt` `oyaji/favicon.ico` `perky_girl/perky_anime.swf` |

合計 3,817 + 1 + 1,196 + 3 = **5,017**(S.51 の `novel/` 行と一致する)。

**「本文ファイル」の数え方に注意** — S.41 と T.7.8 の「本文 3,818」は
`3,817 (HTML) + 1 (拡張子なし)` であり、**autoindex 83 枚を含んだ数**。
作品本文だけなら **3,735**(= 3,818 − 83)。T.7.8 の受け入れ条件
「`novel/` 配下の本文ファイルで catalog に載らないものが 0」を検査するときは、
autoindex を意図的除外に入れているか必ず確認すること。
