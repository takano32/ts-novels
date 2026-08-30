#!/usr/bin/env bash
# 本番 (ssh novels = novels.xwp.jp) への配備 (タスク 2.3。Fable 所管 — 本番を触るコード)。
#
# 既定は DRY-RUN。実際に書き込むには --apply を付ける。
#   scripts/wp/deploy.sh            # 何が起きるかだけ表示 (rsync -n)
#   scripts/wp/deploy.sh --apply    # 実配備
#
# 配備物と行き先:
#   1) リポジトリ本体  → ~/novels.xwp.jp/repo        (git clone/fetch。catalog と .git が要る —
#                                                     commands.php が ts_catalog_commit の記録に使う)
#   2) bodies/         → ~/novels.xwp.jp/repo/bodies (git 管理外の生成物なので rsync)
#   3) mu-plugins      → public_html/wp-content/mu-plugins/ts-library/
#   4) robots.txt 草案 → public_html/robots.txt
#
# 安全則 (docs/environment.md):
#   - rsync に --delete は使わない (宛先に wp-config.php ほか消してはいけない物が居る)
#   - 本番の既存ファイルを消す操作はしない。上書きは配備物のパスに限る
#   - 配備後の検証 curl はキャッシュバスター付きで行うこと (このスクリプトの外)
set -euo pipefail

HOST=novels
REMOTE_BASE='~/novels.xwp.jp'
REPO_URL='https://github.com/takano32/ts-novels'
LOCAL_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

APPLY=0
[[ "${1:-}" == "--apply" ]] && APPLY=1
RSYNC_FLAGS=(-rv --no-perms --no-owner --no-group)
(( APPLY )) || RSYNC_FLAGS+=(-n)

HEAD_LOCAL=$(git -C "$LOCAL_ROOT" rev-parse HEAD)
if ! git -C "$LOCAL_ROOT" merge-base --is-ancestor "$HEAD_LOCAL" origin/master 2>/dev/null; then
  echo "!! ローカル HEAD ($HEAD_LOCAL) が origin/master に含まれていません。"
  echo "!! 配備はリモート追跡コミットに限る (再現性のため)。先に push してください。"
  exit 1
fi

echo "== [1/4] サーバ側 repo を $HEAD_LOCAL に合わせる"
if (( APPLY )); then
  ssh "$HOST" "if [ ! -d $REMOTE_BASE/repo/.git ]; then git clone --depth 100 '$REPO_URL' $REMOTE_BASE/repo; fi
    cd $REMOTE_BASE/repo && git fetch --depth 100 origin master && git checkout -q '$HEAD_LOCAL' \
    && echo 'repo @' \$(git rev-parse --short HEAD)"
else
  echo "(dry-run) ssh $HOST git clone/fetch/checkout $HEAD_LOCAL"
fi

echo "== [2/4] bodies/ (git 管理外の生成物) を rsync"
if [ -d "$LOCAL_ROOT/bodies" ]; then
  rsync "${RSYNC_FLAGS[@]}" "$LOCAL_ROOT/bodies/" "$HOST:$REMOTE_BASE/repo/bodies/" | tail -3
else
  echo "(bodies/ が無い — python3 scripts/wp/body_convert.py で生成してから)"
fi

echo "== [3/4] mu-plugins を rsync"
rsync "${RSYNC_FLAGS[@]}" "$LOCAL_ROOT/mu-plugins/ts-library/" \
      "$HOST:$REMOTE_BASE/public_html/wp-content/mu-plugins/ts-library/" | tail -3
if (( APPLY )); then
  echo "-- php 構文検査 (サーバ側)"
  ssh "$HOST" "php -l $REMOTE_BASE/public_html/wp-content/mu-plugins/ts-library/includes/commands.php \
    && for f in $REMOTE_BASE/public_html/wp-content/mu-plugins/ts-library/*.php; do php -l \"\$f\"; done"
fi

echo "== [4/4] robots.txt"
if [ -f "$LOCAL_ROOT/scripts/wp/assets/robots.txt" ]; then
  rsync "${RSYNC_FLAGS[@]}" "$LOCAL_ROOT/scripts/wp/assets/robots.txt" \
        "$HOST:$REMOTE_BASE/public_html/robots.txt" | tail -2
fi

if (( APPLY )); then
  echo "== 配備完了。次: cd public_html で wp ts sync-terms / import (docs/publish-runbook.md)"
  echo "   検証 curl は ?_cb=\$RANDOM を付けること。キャッシュクリアは 👤 (パネル)"
else
  echo "== DRY-RUN でした。実配備は: scripts/wp/deploy.sh --apply"
fi
