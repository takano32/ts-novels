# S.60 本番への接続と身元

- 種類: 実測
- 状態: 有効
- 出典: docs/environment.md の「接続と身元」
- 関連: T.13.3

## 接続と身元

```sh
scripts/wp/novels-ssh  # = ssh -p 10022 -o ForwardAgent=yes novels@sv3.xwp.ne.jp。~/.ssh/config は使わない。鍵認証・パスフレーズなし
```

| 項目 | 実測値 |
|---|---|
| ログインユーザ | `novels` (グループ `members`)。**一般ユーザ権限。root なし・Docker 不可** |
| ホスト名 | `sv3.xwp.ne.jp` |
| OS | Rocky Linux 8.10 (Green Obsidian) / カーネルは Ubuntu ベースの共有基盤 (`5.15.0-186-bet02-generic`) |
| ホーム | `/home/novels` |
| シェル | `/bin/bash` |
| プロセス上限 | `ulimit -u` = 2000 |
| サービス | Xserver for WordPress (wpX 系)。公開 URL は **`https://novels.xwp.jp`** |

ssh のログインバナーに post-quantum 鍵交換の警告が出るが、接続自体は正常。
