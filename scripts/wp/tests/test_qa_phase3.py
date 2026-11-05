# -*- coding: utf-8 -*-
"""tslib/qa_phase3.py: 本番ページと原本の併読 QA の抽出と判定 (本番には接続しない)。"""
import unittest

from tslib import qa_phase3 as q


class ExtractTest(unittest.TestCase):
    def test_decode_bytes_charset(self):
        self.assertEqual(q.decode_bytes('<meta charset="shift_jis">日本'.encode('cp932')), '<meta charset="shift_jis">日本')
        self.assertEqual(q.decode_bytes('<meta charset=euc-jp>漢字'.encode('euc_jp')), '<meta charset=euc-jp>漢字')
        self.assertEqual(q.decode_bytes('ふつう'.encode('utf-8')), 'ふつう')

    def test_preclean(self):
        self.assertEqual(q.preclean('<!-- c -->a<!　没稿!!>b ち<ゃんと <FONTCOLOR="#FFD700">x</a>'),
                         'ab ち&lt;ゃんと <FONTCOLOR="#FFD700">x</a>')

    def test_visible_text(self):
        self.assertEqual(q.visible_text('<p>a<script>x</script><ruby>漢<rt>かん</rt></ruby></p>', skip_rt=True), 'a漢')
        self.assertEqual(q.visible_text('<title>T</title><p>a<ruby>漢<rt>かん</rt></ruby></p>'), 'a漢かん')

    def test_squeeze_and_canon(self):
        self.assertEqual(q.squeeze(' あ\n　い​う '), 'あいう')
        self.assertEqual(q.canon_punct('…—“x”‘y’--...😀'), '...-"x"\'y\'-...')

    def test_page_body_html(self):
        self.assertEqual(q.page_body_html('<div class="entry wp-block-post-content x">本文</div><footer>'), '本文</div>')
        self.assertIsNone(q.page_body_html('x'))

    def test_load_allowed(self):
        self.assertEqual(q.load_allowed('e', {'e': {'nav_trimmed': [{'text': '戻 る'}], 'chrome_removed': "['目次']"}}),
                         ['戻る', '目次'])
        self.assertEqual(q.load_allowed('z', {}), [])


class ClassifyTest(unittest.TestCase):
    def test_verdicts(self):
        self.assertEqual(q.classify('前置き本文です', '本文です', []), ('ok', []))
        verdict, issues = q.classify('あ' * 20 + '本文です', '本文です', [])
        self.assertEqual((verdict, issues[0]['kind'], issues[0]['where']), ('ok-trimmed', 'trimmed', 'head'))
        self.assertEqual(q.classify('abcdefghijklmnopqrstuvwxyz', 'abcdefghijklmXXXXXXXXXXXXXXXXnopqrstuvwxyz', []),
                         ('ng-extra', [{'kind': 'extra', 'len': 16, 'sample': 'XXXXXXXXXXXXXXXX'}]))
        self.assertEqual(q.classify('abcdefghijklmnopqrstuvwxyz0123456789', 'abcdefghijkl0123456789', []),
                         ('ng-missing', [{'kind': 'missing', 'len': 14, 'sample': 'mnopqrstuvwxyz'}]))
        self.assertEqual(q.classify('abc', '', []), ('ng-missing', [{'kind': 'empty-body'}]))
        self.assertEqual(q.classify('abcdefgh', 'hgfedcba', [])[0], 'ng-order')

    def test_covered_by_allowed(self):
        self.assertTrue(q.covered_by_allowed('戻る目次ナビ', []))
        self.assertFalse(q.covered_by_allowed('これは本文の長い文章ですよね', []))
        self.assertTrue(q.covered_by_allowed('これは本文の長い文章ですよね', ['これは本文の長い文章']))

    def test_judge_texturize(self):
        self.assertEqual(q.judge('本文…です', '本文...です', [])[0], 'ok-texturize')
        self.assertEqual(q.judge('本文です', '本文です', []), ('ok', []))


if __name__ == '__main__':
    unittest.main()
