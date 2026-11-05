#!/usr/bin/env bash
# PHP の断片をサーバの PHP (8.0) で流す (T45.1.14、specs/S242.md)。開発機に PHP が無いので、scripts/wp/php_test.sh と同じ形で
# 一時ディレクトリに送って流して消す。公開ルート (public_html) と DB には触れない。読むだけの接続 (scripts/wp/novels-ssh)。
#
#   scripts/wp/php_probe.sh ENTRY.php [FILE…] [--old REV:PATH]…
#
#   ENTRY.php      流すスクリプト (どこにあってもよい。送り先では木の根の probe.php になり、カレントディレクトリは木の根)
#   FILE           一緒に送るリポジトリのファイルかディレクトリ (リポジトリの根からの相対パス。同じ場所に置かれる)
#   --old REV:PATH git の旧版のファイルを old/REV/PATH に置く (旧版のコードを同じ代わりで動かして期待値を取る。T45.1.15)
#
# 例 — 分割前 (d586f0eb) の書誌カードの HTML を、いまの試験と同じ代わりと置き場で取る:
#   scripts/wp/php_probe.sh /tmp/probe.php scripts/wp/tests/wp-stubs.php scripts/wp/tests/wp-fixtures.php \
#       --old d586f0eb:mu-plugins/ts-library/includes/render.php
#   probe.php の中身:
#       require 'scripts/wp/tests/wp-stubs.php'; require 'scripts/wp/tests/wp-fixtures.php';
#       require 'old/d586f0eb/mu-plugins/ts-library/includes/render.php';
#       $ids = ts_fixture_biblio();
#       var_export(TS_WP_Stubs::capture(function () use ($ids) { ts_bunko_biblio_card(get_post($ids['full'])); }));
# 終了コードは php のもの。
set -euo pipefail

LOCAL_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
RSH="$LOCAL_ROOT/scripts/wp/novels-ssh"
[ $# -ge 1 ] || { echo "使い方: $0 ENTRY.php [FILE…] [--old REV:PATH]…" >&2; exit 2; }

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
cp "$1" "$work/probe.php"
shift
cd "$LOCAL_ROOT"
while [ $# -gt 0 ]; do
    case "$1" in
        --old)
            spec=$2; shift 2
            rev=${spec%%:*}; path=${spec#*:}
            mkdir -p "$work/old/$rev/$(dirname "$path")"
            git show "$spec" > "$work/old/$rev/$path" ;;
        *)
            mkdir -p "$work/$(dirname "$1")"
            cp -R "$1" "$work/$1"
            shift ;;
    esac
done

# process substitution は使わない (リモートのログインシェルが bash とは限らない)
tar -C "$work" -cf - . | "$RSH" '
    d=$(mktemp -d) && tar -xf - -C "$d" || exit 1
    cd "$d" && php probe.php; rc=$?
    cd / && rm -rf "$d"
    exit $rc'
