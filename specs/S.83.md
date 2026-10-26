# S.83 移設で踏んだ運用上の注意

- 種類: 手順
- 状態: 有効
- 出典: docs/relocation.md の「4. 運用上の注意」
- 関連: T.13, S.82

## 4. 運用上の注意 (旧機で踏んだもの)

- **deploy.sh は origin/master に含まれるコミットしか配備しない**。先に push する
- `wp ts reset --yes` は Claude Code の権限分類器に止められる。必要なら所有者に
  `! scripts/wp/novels-ssh 'cd ~/novels.xwp.jp/public_html && wp ts reset --yes'` を手で打ってもらう。
  reset 後は eyecatch の再設定が要る (S.73 の C)
- `wp ts import --bodies=` には **payloads/** を渡す (bodies/ を渡すと全話がプレースホルダ本文になる)
- 本番の `.htaccess` 全文・CloudSecure のログイン URL は文書にもコミットにも書かない
- 検証の curl は必ず `?_cb=$RANDOM` 付き (前段 nginx キャッシュ)
- 作者・関係者への連絡はエージェントにはさせない (所有者経由)
