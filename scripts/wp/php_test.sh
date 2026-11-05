#!/usr/bin/env bash
# PHP の単体の試験と構文検査を本番サーバの PHP (8.0) で流す (T45.1、specs/S242.md)。
#
#   scripts/wp/php_test.sh        # = make test-php
#
# 開発機に PHP が無く、docker も立てない決まりなので、scripts/wp/deploy.sh の「[0/6] PHP 構文検査」と
# 同じ形で行う: 対象を tar で送り、mktemp -d に展開し、php -l と scripts/wp/ts_library_test.php を
# 実行して消す。公開ルート (public_html) と DB には触れない。読むだけの接続 (scripts/wp/novels-ssh)。
#
# 送るもの: mu-plugins/・themes/ts-bunko/ の .php、scripts/wp/*.php と scripts/wp/tests/、
# 試験が読む takedown/denylist.yml。終了コードは php -l か試験のどちらかが落ちれば 1。
set -euo pipefail

LOCAL_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
RSH="$LOCAL_ROOT/scripts/wp/novels-ssh"

cd "$LOCAL_ROOT"
FILES=$( { find mu-plugins themes -name '*.php'; find scripts/wp -maxdepth 1 -name '*.php'; \
           find scripts/wp/tests -name '*.php' 2>/dev/null; echo takedown/denylist.yml; } | sort)
echo "== PHP の構文検査と単体の試験 (サーバの PHP): $(echo "$FILES" | wc -l) ファイルを送る"

# process substitution は使わない (リモートのログインシェルが bash とは限らない)
tar -cf - $FILES | "$RSH" '
    d=$(mktemp -d) && tar -xf - -C "$d" || exit 1
    rc=0
    echo "-- php -l ($(php -r "echo PHP_VERSION;"))"
    out=$(find "$d" -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors" || true)
    if [ -n "$out" ]; then echo "$out"; rc=1; else echo "-- 構文 OK"; fi
    echo "-- scripts/wp/ts_library_test.php"
    php "$d/scripts/wp/ts_library_test.php" || rc=1
    rm -rf "$d"
    exit $rc'
