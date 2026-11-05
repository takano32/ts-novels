# -*- coding: utf-8 -*-
"""run.py: 全体の流れ・表示の行・許可リストの当て方。doccheck が tslib を import しないこと。"""
import ast
import os
import subprocess
import sys
import unittest
from pathlib import Path

from doccheck.allow import AllowListError, load_allow
from doccheck.run import output_lines, run
from doccheck.tests import DocTreeCase

DOCCHECK = Path(__file__).resolve().parent.parent
ENTRY = DOCCHECK.parent / 'doc_check.py'


class RunTest(DocTreeCase):
    def test_clean_tree(self):
        r = run(self.root, self.allow_path)
        self.assertEqual(output_lines(r), [
            '  [OK ] 文書の検査: 問題 0 件 (S 2 本 / T 1 本 / T 番号 4 / 許可リストで通した 0 件)'])

    def test_problems_in_order(self):
        # denylist → 各文書の行 → SPECS.md → TASKS.md の順
        self.write('takedown/denylist.yml', '  - id: S9\n')
        self.append('docs/a.md', '台帳\n')
        self.replace('SPECS.md', 'いまの最後は S2', 'いまの最後は S1')
        self.replace('TASKS.md', '| 一部完了 |', '| 完了 |')
        self.assertEqual(output_lines(run(self.root, self.allow_path)), [
            '  [NG ] takedown/denylist.yml:1 [deny] denylist の id S9 が specs/ の S として無い',
            '  [NG ] docs/a.md:2 [word] 使わない語「台帳」',
            '  [NG ] SPECS.md:0 [list] 「いまの最後は S2、次は S3」と書かれていない',
            '  [NG ] TASKS.md:0 [list] T1 の状態が tasks/T1.md と違う: 「完了」/「一部完了」',
            '  [NG ] 文書の検査: 問題 4 件 (S 2 本 / T 1 本 / T 番号 4 / 許可リストで通した 0 件)',
        ])

    def test_allow_rules_and_unused_notice(self):
        self.append('docs/a.md', '台帳 の引用\n台帳\n')
        self.write('scripts/doc_check_allow.tsv',
                   '# 許可リスト\n\ndocs/*.md\tword:台帳\tの引用\t規則の引用\n'
                   'docs/*.md\tlink\tの引用\t種類が違う\nnone.md\tword:台帳\tzzz\t使われない\n')
        r = run(self.root, self.allow_path)
        self.assertEqual(r.used, 1)
        self.assertEqual(output_lines(r), [
            '  [NG ] docs/a.md:3 [word] 使わない語「台帳」',
            "  [注意] 許可リスト 4 行目は使われていない: docs/*.md link 'の引用'",
            "  [注意] 許可リスト 5 行目は使われていない: none.md word:台帳 'zzz'",
            '  [NG ] 文書の検査: 問題 1 件 (S 2 本 / T 1 本 / T 番号 4 / 許可リストで通した 1 件)',
        ])

    def test_allow_rule_narrows_same_line(self):
        # 許可リストの行は書いた規則の問題だけを通す。同じ行の別の規則の問題は残る (T45.7.7 より前は行と種類だけで通した)
        self.append('docs/a.md', '台帳 と § の引用\n')
        self.write('scripts/doc_check_allow.tsv', '# 許可リスト\ndocs/*.md\tword:台帳\tの引用\t規則の引用\n')
        r = run(self.root, self.allow_path)
        self.assertEqual((r.problems, r.used), (['docs/a.md:2 [word] 使わない語「§」'], 1))
        self.write('scripts/doc_check_allow.tsv', '# 許可リスト\ndocs/*.md\tword:台帳\tの引用\t規則の引用\n'
                   'docs/*.md\tword:§\tの引用\t規則の引用\n')
        r = run(self.root, self.allow_path)
        self.assertEqual((r.problems, r.used), ([], 2))

    def test_allow_kind_and_rule_forms(self):
        # word は規則の名前が要る (無い名前も受け付けない)。word 以外の種類には規則の名前を書けない
        for kind, msg in (('word', '「word:<規則の名前>」と書く'), ('word:', '「word:<規則の名前>」と書く'),
                          ('word:無い規則', '「word:<規則の名前>」と書く'), ('link:台帳', 'word の種類だけ')):
            with self.subTest(kind=kind):
                self.write('scripts/doc_check_allow.tsv', f'# 許可リスト\ndocs/*.md\t{kind}\tx\t理由\n')
                with self.assertRaises(AllowListError) as cm:
                    run(self.root, self.allow_path)
                self.assertTrue(str(cm.exception).startswith(f'{self.allow_path}:2: '), str(cm.exception))
                self.assertIn(msg, str(cm.exception))

    def test_empty_specs(self):
        # specs/ が空でも落ちない (T45.7.7 より前は「いまの最後は」の max() が ValueError)
        for n in (1, 2):
            (self.root / 'specs' / f'S{n}.md').unlink()
        r = run(self.root, self.allow_path)
        self.assertEqual([p for p in r.problems if 'いまの最後は' in p], [])
        self.assertIn('SPECS.md:0 [list] 一覧の S1 のファイルが無い', r.problems)
        self.assertEqual(r.n_specs, 0)

    def test_broken_allow_line(self):
        self.write('scripts/doc_check_allow.tsv', '# 許可リスト\nfoo\tword\tbar\t \n')
        with self.assertRaises(AllowListError) as cm:
            run(self.root, self.allow_path)
        self.assertEqual(str(cm.exception), f'{self.allow_path}:2: 許可リストの行は'
                         '「パス<TAB>種類<TAB>行に含まれる文字列<TAB>理由」の 4 列')

    def test_missing_allow_file(self):
        self.assertEqual(load_allow(self.root / 'none.tsv'), [])

    def test_entry_exit_codes(self):
        """入口 scripts/doc_check.py: 問題 0 件で 0、問題があれば 1、許可リストの形が違えば標準エラーに文を出して 1。"""
        def entry():
            return subprocess.run([sys.executable, str(ENTRY), '--root', str(self.root)],
                                  capture_output=True, text=True, encoding='utf-8')
        p = entry()
        self.assertEqual((p.returncode, p.stdout.count('\n'), p.stderr), (0, 1, ''))
        self.append('docs/a.md', '台帳\n')
        self.assertEqual(entry().returncode, 1)
        self.write('scripts/doc_check_allow.tsv', 'foo\n')
        p = entry()
        self.assertEqual((p.returncode, p.stdout), (1, ''))
        self.assertIn('許可リストの行は', p.stderr)

    def test_entry_safe_path(self):
        """入口は python3 -I (safe path。スクリプトの置き場を sys.path に足さない) でも動く (T45.7.7)。"""
        p = subprocess.run([sys.executable, '-I', str(ENTRY), '--root', str(self.root)],
                           capture_output=True, text=True, encoding='utf-8', cwd=str(self.root))
        self.assertEqual((p.returncode, p.stderr), (0, ''))
        self.assertTrue(p.stdout.startswith('  [OK ] 文書の検査: 問題 0 件'), p.stdout)


class ImportTest(unittest.TestCase):
    def test_no_tslib(self):
        """doccheck は catalog の共有部分 (scripts/wp/tslib) を import しない (specs/S243.md の設計の判断)。"""
        found = []
        for dirpath, _dirs, files in os.walk(DOCCHECK):
            for name in files:
                if not name.endswith('.py'):
                    continue
                path = os.path.join(dirpath, name)
                with open(path, encoding='utf-8') as fh:
                    tree = ast.parse(fh.read(), path)
                for node in ast.walk(tree):
                    mods = ([a.name for a in node.names] if isinstance(node, ast.Import)
                            else [node.module or ''] if isinstance(node, ast.ImportFrom) else [])
                    found += [(name, m) for m in mods if m.split('.')[0] == 'tslib']
        self.assertEqual(found, [])


if __name__ == '__main__':
    unittest.main()
