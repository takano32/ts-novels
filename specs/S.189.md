# S.189 img の書き換えと eyecatch (T.9.2)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 3.2
- 関連: T.9.2

- [x] T.9.2 img src を `assets/annex-img/` 参照に書換。**eyecatch 画像(実体 4 ファイル・参照 15 話)**は
      dedup して該当話の featured image に設定(survey の「109 件」は参照回数であり実体数ではない)
      - **前半 (img src の書換) は完了 (2026-08-30)**: 本文に `/assets/annex-img/` を含む投稿は
        **688 件**。実配信も確認 — `/novel/wataru1024-futarinokaworu-contents-by-wataru1024/` の
        `/assets/annex-img/novel/200012/10070655/title_i01.gif` が **200 image/gif 10,000B**、
        `kaoru01s.gif` が **200 image/gif 5,895B**。
        なお **`src` 属性は原本のまま大文字 `SRC=`** で出力される(HTML として妥当なので実害なし。
        grep で拾うときは大小文字を無視すること)
      - **後半 (eyecatch = featured image) 完了 (2026-08-30)**: 対応表は
        `catalog/reports/payload_build.json` の **`eyecatch_refs`(15 話)**。
        `wp media import` で **attachment 3 件**を作り(**8733** = 03175506 の gif /
        **8734** = 20050818 の jpg / **8735** = 20050818 の gif)、
        各話に `wp post meta update <post> _thumbnail_id <att>` を実行。
        検収: **`_thumbnail_id` を持つ ts_work = 15 件**、
        attachment 3 件とも配信 200(45,551B gif / 2,510B jpg / 8,034B gif で原本とバイト一致)、
        eyecatch 付きの話 `/novel/million-shijousaiakunosakusen/` の HTML に
        `wp-post-image` が出る(`og:image` は 0 = テーマ未整備のため。T.10 の範囲)
      - **タスクリストの記述の訂正 1 点**: 「**実体 4 ファイル**」は**パスが 4 つ**であって実体は **3 つ**。
        `novel/200012/03175506/eyecatch.gif` と `novel/robot_triathlon/eyecatch.gif` は
        **md5 一致 (`74fb5164…`、45,551B)** なので、media import は 3 回で足り、
        この 2 パスには同じ attachment 8733 を割り当てた
      - **`ac-14` だけ eyecatch が 2 枚**(gif と jpg)登録されている。featured image は
        1 枚しか持てないので **`eyecatch_refs` の先頭 = gif (8735)** を採用した
      - **⚠ 運用注意**: `_thumbnail_id` は **catalog からの import では復元されない**
        (`wp media import` + `wp post meta update` で作った投入経路の外の状態)。
        **`wp ts reset` したら再実行が要る** → 手順を
        S.73 の全消し再構築の後処理に追記済み
