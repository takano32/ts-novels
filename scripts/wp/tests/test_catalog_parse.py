# -*- coding: utf-8 -*-
"""tslib/catalog/regular.py・legacy.py: 作り物の目録エントリのパース。"""
import os
import unittest

from tslib.catalog import regular as rg
from tslib.catalog import legacy as lg
from tests.support import DATA


def data(name):
    with open(os.path.join(DATA, name), encoding='utf-8') as fh:
        return fh.read()


class RegularTest(unittest.TestCase):
    def test_parse_entry(self):
        e = rg.parse_entry(data('catalog_regular_entry.html'), 'lib1.html', 1)
        self.assertEqual(list(e), [
            'title', 'takedown_notice', 'dropped_mailto', '_suisen_full_empty', 'href', 'author',
            'homepage', 'illustrator', 'illustrator_url', 'illustrator_raw', 'date_raw', 'date',
            'weekday', 'size_kb', 'files_n', 'kansou_slug', 'arasuji', 'comment', 'suisen', 'osusume',
            'nav_links', 'inline_links', 'genre', 'genre_raw', 'type', 'type_raw', 'keywords',
            'keywords_raw', 'zokusei', 'catalog_ref'])
        self.assertEqual((e['title'], e['href'], e['author'], e['homepage']),
                         ('題名', 'novel/a.html', 'たろう', 'http://hp.example.com/'))
        self.assertEqual(e['dropped_mailto'], ['taro@example.com'])
        self.assertEqual((e['illustrator'], e['illustrator_raw']), (['花子'], '花子'))
        self.assertEqual((e['date'], e['weekday'], e['size_kb'], e['files_n'], e['kansou_slug']),
                         ('2001-02-03', '土', 12, 2, 'taro'))
        self.assertEqual((e['arasuji'], e['comment'], e['suisen']), ('あら', 'こめ', 'すい'))
        self.assertEqual(e['nav_links'], [{'href': 'b.html', 'label': '第2話はこちら'}])
        self.assertEqual((e['genre'], e['genre_raw']), (['学園', 'TS'], ['学園', 'ＴＳ']))
        self.assertIsNone(e['takedown_notice'])

    def test_parse_page_collects_failures(self):
        good = data('catalog_regular_entry.html')
        bad = good.replace('<TR><TD><B>【あらすじ】', '<TR><TD>x</TD></TR><TR><TD><B>【あらすじ】')
        entries, failures = rg.parse_page('lib1.html', good + bad)
        self.assertEqual(len(entries), 1)
        self.assertEqual(failures, ['lib1.html#2: expected 4 rows, got 5'])

    def test_author_cell_forms(self):
        self.assertEqual(rg.parse_author_cell('<B><A href="#">NAME</a></B> さん'), ('NAME', None))
        self.assertEqual(rg.parse_author_cell('<B>NAME さん</B> (<a href="h.html">Homepage</a>)'),
                         ('NAME', 'h.html'))
        self.assertEqual(rg.parse_author_cell('<B>NAME</B><a href="x.html">x</a> <a href="h.html">Homepage</a>'),
                         ('NAME', 'h.html'))
        with self.assertRaises(rg.ParseError):
            rg.parse_author_cell('名前なし')

    def test_illust_cell(self):
        self.assertEqual(rg.parse_illust_cell('(イラストなし)'), ([], None, None))
        self.assertEqual(
            rg.parse_illust_cell('画(<B>a</B>(<B>巴（<a href="http://x.example.com/">Homepage</a>）、Bさん</B>さん))'),
            (['巴', 'B'], 'http://x.example.com/', '巴、Bさん'))
        with self.assertRaises(rg.ParseError):
            rg.parse_illust_cell('画なし')

    def test_osusume_author_backfill(self):
        got = rg.parse_osusume('<B>【山田さんのオススメ作品】</B> <a href="x1.html">『緋色の風１</a>'
                               '<a href="x2.html">／２』</a>（鈴木さん作）')
        self.assertEqual(got['recommender'], '山田')
        self.assertEqual([(r['title'], r['author'], r['author_inherited']) for r in got['refs']],
                         [('緋色の風１', '鈴木', True), ('／２', '鈴木', False)])
        self.assertIsNone(got['text'])
        self.assertIsNone(rg.parse_osusume('なし'))

    def test_takedown_notice(self):
        self.assertEqual(rg.takedown_notice('前文。作者である太郎さんの要望により公開を停止しました（2002/1/1）'),
                         {'author': '太郎', 'kind': '公開を停止', 'date_raw': ''})
        self.assertIsNone(rg.takedown_notice(None))
        self.assertIsNone(rg.takedown_notice('なし'))


class LegacyTest(unittest.TestCase):
    def test_resolve_year(self):
        s, e = (1997, 11, 4), (1998, 4, 5)
        self.assertEqual(lg.leg_resolve_year(12, 1, s, e, 1998), (1997, 'range-derived'))
        self.assertEqual(lg.leg_resolve_year(4, 10, s, e, 1998), (1998, 'range-clamped'))
        self.assertEqual(lg.leg_resolve_year(3, 1, (1997, 3, 1), (1998, 3, 1), 1998), (1997, 'range-ambiguous'))

    def test_parse_date_two_digit_boundary(self):
        self.assertEqual(lg.leg_parse_date('49/1/2'), '2049-01-02')
        self.assertEqual(lg.leg_parse_date('50/1/2'), '1950-01-02')
        self.assertEqual(lg.leg_parse_date('1999/9/12'), '1999-09-12')
        self.assertIsNone(lg.leg_parse_date('x'))
        self.assertIsNone(lg.leg_parse_date(None))

    def test_parse_credit(self):
        self.assertEqual(lg.leg_parse_credit('作 / 画・山田さん'), ('山田', ['山田'], '作 / 画・山田さん', None))
        self.assertEqual(lg.leg_parse_credit('作・A / 画・B、C (<a href="http://h.example.com/">WWW</a>)'),
                         ('A', ['B', 'C'], '作・A / 画・B、C', 'http://h.example.com/'))
        self.assertEqual(lg.leg_parse_credit('画・D'), ('D', ['D'], '画・D', None))
        self.assertEqual(lg.leg_parse_credit(''), (None, [], None, None))

    def test_split_labels(self):
        self.assertEqual(lg.leg_split_labels('<b>あらすじ：</b>あら<br><b>作者様コメント：こめ</b><br>（編集部）'),
                         ([('あらすじ', 'あら'), ('作者様コメント', 'こめ')], '<br></b><br>（編集部）'))

    def test_page_range(self):
        self.assertEqual(lg.leg_page_range('旧作品</font>(1997.11.4 - 1998.4.5)'), ((1997, 11, 4), (1998, 4, 5)))
        self.assertEqual(lg.leg_page_range('旧作品</font>(1999.4.5 - )'), ((1999, 4, 5), None))
        self.assertEqual(lg.leg_page_range('なし'), (None, None))

    def test_flat_page(self):
        failures = []
        out = lg.parse_legacy_flat_page('lib01.html', data('catalog_legacy_flat.html'), failures)
        self.assertEqual(failures, [])
        got = [(o['title'], o['href'], o['author'], o['date'], o['date_precision'], o['size_kb'],
                o['arasuji'], o['commented_out'], o['catalog_ref']) for o in out]
        self.assertEqual(got, [
            ('題名X', 'novel/x.html', '山田', '1999-04-05', 'range-derived', 44, 'あら', False, 'lib01.html#1'),
            ('Y', 'novel/y.html', '鈴木', '1998-12-01', 'range-derived', 10, None, True, 'lib01.html#2')])
        failures = []
        self.assertEqual(lg.parse_legacy_flat_page('lib01.html', 'x', failures), [])
        self.assertEqual(failures, ['lib01.html: page range not found'])

    def test_table_page_and_notice_role(self):
        entries, failures = lg.parse_legacy_table_page('lib07.html', data('catalog_legacy_table.html'))
        self.assertEqual(failures, [])
        work, notice = entries
        self.assertEqual((work['title'], work['author'], work['illustrator'], work['date'], work['size_kb']),
                         ('T題', '川田', ['森'], '1999-09-12', 20))
        self.assertEqual(work['noteky_url']['id'], 'abc')
        self.assertEqual((work['genre'], work['zokusei'], work['suisen'], work['entry_role']),
                         (['学園'], ['x', 'y'], 'すす', 'work'))
        # 作者欄が *** で分類欄も無いブロックは告知
        self.assertEqual((notice['author'], notice['notice'], notice['entry_role'], notice['size_kb']),
                         (None, 'おし', 'notice', None))

    def test_parse_legacy_pages(self):
        entries, failures = lg.parse_legacy_pages([('lib01.html', data('catalog_legacy_flat.html'))],
                                                  [('lib07.html', data('catalog_legacy_table.html'))])
        self.assertEqual([e['legacy_format'] for e in entries], ['flat', 'flat', 'table', 'table'])
        self.assertEqual(failures, [])


if __name__ == '__main__':
    unittest.main()
