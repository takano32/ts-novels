# -*- coding: utf-8 -*-
"""tslib/docs.py: 運営文書・ギャラリー・解題の ts_doc ペイロード。"""
import unittest
from pathlib import Path

from tslib import docs
from tslib import mail
from tslib.mail import REDACTED
from tests.support import TempDirCase

ANNEX = 'https://annex.example.com/'


class LinkTest(unittest.TestCase):
    def test_doc_slug(self):
        self.assertEqual(docs.doc_slug('columns', 'columns/Col_01.HTML'), 'columns-col-01')
        self.assertEqual(docs.doc_slug('dialy', 'dialy/日誌.html'), 'dialy-index')

    def test_rewrite_links(self):
        src = ('<a href="../lib1.html">相対</a><img src="img/a.gif"><a href="http://x.example.com/">外</a>'
               '<a href="#top">上</a><a href=\'mailto:taro@example.com\'>m</a><a href="//cdn.example.com/x">p</a>')
        self.assertEqual(docs.rewrite_links(src, 'columns', ANNEX), (
            '<a href="https://annex.example.com/lib1.html">相対</a><img src="https://annex.example.com/columns/img/a.gif">'
            '<a href="http://x.example.com/">外</a><a href="#top">上</a><a href=\'#\'>m</a>'
            '<a href="//cdn.example.com/x">p</a>'))

    def test_strip_mailto_text_uses_docs_keep(self):
        # 運営文書の決まり (MAIL_KEEP_DOCS_RE) で伏せる。試験のアドレスは example.com の作り物だけなので、
        # 伏せる側は残す語の判定 (example・foo@ を残す) が docs 用であることを見る
        self.assertEqual(docs.strip_mailto_text('foo@example.com と taro@example.com'),
                         'foo@example.com と taro@example.com')
        self.assertEqual(docs.redact_bare('x foo@example.com', None), 'x ' + REDACTED)
        self.assertIs(docs.MAIL_KEEP_DOCS_RE, mail.MAIL_KEEP_DOCS_RE)

    def test_title_of(self):
        self.assertEqual(docs.title_of('<TITLE>\n 題\n 名 </TITLE>', 'fb'), '題 名')
        self.assertEqual(docs.title_of('<title> </title>', 'fb'), 'fb')
        self.assertEqual(docs.esc('<a href="x">&'), '&lt;a href=&quot;x&quot;&gt;&amp;')
        self.assertEqual(docs.wrap('x'), '<!-- wp:html -->\nx\n<!-- /wp:html -->\n')


class RenderTest(TempDirCase):
    def test_render_doc(self):
        f = Path(self.write('columns/c1.html', '<html><head><title>コラム1</title></head>'
                                               '<body><a href="../a.html">a</a></body></html>'))
        slug, payload, entry = docs.render_doc('columns', 'コラム', f, self.tmp, ANNEX)
        self.assertEqual(slug, 'columns-c1')
        self.assertEqual(payload, '<!-- wp:html -->\n<a href="https://annex.example.com/a.html">a</a>\n<!-- /wp:html -->\n')
        self.assertEqual(entry, {'title': 'コラム1', 'section': 'columns', 'section_label': 'コラム',
                                 'source_path': 'columns/c1.html'})

    def test_doc_sources(self):
        self.write('dialy/b.html', '')
        self.write('dialy/a.html', '')
        self.write('dialy/z.txt', '')
        got = {s: [p.name for p in files] for s, _, files in docs.doc_sources(self.tmp)}
        self.assertEqual(got['dialy'], ['a.html', 'b.html'])
        self.assertEqual(got['columns'], [])

    def test_render_gallery(self):
        self.write('~yays/gallery/art01.html', '<p>『夏の絵』</p>絵師：<b>画家A</b>\n<img src="cg/cg_01.jpg">'
                                                '<a href="story/s1.html">献呈</a>')
        self.write('~yays/gallery/art02.html', '<p>画像なし</p>')
        self.write('~yays/gallery/album/images/20020102a.jpg', b'x')
        all_cg = {'cg/cg_01.jpg', 'cg/cg_01b.jpg', 'cg/cg_09.jpg'}
        payload, entry, n = docs.render_gallery(self.tmp, all_cg, ANNEX)
        self.assertEqual(n, 4)
        self.assertIn('<h2>夏の絵</h2>', payload)
        self.assertIn('<p class="ts-gallery-artist">絵師: 画家A</p>', payload)
        self.assertIn('<figcaption>別版 (cg_01b.jpg)</figcaption>', payload)
        self.assertIn('<h2>そのほかの収蔵 CG</h2>', payload)
        self.assertIn('<a href="https://annex.example.com/~yays/gallery/story/s1.html">献呈 (原本アーカイブ)</a>', payload)
        self.assertIn('alt="投稿アルバム 2002-01-02"', payload)
        self.assertEqual(entry, {'title': 'ギャラリー少年少女', 'section': 'gallery', 'section_label': 'ギャラリー',
                                 'source_path': '~yays/gallery/'})

    def test_render_handwritten(self):
        f = self.write('content/docs/note1.html', '<!-- title: 解題その1 -->\n<p>本文</p>\n')
        self.assertEqual(docs.render_handwritten(f), (
            'note1', '<!-- wp:html -->\n<p>本文</p>\n<!-- /wp:html -->\n',
            {'title': '解題その1', 'section': 'notes', 'section_label': '解題', 'source_path': 'content/docs/note1.html'}))
        self.assertEqual([p.name for p in docs.handwritten_files(self.tmp)], ['note1.html'])


if __name__ == '__main__':
    unittest.main()
