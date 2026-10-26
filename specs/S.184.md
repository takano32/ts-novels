# S.184 パイロット投入 (T.8.4、元の 2.4)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 2.4
- 関連: T.8.4

- [x] 2.4 サーバでパイロット投入: `wp ts import --limit=100`(T.7.5.1 で確定済みの分のみ)→ 検収は
      **WP-CLI(`wp post list --post_type=ts_work`、meta 確認)+curl(キャッシュバスター付)**で:
      パーマリンク・タクソノミー・メタ・noindex メタ/ヘッダ
      - **2.4 実測 (2026-08-30)**: 直前に復旧点 `~/dumps/pre-pilot-20260830-1734.sql`。
        `wp ts sync-terms` = **created=1734 updated=0 skipped=0 / 5.6 秒**
        (= 作者 322 + genre 196 + type 165 + keyword 1,032 + world 14 + corpus 5。catalog と完全一致)。
        `wp ts import --limit=100` = **created=1513 updated=38 skipped=0 / 15.5 秒**。
        **`--limit` は episodes ループにしか効かない**(works 1,451 は毎回全部投入される)ので
        created 1,513 = works 1,451 + 連載話の子投稿 62、updated 38 = 単発 work への本文流し込み。
        62 + 38 = 100 で辻褄が合う(仕様どおり。タスクリストの「100 件」という語感とはずれる)。
        再実行で **created=0**(パイロットの受け入れ条件は達成)。ただし updated は 76 で
        0 にならない → **2.2 (A)** 参照。
        curl 検収(全て `?_cb=$RANDOM` 付き): 単発 work `/works/yaji-trans11/` **200
        `<title>trans11 – 少年少女文庫</title>`**、`/works/unattributed-hanayochanshiriizu-hobosan/`
        **200**、`/works/mashiro_yuu-ahujouri03-1/` **200**、連載子投稿
        `/works/johdan-tsukinuke/<話>/` **200 `<title>突き抜け …</title>`**、
        `/works/sakiemon-hanayochanshiriizu/<話>/` **200**
      - ~~**要判断**: 連載の子投稿の slug が題名由来のパーセントエンコードになる~~
        → **決着 (2026-08-30、コミット `70873826`)**。`upsert_episode()` が
        **原ファイル名の語幹から決定的に ASCII slug を作る**ようになった
        (例 `/novel/unattributed-triangle-equation/triangle_equation2/`、
        `henkaku25` `hunter25` `revival_girl25`)。
        **パーセントエンコードの post_name は 4,363 投稿中 1 件だけ**残る:
        ID 8038「ＢＢＳ☆ハプニング！(原題：BBS OOOOOOPS!)」の
        `story3-%ef%bd%82%ef%bd%82%ef%bd%93`(= `story3-ｂｂｓ`。原ファイル名の語幹が
        **全角英字**なので `sanitize_title` がエンコードする)。実害は小さいが
        気になるなら slug 生成で全角→半角の正規化を足す
      - **URL 基底が `/works/` → `/novel/` に変更された (2026-08-30、コミット `2bd22a7d`。
        ユーザ裁定)**。上の実測ログの `/works/…` は当時の値。現行 URL は `/novel/…`。
        なお旧 `/works/<slug>/` は WP の正規化リダイレクトで **301 → `/novel/<slug>/`**
        になり(404 ではない)、旧 URL も生きたまま
