# S.186 .htaccess のブロック管理 (T.8.6、元の 2.6)

- 種類: 記録
- 状態: 一部失効 (S.119 で上書き)
- 出典: docs/wp-implementation-tasks.md の 2.6
- 関連: T.8.6, S.119

- [x] 2.6 .htaccess の noindex/410 ブロック管理(**v1.5: 全域 noindex は行わない**。
      恒久 noindex 層 /boards/ /dojo/ のヘッダと denylist 410 のみ):
      (a) サーバ上で `cp .htaccess .htaccess.bak-YYYYMMDD` を必ず先行
      (b) `scripts/wp/htaccess_block.py` (実装時に改名) は**マーカーコメント間の追記ブロックのみ**をローカル生成
      (c) 挿入は ssh 経由のサーバ上編集で行い、**既存 .htaccess 全文をローカルに保存・コミットしない**
      (d) 直後に無関係 URL 1 本の 200 と `X-Robots-Tag` ヘッダを curl(バスター付)で確認、
      失敗時は .bak を戻す
      - **2.6 実測 (2026-08-30, 高品位なモデルとエフォート)**: `scripts/wp/htaccess_block.py` を実装し本番適用済み。
        dry-run でブロック表示 → `--install` で .bak 先行・メモリ内差し替え(全文は表示も保存もしない)・
        curl 検証・失敗時自動復元。初回は検証 NG → 自動復元が発動: mod_rewrite の内部リダイレクト後は
        env が `REDIRECT_TS_NOINDEX` になる Apache の仕様で、`Header … env=` を両名義にして解決。
        検証: 無関係 URL 200 + X-Robots-Tag なし / `/dojo/` 200 + noindex あり。
        原本バックアップ `.htaccess.bak-20260830` はサーバ残置(cp -n なのでブロック適用前の原本)
