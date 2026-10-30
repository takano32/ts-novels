# S.70 公開に失敗したときの戻し方

- 種類: 手順
- 状態: 有効
- 出典: docs/publish-runbook.md の「失敗したときの戻し方」
- 関連: T.23.6, S.69, S.77, S.121

## 失敗したときの戻し方

| 症状 | 対処 |
|---|---|
| import が途中で落ちた / 変なデータが入った | (高野さんの作業) `cd ~/GitHub/ts-novels && scripts/wp/novels-ssh 'cd ~/novels.xwp.jp/public_html && wp db import ~/dumps/pre-import-<日時>.sql'` (S.69 の「本番: 復旧点を取る」で作ったファイル) |
| .htaccess を変えたら無関係 URL が 500/403 | `htaccess_block.py --install` は検証 NG なら自動で直前の `.htaccess.bak-<日時>` に戻す。手で戻すなら `cp .htaccess.bak-<日時> .htaccess` (S.77) |
| 反映されない | 1. キャッシュバスター付き curl で確認 2. キャッシュクリア (高野さんの作業) 3. それでも駄目なら調査 |
| 取り下げた作品が読者に 200 で見える | ログインなし・バスター付きの curl で確かめる (ログイン中の運営委員には 200 + 帯が正しい。S.118)。410 でなければ `wp ts apply-takedown` を流し直し、private の件数を見る (S.76、S.121) |
| catalog が壊れた | git で戻して `make catalog` を再実行 (catalog は生成物であり、手で直さない) |
