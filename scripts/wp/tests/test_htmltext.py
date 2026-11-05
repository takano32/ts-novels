# -*- coding: utf-8 -*-
"""tslib/htmltext.py: 表示テキスト化の 3 流儀とアンカー走査。"""
import unittest

from tslib import htmltext as ht


class TextStylesTest(unittest.TestCase):
    FRAG = '<b>あ</b><BR>い&amp;う<td>x</td><td>y</td>'

    def test_three_styles_differ(self):
        self.assertEqual(ht.cell_text(self.FRAG), 'あ\nい&うxy')
        self.assertEqual(ht.cell_text(self.FRAG, keep_breaks=False), 'あ い&うxy')
        self.assertEqual(ht.inline_text(self.FRAG), 'あい&うxy')
        self.assertEqual(ht.spaced_text(self.FRAG), 'あ い&う x y')

    def test_none_and_empty(self):
        for f in (ht.cell_text, ht.inline_text, ht.spaced_text):
            self.assertIsNone(f(None))
            self.assertIsNone(f('<br>  '))

    def test_cell_text_strips_ideographic_space_and_crlf(self):
        self.assertEqual(ht.cell_text('　題名　'), '題名')
        self.assertEqual(ht.cell_text(' a \r\n b \r c '), 'a\nb\nc')

    def test_strip_tags(self):
        self.assertEqual(ht.strip_tags('<a href="x">t</a>'), 't')


class AnchorsTest(unittest.TestCase):
    def test_three_href_forms(self):
        src = ('<a href="d.html">D</a><A HREF=\'s.html\'>S</A>'
               '<a target=_top href=b.html>B</a>')
        got = [(a['href'], a['label']) for a in ht.anchors(src)]
        self.assertEqual(got, [('d.html', 'D'), ('s.html', 'S'), ('b.html', 'B')])

    def test_broken_nesting(self):
        # 閉じない <A> の中に次の <A> がある (原本に実在する壊れ方)。落ちずに拾える分を拾う
        got = ht.anchors('<A HREF="a.html">A<A HREF="b.html">B</A>')
        self.assertEqual(len(got), 1)
        self.assertEqual(got[0]['href'], 'a.html')

    def test_entity_and_span(self):
        a = ht.anchors('xx<a href=" x.html?a=1&amp;b=2 ">L<br>M</a>')[0]
        self.assertEqual(a['href'], 'x.html?a=1&b=2')
        self.assertEqual(a['label'], 'L M')
        self.assertEqual(a['span'][0], 2)
        self.assertEqual(ht.anchors(None), [])

    def test_href_of(self):
        self.assertEqual(ht.href_of(('', '', ' a&amp;b ', 'x')), 'a&b')
        self.assertEqual(ht.href_of(('d', None, None, 'x')), 'd')
        self.assertEqual(ht.href_of((None, None, None, 'x')), '')

    def test_norm_path(self):
        self.assertEqual(ht.norm_path('./././novel/a.html#top'), 'novel/a.html')
        self.assertEqual(ht.norm_path('a&amp;b.html'), 'a&b.html')
        self.assertIsNone(ht.norm_path('http://example.com/a.html'))
        self.assertIsNone(ht.norm_path('#only'))
        self.assertIsNone(ht.norm_path(None))
        self.assertTrue(ht.is_absolute_url('https://x'))
        self.assertFalse(ht.is_absolute_url('mailto:x'))


if __name__ == '__main__':
    unittest.main()
