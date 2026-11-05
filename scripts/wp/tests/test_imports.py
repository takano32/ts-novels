# -*- coding: utf-8 -*-
"""tslib の import の決まり (T45.2.8.8・T45.2.35): ast で import 文を読んで、依存の向きを確かめる。

  - tslib のモジュールの間に循環が無い
  - 独立検算 (tslib/verify_html5.py・tslib/qa_phase3.py と、その入口 scripts/wp/verify_bodies_html5.py・
    scripts/wp/qa_phase3_check.py) は本体の変換系 (tslib/bodies.py・tslib/payloads.py・tslib/htmltext.py) を
    import しない (specs/S12.md)。直接の import だけでなく、共通部分を経由して届くもの (閉包) も見る
  - 段のモジュール (catalog.*・terms/authors/works/uncatalogued/reposts/overrides/bodies/payloads/docs/qa) は
    互いを import しない。catalog.* の中どうしは 1 つの段とみなす。例外は STAGE_EXCEPTIONS の表
"""
import ast
import os
import unittest

WP = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TSLIB = os.path.join(WP, 'tslib')
INDEPENDENT = ('verify_html5', 'qa_phase3')
# 独立検算の入口。グラフの上では「入口:<ファイル名>」の名前で置く
INDEPENDENT_ENTRIES = ('verify_bodies_html5.py', 'qa_phase3_check.py')
CONVERTERS = ('bodies', 'payloads', 'htmltext')
STAGES = ('catalog', 'terms', 'authors', 'works', 'uncatalogued', 'reposts', 'overrides', 'bodies', 'payloads',
          'docs', 'qa')
# 例外 (import する側, される側のモジュール):
#   docs → bodies          運営文書が本文の切り出し bodies.extract_body を共有するのは妥当として残す (T45.2.16)
#   uncatalogued → catalog.keys・catalog.provenance
#                          目録外の話にも目録と同じ episode_id・URL・出所の付け方を当てる。この 2 本は
#                          目録のパースではなく共通部分として使っている (specs/S243.md の「構成と責務」)
STAGE_EXCEPTIONS = {('docs', 'bodies'), ('uncatalogued', 'catalog.keys'), ('uncatalogued', 'catalog.provenance')}


def module_name(path):
    rel = os.path.relpath(path, TSLIB)[:-3].replace(os.sep, '.')
    return rel[:-len('.__init__')] if rel.endswith('__init__') else rel


def is_module(name):
    """tslib の下にその名前のモジュールかパッケージがあるか。"""
    base = os.path.join(TSLIB, *name.split('.'))
    return os.path.isfile(base + '.py') or os.path.isfile(os.path.join(base, '__init__.py'))


def tslib_imports(path):
    """そのファイルが import する tslib のモジュール名 (tslib. を外した形) の集合。
    `from tslib.X import a` で a がモジュールなら X.a も入れる (`from tslib import a` と同じ扱い)。"""
    with open(path, encoding='utf-8') as fh:
        tree = ast.parse(fh.read(), path)
    out = set()
    for node in ast.walk(tree):
        if isinstance(node, ast.ImportFrom) and node.module and node.module.split('.')[0] == 'tslib':
            mod = node.module[len('tslib'):].lstrip('.')
            if mod:
                out.add(mod)
            for a in node.names:
                sub = mod + '.' + a.name if mod else a.name
                if is_module(sub):
                    out.add(sub)
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
    for name in INDEPENDENT_ENTRIES:
        g['入口:' + name] = tslib_imports(os.path.join(WP, name))
    return g


def closure(g, start):
    """start から import を辿って届くモジュールの集合 (start 自身は含まない)。
    X.Y を import すると親のパッケージ X の __init__ も読み込まれるので、親も辿る。"""
    seen, todo = set(), list(g.get(start, ()))
    while todo:
        m = todo.pop()
        if m in seen:
            continue
        seen.add(m)
        todo.extend(g.get(m, ()))
        if '.' in m:
            todo.append(m.rsplit('.', 1)[0])
    return seen


def stage_of(m):
    """段のモジュールなら段の名前 (catalog.* は catalog)、それ以外は None。"""
    top = m.split('.')[0]
    return top if top in STAGES else None


class ImportRulesTest(unittest.TestCase):
    def setUp(self):
        self.g = graph()

    def test_graph_is_read(self):
        self.assertIn('catalog.records', self.g)
        self.assertIn('catalog.regular', self.g['catalog.records'])
        self.assertIn('qa_phase3', self.g['入口:qa_phase3_check.py'])

    def test_closure(self):
        g = {'a': {'b.c'}, 'b.c': {'d'}, 'b': {'e'}, 'd': set(), 'e': set()}
        self.assertEqual(closure(g, 'a'), {'b.c', 'b', 'd', 'e'})

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

    def test_independent_check_does_not_reach_converters(self):
        for m in INDEPENDENT + tuple('入口:' + n for n in INDEPENDENT_ENTRIES):
            self.assertIn(m, self.g)
            self.assertEqual(closure(self.g, m) & set(CONVERTERS), set(), m)

    def test_stages_do_not_import_each_other(self):
        for st in STAGES:
            self.assertTrue(is_module(st), st)
        for m in sorted(self.g):
            st = stage_of(m)
            if st is None:
                continue
            others = {d for d in self.g[m]
                      if stage_of(d) not in (None, st) and (m, d) not in STAGE_EXCEPTIONS}
            self.assertEqual(others, set(), m)

    def test_exceptions_are_used(self):
        # 例外の表に、もう無い import が残っていないこと
        for m, d in sorted(STAGE_EXCEPTIONS):
            self.assertIn(d, self.g[m], (m, d))


if __name__ == '__main__':
    unittest.main()
