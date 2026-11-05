# -*- coding: utf-8 -*-
"""tslib/catalog/records.py・selftest.py・report.py: レコードの組み立て・自己検査・レポート。"""
import os
import unittest

from tslib.catalog import regular as rg
from tslib.catalog import legacy as lg
from tslib.catalog import records as rc
from tslib.catalog import selftest as st
from tslib.catalog import report as rp
from tests.support import TempDirCase, DATA

ANNEX = 'https://annex.example.com/'


def data(name):
    with open(os.path.join(DATA, name), encoding='utf-8') as fh:
        return fh.read()


def regular_entries():
    """作り物の正規目録エントリ 2 件 (同じ source_path・別の掲載日)。"""
    src = data('catalog_regular_entry.html')
    out = []
    for i, s in enumerate((src, src.replace('2001/02/03(土)', '2001/03/04(日)')), 1):
        e = rg.parse_entry(s, 'lib1.html', i)
        e['_key'] = rc.split_href(e.pop('href'))
        out.append(e)
    return out


REGULAR_KEYS = [
    'episode_id', 'corpus', 'source_path', 'source_anchor', 'source_kind', 'source_exists',
    'source_shared_by', 'entry_type', 'title', 'author', 'homepage', 'illustrator', 'illustrator_url',
    'illustrator_raw', 'date', 'date_raw', 'weekday', 'size_kb', 'files_n', 'kansou_slug',
    'kansou_annex_url', 'arasuji', 'comment', 'osusume', 'suisen', 'takedown_notice', 'nav_links',
    'inline_links', 'genre', 'genre_raw', 'type', 'type_raw', 'keywords', 'keywords_raw', 'zokusei',
    'catalog_ref', 'orig_url', 'annex_url', 'annex_yays_url', 'provenance', '_dropped_mailto',
    '_suisen_full_empty']
LEGACY_KEYS = [
    'episode_id', 'corpus', 'source_path', 'source_anchor', 'source_kind', 'source_exists',
    'source_shared_by', 'entry_type', 'title', 'title_raw', 'author', 'homepage', 'illustrator',
    'illustrator_url', 'illustrator_raw', 'legacy_credit_raw', 'date', 'date_raw', 'date_precision',
    'weekday', 'size_kb', 'files_n', 'kansou_slug', 'kansou_annex_url', 'noteky_url', 'noteky_id',
    'arasuji', 'comment', 'osusume', 'suisen', 'notice', 'nav_links', 'inline_links', 'genre',
    'genre_raw', 'type', 'type_raw', 'keywords', 'keywords_raw', 'zokusei', 'zokusei_raw',
    'legacy_format', 'entry_role', 'commented_out', 'catalog_ref', 'orig_url', 'annex_url',
    'annex_yays_url', 'provenance', '_dropped_mailto', '_suisen_full_empty']


class RecordsTest(TempDirCase):
    def setUp(self):
        super().setUp()
        self.write('novel/a.html', '')
        self.write('~yays/library/novel/a.html', '')

    def test_shared_path_gets_date(self):
        recs = rc.regular_records(regular_entries(), self.tmp, ANNEX, {})
        self.assertEqual([r['episode_id'] for r in recs], ['novel__a.html+20010203', 'novel__a.html+20010304'])
        self.assertEqual(list(recs[0]), REGULAR_KEYS)
        r = recs[0]
        self.assertEqual((r['corpus'], r['source_exists'], r['source_shared_by']), ('honkan', True, 2))
        self.assertEqual(r['kansou_annex_url'], ANNEX + '~ts/kansou/bbs@log_taro.cgi')
        self.assertEqual(r['annex_yays_url'], ANNEX + '~yays/library/novel/a.html')
        self.assertIsNone(r['provenance'])

    def test_location_fields_order(self):
        loc = rc.location_fields(self.tmp, ANNEX, 'novel/none.html', 'p', 'local', False, {})
        self.assertEqual(list(loc), ['orig_url', 'annex_url', 'annex_yays_url', 'provenance'])
        self.assertEqual(loc['orig_url'], 'http://ts.novels.jp/novel/none.html#p')
        self.assertIsNone(loc['annex_url'])
        self.assertFalse(rc.source_exists(self.tmp, 'novel/a.html', 'external'))

    def test_strip_private(self):
        self.assertEqual(list(rc.strip_private({'a': 1, '_b': 2, 'c': 3})), ['a', 'c'])

    def test_legacy_dedup_and_suffix(self):
        regular = rc.regular_records(regular_entries()[:1], self.tmp, ANNEX, {})
        failures = []
        flat = lg.parse_legacy_flat_page('lib01.html', data('catalog_legacy_flat.html'), failures)
        table, _ = lg.parse_legacy_table_page('lib07.html', data('catalog_legacy_table.html'))
        flat[0]['href'] = 'novel/a.html'                     # 正規目録と同じパス → 重複として落ちる
        entries = flat + table
        kept, dropped = rc.dedup_against_regular(entries, regular)
        self.assertEqual([e['title'] for e in dropped], ['題名X'])
        recs = rc.legacy_records(kept, regular, self.tmp, ANNEX, {})
        self.assertEqual(list(recs[0]), LEGACY_KEYS)
        self.assertEqual([r['episode_id'] for r in recs], ['novel__y.html', 'novel__t.html', 'novel__n.html'])
        self.assertEqual(recs[1]['noteky_url'], ANNEX + '~ezpe/cgi-bin/noteky/noteky@c_noteread_f_3_id_abc_ff_on.cgi')
        stats = rc.legacy_stats(entries, [], dropped, recs)
        self.assertEqual(list(stats), [
            'blocks_parsed', 'parse_failures', 'parse_failure_detail', 'dropped_as_honkan_duplicate',
            'added', 'added_by_format', 'added_commented_out', 'added_source_present',
            'added_entry_roles', 'added_authors_unresolved', 'added_entry_types',
            'added_year_histogram', 'date_precision', 'noteky_links', 'zokusei_tokens_distinct'])
        self.assertEqual((stats['blocks_parsed'], stats['dropped_as_honkan_duplicate'], stats['added']), (4, 1, 3))

    def test_legacy_id_collision_gets_tilde(self):
        regular = [{'episode_id': 'novel__y.html', 'source_path': 'other.html'}]
        failures = []
        flat = lg.parse_legacy_flat_page('lib01.html', data('catalog_legacy_flat.html'), failures)
        kept, _ = rc.dedup_against_regular(flat[1:], regular)
        recs = rc.legacy_records(kept, regular, self.tmp, ANNEX, {})
        self.assertEqual(recs[0]['episode_id'], 'novel__y.html~2')


SELFTEST_NAMES = [
    '正規目録エントリ数 == 2887', 'パース失敗 0', 'episode_id の重複 0',
    'mailto から抽出したアドレスの残存 0 (1 件を検査)', "出力に 'mailto:' が現れない",
    '出力にアドレス形の文字列が無い', 'homepage 件数 == 918 (=771+147)', '画師あり == 477',
    'オススメあり == 765', '特殊エントリ (画像/外部/anchor) == 28', 'あらすじ空 == 182', 'コメント空 == 290',
    '推薦文空 == 147', 'ジャンル空 == 238', '種別空 == 326', 'キーワード空 == 437',
    '感想板 slug の異なり == 305', 'genre_raw の異なり == 244 (NFKC 前)', 'type_raw の異なり == 187 (NFKC 前)',
    'keywords_raw の異なり == 1083 (NFKC 前)'] + [
    '必須欄 %s に欠落なし (honkan)' % f for f in st.REQUIRED_REGULAR] + [
    '旧目録ブロック数 == 336 (lib01-06 の 252 + lib07-09 の 84)', '旧目録のパース失敗 0',
    '旧目録の追加件数 == blocks - 正規目録との重複'] + [
    '必須欄 %s に欠落なし (legacy)' % f for f in st.REQUIRED_LEGACY] + [
    'legacy(work) の author 解決率 100%', '公開停止の告知を検出 == 9 件', '停止告知のある話が denylist に載っている']


class SelftestReportTest(TempDirCase):
    def records(self):
        self.write('novel/a.html', '')
        return rc.regular_records(regular_entries(), self.tmp, ANNEX, {})

    def test_selftest_names_in_order(self):
        recs = self.records()
        stats = rc.legacy_stats([], [], [], [])
        ok, results = st.selftest(recs, [], stats, '', None)
        self.assertFalse(ok)                                  # 実データの件数には届かない
        self.assertEqual([n for n, _, _ in results], SELFTEST_NAMES)
        by = {n: o for n, o, _ in results}
        self.assertTrue(by['episode_id の重複 0'])
        self.assertTrue(by["出力に 'mailto:' が現れない"])

    def test_work_listed(self):
        rec = {'episode_id': 'e1'}
        self.assertFalse(st.work_listed(rec, None, 'w1'))
        self.assertTrue(st.work_listed(rec, [{'work_slug': 'w1', 'episodes': [{'episode_id': 'e1'}]}], 'id: w1'))
        self.assertTrue(st.work_listed(rec, [{'work_slug': 'w1', 'episodes': ['e1']}], 'id: w1'))
        self.assertFalse(st.work_listed(rec, [{'work_slug': 'w1', 'episodes': ['e2']}], 'id: w1'))

    def test_takedown_notice_unlisted(self):
        recs = [{'episode_id': 'e1', 'source_path': 'novel/a.html', 'catalog_ref': 'lib1.html#1',
                 'takedown_notice': {'author': 'x'}},
                {'episode_id': 'e2', 'source_path': 'novel/b.html', 'catalog_ref': 'lib1.html#2',
                 'takedown_notice': {'author': 'y'}}]
        from tslib.checks import Checks
        c = Checks()
        st.check_takedown_notices(c, recs, 'value: novel/a.html\n', None)
        self.assertEqual(c.results[1], ('停止告知のある話が denylist に載っている', False, '未掲載 1 件 [\'lib1.html#2\']'))

    def test_report_keys_and_summary(self):
        recs = self.records()
        ok, results = st.selftest(recs, [], None, '', None)
        rep = rp.report_of(recs, [], results, None)
        self.assertEqual(list(rep)[:3], ['generated_by', 'corpus', 'entries_total'])
        self.assertEqual(list(rep)[-3:], ['legacy', 'selftest', 'selftest_ok'])
        self.assertEqual(len(rep), 47)
        self.assertEqual(rep['corpus'], 'honkan (lib1-73)')
        lines = rp.summary_lines(rep)
        self.assertEqual(lines[:2], ['entries total=2 (honkan=2 legacy=0)', 'entries=2 parse_failures=0 unique_ids=2'])
        self.assertEqual(lines[6], '--- selftest')
        self.assertTrue(lines[7].startswith('  [NG ] 正規目録エントリ数 == 2887'))


if __name__ == '__main__':
    unittest.main()
