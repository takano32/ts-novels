# S.84 Claude Code の memory の移し方と、移設の完了判定

- 種類: 手順
- 状態: 有効
- 出典: docs/relocation.md の「5. Claude Code の memory の移し方」「6. 移設が終わった判定」
- 関連: T.13, S.82

## 5. Claude Code の memory の移し方

memory はプロジェクトのパスを鍵にした場所にある。パス鍵 = 作業ディレクトリの絶対パスの `/` を `-` にしたもの。

```bash
# 旧機: /home/takano32/GitHub/ts-novels → 鍵 -home-takano32-GitHub-ts-novels
# 新機で同じパスに clone したなら鍵も同じ
rsync -av ~/.claude/projects/-home-takano32-GitHub-ts-novels/memory/ \
      NEWHOST:~/.claude/projects/<新機のパス鍵>/memory/
```

中身 (方針・裁定・教訓) は `MEMORY.md` が索引。特に `wp-build-status.md`
(現状と未決事項) と `delegate-work-to-opus.md` (作業分担の決まり) は再開時に読むこと。
memory を運べなかった場合でも、同じ内容は S.92〜S.97 と TASKS.md・S.170〜S.204 に残してある。

## 6. 移設が終わった判定

- [x] `make catalog` 後に `git status` が空、`make check` の NG が 0
- [x] `payload_build.py` が無損失 OK・アドレス残存 0
- [x] `scripts/wp/novels-ssh … wp ts verify` が OK
- [x] `deploy.sh` (dry-run) が通り、3-6 の checksum 比較が全て 0 件
- [x] `~/ts-novels-holding/` と memory を運んだ

2026-10-26 新機 `a1-free` で全項目を確認済み (checksum 比較は 3 系統とも 0 件、mu-plugins・テーマも一致)。
