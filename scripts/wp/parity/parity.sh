#!/usr/bin/env bash
# parity.sh — 1 段だけの突き合わせ: 基準側と比較先で同じスクリプトを同じ引数で流し、
# 標準出力 (標準エラーを含む)・終了コード・その回に書いた出力ファイルを比べる (T45.2.3)
#
#   scripts/wp/parity/parity.sh [--target DIR] SCRIPT [引数…]
#
#   SCRIPT    リポジトリからの相対パス (例 scripts/wp/terms_build.py)。scripts/wp/ の下なら
#             ファイル名だけでもよい (例 terms_build.py)
#   基準側    PARITY_BASE (既定 0e8c7712) を tree.sh で展開した worktree の SCRIPT を、その worktree で流す
#   比較先    --target DIR (既定はこのリポジトリの作業ツリー) の SCRIPT を、DIR で流す
#
# 引数の中の置き換え記号 (両側で別の値になる)
#   @X@     その回の出力の置き場の接頭辞。基準側は <回>/base、比較先は <回>/new になる
#           (例 `--out @X@.md` → <回>/base.md と <回>/new.md)。この 2 つを並べて比べる
#   @ROOT@  その側のツリーの最上位 (例 `--root @ROOT@`)
# <回> は $PARITY_DIR/run/<SCRIPT のファイル名> で、流す前に空にする (前の回の残りは比べない)。
#
# スクリプトは自分の置き場からリポジトリの最上位を決めるので、基準側は基準のツリーの入力
# (catalog/ など) を読み、比較先は比較先のツリーを読む。両側の catalog/ が同じ状態であることが前提。
# --check でない段は両側のツリーの catalog/ などを書き換える (比較先が本体なら比べた後に
# `git checkout -- catalog` で戻す)。
#
# 標準出力の中のその側の @X@ と @ROOT@ の値 (絶対パスと、ツリーからの相対パス) は記号に戻してから
# 比べる (置き場の違いだけで差にならないように)。元の出力は <回>/raw.base・raw.new に残る。
#
# 結果の行: `stdout: same (N lines)` / `stdout: DIFFERS` と差の先頭、`exit: same (N)` /
# `exit: DIFFERS (基準 a / 比較先 b)`、出力ファイルごとに `file <名>: same` / `DIFFERS`。
# 全部 same なら終了コード 0、違いがあれば 1。
set -u
. "$(dirname "$0")/common.sh"
TARGET="$REPO"
if [ "${1:-}" = --target ]; then TARGET=$(cd "$2" && pwd -P) || exit 2; shift 2; fi
[ $# -ge 1 ] || { sed -n '2,25p' "$0"; exit 2; }
script="$1"; shift
case "$script" in */*) ;; *) script="scripts/wp/$script" ;; esac
BASE_TREE=$(tree_of "$PARITY_BASE") || exit 2
RUN="$PARITY_DIR/run/$(basename "$script")"
rm -rf "$RUN"; mkdir -p "$RUN"

run_side() {   # run_side <tree> <接頭辞> → $RUN/stdout.<接頭辞> と $RUN/exit.<接頭辞>
  local tree="$1" side="$2" a args=()
  for a in "${@:3}"; do a="${a//@X@/$RUN/$side}"; args+=("${a//@ROOT@/$tree}"); done
  ( cd "$tree" && "$PYTHON" "$tree/$script" "${args[@]}" ) > "$RUN/raw.$side" 2>&1
  echo $? > "$RUN/exit.$side"
  # 出力の中のその側の置き場 (絶対と、ツリーからの相対) を置き換え記号に戻してから比べる
  local rel; rel=$(realpath -m --relative-to="$tree" "$RUN/$side")
  RUN_SIDE="$RUN/$side" REL_SIDE="$rel" TREE="$tree" "$PYTHON" -c '
import os, sys
s = open(sys.argv[1], "rb").read()
for k, v in (("RUN_SIDE", b"@X@"), ("REL_SIDE", b"@X@"), ("TREE", b"@ROOT@")):
    s = s.replace(os.fsencode(os.environ[k]), v)
sys.stdout.buffer.write(s)' "$RUN/raw.$side" > "$RUN/stdout.$side"
}
run_side "$BASE_TREE" base "$@"
run_side "$TARGET" new "$@"

rc=0
if diff "$RUN/stdout.base" "$RUN/stdout.new" > "$RUN/stdout.diff"; then
  echo "stdout: same ($(wc -l < "$RUN/stdout.base") lines)"
else
  echo "stdout: DIFFERS"; head -20 "$RUN/stdout.diff"; rc=1
fi
if [ "$(cat "$RUN/exit.base")" = "$(cat "$RUN/exit.new")" ]; then
  echo "exit: same ($(cat "$RUN/exit.base"))"
else
  echo "exit: DIFFERS (基準 $(cat "$RUN/exit.base") / 比較先 $(cat "$RUN/exit.new"))"; rc=1
fi
for f in "$RUN"/base*; do
  [ -e "$f" ] || continue
  n="$RUN/new${f#"$RUN"/base}"
  name=$(basename "$f")
  if [ ! -e "$n" ]; then echo "file $name: 比較先に無い"; rc=1
  elif cmp -s "$f" "$n"; then echo "file $name: same"
  else echo "file $name: DIFFERS"; rc=1
  fi
done
for n in "$RUN"/new*; do
  [ -e "$n" ] || continue
  [ -e "$RUN/base${n#"$RUN"/new}" ] || { echo "file $(basename "$n"): 基準側に無い"; rc=1; }
done
exit $rc
