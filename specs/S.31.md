# S.31 運用 (設計 v1.0 の 8)

- 種類: 設計
- 状態: 一部失効 (S.35・S.39 で上書き)
- 出典: docs/wordpress-library-design.md の「8. 運用」
- 関連: T.38, S.35, S.47

## 8. 運用

**構成**: 公開面 = **Cloudflare Pages** (ヘッダ制御 `_headers`・Pages Functions で 410 — GitHub Pages 単独では X-Robots-Tag も 410 も返せず takedown SLA が弱る、という審査員3の指摘に基づく決定。Netlify は 410 を `_redirects` だけで書ける代替)。WP オリジン = リポジトリ管理の `docker/compose.yml` (wordpress + mariadb、PHP/WP バージョン固定)、インターネット非公開、必要時のみ起動。常時稼働サーバゼロ = 保守・攻撃面・費用が実質ゼロ (ドメイン代のみ)。

> **【失効】** v1.1 により構成を変更 — 公開面は **Xserver for WordPress (`novels.xwp.jp`) のライブ WP**、原本アーカイブは **GitHub Pages 恒久併存**。**Docker は使わない**(サーバは一般ユーザ権限。catalog 生成は開発機、投入はサーバ上の WP-CLI)。`X-Robots-Tag` と真の 410 は novels 側 `.htaccess` で本番検証済みなので、Cloudflare Pages を選んだ理由そのものが消えている。

**プラグイン最小構成 (3 点)**: (1) 自作 `ts-library` — **mu-plugins/ 配置** (管理画面から無効化不能 = コンテンツモデルが消えない。全審査員が推奨)。CPT/タクソノミー/メタ登録・WP-CLI コマンド群・meta robots/canonical 出力・テンプレタグを集約 (2) Simply Static (静的書き出し) (3) WP Multibyte Patch。SEO/キャッシュ/リダイレクト/Akismet/統計系は入れない。検索は Pagefind CLI (WP 非依存)。

> **【失効】** プラグインは **2 点 (自作 `ts-library` mu-plugin + WP Multibyte Patch)** に変更 — **Simply Static** は v1.1 で静的書き出しをやめたため不要、**Pagefind** は v1.2 補足 2 で WP 標準検索+ファセット絞込に置換した。

**バックアップ/再現性**: 一次ソースは常に git (原本 + catalog/ + takedown/ + mu-plugin + テーマ。`docker/` は v1.1 で不採用)。WP DB は `wp ts import` で全再構築できる家畜。加えて publish のたびに `wp db export` を dumps/ (私有ストレージ) へ。uploads/ は eyecatch (実体 4 ファイル・参照 15 話) のみで再 sideload 可能。**年 1 回の「素の VM から `make publish` まで」再構築演習** (初年度は T.9 完了直後に 1 回実施) でドキュメント腐敗を防ぐ。ランブック 2 本: S.69〜S.71 / S.75〜S.81。

**監視**: 静的につき最小 — UptimeRobot 無料枠 (任意) + publish 時 verify (件数照合・衝突検査・lychee サンプル) + 年 2 回リンクフルクロール。
