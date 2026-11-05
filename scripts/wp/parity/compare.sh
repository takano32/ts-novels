#!/usr/bin/env bash
# compare.sh — snapshot.sh の保存先 2 つを並べて比べる (T45.2.3)
#
#   scripts/wp/parity/compare.sh A B      (例 .parity/baseline-0e8c7712 .parity/snapshot-<REV>)
#
# 比べるもの
#   sha256.txt   ファイルの有無と中身。ignore.tsv に載ったファイルは、hash.sh が残した写し
#                (<保存先>/ignored/<パス>) から載った鍵を除いて比べる (時刻だけが違う所)
#   exit.txt     各段の終了コード (所要の sec= の行は見ない)
#   <段>.out     各段の標準出力 (両方にあるものだけ)。保存先の python.txt と tree.txt にある
#                絶対パス (流した Python とツリー) は @PYTHON@ と @ROOT@ に戻してから比べる
# 結果の行は `<対象>: same` か `<対象>: DIFFERS` と差の先頭。全部 same なら最後に
# `same` と出して終了コード 0、違いがあれば `DIFFERS` で 1。
set -u
HERE=$(cd "$(dirname "$0")" && pwd -P)
. "$HERE/common.sh"
A=$(cd "$1" && pwd -P) || exit 2
B=$(cd "$2" && pwd -P) || exit 2
TMP=$(mktemp -d); trap 'rm -rf "$TMP"' EXIT
IGN="$HERE/ignore.tsv"
rc=0

ignored_paths() { grep -v '^#' "$IGN" | cut -f1; }
ignored_paths | LC_ALL=C sort -u > "$TMP/ign"
for s in A B; do
  d=${!s}
  awk 'NR==FNR{ign[$0]=1; next} !(substr($0, 67) in ign)' "$TMP/ign" "$d/sha256.txt" > "$TMP/$s.sha"
done
if diff "$TMP/A.sha" "$TMP/B.sha" > "$TMP/sha.diff"; then
  echo "sha256: same ($(wc -l < "$TMP/A.sha") files + 除外の $(wc -l < "$TMP/ign") files)"
else
  echo "sha256: DIFFERS ($(grep -c '^[<>]' "$TMP/sha.diff") lines)"; head -20 "$TMP/sha.diff"; rc=1
fi

strip_key() {   # strip_key <file> <key> → 最上位の鍵を除いた JSON
  "$PYTHON" -c 'import json,sys; d=json.load(open(sys.argv[1],encoding="utf-8")); d.pop(sys.argv[2],None); print(json.dumps(d,ensure_ascii=False,indent=1,sort_keys=True))' "$1" "$2"
}
while IFS=$'\t' read -r path key; do
  case "$path" in ''|'#'*) continue ;; esac
  fa="$A/ignored/$path"; fb="$B/ignored/$path"
  if [ ! -e "$fa" ] && [ ! -e "$fb" ]; then continue; fi
  if [ ! -e "$fa" ] || [ ! -e "$fb" ]; then echo "$path: DIFFERS (片方に無い)"; rc=1; continue; fi
  if diff <(strip_key "$fa" "$key") <(strip_key "$fb" "$key") > "$TMP/ign.diff"; then
    echo "$path: same ($key を除く)"
  else
    echo "$path: DIFFERS ($key を除いても)"; head -10 "$TMP/ign.diff"; rc=1
  fi
done < "$IGN"

if diff <(grep -v ' sec=' "$A/exit.txt") <(grep -v ' sec=' "$B/exit.txt") > "$TMP/exit.diff"; then
  echo "exit: same"
else
  echo "exit: DIFFERS"; cat "$TMP/exit.diff"; rc=1
fi
norm_out() {   # norm_out <保存先> <段>.out → 流した Python とツリーの絶対パスを記号に戻した中身
  local d="$1" f="$2"
  NPY=$(cat "$d/python.txt" 2>/dev/null) NTREE=$(cat "$d/tree.txt" 2>/dev/null) "$PYTHON" -c '
import os, sys
s = open(sys.argv[1], "rb").read()
for k, v in (("NPY", b"@PYTHON@"), ("NTREE", b"@ROOT@")):
    if os.environ.get(k):
        s = s.replace(os.fsencode(os.environ[k]), v)
sys.stdout.buffer.write(s)' "$d/$f"
}
for fa in "$A"/*.out; do
  f=$(basename "$fa")
  [ -e "$B/$f" ] || continue
  if diff <(norm_out "$A" "$f") <(norm_out "$B" "$f") > "$TMP/out.diff"; then echo "$f: same"
  else echo "$f: DIFFERS"; head -10 "$TMP/out.diff"; rc=1
  fi
done
if [ $rc = 0 ]; then echo same; else echo DIFFERS; fi
exit $rc
