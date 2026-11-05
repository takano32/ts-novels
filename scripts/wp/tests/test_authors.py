# -*- coding: utf-8 -*-
"""tslib/authors.py・kana_index.py: 作者の同定・併合・五十音目録。"""
import unittest

from tslib import authors as au
from tslib import kana_index as ki
from tests.support import TempDirCase

BOARD_STATS = {'board_list_rows': 0, 'board_list_shared_world': 0, 'board_list_personal': 0,
               'board_list_with_author_name': 0, 'board_files_not_listed': 0}


def rec(eid, author, board=None, corpus='honkan', role=None, date='2001-01-01', hp=None):
    return {'episode_id': eid, 'author': author, 'kansou_slug': board, 'corpus': corpus,
            'entry_role': role, 'date': date, 'homepage': hp}


class KanaIndexTest(unittest.TestCase):
    def test_norm_name(self):
        self.assertEqual(ki.norm_name('ＭＯＮＤＯ'), ki.norm_name('mondo'))
        self.assertEqual(ki.norm_name('根室　眞琴'), '根室眞琴')
        self.assertEqual(ki.norm_name(None), '')

    def test_old_html_rowspan(self):
        src = ('<tr><td rowspan=2><b>山田</b></td><td><a href="a.html">A</a></td></tr>'
               '<tr><td><a href="b.html">B</a></td></tr><tr><td><a href="c.html">C</a></td></tr>')
        rows = ki.parse_index_old_html('lib-index-1.html', src)
        self.assertEqual([(r['path'], r['author']) for r in rows], [('a.html', '山田'), ('b.html', '山田')])
        self.assertEqual(list(rows[0]), ['page', 'path', 'title', 'author', 'homepage', 'kana'])

    def test_html_yomi(self):
        yomi = {}
        src = ('<H2>- あ行 -</H2><TR><TD><a href="x.html">X題</a></TD><TD><A HREF="mailto:a@example.com">'
               '阿部</A> <A HREF="http://h.example.com/">(homepage)</A></TD></TR>')
        rows = ki.parse_index_html('lib-index-aa.html', src, yomi)
        self.assertEqual([(r['title'], r['author'], r['homepage'], r['kana']) for r in rows],
                         [('X題', '阿部', 'http://h.example.com/', 'あ行')])
        self.assertEqual(yomi, {'阿部': 'あ行'})



class ParseIndexTest(TempDirCase):
    def test_parse_index_reads_pages(self):
        self.write('lib-index-ka.html', '<H2>か行</H2><TR><TD><a href="k.html">K</a></TD><TD>加藤</TD></TR>')
        index = ki.parse_index(self.tmp)
        rows, yomi = index                                  # タプルとしても展開できる
        self.assertEqual([r['page'] for r in rows], ['lib-index-ka.html'])
        self.assertEqual(yomi, {'加藤': 'か行'})
        self.assertIs(index.rows, rows)
        self.assertIs(index.yomi_of, yomi)


class BoardsTest(unittest.TestCase):
    def test_board_names_of(self):
        self.assertEqual(au.board_names_of('ぽぽさん(HIKUさん)作品感想掲示板'), ['ぽぽ', 'HIKU'])
        self.assertEqual(au.board_names_of('城弾さん　感想BBS'), ['城弾'])
        self.assertEqual(au.board_names_of('『華代ちゃんシリーズ』感想掲示板'), [])
        self.assertEqual(au.board_names_of('感想掲示板一覧'), [])
        self.assertEqual(au.board_names_of(''), [])

    def test_board_list_first(self):
        listed, rows = au.parse_board_list(
            '<TR><TD><A HREF="bbs@log_johdan.cgi">城弾さん作品感想掲示板</A>'
            '<TR><TD><A HREF="bbs@log_kayo_chan.cgi">華代</A>', {'kayo_chan'})
        self.assertEqual((dict(listed), rows), ({'johdan': '城弾さん作品感想掲示板'}, 2))
        boards = au.boards_from_titles(listed, {'hiku': 'HIKUさん感想掲示板', 'johdan2': '城弾さん感想掲示板'}, rows)
        names, stats = boards
        self.assertEqual((boards.by_name, boards.stats), (names, stats))
        self.assertEqual(dict(names), {'城弾': ['johdan', 'johdan2'], 'hiku': ['hiku']})
        self.assertEqual(stats['board_files_not_listed_slugs'], ['hiku', 'johdan2'])
        self.assertEqual(stats['board_list_shared_world'], 1)

    def test_shared_boards_of_terms(self):
        terms = {'taxonomies': {'ts_world': {'terms': [{'rule': {'boards': ['a', 'b']}}, {'rule': {}}]}}}
        self.assertEqual(au.shared_boards_of_terms(terms), {'a', 'b'})

    def test_confirmed_of(self):
        prev = {'Ａ': {'slug': 'a', 'status': 'confirmed'}, 'B': {'slug': 'b', 'status': 'candidate'},
                'S': {'slug': 's', 'status': 'confirmed', 'role': 'not-an-author'}}
        confirmed, not_authors = au.confirmed_of(prev)
        self.assertEqual(sorted(confirmed), ['a', 's'])
        self.assertEqual(not_authors, {'s'})


class AssignTest(unittest.TestCase):
    def test_six_routes(self):
        records = [rec('e1', '城弾', 'johdan'), rec('e2', '城弾'), rec('e3', 'ぽぽ'), rec('e4', '新人'),
                   rec('e5', 'シェアワールド'), rec('e6', None, role='notice'), rec('e7', None)]
        name_boards = au.boards_by_name(records, set())
        got = au.assign_episodes(records, set(), {'シェアワールド'}, name_boards, {'ぽぽ': ['popo']}, {})
        self.assertEqual(got['assign'], {'e1': ('johdan', 'board'), 'e2': ('johdan', 'name->board'),
                                         'e3': ('popo', 'name->board-list'), 'e4': ('name:新人', 'name-only')})
        self.assertEqual((got['not_an_author_eps'], got['notices'], got['unresolved']), (['e5'], ['e6'], ['e7']))
        self.assertEqual(got['from_board_list'], {'ぽぽ': 'popo'})

    def test_confirmed_beats_board_list(self):
        records = [rec('e1', '根室')]
        got = au.assign_episodes(records, set(), set(), {}, {'根室': ['memuro']}, {'根室': {'slug': 'slents'}})
        self.assertEqual(got['assign'], {'e1': ('name:根室', 'name-only')})

    def test_assign_author_slugs_reserves_confirmed(self):
        a1 = {'key': 'name:x', 'board_slug': None, 'display_variants': au.collections.Counter({'ABC': 1})}
        a2 = {'key': 'name:y', 'board_slug': None, 'display_variants': au.collections.Counter({'Y氏': 1})}
        au.assign_author_slugs([a1, a2], {'y氏': {'slug': 'abc', 'source': 'manual'}})
        self.assertEqual((a1['slug'], a1['slug_status']), ('abc-2', 'auto'))
        self.assertEqual((a2['slug'], a2['slug_status'], a2['slug_source']), ('abc', 'confirmed', 'manual'))


class AssembleTest(unittest.TestCase):
    RECORDS = [rec('e1', '城弾', 'johdan', hp='http://j.example.com/'), rec('e2', '城弾', date='2000-05-05', corpus='legacy'),
               rec('e3', 'ぽぽ'), rec('e4', 'HIKU'), rec('e5', 'シェアワールド'), rec('e6', None, role='notice'),
               rec('e7', 'ABC Writer'), rec('e8', '麗香'), rec('e9', 'Ｍａｒｉｅ')]
    OVERRIDES = {'authors': {
        'ぽぽ': {'slug': 'popo', 'status': 'confirmed', 'source': 'kansou-board'},
        'HIKU': {'slug': 'popo', 'status': 'confirmed'},
        '麗香': {'slug': 'marie', 'status': 'confirmed', 'source': 'manual'},
        'Ｍａｒｉｅ': {'slug': 'marie', 'status': 'confirmed', 'source': 'manual'},
        'シェアワールド': {'slug': 'share', 'status': 'confirmed', 'role': 'not-an-author'}}}

    def build(self):
        return au.assemble(self.RECORDS, {'kayo_chan'}, ki.KanaIndex([], {'城弾': 'さ行'}),
                           au.Boards({'ぽぽ': ['popo'], 'hiku': ['hiku', 'popo']}, BOARD_STATS),
                           self.OVERRIDES, annex_base='https://a.example/')

    def test_payload(self):
        payload, report, overrides = self.build()
        self.assertEqual(list(payload), ['generated_by', 'annex_base', 'authors'])
        got = {a['slug']: (a['display_variants'], a['episodes']) for a in payload['authors']}
        self.assertEqual(got['johdan'], (['城弾'], ['e1', 'e2']))
        self.assertEqual(got['popo'], (['ぽぽ', 'HIKU'], ['e3', 'e4']))
        self.assertEqual(sorted(got['marie'][1]), ['e8', 'e9'])
        j = payload['authors'][0]
        self.assertEqual(list(j), [
            'slug', 'display_name', 'display_variants', 'yomi_group', 'kansou_slug', 'kansou_annex_url',
            'kansou_slug_alt', 'shared_boards', 'homepage', 'homepage_wayback', 'homepage_all', 'active_links',
            'contact_status', 'episode_count', 'corpora', 'first_date', 'last_date', 'identified_by',
            'slug_source', 'slug_status', 'episodes'])
        self.assertEqual((j['yomi_group'], j['first_date'], j['homepage_wayback']),
                         ('さ行', '2000-05-05', 'https://web.archive.org/web/2010/http://j.example.com/'))
        self.assertEqual(report['author_merges'], [{'slug': 'marie', 'kept': 'Ｍａｒｉｅ', 'merged': ['麗香'],
                                                    'episodes_added': 1}])
        self.assertEqual(report['episodes_not_an_author'], ['e5'])
        # 裁定済みの表示名と作者を作らない名前は overrides に残る
        self.assertTrue({'ぽぽ', 'HIKU', '麗香', 'Ｍａｒｉｅ', 'シェアワールド'} <= set(overrides['authors']))

    def test_selftest_and_summary(self):
        payload, report, _ = self.build()
        c = au.selftest(payload, report)
        self.assertTrue(c.ok, [r for r in c.results if not r[1]])
        self.assertEqual(len(c.results), 10)
        lines = au.summary_lines(report)
        self.assertEqual(lines[0], 'authors=4 (板あり 2 / 板なし 2) 表示名の異なり=7 表記揺れ統合 2 組')
        self.assertIn('  marie ← Ｍａｒｉｅ / 麗香', lines)


if __name__ == '__main__':
    unittest.main()
