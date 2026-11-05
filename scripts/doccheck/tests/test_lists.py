# -*- coding: utf-8 -*-
"""lists.py: SPECS.md・TASKS.md の一覧の食い違いと tasks/Tn.md の書式。"""
import unittest

from doccheck import inventory, lists
from doccheck.allow import Problems
from doccheck.tests import TASKS_MD, DocTreeCase


class ListsTest(DocTreeCase):
    def check(self):
        problems = Problems(self.root, [])
        lists.check_specs_list(self.root, inventory.spec_files(self.root), problems)
        lists.check_tasks_list(self.root, inventory.task_files(self.root), problems)
        return problems.items

    def test_clean_tree(self):
        self.assertEqual(self.check(), [])

    def test_specs_missing_row_and_file(self):
        self.write('specs/S3.md', '# S3 三つめ\n\n- 種類: 仕様\n- 状態: 有効\n')
        self.replace('SPECS.md', '| [S2](specs/S2.md)', '| [S4](specs/S4.md)')
        self.assertEqual(self.check(), [
            'SPECS.md:0 [list] specs/S2.md が SPECS.md の一覧に無い',
            'SPECS.md:0 [list] specs/S3.md が SPECS.md の一覧に無い',
            'SPECS.md:0 [list] 一覧の S4 のファイルが無い',
            'SPECS.md:0 [list] 「いまの最後は S3、次は S4」と書かれていない',
        ])

    def test_specs_duplicate_link_and_gap(self):
        self.append('SPECS.md', '| [S1](specs/S2.md) | 一つめ | 仕様 | 有効 | 試験 |\n')
        self.write('specs/S4.md', '# S4 四つめ\n')
        out = self.check()
        self.assertEqual(out[:3], [
            'SPECS.md:0 [list] S1 の行のリンク先が S2',
            'SPECS.md:0 [list] SPECS.md の一覧に同じ S が 2 回ある',
            'SPECS.md:0 [list] specs/S4.md が SPECS.md の一覧に無い',
        ])
        self.assertIn('SPECS.md:0 [list] specs/ の番号に欠けがある', out)

    def test_specs_title_kind_state(self):
        self.write('specs/S2.md', '# S2 ちがう題\n\n- 種類: 仕様\n- 状態: 有効\n')
        self.write('specs/S1.md', '題名の行が無い\n\n- 種類: 仕様\n- 状態: 有効\n')
        self.assertEqual(self.check(), [
            'specs/S1.md:1 [list] 1 行目が「# S1 題名」でない',
            'specs/S2.md:1 [list] 題名が SPECS.md の一覧と違う: 「ちがう題」/「二つめ」',
            'specs/S2.md:0 [list] 種類が SPECS.md の一覧と違う: 「仕様」/「記録」',
            'specs/S2.md:0 [list] 状態が SPECS.md の一覧と違う: 「有効」/「完了」',
        ])

    def test_tasks_list(self):
        self.write('tasks/T2.md', '# T2 二番\n\n- 状態: 未了\n- 関連: S1\n')
        self.replace('TASKS.md', '| 最初 | 一部完了 |', '| 最初の題 | 完了 |')
        self.append('TASKS.md', '| [T1](tasks/T3.md) | 最初 | 未了 | S1 |\n')
        self.assertEqual(self.check(), [
            'TASKS.md:0 [list] T1 の行のリンク先が T3',
            'TASKS.md:0 [list] TASKS.md の一覧に同じ T が 2 回ある',
            'TASKS.md:0 [list] tasks/T2.md が TASKS.md の一覧に無い',
            'TASKS.md:0 [list] 「いまの最後は T2、次は T3」と書かれていない',
            # 同じ T の行が 2 つあれば後の行で比べる (題名は後の行と同じ)
            'TASKS.md:0 [list] T1 の状態が tasks/T1.md と違う: 「未了」/「一部完了」',
        ])

    def test_task_format_first_line(self):
        self.replace('tasks/T1.md', '# T1 最初', '# 題だけ')
        self.assertEqual(self.check(), ['tasks/T1.md:1 [format] 1 行目が「# T1 題名」でない'])

    def test_task_format_lines_3_and_4_and_parent(self):
        self.replace('tasks/T1.md', '- 状態: 一部完了\n- 関連: S1', '- 状態: 途中\n関連: S1')
        self.append('tasks/T1.md', '- [ ] T2.1 よその番号\n')
        self.assertEqual(self.check(), [
            'tasks/T1.md:3 [format] 3 行目が「- 状態: 完了 / 未了 / 一部完了」のどれかでない',
            'tasks/T1.md:4 [format] 4 行目が「- 関連: …」でない',
            'tasks/T1.md:9 [format] T2.1 は T1 の下の番号でない',
            'TASKS.md:0 [list] T1 の状態が tasks/T1.md と違う: 「一部完了」/「途中」',
        ])

    def test_task_state_vs_boxes(self):
        for state, boxes, want in (('完了', '- [ ] T1.1 a\n', '未了'), ('未了', '- [x] T1.1 a\n', '完了'),
                                   ('完了', '- [x] T1.1 a\n- [ ] T1.2 b\n', '一部完了')):
            with self.subTest(state=state, want=want):
                self.write('tasks/T1.md', f'# T1 最初\n\n- 状態: {state}\n- 関連: S1\n\n{boxes}')
                self.write('TASKS.md', TASKS_MD.replace('| 一部完了 |', f'| {state} |'))
                self.assertEqual(self.check(), [
                    f'tasks/T1.md:3 [format] 状態「{state}」がチェックボックスと合わない (「{want}」のはず)'])


if __name__ == '__main__':
    unittest.main()
