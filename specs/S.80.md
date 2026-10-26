# S.80 削除 ⑤: /removed/ に掲載

- 種類: 手順
- 状態: 一部失効 (S.131 で上書き。動的生成)
- 出典: docs/removal-runbook.md の「⑤ /removed/ に掲載」
- 関連: T.32.2, S.131

## ⑤ `/removed/` に掲載 (1 週間以内)

- 掲載するのは **作者名 + 作品名 + 理由の要約 (1 行) + 削除日**のみ。
  依頼者の身元・依頼文は載せない。作者本人が名前の掲載も望まない場合は
  「作者の申し出により 1 作品を削除 (YYYY-MM)」の形に匿名化する。
- 目的は**再収蔵の防止**。将来の回収作業者 (人間・実行セッション問わず) が
  「なぜここに穴があるのか」を確認できる場所を 1 つにする。

```sh
scripts/wp/novels-ssh 'cd ~/novels.xwp.jp/public_html && wp post list --post_type=page --name=removed'
# /removed/ の本文を catalog 側から再生成して更新 (手動編集はしない)
```
