# -*- coding: utf-8 -*-
"""tslib/bodies.py: 本文 HTML → Markdown と無損失証明。"""
import unittest

from tslib import bodies as b
from tests.support import TempDirCase


class PrecleanTest(unittest.TestCase):
    def test_preclean(self):
        self.assertEqual(b.preclean('<script>x</script>a<!--c--><! 没稿 >b<form>f</form>ち<ゃんと'),
                         'abち&lt;ゃんと')

    def test_extract_body(self):
        self.assertEqual(b.extract_body('<head><title>T</title></head><body><p>本文</p></body>後'), '<p>本文</p>')
        # </body> が無い文書と、末尾の欠けたタグ
        self.assertEqual(b.extract_body('<head><title>T</title></head><body><p>本文</p'), '<p>本文')
        # <body> も無い文書は </head> 以降
        self.assertEqual(b.extract_body('<head><title>T</title></head><p>本文</p'), '<p>本文')
        # head/body をまたぐ <style> は先に落とすので CSS は本文に紛れない
        self.assertEqual(b.extract_body('<head><title>T</title><style>\n</head><body>s{}</style><p>本文</p></body>x'),
                         '<head><title>T</title><p>本文</p></body>x')

    def test_normalize_chars_two_paths(self):
        self.assertEqual(b.normalize_chars('<b>あ&amp; い</b>&lt;x&gt;'), 'あ&いx')
        # 変換側は温存タグだけを落とす。山括弧で囲んだ地の文 (<右図>) は文字として残る
        self.assertEqual(b.normalize_chars('<ruby>漢<rt>かん</rt></ruby> <右図>', already_unescaped=True), '漢かん右図')


class ChromeTest(unittest.TestCase):
    def test_strip_chrome(self):
        body = '<a href="i.html">戻る</a><hr>題名 作者<hr><p>本文です。</p><hr><a href="i.html">戻る</a>'
        region, removed = b.strip_chrome(body)
        self.assertEqual(region, '<p>本文です。</p><hr>')
        self.assertEqual(removed, [('head', '<a href="i.html">戻る</a><hr>題名 作者<hr>'),
                                   ('tail', '<a href="i.html">戻る</a>')])
        self.assertEqual(b.strip_chrome('<p>本文だけ</p>'), ('<p>本文だけ</p>', []))

    def test_chrome_is_safe(self):
        self.assertTrue(b.chrome_is_safe('<a>戻る</a> | TOP'))
        self.assertFalse(b.chrome_is_safe('これは本文です。'))
        self.assertFalse(b.chrome_is_safe('戻る' * 101))

    def test_nav_lines(self):
        self.assertTrue(b.is_nav_line('<< 戻る'))
        self.assertFalse(b.is_nav_line('。'))
        self.assertFalse(b.is_nav_line('本文の行です'))
        self.assertEqual(b.trim_nav_lines('\n戻る\n本文\n----\n次へ\n'), ('本文\n', [('head', '戻る'), ('tail', '次へ')]))


class MarkdownTest(unittest.TestCase):
    def test_table_kind(self):
        self.assertEqual(b.table_kind('<table>' + '<td>a</td>' * 6 + '</table>'), 'data')
        self.assertEqual(b.table_kind('<table><td>' + '長' * 30 + '</td></table>'), 'layout')

    def test_hardwrap(self):
        lines = ['これは物理的に折り返された文章の一行目であり続きが'] * 31
        self.assertTrue(b.is_hardwrapped(lines))
        self.assertFalse(b.is_hardwrapped(lines[:30]))
        self.assertEqual(b.reflow_hardwrap(['あいう', 'えお。', '', '「か」', 'き']), ['あいうえお。', '', '「か」', 'き'])
        self.assertEqual(b.paragraphs_of(['a', 'b', '', '', 'c']), ['a\nb', 'c'])

    def test_to_markdown_marks_and_kept_html(self):
        md, plain, mode, notes = b.to_markdown(
            '<p>あ<ruby>漢<rt>かん</rt></ruby></p><hr><blockquote>引用\n二</blockquote><h2>見出し</h2>'
            '<img src="a.gif"> &lt;x&gt;')
        self.assertEqual(md, 'あ<ruby>漢<rt>かん</rt></ruby>\n\n----\n\n> 引用\n> 二\n\n## 見出し\n\n<img src="a.gif"> &lt;x&gt;\n')
        self.assertEqual(plain, 'あ<ruby>漢<rt>かん</rt></ruby>\n\n\n\n引用\n二\n\n見出し\n\n<img src="a.gif"> <x>')
        self.assertEqual(mode, 'br-para')
        self.assertEqual(b.to_markdown('<p> </p>')[2], 'empty')

    def test_split_by_anchor_and_plain_text(self):
        self.assertEqual(b.split_by_anchor('<a name="p1">一</a><a name=p2>二', '#P2'), ('<a name=p2>二', True))
        self.assertEqual(b.split_by_anchor('x', 'z'), ('x', False))
        self.assertEqual(b.plain_text_md('\n一段\n\n\n二段\n続き\n'), '一段\n\n二段\n続き\n')

    def test_convert_html(self):
        ok = b.convert_html('<body><p>本文です。</p></body>')
        self.assertEqual((ok['status'], ok['invariant'], ok['md']), ('md', 'ok', '本文です。\n'))
        self.assertEqual(list(ok), ['mode', 'notes', 'chrome_removed', 'chars', 'status', 'invariant', 'md', 'nav_trimmed'])
        bad = b.convert_html('<body><table>' + '<td>&lt;a&gt;</td>' * 6 + '</table></body>')
        self.assertEqual((bad['status'], bad['invariant'], bad['reason']),
                         ('raw-fallback', 'mismatch', '不変量違反 orig=6 conv=0 分岐=0'))
        self.assertEqual(b.convert_html('<body><p>本文</p></body>', anchor='zz')['mode'], 'anchor-split-failed')
        self.assertEqual(b.convert_html('<body> </body>')['mode'], 'empty')


class EpisodeTest(TempDirCase):
    def ep(self, sp, **kw):
        d = {'episode_id': 'e', 'source_path': sp, 'corpus': 'honkan', 'title': '題'}
        d.update(kw)
        return d

    def test_routes(self):
        self.write('reposts/e.txt', '一段\n\n二段')
        self.write('novel/p.gif', b'GIF89a')
        self.write('novel/a.html', '<body><p>本文です。</p></body>')
        self.write('novel/t.txt', 'テキスト\n\n本文'.encode('cp932'))
        cases = [
            (self.ep('novel/a.html', body_text_path='reposts/e.txt'), ('md', 'plain-text')),
            (self.ep('novel/p.gif'), ('md', 'image-work')),
            (self.ep('novel/q.gif'), ('no-source', None)),
            (self.ep('http://www.example.com/x.html'), ('stub', 'external-link')),
            (self.ep('novel/none.html'), ('no-source', None)),
            (self.ep(''), ('no-source', None)),
            (self.ep('novel/t.txt'), ('md', 'plain-text')),
            (self.ep('novel/a.html'), ('md', 'br-para')),
        ]
        for ep, want in cases:
            with self.subTest(ep=ep['source_path']):
                r = b.convert_episode(ep, self.tmp)
                self.assertEqual((r['status'], r.get('mode')), want)
        self.assertEqual(b.convert_episode(self.ep('novel/p.gif'), self.tmp)['md'], '![題](p.gif)\n')
        self.assertEqual(b.convert_episode(self.ep('novel/t.txt'), self.tmp)['source_encoding'], 'cp932')

    def test_convert_safely(self):
        r = b.convert_safely({'episode_id': 'e', 'source_path': 'x', 'body_text_path': 1}, self.tmp)
        self.assertEqual((r['status'], r['mode']), ('raw-fallback', 'exception'))
        self.assertTrue(r['reason'].startswith('TypeError'))

    def test_summarize(self):
        rows = [{'status': 'md', 'mode': 'br-para'}, {'status': 'raw-fallback', 'mode': 'empty'},
                {'status': 'stub', 'mode': 'external-link'}]
        self.assertEqual(b.summarize(rows, 3), {
            'episodes': 3, 'status': {'md': 1, 'raw-fallback': 1, 'stub': 1},
            'modes': {'br-para': 1, 'empty': 1, 'external-link': 1}, 'convertible': 2, 'md_rate_pct': 50.0,
            'acceptance': {'>=85% of convertible': False, 'invariant violations among md': 0}})


if __name__ == '__main__':
    unittest.main()
