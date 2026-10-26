# S.194 索引ページ群 (T.10.3、元の 4.3)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 4.3
- 関連: T.10.3

- [x] 4.3 索引ページ群: `/index/kana/{a..wa}/`・`/index/timeline/`・`/index/bunrui/`・
      `/index/vocabulary/`・`/index/osusume/`(`_ts_osusume(_in)` から)
      - **4.3 実測 (2026-08-30。すべて 200・PHP エラー 0)**:
        `/index/kana/`(作者さくいん・22,708B・内部リンク 21)/
        `/index/timeline/`(年表・22,539B・20)/ `/index/bunrui/`(分類さくいん・73,343B・375)/
        `/index/vocabulary/`(キーワードさくいん・115,903B・**1,032** = keyword term 数と一致)/
        `/index/osusume/`(オススメの環・207,340B・1,038)/
        `/index/docs/`(運営文書・資料・23,735B・**34** = `sync-docs` の件数と一致)
      - ~~**気づき ①**: `/index/docs/` だけトップページからリンクされていない~~
        → **`82c98eba` で解消**。トップのさくいん入口が **6 本**になり
        「運営文書・資料」が出る(作者さくいん / 年表 / 分類さくいん /
        キーワードさくいん / オススメの環 / 運営文書・資料)
      - ~~**気づき ②**: `/index/docs/` の `<h1>` が汎用の「さくいん」~~
        → **`82c98eba` で解消**。h1・`<title>` とも**「運営文書・資料」**に統一
