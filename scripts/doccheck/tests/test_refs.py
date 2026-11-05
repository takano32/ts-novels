# -*- coding: utf-8 -*-
"""refs.py: 番号の実在・リンク・使わない語の行ごとの検査と、denylist の id。"""
import unittest

from doccheck import inventory, refs
from doccheck.allow import Problems
from doccheck.tests import DocTreeCase


class RefsTest(DocTreeCase):
    def check(self):
        problems = Problems(self.root, [])
        specs = inventory.spec_files(self.root)
        t_ids = inventory.task_ids(inventory.task_files(self.root))
        refs.check_denylist(self.root, specs, problems)
        refs.check_docs(inventory.doc_files(self.root), specs, t_ids, problems)
        return problems.items

    def test_clean_tree(self):
        self.assertEqual(self.check(), [])

    def test_inventory(self):
        self.assertEqual(sorted(inventory.spec_files(self.root)), [1, 2])
        self.assertEqual(inventory.task_ids(inventory.task_files(self.root)),
                         {'T1', 'T1.1', 'T1.2', 'T1.2.1'})
        names = [p.relative_to(self.root).as_posix() for p in inventory.doc_files(self.root)]
        self.assertEqual(names, ['README.md', 'DOCS.md', 'SPECS.md', 'TASKS.md', 'docs/a.md',
                                 'specs/S1.md', 'specs/S2.md', 'tasks/T1.md'])

    def test_missing_numbers(self):
        self.append('docs/a.md', 'S3 と S1〜S9 と T1.3、次は S4、次は T2\n')
        self.assertEqual(self.check(), [
            'docs/a.md:2 [S] S3 が specs/ に無い',
            'docs/a.md:2 [S] S9 が specs/ に無い',
            'docs/a.md:2 [T] T1.3 が tasks/ に無い',
        ])

    def test_links(self):
        self.append('docs/a.md', '[無い](none.md) [ある](../SPECS.md#x) `[コード](none.md)` '
                                 '[外](https://example.com/) [頁内](#a) [絶対](/x)\n')
        self.assertEqual(self.check(), ['docs/a.md:2 [link] リンク先が無い: none.md'])

    def test_banned_words(self):
        self.append('docs/a.md', '本館 と 台帳\n')
        self.assertEqual(self.check(), [
            'docs/a.md:2 [word] 使わない語「台帳」',
            'docs/a.md:2 [word] 使わない語「本館/別館/旧館」',
        ])

    def test_denylist_ids(self):
        self.write('takedown/denylist.yml', 'entries:\n  - id: S2\n  - id: S7\n  - id: TD-x\n')
        self.assertEqual(self.check(), [
            'takedown/denylist.yml:3 [deny] denylist の id S7 が specs/ の S として無い',
            'takedown/denylist.yml:4 [deny] denylist の id TD-x が specs/ の S として無い',
        ])

    def test_guide_files_are_checked(self):
        self.write('catalog/README.md', '台帳\n')
        self.assertEqual(self.check(), ['catalog/README.md:1 [word] 使わない語「台帳」'])


if __name__ == '__main__':
    unittest.main()
