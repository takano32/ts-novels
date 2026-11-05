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


class SampleImageSummaryTest(unittest.TestCase):
    def test_sample_episodes(self):
        import random
        ids = ['e%02d' % i for i in range(30)]
        state = random.getstate()
        got = q.sample_episodes(list(reversed(ids)), 5, 20260830)
        self.assertEqual(random.getstate(), state)               # 大域の乱数は変えない
        random.seed(20260830)
        self.assertEqual(got, random.sample(ids, 5))             # 旧版の random.seed + random.sample と同じ並び
        random.setstate(state)
        self.assertEqual(len(set(got)), 5)

    def test_image(self):
        self.assertTrue(q.is_image_source('novel/a/b.JPG'))
        self.assertTrue(q.is_image_source('novel/a/b.jpeg'))
        self.assertFalse(q.is_image_source('novel/a/b.html'))
        self.assertFalse(q.is_image_source(None))
        self.assertEqual(q.judge_image('<img src="/assets/annex-img/novel/a/b.gif">', 'novel/a/b.gif'),
                         ('ok-image', [{'kind': 'image-entry', 'expect': '/assets/annex-img/novel/a/b.gif',
                                        'found': True}]))
        self.assertEqual(q.judge_image('<p>x</p>', 'novel/a/b.gif')[0], 'ng-image')

    def test_source_text(self):
        self.assertEqual(q.source_text('<p>a</p>', 'reposts/x.txt'), '<p>a</p>')
        self.assertEqual(q.source_text('<p>a</p>', 'novel/x.html'), q.visible_text('<p>a</p>'))

    def test_summarize(self):
        res = [{'verdict': 'ok'}, {'verdict': 'ng-missing'}, {'verdict': 'error'}, {'verdict': 'ok'},
               {'verdict': 'ok-image'}, {'verdict': 'ng-image'}]
        s = q.summarize(res, 7)
        self.assertEqual(list(s), ['seed', 'n', 'counts', 'problem_rate_pct'])
        self.assertEqual(s, {'seed': 7, 'n': 6, 'counts': {'ok': 2, 'ng-missing': 1, 'error': 1, 'ok-image': 1,
                                                           'ng-image': 1}, 'problem_rate_pct': 50.0})
        self.assertEqual(list(s['counts']), ['ok', 'ng-missing', 'error', 'ok-image', 'ng-image'])
        self.assertEqual(q.summarize([], 1), {'seed': 1, 'n': 0, 'counts': {}, 'problem_rate_pct': 0.0})


if __name__ == '__main__':
    unittest.main()
