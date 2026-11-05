#!/usr/bin/env bash
# snapshot.sh — 生成物一式を作り、sha256 の一覧・各段の標準出力・終了コードを保存する (T45.2.3)
#
#   scripts/wp/parity/snapshot.sh [--no-verify] [REV [OUT]]
#
#   REV   流すコミット。既定は PARITY_BASE (d586f0eb)。tree.sh で $PARITY_DIR/tree-<REV> に展開して
#         その中だけで流す (リポジトリ本体の生成物には触れない。比較先の作業中の変更を流すなら
#         先にコミットして、そのコミットを REV に渡す)
#   OUT   保存先。既定は基準なら $PARITY_DIR/baseline-<REV>、それ以外は $PARITY_DIR/snapshot-<REV>
#         既定の保存先に sha256.txt があれば上書きせずに止まる (基準を守る)
#   --no-verify  make verify (所要 6 分ほど) を飛ばす
#
# 流す順: make catalog → payload_build.py → docs_build.py → make check → secret_scan.py →
# make qa → make verify。OUT に <段>.out (標準出力と標準エラー)・exit.txt (`<段> exit=N` と
# 所要 `<段> sec=N`)・git-status.txt・sha256.txt・count.txt (hash.sh)・tree.txt と python.txt
# (流したツリーと Python の絶対パス) を書く。
# 2 つの OUT は compare.sh で比べる。
# 所要 (d586f0eb、2 CPU): make catalog 73 秒、make verify 339 秒。
#
# catalog/ は消さない (catalog_build の自己検査が前回の works.jsonl を読む。消すと最初の段で止まる。T45.2.20)。
set -u
HERE=$(cd "$(dirname "$0")" && pwd -P)
. "$HERE/common.sh"
VERIFY=1
if [ "${1:-}" = --no-verify ]; then VERIFY=0; shift; fi
REV="${1:-$PARITY_BASE}"
if [ "$REV" = "$PARITY_BASE" ]; then def="$PARITY_DIR/baseline-$REV"; else def="$PARITY_DIR/snapshot-$REV"; fi
OUT="${2:-$def}"
if [ -z "${2:-}" ] && [ -e "$OUT/sha256.txt" ]; then
  echo "保存済みの基準は上書きしない: $OUT (別の保存先を OUT に渡す)" >&2; exit 1
fi
TREE=$(tree_of "$REV") || exit 1
in_parity_dir "$TREE" || { echo "作業ディレクトリの外のツリーでは流さない: $TREE" >&2; exit 1; }
mkdir -p "$OUT"; OUT=$(cd "$OUT" && pwd -P)
: > "$OUT/exit.txt"
echo "$TREE" > "$OUT/tree.txt"           # compare.sh が標準出力の中の置き場を記号に戻すのに使う
echo "$PYTHON" > "$OUT/python.txt"
cd "$TREE" || exit 1
rm -rf "$TREE/bodies" "$TREE/payloads" "$TREE/payloads-docs"

step() {   # step <名> <コマンド…>
  local name="$1" s rc; shift
  s=$(date +%s)
  "$@" > "$OUT/$name.out" 2>&1; rc=$?
  echo "$name exit=$rc" >> "$OUT/exit.txt"
  echo "$name sec=$(( $(date +%s) - s ))" >> "$OUT/exit.txt"
  echo "$name exit=$rc"
}
step catalog make PYTHON="$PYTHON" catalog
step payload_build "$PYTHON" scripts/wp/payload_build.py
step docs_build "$PYTHON" scripts/wp/docs_build.py
step check make PYTHON="$PYTHON" check
step secret_scan "$PYTHON" scripts/wp/secret_scan.py
step qa make PYTHON="$PYTHON" qa
git status --short > "$OUT/git-status.txt"
[ $VERIFY = 1 ] && step verify make PYTHON="$PYTHON" verify
"$HERE/hash.sh" "$TREE" "$OUT" > /dev/null
echo done >> "$OUT/exit.txt"
echo "saved: $OUT ($(cat "$OUT/count.txt") files)"
