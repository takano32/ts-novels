# S.20 設計原理と全体像

- 種類: 設計
- 状態: 一部失効 (S.35・S.39 で上書き)
- 出典: docs/wordpress-library-design.md の冒頭と「設計原理と全体像」
- 関連: T.5.1, S.35, S.39

## 少年少女文庫 WordPress ライブラリ 最終統合設計

用語 (WP サイト = 移築先の WordPress / 原本アーカイブ = 原本を保つ GitHub Pages ミラー / 旧サイト = 消滅した原サイト、
ほか) は S.14〜S.19 を優先する。**この呼び分け (S.15) は内部用語**であり、
サイト上の表示文言・about ページ・バナー・書誌カードのラベルには使わない
(読者には「原本を見る」「当時のページ」のように具体的に書く)。

土台は審査で 2/3 の審査員が勝者指名した **data-model 案 (正準データモデルファースト)**。そこへ preservation 案の核 (原パス空間の温存・リポジトリ純潔原則・kansou 非インポート)、ops-simple 案の核 (常時稼働サーバゼロ・mu-plugin・make publish 一発化)、reader-ux 案の核 (メタ先行投入マイルストーン・軽量リーダー UX)、および審査員の全指摘 (BR 再フロー封印、guestbook 矛盾の削除、GitHub Issue 窓口の不採用、ヘッダ制御可能ホスト、410、lychee、再構築演習) を取り込んだ。

### 設計原理と全体像

**3原則**

1. **単一真実源は `catalog/`** — 正規目録 lib1–73.html の 2,887 エントリ (+旧目録 lib01–09 の重複排除後差分) を正規化した JSONL 群を git 管理し、WordPress DB は `wp ts import` でいつでも全再構築できる「使い捨て可能な派生ビュー」とする。WP 内での手動編集は原則禁止し、修正はすべて catalog 側の overrides ファイルで行う (審査員指摘の「export-manual 還流の腐敗」リスクを、還流機構ではなく編集経路の一本化で構造的に消す)。
2. **公開面は完全静的** — WP は非公開オリジン (手元 Docker Compose、必要時のみ起動)。Simply Static で書き出した「WP サイト」と、原パス空間をそのまま保持した「原本アーカイブの配信物」を 1 つの静的サイトにマージして配信。常時稼働サーバゼロで 10 年放置耐性を確保する。

   > **【失効】** S.35 により公開面は `novels.xwp.jp` の**ライブ WordPress** に変更(移行先が Xserver for WordPress に決まり、一般ユーザ権限で Docker が使えないため非公開 Docker オリジン+Simply Static の静的書き出しは採らない)。
3. **原本無改変・加工は配信物のみ** — git 作業ツリーの原ファイルには一切触れない。mailto 除去・往還バナー注入・noindex 付与はすべてビルド時に deploy artifact へ施工する (ops-simple の「別コミット直接施工」は不採用。施工スクリプトが git 管理され、成果物はデプロイブランチに載るため来歴も残る)。

**層構造**

```
git リポジトリ (原本18,006ファイル + catalog/ + scripts/wp/ + takedown/)
 ├─ scripts/wp/catalog_build.py ─→ catalog/*.jsonl (単一真実源)
 │        └─ wp ts import ─→ 非公開WP(Docker) ─→ Simply Static ─→ dist/wp/   (予約名前空間)
 ├─ scripts/wp/annex_build.py ──────────────────────────────────→ dist/annex/ (原パス空間・マスク済)
 └─ merge + pagefind + verify ─→ dist/site/ ─→ Cloudflare Pages (1ドメイン)
```

旧 URL (`/novel/200209/19204751/d_upboy06.htm`、`/lib12.html`、`/~ts/kansou/bbs@log_johdan.cgi`、`/~yays/…`) は**マージ後もドキュメントルート直下の原パスのままファイルとして存在**するため、リダイレクト表なしで 1999 年以来のリンクが生きる (preservation 案の nginx 素通しを静的マージに移植。ops-simple の `/annex/` 接頭辞方式は原 URL 空間を壊すため不採用)。

> **【失効】** S.35 により上の層構造は二拠点分業に変更(`dist/` への静的マージ・Cloudflare Pages・Simply Static は不採用。旧 URL 空間を保つのは原本アーカイブ = GitHub Pages 側で、WP サイト `novels.xwp.jp` は `.cgi` を含む URL を前段が 403 にするため原パス空間を持てない)。ファイル数「17,215」も実測 18,006 に訂正(数え方は S.49)。
