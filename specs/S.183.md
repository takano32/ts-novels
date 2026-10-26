# S.183 デプロイスクリプト deploy.sh (T.8.3、元の 2.3)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 2.3
- 関連: T.8.3

- [x] 2.3 デプロイスクリプト `scripts/wp/deploy.sh`: rsync(`-n` 先行・`--delete` 禁止)で
      catalog/ bodies/ mu-plugins/ を配備(mu-plugins → `public_html/wp-content/mu-plugins/`)。
      **robots.txt(0.4 草案)を `public_html/` へ配備し curl で内容確認**もここに含める
      - **2.3 実測 (2026-08-30)**: 引数なし = dry-run、`--apply` = 実配備。dry-run で
        宛先 4 つ(repo / bodies / mu-plugins / robots.txt)以外に転送が無いこと・
        `--delete` が無いことを確認してから `--apply`。**所要 7.5 秒**
        (bodies 120MB は転送済みだったため差分のみ)。サーバ側 `php -l` は
        commands.php・ts-library.php とも **No syntax errors**、ローダも別途 OK。
        配備後: `~/novels.xwp.jp/repo` が HEAD `cf20f746` に一致、`repo/bodies` **3,642 本**、
        `repo/catalog/episodes.jsonl` **3,844 行** / `works.jsonl` **1,451 行**。
        robots.txt は curl 200 でローカル `scripts/wp/assets/robots.txt` と**バイト一致**
      - **補足**: S.69〜S.71 は deploy.sh が `themes/` と `assets/annex-img/` も運ぶと
        書いているが、**現行 deploy.sh は運ばない**(2.7 は手動 rsync で実施した)。
        どちらかに合わせること
        → **解消 (2026-08-30, 高品位なモデルとエフォート)**: deploy.sh に [5] 画像(`git ls-files novel` の
        jpg/gif/png/bmp → `assets/annex-img/`)と [6] `themes/ts-bunko`(存在すれば)を追加。
        runbook の記述どおりになった
