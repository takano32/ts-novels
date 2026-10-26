# S.26 情報設計と URL (設計 v1.0 の 3)

- 種類: 設計
- 状態: 一部失効 (S.35・S.39・S.200 で上書き)
- 出典: docs/wordpress-library-design.md の「3. 情報設計と URL」
- 関連: T.8.5, S.35, S.39, T.40.1

## 3. 情報設計と URL

### 3.1 名前空間の原則

ドキュメントルート = 原本アーカイブの原パス空間。WP サイトは**予約名前空間**にのみ書き出し、ビルド時の衝突検査で予約外への上書きを機械的に禁止する。予約: `/works/ /authors/ /genre/ /type/ /keyword/ /world/ /year/ /index/ /search/ /docs/ /dojo/ /about/ /takedown/ /removed/ /annex/ /assets/` + ルートの `index.html robots.txt sitemap.xml _headers _redirects`。`/special/` は原本アーカイブの実在パス (03summer 61 ファイル) のため WP は使わない。唯一の意図的衝突はルート index.html (WP トップが取る): 原本ルートトップ (2018 再建骨格、固有コンテンツ実質なしと調査済み) は `/annex/index-2018.html` に複製再掲し `/annex/` 案内に明記する。

> **【失効】** v1.1 により「ドキュメントルート = 原本アーカイブの原パス空間に WP を予約名前空間で重ねる静的マージ」は消滅(WP サイト `novels.xwp.jp` と原本アーカイブ GitHub Pages の**二拠点分業**になり、原パス空間は原本アーカイブだけが持つ)。衝突検査という装置も不要。加えて予約名前空間 `/annex/` は S.200 で **`/archive/`** に改称(URL にも表示にも内部語「別館」を出さないため)。

### 3.2 パーマリンク

| ページ | URL 例 |
|---|---|
| トップ | `/` — サイト趣旨 (閉鎖サイトの資料保存)・収蔵統計 (**3,844 話 / 1,463 作品 / 342 作者 / 1997–2014**。v1.0 の「2,887 作品/399 作者」は正規目録だけの話数と表示名の異なり数)・入口 4 つ (五十音/ジャンル/年代/検索)・**「新着」は置かず「今日の一作」** (works-index.json から日付シードでクライアント選出)・原本アーカイブの案内 (表示文言。「別館」とは書かない)・削除窓口 |
| 作品 | `/novel/johdan-d-upboy/` (あらすじ・作者コメント・推薦文・話一覧・オススメ双方向・原本リンク) |
| 話 | `/novel/johdan-d-upboy/06/` (単発は `/novel/{slug}/` で完結) |
| 作者 | `/authors/writerman/` (全作品年代順・異表記・感想板 (原本アーカイブ) へのリンク・Homepage=Wayback・現役活動先) |
| 分類 | `/genre/gakuen/` `/type/henshin/` `/keyword/orekko/` `/world/kayo-chan/` |
| 年代 | `/year/2003/` (mu-plugin のカスタムリライトで post_date 年別一覧) |
| 索引 | `/index/` ハブ → `/index/kana/a/`〜`/index/kana/wa/` (五十音、lib-index の現代版)、`/index/timeline/` (1997–2014 年表+世代解説)、`/index/bunrui/` (化石索引 bunrui.html の SF/FT/リアル×器/核×自/他 マトリクスを解題付きで再構成)、`/index/vocabulary/` (genre.html 語彙解題)、`/index/osusume/` (推薦グラフ: 被推薦ランキング+推薦者別一覧) |
| 検索 | `/search/` — Pagefind (ビルド時索引・クライアントサイド・自己ホスト JS)。`data-pagefind-filter` で 作者/ジャンル/種別/年 のファセット絞込 |
| 運営 | `/docs/comittee-no1/` 等、`/about/`、`/takedown/`、`/removed/` (削除済みリスト)、`/annex/` (原本アーカイブの 4 区画の案内+gallery/paintbbs 導線。**ページ上の見出し・本文は「原本アーカイブ」等の具体語で書き、「別館」とは書かない**) |
| 道場 | `/dojo/20031015213021/` (T.36) |
| 旧 URL | `/novel/200209/19204751/d_upboy06.htm`・`/lib12.html`・`/library.html`・`/series.html`・`/~ts/kansou/bbs@log_johdan.cgi`・`/~yays/…` — **原本ファイルがそのまま返る (リダイレクト不要)**。alias パスも実ファイルとして残り、バナーで同一 WP URL へ誘導 |

> **【失効】** 上の表のうち 2 行:
> - **検索** — v1.2 補足 2 により **WP 標準検索+タクソノミーのファセット絞込**に変更(Pagefind は「ビルド時に静的サイトを索引する」前提が v1.1 のライブ WP 化で消滅したため)。
> - **旧 URL** — v1.1 により旧 URL を原パスのまま返すのは**原本アーカイブ(GitHub Pages)だけ**に変更(WP サイト `novels.xwp.jp` は `.cgi` を含む URL を前段が一律 403 にするので、原パス空間を丸ごとは載せられない)。
