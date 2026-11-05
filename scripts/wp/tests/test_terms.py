# -*- coding: utf-8 -*-
"""tslib/terms.py: 分類語彙の正規化・集計・slug・組み立て。"""
import os
import unittest

from tslib import terms
from tests.support import TempDirCase, HAS_YAML


def rec(eid, corpus='honkan', genre=(), typ=(), kw=(), zok=(), title='題', path='novel/x.html', board=None):
    return {'episode_id': eid, 'corpus': corpus, 'genre_raw': list(genre), 'type_raw': list(typ),
            'keywords_raw': list(kw), 'zokusei_raw': list(zok), 'title': title,
            'source_path': path, 'kansou_slug': board}


RECORDS = [
    rec('e1', genre=['学園', 'ＳＦ'], kw=['オレっ娘'], board='kayo_chan'),
    rec('e2', genre=['学園?'], typ=['薬'], kw=['俺っ娘'], path='novel/rental/a.html'),
    rec('e3', corpus='legacy', genre=['「ＳＦ」'], zok=['入れ替わり'], title='FMS 第1話'),
    rec('e4', corpus='uncatalogued', genre=['学園物'], typ=['薬品']),
    rec('e5', corpus='extern-repost', kw=['ボクっ子', '僕っ娘']),
]


class NormalizeTest(unittest.TestCase):
    def test_normalize_token(self):
        self.assertEqual(terms.normalize_token('「ＳＦ」'), 'SF')
        self.assertEqual(terms.normalize_token('学園？'), '学園')
        self.assertEqual(terms.normalize_token('学園（？）'), '学園')
        self.assertEqual(terms.normalize_token(' 『魔法』 '), '魔法')

    def test_variant_key(self):
        self.assertEqual(terms.variant_key('オレっ娘'), terms.variant_key('おれっ娘'))
        self.assertEqual(terms.variant_key('ハンター・シリーズ'), terms.variant_key('ハンターシリーズ'))
        self.assertNotEqual(terms.variant_key('学園'), terms.variant_key('魔法'))

    def test_parse_vocab_html(self):
        src = ('<H3>【基本】</H3><TABLE><TR><TD><B>学園</B></TD><TD>学校が <i>舞台</i></TD></TR>'
               '<H3>【その他】</H3><TR><TD><B>ＳＦ？</B></TD><TD></TD></TR>'
               '<TR><TD><B>%s</B></TD><TD>長すぎ</TD></TR></TABLE>' % ('長' * 25))
        self.assertEqual(terms.parse_vocab_html(src), {
            '学園': {'description': '学校が 舞台', 'section': '基本'},
            'SF': {'description': None, 'section': 'その他'}})


class CollectTest(unittest.TestCase):
    def test_collect_and_synonyms(self):
        agg = terms.collect(RECORDS, 'genre_raw', {'学園物': '学園'})
        self.assertEqual(list(agg), ['学園', 'SF'])
        self.assertEqual(agg['学園']['count'], 3)
        self.assertEqual(agg['学園']['lookup'], {'学園', '学園?', '学園物'})
        self.assertEqual(agg['SF']['raw_variants'], {'ＳＦ': 1, '「ＳＦ」': 1})
        self.assertEqual(agg['SF']['episodes'], ['e1', 'e3'])

    def test_merge_zokusei(self):
        agg = terms.collect(RECORDS, 'keywords_raw', {})
        terms.merge_zokusei(agg, terms.collect(RECORDS, 'zokusei_raw', {}))
        self.assertTrue(agg['入れ替わり']['from_zokusei'])
        self.assertNotIn('from_zokusei', agg['オレっ娘'])

    def test_ambiguous_lookups(self):
        agg = {'A': {'lookup': {'x', 'y'}}, 'B': {'lookup': {'y'}}}
        self.assertEqual(terms.ambiguous_lookups(agg, ['A', 'B']), {'y'})

    def test_variant_candidates(self):
        agg = terms.collect([rec('a', kw=['オレっ娘', 'オレっ娘', 'おれっ娘', '学園'])], 'keywords_raw', {})
        self.assertEqual(terms.variant_candidates(agg), [[('オレっ娘', 2), ('おれっ娘', 1)]])

    def test_build_worlds(self):
        world_map = {'kayo_chan': {'name': '華代', 'boards': ['kayo_chan'], 'path_prefixes': ['novel/kayo_chan/']},
                     'rental': {'name': 'R', 'boards': [], 'path_prefixes': ['novel/rental/']},
                     'fms': {'name': 'FMS', 'boards': [], 'path_prefixes': [], 'title_patterns': ['^FMS']}}
        w = terms.build_worlds(RECORDS, world_map)
        self.assertEqual({k: (v['episodes'], v['match_reasons']) for k, v in w.items()}, {
            'kayo_chan': (['e1'], {'board': 1}), 'rental': (['e2'], {'path': 1}), 'fms': (['e3'], {'title': 1})})
        self.assertEqual(list(w['fms']), ['name', 'origin_author', 'note', 'rule', 'count', 'match_reasons', 'episodes'])
        self.assertEqual(terms.world_index(w), {'e1': ['kayo_chan'], 'e2': ['rental'], 'e3': ['fms']})


class SlugAndAssembleTest(unittest.TestCase):
    def test_assign_slugs_confirmed_first(self):
        overrides = {'terms_genre': {'学園': {'slug': 'sf', 'status': 'confirmed'}}}
        result, entries = terms.assign_slugs('ts_genre', ['SF', '学園'], overrides, 'terms_genre')
        # confirmed の sf を先に押さえるので、SF (ascii) は sf-2 になる
        self.assertEqual(result, {'SF': 'sf-2', '学園': 'sf'})
        self.assertEqual(entries['SF'], {'slug': 'sf-2', 'status': 'auto', 'source': 'ascii'})
        result, _ = terms.assign_slugs('ts_genre', ['X'], {}, 'terms_genre', board_slugs={'X': 'xb'})
        self.assertEqual(result, {'X': 'xb'})

    def test_build_taxonomy_order_and_keys(self):
        meta = terms.TAXONOMY_META['ts_genre']
        tax, rep, ov, amb = terms.build_taxonomy('ts_genre', meta, RECORDS, {'学園物': '学園'}, {}, {}, 2)
        self.assertEqual([t['name'] for t in tax['terms']], ['学園', 'SF'])       # (-count, name)
        self.assertEqual(list(tax['terms'][0]), ['slug', 'name', 'count', 'core', 'tier', 'description',
                                                 'raw_variants', 'lookup_variants', 'from_zokusei'])
        self.assertEqual(tax['terms'][0]['lookup_variants'], [])
        self.assertTrue(tax['terms'][0]['core'])
        self.assertEqual(list(rep), ['raw_tokens_distinct', 'terms', 'core_terms', 'core_min_count',
                                     'core_coverage_pct', 'tokens_total', 'terms_with_description',
                                     'vocab_page', 'vocab_page_terms', 'vocab_page_matched',
                                     'merged_by_map', 'variant_candidates'])
        self.assertIn('terms_genre', ov)
        self.assertEqual(amb, set())

    def test_selftest(self):
        ok, results = terms.selftest(RECORDS, terms.CORPUS_TERMS)
        self.assertTrue(ok)
        self.assertEqual(len(results), 3)
        ok, results = terms.selftest(RECORDS + [rec('e9', corpus='mystery')], terms.CORPUS_TERMS)
        self.assertFalse(ok)
        self.assertIn("['mystery']", results[0][2])
        # dojo は予定枠なので 0 件でも空 term に数えない
        self.assertEqual(results[1][2], '空 term なし')

    def test_assemble(self):
        synonyms = {'ts_genre': {'学園物': '学園'}, 'ts_type': {'薬': '薬品'}, 'ts_keyword': {}}
        notes = []
        tj, rep, ov = terms.assemble(RECORDS, synonyms, {}, {'x': {'name': 'X', 'boards': ['kayo_chan']}},
                                     {}, notes=notes)
        self.assertEqual(list(tj), ['generated_by', 'episodes', 'taxonomies', 'episode_worlds'])
        self.assertEqual(list(tj['taxonomies']), ['ts_genre', 'ts_type', 'ts_keyword', 'ts_world', 'ts_corpus'])
        self.assertEqual(list(rep), [
            'generated_by', 'episodes', 'taxonomies', 'worlds', 'worlds_total', 'episodes_with_world',
            'corpus_counts', 'corpus_terms', 'slug_pending_review', 'slug_pending_review_all_sections',
            'pykakasi_available', 'selftest', 'selftest_ok'])
        self.assertTrue(rep['selftest_ok'])
        self.assertEqual(tj['episode_worlds'], {'e1': ['x']})
        self.assertEqual(tj['taxonomies']['ts_type']['terms'][0]['count'], 2)
        lines = terms.summary_lines(rep)
        self.assertEqual(len(lines), 6)
        self.assertTrue(lines[3].startswith('ts_world    worlds=1  episodes_with_world=1'))


@unittest.skipUnless(HAS_YAML, 'PyYAML が無い')
class MapFileTest(TempDirCase):
    def test_load_map_writes_default_once(self):
        path = os.path.join(self.tmp, 'catalog', 'genre_map.yml')
        syn, data = terms.load_map(path, {'a': 'b'})
        self.assertEqual((syn, data), ({'a': 'b'}, {'synonyms': {'a': 'b'}}))
        with open(path, encoding='utf-8') as fh:
            self.assertTrue(fh.read().startswith(terms.MAP_COMMENT))
        with open(path, 'w', encoding='utf-8') as fh:
            fh.write('synonyms:\n  c: d\n')
        self.assertEqual(terms.load_map(path, {'a': 'b'})[0], {'c': 'd'})

    def test_load_world_map(self):
        cat = os.path.join(self.tmp, 'catalog')
        os.makedirs(cat)
        self.assertEqual(terms.load_world_map(cat), terms.DEFAULT_WORLD_MAP)
        with open(os.path.join(cat, 'world_map.yml'), encoding='utf-8') as fh:
            self.assertTrue(fh.read().startswith(terms.WORLD_MAP_COMMENT))
        self.assertEqual(terms.load_world_map(cat), terms.DEFAULT_WORLD_MAP)
        self.assertEqual(set(terms.load_synonym_maps(cat)), {'ts_genre', 'ts_type', 'ts_keyword'})


if __name__ == '__main__':
    unittest.main()
