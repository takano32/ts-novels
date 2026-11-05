# -*- coding: utf-8 -*-
"""tslib/works.py: 話 → 作品のクラスタの規則と、work_overrides.yml の当て方。"""
import unittest

from tslib import works as wk

TRIM_CASES = [
    # (題名, 芯) — specs/S148.md の追加規則 (回 / Lesson / EPISODE / stage / その / 其の / № / No.) と従来の巻号
    ('イマジン 第1回', 'イマジン'),
    ('本当の自分 第三話', '本当の自分'),
    ('少女の夢〜その2', '少女の夢'),
    ('少女の夢 其の弐', '少女の夢'),
    ('魔法少女 Lesson 3', '魔法少女'),
    ('天使 EPISODE 12', '天使'),
    ('Experience 1st stage', 'Experience'),
    ('Back to stage', 'Back to stage'),           # 数字の無い stage は巻号ではない
    ('鏡 №3', '鏡'),
    ('鏡 No. 3', '鏡'),
    ('女神仮面ミィミル 第一話 女神テオドラの帰還', '女神仮面ミィミル'),   # 途中の巻号で切る
    ('緋色の風（前編）', '緋色の風'),
    ('緋色の風 part2', '緋色の風'),
    ('Lesson 3', 'Lesson 3'),                     # 全部落ちるなら元の題名
    ('題名', '題名'),
    ('', ''),
]


def rec(eid, path, title, author_slug=None, date='2001-01-01', nav=None, kind='local', corpus='honkan',
        role=None):
    return {'episode_id': eid, 'source_path': path, 'title': title, 'date': date, 'nav_links': nav or [],
            'source_kind': kind, 'corpus': corpus, 'source_exists': True, 'entry_role': role,
            '_author': author_slug}


def author_of(records):
    return {r['episode_id']: r['_author'] for r in records if r['_author']}


class TitleTest(unittest.TestCase):
    def test_trim_title_table(self):
        for title, core in TRIM_CASES:
            with self.subTest(title=title):
                self.assertEqual(wk.trim_title(title), core)

    def test_stem_and_prefix(self):
        self.assertEqual(wk.stem_of('novel/a/story01.html'), 'story')
        self.assertEqual(wk.stem_of('novel/a/st_1-2.htm'), 'st')
        self.assertEqual(wk.stem_of('x/12.html'), '12')
        self.assertEqual(wk.common_prefix(['abcd', 'abxy', 'abz']), 'ab')
        self.assertEqual(wk.common_prefix([]), '')

    def test_parse_series_rows(self):
        src = ('<!-- <TR><TD>X</TD><TD><a href="z.html">z</a></TD></TR> -->'
               '<TR><TH>作者名</TH><TH>作品</TH></TR>'
               '<TR><TD>山田</TD><TD>連載A <a href="./n/t.html">シリーズタイトル</a> <a href="n/1.html#a">1</a>'
               ' <a href="http://x.example.com/">x</a></TD></TR>')
        self.assertEqual(wk.parse_series_rows(src), [
            {'author': '山田', 'work_title': '連載A', 'title_page': 'n/t.html', 'episode_paths': ['n/1.html']}])

    def test_union(self):
        u = wk.Union()
        u.union('a', 'b', 'r1')
        u.union('c', 'b', 'r2')
        self.assertEqual(u.find('c'), u.find('a'))
        self.assertEqual(u.why['b'], {'r1', 'r2'})
        self.assertNotEqual(u.find('z'), u.find('a'))


class ClusterTest(unittest.TestCase):
    def groups(self, records, series_rows=()):
        u = wk.cluster(records, author_of(records), list(series_rows))
        out = {}
        for r in records:
            out.setdefault(u.find(r['episode_id']), []).append(r['episode_id'])
        return sorted(sorted(g) for g in out.values()), u

    def test_series_title_nav_and_title_page(self):
        nav = [{'href': 'n/t.html', 'label': 'シリーズタイトルへ'}]
        recs = [rec('a', 'n/x.html', 'あ', 'au', nav=nav), rec('b', 'n/y.html', 'い', 'au', nav=nav),
                rec('t', 'n/t.html', '表紙', 'au')]
        g, u = self.groups(recs)
        self.assertEqual(g, [['a', 'b', 't']])
        self.assertEqual(u.why['t'], {'title-page'})

    def test_series_html(self):
        recs = [rec('a', 'n/p.html', 'あ', 'au'), rec('b', 'n/q.html', 'い', 'au')]
        g, u = self.groups(recs, [{'episode_paths': ['n/p.html', 'n/q.html'], 'title_page': None}])
        self.assertEqual(g, [['a', 'b']])
        self.assertEqual(u.why['a'], {'series-html'})

    def test_episode_nav(self):
        recs = [rec('a', 'n/p.html', 'あ', 'au', nav=[{'href': 'n/q.html', 'label': '第2話はこちら'}]),
                rec('b', 'n/q.html', 'い', 'au')]
        g, u = self.groups(recs)
        self.assertEqual((g, u.why['b']), ([['a', 'b']], {'episode-nav'}))

    def test_filename_stem_and_title_prefix(self):
        recs = [rec('a', 'n/d/story1.html', 'あ', 'au'), rec('b', 'n/d/story2.html', 'い', 'au'),
                rec('c', 'n/e/x.html', '長い題名 第1話', 'au'), rec('d', 'n/f/y.html', '長い題名 第2話', 'au'),
                rec('e', 'external/h/story3.html', 'う', 'au', kind='external')]
        g, u = self.groups(recs)
        self.assertEqual(g, [['a', 'b'], ['c', 'd'], ['e']])
        self.assertEqual((u.why['a'], u.why['c']), ({'filename-stem'}, {'title-prefix'}))

    def test_never_across_authors(self):
        recs = [rec('a', 'n/d/story1.html', '長い題名 第1話', 'au1'), rec('b', 'n/d/story2.html', '長い題名 第2話', 'au2')]
        g, _ = self.groups(recs)
        self.assertEqual(g, [['a'], ['b']])


class HelpersTest(unittest.TestCase):
    def test_episode_order_key(self):
        self.assertEqual(wk.episode_order_key(rec('a', 'n/s10.html', 't', date='2001-01-01')), ('2001-01-01', 10, 'n/s10.html'))
        self.assertEqual(wk.episode_order_key(rec('a', 'n/x.html', 't', date=None)), ('', 0, 'n/x.html'))

    def test_group_author(self):
        self.assertEqual(wk.group_author([rec('a', 'p', 't')], {'a': 'au'}), 'au')
        self.assertEqual(wk.group_author([rec('a', 'p', 't', role='series-index')], {}), 'series-index')
        self.assertEqual(wk.group_author([rec('a', 'p', 't', role='series-index'), rec('b', 'p', 't')], {}),
                         'unattributed')

    def test_machine_title(self):
        self.assertEqual(wk.machine_title([rec('a', 'p', '単発')]), '単発')
        self.assertEqual(wk.machine_title([rec('a', 'p', '連載物 第1話'), rec('b', 'q', '連載物 第2話')]), '連載物')
        self.assertEqual(wk.machine_title([rec('a', 'p', '共通部分あ'), rec('b', 'q', '共通部分い'),
                                           rec('c', 'r', '共通部分う')]), '共通部分')
        self.assertEqual(wk.machine_title([rec('a', 'p', 'あ'), rec('b', 'q', 'い')]), 'あ')

    def test_alias_map(self):
        members = [rec('a', 'n/1.html', 't', date='2001-01-02'), rec('b', 'n/2.html', 't', date='2001-01-01'),
                   rec('c', 'n/3.html', 't')]
        digests = {'n/1.html': 'x', 'n/2.html': 'x', 'n/3.html': None}
        alias_of, paths = wk.alias_map(members, digests.get)
        self.assertEqual((alias_of, paths), ({'a': 'b'}, ['n/1.html']))


class OverridesTest(unittest.TestCase):
    def setUp(self):
        self.recs = [rec('a1', 'n/a1.html', '甲 第1話', 'au'), rec('a2', 'n/a2.html', '甲 第2話', 'au'),
                     rec('a3', 'n/a3.html', '甲 第3話', 'au'), rec('b1', 'n/b1.html', '乙作品', 'au'),
                     rec('c1', 'n/c1.html', '丙作品', 'other')]
        self.author_of = author_of(self.recs)
        self.order_of = {r['episode_id']: i for i, r in enumerate(self.recs)}
        self.groups = [self.recs[:3], self.recs[3:4], self.recs[4:]]

    def apply(self, ov):
        return wk.apply_overrides([list(g) for g in self.groups], ov, self.author_of, self.order_of)

    def shape(self, units):
        return sorted((u['kind'], sorted(m['episode_id'] for m in u['members']), u['override'], u['confirmed'])
                      for u in units)

    def test_no_directive(self):
        units, errors = self.apply({'au|甲': {'work_title': '甲の物語'}})
        self.assertEqual(errors, [])
        self.assertEqual([u['key'] for u in units], ['au|甲', 'au|乙作品', 'other|丙作品'])

    def test_merge_split_detach_confirmed(self):
        units, errors = self.apply({
            'au|乙作品': {'merge_into': 'au|甲'},
            'au|甲': {'split': [{'work_title': '甲外伝', 'episodes': ['a3']}], 'detach': ['b1']},
            'other|丙作品': {'confirmed': True}})
        self.assertEqual(errors, [])
        self.assertEqual(self.shape(units), [
            ('cluster', ['a1', 'a2'], True, False), ('cluster', ['c1'], True, True),
            ('detach', ['b1'], True, False), ('split', ['a3'], True, False)])
        self.assertEqual([u['title'] for u in units if u['kind'] == 'split'], ['甲外伝'])

    def test_errors(self):
        cases = [
            ({'au|甲': 'x'}, 'au|甲: 値が辞書ではない'),
            ({'au|甲': {'confirmed': 'yes', 'bogus': 1}}, "au|甲: 知らない項目 ['bogus']"),
            ({'au|甲': {'confirmed': 'yes'}}, 'au|甲: confirmed は true / false'),
            ({'au|無い': {'confirmed': True}}, 'au|無い: 鍵に当たるクラスタが 0 件 (1 件であること)'),
            ({'au|甲': {'merge_into': 'au|甲'}}, 'au|甲: merge_into の先が自分自身'),
            ({'au|甲': {'merge_into': 'other|丙作品'}}, 'au|甲: merge_into の先 other|丙作品 と作者が違う'),
            ({'au|甲': {'merge_into': 'au|乙作品'}, 'au|乙作品': {'merge_into': 'au|甲'}},
             'au|乙作品: merge_into の先 au|甲 も merge_into を持つ (連鎖は書かない)'),
            ({'au|甲': {'detach': ['zz']}}, "au|甲: detach の 'zz' は実在しない episode_id"),
            ({'au|甲': {'detach': ['b1']}}, 'au|甲: detach の b1 はこのクラスタの話ではない'),
            ({'au|甲': {'detach': ['a1'], 'split': [{'work_title': 'x', 'episodes': ['a1']}]}},
             'au|甲: detach の a1 は au|甲 の split 「x」 にも現れる (同じ話は 1 つの指示だけ)'),
            ({'au|甲': {'split': [{'work_title': ' ', 'episodes': ['a1']}]}}, 'au|甲: split の work_title が空'),
            ({'au|甲': {'split': [{'work_title': 'x', 'episodes': []}]}}, 'au|甲: split 「x」の episodes が空'),
            ({'au|甲': {'split': 'a1'}}, 'au|甲: split はリスト'),
        ]
        for ov, message in cases:
            with self.subTest(message=message):
                _, errors = self.apply(ov)
                self.assertIn(message, errors)


class AssembleTest(unittest.TestCase):
    def setUp(self):
        self.recs = [rec('a1', 'n/d/story1.html', '甲乙丙丁 第1話', 'au', date='2001-01-01'),
                     rec('a2', 'n/d/story2.html', '甲乙丙丁 第2話', 'au', date='2001-02-01'),
                     rec('x1', 'n/x.html', 'Solo', None, role='unattributed')]
        self.authors = [{'slug': 'au', 'display_name': '作者', 'episodes': ['a1', 'a2']}]

    def test_assemble(self):
        works, report, overrides, review, errors = wk.assemble(self.recs, self.authors, [], set(), {}, {})
        self.assertEqual(errors, [])
        self.assertEqual([w['work_slug'] for w in works][1], 'unattributed-solo')
        w = works[0]
        self.assertEqual(list(w), ['work_slug', 'title', 'author_slug', 'author_display', 'episode_count',
                                   'corpora', 'first_date', 'last_date', 'title_pages', 'title_page_on_disk',
                                   'evidence', 'needs_review', 'multi_directory', 'alias_paths', 'slug_source',
                                   'slug_status', 'episodes'])
        self.assertEqual(list(w['episodes'][0]), ['episode_id', 'menu_order', 'title', 'source_path', 'date', 'alias_of'])
        self.assertEqual((w['title'], w['evidence'], w['needs_review']),
                         ('甲乙丙丁', ['filename-stem', 'title-prefix'], True))
        self.assertEqual(works[1]['author_display'], '作者不詳')
        self.assertEqual([k for k, _ in review], ['au|甲乙丙丁'])
        self.assertEqual(report['orphans'], [])
        self.assertEqual(report['needs_review_reasons'], {'weak-evidence': 1})
        self.assertEqual(list(report)[:3], ['generated_by', 'episodes', 'works'])
        self.assertIn('works', overrides)
        lines = wk.summary_lines(report)
        self.assertEqual(lines[0], 'works=2 (単発 1 / 連載 1)  episodes=3  orphan=0')

    def test_confirmed_slug_and_stub(self):
        prev = {'works': {'au|甲乙丙丁': {'slug': 'au-kou', 'status': 'confirmed', 'source': 'manual'}}}
        works, _, _, review, _ = wk.assemble(self.recs, self.authors, [], set(), prev,
                                             {'au|甲乙丙丁': {'confirmed': True}})
        self.assertEqual((works[0]['work_slug'], works[0]['slug_status'], works[0]['needs_review']),
                         ('au-kou', 'confirmed', False))
        self.assertEqual(review, [])
        stub = wk.overrides_stub({'old': {}}, [('k', {'title': 'T', 'episode_count': 2, 'evidence': ['x']})])
        self.assertEqual(stub, {'old': {}, 'k': {'work_title': 'T', 'episodes': 2, 'evidence': ['x'], 'confirmed': False}})
        self.assertEqual(wk.confirmed_but_review([('k', {})], {'k': {'confirmed': True}}), ['k'])


if __name__ == '__main__':
    unittest.main()
