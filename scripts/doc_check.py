#!/usr/bin/env python3
"""doc_check.py — 文書 (docs / specs / tasks) の検査 (仕様は specs/S219.md)。

調べるもの:
  1. S 番号 (Sn、Sn〜Sm) が specs/Sn.md として実在する
  2. T 番号 (Tn、Tn.m…) が tasks/Tn.md の見出しか小・孫タスクの行として実在する
  3. Markdown の相対リンク [..](path) の行き先が実在する
  4. SPECS.md の一覧と specs/ が一致する (番号の欠け・重複、題名・種類・状態)
  5. TASKS.md の一覧と tasks/ が一致する (番号の欠け・重複、題名・状態)。tasks/Tn.md の書式
  6. SPECS.md・TASKS.md の「いまの最後は … 次は …」が実際の最後の番号と合う
  8. takedown/denylist.yml の各 id が S 番号で、specs/Sn.md として実在する (取り下げの記録の S。S221)
  7. 使わない語 (旧形式の取り下げ番号・§・台帳・Phase n・CP-4・👤・本館/別館/旧館・正典・Tier A/B/C・OF/VR/TF/VS/CO の単独語)
     と、エージェントが作った識別子 (要決定事項の Q 番号・不具合の (A) 記号・丸数字・設計の版 v1.n・文書の版 v0/v1・
     計画の第 n 段・「Sn の 記号/番号」・決定 n・取り下げ番号の旧略記 TD-0・cat + 数字・T0/T+n・審査員 n・
     旧タスクリストの番号 (元の n.n、- [x] n.n)・n 節・A 案・補足/原則/ルール n)

正当な残り (引用・コード片・旧番号の対応表など) は scripts/doc_check_allow.tsv に理由つきで書く。
問題が 1 件でもあれば終了コード 1。

使い方: python3 scripts/doc_check.py [--root DIR]
ロジックは scripts/doccheck/ (この入口は引数の解釈・表示・終了コードだけ)。
"""
import argparse
import sys
from pathlib import Path

from doccheck.allow import AllowListError
from doccheck.run import output_lines, run


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--root', default=str(Path(__file__).resolve().parent.parent))
    ap.add_argument('--allow', default=None, help='許可リスト (既定: <root>/scripts/doc_check_allow.tsv)')
    args = ap.parse_args()
    root = Path(args.root).resolve()
    try:
        result = run(root, Path(args.allow) if args.allow else root / 'scripts' / 'doc_check_allow.tsv')
    except AllowListError as e:
        raise SystemExit(str(e))
    for line in output_lines(result):
        print(line)
    return 1 if result.problems else 0


if __name__ == '__main__':
    sys.exit(main())
