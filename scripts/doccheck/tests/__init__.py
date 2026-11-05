# -*- coding: utf-8 -*-
"""doccheck の試験 (T45.2.22)。流し方は `make test`
(= python -m unittest discover -s scripts/doccheck/tests -t scripts)。

DocTreeCase は問題 0 件の小さな文書の一式を一時ディレクトリに作る。各試験はそこから 1 か所だけ壊す。
"""
import shutil
import tempfile
import unittest
from pathlib import Path

SPECS_MD = '''# SPECS

| S | 題名 | 種類 | 状態 | 出典 |
|---|---|---|---|---|
| [S1](specs/S1.md) | 一つめ | 仕様 | 有効 | 試験 |
| [S2](specs/S2.md) | 二つめ | 記録 | 完了 | 試験 |

いまの最後は S2、次は S3
'''
TASKS_MD = '''# TASKS

| T | 題名 | 状態 | 関連 |
|---|---|---|---|
| [T1](tasks/T1.md) | 最初 | 一部完了 | S1 |

いまの最後は T1、次は T2
'''
S1 = '# S1 一つめ\n\n- 種類: 仕様\n- 状態: 有効\n\n本文は S2 と T1.1 を指す。\n'
S2 = '# S2 二つめ\n\n- 種類: 記録\n- 状態: 完了\n'
T1 = '''# T1 最初

- 状態: 一部完了
- 関連: S1

- [x] T1.1 やったこと
- [ ] T1.2 まだのこと
  - [ ] T1.2.1 孫の行
'''


class DocTreeCase(unittest.TestCase):
    """setUp で self.root に問題 0 件の文書の一式を作り、tearDown で消す。"""

    def setUp(self):
        self.root = Path(tempfile.mkdtemp(prefix='doccheck-test-'))
        for rel, text in (('README.md', '# README\n\n[仕様の一覧](SPECS.md)\n'), ('DOCS.md', '# DOCS\n'),
                          ('SPECS.md', SPECS_MD), ('TASKS.md', TASKS_MD),
                          ('docs/a.md', '# a\n'), ('specs/S1.md', S1), ('specs/S2.md', S2),
                          ('tasks/T1.md', T1), ('scripts/doc_check_allow.tsv', '# 許可リスト\n')):
            self.write(rel, text)

    def tearDown(self):
        shutil.rmtree(self.root, ignore_errors=True)

    def write(self, rel, text):
        p = self.root / rel
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(text, encoding='utf-8')
        return p

    def append(self, rel, text):
        p = self.root / rel
        p.write_text(p.read_text(encoding='utf-8') + text, encoding='utf-8')
        return p

    def replace(self, rel, old, new):
        p = self.root / rel
        s = p.read_text(encoding='utf-8')
        self.assertIn(old, s)
        p.write_text(s.replace(old, new, 1), encoding='utf-8')
        return p

    @property
    def allow_path(self):
        return self.root / 'scripts' / 'doc_check_allow.tsv'
