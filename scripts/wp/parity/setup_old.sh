#!/usr/bin/env bash
# HEAD 時点の scripts/ を scratchpad/old に展開し、catalog/ と takedown/ を worktree へ繋ぐ
set -eu
SP=/tmp/claude-1001/-home-takano32-GitHub-ts-novels/566c9861-9e8b-4c10-9155-27e6e2c7f91e/scratchpad
WT=/home/takano32/GitHub/ts-novels/.claude/worktrees/agent-a6dfccdb049961db6
rm -rf "$SP/old"; mkdir -p "$SP/old"
cd "$WT"
git archive HEAD scripts | tar -x -C "$SP/old"
ln -s "$WT/catalog" "$SP/old/catalog"
ln -s "$WT/takedown" "$SP/old/takedown"
ls "$SP/old/scripts/wp" | head -30
