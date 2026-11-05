# -*- coding: utf-8 -*-
"""lists.py — SPECS.md・TASKS.md の一覧と specs/・tasks/ の突き合わせ、tasks/Tn.md の書式。"""
import re

from doccheck.rules import STATES_T, TASK_ITEM


def check_specs_list(root, specs, problems):
    """SPECS.md の一覧と specs/ (番号の欠け・重複、題名・種類・状態、「いまの最後は」)。"""
    specs_md = root / 'SPECS.md'
    s_text = specs_md.read_text(encoding='utf-8')
    rows = re.findall(r'^\| \[S([0-9]+)\]\(specs/S([0-9]+)\.md\) \| (.*?) \| (.*?) \| (.*?) \| .*\|$', s_text, re.M)
    listed = [int(a) for a, *_ in rows]
    for a, b, *_ in rows:
        if a != b:
            problems.report(specs_md, 0, 'list', f'S{a} の行のリンク先が S{b}')
    if len(listed) != len(set(listed)):
        problems.report(specs_md, 0, 'list', 'SPECS.md の一覧に同じ S が 2 回ある')
    for n in sorted(set(specs) - set(listed)):
        problems.report(specs_md, 0, 'list', f'specs/S{n}.md が SPECS.md の一覧に無い')
    for n in sorted(set(listed) - set(specs)):
        problems.report(specs_md, 0, 'list', f'一覧の S{n} のファイルが無い')
    if specs and sorted(specs) != list(range(1, max(specs) + 1)):
        problems.report(specs_md, 0, 'list', 'specs/ の番号に欠けがある')
    for a, _, title, kind, state in rows:
        p = specs.get(int(a))
        if not p:
            continue
        L = p.read_text(encoding='utf-8').split('\n')
        head = {}
        for ln in L[:8]:
            m = re.match(r'^- (種類|状態): (.*)$', ln)
            if m:
                head[m.group(1)] = m.group(2)
        m = re.match(rf'^# S{a} (.*)$', L[0])
        if not m:
            problems.report(p, 1, 'list', f'1 行目が「# S{a} 題名」でない')
        elif m.group(1).strip() != title.replace('\\|', '|').strip():
            problems.report(p, 1, 'list', f'題名が SPECS.md の一覧と違う: 「{m.group(1)}」/「{title}」')
        if head.get('種類') != kind.strip():
            problems.report(p, 0, 'list', f'種類が SPECS.md の一覧と違う: 「{head.get("種類")}」/「{kind}」')
        if head.get('状態') != state.strip():
            problems.report(p, 0, 'list', f'状態が SPECS.md の一覧と違う: 「{head.get("状態")}」/「{state}」')
    m = re.search(r'いまの最後は S([0-9]+)、次は S([0-9]+)', s_text)
    if not m or int(m.group(1)) != max(specs) or int(m.group(2)) != max(specs) + 1:
        problems.report(specs_md, 0, 'list', f'「いまの最後は S{max(specs)}、次は S{max(specs) + 1}」と書かれていない')


def check_tasks_list(root, tasks, problems):
    """TASKS.md の一覧と tasks/ (番号の欠け・重複、題名・状態、「いまの最後は」) と tasks/Tn.md の書式。"""
    tasks_md = root / 'TASKS.md'
    t_text = tasks_md.read_text(encoding='utf-8')
    rows = re.findall(r'^\| \[T([0-9]+)\]\(tasks/T([0-9]+)\.md\) \| (.*?) \| (.*?) \| .*\|$', t_text, re.M)
    listed = [int(a) for a, *_ in rows]
    for a, b, *_ in rows:
        if a != b:
            problems.report(tasks_md, 0, 'list', f'T{a} の行のリンク先が T{b}')
    if len(listed) != len(set(listed)):
        problems.report(tasks_md, 0, 'list', 'TASKS.md の一覧に同じ T が 2 回ある')
    for n in sorted(set(tasks) - set(listed)):
        problems.report(tasks_md, 0, 'list', f'tasks/T{n}.md が TASKS.md の一覧に無い')
    for n in sorted(set(listed) - set(tasks)):
        problems.report(tasks_md, 0, 'list', f'一覧の T{n} のファイルが無い')
    m = re.search(r'いまの最後は T([0-9]+)、次は T([0-9]+)', t_text)
    if tasks and (not m or int(m.group(1)) != max(tasks) or int(m.group(2)) != max(tasks) + 1):
        problems.report(tasks_md, 0, 'list', f'「いまの最後は T{max(tasks)}、次は T{max(tasks) + 1}」と書かれていない')
    row_of = {int(a): (title, state) for a, _, title, state in rows}
    for n, p in sorted(tasks.items()):
        check_task_file(n, p, row_of.get(n), tasks_md, problems)


def check_task_file(n, p, row, tasks_md, problems):
    """tasks/Tn.md の書式 (1・3・4 行目、番号の親、状態とチェックボックス) と TASKS.md の行 row (題名, 状態)。"""
    L = p.read_text(encoding='utf-8').split('\n')
    m = re.match(rf'^# T{n} (.*)$', L[0])
    if not m:
        problems.report(p, 1, 'format', f'1 行目が「# T{n} 題名」でない')
        return
    st = L[2][len('- 状態: '):] if len(L) > 2 and L[2].startswith('- 状態: ') else None
    if st not in STATES_T:
        problems.report(p, 3, 'format', '3 行目が「- 状態: 完了 / 未了 / 一部完了」のどれかでない')
    if len(L) < 4 or not L[3].startswith('- 関連: '):
        problems.report(p, 4, 'format', '4 行目が「- 関連: …」でない')
    boxes = []
    for i, ln in enumerate(L, 1):
        mm = TASK_ITEM.match(ln)
        if mm:
            boxes.append(mm.group(1))
            if not mm.group(2).startswith(f'T{n}.'):
                problems.report(p, i, 'format', f'{mm.group(2)} は T{n} の下の番号でない', ln)
    if boxes and st in STATES_T:
        want = '完了' if all(b == 'x' for b in boxes) else '未了' if all(b == ' ' for b in boxes) else '一部完了'
        if st != want:
            problems.report(p, 3, 'format', f'状態「{st}」がチェックボックスと合わない (「{want}」のはず)')
    if row is not None:
        title, state = row
        if m.group(1).strip() != title.replace('\\|', '|').strip():
            problems.report(tasks_md, 0, 'list', f'T{n} の題名が tasks/T{n}.md と違う')
        if st != state.strip():
            problems.report(tasks_md, 0, 'list', f'T{n} の状態が tasks/T{n}.md と違う: 「{state}」/「{st}」')
