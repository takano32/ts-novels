#!/usr/bin/env bash
# tree.sh — コミットを作業ディレクトリに git worktree として展開し、そのパスを出す (T45.2.3)
#
#   scripts/wp/parity/tree.sh [REV]        REV の既定は PARITY_BASE (d586f0eb)
#   scripts/wp/parity/tree.sh --remove REV  展開した worktree を片づける (git worktree remove)
#
# 置き場は $PARITY_DIR/tree-<REV> (既定 .parity/tree-d586f0eb)。展開済みならそのまま使う。
# 生成物 (catalog/ の書き換え・bodies/ など) はこの worktree の中にできるので、リポジトリ本体には触れない。
set -u
. "$(dirname "$0")/common.sh"
if [ "${1:-}" = --remove ]; then
  git -C "$REPO" worktree remove --force "$PARITY_DIR/tree-$2"
  exit $?
fi
tree_of "${1:-$PARITY_BASE}"
