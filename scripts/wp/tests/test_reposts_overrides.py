# -*- coding: utf-8 -*-
"""tslib/reposts.py・overrides.py: 作者本人の再掲と、話 1 件単位の人手上書き。"""
import json
import unittest

from tslib import reposts as rp
from tslib import overrides as ov
from tests.support import TempDirCase

NEW_KEYS = [
    'episode_id', 'corpus', 'source_path', 'source_anchor', 'source_kind', 'source_exists',
    'source_shared_by', 'entry_type', 'title', 'author', 'homepage', 'illustrator', 'illustrator_url',
    'date', 'date_raw', 'date_precision', 'weekday', 'size_kb', 'files_n', 'kansou_slug',
    'kansou_annex_url', 'arasuji', 'comment', 'suisen', 'osusume', 'nav_links', 'inline_links', 'genre',
    'genre_raw', 'type', 'type_raw', 'keywords', 'keywords_raw', 'zokusei', 'entry_role', 'catalog_ref',
    'orig_url', 'annex_url', 'annex_yays_url']
META = {'id': '272830', 'userId': '9', 'userName': '作者', 'title': '番外編',
        'createDate': '2010-05-05T12:00:00+09:00', 'characterCount': 1234}


class RepostsTest(TempDirCase):
    def test_split_holding_text(self):
        body, header = rp.split_holding_text('# title: x\n# id: 1\n\n本文\n二行目\n\n')
        self.assertEqual((body, header), ('本文\n二行目\n', ['# title: x', '# id: 1']))

    def test_new_record_keys(self):
        rec = rp.new_record('e', 'p', 't', 'a', 'h', '2010-05-05T12:00', 3, 'u')
        self.assertEqual(list(rec), NEW_KEYS)
        self.assertEqual((rec['date'], rec['corpus'], rec['source_kind']), ('2010-05-05', 'extern-repost', 'repost'))

    def test_pixiv(self):
        rec = rp.pixiv_record(META, '本文', rp.ITEMS[1], '2026-08-30')
        self.assertEqual((rec['episode_id'], rec['source_path'], rec['published_in_bunko']),
                         ('repost__pixiv__272830', 'repost/pixiv/272830', False))
        self.assertEqual(list(rec['provenance']), [
            'method', 'route', 'route_label', 'pixiv_novel_id', 'pixiv_user_id', 'pixiv_user_name',
            'source_url', 'published_at', 'acquired_at', 'character_count'])
        target = {'episode_id': 'novel__a.html'}
        self.assertEqual(rp.attach_pixiv(target, META, rp.ITEMS[0], '2026-08-30'), 'novel__a.html')
        self.assertEqual((target['body_source_path'], target['published_in_bunko']),
                         ('reposts/novel__a.html.txt', True))

    def bcwiki(self, name, attach):
        self.write('reposts/' + name, 'x' * 3000)
        self.write('reposts/' + rp.BCWIKI_PROVENANCE, json.dumps({
            'wiki_page': 'W', 'wiki_page_url': 'https://w.example.com/', 'acquired_at': '2026-09-01',
            'items': [{'file': name, 'attachment_name': attach, 'url': 'https://w.example.com/a',
                       'uploaded_at': '2008-01-02 03:04', 'md5': 'm', 'bytes': 1, 'encoding_raw': 'cp932',
                       'author': 'きりか'}]}))

    def test_bcwiki_txt_and_htm(self):
        self.bcwiki('repost__bcwiki__ama.txt', 'ama.txt')
        item = rp.ITEMS[2]
        rec = rp.bcwiki_record(item, rp.load_bcwiki(self.tmp, item))
        self.assertEqual((rec['title'], rec['size_kb'], rec['body_format'], rec['body_convert_exempt']),
                         ('ama', 3, 'plain-text', True))
        self.assertEqual(rec['date'], '2008-01-02')
        self.bcwiki('repost__bcwiki__saidai_no_higaisha.htm', 's.htm')
        item = rp.ITEMS[3]
        rec = rp.bcwiki_record(item, rp.load_bcwiki(self.tmp, item))
        self.assertEqual((rec['title'], rec['entry_type'], 'body_format' in rec), ('最大の被害者、あるいは', 'html', False))

    def test_build_with_missing_inputs(self):
        records = [{'episode_id': 'x', 'corpus': 'extern-repost', 'source_path': 'old'},
                   {'episode_id': 'a', 'corpus': 'honkan', 'source_path': 'novel/a.html'}]
        out, bodies, report = rp.build(self.tmp, records, self.tmp + '/holding', '2026-08-30')
        self.assertEqual([r['episode_id'] for r in out], ['a'])
        self.assertEqual(bodies, {})
        self.assertEqual(report['missing_inputs'], [
            'amaonna_pixiv351985.txt', 'kirainamono_GW_pixiv272830.txt', 'repost__bcwiki__ama.txt',
            'repost__bcwiki__saidai_no_higaisha.htm', 'repost__bcwiki__bangai3ts.txt'])
        self.assertEqual(list(report), ['generated_by', 'holding_dir', 'items_declared', 'patched_existing',
                                        'added_extern_repost', 'missing_inputs', 'bodies_written', 'note'])


class EpisodeOverridesTest(TempDirCase):
    def records(self):
        return [{'episode_id': 'a', 'source_path': 'novel/a.html', 'source_anchor': None, 'author': '誤植',
                 'kansou_slug': None},
                {'episode_id': 'b', 'source_path': 'novel/b.html', 'source_anchor': 'p2', 'author': 'B'},
                {'episode_id': 'c1', 'source_path': 'novel/c.html', 'source_anchor': None},
                {'episode_id': 'c2', 'source_path': 'novel/c.html', 'source_anchor': None}]

    def test_key_of(self):
        self.assertEqual(ov.key_of({'source_path': 'p', 'source_anchor': 'x'}), 'p#x')
        self.assertEqual(ov.key_of({'source_path': 'p', 'source_anchor': None}), 'p')

    def test_apply(self):
        recs = self.records()
        overrides = {
            'novel/a.html': {'set': {'author': '正しい', 'kansou_slug': 'tadashii'}, 'expect': {'author': '誤植'},
                             'note': 'lib1 の誤植'},
            'novel/b.html#p2': {'set': {'author': 'B2'}, 'expect': {'author': 'X'}},
            'novel/c.html': {'set': {'author': 'C'}},
            'novel/none.html': {'set': {'author': 'N'}},
        }
        applied, problems = ov.apply_overrides(recs, overrides, annex_base='https://annex.example.com/')
        self.assertEqual(recs[0]['author'], '正しい')
        self.assertEqual(recs[0]['kansou_annex_url'], 'https://annex.example.com/~ts/kansou/bbs@log_tadashii.cgi')
        self.assertEqual(recs[0]['overrides_applied'][0], {'field': 'author', 'from': '誤植', 'to': '正しい'})
        self.assertEqual(recs[0]['override_note'], 'lib1 の誤植')
        # b は expect が合わないが、適用はする (自己検査が落ちる)
        self.assertEqual([(a['key'], a['already_applied']) for a in applied],
                         [('novel/a.html', False), ('novel/b.html#p2', False)])
        self.assertEqual(recs[1]['author'], 'B2')
        self.assertEqual([(p['key'], p['why']) for p in problems], [
            ('novel/b.html#p2', 'expect 不一致'), ('novel/c.html', 'episode が 2 件 (1 件でなければ適用しない)'),
            ('novel/none.html', 'episode が 0 件 (1 件でなければ適用しない)')])
        # 再実行では expect が set の値と一致するので素通し
        applied2, problems2 = ov.apply_overrides(recs, {'novel/a.html': overrides['novel/a.html']})
        self.assertEqual((applied2[0]['already_applied'], problems2), (True, []))

    def test_unsettable(self):
        applied, problems = ov.apply_overrides(self.records(), {'novel/a.html': {'set': {'episode_id': 'z'}}})
        self.assertEqual((applied, problems[0]['why']), ([], "上書きできない欄 ['episode_id']"))

    def test_selftest_and_report(self):
        recs = self.records()
        applied, problems = ov.apply_overrides(recs, {'novel/none.html': {'set': {'author': 'N'}}})
        c = ov.selftest(applied, problems, {'novel/none.html': {}})
        self.assertFalse(c.ok)
        self.assertEqual([n for n, _, _ in c.results], [
            'episode_overrides.yml の全行が 1 話に当たる', 'expect (上書き前の値) が原本と一致',
            '上書きできない欄を触っていない', '上書きの件数を記録'])
        rep = ov.report_of({'k': {}}, applied, problems, True, c, c.ok)
        self.assertEqual(list(rep), ['generated_by', 'rules', 'episodes_changed', 'episodes_already_applied',
                                     'applied', 'problems', 'episode_id_unchanged', 'selftest', 'selftest_ok'])


if __name__ == '__main__':
    unittest.main()
