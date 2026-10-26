# S.187 novel/ の画像の配備 (T.8.7、元の 2.7)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 2.7
- 関連: T.8.7

- [x] 2.7 novel/ 画像 **1,196 点**(約 59MB。数え方: `git ls-files novel` の `.jpg` 562 + `.gif` 614 + `.png` 19 + `.bmp` 1)を `public_html/assets/annex-img/` に相対構造ごと rsync
      - **2.7 実測 (2026-08-30)**: 点数はタスクリストどおり **1,196**(jpg 562 / gif 614 / png 19 / bmp 1)、
        実サイズ **59,719,763 バイト**。`git ls-files novel` を拡張子で絞った一覧を
        `rsync --files-from` に渡し、`-n` で 1,196 件・宛先・`--delete` 無しを確認してから実配備
        (**12.9 秒**)。宛先ディレクトリは事前に `mkdir -p` が要る(rsync が自力で作れず一度失敗した)。
        結果: `assets/annex-img/` に **1,196 ファイル / 60MB**。
      - **配置は `assets/annex-img/novel/<元の相対パス>`**(`novel/` を残した)。
        理由: `bodies/*.md` の画像参照は `<img src="maidgirls.gif">` のように
        **元 HTML と同じディレクトリ前提の裸のファイル名**なので、
        `source_path` のディレクトリ部分をそのまま連結すれば解決できる形にした。
        3.2 の書換もこの前提で書くこと
      - 検証(`?_cb=$RANDOM` 付き): `/assets/annex-img/novel/youma/youma1.jpg` → **200 image/jpeg 9,391B**、
        `/assets/annex-img/novel/youma/youma0.gif` → **200 image/gif 5,027B**
