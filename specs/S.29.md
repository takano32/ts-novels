# S.29 静的ミラーとの併存 (設計 v1.0 の 6)

- 種類: 設計
- 状態: 一部失効 (S.35・S.41・S.43 で上書き)
- 出典: docs/wordpress-library-design.md の「6. 静的ミラー (別館) との併存」
- 関連: T.35, S.35, S.41, S.44

## 6. 静的ミラー (原本アーカイブ) との併存

**境界** (調査の boundary_proposal を採用): WP 化 = 第 2 世代リポジトリ直下の作品層のみ (novel/ 5,017 のうち本文 3,818 + 目録 + comittee/columns/dialy ≒ 全体の 30%)。原本アーカイブに凍結 = 残り 70%: (1) 世代の姿 — ~ezpe (1999)、~yays (2000–02、gallery/paintbbs 含む。~yays/library/ は 95.4% 重複と知りつつ「2002 年春のタイムカプセル」として丸ごと)、ts-novels.jp 2018 再建骨格 (2) 交流ログ — ~ts (kansou/bbs 4,008)、~ezpe/~yays cgi-bin 約 4,800 (3) 閉鎖後姉妹 — ts.novels.name / kirika.novels.name / ts.raa0121.info / www.novels.name (4) 周辺 — ~yaji、www2.sts.co.jp。

> **【失効】** v1.4「最大掲載原則」によりこの範囲限定(WP 化は作品層のみ・残り 70% は原本アーカイブ凍結)は**撤回**(サルベージしたもの・できるものはすべて掲載する。原本アーカイブは原本レイヤとして維持したまま、WP 側にも可能な限り載せる)。実際に目録外収蔵 859 話が `corpus=uncatalogued` で catalog に入っている。

**原本アーカイブの配信物への施工** (annex_build.py、原ファイルは無改変):
- mailto 除去 (リンク属性+表示テキスト。目録 3,569 件・全体 6,445 ファイル)、BBS 投稿者メール除去
- 各 HTML 冒頭に 1 行バナー (**読者に見える表示文言**。「本館」とは書かない):
  「これは原本アーカイブです | 少年少女文庫で読む → {path_map.json から解決した WP URL} | 削除依頼」— ビルド時注入なので nginx sub_filter の gzip/charset 問題 (審査員1) は構造的に消える
- noindex (meta + `_headers` の X-Robots-Tag 両方)
- takedown/denylist.yml 該当ファイルの除外

**正規性**: canonical は常に WP 側 (WP サイトは self-canonical、原本アーカイブは全域恒久 noindex なので重複問題は発生しない)。**削除同期**: `takedown/denylist.yml` を単一情報源とし、(a) `wp ts apply-takedown` (draft 化) (b) annex_build の除外 (c) `functions/_middleware.js` (Cloudflare Pages Functions ~20 行) が該当パスに **410 Gone** を返す (d) git 履歴除去は S.75〜S.81 (git filter-repo、raw-original タグにも残る事実を明記) — 四層が同じファイルを読む。**リンク健全性**: 各 publish の verify で lychee サンプル検査、年 2 回フルクロール (バナー・path_map の黙った腐敗を検出)。

> **【失効】** 削除同期 (c) は v1.1 により **novels 側 `.htaccess` の `Redirect gone`(真の 410)** に変更(Cloudflare Pages を使わなくなったため。本番 URL で検証済み)。原本アーカイブ(GitHub Pages)はヘッダ制御も 410 も返せないので、ビルド時の除外=404 で妥協する。
