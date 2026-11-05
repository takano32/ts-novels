# shellcheck shell=bash
# common.sh — 突き合わせの道具 (scripts/wp/parity/*.sh) が source する共通の設定 (T45.2.3)
#
# 環境変数で変えられるもの (どれも省略できる)
#   PARITY_BASE  基準のコミット。既定 d586f0eb (T45.2 の分割の前)
#   PARITY_DIR   作業ディレクトリ。既定はリポジトリの .parity/ (.gitignore 済み)。
#                基準側の git worktree・各回の出力・スナップショットは全部この下に置く
#   PYTHON       使う Python の絶対パス。既定はリポジトリの .venv/bin/python、無ければ python3。
#                make には `make PYTHON=$PYTHON` で渡す
#
# 決めること
#   REPO        このスクリプトがあるリポジトリ (作業用コピーならその作業ツリー) の最上位
#   tree_of REV 基準などのコミットを $PARITY_DIR/tree-<REV> に git worktree add で展開して
#               そのパスを出す。git archive では .git が無く catalog_build の provenance の
#               `git log` が落ちるので worktree にする。展開済みならそのまま使う
#   in_parity_dir DIR  DIR が $PARITY_DIR の下なら真 (消す操作の前に必ず確かめる)

REPO=$(cd "$(dirname "${BASH_SOURCE[0]}")" && git rev-parse --show-toplevel) || exit 1
PARITY_BASE=${PARITY_BASE:-d586f0eb}
PARITY_DIR=${PARITY_DIR:-$REPO/.parity}
mkdir -p "$PARITY_DIR"
PARITY_DIR=$(cd "$PARITY_DIR" && pwd -P)
if [ -z "${PYTHON:-}" ]; then
  if [ -x "$REPO/.venv/bin/python" ]; then PYTHON="$REPO/.venv/bin/python"; else PYTHON=$(command -v python3); fi
fi
case "$PYTHON" in /*) ;; *) PYTHON=$(cd "$(dirname "$PYTHON")" && pwd -P)/$(basename "$PYTHON") ;; esac
export PYTHON

tree_of() {
  local rev="$1" dir="$PARITY_DIR/tree-$1"
  if [ ! -e "$dir/.git" ]; then
    git -C "$REPO" worktree add -q --detach "$dir" "$rev" >&2 || return 1
  fi
  echo "$dir"
}

in_parity_dir() {
  local d
  d=$(cd "$1" 2>/dev/null && pwd -P) || return 1
  case "$d/" in "$PARITY_DIR"/*) return 0 ;; *) return 1 ;; esac
}
