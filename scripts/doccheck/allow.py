# -*- coding: utf-8 -*-
"""allow.py — 許可リスト (scripts/doc_check_allow.tsv) と、問題の集め方。

許可リストの行は「パス (glob 可)<TAB>種類<TAB>行に含まれる文字列<TAB>理由」。
問題は Problems.report に渡し、許可リストの行に当たればその行の使用回数を数えて捨てる。
"""
import fnmatch
from pathlib import Path


class AllowListError(Exception):
    """許可リストの行の形が違う。文は旧版の SystemExit と同じ。"""


def load_allow(path):
    rules = []
    if not path.exists():
        return rules
    for i, ln in enumerate(path.read_text(encoding='utf-8').split('\n'), 1):
        if not ln.strip() or ln.startswith('#'):
            continue
        cols = ln.split('\t')
        if len(cols) != 4 or not cols[3].strip():
            raise AllowListError(f'{path}:{i}: 許可リストの行は「パス<TAB>種類<TAB>行に含まれる文字列<TAB>理由」の 4 列')
        rules.append({'path': cols[0], 'kind': cols[1], 'text': cols[2], 'reason': cols[3], 'line': i, 'used': 0})
    return rules


class Problems:
    """見つけた問題の列 (`<root からの相対パス>:<行> [<種類>] <説明>`) と、許可リストの当て方。"""

    def __init__(self, root, rules):
        self.root = root
        self.rules = rules
        self.items = []

    def report(self, f, lineno, kind, msg, line=''):
        rel = f.relative_to(self.root).as_posix() if isinstance(f, Path) else f
        for r in self.rules:
            if r['kind'] == kind and fnmatch.fnmatch(rel, r['path']) and r['text'] in line:
                r['used'] += 1
                return
        self.items.append(f'{rel}:{lineno} [{kind}] {msg}')

    def unused_rules(self):
        return [r for r in self.rules if not r['used']]

    def used_count(self):
        return sum(r['used'] for r in self.rules)
