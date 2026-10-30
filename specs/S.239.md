# S.239 raw フォールバックの 2 話の「原本配色」トグル (T.39.2 の記録)

- 種類: 記録
- 状態: 有効
- 出典: 2026-10-30 の作業 (T.39.2)
- 関連: T.39.2, S.27, S.37, S.193, S.94

## 対象の 2 話

`catalog/convert_report.jsonl` で status が raw-fallback の 2 件で、`payloads/_meta.json` でも raw-fallback はこの 2 件だけ (converted 3,642 / converted-text 2)。

| episode_id | 題名 | 原本の配色の指定 |
|---|---|---|
| `novel__200510__19213555__koromogae1.htm` | ころもがえ前 | `<style>` の body が背景 #eeeeee・文字 #222222、a:link #0000e0、a:visited #551a8b (S.27 の「#EEEEEE 標準」) |
| `novel__200104__20050818__acgallary.htm` | 天使のギャラリー | `<body bgcolor="#ACCBF9" background="kabe2.gif">`。本文の字は大半が `<font color="#FFFFFF">` (白)、一部 `#0000FF` |

`body_convert.extract_body` は `<body>` の属性と `<style>` を落とすので、これまでの WP サイトの本文には当時のページ配色が残っていなかった。そのうえ `<font color>` は効いたままなので、天使のギャラリーはライト表示で白い字が淡い背景に載り、ほぼ読めなかった。

## 実装したもの

- `scripts/wp/payload_build.py`: raw フォールバックの本文の先頭に、原本から拾った配色を持つ空の `<div class="ts-orig-palette" data-bg data-fg data-link data-vlink [data-bgimg]>` を置く。拾う先は `<style>` の body・a:link・a:visited の規則と `<body>` の bgcolor・text・link・vlink・background 属性 (後者を優先)。指定の無い色は当時のブラウザの既定 (白地 #ffffff・黒字 #000000・リンク #0000ee・訪問済み #551a8b)。背景画像は git にある画像だけを `/assets/annex-img/` の絶対参照にする (天使のギャラリーの kabe2.gif)。属性は `data-*` だけにした (WordPress の HTML の許可一覧で落ちないため)。
  - 生成し直した `payloads/` の差分はこの 2 本だけで、`catalog/reports/payload_build.json` は変わらない。
- `themes/ts-bunko/single.php`: body_status が raw-fallback の話だけ、リーダーの操作列に「原本配色」ボタンを足す。
- `themes/ts-bunko/js/reader.js`・`themes/ts-bunko/functions.php`: 既存の設定 (localStorage `ts-reader`、`<html data-ts-*>`) に `orig` を足した。押すと `data-ts-orig="1"`、もう一度押すと既定に戻る (ほかのボタンと同じ)。印の要素の値は色の形と `/assets/annex-img/` の参照だけを受け取り、本文の器 `.ts-reader` の `--ts-orig-*` に写す。
- `themes/ts-bunko/style.css`: 既定の表示では raw フォールバックの本文の `font[color]` をサイトの文字色に、`[bgcolor]` を透明にする (S.27 の「整形表示」のとおり、色だけを上書き)。「原本配色」では本文の器を当時の背景色・背景画像・文字色・リンク色にし、`font[color]` は原本どおり効かせる。

## 選んだこと (仕様に無かった点)

- 原本の配色をどこに持たせるか: catalog・import・post meta は変えず、raw フォールバックの本文 (payload) の先頭の印の要素に持たせた。2 話の配色は本文と一緒にしか使わないため。
- 切替の範囲: ほかの設定と同じく端末の全ページに効く設定にした (raw フォールバックの話にしかボタンも効き目も無い)。
- 既定の表示で `font[color]` を上書きすることにした: S.27 の既定「整形表示」どおりで、天使のギャラリーの白い字を読めるようにするため。

## 確認

`node --check themes/ts-bunko/js/reader.js` 通過。PHP は php 8.2 の `php -l` で構文検査を通過 (コンテナで実行)。天使のギャラリーの payload を style.css・reader.js と合わせたローカルの HTML をブラウザで開き、既定で白い字が rgb(35,33,32) (サイトの文字色)・背景透明、「原本配色」を押すと字が白・背景が #ACCBF9、もう一度押すと戻ることを確かめた。

## 配備で要ること

テーマ (`themes/ts-bunko/`) の rsync に加えて、2 話の本文を入れ直す (`payloads/` を rsync して `wp ts import --bodies=payloads`)。本文を入れ直すまでは印の要素が無いので、「原本配色」を押しても背景は白・字は黒の既定になる。
