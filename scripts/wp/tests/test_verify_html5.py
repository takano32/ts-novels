# -*- coding: utf-8 -*-
"""tslib/verify_html5.py: html5lib による無損失性の検算 (beautifulsoup4 と html5lib があるときだけ)。"""
import unittest

from tests.support import TempDirCase, HAS_BS4

if HAS_BS4:
    from tslib import verify_html5 as vh


@unittest.skipUnless(HAS_BS4, 'beautifulsoup4 / html5lib が無い')
class VerifyHtml5Test(TempDirCase):
    def test_normalize(self):
        self.assertEqual(vh.normalize(' あ <b>\n ｶ'), 'あbｶ')
        self.assertEqual(vh.normalize('か\u3099 き'), 'がき')               # 結合文字の濁点は NFC で 1 字に

    def test_md_text_strips_marks(self):
        text, stripped = vh.md_text('> ----\n## 見出し\n> 引用 <ruby>漢<rt>かん</rt></ruby> <b>x</b>')
        self.assertEqual(text, '見出し\n> 引用 漢かん <b>x</b>')
        self.assertEqual(dict(stripped), {'sep': 1, 'heading': 1, 'kept_html': 1, 'taglike_text': 2})

    def test_looks_like_chrome(self):
        self.assertTrue(vh.looks_like_chrome(''))
        self.assertTrue(vh.looks_like_chrome('戻る'))
        self.assertFalse(vh.looks_like_chrome('本文。'))
        self.assertFalse(vh.looks_like_chrome('あ' * 201))

    def job(self, eid, html, md, anchor=None):
        self.write('novel/%s.html' % eid, html)
        self.write('bodies/%s.md' % eid, md)
        return (self.tmp, self.tmp + '/bodies', eid, 'novel/%s.html' % eid, 'br-para', anchor, [], [])

    def test_verify_results(self):
        r = vh.verify(self.job('m', '<body><a href="i">戻る</a><p>本文です。</p></body>', '本文です。\n'))
        self.assertEqual((r['result'], r['variant'], r['dropped_head']), ('match', 'strict', 2))
        r = vh.verify(self.job('x', '<body><p>本文です。</p></body>', '違う本文です。\n'))
        self.assertEqual((r['result'], r['diverge_at']), ('mismatch', 0))
        r = vh.verify(self.job('a', '<body><p>前の話です。</p><p>本文です。</p></body>', '本文です。\n', anchor='p2'))
        self.assertEqual(r['result'], 'match-anchor')
        r = vh.verify(self.job('s', '<body><p>前置きの地の文。</p><p>本文です。</p></body>', '本文です。\n'))
        self.assertEqual((r['result'], r['head_text']), ('match-but-chrome-suspect', '前置きの地の文。'))
        missing = (self.tmp, self.tmp + '/bodies', 'none', 'novel/none.html', 'br-para', None, [], [])
        self.assertEqual(vh.verify(missing)['result'], 'no-md')

    def test_html5_text_flags(self):
        m = '<title>T</title><p>a</p>'
        self.assertEqual((vh.html5_text(m), vh.html5_text(m, fragment=True)), ('\na\n', 'T\na\n'))
        m = '<p>a</p><form>f</form>'
        self.assertEqual((vh.html5_text(m), vh.html5_text(m, drop_forms=True)), ('\na\nf', '\na\n'))
        m = '<body><noscript>n</noscript><p>a</p></body>'
        self.assertEqual((vh.html5_text(m), vh.html5_text(m, drop_noscript=False)), ('\na\n', 'n\na\n'))
        m = '<iframe><b>x</b></iframe>'                 # HTML5 では iframe の中身は生テキスト
        self.assertEqual((vh.html5_text(m), vh.html5_text(m, unwrap_rawtext=True)), ('<b>x</b>', 'x'))

    def test_compare_ladder(self):
        # strict で合わないとき、梯子の次の解釈で合えばそれを採る (はみ出し 0 で止まる)
        cases = (('noscript', '<body><noscript>本文です。</noscript></body>'),
                 ('no-form', '<body><p>本文です。</p><form>感想を書く。送信。</form></body>'),
                 ('rawtext', '<body><iframe><a title="あ。">本文です。</a></iframe></body>'))
        for variant, raw in cases:
            self.assertEqual(vh.compare(raw, '本文です。\n', None, [], []),
                             {'conv_chars': 5, 'orig_chars': 5, 'variant': variant, 'dropped_head': 0,
                              'dropped_tail': 0, 'result': 'match'}, variant)

    def test_compare_error_and_empty(self):
        self.assertEqual(vh.compare('<body></body>', None, None, [], []),
                         {'result': 'error', 'reason': "AttributeError: 'NoneType' object has no attribute 'split'"})
        self.assertEqual(vh.compare('<body><a>戻る</a></body>', '----\n', None, [], []),
                         {'conv_chars': 0, 'md_markers': {'sep': 1}, 'orig_chars': 2, 'result': 'match-empty'})
        self.assertEqual(vh.compare('<body><p>地の文です。</p></body>', '', None, [], []),
                         {'conv_chars': 0, 'orig_chars': 6, 'result': 'empty-md-suspect', 'head_text': '地の文です。'})

    def test_verify_keys_order(self):
        r = vh.verify(self.job('k', '<body><p>本文です。</p></body>', '本文です。\n'))
        self.assertEqual(list(r), ['episode_id', 'source_path', 'mode', 'conv_chars', 'orig_chars', 'variant',
                                   'dropped_head', 'dropped_tail', 'result'])
        self.write('novel/ns.html', 'x')
        r = vh.verify((self.tmp, self.tmp + '/bodies', 'ns', 'novel/none.html', 'br-para', None, [], []))
        self.assertEqual(r['result'], 'no-md')
        self.write('bodies/ns.md', 'x')
        self.assertEqual(vh.verify((self.tmp, self.tmp + '/bodies', 'ns', 'novel/none.html', 'br-para', None, [], []))
                         ['result'], 'no-source')


class SelectSummarizeTest(unittest.TestCase):
    """select_jobs と summarize は bs4 を使わないが、モジュールの読み込みに合わせて同じ条件で流す。"""

    @unittest.skipUnless(HAS_BS4, 'beautifulsoup4 / html5lib が無い')
    def test_select_jobs(self):
        eps = [{'episode_id': e, 'source_path': sp, 'source_anchor': None} for e, sp in (
            ('a', 'novel/a.html'), ('b', 'novel/b.html'), ('c', 'novel/c.TXT'), ('d', 'https://x/d.html'),
            ('e', ''), ('f', 'novel/f.html'), ('g', 'novel/g.html'))]
        reports = {'a': {'status': 'md', 'mode': 'br-para', 'chrome_removed': [{'text': '戻る'}], 'nav_trimmed': None},
                   'b': {'status': 'md', 'mode': 'plain-text'}, 'c': {'status': 'md'}, 'd': {'status': 'md'},
                   'e': {'status': 'md'}, 'f': {'status': 'raw'}, 'g': {'status': 'md', 'mode': 'pre'}}
        jobs = vh.select_jobs(eps, reports, '/r', '/r/bodies')
        self.assertEqual(jobs, [('/r', '/r/bodies', 'a', 'novel/a.html', 'br-para', None, ['戻る'], []),
                                ('/r', '/r/bodies', 'g', 'novel/g.html', 'pre', None, [], [])])
        self.assertEqual([j[2] for j in vh.select_jobs(eps, reports, '/r', '/r/b', only_id='g')], ['g'])
        self.assertEqual([j[2] for j in vh.select_jobs(eps, reports, '/r', '/r/b', limit=1)], ['a'])
        self.assertEqual(vh.select_jobs(eps, reports, '/r', '/r/b', only_id='b'), [])

    @unittest.skipUnless(HAS_BS4, 'beautifulsoup4 / html5lib が無い')
    def test_summarize(self):
        rows = [{'episode_id': 'z', 'result': 'match', 'variant': 'strict', 'dropped_head': 3, 'dropped_tail': 1},
                {'episode_id': 'y', 'result': 'mismatch', 'variant': 'strict',
                 'md_markers': {'taglike_text': 2}},
                {'episode_id': 'x', 'result': 'no-md'},
                {'episode_id': 'w', 'result': 'match-anchor', 'variant': 'noscript', 'dropped_tail': 9}]
        s = vh.summarize(rows)
        self.assertEqual(list(s), ['checked', 'result', 'match_rate_pct', 'variant', 'dropped_head_max',
                                   'dropped_tail_max', 'taglike_text_episodes', 'findings', 'taglike_text_count'])
        self.assertEqual((s['checked'], s['result'], s['match_rate_pct'], s['variant']),
                         (4, {'match': 1, 'mismatch': 1, 'no-md': 1, 'match-anchor': 1}, 50.0,
                          {'strict': 2, 'noscript': 1}))
        self.assertEqual((s['dropped_head_max'], s['dropped_tail_max']), (3, 9))
        self.assertEqual((s['taglike_text_episodes'], s['taglike_text_count']), ([('y', 2)], 1))
        self.assertEqual([r['episode_id'] for r in s['findings']], ['y', 'x'])      # result・episode_id の順
        self.assertEqual(vh.summarize([])['match_rate_pct'], 0.0)


if __name__ == '__main__':
    unittest.main()
