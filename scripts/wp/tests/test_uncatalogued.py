# -*- coding: utf-8 -*-
"""tslib/uncatalogued.py: 目録に載らない novel/ 配下の本文の拾い方。"""
import collections
import unittest

from tslib import uncatalogued as uc
from tests.support import TempDirCase

RECORD_KEYS = [
    'episode_id', 'corpus', 'source_path', 'source_anchor', 'source_kind', 'source_exists',
    'source_shared_by', 'entry_type', 'title', 'author', 'homepage', 'illustrator', 'illustrator_url',
    'date', 'date_raw', 'date_precision', 'weekday', 'size_kb', 'files_n', 'kansou_slug',
    'kansou_annex_url', 'arasuji', 'comment', 'suisen', 'osusume', 'nav_links', 'inline_links', 'genre',
    'genre_raw', 'type', 'type_raw', 'keywords', 'keywords_raw', 'zokusei', 'entry_role',
    'metadata_source', 'text_chars', 'catalog_ref', 'orig_url', 'annex_url', 'annex_yays_url', 'provenance']


class TextTest(unittest.TestCase):
    def test_text_and_lines(self):
        src = '<p>あ<script>x()</script>い&amp;う</p><br>作：山田　'
        self.assertEqual(uc.text_of_html(src), 'あ い&う 作：山田')
        self.assertEqual(uc.lines_of_html('<p>作：山田</p>　x'), '\n作：山田\nx')

    def test_clean_title(self):
        self.assertEqual(uc.clean_title('■少年少女文庫・<b>題名</b>'), '題名')
        self.assertEqual(uc.clean_title('題名 - 少年少女文庫'), '題名')
        self.assertIsNone(uc.clean_title('無題ドキュメント'))
        self.assertIsNone(uc.clean_title('少年少女文庫'))
        self.assertIsNone(uc.clean_title(None))


class ExclusionTest(unittest.TestCase):
    def test_order(self):
        cat = {'d1': 'novel/a.html'}
        # duplicate → cgi-view → site-template → title-page → empty
        self.assertEqual(uc.exclusion_reason('novel/x@y.html', 0, False, 'd1', cat),
                         ('duplicate-of-catalogued', {'alias_of_path': 'novel/a.html'}))
        self.assertEqual(uc.exclusion_reason('novel/index@1.html', 0, False, None, cat), ('cgi-view', {}))
        self.assertEqual(uc.exclusion_reason('novel/library.html', 0, False, None, cat), ('site-template', {}))
        self.assertEqual(uc.exclusion_reason('novel/d/index.html', 0, False, None, cat), ('title-page', {}))
        self.assertEqual(uc.exclusion_reason('novel/d/a.html', 0, False, None, cat), ('empty', {}))
        self.assertIsNone(uc.exclusion_reason('novel/d/a.html', 0, True, None, cat))
        self.assertIsNone(uc.exclusion_reason('novel/d/a.html', 5, False, 'zz', cat))

    def test_dir_author_of(self):
        da = collections.defaultdict(collections.Counter)
        da['novel/200703/16205836']['山田'] += 2
        da['novel/200801/1']['A'] += 2
        da['novel/200801/1']['B'] += 1
        self.assertEqual(uc.dir_author_of('novel/200703/16205836/saila/x.html', da), ('山田', 'dir-author'))
        self.assertEqual(uc.dir_author_of('novel/200801/1/y.html', da), ('A', 'dir-author-majority'))
        self.assertEqual(uc.dir_author_of('novel/x/y.html', da), (None, None))

    def test_resolve_metadata_order(self):
        da = {'novel/d': collections.Counter({'D': 1})}
        row = {'title': '目録題', 'author': None}
        self.assertEqual(uc.resolve_metadata('novel/d/a.html', '<p>作：本文作者</p><title>T</title>', row, da),
                         ('目録題', '本文作者', ['lib-index', 'body-credit']))
        self.assertEqual(uc.resolve_metadata('novel/d/a.html', '<title>題 - 少年少女文庫</title>', None, da),
                         ('題', 'D', ['body-title', 'dir-author']))
        self.assertEqual(uc.resolve_metadata('novel/z/b.htm', '', None, da), ('b', None, ['filename']))

    def test_date_of_path(self):
        self.assertEqual(uc.date_of_path('novel/200703/x.html'), ('2007-03-01', 'directory-batch'))
        self.assertEqual(uc.date_of_path('novel/short/x.html'), (None, 'unknown'))

    def test_index_by_path(self):
        rows = [{'path': './novel/a.html#p'}, {'path': 'novel/a.html', 'x': 2}, {'path': None}]
        self.assertEqual(uc.index_by_path(rows), {'novel/a.html': rows[0]})


class BuildTest(TempDirCase):
    def test_build_without_provenance(self):
        self.write('novel/200101/1/a.html', '<title>Aの話</title><p>作：山田</p><p>本文です</p>')
        self.write('novel/200101/1/b.html', '<p>本文その2</p>')
        self.write('novel/200101/1/dup.html', 'カタログ済みと同じ')
        self.write('novel/200101/1/index.html', '目次')
        self.write('novel/200101/1/empty.html', '<p> </p>')
        self.write('novel/200101/1/pic.html', '<img src="p.gif">')
        self.write('novel/cat/c.html', 'カタログ済みと同じ')
        self.write('novel/200101/1/note.txt', 'テキストの本文')
        self.write('~yays/library/novel/200101/1/b.html', 'old')
        records = [{'episode_id': 'c', 'corpus': 'honkan', 'source_path': 'novel/cat/c.html',
                    'source_kind': 'local', 'author': '猫'},
                   {'episode_id': 'old', 'corpus': 'uncatalogued', 'source_path': 'novel/old.html',
                    'source_kind': 'local'}]
        keep, added, excluded, report = uc.build(self.tmp, records, 'https://annex.example.com/', use_provenance=False)
        self.assertEqual([r['episode_id'] for r in keep], ['c'])
        self.assertEqual([r['source_path'] for r in added],
                         ['novel/200101/1/a.html', 'novel/200101/1/b.html', 'novel/200101/1/note.txt',
                          'novel/200101/1/pic.html'])
        self.assertEqual(list(added[0]), RECORD_KEYS)
        a, b, note, pic = added
        self.assertEqual((a['title'], a['author'], a['metadata_source'], a['entry_role']),
                         ('Aの話', '山田', ['body-credit', 'body-title'], 'work'))
        self.assertEqual((b['title'], b['author'], b['entry_role']), ('b', None, 'unattributed'))
        self.assertEqual(b['annex_yays_url'], 'https://annex.example.com/~yays/library/novel/200101/1/b.html')
        self.assertEqual((pic['entry_type'], note['text_chars']), ('image', 7))
        self.assertEqual({x['source_path']: x['reason'] for x in excluded}, {
            'novel/200101/1/dup.html': 'duplicate-of-catalogued', 'novel/200101/1/empty.html': 'empty',
            'novel/200101/1/index.html': 'title-page'})
        self.assertEqual(report['excluded_by_reason'], {'duplicate-of-catalogued': 1, 'title-page': 1, 'empty': 1})
        self.assertEqual((report['novel_body_files'], report['already_catalogued'], report['added']), (8, 1, 4))
        self.assertEqual(list(report), [
            'generated_by', 'novel_body_files', 'already_catalogued', 'added', 'excluded', 'excluded_by_reason',
            'metadata_sources', 'title_resolved', 'author_resolved', 'author_unresolved',
            'author_unresolved_count', 'date_precision', 'text_chars_buckets', 'provenance_covered',
            'index_rows_parsed'])


if __name__ == '__main__':
    unittest.main()
