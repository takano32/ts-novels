# S.176 slug の人手確認と確定 (T.7.5.1)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 1.5b
- 関連: T.7.5.1, S.107, S.217, T.40.1

- [x] T.7.5.1 (サイト所有者の確認) work_overrides.yml と slug_overrides.yml の確認・確定。
      **これが済むまで S.185 (全量投入) に進まない**(needs_review 分の恒久 URL が変わるため。
      2.4 のパイロット 100 件は確定済み分のみで先行可)
      - **確定 (2026-08-30)**: 作者 slug **42 件を `status: confirmed`** にした
        (資料 S.217。元の `catalog/review/authors-slugs.md`、裁定は `catalog/slug_overrides.yml` の
        `authors:` セクションと同ファイル冒頭のコメント)。作品 slug 1,451 件と
        分類語彙 slug 1,407 件は**サイト所有者の決定により自動生成のまま採用**(確認しない)。
        `work_overrides.yml` の needs_review 331 件も確認せず採用
      - **裁定を build 側に追随させた (実装 3 本)**:
        (a) **作者の併合** — 複数の表示名が同じ確定 slug を指すとき 1 人に統合し、
        表示名は `display_variants` に集約する処理を `authors_build.py` に追加。
        **16 組・作者 17 名が統合で消え、`kamikawa_ayano_` の廃止と合わせて
        作者は 342 → 322 名**(数え方: `catalog/authors.json` の `authors` 配列長 /
        `catalog/reports/authors_build.json` の `author_merges` と
        `authors_removed_by_merge`)。統合の一覧は
        `catalog/reports/authors_build.json` の `author_merges` に全件残る
        (slents ← 根室　眞琴 / 根室　眞琴改め…、marie ← 麗香、popo ← HIKU / ぽぽ、
        aizu_rika ← 海津里花、you_ ← you' / you’、qqqqq ← ？？？？ ほか)
        (b) **`role: not-an-author`** — `slug_overrides.yml` の authors 行に書ける新しい欄。
        その表示名では作者を作らない。「シェアワールド」に付けた。該当話
        `novel/200103/24194542/himetitle.html` は**作者不詳ではなく
        `entry_role: series-index`(シリーズ目次)**として扱い、感想板 `himekami` 経由で
        ts_world「妖魔夜行 姫神奇譚シリーズ」(41 話)に紐づく。works 側では
        擬似作者 `series-index`(表示「シリーズ目次」)の下に置き、`unattributed`
        (作者不詳 22 件)と混ぜない
        (c) **`catalog/episode_overrides.yml` を新設**(適用は
        `scripts/wp/episode_overrides.py`。`make catalog` の repost_build 直後)。
        **旧サイトの原本 `lib*.html` の誤植を catalog 側で上書きする唯一の場所**で、
        原本アーカイブ(Pages)は原本レイヤなので誤植をそのまま保つ、という分担。
        `expect:`(上書き前にあるはずの値)を必ず書かせ、合わなければ自己検査が落ちる。
        **episode_id は source_path から導くのでこの上書きでは変わらない**
      - **同名 2 組の裁定の実施結果**:
        - `神川綾乃`: `lib45.html#12` だけが指していた実在しない板 `kamikawa_ayano_` を
          `kamikawa_ayano` に上書き。**作者 `kamikawa_ayano_` は消え、
          `kamikawa_ayano` が 34 → 35 話**
        - `コーディー`: `lib61.html#6`(「蒼い時（第二章）」)だけが指していた
          `jersey_red` を `kohdhi` に上書き。**`kohdhi` が 2 → 3 話(蒼い時 ao1–3 が
          1 作品にまとまった)、`jersey_red` は 27 → 26 話で
          `display_variants` から「コーディー」が消えた**
      - **感想板一覧を第 2 の情報源にした** (S.217 (元の `catalog/review/authors-slugs.md`) の 8-1 の恒久対策):
        `~ts/kansou/bbs@log_.cgi`(**290 板**。うち共有世界の板 9 を除く作者板 281、
        `<title>` から作者名が取れたもの 278)と、**板一覧に載らない板ファイル 16 本**の
        `<title>` を `authors_build.py` が読む。目録から一度もリンクされていない板を
        持つ**作者 8 名**(この市場 / Ｍａｃｆｉｓｔ / 由衣 / 西さとる / ふう / 佐藤由衣 /
        you' / ぽぽ)がこれで板 id に載り、**板を持つ作者は 294 → 300 名**に増えた。
        旧名義・別名義の板は `authors.json` の新欄 **`kansou_slug_alt`** に残る
        (薪喬 → `shinkyou`、根室　眞琴 → `memuro_makoto`、HIKU → `hiku`、
        神川綾乃 → `kamikawa_ayano_` など 8 名)
      - **回帰防止の自己検査 3 本を追加**(`authors_build.py`):
        「**confirmed slug と板一覧の不一致 0**」(表示名がいずれかの板を指すのに
        作者 slug がどの板 id とも一致しなければ落ちる = 今回の見落とし 7 名が再発したら
        検知する)・「👤 1.5b の裁定が全て authors.json に出ている」・
        「裁定による併合の記録」。既存の「**slug の重複 0**」は緩めていない
        (別人の slug 衝突を検出する網なので)
      - **再生成の検収 (2026-08-30 実測)**: `make catalog` 所要 46 秒。
        **作者 322 名**(板あり 300 / 板なし 22、slug 確認待ち **0**)・
        **works 1,451 件**(単発 932 / 連載 519、**orphan 0**、needs_review 331)・
        episodes 3,844(**episode_id の集合は再生成前後で完全一致**)・
        `make catalog` を 2 回連続で流して `catalog/` に**差分ゼロ**(冪等)・
        `make check` は全段 OK
