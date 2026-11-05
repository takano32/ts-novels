# -*- coding: utf-8 -*-
"""tslib の import の決まり (T45.2.8.8): ast で import 文を読んで、依存の向きを確かめる。

  - tslib のモジュールの間に循環が無い
  - 独立検算 (tslib/verify_html5.py・tslib/qa_phase3.py) は本体の変換系
    (tslib/bodies.py・tslib/payloads.py・tslib/htmltext.py) を import しない (specs/S12.md)
  - 段のモジュール (terms/authors/works/uncatalogued/reposts/bodies/payloads/docs/qa) は互いを import しない
    (docs → bodies の extract_body だけは例外。STAGE_EXCEPTIONS)
"""
import ast
import os
import unittest

TSLIB = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'tslib')
INDEPENDENT = ('verify_html5', 'qa_phase3')
CONVERTERS = ('bodies', 'payloads', 'htmltext')
STAGES = ('terms', 'authors', 'works', 'uncatalogued', 'reposts', 'bodies', 'payloads', 'docs', 'qa')
# 例外: 運営文書 (docs) が本文の切り出し bodies.extract_body を共有するのは妥当として残す (T45.2.16)
STAGE_EXCEPTIONS = {('docs', 'bodies')}


def module_name(path):
    rel = os.path.relpath(path, TSLIB)[:-3].replace(os.sep, '.')
    return rel[:-len('.__init__')] if rel.endswith('__init__') else rel


def tslib_imports(path):
    """そのファイルが import する tslib のモジュール名 (tslib. を外した形) の集合。"""
    with open(path, encoding='utf-8') as fh:
        tree = ast.parse(fh.read(), path)
    out = set()
    for node in ast.walk(tree):
        if isinstance(node, ast.ImportFrom) and node.module and node.module.split('.')[0] == 'tslib':
            mod = node.module[len('tslib'):].lstrip('.')
            if mod:
                out.add(mod)
            else:                                   # from tslib import a, b
                out.update(a.name for a in node.names)
        elif isinstance(node, ast.Import):
            for a in node.names:
                if a.name.startswith('tslib.'):
                    out.add(a.name[len('tslib.'):])
    return out


def graph():
    g = {}
    for dirpath, _dirs, files in os.walk(TSLIB):
        for name in files:
            if name.endswith('.py'):
                path = os.path.join(dirpath, name)
                g[module_name(path)] = tslib_imports(path)
    return g


class ImportRulesTest(unittest.TestCase):
    def setUp(self):
        self.g = graph()

    def test_graph_is_read(self):
        self.assertIn('catalog.records', self.g)
        self.assertIn('catalog.regular', self.g['catalog.records'])

    def test_no_cycle(self):
        state = {}

        def visit(m, stack):
            state[m] = 'open'
            for d in sorted(self.g.get(m, ())):
                if d not in self.g:
                    continue
                if state.get(d) == 'open':
                    self.fail('循環: %s' % ' → '.join(stack + [m, d]))
                if d not in state:
                    visit(d, stack + [m])
            state[m] = 'done'
        for m in sorted(self.g):
            if m not in state:
                visit(m, [])

    def test_independent_check_does_not_import_converters(self):
        for m in INDEPENDENT:
            self.assertIn(m, self.g)
            self.assertEqual(self.g[m] & set(CONVERTERS), set(), m)

    def test_stages_do_not_import_each_other(self):
        for m in STAGES:
            self.assertIn(m, self.g)
            others = {d for d in self.g[m] & set(STAGES) if d != m and (m, d) not in STAGE_EXCEPTIONS}
            self.assertEqual(others, set(), m)


if __name__ == '__main__':
    unittest.main()
