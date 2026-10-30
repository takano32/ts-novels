# S.67 危険操作チェックリスト

- 種類: 手順
- 状態: 有効
- 出典: docs/environment.md の「危険操作チェックリスト」
- 関連: T.22.4, T.23.6, S.10, S.42, S.76, S.77, S.116, S.121

## 危険操作チェックリスト

作業を始める前に、該当する行を上から順に確認する。

### rsync (配備)

- [ ] **`--delete` を使っていない**。宛先 `~/novels.xwp.jp/` 直下には `wp-config.php` が居る
- [ ] **`-n` (dry-run) を先に流し**、転送されるファイル一覧を目で見た
- [ ] mu-plugins の宛先は `public_html/wp-content/mu-plugins/` になっている
- [ ] 開発機側のパスに `__pycache__` `.venv` が混ざっていない

### DB を変える前 (import / apply-takedown / option 変更)

- [ ] `mkdir -p ~/dumps` してから `wp db export ~/dumps/pre-<用途>-$(date +%Y%m%d-%H%M).sql`
- [ ] ダンプは**私有ストレージへ退避**。公開リポジトリに入れない
- [ ] `--dry-run` があるコマンドは先に dry-run
- [ ] 実行後、**もう一度同じコマンドを流して created=0 / updated=0**(冪等性の受け入れ条件)。`wp ts role-sync` も同じ (2 回目は created=0 / updated=0。S.116)
- [ ] `wp ts apply-takedown` の後は、2 回目が「private 化 0 / 復帰 0 / 変更なし …」になり、`wp post list --post_type=ts_work --post_status=private --format=count` が取り下げの件数、`--post_status=draft` が 0 であることを確かめる (S.76、S.121)

### `.htaccess` を変える前

- [ ] `scripts/wp/htaccess_block.py --install` で入れる (原本 `.htaccess.orig` と直前の `.htaccess.bak-<日時>` を自動で残し、検証 NG なら自動で戻す。S.77)。手で触るなら `cp .htaccess .htaccess.bak-$(date +%Y%m%d)` を先に
- [ ] 書き込むのは**自分のマーカーブロックの中だけ**。取り下げの 410 で書くのは画像だけ (WP の URL の 410 は WordPress が返す。S.119)
- [ ] 全文をローカルに保存・コミットしていない(ログイン URL 秘匿値)
- [ ] 変更直後に「対象 URL が期待どおり」+「**無関係な URL が 200 のまま**」を curl で確認
- [ ] 壊れていたら即 `.bak` を戻す

### 本番での実験

- [ ] `public_html/_probe/` の中だけで行う
- [ ] **終わったら必ず消す**
- [ ] 実験結果が「反映されない」ときは、修正を重ねる前に**キャッシュを疑う**(S.62)

### 公開に関わるもの

(「公開解禁まで全域 noindex を維持している」の行は S.42 で失効したので 2026-10-30 に削除した。T.22.4)

- [ ] 感想板 `/boards/` ・道場 `/dojo/` ・`/assets/annex-img/` は**解禁後も恒久 noindex**
- [ ] メールアドレスは catalog 生成段階で除去済み(**DB に入れない**)
- [ ] 取り下げた作品の確認は**ログインなし**の curl で行う (410 が期待)。運営委員や管理者でログインした状態では 200 + 帯が正しい応答 (S.118)
