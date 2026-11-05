# -*- coding: utf-8 -*-
"""tslib/payloads.py: Gutenberg ブロック・無損失検査・取り下げの範囲 (論理削除)。"""
import unittest

from tslib import mail
from tslib import payloads as p

MD = ('# 題\n\n本文1\n本文2\n\n----\n\n> 引用\n>\n> ----\n\n<img src="a.gif">\n\n'
      '<table><tr><td>a</td></tr>\n</table>\n\n<table><tr><td>開いたまま\n\n![絵](e.gif)\n\n戻る< & x')


def warn():
    return {'missing': [], 'external': [], 'junk': []}


class SrcTest(unittest.TestCase):
    def test_rewrite_srcs(self):
        w = warn()
        out = p.rewrite_srcs('<img src="pic.gif"><img src="../x.jpg"><img src="http://h.example.com/a.gif">'
                             '<img src="cnt.cgi?x"><img SRC="/abs.gif">', 'novel/d', {'novel/d/pic.gif': 'novel/d/Pic.GIF'}, w)
        self.assertEqual(out, '<img src="/assets/annex-img/novel/d/Pic.GIF"><img src="/assets/annex-img/novel/x.jpg">'
                              '<img src="http://h.example.com/a.gif"><img SRC="/abs.gif">')
        self.assertEqual(w, {'missing': ['novel/x.jpg'], 'external': ['http://h.example.com/a.gif', '/abs.gif'],
                             'junk': ['cnt.cgi?x']})

    def test_orig_palette(self):
        got = p.orig_palette('<style>body{background:#000;color:red} a:visited{color:blue}</style>'
                             '<body bgcolor="#123" background="bg.gif">', 'novel/d', {'novel/d/bg.gif': 'novel/d/bg.gif'})
        self.assertEqual(got, '<div class="ts-orig-palette" data-bg="#123" data-fg="red" data-link="#0000ee" '
                              'data-vlink="blue" data-bgimg="/assets/annex-img/novel/d/bg.gif"></div>')
        self.assertEqual(p.orig_palette('', '', {}), '<div class="ts-orig-palette" data-bg="#ffffff" data-fg="#000000" '
                                                     'data-link="#0000ee" data-vlink="#551a8b"></div>')


class BlocksTest(unittest.TestCase):
    def test_md_to_blocks(self):
        stats = p.new_stats()
        blocks = p.md_to_blocks(MD, stats)
        kinds = [b.split(' ', 2)[1].split('\n')[0] for b in blocks]
        self.assertEqual(kinds, ['wp:heading', 'wp:paragraph', 'wp:separator', 'wp:quote', 'wp:image', 'wp:html',
                                 'wp:html', 'wp:image', 'wp:paragraph'])
        self.assertEqual(blocks[0], '<!-- wp:heading -->\n<h2 class="wp-block-heading">題</h2>\n<!-- /wp:heading -->')
        self.assertEqual(blocks[1], '<!-- wp:paragraph -->\n<p>本文1<br>本文2</p>\n<!-- /wp:paragraph -->')
        self.assertIn('<!-- wp:separator -->', blocks[3])               # 引用の中の区切り線
        self.assertEqual(blocks[6], '<!-- wp:html -->\n<table><tr><td>開いたまま\n<!-- /wp:html -->')  # 閉じない table
        self.assertEqual(blocks[7], '<!-- wp:image -->\n<figure class="wp-block-image"><img src="e.gif" alt="絵"></figure>\n<!-- /wp:image -->')
        self.assertEqual(blocks[8], '<!-- wp:paragraph -->\n<p>戻る&lt; &amp; x</p>\n<!-- /wp:paragraph -->')
        self.assertEqual(stats, {'paragraph': 2, 'separator': 1, 'quote': 1, 'heading': 1, 'image': 2, 'table': 2})

    def test_heading_levels(self):
        self.assertIn('<h2 ', p.b_heading(1, 'x'))
        self.assertIn('{"level":4}', p.b_heading(3, 'x'))
        self.assertIn('<h6 ', p.b_heading(6, 'x'))

    def test_block_builders(self):
        # ブロックの字面は生成物 (payloads/) のバイト一致に懸かる
        self.assertEqual(p.b_para('a'), '<!-- wp:paragraph -->\n<p>a</p>\n<!-- /wp:paragraph -->')
        self.assertEqual(p.b_sep(), '<!-- wp:separator -->\n<hr class="wp-block-separator has-alpha-channel-opacity"/>\n'
                                    '<!-- /wp:separator -->')
        self.assertEqual(p.b_heading(0, 'x'), '<!-- wp:heading -->\n<h2 class="wp-block-heading">x</h2>\n<!-- /wp:heading -->')
        self.assertEqual(p.b_heading(3, 'x'),
                         '<!-- wp:heading {"level":4} -->\n<h4 class="wp-block-heading">x</h4>\n<!-- /wp:heading -->')
        self.assertIn('<h6 ', p.b_heading(9, 'x'))                     # h6 で頭打ち
        self.assertEqual(p.b_quote(['a', '--']),
                         '<!-- wp:quote -->\n<blockquote class="wp-block-quote">\n' + p.b_para('a') + '\n' + p.b_sep()
                         + '\n</blockquote>\n<!-- /wp:quote -->')
        self.assertEqual(p.b_image('<img src="a.gif">'),
                         '<!-- wp:image -->\n<figure class="wp-block-image"><img src="a.gif"></figure>\n<!-- /wp:image -->')
        self.assertEqual(p.b_html('<x>'), '<!-- wp:html -->\n<x>\n<!-- /wp:html -->')

    def test_unbalanced_table(self):
        self.assertFalse(p.unbalanced_table('<table><tr><td>a</td></tr></table>'))
        self.assertFalse(p.unbalanced_table('<TABLE ><TR><TH>a</TH ></TR></table >'))   # 大文字と空白
        self.assertTrue(p.unbalanced_table('<table><tr><td>a'))
        self.assertTrue(p.unbalanced_table('<table><tr><td>a</td></table>'))           # tr だけ閉じない
        self.assertFalse(p.unbalanced_table('<thead><tbody>本文'))                       # \b で thead・tbody は数えない
        self.assertFalse(p.unbalanced_table(''))

    def test_visible_symmetry(self):
        self.assertEqual(p.visible('## 見出し\n> 引用&amp;\n----\n![a](b.gif)', True),
                         p.visible('<p>見出し</p><br>引用&amp;<!--c-->', False))
        # 字面: 見出し・引用の記号・区切り行・画像・タグ・コメント・空白を落とし、実体参照は戻す
        self.assertEqual(p.visible('## 見出し\n> 引用&amp;\n----\n![a](b.gif)', True), '見出し引用&')
        self.assertEqual(p.visible('<p>a<br>--<br>b</p>', False), 'ab')

    def test_payloads(self):
        stats, w, mail_n = p.new_stats(), warn(), {'mailto': 0, 'bare': 0}
        out, ok, eye = p.md_payload('本文 <img src="eyecatch.jpg"> taro@example.com', 'novel/d', {}, stats, w, mail_n)
        self.assertTrue(ok)
        self.assertEqual(eye, ['/assets/annex-img/novel/d/eyecatch.jpg'])
        self.assertEqual(mail_n, {'mailto': 0, 'bare': 0})                    # example は残す
        out, ok = p.text_payload('一段 <b>\n\n二段', p.new_stats(), mail_n)
        self.assertTrue(ok)
        self.assertEqual(out.count('wp:paragraph -->'), 4)
        self.assertIn('&lt;b&gt;', out)
        raw = p.raw_payload('<body text="#fff">', '<p>x</p>', 'novel/d', {}, warn(), mail_n)
        self.assertTrue(raw.startswith('<!-- wp:html -->\n<div class="ts-orig-palette" data-bg="#ffffff" data-fg="#fff"'))
        self.assertTrue(raw.endswith('<p>x</p>\n<!-- /wp:html -->\n'))


class TakedownTest(unittest.TestCase):
    EPISODES = {e['episode_id']: e for e in [
        {'episode_id': 'w1', 'source_path': 'novel/w/1.html'},
        {'episode_id': 'w2', 'source_path': 'novel/w/2.html'},
        {'episode_id': 'a1', 'source_path': 'novel/a/1.html', 'source_anchor': 'P2'},
        {'episode_id': 'a2', 'source_path': 'novel/a/1.html', 'source_anchor': 'p3'},
        {'episode_id': 'g1', 'source_path': 'novel/G/x.gif'},
        {'episode_id': 'g2', 'source_path': 'novel/g/y.gif'},
        {'episode_id': 'u1', 'source_path': 'novel/u/1.html'},
    ]}
    WORKS = [{'work_slug': 'au-w', 'author_slug': 'au', 'episodes': [{'episode_id': 'w1'}, 'w2']},
             {'work_slug': 'un-x', 'author_slug': 'unattributed', 'episodes': ['u1']}]
    AUTHORS = [{'slug': 'AU'}]

    def run_list(self, entries):
        return p.takedown_episodes(self.EPISODES, self.WORKS, self.AUTHORS, {'entries': entries})

    def test_four_kinds(self):
        hit, per = self.run_list([
            {'id': 'S1', 'target': {'kind': 'work', 'value': 'au-w'}},
            {'id': 'S2', 'target': {'kind': 'episode', 'value': 'novel/A/1.html#p2'}},
            {'id': 'S3', 'target': {'kind': 'annex_path', 'value': 'novel/g/*'}},
            {'id': 'S4', 'target': {'kind': 'author', 'value': 'unattributed'}},
            {'id': 'S5', 'target': {'kind': 'author', 'value': 'au'}},
            {'id': 'S6', 'target': {'kind': 'episode', 'value': 'novel/a/1.html'}},
            {'id': 'S7', 'target': {'kind': 'annex_path', 'value': 'novel/g/y.gif'}},
            {'id': 'S8', 'status': 'reinstated', 'target': {'kind': 'work', 'value': 'un-x'}},
        ])
        self.assertEqual(hit, {'w1': ['S1', 'S5'], 'w2': ['S1', 'S5'], 'a1': ['S2', 'S6'], 'a2': ['S6'],
                               'g1': ['S3'], 'g2': ['S3', 'S7'], 'u1': ['S4']})
        self.assertEqual([(e['id'], e['episodes']) for e in per],
                         [('S1', 2), ('S2', 1), ('S3', 2), ('S4', 1), ('S5', 2), ('S6', 2), ('S7', 1)])
        self.assertEqual(list(per[0]), ['id', 'kind', 'value', 'episodes'])

    def test_errors(self):
        with self.assertRaises(p.DenylistError):
            self.run_list([{'id': 'S1', 'target': {'kind': 'bogus', 'value': 'x'}}])
        with self.assertRaises(p.DenylistError):
            self.run_list([{'id': 'S1', 'target': {'kind': 'work', 'value': ''}}])
        with self.assertRaises(p.DenylistError):
            self.run_list([{'id': 'S1', 'target': {'kind': 'annex_path', 'value': 'novel/*/x.gif'}}])

    def test_empty(self):
        self.assertEqual(p.takedown_episodes({}, [], [], None), ({}, []))


class PlanTest(unittest.TestCase):
    def test_plan_order_and_skips(self):
        episodes = {'e1': {'x': 1}, 'r1': {'x': 1}, 't1': {'x': 1}, 'td': {'x': 1}, 'tr': {'x': 1}, 'raw': {'x': 1}}
        reposts = {'r1', 'td', 'tr', 'e1'}
        steps, skipped = p.plan_payloads(['e1', 'raw', 'td'], episodes, ['raw', 'gone', 'tr'],
                                         {'td': ['S1'], 'tr': ['S1']}, reposts.__contains__)
        # md の名前順 → episodes の順の再掲 (md がある e1 は除く) → raw_fallbacks の順 (md と重なれば上書き)
        self.assertEqual(steps, [('e1', 'converted'), ('raw', 'converted'), ('r1', 'converted-text'),
                                 ('raw', 'raw-fallback')])
        # td は md があるので再掲には数えない。tr は md が無く再掲があるので数え、raw でも数える
        self.assertEqual(skipped, {'md': 1, 'repost': 1, 'raw': 1})

    def test_plan_empty(self):
        self.assertEqual(p.plan_payloads([], {}, [], {}, lambda eid: False), ([], {'md': 0, 'repost': 0, 'raw': 0}))


class ReportTest(unittest.TestCase):
    def test_report_keys(self):
        run = p.PayloadRun(written=3, raw_written=1, removed=['t1'], meta={'c': 'converted-text'},
                           eyecatch={'e': ['y', 'x', 'x']})
        run.warn['missing'] += ['b', 'a', 'a']
        rep = p.report_of(run, ['r1', 't1'], {'t1': ['S1']}, [], ['l'])
        self.assertEqual(list(rep), [
            'written', 'mail_redacted', 'mail_leaked', 'converted', 'raw_fallback', 'takedown_skipped',
            'takedown_skipped_with_body', 'takedown_payloads_removed', 'takedown_entries', 'takedown_episodes',
            'blocks', 'invariant_ok', 'invariant_violations', 'images_missing', 'images_external',
            'images_junk_dropped', 'converted_text', 'eyecatch_refs'])
        self.assertEqual((rep['converted'], rep['raw_fallback'], rep['images_missing'], rep['eyecatch_refs']),
                         (2, ['r1'], ['a', 'b'], {'e': ['x', 'y']}))
        self.assertEqual((rep['takedown_payloads_removed'], rep['takedown_skipped_with_body'], rep['mail_redacted']),
                         (1, {'md': 0, 'repost': 0, 'raw': 0}, {'mailto': 0, 'bare': 0}))
        # 既定の累計は呼ぶごとに別の dict
        self.assertIsNot(p.PayloadRun().warn, p.PayloadRun().warn)

    def test_has_leak(self):
        # payload_build の出荷前の検査は tslib/mail.py の has_leak (本文の決まり MAIL_KEEP_RE)
        self.assertFalse(mail.has_leak('taro@example.com'))
        self.assertTrue(mail.has_leak('<a href="mailto:x">'))


if __name__ == '__main__':
    unittest.main()
