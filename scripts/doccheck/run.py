# -*- coding: utf-8 -*-
"""run.py — 文書の検査を順に流し、結果を表示の行にする。

問題の並びは旧版 scripts/doc_check.py と同じ (denylist → 各文書の行 → SPECS.md → TASKS.md)。
`[OK ]`/`[NG ]` の印はここで 1 回だけ書く (catalog の自己検査の束は使わない)。
"""
from collections import namedtuple

from doccheck import inventory, lists, refs
from doccheck.allow import Problems, load_allow

Result = namedtuple('Result', 'problems unused used n_specs n_tasks n_task_ids')


def run(root, allow_path):
    """root の文書を検査して Result を返す。許可リストの形が違えば AllowListError。"""
    problems = Problems(root, load_allow(allow_path))
    specs = inventory.spec_files(root)
    tasks = inventory.task_files(root)
    t_ids = inventory.task_ids(tasks)
    refs.check_denylist(root, specs, problems)
    refs.check_docs(inventory.doc_files(root), specs, t_ids, problems)
    lists.check_specs_list(root, specs, problems)
    lists.check_tasks_list(root, tasks, problems)
    return Result(problems.items, problems.unused_rules(), problems.used_count(),
                  len(specs), len(tasks), len(t_ids))


def output_lines(result):
    lines = [f'  [NG ] {p}' for p in result.problems]
    for r in result.unused:
        lines.append(f'  [注意] 許可リスト {r["line"]} 行目は使われていない: {r["path"]} {r["kind_col"]} {r["text"]!r}')
    mark = 'OK ' if not result.problems else 'NG '
    lines.append(f'  [{mark}] 文書の検査: 問題 {len(result.problems)} 件 (S {result.n_specs} 本 / T {result.n_tasks} 本 / '
                 f'T 番号 {result.n_task_ids} / 許可リストで通した {result.used} 件)')
    return lines
