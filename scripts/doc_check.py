#!/usr/bin/env python3
"""doc_check.py — 文書 (docs / specs / tasks) の検査 (仕様は specs/S.219.md)。

調べるもの:
  1. S 番号 (S.n、S.n〜S.m) が specs/S.n.md として実在する
  2. T 番号 (T.n、T.n.m…) が tasks/T.n.md の見出しか小・孫タスクの行として実在する
  3. Markdown の相対リンク [..](path) の行き先が実在する
  4. SPECS.md の一覧と specs/ が一致する (番号の欠け・重複、題名・種類・状態)
  5. TASKS.md の一覧と tasks/ が一致する (番号の欠け・重複、題名・状態)。tasks/T.n.md の書式
  6. SPECS.md・TASKS.md の「いまの最後は … 次は …」が実際の最後の番号と合う
  8. TD 番号 (TD.n) が TODO.md の行として実在し、takedown/denylist.yml の id が TODO.md にある
  7. 使わない語 (旧形式の取り下げ番号・§・台帳・Phase n・CP-4・👤・本館/別館/旧館・正典・Tier A/B/C・OF/VR/TF/VS/CO の単独語)

正当な残り (引用・コード片・旧番号の対応表など) は scripts/doc_check_allow.tsv に理由つきで書く。
問題が 1 件でもあれば終了コード 1。

使い方: python3 scripts/doc_check.py [--root DIR]
"""
import argparse
import fnmatch
import re
import sys
from pathlib import Path

BANNED = [
    ('旧形式の取り下げ番号', re.compile(r'TD-(?:20[0-9][0-9]|YYYY)-')),
    ('§', re.compile(r'§')),
    ('台帳', re.compile(r'台帳')),
    ('Phase n', re.compile(r'Phase [0-9]')),
    ('CP-4', re.compile(r'CP-4')),
    ('👤', re.compile(r'👤')),
    ('本館/別館/旧館', re.compile(r'本館|別館|旧館')),
    ('正典', re.compile(r'正典')),
    ('Tier A/B/C', re.compile(r'Tier [ABC]')),
    ('略記 OF/VR/TF/VS/CO', re.compile(r'(?<![A-Za-z0-9_])(?:OF|VR|TF|VS|CO)(?![A-Za-z0-9_])')),
]
# 「次は S.n」「次は T.n」はまだ無い番号の予告なので見ない (一覧の検査で正しさを見る)
S_REF = re.compile(r'(?<![\w.])(?<!次は )S\.([0-9]+)(?:〜S\.([0-9]+))?(?![0-9])')
T_REF = re.compile(r'(?<![\w.])(?<!次は )(T\.[0-9]+(?:\.[0-9]+)*)(?![0-9])')
CODE = re.compile(r'`[^`]*`')
LINK = re.compile(r'\]\(([^)\s]+)\)')
TD_REF = re.compile(r'(?<![\w.])(?<!次は )(TD\.[0-9]+)(?![0-9])')
TD_ITEM = re.compile(r'^\s*- \[[ x]\] (TD\.[0-9]+)(?![0-9])')
TASK_ITEM = re.compile(r'^\s*- \[([ x])\] (T\.[0-9]+(?:\.[0-9]+)*)(?![0-9.])')
STATES_T = ('完了', '未了', '一部完了')


def doc_files(root):
    files = [root / n for n in ('README.md', 'DOCS.md', 'SPECS.md', 'TASKS.md', 'TODO.md')]
    files += sorted((root / 'docs').glob('*.md'))
    files += sorted((root / 'specs').glob('S.*.md'), key=lambda p: int(p.stem.split('.')[1]))
    files += sorted((root / 'tasks').glob('T.*.md'), key=lambda p: int(p.stem.split('.')[1]))
    # ディレクトリに残した「移した先」の案内
    for rel in ('scripts/README.md', 'catalog/README.md', 'catalog/review/README.md', 'takedown/README.md'):
        if (root / rel).exists():
            files.append(root / rel)
    return [f for f in files if f.exists()]


def load_allow(path):
    rules = []
    if not path.exists():
        return rules
    for i, ln in enumerate(path.read_text(encoding='utf-8').split('\n'), 1):
        if not ln.strip() or ln.startswith('#'):
            continue
        cols = ln.split('\t')
        if len(cols) != 4 or not cols[3].strip():
            raise SystemExit(f'{path}:{i}: 許可リストの行は「パス<TAB>種類<TAB>行に含まれる文字列<TAB>理由」の 4 列')
        rules.append({'path': cols[0], 'kind': cols[1], 'text': cols[2], 'reason': cols[3], 'line': i, 'used': 0})
    return rules


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--root', default=str(Path(__file__).resolve().parent.parent))
    ap.add_argument('--allow', default=None, help='許可リスト (既定: <root>/scripts/doc_check_allow.tsv)')
    args = ap.parse_args()
    root = Path(args.root).resolve()
    allow = load_allow(Path(args.allow) if args.allow else root / 'scripts' / 'doc_check_allow.tsv')
    problems = []

    def report(f, lineno, kind, msg, line=''):
        rel = f.relative_to(root).as_posix() if isinstance(f, Path) else f
        for r in allow:
            if r['kind'] == kind and fnmatch.fnmatch(rel, r['path']) and r['text'] in line:
                r['used'] += 1
                return
        problems.append(f'{rel}:{lineno} [{kind}] {msg}')

    # --- 実在する番号
    spec_files = {}
    for p in (root / 'specs').glob('S.*.md'):
        m = re.fullmatch(r'S\.([0-9]+)', p.stem)
        if m:
            spec_files[int(m.group(1))] = p
    task_files = {}
    for p in (root / 'tasks').glob('T.*.md'):
        m = re.fullmatch(r'T\.([0-9]+)', p.stem)
        if m:
            task_files[int(m.group(1))] = p
    t_ids = {f'T.{n}' for n in task_files}
    for n, p in task_files.items():
        for ln in p.read_text(encoding='utf-8').split('\n'):
            m = TASK_ITEM.match(ln)
            if m:
                t_ids.add(m.group(2))

    td_ids = set()
    todo_md = root / 'TODO.md'
    if todo_md.exists():
        for ln in todo_md.read_text(encoding='utf-8').split('\n'):
            m = TD_ITEM.match(ln)
            if m:
                td_ids.add(m.group(1))
        m = re.search(r'いまの最後は TD\.([0-9]+)、次は TD\.([0-9]+)', todo_md.read_text(encoding='utf-8'))
        nums = sorted(int(x[3:]) for x in td_ids)
        if nums and (not m or int(m.group(1)) != nums[-1] or int(m.group(2)) != nums[-1] + 1):
            report(todo_md, 0, 'list', f'「いまの最後は TD.{nums[-1]}、次は TD.{nums[-1] + 1}」と書かれていない')
        if nums and nums != list(range(1, nums[-1] + 1)):
            report(todo_md, 0, 'list', 'TODO.md の TD 番号に欠けか重複がある')
    deny = root / 'takedown' / 'denylist.yml'
    if deny.exists():
        for i, ln in enumerate(deny.read_text(encoding='utf-8').split('\n'), 1):
            m = re.match(r'^  - id: (\S+)', ln)
            if m and m.group(1) not in td_ids:
                report(deny, i, 'TD', f'denylist の {m.group(1)} が TODO.md に無い', ln)

    # --- 1〜3, 7: 各文書の行
    for f in doc_files(root):
        for i, ln in enumerate(f.read_text(encoding='utf-8').split('\n'), 1):
            for a, b in S_REF.findall(ln):
                for n in (a, b):
                    if n and int(n) not in spec_files:
                        report(f, i, 'S', f'S.{n} が specs/ に無い', ln)
            for t in TD_REF.findall(ln):
                if t not in td_ids:
                    report(f, i, 'TD', f'{t} が TODO.md に無い', ln)
            for t in T_REF.findall(ln):
                if t not in t_ids:
                    report(f, i, 'T', f'{t} が tasks/ に無い', ln)
            for target in LINK.findall(CODE.sub('', ln)):  # コード片の中の [..](..) はリンクではない
                if re.match(r'(https?|mailto):', target) or target.startswith(('#', '<', '/')):
                    continue
                path = target.split('#', 1)[0]
                if path and not (f.parent / path).exists():
                    report(f, i, 'link', f'リンク先が無い: {target}', ln)
            for name, rx in BANNED:
                if rx.search(ln):
                    report(f, i, 'word', f'使わない語「{name}」', ln)

    # --- 4: SPECS.md と specs/
    specs_md = root / 'SPECS.md'
    s_text = specs_md.read_text(encoding='utf-8')
    rows = re.findall(r'^\| \[S\.([0-9]+)\]\(specs/S\.([0-9]+)\.md\) \| (.*?) \| (.*?) \| (.*?) \| .*\|$', s_text, re.M)
    listed = [int(a) for a, *_ in rows]
    for a, b, *_ in rows:
        if a != b:
            report(specs_md, 0, 'list', f'S.{a} の行のリンク先が S.{b}')
    if len(listed) != len(set(listed)):
        report(specs_md, 0, 'list', 'SPECS.md の一覧に同じ S が 2 回ある')
    for n in sorted(set(spec_files) - set(listed)):
        report(specs_md, 0, 'list', f'specs/S.{n}.md が SPECS.md の一覧に無い')
    for n in sorted(set(listed) - set(spec_files)):
        report(specs_md, 0, 'list', f'一覧の S.{n} のファイルが無い')
    if spec_files and sorted(spec_files) != list(range(1, max(spec_files) + 1)):
        report(specs_md, 0, 'list', 'specs/ の番号に欠けがある')
    for a, _, title, kind, state in rows:
        p = spec_files.get(int(a))
        if not p:
            continue
        L = p.read_text(encoding='utf-8').split('\n')
        head = {}
        for ln in L[:8]:
            m = re.match(r'^- (種類|状態): (.*)$', ln)
            if m:
                head[m.group(1)] = m.group(2)
        m = re.match(rf'^# S\.{a} (.*)$', L[0])
        if not m:
            report(p, 1, 'list', f'1 行目が「# S.{a} 題名」でない')
        elif m.group(1).strip() != title.replace('\\|', '|').strip():
            report(p, 1, 'list', f'題名が SPECS.md の一覧と違う: 「{m.group(1)}」/「{title}」')
        if head.get('種類') != kind.strip():
            report(p, 0, 'list', f'種類が SPECS.md の一覧と違う: 「{head.get("種類")}」/「{kind}」')
        if head.get('状態') != state.strip():
            report(p, 0, 'list', f'状態が SPECS.md の一覧と違う: 「{head.get("状態")}」/「{state}」')
    m = re.search(r'いまの最後は S\.([0-9]+)、次は S\.([0-9]+)', s_text)
    if not m or int(m.group(1)) != max(spec_files) or int(m.group(2)) != max(spec_files) + 1:
        report(specs_md, 0, 'list', f'「いまの最後は S.{max(spec_files)}、次は S.{max(spec_files) + 1}」と書かれていない')

    # --- 5: TASKS.md と tasks/
    tasks_md = root / 'TASKS.md'
    t_text = tasks_md.read_text(encoding='utf-8')
    rows = re.findall(r'^\| \[T\.([0-9]+)\]\(tasks/T\.([0-9]+)\.md\) \| (.*?) \| (.*?) \| .*\|$', t_text, re.M)
    listed = [int(a) for a, *_ in rows]
    for a, b, *_ in rows:
        if a != b:
            report(tasks_md, 0, 'list', f'T.{a} の行のリンク先が T.{b}')
    if len(listed) != len(set(listed)):
        report(tasks_md, 0, 'list', 'TASKS.md の一覧に同じ T が 2 回ある')
    for n in sorted(set(task_files) - set(listed)):
        report(tasks_md, 0, 'list', f'tasks/T.{n}.md が TASKS.md の一覧に無い')
    for n in sorted(set(listed) - set(task_files)):
        report(tasks_md, 0, 'list', f'一覧の T.{n} のファイルが無い')
    m = re.search(r'いまの最後は T\.([0-9]+)、次は T\.([0-9]+)', t_text)
    if task_files and (not m or int(m.group(1)) != max(task_files) or int(m.group(2)) != max(task_files) + 1):
        report(tasks_md, 0, 'list', f'「いまの最後は T.{max(task_files)}、次は T.{max(task_files) + 1}」と書かれていない')
    row_of = {int(a): (title, state) for a, _, title, state in rows}
    for n, p in sorted(task_files.items()):
        L = p.read_text(encoding='utf-8').split('\n')
        m = re.match(rf'^# T\.{n} (.*)$', L[0])
        if not m:
            report(p, 1, 'format', f'1 行目が「# T.{n} 題名」でない')
            continue
        st = L[2][len('- 状態: '):] if len(L) > 2 and L[2].startswith('- 状態: ') else None
        if st not in STATES_T:
            report(p, 3, 'format', '3 行目が「- 状態: 完了 / 未了 / 一部完了」のどれかでない')
        if len(L) < 4 or not L[3].startswith('- 関連: '):
            report(p, 4, 'format', '4 行目が「- 関連: …」でない')
        boxes = []
        for i, ln in enumerate(L, 1):
            mm = TASK_ITEM.match(ln)
            if mm:
                boxes.append(mm.group(1))
                if not mm.group(2).startswith(f'T.{n}.'):
                    report(p, i, 'format', f'{mm.group(2)} は T.{n} の下の番号でない', ln)
        if boxes and st in STATES_T:
            want = '完了' if all(b == 'x' for b in boxes) else '未了' if all(b == ' ' for b in boxes) else '一部完了'
            if st != want:
                report(p, 3, 'format', f'状態「{st}」がチェックボックスと合わない (「{want}」のはず)')
        if n in row_of:
            title, state = row_of[n]
            if m.group(1).strip() != title.replace('\\|', '|').strip():
                report(tasks_md, 0, 'list', f'T.{n} の題名が tasks/T.{n}.md と違う')
            if st != state.strip():
                report(tasks_md, 0, 'list', f'T.{n} の状態が tasks/T.{n}.md と違う: 「{state}」/「{st}」')

    for p in problems:
        print(f'  [NG ] {p}')
    for r in allow:
        if not r['used']:
            print(f'  [注意] 許可リスト {r["line"]} 行目は使われていない: {r["path"]} {r["kind"]} {r["text"]!r}')
    used = sum(r['used'] for r in allow)
    mark = 'OK ' if not problems else 'NG '
    print(f'  [{mark}] 文書の検査: 問題 {len(problems)} 件 (S {len(spec_files)} 本 / T {len(task_files)} 本 / '
          f'T 番号 {len(t_ids)} / 許可リストで通した {used} 件)')
    return 1 if problems else 0


if __name__ == '__main__':
    sys.exit(main())
