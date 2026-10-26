# S.35 v1.1 改訂: Xserver for WordPress の実測に基づく再設計

- 種類: 設計
- 状態: 一部失効 (S.42・S.43 で上書き。全域 noindex と .htaccess の 410)
- 出典: docs/wordpress-library-design.md の「v1.1 改訂」の章
- 関連: T.5.2, S.20, S.42, S.43

## v1.1 改訂: XServer for WordPress 実測に基づく再設計 (2026-08-30)

移行先が **Xserver for WordPress**(`novels.xwp.jp` / sv3.xwp.ne.jp)に決定し、SSH で実地調査・実地テストを行った結果に基づく改訂。

### 実測結果 (ssh novels)

| 項目 | 実測 |
|---|---|
| 基盤 | wpX 系: **nginx キャッシュ → Apache (.htaccess 有効) → PHP 8.0.30**。Rocky Linux 8、一般ユーザ権限 (Docker 不可) |
| 設置済み | WordPress 7.1 @ https://novels.xwp.jp (素の状態 + CloudSecure WP Security 有効・ログイン URL 変更済み) |
| ツール | WP-CLI 2.8.1・mysql・rsync・git・curl・python3.6 が最初から利用可 |
| .htaccess | `Header set X-Robots-Tag` ✓ / `Redirect gone`(真の 410)✓ / mod_rewrite ✓ / `~`・`@` を含むパス ✓ — いずれも本番 URL で検証済み |
| **致命制約** | **ファイル名に `.cgi` を含む URL は前段 nginx が一律 403**。RemoveHandler・改名 (`.cgi.html`)・RewriteRule のいずれでも回避不能 (Apache に届かない)。ミラーの **9,022 ファイル**が該当(フルパスに `.cgi` セグメントを含む数え方。**basename 基準なら 9,021** — S.56) |
| 規模感 | ミラー全体 427MB / novel/ 画像 1,196 点 56MB / ホスト側ディスクは十分 |

### 再設計の決定

1. **二拠点分業が制約により確定** — 旧・要決定事項 Q2 の推奨「Pages 廃止・一本化」を撤回する。
   - **novels.xwp.jp** = WP サイト = WP ライブラリ (公開面・canonical・**全 3,844 話**。v1.1 執筆時の「2,887 話」は正規目録分だけの数で、その後 v1.4 の目録外収蔵 859・旧目録 97・文庫未掲載 1 が加わった)
   - **GitHub Pages 現行ミラー** = 原本アーカイブ = 原本 (`.cgi` を含む**全 18,006 ファイル**の URL 空間をそのまま維持 — 静的配信なので `.cgi` も無害に返せる。稼働実績あり。数え方は S.49 の「ミラー相当」)
   - 相互リンク: WP 書誌カード → Pages 原本 / Pages 側はビルド時バナー注入で `path_map.json` から WP へ誘導
2. **Docker は構成から除外** — catalog 生成は開発機、投入はサーバ上の WP-CLI。`wp ts import` が冪等で DB が使い捨てなので、**全域 noindex の限定公開期間中は本番サイト自体がステージング**。隔離実験が必要になった場合のみ docker context で他マシンを使う (任意・非必須)。
3. **権利ゲートは novels 側 .htaccess で実装** — 全域 `X-Robots-Tag: noindex` → 段階解禁、denylist からの `Redirect gone` 自動生成 (410)。**原本アーカイブ側 (Pages) はヘッダ制御不可**のため、ビルド時 meta noindex 注入+削除は 404 で妥協 (denylist 四層同期は従来どおり)。
4. **novel/ 画像 1,196 点 (56MB) は novels 側にも複製配備** — 読書体験を Pages 非依存にする (`/assets/annex-img/` に相対構造ごと rsync、本文の img src はそこへ)。eyecatch の featured image 化は従来どおり(**実体 4 ファイル・参照 15 話**。v1.0 の「109 件」は参照回数)。
5. **mu-plugin は PHP 8.0 互換で書く** (サーバ CLI/web とも 8.0.30。パネルで上げられるなら 8.2+ 推奨)。
6. **インポート後の nginx キャッシュクリア**を publish 手順に追加 (wpX のキャッシュ削除操作)。CloudSecure のログイン URL 変更は現状維持。

### 改訂後のデプロイフロー

```
[開発機]  make catalog        # catalog_build + work_builder + body_extract (python3.11)
          rsync catalog/ bodies/ mu-plugins/ themes/ assets-img/  → novels:~/novels.xwp.jp/
[novels]  wp ts sync-terms && wp ts import && wp ts apply-takedown && wp ts verify
          (.htaccess を denylist から再生成 → 410 反映)
          キャッシュクリア
[Pages]   原本アーカイブは既存の deploy-pages.yml のまま (バナー/noindex/マスクは今後ビルド注入に移行)
```

ロードマップへの影響: T.6 の「ホスト決定」は完了。T.8 の「docker 環境」は「novels SSH/WP-CLI 疎通 (確認済み)+rsync デプロイスクリプト」に置換。他フェーズは不変。

### 要決定事項の更新

- ~~Q1 公開ドメイン~~ → **決定: novels.xwp.jp** (将来カスタムドメインを被せる余地あり)
- ~~Q2 現行 Pages ミラーの処遇~~ → **決定: 原本アーカイブ (原本レイヤ) として恒久併存** (プラットフォーム制約による必然)
- Q3〜Q7 は引き続き選択待ち
