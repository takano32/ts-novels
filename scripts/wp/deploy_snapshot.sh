#!/usr/bin/env bash
# 配備の前後で本番の見え方を比べる (T45.3.1、specs/S242.md の「配備」)。読むだけで、本番には書かない。
#
#   scripts/wp/deploy_snapshot.sh take DIR     # いまの本番を DIR に取る
#   scripts/wp/deploy_snapshot.sh diff A B     # 2 つの DIR を比べる (差が無ければ終了コード 0)
#
# 取るもの:
#   urls.tsv           主要 URL ごとに「応答コード  本文の sha256  URL」(ログインなし・キャッシュバスター付き)。
#                      sha256 は、本文に写り込むキャッシュバスター (_cb=数字) と、テーマの css・js の版
#                      (?ver=数字。ファイルの更新時刻なので配備のたびに変わる) を伏せ、タグの区切りで行に割って
#                      並べ替えたものから取る。同じ日付の作品の並びは流すたびに変わるため (作者ページ・検索。
#                      配備の前からある性質で、S242 の「見つけたこと」)
#   body/NN.html       その本文
#   verify.txt         wp ts verify の出力
#   takedown-urls.txt  wp ts export-takedown-urls の出力を並べ替えたもの
#                      (子の並びは同じ日付の話で MySQL が決めるので、順でなく集合で比べる。S242 の「見つけたこと」)
# 「今日の一作」は日付で変わるので、前後は同じ日に取ること。
# トップと、ページ送りのある作者ページは、同じ日付の作品の並びでページに載る作品そのものが変わるので、
# 応答コードだけを比べる (VOLATILE。sha256 の欄は -)。
set -euo pipefail

LOCAL_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
RSH="$LOCAL_ROOT/scripts/wp/novels-ssh"
SITE=https://novels.xwp.jp
R='cd ~/novels.xwp.jp/public_html'

# 読者が見る主要な場所。取り下げた作品は 410、普通の作品と話は 200 が正しい (S118)
URLS=(
  /
  /index/kana/ /index/timeline/ /index/bunrui/ /index/vocabulary/ /index/osusume/ /index/docs/
  /year/2005/
  /about/ /archive/ /takedown/ /removed/
  /authors/yays/ /authors/johdan/
  /novel/johdan-senotomeseera/
  /novel/johdan-senotomeseera/saila-saila_vol01/
  /novel/s_haneoka-irasuto-mai-pyua-kuroondooru-meidosanhen/
  /novel/marie-imajin/
  '/?s=%E5%AD%A6%E5%9C%92'
)

# 載る作品が流すたびに変わる URL (応答コードだけ比べる)
VOLATILE=' / /authors/johdan/ '

norm_hash() {
  sed -E 's/_cb=[0-9]+/_cb=X/g; s/\?ver=[0-9]+/?ver=X/g; s/></>\n</g' "$1" | LC_ALL=C sort | sha256sum | cut -d' ' -f1
}

take() {
  local out="$1"
  mkdir -p "$out/body"
  : > "$out/urls.tsv"
  local i=0
  for u in "${URLS[@]}"; do
    i=$((i + 1))
    local f
    f=$(printf '%s/body/%02d.html' "$out" "$i")
    local sep='?'; [[ "$u" == *\?* ]] && sep='&'
    local code
    code=$(curl -s -o "$f" -w '%{http_code}' "$SITE$u${sep}_cb=$RANDOM$RANDOM")
    local h='-'
    [[ "$VOLATILE" == *" $u "* ]] || h=$(norm_hash "$f")
    printf '%s\t%s\t%s\n' "$code" "$h" "$u" >> "$out/urls.tsv"
  done
  "$RSH" "$R && wp ts verify" > "$out/verify.txt" 2>&1 || true
  "$RSH" "$R && wp ts export-takedown-urls" 2>/dev/null | LC_ALL=C sort > "$out/takedown-urls.txt" || true
  echo "== $out"
  cat "$out/urls.tsv"
  tail -1 "$out/verify.txt"
}

diff_dirs() {
  local a="$1" b="$2" rc=0
  for f in urls.tsv verify.txt takedown-urls.txt; do
    if diff -u "$a/$f" "$b/$f"; then echo "same: $f"; else rc=1; fi
  done
  return $rc
}

case "${1:-}" in
  take) take "${2:?DIR}" ;;
  diff) diff_dirs "${2:?A}" "${3:?B}" ;;
  *) sed -n '2,13p' "$0"; exit 2 ;;
esac
