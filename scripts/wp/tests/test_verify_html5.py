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


if __name__ == '__main__':
    unittest.main()
