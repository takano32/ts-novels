#!/usr/bin/env bash
# 本番 (scripts/wp/novels-ssh = novels.xwp.jp) への配備 (T8.3。高品位なモデルとエフォートの所管 — 本番を触るコード)。
#
# 既定は DRY-RUN。実際に書き込むには --apply を付ける。
#   scripts/wp/deploy.sh            # 何が起きるかだけ表示 (rsync -n)
#   scripts/wp/deploy.sh --apply    # 実配備
#
# 段を分ける (T45.6.1。scripts/wp/deploy_run.sh がメンテナンス表示を挟むために使う。無ければ 0〜6 を全部流す):
#   --skip-php   PHP を置く段 (3 の mu-plugin・6 のテーマ) を飛ばし、それ以外 (1・2・4・5) を流す
#   --php-only   PHP を置く段 (3・6) だけ流す
#   構文検査 (0) はどちらでも流す。段の順と中身は変えない
#
# 配備物と行き先:
#   1) リポジトリ本体  → ~/novels.xwp.jp/repo        (git clone/fetch。catalog と .git が要る —
#                                                     wp ts import が ts_catalog_commit の記録に使う)
#   2) bodies/         → ~/novels.xwp.jp/repo/bodies (git 管理外の生成物なので rsync)
#   3) mu-plugins      → public_html/wp-content/mu-plugins/ts-library/
#   4) robots.txt 草案 → public_html/robots.txt
#   5) novel/ の画像   → public_html/assets/annex-img/ (git ls-files の jpg/gif/png/bmp。T8.7、specs/S187.md)
#   6) themes/ts-bunko → public_html/wp-content/themes/ (T10 以降、存在すれば)
#
# 安全則 (specs/S59.md〜S68.md):
#   - rsync に --delete は使わない (宛先に wp-config.php ほか消してはいけない物が居る)
#   - 本番の既存ファイルを消す操作はしない。上書きは配備物のパスに限る
#   - 配備後の検証 curl はキャッシュバスター付きで行うこと (このスクリプトの外)
set -euo pipefail

HOST=novels   # rsync の宛先表記用。実際の接続先は scripts/wp/novels-ssh が持つ
REMOTE_BASE='~/novels.xwp.jp'
REPO_URL='https://github.com/takano32/ts-novels'
LOCAL_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
RSH="$LOCAL_ROOT/scripts/wp/novels-ssh"

APPLY=0
PART=all
for arg in "$@"; do
  case "$arg" in
    --apply)    APPLY=1 ;;
    --skip-php) PART=skip-php ;;
    --php-only) PART=php-only ;;
    *) echo "使い方: scripts/wp/deploy.sh [--apply] [--skip-php | --php-only]"; exit 2 ;;
  esac
done
RSYNC_FLAGS=(-rv --no-perms --no-owner --no-group -e "$RSH")
(( APPLY )) || RSYNC_FLAGS+=(-n)

# 段 N を流すか (0 の構文検査はいつも)
want() {
  case "$PART" in
    all)      return 0 ;;
    skip-php) [[ "$1" != 3 && "$1" != 6 ]] ;;
    php-only) [[ "$1" == 0 || "$1" == 3 || "$1" == 6 ]] ;;
  esac
}
skip() { echo "== [$1/6] $2 (--$PART で飛ばす)"; }

HEAD_LOCAL=$(git -C "$LOCAL_ROOT" rev-parse HEAD)
if ! git -C "$LOCAL_ROOT" merge-base --is-ancestor "$HEAD_LOCAL" origin/master 2>/dev/null; then
  echo "!! ローカル HEAD ($HEAD_LOCAL) が origin/master に含まれていません。"
  echo "!! 配備はリモート追跡コミットに限る (再現性のため)。先に push してください。"
  exit 1
fi

echo "== [0/6] PHP 構文検査 (配備の前に。全 .php を対象)"
PHP_FILES=$(find "$LOCAL_ROOT/mu-plugins" "$LOCAL_ROOT/themes" -name '*.php' 2>/dev/null | sed "s|$LOCAL_ROOT/||")
if [ -n "$PHP_FILES" ]; then
  echo "-- $(echo "$PHP_FILES" | wc -l) ファイル"
  # サーバの PHP (8.0) で検査する。壊れたコードを本番に置いてから気づくのを避けるため配備前に行う
  # (process substitution は使わない — リモートのログインシェルが bash とは限らない)
  if ! tar -C "$LOCAL_ROOT" -cf - $PHP_FILES | "$RSH" '
      d=$(mktemp -d) && tar -xf - -C "$d" || exit 1
      out=$(find "$d" -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors" || true)
      rm -rf "$d"
      [ -z "$out" ] || { echo "$out"; exit 1; }'; then
    echo "!! PHP 構文エラー — 配備を中止します"
    exit 1
  fi
  echo "-- 構文 OK"
fi

if want 1; then
  echo "== [1/6] サーバ側 repo を $HEAD_LOCAL に合わせる"
  if (( APPLY )); then
    "$RSH" "if [ ! -d $REMOTE_BASE/repo/.git ]; then git clone --depth 100 '$REPO_URL' $REMOTE_BASE/repo; fi
      cd $REMOTE_BASE/repo && git fetch --depth 100 origin master && git checkout -q '$HEAD_LOCAL' \
      && echo 'repo @' \$(git rev-parse --short HEAD)"
  else
    echo "(dry-run) novels-ssh: git clone/fetch/checkout $HEAD_LOCAL"
  fi
else
  skip 1 "サーバ側 repo"
fi

if want 2; then
  echo "== [2/6] bodies/ と payloads/ (git 管理外の生成物) を rsync"
  if [ -d "$LOCAL_ROOT/bodies" ]; then
    rsync "${RSYNC_FLAGS[@]}" "$LOCAL_ROOT/bodies/" "$HOST:$REMOTE_BASE/repo/bodies/" | tail -3
  else
    echo "(bodies/ が無い — python3 scripts/wp/body_convert.py で生成してから)"
  fi
  if [ -d "$LOCAL_ROOT/payloads" ]; then
    rsync "${RSYNC_FLAGS[@]}" "$LOCAL_ROOT/payloads/" "$HOST:$REMOTE_BASE/repo/payloads/" | tail -3
  else
    echo "(payloads/ が無い — python3 scripts/wp/payload_build.py で生成してから)"
  fi
  if [ -d "$LOCAL_ROOT/payloads-docs" ]; then
    rsync "${RSYNC_FLAGS[@]}" "$LOCAL_ROOT/payloads-docs/" "$HOST:$REMOTE_BASE/repo/payloads-docs/" | tail -3
  fi
else
  skip 2 "bodies/ と payloads/"
fi

if want 3; then
  echo "== [3/6] mu-plugins を rsync (ローダ + 本体)"
  rsync "${RSYNC_FLAGS[@]}" "$LOCAL_ROOT/mu-plugins/ts-library-loader.php" \
        "$HOST:$REMOTE_BASE/public_html/wp-content/mu-plugins/ts-library-loader.php" | tail -2
  rsync "${RSYNC_FLAGS[@]}" "$LOCAL_ROOT/mu-plugins/ts-library/" \
        "$HOST:$REMOTE_BASE/public_html/wp-content/mu-plugins/ts-library/" | tail -3
  if (( APPLY )); then
    echo "-- 配備後の再検査 (転送で壊れていないか)"
    "$RSH" "find $REMOTE_BASE/public_html/wp-content/mu-plugins/ts-library \
        $REMOTE_BASE/public_html/wp-content/mu-plugins/ts-library-loader.php \
        -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors' || true"
  fi
else
  skip 3 "mu-plugins"
fi

if want 4; then
  echo "== [4/6] robots.txt"
  if [ -f "$LOCAL_ROOT/scripts/wp/assets/robots.txt" ]; then
    rsync "${RSYNC_FLAGS[@]}" "$LOCAL_ROOT/scripts/wp/assets/robots.txt" \
          "$HOST:$REMOTE_BASE/public_html/robots.txt" | tail -2
  fi
else
  skip 4 "robots.txt"
fi

if want 5; then
  echo "== [5/6] 画像 (novel/ + ~yays/gallery/ の画像) → assets/annex-img/ (T8.7/T10.5)"
  IMG_LIST=$(mktemp)
  git -C "$LOCAL_ROOT" ls-files novel '~yays/gallery' | grep -Ei '\.(jpe?g|gif|png|bmp)$' > "$IMG_LIST" || true
  if [ -s "$IMG_LIST" ]; then
    (( APPLY )) && "$RSH" "mkdir -p $REMOTE_BASE/public_html/assets/annex-img"
    echo "-- $(wc -l < "$IMG_LIST") 点"
    rsync "${RSYNC_FLAGS[@]}" --files-from="$IMG_LIST" "$LOCAL_ROOT/" \
          "$HOST:$REMOTE_BASE/public_html/assets/annex-img/" | tail -2
  fi
  rm -f "$IMG_LIST"
else
  skip 5 "画像"
fi

if want 6; then
  echo "== [6/6] テーマ (T10 以降。無ければ何もしない)"
  if [ -d "$LOCAL_ROOT/themes/ts-bunko" ]; then
    rsync "${RSYNC_FLAGS[@]}" "$LOCAL_ROOT/themes/ts-bunko/" \
          "$HOST:$REMOTE_BASE/public_html/wp-content/themes/ts-bunko/" | tail -3
  fi
else
  skip 6 "テーマ"
fi

if (( APPLY )); then
  case "$PART" in
    all)
      echo "== 配備完了。次: cd public_html で wp ts sync-terms / import (specs/S69.md)"
      echo "   検証 curl は ?_cb=\$RANDOM を付けること。キャッシュクリアは高野さんの作業 (サーバパネル)" ;;
    skip-php) echo "== PHP 以外 (1・2・4・5) の配備完了。残りは scripts/wp/deploy.sh --apply --php-only" ;;
    php-only) echo "== PHP (mu-plugin・テーマ) の配備完了。検証 curl は ?_cb=\$RANDOM を付けること" ;;
  esac
else
  echo "== DRY-RUN でした。実配備は: scripts/wp/deploy.sh --apply"
fi
