#!/usr/bin/env bash
# 1 段の旧 (HEAD) と新 (worktree) を同じ引数で流し、標準出力と (あれば) 出力ファイルを比べる
#   parity.sh <script.py> [引数…]   出力ファイルは引数の中の @OLD@ / @NEW@ を置き換える
set -u
SP=/tmp/claude-1001/-home-takano32-GitHub-ts-novels/566c9861-9e8b-4c10-9155-27e6e2c7f91e/scratchpad
WT=/home/takano32/GitHub/ts-novels/.claude/worktrees/agent-a6dfccdb049961db6
PY="$WT/.venv/bin/python"
script="$1"; shift
mkdir -p "$SP/cmp"
old_args=(); new_args=()
for a in "$@"; do old_args+=("${a//@X@/$SP/cmp/old}"); new_args+=("${a//@X@/$SP/cmp/new}"); done
cd "$WT"
"$PY" "$SP/old/scripts/wp/$script" "${old_args[@]}" > "$SP/cmp/old.$script.out" 2>&1; echo "old exit=$?"
"$PY" "$WT/scripts/wp/$script" "${new_args[@]}" > "$SP/cmp/new.$script.out" 2>&1; echo "new exit=$?"
if diff "$SP/cmp/old.$script.out" "$SP/cmp/new.$script.out" > "$SP/cmp/diff.$script.out"; then
  echo "stdout: same ($(wc -l < "$SP/cmp/old.$script.out") lines)"
else
  echo "stdout: DIFFERS"; head -20 "$SP/cmp/diff.$script.out"
fi
for f in "$SP"/cmp/old.*; do
  case "$f" in *.out) continue;; esac
  n="${f/old./new.}"
  [ -e "$n" ] || continue
  if cmp -s "$f" "$n"; then echo "file $(basename "$f"): same"; else echo "file $(basename "$f"): DIFFERS"; fi
done
