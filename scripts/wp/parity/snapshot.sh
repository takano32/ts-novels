#!/usr/bin/env bash
# 生成物一式を作り、sha256 と検査の出力を $1 に保存する (worktree の中だけで動く)
set -u
OUT="$1"; mkdir -p "$OUT"
cd /home/takano32/GitHub/ts-novels/.claude/worktrees/agent-a6dfccdb049961db6 || exit 1
# catalog/ は消さない (catalog_build の自己検査が前回の works.jsonl を読む。消すと最初の段で止まる)
rm -rf bodies payloads payloads-docs
S=$(date +%s)
make catalog > "$OUT/catalog.out" 2>&1; echo "catalog exit=$?" >> "$OUT/exit.txt"
echo "catalog sec=$(( $(date +%s) - S ))" >> "$OUT/exit.txt"
.venv/bin/python scripts/wp/payload_build.py > "$OUT/payload_build.out" 2>&1; echo "payload_build exit=$?" >> "$OUT/exit.txt"
.venv/bin/python scripts/wp/docs_build.py > "$OUT/docs_build.out" 2>&1; echo "docs_build exit=$?" >> "$OUT/exit.txt"
make check > "$OUT/check.out" 2>&1; echo "check exit=$?" >> "$OUT/exit.txt"
.venv/bin/python scripts/wp/secret_scan.py > "$OUT/secret_scan.out" 2>&1; echo "secret_scan exit=$?" >> "$OUT/exit.txt"
make qa > "$OUT/qa.out" 2>&1; echo "qa exit=$?" >> "$OUT/exit.txt"
git status --short > "$OUT/git-status.txt"
S=$(date +%s)
make verify > "$OUT/verify.out" 2>&1; echo "verify exit=$?" >> "$OUT/exit.txt"
echo "verify sec=$(( $(date +%s) - S ))" >> "$OUT/exit.txt"
find catalog bodies payloads payloads-docs -type f -print0 | sort -z | xargs -0 sha256sum > "$OUT/sha256.txt"
wc -l < "$OUT/sha256.txt" > "$OUT/count.txt"
echo done >> "$OUT/exit.txt"
