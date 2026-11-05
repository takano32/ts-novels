#!/usr/bin/env bash
# 生成物の sha256 一覧を $1/sha256.txt に書く (ファイル名の引用符・空白に耐えるよう -print0)
set -u
OUT="$1"; mkdir -p "$OUT"
cd /home/takano32/GitHub/ts-novels/.claude/worktrees/agent-a6dfccdb049961db6 || exit 1
find catalog bodies payloads payloads-docs -type f -print0 | sort -z | xargs -0 sha256sum > "$OUT/sha256.txt"
wc -l < "$OUT/sha256.txt" > "$OUT/count.txt"
echo "files: $(cat "$OUT/count.txt")"
awk '{print $2}' "$OUT/sha256.txt" | cut -d/ -f1 | sort | uniq -c
