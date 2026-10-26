# S.82 作業場所の移設手順 (別サーバへ git clone して続きをやる)

- 種類: 手順
- 状態: 有効
- 出典: docs/relocation.md の冒頭と「0.」〜「3. 手順」
- 関連: T.13, T.38.4, S.83, S.84

## 作業場所の移設手順 (別サーバへ git clone して続きをやる)

2026-10-26 作成。旧作業機 = `/home/takano32/GitHub/ts-novels`。
このリポジトリだけで作業は再開できるが、**git 管理外のものが 4 つある**ので、それだけ手で運ぶか作り直す。

### 0. 移設時点の状態 (ここから再開する)

- WordPress 移築は **T.10 まで完了、所有者レビュー (T.29) 待ち**。
  レビュー資料 S.92〜S.97、タスク一覧 TASKS.md
- **未決の判断が 2 件** (最優先で所有者に相談):
  1. 原本アーカイブ (GitHub Pages) に残る第三者のメールアドレス・IP → S.155 (推奨 A: 伏せ字)
  2. オフ会レポート 2 件 (顔写真) は一時非公開中、/removed/ 掲示は保留 → `takedown/denylist.yml` TD-2026-0010〜0013
- 本番 https://novels.xwp.jp は稼働中。移設中に本番を触る必要はない

### 1. 運ぶもの / 作り直すもの

| もの | 場所 | git | 移設方法 |
|---|---|---|---|
| リポジトリ本体 | `ts-novels/` | ○ | `git clone git@github.com:takano32/ts-novels.git` |
| Python 環境 | `.venv/` | × | `make venv` で作り直す (版は Makefile で固定済み) |
| 本文の生成物 | `bodies/` (123MB) `payloads/` (178MB) `payloads-docs/` | × | **作り直す** (手順 3)。rsync で運んでもよいが、作り直して差分ゼロを確かめる方が移設の検証になる |
| サルベージ途中の資材 | `~/ts-novels-holding/` (59MB) | × | **rsync で運ぶ**。作り直せない (取得済みの Wayback/CC の状態ファイル等) |
| ssh 接続先 | `scripts/wp/novels-ssh` | ○ | `~/.ssh/config` は不要 (接続先はラッパに書いてある)。新機の公開鍵を本番 (Xserver パネル) に登録し、初回接続でホスト鍵を受け入れる |
| Claude の memory | `~/.claude/projects/<パス鍵>/memory/` | × | コピー (手順 5) |

### 2. 新サーバの前提

- `git` `rsync` `curl` `make` `python3` (3.11 以上。旧機は 3.14)
- **PHP は不要** — 構文検査は deploy.sh が本番サーバの PHP 8.0 で行う
- GitHub への push 権限 (ssh 鍵) と `scripts/wp/novels-ssh true` が通ること
  (ホスト鍵 `[sv3.xwp.ne.jp]:10022` ECDSA SHA256:a0gd1KO5BA+7RXOznQtr4Ta5zVf89AUTaSd++QDd5k0)

### 3. 手順

```bash
# 3-1 取得
git clone git@github.com:takano32/ts-novels.git ~/GitHub/ts-novels
cd ~/GitHub/ts-novels

# 3-2 Python 環境 (pykakasi を必ず入れる。無いと slug が genre-1 等に退化する)
make venv

# 3-3 catalog を作り直し、**差分ゼロ**を確かめる (移設の検証そのもの)
make catalog          # bodies/ も body_convert で再生成される (数分)
git status --short    # ← 何も出ないこと。差分が出たら環境差 (pykakasi の版など) を疑う
make check            # [NG] が 0 件であること

# 3-4 本番投入用の生成物
python3 scripts/wp/payload_build.py   # 「無損失検査: OK」「残存 0 本」で終わること
python3 scripts/wp/docs_build.py

# 3-5 本番への疎通 (読み取りのみ)
scripts/wp/novels-ssh 'cd ~/novels.xwp.jp/public_html && wp ts verify | tail -1'   # Success: verify OK
scripts/wp/deploy.sh                  # 引数なし = dry-run。エラーなく最後まで通ること

# 3-6 生成物が本番と同じ中身か (checksum 比較。転送予定が 0 件であること)
for d in bodies payloads payloads-docs; do
  printf '%s: ' $d
  rsync -rnc -i -e scripts/wp/novels-ssh $d/ novels:"~/novels.xwp.jp/repo/$d/" | grep -c '^<f'
done
```

deploy.sh は時刻を保たない rsync なので、dry-run の一覧には毎回全件が出る (`<f..T` = 時刻だけ違う)。
中身の一致は 3-6 の checksum 比較で見る。ここで 1 件でも出たら生成物が旧機と違う。
**`--apply` せずに原因を調べる** (本番の本文を書き換えてしまうため)。

```bash
# 3-7 holding とメモリを旧機から運ぶ (旧機で実行)
rsync -av ~/ts-novels-holding/ NEWHOST:~/ts-novels-holding/
```
