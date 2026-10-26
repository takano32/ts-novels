# S.67 危険操作チェックリスト

- 種類: 手順
- 状態: 一部失効 (S.42 で上書き。全域 noindex の行)
- 出典: docs/environment.md の「危険操作チェックリスト」
- 関連: T.22.4, T.23.6, S.10, S.42

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
- [ ] 実行後、**もう一度同じコマンドを流して created=0 / updated=0**(冪等性の受け入れ条件)

### `.htaccess` を変える前

- [ ] `cp .htaccess .htaccess.bak-$(date +%Y%m%d)` を実行した
- [ ] 書き込むのは**自分のマーカーブロックの中だけ**
- [ ] 全文をローカルに保存・コミットしていない(ログイン URL 秘匿値)
- [ ] 変更直後に「対象 URL が期待どおり」+「**無関係な URL が 200 のまま**」を curl で確認
- [ ] 壊れていたら即 `.bak` を戻す

### 本番での実験

- [ ] `public_html/_probe/` の中だけで行う
- [ ] **終わったら必ず消す**
- [ ] 実験結果が「反映されない」ときは、修正を重ねる前に**キャッシュを疑う**(S.62)

### 公開に関わるもの

- [ ] 公開解禁(T.37.4)まで**全域 noindex** を維持している
- [ ] 感想板 `/boards/` ・道場 `/dojo/` ・`/assets/annex-img/` は**解禁後も恒久 noindex**
- [ ] メールアドレスは catalog 生成段階で除去済み(**DB に入れない**)
