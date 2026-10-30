# S.204 holding 分の検証と収録 (T.4.4)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の「定常運用」の 8.4
- 関連: T.4.4, T.34, S.91

- [x] T.4.4 **holding 分の検証と収録** (第 4 次サルベージの続き) — **2026-08-30 完了**。
      `~/ts-novels-holding/stage_repost` の 13 点を全部同定した。結果:
      - **収録 3 点** → `reposts/repost__bcwiki__{ama,saidai_no_higaisha,bangai3ts}.*`。
        出所は**きりか進ノ介さんの現行サイト `bc-cafe.net/bcwiki.old/`**
        (ページ「トレイル/虹色のかけら」の孤立添付。md5・登録日時つきで
        `reposts/repost__bcwiki__provenance.json` に記録)。3 点とも**文庫未掲載**なので
        きらいなもの→ＧＷ と同じ `corpus=extern-repost` 相当の扱いになる。
        `saidai_no_higaisha` は作者自身が性描写を注記しているので年齢表示の要否を要判断
      - **重複 8 点は不収録** — ゴールデンロード 0A/0B と wiki 3 頁は
        `kirika.novels.name/wiki` に、ヴァルキュリア外伝 1/2 は
        `novel/200210/28213920/valkyrie_ex{1,2}.htm` に、ディレイド 4/5 は
        `novel/201009/16215412/delayed0{4,5}.htm` に既収蔵で本文一致
      - **同定の訂正 2 件**: `あま.txt` は欠落作品「二代目は海女」
        (`novel/201004/02215640/ama.htm`) **ではない**(本文に海女もセバスチャンも無く、
        添付の登録が 2008-09 で掲載日 2010-05 に先行)。また「天女の末裔」は欠落
        `novel/200011/15112734/nouryo_title.html`(BAF「納涼ＴｏＳｉ伝説」)と**無関係**で、
        文庫版は `novel/200707/14133526/tennyo.htm` に既収蔵。holding のものは
        作者サイトの改訂版(本文が別テキスト)なので不収録
      - 派生: 上記の出所調査から `bc-cafe.net/bcwiki.old/` の**全 297 ファイルを新規収蔵**
        (未収蔵作品「ホーリーメイデンズ外伝『復活の依代』」ほか。S.51 参照)。
        **catalog への反映は未了** — `repost_build.py` の `ITEMS` に 3 点を足し、
        `bc-cafe.net/` の作品ページを uncatalogued として拾うかを決める必要がある
      - 2026-10-30 追記: 3 点は T.34.2 で `repost_build.py` の `ITEMS` に入れた (episodes 3,844 → 3,847、
        works 1,440 → 1,443、作者はきりか進ノ介さん (slug `kirika`) に 29 → 32 話)。本番は未配備。
        作品ページを拾うかと `saidai_no_higaisha` の年齢表示は T.34.1 (八重洲さんの判断) のまま
