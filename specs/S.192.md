# S.192 テーマ: 状況と ts-bunko (T.10.2)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の「テーマ・索引・読書 UX」の段の状況と最初の行 (いまの T.10)
- 関連: T.10, T.10.2, T.39.1, T.39.3, S.27, S.94, S.240

## T.10 — テーマ・索引・読書 UX(目安 2〜3 週)

**状況 (2026-08-30 配備・検収。コミット `242efc62`)**: T.10.1〜T.10.5 は**配備して動作確認済み**。
テーマ `ts-bunko` 0.1.0 を有効化し、`wp ts sync-pages`(created=4)・`wp ts sync-docs`(created=34)・
`wp ts import` を実行。**`wp ts verify` OK**(連載親への term 合併後の新期待値
**ts_genre 6182 / ts_type 5275 / ts_keyword 7128 / ts_world 495 / ts_corpus 4537 /
ts_author 4363** がすべて一致、書誌メタ・本文 status は不変)。
**主要 22 URL を巡回して全部 200・PHP エラー 0・`</html>` 到達 0 欠け**、
外部 CDN/フォントの読み込み **0 件**(設計 (S.27) の要件)。
検収で出した軽微な指摘 3 件は **`82c98eba` で全て解消**(各タスクの「気づき」参照)。
**T.10 は完了。次は所有者レビュー (T.29) で、ここで止まる。**
レビュー資料は **S.92〜S.97** に作成済み
(レビュー用 URL 25 本・ブラウザ確認の観点・既知の未了 12 件)。

**所有者レビューの時点で新たに見つかった要判断 1 件 (T.39.1)**: **WordPress の初期コンテンツが公開のまま残っている** —
`https://novels.xwp.jp/sample-page/`(サンプルページ)と
`https://novels.xwp.jp/2026/07/23/hello-world/`(Hello world!)が**どちらも 200 で開ける**
(`privacy-policy` は draft なので 404 で無害)。
**本番 DB を実行セッションの判断で消さない方針**なので削除していない。
消す指示をもらえば `wp post delete` で対応する。

- [x] T.10.2 (テーマ) ブロックテーマ `ts-bunko`(リポジトリ管理→rsync 配備): S.27 のとおり。
      システムフォントスタック・本文 38em・書誌カード(初出/回収経路/原本リンク/~yays 初出版/
      当時の感想)・話ナビ(menu_order)。**タクソノミーアーカイブ(genre/type/keyword/world)は
      テーマの標準テンプレートで提供**(= S.26 の「索引」の内数)。
      **作者ページの Homepage リンクは既定で homepage_wayback、現存確認済みの移転先のみ生 URL**
      (ドメインスクワット対策)。外部 CDN/フォント読み込みなし
      - **書誌カードのラベルは読者に見える表示文言**。「原本を見る」「初出時の姿(~yays 版)」
        「当時の感想を読む」のように具体的に書き、**「別館」「本館」等の内部語は出さない**
        (→ S.14〜S.19)
      - **テーマの実測 (2026-08-30)**: `wp theme activate ts-bunko` 成功、**ts-bunko 0.1.0** が有効。
        `wp rewrite flush --hard` 後の rewrite ルール **187 本**。
        話ページ `/novel/johdan-tsukinuke/19204751-tsukinuke/`(200・86,398B)で確認できたもの:
        **パンくず**(`<nav class="ts-breadcrumb">` = 城弾 › 突き抜け › 突き抜け)・
        **分類チップ**(`ts-chip` = コメディ / 変身 / ぺド / マッチョ)・**話ナビ**
        (`ts-episode-nav`)・**書誌カード**・**「無改変の原本を読む」**リンク。
        原本リンク先は **`https://takano32.github.io/ts-novels/novel/200209/19204751/tsukinuke.htm`
        で実際に 200**(死んでいる ts.novels.jp ではなく Pages を指している)。
        `style.css` **200 text/css 9,004B**。
        **外部ホストへの参照は 2 本だけで、どちらも意図的な原本アーカイブ
        (Pages の原本と感想板)。CDN・Web フォントの読み込みは 0 件**
      - **内部語の露出 0**: `/` `/about/` `/archive/` `/takedown/` `/removed/` `/index/kana/`
        のいずれにも「本館」「別館」「旧館」は**出現しない**(表示文言の規則を満たす)
      - **タクソノミーアーカイブ 5 本とも 200**: `/genre/senkihaoazuke/`・
        `/type/tshakonkainashi/`・`/keyword/toka/`・`/world/fms/`(FMS シリーズ・作品 5 本)・
        `/authors/million/`(100万Hit・作品 11 本)。`ts_corpus` だけ `rewrite=false`・
        `public=0` で URL を持たない(収蔵区分は内部用なので設計どおり)
      - **連載親への term 合併付与の確認 (コミット `1789204f`)**: `/genre/gakuen/` は
        200・34,305B で **作品 60 本**が並び、その内訳は
        **連載の親 30 / 単発 30**(例「キャリア・デイ　９７」10 話・「美少年も楽じゃない」4 話・
        「オニキス」6 話)。**子の話は 1 本も出ない**(作品単位の一覧になっている)。
        並びは **post_date の昇順 = 発表順**(先頭 1997-12-04 → 以降昇順であることを実測)。
        合併前は連載が分類アーカイブに出てこなかったので、これで書庫に連載が並ぶようになった
      - **連載の目次**: 親 `/novel/johdan-tsukinuke/` は 200 で子 2 話を列挙。
        最大の連載「戦乙女セーラ」`/novel/johdan-senotomeseera/` は
        **69 話が menu_order どおり(vol.1 → vol.2 → EPISODE2 → vol.3 …)に並ぶ**
