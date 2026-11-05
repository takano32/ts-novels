# -*- coding: utf-8 -*-
"""refs.py — 各文書の行の検査 (番号の実在・リンク・使わない語) と、denylist の id の検査。"""
import re

from doccheck.rules import BANNED, CODE, LINK, S_REF, T_REF


def check_denylist(root, specs, problems):
    """takedown/denylist.yml の各 id が S 番号で、specs/Sn.md として実在する (S221)。"""
    deny = root / 'takedown' / 'denylist.yml'
    if not deny.exists():
        return
    for i, ln in enumerate(deny.read_text(encoding='utf-8').split('\n'), 1):
        m = re.match(r'^  - id: (\S+)', ln)
        if m:
            mm = re.fullmatch(r'S\.([0-9]+)', m.group(1))
            if not mm or int(mm.group(1)) not in specs:
                problems.report(deny, i, 'deny', f'denylist の id {m.group(1)} が specs/ の S として無い', ln)


def check_line(f, i, ln, specs, t_ids, problems):
    """文書 f の i 行目 ln: S と T の番号の実在・相対リンクの行き先・使わない語。"""
    for a, b in S_REF.findall(ln):
        for n in (a, b):
            if n and int(n) not in specs:
                problems.report(f, i, 'S', f'S{n} が specs/ に無い', ln)
    for t in T_REF.findall(ln):
        if t not in t_ids:
            problems.report(f, i, 'T', f'{t} が tasks/ に無い', ln)
    for target in LINK.findall(CODE.sub('', ln)):  # コード片の中の [..](..) はリンクではない
        if re.match(r'(https?|mailto):', target) or target.startswith(('#', '<', '/')):
            continue
        path = target.split('#', 1)[0]
        if path and not (f.parent / path).exists():
            problems.report(f, i, 'link', f'リンク先が無い: {target}', ln)
    for name, rx in BANNED:
        if rx.search(ln):
            problems.report(f, i, 'word', f'使わない語「{name}」', ln)


def check_docs(files, specs, t_ids, problems):
    for f in files:
        for i, ln in enumerate(f.read_text(encoding='utf-8').split('\n'), 1):
            check_line(f, i, ln, specs, t_ids, problems)
