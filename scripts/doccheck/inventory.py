# -*- coding: utf-8 -*-
"""inventory.py — 検査する文書の一覧と、実在する S と T の番号。"""
import re

from doccheck.rules import TASK_ITEM


def doc_files(root):
    files = [root / n for n in ('README.md', 'DOCS.md', 'SPECS.md', 'TASKS.md')]
    files += sorted((root / 'docs').glob('*.md'))
    files += sorted((root / 'specs').glob('S*.md'), key=lambda p: int(p.stem[1:]))
    files += sorted((root / 'tasks').glob('T*.md'), key=lambda p: int(p.stem[1:]))
    # ディレクトリに残した「移した先」の案内
    for rel in ('scripts/README.md', 'catalog/README.md', 'catalog/review/README.md', 'takedown/README.md'):
        if (root / rel).exists():
            files.append(root / rel)
    return [f for f in files if f.exists()]


def spec_files(root):
    """{番号: specs/Sn.md のパス}。"""
    found = {}
    for p in (root / 'specs').glob('S*.md'):
        m = re.fullmatch(r'S([0-9]+)', p.stem)
        if m:
            found[int(m.group(1))] = p
    return found


def task_files(root):
    """{番号: tasks/Tn.md のパス}。"""
    found = {}
    for p in (root / 'tasks').glob('T*.md'):
        m = re.fullmatch(r'T([0-9]+)', p.stem)
        if m:
            found[int(m.group(1))] = p
    return found


def task_ids(tasks):
    """実在する T 番号の集合: tasks/Tn.md の Tn と、各ファイルのチェックボックスの行の番号。"""
    ids = {f'T{n}' for n in tasks}
    for n, p in tasks.items():
        for ln in p.read_text(encoding='utf-8').split('\n'):
            m = TASK_ITEM.match(ln)
            if m:
                ids.add(m.group(2))
    return ids
