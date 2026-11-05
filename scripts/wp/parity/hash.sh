#!/usr/bin/env bash
# hash.sh — ツリーの生成物 (catalog/ bodies/ payloads/ payloads-docs/) の sha256 一覧を書く (T45.2.3)
#
#   scripts/wp/parity/hash.sh TREE OUT
#
# OUT/sha256.txt に `<sha256>  <ツリーからの相対パス>` を、OUT/count.txt に行数を書く。
# 並びはロケールに左右されないよう LC_ALL=C で固定する (ファイル名の空白・引用符に耐えるよう -print0)。
# ignore.tsv に載ったファイル (時刻だけが違う所) は、OUT/ignored/<相対パス> に写しも置く
# (compare.sh が該当の鍵を読み飛ばして比べる)。
set -u
HERE=$(cd "$(dirname "$0")" && pwd -P)
. "$HERE/common.sh"
TREE=$(cd "$1" && pwd -P) || exit 1
OUT="$2"; mkdir -p "$OUT"
OUT=$(cd "$OUT" && pwd -P)
dirs=()
for d in catalog bodies payloads payloads-docs; do [ -d "$TREE/$d" ] && dirs+=("$d"); done
# 対象が 1 つも無ければ止まる (find にパスが渡らずカレントの全部を数えてしまう)
[ ${#dirs[@]} -gt 0 ] || { echo "生成物のディレクトリが 1 つも無い: $TREE" >&2; exit 1; }
( cd "$TREE" && find "${dirs[@]}" -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum ) > "$OUT/sha256.txt"
wc -l < "$OUT/sha256.txt" > "$OUT/count.txt"
rm -rf "$OUT/ignored"
grep -v '^#' "$HERE/ignore.tsv" | while IFS=$'\t' read -r path _key; do
  [ -n "$path" ] && [ -f "$TREE/$path" ] || continue
  mkdir -p "$OUT/ignored/$(dirname "$path")"
  cp "$TREE/$path" "$OUT/ignored/$path"
done
echo "files: $(cat "$OUT/count.txt")"
awk '{print $2}' "$OUT/sha256.txt" | cut -d/ -f1 | LC_ALL=C sort | uniq -c
